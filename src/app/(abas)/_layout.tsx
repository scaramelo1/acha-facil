import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Tabs } from 'expo-router';
import { Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { cores } from '../../tema';

type NomeIcone = keyof typeof MaterialCommunityIcons.glyphMap;

/** Aba com ícone e nome; a aba aberta vira uma pílula verde com ícone e nome brancos. */
function aba(titulo: string, nome: NomeIcone, ativo: NomeIcone) {
  // eslint-disable-next-line react/display-name
  return ({ focused }: { focused: boolean }) => (
    <View
      style={{
        minWidth: 84,
        height: 60,
        paddingHorizontal: 10,
        borderRadius: 22,
        alignItems: 'center',
        justifyContent: 'center',
        backgroundColor: focused ? cores.verde : 'transparent',
      }}
    >
      <MaterialCommunityIcons name={focused ? ativo : nome} size={28} color={focused ? '#fff' : cores.texto} />
      <Text numberOfLines={1} style={{ fontSize: 14, fontWeight: '700', color: focused ? '#fff' : cores.texto }}>
        {titulo}
      </Text>
    </View>
  );
}

// Barra de abas branca embaixo, com a aba aberta em pílula verde (nomes sempre escritos: mais fácil que menus escondidos)
export default function Abas() {
  const baixo = useSafeAreaInsets().bottom;
  return (
    <Tabs
      screenOptions={{
        tabBarShowLabel: false,
        tabBarStyle: {
          position: 'absolute',
          height: 84 + baixo,
          paddingTop: 12,
          paddingBottom: baixo + 8,
          borderTopWidth: 0,
          borderTopLeftRadius: 26,
          borderTopRightRadius: 26,
          backgroundColor: cores.cartao,
          elevation: 12,
          shadowColor: '#0E2A1B',
          shadowOpacity: 0.1,
          shadowRadius: 14,
          shadowOffset: { width: 0, height: -4 },
        },
        tabBarIconStyle: { width: '100%', height: 60 },
        headerStyle: { backgroundColor: cores.fundo },
        headerTintColor: cores.texto,
        headerTitleStyle: { fontSize: 24, fontWeight: '900' },
        headerTitleAlign: 'left',
        headerShadowVisible: false,
        sceneStyle: { backgroundColor: cores.fundo },
      }}
    >
      <Tabs.Screen name="index" options={{ title: 'Início', headerShown: false, tabBarAccessibilityLabel: 'Início', tabBarIcon: aba('Início', 'home-outline', 'home') }} />
      <Tabs.Screen name="explorar" options={{ title: 'Categorias', tabBarAccessibilityLabel: 'Categorias', tabBarIcon: aba('Categorias', 'view-grid-outline', 'view-grid') }} />
      <Tabs.Screen name="salvos" options={{ title: 'Favoritos', tabBarAccessibilityLabel: 'Favoritos', tabBarIcon: aba('Favoritos', 'heart-outline', 'heart') }} />
      <Tabs.Screen name="conta" options={{ title: 'Perfil', tabBarAccessibilityLabel: 'Perfil', tabBarIcon: aba('Perfil', 'account-outline', 'account') }} />
    </Tabs>
  );
}
