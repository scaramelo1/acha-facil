<?php
// O Google volta aqui com ?code=&state=. Troca o código, pega nome, e-mail e foto, acha ou cria a conta
// e devolve ao app um código de uso único (o app troca por uma sessão em api/google.php).
require __DIR__ . '/../../inc/google.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
$estado = (string)($_GET['state'] ?? '');
$l = preg_match('/^[a-f0-9]{64}$/', $estado)
    ? linha('SELECT * FROM logins_google WHERE estado = ? AND codigo_hash IS NULL AND criado_em > NOW() - INTERVAL 10 MINUTE', [$estado])
    : null;
if (!$l) { http_response_code(400); exit('Esta entrada expirou. Volte ao app e toque em "Entrar com o Google" de novo.'); }
$volta = $l['volta'];
$falhar = function (string $msg) use ($volta, $estado): never {
    q('DELETE FROM logins_google WHERE estado = ?', [$estado]);
    header('Location: ' . volta_com($volta, ['erro' => $msg]));
    exit;
};
if (isset($_GET['error'])) $falhar('Entrada cancelada.');
$code = (string)($_GET['code'] ?? '');
if ($code === '') $falhar('O Google não confirmou a entrada.');

try {
    $tok = google_http('https://oauth2.googleapis.com/token', [
        'code' => $code,
        'client_id' => config()['google_client_id'],
        'client_secret' => config()['google_client_secret'],
        'redirect_uri' => google_retorno(),
        'grant_type' => 'authorization_code',
    ]);
    $p = google_http('https://openidconnect.googleapis.com/v1/userinfo', null, (string)($tok['access_token'] ?? ''));
    if (empty($p['sub']) || empty($p['email']) || empty($p['email_verified'])) $falhar('Sua conta Google não tem um e-mail confirmado.');
    $uid = conta_do_google($p);
} catch (Throwable $e) {
    error_log('acha-facil google: ' . $e->getMessage());
    $falhar('Não foi possível entrar com o Google agora. Tente de novo.');
}

$codigo = bin2hex(random_bytes(32));
q('UPDATE logins_google SET codigo_hash = ?, usuario_id = ?, criado_em = NOW() WHERE estado = ?', [hash('sha256', $codigo), $uid, $estado]);
header('Location: ' . volta_com($volta, ['codigo' => $codigo]));
