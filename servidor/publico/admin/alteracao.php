<?php
// Conferir uma alteração pedida pelo dono: como está no app x como ficaria. Aprovar aplica; recusar descarta.
require __DIR__ . '/../../inc/admin.php';
$admin = exigir_admin();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if (e_post()) {
    exigir_post();
    try {
        if (($_POST['decisao'] ?? '') === 'aprovar') {
            aprovar_alteracao($id, (int)$admin['id']);
            flash('Alteração aprovada: já está no app.');
        } else {
            $motivo = txt('motivo', 500);
            if (!$motivo) { flash('Escreva o motivo da recusa (o dono vê no painel).', 'erro'); ir('admin/alteracao.php?id=' . $id); }
            recusar_alteracao($id, (int)$admin['id'], $motivo);
            flash('Alteração recusada.');
        }
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'erro');
    }
    ir('admin/');
}

$a = linha('SELECT a.*, d.nome AS dono, d.telefone AS dono_tel, ad.nome AS admin_nome FROM alteracoes a
    LEFT JOIN acessos_dono d ON d.id = a.acesso_id LEFT JOIN admins ad ON ad.id = a.admin_id WHERE a.id = ?', [$id]);
if (!$a) { http_response_code(404); exit('Alteração não encontrada.'); }
$p = linha('SELECT * FROM profissionais WHERE id = ?', [$a['profissional_id']]);
$novos = json_decode($a['novos'], true) ?: [];
$antes = json_decode($a['anteriores'], true) ?: [];

admin_inicio('Conferir alteração', $admin);
?>
<p><a href="./">&larr; Pendências</a></p>
<h1><?= h($p['nome']) ?></h1>
<p class="discreto"><?= h(nome_cidade($p['cidade'])) ?> · pedido por <?= h($a['dono'] ?? '?') ?>
  <?= $a['dono_tel'] ? '(' . h(telefone_formatado($a['dono_tel'])) . ')' : '' ?> em <?= h(datahora_br($a['criado_em'])) ?>
  · <a href="<?= h(url_publica('busca.php?q=' . rawurlencode($p['nome']))) ?>" target="_blank" rel="noopener">ver no site</a></p>

<?php if ($a['status'] !== 'pendente'): ?>
  <div class="aviso <?= $a['status'] === 'aprovada' ? 'ok' : 'erro' ?>">
    <?= $a['status'] === 'aprovada' ? 'Aprovada' : 'Recusada' ?> por <?= h($a['admin_nome'] ?? '?') ?> em <?= h(datahora_br($a['decidido_em'])) ?>
    <?= $a['motivo'] ? ': ' . h($a['motivo']) : '' ?>
  </div>
<?php endif ?>

<div class="tabela"><table class="comparar">
  <tr><th>Campo</th><th>Como está no app</th><th>Como vai ficar</th></tr>
  <?php foreach ($novos as $c => $v):
      $agora = $p[$c] === null ? null : (string)$p[$c];
      $mudou_depois = array_key_exists($c, $antes) && $antes[$c] !== $agora; ?>
  <tr>
    <th><?= h(CAMPOS_DONO[$c][0] ?? $c) ?></th>
    <td class="antes"><?= valor_campo_html($c, $agora) ?><?= $mudou_depois && $a['status'] === 'pendente' ? '<br><span class="discreto">(mudou depois do pedido)</span>' : '' ?></td>
    <td class="depois"><?= valor_campo_html($c, $v === null ? null : (string)$v) ?></td>
  </tr>
  <?php endforeach ?>
</table></div>

<?php if ($a['status'] === 'pendente'): ?>
<div class="quadro">
  <strong>Antes de aprovar, confira:</strong> telefone e WhatsApp são mesmo do comércio? O endereço existe? A foto é do
  próprio comércio (sem pessoas sem autorização, sem logo de outra empresa)? Não tem palavrão, propaganda enganosa ou
  link estranho?
</div>
<div class="decidir">
  <form method="post">
    <?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
    <button class="botao" name="decisao" value="aprovar">Aprovar e publicar</button>
  </form>
  <form method="post">
    <?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
    <label for="motivo">Motivo da recusa (o dono vê)</label>
    <textarea id="motivo" name="motivo" rows="2" maxlength="500" required></textarea>
    <p><button class="botao contorno" name="decisao" value="recusar">Recusar</button></p>
  </form>
</div>
<?php endif ?>
<?php admin_fim();
