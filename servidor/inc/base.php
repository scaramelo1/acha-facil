<?php
// Acha Fácil — funções comuns: configuração, banco, formatação, segurança
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');
mb_internal_encoding('UTF-8');

// /etc/acha-facil/config.php na VPS; config.local.php na pasta servidor/ para testes
function config(): array {
    static $c = null;
    if ($c !== null) return $c;
    $local = dirname(__DIR__) . '/config.local.php';
    $c = require (is_file($local) ? $local : '/etc/acha-facil/config.php');
    $c['url'] = rtrim($c['url'] ?? '/acha-facil', '/');
    $c['dados_dir'] = rtrim($c['dados_dir'] ?? '/var/lib/acha-facil', '/');
    return $c;
}
function url(string $caminho = ''): string { return config()['url'] . '/' . ltrim($caminho, '/'); }
function url_publica(string $caminho = ''): string {
    return rtrim((string)(config()['endereco_publico'] ?? 'https://fsadesigners.com.br/acha-facil/'), '/') . '/' . ltrim($caminho, '/');
}

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = config();
    $pdo = new PDO("mysql:host={$c['db_host']};dbname={$c['db_nome']};charset=utf8mb4", $c['db_usuario'], $c['db_senha'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '-03:00'");
    return $pdo;
}
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function linha(string $sql, array $p = []): ?array { return q($sql, $p)->fetch() ?: null; }
function linhas(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function valor(string $sql, array $p = []) { $v = q($sql, $p)->fetchColumn(); return $v === false ? null : $v; }
function inserir(string $tabela, array $dados): int {
    $cols = array_keys($dados);
    q("INSERT INTO $tabela (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($dados));
    return (int)db()->lastInsertId();
}
function atualizar(string $tabela, int $id, array $dados): void {
    $sets = implode(',', array_map(fn($c) => "$c = ?", array_keys($dados)));
    q("UPDATE $tabela SET $sets WHERE id = ?", [...array_values($dados), $id]);
}

// ---------- formatação ----------
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function so_digitos(?string $s): string { return preg_replace('/\D+/', '', (string)$s); }
function telefone_formatado(?string $t): string {
    $t = so_digitos($t);
    if (strlen($t) === 11) return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $t);
    if (strlen($t) === 10) return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $t);
    return $t;
}
// telefone digitado → só dígitos com DDD (10 ou 11); sem DDD assume 19 (São João da Boa Vista)
function telefone_normalizado(?string $t): ?string {
    $t = so_digitos($t);
    if (str_starts_with($t, '55') && strlen($t) >= 12) $t = substr($t, 2);
    if (strlen($t) === 8 || strlen($t) === 9) $t = '19' . $t;
    return (strlen($t) === 10 || strlen($t) === 11) ? $t : null;
}
function link_whatsapp(?string $t, string $msg = ''): ?string {
    $t = so_digitos($t);
    if (strlen($t) < 10) return null;
    return 'https://wa.me/55' . $t . ($msg !== '' ? '?text=' . rawurlencode($msg) : '');
}
function hoje(): string { return date('Y-m-d'); }
function data_br(?string $d): string { return $d ? date('d/m/Y', strtotime($d)) : '—'; }
function datahora_br(?string $d): string { return $d ? date('d/m/Y H:i', strtotime($d)) : '—'; }
function email_valido(?string $e): ?string {
    $e = mb_strtolower(trim((string)$e));
    return filter_var($e, FILTER_VALIDATE_EMAIL) && mb_strlen($e) <= 160 ? $e : null;
}

// ---------- segurança ----------
function ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '?'; }
function login_bloqueado(): bool {
    q('DELETE FROM tentativas_login WHERE quando < NOW() - INTERVAL 1 DAY');
    return (int)valor('SELECT COUNT(*) FROM tentativas_login WHERE ip = ? AND quando > NOW() - INTERVAL 15 MINUTE', [ip()]) >= 8;
}
function login_falhou(): void { q('INSERT INTO tentativas_login (ip, quando) VALUES (?, NOW())', [ip()]); usleep(400000); }

// sessão das páginas web (painel do profissional e administração)
function iniciar_sessao(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $c = config();
    session_name('ACHAFACIL');
    session_set_cookie_params(['lifetime' => 0, 'path' => $c['url'] . '/', 'secure' => !empty($c['https']), 'httponly' => true, 'samesite' => 'Lax']);
    ini_set('session.use_strict_mode', '1');
    session_start();
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}
function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function campo_csrf(): string { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
function e_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
function exigir_post(): void {
    if (!e_post() || !hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Sessão expirada. Volte, recarregue a página e tente novamente.');
    }
}
function flash(string $msg, string $tipo = 'ok'): void { $_SESSION['flash'][] = [$tipo, $msg]; }
function ir(string $caminho): never { header('Location: ' . (str_starts_with($caminho, 'http') ? $caminho : url($caminho))); exit; }
function txt(string $campo, int $max = 255): ?string {
    $v = trim((string)($_POST[$campo] ?? ''));
    return $v === '' ? null : mb_substr($v, 0, $max);
}
