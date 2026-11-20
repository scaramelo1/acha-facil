<?php
// Conversão da abordagem e projeção de ganhos (20/11, passo 118). Conta do zero a partir de CONVERSAO_INICIO (inc/conversao.php).
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/mercadopago.php';
require_once __DIR__ . '/../../inc/conversao.php';
$admin = exigir_admin();

$d = conversao_dados();
$f = $d['funil'];
$ritmo_medido = (int)round($f['abordados'] * 30 / $d['dias']);
$ritmo = max(0, min(5000, (int)($_GET['ritmo'] ?? 0))) ?: ($ritmo_medido ?: 300);
$ads = max(0, min(100000, (float)str_replace(',', '.', (string)($_GET['ads'] ?? '0'))));
$pr = conversao_projecao($d, $ritmo, $ads);

$pct = fn(float $t) => number_format($t * 100, 1, ',', '.') . '%';
$rs = fn(float $v) => 'R$ ' . number_format($v, 0, ',', '.');
$de = fn(int $a, int $b) => $b ? ' (' . number_format($a / $b * 100, 1, ',', '.') . '%)' : '';
$poucos = $f['abordados'] < CONVERSAO_MIN_ABORDADOS;
$mes_nome = fn(int $m) => ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'][((int)date('n') + $m - 1) % 12];

admin_inicio('Conversão e projeção', $admin);
?>
<h1>📈 Conversão e projeção de ganhos</h1>
<p class="discreto">Conta <strong>do zero desde <?= h(data_br(CONVERSAO_INICIO)) ?></strong>: pagamentos e chamadas de antes não entram.
  Cada <strong>✓ Chamei</strong> do <a href="chamar.php">📞 Quem chamar hoje</a> é uma abordagem; se o comércio assumir ou pagar
  <em>depois</em> da chamada, conta como conversão. O que chega sem chamada é <strong>orgânico</strong>.
  Dia <?= num($d['dias']) ?> de 30 da primeira medição.</p>

<h2>Funil da abordagem</h2>
<div class="numeros-resumo">
  <div><strong><?= num($f['abordados']) ?></strong><span>comércios chamados</span></div>
  <div><strong><?= num($f['assumiram']) ?></strong><span>assumiram a ficha<?= $de($f['assumiram'], $f['abordados']) ?></span></div>
  <div><strong><?= num($f['basico']) ?></strong><span>pagaram Básico<?= $de($f['basico'], $f['abordados']) ?></span></div>
  <div><strong><?= num($f['destaque']) ?></strong><span>pagaram Destaque<?= $de($f['destaque'], $f['abordados']) ?></span></div>
  <div><strong><?= num($f['site']) ?></strong><span>pagaram Destaque + Site<?= $de($f['site'], $f['abordados']) ?></span></div>
</div>
<p class="discreto">Orgânico no mesmo período (sem chamada antes): <?= num($d['organico']['assumiram']) ?> assumiram,
  <?= num($d['organico']['basico']) ?> Básico, <?= num($d['organico']['destaque']) ?> Destaque, <?= num($d['organico']['site']) ?> Destaque + Site.
  Ritmo medido: <strong><?= num($ritmo_medido) ?> chamadas por mês</strong>. Cortesia (cupom de 100%) não conta como pagamento.</p>
<p class="discreto">Renovação do Destaque: <?= $d['destaques_35']
  ? num($d['renovaram']) . ' de ' . num($d['destaques_35']) . ' Destaques pagos há 35+ dias continuam em dia.'
  : 'ainda não há Destaque pago há 35+ dias.' ?>
  <?= $pr['cancel_medido'] ? 'Cancelamento medido.' : 'Até ter 3 deles, o cancelamento é suposto: 25% ao mês no pessimista e 15% no normal.' ?></p>

<h2>Projeção de 12 meses</h2>
<?php if ($poucos): ?>
<div class="aviso"><?= $f['abordados'] ? 'Só ' . num($f['abordados']) . ' chamadas' : 'Nenhuma chamada ainda' ?>: a projeção fica
  confiável com pelo menos <?= CONVERSAO_MIN_ABORDADOS ?> e de verdade no fim dos 30 dias. Até lá, os números abaixo mudam muito a cada venda.</div>
<?php endif ?>
<form method="get" class="filtros-numeros">
  <label>Chamadas por mês <input type="number" name="ritmo" min="0" max="5000" value="<?= (int)$ritmo ?>" style="width:90px"></label>
  <label>AdSense por mês hoje (R$) <input type="text" name="ads" value="<?= h(number_format($ads, 2, ',', '')) ?>" style="width:90px"></label>
  <button class="botao pequeno">Recalcular</button>
  <a class="botao pequeno contorno" href="conversao.php">Usar o medido</a>
</form>
<p class="discreto"><strong>Normal</strong> = a taxa medida. <strong>Pessimista</strong> = o menor valor provável da taxa com os dados
  que temos (limite de baixo de Wilson, ~80%), metade do orgânico e o cancelamento mais alto. Valores líquidos, já sem ~5% do
  Mercado Pago, sem os custos (VPS, domínio, lojas). O AdSense entra fixo, sem crescimento.</p>

<div class="tabela"><table>
  <tr><th></th><th>Pessimista</th><th>Normal</th></tr>
  <?php foreach (['basico' => 'Básico por chamada', 'destaque' => 'Destaque por chamada', 'site' => 'Destaque + Site por chamada'] as $k => $r): ?>
  <tr><td><?= h($r) ?></td><td><?= $pct($pr['pessimista']['taxas'][$k]) ?></td><td><?= $pct($pr['normal']['taxas'][$k]) ?></td></tr>
  <?php endforeach ?>
  <tr><td>Destaques novos por mês</td><td><?= number_format($pr['pessimista']['novos']['destaque'], 1, ',', '') ?></td><td><?= number_format($pr['normal']['novos']['destaque'], 1, ',', '') ?></td></tr>
  <tr><td>Cancelamento do Destaque por mês</td><td><?= $pct($pr['pessimista']['cancel']) ?></td><td><?= $pct($pr['normal']['cancel']) ?></td></tr>
</table></div>

<div class="tabela"><table>
  <tr><th>Mês</th><th>Pess.: Destaques</th><th>Pess.: no mês</th><th>Pess.: acumulado</th><th>Normal: Destaques</th><th>Normal: no mês</th><th>Normal: acumulado</th></tr>
  <?php for ($i = 0; $i < 12; $i++): $p = $pr['pessimista']['meses'][$i]; $n = $pr['normal']['meses'][$i]; ?>
  <tr><td><?= $i + 1 ?> (<?= $mes_nome($i + 1) ?>)</td>
    <td><?= num(round($p['destaque'] + $p['site'])) ?></td><td><?= $rs($p['liquido']) ?></td><td><?= $rs($p['acumulado']) ?></td>
    <td><?= num(round($n['destaque'] + $n['site'])) ?></td><td><strong><?= $rs($n['liquido']) ?></strong></td><td><?= $rs($n['acumulado']) ?></td></tr>
  <?php endfor ?>
</table></div>
<?php $p12 = $pr['pessimista']['meses'][11]; $n12 = $pr['normal']['meses'][11]; ?>
<div class="numeros-resumo">
  <div><strong><?= $rs($p12['liquido']) ?></strong><span>por mês no 12º mês (pessimista)</span></div>
  <div><strong><?= $rs($n12['liquido']) ?></strong><span>por mês no 12º mês (normal)</span></div>
  <div><strong><?= $rs($p12['acumulado']) ?></strong><span>no 1º ano (pessimista)</span></div>
  <div><strong><?= $rs($n12['acumulado']) ?></strong><span>no 1º ano (normal)</span></div>
</div>
<?php
admin_fim();
