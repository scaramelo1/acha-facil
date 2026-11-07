import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { useEffect, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { chamar, type Cidade, type Estado } from '../api/cliente';
import { cores, fonte, RAIO, sombra } from '../tema';

/** Pino de localização no estilo do Google Maps: gota vermelha com o miolo escuro. */
export function PinoLocal({ tamanho = 64 }: { tamanho?: number }) {
  const miolo = tamanho * 0.2;
  return (
    <View style={{ width: tamanho, height: tamanho }}>
      <MaterialCommunityIcons name="map-marker" size={tamanho} color="#EA4335" />
      <View
        style={{
          position: 'absolute',
          width: miolo,
          height: miolo,
          borderRadius: miolo / 2,
          backgroundColor: '#A50E0E',
          left: (tamanho - miolo) / 2,
          top: tamanho * 0.385 - miolo / 2,
        }}
      />
    </View>
  );
}

/**
 * Tela que cobre o app ao abrir, em dois passos: primeiro o estado (SP, MG…), depois a cidade daquele estado.
 * Cada opção é um quadrado com o pino de localização. A escolha da última vez fica marcada.
 * Os totais vêm do servidor; sem internet, a escolha funciona do mesmo jeito.
 */
export function EscolherCidade({
  atual,
  estados,
  cidades,
  aoEscolher,
}: {
  atual: Cidade;
  estados: Estado[];
  cidades: Cidade[];
  aoEscolher: (c: Cidade) => void;
}) {
  const [totais, setTotais] = useState<Record<string, number>>({});
  // com um estado só, pula direto para as cidades
  const [estado, setEstado] = useState<string | null>(estados.length === 1 ? estados[0].uf : null);

  useEffect(() => {
    chamar<{ cidades: (Cidade & { total: number })[] }>('cidades.php')
      .then((r) => setTotais(Object.fromEntries(r.cidades.map((c) => [c.slug, c.total]))))
      .catch(() => {});
  }, []);

  const totalEstado = (uf: string) => cidades.filter((c) => c.estado === uf).reduce((s, c) => s + (totais[c.slug] ?? 0), 0);
  const daqui = cidades.filter((c) => c.estado === estado);
  const nomeEstado = estados.find((e) => e.uf === estado)?.nome ?? '';

  return (
    <View style={[StyleSheet.absoluteFill, estilos.fundo]}>
      <SafeAreaView style={{ flex: 1 }}>
        <ScrollView contentContainerStyle={{ flexGrow: 1, justifyContent: 'center', padding: 20 }}>
          <View style={{ alignItems: 'center', marginBottom: 28 }}>
            <Image source={require('../../assets/logo.png')} style={{ width: 64, height: 64 }} />
            <Text style={estilos.titulo}>{estado ? 'Qual é a sua cidade?' : 'Qual é o seu estado?'}</Text>
            <Text style={estilos.sub}>{estado ? `Cidades de ${nomeEstado} no Acha Fácil.` : 'Escolha o estado e depois a cidade.'}</Text>
          </View>

          <View style={estilos.grade}>
            {estado === null
              ? estados.map((e) => (
                  <Quadrado
                    key={e.uf}
                    marcado={e.uf === atual.estado}
                    rotulo={`${e.nome} (${e.uf})`}
                    onPress={() => setEstado(e.uf)}
                  >
                    <Text style={estilos.sigla}>{e.uf}</Text>
                    <Text style={estilos.nome}>{e.nome}</Text>
                    <Text style={estilos.total}>
                      {(() => {
                        const n = cidades.filter((c) => c.estado === e.uf).length;
                        const t = totalEstado(e.uf);
                        return `${n} ${n === 1 ? 'cidade' : 'cidades'}${t ? ` · ${t.toLocaleString('pt-BR')} lugares` : ''}`;
                      })()}
                    </Text>
                  </Quadrado>
                ))
              : daqui.map((c) => (
                  <Quadrado key={c.slug} marcado={c.slug === atual.slug} rotulo={c.nome} onPress={() => aoEscolher(c)}>
                    <PinoLocal tamanho={64} />
                    <Text style={estilos.nome}>{c.nome}</Text>
                    <Text style={estilos.total}>{totais[c.slug] ? `${totais[c.slug].toLocaleString('pt-BR')} lugares` : c.estado}</Text>
                  </Quadrado>
                ))}
          </View>

          {estado !== null && estados.length > 1 && (
            <Pressable accessibilityRole="button" onPress={() => setEstado(null)} style={estilos.voltar} hitSlop={8}>
              <MaterialCommunityIcons name="chevron-left" size={26} color="#fff" />
              <Text style={estilos.voltarTexto}>Trocar o estado</Text>
            </Pressable>
          )}
        </ScrollView>
      </SafeAreaView>
    </View>
  );
}

function Quadrado({ marcado, rotulo, onPress, children }: { marcado: boolean; rotulo: string; onPress: () => void; children: React.ReactNode }) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={rotulo}
      accessibilityState={{ selected: marcado }}
      onPress={onPress}
      style={({ pressed }) => [estilos.quadrado, marcado && estilos.marcado, pressed && { opacity: 0.85 }]}
    >
      {marcado && (
        <View style={estilos.ultima}>
          <Text style={estilos.ultimaTexto}>Última escolha</Text>
        </View>
      )}
      {children}
    </Pressable>
  );
}

const estilos = StyleSheet.create({
  fundo: { backgroundColor: cores.verde, zIndex: 10 },
  titulo: { color: '#fff', fontSize: fonte.titulo, fontWeight: '900', marginTop: 14, textAlign: 'center' },
  sub: { color: '#CFE6D9', fontSize: fonte.normal, textAlign: 'center', marginTop: 4 },
  grade: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'center', gap: 14 },
  quadrado: {
    width: '46%',
    aspectRatio: 1,
    backgroundColor: cores.cartao,
    borderRadius: RAIO,
    borderWidth: 4,
    borderColor: 'transparent',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 10,
    gap: 4,
    ...sombra,
  },
  marcado: { borderColor: cores.amarelo },
  ultima: { position: 'absolute', top: -12, backgroundColor: cores.amarelo, borderRadius: 10, paddingHorizontal: 10, paddingVertical: 2 },
  ultimaTexto: { color: cores.verde, fontSize: 13, fontWeight: '900' },
  sigla: { fontSize: 44, fontWeight: '900', color: cores.verde, lineHeight: 50 },
  nome: { fontSize: fonte.normal, fontWeight: '900', color: cores.texto, textAlign: 'center' },
  total: { fontSize: 14, fontWeight: '700', color: cores.textoSuave, textAlign: 'center' },
  voltar: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', minHeight: 52, marginTop: 24 },
  voltarTexto: { color: '#fff', fontSize: fonte.normal, fontWeight: '800' },
});
