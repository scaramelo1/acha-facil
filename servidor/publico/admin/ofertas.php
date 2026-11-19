<?php
// Ofertas da semana (19/11, inc/ofertas.php): conferir as pagas (aprovar = no ar pelos dias escolhidos; recusar com motivo =
// o dono corrige e manda de novo sem pagar), ver as que estão no ar (encerrar) e o histórico.
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/ofertas.php';
$admin = exigir_admin();
if (!ofertas_prontas()) { admin_inicio('Ofertas', $admin); echo '<h1>Ofertas</h1><p>Falta aplicar a mudança 031 (bash servidor/instalar.sh).</p>'; admin_fim(); exit; }

if (e_post()) {
    exigir_post();
    $id = (int)($_POST['id'] ?? 0);
    try {
        match ((string)($_POST['acao'] ?? '')) {
            'aprovar' => aprovar_oferta($id, (int)$admin['id']),
            'recusar' => recusar_oferta($id, (int)$admin['id'], (string)($_POST['motivo'] ?? '')),
            'encerrar' => encerrar_oferta($id, (int)$admin['id']),
            default => null,
        };
        flash(['aprovar' => 'Oferta no ar. O dono recebeu o aviso.', 'recusar' => 'Oferta recusada. O dono recebeu o motivo.',
            'encerrar' => 'Oferta encerrada.'][$_POST['acao']] ?? 'Feito.');
    } catch (RuntimeException $e) { flash($e->getMessage(), 'erro'); }
    ir('admin/ofertas.php');
}

$sel = 'SELECT o.*, p.nome AS comercio, p.cidade, ' . SQL_DESTAQUE . ' AS destaque_ok FROM ofertas o JOIN profissionais p ON p.id = o.profissional_id';
$analise = linhas("$sel WHERE o.status = 'em_analise' ORDER BY o.pago_em");
$no_ar = linhas("$sel WHERE o.status = 'no_ar' AND o.fim > NOW() ORDER BY o.fim");
$historico = linhas("$sel WHERE NOT (o.status = 'em_analise' OR (o.status = 'no_ar' AND o.fim > NOW())) ORDER BY o.id DESC LIMIT 40");
$base = config()['url'];

admin_inicio('Ofertas da semana', $admin);
?>
<h1>Ofertas da semana</h1>
<?php mostrar_flash(); ?>
<p class="discreto">Benefício do Destaque: <?= h(oferta_preco_texto()) ?> por oferta (pagamento único), no ar por até <?= OFERTA_MAX_DIAS ?> dias na ficha
  e na página da cidade. Os dias contam a partir da aprovação.</p>

<h2>Para conferir (<?= count($analise) ?>)</h2>
<?php if (!$analise): ?><p class="discreto">Nenhuma oferta esperando.</p><?php endif ?>
<?php foreach ($analise as $o): ?>
  <div class="quadro">
    <p><strong><a href="<?= h(url_ficha(['id' => $o['profissional_id'], 'nome' => $o['comercio'], 'cidade' => $o['cidade']])) ?>" target="_blank"><?= h($o['comercio']) ?></a></strong>
      · <?= h(nome_cidade($o['cidade'], true)) ?> · pago em <?= h(datahora_br((string)$o['pago_em'])) ?> (<?= h(dinheiro((string)$o['valor'])) ?>)
      · <?= (int)$o['dias'] ?> dia<?= $o['dias'] > 1 ? 's' : '' ?>
      <?php if (!$o['destaque_ok']): ?><br><span class="aviso erro">O Destaque deste comércio não está em dia: se aprovar, a oferta não aparece.</span><?php endif ?></p>
    <?= oferta_quadro_ficha(array_merge($o, ['fim' => date('Y-m-d H:i:s', strtotime('+' . (int)$o['dias'] . ' days'))])) ?>
    <form method="post" class="linha-botoes"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
      <button class="botao" name="acao" value="aprovar">✓ Aprovar e pôr no ar</button></form>
    <form method="post"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
      <label for="motivo<?= (int)$o['id'] ?>">Recusar (o dono corrige e manda de novo sem pagar)</label>
      <input id="motivo<?= (int)$o['id'] ?>" name="motivo" maxlength="300" placeholder="Ex.: tire o telefone do texto" required>
      <button class="botao contorno" name="acao" value="recusar">Recusar com este motivo</button></form>
  </div>
<?php endforeach ?>

<h2>No ar (<?= count($no_ar) ?>)</h2>
<?php if (!$no_ar): ?><p class="discreto">Nenhuma oferta no ar agora.</p><?php else: ?>
<div class="tabela"><table><thead><tr><th>Comércio</th><th>Oferta</th><th>Fica</th><th></th></tr></thead><tbody>
<?php foreach ($no_ar as $o): ?>
  <tr><td><?= h($o['comercio']) ?><br><small><?= h(nome_cidade($o['cidade'], true)) ?></small></td><td><?= h($o['titulo']) ?></td>
    <td><?= h(oferta_validade($o)) ?></td>
    <td><form method="post"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
      <button class="botao pequeno contorno" name="acao" value="encerrar" onclick="return confirm('Tirar esta oferta do ar?')">Encerrar</button></form></td></tr>
<?php endforeach ?>
</tbody></table></div>
<?php endif ?>

<h2>Histórico</h2>
<?php if (!$historico): ?><p class="discreto">Nada ainda.</p><?php else: ?>
<div class="tabela"><table><thead><tr><th>Quando</th><th>Comércio</th><th>Oferta</th><th>Situação</th><th>Pago</th></tr></thead><tbody>
<?php foreach ($historico as $o): $acabou = $o['status'] === 'no_ar'; ?>
  <tr><td><?= h(datahora_br($o['criado_em'])) ?></td><td><?= h($o['comercio']) ?></td><td><?= h($o['titulo']) ?></td>
    <td><?= $acabou ? 'Terminou em ' . h(datahora_br($o['fim'])) : h(OFERTA_STATUS[$o['status']]) ?><?= $o['motivo'] ? '<br><small>' . h($o['motivo']) . '</small>' : '' ?></td>
    <td><?= $o['pago_em'] ? h(dinheiro((string)$o['valor'])) : '—' ?></td></tr>
<?php endforeach ?>
</tbody></table></div>
<?php endif ?>
<?php admin_fim();
