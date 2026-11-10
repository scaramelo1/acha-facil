#!/bin/bash
# Publica um APK novo do Acha Fácil no site (botão "Baixar APK" de https://achafacilapp.com.br/).
# Rodar como root na VPS. Aceita o arquivo .apk ou o link que o EAS mostra no fim do build:
#   bash servidor/publicar-apk.sh https://expo.dev/artifacts/eas/XXXX.apk
#   bash servidor/publicar-apk.sh /root/acha-facil.apk
# O total de downloads nunca é zerado. A versão vem do app.json (ou do 2º argumento).
set -euo pipefail

RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
DADOS=$(php -r 'echo rtrim((require "/etc/acha-facil/config.php")["dados_dir"] ?? "/var/lib/acha-facil", "/");')
DIR="$DADOS/apk"
ORIGEM="${1:-}"
VERSAO="${2:-$(php -r 'echo json_decode(file_get_contents($argv[1]), true)["expo"]["version"] ?? "";' "$RAIZ/app.json")}"

if [ -z "$ORIGEM" ]; then
  echo "Uso: bash servidor/publicar-apk.sh <arquivo.apk | link do EAS> [versão]"
  exit 1
fi

# link da página do build (não é o APK): explica como achar o link do arquivo
if [[ "$ORIGEM" =~ expo\.dev/accounts/.*/builds/([0-9a-f-]+) ]]; then
  echo "Esse é o link da PÁGINA do build, não do APK. No PC, rode:"
  echo "  npx eas build:view ${BASH_REMATCH[1]}"
  echo "e use o link da linha 'Application Archive URL' (https://expo.dev/artifacts/eas/....apk)."
  exit 1
fi

mkdir -p "$DIR"
NOVO="$DIR/acha-facil.apk.novo"
if [[ "$ORIGEM" =~ ^https:// ]]; then
  echo "Baixando o APK..."
  curl -fsSL --retry 3 -m 900 -o "$NOVO" "$ORIGEM"
else
  cp "$ORIGEM" "$NOVO"
fi

# Confere se é mesmo um APK (arquivo zip com AndroidManifest.xml) antes de trocar
# (lista guardada numa variável: "unzip | grep -q" falha com pipefail em APK grande, porque o grep para no meio)
LISTA=$(unzip -Z1 "$NOVO" 2>/dev/null || true)
if [ "$(head -c 2 "$NOVO")" != "PK" ] || ! grep -qx 'AndroidManifest.xml' <<< "$LISTA"; then
  rm -f "$NOVO"
  echo "Falhou: o arquivo não é um APK. Nada foi trocado."
  exit 1
fi

# a versão vem de dentro do próprio APK (a que o celular vai ter); se não achar, a do app.json ou do 2º argumento.
# É ela que o app compara para avisar "Nova versão" (api/versao.php).
DO_APK=$(unzip -p "$NOVO" assets/app.config 2>/dev/null | grep -o '"version":"[0-9.]*"' | head -1 | cut -d'"' -f4 || true)
if [ -z "${2:-}" ] && [ -n "$DO_APK" ]; then VERSAO="$DO_APK"; fi

mv "$NOVO" "$DIR/acha-facil.apk"          # troca de uma vez: ninguém baixa pela metade
echo "$VERSAO" > "$DIR/versao.txt"
[ -f "$DIR/downloads.txt" ] || echo 0 > "$DIR/downloads.txt"
chown -R www-data:www-data "$DIR"
chmod 750 "$DIR"

echo "APK:        $(du -h "$DIR/acha-facil.apk" | cut -f1), versão ${VERSAO:-?}"
echo "Downloads:  $(cat "$DIR/downloads.txt") até agora"
echo "Página:     $(curl -s -o /dev/null -w '%{http_code}' -m 10 https://achafacilapp.com.br/)"
echo "Baixar:     $(curl -s -I -o /dev/null -w '%{http_code} %{content_type}' -m 10 https://achafacilapp.com.br/baixar-apk.php)"
echo "Link:       https://achafacilapp.com.br/baixar-apk.php"
echo "Aviso app:  $(curl -s -m 10 https://achafacilapp.com.br/api/versao.php)   (quem tem versão mais velha recebe o aviso)"
