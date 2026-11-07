import * as SplashScreen from 'expo-splash-screen';
import { useEffect, useRef, useState } from 'react';
import { Animated, Easing, StyleSheet, Text, View } from 'react-native';

import { cores, fonte } from '../tema';

// Segura a splash nativa (logo de 200 px no fundo verde, definida no app.json) até a abertura animada
// estar desenhada. Como a abertura começa idêntica, a troca não aparece.
SplashScreen.preventAutoHideAsync().catch(() => {});

const LOGO = 200;
const DURACAO_PARADA = 1400; // tempo com o nome na tela antes de sumir

/**
 * Splash Screen do Acha Fácil (fundo verde, como a arte da marca): a logo sobe um pouco,
 * aparecem "Acha Fácil!" e "O comércio da sua cidade, pertinho de você."; depois tudo some e mostra o app.
 * Tocar na tela pula a abertura.
 */
export function Abertura({ children }: { children: React.ReactNode }) {
  const [visivel, setVisivel] = useState(true);
  // Valores da animação criados uma vez só (useState, e não useRef(...).current, que o lint proíbe no render)
  const [logo] = useState(() => new Animated.Value(0)); // 0 = igual à splash nativa, 1 = posição final
  const [texto] = useState(() => new Animated.Value(0));
  const [saida] = useState(() => new Animated.Value(1));
  const iniciou = useRef(false);

  function sair() {
    Animated.timing(saida, { toValue: 0, duration: 350, useNativeDriver: true }).start(() => setVisivel(false));
  }

  function iniciar() {
    if (iniciou.current) return;
    iniciou.current = true;
    SplashScreen.hideAsync().catch(() => {});
    Animated.sequence([
      Animated.timing(logo, { toValue: 1, duration: 550, easing: Easing.out(Easing.back(1.4)), useNativeDriver: true }),
      Animated.timing(texto, { toValue: 1, duration: 450, easing: Easing.out(Easing.cubic), useNativeDriver: true }),
      Animated.delay(DURACAO_PARADA),
    ]).start(({ finished }) => finished && sair());
  }

  // Garantia: se algo travar a animação, o app aparece mesmo assim.
  useEffect(() => {
    const t = setTimeout(() => setVisivel(false), 6000);
    return () => clearTimeout(t);
  }, []);

  return (
    <View style={{ flex: 1 }}>
      {children}
      {visivel && (
        <Animated.View
          style={[StyleSheet.absoluteFill, estilos.fundo, { opacity: saida }]}
          onLayout={iniciar}
          onTouchEnd={sair}
          accessibilityLabel="Acha Fácil! O comércio da sua cidade, pertinho de você."
        >
          <Animated.Image
            source={require('../../assets/splash-icon.png')}
            style={[
              estilos.logo,
              {
                transform: [
                  { translateY: logo.interpolate({ inputRange: [0, 1], outputRange: [0, -70] }) },
                  { scale: logo.interpolate({ inputRange: [0, 1], outputRange: [1, 0.8] }) },
                ],
              },
            ]}
          />
          <Animated.View
            style={[
              estilos.textos,
              {
                opacity: texto,
                transform: [{ translateY: texto.interpolate({ inputRange: [0, 1], outputRange: [20, 0] }) }],
              },
            ]}
          >
            <Text style={estilos.titulo}>
              Acha <Text style={{ color: cores.amarelo }}>Fácil!</Text>
            </Text>
            <Text style={estilos.lema}>O comércio da sua cidade,{'\n'}pertinho de você.</Text>
          </Animated.View>
        </Animated.View>
      )}
    </View>
  );
}

const estilos = StyleSheet.create({
  fundo: { backgroundColor: cores.verde, alignItems: 'center', justifyContent: 'center', zIndex: 100 },
  logo: { width: LOGO, height: LOGO },
  textos: { position: 'absolute', top: '50%', marginTop: 70, alignItems: 'center' },
  titulo: { fontSize: 40, fontWeight: '900', color: '#FFFFFF', letterSpacing: 0.5 },
  lema: { fontSize: fonte.media, color: '#FFFFFF', fontWeight: '700', textAlign: 'center', marginTop: 10, lineHeight: 28 },
});
