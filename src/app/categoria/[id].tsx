import { Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { FlatList, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { chamar, type ProfissionalResumo, type TipoNegocio } from '../../api/cliente';
import { useBarraClara } from '../../componentes/BarraClara';
import { CartaoProfissional, Carregando, Falha, Icone, Vazio, useCarregar } from '../../componentes/ui';
import { useCidade } from '../../cidade';
import { cores, fonte } from '../../tema';

interface Resposta {
  categoria: { id: number; nome: string; icone: string; cor: string };
  tipos: TipoNegocio[];
  profissionais: ProfissionalResumo[];
}

// Uma área (ex.: Alimentação): faixa colorida no topo e filtros pelo tipo de negócio (Pizzaria, Padaria…)
export default function TelaCategoria() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { cidade } = useCidade();
  const { dados, erro, carregando, recarregar } = useCarregar(() => chamar<Resposta>(`categoria.php?id=${id}`), `categoria-${id}-${cidade.slug}`);
  useBarraClara();
  const [tipo, setTipo] = useState<string | null>(null);

  if (carregando && !dados) return <Carregando />;
  if (erro && !dados) return <Falha mensagem={erro} tentar={recarregar} />;
  if (!dados) return null;

  const { categoria: c } = dados;
  const lista = tipo ? dados.profissionais.filter((p) => p.tipo === tipo) : dados.profissionais;
  // só mostra filtros que valem a pena (tipos com pelo menos 2 lugares)
  const tipos = dados.tipos.filter((t) => t.total >= 2);

  return (
    <>
      <Stack.Screen options={{ title: '', headerStyle: { backgroundColor: c.cor }, headerTintColor: '#fff' }} />
      <FlatList
        data={lista}
        keyExtractor={(p) => String(p.id)}
        contentContainerStyle={{ paddingBottom: 40, flexGrow: 1 }}
        refreshing={carregando}
        onRefresh={recarregar}
        initialNumToRender={12}
        windowSize={7}
        ListHeaderComponent={
          <View>
            <View style={[estilos.topo, { backgroundColor: c.cor }]}>
              <View style={estilos.topoIcone}>
                <Icone nome={c.icone} tamanho={34} cor={c.cor} />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={estilos.topoNome}>{c.nome}</Text>
                <Text style={estilos.topoTotal}>
                  {dados.profissionais.length.toLocaleString('pt-BR')} {dados.profissionais.length === 1 ? 'lugar' : 'lugares'} em {cidade.curto}
                </Text>
              </View>
            </View>
            {tipos.length > 1 && (
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={estilos.filtros}>
                <Filtro texto="Todos" ativo={tipo === null} cor={c.cor} onPress={() => setTipo(null)} />
                {tipos.map((t) => (
                  <Filtro key={t.nome} texto={`${t.nome} (${t.total})`} ativo={tipo === t.nome} cor={c.cor} onPress={() => setTipo(tipo === t.nome ? null : t.nome)} />
                ))}
              </ScrollView>
            )}
          </View>
        }
        renderItem={({ item }) => (
          <View style={{ paddingHorizontal: 16 }}>
            <CartaoProfissional p={item} />
          </View>
        )}
        ListEmptyComponent={<Vazio icone="store-search-outline" texto="Ainda não há lugares nesta área. Volte em breve!" />}
      />
    </>
  );
}

function Filtro({ texto, ativo, cor, onPress }: { texto: string; ativo: boolean; cor: string; onPress: () => void }) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ selected: ativo }}
      onPress={onPress}
      style={[estilos.filtro, ativo ? { backgroundColor: cor, borderColor: cor } : null]}
    >
      <Text style={[estilos.filtroTexto, ativo && { color: '#fff' }]}>{texto}</Text>
    </Pressable>
  );
}

const estilos = StyleSheet.create({
  topo: { flexDirection: 'row', alignItems: 'center', gap: 14, paddingHorizontal: 16, paddingBottom: 24, borderBottomLeftRadius: 28, borderBottomRightRadius: 28 },
  topoIcone: { width: 64, height: 64, borderRadius: 20, backgroundColor: '#fff', alignItems: 'center', justifyContent: 'center' },
  topoNome: { color: '#fff', fontSize: fonte.titulo, fontWeight: '900' },
  topoTotal: { color: '#fff', opacity: 0.9, fontSize: fonte.pequena, fontWeight: '700' },
  filtros: { paddingHorizontal: 16, paddingVertical: 16, gap: 8 },
  filtro: { minHeight: 44, justifyContent: 'center', paddingHorizontal: 16, borderRadius: 22, borderWidth: 2, borderColor: cores.borda, backgroundColor: cores.cartao },
  filtroTexto: { fontSize: fonte.pequena, fontWeight: '800', color: cores.texto },
});
