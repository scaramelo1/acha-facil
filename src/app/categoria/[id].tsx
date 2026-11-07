import { Stack, useLocalSearchParams } from 'expo-router';
import { FlatList } from 'react-native';

import { chamar, type ProfissionalResumo } from '../../api/cliente';
import { CartaoProfissional, Carregando, Falha, Vazio, useCarregar } from '../../componentes/ui';

interface Resposta {
  categoria: { id: number; nome: string; icone: string };
  profissionais: ProfissionalResumo[];
}

export default function TelaCategoria() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { dados, erro, carregando, recarregar } = useCarregar(() => chamar<Resposta>(`categoria.php?id=${id}`), `categoria-${id}`);
  return (
    <>
      <Stack.Screen options={{ title: dados?.categoria.nome ?? '' }} />
      {carregando && !dados ? (
        <Carregando />
      ) : erro && !dados ? (
        <Falha mensagem={erro} tentar={recarregar} />
      ) : (
        <FlatList
          data={dados?.profissionais ?? []}
          keyExtractor={(p) => String(p.id)}
          contentContainerStyle={{ padding: 16, flexGrow: 1 }}
          refreshing={carregando}
          onRefresh={recarregar}
          renderItem={({ item }) => <CartaoProfissional p={item} />}
          ListEmptyComponent={<Vazio icone="account-search-outline" texto="Ainda não há profissionais nesta categoria. Volte em breve!" />}
        />
      )}
    </>
  );
}
