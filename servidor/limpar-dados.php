<?php
// Limpeza do que já está no banco (16/11, pedido do usuário). Regras em inc/limpeza.php.
// Só mexe em ficha importada SEM dono. Primeiro confira, depois aplique:
//   php servidor/limpar-dados.php            → só mostra o que mudaria (nada é gravado)
//   php servidor/limpar-dados.php --aplicar  → grava e guarda um arquivo para desfazer em <dados>/backups/
// Desfazer: mysql acha_facil < <arquivo .sql mostrado no fim>
// Faz: bairros (tira o que não é bairro, padroniza, junta acentos, tira os raros sem cara de bairro), tipos (junta
// repetidos, tira endereço no lugar do tipo), área certa do tipo, tira do app o que não é comércio e as fichas repetidas
// (mesmo nome e endereço na mesma cidade: fica a mais completa; favoritos e avaliações passam para ela).
declare(strict_types=1);
require __DIR__ . '/inc/base.php';
require_once __DIR__ . '/inc/guia.php';
require_once __DIR__ . '/inc/limpeza.php';
require_once __DIR__ . '/inc/moderacao.php';   // editados_pelo_admin()
if (PHP_SAPI !== 'cli') exit("Rode pelo terminal.\n");
$aplicar = in_array('--aplicar', $argv, true);

$cat = array_map('intval', array_column(linhas('SELECT id, slug FROM categorias'), 'id', 'slug'));
$nome_area = array_column(linhas('SELECT slug, nome FROM categorias'), 'nome', 'slug');
$slug_de = array_flip($cat);
$todos = linhas("SELECT p.id, p.nome, p.bairro, p.tipo, p.categoria_id, p.cidade, p.status, p.endereco, p.telefone, p.whatsapp, p.instagram,
    p.site, p.lat, p.total_avaliacoes, " . SQL_TEM_DONO . " AS tem_dono FROM profissionais p WHERE p.status = 'ativo'");
$do_admin = editados_pelo_admin();   // editado à mão em /admin/comercio.php: não mexe
$conta = function (array &$l, string $k) { $l[$k] = ($l[$k] ?? 0) + 1; };

// 1) regras de cada ficha
$novo = [];   // id => [bairro, tipo, categoria_id, status, pode mexer]
$tirados = $padronizados = $tipos = $areas = $nao_comercio = $raros = [];
foreach ($todos as $p) {
    $b = $p['bairro'] === '' ? null : $p['bairro'];
    $t = $p['tipo'];
    $c = (int)$p['categoria_id'];
    if ($p['tem_dono'] || isset($do_admin[(int)$p['id']])) { $novo[$p['id']] = [$b, $t, $c, 'ativo', false]; continue; }   // dado do dono: não mexe
    $nb = $b === null ? null : limpar_bairro($b, $p['nome'], $p['cidade']);
    if ($b !== null && $nb === null) $conta($tirados, $b);
    elseif ($nb !== null && $nb !== $b) $conta($padronizados, "$b → $nb");
    $nt = limpar_tipo($t);
    if ($nt !== $t) $conta($tipos, ($t ?? '') . ' → ' . ($nt ?? '(vazio)'));
    $slug = area_certa_do_tipo($nt);
    $nc = $slug && isset($cat[$slug]) ? $cat[$slug] : $c;
    if ($nc !== $c) $conta($areas, ($nt ?? '') . ': ' . ($nome_area[$slug_de[$c] ?? ''] ?? '?') . ' → ' . $nome_area[$slug]);
    $st = 'ativo';
    if (tipo_nao_comercio($nt)) { $st = 'pausado'; $conta($nao_comercio, "$nt: {$p['nome']}"); }
    $novo[$p['id']] = [$nb, $nt, $nc, $st, true];
}

// 2) mesmo bairro escrito de jeitos diferentes na mesma cidade ("Jardim Sao Paulo" e "Jardim São Paulo"):
//    fica o jeito mais usado (empate: o com acento)
$grupos = [];
foreach ($todos as $p) {
    $b = $novo[$p['id']][0];
    if ($b !== null) $grupos[$p['cidade'] . '|' . chave_texto($b)][$b] = ($grupos[$p['cidade'] . '|' . chave_texto($b)][$b] ?? 0) + 1;
}
$escolhido = [];
foreach ($grupos as $g => $variantes) {
    if (count($variantes) < 2) continue;
    uksort($variantes, fn($x, $y) => [$variantes[$y], strlen($y) - mb_strlen($y)] <=> [$variantes[$x], strlen($x) - mb_strlen($x)]);
    $escolhido[$g] = array_key_first($variantes);
}
foreach ($todos as $p) {
    [$b, , , , $pode] = $novo[$p['id']];
    if (!$pode || $b === null) continue;
    $g = $p['cidade'] . '|' . chave_texto($b);
    if (isset($escolhido[$g]) && $escolhido[$g] !== $b) { $conta($padronizados, "$b → {$escolhido[$g]}"); $novo[$p['id']][0] = $escolhido[$g]; }
}

// 3) bairro que só aparece numa ficha da cidade e não tem cara de bairro (Jardim, Vila, Parque...): sai
$uso = [];
foreach ($todos as $p) if (($b = $novo[$p['id']][0]) !== null) $uso[$p['cidade'] . '|' . $b] = ($uso[$p['cidade'] . '|' . $b] ?? 0) + 1;
foreach ($todos as $p) {
    [$b, , , , $pode] = $novo[$p['id']];
    if ($pode && $b !== null && $uso[$p['cidade'] . '|' . $b] === 1 && bairro_raro_suspeito($b)) { $conta($raros, $b); $novo[$p['id']][0] = null; }
}

// 4) fichas repetidas: mesmo nome e mesmo endereço na mesma cidade, todas sem dono. Fica a mais completa.
$rep = [];
foreach ($todos as $p) {
    if (!$novo[$p['id']][4] || $novo[$p['id']][3] !== 'ativo' || trim((string)$p['endereco']) === '') continue;
    $rep[$p['cidade'] . '|' . chave_texto($p['nome']) . '|' . chave_texto((string)$p['endereco'])][] = $p;
}
$juntar = [];   // id repetido => id que fica
$repetidas = [];
foreach ($rep as $lista) {
    if (count($lista) < 2) continue;
    $nota = fn($p) => [(int)(bool)$p['whatsapp'] + (int)(bool)$p['telefone'] + (int)(bool)$p['instagram'] + (int)(bool)$p['site'] + (int)($p['lat'] !== null)
        + (int)$p['total_avaliacoes'], -(int)$p['id']];
    usort($lista, fn($a, $b) => $nota($b) <=> $nota($a));
    $fica = array_shift($lista);
    foreach ($lista as $p) { $juntar[(int)$p['id']] = (int)$fica['id']; $novo[$p['id']][3] = 'pausado'; }
    $repetidas[] = nome_cidade($fica['cidade'], true) . ": {$fica['nome']} (fica #{$fica['id']}, sai " . implode(', ', array_map(fn($p) => '#' . $p['id'], $lista)) . ')';
}

// 5) o que muda
$mudar = [];
foreach ($todos as $p) {
    [$b, $t, $c, $st] = $novo[$p['id']];
    if ($b !== ($p['bairro'] === '' ? null : $p['bairro']) || $t !== $p['tipo'] || $c !== (int)$p['categoria_id'] || $st !== $p['status']) $mudar[] = [$p, $b, $t, $c, $st];
}
arsort($tirados); arsort($padronizados); arsort($tipos); arsort($areas); arsort($raros);
$mostrar = function (string $titulo, array $l, int $max = 40) {
    echo "\n$titulo (" . count($l) . ' diferentes, ' . array_sum($l) . " fichas):\n";
    foreach (array_slice($l, 0, $max, true) as $k => $n) echo "  {$n}× $k\n";
};
echo 'Fichas que mudam: ' . count($mudar) . ' de ' . count($todos) . "\n";
$mostrar('Bairros tirados (não eram bairro)', $tirados, 30);
$mostrar('Bairros padronizados', $padronizados, 20);
$mostrar('Bairros raros sem cara de bairro (aparecem 1 vez na cidade)', $raros, 80);
$mostrar('Tipos juntados ou tirados', $tipos, 30);
$mostrar('Tipos que mudam de área', $areas, 80);
$mostrar('Saem do app: não é comércio', $nao_comercio, 60);
echo "\nFichas repetidas (" . count($repetidas) . " grupos):\n" . implode('', array_map(fn($l) => "  $l\n", $repetidas));

if (!$aplicar) exit("\nNada foi gravado. Para gravar: php servidor/limpar-dados.php --aplicar\n");

// 6) grava, com arquivo para desfazer
$dir = config()['dados_dir'] . '/backups';
@mkdir($dir, 0750, true);
$arq = "$dir/limpeza-dados-" . date('Ymd-His') . '.sql';
$pdo = db();
$sql = "-- Desfaz a limpeza de " . date('d/m/Y H:i') . " (mysql acha_facil < este arquivo)\n";
foreach ($mudar as [$p]) $sql .= 'UPDATE profissionais SET bairro = ' . ($p['bairro'] === null ? 'NULL' : $pdo->quote($p['bairro'])) . ', tipo = '
    . ($p['tipo'] === null ? 'NULL' : $pdo->quote($p['tipo'])) . ', categoria_id = ' . (int)$p['categoria_id'] . ', status = ' . $pdo->quote($p['status'])
    . ', atualizado_em = atualizado_em WHERE id = ' . (int)$p['id'] . ";\n";
// favoritos e avaliações das repetidas passam para a que fica (guardando quem era de quem, para desfazer)
$movidos = [];
foreach ($juntar as $de => $para) {
    foreach (['favoritos', 'avaliacoes'] as $tab) foreach (linhas("SELECT usuario_id FROM $tab WHERE profissional_id = ?", [$de]) as $l) {
        $movidos[] = [$tab, (int)$l['usuario_id'], $de, $para];
        $sql .= "UPDATE $tab SET profissional_id = $de WHERE profissional_id = $para AND usuario_id = " . (int)$l['usuario_id'] . ";\n";
    }
}
file_put_contents($arq, $sql) !== false || exit("✘ Não consegui gravar o arquivo para desfazer ($arq). Nada foi mudado.\n");
$pdo->beginTransaction();
// atualizado_em = atualizado_em: limpeza não conta como "ficha mudou" (sitemap e Novidades)
$st = $pdo->prepare('UPDATE profissionais SET bairro = ?, tipo = ?, categoria_id = ?, status = ?, atualizado_em = atualizado_em WHERE id = ?');
foreach ($mudar as [$p, $b, $t, $c, $s]) $st->execute([$b, $t, $c, $s, $p['id']]);
foreach ($movidos as [$tab, $u, $de, $para]) q("UPDATE IGNORE $tab SET profissional_id = ? WHERE profissional_id = ? AND usuario_id = ?", [$para, $de, $u]);
$pdo->commit();
require_once __DIR__ . '/inc/web.php';
web_cache_limpar();
echo "\n✔ Gravado: " . count($mudar) . " fichas" . ($movidos ? ', ' . count($movidos) . ' favoritos/avaliações passados para a ficha que ficou' : '') . ". Para desfazer: mysql acha_facil < $arq\n";
