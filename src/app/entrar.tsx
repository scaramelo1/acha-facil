import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { URL_PRIVACIDADE, URL_TERMOS } from '../api/cliente';
import { Cabecalho } from '../componentes/Cabecalho';
import { Botao, Campo, estilos as ui } from '../componentes/ui';
import { useConta } from '../conta';
import { cores, fonte } from '../tema';

// Entrar e Criar conta na mesma tela (troca pelo botão de baixo)
export default function TelaEntrar() {
  const router = useRouter();
  const { entrar, criarConta } = useConta();
  const [nova, setNova] = useState(false);
  const [nome, setNome] = useState('');
  const [email, setEmail] = useState('');
  const [senha, setSenha] = useState('');
  const [confirma, setConfirma] = useState('');
  const [aceito, setAceito] = useState(false);
  const [erro, setErro] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);

  const enviar = async () => {
    setErro(null);
    if (nova) {
      if (senha !== confirma) return setErro('As duas senhas não são iguais.');
      if (!aceito) return setErro('Para criar a conta, marque que leu e aceita os termos.');
    }
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

  const trocar = () => {
    setNova(!nova);
    setErro(null);
  };

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={{ paddingBottom: 40 }} keyboardShouldPersistTaps="handled">
        <Cabecalho voltar titulo={nova ? 'Crie sua conta no Acha Fácil!' : 'Que bom ter você aqui!'} />
        <View style={{ padding: 20 }}>
          {nova && (
            <>
              <Text style={[ui.rotulo, { marginTop: 4 }]}>Nome completo</Text>
              <Campo icone="account-outline" value={nome} onChangeText={setNome} autoComplete="name" placeholder="Seu nome completo" />
            </>
          )}
          <Text style={[ui.rotulo, !nova && { marginTop: 4 }]}>E-mail</Text>
          <Campo icone="email-outline" value={email} onChangeText={setEmail} autoCapitalize="none" autoComplete="email" keyboardType="email-address" placeholder="Seu e-mail" />
          <Text style={ui.rotulo}>Senha</Text>
          <Campo
            icone="lock-outline"
            senha
            value={senha}
            onChangeText={setSenha}
            autoComplete={nova ? 'new-password' : 'current-password'}
            placeholder={nova ? 'Crie uma senha (mínimo 6)' : 'Sua senha'}
          />
          {nova && (
            <>
              <Text style={ui.rotulo}>Confirmar senha</Text>
              <Campo icone="lock-outline" senha value={confirma} onChangeText={setConfirma} autoComplete="new-password" placeholder="Confirme sua senha" />
              <Pressable accessibilityRole="checkbox" accessibilityState={{ checked: aceito }} onPress={() => setAceito(!aceito)} style={estilos.aceite}>
                <MaterialCommunityIcons name={aceito ? 'checkbox-marked' : 'checkbox-blank-outline'} size={28} color={aceito ? cores.verde : cores.textoSuave} />
                <Text style={estilos.aceiteTexto}>
                  Li e aceito os{' '}
                  <Text style={estilos.link} onPress={() => WebBrowser.openBrowserAsync(URL_TERMOS)}>
                    Termos de uso
                  </Text>{' '}
                  e a{' '}
                  <Text style={estilos.link} onPress={() => WebBrowser.openBrowserAsync(URL_PRIVACIDADE)}>
                    Política de Privacidade
                  </Text>
                  .
                </Text>
              </Pressable>
            </>
          )}

          {!nova && (
            <Pressable accessibilityRole="link" onPress={() => router.push('/recuperar-senha')} style={estilos.esqueci} hitSlop={8}>
              <Text style={estilos.link}>Esqueci minha senha</Text>
            </Pressable>
          )}

          {erro && <Text style={estilos.erro}>{erro}</Text>}

          <Botao
            titulo={enviando ? 'Aguarde...' : nova ? 'Criar conta' : 'Entrar'}
            tipo="amarelo"
            seta={!enviando}
            onPress={enviar}
            desativado={enviando}
            estilo={{ marginTop: 22 }}
          />
          {nova ? (
            <Pressable accessibilityRole="link" onPress={trocar} style={estilos.esqueci} hitSlop={8}>
              <Text style={estilos.link}>Voltar para entrar</Text>
            </Pressable>
          ) : (
            <Botao titulo="Criar conta" tipo="contorno" onPress={trocar} estilo={{ marginTop: 14 }} />
          )}
          <Text style={estilos.nota}>A conta serve para avaliar e guardar favoritos. Para procurar, não precisa.</Text>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const estilos = StyleSheet.create({
  aceite: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 18, minHeight: 48 },
  aceiteTexto: { flex: 1, fontSize: fonte.pequena, color: cores.texto, lineHeight: 22 },
  link: { color: cores.verde, fontSize: fonte.pequena + 1, fontWeight: '800', textDecorationLine: 'underline' },
  esqueci: { alignSelf: 'center', minHeight: 44, justifyContent: 'center', marginTop: 12 },
  erro: { color: cores.erro, fontSize: fonte.normal, marginTop: 14 },
  nota: { fontSize: 15, color: cores.textoSuave, textAlign: 'center', marginTop: 20 },
});
