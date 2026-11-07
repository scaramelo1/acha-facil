<?php
// Página da assinatura do dono (endereço secreto: ?c=código do pedido). Mostra o plano e a situação; o botão leva ao
// Mercado Pago para autorizar a cobrança mensal no cartão. O Mercado Pago volta para cá com ?preapproval_id=.
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
if ($volta !== '' && mp_modo() !== 'desligado') {
    try { $pedido = mp_conferir_assinatura($volta) ?? $pedido; }
    catch (Throwable $e) { error_log('acha-facil assinatura retorno: ' . $e->getMessage()); }
}
if (e_post()) {
    exigir_post();
    try { header('Location: ' . mp_assinar($pedido)); exit; }
    catch (Throwable $e) { error_log('acha-facil assinar: ' . $e->getMessage()); $erro = 'Não foi possível abrir o Mercado Pago agora. Tente de novo em alguns minutos.'; }
}

$p = $pedido['profissional_id'] ? linha('SELECT nome, tipo, endereco, assinatura_ate FROM profissionais WHERE id = ?', [$pedido['profissional_id']]) : null;
$plano = (string)$pedido['plano'];
$status = $pedido['mp_status'] ?: null;
$modo = mp_modo();
pagina_inicio('Plano ' . PLANOS_NOME[$plano] . ' · Acha Fácil!', 'Assinatura do plano do seu comércio.');
?>
<main class="documento">
  <h1>Plano <?= h(PLANOS_NOME[$plano]) ?></h1>
  <?php if ($p): ?>
    <div class="quadro"><strong><?= h($p['nome']) ?></strong><br><?= h($p['tipo'] ?? '') ?><?= $p['endereco'] ? ' · ' . h($p['endereco']) : '' ?></div>
  <?php endif ?>
  <p class="valor"><strong><?= h(preco_plano($plano)) ?> por mês</strong>, cobrado todo mês no cartão pelo Mercado Pago. Cancele quando quiser.</p>
  <?php if ($modo === 'sandbox'): ?><div class="aviso erro">Modo de teste: nada é cobrado de verdade.</div><?php endif ?>
  <?php if ($erro): ?><div class="aviso erro"><?= h($erro) ?></div><?php endif ?>

  <?php if ($status === 'authorized'): ?>
    <div class="aviso ok">Assinatura ativa! O plano já está ligado<?= $p && $p['assinatura_ate'] ? ' até ' . h(data_br($p['assinatura_ate'])) . ' e renova sozinho a cada mês' : '' ?>.</div>
    <p>Vamos chamar você no WhatsApp para confirmar que é o responsável pelo comércio e liberar a edição dos dados e das fotos.</p>
    <p class="discreto">Para cancelar, entre no Mercado Pago, em Assinaturas. O plano continua até o fim do mês já pago.</p>
  <?php elseif ($modo === 'desligado'): ?>
    <div class="aviso ok">Pedido recebido!</div>
    <p>O pagamento on-line está sendo configurado. Vamos chamar você no WhatsApp para confirmar que é o responsável e combinar o pagamento.</p>
  <?php else: ?>
    <?php if ($status === 'pending'): ?><div class="aviso ok">Falta só concluir a assinatura no Mercado Pago.</div><?php endif ?>
    <?php if (in_array($status, ['cancelled', 'paused'], true)): ?><div class="aviso erro">A assinatura está <?= h(mp_status_nome($status)) ?>.</div><?php endif ?>
    <form method="post">
      <?= campo_csrf() ?>
      <p><button class="botao amarelo"><?= in_array($status, ['cancelled', 'paused'], true) ? 'Assinar de novo' : 'Assinar com o Mercado Pago' ?></button></p>
    </form>
    <p class="discreto">Use o e-mail <strong><?= h((string)$pedido['email']) ?></strong> na sua conta do Mercado Pago (o Mercado Pago pede o mesmo e-mail).
      Depois de assinar, confirmamos por WhatsApp que você é o responsável e liberamos a edição dos dados e das fotos.</p>
  <?php endif ?>
  <p class="discreto">Guarde o endereço desta página para acompanhar a sua assinatura.</p>
</main>
<?php pagina_fim();
