<?php
// Página do pagamento do dono (endereço secreto: ?c=código do pedido). Mostra o plano e a situação; o botão leva ao
// Mercado Pago: Básico = pagamento único (volta com ?payment_id=); Destaque = assinatura mensal no cartão
// (volta com ?preapproval_id=). A volta é sempre conferida direto na API.
// Com a Public Key configurada, o Destaque é pago com o cartão digitado aqui mesmo (Card Payment Brick do Mercado Pago),
// sem conta no Mercado Pago: o navegador manda o token do cartão para ?c=...&cartao=1 (JSON). Cancelar também é aqui.
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
// cartão digitado no formulário do Mercado Pago desta página (Destaque): chega só o token, nunca o número do cartão
if (e_post() && isset($_GET['cartao'])) {
    header('Content-Type: application/json; charset=utf-8');
    $corpo = json_decode((string)file_get_contents('php://input'), true) ?: [];
    if (!hash_equals(csrf(), (string)($corpo['csrf'] ?? ''))) { echo json_encode(['erro' => 'Sessão expirada. Recarregue a página.']); exit; }
    if (pedido_pago($pedido)) { echo json_encode(['ok' => true]); exit; }
    try {
        $novo = mp_assinar_cartao($pedido, (array)($corpo['dados'] ?? []));
        echo json_encode(pedido_pago($novo) ? ['ok' => true]
            : ['erro' => 'O Mercado Pago não autorizou a assinatura (' . mp_status_nome($novo['mp_status'] ?? null) . '). Confira o cartão ou use outro.'],
            JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('acha-facil cartao: ' . $e->getMessage());
        echo json_encode(['erro' => mp_erro_para_dono($e)], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
if (e_post()) {
    exigir_post();
    if (in_array($_POST['acao'] ?? '', ['cupom', 'tirar_cupom'], true) && cupons_prontos()) {
        // cupom de desconto: muda o valor do pedido antes de pagar (100% = cortesia, já liga o plano)
        if ((int)valor('SELECT COUNT(*) FROM pedidos WHERE ip = ? AND atualizado_em > NOW() - INTERVAL 1 HOUR', [ip()]) >= 30) {
            $erro = 'Muitas tentativas. Tente de novo daqui a pouco.';
        } else {
            try {
                $pedido = aplicar_cupom($pedido, $_POST['acao'] === 'cupom' ? (string)txt('cupom', 30) : '');
                flash(pedido_pago($pedido) ? 'Cupom aplicado: plano liberado de cortesia!' : ($pedido['desconto'] ? 'Cupom aplicado!' : 'Cupom retirado.'));
                ir('assinatura.php?c=' . $codigo);
            } catch (RuntimeException $e) { $erro = $e->getMessage(); }
        }
    } elseif (($_POST['acao'] ?? '') === 'cancelar') {
        try {
            if ($pedido['mp_assinatura'] && $pedido['mp_status'] === 'authorized') {
                mp_api('PUT', '/preapproval/' . rawurlencode((string)$pedido['mp_assinatura']), ['status' => 'cancelled']);
                $pedido = mp_conferir_assinatura((string)$pedido['mp_assinatura']) ?? $pedido;
            }
        } catch (Throwable $e) { error_log('acha-facil cancelar: ' . $e->getMessage()); $erro = 'Não foi possível cancelar agora. Tente de novo em alguns minutos.'; }
    } else {
        try { header('Location: ' . mp_assinar($pedido)); exit; }
        catch (Throwable $e) { error_log('acha-facil assinar: ' . $e->getMessage()); $erro = mp_erro_para_dono($e); }
    }
}

$p = $pedido['profissional_id'] ? linha('SELECT nome, tipo, endereco, assinatura_ate FROM profissionais WHERE id = ?', [$pedido['profissional_id']]) : null;
$plano = (string)$pedido['plano'];
$unico = plano_unico($plano);
$status = $pedido['mp_status'] ?: null;
$pago = pedido_pago($pedido);
$modo = mp_modo();
$cartao_no_site = !$unico && $modo !== 'desligado' && mp_public_key() !== '';
$valor = preco_pedido($pedido);           // com o desconto do cupom, se houver
$desconto = (int)($pedido['desconto'] ?? 0);
$cortesia = $desconto === 100;
$cupom = $desconto ? (string)valor('SELECT codigo FROM cupons WHERE id = ?', [$pedido['cupom_id']]) : '';
$preco_html = $desconto && !$cortesia ? '<s>' . h(preco_plano($plano)) . '</s> ' . h(reais($valor)) : h(reais($valor));
pagina_inicio('Plano ' . PLANOS_NOME[$plano] . ' · Acha Fácil!', 'Pagamento do plano do seu comércio.');
?>
<main class="documento">
  <h1>Plano <?= h(PLANOS_NOME[$plano]) ?></h1>
  <?php if ($p): ?>
    <div class="quadro"><strong><?= h($p['nome']) ?></strong><br><?= h($p['tipo'] ?? '') ?><?= $p['endereco'] ? ' · ' . h($p['endereco']) : '' ?></div>
  <?php endif ?>
  <?php foreach ($_SESSION['flash'] ?? [] as [$t, $m]) echo '<div class="aviso ' . h($t) . '">' . h($m) . '</div>'; unset($_SESSION['flash']); ?>
  <?php if ($cortesia): ?>
    <p class="valor"><strong>Cortesia</strong> (cupom <?= h($cupom) ?>): <?= $unico ? 'sem pagamento.' : 'sem mensalidade.' ?></p>
  <?php elseif ($unico): ?>
    <p class="valor"><strong><?= $preco_html ?>, pagamento único</strong> pelo Mercado Pago (cartão, Pix ou boleto). Sem mensalidade.</p>
  <?php else: ?>
    <p class="valor"><strong><?= $preco_html ?> por mês</strong>, cobrado todo mês no cartão pelo Mercado Pago. Cancele quando quiser.</p>
  <?php endif ?>
  <?php if ($desconto && !$cortesia): ?><p class="discreto">Cupom <strong><?= h($cupom) ?></strong>: <?= $desconto ?>% de desconto<?= $unico ? '' : ' em todas as mensalidades' ?>.</p><?php endif ?>
  <?php if ($modo === 'sandbox'): ?><div class="aviso erro">Modo de teste: nada é cobrado de verdade. Use um cartão de teste do Mercado Pago.</div><?php endif ?>
  <?php if ($erro): ?><div class="aviso erro"><?= h($erro) ?></div><?php endif ?>

  <?php if ($pago && $unico): ?>
    <div class="aviso ok"><?= $cortesia ? 'Cortesia liberada!' : 'Pagamento confirmado!' ?> O plano Básico já está ligado, sem mensalidade.</div>
    <?php if ($p): ?>
      <p><a class="botao" href="<?= h(config()['url']) ?>/painel/?c=<?= h($codigo) ?>">Editar os dados do meu comércio</a></p>
      <p class="discreto">Você já pode corrigir os dados e colocar fotos. Para evitar fraudes, cada mudança aparece no app
        depois que a equipe do Acha Fácil confere (normalmente em até 1 dia útil).</p>
    <?php endif ?>
    <p class="discreto">Quer o selo de verificado, responder as avaliações, ver os números da sua página e aparecer no topo?
      <a href="<?= h(config()['url']) ?>/perfil.php?acao=assumir&amp;plano=destaque<?= $p ? '&amp;id=' . (int)$pedido['profissional_id'] : '' ?>">Conheça o Destaque</a>.</p>
  <?php elseif ($pago && $cortesia): ?>
    <div class="aviso ok">Cortesia liberada! O plano Destaque já está ligado, sem cobrança.</div>
    <?php if ($p): ?>
      <p><a class="botao" href="<?= h(config()['url']) ?>/painel/?c=<?= h($codigo) ?>">Editar os dados do meu comércio</a></p>
      <p class="discreto">Para evitar fraudes, cada mudança aparece no app depois que a equipe do Acha Fácil confere (normalmente em até 1 dia útil).</p>
    <?php endif ?>
  <?php elseif ($pago): ?>
    <div class="aviso ok">Assinatura ativa! O plano já está ligado<?= $p && $p['assinatura_ate'] ? ' até ' . h(data_br($p['assinatura_ate'])) . ' e renova sozinho a cada mês' : '' ?>.</div>
    <?php if ($p): ?>
      <p><a class="botao" href="<?= h(config()['url']) ?>/painel/?c=<?= h($codigo) ?>">Editar os dados do meu comércio</a></p>
      <p class="discreto">Você já pode corrigir os dados e colocar foto. Para evitar fraudes, cada mudança aparece no app
        depois que a equipe do Acha Fácil confere (normalmente em até 1 dia útil).</p>
    <?php endif ?>
    <form method="post" onsubmit="return confirm('Cancelar a assinatura do Destaque? Ele continua até o fim do mês já pago.')">
      <?= campo_csrf() ?><input type="hidden" name="acao" value="cancelar">
      <p class="discreto">Não quer mais? <button style="background:none;border:0;padding:0;color:inherit;text-decoration:underline;cursor:pointer;font:inherit">Cancelar a assinatura</button>. O plano continua até o fim do mês já pago.</p>
    </form>
  <?php else: ?>
    <?php if (cupons_prontos()): ?>
      <details class="cupom"<?= $desconto || $erro ? ' open' : '' ?>>
        <summary>Tem cupom de desconto?</summary>
        <?php if ($desconto): ?>
          <form method="post"><?= campo_csrf() ?><input type="hidden" name="acao" value="tirar_cupom">
            <p>Cupom <strong><?= h($cupom) ?></strong> aplicado (<?= $desconto ?>%). <button class="link">Tirar o cupom</button></p></form>
        <?php else: ?>
          <form method="post" class="linha-cupom"><?= campo_csrf() ?><input type="hidden" name="acao" value="cupom">
            <label for="cupom" class="oculto">Cupom</label>
            <input id="cupom" name="cupom" maxlength="30" autocapitalize="characters" autocomplete="off" placeholder="Digite o cupom" required>
            <button class="botao contorno">Aplicar</button></form>
        <?php endif ?>
      </details>
    <?php endif ?>
  <?php if ($modo === 'desligado'): ?>
    <div class="aviso ok">Pedido recebido!</div>
    <p>O pagamento on-line está sendo configurado. Vamos chamar você no WhatsApp para confirmar que é o responsável e combinar o pagamento.</p>
  <?php else: ?>
    <?php if ($unico): ?>
      <?php if ($status === 'in_process' || ($status === 'pending' && !empty($pedido['mp_pagamento']))): ?><div class="aviso ok">Pagamento em análise ou aguardando (Pix/boleto). Assim que o Mercado Pago confirmar, o plano é ligado.</div><?php endif ?>
      <?php if (in_array($status, ['rejected', 'cancelled'], true)): ?><div class="aviso erro">O pagamento foi <?= h(mp_status_nome($status)) ?>. Tente de novo.</div><?php endif ?>
      <form method="post">
        <?= campo_csrf() ?>
        <p><button class="botao amarelo">Pagar <?= h(reais($valor)) ?> com o Mercado Pago</button></p>
      </form>
      <p class="discreto">Depois de pagar, você já pode editar os dados e as fotos; cada mudança aparece no app depois da conferência da equipe.</p>
    <?php elseif ($cartao_no_site): ?>
      <?php if (in_array($status, ['cancelled', 'paused'], true)): ?><div class="aviso erro">A assinatura está <?= h(mp_status_nome($status)) ?>. Assine de novo abaixo.</div><?php endif ?>
      <p>Digite o cartão de crédito abaixo. Não precisa ter conta no Mercado Pago: o cartão vai direto para o Mercado Pago,
        com segurança, e não fica guardado no Acha Fácil.</p>
      <div id="erro-cartao" class="aviso erro" hidden></div>
      <div id="cartao"></div>
      <p class="discreto">A cobrança de <?= h(reais($valor)) ?> se repete todo mês no mesmo cartão. Você pode cancelar nesta página quando quiser.
        Depois de assinar, já pode editar os dados e as fotos; cada mudança aparece no app depois da conferência da equipe.</p>
      <script src="https://sdk.mercadopago.com/js/v2"></script>
      <script>
      (function () {
        var erro = document.getElementById('erro-cartao');
        var mp = new MercadoPago(<?= json_encode(mp_public_key()) ?>, { locale: 'pt-BR' });
        mp.bricks().create('cardPayment', 'cartao', {
          initialization: { amount: <?= json_encode($valor) ?>, payer: { email: <?= json_encode((string)$pedido['email']) ?> } },
          customization: { paymentMethods: { maxInstallments: 1, types: { excluded: ['debit_card', 'prepaid_card'] } } },
          callbacks: {
            onReady: function () {},
            onError: function (e) { console.error(e); },
            onSubmit: function (dados) {
              erro.hidden = true;
              return fetch(location.pathname + '?c=<?= h($codigo) ?>&cartao=1', {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
                body: JSON.stringify({ csrf: <?= json_encode(csrf()) ?>, dados: dados })
              }).then(function (r) { return r.json(); }).then(function (j) {
                if (j.ok) { location.href = location.pathname + '?c=<?= h($codigo) ?>'; return; }
                erro.textContent = j.erro || 'Não foi possível assinar agora.'; erro.hidden = false;
                throw new Error(j.erro);
              }, function () {
                erro.textContent = 'Sem conexão. Tente de novo.'; erro.hidden = false;
                throw new Error('rede');
              });
            }
          }
        });
      })();
      </script>
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
  <?php endif ?>
  <p class="discreto">Guarde o endereço desta página para acompanhar o seu plano.</p>
</main>
<?php pagina_fim();
