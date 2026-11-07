import { useRouter } from 'expo-router';
import { FlatList, Pressable, StyleSheet, Text, useWindowDimensions, View } from 'react-native';

import { chamar, type Categoria } from '../../api/cliente';
import { useCidade } from '../../cidade';
import { Cabecalho } from '../../componentes/Cabecalho';
import { Carregando, Falha, Foto, useCarregar } from '../../componentes/ui';
import { cartao, cores, ESPACO_BARRA, fonte } from '../../tema';

// Todas as áreas em duas colunas: cartão branco com a cor e o ícone da área em cima, o nome e quantos lugares tem
export default function TelaAreas() {
  const router = useRouter();
  const { cidade } = useCidade();
  const { width } = useWindowDimensions();
  const largura = (width - 32 - 12) / 2;
  const { dados, erro, carregando, recarregar } = useCarregar(
    () => chamar<{ categorias: Categoria[] }>('inicio.php').then((r) => r.categorias),
    `areas-${cidade.slug}`,
  );

  return (
    <FlatList
      data={carregando && !dados ? [] : (dados ?? [])}
      keyExtractor={(c) => String(c.id)}
      numColumns={2}
      columnWrapperStyle={{ gap: 12, paddingHorizontal: 16 }}
      contentContainerStyle={{ gap: 12, paddingBottom: ESPACO_BARRA }}
      refreshing={carregando && !!dados}
      onRefresh={recarregar}
      ListHeaderComponent={
        <View style={{ marginBottom: 4 }}>
          <Cabecalho titulo="Categorias" busca={{ texto: 'Buscar comércios ou serviços', aoTocar: () => router.push('/busca') }} />
          {carregando && !dados ? (
            <View style={{ height: 300 }}>
              <Carregando />
            </View>
          ) : erro && !dados ? (
            <View style={{ height: 300 }}>
              <Falha mensagem={erro} tentar={recarregar} />
            </View>
          ) : null}
        </View>
      }
      renderItem={({ item: c }) => (
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={`${c.nome}, ${c.total} lugares`}
          onPress={() => router.push(`/categoria/${c.id}`)}
          style={({ pressed }) => [estilos.area, { width: largura }, pressed && { opacity: 0.85 }]}
        >
          <Foto foto={null} icone={c.icone} cor={c.cor} largura={largura - 2} altura={96} raio={0} />
          <View style={{ padding: 10 }}>
            <Text style={estilos.nome} numberOfLines={1}>
              {c.nome}
            </Text>
            <Text style={estilos.total}>{c.total === 0 ? 'Em breve' : c.total === 1 ? '1 lugar' : `${c.total.toLocaleString('pt-BR')} lugares`}</Text>
          </View>
        </Pressable>
      )}
    />
  );
}

const estilos = StyleSheet.create({
  area: { ...cartao, overflow: 'hidden' },
  nome: { fontSize: fonte.normal - 1, fontWeight: '900', color: cores.texto },
  total: { fontSize: 15, color: cores.textoSuave, fontWeight: '600', marginTop: 2 },
});
