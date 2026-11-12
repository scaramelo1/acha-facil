import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { useLocalSearchParams } from 'expo-router';
import { Alert, Linking, ScrollView, Share, StyleSheet, Text, View } from 'react-native';

import { chamar, type Evento } from '../../api/cliente';
import { Cabecalho } from '../../componentes/Cabecalho';
import { Folhinha } from '../../componentes/Folhinha';
import { Botao, Carregando, Falha, useCarregar } from '../../componentes/ui';
import { cartao, cores, fonte } from '../../tema';

type NomeIcone = keyof typeof MaterialCommunityIcons.glyphMap;

/** Pagina do evento: cartaz, quando, onde, entrada, descricao e os botoes (pagina oficial, agenda, como chegar). */
export default function TelaEvento() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { dados: e, erro, carregando, recarregar } = useCarregar(
    () => chamar<{ evento: Evento }>(`eventos.php?id=${encodeURIComponent(id)}`).then((r) => r.evento),
    `evento-${id}`,
  );

  if (!e)
    return (
      <View style={{ flex: 1 }}>
        <Cabecalho voltar />
        {carregando ? <Carregando /> : <Falha mensagem={erro ?? 'Não foi possível carregar.'} tentar={recarregar} />}
      </View>
    );

  const abrir = (link: string) => Linking.openURL(link).catch(() => Alert.alert('Não foi possível abrir', 'Tente de novo em instantes.'));
  const compartilhar = () => Share.share({ message: `${e.titulo} - ${e.quando}${e.horario ? `, ${e.horario}` : ''}. ${e.site}` });

  return (
    <ScrollView contentContainerStyle={{ paddingBottom: 48 }}>
      <Cabecalho voltar />
      {e.imagem && <Image source={{ uri: e.imagem }} style={estilos.capa} contentFit="cover" accessibilityLabel={`Cartaz de ${e.titulo}`} />}

      <View style={{ paddingHorizontal: 16, gap: 16, marginTop: 16 }}>
        <View style={{ flexDirection: 'row', gap: 14, alignItems: 'center' }}>
          <Folhinha semana={e.semana} dia={e.dia} mes={e.mes} grande />
          <View style={{ flex: 1, gap: 4 }}>
            <Text style={estilos.titulo}>{e.titulo}</Text>
            <Text style={estilos.sub}>
              {e.quando}
              {e.horario ? ` - ${e.horario}` : ''}
            </Text>
          </View>
        </View>

        {e.terminou && (
          <View style={estilos.aviso}>
            <MaterialCommunityIcons name="calendar-remove-outline" size={24} color={cores.erro} />
            <Text style={[estilos.texto, { color: cores.erro, flex: 1 }]}>Este evento já aconteceu.</Text>
          </View>
        )}

        <View style={{ gap: 10 }}>
          {e.linkOficial && <Botao titulo="Página oficial do evento" icone="open-in-new" tipo="amarelo" onPress={() => abrir(e.linkOficial!)} />}
          {!e.terminou && <Botao titulo="Salvar na minha agenda" icone="calendar-plus" tipo="contorno" onPress={() => abrir(e.agenda)} />}
          {e.mapa && <Botao titulo="Como chegar" icone="map-marker-radius-outline" tipo="contorno" onPress={() => abrir(e.mapa!)} />}
          <Botao titulo="Compartilhar" icone="share-variant-outline" tipo="contorno" onPress={compartilhar} />
        </View>

        <View style={[cartao, { padding: 16, gap: 14 }]}>
          <Linha icone="calendar-month-outline" titulo="Quando" texto={`${e.quando}${e.horario ? `\n${e.horario}` : ''}`} />
          <Linha icone="map-marker-outline" titulo="Onde" texto={[e.local, e.endereco, e.cidade].filter(Boolean).join('\n')} />
          {e.preco ? <Linha icone="ticket-outline" titulo="Entrada" texto={e.preco} /> : null}
        </View>

        {e.descricao ? (
          <View style={{ gap: 6 }}>
            <Text style={estilos.secao}>Sobre o evento</Text>
            <Text style={estilos.texto}>{e.descricao}</Text>
          </View>
        ) : null}

        <Text style={estilos.nota}>Datas e valores podem mudar: confira sempre na página oficial do evento.</Text>
      </View>
    </ScrollView>
  );
}

function Linha({ icone, titulo, texto }: { icone: NomeIcone; titulo: string; texto: string }) {
  return (
    <View style={{ flexDirection: 'row', gap: 12 }}>
      <MaterialCommunityIcons name={icone} size={26} color={cores.verde} />
      <View style={{ flex: 1 }}>
        <Text style={estilos.linhaTitulo}>{titulo}</Text>
        <Text style={estilos.texto}>{texto}</Text>
      </View>
    </View>
  );
}

const estilos = StyleSheet.create({
  capa: { width: '100%', aspectRatio: 16 / 10, backgroundColor: cores.verdeClaro },
  titulo: { fontSize: fonte.titulo - 4, fontWeight: '900', color: cores.verde, lineHeight: 32 },
  sub: { fontSize: fonte.normal, color: cores.textoSuave },
  secao: { fontSize: fonte.grande, fontWeight: '900', color: cores.verde },
  texto: { fontSize: fonte.normal, color: cores.texto, lineHeight: 26 },
  linhaTitulo: { fontSize: fonte.pequena, fontWeight: '900', color: cores.verde },
  nota: { fontSize: fonte.pequena - 1, color: cores.textoSuave },
  aviso: { flexDirection: 'row', alignItems: 'center', gap: 10, padding: 12, borderRadius: 12, backgroundColor: '#FDECEC' },
});
