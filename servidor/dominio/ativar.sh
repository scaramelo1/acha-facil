#!/bin/bash
# Liga o domínio achafacilapp.com.br: site no Apache, HTTPS (certbot) e redirecionamento do endereço antigo.
# Rodar como root na VPS, depois que o registro A do domínio apontar para esta VPS:  bash servidor/dominio/ativar.sh
set -e
cd "$(dirname "$0")"
IP=$(curl -s -4 ifconfig.me)
for h in achafacilapp.com.br www.achafacilapp.com.br; do
    d=$(dig +short "$h" A | tail -1)
    if [ "$d" != "$IP" ]; then echo "ERRO: $h aponta para '${d:-nada}', e a VPS é $IP. Acerte o DNS no Registro.br e espere propagar."; exit 1; fi
done
cp achafacilapp.conf /etc/apache2/sites-available/achafacilapp.conf
a2ensite -q achafacilapp
apache2ctl configtest && systemctl reload apache2
certbot --apache --non-interactive --redirect -d achafacilapp.com.br -d www.achafacilapp.com.br
code=$(curl -s -o /dev/null -w '%{http_code}' https://achafacilapp.com.br/)
if [ "$code" != "200" ]; then echo "ERRO: https://achafacilapp.com.br/ respondeu $code; redirecionamento NÃO ligado."; exit 1; fi
cp htaccess-redirecionar ../publico/.htaccess
echo "Pronto: https://achafacilapp.com.br no ar e fsadesigners.com.br/acha-facil redirecionando (menos api/ e mp-webhook.php)."
