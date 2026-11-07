import { useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, Text, TextInput } from 'react-native';

import { URL_PRIVACIDADE, URL_TERMOS } from '../api/cliente';
import { Botao, estilos as ui } from '../componentes/ui';
import { useConta } from '../conta';
import { cores, fonte } from '../tema';

export default function TelaEntrar() {
  const router = useRouter();
  const { entrar, criarConta } = useConta();
  const [nova, setNova] = useState(false);
  const [nome, setNome] = useState('');
  const [email, setEmail] = useState('');
  const [senha, setSenha] = useState('');
  const [erro, setErro] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);

  const enviar = async () => {
    setErro(null);
    setEnviando(true);
    try {
      if (nova) await criarConta(nome.trim(), email.trim(), senha);
      else await entrar(email.trim(), senha);
      router.back();
    } catch (e) {
      setErro(e instanceof Error ? e.message : 'Não foi possível entrar.');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={{ padding: 20 }} keyboardShouldPersistTaps="handled">
        <Text style={{ fontSize: fonte.titulo, fontWeight: '800', color: cores.texto }}>{nova ? 'Criar conta' : 'Entrar'}</Text>
        <Text style={[ui.texto, { color: cores.textoSuave, marginTop: 6 }]}>
          A conta serve para avaliar, comentar e salvar profissionais. Para procurar, não precisa.
        </Text>

        {nova && (
          <>
            <Text style={ui.rotulo}>Seu nome</Text>
            <TextInput value={nome} onChangeText={setNome} autoComplete="name" style={ui.campo} />
          </>
        )}
        <Text style={ui.rotulo}>E-mail</Text>
        <TextInput
          value={email}
          onChangeText={setEmail}
          autoCapitalize="none"
          autoComplete="email"
          keyboardType="email-address"
          style={ui.campo}
        />
        <Text style={ui.rotulo}>Senha{nova ? ' (mínimo 6)' : ''}</Text>
        <TextInput value={senha} onChangeText={setSenha} secureTextEntry autoComplete={nova ? 'new-password' : 'current-password'} style={ui.campo} />

        {nova && (
          <Text style={{ fontSize: fonte.pequena, color: cores.textoSuave, marginTop: 14, lineHeight: 22 }}>
            Ao criar a conta, você concorda com os{' '}
            <Text style={{ color: cores.destaque, textDecorationLine: 'underline' }} onPress={() => WebBrowser.openBrowserAsync(URL_TERMOS)}>
              Termos de uso
            </Text>{' '}
            e a{' '}
            <Text style={{ color: cores.destaque, textDecorationLine: 'underline' }} onPress={() => WebBrowser.openBrowserAsync(URL_PRIVACIDADE)}>
              Política de privacidade
            </Text>
            .
          </Text>
        )}

        {erro && <Text style={{ color: cores.erro, fontSize: fonte.normal, marginTop: 14 }}>{erro}</Text>}

        <Botao
          titulo={enviando ? 'Aguarde...' : nova ? 'Criar conta' : 'Entrar'}
          onPress={enviar}
          desativado={enviando}
          estilo={{ marginTop: 24 }}
        />
        <Botao
          titulo={nova ? 'Já tenho conta' : 'Criar uma conta'}
          tipo="contorno"
          onPress={() => {
            setNova(!nova);
            setErro(null);
          }}
          estilo={{ marginTop: 12 }}
        />
      </ScrollView>
    </KeyboardAvoidingView>
  );
}
