<?php
// Busca no site, para o dono achar o próprio negócio e assumir o perfil (ou pedir para tirar do app)
require __DIR__ . '/../inc/base.php';
require_once __DIR__ . '/../inc/guia.php';
require __DIR__ . '/../inc/pagina.php';

// Primeiro a cidade (pedido do usuário, 10/11): sem ela aparecem muitos comércios com o mesmo nome em cidades diferentes
$cidade = isset(CIDADES[$_GET['cidade'] ?? '']) ? (string)$_GET['cidade'] : null;
$termo = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 60));
$achados = $cidade && mb_strlen($termo) >= 2 ? buscar($termo, $cidade, 30) : [];
$base = config()['url'];

pagina_inicio('Procure seu negócio · Acha Fácil!', 'Ache o seu comércio no Acha Fácil e confira os planos para corrigir os dados e colocar fotos.');
?>
<main class="documento">
  <h1>Procure seu negócio</h1>
  <p>Ache o seu comércio para <strong>conferir os planos</strong> (corrigir os dados e colocar fotos) ou pedir para <strong>tirar do app</strong>.</p>
  <form class="procurar" method="get">
    <select name="cidade" aria-label="Cidade do seu comércio" required<?= $cidade ? '' : ' autofocus' ?>><?= opcoes_cidades($cidade) ?></select>
    <input name="q" value="<?= h($termo) ?>" placeholder="Nome do seu comércio" aria-label="Nome do seu comércio" required minlength="2"<?= $cidade ? ' autofocus' : '' ?>>
    <button class="botao">Procurar</button>
  </form>
  <?php if ($termo !== '' && !$cidade): ?>
    <div class="aviso erro">Escolha primeiro a cidade do seu comércio.</div>
  <?php elseif ($termo !== '' && !$achados): ?>
    <div class="aviso erro">Nada encontrado com "<?= h($termo) ?>" em <?= h(nome_cidade($cidade)) ?>. Tente uma parte do nome.</div>
    <p>Seu negócio não está no app? <a class="botao amarelo" href="<?= $base ?>/incluir.php">Incluir de graça</a></p>
  <?php elseif ($achados): ?>
    <ul class="resultados">
      <?php foreach ($achados as $p): ?>
        <li>
          <span><b><?= h($p['nome']) ?></b><small><?= h($p['tipo']) ?> · <?= h($p['cidade']) ?><?= $p['bairro'] ? ' · ' . h($p['bairro']) : '' ?></small></span>
          <span class="acoes">
            <?php if ($p['cadastrado']): ?><small>Perfil já assumido</small>
            <?php else: ?>
              <a href="<?= $base ?>/planos.php?id=<?= $p['id'] ?>">É meu: ver planos</a>
              <a class="remover" href="<?= $base ?>/perfil.php?id=<?= $p['id'] ?>&amp;acao=remover">Tirar do app</a>
            <?php endif ?>
          </span>
        </li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>
  <p class="discreto">Não achou? <a href="<?= $base ?>/incluir.php">Inclua o seu negócio de graça</a>.</p>
</main>
<?php pagina_fim();
