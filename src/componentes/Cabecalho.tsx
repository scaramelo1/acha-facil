import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { router } from 'expo-router';
import type { ReactNode } from 'react';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useCidade } from '../cidade';
import { cores, fonte } from '../tema';

const PAISAGEM = require('../../assets/ilustracoes/cabecalho.png');
const TRACO = require('../../assets/ilustracoes/traco.png');

/** Traço amarelo (pincelada) embaixo da marca e dos títulos. */
export function Traco({ largura = 96 }: { largura?: number }) {
  return <Image source={TRACO} style={{ width: largura, height: largura / 10 }} contentFit="contain" accessible={false} />;
}

/** "Acha Fácil!" em branco, inclinado, com o traço amarelo embaixo. */
export function Marca({ tamanho = 24 }: { tamanho?: number }) {
  return (
    <View accessibilityRole="header" accessibilityLabel="Acha Fácil">
      <Text style={[estilos.marca, { fontSize: tamanho }]}>Acha Fácil!</Text>
      <View style={{ marginTop: -3 }}>
        <Traco largura={tamanho * 3.9} />
      </View>
    </View>
  );
}

/** Pílula com a cidade escolhida (contorno branco); tocar abre a troca de cidade. */
export function PilulaCidade() {
  const { cidade, trocarCidade } = useCidade();
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`Cidade: ${cidade.nome}. Toque para trocar`}
      onPress={trocarCidade}
      hitSlop={6}
      style={({ pressed }) => [estilos.cidade, pressed && { opacity: 0.8 }]}
    >
      <MaterialCommunityIcons name="map-marker" size={17} color="#fff" />
      <Text style={estilos.cidadeTexto} numberOfLines={1}>
        {cidade.nome}
      </Text>
      <MaterialCommunityIcons name="chevron-down" size={18} color="#fff" />
    </Pressable>
  );
}

type Busca =
  | { texto: string; aoTocar: () => void }
  | { texto: string; valor: string; aoMudar: (t: string) => void; autoFocus?: boolean; aoEnviar?: () => void };

/**
 * Cabeçalho de todas as telas: fundo verde com a paisagem da cidade, marca e cidade em cima,
 * título grande com o traço amarelo e, se tiver, a busca amarela (botão que abre a busca, ou campo de digitar).
 * `voltar` mostra a seta; `semCidade` esconde a pílula da cidade (tela de escolher a cidade).
 */
export function Cabecalho({
  titulo,
  subtitulo,
  voltar,
  aoVoltar,
  busca,
  semCidade,
  children,
}: {
  titulo?: string;
  subtitulo?: string;
  voltar?: boolean;
  aoVoltar?: () => void;
  busca?: Busca;
  semCidade?: boolean;
  children?: ReactNode;
}) {
  const topo = useSafeAreaInsets().top;
  const sair = aoVoltar ?? (() => (router.canGoBack() ? router.back() : router.replace('/')));
  return (
    <View style={[estilos.caixa, { paddingTop: topo + 10, paddingBottom: titulo || busca ? 22 : 14 }]}>
      <Image source={PAISAGEM} style={StyleSheet.absoluteFill} contentFit="cover" contentPosition="right center" accessible={false} />
      <View style={estilos.linha}>
        {voltar && (
          <Pressable accessibilityRole="button" accessibilityLabel="Voltar" onPress={sair} hitSlop={10} style={estilos.voltar}>
            <MaterialCommunityIcons name="arrow-left" size={28} color="#fff" />
          </Pressable>
        )}
        <Marca />
        <View style={{ flex: 1 }} />
        {!semCidade && <PilulaCidade />}
      </View>

      {titulo ? (
        <View style={{ marginTop: 20, maxWidth: '78%' }}>
          <Text style={estilos.titulo} accessibilityRole="header">
            {titulo}
          </Text>
          <View style={{ marginTop: 4 }}>
            <Traco largura={88} />
          </View>
        </View>
      ) : null}
      {subtitulo ? <Text style={estilos.subtitulo}>{subtitulo}</Text> : null}
      {children}

      {busca && 'aoTocar' in busca ? (
        <Pressable
          accessibilityRole="search"
          accessibilityLabel={busca.texto}
          onPress={busca.aoTocar}
          style={({ pressed }) => [estilos.busca, pressed && { opacity: 0.9 }]}
        >
          <MaterialCommunityIcons name="magnify" size={26} color={cores.verde} />
          <Text style={estilos.buscaTexto} numberOfLines={1}>
            {busca.texto}
          </Text>
        </Pressable>
      ) : busca ? (
        <View style={estilos.busca}>
          <MaterialCommunityIcons name="magnify" size={26} color={cores.verde} />
          <TextInput
            value={busca.valor}
            onChangeText={busca.aoMudar}
            placeholder={busca.texto}
            placeholderTextColor="#2E5A3F"
            autoFocus={busca.autoFocus}
            returnKeyType="search"
            onSubmitEditing={busca.aoEnviar}
            style={estilos.buscaCampo}
            accessibilityLabel={busca.texto}
          />
          {busca.valor.length > 0 && (
            <Pressable accessibilityRole="button" accessibilityLabel="Limpar" hitSlop={10} onPress={() => busca.aoMudar('')}>
              <MaterialCommunityIcons name="close-circle" size={24} color={cores.verde} />
            </Pressable>
          )}
        </View>
      ) : null}
    </View>
  );
}

const estilos = StyleSheet.create({
  caixa: {
    backgroundColor: cores.verdeEscuro,
    paddingHorizontal: 16,
    borderBottomLeftRadius: 26,
    borderBottomRightRadius: 26,
    overflow: 'hidden',
  },
  linha: { flexDirection: 'row', alignItems: 'center', gap: 10, minHeight: 48 },
  voltar: { width: 40, height: 44, justifyContent: 'center' },
  marca: { color: '#fff', fontWeight: '900', fontStyle: 'italic', letterSpacing: -0.5 },
  cidade: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
    flexShrink: 1,
    maxWidth: '58%',
    minHeight: 40,
    paddingLeft: 10,
    paddingRight: 6,
    borderRadius: 20,
    borderWidth: 1.5,
    borderColor: 'rgba(255,255,255,0.75)',
    backgroundColor: 'rgba(0,0,0,0.18)',
  },
  cidadeTexto: { flexShrink: 1, color: '#fff', fontSize: 14, fontWeight: '700' },
  titulo: {
    color: '#fff',
    fontSize: fonte.titulo,
    fontWeight: '900',
    lineHeight: 35,
    textShadowColor: 'rgba(0,0,0,0.35)',
    textShadowRadius: 6,
    textShadowOffset: { width: 0, height: 1 },
  },
  subtitulo: { color: '#fff', fontSize: fonte.normal, fontWeight: '600', marginTop: 8 },
  busca: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    minHeight: 56,
    marginTop: 20,
    paddingHorizontal: 18,
    borderRadius: 28,
    backgroundColor: cores.amarelo,
  },
  buscaTexto: { flex: 1, fontSize: fonte.normal - 1, fontWeight: '600', color: cores.verde },
  buscaCampo: { flex: 1, fontSize: fonte.normal - 1, fontWeight: '600', color: cores.verde, minHeight: 52 },
});
