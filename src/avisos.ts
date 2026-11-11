import Constants from 'expo-constants';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import { Platform } from 'react-native';

import { chamar } from './api/cliente';

// Avisos no celular (Expo Push). Desde a 1.6.0 vale para todos: o app pede a permissao, pega o endereco de aviso do
// aparelho e entrega ao servidor (api/avisos.php) junto com a cidade escolhida (vai no ?cidade= de toda chamada).
// Os administradores mandam notificacoes por cidade (canal "novidades"); quem e admin tambem recebe os avisos de
// administracao (pagamentos, cidades pedidas, alteracoes - canal "admin"). No Expo Go e no emulador sem Google Play
// isso nao funciona: falha em silencio.

// aviso que chega com o app aberto tambem aparece
Notifications.setNotificationHandler({
  handleNotification: async () => ({ shouldShowBanner: true, shouldShowList: true, shouldPlaySound: true, shouldSetBadge: false }),
});

let tokenAtual: string | null = null;
let canaisProntos = false;

async function criarCanais() {
  if (Platform.OS !== 'android' || canaisProntos) return;
  // o AndroidImportance existe (vem de outro arquivo do pacote); o eslint-plugin-import e que nao o enxerga
  /* eslint-disable import/namespace */
  await Notifications.setNotificationChannelAsync('novidades', {
    name: 'Novidades',
    description: 'Novidades do Acha Fácil na sua cidade',
    importance: Notifications.AndroidImportance.HIGH,
    lightColor: '#F7C600',
  });
  await Notifications.setNotificationChannelAsync('admin', {
    name: 'Administração',
    description: 'Pagamentos, cidades pedidas e alterações para aprovar',
    importance: Notifications.AndroidImportance.HIGH,
    lightColor: '#F7C600',
  });
  /* eslint-enable import/namespace */
  canaisProntos = true;
}

/**
 * Liga os avisos neste aparelho e conta ao servidor a cidade escolhida (e a conta, se entrou). Pode ser chamada
 * varias vezes (ao escolher a cidade, ao entrar na conta): a permissao so e pedida uma vez.
 */
export async function ligarAvisos(): Promise<void> {
  try {
    if (!Device.isDevice && Platform.OS === 'ios') return;
    await criarCanais();
    let permissao = await Notifications.getPermissionsAsync();
    if (!permissao.granted && permissao.canAskAgain) permissao = await Notifications.requestPermissionsAsync();
    if (!permissao.granted) return;
    if (!tokenAtual) {
      const projectId = Constants.expoConfig?.extra?.eas?.projectId as string | undefined;
      const { data } = await Notifications.getExpoPushTokenAsync(projectId ? { projectId } : undefined);
      tokenAtual = data;
    }
    await chamar('avisos.php', 'POST', { token: tokenAtual });
  } catch {
    // sem avisos neste aparelho (Expo Go, sem Google Play, sem internet...): o resto do app segue normal
  }
}

/** Ao sair da conta: param os avisos de administracao (as novidades da cidade continuam). */
export async function desligarAvisos(): Promise<void> {
  if (!tokenAtual) return;
  await chamar('avisos.php', 'DELETE', { token: tokenAtual }).catch(() => {});
}
