<?php
// Visual comum das páginas públicas (divulgação, busca, termos, privacidade): creme, verde e amarelo da marca,
// cantos redondos e letra grande (Nunito), como o app.
declare(strict_types=1);
require_once __DIR__ . '/guia.php';   // texto_cidades() no rodapé

/** Contatos e links das lojas, definidos em /etc/acha-facil/config.php (vazio = ainda não existe). */
function site(string $chave): string {
    return trim((string)(config()[$chave] ?? ''));
}

/** Como falar com o Acha Fácil: e-mail, WhatsApp e Instagram que estiverem no config. */
function contatos_html(): string {
    $itens = [];
    if ($e = site('contato_email')) $itens[] = '<a href="mailto:' . h($e) . '">' . h($e) . '</a>';
    if ($w = site('contato_whatsapp')) $itens[] = '<a href="' . h(link_whatsapp($w, 'Olá! Vim pelo site do Acha Fácil.')) . '">WhatsApp ' . h(telefone_formatado($w)) . '</a>';
    if ($i = site('instagram')) $itens[] = '<a href="https://instagram.com/' . h(ltrim($i, '@')) . '">Instagram @' . h(ltrim($i, '@')) . '</a>';
    return $itens ? implode(' · ', $itens) : 'pelo Instagram do Acha Fácil';
}

// Emoji de enfeite por ícone da área (o site não carrega a fonte de ícones do app)
const EMOJI_AREAS = ['silverware-fork-knife' => '🍽️', 'cart-outline' => '🛒', 'tools' => '🛠️', 'heart-pulse' => '🩺', 'lipstick' => '💄',
    'tshirt-crew-outline' => '👕', 'home-roof' => '🏠', 'car-wrench' => '🚗', 'shopping-outline' => '🛍️', 'school-outline' => '🎓',
    'dumbbell' => '🏋️', 'paw' => '🐾', 'bed-outline' => '🛏️', 'bank-outline' => '🏦', 'factory' => '🏭', 'hand-heart-outline' => '🤝'];
function emoji_area(?string $icone): string { return EMOJI_AREAS[$icone] ?? '📍'; }

/**
 * Começo da página. $opc (páginas públicas da cidade, passo 21): 'canonica' (endereço completo), 'noindex' (true = o Google
 * não guarda), 'imagem' (prévia do link), 'head' (HTML a mais no <head>, ex.: dados estruturados).
 */
function pagina_inicio(string $titulo, string $descricao, string $classe = '', array $opc = []): void {
    $base = config()['url'];
    $canonica = $opc['canonica'] ?? url_publica(basename($_SERVER['SCRIPT_NAME']) === 'index.php' ? '' : basename($_SERVER['SCRIPT_NAME']));
    ?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($titulo) ?></title>
<meta name="description" content="<?= h($descricao) ?>">
<link rel="canonical" href="<?= h($canonica) ?>">
<?php if (!empty($opc['noindex'])): ?><meta name="robots" content="noindex, follow">
<?php endif ?>
<meta property="og:url" content="<?= h($canonica) ?>">
<meta property="og:title" content="<?= h($titulo) ?>">
<meta property="og:description" content="<?= h($descricao) ?>">
<meta property="og:image" content="<?= h($opc['imagem'] ?? url_publica('assets/logo.png')) ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="pt_BR">
<link rel="icon" href="<?= $base ?>/assets/favicon.ico" sizes="any">
<link rel="icon" type="image/png" href="<?= $base ?>/assets/favicon.png">
<link rel="apple-touch-icon" href="<?= $base ?>/assets/apple-touch-icon.png">
<meta name="theme-color" content="#005c38">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@500;700;800;900&display=swap">
<link rel="stylesheet" href="<?= $base ?>/assets/site.css?v=18">
<?= $opc['head'] ?? '' ?>
</head>
<body class="<?= h($classe) ?>">
<header class="topo">
  <a class="marca" href="<?= $base ?>/"><img src="<?= $base ?>/assets/logo.png" alt="" width="40" height="40"><span>Acha <b>Fácil!</b></span></a>
  <nav><a href="<?= $base ?>/#cidades">Cidades</a><a href="<?= $base ?>/#seu-negocio">Seu negócio</a><a href="<?= $base ?>/#baixar" class="nav-botao">Baixar o app</a></nav>
</header>
<?php
}

function pagina_fim(): void {
    $base = config()['url'];
    ?>
<footer class="rodape">
  <p class="rodape-marca"><img src="<?= $base ?>/assets/logo.png" alt="" width="36" height="36"> <strong>Acha <b>Fácil!</b></strong></p>
  <p class="lema">O comércio da sua cidade, pertinho de você.</p>
  <p class="rodape-cidades"><?php foreach (CIDADES as $slug => [$nome, , $uf]): ?><a href="<?= h(url(cidade_web($slug) . '/')) ?>"><?= h("$nome - $uf") ?></a> <?php endforeach ?></p>
  <?php require_once __DIR__ . '/eventos.php'; if (eventos_prontos()): ?>
  <p class="rodape-cidades rodape-eventos">📅 Agenda de eventos: <?php foreach (CIDADES as $slug => [$nome]): ?><a href="<?= h(url_eventos($slug)) ?>"><?= h($nome) ?></a> <?php endforeach ?></p>
  <?php endif ?>
  <p><a href="<?= $base ?>/busca.php">Procurar meu negócio</a> · <a href="<?= $base ?>/termos.php">Termos de uso</a> · <a href="<?= $base ?>/privacidade.php">Privacidade</a> · <a href="<?= $base ?>/privacidade.php#excluir-conta">Excluir minha conta</a></p>
  <p class="discreto"><?= h(texto_cidades()) ?> · <?= date('Y') ?></p>
</footer>
</body>
</html>
<?php
}

/** Botão "Baixar APK" com versão, tamanho, data da última atualização (o total de downloads fica só para a administração). */
function botao_apk(): string {
    require_once __DIR__ . '/apk.php';
    $base = config()['url'];
    $icone = '<svg viewBox="0 0 24 24" width="30" height="30" fill="currentColor" aria-hidden="true"><path d="M12 3a1 1 0 0 1 1 1v9.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-5 5a1 1 0 0 1-1.4 0l-5-5a1 1 0 1 1 1.4-1.4l3.3 3.3V4a1 1 0 0 1 1-1ZM5 19h14a1 1 0 1 1 0 2H5a1 1 0 1 1 0-2Z"/></svg>';
    $a = apk_info();
    if (!$a) return '<div class="apk"><span class="loja breve apk-botao" aria-disabled="true">' . $icone . '<span><small>Android · em breve</small>Baixar APK</span></span></div>';
    $meta = [];
    if ($a['versao'] !== '') $meta[] = 'Versão ' . h($a['versao']);
    $meta[] = h($a['mb']);
    return '<div class="apk"><a class="loja apk-botao" href="' . $base . '/baixar-apk.php" download>' . $icone
        . '<span><small>Android · direto do site</small>Baixar APK</span></a>'
        . '<p class="apk-info"><span>Atualizado em <b>' . h($a['atualizado']) . '</b></span><span>' . implode(' · ', $meta) . '</span></p>'
        . '<p class="apk-ajuda"><a href="' . $base . '/#instalar-apk">Como instalar o APK</a></p></div>';
}

/** Botões das lojas: link quando existe, "em breve" quando ainda não. */
function botoes_lojas(): string {
    $lojas = [
        ['link_play', 'Google Play', 'Android', '<g fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"><path d="M3.9 2.6c-.6-.3-1.3.1-1.3.8v17.2c0 .7.7 1.1 1.3.8l16.6-8.6c.7-.4.7-1.3 0-1.7Z"/><path d="M3.2 2.5 16.2 15M3.2 21.5 16.2 9"/></g>'],
        ['link_app_store', 'App Store', 'iPhone', '<path d="M16.4 12.6c0-2.4 2-3.5 2-3.6a4.3 4.3 0 0 0-3.4-1.8c-1.4-.2-2.8.8-3.5.8s-1.9-.8-3-.8a4.6 4.6 0 0 0-3.9 2.4c-1.7 2.9-.4 7.2 1.2 9.6.8 1.2 1.8 2.5 3 2.4 1.2 0 1.7-.8 3.1-.8s1.9.8 3.1.8 2.1-1.2 2.9-2.4a10 10 0 0 0 1.3-2.7 4.1 4.1 0 0 1-2.8-3.9ZM14 5.5A4.1 4.1 0 0 0 15 2.5a4.2 4.2 0 0 0-2.8 1.4 4 4 0 0 0-1 2.9A3.5 3.5 0 0 0 14 5.5Z"/>'],
    ];
    $html = botao_apk();
    foreach ($lojas as [$chave, $nome, $sistema, $svg]) {
        $icone = '<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor" aria-hidden="true">' . $svg . '</svg>';
        $link = site($chave);
        $html .= $link
            ? '<a class="loja" href="' . h($link) . '">' . $icone . '<span><small>Baixar no</small>' . $nome . '</span></a>'
            : '<span class="loja breve" aria-disabled="true">' . $icone . '<span><small>' . $sistema . ' · em breve</small>' . $nome . '</span></span>';
    }
    return $html;
}
