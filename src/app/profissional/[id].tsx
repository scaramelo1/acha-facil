import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useState } from 'react';
import { Alert, Linking, Pressable, ScrollView, Share, StyleSheet, Text, TextInput, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { chamar, registrarContato, type Profissional } from '../../api/cliente';
import { useBarraClara } from '../../componentes/BarraClara';
import { Botao, Estrelas, Falha, Carregando, Foto, Icone, SeloVerificado, Titulo, estilos as ui, useCarregar } from '../../componentes/ui';
import { useConta } from '../../conta';
import { cores, fonte, RAIO, sombra, TOQUE } from '../../tema';

type NomeIcone = keyof typeof MaterialCommunityIcons.glyphMap;
const CAPA = 230;

export default function TelaProfissional() {
  useBarraClara();
  const { id } = useLocalSearchParams<{ id: string }>();
  const { usuario } = useConta();
  const router = useRouter();
  const topo = useSafeAreaInsets().top;
  const { dados: p, setDados, erro, carregando, recarregar } = useCarregar(
    () => chamar<{ profissional: Profissional }>(`profissional.php?id=${id}`).then((r) => r.profissional),
    `${id}-${usuario?.id ?? 0}`,
  );

  if (carregando && !p)
    return (
      <>
        <Stack.Screen options={{ headerShown: true, title: '' }} />
        <Carregando />
      </>
    );
  if (erro && !p)
    return (
      <>
        <Stack.Screen options={{ headerShown: true, title: '' }} />
        <Falha mensagem={erro} tentar={recarregar} />
      </>
    );
  if (!p) return null;

  const abrir = (tipo: 'whatsapp' | 'ligar' | 'instagram' | 'mapa' | 'site', link: string) => {
    registrarContato(p.id, tipo);
    Linking.openURL(link).catch(() => Alert.alert('Não foi possível abrir', 'Confira se o aplicativo está instalado.'));
  };
  const compartilhar = () =>
    Share.share({ message: `${p.nome} (${p.tipo}) no Acha Fácil${p.telefoneFormatado ? `: ${p.telefoneFormatado}` : ''}` });

  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <ScrollView contentContainerStyle={{ paddingBottom: 48 }}>
        {/* capa: a foto que o dono colocar; sem foto, a cor da área com o ícone grande */}
        <View style={[estilos.capa, { backgroundColor: p.cor }]}>
          {p.foto ? (
            <Image source={p.foto} style={StyleSheet.absoluteFill} contentFit="cover" />
          ) : (
            <View style={estilos.capaIcone}>
              <Icone nome={p.icone} tamanho={120} cor="rgba(255,255,255,0.28)" />
            </View>
          )}
          <View style={[estilos.capaBotoes, { top: topo + 8 }]}>
            <Redondo icone="arrow-left" rotulo="Voltar" onPress={() => (router.canGoBack() ? router.back() : router.replace('/'))} />
            <BotaoSalvar p={p} aoMudar={(f) => setDados({ ...p, favorito: f })} />
          </View>
        </View>

        <View style={{ paddingHorizontal: 16 }}>
          {/* cartão com nome e tipo, por cima da capa */}
          <View style={estilos.cabeca}>
            <Foto foto={null} icone={p.icone} cor={p.cor} largura={72} altura={72} />
            <View style={{ flex: 1, gap: 6 }}>
              <Text style={estilos.nome}>{p.nome}</Text>
              <View style={{ flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 8 }}>
                <View style={estilos.tipo}>
                  <Text style={estilos.tipoTexto}>{p.tipo}</Text>
                </View>
                {p.verificado && <SeloVerificado compacto />}
              </View>
              {p.avaliacoes > 0 && (
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                  <Estrelas nota={p.nota} tamanho={18} />
                  <Text style={estilos.sub}>
                    {p.nota.toFixed(1).replace('.', ',')} ({p.avaliacoes})
                  </Text>
                </View>
              )}
            </View>
          </View>

          {p.whatsapp && (
            <Pressable accessibilityRole="button" onPress={() => abrir('whatsapp', p.whatsapp!)} style={({ pressed }) => [estilos.whats, pressed && { opacity: 0.88 }]}>
              <MaterialCommunityIcons name="whatsapp" size={30} color="#fff" />
              <Text style={estilos.whatsTexto}>Conversar no WhatsApp</Text>
            </Pressable>
          )}
          <View style={estilos.acoes}>
            {p.telefone && <Acao icone="phone" texto="Ligar" onPress={() => abrir('ligar', `tel:${p.telefone}`)} />}
            {p.mapa && <Acao icone="map-marker-outline" texto="Como chegar" onPress={() => abrir('mapa', p.mapa!)} />}
            <Acao icone="share-variant" texto="Compartilhar" onPress={compartilhar} />
          </View>

          {!p.whatsapp && !p.telefone && (
            <View style={estilos.aviso}>
              <MaterialCommunityIcons name="information-outline" size={24} color={cores.verde} />
              <Text style={[ui.texto, { flex: 1 }]}>Ainda não temos o telefone deste lugar. Use o "Como chegar" para ir até lá.</Text>
            </View>
          )}

          {p.descricao ? <Text style={[ui.texto, { marginTop: 16 }]}>{p.descricao}</Text> : null}

          {p.servicos.length > 0 && (
            <>
              <Titulo>O que faz</Titulo>
              <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
                {p.servicos.map((s) => (
                  <View key={s} style={estilos.servico}>
                    <Text style={{ fontSize: fonte.normal, fontWeight: '700', color: cores.verde }}>{s}</Text>
                  </View>
                ))}
              </View>
            </>
          )}

          <Titulo>Informações</Titulo>
          <View style={estilos.caixa}>
            {p.endereco || p.bairro ? (
              <Linha
                icone="map-marker-outline"
                texto={p.endereco ?? p.bairro ?? ''}
                detalhe={[p.endereco ? p.bairro : null, p.cidade].filter(Boolean).join(' · ')}
                onPress={p.mapa ? () => abrir('mapa', p.mapa!) : undefined}
              />
            ) : null}
            {p.telefoneFormatado ? <Linha icone="phone-outline" texto={p.telefoneFormatado} onPress={() => abrir('ligar', `tel:${p.telefone}`)} /> : null}
            {p.instagram ? <Linha icone="instagram" texto="Ver Instagram" onPress={() => abrir('instagram', p.instagram!)} /> : null}
            {p.site ? <Linha icone="web" texto={siteCurto(p.site)} onPress={() => abrir('site', p.site!)} /> : null}
            {p.atendeEmCasa && <Linha icone="home-outline" texto="Vai até você" />}
            <Linha icone="clock-outline" texto="Horário de funcionamento" detalhe={p.horario ?? 'Ainda não informado'} ultima />
          </View>

          {p.linkAssumir && (
            <Pressable accessibilityRole="button" onPress={() => WebBrowser.openBrowserAsync(p.linkAssumir!)} style={({ pressed }) => [estilos.dono, pressed && { opacity: 0.9 }]}>
              <MaterialCommunityIcons name="storefront-outline" size={38} color={cores.verde} />
              <View style={{ flex: 1 }}>
                <Text style={estilos.donoTitulo}>Este comércio é seu?</Text>
                <Text style={estilos.donoTexto}>Confira os planos para corrigir os dados e colocar fotos.</Text>
              </View>
              <MaterialCommunityIcons name="chevron-right" size={28} color={cores.verde} />
            </Pressable>
          )}

          <Avaliacoes p={p} aoAtualizar={setDados} />

          {p.linkRemover && (
            <Text style={estilos.fonte}>
              Informações públicas do comércio.{' '}
              <Text style={{ textDecorationLine: 'underline' }} onPress={() => WebBrowser.openBrowserAsync(p.linkRemover!)}>
                Algo errado ou quer tirar do app?
              </Text>
            </Text>
          )}
        </View>
      </ScrollView>
    </>
  );
}

/** "https://www.exemplo.com.br/pagina" → "exemplo.com.br" */
function siteCurto(url: string) {
  return url.replace(/^https?:\/\/(www\.)?/, '').replace(/\/.*$/, '');
}

/** Botão redondo branco sobre a capa (voltar, favoritar). */
function Redondo({ icone, rotulo, cor = cores.texto, onPress }: { icone: NomeIcone; rotulo: string; cor?: string; onPress: () => void }) {
  return (
    <Pressable accessibilityRole="button" accessibilityLabel={rotulo} onPress={onPress} style={({ pressed }) => [estilos.redondo, pressed && { opacity: 0.85 }]}>
      <MaterialCommunityIcons name={icone} size={28} color={cor} />
    </Pressable>
  );
}

function Acao({ icone, texto, onPress }: { icone: NomeIcone; texto: string; onPress: () => void }) {
  return (
    <Pressable accessibilityRole="button" accessibilityLabel={texto} onPress={onPress} style={({ pressed }) => [estilos.acao, pressed && { opacity: 0.85 }]}>
      <MaterialCommunityIcons name={icone} size={30} color={cores.verde} />
      <Text style={estilos.acaoTexto} numberOfLines={1}>
        {texto}
      </Text>
    </Pressable>
  );
}

function Linha({ icone, texto, detalhe, onPress, ultima }: { icone: NomeIcone; texto: string; detalhe?: string; onPress?: () => void; ultima?: boolean }) {
  const conteudo = (
    <>
      <View style={estilos.linhaIcone}>
        <MaterialCommunityIcons name={icone} size={22} color={cores.verde} />
      </View>
      <View style={{ flex: 1 }}>
        <Text style={estilos.linhaTexto}>{texto}</Text>
        {detalhe ? <Text style={estilos.sub}>{detalhe}</Text> : null}
      </View>
      {onPress && <MaterialCommunityIcons name="chevron-right" size={26} color={cores.texto} />}
    </>
  );
  const estilo = [estilos.linha, ultima && { borderBottomWidth: 0 }];
  return onPress ? (
    <Pressable accessibilityRole="link" onPress={onPress} style={estilo}>
      {conteudo}
    </Pressable>
  ) : (
    <View style={estilo}>{conteudo}</View>
  );
}

function BotaoSalvar({ p, aoMudar }: { p: Profissional; aoMudar: (f: boolean) => void }) {
  const { usuario } = useConta();
  const router = useRouter();
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={p.favorito ? 'Tirar dos salvos' : 'Salvar'}
      style={({ pressed }) => [estilos.redondo, pressed && { opacity: 0.85 }]}
      onPress={async () => {
        if (!usuario) return router.push('/entrar');
        const novo = !p.favorito;
        aoMudar(novo);
        try {
          await chamar('favoritos.php', 'POST', { id: p.id, salvar: novo });
        } catch (e) {
          aoMudar(!novo);
          Alert.alert('Não foi possível salvar', e instanceof Error ? e.message : '');
        }
      }}
    >
      <MaterialCommunityIcons name={p.favorito ? 'heart' : 'heart-outline'} size={28} color={p.favorito ? '#E03131' : cores.texto} />
    </Pressable>
  );
}

function Avaliacoes({ p, aoAtualizar }: { p: Profissional; aoAtualizar: (p: Profissional) => void }) {
  const { usuario } = useConta();
  const router = useRouter();
  const [editando, setEditando] = useState(false);
  const [estrelas, setEstrelas] = useState(p.minhaAvaliacao?.estrelas ?? 0);
  const [comentario, setComentario] = useState(p.minhaAvaliacao?.comentario ?? '');
  const [enviando, setEnviando] = useState(false);

  const enviar = async () => {
    if (estrelas < 1) return Alert.alert('Escolha as estrelas', 'Toque de 1 a 5 estrelas.');
    setEnviando(true);
    try {
      const r = await chamar<{ profissional: Profissional }>('avaliar.php', 'POST', { id: p.id, estrelas, comentario });
      aoAtualizar(r.profissional);
      setEditando(false);
    } catch (e) {
      Alert.alert('Não foi possível enviar', e instanceof Error ? e.message : '');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <View>
      <Titulo>Avaliações</Titulo>
      {!usuario ? (
        <Botao titulo="Entrar para avaliar" icone="star-outline" tipo="contorno" onPress={() => router.push('/entrar')} />
      ) : editando ? (
        <View style={estilos.caixaAval}>
          <Text style={[ui.texto, { fontWeight: '700' }]}>Sua nota</Text>
          <View style={{ flexDirection: 'row', justifyContent: 'center', marginVertical: 8 }}>
            {[1, 2, 3, 4, 5].map((i) => (
              <Pressable key={i} accessibilityRole="button" accessibilityLabel={`${i} estrelas`} onPress={() => setEstrelas(i)} style={{ padding: 6 }}>
                <MaterialCommunityIcons name={estrelas >= i ? 'star' : 'star-outline'} size={44} color={cores.estrela} />
              </Pressable>
            ))}
          </View>
          <TextInput
            value={comentario}
            onChangeText={setComentario}
            placeholder="Conte como foi o atendimento (opcional)"
            placeholderTextColor={cores.textoSuave}
            multiline
            maxLength={1000}
            style={[ui.campo, { minHeight: 110, paddingTop: 12, textAlignVertical: 'top' }]}
          />
          <View style={{ gap: 10, marginTop: 12 }}>
            <Botao titulo={enviando ? 'Enviando...' : 'Enviar avaliação'} icone="send" onPress={enviar} desativado={enviando} />
            <Botao titulo="Cancelar" tipo="contorno" onPress={() => setEditando(false)} />
          </View>
        </View>
      ) : (
        <Botao
          titulo={p.minhaAvaliacao ? 'Mudar minha avaliação' : 'Avaliar este lugar'}
          icone="star-outline"
          tipo="contorno"
          onPress={() => setEditando(true)}
        />
      )}

      {p.comentarios.map((c) => (
        <View key={c.id} style={estilos.comentario}>
          <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
            <Text style={[ui.texto, { fontWeight: '700' }]}>{c.autor}</Text>
            <Text style={estilos.sub}>{c.data}</Text>
          </View>
          <Estrelas nota={c.estrelas} tamanho={18} />
          {c.comentario ? <Text style={ui.texto}>{c.comentario}</Text> : null}
          {c.resposta ? (
            <View style={estilos.resposta}>
              <Text style={[estilos.sub, { fontWeight: '700' }]}>Resposta do comércio</Text>
              <Text style={ui.texto}>{c.resposta}</Text>
            </View>
          ) : null}
        </View>
      ))}
    </View>
  );
}

const estilos = StyleSheet.create({
  capa: { height: CAPA, overflow: 'hidden', borderBottomLeftRadius: 28, borderBottomRightRadius: 28 },
  capaIcone: { ...StyleSheet.absoluteFillObject, alignItems: 'center', justifyContent: 'center' },
  capaBotoes: { position: 'absolute', left: 16, right: 16, flexDirection: 'row', justifyContent: 'space-between' },
  redondo: { width: TOQUE, height: TOQUE, borderRadius: TOQUE / 2, backgroundColor: '#fff', alignItems: 'center', justifyContent: 'center', ...sombra },
  cabeca: { flexDirection: 'row', alignItems: 'center', gap: 14, backgroundColor: cores.cartao, borderRadius: RAIO, padding: 14, marginTop: -56, ...sombra },
  nome: { fontSize: fonte.grande, fontWeight: '900', color: cores.texto, lineHeight: 29 },
  tipo: { backgroundColor: cores.amarelo, borderRadius: 12, paddingHorizontal: 12, paddingVertical: 4 },
  tipoTexto: { fontSize: fonte.pequena, fontWeight: '900', color: cores.verde },
  sub: { fontSize: fonte.pequena, color: cores.textoSuave },
  whats: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 12, minHeight: 60, borderRadius: 18, backgroundColor: cores.verde, marginTop: 16 },
  whatsTexto: { color: '#fff', fontSize: fonte.media, fontWeight: '800' },
  acoes: { flexDirection: 'row', gap: 10, marginTop: 12 },
  acao: { flex: 1, minHeight: 84, borderRadius: 18, backgroundColor: cores.cartao, alignItems: 'center', justifyContent: 'center', gap: 4, padding: 6, ...sombra },
  acaoTexto: { fontSize: 15, fontWeight: '700', color: cores.texto },
  aviso: { flexDirection: 'row', alignItems: 'center', gap: 10, backgroundColor: cores.verdeClaro, borderRadius: 16, padding: 12, marginTop: 12 },
  servico: { backgroundColor: cores.fundoSuave, borderRadius: 12, paddingHorizontal: 12, paddingVertical: 8 },
  caixa: { backgroundColor: cores.cartao, borderRadius: RAIO, paddingHorizontal: 14, ...sombra },
  linha: { flexDirection: 'row', alignItems: 'center', gap: 12, minHeight: 64, paddingVertical: 8, borderBottomWidth: 1, borderColor: cores.borda },
  linhaIcone: { width: 40, height: 40, borderRadius: 20, backgroundColor: cores.verdeClaro, alignItems: 'center', justifyContent: 'center' },
  linhaTexto: { fontSize: fonte.normal, fontWeight: '700', color: cores.texto },
  dono: { flexDirection: 'row', alignItems: 'center', gap: 14, backgroundColor: cores.amarelo, borderRadius: RAIO, padding: 18, marginTop: 20 },
  donoTitulo: { fontSize: fonte.media, fontWeight: '900', color: cores.verde },
  donoTexto: { fontSize: fonte.pequena, color: cores.verde },
  fonte: { fontSize: 14, color: cores.textoSuave, textAlign: 'center', marginTop: 28 },
  caixaAval: { backgroundColor: cores.cartao, borderRadius: RAIO, padding: 16, ...sombra },
  comentario: { backgroundColor: cores.cartao, borderRadius: 18, padding: 14, gap: 6, marginTop: 12, ...sombra },
  resposta: { backgroundColor: cores.fundoSuave, borderRadius: 12, padding: 10, marginTop: 4 },
});
