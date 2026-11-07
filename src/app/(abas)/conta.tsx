import * as WebBrowser from 'expo-web-browser';
import { useRouter } from 'expo-router';
import { Alert, ScrollView, Text, View } from 'react-native';

import { URL_PAINEL, URL_PRIVACIDADE, URL_TERMOS } from '../../api/cliente';
import { Botao, Titulo, estilos as ui } from '../../componentes/ui';
import { useConta } from '../../conta';
import { cores, fonte } from '../../tema';

export default function TelaConta() {
  const router = useRouter();
  const { usuario, sair, excluirConta } = useConta();

  const confirmarExclusao = () =>
    Alert.alert('Excluir conta?', 'Suas avaliações e salvos serão apagados. Isso não pode ser desfeito.', [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Excluir',
        style: 'destructive',
        onPress: () => excluirConta().catch((e) => Alert.alert('Não foi possível excluir', e instanceof Error ? e.message : '')),
      },
    ]);

  return (
    <ScrollView contentContainerStyle={{ padding: 20, gap: 12 }}>
      {usuario ? (
        <View style={{ gap: 12 }}>
          <Text style={{ fontSize: fonte.grande, fontWeight: '800', color: cores.texto }}>Olá, {usuario.nome.split(' ')[0]}!</Text>
          <Text style={[ui.texto, { color: cores.textoSuave }]}>{usuario.email}</Text>
          <Botao titulo="Meus salvos" icone="heart-outline" tipo="contorno" onPress={() => router.push('/salvos')} />
          <Botao titulo="Sair" icone="logout" tipo="contorno" onPress={sair} />
        </View>
      ) : (
        <View style={{ gap: 12 }}>
          <Text style={ui.texto}>Entre para avaliar, comentar e salvar profissionais.</Text>
          <Botao titulo="Entrar ou criar conta" icone="login" onPress={() => router.push('/entrar')} />
        </View>
      )}

      <Titulo>É profissional em São João?</Titulo>
      <Text style={ui.texto}>Cadastre seu serviço e apareça para toda a cidade.</Text>
      <Botao titulo="Quero aparecer no Acha Fácil" icone="storefront-outline" onPress={() => WebBrowser.openBrowserAsync(URL_PAINEL)} />

      <Titulo>Sobre o app</Titulo>
      <Botao titulo="Termos de uso" icone="file-document-outline" tipo="contorno" onPress={() => WebBrowser.openBrowserAsync(URL_TERMOS)} />
      <Botao titulo="Privacidade" icone="shield-account-outline" tipo="contorno" onPress={() => WebBrowser.openBrowserAsync(URL_PRIVACIDADE)} />

      {usuario && (
        <View style={{ marginTop: 32 }}>
          <Botao titulo="Excluir minha conta" icone="delete-outline" tipo="perigo" onPress={confirmarExclusao} />
        </View>
      )}
    </ScrollView>
  );
}
