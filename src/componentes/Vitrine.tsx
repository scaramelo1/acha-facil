import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { Pressable, ScrollView, StyleSheet, Text, useWindowDimensions, View } from 'react-native';

import { URL_PLANOS, type ProfissionalResumo } from '../api/cliente';
import { cores, fonte, RAIO, sombra } from '../tema';
import { Contato, Foto, SeloVerificado } from './ui';

/**
 * "Comércios em destaque": só quem paga o plano Destaque. Cartões brancos que rolam para o lado, com o espaço
 * da foto em cima (ícone da área enquanto o dono não põe foto) e a fita amarela.
 * No fim (ou sozinho, enquanto ninguém assinou), um cartão amarelo convida o dono a aparecer aqui.
 */
export function Vitrine({ itens }: { itens: ProfissionalResumo[] }) {
  const router = useRouter();
  const { width } = useWindowDimensions();
  // sem destaques pagos, o convite ocupa a largura toda
  const largura = itens.length ? Math.min(width * 0.62, 280) : width - 32;

  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      snapToInterval={largura + 12}
      decelerationRate="fast"
      contentContainerStyle={{ paddingHorizontal: 16, gap: 12, paddingTop: 2, paddingBottom: 10 }}
      style={{ marginHorizontal: -16 }}
    >
      {itens.map((p) => (
        <Pressable
          key={p.id}
          accessibilityRole="button"
          accessibilityLabel={`${p.destaque ? 'Destaque: ' : ''}${p.nome}, ${p.tipo}`}
          onPress={() => router.push(`/profissional/${p.id}`)}
          style={({ pressed }) => [estilos.cartao, { width: largura }, pressed && { opacity: 0.85 }]}
        >
          <View>
            <Foto foto={p.foto} icone={p.icone} cor={p.cor} largura={largura - 20} altura={118} />
            {p.destaque && (
              <View style={estilos.fita}>
                <MaterialCommunityIcons name="star-four-points" size={14} color={cores.verde} />
                <Text style={estilos.fitaTexto}>Destaque</Text>
              </View>
            )}
          </View>
          <Text style={estilos.nome} numberOfLines={2}>
            {p.nome}
          </Text>
          <Text style={estilos.tipo} numberOfLines={1}>
            {p.tipo}
            {p.bairro ? ` · ${p.bairro}` : ''}
          </Text>
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 8, marginTop: 'auto' }}>
            {p.verificado && <SeloVerificado compacto />}
            <Contato p={p} />
          </View>
        </Pressable>
      ))}

      <Pressable
        accessibilityRole="button"
        accessibilityLabel="Seu comércio aqui. Ver planos"
        onPress={() => WebBrowser.openBrowserAsync(URL_PLANOS)}
        style={({ pressed }) => [estilos.cartao, estilos.anuncie, { width: largura }, pressed && { opacity: 0.9 }]}
      >
        <View style={estilos.anuncieIcone}>
          <MaterialCommunityIcons name="storefront-outline" size={40} color={cores.verde} />
        </View>
        <Text style={estilos.anuncieTitulo}>Seu comércio aqui</Text>
        <Text style={estilos.anuncieTexto}>Apareça em destaque para toda a cidade.</Text>
        <View style={estilos.anuncieBotao}>
          <Text style={estilos.anuncieBotaoTexto}>Ver planos</Text>
          <MaterialCommunityIcons name="chevron-right" size={20} color="#fff" />
        </View>
      </Pressable>
    </ScrollView>
  );
}

const estilos = StyleSheet.create({
  cartao: { backgroundColor: cores.cartao, borderRadius: RAIO, padding: 10, gap: 4, minHeight: 250, ...sombra },
  fita: {
    position: 'absolute',
    top: 8,
    left: 8,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: cores.amarelo,
    borderRadius: 10,
    paddingHorizontal: 10,
    paddingVertical: 3,
  },
  fitaTexto: { color: cores.verde, fontSize: 14, fontWeight: '900' },
  nome: { color: cores.texto, fontSize: fonte.normal, fontWeight: '900', marginTop: 6 },
  tipo: { color: cores.textoSuave, fontSize: fonte.pequena },
  anuncie: { backgroundColor: cores.amarelo, alignItems: 'center', justifyContent: 'center', padding: 16, gap: 6 },
  anuncieIcone: { width: 72, height: 72, borderRadius: 36, backgroundColor: '#fff', alignItems: 'center', justifyContent: 'center', marginBottom: 4 },
  anuncieTitulo: { color: cores.verde, fontSize: fonte.media, fontWeight: '900', textAlign: 'center' },
  anuncieTexto: { color: cores.verde, fontSize: fonte.pequena, textAlign: 'center' },
  anuncieBotao: { flexDirection: 'row', alignItems: 'center', gap: 2, backgroundColor: cores.verde, borderRadius: 14, paddingLeft: 14, paddingRight: 8, minHeight: 44, marginTop: 6 },
  anuncieBotaoTexto: { color: '#fff', fontSize: fonte.pequena, fontWeight: '800' },
});
