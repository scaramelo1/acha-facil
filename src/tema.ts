// Visual do Acha Fácil: telas claras, cartões brancos com sombra suave, verde da marca nos botões e na aba ativa,
// amarelo nos selos e chamadas, e cada área com a sua cor. Letras grandes e botões altos (fácil para a terceira idade).
export const cores = {
  fundo: '#F6F8F3', // quase branco, levemente esverdeado
  cartao: '#FFFFFF',
  fundoSuave: '#FFF4C7', // amarelo bem claro: etiquetas, setas
  texto: '#0E2A1B',
  textoSuave: '#56645B',
  borda: '#E3E8E0',
  verde: '#005C38', // verde da logo: botões principais, aba ativa, faixas
  destaque: '#00804F', // verde mais claro: ícones, links
  verdeClaro: '#E4F2E8',
  amarelo: '#F7C600', // amarelo da logo: cidade, tipo do comércio, chamada para o dono
  whatsapp: '#1C9A3C',
  estrela: '#E8A400',
  erro: '#B00020',
};

export const fonte = {
  pequena: 16,
  normal: 18,
  media: 20,
  grande: 24,
  titulo: 30,
};

/** Altura mínima de qualquer coisa tocável (maior que o padrão de 44/48 px). */
export const TOQUE = 56;
export const RAIO = 20;
/** Espaço que as telas das abas deixam embaixo para a barra de abas flutuante não cobrir o conteúdo. */
export const ESPACO_BARRA = 130;

/** Sombra suave dos cartões. */
export const sombra = {
  shadowColor: '#0E2A1B',
  shadowOpacity: 0.08,
  shadowRadius: 12,
  shadowOffset: { width: 0, height: 4 },
  elevation: 2,
} as const;

/** Bom dia / boa tarde / boa noite, pela hora do celular. */
export function saudacao(agora = new Date()) {
  const h = agora.getHours();
  return h < 5 ? 'Boa noite' : h < 12 ? 'Bom dia' : h < 18 ? 'Boa tarde' : 'Boa noite';
}
