<?php
// Limpeza de bairros e tipos do que já está no banco (16/11, pedido do usuário). Regras em inc/limpeza.php.
// Só mexe em ficha importada SEM dono. Primeiro confira, depois aplique:
//   php servidor/limpar-dados.php            → só mostra o que mudaria (nada é gravado)
//   php servidor/limpar-dados.php --aplicar  → grava e guarda um arquivo para desfazer em <dados>/backups/
// Desfazer: mysql acha_facil < <arquivo .sql mostrado no fim>
declare(strict_types=1);
require __DIR__ . '/inc/base.php';
require_once __DIR__ . '/inc/guia.php';
require_once __DIR__ . '/inc/limpeza.php';
if (PHP_SAPI !== 'cli') exit("Rode pelo terminal.\n");
$aplicar = in_array('--aplicar', $argv, true);

$cat = array_column(linhas('SELECT id, slug FROM categorias'), 'id', 'slug');
$nome_area = array_column(linhas('SELECT slug, nome FROM categorias'), 'nome', 'slug');
$slug_de = array_flip(array_map('intval', $cat));
$todos = linhas('SELECT p.id, p.nome, p.bairro, p.tipo, p.categoria_id, p.cidade, ' . SQL_TEM_DONO . ' AS tem_dono FROM profissionais p');

// 1) regras de cada ficha
$novo = [];   // id => [bairro, tipo, categoria_id]
$tirados = $padronizados = $tipos = $areas = [];
foreach ($todos as $p) {
    $b = $p['bairro'];
    $t = $p['tipo'];
    $c = (int)$p['categoria_id'];
    if ($p['tem_dono']) { $novo[$p['id']] = [$b, $t, $c, false]; continue; }   // dado do dono: não mexe
    $nb = ($b === null || $b === '') ? null : limpar_bairro($b, $p['nome'], $p['cidade']);
    if ($b !== null && $b !== '' && $nb === null) $tirados[$b] = ($tirados[$b] ?? 0) + 1;
    elseif ($nb !== null && $nb !== $b) $padronizados["$b → $nb"] = ($padronizados["$b → $nb"] ?? 0) + 1;
    $nt = limpar_tipo($t);
    if ($nt !== $t) $tipos["$t → $nt"] = ($tipos["$t → $nt"] ?? 0) + 1;
    $slug = area_certa_do_tipo($nt);
    $nc = $slug && isset($cat[$slug]) ? (int)$cat[$slug] : $c;
    if ($nc !== $c) $areas[($nt ?? '') . ': ' . ($nome_area[$slug_de[$c] ?? ''] ?? '?') . ' → ' . $nome_area[$slug]] = ($areas[($nt ?? '') . ': ' . ($nome_area[$slug_de[$c] ?? ''] ?? '?') . ' → ' . $nome_area[$slug]] ?? 0) + 1;
    $novo[$p['id']] = [$nb, $nt, $nc, true];
}

// 2) mesmo bairro escrito de jeitos diferentes na mesma cidade ("Jardim Sao Paulo" e "Jardim São Paulo"):
//    fica o jeito mais usado (empate: o com acento)
$grupos = [];
foreach ($todos as $p) {
    $b = $novo[$p['id']][0];
    if ($b === null || $b === '') continue;
    $grupos[$p['cidade'] . '|' . chave_texto($b)][$b] = ($grupos[$p['cidade'] . '|' . chave_texto($b)][$b] ?? 0) + 1;
}
$escolhido = [];
foreach ($grupos as $g => $variantes) {
    if (count($variantes) < 2) continue;
    uksort($variantes, fn($x, $y) => [$variantes[$y], strlen($y) - mb_strlen($y)] <=> [$variantes[$x], strlen($x) - mb_strlen($x)]);
    $escolhido[$g] = array_key_first($variantes);
}
$unificados = 0;
foreach ($todos as $p) {
    [$b, , , $pode] = $novo[$p['id']];
    if (!$pode || $b === null) continue;
    $g = $p['cidade'] . '|' . chave_texto($b);
    if (isset($escolhido[$g]) && $escolhido[$g] !== $b) {
        $padronizados["$b → {$escolhido[$g]}"] = ($padronizados["$b → {$escolhido[$g]}"] ?? 0) + 1;
        $novo[$p['id']][0] = $escolhido[$g];
        $unificados++;
    }
}

// 3) o que muda
$mudar = [];
foreach ($todos as $p) {
    [$b, $t, $c] = $novo[$p['id']];
    if ($b !== ($p['bairro'] === '' ? null : $p['bairro']) || $t !== $p['tipo'] || $c !== (int)$p['categoria_id']) $mudar[] = [$p, $b, $t, $c];
}
$antes = count(array_unique(array_filter(array_map(fn($p) => $p['cidade'] . '|' . $p['bairro'], array_filter($todos, fn($p) => $p['bairro'] !== null && $p['bairro'] !== '')))));
$depois = count(array_unique(array_map(fn($p) => $p['cidade'] . '|' . $novo[$p['id']][0], array_filter($todos, fn($p) => $novo[$p['id']][0] !== null))));
arsort($tirados); arsort($padronizados); arsort($tipos); arsort($areas);
$mostrar = function (string $titulo, array $l, int $max = 40) {
    echo "\n$titulo (" . count($l) . ' diferentes, ' . array_sum($l) . " fichas):\n";
    foreach (array_slice($l, 0, $max, true) as $k => $n) echo "  {$n}× $k\n";
};
echo 'Fichas que mudam: ' . count($mudar) . ' de ' . count($todos) . "\n";
echo "Bairros diferentes (por cidade): $antes → $depois\n";
$mostrar('Bairros tirados (não eram bairro)', $tirados, 60);
$mostrar('Bairros padronizados', $padronizados, 40);
$mostrar('Tipos juntados', $tipos);
$mostrar('Tipos que mudam de área', $areas, 80);

if (!$aplicar) exit("\nNada foi gravado. Para gravar: php servidor/limpar-dados.php --aplicar\n");

// 4) grava, com arquivo para desfazer
$dir = config()['dados_dir'] . '/backups';
@mkdir($dir, 0750, true);
$arq = "$dir/limpeza-bairros-tipos-" . date('Ymd-His') . '.sql';
$pdo = db();
$sql = "-- Desfaz a limpeza de bairros e tipos de " . date('d/m/Y H:i') . " (mysql acha_facil < este arquivo)\n";
foreach ($mudar as [$p]) $sql .= 'UPDATE profissionais SET bairro = ' . ($p['bairro'] === null ? 'NULL' : $pdo->quote($p['bairro'])) . ', tipo = '
    . ($p['tipo'] === null ? 'NULL' : $pdo->quote($p['tipo'])) . ', categoria_id = ' . (int)$p['categoria_id'] . ', atualizado_em = atualizado_em WHERE id = ' . (int)$p['id'] . ";\n";
file_put_contents($arq, $sql) !== false || exit("✘ Não consegui gravar o arquivo para desfazer ($arq). Nada foi mudado.\n");
$pdo->beginTransaction();
// atualizado_em = atualizado_em: limpeza não conta como "ficha mudou" (sitemap e Novidades)
$st = $pdo->prepare('UPDATE profissionais SET bairro = ?, tipo = ?, categoria_id = ?, atualizado_em = atualizado_em WHERE id = ?');
foreach ($mudar as [$p, $b, $t, $c]) $st->execute([$b, $t, $c, $p['id']]);
$pdo->commit();
require_once __DIR__ . '/inc/web.php';
web_cache_limpar();
echo "\n✔ Gravado: " . count($mudar) . " fichas. Para desfazer: mysql acha_facil < $arq\n";
