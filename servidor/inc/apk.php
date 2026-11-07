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
}

/** "1.234 downloads" / "1 download" */
function apk_downloads_texto(int $n): string {
    return number_format($n, 0, ',', '.') . ($n === 1 ? ' download' : ' downloads');
}
