<?php
// Pedido do dono: assumir o perfil de um comércio importado (ou incluir um novo) ou tirar do app.
// Fica em "pedidos" para a equipe conferir (ligando no telefone do comércio) antes de liberar ou retirar.
require __DIR__ . '/../inc/base.php';
require __DIR__ . '/../inc/guia.php';
require __DIR__ . '/../inc/pagina.php';
iniciar_sessao();

$acao = ($_GET['acao'] ?? '') === 'remover' ? 'remover' : 'assumir';
$plano = in_array($_GET['plano'] ?? '', ['basico', 'destaque'], true) ? $_GET['plano'] : null;
if ($acao === 'remover') $plano = null;
$id = (int)($_GET['id'] ?? 0);
$p = $id ? linha('SELECT p.id, p.nome, p.tipo, p.endereco FROM profissionais p WHERE p.id = ? AND p.importado = 1', [$id]) : null;
if ($acao === 'remover' && !$p) ir('busca.php');
$enviado = false;
$erro = null;

if (e_post()) {
    exigir_post();
    $nome = txt('nome', 120);
    $telefone = telefone_normalizado(txt('telefone', 20));
    $mensagem = txt('mensagem', 1000);
    if (!$nome || !$telefone) $erro = 'Preencha seu nome e um telefone com DDD.';
    elseif ((int)valor('SELECT COUNT(*) FROM pedidos WHERE ip = ? AND criado_em > NOW() - INTERVAL 1 DAY', [ip()]) >= 10) $erro = 'Muitos pedidos hoje. Tente amanhã.';
    else {
        inserir('pedidos', ['profissional_id' => $p['id'] ?? null, 'tipo' => $acao, 'plano' => $plano, 'nome' => $nome, 'telefone' => $telefone, 'mensagem' => $mensagem, 'ip' => ip()]);
        $enviado = true;
    }
}

$titulo = $acao === 'remover' ? 'Tirar do app' : ($plano ? 'Plano ' . ($plano === 'destaque' ? 'Destaque' : 'Básico') : ($p ? 'Assumir o perfil' : 'Incluir meu negócio'));
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
        : ($p ? 'Depois de confirmarmos que você é o responsável, você poderá corrigir os dados, colocar foto, horários e ganhar o selo de verificado. Vamos passar o valor do plano e a forma de pagamento.'
              : 'Conte qual é o seu negócio. Vamos incluir e chamar você para completar o perfil.') ?></p>
    <?php if ($erro): ?><div class="aviso erro"><?= h($erro) ?></div><?php endif ?>
    <form method="post">
      <?= campo_csrf() ?>
      <label for="nome">Seu nome</label>
      <input id="nome" name="nome" required maxlength="120" value="<?= h($_POST['nome'] ?? '') ?>">
      <label for="telefone">Telefone ou WhatsApp (com DDD)</label>
      <input id="telefone" name="telefone" required inputmode="tel" maxlength="20" value="<?= h($_POST['telefone'] ?? '') ?>">
      <label for="mensagem"><?= $p ? 'Algo a mais? (opcional)' : 'Nome, endereço e o que o seu negócio faz' ?></label>
      <textarea id="mensagem" name="mensagem" rows="4" maxlength="1000"<?= $p ? '' : ' required' ?>><?= h($_POST['mensagem'] ?? '') ?></textarea>
      <p><button class="botao<?= $acao === 'remover' ? ' contorno' : '' ?>"><?= $acao === 'remover' ? 'Pedir para tirar' : 'Enviar pedido' ?></button></p>
      <p class="discreto">Usamos seu nome e telefone só para responder este pedido. Veja a <a href="<?= h(config()['url']) ?>/privacidade.php">privacidade</a>.</p>
    </form>
  <?php endif ?>
</main>
<?php pagina_fim();
