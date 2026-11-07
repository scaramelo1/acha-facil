<?php
// Base dos endereços JSON usados pelo app. O app manda o token da conta no cabeçalho X-Token.
declare(strict_types=1);
require __DIR__ . '/base.php';
require __DIR__ . '/guia.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Token');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

set_exception_handler(function (Throwable $e) {
    error_log('Acha Fácil API: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    responder(['erro' => 'Algo deu errado no servidor. Tente de novo em instantes.'], 500);
});

function responder(array $dados, int $status = 200): never {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function erro(string $msg, int $status = 400): never { responder(['erro' => $msg], $status); }
function metodo(string ...$aceitos): string {
    $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($m, $aceitos, true)) erro('Método não permitido.', 405);
    return $m;
}
function corpo(): array {
    static $c = null;
    return $c ??= (json_decode(file_get_contents('php://input') ?: '', true) ?: []);
}
function campo(string $nome, int $max = 255): string { return mb_substr(trim((string)(corpo()[$nome] ?? '')), 0, $max); }

// ---------- contas do app ----------
function token_hash(string $t): string { return hash('sha256', $t); }
function criar_sessao(int $usuario_id): string {
    $t = bin2hex(random_bytes(32));
    q('INSERT INTO sessoes_app (token_hash, usuario_id) VALUES (?, ?)', [token_hash($t), $usuario_id]);
    return $t;
}
function usuario_app(): ?array {
    static $u = false;
    if ($u !== false) return $u;
    $t = (string)($_SERVER['HTTP_X_TOKEN'] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $t)) return $u = null;
    $u = linha('SELECT u.* FROM sessoes_app s JOIN usuarios u ON u.id = s.usuario_id AND u.ativo = 1 WHERE s.token_hash = ?', [token_hash($t)]);
    if ($u) q('UPDATE sessoes_app SET usado_em = NOW() WHERE token_hash = ? AND usado_em < NOW() - INTERVAL 1 HOUR', [token_hash($t)]);
    return $u;
}
function exigir_usuario(): array {
    return usuario_app() ?? erro('Entre na sua conta para continuar.', 401);
}
function usuario_publico(array $u): array {
    require_once __DIR__ . '/avisos.php';
    return ['id' => (int)$u['id'], 'nome' => $u['nome'], 'email' => $u['email'], 'foto' => $u['foto'] ?? null, 'admin' => admin_do_usuario($u) !== null];
}
