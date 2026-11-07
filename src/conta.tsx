import Constants, { ExecutionEnvironment } from 'expo-constants';
import * as Linking from 'expo-linking';
import * as WebBrowser from 'expo-web-browser';
import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react';

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
  const uri = Constants.linkingUri;
  if (Constants.executionEnvironment === ExecutionEnvironment.StoreClient && uri && uri.startsWith('exp')) {
    const base = uri.includes('/--/') ? uri : uri.replace(/\/?$/, '/--/');
    return `${base.replace(/\/?$/, '/')}google`;
  }
  return Linking.createURL('google');
}

type Sessao = { token: string; usuario: Usuario };
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

  const valor = useMemo<ContextoConta>(
    () => ({
      usuario,
      async entrarComGoogle() {
        const volta = enderecoDeVolta();
        const { endereco, chave } = await chamar<{ endereco: string; chave: string }>('google.php', 'POST', { acao: 'iniciar', volta });
        const situacao: { sessao: Sessao | null; falhou: boolean; aberto: boolean } = { sessao: null, falhou: false, aberto: true };

        const verificar = async () => {
          try {
            const r = await chamar<{ pendente?: boolean; token?: string; usuario?: Usuario }>('google.php', 'POST', { acao: 'verificar', chave });
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
          guardar(situacao.sessao.token, situacao.sessao.usuario);
          return true;
        }
        if (situacao.falhou) throw new Error('Não foi possível entrar com o Google. Tente de novo.');
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
