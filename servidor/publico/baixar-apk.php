<?php
// Entrega o APK e conta o download. Pedidos que só continuam um download (Range a partir do meio) não contam de novo.
require __DIR__ . '/../inc/base.php';
require __DIR__ . '/../inc/apk.php';

$f = apk_arquivo();
if (!is_file($f)) {
    header('Location: ' . config()['url'] . '/#baixar', true, 302);
    exit;
}
$tamanho = filesize($f);
$inicio = 0;
$fim = $tamanho - 1;
if (preg_match('/^bytes=(\d*)-(\d*)$/', $_SERVER['HTTP_RANGE'] ?? '', $r) && ($r[1] !== '' || $r[2] !== '')) {
    if ($r[1] === '') { $inicio = max(0, $tamanho - (int)$r[2]); }
    else { $inicio = (int)$r[1]; if ($r[2] !== '') $fim = min($fim, (int)$r[2]); }
    if ($inicio > $fim || $inicio >= $tamanho) { http_response_code(416); header("Content-Range: bytes */$tamanho"); exit; }
    http_response_code(206);
    header("Content-Range: bytes $inicio-$fim/$tamanho");
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $inicio === 0) apk_contar();

header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="acha-facil.apk"');
header('Content-Length: ' . ($fim - $inicio + 1));
header('Accept-Ranges: bytes');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') exit;

while (ob_get_level()) ob_end_clean();
set_time_limit(0);
$h = fopen($f, 'rb');
fseek($h, $inicio);
$falta = $fim - $inicio + 1;
while ($falta > 0 && !feof($h) && !connection_aborted()) {
    $bloco = fread($h, (int)min(1048576, $falta));
    echo $bloco;
    flush();
    $falta -= strlen($bloco);
}
fclose($h);
