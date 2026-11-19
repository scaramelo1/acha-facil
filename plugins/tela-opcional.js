// O app.json fixa a tela em pe ("orientation": "portrait"). O Android entende isso como se o aparelho PRECISASSE ter
// tela em pe (android.hardware.screen.portrait obrigatorio) e a Play avisa que isso tira o app de alguns tablets,
// Chromebooks e Android XR (19/11, versao 1.10.0). Aqui o recurso vira opcional: o app continua em pe, mas instala
// em qualquer aparelho.
const { withAndroidManifest } = require('expo/config-plugins');

const RECURSOS = ['android.hardware.screen.portrait', 'android.hardware.screen.landscape'];

module.exports = function telaOpcional(config) {
  return withAndroidManifest(config, (cfg) => {
    const manifesto = cfg.modResults.manifest;
    const lista = (manifesto['uses-feature'] = manifesto['uses-feature'] ?? []);
    for (const nome of RECURSOS) {
      const existente = lista.find((f) => f.$?.['android:name'] === nome);
      if (existente) existente.$['android:required'] = 'false';
      else lista.push({ $: { 'android:name': nome, 'android:required': 'false' } });
    }
    return cfg;
  });
};
