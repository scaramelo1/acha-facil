import * as Notifications from 'expo-notifications';
import { Stack, useRouter } from 'expo-router';
import { useEffect } from 'react';
import { StatusBar } from 'expo-status-bar';

import { Abertura } from '../componentes/Abertura';
import { ProvedorCidade } from '../cidade';
import { ProvedorConta } from '../conta';
import { cores } from '../tema';
import { useAvisoDeVersao } from '../versao';

/**
 * Tocar num aviso (também com o app fechado): o de administração abre a tela Administração; a notificação dos admins
 * abre a ficha do comércio, quando tem um, ou só o app.
 */
function AbrirAvisos() {
  const router = useRouter();
  useEffect(() => {
    let ultimo = '';
    const abrir = (r: Notifications.NotificationResponse | null) => {
      if (!r || r.notification.request.identifier === ultimo) return;
      ultimo = r.notification.request.identifier;
      const dados = r.notification.request.content.data ?? {};
      if (dados.tela === 'admin') router.push('/admin');
      else if (dados.tela === 'profissional' && Number(dados.id) > 0) router.push(`/profissional/${Number(dados.id)}?origem=link`);
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
            <Stack.Screen name="notificar" />
          </Stack>
        </ProvedorConta>
      </ProvedorCidade>
    </Abertura>
  );
}
