import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback } from 'react';
import { Alert, FlatList, Pressable, StyleSheet, Text, View } from 'react-native';

import { chamar, type ProfissionalResumo } from '../../api/cliente';
import { Cabecalho } from '../../componentes/Cabecalho';
import { Botao, Carregando, Falha, Foto, Vazio, useCarregar } from '../../componentes/ui';
import { useConta } from '../../conta';
import { cartao, cores, ESPACO_BARRA, fonte } from '../../tema';

export default function TelaFavoritos() {
  const { usuario } = useConta();
  const router = useRouter();
  const { dados, setDados, erro, carregando, recarregar } = useCarregar(
    () =>
      usuario
        ? chamar<{ profissionais: ProfissionalResumo[] }>('favoritos.php').then((r) => r.profissionais)
        : Promise.resolve([]),
    `salvos-${usuario?.id ?? 0}`,
  );
  // volta do perfil do comércio (onde pode ter tirado dos favoritos): atualiza a lista
  useFocusEffect(
    useCallback(() => {
      if (usuario) recarregar();
    }, [usuario, recarregar]),
  );

  const tirar = async (p: ProfissionalResumo) => {
    const antes = dados ?? [];
    setDados(antes.filter((x) => x.id !== p.id));
    try {
      await chamar('favoritos.php', 'POST', { id: p.id, salvar: false });
    } catch (e) {
      setDados(antes);
      Alert.alert('Não foi possível tirar', e instanceof Error ? e.message : '');
    }
  };

  const cabecalho = <Cabecalho titulo="Seus lugares preferidos" busca={{ texto: 'Buscar comércios ou serviços', aoTocar: () => router.push('/busca') }} />;

  if (!usuario)
    return (
      <View style={{ flex: 1 }}>
        {cabecalho}
        <View style={estilos.convite}>
          <CoracaoGrande />
          <Text style={estilos.conviteTexto}>Entre na sua conta para guardar seus lugares favoritos.</Text>
          <Botao titulo="Entrar com o Google" icone="google" tipo="amarelo" onPress={() => router.push('/entrar')} estilo={{ alignSelf: 'stretch' }} />
        </View>
      </View>
    );

  return (
    <FlatList
      data={dados ?? []}
      keyExtractor={(p) => String(p.id)}
      contentContainerStyle={{ paddingBottom: ESPACO_BARRA, flexGrow: 1 }}
      refreshing={carregando && !!dados}
      onRefresh={recarregar}
      ListHeaderComponent={<View style={{ marginBottom: 16 }}>{cabecalho}</View>}
      renderItem={({ item: p }) => (
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={`${p.nome}, ${p.tipo}`}
          onPress={() => router.push(`/profissional/${p.id}`)}
          style={({ pressed }) => [estilos.cartao, pressed && { opacity: 0.85 }]}
        >
          <Foto foto={p.foto} icone={p.icone} cor={p.cor} largura={112} altura={88} raio={0} />
          <View style={{ flex: 1 }}>
            <Text style={estilos.nome} numberOfLines={2}>
              {p.nome}
            </Text>
            <Text style={estilos.tipo} numberOfLines={1}>
              {p.tipo}
            </Text>
          </View>
          <Pressable accessibilityRole="button" accessibilityLabel={`Tirar ${p.nome} dos favoritos`} onPress={() => tirar(p)} hitSlop={8} style={estilos.coracao}>
            <MaterialCommunityIcons name="heart" size={30} color={cores.verde} />
          </Pressable>
        </Pressable>
      )}
      ListEmptyComponent={
        carregando && !dados ? (
          <Carregando />
        ) : erro && !dados ? (
          <Falha mensagem={erro} tentar={recarregar} />
        ) : (
          <Vazio icone="heart-outline" texto="Toque no coração de um comércio para guardar aqui." />
        )
      }
    />
  );
}

/** Coração amarelo com contorno verde e uns risquinhos de brilho em volta. */
function CoracaoGrande() {
  return (
    <View style={{ width: 190, height: 160, alignItems: 'center', justifyContent: 'center' }} accessible={false}>
      {[
        { top: 6, left: 120, rot: '30deg' },
        { top: 30, left: 150, rot: '60deg' },
        { top: 64, left: 158, rot: '90deg' },
        { top: 104, left: 22, rot: '-60deg' },
        { top: 126, left: 46, rot: '-30deg' },
      ].map((r, i) => (
        <View key={i} style={[estilos.risco, { top: r.top, left: r.left, transform: [{ rotate: r.rot }] }]} />
      ))}
      <MaterialCommunityIcons name="heart" size={150} color={cores.verde} style={{ position: 'absolute' }} />
      <MaterialCommunityIcons name="heart" size={112} color={cores.amarelo} style={{ position: 'absolute', top: 30 }} />
    </View>
  );
}

const estilos = StyleSheet.create({
  convite: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24, gap: 18 },
  conviteTexto: { fontSize: 26, fontWeight: '900', color: cores.verde, textAlign: 'center', lineHeight: 32 },
  risco: { position: 'absolute', width: 6, height: 22, borderRadius: 3, backgroundColor: cores.amarelo },
  cartao: { ...cartao, flexDirection: 'row', alignItems: 'center', gap: 14, marginHorizontal: 16, marginBottom: 12, overflow: 'hidden' },
  nome: { fontSize: fonte.normal + 1, fontWeight: '900', color: cores.texto },
  tipo: { fontSize: fonte.pequena - 1, color: cores.textoSuave, marginTop: 2 },
  coracao: { width: 52, height: 52, alignItems: 'center', justifyContent: 'center', marginRight: 6 },
});
