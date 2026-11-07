import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import { useCallback, useEffect, useRef, useState, type ComponentProps, type ReactNode } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, TextInput, View, type StyleProp, type TextInputProps, type ViewStyle } from 'react-native';

import type { ProfissionalResumo } from '../api/cliente';
import { cartao, cores, fonte, TOQUE } from '../tema';

export type NomeIcone = ComponentProps<typeof MaterialCommunityIcons>['name'];

/** Ícone vindo do servidor (nome em texto); usa um genérico se o nome não existir. */
export function Icone({ nome, tamanho = 28, cor = cores.texto }: { nome: string; tamanho?: number; cor?: string }) {
  const valido = nome in MaterialCommunityIcons.glyphMap ? (nome as NomeIcone) : 'storefront-outline';
  return <MaterialCommunityIcons name={valido} size={tamanho} color={cor} />;
}

/**
 * Carrega dados da API com estado de carregando/erro e função para tentar de novo.
 * `chave` identifica o que está sendo carregado (ex.: `categoria-3`); quando ela muda, carrega de novo.
 */
export function useCarregar<T>(buscar: () => Promise<T>, chave: string) {
  const [dados, setDados] = useState<T | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const [carregando, setCarregando] = useState(true);
  const [versao, setVersao] = useState(0);
  const buscarAtual = useRef(buscar);

  useEffect(() => {
    buscarAtual.current = buscar;
  });

  useEffect(() => {
    let cancelado = false;
    buscarAtual
      .current()
      .then(
        (d) => {
          if (cancelado) return;
          setDados(d);
          setErro(null);
        },
        (e: unknown) => {
          if (!cancelado) setErro(e instanceof Error ? e.message : 'Não foi possível carregar.');
        },
      )
      .finally(() => {
        if (!cancelado) setCarregando(false);
      });
    return () => {
      cancelado = true;
    };
  }, [chave, versao]);

  const recarregar = useCallback(() => {
    setCarregando(true);
    setVersao((v) => v + 1);
  }, []);

  return { dados, setDados, erro, carregando, recarregar };
}

export function Carregando() {
  return (
    <View style={estilos.centro}>
      <ActivityIndicator size="large" color={cores.verde} />
    </View>
  );
}

export function Falha({ mensagem, tentar }: { mensagem: string; tentar: () => void }) {
  return (
    <View style={estilos.centro}>
      <View style={estilos.bolha}>
        <MaterialCommunityIcons name="wifi-off" size={40} color={cores.verde} />
      </View>
      <Text style={[estilos.texto, { textAlign: 'center', marginVertical: 16 }]}>{mensagem}</Text>
      <Botao titulo="Tentar de novo" icone="refresh" onPress={tentar} />
    </View>
  );
}

export function Vazio({ icone, texto }: { icone: NomeIcone; texto: string }) {
  return (
    <View style={estilos.centro}>
      <View style={estilos.bolha}>
        <MaterialCommunityIcons name={icone} size={40} color={cores.verde} />
      </View>
      <Text style={[estilos.texto, { textAlign: 'center', marginTop: 16, color: cores.textoSuave }]}>{texto}</Text>
    </View>
  );
}

type TipoBotao = 'principal' | 'amarelo' | 'whatsapp' | 'contorno' | 'perigo';

export function Botao({
  titulo,
  icone,
  onPress,
  tipo = 'principal',
  desativado,
  estilo,
  seta,
}: {
  titulo: string;
  icone?: NomeIcone;
  onPress: () => void;
  tipo?: TipoBotao;
  desativado?: boolean;
  estilo?: StyleProp<ViewStyle>;
  /** Setinha ">" depois do texto (como nos botões amarelos do modelo). */
  seta?: boolean;
}) {
  const fundo = { principal: cores.verde, amarelo: cores.amarelo, whatsapp: cores.whatsapp, contorno: cores.cartao, perigo: cores.cartao }[tipo];
  const cor = { principal: '#fff', amarelo: cores.verde, whatsapp: '#fff', contorno: cores.verde, perigo: cores.erro }[tipo];
  const borda = tipo === 'perigo' ? cores.erro : tipo === 'contorno' ? cores.verde : 'transparent';
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={titulo}
      disabled={desativado}
      onPress={onPress}
      style={({ pressed }) => [
        estilos.botao,
        { backgroundColor: fundo, borderColor: borda, opacity: desativado ? 0.5 : pressed ? 0.85 : 1 },
        estilo,
      ]}
    >
      {icone && <MaterialCommunityIcons name={icone} size={26} color={cor} />}
      <Text style={[estilos.botaoTexto, { color: cor }]}>{titulo}</Text>
      {seta && <MaterialCommunityIcons name="chevron-right" size={26} color={cor} />}
    </Pressable>
  );
}

export function Estrelas({ nota, tamanho = 20 }: { nota: number; tamanho?: number }) {
  return (
    <View style={{ flexDirection: 'row' }} accessibilityLabel={`Nota ${nota.toFixed(1)} de 5`}>
      {[1, 2, 3, 4, 5].map((i) => (
        <MaterialCommunityIcons
          key={i}
          name={nota >= i ? 'star' : nota >= i - 0.5 ? 'star-half-full' : 'star-outline'}
          size={tamanho}
          color={cores.estrela}
        />
      ))}
    </View>
  );
}

/** Cor da área com transparência (fundo clarinho dos ícones). */
export function corClara(cor: string, alfa = '22') {
  return /^#[0-9a-f]{6}$/i.test(cor) ? cor + alfa : cores.verdeClaro;
}

/**
 * Espaço da foto do comércio. Sem foto (o dono coloca depois, pelo plano), mostra a cor da área
 * com o ícone dela em branco e um círculo claro de enfeite.
 */
export function Foto({
  foto,
  icone,
  cor = cores.verde,
  largura = 104,
  altura = 88,
  raio = 12,
}: {
  foto: string | null;
  icone: string;
  cor?: string;
  largura?: number | `${number}%`;
  altura?: number;
  raio?: number;
}) {
  const forma = { width: largura, height: altura, borderRadius: raio, overflow: 'hidden' as const };
  if (foto) return <Image source={foto} style={[forma, { backgroundColor: cores.fundoSuave }]} contentFit="cover" />;
  const lado = typeof largura === 'number' ? Math.min(largura, altura) : altura;
  return (
    <View style={[forma, { backgroundColor: /^#[0-9a-f]{6}$/i.test(cor) ? cor : cores.verde, alignItems: 'center', justifyContent: 'center' }]}>
      <View style={[estilos.enfeite, { width: lado * 1.1, height: lado * 1.1, borderRadius: lado * 0.55, right: -lado * 0.35, top: -lado * 0.45 }]} />
      <Icone nome={icone} tamanho={lado * 0.46} cor="rgba(255,255,255,0.95)" />
    </View>
  );
}

/** Foto do comércio ou, sem foto, o ícone da área num quadrado arredondado com a cor da área. */
export function Avatar({ foto, icone, cor = cores.verde, tamanho = 64 }: { foto: string | null; icone: string; cor?: string; tamanho?: number }) {
  const forma = { width: tamanho, height: tamanho, borderRadius: tamanho * 0.3 };
  if (foto) return <Image source={foto} style={[forma, { backgroundColor: cores.fundoSuave }]} contentFit="cover" />;
  return (
    <View style={[forma, { backgroundColor: corClara(cor), alignItems: 'center', justifyContent: 'center' }]}>
      <Icone nome={icone} tamanho={tamanho * 0.5} cor={cor} />
    </View>
  );
}

export function SeloVerificado({ compacto, claro }: { compacto?: boolean; claro?: boolean }) {
  return (
    <View style={[estilos.selo, claro && { backgroundColor: cores.amarelo }]} accessibilityLabel="Verificado pelo Acha Fácil">
      <MaterialCommunityIcons name="check-decagram" size={16} color={cores.verde} />
      <Text style={estilos.seloTexto}>{compacto ? 'Verificado' : 'Verificado pelo Acha Fácil'}</Text>
    </View>
  );
}

/** Cartão de comércio nas listas: espaço da foto, nome, tipo · bairro, como falar e a setinha amarela. */
/** 0,35 → "350 m"; 1,24 → "1,2 km" (distância aproximada, em linha reta). */
export function textoDistancia(km: number) {
  if (km < 1) return `${Math.max(50, Math.round((km * 1000) / 50) * 50)} m`;
  return `${km < 10 ? km.toFixed(1).replace('.', ',') : Math.round(km)} km`;
}

export function CartaoProfissional({ p, distancia }: { p: ProfissionalResumo; distancia?: number }) {
  const router = useRouter();
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`${p.nome}, ${p.tipo}`}
      onPress={() => router.push(`/profissional/${p.id}`)}
      style={({ pressed }) => [estilos.cartao, pressed && { opacity: 0.85 }]}
    >
      <Foto foto={p.foto} icone={p.icone} cor={p.cor} largura={108} altura={92} raio={0} />
      <View style={{ flex: 1, gap: 2, paddingVertical: 10 }}>
        <Text style={estilos.cartaoNome} numberOfLines={2}>
          {p.nome}
        </Text>
        <Text style={estilos.cartaoSub} numberOfLines={1}>
          {p.tipo}
          {p.bairro ? ` · ${p.bairro}` : ''}
        </Text>
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 6, marginTop: 4 }}>
          {distancia !== undefined && (
            <View style={estilos.distancia} accessibilityLabel={`A cerca de ${textoDistancia(distancia)}`}>
              <MaterialCommunityIcons name="map-marker-outline" size={15} color={cores.verde} />
              <Text style={estilos.distanciaTexto}>{textoDistancia(distancia)}</Text>
            </View>
          )}
          {p.destaque && <Etiqueta texto="Destaque" icone="star-four-points" amarela />}
          {p.verificado && <SeloVerificado compacto />}
          {p.avaliacoes > 0 && (
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 2 }}>
              <MaterialCommunityIcons name="star" size={17} color={cores.estrela} />
              <Text style={estilos.cartaoSub}>{p.nota.toFixed(1).replace('.', ',')}</Text>
            </View>
          )}
          <Contato p={p} />
        </View>
      </View>
      <MaterialCommunityIcons name="chevron-right" size={28} color={cores.texto} style={{ marginRight: 8 }} />
    </Pressable>
  );
}

/** "● WhatsApp" (verde) ou "● Telefone": como dá para falar com o comércio. */
export function Contato({ p }: { p: Pick<ProfissionalResumo, 'temWhatsapp' | 'temTelefone'> }) {
  if (!p.temWhatsapp && !p.temTelefone) return null;
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
      <View style={[estilos.bolinha, !p.temWhatsapp && { backgroundColor: cores.destaque }]} />
      <Text style={estilos.contatoTexto}>{p.temWhatsapp ? 'Tem WhatsApp' : 'Tem telefone'}</Text>
    </View>
  );
}

export function Etiqueta({ texto, icone, amarela }: { texto: string; icone?: NomeIcone; amarela?: boolean }) {
  return (
    <View style={[estilos.etiqueta, amarela && { backgroundColor: cores.amarelo }]}>
      {icone && <MaterialCommunityIcons name={icone} size={15} color={cores.verde} />}
      <Text style={estilos.etiquetaTexto}>{texto}</Text>
    </View>
  );
}

/** Título de seção (texto escuro e forte) e, se tiver, um link "Ver todas" à direita. */
export function Titulo({ children, acao, aoTocarAcao }: { children: ReactNode; acao?: string; aoTocarAcao?: () => void }) {
  return (
    <View style={estilos.tituloLinha}>
      <Text style={estilos.titulo} accessibilityRole="header">
        {children}
      </Text>
      {acao && aoTocarAcao && (
        <Pressable accessibilityRole="button" onPress={aoTocarAcao} hitSlop={12} style={estilos.tituloAcao}>
          <Text style={estilos.tituloAcaoTexto}>{acao}</Text>
          <MaterialCommunityIcons name="chevron-right" size={22} color={cores.destaque} />
        </Pressable>
      )}
    </View>
  );
}

/** Pílula de filtro (Todos, Lanchonete, Padaria…): verde cheia quando escolhida, branca com contorno quando não. */
export function Pilula({ texto, ativo, onPress, icone }: { texto: string; ativo: boolean; onPress: () => void; icone?: string }) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ selected: ativo }}
      onPress={onPress}
      style={[estilos.pilula, ativo && { backgroundColor: cores.verde, borderColor: cores.verde }]}
    >
      {icone && <Icone nome={icone} tamanho={20} cor={ativo ? '#fff' : cores.verde} />}
      <Text style={[estilos.pilulaTexto, ativo && { color: '#fff' }]}>{texto}</Text>
    </Pressable>
  );
}

/** Linha de menu em cartão branco (Perfil, Sobre): ícone verde, texto, e a setinha ou algo à direita. */
export function LinhaMenu({
  icone,
  texto,
  detalhe,
  onPress,
  direita,
  cor = cores.texto,
}: {
  icone: NomeIcone;
  texto: string;
  detalhe?: string;
  onPress: () => void;
  direita?: ReactNode;
  cor?: string;
}) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={detalhe ? `${texto}: ${detalhe}` : texto}
      onPress={onPress}
      style={({ pressed }) => [estilos.menu, pressed && { opacity: 0.85 }]}
    >
      <MaterialCommunityIcons name={icone} size={26} color={cor === cores.texto ? cores.verde : cor} />
      <View style={{ flex: 1 }}>
        <Text style={[estilos.menuTexto, { color: cor }]}>{texto}</Text>
        {detalhe ? <Text style={estilos.cartaoSub}>{detalhe}</Text> : null}
      </View>
      {direita ?? <MaterialCommunityIcons name="chevron-right" size={26} color={cores.texto} />}
    </Pressable>
  );
}

/** Campo de formulário com ícone na frente e, para senha, o olho para mostrar/esconder. */
export function Campo({ icone, senha, ...props }: TextInputProps & { icone: NomeIcone; senha?: boolean }) {
  const [ver, setVer] = useState(false);
  return (
    <View style={estilos.campoCaixa}>
      <MaterialCommunityIcons name={icone} size={24} color={cores.textoSuave} />
      <TextInput placeholderTextColor={cores.textoSuave} secureTextEntry={senha && !ver} style={estilos.campoTexto} {...props} />
      {senha && (
        <Pressable accessibilityRole="button" accessibilityLabel={ver ? 'Esconder a senha' : 'Mostrar a senha'} hitSlop={10} onPress={() => setVer(!ver)}>
          <MaterialCommunityIcons name={ver ? 'eye-off-outline' : 'eye-outline'} size={26} color={cores.texto} />
        </Pressable>
      )}
    </View>
  );
}

/** Quadro de uma área (Alimentação, Mercados…) na cor dela, com o ícone em branco e o nome embaixo. */
export function QuadroArea({ nome, icone, cor, largura, altura, onPress }: { nome: string; icone: string; cor: string; largura: number; altura: number; onPress: () => void }) {
  return (
    <Pressable accessibilityRole="button" accessibilityLabel={nome} onPress={onPress} style={({ pressed }) => [{ width: largura }, pressed && { opacity: 0.85 }]}>
      <Foto foto={null} icone={icone} cor={cor} largura={largura} altura={altura} raio={14} />
      <View style={estilos.quadroRotulo}>
        <Text style={estilos.quadroTexto} numberOfLines={1}>
          {nome}
        </Text>
      </View>
    </Pressable>
  );
}

export const estilos = StyleSheet.create({
  centro: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24 },
  bolha: { width: 84, height: 84, borderRadius: 28, backgroundColor: cores.fundoSuave, alignItems: 'center', justifyContent: 'center' },
  texto: { fontSize: fonte.normal, color: cores.texto, lineHeight: 26 },
  tituloLinha: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 24, marginBottom: 12 },
  titulo: { flex: 1, fontSize: fonte.grande, fontWeight: '900', color: cores.texto },
  tituloAcao: { flexDirection: 'row', alignItems: 'center', minHeight: 40 },
  tituloAcaoTexto: { fontSize: fonte.pequena, fontWeight: '800', color: cores.destaque },
  botao: {
    minHeight: TOQUE,
    borderRadius: 28,
    borderWidth: 2,
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
  },
  botaoTexto: { fontSize: fonte.media, fontWeight: '800' },
  cartao: { ...cartao, flexDirection: 'row', alignItems: 'center', gap: 12, minHeight: 92, marginBottom: 12, overflow: 'hidden' },
  cartaoNome: { fontSize: fonte.normal + 1, fontWeight: '900', color: cores.texto },
  cartaoSub: { fontSize: fonte.pequena, color: cores.textoSuave },
  enfeite: { position: 'absolute', backgroundColor: 'rgba(255,255,255,0.14)' },
  pilula: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    minHeight: 46,
    paddingHorizontal: 18,
    borderRadius: 23,
    borderWidth: 1,
    borderColor: cores.borda,
    backgroundColor: cores.cartao,
  },
  pilulaTexto: { fontSize: fonte.pequena, fontWeight: '800', color: cores.texto },
  menu: { ...cartao, flexDirection: 'row', alignItems: 'center', gap: 14, minHeight: 60, paddingHorizontal: 14, paddingVertical: 8, marginBottom: 10 },
  menuTexto: { fontSize: fonte.normal, fontWeight: '700' },
  campoCaixa: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    minHeight: TOQUE,
    borderWidth: 1.5,
    borderColor: cores.borda,
    borderRadius: 14,
    paddingHorizontal: 14,
    backgroundColor: cores.cartao,
  },
  campoTexto: { flex: 1, fontSize: fonte.normal, color: cores.texto, minHeight: 52 },
  quadroRotulo: { position: 'absolute', left: 0, right: 0, bottom: 0, paddingHorizontal: 8, paddingVertical: 6, backgroundColor: 'rgba(0,0,0,0.28)', borderBottomLeftRadius: 14, borderBottomRightRadius: 14 },
  quadroTexto: { color: '#fff', fontSize: 15, fontWeight: '800' },
  distancia: { flexDirection: 'row', alignItems: 'center', gap: 2, backgroundColor: cores.verdeClaro, borderRadius: 10, paddingHorizontal: 8, paddingVertical: 2 },
  distanciaTexto: { fontSize: 14, fontWeight: '800', color: cores.verde },
  bolinha: { width: 10, height: 10, borderRadius: 5, backgroundColor: cores.whatsapp },
  contatoTexto: { fontSize: 15, fontWeight: '800', color: cores.whatsapp },
  selo: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: cores.verdeClaro, borderRadius: 10, paddingHorizontal: 8, paddingVertical: 2 },
  seloTexto: { fontSize: 14, fontWeight: '800', color: cores.verde },
  etiqueta: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: cores.fundoSuave,
    borderRadius: 10,
    paddingHorizontal: 8,
    paddingVertical: 2,
  },
  etiquetaTexto: { fontSize: 14, fontWeight: '700', color: cores.verde },
  campo: {
    minHeight: TOQUE,
    borderWidth: 1.5,
    borderColor: cores.borda,
    borderRadius: 14,
    paddingHorizontal: 16,
    fontSize: fonte.media,
    color: cores.texto,
    backgroundColor: cores.cartao,
  },
  rotulo: { fontSize: fonte.normal, fontWeight: '800', color: cores.texto, marginBottom: 6, marginTop: 16 },
});
