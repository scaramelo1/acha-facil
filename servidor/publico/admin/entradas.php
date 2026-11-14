<?php
// Detalhe de uma origem do site (passo 39): quem veio do Google (ou de outra origem) entrou por quais páginas,
// abriu quais fichas e tocou no WhatsApp/ligar/mapa de quais comércios. Só totais por dia, nada por pessoa.
require __DIR__ . '/../../inc/admin.php';
$admin = exigir_admin();
exigir_numeros_prontos();

$origem = in_array($_GET['origem'] ?? '', ORIGENS_SITE, true) ? (string)$_GET['origem'] : 'google';
$cidade = isset(TODAS_CIDADES[$_GET['cidade'] ?? '']) ? (string)$_GET['cidade'] : '';
$dias = in_array((int)($_GET['dias'] ?? 30), [7, 30, 90], true) ? (int)($_GET['dias'] ?? 30) : 30;
$desde = desde_dias($dias);
$link = fn(array $mudar) => '?' . http_build_query(array_filter(array_merge(['origem' => $origem, 'cidade' => $cidade, 'dias' => $dias], $mudar), fn($v) => $v !== '' && $v !== null));

// 1. páginas onde entraram
$paginas = [];
if (entradas_prontas()) {
    $w = $cidade !== '' ? ' AND cidade = ?' : '';
    $paginas = linhas("SELECT caminho, pagina, cidade, SUM(total) AS total FROM entradas WHERE origem = ? AND dia >= ?$w
        GROUP BY caminho, pagina, cidade ORDER BY total DESC, caminho LIMIT 500", array_merge([$origem, $desde], $cidade !== '' ? [$cidade] : []));
}
$total_entradas = array_sum(array_map(fn($l) => (int)$l['total'], $paginas));

// 2 e 3. fichas abertas e toques de quem veio dessa origem
$w = $cidade !== '' ? ' AND p.cidade = ?' : '';
$fichas = linhas("SELECT p.id, p.nome, p.tipo, p.cidade, p.bairro, c.nome AS categoria,
        SUM(IF(k.tipo = 'perfil', k.total, 0)) AS visualizacoes, SUM(IF(k.tipo = 'perfil', k.pessoas, 0)) AS pessoas,
        SUM(IF(k.tipo = 'whatsapp', k.total, 0)) AS whatsapp, SUM(IF(k.tipo = 'ligar', k.total, 0)) AS ligar,
        SUM(IF(k.tipo = 'mapa', k.total, 0)) AS mapa, SUM(IF(k.tipo = 'site', k.total, 0)) AS site_t,
        SUM(IF(k.tipo = 'instagram', k.total, 0)) AS instagram, SUM(IF(k.tipo <> 'perfil', k.total, 0)) AS toques
    FROM contatos k JOIN profissionais p ON p.id = k.profissional_id JOIN categorias c ON c.id = p.categoria_id
    WHERE k.origem = ? AND k.dia >= ?$w
    GROUP BY p.id ORDER BY visualizacoes DESC, toques DESC, p.nome LIMIT 500", array_merge([$origem, $desde], $cidade !== '' ? [$cidade] : []));
$abertas = array_values(array_filter($fichas, fn($l) => (int)$l['visualizacoes'] > 0));
$tocadas = array_values(array_filter($fichas, fn($l) => (int)$l['toques'] > 0));
usort($tocadas, fn($a, $b) => (int)$b['toques'] <=> (int)$a['toques'] ?: strcmp($a['nome'], $b['nome']));
$soma = fn(array $l, string $k) => array_sum(array_map(fn($x) => (int)$x[$k], $l));
$comercio = fn(array $l) => '<strong>' . h($l['nome']) . '</strong><br><span class="discreto">' . h($l['tipo'] ?: $l['categoria'])
    . ' · ' . h(nome_cidade($l['cidade'], true)) . ($l['bairro'] ? ' · ' . h($l['bairro']) : '') . '</span>';
$ver = fn(array $l) => '<a class="botao pequeno contorno" href="numeros-ficha.php?id=' . (int)$l['id'] . '&amp;dias=' . $dias . '">Ver</a>'
    . ' <a class="botao pequeno contorno" href="' . h(url_ficha($l)) . '" target="_blank" rel="noopener">Abrir</a>';

admin_inicio(ORIGENS[$origem], $admin);
?>
<p><a href="numeros.php?<?= h(http_build_query(array_filter(['cidade' => $cidade, 'dias' => $dias]))) ?>">‹ Números</a></p>
<h1><?= h(ORIGENS[$origem]) ?></h1>
<p class="discreto">O que fizeram no site as pessoas que chegaram por esta origem. São totais por dia: não dá para seguir uma pessoa
  (o site não guarda isso). As palavras pesquisadas no Google ficam só no
  <a href="https://search.google.com/search-console" target="_blank" rel="noopener">Search Console</a>. Separado desde 14/11/2026.</p>

<form method="get" class="filtros-numeros">
  <label>Origem <select name="origem">
    <?php foreach (ORIGENS_SITE as $o): ?><option value="<?= $o ?>"<?= $o === $origem ? ' selected' : '' ?>><?= h(ORIGENS[$o]) ?></option><?php endforeach ?>
  </select></label>
  <label>Cidade <select name="cidade"><option value="">Todas</option>
    <?php foreach (TODAS_CIDADES as $slug => $c): ?><option value="<?= h($slug) ?>"<?= $slug === $cidade ? ' selected' : '' ?>><?= h($c[0]) ?></option><?php endforeach ?>
  </select></label>
  <label>Período <select name="dias">
    <?php foreach ([7, 30, 90] as $d): ?><option value="<?= $d ?>"<?= $d === $dias ? ' selected' : '' ?>>Últimos <?= $d ?> dias</option><?php endforeach ?>
  </select></label>
  <button class="botao pequeno">Filtrar</button>
</form>

<div class="numeros-resumo">
  <?php if (entradas_prontas()): ?><div><strong><?= num($total_entradas) ?></strong><span>entradas no site</span></div><?php endif ?>
  <div><strong><?= num(count($abertas)) ?></strong><span>fichas abertas</span></div>
  <div><strong><?= num($soma($abertas, 'visualizacoes')) ?></strong><span>visualizações</span></div>
  <div><strong><?= num($soma($tocadas, 'toques')) ?></strong><span>toques em <?= num(count($tocadas)) ?> comércios</span></div>
</div>

<h2>1. Páginas onde entraram</h2>
<?php if (!entradas_prontas()): ?>
<p class="aviso">Começa a contar depois de aplicar a mudança 022 (bash servidor/instalar.sh, como root).</p>
<?php elseif (!$paginas): ?>
<p class="discreto">Nenhuma entrada por esta origem no período.</p>
<?php else: ?>
<div class="tabela"><table>
  <tr><th>Página</th><th>Tipo</th><th>Entradas</th><th>%</th></tr>
  <?php foreach ($paginas as $l): ?><tr>
    <td><a href="<?= h(url_publica(ltrim($l['caminho'], '/'))) ?>" target="_blank" rel="noopener"><?= h($l['caminho']) ?></a></td>
    <td><?= h(PAGINAS_ENTRADA[$l['pagina']] ?? $l['pagina']) ?><?= $l['cidade'] ? ' · ' . h(nome_cidade($l['cidade'], true)) : '' ?></td>
    <td><?= num((int)$l['total']) ?></td><td><?= pct((int)$l['total'], $total_entradas) ?></td>
  </tr><?php endforeach ?>
</table></div>
<?php endif ?>

<h2>2. Fichas de comércio abertas</h2>
<?php if (!$abertas): ?><p class="discreto">Nenhuma ficha aberta por quem veio desta origem no período.</p><?php else: ?>
<div class="tabela"><table>
  <tr><th>Comércio</th><th>Visualizações</th><th>Pessoas</th><th>Toques</th><th></th></tr>
  <?php foreach ($abertas as $l): ?><tr>
    <td><?= $comercio($l) ?></td><td><?= num((int)$l['visualizacoes']) ?></td><td><?= num((int)$l['pessoas']) ?></td>
    <td><?= num((int)$l['toques']) ?></td><td><?= $ver($l) ?></td>
  </tr><?php endforeach ?>
</table></div>
<?php endif ?>

<h2>3. Toques em WhatsApp, Ligar, Mapa, Site e Instagram</h2>
<?php if (!$tocadas): ?><p class="discreto">Nenhum toque de quem veio desta origem no período.</p><?php else: ?>
<div class="tabela"><table>
  <tr><th>Comércio</th><th>WhatsApp</th><th>Ligar</th><th>Mapa</th><th>Site</th><th>Instagram</th><th>Total</th><th></th></tr>
  <?php foreach ($tocadas as $l): ?><tr>
    <td><?= $comercio($l) ?></td>
    <?php foreach (['whatsapp', 'ligar', 'mapa', 'site_t', 'instagram'] as $k): ?><td><?= num((int)$l[$k]) ?></td><?php endforeach ?>
    <td><strong><?= num((int)$l['toques']) ?></strong></td><td><?= $ver($l) ?></td>
  </tr><?php endforeach ?>
</table></div>
<?php endif ?>
<?php admin_fim();
