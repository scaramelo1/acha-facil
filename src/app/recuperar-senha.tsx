import { useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, KeyboardAvoidingView, Linking, Platform, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { EMAIL_CONTATO } from '../api/cliente';
import { Cabecalho } from '../componentes/Cabecalho';
import { Botao, Campo, estilos as ui } from '../componentes/ui';
import { cores, fonte } from '../tema';

/**
 * "Recuperar senha". O servidor ainda não manda e-mail sozinho, então o app abre o e-mail da pessoa
 * com o pedido pronto para a FSA Designer, que responde com uma senha nova.
 */
export default function TelaRecuperarSenha() {
  const router = useRouter();
  const [email, setEmail] = useState('');

  const enviar = () => {
    const e = email.trim();
    if (!/^\S+@\S+\.\S+$/.test(e)) return Alert.alert('Confira o e-mail', 'Digite o e-mail que você usou para criar a conta.');
    const assunto = encodeURIComponent('Acha Fácil: recuperar senha');
    const corpo = encodeURIComponent(`Olá! Esqueci a senha da minha conta no Acha Fácil.\nE-mail da conta: ${e}`);
    Linking.openURL(`mailto:${EMAIL_CONTATO}?subject=${assunto}&body=${corpo}`).catch(() =>
      Alert.alert('Não foi possível abrir o e-mail', `Mande um e-mail para ${EMAIL_CONTATO} com o e-mail da sua conta.`),
    );
  };

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={{ paddingBottom: 40 }} keyboardShouldPersistTaps="handled">
        <Cabecalho voltar titulo="Recuperar senha" />
        <View style={{ padding: 20 }}>
          <Text style={ui.texto}>
            Informe seu e-mail cadastrado. Vamos abrir o seu aplicativo de e-mail com o pedido pronto; é só enviar e respondemos com uma senha nova.
          </Text>
          <Text style={ui.rotulo}>E-mail</Text>
          <Campo icone="email-outline" value={email} onChangeText={setEmail} autoCapitalize="none" autoComplete="email" keyboardType="email-address" placeholder="Seu e-mail" />
          <Botao titulo="Enviar pedido" tipo="amarelo" seta onPress={enviar} estilo={{ marginTop: 24 }} />
          <Pressable accessibilityRole="link" onPress={() => router.back()} style={estilos.voltar} hitSlop={8}>
            <Text style={estilos.link}>Voltar para entrar</Text>
          </Pressable>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const estilos = StyleSheet.create({
  voltar: { alignSelf: 'center', minHeight: 44, justifyContent: 'center', marginTop: 16 },
  link: { color: cores.verde, fontSize: fonte.pequena + 1, fontWeight: '800', textDecorationLine: 'underline' },
});
