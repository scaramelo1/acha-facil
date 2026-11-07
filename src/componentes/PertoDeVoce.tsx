import { MaterialCommunityIcons } from '@expo/vector-icons';
import * as Location from 'expo-location';
import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Linking, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { chamar, type ProfissionalResumo } from '../api/cliente';
import { useCidade } from '../cidade';
import { cores, fonte, RAIO, sombra } from '../tema';
import { Botao, CartaoProfissional, Icone } from './ui';

interface Atalho {
  id: string;
  rotulo: string;
  icone: string;
}
type Perto = ProfissionalResumo & { distancia: number };
type Estado = 'conferindo' | 'pedir' | 'negado' | 'buscando' | 'pronto' | 'longe' | 'falha';

/**
 * "Perto de você": pede a localização (só quando a pessoa toca no botão), mostra os comércios mais perto
 * e deixa escolher o tipo (Restaurantes, Floriculturas, Farmácias…). A posição não é guardada no servidor.
 * Só lista comércios da cidade escolhida; se a pessoa está em outra cidade (longe), avisa e mostra `semLocalizacao`.
 * Sem localização, mostra `semLocalizacao` (os conhecidos da cidade).
 */
export function PertoDeVoce({ semLocalizacao }: { semLocalizacao?: React.ReactNode }) {
  const [estado, setEstado] = useState<Estado>('conferindo');
  const [posicao, setPosicao] = useState<{ lat: number; lng: number } | null>(null);
  const [atalho, setAtalho] = useState<string | null>(null);
  const [atalhos, setAtalhos] = useState<Atalho[]>([]);
  const [lista, setLista] = useState<Perto[]>([]);
  const [podePerguntar, setPodePerguntar] = useState(true);
  const { cidade } = useCidade();

  const localizar = useCallback(async () => {
    setEstado('buscando');
    try {
      const ultima = await Location.getLastKnownPositionAsync({ maxAge: 5 * 60 * 1000 });
      const p = ultima ?? (await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced }));
      setPosicao({ lat: p.coords.latitude, lng: p.coords.longitude });
    } catch {
      setEstado('falha');
    }
  }, []);

  // ao abrir: se a pessoa já deixou antes, localiza direto; se não, mostra o botão
  useEffect(() => {
    Location.getForegroundPermissionsAsync()
      .then((r) => {
        setPodePerguntar(r.canAskAgain);
        if (r.granted) localizar();
        else setEstado(r.canAskAgain ? 'pedir' : 'negado');
      })
      .catch(() => setEstado('pedir'));
  }, [localizar]);

  // com a posição (ou ao trocar o tipo ou a cidade), busca os mais perto dentro da cidade escolhida
  useEffect(() => {
    if (!posicao) return;
    let cancelado = false;
    setEstado('buscando');
    chamar<{ atalhos: Atalho[]; profissionais: Perto[]; longe?: boolean }>(
      `perto.php?lat=${posicao.lat.toFixed(5)}&lng=${posicao.lng.toFixed(5)}${atalho ? `&atalho=${atalho}` : ''}`,
    )
      .then((r) => {
        if (cancelado) return;
        setAtalhos(r.atalhos);
        setLista(r.profissionais);
        setEstado(r.longe ? 'longe' : 'pronto');
      })
      .catch(() => !cancelado && setEstado('falha'));
    return () => {
      cancelado = true;
    };
  }, [posicao, atalho, cidade.slug]);

  // trocar o tipo: mostra "carregando" já no toque (o efeito acima busca de novo)
  const escolher = (id: string | null) => {
    setEstado('buscando');
    setAtalho(id);
  };

  const pedir = async () => {
    const r = await Location.requestForegroundPermissionsAsync();
    setPodePerguntar(r.canAskAgain);
    if (r.granted) localizar();
    else setEstado('negado');
  };

  if (estado === 'conferindo') return null;

  // em outra cidade: o "perto" não serve para a cidade que a pessoa está olhando
  if (estado === 'longe')
    return (
      <>
        <View style={estilos.convite}>
          <View style={estilos.conviteIcone}>
            <MaterialCommunityIcons name="map-marker-distance" size={34} color={cores.verde} />
          </View>
          <Text style={estilos.conviteTitulo}>Você não está em {cidade.curto}</Text>
          <Text style={estilos.conviteTexto}>
            O "Perto de você" mostra os lugares mais perto quando você estiver na cidade. Enquanto isso, veja os conhecidos de lá.
          </Text>
        </View>
        {semLocalizacao}
      </>
    );

  if (estado === 'pedir' || estado === 'negado')
    return (
      <>
        <View style={estilos.convite}>
          <View style={estilos.conviteIcone}>
            <MaterialCommunityIcons name="crosshairs-gps" size={34} color={cores.verde} />
          </View>
          <Text style={estilos.conviteTitulo}>Veja o que tem perto de você</Text>
          <Text style={estilos.conviteTexto}>
            {estado === 'negado' && !podePerguntar
              ? 'A localização está desligada para o Acha Fácil. Ligue nas configurações do celular.'
              : 'Restaurantes, farmácias, floriculturas e muito mais, do mais perto ao mais longe.'}
          </Text>
          {estado === 'negado' && !podePerguntar ? (
            <Botao titulo="Abrir configurações" icone="cog-outline" tipo="contorno" onPress={() => Linking.openSettings()} estilo={{ alignSelf: 'stretch' }} />
          ) : (
            <Botao titulo="Usar minha localização" icone="map-marker-radius" onPress={pedir} estilo={{ alignSelf: 'stretch' }} />
          )}
        </View>
        {semLocalizacao}
      </>
    );

  return (
    <View>
      {atalhos.length > 0 && (
        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginHorizontal: -16 }} contentContainerStyle={estilos.atalhos}>
          <Pilula rotulo="Tudo" icone="map-marker-radius-outline" ativo={atalho === null} onPress={() => atalho !== null && escolher(null)} />
          {atalhos.map((a) => (
            <Pilula key={a.id} rotulo={a.rotulo} icone={a.icone} ativo={atalho === a.id} onPress={() => escolher(atalho === a.id ? null : a.id)} />
          ))}
        </ScrollView>
      )}

      {estado === 'buscando' ? (
        <View style={{ paddingVertical: 32 }}>
          <ActivityIndicator size="large" color={cores.verde} />
        </View>
      ) : estado === 'falha' ? (
        <View style={estilos.convite}>
          <Text style={estilos.conviteTexto}>Não foi possível achar sua localização agora.</Text>
          <Botao titulo="Tentar de novo" icone="refresh" tipo="contorno" onPress={localizar} estilo={{ alignSelf: 'stretch' }} />
        </View>
      ) : lista.length === 0 ? (
        <Text style={estilos.vazio}>
          {atalho ? 'Ainda não achamos esse tipo de lugar perto de você.' : 'Ainda não achamos lugares perto de você.'}
        </Text>
      ) : (
        <>
          {lista.slice(0, 10).map((p) => (
            <CartaoProfissional key={p.id} p={p} distancia={p.distancia} />
          ))}
          <Pressable accessibilityRole="button" onPress={localizar} style={estilos.atualizar} hitSlop={8}>
            <MaterialCommunityIcons name="refresh" size={18} color={cores.destaque} />
            <Text style={estilos.atualizarTexto}>Atualizar minha localização</Text>
          </Pressable>
          <Text style={estilos.nota}>Distâncias aproximadas, em linha reta. Endereços localizados com o OpenStreetMap.</Text>
        </>
      )}
    </View>
  );
}

function Pilula({ rotulo, icone, ativo, onPress }: { rotulo: string; icone: string; ativo: boolean; onPress: () => void }) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ selected: ativo }}
      onPress={onPress}
      style={[estilos.pilula, ativo && { backgroundColor: cores.verde, borderColor: cores.verde }]}
    >
      <Icone nome={icone} tamanho={20} cor={ativo ? '#fff' : cores.verde} />
      <Text style={[estilos.pilulaTexto, ativo && { color: '#fff' }]}>{rotulo}</Text>
    </Pressable>
  );
}

const estilos = StyleSheet.create({
  convite: { backgroundColor: cores.cartao, borderRadius: RAIO, padding: 18, alignItems: 'center', gap: 10, marginBottom: 12, ...sombra },
  conviteIcone: { width: 64, height: 64, borderRadius: 32, backgroundColor: cores.verdeClaro, alignItems: 'center', justifyContent: 'center' },
  conviteTitulo: { fontSize: fonte.media, fontWeight: '900', color: cores.texto, textAlign: 'center' },
  conviteTexto: { fontSize: fonte.pequena, color: cores.textoSuave, textAlign: 'center' },
  atalhos: { paddingHorizontal: 16, paddingBottom: 14, gap: 8 },
  pilula: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    minHeight: 46,
    paddingHorizontal: 16,
    borderRadius: 23,
    borderWidth: 1,
    borderColor: cores.borda,
    backgroundColor: cores.cartao,
  },
  pilulaTexto: { fontSize: fonte.pequena, fontWeight: '800', color: cores.texto },
  vazio: { fontSize: fonte.pequena, color: cores.textoSuave, textAlign: 'center', paddingVertical: 20 },
  atualizar: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6, minHeight: 44 },
  atualizarTexto: { fontSize: fonte.pequena, fontWeight: '800', color: cores.destaque },
  nota: { fontSize: 13, color: cores.textoSuave, textAlign: 'center' },
});
