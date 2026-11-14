<?php
// APK do app para baixar direto do site (enquanto não está nas lojas, e depois como alternativa).
// Fica fora da pasta pública: dados_dir/apk/acha-facil.apk, publicado por servidor/publicar-apk.sh.
// downloads.txt guarda o total (nunca é zerado ao trocar o APK); versao.txt a versão do app.
declare(strict_types=1);

function apk_dir(): string { return config()['dados_dir'] . '/apk'; }
function apk_arquivo(): string { return apk_dir() . '/acha-facil.apk'; }

function apk_info(): ?array {
    $f = apk_arquivo();
    if (!is_file($f)) return null;
    clearstatcache(true, $f);
    $v = @file_get_contents(apk_dir() . '/versao.txt');
    return [
        'tamanho' => filesize($f),
        'mb' => number_format(filesize($f) / 1048576, 0, ',', '.') . ' MB',
        'atualizado' => date('d/m/Y', filemtime($f)),
        'versao' => $v !== false ? trim($v) : '',
        'downloads' => apk_downloads(),
    ];
}

function apk_downloads(): int {
    $n = @file_get_contents(apk_dir() . '/downloads.txt');
    return $n === false ? 0 : (int)trim($n);
}

/** Soma 1 ao total, com trava para dois downloads ao mesmo tempo não se atrapalharem. */
function apk_contar(): void {
    $h = @fopen(apk_dir() . '/downloads.txt', 'c+');
    if (!$h) return;
    if (flock($h, LOCK_EX)) {
        $n = (int)trim((string)stream_get_contents($h));
        ftruncate($h, 0);
        rewind($h);
        fwrite($h, (string)($n + 1));
        fflush($h);
        flock($h, LOCK_UN);
    }
    fclose($h);
    apk_log('inicio');
}

// ---------- histórico dos downloads (passo 40, 14/11) ----------
// downloads.log: uma linha por evento, "AAAA-MM-DD HH:MM:SS;versão;inicio|fim" (sem IP nem nada da pessoa).
// inicio = começou a baixar (é o que soma no downloads.txt); fim = o arquivo chegou até o último byte.
function apk_log(string $evento): void {
    $v = trim((string)@file_get_contents(apk_dir() . '/versao.txt')) ?: '?';
    @file_put_contents(apk_dir() . '/downloads.log', date('Y-m-d H:i:s') . ";$v;$evento\n", FILE_APPEND | LOCK_EX);
}

/** Downloads por dia e por versão desde $desde (AAAA-MM-DD): ['dias' => [dia => [inicio, fim]], 'versoes' => [v => [inicio, fim]]]. */
function apk_historico(string $desde): array {
    $r = ['dias' => [], 'versoes' => [], 'inicio' => 0, 'fim' => 0, 'desde_log' => null];
    $linhas = @file(apk_dir() . '/downloads.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    if ($linhas) $r['desde_log'] = substr($linhas[0], 0, 10);
    for ($d = $desde; $d <= hoje(); $d = date('Y-m-d', strtotime("$d +1 day"))) $r['dias'][$d] = ['inicio' => 0, 'fim' => 0];
    foreach ($linhas as $l) {
        [$quando, $v, $ev] = array_pad(explode(';', $l), 3, '');
        $dia = substr($quando, 0, 10);
        if ($dia < $desde || !isset($r['dias'][$dia]) || !in_array($ev, ['inicio', 'fim'], true)) continue;
        $r['dias'][$dia][$ev]++;
        $r['versoes'][$v] ??= ['inicio' => 0, 'fim' => 0];
        $r['versoes'][$v][$ev]++;
        $r[$ev]++;
    }
    uksort($r['versoes'], fn($a, $b) => version_compare((string)$b, (string)$a));
    return $r;
}

/** "1.234 downloads" / "1 download" */
function apk_downloads_texto(int $n): string {
    return number_format($n, 0, ',', '.') . ($n === 1 ? ' download' : ' downloads');
}

// ---------- versão da Play Store (passo 34) ----------
// A Play não tem como o servidor perguntar a versão publicada: o admin informa em /admin/app.php quando a versão nova
// fica disponível na loja (depois da revisão). O app instalado pela Play compara com ela e oferece abrir a loja.
const PACOTE_ANDROID = 'br.com.fsadesigner.achafacil';
function versao_play(): ?string {
    $v = trim((string)@file_get_contents(apk_dir() . '/versao-play.txt'));
    return preg_match('/^\d{1,4}(\.\d{1,4}){0,2}$/', $v) ? $v : null;
}
function salvar_versao_play(?string $v): bool {
    if (!is_dir(apk_dir())) @mkdir(apk_dir(), 0750, true);
    return @file_put_contents(apk_dir() . '/versao-play.txt', (string)$v) !== false;
}
/** Página do app na Play Store (o link do config, ou o endereço padrão pelo pacote). */
function link_play(): string {
    $l = trim((string)(config()['link_play'] ?? ''));
    return $l !== '' ? $l : 'https://play.google.com/store/apps/details?id=' . PACOTE_ANDROID;
}
