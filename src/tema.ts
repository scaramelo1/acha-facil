// Visual do Acha Fácil: "comércio de bairro". Fundo creme, cabeçalhos verdes, amarelo da marca nos destaques,
// cantos bem redondos e cada área com a sua cor. Letras grandes e botões altos (fácil para a terceira idade).
export const cores = {
  fundo: '#FFFBEA', // creme: fundo das telas
  cartao: '#FFFFFF',
  fundoSuave: '#FFF3C4', // amarelo bem claro: etiquetas, toques
  texto: '#10261B',
  textoSuave: '#4B5A52',
  borda: '#ECE4C2',
  verde: '#005C38', // verde da logo: cabeçalhos, botões principais
  destaque: '#00804F', // verde mais claro: ícones, links, aba ativa
  verdeClaro: '#E3F2EA',
  amarelo: '#F7C600', // amarelo da logo: busca, selos, aba ativa (sempre com texto verde)
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
export const RAIO = 22;
/** Espaço que as telas das abas deixam embaixo para a barra de abas flutuante não cobrir o conteúdo. */
export const ESPACO_BARRA = 120;

/** Sombra "de carimbo" (deslocada para baixo), igual à do site. */
export const sombra = {
  shadowColor: '#005C38',
  shadowOpacity: 0.12,
  shadowRadius: 0,
  shadowOffset: { width: 0, height: 5 },
  elevation: 3,
} as const;

/** Bom dia / boa tarde / boa noite, pela hora do celular. */
export function saudacao(agora = new Date()) {
  const h = agora.getHours();
  return h < 5 ? 'Boa noite' : h < 12 ? 'Bom dia' : h < 18 ? 'Boa tarde' : 'Boa noite';
}
