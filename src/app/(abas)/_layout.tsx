import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Tabs } from 'expo-router';

import { cores } from '../../tema';

type NomeIcone = keyof typeof MaterialCommunityIcons.glyphMap;

function icone(nome: NomeIcone, ativo: NomeIcone) {
  // eslint-disable-next-line react/display-name
  return ({ focused, color }: { focused: boolean; color: string }) => (
    <MaterialCommunityIcons name={focused ? ativo : nome} size={30} color={color} />
  );
}

// Abas embaixo, grandes e com nome escrito (mais fácil que menus escondidos)
export default function Abas() {
  return (
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: cores.destaque,
        tabBarInactiveTintColor: cores.texto,
        tabBarLabelStyle: { fontSize: 14, fontWeight: '700' },
        tabBarStyle: { minHeight: 68, paddingTop: 6 },
        headerStyle: { backgroundColor: cores.fundo },
        headerTintColor: cores.texto,
        headerTitleStyle: { fontSize: 20, fontWeight: '800' },
        headerShadowVisible: false,
        sceneStyle: { backgroundColor: cores.fundo },
      }}
    >
      <Tabs.Screen name="index" options={{ title: 'Início', headerShown: false, tabBarIcon: icone('home-outline', 'home') }} />
      <Tabs.Screen name="salvos" options={{ title: 'Salvos', tabBarIcon: icone('heart-outline', 'heart') }} />
      <Tabs.Screen name="conta" options={{ title: 'Conta', tabBarIcon: icone('account-circle-outline', 'account-circle') }} />
    </Tabs>
  );
}
