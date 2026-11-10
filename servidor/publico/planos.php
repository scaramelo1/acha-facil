<?php
// Planos para o dono do comércio: corrigir os dados, colocar fotos e aparecer em destaque.
// Aberto pelo app ("É o dono deste lugar?") com ?id= do comércio, ou pelo site sem id.
require __DIR__ . '/../inc/base.php';
require_once __DIR__ . '/../inc/guia.php';
require __DIR__ . '/../inc/pagina.php';

$id = (int)($_GET['id'] ?? 0);
$p = $id ? linha('SELECT id, nome, tipo, endereco FROM profissionais WHERE id = ? AND status = \'ativo\'', [$id]) : null;
$base = config()['url'];
// sem comércio escolhido, primeiro acha (ou inclui de graça) o negócio na busca; o plano é sempre de um comércio do app
$pedir = fn(string $plano) => $p ? $base . '/perfil.php?acao=assumir&plano=' . $plano . '&id=' . $p['id'] : $base . '/busca.php';

$planos = [
    'basico' => ['Básico', 'Seu perfil completo e certinho', [
        'Corrigir nome, telefone, WhatsApp, endereço e horário',
        'Colocar fotos do lugar e dos produtos',
        'Descrição e lista do que você vende ou faz',
    ]],
    'destaque' => ['Destaque', 'Tudo do Básico e mais visibilidade', [
        'Tudo o que tem no Básico',
        'Selo "Verificado pelo Acha Fácil"',
        'Responder as avaliações dos clientes',
        'Ver quantas pessoas abriram sua página e chamaram no WhatsApp',
        'Aparece em "Em destaque" no topo da tela inicial',
        'Aparece na frente dos outros na sua área e na busca',
    ]],
    'site' => ['Destaque + Site', 'O Destaque e um site só seu, feito pela FSA', [
        'Tudo o que tem no Destaque',
        'Site próprio do seu negócio (landing page) feito pela FSA Designer',
        'Domínio incluso (ex.: seunegocio.com.br), registro e renovação por nossa conta',
        'Botões de WhatsApp, mapa e horário no site, ligado à sua página no app',
        'Mudanças de texto e fotos do site quando você precisar',
    ]],
];
if (!plano_site_pronto()) unset($planos['site']);

pagina_inicio('Planos para o seu negócio · Acha Fácil!', 'Corrija os dados, coloque fotos, apareça em destaque e tenha um site próprio com o Acha Fácil.');
?>
<main class="documento" style="max-width:960px">
  <h1>Planos para o seu negócio</h1>
  <p>Com um plano, você corrige os dados, coloca fotos e deixa seu comércio do jeito certo para toda a cidade ver.</p>
  <?php if (!$p): ?>
    <div class="quadro">Primeiro <a href="<?= $base ?>/busca.php">procure o seu negócio</a> e toque em "É meu: ver planos".
      Ele ainda não está no app? <a href="<?= $base ?>/incluir.php">Inclua de graça</a>.</div>
  <?php endif ?>
  <?php if ($p): ?>
    <div class="quadro"><strong><?= h($p['nome']) ?></strong><br><?= h($p['tipo'] ?? '') ?><?= $p['endereco'] ? ' · ' . h($p['endereco']) : '' ?></div>
  <?php endif ?>

  <div class="tabela-planos">
    <?php foreach ($planos as $chave => [$nome, $sub, $itens]): ?>
      <div class="plano-cartao<?= $chave === 'destaque' ? ' destaque' : '' ?>">
        <?php if ($chave === 'destaque'): ?><span class="fita">Mais visto</span><?php endif ?>
        <h2><?= h($nome) ?></h2>
        <p class="sub"><?= h($sub) ?></p>
        <?php if (plano_unico($chave)): ?>
          <p class="valor"><?= h(preco_plano($chave)) ?> uma vez só<small>pagamento único, sem mensalidade (cartão, Pix ou boleto)</small></p>
        <?php else: ?>
          <p class="valor"><?= h(preco_plano($chave)) ?> por mês<small>assinatura mensal no cartão, cancele quando quiser</small></p>
        <?php endif ?>
        <ul class="lista-check">
          <?php foreach ($itens as $i): ?><li><?= h($i) ?></li><?php endforeach ?>
        </ul>
        <?php if ($chave === 'site'): ?><p class="discreto" style="margin:0 0 14px">Depois do pagamento, chamamos você no WhatsApp para montar o site.
          O site e o domínio continuam enquanto a assinatura estiver em dia.</p><?php endif ?>
        <a class="botao<?= $chave === 'destaque' ? ' amarelo' : '' ?>" href="<?= h($pedir($chave)) ?>">Quero o <?= h($nome) ?></a>
      </div>
    <?php endforeach ?>
  </div>

  <p class="discreto" style="margin-top:28px">Antes de liberar, confirmamos por telefone ou WhatsApp que você é o responsável pelo comércio.
    <?php if ($p): ?>Não quer aparecer no app? <a href="<?= $base ?>/perfil.php?id=<?= $p['id'] ?>&amp;acao=remover">Pedir para tirar</a> (sem custo).<?php endif ?></p>
</main>
<?php pagina_fim();
