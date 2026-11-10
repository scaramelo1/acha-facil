<?php
// Números de uma ficha (passo 20): dia a dia, de onde vieram, onde apareceu e contatos; link para o relatório de 1 folha.
require __DIR__ . '/../../inc/admin.php';
$admin = exigir_admin();
exigir_numeros_prontos();

$p = linha('SELECT p.*, c.nome AS categoria FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE p.id = ?', [(int)($_GET['id'] ?? 0)]);
if (!$p) { flash('Comércio não encontrado.', 'erro'); ir('admin/numeros.php'); }
$dias = in_array((int)($_GET['dias'] ?? 30), [7, 30, 90], true) ? (int)($_GET['dias'] ?? 30) : 30;
$desde = desde_dias($dias);
$n = numeros_ficha((int)$p['id'], $desde);
$pos = posicao_ficha($p, $desde, hoje());
$maior = max([1, ...array_values(array_map(fn($d) => max($d["aparicoes"], $d["visualizacoes"]), $n["por_dia"]))]);

admin_inicio('Números · ' . $p['nome'], $admin);
?>
<p><a href="numeros.php">← Números das fichas</a></p>
<h1><?= h($p['nome']) ?></h1>
<p class="discreto"><?= h($p['tipo'] ?: $p['categoria']) ?> · <?= h(nome_cidade($p['cidade'])) ?><?= $p['bairro'] ? ' · ' . h($p['bairro']) : '' ?>
  · <?php foreach ([7, 30, 90] as $d): ?><a href="?id=<?= (int)$p['id'] ?>&amp;dias=<?= $d ?>"><?= $d === $dias ? "<strong>$d dias</strong>" : "$d dias" ?></a> <?php endforeach ?></p>
<p><a class="botao pequeno" href="relatorio.php?id=<?= (int)$p['id'] ?>&amp;dias=<?= $dias ?>" target="_blank">Relatório para o comerciante</a></p>

<div class="numeros-resumo">
  <div><strong><?= num($n['aparicoes']) ?></strong><span>aparições</span></div>
  <div><strong><?= num($n['visualizacoes']) ?></strong><span>visualizações</span></div>
  <div><strong><?= num($n['pessoas']) ?></strong><span>pessoas</span></div>
  <div><strong><?= num($n['contatos']) ?></strong><span>contatos (<?= pct($n['contatos'], $n['visualizacoes']) ?>)</span></div>
  <?php if ($pos): ?><div><strong><?= $pos['posicao'] ?>º</strong><span>de <?= num($pos['de']) ?> em <?= h($pos['grupo']) ?></span></div><?php endif ?>
</div>

<h2>Dia a dia</h2>
<p class="discreto"><span class="barra-legenda aparicoes"></span> aparições · <span class="barra-legenda vistas"></span> visualizações</p>
<div class="barras-dias" role="img" aria-label="Aparições e visualizações por dia">
  <?php foreach ($n['por_dia'] as $dia => $d): ?>
  <div class="dia" title="<?= h(data_br($dia)) ?>: <?= $d['aparicoes'] ?> aparições, <?= $d['visualizacoes'] ?> visualizações, <?= $d['pessoas'] ?> pessoas, <?= $d['contatos'] ?> contatos">
    <span class="aparicoes" style="height:<?= round(100 * $d['aparicoes'] / $maior) ?>%"></span>
    <span class="vistas" style="height:<?= round(100 * $d['visualizacoes'] / $maior) ?>%"></span>
  </div>
  <?php endforeach ?>
</div>
<p class="discreto"><?= h(data_br($desde)) ?> a <?= h(data_br(hoje())) ?></p>

<div class="colunas-numeros">
  <div>
    <h2>De onde abriram</h2>
    <?php if (!$n['origens']): ?><p class="discreto">Ninguém abriu no período.</p><?php else: ?>
    <div class="tabela"><table>
      <?php foreach ($n['origens'] as $o => $t): ?><tr><td><?= h(ORIGENS[$o] ?? $o) ?></td><td><?= num($t) ?></td><td><?= pct($t, $n['visualizacoes']) ?></td></tr><?php endforeach ?>
    </table></div>
    <?php endif ?>
  </div>
  <div>
    <h2>Onde apareceu</h2>
    <?php if (!$n['onde']): ?><p class="discreto">Não apareceu em listas no período.</p><?php else: ?>
    <div class="tabela"><table>
      <?php foreach ($n['onde'] as $o => $t): ?><tr><td><?= h(ONDE_APARICAO[$o] ?? $o) ?></td><td><?= num($t) ?></td></tr><?php endforeach ?>
    </table></div>
    <?php endif ?>
  </div>
  <div>
    <h2>Contatos</h2>
    <div class="tabela"><table>
      <?php foreach (TIPOS_CONTATO as $k => $nome): ?><tr><td><?= $nome ?></td><td><?= num($n['por_tipo'][$k] ?? 0) ?></td></tr><?php endforeach ?>
    </table></div>
  </div>
</div>

<h2>Tabela por dia</h2>
<div class="tabela"><table>
  <tr><th>Dia</th><th>Aparições</th><th>Visualizações</th><th>Pessoas</th><th>Contatos</th></tr>
  <?php foreach (array_reverse($n['por_dia'], true) as $dia => $d): if (!array_sum($d)) continue; ?>
  <tr><td><?= h(data_br($dia)) ?></td><td><?= num($d['aparicoes']) ?></td><td><?= num($d['visualizacoes']) ?></td><td><?= num($d['pessoas']) ?></td><td><?= num($d['contatos']) ?></td></tr>
  <?php endforeach ?>
</table></div>
<?php admin_fim();
