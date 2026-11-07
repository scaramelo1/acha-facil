import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { chamar, type Categoria, type ProfissionalResumo } from '../../api/cliente';
import { Vitrine } from '../../componentes/Vitrine';
import { Carregando, Falha, Icone, Titulo, useCarregar } from '../../componentes/ui';
import { cores, fonte, TOQUE } from '../../tema';

interface Inicio {
  destaques: ProfissionalResumo[];
  categorias: Categoria[];
}

export default function TelaInicio() {
  const router = useRouter();
  const { dados, erro, carregando, recarregar } = useCarregar(() => chamar<Inicio>('inicio.php'), 'inicio');
  const principais = dados?.categorias.filter((c) => c.naInicial) ?? [];

  return (
    <SafeAreaView style={{ flex: 1, backgroundColor: cores.fundo }} edges={['top']}>
      <View style={estilos.topo}>
        <Image source={require('../../../assets/logo.png')} style={{ width: 48, height: 48 }} />
        <View style={{ flex: 1 }}>
          <Text style={estilos.marca}>Acha Fácil</Text>
          <Text style={estilos.lema}>Profissionais de São João</Text>
        </View>
      </View>

      <ScrollView
        contentContainerStyle={{ padding: 16, paddingBottom: 40 }}
        refreshControl={<RefreshControl refreshing={carregando && !!dados} onRefresh={recarregar} />}
      >
        <Pressable
          accessibilityRole="search"
          accessibilityLabel="Buscar profissional ou serviço"
          onPress={() => router.push('/busca')}
          style={estilos.busca}
        >
          <MaterialCommunityIcons name="magnify" size={28} color={cores.textoSuave} />
          <Text style={estilos.buscaTexto}>O que você precisa?</Text>
        </Pressable>

        {carregando && !dados ? (
          <View style={{ height: 300 }}>
            <Carregando />
          </View>
        ) : erro && !dados ? (
          <View style={{ height: 300 }}>
            <Falha mensagem={erro} tentar={recarregar} />
          </View>
        ) : dados ? (
          <>
            {dados.destaques.length > 0 && (
              <View style={{ marginTop: 20 }}>
                <Vitrine itens={dados.destaques} />
              </View>
            )}

            <Titulo>Escolha o serviço</Titulo>
            <View style={estilos.grade}>
              {principais.map((c) => (
                <Pressable
                  key={c.id}
                  accessibilityRole="button"
                  accessibilityLabel={c.nome}
                  onPress={() => router.push(`/categoria/${c.id}`)}
                  style={({ pressed }) => [estilos.categoria, pressed && { backgroundColor: cores.borda }]}
                >
                  <Icone nome={c.icone} tamanho={36} />
                  <Text style={estilos.categoriaTexto} numberOfLines={2}>
                    {c.nome}
                  </Text>
                </Pressable>
              ))}
            </View>
            <Pressable
              accessibilityRole="button"
              onPress={() => router.push('/categorias')}
              style={({ pressed }) => [estilos.todas, pressed && { opacity: 0.8 }]}
            >
              <MaterialCommunityIcons name="view-grid-outline" size={26} color="#fff" />
              <Text style={estilos.todasTexto}>Ver todas as categorias</Text>
            </Pressable>
          </>
        ) : null}
      </ScrollView>
    </SafeAreaView>
  );
}

const estilos = StyleSheet.create({
  topo: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 16, paddingVertical: 8 },
  marca: { fontSize: fonte.media, fontWeight: '800', color: cores.texto },
  lema: { fontSize: 14, color: cores.textoSuave },
  busca: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    minHeight: 60,
    borderRadius: 16,
    borderWidth: 2,
    borderColor: cores.borda,
    paddingHorizontal: 16,
    backgroundColor: cores.fundoSuave,
  },
  buscaTexto: { fontSize: fonte.media, color: cores.textoSuave },
  grade: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', rowGap: 12 },
  categoria: {
    width: '48.5%',
    minHeight: 110,
    borderRadius: 16,
    backgroundColor: cores.fundoSuave,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 10,
    gap: 8,
  },
  categoriaTexto: { fontSize: fonte.normal, fontWeight: '700', color: cores.texto, textAlign: 'center' },
  todas: {
    marginTop: 16,
    minHeight: TOQUE,
    borderRadius: 14,
    backgroundColor: cores.verde,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
  },
  todasTexto: { color: '#fff', fontSize: fonte.media, fontWeight: '700' },
});
