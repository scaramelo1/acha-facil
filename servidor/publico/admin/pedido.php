<?php
// Pedido de um dono: assumir o perfil (depois de confirmar por telefone, libera o link do painel) ou tirar do app
require __DIR__ . '/../../inc/admin.php';
$admin = exigir_admin();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$pe = linha('SELECT * FROM pedidos WHERE id = ?', [$id]);
if (!$pe) { http_response_code(404); exit('Pedido não encontrado.'); }
$p = $pe['profissional_id'] ? linha('SELECT * FROM profissionais WHERE id = ?', [$pe['profissional_id']]) : null;

if (e_post()) {
    exigir_post();
    $acao = (string)($_POST['acao'] ?? '');
    if ($pe['status'] !== 'novo' && $acao !== 'novo_link') { flash('Este pedido já foi resolvido.', 'erro'); ir('admin/pedido.php?id=' . $id); }
    if ($acao === 'liberar' && $p && $pe['tipo'] === 'assumir') {
        $_SESSION['link_novo'] = [$id, liberar_acesso((int)$p['id'], $id, $pe['nome'], $pe['telefone'], (int)$admin['id'])];
        q("UPDATE pedidos SET status = 'feito' WHERE id = ?", [$id]);
        flash('Painel liberado. Mande o link abaixo para o dono (ele só aparece agora).');
    } elseif ($acao === 'novo_link' && $p && $pe['tipo'] === 'assumir' && $pe['status'] === 'feito') {
        $_SESSION['link_novo'] = [$id, liberar_acesso((int)$p['id'], $id, $pe['nome'], $pe['telefone'], (int)$admin['id'])];
        flash('Link novo gerado. Se o antigo não deve mais valer, corte-o na lista de acessos.');
    } elseif ($acao === 'tirar' && $p && $pe['tipo'] === 'remover') {
        q("UPDATE profissionais SET status = 'pausado' WHERE id = ?", [$p['id']]);
        q("UPDATE pedidos SET status = 'feito' WHERE id = ?", [$id]);
        registrar((int)$admin['id'], 'tirou_do_app', (int)$p['id'], 'pedido ' . $id);
        flash($p['nome'] . ' saiu do app.');
        ir('admin/');
    } elseif ($acao === 'feito') {
        q("UPDATE pedidos SET status = 'feito' WHERE id = ?", [$id]);
        registrar((int)$admin['id'], 'pedido_feito', $p ? (int)$p['id'] : null, 'pedido ' . $id);
        flash('Pedido marcado como resolvido.');
        ir('admin/');
    } elseif ($acao === 'recusar') {
        q("UPDATE pedidos SET status = 'recusado' WHERE id = ?", [$id]);
        registrar((int)$admin['id'], 'recusou_pedido', $p ? (int)$p['id'] : null, 'pedido ' . $id . ': ' . (txt('motivo', 300) ?? ''));
        flash('Pedido recusado.');
        ir('admin/');
    }
    ir('admin/pedido.php?id=' . $id);
}

$link = null;
if (($_SESSION['link_novo'][0] ?? null) === $id) { $link = $_SESSION['link_novo'][1]; unset($_SESSION['link_novo']); }

admin_inicio('Pedido', $admin);
?>
<p><a href="./">&larr; Pendências</a></p>
<h1><?= h(TIPO_PEDIDO[$pe['tipo']] ?? $pe['tipo']) ?></h1>

<?php if ($link):
    $msg = "Olá, {$pe['nome']}! Aqui é do Acha Fácil. Este é o link do painel do seu comércio" . ($p ? " ({$p['nome']})" : '') . ":\n$link\n"
         . "Não passe para ninguém. Toda mudança que você fizer passa pela nossa conferência antes de aparecer no app."; ?>
  <div class="quadro">
    <label for="link">Link do painel (só aparece agora)</label>
    <input id="link" value="<?= h($link) ?>" readonly onclick="this.select()">
    <p><?php if ($w = link_whatsapp($pe['telefone'], $msg)): ?><a class="botao whats" href="<?= h($w) ?>" target="_blank" rel="noopener">Mandar pelo WhatsApp</a><?php endif ?></p>
  </div>
<?php endif ?>

<div class="tabela"><table>
  <tr><th>Situação</th><td><?= h(['novo' => 'Novo', 'feito' => 'Resolvido', 'recusado' => 'Recusado'][$pe['status']] ?? $pe['status']) ?></td></tr>
  <tr><th>Comércio</th><td><?= $p ? '<strong>' . h($p['nome']) . '</strong><br>' . h($p['endereco'] ?? '') . ' · ' . h(nome_cidade($p['cidade']))
        . '<br>Telefone do cadastro: ' . h(telefone_formatado($p['telefone'] ?: $p['whatsapp'])) : '<em>Negócio novo (não está no app)</em>' ?></td></tr>
  <tr><th>Quem pediu</th><td><?= h($pe['nome']) ?> · <?= h(telefone_formatado($pe['telefone'])) ?><?= !empty($pe['email']) ? ' · ' . h($pe['email']) : '' ?></td></tr>
  <?php if ($pe['plano']): ?><tr><th>Plano</th><td><?= h(PLANOS_NOME[$pe['plano']] ?? $pe['plano']) ?><?= !empty($pe['mp_status']) ? ' · Mercado Pago: ' . h($pe['mp_status']) : '' ?></td></tr><?php endif ?>
  <?php if ($pe['mensagem']): ?><tr><th>Mensagem</th><td><?= nl2br(h($pe['mensagem'])) ?></td></tr><?php endif ?>
  <tr><th>Quando</th><td><?= h(datahora_br($pe['criado_em'])) ?> · IP <?= h($pe['ip'] ?? '?') ?></td></tr>
</table></div>

<?php if ($pe['status'] === 'novo'): ?>
  <div class="quadro">
    <?php if ($pe['tipo'] === 'assumir'): ?>
      <strong>Antes de liberar:</strong> ligue ou chame no WhatsApp <em>pelo telefone que já está no cadastro do comércio</em>
      (não pelo que a pessoa digitou) e confirme que quem pediu é mesmo o responsável. Com o painel, o dono só
      <em>pede</em> mudanças; tudo continua passando por aqui.
    <?php else: ?>
      <strong>Antes de tirar:</strong> confirme pelo telefone do cadastro que o pedido veio do responsável (evita um
      concorrente tirar o comércio de alguém).
    <?php endif ?>
  </div>
  <div class="decidir">
    <form method="post"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= $id ?>">
      <?php if ($pe['tipo'] === 'assumir' && $p): ?>
        <button class="botao" name="acao" value="liberar">Confirmei: liberar o painel</button>
      <?php elseif ($pe['tipo'] === 'remover' && $p): ?>
        <button class="botao" name="acao" value="tirar">Confirmei: tirar do app</button>
      <?php else: ?>
        <p class="discreto">Negócio novo: por enquanto o cadastro é feito à mão no banco.</p>
        <button class="botao" name="acao" value="feito">Marcar como resolvido</button>
      <?php endif ?>
    </form>
    <form method="post" onsubmit="return confirm('Recusar este pedido?')"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= $id ?>">
      <label for="motivo">Motivo (fica no registro)</label>
      <input id="motivo" name="motivo" maxlength="300">
      <p><button class="botao contorno" name="acao" value="recusar">Recusar</button></p>
    </form>
  </div>
<?php elseif ($pe['status'] === 'feito' && $pe['tipo'] === 'assumir' && $p && !$link): ?>
  <form method="post" onsubmit="return confirm('Gerar outro link de painel para este dono?')"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= $id ?>">
    <p><button class="botao contorno" name="acao" value="novo_link">Gerar outro link (o dono perdeu o anterior)</button></p>
  </form>
<?php endif ?>
<?php admin_fim();
