import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { chamar, URL_MEU_NEGOCIO, type Categoria, type ProfissionalResumo } from '../../api/cliente';
import { useCidade } from '../../cidade';
import { PertoDeVoce } from '../../componentes/PertoDeVoce';
import { Vitrine } from '../../componentes/Vitrine';
import { CartaoProfissional, Carregando, corClara, Falha, Icone, Titulo, useCarregar } from '../../componentes/ui';
import { cores, ESPACO_BARRA, fonte, RAIO, sombra } from '../../tema';

interface Inicio {
  destaques: ProfissionalResumo[];
  categorias: Categoria[];
  populares: ProfissionalResumo[];
  total: number;
}

// Nome curto das áreas nos quadradinhos da tela inicial (o nome completo aparece na tela da área)
const CURTO: Record<string, string> = { mercados: 'Mercados', casa: 'Casa', lojas: 'Lojas', esporte: 'Esporte', pet: 'Pet e agro' };

export default function TelaInicio() {
  const router = useRouter();
  const { cidade, trocarCidade } = useCidade();
  const { dados, erro, carregando, recarregar } = useCarregar(() => chamar<Inicio>('inicio.php'), `inicio-${cidade.slug}`);
  const principais = (dados?.categorias.filter((c) => c.naInicial && c.total > 0) ?? []).slice(0, 6);

  return (
    <SafeAreaView style={{ flex: 1, backgroundColor: cores.fundo }} edges={['top']}>
      <ScrollView
        contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: ESPACO_BARRA }}
        refreshControl={<RefreshControl refreshing={carregando && !!dados} onRefresh={recarregar} />}
      >
        {/* marca + cidade */}
        <View style={estilos.topo}>
          <Image source={require('../../../assets/logo.png')} style={{ width: 40, height: 40 }} />
          <Text style={estilos.marca}>
            Acha <Text style={{ color: cores.amarelo }}>Fácil!</Text>
          </Text>
          <Pressable
            accessibilityRole="button"
            accessibilityLabel={`Cidade: ${cidade.nome}. Toque para trocar`}
            onPress={trocarCidade}
            style={({ pressed }) => [estilos.cidade, pressed && { opacity: 0.85 }]}
          >
            <MaterialCommunityIcons name="map-marker" size={18} color={cores.verde} />
            <Text style={estilos.cidadeTexto} numberOfLines={1}>
              {cidade.curto}
            </Text>
            <MaterialCommunityIcons name="chevron-down" size={20} color={cores.verde} />
          </Pressable>
        </View>

        <Text style={estilos.ola}>Olá, boas-vindas!</Text>
        <Text style={estilos.pergunta}>Encontre o que precisa, perto de você.</Text>

        <Pressable
          accessibilityRole="search"
          accessibilityLabel="Buscar comércios e serviços"
          onPress={() => router.push('/busca')}
          style={({ pressed }) => [estilos.busca, pressed && { opacity: 0.85 }]}
        >
          <MaterialCommunityIcons name="magnify" size={28} color={cores.texto} />
          <Text style={estilos.buscaTexto}>Buscar comércios e serviços</Text>
        </Pressable>

        {/* faixa verde "Explore o melhor da cidade" com desenho de lojinhas feito com ícones */}
        <Pressable accessibilityRole="button" onPress={() => router.push('/explorar')} style={({ pressed }) => [estilos.faixa, pressed && { opacity: 0.9 }]}>
          <View style={{ flex: 1 }}>
            <Text style={estilos.faixaTitulo}>Explore o melhor{'\n'}da cidade</Text>
            <View style={estilos.faixaTraco} />
          </View>
          <View style={estilos.desenho} accessible={false}>
            <MaterialCommunityIcons name="tree" size={34} color="#4CAF50" style={{ position: 'absolute', left: 0, bottom: 4 }} />
            <MaterialCommunityIcons name="home-city" size={46} color="#FFE7A3" style={{ position: 'absolute', right: 6, bottom: 4 }} />
            <MaterialCommunityIcons name="storefront" size={58} color={cores.amarelo} style={{ position: 'absolute', left: 24, bottom: 0 }} />
            <MaterialCommunityIcons name="church" size={34} color="#F3E5C8" style={{ position: 'absolute', right: 2, top: -2 }} />
          </View>
        </Pressable>

        {carregando && !dados ? (
          <View style={{ height: 260 }}>
            <Carregando />
          </View>
        ) : erro && !dados ? (
          <View style={{ height: 260 }}>
            <Falha mensagem={erro} tentar={recarregar} />
          </View>
        ) : dados ? (
          <>
            <Titulo acao="Ver todas" aoTocarAcao={() => router.push('/explorar')}>
              Categorias
            </Titulo>
            <View style={estilos.grade}>
              {principais.map((c) => (
                <Pressable
                  key={c.id}
                  accessibilityRole="button"
                  accessibilityLabel={`${c.nome}, ${c.total} lugares`}
                  onPress={() => router.push(`/categoria/${c.id}`)}
                  style={({ pressed }) => [estilos.area, pressed && { opacity: 0.85 }]}
                >
                  <View style={[estilos.areaIcone, { backgroundColor: corClara(c.cor) }]}>
                    <Icone nome={c.icone} tamanho={30} cor={c.cor} />
                  </View>
                  <Text style={estilos.areaNome} numberOfLines={1}>
                    {CURTO[c.slug] ?? c.nome}
                  </Text>
                </Pressable>
              ))}
            </View>

            {dados.destaques.length > 0 && (
              <>
                <Titulo>Em destaque</Titulo>
                <Vitrine itens={dados.destaques} destaque />
              </>
            )}

            <Titulo>Perto de você</Titulo>
            <PertoDeVoce
              semLocalizacao={
                dados.populares.length > 0 ? (
                  <>
                    <Titulo>Conhecidos na cidade</Titulo>
                    {dados.populares.slice(0, 6).map((p) => (
                      <CartaoProfissional key={p.id} p={p} />
                    ))}
                  </>
                ) : null
              }
            />

            {/* chamada para o dono do negócio */}
            <Pressable accessibilityRole="button" onPress={() => WebBrowser.openBrowserAsync(URL_MEU_NEGOCIO)} style={estilos.dono}>
              <MaterialCommunityIcons name="storefront-outline" size={36} color={cores.verde} />
              <View style={{ flex: 1 }}>
                <Text style={estilos.donoTitulo}>Tem um comércio aqui?</Text>
                <Text style={estilos.donoTexto}>Confira os planos para corrigir os dados e colocar fotos.</Text>
              </View>
              <MaterialCommunityIcons name="chevron-right" size={26} color={cores.verde} />
            </Pressable>
            <Text style={estilos.rodape}>
              {dados.total.toLocaleString('pt-BR')} comércios e serviços de {cidade.nome}
            </Text>
          </>
        ) : null}
      </ScrollView>
    </SafeAreaView>
  );
}

const estilos = StyleSheet.create({
  topo: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingTop: 8 },
  marca: { flex: 1, fontSize: fonte.grande, fontWeight: '900', color: cores.verde },
  cidade: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    maxWidth: '52%',
    minHeight: 44,
    backgroundColor: cores.amarelo,
    borderRadius: 16,
    paddingLeft: 12,
    paddingRight: 8,
  },
  cidadeTexto: { flexShrink: 1, fontSize: fonte.pequena, fontWeight: '800', color: cores.verde },
  ola: { color: cores.textoSuave, fontSize: fonte.normal, marginTop: 22 },
  pergunta: { color: cores.texto, fontSize: 30, fontWeight: '900', lineHeight: 36, marginTop: 2, marginBottom: 18 },
  busca: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    minHeight: 60,
    borderRadius: 30,
    paddingHorizontal: 20,
    backgroundColor: cores.cartao,
    borderWidth: 1,
    borderColor: cores.borda,
    ...sombra,
  },
  buscaTexto: { fontSize: fonte.normal, color: cores.textoSuave },
  faixa: { flexDirection: 'row', alignItems: 'center', backgroundColor: cores.verde, borderRadius: RAIO, padding: 20, marginTop: 18, minHeight: 120, overflow: 'hidden' },
  faixaTitulo: { color: '#fff', fontSize: fonte.grande, fontWeight: '900', lineHeight: 30 },
  faixaTraco: { width: 56, height: 5, borderRadius: 3, backgroundColor: cores.amarelo, marginTop: 10 },
  desenho: { width: 130, height: 84 },
  grade: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', rowGap: 12 },
  area: { width: '31.5%', minHeight: 108, borderRadius: RAIO, backgroundColor: cores.cartao, alignItems: 'center', justifyContent: 'center', padding: 8, gap: 10, ...sombra },
  areaIcone: { width: 56, height: 56, borderRadius: 16, alignItems: 'center', justifyContent: 'center' },
  areaNome: { fontSize: fonte.pequena, fontWeight: '800', color: cores.texto, textAlign: 'center' },
  dono: { flexDirection: 'row', alignItems: 'center', gap: 14, backgroundColor: cores.amarelo, borderRadius: RAIO, padding: 18, marginTop: 16 },
  donoTitulo: { color: cores.verde, fontSize: fonte.media, fontWeight: '900' },
  donoTexto: { color: cores.verde, fontSize: fonte.pequena },
  rodape: { textAlign: 'center', color: cores.textoSuave, fontSize: 14, marginTop: 20 },
});
