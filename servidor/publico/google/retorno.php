<?php
// O Google volta aqui com ?code=&state=. Troca o código, pega nome, e-mail e foto, acha ou cria a conta e grava
// na entrada; o app, que está perguntando ao servidor (api/google.php, acao=verificar), recebe a sessão.
// A página ainda tenta voltar para o app sozinha (só para fechar o navegador mais rápido).
require __DIR__ . '/../../inc/google.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
$estado = (string)($_GET['state'] ?? '');
$l = preg_match('/^[a-f0-9]{64}$/', $estado)
    ? linha('SELECT * FROM logins_google WHERE estado = ? AND usuario_id IS NULL AND criado_em > NOW() - INTERVAL 10 MINUTE', [$estado])
    : null;
if (!$l) { pagina_google('Esta entrada expirou', 'Volte ao app e toque em "Entrar com o Google" de novo.', null); exit; }
$volta = $l['volta'];
$falhar = function (string $msg) use ($volta, $estado): never {
    q('DELETE FROM logins_google WHERE estado = ?', [$estado]);   // o app recebe "não deu certo" e mostra o aviso
    pagina_google('Não foi possível entrar', $msg, $volta);
    exit;
};
if (isset($_GET['error'])) $falhar('Você cancelou a entrada com o Google.');
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

q('UPDATE logins_google SET usuario_id = ?, criado_em = NOW() WHERE estado = ?', [$uid, $estado]);
pagina_google('Pronto, você entrou!', 'Voltando para o Acha Fácil...', $volta, true);
