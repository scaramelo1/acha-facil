import { requireOptionalNativeModule } from 'expo-modules-core';

// Guarda pequenas informações no celular (cidade escolhida, login). Usa o expo-sqlite quando o módulo nativo
// existe (APK e Expo Go certo); se não existir (Expo Go de outra versão), guarda só na memória e o app abre do
// mesmo jeito, só não lembra a escolha depois de fechar.
interface Guarda {
  getItemSync(chave: string): string | null;
  setItemSync(chave: string, valor: string): void;
  removeItemSync(chave: string): unknown;
}

const memoria = new Map<string, string>();
const naMemoria: Guarda = {
  getItemSync: (chave) => memoria.get(chave) ?? null,
  setItemSync: (chave, valor) => void memoria.set(chave, valor),
  removeItemSync: (chave) => memoria.delete(chave),
};

function abrir(): Guarda {
  if (!requireOptionalNativeModule('ExpoSQLite')) return naMemoria;
  try {
    // só carrega o expo-sqlite depois de saber que o módulo nativo existe (carregar antes derruba o app)
    // eslint-disable-next-line @typescript-eslint/no-require-imports -- carregar sob demanda é de propósito (ver acima)
    return (require('expo-sqlite/kv-store') as { default: Guarda }).default;
  } catch {
    return naMemoria;
  }
}

const guarda = abrir();

/** Lê, grava e apaga sem derrubar o app se o celular recusar (ex.: armazenamento cheio). */
export const Armazenamento = {
  ler(chave: string): string | null {
    try {
      return guarda.getItemSync(chave);
    } catch {
      return memoria.get(chave) ?? null;
    }
  },
  gravar(chave: string, valor: string) {
    memoria.set(chave, valor);
    try {
      guarda.setItemSync(chave, valor);
    } catch {
      // fica só na memória
    }
  },
  apagar(chave: string) {
    memoria.delete(chave);
    try {
      guarda.removeItemSync(chave);
    } catch {
      // nada a fazer
    }
  },
};
