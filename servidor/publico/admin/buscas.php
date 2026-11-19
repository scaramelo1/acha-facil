<?php
// O que as pessoas procuram no site e no app (19/11, passo 106, pedido do usuário, avaliação item 2).
// "Sem resultado" = falta sinônimo em inc/busca.php (avisar o Claude) ou falta esse comércio na cidade (oportunidade de venda).
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/busca.php';
$admin = exigir_admin();

$cidade = isset(TODAS_CIDADES[$_GET['cidade'] ?? '']) ? (string)$_GET['cidade'] : '';
$dias = in_array((int)($_GET['dias'] ?? 7), [1, 7, 30, 90], true) ? (int)($_GET['dias'] ?? 7) : 7;
$origem = in_array($_GET['origem'] ?? '', ['app', 'site'], true) ? (string)$_GET['origem'] : '';
$desde = desde_dias($dias) . ' 00:00:00';

$where = ['criado_em >= ?'];
$params = [$desde];
if ($cidade !== '') { $where[] = 'cidade = ?'; $params[] = $cidade; }
if ($origem !== '') { $where[] = 'origem = ?'; $params[] = $origem; }
$w = implode(' AND ', $where);

$total = $pessoas = $zero = 0;
$sem = $mais = $corrigidas = [];
if (buscas_prontas()) {
    $t = linha("SELECT COUNT(*) AS n, COUNT(DISTINCT quem) AS p, SUM(resultados = 0) AS z FROM buscas WHERE $w", $params);
    [$total, $pessoas, $zero] = [(int)$t['n'], (int)$t['p'], (int)$t['z']];
    // LOWER + collation sem acento: "Pizzaria", "pizzaria" e "pizzária" viram uma linha só
    $sel = "SELECT MIN(termo) AS termo, cidade, COUNT(*) AS vezes, COUNT(DISTINCT quem) AS pessoas, MAX(resultados) AS resultados,
        MAX(corrigido) AS corrigido, MAX(criado_em) AS ultima FROM buscas WHERE $w";
    $sem = linhas("$sel AND resultados = 0 GROUP BY LOWER(termo), cidade ORDER BY pessoas DESC, vezes DESC, ultima DESC LIMIT 200", $params);
    $corrigidas = linhas("$sel AND resultados > 0 AND corrigido IS NOT NULL GROUP BY LOWER(termo), cidade ORDER BY pessoas DESC, ultima DESC LIMIT 100", $params);
    $mais = linhas("$sel AND resultados > 0 GROUP BY LOWER(termo), cidade ORDER BY pessoas DESC, vezes DESC LIMIT 100", $params);
}

$busca_site = fn(string $c, string $termo) => $c !== '' && isset(CIDADES[$c])
    ? url(cidade_web($c) . '/buscar') . '?' . http_build_query(['q' => $termo]) : null;
$nome_c = fn(string $c) => $c !== '' ? nome_cidade($c, true) : '—';
$num = fn(int $n) => number_format($n, 0, ',', '.');

admin_inicio('O que procuram', $admin);
?>
<h1>O que as pessoas procuram</h1>
<p class="discreto">Buscas feitas no site e no app (robôs não contam; no app, o que a pessoa digita aos poucos vira uma busca só).
  <strong>Sem resultado</strong> pode ser palavra que a busca ainda não entende (peça ao Claude para incluir o sinônimo) ou
  um tipo de comércio que falta na cidade: aí é chance de incluir ou de oferecer o Destaque.</p>

<?php if (!buscas_prontas()): ?>
  <p class="aviso erro">Falta aplicar a mudança <b>033-buscas.sql</b> no banco (rode o <code>instalar.sh</code>). Até lá as buscas não são guardadas.</p>
<?php endif ?>

<form method="get" class="filtros-numeros">
  <label>Cidade <select name="cidade"><option value="">Todas</option>
    <?php foreach (TODAS_CIDADES as $slug => $c): ?><option value="<?= h($slug) ?>"<?= $slug === $cidade ? ' selected' : '' ?>><?= h($c[0]) ?></option><?php endforeach ?>
  </select></label>
  <label>Período <select name="dias">
    <?php foreach ([1, 7, 30, 90] as $d): ?><option value="<?= $d ?>"<?= $d === $dias ? ' selected' : '' ?>><?= $d === 1 ? 'Hoje' : "Últimos $d dias" ?></option><?php endforeach ?>
  </select></label>
  <label>Onde <select name="origem"><option value="">Site e app</option>
    <option value="site"<?= $origem === 'site' ? ' selected' : '' ?>>Só site</option><option value="app"<?= $origem === 'app' ? ' selected' : '' ?>>Só app</option>
  </select></label>
  <button class="botao pequeno">Filtrar</button>
</form>

<p><b><?= $num($total) ?></b> buscas de <b><?= $num($pessoas) ?></b> pessoas · <b><?= $num($zero) ?></b> sem resultado<?= $total ? ' (' . round($zero * 100 / $total) . '%)' : '' ?>.</p>

<h2>Sem resultado</h2>
<?php if (!$sem): ?><p class="discreto">Nenhuma no período.</p><?php else: ?>
<div class="tabela-rolar"><table class="tabela">
  <tr><th>Procuraram</th><th>Cidade</th><th>Pessoas</th><th>Vezes</th><th>Última</th><th></th></tr>
  <?php foreach ($sem as $l): $u = $busca_site($l['cidade'], $l['termo']); ?>
  <tr><td><b><?= h($l['termo']) ?></b></td><td><?= h($nome_c($l['cidade'])) ?></td><td><?= (int)$l['pessoas'] ?></td><td><?= (int)$l['vezes'] ?></td>
    <td><?= h(date('d/m H:i', strtotime($l['ultima']))) ?></td><td><?= $u ? '<a href="' . h($u) . '" target="_blank">testar</a>' : '' ?></td></tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>

<h2>Corrigidas pela busca</h2>
<p class="discreto">Escreveram errado e a busca trocou pela palavra mais parecida. Se a troca estiver errada, avise o Claude.</p>
<?php if (!$corrigidas): ?><p class="discreto">Nenhuma no período.</p><?php else: ?>
<div class="tabela-rolar"><table class="tabela">
  <tr><th>Escreveram</th><th>Virou</th><th>Cidade</th><th>Pessoas</th><th>Achou</th><th></th></tr>
  <?php foreach ($corrigidas as $l): $u = $busca_site($l['cidade'], $l['termo']); ?>
  <tr><td><?= h($l['termo']) ?></td><td><b><?= h((string)$l['corrigido']) ?></b></td><td><?= h($nome_c($l['cidade'])) ?></td>
    <td><?= (int)$l['pessoas'] ?></td><td><?= (int)$l['resultados'] ?></td><td><?= $u ? '<a href="' . h($u) . '" target="_blank">testar</a>' : '' ?></td></tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>

<h2>Mais procuradas</h2>
<?php if (!$mais): ?><p class="discreto">Nenhuma no período.</p><?php else: ?>
<div class="tabela-rolar"><table class="tabela">
  <tr><th>Procuraram</th><th>Cidade</th><th>Pessoas</th><th>Vezes</th><th>Achou</th><th></th></tr>
  <?php foreach ($mais as $l): $u = $busca_site($l['cidade'], $l['termo']); ?>
  <tr><td><b><?= h($l['termo']) ?></b></td><td><?= h($nome_c($l['cidade'])) ?></td><td><?= (int)$l['pessoas'] ?></td><td><?= (int)$l['vezes'] ?></td>
    <td><?= (int)$l['resultados'] >= 50 ? '50+' : (int)$l['resultados'] ?></td><td><?= $u ? '<a href="' . h($u) . '" target="_blank">testar</a>' : '' ?></td></tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>
<?php
admin_fim();
