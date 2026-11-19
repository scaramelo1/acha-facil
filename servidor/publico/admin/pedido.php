<?php
// Pedido de um dono: assumir o perfil (depois de confirmar por telefone, libera o link do painel) ou tirar do app
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/avisos.php';
require_once __DIR__ . '/../../inc/mercadopago.php';   // pedido_pago()
$admin = exigir_admin();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$pe = linha('SELECT * FROM pedidos WHERE id = ?', [$id]);
if (!$pe) { http_response_code(404); exit('Pedido não encontrado.'); }
$p = $pe['profissional_id'] ? linha('SELECT * FROM profissionais WHERE id = ?', [$pe['profissional_id']]) : null;

if (e_post()) {
    exigir_post();
    $acao = (string)($_POST['acao'] ?? '');
    if (in_array($acao, ['visto', 'desfazer_visto'], true) && $pe['status'] === 'novo') {   // passo 89
        require_once __DIR__ . '/../../inc/pedidos-vistos.php';
        pedido_marcar_visto($id, $acao === 'visto');
        registrar((int)$admin['id'], $acao === 'visto' ? 'pedido_visto' : 'pedido_visto_desfeito', $p ? (int)$p['id'] : null, 'pedido ' . $id);
        flash($acao === 'visto' ? 'Marcado como visto: saiu da contagem de pendências (volta sozinho se for pago).' : 'Pedido voltou para as pendências.');
        ir($acao === 'visto' ? 'admin/' : 'admin/pedido.php?id=' . $id);
    }
    if ($pe['status'] !== 'novo' && $acao !== 'novo_link') { flash('Este pedido já foi resolvido.', 'erro'); ir('admin/pedido.php?id=' . $id); }
    if ($acao === 'liberar' && $pe['plano'] && !pedido_pago($pe)) { flash('Plano não pago: não dá para liberar por aqui (use um cupom de 100% para cortesia).', 'erro'); ir('admin/pedido.php?id=' . $id); }
    if ($acao === 'liberar' && $p && $pe['tipo'] === 'assumir') {
        $_SESSION['link_novo'] = [$id, liberar_acesso((int)$p['id'], $id, $pe['nome'], $pe['telefone'], (int)$admin['id'])];
        q("UPDATE pedidos SET status = 'feito' WHERE id = ?", [$id]);
        $n = avisar_usuario(isset($pe['usuario_id']) ? (int)$pe['usuario_id'] : null, 'Pedido aceito!',
            "Confirmamos que você é o responsável por {$p['nome']}. Vamos mandar o link do painel pelo WhatsApp.",
            ['tela' => 'profissional', 'id' => (int)$p['id']]);
        flash('Painel liberado. Mande o link abaixo para o dono (ele só aparece agora).' . ($n ? ' Ele também recebeu o aviso no celular.' : ''));
    } elseif ($acao === 'novo_link' && $p && $pe['tipo'] === 'assumir' && $pe['status'] === 'feito') {
        $_SESSION['link_novo'] = [$id, liberar_acesso((int)$p['id'], $id, $pe['nome'], $pe['telefone'], (int)$admin['id'])];
        flash('Link novo gerado. Se o antigo não deve mais valer, corte-o na lista de acessos.');
    } elseif ($acao === 'publicar' && $p && $pe['tipo'] === 'incluir') {
        q("UPDATE profissionais SET status = 'ativo', geo_tentado = NULL WHERE id = ? AND status = 'pendente'", [$p['id']]);
        q("UPDATE pedidos SET status = 'feito' WHERE id = ?", [$id]);
        registrar((int)$admin['id'], 'incluiu', (int)$p['id'], 'pedido ' . $id);
        acesso_do_incluir(['status' => 'feito'] + $pe);   // passo 93: quem incluiu já entra no painel com a conta Google
        web_cache_limpar();
        $n = avisar_usuario(isset($pe['usuario_id']) ? (int)$pe['usuario_id'] : null, 'Seu comércio foi aceito!',
            "{$p['nome']} já aparece no Acha Fácil. Toque para ver.", ['tela' => 'profissional', 'id' => (int)$p['id']]);
        flash($p['nome'] . ' já aparece no app. ' . ($n ? 'A pessoa recebeu o aviso no celular; ' : '') . 'Avise também pelo WhatsApp abaixo.');
        ir('admin/pedido.php?id=' . $id);
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
        if ($p && $pe['tipo'] === 'incluir') q("UPDATE profissionais SET status = 'recusado' WHERE id = ? AND status = 'pendente'", [$p['id']]);
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
<?php require_once __DIR__ . '/../../inc/pedidos-vistos.php';
if ($pe['status'] === 'novo' && !in_array($pe['mp_status'], ['authorized', 'approved'], true)): $visto_em = pedidos_vistos()[$id] ?? null; ?>
  <form method="post" style="float:right;margin:0 0 8px 8px"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= $id ?>">
    <?php if ($visto_em): ?><span class="discreto">Visto em <?= h(datahora_br($visto_em)) ?> · </span>
      <input type="hidden" name="acao" value="desfazer_visto"><button class="botao pequeno contorno">Voltar às pendências</button>
    <?php else: ?><input type="hidden" name="acao" value="visto">
      <button class="botao pequeno contorno" title="Já vi; estou esperando retorno da pessoa. Sai da contagem de pendências.">✓ Visto, aguardando retorno</button>
    <?php endif ?></form>
<?php endif ?>
<h1><?= h(TIPO_PEDIDO[$pe['tipo']] ?? $pe['tipo']) ?><?= $pe['plano'] ? ' · plano ' . h(PLANOS_NOME[$pe['plano']] ?? $pe['plano']) : '' ?></h1>
<?php // 17/11: plano escolhido e não pago NÃO se libera por aqui (o plano só vale com o pagamento ou cupom de 100%)
$sem_pagar = $pe['plano'] && !pedido_pago($pe) && $pe['status'] === 'novo';
$ja_dono = $p ? linha('SELECT a.criado_em, pe2.plano FROM acessos_dono a LEFT JOIN pedidos pe2 ON pe2.id = a.pedido_id
    WHERE a.profissional_id = ? AND a.ativo = 1 ORDER BY a.id DESC LIMIT 1', [$p['id']]) : null; ?>
<?php if ($ja_dono && $pe['status'] === 'novo'): ?>
  <div class="aviso ok"><strong>Já é dono deste comércio</strong> pelo plano <?= h($ja_dono['plano'] ? (PLANOS_NOME[$ja_dono['plano']] ?? $ja_dono['plano']) : 'Grátis') ?>
    desde <?= h(datahora_br($ja_dono['criado_em'])) ?>. Este pedido é para <strong>mudar de plano</strong>.</div>
<?php endif ?>
<?php if ($sem_pagar): ?>
  <div class="aviso erro"><strong>Pagamento não feito.</strong> A pessoa escolheu o plano <?= h(PLANOS_NOME[$pe['plano']] ?? $pe['plano']) ?>
    mas não terminou o pagamento. <strong>Não há nada para ativar:</strong> quando ela pagar, o plano libera sozinho. Para dar de cortesia,
    crie um <a href="cupons.php">cupom de 100%</a> e mande o link da assinatura abaixo para ela usar.
    <?php if ($p && $pe['tipo'] === 'assumir'): ?>O comércio <strong><?= adm_comercio((int)$p['id'], $p['nome'], $p['cidade']) ?></strong> <em>já estava no app</em> (veio da lista do Google), não é um negócio novo.<?php endif ?></div>
  <?php if (!empty($pe['codigo'])):
      $msg_pag = "Olá, {$pe['nome']}! Aqui é do Acha Fácil. Vimos que você escolheu o plano " . (PLANOS_NOME[$pe['plano']] ?? $pe['plano'])
          . ($p ? " para {$p['nome']}" : '') . ", mas o pagamento não foi concluído. Para terminar, é só abrir este link:\n"
          . url_publica('assinatura.php?c=' . $pe['codigo']) . "\nQualquer dúvida, é só responder aqui."; ?>
    <div class="quadro"><strong>Lembrar do pagamento</strong>
      <p><?php if ($w = link_whatsapp($pe['telefone'], $msg_pag)): ?><a class="botao whats" href="<?= h($w) ?>" target="_blank" rel="noopener">Mandar pelo WhatsApp</a><?php endif ?>
        <?php if (!empty($pe['usuario_id'])): ?><a class="botao contorno" href="pessoa.php?id=<?= (int)$pe['usuario_id'] ?>#notificar">Mandar notificação no app</a><?php endif ?></p>
    </div>
  <?php endif ?>
<?php endif ?>
<?php if ($pe['tipo'] === 'incluir' && $pe['status'] === 'feito' && $p && $p['status'] === 'ativo'):
    $msg_ok = "Olá, {$pe['nome']}! Aqui é do Acha Fácil. O seu comércio {$p['nome']} foi aceito e já aparece no app e no site:\n" . url_publica(caminho_ficha((int)$p['id'], (string)$p['nome'], (string)$p['cidade']))
            . "\nQuer colocar fotos, descrição e aparecer em destaque? Veja os planos: " . url_publica('planos.php?id=' . (int)$p['id']); ?>
  <div class="quadro">
    <strong>Avisar que foi aceito</strong>
    <p><?php if ($w = link_whatsapp($pe['telefone'], $msg_ok)): ?><a class="botao whats" href="<?= h($w) ?>" target="_blank" rel="noopener">Mandar pelo WhatsApp</a><?php else: ?>Telefone inválido para WhatsApp: <?= h(telefone_formatado($pe['telefone'])) ?><?php endif ?></p>
  </div>
<?php endif ?>

<?php if ($link):
    $msg = "Olá, {$pe['nome']}! Aqui é do Acha Fácil. Este é o link do painel do seu comércio" . ($p ? " ({$p['nome']})" : '') . ":\n$link\n"
         . "Não passe para ninguém. Toda mudança que você fizer passa pela nossa conferência antes de aparecer no app."; ?>
  <div class="quadro">
    <label for="link">Link do painel (só aparece agora)</label>
    <input id="link" value="<?= h($link) ?>" readonly onclick="this.select()">
    <p><?php if ($w = link_whatsapp($pe['telefone'], $msg)): ?><a class="botao whats" href="<?= h($w) ?>" target="_blank" rel="noopener">Mandar pelo WhatsApp</a><?php endif ?></p>
  </div>
<?php endif ?>

<?php if ($p && $pe['tipo'] === 'assumir'): ?><p><a class="botao pequeno contorno" href="<?= h(url('painel/?ver=' . (int)$p['id'])) ?>" target="_blank" rel="noopener">👁 Ver o painel como o dono</a></p><?php endif ?>
<div class="tabela"><table>
  <tr><th>Situação</th><td><?= h(['novo' => 'Novo', 'feito' => 'Resolvido', 'recusado' => 'Recusado'][$pe['status']] ?? $pe['status']) ?></td></tr>
  <tr><th>Comércio</th><td><?= $p ? '<strong>' . adm_comercio((int)$p['id'], $p['nome'], $p['cidade']) . '</strong><br>' . h($p['endereco'] ?? '') . ' · ' . adm_cidade($p['cidade'])
        . '<br>Telefone do cadastro: ' . h(telefone_formatado($p['telefone'] ?: $p['whatsapp'])) : '<em>Negócio novo (não está no app)</em>' ?></td></tr>
  <tr><th>Quem pediu</th><td><?= h($pe['nome']) ?> · <?= h(telefone_formatado($pe['telefone'])) ?><?= !empty($pe['email']) ? ' · ' . h($pe['email']) : '' ?></td></tr>
  <?php if ($pe['plano']): ?><tr><th>Plano</th><td><?= h(PLANOS_NOME[$pe['plano']] ?? $pe['plano']) ?><?= pedido_pago($pe) ? ' · <strong>pago</strong>' : ' · <strong>não pago</strong>' ?><?= !empty($pe['mp_status']) ? ' (Mercado Pago: ' . h($pe['mp_status']) . ')' : '' ?></td></tr><?php endif ?>
  <?php if ($pe['tipo'] === 'incluir' && $p): ?>
    <tr><th>Dados enviados</th><td>
      <strong><?= adm_comercio((int)$p['id'], $p['nome'], $p['cidade']) ?></strong> · <?= h($p['tipo'] ?? '') ?> · área <?= h((string)valor('SELECT nome FROM categorias WHERE id = ?', [$p['categoria_id']])) ?><br>
      <?= h($p['endereco'] ?? '') ?><?= $p['bairro'] ? ' · ' . h($p['bairro']) : '' ?> · <?= adm_cidade($p['cidade']) ?><br>
      Telefone: <?= h(telefone_formatado($p['telefone']) ?: '-') ?> · WhatsApp: <?= h(telefone_formatado($p['whatsapp']) ?: '-') ?><br>
      Horário: <?= h($p['horario'] ?? '-') ?><?= $p['instagram'] ? ' · Instagram @' . h($p['instagram']) : '' ?>
    </td></tr>
  <?php endif ?>
  <?php if ($pe['mensagem']): ?><tr><th>Mensagem<?= $pe['plano'] ? '<br><small class="discreto">escrita antes do pagamento, não é alteração da ficha</small>' : '' ?></th><td><?= nl2br(h($pe['mensagem'])) ?></td></tr><?php endif ?>
  <tr><th>Quando</th><td><?= h(datahora_br($pe['criado_em'])) ?> · IP <?= h($pe['ip'] ?? '?') ?></td></tr>
</table></div>

<?php if ($pe['status'] === 'novo'): ?>
  <div class="quadro">
    <?php if ($pe['tipo'] === 'incluir'): ?>
      <strong>Antes de publicar:</strong> confira se o negócio existe (Google Maps, Instagram ou ligando no telefone dele) e se os
      dados estão certos. Publicado, ele aparece no app como os da lista, sem plano.
    <?php elseif ($pe['tipo'] === 'assumir'): ?>
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
      <?php if ($pe['tipo'] === 'assumir' && $p && $sem_pagar): ?>
        <p class="discreto">Esperando o pagamento. Se a pessoa desistir, encerre o pedido em "Recusar" (motivo: não pagou).</p>
      <?php elseif ($pe['tipo'] === 'assumir' && $p): ?>
        <button class="botao" name="acao" value="liberar">Confirmei: liberar o painel</button>
      <?php elseif ($pe['tipo'] === 'incluir' && $p): ?>
        <button class="botao" name="acao" value="publicar">Conferi: publicar no app</button>
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
