<?php
// Busca no site, para o dono achar o próprio negócio e assumir o perfil (ou pedir para tirar do app)
require __DIR__ . '/../inc/base.php';
require __DIR__ . '/../inc/guia.php';
require __DIR__ . '/../inc/pagina.php';

$termo = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 60));
$achados = mb_strlen($termo) >= 2 ? buscar($termo, 30) : [];
$base = config()['url'];

pagina_inicio('Procure seu negócio · Acha Fácil!', 'Ache o seu comércio no Acha Fácil e assuma o perfil, de graça.');
?>
<main class="documento">
  <h1>Procure seu negócio</h1>
  <p>Ache o seu comércio para <strong>assumir o perfil</strong> (de graça) ou pedir para <strong>tirar do app</strong>.</p>
  <form class="procurar" method="get">
    <input name="q" value="<?= h($termo) ?>" placeholder="Nome do seu comércio" aria-label="Nome do seu comércio" required minlength="2" autofocus>
    <button class="botao">Procurar</button>
  </form>
  <?php if ($termo !== '' && !$achados): ?>
    <div class="aviso erro">Nada encontrado com "<?= h($termo) ?>". Tente uma parte do nome.</div>
    <p>Seu negócio não está no app? <a href="<?= $base ?>/perfil.php?acao=assumir">Peça para incluir</a>.</p>
  <?php elseif ($achados): ?>
    <ul class="resultados">
      <?php foreach ($achados as $p): ?>
        <li>
          <span><b><?= h($p['nome']) ?></b><small><?= h($p['tipo']) ?><?= $p['bairro'] ? ' · ' . h($p['bairro']) : '' ?></small></span>
          <span class="acoes">
            <?php if ($p['cadastrado']): ?><small>Perfil já assumido</small>
            <?php else: ?>
              <a href="<?= $base ?>/perfil.php?id=<?= $p['id'] ?>&amp;acao=assumir">É meu: assumir</a>
              <a class="remover" href="<?= $base ?>/perfil.php?id=<?= $p['id'] ?>&amp;acao=remover">Tirar do app</a>
            <?php endif ?>
          </span>
        </li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>
</main>
<?php pagina_fim();
