// Completa o app.json: o arquivo do Firebase (google-services.json) só entra se existir na raiz do projeto.
// Ele é necessário para os avisos no celular (push) do Android; sem ele o app funciona, só sem avisos.
const fs = require('fs');
const path = require('path');

module.exports = ({ config }) => {
  const firebase = path.join(__dirname, 'google-services.json');
  if (!fs.existsSync(firebase)) return config;
  return { ...config, android: { ...config.android, googleServicesFile: './google-services.json' } };
};
