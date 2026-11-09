<?php
// Moldura das páginas da administração (/admin): mesma folha de estilo do site, sem indexar e sem cache.
declare(strict_types=1);
require_once __DIR__ . '/moderacao.php';

function admin_inicio(string $titulo, ?array $admin): void {
    $base = config()['url'];
    header('Cache-Control: no-store');
    $pend = $admin ? (int)valor("SELECT COUNT(*) FROM alteracoes WHERE status = 'pendente'") : 0;
    $novos = $admin ? (int)valor("SELECT COUNT(*) FROM pedidos WHERE status = 'novo'") : 0;
    ?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h($titulo) ?> · Admin Acha Fácil</title>
<link rel="icon" type="image/png" href="<?= $base ?>/assets/favicon.png">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@500;700;800;900&display=swap">
<link rel="stylesheet" href="<?= $base ?>/assets/site.css?v=11">
</head>
<body class="admin">
<header class="topo">
  <a class="marca" href="<?= $base ?>/admin/"><img src="<?= $base ?>/assets/logo.png" alt="" width="40" height="40"><span>Acha <b>Fácil!</b> admin</span></a>
  <?php if ($admin): ?>
  <nav>
    <a href="<?= $base ?>/admin/">Pendências<?= $pend + $novos ? ' <span class="contador">' . ($pend + $novos) . '</span>' : '' ?></a>
    <a href="<?= $base ?>/admin/cupons.php">Cupons</a>
    <a href="<?= $base ?>/admin/registro.php">Registro</a>
    <a href="<?= $base ?>/admin/senha.php">Trocar senha</a>
    <a href="<?= $base ?>/admin/sair.php">Sair</a>
  </nav>
  <?php endif ?>
</header>
<main class="documento largo">
<?php
    mostrar_flash();
}

function admin_fim(): void {
    echo "</main>\n</body>\n</html>\n";
}

const TIPO_PEDIDO = ['assumir' => 'Assumir o perfil', 'remover' => 'Tirar do app', 'incluir' => 'Incluir negócio'];
