import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
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
import { useConta } from '../../conta';
import { cores, fonte, TOQUE } from '../../tema';

export default function TelaProfissional() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { usuario } = useConta();
  const { dados: p, setDados, erro, carregando, recarregar } = useCarregar(
    () => chamar<{ profissional: Profissional }>(`profissional.php?id=${id}`).then((r) => r.profissional),
    `${id}-${usuario?.id ?? 0}`,
  );

  if (carregando && !p) return <Carregando />;
  if (erro && !p) return <Falha mensagem={erro} tentar={recarregar} />;
  if (!p) return null;

  const abrir = (tipo: 'whatsapp' | 'ligar' | 'instagram' | 'mapa', link: string) => {
    registrarContato(p.id, tipo);
    Linking.openURL(link).catch(() => Alert.alert('Não foi possível abrir', 'Confira se o aplicativo está instalado.'));
  };

  return (
    <>
      <Stack.Screen options={{ title: p.categoria, headerRight: () => <BotaoSalvar p={p} aoMudar={(f) => setDados({ ...p, favorito: f })} /> }} />
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 48 }}>
        <View style={{ alignItems: 'center', gap: 8 }}>
          <Avatar foto={p.foto} icone={p.icone} tamanho={112} />
          <Text style={estilos.nome}>{p.nome}</Text>
          {p.responsavel && p.responsavel !== p.nome ? <Text style={estilos.sub}>{p.responsavel}</Text> : null}
          {p.verificado && <SeloVerificado />}
          {p.avaliacoes > 0 ? (
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
              <Estrelas nota={p.nota} tamanho={24} />
              <Text style={estilos.sub}>
                {p.nota.toFixed(1).replace('.', ',')} ({p.avaliacoes} {p.avaliacoes === 1 ? 'avaliação' : 'avaliações'})
              </Text>
            </View>
          ) : (
            <Text style={estilos.sub}>Ainda sem avaliações</Text>
          )}
        </View>

        <View style={{ gap: 12, marginTop: 24 }}>
          {p.whatsapp && <Botao titulo="Chamar no WhatsApp" icone="whatsapp" tipo="whatsapp" onPress={() => abrir('whatsapp', p.whatsapp!)} />}
          {p.telefone && (
            <Botao titulo={`Ligar ${p.telefoneFormatado}`} icone="phone" onPress={() => abrir('ligar', `tel:${p.telefone}`)} />
          )}
        </View>

        {p.descricao ? <Text style={[ui.texto, { marginTop: 24 }]}>{p.descricao}</Text> : null}

        {p.servicos.length > 0 && (
          <>
            <Titulo>Serviços</Titulo>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
              {p.servicos.map((s) => (
                <View key={s} style={estilos.servico}>
                  <Text style={{ fontSize: fonte.normal, color: cores.texto }}>{s}</Text>
                </View>
              ))}
            </View>
          </>
        )}

        <Titulo>Informações</Titulo>
        <View style={{ gap: 4 }}>
          {p.atendeEmCasa && <Linha icone="home-outline" texto="Vai até você" />}
          {p.horario && <Linha icone="clock-outline" texto={p.horario} />}
          {p.bairro && <Linha icone="map-marker-outline" texto={p.endereco ? `${p.endereco} · ${p.bairro}` : p.bairro} onPress={p.mapa ? () => abrir('mapa', p.mapa!) : undefined} />}
          {p.instagram && <Linha icone="instagram" texto="Ver Instagram" onPress={() => abrir('instagram', p.instagram!)} />}
          <Linha
            icone="share-variant-outline"
            texto="Indicar para alguém"
            onPress={() => Share.share({ message: `${p.nome} (${p.categoria}) no Acha Fácil${p.whatsapp ? `: ${p.whatsapp}` : ''}` })}
          />
        </View>

        <Avaliacoes p={p} aoAtualizar={setDados} />
      </ScrollView>
    </>
  );
}

function Linha({ icone, texto, onPress }: { icone: keyof typeof MaterialCommunityIcons.glyphMap; texto: string; onPress?: () => void }) {
  const conteudo = (
    <>
      <MaterialCommunityIcons name={icone} size={26} color={cores.texto} />
      <Text style={[ui.texto, { flex: 1 }, onPress && { textDecorationLine: 'underline' }]}>{texto}</Text>
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
      accessibilityLabel={p.favorito ? 'Tirar dos salvos' : 'Salvar profissional'}
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
      <MaterialCommunityIcons name={p.favorito ? 'heart' : 'heart-outline'} size={30} color={p.favorito ? cores.destaque : cores.texto} />
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
        <View style={estilos.caixa}>
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
          titulo={p.minhaAvaliacao ? 'Mudar minha avaliação' : 'Avaliar este profissional'}
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
              <Text style={[estilos.sub, { fontWeight: '700' }]}>Resposta do profissional</Text>
              <Text style={ui.texto}>{c.resposta}</Text>
            </View>
          ) : null}
        </View>
      ))}
    </View>
  );
}

const estilos = StyleSheet.create({
  nome: { fontSize: fonte.titulo, fontWeight: '800', color: cores.texto, textAlign: 'center' },
  sub: { fontSize: fonte.pequena, color: cores.textoSuave },
  servico: { backgroundColor: cores.fundoSuave, borderRadius: 10, paddingHorizontal: 12, paddingVertical: 8 },
  linha: { flexDirection: 'row', alignItems: 'center', gap: 12, minHeight: TOQUE },
  caixa: { borderWidth: 1, borderColor: cores.borda, borderRadius: 16, padding: 16 },
  comentario: { borderTopWidth: 1, borderColor: cores.borda, paddingVertical: 14, gap: 6, marginTop: 8 },
  resposta: { backgroundColor: cores.fundoSuave, borderRadius: 10, padding: 10, marginTop: 4 },
});

