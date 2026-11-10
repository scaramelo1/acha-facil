import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

import { chamar, definirCidade, type Cidade, type Estado } from './api/cliente';
import { Armazenamento } from './armazenamento';
import { EscolherCidade } from './componentes/EscolherCidade';

// As cidades atendidas vêm do servidor (api/cidades.php, tabela cidades editada em /admin/cidades.php): cidade nova
// aparece no app sem APK novo. O app guarda a última lista baixada para abrir sem internet; a lista abaixo é só a
// reserva da primeira vez (instalou e abriu sem internet). lat/lng = centro da cidade (achar a cidade pela localização).
const ESTADOS_RESERVA: Estado[] = [
  { uf: 'SP', nome: 'São Paulo' },
  { uf: 'MG', nome: 'Minas Gerais' },
];
const CIDADES_RESERVA: Cidade[] = [
  { slug: 'sao-joao', nome: 'São João da Boa Vista', curto: 'São João', estado: 'SP', lat: -21.9696, lng: -46.7981 },
  { slug: 'aguas-da-prata', nome: 'Águas da Prata', curto: 'Águas da Prata', estado: 'SP', lat: -21.9371, lng: -46.7172 },
  { slug: 'pocos-de-caldas', nome: 'Poços de Caldas', curto: 'Poços de Caldas', estado: 'MG', lat: -21.7878, lng: -46.5614 },
  { slug: 'vargem-grande-do-sul', nome: 'Vargem Grande do Sul', curto: 'Vargem Grande', estado: 'SP', lat: -21.8322, lng: -46.8936 },
  { slug: 'aguai', nome: 'Aguaí', curto: 'Aguaí', estado: 'SP', lat: -22.0597, lng: -46.9803 },
];
const CHAVE = 'cidade';
const CHAVE_LISTA = 'cidades-lista';

interface Lista {
  estados: Estado[];
  cidades: Cidade[];
}
type RespostaCidades = { estados: Estado[]; cidades: (Cidade & { total?: number; lat?: number | null; lng?: number | null })[] };

/** Confere o que veio do servidor (ou do celular) antes de usar; lista vazia ou estranha = null. */
function validar(r: RespostaCidades | null | undefined): Lista | null {
  if (!r || !Array.isArray(r.cidades) || !Array.isArray(r.estados)) return null;
  const cidades: Cidade[] = r.cidades
    .filter((c) => c && typeof c.slug === 'string' && typeof c.nome === 'string' && typeof c.estado === 'string')
    .map((c) => ({
      slug: c.slug,
      nome: c.nome,
      curto: c.curto || c.nome,
      estado: c.estado,
      lat: typeof c.lat === 'number' ? c.lat : undefined,
      lng: typeof c.lng === 'number' ? c.lng : undefined,
    }));
  const estados = r.estados.filter((e) => e && typeof e.uf === 'string' && typeof e.nome === 'string');
  return cidades.length > 0 && estados.length > 0 ? { estados, cidades } : null;
}

function listaGuardada(): Lista {
  try {
    const salva = validar(JSON.parse(Armazenamento.ler(CHAVE_LISTA) ?? 'null'));
    if (salva) return salva;
  } catch {
    // guardada com defeito: usa a reserva
  }
  return { estados: ESTADOS_RESERVA, cidades: CIDADES_RESERVA };
}

interface ContextoCidade {
  cidade: Cidade;
  /** Cidades atendidas (a última lista baixada do servidor). */
  cidades: Cidade[];
  /** Abre de novo a escolha da cidade. */
  trocarCidade: () => void;
}

const Contexto = createContext<ContextoCidade | null>(null);

function lerSalva(cidades: Cidade[]): Cidade {
  const slug = Armazenamento.ler(CHAVE);
  const c = cidades.find((x) => x.slug === slug) ?? cidades[0];
  definirCidade(c.slug);
  return c;
}

/** Toda vez que o app abre, pergunta a cidade ("Onde você quer buscar?"); a última escolha vem marcada. */
export function ProvedorCidade({ children }: { children: ReactNode }) {
  const [lista, setLista] = useState<Lista>(listaGuardada);
  const [cidade, setCidade] = useState<Cidade>(() => lerSalva(lista.cidades));
  const [totais, setTotais] = useState<Record<string, number>>({});
  const [escolhendo, setEscolhendo] = useState(true);

  // baixa a lista do servidor (ao abrir e a cada troca de cidade): cidade nova aparece, desligada some
  const atualizar = useCallback(() => {
    chamar<RespostaCidades>('cidades.php')
      .then((r) => {
        const nova = validar(r);
        if (!nova) return;
        Armazenamento.gravar(CHAVE_LISTA, JSON.stringify(nova));
        setLista(nova);
        setTotais(Object.fromEntries(r.cidades.map((c) => [c.slug, c.total ?? 0])));
        // nome mudou no servidor: usa o novo; cidade desligada: a escolha (que está aberta ao abrir o app) cai na primeira
        setCidade((atual) => {
          const igual = nova.cidades.find((c) => c.slug === atual.slug);
          if (igual) return igual;
          definirCidade(nova.cidades[0].slug);
          return nova.cidades[0];
        });
      })
      .catch(() => {});
  }, []);
  useEffect(atualizar, [atualizar]);

  const escolher = useCallback((c: Cidade) => {
    definirCidade(c.slug);
    Armazenamento.gravar(CHAVE, c.slug);
    setCidade(c);
    setEscolhendo(false);
  }, []);

  const valor = useMemo(
    () => ({
      cidade,
      cidades: lista.cidades,
      trocarCidade: () => {
        atualizar();
        setEscolhendo(true);
      },
    }),
    [cidade, lista, atualizar],
  );

  return (
    <Contexto.Provider value={valor}>
      {children}
      {escolhendo && <EscolherCidade atual={cidade} estados={lista.estados} cidades={lista.cidades} totais={totais} aoEscolher={escolher} />}
    </Contexto.Provider>
  );
}

export function useCidade() {
  const c = useContext(Contexto);
  if (!c) throw new Error('useCidade fora do ProvedorCidade');
  return c;
}
