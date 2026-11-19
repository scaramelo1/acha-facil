<?php
// Conta no site (passo 44): a mesma conta do app (entrar com o Google), guardada num cookie.
// O cookie af_conta leva um token de sessoes_app (o mesmo tipo de sessão do app): só o servidor lê (httponly).
// af_logado=1 (sem nada secreto) só avisa o JavaScript do topo das páginas que vale a pena perguntar quem entrou.
// As ações (salvar, avaliar) usam os mesmos endereços do app (api/), com o cabeçalho X-AF-Web: 1 (ver usuario_app()).
declare(strict_types=1);
require_once __DIR__ . '/base.php';

const CONTA_COOKIE = 'af_conta';
const CONTA_DIAS = 180;

function conta_cookie(string $nome, string $valor, int $expira, bool $httponly = true): void {
    setcookie($nome, $valor, ['expires' => $expira, 'path' => '/', 'secure' => !empty(config()['https']),
        'httponly' => $httponly, 'samesite' => 'Lax']);
}

/** Pessoa que entrou no site (ou null). */
function usuario_web(): ?array {
    static $u = false;
    if ($u !== false) return $u;
    $t = (string)($_COOKIE[CONTA_COOKIE] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $t)) return $u = null;
    $h = hash('sha256', $t);
    $u = linha('SELECT u.* FROM sessoes_app s JOIN usuarios u ON u.id = s.usuario_id AND u.ativo = 1 WHERE s.token_hash = ?', [$h]);
    if ($u) q('UPDATE sessoes_app SET usado_em = NOW() WHERE token_hash = ? AND usado_em < NOW() - INTERVAL 1 HOUR', [$h]);
    return $u;
}

/** Depois do Google: cria a sessão e grava os cookies. */
function entrar_web(int $usuario_id): void {
    $t = bin2hex(random_bytes(32));
    q('INSERT INTO sessoes_app (token_hash, usuario_id) VALUES (?, ?)', [hash('sha256', $t), $usuario_id]);
    $ate = time() + CONTA_DIAS * 86400;
    conta_cookie(CONTA_COOKIE, $t, $ate);
    conta_cookie('af_logado', '1', $ate, false);
}

function sair_web(): void {
    $t = (string)($_COOKIE[CONTA_COOKIE] ?? '');
    if (preg_match('/^[a-f0-9]{64}$/', $t)) q('DELETE FROM sessoes_app WHERE token_hash = ?', [hash('sha256', $t)]);
    conta_cookie(CONTA_COOKIE, '', time() - 3600);
    conta_cookie('af_logado', '', time() - 3600, false);
}

/** Só volta para uma página do próprio site ("/..."), nunca para outro endereço. */
function volta_web(?string $v): string {
    $v = (string)$v;
    return strlen($v) <= 280 && preg_match('#^/(?![/\\\\])[^\s"<>]*$#', $v) && !preg_match('#^/(entrar|sair|google/)#', $v) ? $v : '/';
}

/** Link "Entrar com o Google" que volta para $volta. */
function link_entrar(string $volta): string {
    return url('entrar.php?volta=' . rawurlencode(volta_web($volta)));
}

/** Botão do Google (usado na ficha e na página de entrar). */
function botao_google(string $volta, string $texto = 'Entrar com o Google'): string {
    return '<a class="botao botao-google" href="' . h(link_entrar($volta)) . '" rel="nofollow">'
        . '<svg viewBox="0 0 48 48" width="22" height="22" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>'
        . '<span>' . h($texto) . '</span></a>';
}

/**
 * Pedidos de donos (assumir, plano, incluir negócio): só com a conta Google do site, para a pessoa receber depois o aviso
 * de "aceito" no celular (pedidos.usuario_id). Sem conta, mostra a página "Entre com o Google" e para aqui.
 * Precisa de inc/pagina.php já carregado.
 */
function exigir_login_web(string $titulo, string $explicacao): array {
    if ($u = usuario_web()) return $u;
    $volta = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $base = config()['url'];
    if ($base !== '' && str_starts_with($volta, $base)) $volta = substr($volta, strlen($base)) ?: '/';
    pagina_inicio("$titulo | Acha Fácil!", 'Entre com o Google para continuar.', '', ['noindex' => true]);
    echo '<main class="documento"><h1>' . h($titulo) . '</h1>'
        . '<p>' . h($explicacao) . '</p>'
        . '<p>Para continuar, <strong>entre com a sua conta Google</strong>. Assim avisamos você no celular quando o pedido for aceito.</p>'
        . '<p><a class="botao" href="' . h(url('entrar.php?volta=' . rawurlencode($volta))) . '">Entrar com o Google</a></p>'
        . '<p class="discreto">Usamos só o seu nome, a foto e o e-mail. Você pode sair quando quiser.</p></main>';
    pagina_fim();
    exit;
}
