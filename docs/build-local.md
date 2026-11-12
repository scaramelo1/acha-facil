# Gerar o app no PC (sem a cota do EAS)

Quando a cota grátis de builds do EAS acaba, o `.aab` (Play Store) e o `.apk` (site) saem do próprio PC com o
Android Studio. O arquivo é assinado com a **mesma chave** do EAS: a Play aceita como atualização e o APK do site
instala por cima do anterior.

## Uma vez só

Projeto no PC em `D:\acha-facil` (desde 12/11; antes em C:\Users\Fellipe\acha-facil) e cache do Gradle em
`D:\gradle` (variável `GRADLE_USER_HOME`), para não lotar o C:.


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

3. **Caminhos longos** (o C++ do react-native-gesture-handler passa de 260 letras no caminho; com o padrão do Windows
   e o CMake 3.22.1 do SDK dá "Filename longer than 260 characters"):
   - PowerShell **como administrador**:
     `New-ItemProperty -Path 'HKLM:\SYSTEM\CurrentControlSet\Control\FileSystem' -Name LongPathsEnabled -Value 1 -PropertyType DWORD -Force`
     e reiniciar o PC;
   - Android Studio → Settings → Languages & Frameworks → Android SDK → aba SDK Tools → "Show Package Details" →
     em **CMake** marcar a versão mais nova (3.31.x ou 4.x) → Apply. O script usa sozinho o CMake mais novo instalado
     (`cmake.dir` no android\local.properties) e para com a explicação se faltar alguma das duas coisas.

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

## Se o Gradle falhar

O script já compila só para celulares de verdade (armeabi-v7a e arm64-v8a; sem x86 de emulador), com mais memória
para o Gradle/Kotlin e 2 tarefas por vez, e guarda tudo em `saida\build-log.txt` (no erro, mostra as primeiras linhas).
"Internal compiler error" do Kotlin + C++ (ninja) + "Could not add entry ... executionHistory" ao mesmo tempo = quase
sempre falta de memória/espaço ou antivírus: feche o emulador, o Android Studio e o navegador durante o build, deixe
uns 15 GB livres no disco e, se continuar, ponha a pasta do projeto (`D:\acha-facil`) e a do cache do Gradle (`D:\gradle`, ou `C:\Users\Fellipe\.gradle`)
nas exclusões do Windows Defender.
