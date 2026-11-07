import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react';

import { definirCidade, type Cidade, type Estado } from './api/cliente';
import { Armazenamento } from './armazenamento';
import { EscolherCidade } from './componentes/EscolherCidade';

// Estados e cidades atendidas (iguais às do servidor, servidor/inc/guia.php). Ficam fixos no app para a escolha
// funcionar até sem internet. lat/lng = centro da cidade (para achar a cidade pela localização). Cidade nova: pôr aqui também.
export const ESTADOS: Estado[] = [
  { uf: 'SP', nome: 'São Paulo' },
  { uf: 'MG', nome: 'Minas Gerais' },
];
export const CIDADES: Cidade[] = [
  { slug: 'sao-joao', nome: 'São João da Boa Vista', curto: 'São João', estado: 'SP', lat: -21.9696, lng: -46.7981 },
  { slug: 'aguas-da-prata', nome: 'Águas da Prata', curto: 'Águas da Prata', estado: 'SP', lat: -21.9371, lng: -46.7172 },
  { slug: 'pocos-de-caldas', nome: 'Poços de Caldas', curto: 'Poços de Caldas', estado: 'MG', lat: -21.7878, lng: -46.5614 },
];
const CHAVE = 'cidade';

interface ContextoCidade {
  cidade: Cidade;
  /** Abre de novo a escolha da cidade. */
  trocarCidade: () => void;
}

const Contexto = createContext<ContextoCidade | null>(null);

function lerSalva(): Cidade {
  const slug = Armazenamento.ler(CHAVE);
  const c = CIDADES.find((x) => x.slug === slug) ?? CIDADES[0];
  definirCidade(c.slug);
  return c;
}

/** Toda vez que o app abre, pergunta a cidade ("Onde você quer buscar?"); a última escolha vem marcada. */
export function ProvedorCidade({ children }: { children: ReactNode }) {
  const [cidade, setCidade] = useState<Cidade>(lerSalva);
  const [escolhendo, setEscolhendo] = useState(true);

  const escolher = useCallback((c: Cidade) => {
    definirCidade(c.slug);
    Armazenamento.gravar(CHAVE, c.slug);
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
