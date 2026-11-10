<?php
// Acha a localização (lat/lng) dos comércios para o "Perto de você". Rodar na VPS como root (pode levar ~1 h na 1ª vez):
//   nohup php servidor/geocodificar.php > /var/log/acha-facil-geo.log 2>&1 &
// 1) Link do Google Maps com coordenadas (!3d…!4d…) → usa direto.
// 2) Senão, procura o endereço no OpenStreetMap (Nominatim): 1 pedido a cada 1,2 s, como pede a política de uso deles.
//    Sem número exato, fica na rua; sem rua, no bairro. Quem não for achado fica sem localização (não entra no "Perto").
// Só tenta quem ainda não tem localização e não foi tentado nos últimos 30 dias. Pode rodar de novo quando quiser.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/inc/base.php';
require_once __DIR__ . '/inc/guia.php';

// 1) coordenadas que já vêm no link do Google
$n = 0;
foreach (linhas("SELECT id, mapa FROM profissionais WHERE lat IS NULL AND mapa LIKE '%!3d%!4d%'") as $p) {
    if (preg_match('/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $p['mapa'], $m)) {
        q("UPDATE profissionais SET lat = ?, lng = ?, geo_origem = 'google' WHERE id = ?", [$m[1], $m[2], $p['id']]);
        $n++;
    }
}
echo "✔ $n localizações tiradas dos links do Google\n";

// caixa em volta das cidades atendidas (descarta resultados de outra cidade com rua de mesmo nome)
// caixa = centros das cidades cadastradas (tabela cidades) + 0,25° (~25 km) de folga
$centros = array_filter(TODAS_CIDADES, fn($c) => $c[3] !== null && $c[4] !== null);
define('CAIXA', $centros
    ? ['lat' => [min(array_column($centros, 3)) - 0.25, max(array_column($centros, 3)) + 0.25], 'lng' => [min(array_column($centros, 4)) - 0.25, max(array_column($centros, 4)) + 0.25]]
    : ['lat' => [-22.20, -21.65], 'lng' => [-47.10, -46.40]]);

function nominatim(string $consulta): ?array {
    usleep(1200000);
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => $consulta, 'format' => 'json', 'limit' => 1, 'countrycodes' => 'br',
        'viewbox' => CAIXA['lng'][0] . ',' . CAIXA['lat'][1] . ',' . CAIXA['lng'][1] . ',' . CAIXA['lat'][0], 'bounded' => 1,   // mesma área da CAIXA
    ]);
    $ctx = stream_context_create(['http' => ['timeout' => 20, 'header' => "User-Agent: AchaFacil/1.0 (guia de comercios locais; geocodificacao unica)\r\nAccept-Language: pt-BR\r\n"]]);
    $r = @file_get_contents($url, false, $ctx);
    $j = $r ? json_decode($r, true) : null;
    if (!$j || !isset($j[0]['lat'])) return null;
    $lat = (float)$j[0]['lat']; $lng = (float)$j[0]['lon'];
    if ($lat < CAIXA['lat'][0] || $lat > CAIXA['lat'][1] || $lng < CAIXA['lng'][0] || $lng > CAIXA['lng'][1]) return null;
    // "building/house" = número achado; o resto = rua ou lugar aproximado
    return [$lat, $lng, in_array($j[0]['addresstype'] ?? '', ['building', 'house', 'amenity', 'shop'], true) ? 'endereco' : 'rua'];
}

// "R. Cel. Ernesto de Oliveira, 457" → "Rua Coronel Ernesto de Oliveira 457" (o OpenStreetMap acha melhor sem abreviação)
function por_extenso(string $e): string {
    $e = preg_replace('/^(R|Rua)[.:]?\s*:?\s+/iu', 'Rua ', $e);
    $e = preg_replace('/^Av\.?\s+/iu', 'Avenida ', $e);
    $e = preg_replace('/^Pça\.?\s+|^Pç\.?\s+/iu', 'Praça ', $e);
    $e = preg_replace(['/\bCel\.\s*/u', '/\bDr\.\s*/u', '/\bProf\.\s*/u', '/\bCap\.\s*/u', '/\bMal\.\s*/u', '/\bSen\.\s*/u', '/\bEng\.\s*/u', '/\bSta\.\s*/u', '/\bSto\.\s*/u'],
        ['Coronel ', 'Doutor ', 'Professor ', 'Capitão ', 'Marechal ', 'Senador ', 'Engenheiro ', 'Santa ', 'Santo '], $e);
    return trim(preg_replace('/\s*-\s*\d+$/', '', $e));   // "567-605" → "567"
}

$pendentes = linhas("SELECT id, endereco, bairro, cidade FROM profissionais
    WHERE lat IS NULL AND status = 'ativo' AND (endereco IS NOT NULL OR bairro IS NOT NULL)
      AND (geo_tentado IS NULL OR geo_tentado < NOW() - INTERVAL 30 DAY) ORDER BY id");
echo '• ' . count($pendentes) . " endereços para procurar no OpenStreetMap (~" . ceil(count($pendentes) * 1.3 / 60) . " min)\n";
$ok = $falha = 0;
foreach ($pendentes as $i => $p) {
    $cidade = nome_cidade($p['cidade']) . ', ' . uf_cidade($p['cidade']) . ', Brasil';
    $achado = null;
    if ($p['endereco']) {
        $achado = nominatim(por_extenso($p['endereco']) . ', ' . $cidade);
        // sem o número: pelo menos a rua
        if (!$achado && preg_match('/^(.*?),?\s*\d+\D*$/u', por_extenso($p['endereco']), $m) && mb_strlen($m[1]) > 4) {
            $achado = nominatim($m[1] . ', ' . $cidade);
            if ($achado) $achado[2] = 'rua';
        }
    }
    if (!$achado && $p['bairro']) {
        $achado = nominatim($p['bairro'] . ', ' . $cidade);
        if ($achado) $achado[2] = 'bairro';
    }
    if ($achado) {
        q('UPDATE profissionais SET lat = ?, lng = ?, geo_origem = ?, geo_tentado = NOW() WHERE id = ?', [$achado[0], $achado[1], $achado[2], $p['id']]);
        $ok++;
    } else {
        q('UPDATE profissionais SET geo_tentado = NOW() WHERE id = ?', [$p['id']]);
        $falha++;
    }
    if (($i + 1) % 100 === 0) echo '  ' . ($i + 1) . " feitos ($ok achados)\n";
}
echo "✔ OpenStreetMap: $ok achados, $falha sem resultado\n";
echo '✔ Com localização: ' . valor('SELECT COUNT(*) FROM profissionais WHERE lat IS NOT NULL') . ' de ' . valor("SELECT COUNT(*) FROM profissionais WHERE status = 'ativo'") . "\n";
