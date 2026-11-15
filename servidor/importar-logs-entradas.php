<?php
// Passo 43: traz para as tabelas entradas/entradas_hora as chegadas ao site vindas de fora (Google, redes, outros
// sites) que estão no registro do Apache de ANTES da contagem começar (mudança 022). Roda uma vez só (marca na tabela
// migracoes). Entradas "direto" não entram (no registro não dá para separar de recarga). Mesmas regras da contagem:
// só páginas 200, sem robôs, a mesma pessoa (IP + navegador) pela mesma origem em menos de 30 min conta uma vez.
//   php servidor/importar-logs-entradas.php [--teste] [arquivos de log...]
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/inc/base.php';
require __DIR__ . '/inc/guia.php';

const MARCA = 'logs-entradas-antes-022';
$teste = in_array('--teste', $argv, true);
$arquivos = array_values(array_filter(array_slice($argv, 1), fn($a) => $a !== '--teste'));
if (!$arquivos) $arquivos = glob('/var/log/apache2/achafacilapp_acesso.log*') ?: [];
if (!$teste && valor('SELECT 1 FROM migracoes WHERE nome = ?', [MARCA])) exit("Já importado antes.\n");
$corte = valor("SELECT aplicada_em FROM migracoes WHERE nome = '022-entradas-site.sql'");
if (!$corte) exit("Falta aplicar a mudança 022.\n");
$_SERVER['HTTP_HOST'] = 'achafacilapp.com.br';

$linhas = [];
foreach ($arquivos as $f) {
    $h = str_ends_with($f, '.gz') ? gzopen($f, 'r') : fopen($f, 'r');
    if (!$h) { echo "Não abri $f\n"; continue; }
    while (($l = str_ends_with($f, '.gz') ? gzgets($h) : fgets($h)) !== false) {
        if (!preg_match('/^(\S+) \S+ \S+ \[([^\]]+)\] "GET (\S+) [^"]*" (\d{3}) \S+ "([^"]*)" "([^"]*)"/', $l, $m)) continue;
        [, $ip, $data, $url, $status, $ref, $ua] = $m;
        if ($status !== '200' || $ref === '' || $ref === '-') continue;
        if (preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp\/|curl|wget|python|httpclient|headless|lighthouse|Teste/i', $ua)) continue;
        $quando = DateTime::createFromFormat('d/M/Y:H:i:s O', $data);
        if (!$quando) continue;
        $quando->setTimezone(new DateTimeZone('America/Sao_Paulo'));
        $q = $quando->format('Y-m-d H:i:s');
        if ($q >= $corte) continue;
        $origem = origem_do_referer($ref);
        if ($origem === null) continue;   // veio do próprio site
        $caminho = rawurldecode((string)parse_url($url, PHP_URL_PATH));
        if (preg_match('#^/(admin|api|painel|google|assets|fotos|apk|\.well-known)/#', $caminho)
            || preg_match('#\.(?!php$)[a-z0-9]{2,5}$#i', $caminho)
            || preg_match('#^/(assinatura|baixar-apk|mp-webhook|perfil|web)\.php$#', $caminho)) continue;
        $linhas[] = [$q, $ip . '|' . $ua, $origem, $caminho];
    }
    fclose($h);
}
usort($linhas, fn($a, $b) => strcmp($a[0], $b[0]));

// tipo de página e cidade pelo endereço (mesma divisão de publico/web.php)
function pagina_do_caminho(string $c): array {
    if ($c === '/' || $c === '/index.php') return ['inicio', null];
    if (preg_match('#^/[a-z-]+\.php$#', $c)) return [$c === '/busca.php' ? 'busca' : 'outra', null];
    $p = array_values(array_filter(explode('/', $c), fn($s) => $s !== ''));
    $cid = cidade_da_web($p[0] ?? '');
    if (!$cid) return ['outra', null];
    $n = count($p);
    return [match (true) {
        $n >= 3 && $p[1] === 'c' => 'ficha',
        $n >= 2 && $p[1] === 'eventos' => $n === 2 ? 'eventos' : 'evento',
        $n >= 2 && $p[1] === 'buscar' => 'busca',
        $n === 1 => 'cidade',
        default => 'lista',
    }, $cid];
}

$visto = [];   // pessoa|origem => última vez (30 min, como o cookie)
$ok = 0; $por = [];
if (!$teste) db()->beginTransaction();
foreach ($linhas as [$q, $pessoa, $origem, $caminho]) {
    $t = strtotime($q);
    $k = "$pessoa|$origem";
    $nova = !isset($visto[$k]) || $t - $visto[$k] > 1800;
    $visto[$k] = $t;
    if (!$nova) continue;
    [$pagina, $cid] = pagina_do_caminho($caminho);
    $ok++;
    $por[$origem] = ($por[$origem] ?? 0) + 1;
    if ($teste) continue;
    q('INSERT INTO entradas (dia, origem, caminho, pagina, cidade, total) VALUES (?, ?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE total = total + 1',
        [substr($q, 0, 10), $origem, mb_substr($caminho, 0, 190), $pagina, $cid]);
    q('INSERT INTO entradas_hora (quando, origem, caminho, pagina, cidade) VALUES (?, ?, ?, ?, ?)', [$q, $origem, mb_substr($caminho, 0, 190), $pagina, $cid]);
}
if (!$teste) { q('INSERT INTO migracoes (nome) VALUES (?)', [MARCA]); db()->commit(); }
echo ($teste ? '[teste] ' : '') . "Entradas de antes de $corte: $ok (" . implode(', ', array_map(fn($o, $n) => "$o $n", array_keys($por), $por)) . ")\n";
