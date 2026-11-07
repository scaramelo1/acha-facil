import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { StyleSheet, Text, View } from 'react-native';

import { useCidade } from '../cidade';
import { cores, fonte, RAIO } from '../tema';
import { Botao } from './ui';

/** Cartão amarelo-claro "Tem um comércio em …?" com o botão verde que leva a procurar o próprio negócio. */
export function CartaoDono() {
  const router = useRouter();
  const { cidade } = useCidade();
  return (
    <View style={estilos.caixa}>
      <View style={{ flexDirection: 'row', gap: 12, alignItems: 'flex-start' }}>
        <MaterialCommunityIcons name="storefront-outline" size={52} color={cores.verde} />
        <View style={{ flex: 1 }}>
          <Text style={estilos.titulo}>Tem um comércio em {cidade.curto}?</Text>
          <Text style={estilos.texto}>Divulgue seu negócio no Acha Fácil! e seja encontrado por mais clientes da cidade.</Text>
        </View>
      </View>
      <Botao titulo="Procurar meu negócio" seta onPress={() => router.push('/meu-negocio')} estilo={{ marginTop: 14 }} />
    </View>
  );
}

const estilos = StyleSheet.create({
  caixa: { backgroundColor: cores.fundoSuave, borderRadius: RAIO, padding: 16, borderWidth: 1, borderColor: '#F3E3A0' },
  titulo: { color: cores.verde, fontSize: fonte.media, fontWeight: '900' },
  texto: { color: cores.texto, fontSize: fonte.pequena, marginTop: 4, lineHeight: 22 },
});
