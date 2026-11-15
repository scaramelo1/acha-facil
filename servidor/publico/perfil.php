<?php
// Pedido do dono: assumir o perfil de um comércio importado (ou incluir um novo) ou tirar do app.
// Fica em "pedidos" para a equipe conferir (ligando no telefone do comércio) antes de liberar ou retirar.
// Com plano escolhido (e a mudança 007 do banco aplicada), pede também o e-mail e leva à página da assinatura
// (assinatura.php), onde o dono assina pelo Mercado Pago.
require __DIR__ . '/../inc/base.php';
require_once __DIR__ . '/../inc/guia.php';
require __DIR__ . '/../inc/pagina.php';
require __DIR__ . '/../inc/mercadopago.php';
iniciar_sessao();

$acao = ($_GET['acao'] ?? '') === 'remover' ? 'remover' : 'assumir';
$plano = in_array($_GET['plano'] ?? '', planos_disponiveis(), true) ? $_GET['plano'] : null;
if ($acao === 'remover') $plano = null;
$id = (int)($_GET['id'] ?? 0);
$p = $id ? linha('SELECT p.id, p.nome, p.tipo, p.endereco FROM profissionais p WHERE p.id = ? AND p.importado = 1', [$id]) : null;
if ($acao === 'remover' && !$p) ir('busca.php');
if ($acao === 'assumir' && !$p) ir($plano ? 'busca.php' : 'incluir.php');   // negócio fora do app: incluir é de graça
// assumir o perfil ou escolher um plano: só com a conta Google (para receber o aviso de "aceito" no celular)
require_once __DIR__ . '/../inc/conta-web.php';
require_once __DIR__ . '/../inc/avisos.php';
// tirar do app também (passo 52): o admin recebe o aviso e sabe quem pediu
$conta = exigir_login_web($acao === 'remover' ? 'Tirar do app' : ($plano ? 'Plano ' . PLANOS_NOME[$plano] : 'Assumir o perfil'),
    $acao === 'remover' ? "Você vai pedir para tirar {$p['nome']} do Acha Fácil."
        : ($p ? "Você vai pedir para cuidar de {$p['nome']} no Acha Fácil." : 'Você vai fazer um pedido para o seu comércio.'));
// comércio que já tem dono (plano pago ou painel liberado): ninguém mais assume nem tira por aqui
require_once __DIR__ . '/../inc/moderacao.php';
$e_o_dono = fn(): bool => (int)(dono_atual()['id'] ?? 0) === (int)$p['id']
    || (pedido_usuario_pronto() && valor("SELECT 1 FROM pedidos WHERE profissional_id = ? AND usuario_id = ? AND status = 'feito'", [$p['id'], $conta['id']]));
if ($p && tem_dono($p) && !$e_o_dono()) {
    pagina_inicio('Comércio com responsável · Acha Fácil!', 'Este comércio já tem um responsável no Acha Fácil.', '', ['noindex' => true]);
    echo '<main class="documento"><h1>Este comércio já tem responsável</h1><div class="quadro"><strong>' . h($p['nome']) . '</strong></div>'
        . '<p>O responsável por este comércio já cuida da página no Acha Fácil. Se acha que isso está errado, fale com a gente: '
        . contatos_html() . '.</p></main>';
    pagina_fim();
    exit;
}
$enviado = false;
$erro = null;
$com_assinatura = $plano !== null && mp_pronto();

if (e_post()) {
    exigir_post();
    $nome = txt('nome', 120);
    $telefone = telefone_normalizado(txt('telefone', 20));
    $mensagem = txt('mensagem', 1000);
    $email = $com_assinatura ? email_valido(txt('email', 160)) : null;
    if (!$nome || !$telefone) $erro = 'Preencha seu nome e um telefone com DDD.';
    elseif ($com_assinatura && !$email) $erro = plano_unico($plano) || mp_public_key() !== '' ? 'Digite um e-mail válido.' : 'Digite um e-mail válido (o mesmo da sua conta do Mercado Pago).';
    elseif ((int)valor('SELECT COUNT(*) FROM pedidos WHERE ip = ? AND criado_em > NOW() - INTERVAL 1 DAY', [ip()]) >= 10) $erro = 'Muitos pedidos hoje. Tente amanhã.';
    else {
        $dados = ['profissional_id' => $p['id'] ?? null, 'tipo' => $acao, 'plano' => $plano, 'nome' => $nome, 'telefone' => $telefone, 'mensagem' => $mensagem, 'ip' => ip()];
        if ($com_assinatura) $dados += ['email' => $email, 'codigo' => bin2hex(random_bytes(32)), 'valor' => PLANOS_PRECO[$plano]];
        require_once __DIR__ . '/../inc/avisos.php';
        if ($uid = pedido_usuario_atual()) $dados['usuario_id'] = $uid;
        $pedido_id = inserir('pedidos', $dados);
        // aviso no celular dos admins já no pedido (o "plano pago" vem depois, quando o Mercado Pago confirmar)
        $comercio = $p['nome'] ?? 'um negócio novo';
        [$titulo_aviso, $texto_aviso] = match (true) {
            $acao === 'remover' => ['Pedido para tirar do app', "$nome pediu para tirar $comercio do app."],
            $plano !== null => ['Plano escolhido (ainda sem pagamento)', "$nome quer assumir $comercio, que já está no app, com o plano "
                . PLANOS_NOME[$plano] . ". Foi para o pagamento; o aviso \"Novo plano pago\" chega quando pagar."
                . ($plano === 'site' && $mensagem !== '' ? " Endereço do site: $mensagem" : '')],
            default => [$p ? 'Pedido para assumir' : 'Pedido para incluir', "$nome quer " . ($p ? 'assumir' : 'incluir') . " $comercio."],
        };
        avisar_admins($titulo_aviso, $texto_aviso, ['pedido' => (int)$pedido_id]);
        if ($com_assinatura) ir('assinatura.php?c=' . $dados['codigo']);
        $enviado = true;
    }
}

$titulo = $acao === 'remover' ? 'Tirar do app' : ($plano ? 'Plano ' . PLANOS_NOME[$plano] : ($p ? 'Assumir o perfil' : 'Incluir meu negócio'));
pagina_inicio("$titulo · Acha Fácil!", 'Pedido do dono do comércio para o Acha Fácil.');
?>
<main class="documento">
  <h1><?= h($titulo) ?></h1>
  <?php if ($p): ?>
    <div class="quadro"><strong><?= h($p['nome']) ?></strong><br><?= h($p['tipo'] ?? '') ?><?= $p['endereco'] ? ' · ' . h($p['endereco']) : '' ?></div>
  <?php endif ?>

  <?php if ($enviado): ?>
    <div class="aviso ok">Pedido recebido! <?= $acao === 'remover'
        ? 'Vamos conferir e retirar o comércio do app em até 2 dias úteis.'
        : 'Vamos ligar ou chamar no WhatsApp para confirmar que você é o responsável e combinar o plano.' ?></div>
    <p><a class="botao contorno" href="<?= h(config()['url']) ?>/">Voltar ao início</a></p>
  <?php elseif ($acao === 'assumir' && !$plano): ?>
    <p>Para corrigir os dados e colocar fotos, escolha um plano.</p>
    <p><a class="botao amarelo" href="<?= h(config()['url']) ?>/planos.php<?= $p ? '?id=' . $p['id'] : '' ?>">Conferir os planos</a></p>
  <?php else: ?>
    <p><?= $acao === 'remover'
        ? 'É o responsável por este comércio e não quer que ele apareça no Acha Fácil? Deixe seu contato para confirmarmos.'
        : ($p ? ($com_assinatura
                ? 'Plano ' . PLANOS_NOME[$plano] . ': ' . preco_plano_texto($plano) . '. Preencha seus dados e, em seguida, '
                    . (plano_unico($plano) ? 'pague pelo Mercado Pago (cartão, Pix ou boleto, sem precisar de conta). Pagou' : 'assine com o seu cartão de crédito. Assinou')
                    . ', já pode editar os dados, as fotos e os horários; cada mudança aparece no app depois da conferência da equipe.'
                : 'Depois de confirmarmos que você é o responsável, você poderá corrigir os dados, colocar fotos e os horários. Vamos passar o valor do plano e a forma de pagamento.')
              : 'Conte qual é o seu negócio. Vamos incluir e chamar você para completar o perfil.') ?></p>
    <?php if ($erro): ?><div class="aviso erro"><?= h($erro) ?></div><?php endif ?>
    <form method="post">
      <?= campo_csrf() ?>
      <label for="nome">Seu nome</label>
      <input id="nome" name="nome" required maxlength="120" value="<?= h($_POST['nome'] ?? ($conta['nome'] ?? '')) ?>">
      <label for="telefone">Telefone ou WhatsApp (com DDD)</label>
      <input id="telefone" name="telefone" required inputmode="tel" maxlength="20" value="<?= h($_POST['telefone'] ?? '') ?>">
      <?php if ($com_assinatura): ?>
        <label for="email"><?= plano_unico($plano) || mp_public_key() !== '' ? 'Seu e-mail' : 'E-mail (o mesmo da sua conta do Mercado Pago)' ?></label>
        <input id="email" name="email" type="email" required maxlength="160" autocomplete="email" value="<?= h($_POST['email'] ?? '') ?>">
      <?php endif ?>
      <?php if ($plano === 'site'): ?>
        <label for="mensagem">Que endereço você quer para o site? (opcional, ex.: padariadojoao.com.br)</label>
      <?php else: ?>
        <label for="mensagem"><?= $p ? 'Algo a mais? (opcional)' : 'Nome, endereço e o que o seu negócio faz' ?></label>
      <?php endif ?>
      <textarea id="mensagem" name="mensagem" rows="4" maxlength="1000"<?= $p ? '' : ' required' ?>><?= h($_POST['mensagem'] ?? '') ?></textarea>
      <p><button class="botao<?= $acao === 'remover' ? ' contorno' : '' ?>"><?= $acao === 'remover' ? 'Pedir para tirar' : ($com_assinatura ? 'Continuar para o pagamento' : 'Enviar pedido') ?></button></p>
      <p class="discreto">Usamos seu nome, telefone<?= $com_assinatura ? ' e e-mail' : '' ?> só para este pedido<?= $com_assinatura ? ' e a assinatura' : '' ?>. Veja a <a href="<?= h(config()['url']) ?>/privacidade.php">privacidade</a>.</p>
    </form>
  <?php endif ?>
</main>
<?php pagina_fim();
