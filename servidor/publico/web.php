<?php
// Rotas das páginas públicas (passo 21): o .htaccess manda para cá todo endereço que não é arquivo nem pasta.
// Páginas e regras em inc/web.php.
require __DIR__ . '/../inc/base.php';
require __DIR__ . '/../inc/web.php';
require __DIR__ . '/../inc/eventos.php';

$caminho = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$base = config()['url'];
if ($base !== '' && str_starts_with($caminho, $base)) $caminho = substr($caminho, strlen($base));
$caminho = rawurldecode($caminho);
$partes = array_values(array_filter(explode('/', $caminho), fn($s) => $s !== ''));
$barra = str_ends_with($caminho, '/');
$get = fn(string $k) => ($v = trim(mb_substr((string)($_GET[$k] ?? ''), 0, 80))) === '' ? null : $v;

function redirecionar(string $para): never {
    $q = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . $para . ($q !== '' ? '?' . $q : ''), true, 301);
    exit;
}
function so_get(): void {
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) { http_response_code(405); exit; }
}
so_get();

// sitemap (dividido por cidade e tipo; ver inc/web.php)
if (count($partes) === 1 && preg_match('/^sitemap(?:-([a-z0-9-]+))?\.xml$/', $partes[0], $m)) {
    $nome = $m[1] ?? '';
    $tipos = web_sitemap_tipos();
    $gerar = null;
    if ($nome === '') $gerar = fn() => web_sitemap_indice();
    elseif ($nome === 'paginas') $gerar = fn() => web_sitemap_paginas();
    elseif (isset($tipos[$nome])) $gerar = fn() => web_sitemap_indice_tipo($nome);
    elseif ($c = cidade_da_web($nome)) $gerar = fn() => web_sitemap_indice_cidade($c);
    elseif (preg_match('/^(.+)-(' . implode('|', array_keys($tipos)) . ')$/', $nome, $mt) && ($c = cidade_da_web($mt[1])))
        $gerar = fn() => web_sitemap_cidade($c, $mt[2]);
    if (!$gerar) { web_pagina_404(); exit; }
    header('Content-Type: application/xml; charset=utf-8');
    header('X-Robots-Tag: noindex');
    echo web_cache('sitemap2|' . $partes[0], function () use ($gerar) { echo $gerar(); }, 6 * 3600);
    exit;
}

if (!$partes) { web_pagina_404(); exit; }

// cidade: nome completo; o slug curto antigo (sao-joao) redireciona
$cidade = cidade_da_web($partes[0]);
if (!$cidade && isset(CIDADES[$partes[0]]) && cidade_web($partes[0]) !== $partes[0]) {
    $partes[0] = cidade_web($partes[0]);
    redirecionar(url(implode('/', $partes) . ($barra ? '/' : '')));
}
if (!$cidade) { web_pagina_404(); exit; }
$GLOBALS['og_cidade'] = $cidade;   // 19/11: imagem de compartilhar da cidade (pagina_inicio)
$n = count($partes);

// de onde a pessoa chegou ao site (passo 38): conta a entrada e guarda a origem para a ficha
$origem_site = registrar_entrada_site(match (true) {
    $n >= 3 && $partes[1] === 'c' => 'ficha',
    $n >= 2 && $partes[1] === 'eventos' => $n === 2 ? 'eventos' : 'evento',
    $n >= 2 && $partes[1] === 'buscar' => 'busca',
    $n === 1 => 'cidade',
    default => 'lista',
}, $cidade);

// ficha: /<cidade>/c/<nome>-<id>
if ($n === 3 && $partes[1] === 'c') {
    $p = preg_match('/-(\d{1,9})$/', $partes[2], $m) || preg_match('/^(\d{1,9})$/', $partes[2], $m) ? web_ficha((int)$m[1]) : null;
    if (!$p) { web_pagina_404(); exit; }
    $certo = url_ficha($p);
    if ($certo !== url(implode('/', $partes))) redirecionar($certo);   // nome ou cidade mudou: endereço certo
    // 16/11: pré-carregamento do Chrome não conta agora; a página conta sozinha se a pessoa abrir (visita_adiada_js)
    if (e_pre_carregamento()) $GLOBALS['visita_adiada'] = $origem_site;
    else contar_visita((int)$p['id'], $origem_site);
    web_pagina_ficha($p);
    exit;
}

// busca
if ($n === 2 && $partes[1] === 'buscar') {
    header('X-Robots-Tag: noindex');
    web_pagina_busca($cidade, (string)$get('q'), isset($_GET['aberto']));   // 19/11: ?aberto=1
    exit;
}

// eventos (passo 32): /<cidade>/eventos/ e /<cidade>/eventos/<nome>-<id>
if ($n >= 2 && $partes[1] === 'eventos' && eventos_prontos()) {
    if ($n === 2) {
        if (!$barra) redirecionar(url(implode('/', $partes) . '/'));
        echo web_cache('eventos|' . $cidade, fn() => web_pagina_eventos($cidade));
        exit;
    }
    $e = $n === 3 && preg_match('/-(\d{1,9})$/', $partes[2], $m) ? evento((int)$m[1]) : null;
    if (!$e || $e['cidade'] !== $cidade) { web_pagina_404(); exit; }
    $certo = url_evento($e);
    if ($certo !== url(implode('/', $partes))) redirecionar($certo);
    echo web_cache('evento|' . $e['id'], fn() => web_pagina_evento($e));
    exit;
}

// listas terminam com barra
if ($n <= 3 && !$barra) redirecionar(url(implode('/', $partes) . '/'));

if ($n === 1) {
    echo web_cache('cidade|' . $cidade, fn() => web_pagina_cidade($cidade));
    exit;
}

$area = web_categorias()[$partes[1]] ?? null;
if (!$area || $n > 3) { web_pagina_404(); exit; }
$tipo = null;
if ($n === 3) {
    $tipo = web_tipo_do_slug($cidade, (int)$area['id'], $partes[2]);
    if ($tipo === null) { web_pagina_404(); exit; }
}
// ?pagina=1 (ou inválida) vai para o endereço sem página
$pagina = (int)($get('pagina') ?? 1);
if (isset($_GET['pagina']) && $pagina <= 1) {
    $q = $_GET; unset($q['pagina']);
    header('Location: ' . strtok((string)$_SERVER['REQUEST_URI'], '?') . ($q ? '?' . http_build_query($q) : ''), true, 301);
    exit;
}
$bairro = $get('bairro');
// página além do fim, ou área sem nenhum comércio na cidade = 404
$total = web_contar($cidade, (int)$area['id'], $tipo, $bairro);
if (($total === 0 && $bairro === null) || ($pagina - 1) * WEB_POR_PAGINA >= max(1, $total)) { web_pagina_404(); exit; }
// 19/11: "Aberto agora" (?aberto=1) muda a cada minuto: sem cache e fora do Google
if (isset($_GET['aberto'])) {
    header('X-Robots-Tag: noindex');
    web_pagina_lista($cidade, $area, $tipo, $bairro, 1, $total, true);
    exit;
}
echo web_cache("lista|$cidade|{$area['slug']}|" . ($tipo ?? '') . '|' . ($bairro ?? '') . "|$pagina",
    fn() => web_pagina_lista($cidade, $area, $tipo, $bairro, $pagina, $total));
