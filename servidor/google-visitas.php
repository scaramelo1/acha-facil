<?php
// Visitas do robô do Google por cidade (passo 69). Lê o registro do Apache do achafacilapp.com.br e guarda em
// $DADOS/google/visitas.json quais fichas o Google já visitou (pelo nº da ficha) e quantas páginas ele visitou
// por dia em cada cidade. Só conta o Google de verdade: User-Agent "Googlebot" e IP que volta *.googlebot.com
// ou *.google.com (conferido nos dois sentidos, guardado no próprio arquivo). Pode rodar quantas vezes quiser:
// o registro do Apache só guarda alguns dias, mas o que já foi visto fica no arquivo.
// Precisa ler /var/log/apache2 (root). O instalar.sh põe no cron de hora em hora; a página é /admin/google.php.
//   php servidor/google-visitas.php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/inc/base.php';
require __DIR__ . '/inc/guia.php';

$dir = config()['dados_dir'] . '/google';
if (!is_dir($dir)) mkdir($dir, 0750, true);
$arq = "$dir/visitas.json";
$d = is_file($arq) ? (json_decode((string)file_get_contents($arq), true) ?: []) : [];
$d += ['fichas' => [], 'dias' => [], 'ips' => []];

/** IP do Google? Reverso termina em googlebot.com/google.com e o nome volta para o mesmo IP. */
$e_google = function (string $ip) use (&$d): bool {
    if (isset($d['ips'][$ip])) return (bool)$d['ips'][$ip];
    $nome = @gethostbyaddr($ip);
    $ok = $nome && $nome !== $ip && preg_match('/\.(googlebot|google)\.com$/', $nome) && in_array($ip, gethostbynamel($nome) ?: [], true);
    $d['ips'][$ip] = $ok ? 1 : 0;
    return $ok;
};

$cidades_web = [];
foreach (TODAS_CIDADES as $slug => $_) $cidades_web[cidade_web($slug)] = $slug;
$dias = [];   // contados agora: dia => cidade => [paginas, fichas]
$lidas = 0;
foreach (glob('/var/log/apache2/achafacilapp_acesso.log*') ?: [] as $f) {
    $gz = str_ends_with($f, '.gz');
    $h = $gz ? @gzopen($f, 'r') : @fopen($f, 'r');
    if (!$h) { fwrite(STDERR, "Sem permissão para ler $f (rode como root).\n"); continue; }
    while (($l = $gz ? gzgets($h) : fgets($h)) !== false) {
        if (stripos($l, 'googlebot') === false) continue;
        if (!preg_match('#^(\S+) \S+ \S+ \[(\d\d)/(\w{3})/(\d{4}):[^\]]+\] "GET (/[^ ?"]*)\S* [^"]*" 200 #', $l, $m)) continue;
        [, $ip, $dd, $mes, $ano, $caminho] = $m;
        $partes = explode('/', trim(rawurldecode($caminho), '/'));
        $cid = $cidades_web[$partes[0] ?? ''] ?? null;
        if (!$cid || !$e_google($ip)) continue;
        $dia = date('Y-m-d', strtotime("$dd $mes $ano"));
        $dias[$dia][$cid]['paginas'] = ($dias[$dia][$cid]['paginas'] ?? 0) + 1;
        if (($partes[1] ?? '') === 'c' && preg_match('/-(\d+)$/', $partes[2] ?? '', $n)) {
            $id = $n[1];
            $dias[$dia][$cid]['fichas'] = ($dias[$dia][$cid]['fichas'] ?? 0) + 1;
            $antes = $d['fichas'][$id] ?? null;
            $d['fichas'][$id] = [min($antes[0] ?? $dia, $dia), max($antes[1] ?? $dia, $dia)];   // primeira e última visita
        }
        $lidas++;
    }
    $gz ? gzclose($h) : fclose($h);
}
// o dia mais antigo do registro pode estar pela metade: só troca se agora contou mais
foreach ($dias as $dia => $porCidade) foreach ($porCidade as $cid => $v) {
    $velho = $d['dias'][$dia][$cid] ?? ['paginas' => 0];
    if (($v['paginas'] ?? 0) >= ($velho['paginas'] ?? 0)) $d['dias'][$dia][$cid] = $v + ['fichas' => 0];
}
ksort($d['dias']);
$d['atualizado'] = date('Y-m-d H:i:s');
file_put_contents("$arq.novo", json_encode($d, JSON_UNESCAPED_SLASHES));
rename("$arq.novo", $arq);
@chown($dir, 'www-data'); @chown($arq, 'www-data'); @chmod($arq, 0640);
echo "Google: $lidas páginas no registro, " . count($d['fichas']) . " fichas diferentes já visitadas.\n";
