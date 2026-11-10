import { MaterialCommunityIcons } from '@expo/vector-icons';
import * as Location from 'expo-location';
import { useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Linking, Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { chamar, type Cidade, type Estado } from '../api/cliente';
import { cartao, cores, fonte } from '../tema';
import { Cabecalho } from './Cabecalho';
import { Botao, Campo, Pilula } from './ui';

/** Sem acento e em minúsculas, para "pocos" achar "Poços". */
function simples(t: string) {
  return t.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
}

/** Estados do Brasil, para o "Quero minha Cidade!". */
const UFS: [string, string][] = [
  ['AC', 'Acre'], ['AL', 'Alagoas'], ['AP', 'Amapá'], ['AM', 'Amazonas'], ['BA', 'Bahia'], ['CE', 'Ceará'],
  ['DF', 'Distrito Federal'], ['ES', 'Espírito Santo'], ['GO', 'Goiás'], ['MA', 'Maranhão'], ['MT', 'Mato Grosso'],
  ['MS', 'Mato Grosso do Sul'], ['MG', 'Minas Gerais'], ['PA', 'Pará'], ['PB', 'Paraíba'], ['PR', 'Paraná'],
  ['PE', 'Pernambuco'], ['PI', 'Piauí'], ['RJ', 'Rio de Janeiro'], ['RN', 'Rio Grande do Norte'],
  ['RS', 'Rio Grande do Sul'], ['RO', 'Rondônia'], ['RR', 'Roraima'], ['SC', 'Santa Catarina'], ['SP', 'São Paulo'],
  ['SE', 'Sergipe'], ['TO', 'Tocantins'],
];

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
  totais,
  aoEscolher,
}: {
  atual: Cidade;
  estados: Estado[];
  cidades: Cidade[];
  /** Comércios por cidade (slug), vindos com a lista do servidor. */
  totais: Record<string, number>;
  aoEscolher: (c: Cidade) => void;
}) {
  const baixo = useSafeAreaInsets().bottom;
  const [escolhida, setEscolhida] = useState<Cidade>(atual);
  const [termo, setTermo] = useState('');
  const [estado, setEstado] = useState(atual.estado);
  const [local, setLocal] = useState<Local>({ situacao: 'procurando' });
  // se a pessoa já tocou numa cidade, a localização que chegar depois não troca a escolha dela
  const mexeu = useRef(false);

  // a lista nova do servidor chegou com a tela aberta: a cidade marcada saiu (desligada) ou mudou de nome
  useEffect(() => {
    setEscolhida((e) => cidades.find((c) => c.slug === e.slug) ?? cidades.find((c) => c.slug === atual.slug) ?? cidades[0]);
    setEstado((uf) => (cidades.some((c) => c.estado === uf) ? uf : (cidades[0]?.estado ?? uf)));
  }, [cidades, atual.slug]);

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

  // "Quero minha Cidade!": pede o Acha Fácil na cidade da pessoa (fica na lista da administração).
  // Primeiro o estado (lista fixa); depois a cidade, escolhida na lista do IBGE daquele estado (api/municipios.php).
  const [quero, setQuero] = useState<'fechado' | 'aberto' | 'enviando' | 'enviado'>('fechado');
  const [queroUf, setQueroUf] = useState('');
  const [queroCidade, setQueroCidade] = useState('');
  const [queroEscolhida, setQueroEscolhida] = useState<string | null>(null);
  const [listaUf, setListaUf] = useState<string[] | null>(null);
  const [queroErro, setQueroErro] = useState('');
  const [listaEstados, setListaEstados] = useState(false);
  const listas = useRef<Record<string, string[]>>({});
  const escolherUf = async (uf: string) => {
    setQueroUf(uf);
    setQueroCidade('');
    setQueroEscolhida(null);
    setQueroErro('');
    if (listas.current[uf]) return setListaUf(listas.current[uf]);
    setListaUf(null);
    try {
      const r = await chamar<{ cidades: string[] }>(`municipios.php?uf=${uf}`);
      listas.current[uf] = r.cidades;
      setListaUf(r.cidades);
    } catch (e) {
      setQueroErro(e instanceof Error ? e.message : 'Não foi possível carregar as cidades.');
    }
  };
  const digitarCidade = (texto: string) => {
    setQueroCidade(texto);
    // digitou o nome inteiro (sem ligar para acento): já vale como escolhida
    const igual = (listaUf ?? []).find((c) => simples(c) === simples(texto.trim()));
    setQueroEscolhida(igual ?? null);
  };
  const tq = simples(queroCidade.trim());
  const sugestoes =
    listaUf && tq.length >= 1 && !queroEscolhida
      ? [...listaUf.filter((c) => simples(c).startsWith(tq)), ...listaUf.filter((c) => !simples(c).startsWith(tq) && simples(c).includes(tq))].slice(0, 6)
      : [];
  const enviarQuero = async () => {
    if (!queroUf) return setQueroErro('Escolha o estado.');
    if (!queroEscolhida) return setQueroErro('Escolha a cidade na lista.');
    setQueroErro('');
    setQuero('enviando');
    try {
      await chamar('quero-cidade.php', 'POST', { cidade: queroEscolhida, uf: queroUf });
      setQuero('enviado');
    } catch (e) {
      setQueroErro(e instanceof Error ? e.message : 'Não foi possível enviar. Tente de novo.');
      setQuero('aberto');
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
                <Aviso icone="crosshairs-gps" texto="Use a localização do celular para achar a cidade onde você está." botao={{ titulo: 'Usar minha localização', onPress: pedir }} />
              ) : local.situacao === 'negado' ? (
                <Aviso
                  icone="map-marker-off-outline"
                  texto="A localização está desligada para o Acha Fácil. Ligue nas configurações ou escolha a cidade abaixo."
                  botao={{ titulo: 'Abrir configurações', onPress: () => Linking.openSettings() }}
                />
              ) : local.situacao === 'longe' ? (
                <Aviso icone="map-marker-question-outline" texto="Você não está em nenhuma cidade atendida pelo Acha Fácil. Escolha abaixo onde quer buscar." />
              ) : (
                <Aviso icone="map-marker-alert-outline" texto="Não foi possível achar sua localização agora." botao={{ titulo: 'Tentar de novo', onPress: pedir }} />
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

          <View style={estilos.quero}>
            {quero === 'enviado' ? (
              <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
                <MaterialCommunityIcons name="check-circle" size={30} color={cores.verde} />
                <Text style={[estilos.avisoTexto, { flex: 1 }]}>
                  Pedido anotado! Quanto mais gente pedir {queroEscolhida}, mais rápido o Acha Fácil chega aí.
                </Text>
              </View>
            ) : quero === 'fechado' ? (
              <>
                <Text style={estilos.queroTitulo}>Sua cidade não está aqui?</Text>
                <Botao titulo="Quero minha Cidade!" icone="map-marker-plus" tipo="principal" onPress={() => {
                    setQuero('aberto');
                    if (!queroUf) escolherUf(estado);   // começa no estado que a pessoa está olhando
                  }}
                  estilo={{ marginTop: 10 }}
                />
              </>
            ) : (
              <>
                <Text style={estilos.queroTitulo}>Quero minha Cidade!</Text>
                <Text style={[estilos.avisoTexto, { marginTop: 4, marginBottom: 10 }]}>Conte onde você mora. As cidades mais pedidas entram primeiro.</Text>
                <Text style={estilos.queroRotulo}>1. Estado</Text>
                <Pressable
                  accessibilityRole="button"
                  accessibilityLabel={`Estado: ${queroUf ? (UFS.find((u) => u[0] === queroUf)?.[1] ?? queroUf) : 'escolha'}`}
                  onPress={() => setListaEstados(true)}
                  style={estilos.select}
                >
                  <MaterialCommunityIcons name="map-outline" size={24} color={cores.textoSuave} />
                  <Text style={[estilos.selectTexto, !queroUf && { color: cores.textoSuave }]}>
                    {queroUf ? `${UFS.find((u) => u[0] === queroUf)?.[1] ?? queroUf} (${queroUf})` : 'Escolha o estado'}
                  </Text>
                  <MaterialCommunityIcons name="chevron-down" size={28} color={cores.verde} />
                </Pressable>
                <Modal visible={listaEstados} transparent animationType="fade" onRequestClose={() => setListaEstados(false)} statusBarTranslucent>
                  <Pressable style={estilos.modalFundo} onPress={() => setListaEstados(false)}>
                    <View style={estilos.modalCaixa}>
                      <Text style={estilos.modalTitulo}>Escolha o estado</Text>
                      <ScrollView>
                        {UFS.map(([uf, nome]) => (
                          <Pressable
                            key={uf}
                            accessibilityRole="button"
                            accessibilityState={{ selected: queroUf === uf }}
                            onPress={() => {
                              setListaEstados(false);
                              escolherUf(uf);
                            }}
                            style={[estilos.opcao, queroUf === uf && { backgroundColor: cores.verdeClaro }]}
                          >
                            <Text style={[estilos.opcaoTexto, queroUf === uf && { color: cores.verde, fontWeight: '900' }]}>{nome}</Text>
                            <Text style={estilos.opcaoUf}>{uf}</Text>
                          </Pressable>
                        ))}
                      </ScrollView>
                    </View>
                  </Pressable>
                </Modal>
                {queroUf !== '' && (
                  <>
                    <Text style={estilos.queroRotulo}>2. Cidade</Text>
                    {listaUf === null && queroErro === '' ? (
                      <ActivityIndicator color={cores.verde} style={{ marginVertical: 10 }} />
                    ) : (
                      <Campo
                        icone="city-variant-outline"
                        placeholder={`Digite a cidade (${queroUf})`}
                        value={queroCidade}
                        onChangeText={digitarCidade}
                        maxLength={80}
                        autoCapitalize="words"
                        autoCorrect={false}
                      />
                    )}
                    {queroEscolhida && (
                      <Text style={estilos.queroOk}>
                        <MaterialCommunityIcons name="check-circle" size={18} color={cores.verde} /> {queroEscolhida} - {queroUf}
                      </Text>
                    )}
                    {sugestoes.map((c) => (
                      <Pressable
                        key={c}
                        accessibilityRole="button"
                        onPress={() => {
                          setQueroCidade(c);
                          setQueroEscolhida(c);
                          setQueroErro('');
                        }}
                        style={estilos.sugestao}
                      >
                        <MaterialCommunityIcons name="map-marker-outline" size={22} color={cores.verde} />
                        <Text style={estilos.sugestaoTexto}>{c}</Text>
                      </Pressable>
                    ))}
                    {listaUf && tq.length >= 2 && !queroEscolhida && sugestoes.length === 0 && (
                      <Text style={estilos.avisoTexto}>Nenhuma cidade de {queroUf} com esse nome.</Text>
                    )}
                  </>
                )}
                {queroErro !== '' && <Text style={estilos.queroErro}>{queroErro}</Text>}
                <Botao
                  titulo={quero === 'enviando' ? 'Enviando...' : 'Enviar pedido'}
                  icone="send"
                  desativado={quero === 'enviando' || !queroEscolhida}
                  onPress={enviarQuero}
                  estilo={{ marginTop: 12 }}
                />
              </>
            )}
          </View>
        </View>
      </ScrollView>
      <View style={[estilos.rodape, { paddingBottom: baixo + 16 }]}>
        <Botao titulo={`Confirmar: ${escolhida.curto}`} tipo="amarelo" onPress={() => aoEscolher(escolhida)} />
      </View>
    </View>
  );
}

/** Caixa da seção "Cidade atual" quando a cidade ainda não foi achada pela localização. */
function Aviso({ icone, texto, botao }: { icone: keyof typeof MaterialCommunityIcons.glyphMap; texto: string; botao?: { titulo: string; onPress: () => void } }) {
  return (
    <View style={estilos.aviso}>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
        <MaterialCommunityIcons name={icone} size={28} color={cores.verde} />
        <Text style={[estilos.avisoTexto, { flex: 1 }]}>{texto}</Text>
      </View>
      {botao && <Botao titulo={botao.titulo} tipo="contorno" onPress={botao.onPress} estilo={{ marginTop: 12 }} />}
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
  quero: { ...cartao, padding: 16, marginTop: 18, borderWidth: 2, borderColor: cores.amarelo, backgroundColor: cores.cartao },
  queroTitulo: { fontSize: fonte.grande, fontWeight: '900', color: cores.verde },
  queroErro: { fontSize: fonte.pequena, color: cores.erro, marginTop: 8, fontWeight: '700' },
  queroRotulo: { fontSize: fonte.pequena, fontWeight: '900', color: cores.texto, marginTop: 6, marginBottom: 8 },
  queroOk: { fontSize: fonte.pequena, fontWeight: '800', color: cores.verde, marginTop: 8 },
  select: { flexDirection: 'row', alignItems: 'center', gap: 10, minHeight: 56, paddingHorizontal: 14, borderRadius: 14, borderWidth: 1.5, borderColor: cores.borda, backgroundColor: cores.cartao, marginBottom: 8 },
  selectTexto: { flex: 1, fontSize: fonte.normal, fontWeight: '700', color: cores.texto },
  modalFundo: { flex: 1, backgroundColor: 'rgba(0,0,0,0.45)', justifyContent: 'center', padding: 24 },
  modalCaixa: { ...cartao, maxHeight: '80%', paddingVertical: 12 },
  modalTitulo: { fontSize: fonte.grande, fontWeight: '900', color: cores.verde, paddingHorizontal: 18, paddingBottom: 8 },
  opcao: { flexDirection: 'row', alignItems: 'center', minHeight: 52, paddingHorizontal: 18, borderTopWidth: 1, borderColor: cores.borda },
  opcaoTexto: { flex: 1, fontSize: fonte.normal, color: cores.texto },
  opcaoUf: { fontSize: 15, fontWeight: '800', color: cores.textoSuave },
  sugestao: { flexDirection: 'row', alignItems: 'center', gap: 10, minHeight: 48, paddingHorizontal: 12, borderBottomWidth: 1, borderColor: cores.borda },
  sugestaoTexto: { fontSize: fonte.normal - 1, color: cores.texto, flex: 1 },
  vazio: { fontSize: fonte.normal, color: cores.textoSuave, textAlign: 'center', marginTop: 16 },
  rodape: { paddingHorizontal: 16, paddingTop: 12, borderTopWidth: 1, borderColor: cores.borda, backgroundColor: cores.cartao },
});
