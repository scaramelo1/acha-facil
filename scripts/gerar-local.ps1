# Gera o app no PC (Windows), sem usar a cota de builds do EAS.
#   .\scripts\gerar-local.ps1 -Tipo play    -> .aab para enviar no Play Console
#   .\scripts\gerar-local.ps1 -Tipo apk     -> .apk para o botao "Baixar APK" do site
#   (opcional) -VersionCode 25              -> numero da versao no Android; sem ele, pega o ultimo do EAS + 1
# Precisa (uma vez): Android Studio instalado (Java e SDK) e a chave do EAS baixada com
#   npx eas credentials -p android  ->  production -> credentials.json: Upload/Download -> Download credentials from EAS to credentials.json
# O arquivo pronto vai para a pasta saida\. Explicado em docs/build-local.md.
param(
  [Parameter(Mandatory = $true)][ValidateSet('play', 'apk')][string]$Tipo,
  [int]$VersionCode = 0
)
$ErrorActionPreference = 'Continue'
$raiz = Split-Path -Parent $PSScriptRoot
Set-Location $raiz

function Parar([string]$msg) { Write-Host ''; Write-Host "ERRO: $msg" -ForegroundColor Red; exit 1 }

# 1. chave do EAS
if (-not (Test-Path 'credentials.json')) {
  Parar "Falta a chave do EAS. Rode: npx eas credentials -p android  (escolha production -> credentials.json -> Download credentials from EAS to credentials.json) e rode este script de novo."
}
if (-not (Test-Path 'node_modules')) { Write-Host 'Instalando as bibliotecas (npm install)...'; npm install; if ($LASTEXITCODE -ne 0) { Parar 'npm install falhou.' } }

# 2. Java e SDK do Android Studio
if (-not $env:JAVA_HOME) {
  foreach ($j in @("$env:ProgramFiles\Android\Android Studio\jbr", "$env:LOCALAPPDATA\Programs\Android Studio\jbr", "$env:ProgramFiles\Android\Android Studio\jre")) {
    if (Test-Path "$j\bin\java.exe") { $env:JAVA_HOME = $j; break }
  }
}
if (-not $env:JAVA_HOME) { Parar 'Nao achei o Java do Android Studio. Defina JAVA_HOME com a pasta jbr do Android Studio.' }
if (-not $env:ANDROID_HOME) {
  $sdk = "$env:LOCALAPPDATA\Android\Sdk"
  if (Test-Path $sdk) { $env:ANDROID_HOME = $sdk } else { Parar 'Nao achei o SDK do Android. Abra o Android Studio > SDK Manager e confira a pasta (defina ANDROID_HOME).' }
}
Write-Host "Java: $env:JAVA_HOME"
Write-Host "SDK:  $env:ANDROID_HOME"

# 3. numero da versao (versionCode): a Play so aceita numero maior que o do ultimo envio
if ($VersionCode -le 0) {
  Write-Host 'Perguntando ao EAS o ultimo numero de versao (nao gasta build)...'
  $json = (npx eas build:version:get -p android -e production --json 2>$null) | Out-String
  $atual = 0
  try { $atual = [int](($json | ConvertFrom-Json).versionCode) } catch { $atual = 0 }
  if ($atual -le 0) { Parar "Nao consegui ler o numero do EAS. Veja o 'Version code' do ultimo envio no Play Console e rode de novo com -VersionCode (esse numero + 1)." }
  $VersionCode = $atual + 1
}
$versao = (Get-Content 'app.json' -Raw | ConvertFrom-Json).expo.version
Write-Host "Versao $versao, numero $VersionCode, tipo $Tipo"

# 4. projeto Android do zero, assinado com a chave do EAS (plugins/assinatura-local.js)
if ($Tipo -eq 'play') { $env:APP_LOJA = 'play' } else { Remove-Item Env:APP_LOJA -ErrorAction SilentlyContinue }
$env:ACHAFACIL_VERSION_CODE = "$VersionCode"
$env:EXPO_NO_GIT_STATUS = '1'
npx expo prebuild -p android --clean --no-install
if ($LASTEXITCODE -ne 0) { Parar 'expo prebuild falhou (veja a mensagem acima).' }

# 5. compila (a primeira vez demora: o Gradle baixa muita coisa)
Push-Location android
if ($Tipo -eq 'play') { .\gradlew.bat bundleRelease } else { .\gradlew.bat assembleRelease }
$codigo = $LASTEXITCODE
Pop-Location
if ($codigo -ne 0) { Parar 'O Gradle falhou (veja a mensagem acima).' }

# 6. copia para saida\
New-Item -ItemType Directory -Force -Path 'saida' | Out-Null
if ($Tipo -eq 'play') {
  $origem = 'android\app\build\outputs\bundle\release\app-release.aab'
  $destino = "saida\acha-facil-$versao-$VersionCode.aab"
} else {
  $origem = 'android\app\build\outputs\apk\release\app-release.apk'
  $destino = "saida\acha-facil-$versao.apk"
}
if (-not (Test-Path $origem)) { Parar "Nao achei $origem." }
Copy-Item $origem $destino -Force
$mb = [math]::Round((Get-Item $destino).Length / 1MB)

Write-Host ''
Write-Host "PRONTO: $destino ($mb MB)" -ForegroundColor Green
if ($Tipo -eq 'play') {
  Write-Host 'Envie no Play Console: Testar e lancar > (Producao ou o teste) > Criar nova versao > enviar o .aab.'
  Write-Host "Depois, para o EAS continuar a contagem certa, rode: npx eas build:version:set -p android   e digite $VersionCode"
} else {
  Write-Host 'Mande para a VPS e publique:'
  Write-Host "  scp $destino root@achafacilapp.com.br:/root/"
  Write-Host "  (na VPS) bash /var/www/acha-facil/servidor/publicar-apk.sh /root/acha-facil-$versao.apk"
}
