import Constants from 'expo-constants';
import { useEffect, useRef } from 'react';
import { Alert, AppState, Linking, Platform } from 'react-native';

import { chamar } from './api/cliente';

// Aviso de versão nova (igual ao ReadyTAF): ao abrir o app (e ao voltar para ele), pergunta ao servidor a versão nova
// (api/versao.php) e avisa quem está numa versão mais velha, com "Depois" ou o botão para atualizar.
// - Instalado pelo site: compara com o APK publicado (publicar-apk.sh) e o botão baixa o APK.
// - Instalado pela Play Store (extra.loja = 'play', vindo do app.config.js): pergunta direto para a Play
//   (atualização no app do Google, biblioteca expo-in-app-updates). Só avisa quando a versão nova já está liberada
//   para aquele celular, sem ninguém precisar informar nada; "Atualizar" abre a tela de atualização da própria Play.
//   Se a Play não responder (app instalado por fora, celular sem a loja), usa a versão informada em /admin/app.php e
//   abre a página do app na loja. Até a 1.6.0 a versão da Play não avisava nada.

export const VERSAO_INSTALADA = Constants.expoConfig?.version ?? null;
export const DA_PLAY_STORE = Constants.expoConfig?.extra?.loja === 'play';

const partes = (v: string) => v.split('.').map((p) => Number.parseInt(p, 10) || 0);
const valida = (v: unknown): v is string => typeof v === 'string' && /^\d{1,4}(\.\d{1,4}){0,2}$/.test(v);

/** True quando a versão publicada é mais nova que a instalada. */
export function precisaAtualizar(instalada: string | null, publicada: string | null) {
  if (!valida(instalada) || !valida(publicada)) return false;
  const [a, b] = [partes(instalada), partes(publicada)];
  for (let i = 0; i < 3; i++) {
    const d = (a[i] ?? 0) - (b[i] ?? 0);
    if (d !== 0) return d < 0;
  }
  return false;
}

interface RespostaVersao {
  versao: string | null;
  link: string | null;
  /** Versão disponível na Play Store (informada no /admin/app.php) e a página do app na loja. */
  play?: { versao: string | null; link: string | null; pacote?: string };
}

const PACOTE = 'br.com.fsadesigner.achafacil';

/** Abre o app da Play Store direto na página do Acha Fácil; sem o app da loja, abre a página no navegador. */
async function abrirPlayStore(pacote: string, pagina: string) {
  try {
    await Linking.openURL(`market://details?id=${pacote}`);
  } catch {
    Linking.openURL(pagina).catch(() => {});
  }
}

/**
 * Pergunta para a Play se há atualização liberada para este celular. Devolve true quando a Play respondeu (com ou
 * sem atualização: aí não precisa perguntar ao servidor) e false quando não deu para perguntar.
 * O módulo é carregado só aqui (versão da Play), para o modo de desenvolvimento não precisar dele.
 */
let avisouPlay = false;
async function avisoPelaPlay(): Promise<boolean> {
  try {
    const Play = await import('expo-in-app-updates');
    const info = await Play.checkForUpdate();
    if (!info.updateAvailable || avisouPlay) return true;
    avisouPlay = true;
    Alert.alert('Nova versão do Acha Fácil', 'Tem uma versão nova na Play Store. Atualize agora: seus favoritos e sua conta continuam.', [
      { text: 'Depois', style: 'cancel' },
      {
        text: 'Atualizar',
        onPress: () => {
          // tela cheia da Play ("Atualizar"/"Instalar"); se não der, abre a página do app na loja
          Play.startUpdate(info.immediateAllowed !== false).catch(
            () => void abrirPlayStore(PACOTE, `https://play.google.com/store/apps/details?id=${PACOTE}`),
          );
        },
      },
    ]);
    return true;
  } catch {
    return false;
  }
}

/** Mínimo entre duas consultas, para não perguntar toda vez que o app volta para a frente. */
const INTERVALO_MS = 6 * 3600 * 1000;

export function useAvisoDeVersao() {
  const ultimaConsulta = useRef(0);
  const jaAvisou = useRef<string | null>(null);

  useEffect(() => {
    const consultar = async () => {
      if (Date.now() - ultimaConsulta.current < INTERVALO_MS) return;
      ultimaConsulta.current = Date.now();
      try {
        if (DA_PLAY_STORE && Platform.OS === 'android' && (await avisoPelaPlay())) return;
        const r = await chamar<RespostaVersao>('versao.php');
        const versao = DA_PLAY_STORE ? (r.play?.versao ?? null) : r.versao;
        if (!versao || !precisaAtualizar(VERSAO_INSTALADA, versao) || jaAvisou.current === versao) return;
        if (DA_PLAY_STORE) {
          const pagina = r.play?.link || `https://play.google.com/store/apps/details?id=${PACOTE}`;
          jaAvisou.current = versao;
          Alert.alert(
            'Nova versão do Acha Fácil',
            `Saiu a versão ${versao} (a sua é a ${VERSAO_INSTALADA}). Atualize pela Play Store: seus favoritos e sua conta continuam.`,
            [
              { text: 'Depois', style: 'cancel' },
              { text: 'Atualizar', onPress: () => void abrirPlayStore(r.play?.pacote || PACOTE, pagina) },
            ],
          );
          return;
        }
        const link = r.link;
        if (!link) return;
        jaAvisou.current = versao;
        Alert.alert(
          'Nova versão do Acha Fácil',
          `Saiu a versão ${versao} (a sua é a ${VERSAO_INSTALADA}). Baixe e instale por cima: seus favoritos e sua conta continuam.`,
          [
            { text: 'Depois', style: 'cancel' },
            { text: 'Baixar', onPress: () => void Linking.openURL(link) },
          ],
        );
      } catch {
        // sem internet: tenta de novo na próxima vez que o app abrir
        ultimaConsulta.current = 0;
      }
    };
    void consultar();
    const assinatura = AppState.addEventListener('change', (estado) => {
      if (estado === 'active') void consultar();
    });
    return () => assinatura.remove();
  }, []);
}
