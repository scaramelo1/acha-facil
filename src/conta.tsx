import * as Linking from 'expo-linking';
import * as WebBrowser from 'expo-web-browser';
import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react';

import { chamar, definirToken, URL_GOOGLE, type Usuario } from './api/cliente';
import { Armazenamento } from './armazenamento';

// A conta só é necessária para avaliar, comentar e salvar profissionais. Navegar no app não pede login.
// Entrar é só pelo Google: o app abre a página do servidor, que leva ao Google e volta com um código de uso único
// (achafacil://google?codigo=...); o app troca o código pela sessão em api/google.php.
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
        // achafacil://google no APK; exp://.../--/google no Expo Go
        const volta = Linking.createURL('google');
        const r = await WebBrowser.openAuthSessionAsync(`${URL_GOOGLE}?volta=${encodeURIComponent(volta)}`, volta);
        if (r.type !== 'success') return false;
        const { queryParams } = Linking.parse(r.url);
        const erro = queryParams?.erro;
        if (typeof erro === 'string' && erro) throw new Error(erro);
        const codigo = queryParams?.codigo;
        if (typeof codigo !== 'string' || !codigo) throw new Error('O Google não confirmou a entrada. Tente de novo.');
        const s = await chamar<{ token: string; usuario: Usuario }>('google.php', 'POST', { codigo });
        guardar(s.token, s.usuario);
        return true;
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
