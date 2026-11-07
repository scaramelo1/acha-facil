import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';

import { chamar, type Categoria } from '../../api/cliente';
import { Carregando, corClara, Falha, Icone, useCarregar } from '../../componentes/ui';
import { useCidade } from '../../cidade';
import { cores, ESPACO_BARRA, fonte, RAIO, sombra } from '../../tema';

// Todas as áreas, em duas colunas, cada uma com a sua cor e quantos lugares tem
export default function TelaAreas() {
  const router = useRouter();
  const { cidade } = useCidade();
  const { dados, erro, carregando, recarregar } = useCarregar(
    () => chamar<{ categorias: Categoria[] }>('inicio.php').then((r) => r.categorias),
    `areas-${cidade.slug}`,
  );
  if (carregando && !dados) return <Carregando />;
  if (erro && !dados) return <Falha mensagem={erro} tentar={recarregar} />;
  return (
    <FlatList
      data={dados ?? []}
      keyExtractor={(c) => String(c.id)}
      numColumns={2}
      columnWrapperStyle={{ gap: 12 }}
      contentContainerStyle={{ padding: 16, gap: 12, paddingBottom: ESPACO_BARRA }}
      refreshing={carregando}
      onRefresh={recarregar}
      ListHeaderComponent={
        <Pressable accessibilityRole="search" onPress={() => router.push('/busca')} style={estilos.busca}>
          <MaterialCommunityIcons name="magnify" size={26} color={cores.verde} />
          <Text style={estilos.buscaTexto}>Buscar em {cidade.curto}</Text>
        </Pressable>
      }
      renderItem={({ item: c }) => (
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={`${c.nome}, ${c.total} lugares`}
          onPress={() => router.push(`/categoria/${c.id}`)}
          style={({ pressed }) => [estilos.area, { borderTopColor: c.cor }, pressed && { transform: [{ translateY: 2 }] }]}
        >
          <View style={[estilos.icone, { backgroundColor: corClara(c.cor) }]}>
            <Icone nome={c.icone} tamanho={30} cor={c.cor} />
          </View>
          <Text style={estilos.nome} numberOfLines={2}>
            {c.nome}
          </Text>
          <Text style={estilos.total}>{c.total === 0 ? 'Em breve' : c.total === 1 ? '1 lugar' : `${c.total.toLocaleString('pt-BR')} lugares`}</Text>
        </Pressable>
      )}
    />
  );
}

const estilos = StyleSheet.create({
  busca: { flexDirection: 'row', alignItems: 'center', gap: 10, minHeight: 58, borderRadius: 18, paddingHorizontal: 16, backgroundColor: cores.amarelo, marginBottom: 4 },
  buscaTexto: { fontSize: fonte.normal, fontWeight: '800', color: cores.verde },
  area: { flex: 1, minHeight: 150, borderRadius: RAIO, borderTopWidth: 6, backgroundColor: cores.cartao, padding: 14, gap: 6, ...sombra },
  icone: { width: 52, height: 52, borderRadius: 16, alignItems: 'center', justifyContent: 'center', marginBottom: 4 },
  nome: { fontSize: fonte.normal, fontWeight: '900', color: cores.texto },
  total: { fontSize: 15, color: cores.textoSuave, fontWeight: '700' },
});
