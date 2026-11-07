import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import { useCallback, useEffect, useRef, useState, type ComponentProps, type ReactNode } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';

import type { ProfissionalResumo } from '../api/cliente';
import { cores, fonte, RAIO, sombra, TOQUE } from '../tema';

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
}: {
  titulo: string;
  icone?: NomeIcone;
  onPress: () => void;
  tipo?: TipoBotao;
  desativado?: boolean;
  estilo?: StyleProp<ViewStyle>;
}) {
  const fundo = { principal: cores.verde, amarelo: cores.amarelo, whatsapp: cores.whatsapp, contorno: cores.cartao, perigo: cores.cartao }[tipo];
  const cor = { principal: '#fff', amarelo: cores.verde, whatsapp: '#fff', contorno: cores.verde, perigo: cores.erro }[tipo];
  const borda = tipo === 'perigo' ? cores.erro : tipo === 'contorno' ? cores.borda : 'transparent';
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
 * Espaço da foto do comércio (retângulo arredondado). Sem foto (o dono coloca depois, pelo plano),
 * mostra o ícone da área sobre a cor clarinha da área.
 */
export function Foto({ foto, icone, cor = cores.verde, largura = 104, altura = 88 }: { foto: string | null; icone: string; cor?: string; largura?: number; altura?: number }) {
  const forma = { width: largura, height: altura, borderRadius: 14 };
  if (foto) return <Image source={foto} style={[forma, { backgroundColor: cores.fundoSuave }]} contentFit="cover" />;
  return (
    <View style={[forma, { backgroundColor: corClara(cor), alignItems: 'center', justifyContent: 'center' }]}>
      <Icone nome={icone} tamanho={Math.min(largura, altura) * 0.42} cor={cor} />
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
      <Foto foto={p.foto} icone={p.icone} cor={p.cor} />
      <View style={{ flex: 1, gap: 3 }}>
        <Text style={estilos.cartaoNome} numberOfLines={2}>
          {p.nome}
        </Text>
        <Text style={estilos.cartaoSub} numberOfLines={1}>
          {p.tipo}
          {p.bairro ? ` · ${p.bairro}` : ''}
        </Text>
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 6, marginTop: 2 }}>
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
              <MaterialCommunityIcons name="star" size={18} color={cores.estrela} />
              <Text style={estilos.cartaoSub}>{p.nota.toFixed(1).replace('.', ',')}</Text>
            </View>
          )}
          <Contato p={p} />
        </View>
      </View>
      <View style={estilos.seta}>
        <MaterialCommunityIcons name="chevron-right" size={24} color={cores.verde} />
      </View>
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

/** Título de seção com um tracinho amarelo na frente e, se tiver, um link "Ver tudo" à direita. */
export function Titulo({ children, acao, aoTocarAcao }: { children: ReactNode; acao?: string; aoTocarAcao?: () => void }) {
  return (
    <View style={estilos.tituloLinha}>
      <View style={estilos.tracinho} />
      <Text style={estilos.titulo}>{children}</Text>
      {acao && aoTocarAcao && (
        <Pressable accessibilityRole="button" onPress={aoTocarAcao} hitSlop={12} style={estilos.tituloAcao}>
          <Text style={estilos.tituloAcaoTexto}>{acao}</Text>
          <MaterialCommunityIcons name="chevron-right" size={22} color={cores.destaque} />
        </Pressable>
      )}
    </View>
  );
}

export const estilos = StyleSheet.create({
  centro: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24 },
  bolha: { width: 84, height: 84, borderRadius: 28, backgroundColor: cores.fundoSuave, alignItems: 'center', justifyContent: 'center' },
  texto: { fontSize: fonte.normal, color: cores.texto, lineHeight: 26 },
  tituloLinha: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 26, marginBottom: 12 },
  tracinho: { width: 6, height: 26, borderRadius: 3, backgroundColor: cores.amarelo },
  titulo: { flex: 1, fontSize: fonte.grande, fontWeight: '900', color: cores.texto },
  tituloAcao: { flexDirection: 'row', alignItems: 'center', minHeight: 40 },
  tituloAcaoTexto: { fontSize: fonte.pequena, fontWeight: '800', color: cores.destaque },
  botao: {
    minHeight: TOQUE,
    borderRadius: 16,
    borderWidth: 2,
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
  },
  botaoTexto: { fontSize: fonte.media, fontWeight: '800' },
  cartao: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    padding: 10,
    paddingRight: 12,
    borderRadius: RAIO,
    backgroundColor: cores.cartao,
    marginBottom: 12,
    ...sombra,
  },
  cartaoNome: { fontSize: fonte.normal + 1, fontWeight: '800', color: cores.texto },
  cartaoSub: { fontSize: fonte.pequena, color: cores.textoSuave },
  seta: { width: 36, height: 36, borderRadius: 18, backgroundColor: cores.fundoSuave, alignItems: 'center', justifyContent: 'center' },
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
    borderWidth: 2,
    borderColor: cores.borda,
    borderRadius: 18,
    paddingHorizontal: 16,
    fontSize: fonte.media,
    color: cores.texto,
    backgroundColor: cores.cartao,
  },
  rotulo: { fontSize: fonte.normal, fontWeight: '800', color: cores.texto, marginBottom: 6, marginTop: 16 },
});
