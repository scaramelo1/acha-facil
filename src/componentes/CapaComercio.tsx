import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { useState } from 'react';
import { Modal, Pressable, ScrollView, StyleSheet, Text, useWindowDimensions, View } from 'react-native';

import { cores, RAIO } from '../tema';
import { Foto, Icone } from './ui';

const ALTURA = 210;
const LOGO = 104;

/**
 * Topo da página do comércio, como uma capa de rede social: as fotos do comércio passam para o lado
 * (carrossel com bolinhas e contador) e o logo fica num círculo por cima da borda de baixo.
 * Sem fotos, a capa é só a cor da área; sem logo, o círculo mostra o ícone da área.
 * Tocar numa foto abre as fotos em tela cheia.
 */
export function CapaComercio({ fotos, logo, icone, cor, nome }: { fotos: string[]; logo: string | null; icone: string; cor: string; nome: string }) {
  const { width } = useWindowDimensions();
  const largura = width - 32;
  const [atual, setAtual] = useState(0);
  const [cheia, setCheia] = useState<number | null>(null);

  return (
    <View style={{ marginHorizontal: 16, marginTop: 16, marginBottom: LOGO / 2 }}>
      <View style={estilos.capa}>
        {fotos.length === 0 ? (
          <Foto foto={null} semIcone icone={icone} cor={cor} largura={largura} altura={ALTURA} raio={0} />
        ) : (
          <ScrollView
            horizontal
            pagingEnabled
            showsHorizontalScrollIndicator={false}
            onMomentumScrollEnd={(e) => setAtual(Math.round(e.nativeEvent.contentOffset.x / largura))}
          >
            {fotos.map((f, i) => (
              <Pressable key={f} accessibilityRole="imagebutton" accessibilityLabel={`Foto ${i + 1} de ${fotos.length} de ${nome}`} onPress={() => setCheia(i)}>
                <Image source={f} style={{ width: largura, height: ALTURA }} contentFit="cover" transition={150} />
              </Pressable>
            ))}
          </ScrollView>
        )}
        {fotos.length > 1 && (
          <>
            <View style={estilos.contador}>
              <MaterialCommunityIcons name="image-multiple-outline" size={15} color="#fff" />
              <Text style={estilos.contadorTexto}>
                {atual + 1}/{fotos.length}
              </Text>
            </View>
            <View style={estilos.bolinhas} pointerEvents="none">
              {fotos.map((f, i) => (
                <View key={f} style={[estilos.bolinha, i === atual && estilos.bolinhaAtual]} />
              ))}
            </View>
          </>
        )}
      </View>

      <View style={estilos.logo} accessibilityLabel={logo ? `Logo de ${nome}` : undefined}>
        {logo ? (
          <Image source={logo} style={{ width: LOGO - 20, height: LOGO - 20 }} contentFit="contain" />
        ) : (
          <View style={[estilos.semLogo, { backgroundColor: /^#[0-9a-f]{6}$/i.test(cor) ? cor : cores.verde }]}>
            <Icone nome={icone} tamanho={46} cor="#fff" />
          </View>
        )}
      </View>

      {cheia !== null && <TelaCheia fotos={fotos} inicio={cheia} fechar={() => setCheia(null)} />}
    </View>
  );
}

function TelaCheia({ fotos, inicio, fechar }: { fotos: string[]; inicio: number; fechar: () => void }) {
  const { width, height } = useWindowDimensions();
  const [atual, setAtual] = useState(inicio);
  return (
    <Modal visible animationType="fade" onRequestClose={fechar} statusBarTranslucent>
      <View style={{ flex: 1, backgroundColor: '#000' }}>
        <ScrollView
          horizontal
          pagingEnabled
          showsHorizontalScrollIndicator={false}
          contentOffset={{ x: inicio * width, y: 0 }}
          onMomentumScrollEnd={(e) => setAtual(Math.round(e.nativeEvent.contentOffset.x / width))}
        >
          {fotos.map((f) => (
            <Image key={f} source={f} style={{ width, height }} contentFit="contain" />
          ))}
        </ScrollView>
        <Text style={estilos.cheiaContador}>
          {atual + 1} de {fotos.length}
        </Text>
        <Pressable accessibilityRole="button" accessibilityLabel="Fechar fotos" onPress={fechar} style={estilos.fechar} hitSlop={10}>
          <MaterialCommunityIcons name="close" size={30} color="#fff" />
        </Pressable>
      </View>
    </Modal>
  );
}

const estilos = StyleSheet.create({
  capa: { height: ALTURA, borderRadius: RAIO, overflow: 'hidden', backgroundColor: cores.verdeClaro },
  contador: {
    position: 'absolute',
    top: 10,
    right: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: 'rgba(0,0,0,0.55)',
    borderRadius: 12,
    paddingHorizontal: 9,
    paddingVertical: 3,
  },
  contadorTexto: { color: '#fff', fontSize: 14, fontWeight: '800' },
  bolinhas: { position: 'absolute', bottom: 10, right: 12, flexDirection: 'row', gap: 6 },
  bolinha: { width: 8, height: 8, borderRadius: 4, backgroundColor: 'rgba(255,255,255,0.6)' },
  bolinhaAtual: { backgroundColor: '#fff', width: 20 },
  logo: {
    position: 'absolute',
    left: 14,
    bottom: -LOGO / 2,
    width: LOGO,
    height: LOGO,
    borderRadius: LOGO / 2,
    borderWidth: 4,
    borderColor: '#fff',
    backgroundColor: '#fff',
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
    elevation: 4,
    shadowColor: '#000',
    shadowOpacity: 0.18,
    shadowRadius: 6,
    shadowOffset: { width: 0, height: 2 },
  },
  semLogo: { width: '100%', height: '100%', alignItems: 'center', justifyContent: 'center' },
  cheiaContador: { position: 'absolute', bottom: 40, alignSelf: 'center', color: '#fff', fontSize: 16, fontWeight: '800' },
  fechar: {
    position: 'absolute',
    top: 44,
    right: 16,
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: 'rgba(255,255,255,0.18)',
    alignItems: 'center',
    justifyContent: 'center',
  },
});
