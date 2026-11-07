import { useRouter } from 'expo-router';
import { useEffect } from 'react';

import { Carregando } from '../componentes/ui';

/**
 * Volta do Google (achafacil://google?codigo=...). Quem troca o código pela sessão é a tela Entrar
 * (src/conta.tsx, entrarComGoogle); no Android o link também abre esta rota, que só sai de cena.
 */
export default function RetornoGoogle() {
  const router = useRouter();
  useEffect(() => {
    const t = setTimeout(() => (router.canGoBack() ? router.back() : router.replace('/')), 0);
    return () => clearTimeout(t);
  }, [router]);
  return <Carregando />;
}
