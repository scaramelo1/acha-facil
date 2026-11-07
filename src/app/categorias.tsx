import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { FlatList, Pressable, Text, View } from 'react-native';

import { chamar, type Categoria } from '../api/cliente';
import { Carregando, Falha, Icone, useCarregar } from '../componentes/ui';
import { cores, fonte } from '../tema';

export default function TelaCategorias() {
  const router = useRouter();
  const { dados, erro, carregando, recarregar } = useCarregar(
    () => chamar<{ categorias: Categoria[] }>('inicio.php').then((r) => r.categorias),
    'categorias',
  );
  if (carregando && !dados) return <Carregando />;
  if (erro && !dados) return <Falha mensagem={erro} tentar={recarregar} />;
  return (
    <FlatList
      data={dados ?? []}
      keyExtractor={(c) => String(c.id)}
      contentContainerStyle={{ padding: 16 }}
      renderItem={({ item: c }) => (
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={`${c.nome}, ${c.total} cadastrados`}
          onPress={() => router.push(`/categoria/${c.id}`)}
          style={({ pressed }) => ({
            flexDirection: 'row',
            alignItems: 'center',
            gap: 16,
            minHeight: 68,
            paddingHorizontal: 12,
            borderBottomWidth: 1,
            borderColor: cores.borda,
            backgroundColor: pressed ? cores.fundoSuave : cores.fundo,
          })}
        >
          <Icone nome={c.icone} tamanho={32} />
          <View style={{ flex: 1 }}>
            <Text style={{ fontSize: fonte.media, fontWeight: '700', color: cores.texto }}>{c.nome}</Text>
            <Text style={{ fontSize: fonte.pequena, color: cores.textoSuave }}>
              {c.total === 0 ? 'Em breve' : c.total === 1 ? '1 cadastrado' : `${c.total} cadastrados`}
            </Text>
          </View>
          <MaterialCommunityIcons name="chevron-right" size={30} color={cores.textoSuave} />
        </Pressable>
      )}
    />
  );
}
