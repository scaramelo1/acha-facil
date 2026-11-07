import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { FlatList, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';

import { chamar, type ProfissionalResumo, type TipoNegocio } from '../../api/cliente';
import { useCidade } from '../../cidade';
import { CartaoProfissional, Carregando, corClara, Falha, Icone, Vazio, useCarregar } from '../../componentes/ui';
import { cores, fonte, sombra } from '../../tema';

interface Resposta {
  categoria: { id: number; nome: string; icone: string; cor: string };
  tipos: TipoNegocio[];
  profissionais: ProfissionalResumo[];
}

/** Sem acento e em minúsculas, para a busca dentro da área achar "cafe" em "Café". */
function simples(t: string) {
  return t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

// Uma área (ex.: Mercados e bebidas): título com ícone, busca dentro da área e filtros pelo tipo de negócio
export default function TelaCategoria() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { cidade } = useCidade();
  const { dados, erro, carregando, recarregar } = useCarregar(() => chamar<Resposta>(`categoria.php?id=${id}`), `categoria-${id}-${cidade.slug}`);
  const [tipo, setTipo] = useState<string | null>(null);
  const [termo, setTermo] = useState('');

  if (carregando && !dados) return <Carregando />;
  if (erro && !dados) return <Falha mensagem={erro} tentar={recarregar} />;
  if (!dados) return null;

  const { categoria: c } = dados;
  const t = simples(termo.trim());
  const lista = dados.profissionais.filter(
    (p) => (!tipo || p.tipo === tipo) && (!t || simples(`${p.nome} ${p.tipo} ${p.bairro ?? ''}`).includes(t)),
  );
  // só mostra filtros que valem a pena (tipos com pelo menos 2 lugares)
  const tipos = dados.tipos.filter((x) => x.total >= 2);

  return (
    <>
      <Stack.Screen options={{ title: '' }} />
      <FlatList
        data={lista}
        keyExtractor={(p) => String(p.id)}
        contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 40, flexGrow: 1 }}
        keyboardShouldPersistTaps="handled"
        refreshing={carregando}
        onRefresh={recarregar}
        initialNumToRender={12}
        windowSize={7}
        ListHeaderComponent={
          <View style={{ marginBottom: 6 }}>
            <View style={estilos.topo}>
              <View style={[estilos.topoIcone, { backgroundColor: corClara(c.cor) }]}>
                <Icone nome={c.icone} tamanho={36} cor={c.cor} />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={estilos.topoNome}>{c.nome}</Text>
                <Text style={estilos.topoTotal}>
                  {dados.profissionais.length.toLocaleString('pt-BR')} {dados.profissionais.length === 1 ? 'lugar' : 'lugares'} em {cidade.curto}
                </Text>
              </View>
            </View>

            <View style={estilos.busca}>
              <MaterialCommunityIcons name="magnify" size={26} color={cores.texto} />
              <TextInput
                value={termo}
                onChangeText={setTermo}
                placeholder={`Buscar em ${c.nome}`}
                placeholderTextColor={cores.textoSuave}
                style={estilos.buscaCampo}
                accessibilityLabel={`Buscar em ${c.nome}`}
              />
              {termo.length > 0 && (
                <Pressable accessibilityRole="button" accessibilityLabel="Limpar" hitSlop={10} onPress={() => setTermo('')}>
                  <MaterialCommunityIcons name="close-circle" size={24} color={cores.textoSuave} />
                </Pressable>
              )}
            </View>

            {tipos.length > 1 && (
              <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginHorizontal: -16 }} contentContainerStyle={estilos.filtros}>
                <Filtro texto="Todos" ativo={tipo === null} onPress={() => setTipo(null)} />
                {tipos.map((x) => (
                  <Filtro key={x.nome} texto={`${x.nome} (${x.total})`} ativo={tipo === x.nome} onPress={() => setTipo(tipo === x.nome ? null : x.nome)} />
                ))}
              </ScrollView>
            )}
          </View>
        }
        renderItem={({ item }) => <CartaoProfissional p={item} />}
        ListEmptyComponent={
          <Vazio icone="store-search-outline" texto={termo || tipo ? 'Nada encontrado com esse filtro.' : 'Ainda não há lugares nesta área. Volte em breve!'} />
        }
      />
    </>
  );
}

function Filtro({ texto, ativo, onPress }: { texto: string; ativo: boolean; onPress: () => void }) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ selected: ativo }}
      onPress={onPress}
      style={[estilos.filtro, ativo && { backgroundColor: cores.verde, borderColor: cores.verde }]}
    >
      <Text style={[estilos.filtroTexto, ativo && { color: '#fff' }]}>{texto}</Text>
    </Pressable>
  );
}

const estilos = StyleSheet.create({
  topo: { flexDirection: 'row', alignItems: 'center', gap: 14, paddingTop: 4, paddingBottom: 16 },
  topoIcone: { width: 68, height: 68, borderRadius: 20, alignItems: 'center', justifyContent: 'center' },
  topoNome: { color: cores.texto, fontSize: 28, fontWeight: '900', lineHeight: 33 },
  topoTotal: { color: cores.textoSuave, fontSize: fonte.normal, marginTop: 2 },
  busca: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    minHeight: 58,
    borderRadius: 29,
    paddingHorizontal: 18,
    backgroundColor: cores.cartao,
    borderWidth: 1,
    borderColor: cores.borda,
    ...sombra,
  },
  buscaCampo: { flex: 1, fontSize: fonte.normal, color: cores.texto, minHeight: 54 },
  filtros: { paddingHorizontal: 16, paddingVertical: 14, gap: 8 },
  filtro: { minHeight: 46, justifyContent: 'center', paddingHorizontal: 18, borderRadius: 23, borderWidth: 1, borderColor: cores.borda, backgroundColor: cores.cartao },
  filtroTexto: { fontSize: fonte.pequena, fontWeight: '800', color: cores.texto },
});
