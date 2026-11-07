import { useRouter } from 'expo-router';
import { useEffect } from 'react';

import { Carregando } from '../componentes/ui';

/**
 * Endereço que o app não conhece (ex.: um link de volta de outro sistema): em vez da página "Unmatched Route",
 * volta para a tela anterior (ou para o Início). Se era a volta do Google, a conta entra pela conferência de
 * src/conta.tsx quando o app volta para a frente.
 */
export default function NaoEncontrada() {
  const router = useRouter();
  useEffect(() => {
    const t = setTimeout(() => (router.canGoBack() ? router.back() : router.replace('/')), 0);
    return () => clearTimeout(t);
  }, [router]);
  return <Carregando />;
}
