import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { chamar, URL_MEU_NEGOCIO, type Categoria, type ProfissionalResumo } from '../../api/cliente';
import { useBarraClara } from '../../componentes/BarraClara';
import { useCidade } from '../../cidade';
import { Vitrine } from '../../componentes/Vitrine';
import { Carregando, corClara, Falha, Icone, Titulo, useCarregar } from '../../componentes/ui';
import { cores, ESPACO_BARRA, fonte, RAIO, saudacao, sombra } from '../../tema';

interface Inicio {
  destaques: ProfissionalResumo[];
  categorias: Categoria[];
  populares: ProfissionalResumo[];
  total: number;
}

const SUGESTOES = ['Pizzaria', 'Farmácia', 'Mecânica', 'Pet shop', 'Padaria', 'Academia'];

export default function TelaInicio() {
  useBarraClara();
  const router = useRouter();
  const { cidade, trocarCidade } = useCidade();
  const { dados, erro, carregando, recarregar } = useCarregar(() => chamar<Inicio>('inicio.php'), `inicio-${cidade.slug}`);
  const principais = dados?.categorias.filter((c) => c.naInicial) ?? [];

  return (
    <View style={{ flex: 1, backgroundColor: cores.fundo }}>
      <ScrollView
        contentContainerStyle={{ paddingBottom: ESPACO_BARRA }}
        refreshControl={<RefreshControl refreshing={carregando && !!dados} onRefresh={recarregar} tintColor="#fff" />}
      >
        {/* Cabeçalho verde com saudação e busca amarela */}
        <View style={estilos.cabecalho}>
          <SafeAreaView edges={['top']}>
            <View style={estilos.marcaLinha}>
              <Image source={require('../../../assets/logo.png')} style={{ width: 40, height: 40 }} />
              <Text style={estilos.marca}>
                Acha <Text style={{ color: cores.amarelo }}>Fácil!</Text>
              </Text>
              <Pressable accessibilityRole="button" accessibilityLabel={`Cidade: ${cidade.nome}. Toque para trocar`} onPress={trocarCidade} style={estilos.cidade}>
                <MaterialCommunityIcons name="map-marker" size={16} color={cores.verde} />
                <Text style={estilos.cidadeTexto}>{cidade.curto}</Text>
                <MaterialCommunityIcons name="chevron-down" size={18} color={cores.verde} />
              </Pressable>
            </View>
            <Text style={estilos.ola}>{saudacao()}!</Text>
            <Text style={estilos.pergunta}>O que você procura hoje?</Text>
            <Pressable
              accessibilityRole="search"
              accessibilityLabel="Buscar comércio ou serviço"
              onPress={() => router.push('/busca')}
              style={({ pressed }) => [estilos.busca, pressed && { transform: [{ translateY: 2 }] }]}
            >
              <MaterialCommunityIcons name="magnify" size={30} color={cores.verde} />
              <Text style={estilos.buscaTexto} numberOfLines={1}>Buscar em {cidade.curto}</Text>
            </Pressable>
          </SafeAreaView>
        </View>

        <View style={{ paddingHorizontal: 16 }}>
          {/* atalhos de busca rápida */}
          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginHorizontal: -16, marginTop: 16 }} contentContainerStyle={{ paddingHorizontal: 16, gap: 8 }}>
            {SUGESTOES.map((s) => (
              <Pressable key={s} accessibilityRole="button" onPress={() => router.push({ pathname: '/busca', params: { q: s } })} style={estilos.sugestao}>
                <Text style={estilos.sugestaoTexto}>{s}</Text>
              </Pressable>
            ))}
          </ScrollView>

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
                <>
                  <Titulo>Em destaque</Titulo>
                  <Vitrine itens={dados.destaques} destaque />
                </>
              )}

              <Titulo acao="Todas" aoTocarAcao={() => router.push('/explorar')}>
                Áreas
              </Titulo>
              <View style={estilos.grade}>
                {principais.map((c) => (
                  <Pressable
                    key={c.id}
                    accessibilityRole="button"
                    accessibilityLabel={`${c.nome}, ${c.total} lugares`}
                    onPress={() => router.push(`/categoria/${c.id}`)}
                    style={({ pressed }) => [estilos.area, pressed && { transform: [{ translateY: 2 }] }]}
                  >
                    <View style={[estilos.areaIcone, { backgroundColor: corClara(c.cor) }]}>
                      <Icone nome={c.icone} tamanho={32} cor={c.cor} />
                    </View>
                    <Text style={estilos.areaNome} numberOfLines={2}>
                      {c.nome}
                    </Text>
                  </Pressable>
                ))}
              </View>

              {dados.populares.length > 0 && (
                <>
                  <Titulo>Conhecidos na cidade</Titulo>
                  <Vitrine itens={dados.populares} />
                </>
              )}

              {/* chamada para o dono do negócio */}
              <Pressable accessibilityRole="button" onPress={() => WebBrowser.openBrowserAsync(URL_MEU_NEGOCIO)} style={estilos.dono}>
                <View style={estilos.donoIcone}>
                  <MaterialCommunityIcons name="storefront-outline" size={34} color={cores.verde} />
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={estilos.donoTitulo}>Tem um comércio aqui?</Text>
                  <Text style={estilos.donoTexto}>Confira os planos para corrigir os dados e colocar fotos.</Text>
                </View>
                <MaterialCommunityIcons name="arrow-right" size={26} color={cores.amarelo} />
              </Pressable>
              <Text style={estilos.rodape}>{dados.total.toLocaleString('pt-BR')} comércios e serviços de {cidade.nome}</Text>
            </>
          ) : null}
        </View>
      </ScrollView>
    </View>
  );
}

const estilos = StyleSheet.create({
  cabecalho: { backgroundColor: cores.verde, paddingHorizontal: 16, paddingBottom: 24, borderBottomLeftRadius: 32, borderBottomRightRadius: 32 },
  marcaLinha: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingTop: 8 },
  marca: { flex: 1, fontSize: fonte.media, fontWeight: '900', color: '#fff' },
  cidade: { flexDirection: 'row', alignItems: 'center', gap: 2, minHeight: 40, backgroundColor: cores.amarelo, borderRadius: 14, paddingLeft: 10, paddingRight: 6 },
  cidadeTexto: { fontSize: 14, fontWeight: '800', color: cores.verde },
  ola: { color: '#CFE6D9', fontSize: fonte.normal, fontWeight: '700', marginTop: 20 },
  pergunta: { color: '#fff', fontSize: fonte.titulo, fontWeight: '900', lineHeight: 36, marginBottom: 16 },
  busca: { flexDirection: 'row', alignItems: 'center', gap: 10, minHeight: 64, borderRadius: 20, paddingHorizontal: 16, backgroundColor: cores.amarelo, ...sombra },
  buscaTexto: { fontSize: fonte.media, fontWeight: '800', color: cores.verde },
  sugestao: { minHeight: 44, justifyContent: 'center', paddingHorizontal: 16, borderRadius: 22, backgroundColor: cores.cartao, borderWidth: 2, borderColor: cores.borda },
  sugestaoTexto: { fontSize: fonte.pequena, fontWeight: '800', color: cores.texto },
  grade: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', rowGap: 12 },
  area: { width: '31.5%', minHeight: 116, borderRadius: RAIO, backgroundColor: cores.cartao, alignItems: 'center', justifyContent: 'center', padding: 8, gap: 8, ...sombra },
  areaIcone: { width: 56, height: 56, borderRadius: 18, alignItems: 'center', justifyContent: 'center' },
  areaNome: { fontSize: 15, fontWeight: '800', color: cores.texto, textAlign: 'center' },
  dono: { flexDirection: 'row', alignItems: 'center', gap: 14, backgroundColor: cores.verde, borderRadius: RAIO, padding: 16, marginTop: 28 },
  donoIcone: { width: 56, height: 56, borderRadius: 18, backgroundColor: cores.amarelo, alignItems: 'center', justifyContent: 'center' },
  donoTitulo: { color: '#fff', fontSize: fonte.media, fontWeight: '900' },
  donoTexto: { color: '#CFE6D9', fontSize: fonte.pequena },
  rodape: { textAlign: 'center', color: cores.textoSuave, fontSize: 14, marginTop: 20 },
});
