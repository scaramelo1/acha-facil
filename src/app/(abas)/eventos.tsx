import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import { useMemo } from 'react';
import { Pressable, SectionList, StyleSheet, Text, View } from 'react-native';

import { chamar, type EventoResumo } from '../../api/cliente';
import { useCidade } from '../../cidade';
import { Cabecalho } from '../../componentes/Cabecalho';
import { Folhinha } from '../../componentes/Folhinha';
import { Carregando, Falha, Vazio, useCarregar } from '../../componentes/ui';
import { cartao, cores, ESPACO_BARRA, fonte } from '../../tema';

/** Agenda da cidade: proximos eventos em ordem de data, separados por mes, cada um com a folhinha do calendario. */
export default function TelaEventos() {
  const { cidade } = useCidade();
  const router = useRouter();
  const { dados, erro, carregando, recarregar } = useCarregar(
    () => chamar<{ eventos: EventoResumo[] }>('eventos.php').then((r) => r.eventos),
    `eventos-${cidade.slug}`,
  );

  // a lista ja vem em ordem de data; junta os seguidos do mesmo grupo (mes)
  const secoes = useMemo(() => {
    const s: { title: string; data: EventoResumo[] }[] = [];
    for (const e of dados ?? []) {
      const ultima = s[s.length - 1];
      if (ultima && ultima.title === e.grupo) ultima.data.push(e);
      else s.push({ title: e.grupo, data: [e] });
    }
    return s;
  }, [dados]);

  return (
    <SectionList
      sections={secoes}
      keyExtractor={(e) => String(e.id)}
      stickySectionHeadersEnabled={false}
      contentContainerStyle={{ paddingBottom: ESPACO_BARRA, flexGrow: 1 }}
      refreshing={carregando && !!dados}
      onRefresh={recarregar}
      ListHeaderComponent={<Cabecalho titulo={`Eventos em ${cidade.curto}`} subtitulo="A agenda da cidade, em ordem de data" />}
      renderSectionHeader={({ section }) => <Text style={estilos.grupo}>{section.title}</Text>}
      renderItem={({ item: e }) => (
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={`${e.titulo}, ${e.quando}${e.horario ? ', ' + e.horario : ''}`}
          onPress={() => router.push(`/evento/${e.id}`)}
          style={({ pressed }) => [estilos.cartao, pressed && { opacity: 0.85 }]}
        >
          <Folhinha semana={e.semana} dia={e.dia} mes={e.mes} />
          <View style={{ flex: 1, gap: 3 }}>
            <Text style={estilos.titulo} numberOfLines={3}>
              {e.titulo}
            </Text>
            <Text style={estilos.linha}>
              {e.quando}
              {e.horario ? ` - ${e.horario}` : ''}
            </Text>
            {(e.local || e.endereco) && (
              <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4 }}>
                <MaterialCommunityIcons name="map-marker-outline" size={18} color={cores.textoSuave} />
                <Text style={[estilos.linha, { flex: 1 }]} numberOfLines={2}>
                  {e.local || e.endereco}
                </Text>
              </View>
            )}
            {e.preco ? <Text style={estilos.preco}>{e.preco}</Text> : null}
          </View>
          {e.imagem ? (
            <Image source={{ uri: e.imagem }} style={estilos.mini} contentFit="cover" accessible={false} />
          ) : (
            <MaterialCommunityIcons name="chevron-right" size={28} color={cores.verde} />
          )}
        </Pressable>
      )}
      ListEmptyComponent={
        carregando && !dados ? (
          <Carregando />
        ) : erro && !dados ? (
          <Falha mensagem={erro} tentar={recarregar} />
        ) : (
          <Vazio icone="calendar-blank-outline" texto={`Nenhum evento marcado em ${cidade.curto} por enquanto. Volte logo!`} />
        )
      }
    />
  );
}

const estilos = StyleSheet.create({
  grupo: { fontSize: fonte.grande, fontWeight: '900', color: cores.verde, marginHorizontal: 16, marginTop: 20, marginBottom: 10 },
  cartao: { ...cartao, flexDirection: 'row', alignItems: 'center', gap: 14, marginHorizontal: 16, marginBottom: 12, padding: 12 },
  titulo: { fontSize: fonte.normal + 1, fontWeight: '900', color: cores.verde },
  linha: { fontSize: fonte.pequena - 1, color: cores.textoSuave },
  preco: {
    alignSelf: 'flex-start',
    marginTop: 4,
    backgroundColor: cores.fundoSuave,
    color: cores.verde,
    fontWeight: '900',
    fontSize: 14,
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 2,
    overflow: 'hidden',
  },
  mini: { width: 72, height: 72, borderRadius: 12 },
});
