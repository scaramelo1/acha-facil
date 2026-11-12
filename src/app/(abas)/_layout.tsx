import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Tabs } from 'expo-router';
import { Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { cores } from '../../tema';

type NomeIcone = keyof typeof MaterialCommunityIcons.glyphMap;

/** Aba com ícone e nome; a aba aberta fica verde, com o ícone cheio, nome em negrito e um tracinho embaixo. */
function aba(titulo: string, nome: NomeIcone, ativo: NomeIcone) {
  // eslint-disable-next-line react/display-name
  return ({ focused }: { focused: boolean }) => (
    <View style={{ width: '100%', alignItems: 'center', justifyContent: 'center', gap: 2 }}>
      <MaterialCommunityIcons name={focused ? ativo : nome} size={27} color={focused ? cores.verde : cores.texto} />
      <Text numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.8} style={{ fontSize: 13, fontWeight: focused ? '900' : '600', color: focused ? cores.verde : cores.texto }}>
        {titulo}
      </Text>
      <View style={{ width: 36, height: 3, borderRadius: 2, backgroundColor: focused ? cores.verde : 'transparent', marginTop: 2 }} />
    </View>
  );
}

// Barra de abas branca embaixo (nomes sempre escritos: mais fácil que menus escondidos). Cada tela tem o próprio cabeçalho verde.
export default function Abas() {
  const baixo = useSafeAreaInsets().bottom;
  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarShowLabel: false,
        tabBarStyle: {
          height: 76 + baixo,
          paddingTop: 10,
          paddingBottom: baixo + 6,
          borderTopWidth: 1,
          borderTopColor: cores.borda,
          backgroundColor: cores.cartao,
          elevation: 8,
        },
        tabBarIconStyle: { width: '100%', height: 58 },
        sceneStyle: { backgroundColor: cores.fundo },
      }}
    >
      <Tabs.Screen name="index" options={{ title: 'Início', tabBarAccessibilityLabel: 'Início', tabBarIcon: aba('Início', 'home-outline', 'home') }} />
      <Tabs.Screen name="explorar" options={{ title: 'Categorias', tabBarAccessibilityLabel: 'Categorias', tabBarIcon: aba('Categorias', 'view-grid-outline', 'view-grid') }} />
      <Tabs.Screen name="eventos" options={{ title: 'Eventos', tabBarAccessibilityLabel: 'Eventos', tabBarIcon: aba('Eventos', 'calendar-month-outline', 'calendar-month') }} />
      <Tabs.Screen name="salvos" options={{ title: 'Favoritos', tabBarAccessibilityLabel: 'Favoritos', tabBarIcon: aba('Favoritos', 'heart-outline', 'heart') }} />
      <Tabs.Screen name="conta" options={{ title: 'Perfil', tabBarAccessibilityLabel: 'Perfil', tabBarIcon: aba('Perfil', 'account-outline', 'account') }} />
    </Tabs>
  );
}
