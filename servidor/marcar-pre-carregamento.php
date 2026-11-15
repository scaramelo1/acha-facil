<?php
// Passo 55: marca como pré-carregamento (entradas_hora.pre = 1) as entradas que já estavam no banco e vieram do
// servidor de pré-carregamento do Google, olhando o registro do Apache (mesma hora e mesma página). Pode rodar de
// novo sem problema (só marca). O instalar.sh roda depois da mudança 027.
//   php servidor/marcar-pre-carregamento.php [--teste]
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/inc/base.php';
require __DIR__ . '/inc/guia.php';
require_once __DIR__ . '/inc/numeros.php';
$teste = in_array('--teste', $argv, true);
if (!pre_carregamento_pronto()) exit("Falta aplicar a mudança 027.\n");

$marcadas = $achadas = 0;
foreach (glob('/var/log/apache2/achafacilapp_acesso.log*') ?: [] as $f) {
    $gz = str_ends_with($f, '.gz');
    $h = $gz ? gzopen($f, 'r') : fopen($f, 'r');
    if (!$h) continue;
    while (($l = $gz ? gzgets($h) : fgets($h)) !== false) {
        if (!preg_match('/^(\S+) \S+ \S+ \[([^\]]+)\] "GET (\S+) [^"]*" 200 \S+ "(https?:\/\/[^"]+)"/', $l, $m)) continue;
        [, $ip, $data, $url] = $m;
        if (!e_pre_carregamento($ip, '')) continue;
        $quando = DateTime::createFromFormat('d/M/Y:H:i:s O', $data);
        if (!$quando) continue;
        $quando->setTimezone(new DateTimeZone(date_default_timezone_get()));
        $caminho = mb_substr(rawurldecode((string)parse_url($url, PHP_URL_PATH)), 0, 190);
        $achadas++;
        if ($teste) { $marcadas += (int)valor('SELECT COUNT(*) FROM entradas_hora WHERE pre = 0 AND caminho = ? AND quando BETWEEN ? - INTERVAL 2 SECOND AND ? + INTERVAL 2 SECOND',
            [$caminho, $quando->format('Y-m-d H:i:s'), $quando->format('Y-m-d H:i:s')]) > 0; continue; }
        $st = q('UPDATE entradas_hora SET pre = 1 WHERE pre = 0 AND caminho = ? AND quando BETWEEN ? - INTERVAL 2 SECOND AND ? + INTERVAL 2 SECOND LIMIT 1',
            [$caminho, $quando->format('Y-m-d H:i:s'), $quando->format('Y-m-d H:i:s')]);
        $marcadas += $st->rowCount();
    }
}
echo ($teste ? '[teste] ' : '') . "Pré-carregamentos no registro do Apache: $achadas; entradas marcadas: $marcadas\n";
