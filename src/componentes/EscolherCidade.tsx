import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { useEffect, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { chamar, type Cidade } from '../api/cliente';
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
 * Tela que cobre o app ao abrir: "Onde você está?" com um quadrado por cidade (pino de localização + nome).
 * A cidade da última vez fica marcada. Os totais vêm do servidor; sem internet, a escolha funciona do mesmo jeito.
 */
export function EscolherCidade({ atual, cidades, aoEscolher }: { atual: string; cidades: Cidade[]; aoEscolher: (c: Cidade) => void }) {
  const [totais, setTotais] = useState<Record<string, number>>({});

  useEffect(() => {
    chamar<{ cidades: (Cidade & { total: number })[] }>('cidades.php')
      .then((r) => setTotais(Object.fromEntries(r.cidades.map((c) => [c.slug, c.total]))))
      .catch(() => {});
  }, []);

  return (
    <View style={[StyleSheet.absoluteFill, estilos.fundo]}>
      <SafeAreaView style={{ flex: 1, justifyContent: 'center', padding: 20 }}>
        <View style={{ alignItems: 'center', marginBottom: 28 }}>
          <Image source={require('../../assets/logo.png')} style={{ width: 64, height: 64 }} />
          <Text style={estilos.titulo}>Onde você está?</Text>
          <Text style={estilos.sub}>Escolha a cidade para ver os comércios de lá.</Text>
        </View>
        <View style={estilos.grade}>
          {cidades.map((c) => {
            const marcada = c.slug === atual;
            return (
              <Pressable
                key={c.slug}
                accessibilityRole="button"
                accessibilityLabel={c.nome}
                accessibilityState={{ selected: marcada }}
                onPress={() => aoEscolher(c)}
                style={({ pressed }) => [estilos.quadrado, marcada && estilos.marcado, pressed && { transform: [{ translateY: 3 }] }]}
              >
                {marcada && (
                  <View style={estilos.ultima}>
                    <Text style={estilos.ultimaTexto}>Última escolha</Text>
                  </View>
                )}
                <PinoLocal tamanho={68} />
                <Text style={estilos.nome}>{c.nome}</Text>
                <Text style={estilos.total}>{totais[c.slug] ? `${totais[c.slug].toLocaleString('pt-BR')} lugares` : 'SP'}</Text>
              </Pressable>
            );
          })}
        </View>
      </SafeAreaView>
    </View>
  );
}

const estilos = StyleSheet.create({
  fundo: { backgroundColor: cores.verde, zIndex: 10 },
  titulo: { color: '#fff', fontSize: fonte.titulo, fontWeight: '900', marginTop: 14 },
  sub: { color: '#CFE6D9', fontSize: fonte.normal, textAlign: 'center', marginTop: 4 },
  grade: { flexDirection: 'row', gap: 14 },
  quadrado: {
    flex: 1,
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
  nome: { fontSize: fonte.normal, fontWeight: '900', color: cores.texto, textAlign: 'center' },
  total: { fontSize: 15, fontWeight: '700', color: cores.textoSuave },
});
