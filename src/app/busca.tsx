import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { FlatList, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';

import { chamar, type ProfissionalResumo } from '../api/cliente';
import { CartaoProfissional, Carregando, Vazio } from '../componentes/ui';
import { useCidade } from '../cidade';
import { cores, fonte, sombra } from '../tema';

export default function TelaBusca() {
  // pode chegar com um termo pronto (atalhos da tela inicial)
  const { q } = useLocalSearchParams<{ q?: string }>();
  const { cidade } = useCidade();
  const [termo, setTermo] = useState(q ?? '');
  const [itens, setItens] = useState<ProfissionalResumo[] | null>(null);
  const [erro, setErro] = useState<string | null>(null);

  // busca enquanto digita, esperando a pessoa parar por meio segundo
  useEffect(() => {
    const t = termo.trim();
    if (t.length < 2) return;
    let cancelado = false;
    const espera = setTimeout(async () => {
      try {
        const r = await chamar<{ profissionais: ProfissionalResumo[] }>(`busca.php?q=${encodeURIComponent(t)}`);
        if (!cancelado) {
          setItens(r.profissionais);
          setErro(null);
        }
      } catch (e) {
        if (!cancelado) setErro(e instanceof Error ? e.message : 'Não foi possível buscar.');
      }
    }, 450);
    return () => {
      cancelado = true;
      clearTimeout(espera);
    };
  }, [termo, cidade.slug]);

  const curto = termo.trim().length < 2;

  return (
    <View style={{ flex: 1 }}>
      <View style={estilos.barra}>
        <MaterialCommunityIcons name="magnify" size={28} color={cores.texto} />
        <TextInput
          autoFocus={!q}
          value={termo}
          onChangeText={setTermo}
          placeholder="Ex.: pizza, farmácia, borracharia"
          placeholderTextColor={cores.textoSuave}
          returnKeyType="search"
          style={estilos.campo}
          accessibilityLabel="Digite o que você procura"
        />
        {termo.length > 0 && (
          <Pressable accessibilityRole="button" accessibilityLabel="Limpar" hitSlop={10} onPress={() => setTermo('')} style={estilos.limpar}>
            <MaterialCommunityIcons name="close" size={22} color={cores.verde} />
          </Pressable>
        )}
      </View>
      {curto ? (
        <Vazio icone="store-search-outline" texto={`Procurando em ${cidade.nome}. Digite o nome, o tipo de negócio (pizzaria, oficina…) ou o bairro.`} />
      ) : erro ? (
        <Vazio icone="wifi-off" texto={erro} />
      ) : itens === null ? (
        <Carregando />
      ) : (
        <FlatList
          data={itens}
          keyExtractor={(p) => String(p.id)}
          contentContainerStyle={{ padding: 16, paddingTop: 4, flexGrow: 1 }}
          keyboardShouldPersistTaps="handled"
          ListHeaderComponent={
            itens.length > 0 ? (
              <Text style={estilos.contagem}>
                {itens.length >= 50 ? 'Os 50 primeiros resultados' : `${itens.length} ${itens.length === 1 ? 'resultado' : 'resultados'}`}
              </Text>
            ) : null
          }
          renderItem={({ item }) => <CartaoProfissional p={item} />}
          ListEmptyComponent={<Vazio icone="store-search-outline" texto="Nada encontrado. Tente outra palavra." />}
        />
      )}
    </View>
  );
}

const estilos = StyleSheet.create({
  barra: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    margin: 16,
    paddingLeft: 14,
    paddingRight: 8,
    minHeight: 64,
    borderRadius: 32,
    backgroundColor: cores.cartao,
    borderWidth: 1,
    borderColor: cores.borda,
    ...sombra,
  },
  campo: { flex: 1, fontSize: fonte.media, color: cores.texto, minHeight: 56 },
  limpar: { width: 40, height: 40, borderRadius: 14, backgroundColor: cores.fundoSuave, alignItems: 'center', justifyContent: 'center' },
  contagem: { fontSize: fonte.pequena, fontWeight: '800', color: cores.textoSuave, marginBottom: 10 },
});
