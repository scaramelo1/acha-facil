import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useEffect, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { chamar, type Cidade, type Estado } from '../api/cliente';
import { cartao, cores, fonte } from '../tema';
import { Cabecalho } from './Cabecalho';
import { Botao, Pilula } from './ui';

/** Sem acento e em minúsculas, para "pocos" achar "Poços". */
function simples(t: string) {
  return t.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
}

/**
 * "Onde você quer buscar?": cobre o app ao abrir (e ao tocar na cidade do cabeçalho).
 * Busca amarela, o estado (SP, MG...) em pílulas, a cidade atual marcada, as cidades do estado escolhido
 * embaixo e "Confirmar cidade". Digitando na busca, procura em todos os estados.
 * Os totais vêm do servidor; sem internet, a escolha funciona do mesmo jeito.
 */
export function EscolherCidade({
  atual,
  estados,
  cidades,
  aoEscolher,
}: {
  atual: Cidade;
  estados: Estado[];
  cidades: Cidade[];
  aoEscolher: (c: Cidade) => void;
}) {
  const baixo = useSafeAreaInsets().bottom;
  const [totais, setTotais] = useState<Record<string, number>>({});
  const [escolhida, setEscolhida] = useState<Cidade>(atual);
  const [termo, setTermo] = useState('');
  const [estado, setEstado] = useState(atual.estado);

  useEffect(() => {
    chamar<{ cidades: (Cidade & { total: number })[] }>('cidades.php')
      .then((r) => setTotais(Object.fromEntries(r.cidades.map((c) => [c.slug, c.total]))))
      .catch(() => {});
  }, []);

  const t = simples(termo.trim());
  const nomeEstado = (uf: string) => estados.find((e) => e.uf === uf)?.nome ?? uf;
  const acha = (c: Cidade) => !t || simples(`${c.nome} ${c.estado} ${nomeEstado(c.estado)}`).includes(t);
  // com algo digitado, procura em todos os estados; sem nada, mostra só as do estado escolhido
  const outras = cidades.filter((c) => c.slug !== atual.slug && acha(c) && (t !== '' || c.estado === estado));

  const linha = (c: Cidade) => {
    const marcada = escolhida.slug === c.slug;
    const total = totais[c.slug];
    return (
      <Pressable
        key={c.slug}
        accessibilityRole="radio"
        accessibilityState={{ checked: marcada }}
        accessibilityLabel={`${c.nome}, ${nomeEstado(c.estado)}`}
        onPress={() => setEscolhida(c)}
        style={({ pressed }) => [estilos.cidade, marcada && estilos.marcada, pressed && { opacity: 0.85 }]}
      >
        <MaterialCommunityIcons name="map-marker" size={28} color={cores.verde} />
        <View style={{ flex: 1 }}>
          <Text style={estilos.nome}>{c.nome}</Text>
          <Text style={estilos.sub}>
            {nomeEstado(c.estado)}
            {total ? ` · ${total.toLocaleString('pt-BR')} lugares` : ''}
          </Text>
        </View>
        {marcada ? (
          <MaterialCommunityIcons name="check-circle" size={30} color={cores.verde} />
        ) : (
          <MaterialCommunityIcons name="chevron-right" size={26} color={cores.texto} />
        )}
      </Pressable>
    );
  };

  return (
    <View style={[StyleSheet.absoluteFill, estilos.fundo]}>
      <ScrollView contentContainerStyle={{ paddingBottom: 24 }} keyboardShouldPersistTaps="handled">
        <Cabecalho semCidade titulo="Onde você quer buscar?" busca={{ texto: 'Buscar cidade', valor: termo, aoMudar: setTermo }} />
        <View style={{ padding: 16 }}>
          {estados.length > 1 && (
            <>
              <Text style={estilos.secao}>Estado</Text>
              <View style={estilos.estados}>
                {estados.map((e) => (
                  <Pilula key={e.uf} texto={`${e.nome} (${e.uf})`} ativo={t === '' && estado === e.uf} onPress={() => {
                    setEstado(e.uf);
                    setTermo('');
                  }} />
                ))}
              </View>
            </>
          )}
          {acha(atual) && (
            <>
              <Text style={estilos.secao}>Cidade atual</Text>
              {linha(atual)}
            </>
          )}
          {outras.length > 0 && (
            <Text style={estilos.secao}>{t !== '' ? 'Outras cidades' : `Cidades de ${nomeEstado(estado)}`}</Text>
          )}
          {outras.map(linha)}
          {outras.length === 0 && (t !== '' ? !acha(atual) : true) && (
            <Text style={estilos.vazio}>{t !== '' ? 'Ainda não atendemos essa cidade.' : `Por enquanto, nenhuma outra cidade de ${nomeEstado(estado)}.`}</Text>
          )}
        </View>
      </ScrollView>
      <View style={[estilos.rodape, { paddingBottom: baixo + 16 }]}>
        <Botao titulo="Confirmar cidade" tipo="amarelo" onPress={() => aoEscolher(escolhida)} />
      </View>
    </View>
  );
}

const estilos = StyleSheet.create({
  fundo: { backgroundColor: cores.fundo, zIndex: 10 },
  estados: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 14 },
  secao: { fontSize: fonte.grande, fontWeight: '900', color: cores.texto, marginTop: 8, marginBottom: 10 },
  cidade: { ...cartao, flexDirection: 'row', alignItems: 'center', gap: 12, minHeight: 64, paddingHorizontal: 14, paddingVertical: 8, marginBottom: 10, borderWidth: 2 },
  marcada: { borderColor: cores.verde, backgroundColor: cores.verdeClaro },
  nome: { fontSize: fonte.normal, fontWeight: '900', color: cores.texto },
  sub: { fontSize: 14, color: cores.textoSuave, marginTop: 1 },
  vazio: { fontSize: fonte.normal, color: cores.textoSuave, textAlign: 'center', marginTop: 24 },
  rodape: { paddingHorizontal: 16, paddingTop: 12, borderTopWidth: 1, borderColor: cores.borda, backgroundColor: cores.cartao },
});
