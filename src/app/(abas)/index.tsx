import { useRouter } from 'expo-router';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';

import { chamar, type Categoria, type ProfissionalResumo } from '../../api/cliente';
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

            <View style={{ marginTop: 18 }}>
              <Vitrine itens={dados.destaques} />
            </View>

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
  rodape: { textAlign: 'center', color: cores.textoSuave, fontSize: 14, marginTop: 20 },
});
