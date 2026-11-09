import Constants from 'expo-constants';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import { Platform } from 'react-native';

import { chamar } from './api/cliente';

// Avisos no celular para administradores (quem paga um plano, pede uma cidade ou manda alterações).
// O servidor só manda para contas cujo e-mail é de admin; aqui o app de um admin pede a permissão, pega o endereço de
// aviso do aparelho (Expo Push) e entrega ao servidor (api/avisos.php). No Expo Go e no emulador sem Google Play
// isso não funciona: falha em silêncio.

// aviso que chega com o app aberto também aparece
Notifications.setNotificationHandler({
  handleNotification: async () => ({ shouldShowBanner: true, shouldShowList: true, shouldPlaySound: true, shouldSetBadge: false }),
});

let tokenAtual: string | null = null;

export async function ligarAvisosDeAdmin(): Promise<void> {
  try {
    if (!Device.isDevice && Platform.OS === 'ios') return;
    if (Platform.OS === 'android') {
      await Notifications.setNotificationChannelAsync('admin', {
        name: 'Administração',
        description: 'Pagamentos, cidades pedidas e alterações para aprovar',
        // o AndroidImportance existe (vem de outro arquivo do pacote); o eslint-plugin-import é que não o enxerga
        // eslint-disable-next-line import/namespace
        importance: Notifications.AndroidImportance.HIGH,
        lightColor: '#F7C600',
      });
    }
    let permissao = await Notifications.getPermissionsAsync();
    if (!permissao.granted && permissao.canAskAgain) permissao = await Notifications.requestPermissionsAsync();
    if (!permissao.granted) return;
    const projectId = Constants.expoConfig?.extra?.eas?.projectId as string | undefined;
    const { data } = await Notifications.getExpoPushTokenAsync(projectId ? { projectId } : undefined);
    tokenAtual = data;
    await chamar('avisos.php', 'POST', { token: data });
  } catch {
    // sem avisos neste aparelho (Expo Go, sem Google Play, sem internet...): o resto do app segue normal
  }
}

/** Ao sair da conta: o servidor para de mandar avisos para este aparelho. */
export async function desligarAvisos(): Promise<void> {
  if (!tokenAtual) return;
  const token = tokenAtual;
  tokenAtual = null;
  await chamar('avisos.php', 'DELETE', { token }).catch(() => {});
}
