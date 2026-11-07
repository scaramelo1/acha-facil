export const URL_API = 'https://fsadesigners.com.br/acha-facil/api';
/** Página onde o profissional se cadastra e paga a assinatura. */
export const URL_PAINEL = 'https://fsadesigners.com.br/acha-facil/painel/';
export const URL_TERMOS = 'https://fsadesigners.com.br/acha-facil/termos.php';
export const URL_PRIVACIDADE = 'https://fsadesigners.com.br/acha-facil/privacidade.php';

export interface Categoria {
  id: number;
  nome: string;
  slug: string;
  icone: string;
  naInicial: boolean;
  total: number;
}

export interface ProfissionalResumo {
  id: number;
  nome: string;
  categoria: string;
  categoriaId: number;
  icone: string;
  servicos: string[];
  bairro: string | null;
  atendeEmCasa: boolean;
  foto: string | null;
  verificado: boolean;
  destaque: boolean;
  nota: number;
  avaliacoes: number;
  temWhatsapp: boolean;
  temTelefone: boolean;
}

export interface Comentario {
  id: number;
  autor: string;
  estrelas: number;
  comentario: string | null;
  resposta: string | null;
  data: string;
}

export interface Profissional extends ProfissionalResumo {
  responsavel: string | null;
  descricao: string | null;
  telefone: string | null;
  telefoneFormatado: string | null;
  whatsapp: string | null;
  instagram: string | null;
  endereco: string | null;
  mapa: string | null;
  horario: string | null;
  favorito: boolean;
  minhaAvaliacao: { estrelas: number; comentario: string | null } | null;
  comentarios: Comentario[];
}

export interface Usuario {
  id: number;
  nome: string;
  email: string;
}

export class ErroApi extends Error {
  status: number;
  constructor(status: number, mensagem: string) {
    super(mensagem);
    this.status = status;
  }
}

let tokenAtual: string | null = null;
export function definirToken(t: string | null) {
  tokenAtual = t;
}

export async function chamar<T>(caminho: string, metodo: 'GET' | 'POST' | 'DELETE' = 'GET', corpo?: unknown): Promise<T> {
  let r: Response;
  try {
    r = await fetch(`${URL_API}/${caminho}`, {
      method: metodo,
      headers: {
        'Content-Type': 'application/json',
        ...(tokenAtual ? { 'X-Token': tokenAtual } : {}),
      },
      body: corpo === undefined ? undefined : JSON.stringify(corpo),
    });
  } catch {
    throw new ErroApi(0, 'Sem internet. Confira a conexão e tente de novo.');
  }
  const dados = await r.json().catch(() => ({}));
  if (!r.ok) throw new ErroApi(r.status, dados.erro ?? 'Não foi possível carregar. Tente de novo.');
  return dados as T;
}

/** Conta toques em WhatsApp, ligar etc. (estatística para o profissional). Falha em silêncio. */
export function registrarContato(id: number, tipo: 'whatsapp' | 'ligar' | 'instagram' | 'mapa') {
  chamar('contato.php', 'POST', { id, tipo }).catch(() => {});
}
