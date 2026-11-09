import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, useWindowDimensions, View } from 'react-native';

import type { ProfissionalResumo } from '../api/cliente';
import { cores, fonte, RAIO } from '../tema';
import { Foto } from './ui';

const ALTURA = 150;

/**
 * "Comércios em destaque": só quem paga o plano Destaque. Faixas largas que rolam para o lado, com a 1a foto
 * (ou a cor da área), o nome em branco e a setinha amarela, como no modelo.
 * No fim (ou sozinho, enquanto ninguém assinou), uma faixa amarela convida o dono a aparecer aqui.
 */
export function Vitrine({ itens }: { itens: ProfissionalResumo[] }) {
  const router = useRouter();
  const { width } = useWindowDimensions();
  // sem destaques pagos, o convite ocupa a largura toda
  const largura = itens.length ? Math.min(width * 0.84, 420) : width - 32;

  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      snapToInterval={largura + 12}
      decelerationRate="fast"
      contentContainerStyle={{ paddingHorizontal: 16, gap: 12 }}
      style={{ marginHorizontal: -16 }}
    >
      {itens.map((p) => (
        <Pressable
          key={p.id}
          accessibilityRole="button"
          accessibilityLabel={`Destaque: ${p.nome}, ${p.tipo}`}
          onPress={() => router.push(`/profissional/${p.id}`)}
          style={({ pressed }) => [{ width: largura, height: ALTURA }, pressed && { opacity: 0.9 }]}
        >
          {/* 1a foto da galeria; sem fotos, o logo (inteiro, fundo branco) ou a cor da área */}
          <Foto foto={p.capa ?? p.foto} cobrir={!!p.capa} icone={p.icone} cor={p.cor} largura={largura} altura={ALTURA} raio={RAIO} />
          <View style={estilos.fita}>
            <MaterialCommunityIcons name="star-four-points" size={14} color={cores.verde} />
            <Text style={estilos.fitaTexto}>Destaque</Text>
          </View>
          <View style={estilos.rodape}>
            <View style={{ flex: 1 }}>
              <Text style={estilos.nome} numberOfLines={1}>
                {p.nome}
              </Text>
              <Text style={estilos.tipo} numberOfLines={1}>
                {p.tipo}
              </Text>
            </View>
            <View style={estilos.seta}>
              <MaterialCommunityIcons name="arrow-right" size={22} color={cores.verde} />
            </View>
          </View>
        </Pressable>
      ))}

      <Pressable
        accessibilityRole="button"
        accessibilityLabel="Seu comércio aqui. Saiba mais"
        onPress={() => router.push('/planos')}
        style={({ pressed }) => [estilos.anuncie, { width: largura }, pressed && { opacity: 0.9 }]}
      >
        <View style={estilos.anuncieIcone}>
          <MaterialCommunityIcons name="storefront-outline" size={38} color={cores.verde} />
        </View>
        <View style={{ flex: 1 }}>
          <Text style={estilos.anuncieTitulo}>Seu comércio aqui</Text>
          <Text style={estilos.anuncieTexto}>Apareça em destaque para toda a cidade.</Text>
          <View style={estilos.anuncieBotao}>
            <Text style={estilos.anuncieBotaoTexto}>Saiba mais</Text>
            <MaterialCommunityIcons name="chevron-right" size={20} color="#fff" />
          </View>
        </View>
      </Pressable>
    </ScrollView>
  );
}

const estilos = StyleSheet.create({
  fita: {
    position: 'absolute',
    top: 10,
    left: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: cores.amarelo,
    borderRadius: 10,
    paddingHorizontal: 10,
    paddingVertical: 3,
  },
  fitaTexto: { color: cores.verde, fontSize: 14, fontWeight: '900' },
  rodape: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 14,
    paddingVertical: 10,
    backgroundColor: 'rgba(0,0,0,0.35)',
    borderBottomLeftRadius: RAIO,
    borderBottomRightRadius: RAIO,
  },
  nome: { color: '#fff', fontSize: fonte.grande, fontWeight: '900' },
  tipo: { color: '#fff', fontSize: 15, fontWeight: '600' },
  seta: { width: 40, height: 40, borderRadius: 20, backgroundColor: cores.amarelo, alignItems: 'center', justifyContent: 'center' },
  anuncie: { height: ALTURA, flexDirection: 'row', alignItems: 'center', gap: 14, backgroundColor: cores.amarelo, borderRadius: RAIO, paddingHorizontal: 16 },
  anuncieIcone: { width: 68, height: 68, borderRadius: 34, backgroundColor: '#fff', alignItems: 'center', justifyContent: 'center' },
  anuncieTitulo: { color: cores.verde, fontSize: fonte.media, fontWeight: '900' },
  anuncieTexto: { color: cores.verde, fontSize: fonte.pequena },
  anuncieBotao: { alignSelf: 'flex-start', flexDirection: 'row', alignItems: 'center', gap: 2, backgroundColor: cores.verde, borderRadius: 22, paddingLeft: 14, paddingRight: 8, minHeight: 44, marginTop: 8 },
  anuncieBotaoTexto: { color: '#fff', fontSize: fonte.pequena, fontWeight: '800' },
});
