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
<link rel="stylesheet" href="<?= $base ?>/assets/site.css?v=43">
</head>
<body class="admin">
<header class="topo">
  <a class="marca" href="<?= $base ?>/admin/"><img src="<?= $base ?>/assets/logo.png" alt="" width="40" height="40"><span>Acha <b>Fácil!</b> admin</span></a>
  <?php if ($admin): ?>
  <nav>
    <a href="<?= $base ?>/admin/">Pendências<?= $pend + $novos ? ' <span class="contador">' . ($pend + $novos) . '</span>' : '' ?></a>
    <a href="<?= $base ?>/admin/numeros.php">Números</a>
    <a href="<?= $base ?>/admin/chamar.php">📞 Quem chamar</a>
    <a href="<?= $base ?>/admin/pedidos-info.php">Pedidos de informação</a>
    <a href="<?= $base ?>/admin/cidades.php">Cidades</a>
    <a href="<?= $base ?>/admin/novidades.php">Novidades<?= $nov ? ' <span class="contador">' . $nov . '</span>' : '' ?></a>
    <a href="<?= $base ?>/admin/pessoas.php">Pessoas</a>
    <a href="<?= $base ?>/admin/suporte.php">Suporte<?= $sup ? ' <span class="contador">' . $sup . '</span>' : '' ?></a>
    <details class="menu-suspenso">
      <summary>Mais<?= $ev ? ' <span class="contador">' . $ev . '</span>' : '' ?> <span aria-hidden="true">▾</span></summary>
      <div class="menu-lista">
        <a class="so-celular" href="<?= $base ?>/admin/">Pendências<?= $pend + $novos ? ' <span class="contador">' . ($pend + $novos) . '</span>' : '' ?></a>
        <a class="so-celular" href="<?= $base ?>/admin/numeros.php">Números</a>
        <a class="so-celular" href="<?= $base ?>/admin/chamar.php">📞 Quem chamar</a>
        <a class="so-celular" href="<?= $base ?>/admin/pedidos-info.php">Pedidos de informação</a>
        <a class="so-celular" href="<?= $base ?>/admin/cidades.php">Cidades</a>
        <a class="so-celular" href="<?= $base ?>/admin/novidades.php">Novidades<?= $nov ? ' <span class="contador">' . $nov . '</span>' : '' ?></a>
        <a href="<?= $base ?>/admin/mapa.php">Mapa das cidades</a>
        <a href="<?= $base ?>/admin/comercios.php">Comércios (editar)</a>
        <a href="<?= $base ?>/admin/buscas.php">O que procuram</a>
        <a href="<?= $base ?>/admin/google.php">Google por cidade</a>
        <a href="<?= $base ?>/admin/importar.php">Importar lista</a>
        <a class="so-celular" href="<?= $base ?>/admin/pessoas.php">Pessoas</a>
        <a class="so-celular" href="<?= $base ?>/admin/suporte.php">Suporte<?= $sup ? ' <span class="contador">' . $sup . '</span>' : '' ?></a>
        <a href="<?= $base ?>/admin/notificacoes.php">Notificações</a>
        <a href="<?= $base ?>/admin/cupons.php">Cupons</a>
        <a href="<?= $base ?>/admin/eventos.php">Eventos<?= $ev ? ' <span class="contador">' . $ev . '</span>' : '' ?></a>
        <a href="<?= $base ?>/admin/ofertas.php">Ofertas da semana<?= $of ? ' <span class="contador">' . $of . '</span>' : '' ?></a>
        <a href="<?= $base ?>/admin/app.php">App e downloads</a>
        <a href="<?= $base ?>/admin/anuncios.php">Anúncios</a>
        <a href="<?= $base ?>/admin/registro.php">Registro</a>
        <a href="<?= $base ?>/admin/senha.php">Trocar senha</a>
        <a href="<?= $base ?>/admin/sair.php">Sair</a>
      </div>
    </details>
  </nav>
  <script>
  // fecha o menu suspenso ao clicar fora dele
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details.menu-suspenso[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
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
