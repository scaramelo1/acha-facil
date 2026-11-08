import * as Notifications from 'expo-notifications';
import { Stack, useRouter } from 'expo-router';
import { useEffect } from 'react';
import { StatusBar } from 'expo-status-bar';

import { Abertura } from '../componentes/Abertura';
import { ProvedorCidade } from '../cidade';
import { ProvedorConta } from '../conta';
import { cores } from '../tema';
import { useAvisoDeVersao } from '../versao';

/** Tocar num aviso de administração abre a tela Administração (também quando o app estava fechado). */
function AbrirAvisos() {
  const router = useRouter();
  useEffect(() => {
    const abrir = (r: Notifications.NotificationResponse | null) => {
      if (r?.notification.request.content.data?.tela === 'admin') router.push('/admin');
    };
    try {
      abrir(Notifications.getLastNotificationResponse());
    } catch {
      // sem módulo de avisos (Expo Go): nada a abrir
    }
    const assinatura = Notifications.addNotificationResponseReceivedListener(abrir);
    return () => assinatura.remove();
  }, [router]);
  return null;
}

export default function Raiz() {
  useAvisoDeVersao();
  return (
    <Abertura>
      <ProvedorCidade>
        <ProvedorConta>
          {/* todas as telas têm o cabeçalho verde: ícones claros na barra de status */}
          <StatusBar style="light" />
          <AbrirAvisos />
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
            <Stack.Screen name="admin" />
          </Stack>
        </ProvedorConta>
      </ProvedorCidade>
    </Abertura>
  );
}
