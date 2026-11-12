// Build no PC (scripts/gerar-local.ps1), sem gastar a cota do EAS: assina a versao final (release) com a MESMA chave
// que o EAS usa, baixada uma vez com `npx eas credentials -p android` (Download -> credentials.json na raiz do
// projeto + credentials/android/keystore.jks; os dois ficam fora do git). Com a mesma chave, a Play aceita o .aab
// como atualizacao e o APK do site instala por cima do anterior.
// Sem o credentials.json (ex.: build na nuvem do EAS) este plugin nao muda nada.
const fs = require('fs');
const path = require('path');
const { withAppBuildGradle } = require('expo/config-plugins');

function lerCredenciais(raiz) {
  const arq = path.join(raiz, 'credentials.json');
  if (!fs.existsSync(arq)) return null;
  const k = JSON.parse(fs.readFileSync(arq, 'utf8'))?.android?.keystore;
  if (!k?.keystorePath || !k.keystorePassword || !k.keyAlias || !k.keyPassword) {
    throw new Error('credentials.json sem android.keystore completo. Baixe de novo com: npx eas credentials -p android');
  }
  const loja = path.resolve(raiz, k.keystorePath);
  if (!fs.existsSync(loja)) throw new Error(`Chave nao encontrada em ${loja}. Baixe de novo com: npx eas credentials -p android`);
  return { ...k, keystorePath: loja };
}

// texto entre aspas simples do Groovy (barra invertida e aspa escapadas)
const g = (s) => `'${String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;

module.exports = function assinaturaLocal(config) {
  return withAppBuildGradle(config, (cfg) => {
    const k = lerCredenciais(cfg.modRequest.projectRoot);
    if (!k) return cfg;
    let s = cfg.modResults.contents;
    if (s.includes('signingConfigs.release')) return cfg; // ja aplicado
    const bloco = [
      '        release {',
      `            storeFile file(${g(k.keystorePath.replace(/\\/g, '/'))})`,
      `            storePassword ${g(k.keystorePassword)}`,
      `            keyAlias ${g(k.keyAlias)}`,
      `            keyPassword ${g(k.keyPassword)}`,
      '        }',
    ].join('\n');
    if (!/signingConfigs\s*\{/.test(s)) throw new Error('assinatura-local: nao achei signingConfigs no android/app/build.gradle');
    s = s.replace(/signingConfigs\s*\{/, (m) => `${m}\n${bloco}`);
    // dentro de buildTypes > release: troca a chave de teste (debug) pela release
    const tipos = s.indexOf('buildTypes');
    const rel = tipos >= 0 ? s.indexOf('release {', tipos) : -1;
    const deb = rel >= 0 ? s.indexOf('signingConfig signingConfigs.debug', rel) : -1;
    if (deb < 0) throw new Error('assinatura-local: nao achei o signingConfig do release no android/app/build.gradle');
    s = s.slice(0, deb) + 'signingConfig signingConfigs.release' + s.slice(deb + 'signingConfig signingConfigs.debug'.length);
    cfg.modResults.contents = s;
    return cfg;
  });
};
