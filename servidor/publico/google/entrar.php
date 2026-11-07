<?php
// O app abre esta página (no navegador do celular) com ?volta=achafacil://google; daqui vai para o Google.
require __DIR__ . '/../../inc/google.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
$volta = (string)($_GET['volta'] ?? '');
if (!volta_valida($volta)) { http_response_code(400); exit('Endereço de volta inválido.'); }
if (!google_configurado()) { header('Location: ' . volta_com($volta, ['erro' => 'O login com o Google ainda não foi ligado no servidor.'])); exit; }

q('DELETE FROM logins_google WHERE criado_em < NOW() - INTERVAL 1 HOUR');
$estado = bin2hex(random_bytes(32));
q('INSERT INTO logins_google (estado, volta) VALUES (?, ?)', [$estado, $volta]);
header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => config()['google_client_id'],
    'redirect_uri' => google_retorno(),
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $estado,
    'prompt' => 'select_account',
]));
