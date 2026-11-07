import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import * as WebBrowser from 'expo-web-browser';
import { Alert, Linking, Pressable, ScrollView, Share, StyleSheet, Text, TextInput, View } from 'react-native';

import { chamar, registrarContato, type Profissional } from '../../api/cliente';
import {
  Avatar,
  Botao,
  Carregando,
  Estrelas,
  Falha,
  SeloVerificado,
  Titulo,
  estilos as ui,
  useCarregar,
} from '../../componentes/ui';
import { useBarraClara } from '../../componentes/BarraClara';
import { useConta } from '../../conta';
import { cores, fonte, RAIO, sombra, TOQUE } from '../../tema';

export default function TelaProfissional() {
  const { id } = useLocalSearchParams<{ id: string }>();
  useBarraClara();
  const { usuario } = useConta();
  const { dados: p, setDados, erro, carregando, recarregar } = useCarregar(
    () => chamar<{ profissional: Profissional }>(`profissional.php?id=${id}`).then((r) => r.profissional),
    `${id}-${usuario?.id ?? 0}`,
  );

  if (carregando && !p) return <Carregando />;
  if (erro && !p) return <Falha mensagem={erro} tentar={recarregar} />;
  if (!p) return null;

  const abrir = (tipo: 'whatsapp' | 'ligar' | 'instagram' | 'mapa' | 'site', link: string) => {
    registrarContato(p.id, tipo);
    Linking.openURL(link).catch(() => Alert.alert('Não foi possível abrir', 'Confira se o aplicativo está instalado.'));
  };
  const endereco = [p.endereco, p.bairro].filter(Boolean).join(' · ');

  return (
    <>
      <Stack.Screen
        options={{
          title: '',
          headerStyle: { backgroundColor: p.cor },
          headerTintColor: '#fff',
          headerRight: () => <BotaoSalvar p={p} aoMudar={(f) => setDados({ ...p, favorito: f })} />,
        }}
      />
      <ScrollView contentContainerStyle={{ paddingBottom: 48 }}>
        {/* cabeçalho com a cor da área */}
        <View style={[estilos.topo, { backgroundColor: p.cor }]}>
          <View style={estilos.topoAvatar}>
            <Avatar foto={p.foto} icone={p.icone} cor={p.cor} tamanho={84} />
          </View>
          <Text style={estilos.nome}>{p.nome}</Text>
          <View style={estilos.tipo}>
            <Text style={estilos.tipoTexto}>{p.tipo}</Text>
          </View>
          {p.responsavel && p.responsavel !== p.nome ? <Text style={estilos.responsavel}>{p.responsavel}</Text> : null}
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'center', gap: 8, marginTop: 4 }}>
            {p.verificado && <SeloVerificado claro />}
            {p.avaliacoes > 0 && (
              <View style={estilos.nota}>
                <MaterialCommunityIcons name="star" size={18} color={cores.estrela} />
                <Text style={estilos.notaTexto}>
                  {p.nota.toFixed(1).replace('.', ',')} · {p.avaliacoes} {p.avaliacoes === 1 ? 'avaliação' : 'avaliações'}
                </Text>
              </View>
            )}
          </View>
        </View>

        {/* ações principais em botões redondos */}
        <View style={estilos.acoes}>
          {p.whatsapp && <Acao icone="whatsapp" texto="WhatsApp" cor={cores.whatsapp} onPress={() => abrir('whatsapp', p.whatsapp!)} />}
          {p.telefone && <Acao icone="phone" texto="Ligar" cor={cores.verde} onPress={() => abrir('ligar', `tel:${p.telefone}`)} />}
          {p.mapa && <Acao icone="map-marker-radius" texto="Como chegar" cor="#1864AB" onPress={() => abrir('mapa', p.mapa!)} />}
          <Acao
            icone="share-variant"
            texto="Indicar"
            cor={cores.textoSuave}
            onPress={() =>
              Share.share({ message: `${p.nome} (${p.tipo}) no Acha Fácil${p.telefoneFormatado ? `: ${p.telefoneFormatado}` : ''}` })
            }
          />
        </View>

        <View style={{ paddingHorizontal: 16 }}>
          {!p.whatsapp && !p.telefone && (
            <View style={estilos.aviso}>
              <MaterialCommunityIcons name="information-outline" size={24} color={cores.verde} />
              <Text style={[ui.texto, { flex: 1 }]}>Ainda não temos o telefone deste lugar. Use o mapa para ir até lá.</Text>
            </View>
          )}

          {p.descricao ? <Text style={[ui.texto, { marginTop: 8 }]}>{p.descricao}</Text> : null}

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
            {endereco ? <Linha icone="map-marker-outline" texto={endereco} onPress={p.mapa ? () => abrir('mapa', p.mapa!) : undefined} /> : null}
            {p.telefoneFormatado ? <Linha icone="phone-outline" texto={p.telefoneFormatado} onPress={() => abrir('ligar', `tel:${p.telefone}`)} /> : null}
            {p.horario ? <Linha icone="clock-outline" texto={p.horario} /> : null}
            {p.atendeEmCasa && <Linha icone="home-outline" texto="Vai até você" />}
            {p.instagram ? <Linha icone="instagram" texto="Ver Instagram" onPress={() => abrir('instagram', p.instagram!)} /> : null}
            {p.site ? <Linha icone="web" texto={siteCurto(p.site)} onPress={() => abrir('site', p.site!)} /> : null}
          </View>

          {p.linkAssumir && (
            <Pressable accessibilityRole="button" onPress={() => WebBrowser.openBrowserAsync(p.linkAssumir!)} style={estilos.dono}>
              <MaterialCommunityIcons name="storefront-outline" size={30} color={cores.verde} />
              <View style={{ flex: 1 }}>
                <Text style={estilos.donoTitulo}>É o dono deste lugar?</Text>
                <Text style={estilos.donoTexto}>Confira os planos para corrigir os dados e colocar fotos.</Text>
              </View>
              <MaterialCommunityIcons name="arrow-right" size={24} color={cores.verde} />
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

function Acao({ icone, texto, cor, onPress }: { icone: keyof typeof MaterialCommunityIcons.glyphMap; texto: string; cor: string; onPress: () => void }) {
  return (
    <Pressable accessibilityRole="button" accessibilityLabel={texto} onPress={onPress} style={estilos.acao}>
      {({ pressed }) => (
        <>
          <View style={[estilos.acaoBola, { backgroundColor: cor }, pressed && { transform: [{ scale: 0.94 }] }]}>
            <MaterialCommunityIcons name={icone} size={30} color="#fff" />
          </View>
          <Text style={estilos.acaoTexto}>{texto}</Text>
        </>
      )}
    </Pressable>
  );
}

function Linha({ icone, texto, onPress }: { icone: keyof typeof MaterialCommunityIcons.glyphMap; texto: string; onPress?: () => void }) {
  const conteudo = (
    <>
      <View style={estilos.linhaIcone}>
        <MaterialCommunityIcons name={icone} size={22} color={cores.verde} />
      </View>
      <Text style={[ui.texto, { flex: 1 }, onPress && { color: cores.destaque, fontWeight: '700' }]}>{texto}</Text>
      {onPress && <MaterialCommunityIcons name="chevron-right" size={26} color={cores.textoSuave} />}
    </>
  );
  return onPress ? (
    <Pressable accessibilityRole="link" onPress={onPress} style={estilos.linha}>
      {conteudo}
    </Pressable>
  ) : (
    <View style={estilos.linha}>{conteudo}</View>
  );
}

function BotaoSalvar({ p, aoMudar }: { p: Profissional; aoMudar: (f: boolean) => void }) {
  const { usuario } = useConta();
  const router = useRouter();
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={p.favorito ? 'Tirar dos salvos' : 'Salvar'}
      style={{ minWidth: TOQUE, minHeight: TOQUE, alignItems: 'center', justifyContent: 'center' }}
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
      <MaterialCommunityIcons name={p.favorito ? 'heart' : 'heart-outline'} size={30} color={p.favorito ? cores.amarelo : '#fff'} />
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
  topo: { alignItems: 'center', gap: 8, paddingHorizontal: 20, paddingBottom: 56, borderBottomLeftRadius: 36, borderBottomRightRadius: 36 },
  topoAvatar: { padding: 6, borderRadius: 32, backgroundColor: '#fff' },
  nome: { fontSize: fonte.titulo, fontWeight: '900', color: '#fff', textAlign: 'center', marginTop: 4 },
  tipo: { backgroundColor: cores.amarelo, borderRadius: 12, paddingHorizontal: 12, paddingVertical: 4 },
  tipoTexto: { fontSize: fonte.pequena, fontWeight: '900', color: cores.verde },
  responsavel: { color: '#fff', fontSize: fonte.pequena, opacity: 0.9 },
  nota: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: '#fff', borderRadius: 10, paddingHorizontal: 8, paddingVertical: 2 },
  notaTexto: { fontSize: 14, fontWeight: '800', color: cores.texto },
  acoes: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    marginHorizontal: 16,
    marginTop: -40,
    marginBottom: 12,
    paddingVertical: 14,
    borderRadius: RAIO,
    backgroundColor: cores.cartao,
    ...sombra,
  },
  acao: { alignItems: 'center', gap: 6, minWidth: 72 },
  acaoBola: { width: 60, height: 60, borderRadius: 30, alignItems: 'center', justifyContent: 'center' },
  acaoTexto: { fontSize: 14, fontWeight: '800', color: cores.texto },
  aviso: { flexDirection: 'row', alignItems: 'center', gap: 10, backgroundColor: cores.verdeClaro, borderRadius: 16, padding: 12, marginTop: 8 },
  servico: { backgroundColor: cores.fundoSuave, borderRadius: 12, paddingHorizontal: 12, paddingVertical: 8 },
  caixa: { backgroundColor: cores.cartao, borderRadius: RAIO, paddingHorizontal: 14, paddingVertical: 6, ...sombra },
  linha: { flexDirection: 'row', alignItems: 'center', gap: 12, minHeight: TOQUE },
  linhaIcone: { width: 38, height: 38, borderRadius: 12, backgroundColor: cores.verdeClaro, alignItems: 'center', justifyContent: 'center' },
  dono: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: cores.amarelo, borderRadius: RAIO, padding: 16, marginTop: 20 },
  donoTitulo: { fontSize: fonte.normal, fontWeight: '900', color: cores.verde },
  donoTexto: { fontSize: 15, color: cores.verde },
  sub: { fontSize: fonte.pequena, color: cores.textoSuave },
  fonte: { fontSize: 14, color: cores.textoSuave, textAlign: 'center', marginTop: 28 },
  caixaAval: { backgroundColor: cores.cartao, borderRadius: RAIO, padding: 16, ...sombra },
  comentario: { backgroundColor: cores.cartao, borderRadius: 18, padding: 14, gap: 6, marginTop: 12 },
  resposta: { backgroundColor: cores.fundoSuave, borderRadius: 12, padding: 10, marginTop: 4 },
});
