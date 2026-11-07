<?php
// Entrar no app com o Google (OAuth do tipo "aplicativo da Web"). As chaves ficam em /etc/acha-facil/config.php:
// google_client_id e google_client_secret. O endereço de retorno cadastrado no Google é url_publica('google/retorno.php').
declare(strict_types=1);
require_once __DIR__ . '/base.php';

function google_configurado(): bool {
    return (config()['google_client_id'] ?? '') !== '' && (config()['google_client_secret'] ?? '') !== '';
}
function google_retorno(): string { return url_publica('google/retorno.php'); }

/** Só volta para o próprio app: achafacil://... (APK) ou exp://... (Expo Go, nos testes). */
function volta_valida(string $v): bool {
    return strlen($v) <= 300 && (bool)preg_match('#^(achafacil|exp)://[^\s"<>]*$#', $v);
}

/** Endereço do app com ?codigo= ou ?erro= no fim. */
function volta_com(string $volta, array $params): string {
    return $volta . (str_contains($volta, '?') ? '&' : '?') . http_build_query($params);
}

/** POST e lê JSON (troca do código do Google pelo token, dados da pessoa). */
function google_http(string $url, ?array $post = null, ?string $bearer = null): array {
    $c = curl_init($url);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8]);
    if ($post !== null) { curl_setopt($c, CURLOPT_POST, true); curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($post)); }
    if ($bearer !== null) curl_setopt($c, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $bearer]);
    $r = curl_exec($c);
    $st = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);
    $j = is_string($r) ? json_decode($r, true) : null;
    if ($st !== 200 || !is_array($j)) throw new RuntimeException("Google respondeu $st");
    return $j;
}

/** Acha (pelo id do Google ou pelo e-mail) ou cria a conta; nome e foto sempre atualizados com os do Google. */
function conta_do_google(array $p): int {
    $gid = (string)$p['sub'];
    $email = mb_strtolower((string)$p['email']);
    $nome = mb_substr(trim((string)($p['name'] ?? '')) ?: explode('@', $email)[0], 0, 80);
    $foto = isset($p['picture']) && str_starts_with((string)$p['picture'], 'https://') ? mb_substr((string)$p['picture'], 0, 500) : null;
    $u = linha('SELECT id, ativo FROM usuarios WHERE google_id = ?', [$gid]) ?? linha('SELECT id, ativo FROM usuarios WHERE email = ?', [$email]);
    if ($u) {
        if (!(int)$u['ativo']) throw new RuntimeException('conta desativada');
        q('UPDATE usuarios SET google_id = ?, email = ?, nome = ?, foto = ? WHERE id = ?', [$gid, $email, $nome, $foto, $u['id']]);
        return (int)$u['id'];
    }
    return inserir('usuarios', ['nome' => $nome, 'email' => $email, 'google_id' => $gid, 'foto' => $foto]);
}

/**
 * Página simples no navegador (fim da entrada ou erro). Não abre link para o app de propósito: no celular, outro app
 * instalado com o mesmo esquema (um APK antigo, por exemplo) pegava o link e mostrava "Unmatched Route". A pessoa
 * volta pelo X do navegador ou pelo botão voltar, e o app confere a entrada no servidor ao voltar para a frente.
 */
function pagina_google(string $titulo, string $texto): void {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>' . h($titulo) . ' · Acha Fácil</title><style>'
        . 'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#073D25;font-family:system-ui,sans-serif;color:#fff;text-align:center;padding:24px;box-sizing:border-box}'
        . 'h1{font-size:28px;margin:0 0 10px}p{font-size:18px;margin:0 0 24px;color:#E6F3EA}'
        . 'a{display:inline-block;background:#F7C600;color:#005C38;font-weight:800;font-size:18px;text-decoration:none;padding:16px 28px;border-radius:30px}'
        . '.marca{font-style:italic;font-weight:900;font-size:22px;margin-bottom:28px}'
        . '.volta{background:#F7C600;color:#005C38;font-weight:700;font-size:18px;padding:16px 20px;border-radius:18px;line-height:1.4}</style></head><body><div>'
        . '<div class="marca">Acha Fácil!</div><h1>' . h($titulo) . '</h1><p>' . h($texto) . '</p>'
        . '<div class="volta">Toque no <b>X</b> lá em cima ou no botão <b>voltar</b> do celular.</div>'
        . '</div></body></html>';
}
