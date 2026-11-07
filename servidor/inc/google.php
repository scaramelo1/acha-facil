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
