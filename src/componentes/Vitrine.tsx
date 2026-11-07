import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, useWindowDimensions, View } from 'react-native';

import type { ProfissionalResumo } from '../api/cliente';
import { cores, fonte, RAIO, sombra } from '../tema';
import { Avatar, SeloVerificado } from './ui';

/**
 * Fileira de cartões que rola para o lado, com o próximo aparecendo na beirada (mostra que tem mais).
 * `destaque`: cartões amarelos da vitrine paga. Sem ele: cartões brancos ("Conhecidos na cidade").
 */
export function Vitrine({ itens, destaque }: { itens: ProfissionalResumo[]; destaque?: boolean }) {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const largura = Math.min(width * (destaque ? 0.8 : 0.62), 340);
  if (itens.length === 0) return null;

  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      snapToInterval={largura + 12}
      decelerationRate="fast"
      contentContainerStyle={{ paddingHorizontal: 16, gap: 12, paddingBottom: 8 }}
      style={{ marginHorizontal: -16 }}
    >
      {itens.map((p) => (
        <Pressable
          key={p.id}
          accessibilityRole="button"
          accessibilityLabel={`${destaque ? 'Destaque: ' : ''}${p.nome}, ${p.tipo}`}
          onPress={() => router.push(`/profissional/${p.id}`)}
          style={({ pressed }) => [
            estilos.cartao,
            { width: largura, backgroundColor: destaque ? cores.amarelo : cores.cartao },
            pressed && { transform: [{ translateY: 2 }] },
          ]}
        >
          <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
            <Avatar foto={p.foto} icone={p.icone} cor={destaque ? cores.verde : p.cor} tamanho={destaque ? 64 : 52} />
            {destaque ? (
              <View style={estilos.faixa}>
                <MaterialCommunityIcons name="star-four-points" size={14} color={cores.amarelo} />
                <Text style={estilos.faixaTexto}>DESTAQUE</Text>
              </View>
            ) : (
              <View style={[estilos.ponto, { backgroundColor: p.cor }]} />
            )}
          </View>
          <Text style={[estilos.nome, destaque && { fontSize: fonte.grande }]} numberOfLines={2}>
            {p.nome}
          </Text>
          <Text style={estilos.tipo} numberOfLines={1}>
            {p.tipo}
            {p.bairro ? ` · ${p.bairro}` : ''}
          </Text>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 'auto' }}>
            {p.verificado && <SeloVerificado compacto />}
            {p.temWhatsapp && (
              <View style={estilos.whats}>
                <MaterialCommunityIcons name="whatsapp" size={16} color="#fff" />
                <Text style={estilos.whatsTexto}>WhatsApp</Text>
              </View>
            )}
          </View>
        </Pressable>
      ))}
    </ScrollView>
  );
}

const estilos = StyleSheet.create({
  cartao: { borderRadius: RAIO, padding: 16, gap: 6, minHeight: 190, ...sombra },
  faixa: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: cores.verde, borderRadius: 10, paddingHorizontal: 10, paddingVertical: 4 },
  faixaTexto: { color: cores.amarelo, fontSize: 13, fontWeight: '900', letterSpacing: 1 },
  ponto: { width: 14, height: 14, borderRadius: 7 },
  nome: { color: cores.texto, fontSize: fonte.media, fontWeight: '900', marginTop: 6 },
  tipo: { color: cores.textoSuave, fontSize: fonte.pequena },
  whats: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: cores.whatsapp, borderRadius: 10, paddingHorizontal: 8, paddingVertical: 3 },
  whatsTexto: { color: '#fff', fontSize: 14, fontWeight: '800' },
});
