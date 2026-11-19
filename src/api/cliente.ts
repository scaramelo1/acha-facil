import { Armazenamento } from '../armazenamento';

export const URL_API = 'https://achafacilapp.com.br/api';
/** Página onde o profissional se cadastra e paga a assinatura. */
export const URL_PAINEL = 'https://achafacilapp.com.br/painel/';
export const URL_TERMOS = 'https://achafacilapp.com.br/termos.php';
export const URL_PRIVACIDADE = 'https://achafacilapp.com.br/privacidade.php';
/** Página onde o dono procura o próprio comércio para assumir o perfil ou pedir para tirar do app. */
/** Planos para o dono (corrigir dados, fotos, aparecer em destaque). */
export const URL_PLANOS = 'https://achafacilapp.com.br/planos.php';
export const URL_MEU_NEGOCIO = 'https://achafacilapp.com.br/busca.php';
/** Contato do Acha Fácil (iguais aos do site, /etc/acha-facil/config.php). */
export const EMAIL_CONTATO = 'achafacilcontato@gmail.com';
export const URL_INSTAGRAM = 'https://instagram.com/fsa.designer';

export interface Cidade {
  slug: string;
  nome: string;
  /** Nome curto para cabeçalhos ("São João"). */
  curto: string;
  /** Sigla do estado ("SP", "MG"). */
  estado: string;
  /** Centro da cidade, para achar a cidade pela localização do celular (vem do servidor; pode faltar). */
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
  /** Logo do comércio (listas e círculo do perfil). */
  foto: string | null;
  /** Primeira foto da galeria (faixa do destaque). */
  capa?: string | null;
  verificado: boolean;
  destaque: boolean;
  nota: number;
  avaliacoes: number;
  temWhatsapp: boolean;
  temTelefone: boolean;
  /** false = veio da lista pública de comércios; o dono ainda não assumiu o perfil. */
  cadastrado: boolean;
  /** Pagou o Básico (ou se cadastrou sozinho): "Ficha completa". Servidor antigo não manda. */
  fichaCompleta?: boolean;
  /** O dono assumiu a ficha (plano Grátis ou pago): "Dados confirmados pelo dono". */
  donoConfirmado?: boolean;
  /** Aberto agora pelo horário do painel (null = horário não informado). Servidor de 19/11 em diante. */
  abertoAgora?: boolean | null;
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

/** Um campo do horário: Segunda a sexta, Sábado, Domingo ou Feriados. */
export interface HorarioCampo {
  campo: 'semana' | 'sab' | 'dom' | 'fer';
  nome: string;
  /** "08:00 às 18:00", "Fechado" ou "Aberto 24 horas". */
  texto: string;
  situacao: 'aberto' | 'fechado' | '24h';
  abre: string | null;
  fecha: string | null;
}

/** Oferta da semana (beneficio pago do Destaque, servidor 19/11). Precos em reais; validade ja escrita ("até domingo, 22/11"). */
export interface Oferta {
  titulo: string;
  texto: string | null;
  precoDe: number | null;
  precoPor: number | null;
  foto: string | null;
  validade: string;
}

/** Oferta na faixa "Ofertas da semana" da tela inicial: a oferta e o comercio (id = comercio). */
export interface OfertaInicio {
  id: number;
  nome: string;
  titulo: string;
  precoDe: number | null;
  precoPor: number | null;
  foto: string | null;
  validade: string;
}

/** "R$ 49,90" */
export function reais(v: number): string {
  return 'R$ ' + v.toFixed(2).replace('.', ',');
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
  /** Numero nacional da rede (4003, 0800...), nao o da loja. */
  telefoneCentral?: boolean;
  linkAssumir: string | null;
  linkRemover: string | null;
  horario: string | null;
  /** Horário separado por campo (null = o dono ainda não preencheu; aí vale o texto de horario). */
  horarios?: HorarioCampo[] | null;
  /** Fotos do comércio: o carrossel da capa do perfil. */
  fotos?: string[];
  favorito: boolean;
  /** Pagina da ficha no site (para compartilhar). Servidor antigo nao manda. */
  linkSite?: string | null;
  /** Oferta da semana no ar (so Destaque). */
  oferta?: Oferta | null;
  minhaAvaliacao: { estrelas: number; comentario: string | null } | null;
  comentarios: Comentario[];
}

/** Evento da agenda da cidade (api/eventos.php). As datas ja vem escritas pelo servidor. */
export interface EventoResumo {
  id: number;
  titulo: string;
  /** Data de inicio (AAAA-MM-DD). */
  data: string;
  /** Grupo da lista: "Acontecendo agora", "Novembro", "Fevereiro de 2027". */
  grupo: string;
  /** Folhinha do calendario: "SAB", "15" ou "20 a 23", "NOV". */
  semana: string;
  dia: string;
  mes: string;
  /** "Sábado, 15 de novembro" ou "20 a 23 de novembro". */
  quando: string;
  /** "A partir das 19h", "9h às 14h" ou null. */
  horario: string | null;
  local: string | null;
  endereco: string | null;
  /** Entrada: "Gratuito", "R$ 40 (meia R$ 20)"... */
  preco: string | null;
  imagem: string | null;
  terminou: boolean;
}
export interface Evento extends EventoResumo {
  cidade: string;
  descricao: string | null;
  linkOficial: string | null;
  /** Link do Google Agenda com o evento preenchido. */
  agenda: string;
  mapa: string | null;
  /** Pagina do evento no site (para compartilhar). */
  site: string;
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
  profissionalId?: number; // 19/11 (passo 109): nome abre a ficha
  linkCidade?: string | null; // pagina da cidade no site
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
  profissionalId?: number | null; // 19/11 (passo 109)
  cidade?: string | null;
  linkCidade?: string | null;
}
/** Evento enviado pelo publico em /incluir-evento.php, esperando autorizacao. */
export interface EventoPendente {
  id: number;
  titulo: string;
  cidade: string;
  linkCidade?: string | null; // 19/11 (passo 109)
  quando: string;
  horario: string | null;
  local: string | null;
  preco: string | null;
  descricao: string | null;
  linkOficial: string | null;
  imagem: string | null;
  enviadoPor: string | null;
  telefone: string;
  whatsapp: string | null;
  recado: string | null;
  enviadoEm: string;
}
export interface Pendencias {
  /** Eventos para autorizar (servidor com a mudanca 021; antes vem sem o campo). */
  eventos?: EventoPendente[];
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

// Codigo aleatorio criado no celular na 1a vez, so para o servidor contar pessoas diferentes por dia em cada ficha
// (nao tem ligacao com a conta). Vai no cabecalho X-Visitante; o servidor guarda so uma versao embaralhada por 2 dias.
const CHAVE_VISITANTE = 'visitante';
let visitante: string | null = null;
function codigoVisitante(): string {
  let c = Armazenamento.ler(CHAVE_VISITANTE);
  if (!c || !/^[A-Za-z0-9-]{16,64}$/.test(c)) {
    const parte = () => Math.random().toString(36).slice(2, 10).padEnd(8, '0');
    c = `${Date.now().toString(36)}-${parte()}${parte()}${parte()}`;
    Armazenamento.gravar(CHAVE_VISITANTE, c);
  }
  return c;
}

/** De onde a pessoa abriu a ficha (numeros do comercio). 'nenhuma' = recarga ou tela de avaliar: nao conta. */
export type Origem = 'busca' | 'categoria' | 'perto' | 'destaque' | 'inicio' | 'favoritos' | 'link' | 'nenhuma';
export const ORIGENS: readonly Origem[] = ['busca', 'categoria', 'perto', 'destaque', 'inicio', 'favoritos', 'link', 'nenhuma'];

export async function chamar<T>(caminho: string, metodo: 'GET' | 'POST' | 'DELETE' = 'GET', corpo?: unknown): Promise<T> {
  let r: Response;
  try {
    const url = `${URL_API}/${caminho}${caminho.includes('?') ? '&' : '?'}cidade=${encodeURIComponent(cidadeAtual)}`;
    r = await fetch(url, {
      method: metodo,
      headers: {
        'Content-Type': 'application/json',
        ...(tokenAtual ? { 'X-Token': tokenAtual } : {}),
        'X-Visitante': (visitante ??= codigoVisitante()),
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
export function registrarContato(id: number, tipo: 'whatsapp' | 'ligar' | 'instagram' | 'mapa' | 'site' | 'compartilhar') {
  chamar('contato.php', 'POST', { id, tipo, origem: 'app' }).catch(() => {});
}

/** Passo 59: "Quero saber o horário", "Quero ver fotos"... numa ficha incompleta. Conta 1 por pessoa no dia. Falha em silêncio. */
export type TipoPedidoInfo = 'horario' | 'fotos' | 'sobre' | 'whatsapp';
export function pedirInfo(id: number, tipo: TipoPedidoInfo) {
  chamar('quero.php', 'POST', { id, tipo, origem: 'app' }).catch(() => {});
}
