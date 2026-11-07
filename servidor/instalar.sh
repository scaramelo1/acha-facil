#!/usr/bin/env bash
# Acha Fácil — instalação/atualização na VPS (rodar como root). Pode rodar de novo sem estragar nada.
#   bash instalar.sh              cria/atualiza banco, config e publicação
#   bash instalar.sh --leads      também importa/atualiza os comércios das listas de leads (São João e Águas da Prata)
set -euo pipefail
cd "$(dirname "$0")"
RAIZ="$(pwd)"
NOME=acha-facil
BANCO=acha_facil
CONF_DIR=/etc/$NOME
CONF=$CONF_DIR/config.php
DADOS=/var/lib/$NOME
APACHE_SSL=/etc/apache2/sites-enabled/fsadesigners-le-ssl.conf
[ "$(id -u)" = 0 ] || { echo "Rode como root: sudo bash $0"; exit 1; }

# 1. banco e configuração (senha sorteada na primeira vez)
if [ ! -f "$CONF" ]; then
  SENHA="$(head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 28)"
  mysql <<SQL
CREATE DATABASE IF NOT EXISTS $BANCO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$BANCO'@'localhost' IDENTIFIED BY '$SENHA';
ALTER USER '$BANCO'@'localhost' IDENTIFIED BY '$SENHA';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES ON $BANCO.* TO '$BANCO'@'localhost';
FLUSH PRIVILEGES;
SQL
  mkdir -p "$CONF_DIR"
  cat > "$CONF" <<PHP
<?php
return [
    'db_host' => 'localhost',
    'db_nome' => '$BANCO',
    'db_usuario' => '$BANCO',
    'db_senha' => '$SENHA',
    'url' => '/$NOME',
    'https' => true,
    'endereco_publico' => 'https://fsadesigners.com.br/$NOME/',
    'dados_dir' => '$DADOS',
    // Mercado Pago (assinatura recorrente no cartão): 'desligado' | 'sandbox' | 'producao'
    'mp_modo' => 'desligado',
    // Página de divulgação, termos e privacidade (vazio = não aparece / "em breve")
    'contato_email' => '',
    'contato_whatsapp' => '',          // ex.: '19999999999'
    'instagram' => '',                 // ex.: 'achafacil'
    'link_play' => '',                 // link da Play Store quando publicar
    'link_app_store' => '',            // link da App Store quando publicar
    'mp_access_token' => '',
    'mp_webhook_secret' => '',
];
PHP
  chown root:www-data "$CONF_DIR" "$CONF"; chmod 750 "$CONF_DIR"; chmod 640 "$CONF"
  echo "✔ banco $BANCO e $CONF criados"
else
  echo "• $CONF já existe (mantido)"
fi

# 2. tabelas + mudanças numeradas (cada uma uma vez, com backup antes)
mkdir -p /var/backups/$NOME && chmod 700 /var/backups/$NOME
mysql $BANCO < sql/schema.sql
mysql $BANCO -e "CREATE TABLE IF NOT EXISTS migracoes (nome VARCHAR(100) PRIMARY KEY, aplicada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)"
for f in sql/migracoes/*.sql; do
  [ -e "$f" ] || continue
  n="$(basename "$f")"
  if [ -z "$(mysql -N $BANCO -e "SELECT 1 FROM migracoes WHERE nome = '$n'")" ]; then
    b="/var/backups/$NOME/antes-$n-$(date +%Y%m%d%H%M%S).sql.gz"
    mysqldump --single-transaction $BANCO | gzip > "$b"
    mysql $BANCO < "$f"
    mysql $BANCO -e "INSERT INTO migracoes (nome) VALUES ('$n')"
    echo "✔ mudança $n aplicada (backup em $b)"
  fi
done
mysql $BANCO < sql/categorias.sql   # depois das mudanças: usa colunas novas (cor)
echo "✔ tabelas conferidas"
mkdir -p $DADOS/fotos && chown -R www-data:www-data $DADOS && chmod 750 $DADOS $DADOS/fotos

# 3. arquivos e publicação em https://fsadesigners.com.br/acha-facil/
find "$RAIZ" -type d -exec chmod 755 {} +
find "$RAIZ" -type f -exec chmod 644 {} +
chmod 755 "$RAIZ/instalar.sh" "$RAIZ/publicar-apk.sh" "$RAIZ/importar-leads.php" "$RAIZ/geocodificar.php"
ln -sfn "$RAIZ/publico" /var/www/fsa-designer/$NOME
echo "✔ /var/www/fsa-designer/$NOME -> $RAIZ/publico"

# 4. Apache: sem marca d'água de protótipo (a API devolve JSON)
if grep -q '|leads/)\[^/\]+/' "$APACHE_SSL" && ! grep -q "$NOME/" "$APACHE_SSL"; then
  cp "$APACHE_SSL" "/root/fsadesigners-le-ssl.conf.antes-$NOME.$(date +%Y%m%d%H%M%S)"
  sed -i "s#|leads/)\[^/\]+/#|leads/|$NOME/)[^/]+/#" "$APACHE_SSL"
  if apache2ctl configtest; then systemctl reload apache2; echo "✔ Apache recarregado"
  else echo "✘ configtest falhou; voltando a cópia"; cp "$(ls -t /root/fsadesigners-le-ssl.conf.antes-$NOME.* | head -1)" "$APACHE_SSL"; exit 1; fi
else
  echo "• Apache já ajustado (ou padrão diferente; confira a LocationMatch em $APACHE_SSL)"
fi
if [ "${1:-}" = "--leads" ]; then
  php "$RAIZ/importar-leads.php"                                   # São João (lista de fsadesigners.com.br/leads)
  PRATA=$DADOS/leads/aguas-da-prata.html
  [ -f "$PRATA" ] && php "$RAIZ/importar-leads.php" "$PRATA" aguas-da-prata || echo "• sem lista de Águas da Prata em $PRATA"
fi
echo; echo "Pronto: https://fsadesigners.com.br/$NOME/api/inicio.php"
