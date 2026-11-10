import { Image } from 'expo-image';
import Constants from 'expo-constants';
import * as WebBrowser from 'expo-web-browser';
import { Alert, Linking, ScrollView, StyleSheet, Text, View } from 'react-native';

import { EMAIL_CONTATO, URL_INSTAGRAM, URL_PRIVACIDADE, URL_TERMOS } from '../api/cliente';
import { useCidade } from '../cidade';
import { Cabecalho } from '../componentes/Cabecalho';
import { LinhaMenu } from '../componentes/ui';
import { cores, fonte, RAIO } from '../tema';

// "Sobre o Acha Fácil!": o que é, as cidades atendidas, termos, privacidade e como falar com a FSA Designer
export default function TelaSobre() {
  const cidades = useCidade().cidades.map((c) => c.nome);
  const lista = cidades.length > 1 ? `${cidades.slice(0, -1).join(', ')} e ${cidades[cidades.length - 1]}` : cidades[0];
  const faleConosco = () =>
    Linking.openURL(`mailto:${EMAIL_CONTATO}?subject=${encodeURIComponent('Acha Fácil: contato')}`).catch(() =>
      Alert.alert('Não foi possível abrir o e-mail', `Escreva para ${EMAIL_CONTATO}.`),
    );

  return (
    <ScrollView contentContainerStyle={{ paddingBottom: 32 }}>
      <Cabecalho voltar titulo="Sobre o Acha Fácil!" />
      <View style={{ padding: 16 }}>
        <View style={estilos.figura}>
          <Image source={require('../../assets/ilustracoes/cidade.png')} style={{ width: '100%', height: 150 }} contentFit="contain" accessible={false} />
        </View>
        <Text style={estilos.titulo}>Conectando você ao comércio da sua cidade</Text>
        <Text style={estilos.texto}>
          O Acha Fácil! é o guia do comércio local de {lista}: você acha produtos, serviços e empresas da sua cidade e fala direto com
          eles, sem precisar de conta.
        </Text>
        <Text style={[estilos.texto, { marginBottom: 18 }]}>Feito pela FSA Designer.</Text>

        <LinhaMenu icone="file-document-outline" texto="Termos de uso" onPress={() => WebBrowser.openBrowserAsync(URL_TERMOS)} />
        <LinhaMenu icone="shield-check-outline" texto="Política de Privacidade" onPress={() => WebBrowser.openBrowserAsync(URL_PRIVACIDADE)} />
        <LinhaMenu icone="message-text-outline" texto="Fale com a gente" detalhe={EMAIL_CONTATO} onPress={faleConosco} />
        <LinhaMenu icone="instagram" texto="Instagram" detalhe="@fsa.designer" onPress={() => Linking.openURL(URL_INSTAGRAM)} />
        <Text style={estilos.versao}>Versão {Constants.expoConfig?.version ?? ''}</Text>
      </View>
    </ScrollView>
  );
}

const estilos = StyleSheet.create({
  figura: { backgroundColor: cores.verdeClaro, borderRadius: RAIO, paddingTop: 16, overflow: 'hidden' },
  titulo: { fontSize: 26, fontWeight: '900', color: cores.verde, marginTop: 18, lineHeight: 31 },
  texto: { fontSize: fonte.normal - 1, color: cores.texto, lineHeight: 25, marginTop: 8 },
  versao: { fontSize: 14, color: cores.textoSuave, textAlign: 'center', marginTop: 16 },
});
