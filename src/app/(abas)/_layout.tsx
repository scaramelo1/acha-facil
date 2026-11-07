import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Tabs } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { cores } from '../../tema';

type NomeIcone = keyof typeof MaterialCommunityIcons.glyphMap;

function icone(nome: NomeIcone, ativo: NomeIcone) {
  // eslint-disable-next-line react/display-name
  return ({ focused, color }: { focused: boolean; color: string }) => (
    <MaterialCommunityIcons name={focused ? ativo : nome} size={30} color={color} />
  );
}

// Barra de abas flutuante: pílula verde, aba ativa em amarelo, com o nome escrito (mais fácil que menus escondidos)
export default function Abas() {
  const baixo = useSafeAreaInsets().bottom;
  return (
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: cores.amarelo,
        tabBarInactiveTintColor: '#CFE6D9',
        tabBarLabelStyle: { fontSize: 14, fontWeight: '800' },
        tabBarStyle: {
          position: 'absolute',
          left: 12,
          right: 12,
          bottom: Math.max(baixo, 12),
          height: 76,
          paddingTop: 8,
          paddingBottom: 10,
          borderRadius: 28,
          borderTopWidth: 0,
          backgroundColor: cores.verde,
          elevation: 8,
          shadowColor: '#000',
          shadowOpacity: 0.18,
          shadowRadius: 12,
          shadowOffset: { width: 0, height: 6 },
        },
        tabBarItemStyle: { borderRadius: 22 },
        headerStyle: { backgroundColor: cores.fundo },
        headerTintColor: cores.texto,
        headerTitleStyle: { fontSize: 24, fontWeight: '900' },
        headerTitleAlign: 'left',
        headerShadowVisible: false,
        sceneStyle: { backgroundColor: cores.fundo },
      }}
    >
      <Tabs.Screen name="index" options={{ title: 'Início', headerShown: false, tabBarIcon: icone('home-variant-outline', 'home-variant') }} />
      <Tabs.Screen name="explorar" options={{ title: 'Áreas', tabBarIcon: icone('view-grid-outline', 'view-grid') }} />
      <Tabs.Screen name="salvos" options={{ title: 'Salvos', tabBarIcon: icone('heart-outline', 'heart') }} />
      <Tabs.Screen name="conta" options={{ title: 'Conta', tabBarIcon: icone('account-circle-outline', 'account-circle') }} />
    </Tabs>
  );
}
