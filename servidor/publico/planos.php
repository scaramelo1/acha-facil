<?php
// Planos para o dono do comércio: corrigir os dados, colocar fotos e aparecer em destaque.
// Aberto pelo app ("É o dono deste lugar?") com ?id= do comércio, ou pelo site sem id.
require __DIR__ . '/../inc/base.php';
require __DIR__ . '/../inc/guia.php';
require __DIR__ . '/../inc/pagina.php';

$id = (int)($_GET['id'] ?? 0);
$p = $id ? linha('SELECT id, nome, tipo, endereco FROM profissionais WHERE id = ? AND status = \'ativo\'', [$id]) : null;
$base = config()['url'];
$pedir = fn(string $plano) => $base . '/perfil.php?acao=assumir&plano=' . $plano . ($p ? '&id=' . $p['id'] : '');

$planos = [
    'basico' => ['Básico', 'Seu perfil completo e certinho', [
        'Corrigir nome, telefone, WhatsApp, endereço e horário',
        'Colocar fotos do lugar e dos produtos',
        'Descrição e lista do que você vende ou faz',
        'Selo "Verificado pelo Acha Fácil"',
        'Responder as avaliações dos clientes',
        'Ver quantas pessoas abriram sua página e chamaram no WhatsApp',
    ]],
    'destaque' => ['Destaque', 'Tudo do Básico e mais visibilidade', [
        'Tudo o que tem no Básico',
        'Aparece em "Em destaque" no topo da tela inicial',
        'Aparece na frente dos outros na sua área e na busca',
    ]],
];

pagina_inicio('Planos para o seu negócio · Acha Fácil!', 'Corrija os dados, coloque fotos e apareça em destaque no Acha Fácil.');
?>
<main class="documento" style="max-width:960px">
  <h1>Planos para o seu negócio</h1>
  <p>Com um plano, você corrige os dados, coloca fotos e deixa seu comércio do jeito certo para toda São João ver.</p>
  <?php if ($p): ?>
    <div class="quadro"><strong><?= h($p['nome']) ?></strong><br><?= h($p['tipo'] ?? '') ?><?= $p['endereco'] ? ' · ' . h($p['endereco']) : '' ?></div>
  <?php endif ?>

  <div class="tabela-planos">
    <?php foreach ($planos as $chave => [$nome, $sub, $itens]): ?>
      <div class="plano-cartao<?= $chave === 'destaque' ? ' destaque' : '' ?>">
        <?php if ($chave === 'destaque'): ?><span class="fita">Mais visto</span><?php endif ?>
        <h2><?= h($nome) ?></h2>
        <p class="sub"><?= h($sub) ?></p>
        <p class="valor">Valor em breve<small>assinatura mensal no cartão, cancele quando quiser</small></p>
        <ul class="lista-check">
          <?php foreach ($itens as $i): ?><li><?= h($i) ?></li><?php endforeach ?>
        </ul>
        <a class="botao<?= $chave === 'destaque' ? ' amarelo' : '' ?>" href="<?= h($pedir($chave)) ?>">Quero o <?= h($nome) ?></a>
      </div>
    <?php endforeach ?>
  </div>

  <p class="discreto" style="margin-top:28px">Antes de liberar, confirmamos por telefone ou WhatsApp que você é o responsável pelo comércio.
    <?php if ($p): ?>Não quer aparecer no app? <a href="<?= $base ?>/perfil.php?id=<?= $p['id'] ?>&amp;acao=remover">Pedir para tirar</a> (sem custo).<?php endif ?></p>
</main>
<?php pagina_fim();
