import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { Image, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';

import { chamar, reais, type Categoria, type OfertaInicio, type ProfissionalResumo } from '../../api/cliente';
import { useCidade } from '../../cidade';
import { Cabecalho } from '../../componentes/Cabecalho';
import { CartaoDono } from '../../componentes/CartaoDono';
import { PertoDeVoce } from '../../componentes/PertoDeVoce';
import { Vitrine } from '../../componentes/Vitrine';
import { Carregando, Falha, Foto, QuadroArea, Titulo, useCarregar } from '../../componentes/ui';
import { cartao, cores, ESPACO_BARRA, fonte } from '../../tema';

interface Inicio {
  destaques: ProfissionalResumo[];
  categorias: Categoria[];
  populares: ProfissionalResumo[];
  /** 19/11: Ofertas da semana da cidade (servidor antigo nao manda). */
  ofertas?: OfertaInicio[];
  total: number;
}

// Nome curto das áreas nos quadros da tela inicial (o nome completo aparece na tela da área)
const CURTO: Record<string, string> = {
  mercados: 'Mercados',
  casa: 'Casa',
  lojas: 'Lojas',
  esporte: 'Esporte',
  pet: 'Pet e agro',
  hospedagem: 'Hospedagem',
  entidades: 'Igrejas',
};

export default function TelaInicio() {
  const router = useRouter();
  const { cidade } = useCidade();
  const { dados, erro, carregando, recarregar } = useCarregar(() => chamar<Inicio>('inicio.php'), `inicio-${cidade.slug}`);
  // as áreas marcadas para a inicial vêm primeiro; as outras com lugares vêm depois
  const areas = (dados?.categorias.filter((c) => c.total > 0) ?? []).sort((a, b) => Number(b.naInicial) - Number(a.naInicial));

  return (
    <ScrollView
      contentContainerStyle={{ paddingBottom: ESPACO_BARRA }}
      refreshControl={<RefreshControl refreshing={carregando && !!dados} onRefresh={recarregar} />}
    >
      <Cabecalho titulo="Descubra o melhor da cidade" busca={{ texto: 'Buscar comércios ou serviços', aoTocar: () => router.push('/busca') }} />

      <View style={{ paddingHorizontal: 16 }}>
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
            {/* 19/11: Ofertas da semana (beneficio pago do Destaque), so quando tem alguma no ar */}
            {(dados.ofertas?.length ?? 0) > 0 && (
              <>
                <Titulo>Ofertas da semana</Titulo>
                <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginHorizontal: -16 }} contentContainerStyle={{ paddingHorizontal: 16, gap: 10 }}>
                  {dados.ofertas!.map((o) => (
                    <Pressable
                      key={o.id}
                      accessibilityRole="button"
                      accessibilityLabel={`Oferta: ${o.titulo}, ${o.nome}`}
                      onPress={() => router.push(`/profissional/${o.id}?origem=inicio`)}
                      style={({ pressed }) => [estilos.oferta, pressed && { opacity: 0.85 }]}
                    >
                      {/* 19/11 (pedido do usuario): foto quadrada maior e sem a data de fim */}
                      {o.foto ? (
                        <Image source={{ uri: o.foto }} style={estilos.ofertaFoto} resizeMode="cover" accessibilityIgnoresInvertColors />
                      ) : (
                        <View style={[estilos.ofertaFoto, estilos.ofertaSemFoto]}>
                          <MaterialCommunityIcons name="tag-outline" size={48} color={cores.verde} />
                        </View>
                      )}
                      <Text style={estilos.ofertaTitulo} numberOfLines={3}>{o.titulo}</Text>
                      {o.precoPor != null && (
                        <Text style={estilos.ofertaPreco}>
                          {o.precoDe != null && <Text style={estilos.ofertaDe}>{reais(o.precoDe)}  </Text>}
                          {reais(o.precoPor)}
                        </Text>
                      )}
                      <Text style={estilos.miniTipo} numberOfLines={1}>{o.nome}</Text>
                    </Pressable>
                  ))}
                </ScrollView>
              </>
            )}

            {/* 17/11 (pedido do usuario): Destaque antes das categorias; sem Destaque pago, o convite fica depois delas */}
            {dados.destaques.length > 0 && (
              <View style={{ marginTop: 20, marginBottom: 12 }}>
                <Vitrine itens={dados.destaques} />
              </View>
            )}

            <Titulo acao="Ver todas" aoTocarAcao={() => router.push('/explorar')}>
              Explore por categoria
            </Titulo>
            <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginHorizontal: -16 }} contentContainerStyle={{ paddingHorizontal: 16, gap: 10 }}>
              {areas.map((c) => (
                <QuadroArea
                  key={c.id}
                  nome={CURTO[c.slug] ?? c.nome}
                  icone={c.icone}
                  cor={c.cor}
                  largura={108}
                  altura={100}
                  onPress={() => router.push(`/categoria/${c.id}`)}
                />
              ))}
            </ScrollView>


            {dados.destaques.length === 0 && (
              <View style={{ marginTop: 18 }}>
                <Vitrine itens={dados.destaques} />
              </View>
            )}

            {dados.populares.length > 0 && (
              <>
                <Titulo>Recomendados em {cidade.nome}</Titulo>
                <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginHorizontal: -16 }} contentContainerStyle={{ paddingHorizontal: 16, gap: 10 }}>
                  {dados.populares.map((p) => (
                    <Pressable
                      key={p.id}
                      accessibilityRole="button"
                      accessibilityLabel={`${p.nome}, ${p.tipo}`}
                      onPress={() => router.push(`/profissional/${p.id}?origem=inicio`)}
                      style={({ pressed }) => [estilos.mini, pressed && { opacity: 0.85 }]}
                    >
                      <Foto foto={p.foto} icone={p.icone} cor={p.cor} largura={140} altura={92} raio={0} />
                      <View style={{ padding: 8 }}>
                        <Text style={estilos.miniNome} numberOfLines={2}>
                          {p.nome}
                        </Text>
                        <Text style={estilos.miniTipo} numberOfLines={1}>
                          {p.tipo}
                        </Text>
                      </View>
                    </Pressable>
                  ))}
                </ScrollView>
              </>
            )}

            <Titulo>Perto de você</Titulo>
            <PertoDeVoce />

            <View style={{ marginTop: 24 }}>
              <CartaoDono />
            </View>
            <Text style={estilos.rodape}>
              {dados.total.toLocaleString('pt-BR')} comércios e serviços de {cidade.nome}
            </Text>
          </>
        ) : null}
      </View>
    </ScrollView>
  );
}

const estilos = StyleSheet.create({
  mini: { ...cartao, width: 140, overflow: 'hidden', paddingBottom: 2 },
  miniNome: { fontSize: fonte.pequena, fontWeight: '900', color: cores.texto, lineHeight: 20 },
  miniTipo: { fontSize: 14, color: cores.textoSuave, marginTop: 2 },
  oferta: { ...cartao, width: 220, padding: 12, gap: 4, borderWidth: 2, borderColor: cores.amarelo, backgroundColor: cores.fundoSuave },
  ofertaFoto: { width: '100%', aspectRatio: 1, borderRadius: 12, marginBottom: 6 },
  ofertaSemFoto: { alignItems: 'center', justifyContent: 'center', backgroundColor: '#fff' },
  ofertaTitulo: { fontSize: fonte.pequena, fontWeight: '900', color: cores.texto, lineHeight: 21 },
  ofertaPreco: { fontSize: fonte.media, fontWeight: '900', color: cores.verde },
  ofertaDe: { fontSize: 14, fontWeight: '700', color: cores.textoSuave, textDecorationLine: 'line-through' },
  rodape: { textAlign: 'center', color: cores.textoSuave, fontSize: 14, marginTop: 20 },
});
