import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { Text, View } from 'react-native';

import { Botao, Carregando } from '../componentes/ui';
import { useConta } from '../conta';
import { cores, fonte } from '../tema';

/**
 * Volta do Google (achafacil:///google?codigo=...). Troca o código pela sessão e sai desta tela.
 * A tela Entrar também troca o mesmo código; src/conta.tsx garante que a troca acontece uma vez só.
 */
export default function RetornoGoogle() {
  const router = useRouter();
  const { codigo, erro: erroGoogle } = useLocalSearchParams<{ codigo?: string; erro?: string }>();
  const { entrarComCodigo } = useConta();
  const [erro, setErro] = useState<string | null>(null);
  const feito = useRef(false);

  useEffect(() => {
    if (feito.current) return;
    feito.current = true;
    const sair = () => (router.canGoBack() ? router.back() : router.replace('/'));
    if (typeof erroGoogle === 'string' && erroGoogle) {
      Promise.resolve().then(() => setErro(erroGoogle));
      return;
    }
    if (typeof codigo !== 'string' || !codigo) {
      sair();
      return;
    }
    entrarComCodigo(codigo).then(sair, (e: unknown) => setErro(e instanceof Error ? e.message : 'Não foi possível entrar com o Google.'));
  }, [codigo, erroGoogle, entrarComCodigo, router]);

  if (!erro) return <Carregando />;
  return (
    <View style={{ flex: 1, justifyContent: 'center', padding: 24, gap: 16 }}>
      <Text style={{ fontSize: fonte.normal, color: cores.texto, textAlign: 'center' }}>{erro}</Text>
      <Botao titulo="Voltar" tipo="amarelo" onPress={() => (router.canGoBack() ? router.back() : router.replace('/'))} />
    </View>
  );
}
