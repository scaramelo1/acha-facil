import Storage from 'expo-sqlite/kv-store';
import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react';

import { definirCidade, type Cidade, type Estado } from './api/cliente';
import { EscolherCidade } from './componentes/EscolherCidade';

// Estados e cidades atendidas (iguais às do servidor, servidor/inc/guia.php). Ficam fixos no app para a escolha
// funcionar até sem internet.
export const ESTADOS: Estado[] = [
  { uf: 'SP', nome: 'São Paulo' },
  { uf: 'MG', nome: 'Minas Gerais' },
];
export const CIDADES: Cidade[] = [
  { slug: 'sao-joao', nome: 'São João da Boa Vista', curto: 'São João', estado: 'SP' },
  { slug: 'aguas-da-prata', nome: 'Águas da Prata', curto: 'Águas da Prata', estado: 'SP' },
  { slug: 'pocos-de-caldas', nome: 'Poços de Caldas', curto: 'Poços de Caldas', estado: 'MG' },
];
const CHAVE = 'cidade';

interface ContextoCidade {
  cidade: Cidade;
  /** Abre de novo a escolha da cidade. */
  trocarCidade: () => void;
}

const Contexto = createContext<ContextoCidade | null>(null);

function lerSalva(): Cidade {
  const slug = Storage.getItemSync(CHAVE);
  const c = CIDADES.find((x) => x.slug === slug) ?? CIDADES[0];
  definirCidade(c.slug);
  return c;
}

/** Toda vez que o app abre, pergunta o estado e depois a cidade; a última escolha fica marcada. */
export function ProvedorCidade({ children }: { children: ReactNode }) {
  const [cidade, setCidade] = useState<Cidade>(lerSalva);
  const [escolhendo, setEscolhendo] = useState(true);

  const escolher = useCallback((c: Cidade) => {
    definirCidade(c.slug);
    Storage.setItemSync(CHAVE, c.slug);
    setCidade(c);
    setEscolhendo(false);
  }, []);

  const valor = useMemo(() => ({ cidade, trocarCidade: () => setEscolhendo(true) }), [cidade]);

  return (
    <Contexto.Provider value={valor}>
      {children}
      {escolhendo && <EscolherCidade atual={cidade} estados={ESTADOS} cidades={CIDADES} aoEscolher={escolher} />}
    </Contexto.Provider>
  );
}

export function useCidade() {
  const c = useContext(Contexto);
  if (!c) throw new Error('useCidade fora do ProvedorCidade');
  return c;
}
