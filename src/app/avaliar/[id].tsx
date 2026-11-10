import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';

import { chamar, type Profissional } from '../../api/cliente';
import { Cabecalho } from '../../componentes/Cabecalho';
import { Botao, Carregando, Falha, Foto, estilos as ui, useCarregar } from '../../componentes/ui';
import { useConta } from '../../conta';
import { cartao, cores, fonte } from '../../tema';

const NOMES = ['', 'Ruim', 'Regular', 'Bom', 'Muito bom', 'Excelente'];

// "Avalie este comércio": estrelas, comentário opcional e "Publicar avaliação" (precisa estar com a conta aberta)
export default function TelaAvaliar() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { usuario } = useConta();
  const router = useRouter();
  const { dados: p, erro, carregando, recarregar } = useCarregar(
    () => chamar<{ profissional: Profissional }>(`profissional.php?id=${id}&origem=nenhuma`).then((r) => r.profissional),
    `avaliar-${id}-${usuario?.id ?? 0}`,
  );

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={{ paddingBottom: 40 }} keyboardShouldPersistTaps="handled">
        <Cabecalho voltar titulo="Avalie este comércio" />
        <View style={{ padding: 16 }}>
          {!usuario ? (
            <View style={{ gap: 14, marginTop: 12 }}>
              <Text style={ui.texto}>Entre na sua conta para avaliar.</Text>
              <Botao titulo="Entrar com o Google" icone="google" tipo="amarelo" onPress={() => router.replace('/entrar')} />
            </View>
          ) : !p ? (
            carregando ? (
              <View style={{ height: 240 }}>
                <Carregando />
              </View>
            ) : (
              <Falha mensagem={erro ?? 'Não foi possível carregar.'} tentar={recarregar} />
            )
          ) : (
            <Formulario key={p.id} p={p} />
          )}
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

/** Estrelas e comentário; quem já avaliou começa com a avaliação de antes. */
function Formulario({ p }: { p: Profissional }) {
  const router = useRouter();
  const [estrelas, setEstrelas] = useState(p.minhaAvaliacao?.estrelas ?? 0);
  const [comentario, setComentario] = useState(p.minhaAvaliacao?.comentario ?? '');
  const [enviando, setEnviando] = useState(false);

  const enviar = async () => {
    if (estrelas < 1) return Alert.alert('Escolha as estrelas', 'Toque de 1 a 5 estrelas.');
    setEnviando(true);
    try {
      await chamar('avaliar.php', 'POST', { id: p.id, estrelas, comentario });
      router.back();
    } catch (e) {
      Alert.alert('Não foi possível publicar', e instanceof Error ? e.message : '');
    } finally {
      setEnviando(false);
    }
  };

  return (
            <>
              <Pressable accessibilityRole="button" onPress={() => router.back()} style={estilos.comercio}>
                <Foto foto={p.foto} icone={p.icone} cor={p.cor} largura={104} altura={78} raio={0} />
                <View style={{ flex: 1 }}>
                  <Text style={estilos.nome} numberOfLines={2}>
                    {p.nome}
                  </Text>
                  <Text style={estilos.sub} numberOfLines={1}>
                    {p.tipo}
                  </Text>
                </View>
                <MaterialCommunityIcons name="chevron-right" size={26} color={cores.texto} style={{ marginRight: 8 }} />
              </Pressable>

              <Text style={estilos.pergunta}>Como foi sua experiência?</Text>
              <View style={estilos.estrelas}>
                {[1, 2, 3, 4, 5].map((i) => (
                  <Pressable key={i} accessibilityRole="button" accessibilityLabel={`${i} ${i === 1 ? 'estrela' : 'estrelas'}`} accessibilityState={{ selected: estrelas === i }} onPress={() => setEstrelas(i)} hitSlop={4}>
                    <MaterialCommunityIcons name={estrelas >= i ? 'star' : 'star-outline'} size={52} color={estrelas >= i ? cores.estrela : cores.verde} />
                  </Pressable>
                ))}
              </View>
              <Text style={estilos.nota}>{NOMES[estrelas] || 'Toque nas estrelas'}</Text>

              <Text style={estilos.rotulo}>Deixe um comentário (opcional)</Text>
              <TextInput
                value={comentario}
                onChangeText={setComentario}
                placeholder="Conte como foi o atendimento, os produtos ou serviços..."
                placeholderTextColor={cores.textoSuave}
                multiline
                maxLength={1000}
                style={[ui.campo, { minHeight: 120, paddingTop: 12, textAlignVertical: 'top' }]}
              />

              <Botao titulo={enviando ? 'Publicando...' : 'Publicar avaliação'} tipo="amarelo" onPress={enviar} desativado={enviando} estilo={{ marginTop: 18 }} />

              <View style={estilos.dica}>
                <MaterialCommunityIcons name="account-group" size={28} color={cores.verde} />
                <Text style={[estilos.sub, { flex: 1 }]}>Ajude a comunidade com uma avaliação respeitosa e construtiva. Seu comentário aparece com o primeiro nome e a inicial.</Text>
              </View>
            </>
  );
}

const estilos = StyleSheet.create({
  comercio: { ...cartao, flexDirection: 'row', alignItems: 'center', gap: 12, overflow: 'hidden' },
  nome: { fontSize: fonte.normal + 1, fontWeight: '900', color: cores.texto },
  sub: { fontSize: fonte.pequena - 1, color: cores.textoSuave, lineHeight: 21 },
  pergunta: { fontSize: fonte.grande + 2, fontWeight: '900', color: cores.verde, marginTop: 26 },
  estrelas: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 12, paddingHorizontal: 6 },
  nota: { textAlign: 'center', fontSize: fonte.pequena, fontWeight: '800', color: cores.textoSuave, marginTop: 4 },
  rotulo: { fontSize: fonte.normal, color: cores.texto, fontWeight: '700', marginTop: 22, marginBottom: 8 },
  dica: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: '#EEF1EC', borderRadius: 14, padding: 14, marginTop: 18 },
});
