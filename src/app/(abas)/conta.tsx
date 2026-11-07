import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';

import { URL_PRIVACIDADE, URL_TERMOS } from '../../api/cliente';
import { useCidade } from '../../cidade';
import { Cabecalho } from '../../componentes/Cabecalho';
import { CartaoDono } from '../../componentes/CartaoDono';
import { Botao, LinhaMenu } from '../../componentes/ui';
import { useConta } from '../../conta';
import { cartao, cores, ESPACO_BARRA, fonte } from '../../tema';

// Perfil: sem conta, convite para entrar; com conta, o menu (favoritos, cidade, meu negócio, sair...)
export default function TelaPerfil() {
  const router = useRouter();
  const { usuario, sair, excluirConta } = useConta();
  const { cidade, trocarCidade } = useCidade();

  const confirmarSaida = () =>
    Alert.alert('Sair da conta?', 'Você pode entrar de novo quando quiser.', [
      { text: 'Cancelar', style: 'cancel' },
      { text: 'Sair', onPress: () => sair() },
    ]);
  const confirmarExclusao = () =>
    Alert.alert('Excluir conta?', 'Suas avaliações e favoritos serão apagados. Isso não pode ser desfeito.', [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Excluir',
        style: 'destructive',
        onPress: () => excluirConta().catch((e) => Alert.alert('Não foi possível excluir', e instanceof Error ? e.message : '')),
      },
    ]);

  const trocar = (
    <View style={estilos.trocar}>
      <Text style={estilos.trocarTexto}>Trocar</Text>
      <MaterialCommunityIcons name="chevron-right" size={18} color={cores.verde} />
    </View>
  );
  const linhaCidade = <LinhaMenu icone="map-marker-outline" texto="Cidade" detalhe={cidade.nome} onPress={trocarCidade} direita={trocar} />;
  const termos = <LinhaMenu icone="file-document-outline" texto="Termos de uso" onPress={() => WebBrowser.openBrowserAsync(URL_TERMOS)} />;
  const privacidade = <LinhaMenu icone="shield-check-outline" texto="Privacidade" onPress={() => WebBrowser.openBrowserAsync(URL_PRIVACIDADE)} />;
  const sobre = <LinhaMenu icone="information-outline" texto="Sobre o app" onPress={() => router.push('/sobre')} />;

  return (
    <ScrollView contentContainerStyle={{ paddingBottom: ESPACO_BARRA }}>
      <Cabecalho titulo={usuario ? 'Meu perfil' : 'Perfil'} />
      <View style={{ padding: 16 }}>
        {usuario ? (
          <>
            <View style={estilos.eu}>
              <View style={estilos.avatar}>
                <MaterialCommunityIcons name="account" size={60} color="#fff" />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={estilos.euNome} numberOfLines={2}>
                  {usuario.nome}
                </Text>
                <Text style={estilos.euEmail} numberOfLines={1}>
                  {usuario.email}
                </Text>
              </View>
            </View>
            <LinhaMenu icone="heart-outline" texto="Meus favoritos" onPress={() => router.push('/salvos')} />
            {linhaCidade}
            <LinhaMenu icone="storefront-outline" texto="Meu negócio" onPress={() => router.push('/meu-negocio')} />
            <LinhaMenu icone="star-outline" texto="Planos para comércio" onPress={() => router.push('/planos')} />
            {sobre}
            {termos}
            {privacidade}
            <LinhaMenu icone="logout" texto="Sair" onPress={confirmarSaida} direita={<View />} />
            <LinhaMenu icone="delete-outline" texto="Excluir minha conta" cor={cores.erro} onPress={confirmarExclusao} direita={<View />} />
          </>
        ) : (
          <>
            <View style={estilos.convite}>
              <View style={{ flexDirection: 'row', alignItems: 'center', gap: 14 }}>
                <View style={estilos.avatarClaro}>
                  <MaterialCommunityIcons name="account-outline" size={40} color={cores.verde} />
                </View>
                <Text style={[estilos.conviteTexto, { flex: 1 }]}>Entre na sua conta para salvar favoritos e avaliar os comércios.</Text>
              </View>
              <Botao titulo="Entrar ou criar conta" tipo="amarelo" seta onPress={() => router.push('/entrar')} estilo={{ marginTop: 14 }} />
            </View>
            {linhaCidade}
            <View style={{ marginVertical: 6 }}>
              <CartaoDono />
            </View>
            {sobre}
            {termos}
            {privacidade}
          </>
        )}
      </View>
    </ScrollView>
  );
}

const estilos = StyleSheet.create({
  eu: { flexDirection: 'row', alignItems: 'center', gap: 16, marginBottom: 18 },
  avatar: { width: 84, height: 84, borderRadius: 42, backgroundColor: cores.verde, alignItems: 'center', justifyContent: 'center' },
  euNome: { fontSize: fonte.grande + 2, fontWeight: '900', color: cores.texto },
  euEmail: { fontSize: fonte.pequena - 1, color: cores.textoSuave, marginTop: 2 },
  convite: { ...cartao, padding: 16, marginBottom: 12 },
  avatarClaro: { width: 64, height: 64, borderRadius: 32, backgroundColor: cores.verdeClaro, alignItems: 'center', justifyContent: 'center' },
  conviteTexto: { fontSize: fonte.pequena, color: cores.texto, lineHeight: 22 },
  trocar: { flexDirection: 'row', alignItems: 'center', backgroundColor: cores.fundoSuave, borderRadius: 14, paddingLeft: 12, paddingRight: 6, minHeight: 34 },
  trocarTexto: { fontSize: 15, fontWeight: '900', color: cores.verde },
});
