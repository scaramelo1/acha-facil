import Storage from 'expo-sqlite/kv-store';
import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react';

import { chamar, definirToken, type Usuario } from './api/cliente';

// A conta só é necessária para avaliar, comentar e salvar profissionais. Navegar no app não pede login.
const CHAVE_TOKEN = 'token';
const CHAVE_USUARIO = 'usuario';

interface ContextoConta {
  usuario: Usuario | null;
  entrar: (email: string, senha: string) => Promise<void>;
  criarConta: (nome: string, email: string, senha: string) => Promise<void>;
  sair: () => Promise<void>;
  excluirConta: () => Promise<void>;
}

const Contexto = createContext<ContextoConta | null>(null);

function lerSalvo(): Usuario | null {
  const t = Storage.getItemSync(CHAVE_TOKEN);
  const u = Storage.getItemSync(CHAVE_USUARIO);
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
      Storage.setItemSync(CHAVE_TOKEN, token);
      Storage.setItemSync(CHAVE_USUARIO, JSON.stringify(u));
    } else {
      Storage.removeItemSync(CHAVE_TOKEN);
      Storage.removeItemSync(CHAVE_USUARIO);
    }
    setUsuario(u);
  }, []);

  const valor = useMemo<ContextoConta>(
    () => ({
      usuario,
      async entrar(email, senha) {
        const r = await chamar<{ token: string; usuario: Usuario }>('entrar.php', 'POST', { email, senha });
        guardar(r.token, r.usuario);
      },
      async criarConta(nome, email, senha) {
        const r = await chamar<{ token: string; usuario: Usuario }>('criar-conta.php', 'POST', { nome, email, senha });
        guardar(r.token, r.usuario);
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
