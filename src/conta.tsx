import Constants, { ExecutionEnvironment } from 'expo-constants';
import * as Linking from 'expo-linking';
import * as WebBrowser from 'expo-web-browser';
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { AppState } from 'react-native';

import { chamar, definirToken, ErroApi, type Usuario } from './api/cliente';
import { Armazenamento } from './armazenamento';

// A conta só é necessária para avaliar, comentar e salvar profissionais. Navegar no app não pede login.
// Entrar é só pelo Google, sem depender de o celular devolver um link para o app (isso varia entre Expo Go, APK e
// iPhone): o app pede uma entrada ao servidor (api/google.php, acao=iniciar), abre o endereço no navegador e, enquanto
// a pessoa entra no Google, pergunta ao servidor a cada 1,5 s (acao=verificar) até receber a sessão. Aí fecha o navegador.
const CHAVE_TOKEN = 'token';
const CHAVE_USUARIO = 'usuario';

interface ContextoConta {
  usuario: Usuario | null;
  /** Abre o Google. true = entrou; false = a pessoa fechou ou cancelou. Erro = algo deu errado (mensagem pronta). */
  entrarComGoogle: () => Promise<boolean>;
  sair: () => Promise<void>;
  excluirConta: () => Promise<void>;
}

const Contexto = createContext<ContextoConta | null>(null);

/**
 * Para onde a página final do navegador tenta voltar (só para fechar o navegador mais rápido; o login não depende
 * disso): no Expo Go, o endereço do próprio Expo Go (exp://.../--/google); no APK, achafacil:///google.
 */
function enderecoDeVolta() {
  // Expo Go: exp://<servidor de desenvolvimento>/--/google (hostUri é sempre informado pelo Expo Go)
  const noExpoGo = Constants.executionEnvironment === ExecutionEnvironment.StoreClient || String(Constants.appOwnership) === 'expo';
  const servidor = Constants.expoConfig?.hostUri;
  if (noExpoGo && servidor) return `exp://${servidor}/--/google`;
  return Linking.createURL('google');
}

type Sessao = { token: string; usuario: Usuario };
type Verificacao = { pendente?: boolean; token?: string; usuario?: Usuario };

const esperar = (ms: number) => new Promise<void>((pronto) => setTimeout(pronto, ms));

/** Fecha o navegador do login (cada sistema tem o seu jeito; o que não existir, ignora). */
function fecharNavegador() {
  try {
    WebBrowser.dismissAuthSession();
  } catch {
    // nada a fazer
  }
  WebBrowser.dismissBrowser().catch(() => {});
}

function lerSalvo(): Usuario | null {
  const t = Armazenamento.ler(CHAVE_TOKEN);
  const u = Armazenamento.ler(CHAVE_USUARIO);
  if (!t || !u) return null;
  definirToken(t);
  try {
    return JSON.parse(u) as Usuario;
  } catch {
    return null;
  }
}

export function ProvedorConta({ children }: { children: ReactNode }) {
  const [usuario, setUsuario] = useState<Usuario | null>(lerSalvo);
  // Entrada pelo Google em andamento (a chave só este app conhece). Guardada aqui para a conferência de quando o
  // app volta para a frente (no Android, enquanto o navegador está na frente, os relógios do app param).
  const pendente = useRef<{ chave: string; ate: number } | null>(null);
  // chave da última entrada concluída pela conferência ao voltar (para a tela Entrar não mostrar erro falso)
  const concluida = useRef<string | null>(null);

  const guardar = useCallback((token: string | null, u: Usuario | null) => {
    definirToken(token);
    if (token && u) {
      Armazenamento.gravar(CHAVE_TOKEN, token);
      Armazenamento.gravar(CHAVE_USUARIO, JSON.stringify(u));
    } else {
      Armazenamento.apagar(CHAVE_TOKEN);
      Armazenamento.apagar(CHAVE_USUARIO);
    }
    setUsuario(u);
  }, []);

  // voltou para o app com uma entrada pelo Google pendente: pergunta ao servidor se terminou
  useEffect(() => {
    const assinatura = AppState.addEventListener('change', (agora) => {
      const p = pendente.current;
      if (agora !== 'active' || !p || Date.now() > p.ate) return;
      chamar<Verificacao>('google.php', 'POST', { acao: 'verificar', chave: p.chave })
        .then((r) => {
          if (r.token && r.usuario && pendente.current?.chave === p.chave) {
            pendente.current = null;
            concluida.current = p.chave;
            guardar(r.token, r.usuario);
          }
        })
        .catch((e: unknown) => {
          if (e instanceof ErroApi && e.status === 404 && pendente.current?.chave === p.chave) pendente.current = null;
        });
    });
    return () => assinatura.remove();
  }, [guardar]);

  const valor = useMemo<ContextoConta>(
    () => ({
      usuario,
      async entrarComGoogle() {
        const volta = enderecoDeVolta();
        // ambiente: de onde o app está rodando (Expo Go, APK...), só para o registro do servidor ajudar a achar problemas
        const ambiente = {
          execucao: String(Constants.executionEnvironment),
          dono: String(Constants.appOwnership),
          linkingUri: Constants.linkingUri ?? null,
          hostUri: Constants.expoConfig?.hostUri ?? null,
          versao: Constants.expoConfig?.version ?? null,
          codigoLogin: 3,
        };
        const { endereco, chave } = await chamar<{ endereco: string; chave: string }>('google.php', 'POST', { acao: 'iniciar', volta, ambiente });
        pendente.current = { chave, ate: Date.now() + 15 * 60 * 1000 };
        const situacao: { sessao: Sessao | null; falhou: boolean; aberto: boolean } = { sessao: null, falhou: false, aberto: true };

        const verificar = async () => {
          try {
            const r = await chamar<Verificacao>('google.php', 'POST', { acao: 'verificar', chave });
            if (r.token && r.usuario) situacao.sessao = { token: r.token, usuario: r.usuario };
          } catch (e) {
            // 404 = não deu certo no Google ou expirou; outros erros (internet piscou) tentam de novo
            if (e instanceof ErroApi && e.status === 404) situacao.falhou = true;
          }
        };

        // enquanto o navegador está aberto, pergunta ao servidor; quando terminar, fecha o navegador
        const vigia = (async () => {
          while (situacao.aberto && !situacao.sessao && !situacao.falhou) {
            await esperar(1500);
            if (!situacao.aberto) break;
            await verificar();
          }
          if (situacao.sessao || situacao.falhou) fecharNavegador();
        })();

        await WebBrowser.openAuthSessionAsync(endereco, volta);
        situacao.aberto = false;
        await vigia;
        // o navegador fechou (voltou ao app ou a pessoa fechou): confere mais um pouco, o servidor pode estar terminando
        for (let i = 0; i < 3 && !situacao.sessao && !situacao.falhou; i++) {
          await verificar();
          if (!situacao.sessao && !situacao.falhou) await esperar(700);
        }

        if (situacao.sessao) {
          if (pendente.current?.chave === chave) pendente.current = null;
          guardar(situacao.sessao.token, situacao.sessao.usuario);
          return true;
        }
        // a conferência ao voltar para o app chegou primeiro e já entrou com esta chave
        if (concluida.current === chave) return true;
        if (situacao.falhou) {
          if (pendente.current?.chave === chave) pendente.current = null;
          throw new Error('Não foi possível entrar com o Google. Tente de novo.');
        }
        // continua pendente: se a pessoa terminar no navegador depois, a conferência ao voltar para o app pega
        return false;
      },
      async sair() {
        await chamar('sair.php', 'POST').catch(() => {});
        guardar(null, null);
      },
      async excluirConta() {
        await chamar('eu.php', 'DELETE');
        guardar(null, null);
      },
    }),
    [usuario, guardar],
  );

  return <Contexto.Provider value={valor}>{children}</Contexto.Provider>;
}

export function useConta() {
  const c = useContext(Contexto);
  if (!c) throw new Error('useConta fora do ProvedorConta');
  return c;
}
