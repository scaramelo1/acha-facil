<?php
// O app abre esta página no navegador (endereço vindo de api/google.php, acao=iniciar); daqui vai para o Google.
require __DIR__ . '/../../inc/google.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
$estado = (string)($_GET['estado'] ?? '');
$l = preg_match('/^[a-f0-9]{64}$/', $estado)
    ? linha('SELECT 1 FROM logins_google WHERE estado = ? AND usuario_id IS NULL AND criado_em > NOW() - INTERVAL 10 MINUTE', [$estado])
    : null;
if (!$l || !google_configurado()) { pagina_google('Esta entrada expirou', 'Volte ao app e toque em "Entrar com o Google" de novo.', null); exit; }
header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => config()['google_client_id'],
    'redirect_uri' => google_retorno(),
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $estado,
    'prompt' => 'select_account',
]));
