import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';

import { Abertura } from '../componentes/Abertura';
import { ProvedorCidade } from '../cidade';
import { ProvedorConta } from '../conta';
import { cores } from '../tema';

export default function Raiz() {
  return (
    <Abertura>
      <ProvedorCidade>
        <ProvedorConta>
          {/* todas as telas têm o cabeçalho verde: ícones claros na barra de status */}
          <StatusBar style="light" />
          <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: cores.fundo } }}>
            <Stack.Screen name="(abas)" />
            <Stack.Screen name="categoria/[id]" />
            <Stack.Screen name="busca" />
            <Stack.Screen name="profissional/[id]" />
            <Stack.Screen name="avaliar/[id]" />
            <Stack.Screen name="entrar" />
            <Stack.Screen name="google" options={{ animation: 'none' }} />
            <Stack.Screen name="meu-negocio" />
            <Stack.Screen name="planos" />
            <Stack.Screen name="sobre" />
          </Stack>
        </ProvedorConta>
      </ProvedorCidade>
    </Abertura>
  );
}
