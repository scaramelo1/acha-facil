import Constants from 'expo-constants';
import { useEffect, useRef } from 'react';
import { Alert, AppState, Linking } from 'react-native';

import { chamar } from './api/cliente';

// Aviso de versão nova (igual ao ReadyTAF): ao abrir o app, pergunta ao servidor qual é o APK publicado no site
// (api/versao.php, gravado pelo publicar-apk.sh) e avisa quem está com uma versão mais velha. Uma vez por versão nova.

export const VERSAO_INSTALADA = Constants.expoConfig?.version ?? null;

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
        const { versao, link } = await chamar<{ versao: string | null; link: string | null }>('versao.php');
        if (!versao || !link || !precisaAtualizar(VERSAO_INSTALADA, versao) || jaAvisou.current === versao) return;
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
