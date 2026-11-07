// Links que abrem o app por fora (ex.: a volta do Google: achafacil:///google?codigo=...).
// O roteador não reconhecia esse endereço com três barras; aqui ele vira a rota /google com o código.
export function redirectSystemPath({ path }: { path: string; initial: boolean }) {
  try {
    const semEsquema = path.replace(/^[a-z][a-z0-9+.-]*:\/*/i, '/').replace(/^\/--\//, '/');
    const [caminho, busca = ''] = semEsquema.split('?');
    if (/(^|\/)google\/?$/.test(caminho)) return `/google${busca ? `?${busca}` : ''}`;
    return path;
  } catch {
    return '/';
  }
}
