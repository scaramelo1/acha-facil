import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback } from 'react';
import { FlatList, View } from 'react-native';

import { chamar, type ProfissionalResumo } from '../../api/cliente';
import { Botao, CartaoProfissional, Carregando, Falha, Vazio, useCarregar } from '../../componentes/ui';
import { useConta } from '../../conta';
import { ESPACO_BARRA } from '../../tema';

export default function TelaSalvos() {
  const { usuario } = useConta();
  const router = useRouter();
  const { dados, erro, carregando, recarregar } = useCarregar(
    () =>
      usuario
        ? chamar<{ profissionais: ProfissionalResumo[] }>('favoritos.php').then((r) => r.profissionais)
        : Promise.resolve([]),
    `salvos-${usuario?.id ?? 0}`,
  );
  // volta do perfil (onde pode ter tirado dos salvos): atualiza a lista
  useFocusEffect(
    useCallback(() => {
      if (usuario) recarregar();
    }, [usuario, recarregar]),
  );

  if (!usuario)
    return (
      <View style={{ flex: 1, padding: 24 }}>
        <Vazio icone="heart-outline" texto="Entre na sua conta para guardar seus lugares favoritos." />
        <Botao titulo="Entrar" onPress={() => router.push('/entrar')} />
      </View>
    );
  if (carregando && !dados) return <Carregando />;
  if (erro && !dados) return <Falha mensagem={erro} tentar={recarregar} />;
  return (
    <FlatList
      data={dados ?? []}
      keyExtractor={(p) => String(p.id)}
      contentContainerStyle={{ padding: 16, paddingBottom: ESPACO_BARRA, flexGrow: 1 }}
      renderItem={({ item }) => <CartaoProfissional p={item} />}
      ListEmptyComponent={<Vazio icone="heart-outline" texto="Toque no coração de um comércio para guardar aqui." />}
    />
  );
}
