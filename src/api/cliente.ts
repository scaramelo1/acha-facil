export const URL_API = 'https://fsadesigners.com.br/acha-facil/api';
/** Página onde o profissional se cadastra e paga a assinatura. */
export const URL_PAINEL = 'https://fsadesigners.com.br/acha-facil/painel/';
export const URL_TERMOS = 'https://fsadesigners.com.br/acha-facil/termos.php';
export const URL_PRIVACIDADE = 'https://fsadesigners.com.br/acha-facil/privacidade.php';
/** Página onde o dono procura o próprio comércio para assumir o perfil ou pedir para tirar do app. */
/** Planos para o dono (corrigir dados, fotos, aparecer em destaque). */
export const URL_PLANOS = 'https://fsadesigners.com.br/acha-facil/planos.php';
export const URL_MEU_NEGOCIO = 'https://fsadesigners.com.br/acha-facil/busca.php';
/** Contato da FSA Designer, responsável pelo Acha Fácil (iguais aos do site, /etc/acha-facil/config.php). */
export const EMAIL_CONTATO = 'fsadesignerbr@gmail.com';
export const URL_INSTAGRAM = 'https://instagram.com/fsa.designer';

export interface Cidade {
  slug: string;
  nome: string;
  /** Nome curto para cabeçalhos ("São João"). */
  curto: string;
  /** Sigla do estado ("SP", "MG"). */
  estado: string;
  /** Centro da cidade, para achar a cidade pela localização do celular (só na lista fixa do app). */
  lat?: number;
  lng?: number;
}

export interface Estado {
  uf: string;
  nome: string;
}

export interface Categoria {
  id: number;
  nome: string;
  slug: string;
  icone: string;
  cor: string;
  naInicial: boolean;
  total: number;
}

export interface ProfissionalResumo {
  id: number;
  nome: string;
  categoria: string;
  categoriaId: number;
  icone: string;
  cor: string;
  /** Tipo de negócio (ex.: "Pizzaria", "Academia"). */
  tipo: string;
  servicos: string[];
  bairro: string | null;
  /** Nome curto da cidade do comércio. */
  cidade: string;
  atendeEmCasa: boolean;
  foto: string | null;
  verificado: boolean;
  destaque: boolean;
  nota: number;
  avaliacoes: number;
  temWhatsapp: boolean;
  temTelefone: boolean;
  /** false = veio da lista pública de comércios; o dono ainda não assumiu o perfil. */
  cadastrado: boolean;
}

export interface TipoNegocio {
  nome: string;
  total: number;
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
  site: string | null;
  linkAssumir: string | null;
  linkRemover: string | null;
  horario: string | null;
  favorito: boolean;
  minhaAvaliacao: { estrelas: number; comentario: string | null } | null;
  comentarios: Comentario[];
}

export interface Usuario {
  id: number;
  nome: string;
  email: string;
  /** Foto do perfil do Google. */
  foto: string | null;
  /** E-mail cadastrado como administrador: vê a tela Administração e recebe os avisos. */
  admin?: boolean;
}

/** Tela Administração (api/admin.php) */
export interface CampoAlterado {
  campo: string;
  rotulo: string;
  antes: string | null;
  depois: string | null;
}
export interface AlteracaoPendente {
  id: number;
  comercio: string;
  cidade: string;
  quando: string;
  dono: string | null;
  donoTelefone: string;
  pago: { nome: string; telefone: string; email: string | null; plano: string | null; status: string | null } | null;
  primeira: boolean;
  conferirTelefone: string | null;
  campos: CampoAlterado[];
}
export interface PedidoNovo {
  id: number;
  tipo: 'assumir' | 'remover' | 'incluir';
  plano: string | null;
  pago: boolean;
  comercio: string | null;
  nome: string;
  telefone: string;
  quando: string;
}
export interface Pendencias {
  alteracoes: AlteracaoPendente[];
  pedidos: PedidoNovo[];
  cidades: { cidade: string; uf: string | null; total: number; ultimo: string }[];
  site: string;
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

// Cidade escolhida ao abrir o app: vai em todas as chamadas (?cidade=), e o servidor só devolve comércios dela.
let cidadeAtual = 'sao-joao';
export function definirCidade(slug: string) {
  cidadeAtual = slug;
}

export async function chamar<T>(caminho: string, metodo: 'GET' | 'POST' | 'DELETE' = 'GET', corpo?: unknown): Promise<T> {
  let r: Response;
  try {
    const url = `${URL_API}/${caminho}${caminho.includes('?') ? '&' : '?'}cidade=${encodeURIComponent(cidadeAtual)}`;
    r = await fetch(url, {
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
export function registrarContato(id: number, tipo: 'whatsapp' | 'ligar' | 'instagram' | 'mapa' | 'site') {
  chamar('contato.php', 'POST', { id, tipo }).catch(() => {});
}
