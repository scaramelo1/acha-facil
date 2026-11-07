import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';

import { Abertura } from '../componentes/Abertura';
import { ProvedorCidade } from '../cidade';
import { ProvedorConta } from '../conta';
import { cores, fonte } from '../tema';

export default function Raiz() {
  return (
    <Abertura>
      <ProvedorCidade>
        <ProvedorConta>
          <StatusBar style="dark" />
          <Stack
            screenOptions={{
              headerStyle: { backgroundColor: cores.fundo },
              headerTintColor: cores.texto,
              headerTitleStyle: { fontSize: fonte.media, fontWeight: '900' },
              headerShadowVisible: false,
              headerBackTitle: 'Voltar',
              contentStyle: { backgroundColor: cores.fundo },
            }}
          >
            <Stack.Screen name="(abas)" options={{ headerShown: false }} />
            <Stack.Screen name="categoria/[id]" options={{ title: '' }} />
            <Stack.Screen name="busca" options={{ title: 'Buscar' }} />
            <Stack.Screen name="profissional/[id]" options={{ title: '' }} />
            <Stack.Screen name="entrar" options={{ title: 'Entrar', presentation: 'modal' }} />
          </Stack>
        </ProvedorConta>
      </ProvedorCidade>
    </Abertura>
  );
}
