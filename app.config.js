// Completa o app.json: o arquivo do Firebase (google-services.json) só entra se existir na raiz do projeto.
// Ele é necessário para os avisos no celular (push) do Android; sem ele o app funciona, só sem avisos.
const fs = require('fs');
const path = require('path');

// APP_LOJA=play só no perfil "production" do eas.json (build .aab da Play Store). Sem ela, é o APK do site.
// O app usa isso para não mandar baixar o APK do site quando foi instalado pela Play (regra da loja).
module.exports = ({ config }) => {
  const loja = process.env.APP_LOJA === 'play' ? 'play' : 'site';
  const comLoja = { ...config, extra: { ...config.extra, loja } };
  const firebase = path.join(__dirname, 'google-services.json');
  if (!fs.existsSync(firebase)) return comLoja;
  return { ...comLoja, android: { ...comLoja.android, googleServicesFile: './google-services.json' } };
};
