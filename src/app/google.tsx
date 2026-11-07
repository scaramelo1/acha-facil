import { useRouter } from 'expo-router';
import { useEffect } from 'react';

import { Carregando } from '../componentes/ui';

/**
 * Volta do navegador depois do Google (achafacil:///google ou exp://.../--/google). Quem conclui o login é a
 * tela Entrar (src/conta.tsx pergunta ao servidor); esta rota só existe para o link não cair em "página não
 * encontrada" e sai de cena na hora.
 */
export default function RetornoGoogle() {
  const router = useRouter();
  useEffect(() => {
    const t = setTimeout(() => (router.canGoBack() ? router.back() : router.replace('/')), 0);
    return () => clearTimeout(t);
  }, [router]);
  return <Carregando />;
}
