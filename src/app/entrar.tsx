import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { URL_PRIVACIDADE, URL_TERMOS } from '../api/cliente';
import { Cabecalho } from '../componentes/Cabecalho';
import { useConta } from '../conta';
import { cartao, cores, fonte } from '../tema';

// Entrar no app: só com a conta Google (o app recebe nome, e-mail e foto)
export default function TelaEntrar() {
  const router = useRouter();
  const { usuario, entrarComGoogle } = useConta();
  const [erro, setErro] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);

  // entrou (por aqui ou pela volta do Google, que no Android abre a rota /google): sai desta tela uma vez
  const saiu = useRef(false);
  useEffect(() => {
    if (usuario && !saiu.current) {
      saiu.current = true;
      if (router.canGoBack()) router.back();
      else router.replace('/');
    }
  }, [usuario, router]);

  const entrar = async () => {
    setErro(null);
    setEnviando(true);
    try {
      await entrarComGoogle();
    } catch (e) {
      setErro(e instanceof Error ? e.message : 'Não foi possível entrar com o Google.');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <ScrollView contentContainerStyle={{ paddingBottom: 40 }}>
      <Cabecalho voltar titulo="Que bom ter você aqui!" />
      <View style={{ padding: 20 }}>
        <Text style={estilos.texto}>Entre com a sua conta Google para guardar seus lugares favoritos e avaliar os comércios.</Text>

        <View style={estilos.lista}>
          {(
            [
              ['heart-outline', 'Guardar seus lugares favoritos'],
              ['star-outline', 'Avaliar e comentar os comércios'],
              ['shield-check-outline', 'Sem senha nova: é a sua conta Google'],
            ] as const
          ).map(([icone, texto]) => (
            <View key={texto} style={estilos.item}>
              <MaterialCommunityIcons name={icone} size={24} color={cores.verde} />
              <Text style={estilos.itemTexto}>{texto}</Text>
            </View>
          ))}
        </View>

        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Entrar com o Google"
          disabled={enviando}
          onPress={entrar}
          style={({ pressed }) => [estilos.google, (pressed || enviando) && { opacity: 0.8 }]}
        >
          {enviando ? <ActivityIndicator color={cores.texto} /> : <MaterialCommunityIcons name="google" size={28} color="#DB4437" />}
          <Text style={estilos.googleTexto}>{enviando ? 'Aguardando o Google...' : 'Entrar com o Google'}</Text>
        </Pressable>

        {erro && <Text style={estilos.erro}>{erro}</Text>}

        <Text style={estilos.nota}>
          Recebemos só o seu nome, e-mail e foto do perfil. Ao entrar, você concorda com os{' '}
          <Text style={estilos.link} onPress={() => WebBrowser.openBrowserAsync(URL_TERMOS)}>
            Termos de uso
          </Text>{' '}
          e a{' '}
          <Text style={estilos.link} onPress={() => WebBrowser.openBrowserAsync(URL_PRIVACIDADE)}>
            Política de Privacidade
          </Text>
          .
        </Text>
        <Text style={estilos.nota}>Para procurar comércios, não precisa entrar.</Text>
      </View>
    </ScrollView>
  );
}

const estilos = StyleSheet.create({
  texto: { fontSize: fonte.normal, color: cores.texto, lineHeight: 26 },
  lista: { ...cartao, padding: 14, gap: 12, marginTop: 18 },
  item: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  itemTexto: { flex: 1, fontSize: fonte.pequena, color: cores.texto },
  google: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 12,
    minHeight: 60,
    marginTop: 24,
    borderRadius: 30,
    borderWidth: 1.5,
    borderColor: cores.borda,
    backgroundColor: '#fff',
  },
  googleTexto: { fontSize: fonte.media, fontWeight: '800', color: cores.texto },
  erro: { color: cores.erro, fontSize: fonte.normal, marginTop: 14, textAlign: 'center' },
  nota: { fontSize: 15, color: cores.textoSuave, textAlign: 'center', marginTop: 18, lineHeight: 22 },
  link: { color: cores.verde, fontWeight: '800', textDecorationLine: 'underline' },
});
