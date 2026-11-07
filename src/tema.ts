// Visual do Acha Fácil (modelo do usuário, 07/11): cabeçalho verde-escuro com a paisagem da cidade (morros e igreja),
// título branco com traço amarelo e busca amarela; corpo claro com cartões brancos de borda fina; botões amarelos
// com texto verde. Letras grandes e botões altos (fácil para a terceira idade).
export const cores = {
  fundo: '#F7F8F4', // quase branco
  cartao: '#FFFFFF',
  fundoSuave: '#FFF6D1', // amarelo bem claro: cartão "Tem um comércio?", etiquetas
  texto: '#123222',
  textoSuave: '#5B675F',
  borda: '#E2E6DF',
  verde: '#005C38', // verde da logo: títulos, ícones, botões verdes
  verdeEscuro: '#073D25', // fundo do cabeçalho por trás da paisagem
  destaque: '#00804F', // verde mais claro: links
  verdeClaro: '#E6F3EA',
  amarelo: '#F7C600', // amarelo da logo: busca, botões principais, traços
  whatsapp: '#1C9A3C',
  estrela: '#E8A400',
  erro: '#B00020',
};

export const fonte = {
  pequena: 16,
  normal: 18,
  media: 20,
  grande: 22,
  titulo: 30,
};

/** Altura mínima de qualquer coisa tocável (maior que o padrão de 44/48 px). */
export const TOQUE = 56;
export const RAIO = 16;
/** Espaço livre no fim das telas das abas (a barra de abas fica embaixo, sem cobrir o conteúdo). */
export const ESPACO_BARRA = 28;

/** Sombra bem leve dos cartões (o contorno fino faz o resto). */
export const sombra = {
  shadowColor: '#123222',
  shadowOpacity: 0.06,
  shadowRadius: 8,
  shadowOffset: { width: 0, height: 2 },
  elevation: 1,
} as const;

/** Cartão branco com contorno fino, como no modelo. */
export const cartao = {
  backgroundColor: '#FFFFFF',
  borderRadius: RAIO,
  borderWidth: 1,
  borderColor: '#E2E6DF',
  ...sombra,
} as const;

/** Bom dia / boa tarde / boa noite, pela hora do celular. */
export function saudacao(agora = new Date()) {
  const h = agora.getHours();
  return h < 5 ? 'Boa noite' : h < 12 ? 'Bom dia' : h < 18 ? 'Boa tarde' : 'Boa noite';
}
