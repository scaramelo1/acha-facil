# Gerar o app no PC (sem a cota do EAS)

Quando a cota grátis de builds do EAS acaba, o `.aab` (Play Store) e o `.apk` (site) saem do próprio PC com o
Android Studio. O arquivo é assinado com a **mesma chave** do EAS: a Play aceita como atualização e o APK do site
instala por cima do anterior.

## Uma vez só

1. Android Studio instalado (o mesmo do emulador). O script acha sozinho o Java (`jbr`) e o SDK
   (`%LOCALAPPDATA%\Android\Sdk`).
2. Baixar a chave do EAS (não gasta build):
   ```
   npx eas credentials -p android
   ```
   Escolha o perfil **production** → **credentials.json: Upload/Download credentials between EAS servers and your local json**
   → **Download credentials from EAS to credentials.json**. Cria `credentials.json` e `credentials\android\keystore.jks`
   na pasta do projeto. Os dois ficam **fora do git** (.gitignore) — guarde uma cópia em lugar seguro e nunca mande para
   ninguém.

## Cada versão nova

```
.\scripts\gerar-local.ps1 -Tipo play     # .aab para a Play Store
.\scripts\gerar-local.ps1 -Tipo apk      # .apk para o site
```

- O número da versão (versionCode) vem do EAS (+1). Se der erro ao ler, veja o "Version code" do último envio no Play
  Console e rode com `-VersionCode <esse número + 1>`.
- A primeira vez demora (o Gradle baixa muita coisa); depois fica mais rápido.
- O arquivo vai para `saida\`.
- `.aab`: Play Console → Testar e lançar → (Produção ou o teste) → Criar nova versão → enviar o arquivo. Depois rode
  `npx eas build:version:set -p android` e digite o mesmo número, para o EAS continuar a contagem quando voltar a usar.
- `.apk`: `scp saida\acha-facil-<versão>.apk root@achafacilapp.com.br:/root/` e, na VPS,
  `bash /var/www/acha-facil/servidor/publicar-apk.sh /root/acha-facil-<versão>.apk`.

Se o PowerShell bloquear o script ("execução de scripts foi desabilitada"), rode antes, na mesma janela:
`Set-ExecutionPolicy -Scope Process Bypass`.

## Como funciona

`scripts/gerar-local.ps1` roda `npx expo prebuild -p android --clean` (pasta `android\` nova, fora do git) com
`APP_LOJA=play` no tipo play (como o perfil production do eas.json) e `ACHAFACIL_VERSION_CODE`, que o app.config.js põe
em `android.versionCode`. O plugin `plugins/assinatura-local.js` troca a chave de teste do release pela do
credentials.json. Depois `gradlew bundleRelease` (.aab) ou `assembleRelease` (.apk). Sem o credentials.json (build na
nuvem do EAS), o plugin não muda nada.
