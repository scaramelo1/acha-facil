<?php
// Página do pagamento do dono (endereço secreto: ?c=código do pedido). Mostra o plano e a situação; o botão leva ao
// Mercado Pago: Básico = pagamento único (volta com ?payment_id=); Destaque = assinatura mensal no cartão
// (volta com ?preapproval_id=). A volta é sempre conferida direto na API.
require __DIR__ . '/../inc/base.php';
require __DIR__ . '/../inc/guia.php';
require __DIR__ . '/../inc/pagina.php';
require __DIR__ . '/../inc/mercadopago.php';
iniciar_sessao();
header('X-Robots-Tag: noindex');

$codigo = (string)($_GET['c'] ?? '');
$pedido = pedido_por_codigo($codigo);
if (!$pedido) { http_response_code(404); pagina_inicio('Assinatura · Acha Fácil!', 'Assinatura do plano.');
    echo '<main class="documento"><h1>Assinatura não encontrada</h1><p>Confira o endereço ou escolha um plano de novo.</p><p><a class="botao amarelo" href="' . h(config()['url']) . '/planos.php">Ver os planos</a></p></main>';
    pagina_fim(); exit; }

$erro = null;
// voltou do Mercado Pago: confere direto na API
$volta = (string)($_GET['preapproval_id'] ?? '');
$volta_pg = (string)($_GET['payment_id'] ?? $_GET['collection_id'] ?? '');
if (($volta !== '' || $volta_pg !== '') && mp_modo() !== 'desligado') {
    try { $pedido = ($volta !== '' ? mp_conferir_assinatura($volta) : mp_conferir_pagamento($volta_pg)) ?? $pedido; }
    catch (Throwable $e) { error_log('acha-facil assinatura retorno: ' . $e->getMessage()); }
}
if (e_post()) {
    exigir_post();
    try { header('Location: ' . mp_assinar($pedido)); exit; }
    catch (Throwable $e) {
        error_log('acha-facil assinar: ' . $e->getMessage());
        $erro = str_contains($e->getMessage(), 'real or test users')
            ? 'No modo de teste, o e-mail do pedido precisa ser o de uma conta de teste (comprador) do Mercado Pago. Faça um pedido novo com esse e-mail.'
            : 'Não foi possível abrir o Mercado Pago agora. Tente de novo em alguns minutos.';
    }
}

$p = $pedido['profissional_id'] ? linha('SELECT nome, tipo, endereco, assinatura_ate FROM profissionais WHERE id = ?', [$pedido['profissional_id']]) : null;
$plano = (string)$pedido['plano'];
$unico = plano_unico($plano);
$status = $pedido['mp_status'] ?: null;
$pago = pedido_pago($pedido);
$modo = mp_modo();
pagina_inicio('Plano ' . PLANOS_NOME[$plano] . ' · Acha Fácil!', 'Pagamento do plano do seu comércio.');
?>
<main class="documento">
  <h1>Plano <?= h(PLANOS_NOME[$plano]) ?></h1>
  <?php if ($p): ?>
    <div class="quadro"><strong><?= h($p['nome']) ?></strong><br><?= h($p['tipo'] ?? '') ?><?= $p['endereco'] ? ' · ' . h($p['endereco']) : '' ?></div>
  <?php endif ?>
  <?php if ($unico): ?>
    <p class="valor"><strong><?= h(preco_plano($plano)) ?>, pagamento único</strong> pelo Mercado Pago (cartão, Pix ou boleto). Sem mensalidade.</p>
  <?php else: ?>
    <p class="valor"><strong><?= h(preco_plano($plano)) ?> por mês</strong>, cobrado todo mês no cartão pelo Mercado Pago. Cancele quando quiser.</p>
  <?php endif ?>
  <?php if ($modo === 'sandbox'): ?><div class="aviso erro">Modo de teste: nada é cobrado de verdade. Use o e-mail e o login de uma conta de teste (comprador) do Mercado Pago.</div><?php endif ?>
  <?php if ($erro): ?><div class="aviso erro"><?= h($erro) ?></div><?php endif ?>

  <?php if ($pago && $unico): ?>
    <div class="aviso ok">Pagamento confirmado! O plano Básico já está ligado, sem mensalidade.</div>
    <?php if ($p): ?>
      <p><a class="botao" href="<?= h(config()['url']) ?>/painel/?c=<?= h($codigo) ?>">Editar os dados do meu comércio</a></p>
      <p class="discreto">Você já pode corrigir os dados e colocar fotos. Para evitar fraudes, cada mudança aparece no app
        depois que a equipe do Acha Fácil confere (normalmente em até 1 dia útil).</p>
    <?php endif ?>
    <p class="discreto">Quer o selo de verificado, responder as avaliações, ver os números da sua página e aparecer no topo?
      <a href="<?= h(config()['url']) ?>/perfil.php?acao=assumir&amp;plano=destaque<?= $p ? '&amp;id=' . (int)$pedido['profissional_id'] : '' ?>">Conheça o Destaque</a>.</p>
  <?php elseif ($pago): ?>
    <div class="aviso ok">Assinatura ativa! O plano já está ligado<?= $p && $p['assinatura_ate'] ? ' até ' . h(data_br($p['assinatura_ate'])) . ' e renova sozinho a cada mês' : '' ?>.</div>
    <?php if ($p): ?>
      <p><a class="botao" href="<?= h(config()['url']) ?>/painel/?c=<?= h($codigo) ?>">Editar os dados do meu comércio</a></p>
      <p class="discreto">Você já pode corrigir os dados e colocar foto. Para evitar fraudes, cada mudança aparece no app
        depois que a equipe do Acha Fácil confere (normalmente em até 1 dia útil).</p>
    <?php endif ?>
    <p class="discreto">Para cancelar, entre no Mercado Pago, em Assinaturas. O plano continua até o fim do mês já pago.</p>
  <?php elseif ($modo === 'desligado'): ?>
    <div class="aviso ok">Pedido recebido!</div>
    <p>O pagamento on-line está sendo configurado. Vamos chamar você no WhatsApp para confirmar que é o responsável e combinar o pagamento.</p>
  <?php else: ?>
    <?php if ($unico): ?>
      <?php if ($status === 'in_process' || ($status === 'pending' && !empty($pedido['mp_pagamento']))): ?><div class="aviso ok">Pagamento em análise ou aguardando (Pix/boleto). Assim que o Mercado Pago confirmar, o plano é ligado.</div><?php endif ?>
      <?php if (in_array($status, ['rejected', 'cancelled'], true)): ?><div class="aviso erro">O pagamento foi <?= h(mp_status_nome($status)) ?>. Tente de novo.</div><?php endif ?>
      <form method="post">
        <?= campo_csrf() ?>
        <p><button class="botao amarelo">Pagar <?= h(preco_plano($plano)) ?> com o Mercado Pago</button></p>
      </form>
      <p class="discreto">Depois de pagar, você já pode editar os dados e as fotos; cada mudança aparece no app depois da conferência da equipe.</p>
    <?php else: ?>
      <?php if ($status === 'pending'): ?><div class="aviso ok">Falta só concluir a assinatura no Mercado Pago.</div><?php endif ?>
      <?php if (in_array($status, ['cancelled', 'paused'], true)): ?><div class="aviso erro">A assinatura está <?= h(mp_status_nome($status)) ?>.</div><?php endif ?>
      <form method="post">
        <?= campo_csrf() ?>
        <p><button class="botao amarelo"><?= in_array($status, ['cancelled', 'paused'], true) ? 'Assinar de novo' : 'Assinar com o Mercado Pago' ?></button></p>
      </form>
      <p class="discreto">Use o e-mail <strong><?= h((string)$pedido['email']) ?></strong> na sua conta do Mercado Pago (o Mercado Pago pede o mesmo e-mail).
        Depois de assinar, você já pode editar os dados e as fotos; cada mudança aparece no app depois da conferência da equipe.</p>
    <?php endif ?>
  <?php endif ?>
  <p class="discreto">Guarde o endereço desta página para acompanhar o seu plano.</p>
</main>
<?php pagina_fim();
