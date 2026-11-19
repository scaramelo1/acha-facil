<?php
// Ofertas da semana (19/11, inc/ofertas.php): conferir as pagas (aprovar = no ar pelos dias escolhidos; recusar com motivo =
// o dono corrige e manda de novo sem pagar), ver as que estão no ar (encerrar) e o histórico.
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/ofertas.php';
$admin = exigir_admin();
if (!ofertas_prontas()) { admin_inicio('Ofertas', $admin); echo '<h1>Ofertas</h1><p>Falta aplicar a mudança 031 (bash servidor/instalar.sh).</p>'; admin_fim(); exit; }

if (e_post() && ($_POST['acao'] ?? '') === 'salvar') {
    // 19/11 (pedido do usuário): editar a oferta direto pelo admin
    exigir_post();
    $id = (int)($_POST['id'] ?? 0);
    [$d, $erro] = oferta_do_post($_POST);
    $pid = (int)valor('SELECT profissional_id FROM ofertas WHERE id = ?', [$id]);
    $foto = null;
    if (!$erro && $pid && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try { $foto = salvar_foto_oferta($_FILES['foto'], $pid); } catch (RuntimeException $e) { $erro = 'Foto: ' . $e->getMessage(); }
    }
    if ($erro) { flash($erro, 'erro'); ir('admin/ofertas.php?editar=' . $id); }
    try { editar_oferta_admin($id, (int)$admin['id'], $d, $foto, !empty($_POST['tirar_foto'])); flash('Oferta salva. Já vale no site e no app.'); }
    catch (RuntimeException $e) { if ($foto) apagar_foto($foto); flash($e->getMessage(), 'erro'); }
    ir('admin/ofertas.php');
}
if (e_post()) {
    exigir_post();
    $id = (int)($_POST['id'] ?? 0);
    try {
        match ((string)($_POST['acao'] ?? '')) {
            'aprovar' => aprovar_oferta($id, (int)$admin['id']),
            'recusar' => recusar_oferta($id, (int)$admin['id'], (string)($_POST['motivo'] ?? '')),
            'encerrar' => encerrar_oferta($id, (int)$admin['id']),
            'liberar' => liberar_oferta_sem_pagamento($id, (int)$admin['id']),
            default => null,
        };
        flash(['aprovar' => 'Oferta no ar. O dono recebeu o aviso.', 'recusar' => 'Oferta recusada. O dono recebeu o motivo.',
            'encerrar' => 'Oferta encerrada.', 'liberar' => 'Oferta no ar sem pagamento (teste/cortesia).'][$_POST['acao']] ?? 'Feito.');
    } catch (RuntimeException $e) { flash($e->getMessage(), 'erro'); }
    ir('admin/ofertas.php');
}

$sel = 'SELECT o.*, p.nome AS comercio, p.cidade, ' . SQL_DESTAQUE . ' AS destaque_ok FROM ofertas o JOIN profissionais p ON p.id = o.profissional_id';
$analise = linhas("$sel WHERE o.status = 'em_analise' ORDER BY o.pago_em");
$esperando = linhas("$sel WHERE o.status IN ('aguardando_pagamento', 'recusada') ORDER BY o.id DESC");
$editar = isset($_GET['editar']) ? linha("$sel WHERE o.id = ? AND o.status <> 'cancelada'", [(int)$_GET['editar']]) : null;
$no_ar = linhas("$sel WHERE o.status = 'no_ar' AND o.fim > NOW() ORDER BY o.fim");
$historico = linhas("$sel WHERE NOT (o.status IN ('em_analise', 'aguardando_pagamento', 'recusada') OR (o.status = 'no_ar' AND o.fim > NOW())) ORDER BY o.id DESC LIMIT 40");
$base = config()['url'];

admin_inicio('Ofertas da semana', $admin);
?>
<h1>Ofertas da semana</h1>
<?php mostrar_flash(); ?>
<p class="discreto">Benefício do Destaque: <?= h(oferta_preco_texto()) ?> por oferta (pagamento único), no ar por até <?= OFERTA_MAX_DIAS ?> dias na ficha
  e na página da cidade. Os dias contam a partir da aprovação.</p>

<?php if ($editar): $fd = fn($v) => $v === null ? '' : number_format((float)$v, 2, ',', ''); ?>
<div class="quadro" id="editar">
  <h2 style="margin-top:0">✏️ Editar oferta de <?= adm_comercio((int)$editar['profissional_id'], $editar['comercio'], $editar['cidade']) ?></h2>
  <p class="discreto">Situação: <?= h(OFERTA_STATUS[$editar['status']] ?? $editar['status']) ?><?= $editar['status'] === 'no_ar' ? ' · ' . h(oferta_validade($editar)) . ' (mudar os dias refaz o prazo a partir de quando entrou no ar)' : '' ?></p>
  <form method="post" enctype="multipart/form-data"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$editar['id'] ?>">
    <label for="titulo">Título <small class="discreto">(até <?= OFERTA_TITULO_MAX ?> letras)</small></label><input id="titulo" name="titulo" maxlength="<?= OFERTA_TITULO_MAX ?>" data-contar required value="<?= h($editar['titulo']) ?>">
    <label for="texto">Texto <small class="discreto">(até <?= OFERTA_TEXTO_MAX ?> letras)</small></label><textarea id="texto" name="texto" maxlength="<?= OFERTA_TEXTO_MAX ?>" data-contar rows="4"><?= h((string)$editar['texto']) ?></textarea>
    <div class="linha-botoes">
      <span><label for="preco_de">Preço "de" (opcional)</label><input id="preco_de" name="preco_de" inputmode="decimal" value="<?= h($fd($editar['preco_de'])) ?>" placeholder="59,90"></span>
      <span><label for="preco_por">Preço "por" (opcional)</label><input id="preco_por" name="preco_por" inputmode="decimal" value="<?= h($fd($editar['preco_por'])) ?>" placeholder="49,90"></span>
      <span><label for="dias">Dias no ar</label><select id="dias" name="dias"><?php for ($i = 1; $i <= OFERTA_MAX_DIAS; $i++): ?><option value="<?= $i ?>"<?= $i === (int)$editar['dias'] ? ' selected' : '' ?>><?= $i ?></option><?php endfor ?></select></span>
    </div>
    <label for="foto">Foto (opcional) <small class="discreto">quadrada 800 x 800 px, mínimo <?= OFERTA_FOTO_MIN ?> (o servidor corta o meio)</small></label><input id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp">
    <div id="foto-previa" class="foto-previa" hidden><img alt=""><small></small></div>
    <?php if ($editar['foto']): ?><p><img src="<?= h(foto_url($editar['foto'])) ?>" alt="" style="max-width:220px;border-radius:12px"></p>
      <label class="marcar"><input type="checkbox" name="tirar_foto" value="1"> Tirar a foto atual</label><?php endif ?>
    <p class="linha-botoes"><button class="botao" name="acao" value="salvar">Salvar</button> <a class="botao contorno" href="ofertas.php">Cancelar</a></p>
  </form>
  <?= oferta_campos_ajuda_js() ?>
</div>
<?php endif ?>

<h2>Esperando pagamento (<?= count($esperando) ?>)</h2>
<?php if (!$esperando): ?><p class="discreto">Nenhuma.</p><?php endif ?>
<?php foreach ($esperando as $o): ?>
  <div class="quadro">
    <p><strong><?= adm_comercio((int)$o['profissional_id'], $o['comercio'], $o['cidade']) ?></strong> · <?= adm_cidade($o['cidade'], null, true) ?>
      · <?= h(OFERTA_STATUS[$o['status']]) ?> · criada em <?= h(datahora_br($o['criado_em'])) ?> · <?= (int)$o['dias'] ?> dia<?= $o['dias'] > 1 ? 's' : '' ?>
      <?php if (!$o['destaque_ok']): ?><br><span class="aviso erro">O Destaque deste comércio não está em dia: no ar, a oferta não aparece.</span><?php endif ?></p>
    <?= oferta_quadro_ficha(array_merge($o, ['fim' => date('Y-m-d H:i:s', strtotime('+' . (int)$o['dias'] . ' days'))])) ?>
    <form method="post" class="linha-botoes"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
      <a class="botao contorno" href="?editar=<?= (int)$o['id'] ?>#editar">✏️ Editar</a>
      <button class="botao" name="acao" value="liberar" onclick="return confirm('Pôr no ar SEM pagamento (teste ou cortesia)? O dono recebe o aviso de oferta no ar.')">Pôr no ar sem pagamento</button></form>
  </div>
<?php endforeach ?>

<h2>Para conferir (<?= count($analise) ?>)</h2>
<?php if (!$analise): ?><p class="discreto">Nenhuma oferta esperando.</p><?php endif ?>
<?php foreach ($analise as $o): ?>
  <div class="quadro">
    <p><strong><?= adm_comercio((int)$o['profissional_id'], $o['comercio'], $o['cidade']) ?></strong>
      · <?= adm_cidade($o['cidade'], null, true) ?> · pago em <?= h(datahora_br((string)$o['pago_em'])) ?> (<?= h(dinheiro((string)$o['valor'])) ?>)
      · <?= (int)$o['dias'] ?> dia<?= $o['dias'] > 1 ? 's' : '' ?>
      <?php if (!$o['destaque_ok']): ?><br><span class="aviso erro">O Destaque deste comércio não está em dia: se aprovar, a oferta não aparece.</span><?php endif ?></p>
    <?= oferta_quadro_ficha(array_merge($o, ['fim' => date('Y-m-d H:i:s', strtotime('+' . (int)$o['dias'] . ' days'))])) ?>
    <form method="post" class="linha-botoes"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
      <a class="botao contorno" href="?editar=<?= (int)$o['id'] ?>#editar">✏️ Editar</a>
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
  <tr><td><?= adm_comercio((int)$o['profissional_id'], $o['comercio'], $o['cidade']) ?><br><small><?= adm_cidade($o['cidade'], null, true) ?></small></td><td><?= h($o['titulo']) ?></td>
    <td><?= h(oferta_validade($o)) ?></td>
    <td><form method="post" class="linha-botoes"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
      <a class="botao pequeno contorno" href="?editar=<?= (int)$o['id'] ?>#editar">✏️ Editar</a>
      <button class="botao pequeno contorno" name="acao" value="encerrar" onclick="return confirm('Tirar esta oferta do ar?')">Encerrar</button></form></td></tr>
<?php endforeach ?>
</tbody></table></div>
<?php endif ?>

<h2>Histórico</h2>
<?php if (!$historico): ?><p class="discreto">Nada ainda.</p><?php else: ?>
<div class="tabela"><table><thead><tr><th>Quando</th><th>Comércio</th><th>Oferta</th><th>Situação</th><th>Pago</th></tr></thead><tbody>
<?php foreach ($historico as $o): $acabou = $o['status'] === 'no_ar'; ?>
  <tr><td><?= h(datahora_br($o['criado_em'])) ?></td><td><?= adm_comercio((int)$o['profissional_id'], $o['comercio'], $o['cidade']) ?></td><td><?= h($o['titulo']) ?></td>
    <td><?= $acabou ? 'Terminou em ' . h(datahora_br($o['fim'])) : h(OFERTA_STATUS[$o['status']]) ?><?= $o['motivo'] ? '<br><small>' . h($o['motivo']) . '</small>' : '' ?></td>
    <td><?= $o['pago_em'] ? h(dinheiro((string)$o['valor'])) : '—' ?></td></tr>
<?php endforeach ?>
</tbody></table></div>
<?php endif ?>
<?php admin_fim();
