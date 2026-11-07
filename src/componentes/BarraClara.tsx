import { useFocusEffect } from 'expo-router';
import { setStatusBarStyle } from 'expo-status-bar';
import { useCallback } from 'react';

/** Ícones claros na barra de status enquanto a tela (de cabeçalho colorido) estiver aberta; volta ao escuro ao sair. */
export function useBarraClara() {
  useFocusEffect(
    useCallback(() => {
      setStatusBarStyle('light');
      return () => setStatusBarStyle('dark');
    }, []),
  );
}
