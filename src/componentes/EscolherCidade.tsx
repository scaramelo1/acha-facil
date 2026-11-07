import { MaterialCommunityIcons } from '@expo/vector-icons';
import * as Location from 'expo-location';
import { useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Linking, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { chamar, type Cidade, type Estado } from '../api/cliente';
import { cartao, cores, fonte } from '../tema';
import { Cabecalho } from './Cabecalho';
import { Botao, Pilula } from './ui';

/** Sem acento e em minúsculas, para "pocos" achar "Poços". */
function simples(t: string) {
  return t.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
}

/** Até quantos km do centro conta como "estar na cidade". */
const RAIO_CIDADE_KM = 25;

/** Distância em linha reta (km) entre dois pontos. */
function distanciaKm(lat1: number, lng1: number, lat2: number, lng2: number) {
  const rad = Math.PI / 180;
  const a =
    Math.sin(((lat2 - lat1) * rad) / 2) ** 2 + Math.cos(lat1 * rad) * Math.cos(lat2 * rad) * Math.sin(((lng2 - lng1) * rad) / 2) ** 2;
  return 6371 * 2 * Math.asin(Math.sqrt(a));
}

/** Cidade atendida mais perto do ponto, se estiver a até RAIO_CIDADE_KM do centro dela. */
function cidadeDoPonto(cidades: Cidade[], lat: number, lng: number): Cidade | null {
  let melhor: Cidade | null = null;
  let menor = Infinity;
  for (const c of cidades) {
    if (c.lat === undefined || c.lng === undefined) continue;
    const d = distanciaKm(lat, lng, c.lat, c.lng);
    if (d < menor) {
      menor = d;
      melhor = c;
    }
  }
  return menor <= RAIO_CIDADE_KM ? melhor : null;
}

type Local = { situacao: 'procurando' | 'pedir' | 'negado' | 'longe' | 'falha' } | { situacao: 'achou'; cidade: Cidade };

/**
 * "Onde você quer buscar?": cobre o app ao abrir (e ao tocar na cidade do cabeçalho).
 * "Cidade atual" é a cidade onde o celular está (pela localização), já marcada. Sem localização, a pessoa
 * escolhe o estado (pílulas) e a cidade; a última escolha vem marcada na lista. Digitando, procura em todos os estados.
 * A posição não sai do celular: só serve para achar a cidade mais perto.
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
  const [local, setLocal] = useState<Local>({ situacao: 'procurando' });
  // se a pessoa já tocou numa cidade, a localização que chegar depois não troca a escolha dela
  const mexeu = useRef(false);

  useEffect(() => {
    chamar<{ cidades: (Cidade & { total: number })[] }>('cidades.php')
      .then((r) => setTotais(Object.fromEntries(r.cidades.map((c) => [c.slug, c.total]))))
      .catch(() => {});
  }, []);

  const achar = useCallback(async () => {
    try {
      const ultima = await Location.getLastKnownPositionAsync({ maxAge: 10 * 60 * 1000 });
      const p = ultima ?? (await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced }));
      const c = cidadeDoPonto(cidades, p.coords.latitude, p.coords.longitude);
      if (!c) return setLocal({ situacao: 'longe' });
      setLocal({ situacao: 'achou', cidade: c });
      if (!mexeu.current) {
        setEscolhida(c);
        setEstado(c.estado);
      }
    } catch {
      setLocal({ situacao: 'falha' });
    }
  }, [cidades]);

  // ao abrir: se a pessoa já deixou usar a localização, acha a cidade direto; se não, mostra o botão
  useEffect(() => {
    Location.getForegroundPermissionsAsync()
      .then((r) => {
        if (r.granted) achar();
        else setLocal({ situacao: r.canAskAgain ? 'pedir' : 'negado' });
      })
      .catch(() => setLocal({ situacao: 'pedir' }));
  }, [achar]);

  const pedir = async () => {
    setLocal({ situacao: 'procurando' });
    try {
      const r = await Location.requestForegroundPermissionsAsync();
      if (r.granted) achar();
      else setLocal({ situacao: r.canAskAgain ? 'pedir' : 'negado' });
    } catch {
      setLocal({ situacao: 'falha' });
    }
  };

  const escolher = (c: Cidade) => {
    mexeu.current = true;
    setEscolhida(c);
  };

  const t = simples(termo.trim());
  const nomeEstado = (uf: string) => estados.find((e) => e.uf === uf)?.nome ?? uf;
  const acha = (c: Cidade) => !t || simples(`${c.nome} ${c.estado} ${nomeEstado(c.estado)}`).includes(t);
  const daqui = local.situacao === 'achou' ? local.cidade : null;
  // com algo digitado, procura em todos os estados; sem nada, mostra só as do estado escolhido
  const lista = cidades.filter((c) => c.slug !== daqui?.slug && acha(c) && (t !== '' || c.estado === estado));

  const linha = (c: Cidade, observacao?: string) => {
    const marcada = escolhida.slug === c.slug;
    const total = totais[c.slug];
    const detalhe = [nomeEstado(c.estado), total ? `${total.toLocaleString('pt-BR')} lugares` : null, observacao].filter(Boolean).join(' · ');
    return (
      <Pressable
        key={c.slug}
        accessibilityRole="radio"
        accessibilityState={{ checked: marcada }}
        accessibilityLabel={`${c.nome}, ${detalhe}`}
        onPress={() => escolher(c)}
        style={({ pressed }) => [estilos.cidade, marcada && estilos.marcada, pressed && { opacity: 0.85 }]}
      >
        <MaterialCommunityIcons name="map-marker" size={28} color={cores.verde} />
        <View style={{ flex: 1 }}>
          <Text style={estilos.nome}>{c.nome}</Text>
          <Text style={estilos.sub}>{detalhe}</Text>
        </View>
        {marcada ? (
          <MaterialCommunityIcons name="check-circle" size={30} color={cores.verde} />
        ) : (
          <MaterialCommunityIcons name="chevron-right" size={26} color={cores.texto} />
        )}
      </Pressable>
    );
  };

  /** Caixa da seção "Cidade atual" quando a cidade ainda não foi achada pela localização. */
  const aviso = (icone: keyof typeof MaterialCommunityIcons.glyphMap, texto: string, botao?: { titulo: string; onPress: () => void }) => (
    <View style={estilos.aviso}>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
        <MaterialCommunityIcons name={icone} size={28} color={cores.verde} />
        <Text style={[estilos.avisoTexto, { flex: 1 }]}>{texto}</Text>
      </View>
      {botao && <Botao titulo={botao.titulo} tipo="contorno" onPress={botao.onPress} estilo={{ marginTop: 12 }} />}
    </View>
  );

  return (
    <View style={[StyleSheet.absoluteFill, estilos.fundo]}>
      <ScrollView contentContainerStyle={{ paddingBottom: 24 }} keyboardShouldPersistTaps="handled">
        <Cabecalho semCidade titulo="Onde você quer buscar?" busca={{ texto: 'Buscar cidade', valor: termo, aoMudar: setTermo }} />
        <View style={{ padding: 16 }}>
          {t === '' && (
            <>
              <Text style={estilos.secao}>Cidade atual</Text>
              {local.situacao === 'achou' ? (
                linha(local.cidade, 'pela sua localização')
              ) : local.situacao === 'procurando' ? (
                <View style={[estilos.aviso, { flexDirection: 'row', alignItems: 'center', gap: 12 }]}>
                  <ActivityIndicator color={cores.verde} />
                  <Text style={estilos.avisoTexto}>Procurando sua localização...</Text>
                </View>
              ) : local.situacao === 'pedir' ? (
                aviso('crosshairs-gps', 'Use a localização do celular para achar a cidade onde você está.', { titulo: 'Usar minha localização', onPress: pedir })
              ) : local.situacao === 'negado' ? (
                aviso('map-marker-off-outline', 'A localização está desligada para o Acha Fácil. Ligue nas configurações ou escolha a cidade abaixo.', {
                  titulo: 'Abrir configurações',
                  onPress: () => Linking.openSettings(),
                })
              ) : local.situacao === 'longe' ? (
                aviso('map-marker-question-outline', 'Você não está em nenhuma cidade atendida pelo Acha Fácil. Escolha abaixo onde quer buscar.')
              ) : (
                aviso('map-marker-alert-outline', 'Não foi possível achar sua localização agora.', { titulo: 'Tentar de novo', onPress: pedir })
              )}

              {estados.length > 1 && (
                <>
                  <Text style={estilos.secao}>Estado</Text>
                  <View style={estilos.estados}>
                    {estados.map((e) => (
                      <Pilula key={e.uf} texto={`${e.nome} (${e.uf})`} ativo={estado === e.uf} onPress={() => setEstado(e.uf)} />
                    ))}
                  </View>
                </>
              )}
            </>
          )}

          {lista.length > 0 && <Text style={estilos.secao}>{t !== '' ? 'Cidades encontradas' : `Cidades de ${nomeEstado(estado)}`}</Text>}
          {lista.map((c) => linha(c, c.slug === atual.slug ? 'última escolha' : undefined))}
          {lista.length === 0 && (
            <Text style={estilos.vazio}>
              {t !== '' ? (daqui && acha(daqui) ? 'Sua cidade atual é a única com esse nome.' : 'Ainda não atendemos essa cidade.') : `Por enquanto, nenhuma outra cidade de ${nomeEstado(estado)}.`}
            </Text>
          )}
        </View>
      </ScrollView>
      <View style={[estilos.rodape, { paddingBottom: baixo + 16 }]}>
        <Botao titulo={`Confirmar: ${escolhida.curto}`} tipo="amarelo" onPress={() => aoEscolher(escolhida)} />
      </View>
    </View>
  );
}

const estilos = StyleSheet.create({
  fundo: { backgroundColor: cores.fundo, zIndex: 10 },
  estados: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 6 },
  secao: { fontSize: fonte.grande, fontWeight: '900', color: cores.texto, marginTop: 10, marginBottom: 10 },
  cidade: { ...cartao, flexDirection: 'row', alignItems: 'center', gap: 12, minHeight: 64, paddingHorizontal: 14, paddingVertical: 8, marginBottom: 10, borderWidth: 2 },
  marcada: { borderColor: cores.verde, backgroundColor: cores.verdeClaro },
  nome: { fontSize: fonte.normal, fontWeight: '900', color: cores.texto },
  sub: { fontSize: 14, color: cores.textoSuave, marginTop: 1 },
  aviso: { ...cartao, padding: 14, marginBottom: 10 },
  avisoTexto: { fontSize: fonte.pequena, color: cores.texto, lineHeight: 22 },
  vazio: { fontSize: fonte.normal, color: cores.textoSuave, textAlign: 'center', marginTop: 16 },
  rodape: { paddingHorizontal: 16, paddingTop: 12, borderTopWidth: 1, borderColor: cores.borda, backgroundColor: cores.cartao },
});
