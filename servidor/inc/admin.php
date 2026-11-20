<?php
// Moldura das páginas da administração (/admin): mesma folha de estilo do site, sem indexar e sem cache.
declare(strict_types=1);
require_once __DIR__ . '/moderacao.php';

// ---------- links no admin (19/11, passo 109, pedido do usuário) ----------
// Em todo o admin, o nome do comércio abre a ficha no site e o nome da cidade abre a página da cidade (nova aba).

/** Nome do comércio como link para a ficha pública. Sem id (negócio novo) fica só o texto. */
function adm_comercio(int $id, ?string $nome, ?string $cidade = null): string {
    $nome = (string)$nome;
    if ($id <= 0) return h($nome);
    if ($cidade === null || $cidade === '') $cidade = (string)valor('SELECT cidade FROM profissionais WHERE id = ?', [$id]);
    if ($cidade === '') return h($nome);
    return '<a class="adm-link" href="' . h(url_publica(caminho_ficha($id, $nome !== '' ? $nome : 'comercio', $cidade))) . '" target="_blank" rel="noopener" title="Abrir a ficha no site">' . h($nome) . '</a>';
}

/** Nome da cidade como link para a página dela (só cidade ligada; desligada fica só o texto). $texto = o que aparece. */
function adm_cidade(?string $slug, ?string $texto = null, bool $curto = false): string {
    $slug = (string)$slug;
    $texto ??= $slug !== '' && isset(TODAS_CIDADES[$slug]) ? nome_cidade($slug, $curto) : $slug;
    if ($slug === '' || !isset(CIDADES[$slug])) return h($texto);
    return '<a class="adm-link" href="' . h(url_publica(cidade_web($slug) . '/')) . '" target="_blank" rel="noopener" title="Abrir a cidade no site">' . h($texto) . '</a>';
}

function admin_inicio(string $titulo, ?array $admin): void {
    $base = config()['url'];
    header('Cache-Control: no-store');
    $pend = $admin ? (int)valor("SELECT COUNT(*) FROM alteracoes WHERE status = 'pendente'") : 0;
    require_once __DIR__ . '/pedidos-vistos.php';   // pedido marcado "visto, aguardando retorno" não conta (passo 89)
    $novos = $admin ? count(array_filter(linhas("SELECT id, status, mp_status FROM pedidos WHERE status = 'novo'"), 'pedido_pendente')) : 0;
    require_once __DIR__ . '/eventos.php';
    $ev = $admin ? eventos_pendentes_contar() : 0;   // eventos enviados pelo público esperando autorização
    $novos += $ev;
    require_once __DIR__ . '/ofertas.php';
    $of = $admin && ofertas_prontas() ? (int)valor("SELECT COUNT(*) FROM ofertas WHERE status = 'em_analise'") : 0;   // ofertas pagas para conferir (19/11)
    $novos += $of;
    require_once __DIR__ . '/suporte.php';
    $sup = $admin ? suporte_abertos_contar() : 0;   // chamados esperando resposta (passo 53)
    require_once __DIR__ . '/novidades.php';
    $nov = $admin ? nov_aguardando_contar() : 0;   // listas do robô esperando aprovação (passo 56)
    ?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h($titulo) ?> · Admin Acha Fácil</title>
<link rel="icon" type="image/png" href="<?= $base ?>/assets/favicon.png">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@500;700;800;900&display=swap">
<link rel="stylesheet" href="<?= $base ?>/assets/site.css?v=45">
</head>
<body class="admin">
<header class="topo">
  <a class="marca" href="<?= $base ?>/admin/"><img src="<?= $base ?>/assets/logo.png" alt="" width="40" height="40"><span>Acha <b>Fácil!</b> admin</span></a>
  <?php if ($admin): ?>
  <?php
  // 19/11 (pedido do usuário): menu compacto em 4 grupos + Pendências; o número de cada grupo soma o que espera ali dentro
  $cont = fn(int $n) => $n ? ' <span class="contador">' . $n . '</span>' : '';
  $grupos = [
      ['🏪', 'Comércios', $nov + $of, [
          ['chamar.php', '📞 Quem chamar hoje', 0], ['comercios.php', 'Comércios (editar)', 0], ['ofertas.php', 'Ofertas da semana', $of],
          ['cupons.php', 'Cupons', 0], ['novidades.php', 'Novidades do robô', $nov], ['importar.php', 'Importar lista', 0],
          ['cidades.php', 'Cidades', 0], ['mapa.php', 'Mapa das cidades', 0]]],
      ['📊', 'Números', 0, [
          ['numeros.php', 'Números', 0], ['entradas.php', 'De onde vêm (entradas)', 0], ['buscas.php', 'O que procuram', 0],
          ['pedidos-info.php', 'Pedidos de informação', 0], ['google.php', 'Google por cidade', 0]]],
      ['👥', 'Pessoas', $sup, [
          ['pessoas.php', 'Pessoas', 0], ['suporte.php', 'Suporte', $sup], ['notificacoes.php', 'Notificações', 0]]],
      ['⚙️', 'Mais', $ev, [
          ['eventos.php', 'Eventos', $ev], ['app.php', 'App e downloads', 0], ['anuncios.php', 'Anúncios', 0],
          ['registro.php', 'Registro', 0], ['senha.php', 'Trocar senha', 0], ['sair.php', 'Sair', 0]]],
  ];
  $atual = basename((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
  ?>
  <nav class="nav-admin-grupos">
    <a class="nav-fixo<?= $atual === 'admin' || $atual === 'index.php' || $atual === '' ? ' atual' : '' ?>" href="<?= $base ?>/admin/">📋<span class="rotulo"> Pendências</span><?= $cont($pend + $novos) ?></a>
    <?php foreach ($grupos as $gi => [$ico, $nome, $n, $itens]):
      $aqui = in_array($atual, array_column($itens, 0), true); ?>
    <details class="menu-suspenso<?= $aqui ? ' atual' : '' ?><?= $gi === count($grupos) - 1 ? ' ultimo' : '' ?>">
      <summary><?= $ico ?><span class="rotulo"> <?= $nome ?></span><?= $cont($n) ?> <span aria-hidden="true">▾</span></summary>
      <div class="menu-lista">
        <?php foreach ($itens as [$arq, $rot, $c]): ?><a href="<?= $base ?>/admin/<?= $arq ?>"<?= $arq === $atual ? ' aria-current="page"' : '' ?>><?= h($rot) ?><?= $cont($c) ?></a><?php endforeach ?>
      </div>
    </details>
    <?php endforeach ?>
  </nav>
  <script>
  // fecha o menu suspenso ao clicar fora dele e deixa só um aberto por vez
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details.menu-suspenso[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
  });
  document.querySelectorAll('details.menu-suspenso').forEach(function (d) {
    d.addEventListener('toggle', function () { if (d.open) document.querySelectorAll('details.menu-suspenso[open]').forEach(function (o) { if (o !== d) o.removeAttribute('open'); }); });
  });
  </script>
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
