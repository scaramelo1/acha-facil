<?php
// Nomes em caixa irregular (19/11, avaliação do site): "REDE DROGÃO" → "Rede Drogão", "Canhotos BAR" → "Canhotos Bar",
// "Point Vip Sjbv" → "Point Vip SJBV". Regra em limpar_nome() (inc/limpeza.php), a mesma que o importador usa.
// Só ficha importada SEM dono e não editada pelo admin. O endereço da ficha não muda (é minúsculo).
//   php servidor/limpar-nomes.php            → só mostra o que mudaria
//   php servidor/limpar-nomes.php --aplicar  → grava e guarda um arquivo para desfazer em <dados>/backups/
// Desfazer: mysql acha_facil < <arquivo .sql mostrado no fim>
declare(strict_types=1);
require __DIR__ . '/inc/base.php';
require_once __DIR__ . '/inc/guia.php';
require_once __DIR__ . '/inc/limpeza.php';
require_once __DIR__ . '/inc/moderacao.php';   // editados_pelo_admin()
if (PHP_SAPI !== 'cli') exit("Rode pelo terminal.\n");
$aplicar = in_array('--aplicar', $argv, true);

$do_admin = editados_pelo_admin();
$muda = [];
foreach (linhas('SELECT p.id, p.nome, ' . SQL_TEM_DONO . " AS tem_dono FROM profissionais p WHERE p.importado = 1 AND p.status = 'ativo'") as $p) {
    if ($p['tem_dono'] || isset($do_admin[(int)$p['id']])) continue;
    $novo = limpar_nome((string)$p['nome']);
    if ($novo !== $p['nome']) $muda[(int)$p['id']] = [$p['nome'], $novo];
}
echo count($muda) . " nomes mudariam. Exemplos:\n";
$ex = $muda;
if (!$aplicar) { srand(19); uksort($ex, fn() => rand(-1, 1)); }
foreach (array_slice($ex, 0, $aplicar ? 15 : 60, true) as $id => [$de, $para]) echo "  #$id  $de  →  $para\n";
if (!$aplicar) { echo "\nNada gravado. Para gravar: php servidor/limpar-nomes.php --aplicar\n"; exit; }

$dir = rtrim((string)(config()['dados_dir'] ?? '/var/lib/acha-facil'), '/') . '/backups';
@mkdir($dir, 0750, true);
$arq = "$dir/nomes-antes-" . date('Ymd-His') . '.sql';
$pdo = db();
$sql = '';
foreach ($muda as $id => [$de]) $sql .= 'UPDATE profissionais SET nome = ' . $pdo->quote($de) . ", atualizado_em = atualizado_em WHERE id = $id;\n";
file_put_contents($arq, $sql);
$pdo->beginTransaction();
// atualizado_em = atualizado_em: arrumar a caixa não conta como "ficha mudou" (sitemap e Novidades)
$st = $pdo->prepare('UPDATE profissionais SET nome = ?, atualizado_em = atualizado_em WHERE id = ?');
foreach ($muda as $id => [, $para]) $st->execute([$para, $id]);
$pdo->commit();
require_once __DIR__ . '/inc/web.php';
web_cache_limpar();
echo "\nGravado. Para desfazer: mysql acha_facil < $arq\n";
