import { useRouter } from 'expo-router';
import { useEffect, useRef, useState, type ComponentRef } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, useWindowDimensions, View } from 'react-native';

import type { ProfissionalResumo } from '../api/cliente';
import { cores, fonte } from '../tema';
import { Avatar, Estrelas, SeloVerificado } from './ui';

const INTERVALO = 5000;

/** Carrossel de profissionais em destaque (vitrine paga). Passa sozinho a cada 5 s e para quando a pessoa arrasta. */
export function Vitrine({ itens }: { itens: ProfissionalResumo[] }) {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const largura = width - 32;
  const rolagem = useRef<ComponentRef<typeof ScrollView>>(null);
  const [atual, setAtual] = useState(0);
  const [pausado, setPausado] = useState(false);

  useEffect(() => {
    if (pausado || itens.length < 2) return;
    const t = setInterval(() => {
      setAtual((a) => {
        const prox = (a + 1) % itens.length;
        rolagem.current?.scrollTo({ x: prox * largura, animated: true });
        return prox;
      });
    }, INTERVALO);
    return () => clearInterval(t);
  }, [pausado, itens.length, largura]);

  if (itens.length === 0) return null;

  return (
    <View>
      <ScrollView
        ref={rolagem}
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        onScrollBeginDrag={() => setPausado(true)}
        onMomentumScrollEnd={(e) => setAtual(Math.round(e.nativeEvent.contentOffset.x / largura))}
      >
        {itens.map((p) => (
          <Pressable
            key={p.id}
            accessibilityRole="button"
            accessibilityLabel={`Destaque: ${p.nome}, ${p.categoria}`}
            onPress={() => router.push(`/profissional/${p.id}`)}
            style={[estilos.cartao, { width: largura }]}
          >
            <Text style={estilos.faixa}>EM DESTAQUE</Text>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 14 }}>
              <Avatar foto={p.foto} icone={p.icone} tamanho={76} />
              <View style={{ flex: 1, gap: 4 }}>
                <Text style={estilos.nome} numberOfLines={2}>
                  {p.nome}
                </Text>
                <Text style={estilos.categoria}>{p.categoria}</Text>
                {p.avaliacoes > 0 && <Estrelas nota={p.nota} tamanho={18} />}
                {p.verificado && <SeloVerificado compacto />}
              </View>
            </View>
            {p.servicos.length > 0 && (
              <Text style={estilos.servicos} numberOfLines={1}>
                {p.servicos.join(' · ')}
              </Text>
            )}
          </Pressable>
        ))}
      </ScrollView>
      {itens.length > 1 && (
        <View style={estilos.pontos}>
          {itens.map((p, i) => (
            <View key={p.id} style={[estilos.ponto, i === atual && estilos.pontoAtual]} />
          ))}
        </View>
      )}
    </View>
  );
}

const estilos = StyleSheet.create({
  cartao: {
    backgroundColor: cores.verde,
    borderRadius: 20,
    padding: 18,
    gap: 12,
    borderBottomWidth: 4,
    borderBottomColor: cores.destaque,
  },
  faixa: { color: cores.amarelo, fontSize: 14, fontWeight: '800', letterSpacing: 1.5 },
  nome: { color: '#fff', fontSize: fonte.grande, fontWeight: '800' },
  categoria: { color: '#ddd', fontSize: fonte.normal },
  servicos: { color: '#bbb', fontSize: fonte.pequena },
  pontos: { flexDirection: 'row', justifyContent: 'center', gap: 8, marginTop: 10 },
  ponto: { width: 10, height: 10, borderRadius: 5, backgroundColor: cores.borda },
  pontoAtual: { backgroundColor: cores.destaque, width: 24 },
});
