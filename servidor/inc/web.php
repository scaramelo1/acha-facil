<?php
// Versão web com SEO (passo 21): páginas públicas de cidade, área, tipo e ficha, para quem procura no Google
// ("dentista em São João da Boa Vista") cair no Acha Fácil. Só mostra o que já aparece no app (visivel()).
// Endereços (helpers em inc/guia.php):
//   /<cidade>/                    cidade (nome completo: sao-joao-da-boa-vista)
//   /<cidade>/<area>/             área (slug da categoria)       ?pagina=2  ?bairro=Centro (filtro não indexado)
//   /<cidade>/<area>/<tipo>/      tipo de negócio (indexado só com 3+ comércios)
//   /<cidade>/c/<nome>-<id>       ficha do comércio
//   /<cidade>/buscar?q=           busca (não indexada)
//   /<cidade>/eventos/ e /<cidade>/eventos/<nome>-<id>   agenda de eventos (inc/eventos.php, passo 32)
//   /sitemap.xml e /sitemap-<cidade>.xml
// Rotas: publico/.htaccess manda o que não é arquivo para publico/web.php.
declare(strict_types=1);
require_once __DIR__ . '/guia.php';
require_once __DIR__ . '/pagina.php';

const WEB_POR_PAGINA = 30;
const WEB_TIPO_MINIMO = 3;          // tipo com menos comércios que isso não vai para o Google (página fraca)
const WEB_CACHE_SEGUNDOS = 3600;    // listas guardadas prontas por 1 hora (VPS de 1 GB e robôs de busca)

// ---------- cache em arquivo ----------
/** Devolve o HTML guardado (até 1 h) ou gera com $gerar (que dá echo) e guarda. Sem pasta gravável, só gera. */
function web_cache(string $chave, callable $gerar, int $segundos = WEB_CACHE_SEGUNDOS): string {
    $dir = web_cache_dir();
    $arq = $dir ? "$dir/" . hash('sha256', $chave . '|' . hoje()) . '.html' : null;   // hoje(): a ordem muda todo dia
    if ($arq && is_file($arq) && filemtime($arq) > time() - $segundos) return (string)file_get_contents($arq);
    ob_start();
    $gerar();
    $html = (string)ob_get_clean();
    if ($arq) {
        @file_put_contents("$arq.tmp", $html, LOCK_EX);
        @rename("$arq.tmp", $arq);
        if (random_int(1, 200) === 1) web_cache_limpar(2 * 86400);   // de vez em quando apaga os velhos
    }
    return $html;
}

// ---------- dados ----------
function web_categorias(): array {
    static $c = null;
    if ($c !== null) return $c;
    $c = [];
    foreach (linhas('SELECT id, nome, slug, icone, cor FROM categorias WHERE ativa = 1 ORDER BY ordem, nome') as $l) $c[$l['slug']] = $l;
    return $c;
}
/** Áreas da cidade com o total (só as que têm comércio). */
function web_areas(string $cidade): array {
    return array_filter(categorias_ativas($cidade), fn($a) => $a['total'] > 0);
}
function web_total_cidade(string $cidade): int {
    return (int)valor('SELECT COUNT(*) FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE ' . visivel($cidade));
}
/** Tipos da área com 3+ comércios: [nome, total]. */
function web_tipos(string $cidade, int $categoria_id, int $minimo = WEB_TIPO_MINIMO): array {
    return linhas('SELECT p.tipo AS nome, COUNT(*) AS total FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE ' . visivel($cidade) . ' AND p.categoria_id = ? AND p.tipo IS NOT NULL AND p.tipo <> \'\'
        GROUP BY p.tipo HAVING total >= ? ORDER BY total DESC, p.tipo', [$categoria_id, $minimo]);
}
/** Slug do tipo no endereço -> nome do tipo como está no banco (ou null). */
function web_tipo_do_slug(string $cidade, int $categoria_id, string $slug): ?string {
    foreach (linhas('SELECT DISTINCT p.tipo FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE ' . visivel($cidade) . ' AND p.categoria_id = ? AND p.tipo IS NOT NULL', [$categoria_id]) as $t)
        if (slug_web($t['tipo']) === $slug) return $t['tipo'];
    return null;
}
/** Filtro da lista (área, tipo e bairro) em SQL + parâmetros. */
function web_filtro(string $cidade, int $categoria_id, ?string $tipo, ?string $bairro): array {
    $sql = visivel($cidade) . ' AND p.categoria_id = ?';
    $par = [$categoria_id];
    if ($tipo !== null) { $sql .= ' AND p.tipo = ?'; $par[] = $tipo; }
    if ($bairro !== null) { $sql .= ' AND p.bairro = ?'; $par[] = $bairro; }
    return [$sql, $par];
}
/** Uma página da lista, na mesma ordem justa do app (Destaque, assumidos, com avaliação, sorteio do dia). */
function web_contar(string $cidade, int $categoria_id, ?string $tipo, ?string $bairro): int {
    [$sql, $par] = web_filtro($cidade, $categoria_id, $tipo, $bairro);
    return (int)valor("SELECT COUNT(*) FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE $sql", $par);
}
function web_lista(string $cidade, int $categoria_id, ?string $tipo, ?string $bairro, int $pagina): array {
    [$sql, $par] = web_filtro($cidade, $categoria_id, $tipo, $bairro);
    return linhas('SELECT ' . CAMPOS_RESUMO . ", p.endereco FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE $sql ORDER BY " . SQL_DESTAQUE . ' DESC, p.importado ASC, p.total_avaliacoes > 0 DESC, RAND(?)
        LIMIT ' . WEB_POR_PAGINA . ' OFFSET ' . (max(1, $pagina) - 1) * WEB_POR_PAGINA, [...$par, semente_dia()]);
}
/** Bairros da lista (sem o filtro de bairro), para o filtro. */
function web_bairros(string $cidade, int $categoria_id, ?string $tipo): array {
    [$sql, $par] = web_filtro($cidade, $categoria_id, $tipo, null);
    return array_column(linhas("SELECT p.bairro FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE $sql AND p.bairro IS NOT NULL AND p.bairro <> '' GROUP BY p.bairro ORDER BY p.bairro LIMIT 200", $par), 'bairro');
}
/** Ficha visível pelo número, com a área (slug) e só de cidade ativa. */
function web_ficha(int $id): ?array {
    if ($id <= 0) return null;
    $p = linha('SELECT p.*, ' . SQL_DESTAQUE . ' AS destaque_pago, c.id AS categoria_id, c.nome AS categoria, c.slug AS categoria_slug, c.icone, c.cor
        FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE p.id = ? AND ' . visivel(), [$id]);
    return $p && isset(CIDADES[$p['cidade']]) ? $p : null;
}
/** "Outros dentistas em São João": do mesmo tipo (ou da mesma área, se o tipo tiver poucos). Devolve [lista, do mesmo tipo?]. */
function web_parecidos(array $p, int $limite = 6): array {
    $campos = 'SELECT ' . CAMPOS_RESUMO . ' FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE ' . visivel($p['cidade']) . ' AND p.id <> ?';
    $ordem = ' ORDER BY ' . SQL_DESTAQUE . " DESC, RAND(?) LIMIT $limite";
    $r = $p['tipo'] ? linhas("$campos AND p.tipo = ?$ordem", [$p['id'], $p['tipo'], semente_dia() + (int)$p['id']]) : [];
    if (count($r) >= 3) return [$r, true];
    return [linhas("$campos AND p.categoria_id = ?$ordem", [$p['id'], $p['categoria_id'], semente_dia() + (int)$p['id']]), false];
}

// ---------- horário ----------
/** Aberto agora? true/false, ou null se o horário de hoje não foi informado (feriado o site não sabe: usa o dia da semana). */
function aberto_agora(array $h, ?int $agora = null): ?bool {
    $agora ??= time();
    $campo = fn(int $dia) => $dia === 6 ? 'sab' : ($dia === 7 ? 'dom' : 'semana');
    $dia = (int)date('N', $agora);
    $min = (int)date('G', $agora) * 60 + (int)date('i', $agora);
    $m = fn(string $hora) => (int)substr($hora, 0, 2) * 60 + (int)substr($hora, 3, 2);
    // ontem aberto até depois da meia-noite?
    $ontem = $h[$campo($dia === 1 ? 7 : $dia - 1)] ?? null;
    if ($ontem && $ontem['sit'] === 'aberto' && $m($ontem['fecha']) < $m($ontem['abre']) && $min < $m($ontem['fecha'])) return true;
    $hoje = $h[$campo($dia)] ?? null;
    if (!$hoje) return null;
    if ($hoje['sit'] === '24h') return true;
    if ($hoje['sit'] === 'fechado') return false;
    [$a, $f] = [$m($hoje['abre']), $m($hoje['fecha'])];
    return $f > $a ? ($min >= $a && $min < $f) : $min >= $a;
}
function campo_de_hoje(?int $agora = null): string {
    $dia = (int)date('N', $agora ?? time());
    return $dia === 6 ? 'sab' : ($dia === 7 ? 'dom' : 'semana');
}

// ---------- pedaços de HTML ----------
function web_num(int $n): string { return number_format($n, 0, ',', '.'); }
/** "1 dentista" / "97 comércios": plural simples para os textos. */
function web_qtd(int $n, string $um, string $varios): string { return web_num($n) . ' ' . ($n === 1 ? $um : $varios); }

/** Trilha "Início › Cidade › Área" (HTML + dados estruturados BreadcrumbList). $itens: [[nome, url relativa ou null]]. */
function web_trilha(array $itens): array {
    $html = '<nav class="trilha" aria-label="Você está em"><a href="' . h(url()) . '">Início</a>';
    $ld = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Acha Fácil', 'item' => url_publica()]];
    foreach ($itens as $i => [$nome, $link]) {
        $html .= ' <span aria-hidden="true">›</span> ' . ($link ? '<a href="' . h($link) . '">' . h($nome) . '</a>' : '<span aria-current="page">' . h($nome) . '</span>');
        $item = ['@type' => 'ListItem', 'position' => $i + 2, 'name' => $nome];
        if ($link) $item['item'] = web_absoluta($link);
        $ld[] = $item;
    }
    return [$html . '</nav>', ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $ld]];
}
/** Endereço relativo do site (url()) -> completo (endereco_publico). */
function web_absoluta(string $relativa): string {
    $base = config()['url'];
    if ($base !== '' && str_starts_with($relativa, $base)) $relativa = substr($relativa, strlen($base));
    return url_publica($relativa);
}
function web_json_ld(array ...$dados): string {
    $html = '';
    foreach ($dados as $d) $html .= '<script type="application/ld+json">' . json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . "</script>\n";
    return $html;
}

/** Logo do comércio, ou o emoji da área no círculo com a cor dela. */
function web_logo(array $p, string $classe = 'cc-logo'): string {
    if ($p['foto']) return '<img class="' . $classe . '" src="' . h(foto_url($p['foto'])) . '" alt=""' . ($classe === 'cc-logo' ? ' loading="lazy"' : '') . ' width="64" height="64">';
    return '<i class="' . $classe . '" style="background:' . h($p['cor']) . '" aria-hidden="true">' . emoji_area($p['icone']) . '</i>';
}
/** Estrelas das avaliações do app ("★ 4,5 (3)"), vazio se não tem. */
function web_nota(array $p): string {
    $n = (int)$p['total_avaliacoes'];
    return $n ? '<span class="nota">★ ' . number_format((float)$p['nota_media'], 1, ',', '') . ' <small>(' . $n . ')</small></span>' : '';
}
/** Cartão de um comércio nas listas. */
function web_cartao(array $p): string {
    $sub = array_filter([$p['tipo'] ?: $p['categoria'], $p['bairro']]);
    return '<li class="cc' . ($p['destaque_pago'] ? ' cc-destaque' : '') . '"><a href="' . h(url_ficha($p)) . '">' . web_logo($p)
        . '<span class="cc-texto"><b>' . h($p['nome']) . '</b><small>' . h(implode(' · ', $sub)) . '</small>'
        . ($p['destaque_pago'] ? '<span class="selo-destaque">Destaque</span>' : '') . web_nota($p) . '</span></a></li>';
}
function web_cartoes(array $lista): string {
    return '<ul class="cartoes">' . implode('', array_map('web_cartao', $lista)) . '</ul>';
}
/** Paginação: ‹ 1 2 3 › com ?pagina= (mantém o bairro). */
function web_paginacao(string $base, int $pagina, int $paginas, ?string $bairro): string {
    if ($paginas <= 1) return '';
    $link = function (int $n) use ($base, $bairro) {
        $q = array_filter(['bairro' => $bairro, 'pagina' => $n > 1 ? $n : null], fn($v) => $v !== null);
        return h($base . ($q ? '?' . http_build_query($q) : ''));
    };
    $html = '<nav class="paginas" aria-label="Páginas">';
    if ($pagina > 1) $html .= '<a href="' . $link($pagina - 1) . '" rel="prev">‹ Anterior</a>';
    $de = max(1, $pagina - 3); $ate = min($paginas, $pagina + 3);
    if ($de > 1) $html .= '<a href="' . $link(1) . '">1</a>' . ($de > 2 ? '<span>…</span>' : '');
    for ($n = $de; $n <= $ate; $n++) $html .= $n === $pagina ? '<span aria-current="page">' . $n . '</span>' : '<a href="' . $link($n) . '">' . $n . '</a>';
    if ($ate < $paginas) $html .= ($ate < $paginas - 1 ? '<span>…</span>' : '') . '<a href="' . $link($paginas) . '">' . $paginas . '</a>';
    if ($pagina < $paginas) $html .= '<a href="' . $link($pagina + 1) . '" rel="next">Próxima ›</a>';
    return $html . '</nav>';
}
/** Caixa de busca da cidade. */
function web_form_busca(string $cidade, string $termo = ''): string {
    return '<form class="procurar busca-cidade" action="' . h(url(cidade_web($cidade) . '/buscar')) . '" method="get" role="search">'
        . '<input name="q" value="' . h($termo) . '" placeholder="O que você procura? Ex.: pizza, dentista, borracharia" aria-label="O que você procura" required minlength="2">'
        . '<button class="botao amarelo">Procurar</button></form>';
}
/** Chamada para baixar o app, no fim das páginas. */
function web_baixe_app(string $cidade): string {
    return '<section class="verde baixe"><div class="conteudo"><h2>Leve o Acha Fácil no bolso</h2><p class="intro">O comércio de '
        . h(nome_cidade($cidade)) . ' no seu celular: procure, veja quem está aberto e chame no WhatsApp com um toque.</p><div class="lojas">'
        . botoes_lojas() . '</div></div></section>';
}

// ---------- páginas ----------

/** Página da cidade. */
function web_pagina_cidade(string $cidade): void {
    [$nome, , $uf] = CIDADES[$cidade];
    $areas = web_areas($cidade);
    $total = web_total_cidade($cidade);
    $destaques = linhas('SELECT ' . CAMPOS_RESUMO . ' FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE ' . visivel($cidade) . ' AND ' . SQL_DESTAQUE . ' ORDER BY RAND(?) LIMIT 12', [semente_dia()]);
    $populares = linhas('SELECT * FROM (SELECT ' . CAMPOS_RESUMO . ', p.popularidade FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE ' . visivel($cidade) . ' AND NOT ' . SQL_DESTAQUE . ' ORDER BY p.popularidade + p.total_avaliacoes * 5 DESC LIMIT 60) t
        ORDER BY RAND(?) LIMIT 12', [semente_dia()]);
    [$trilha, $ld] = web_trilha([["$nome - $uf", null]]);
    pagina_inicio("Comércios e serviços em $nome - $uf | Acha Fácil!",
        web_num($total) . " comércios e serviços de $nome ($uf): restaurantes, mercados, oficinas, clínicas, lojas e muito mais. Endereço, horário e WhatsApp.",
        'web', ['canonica' => web_absoluta(url_cidade($cidade)), 'head' => web_json_ld($ld)]);
    ?>
<main>
  <section class="web-topo"><div class="conteudo">
    <?= $trilha ?>
    <h1>Comércios e serviços em <mark><?= h($nome) ?></mark></h1>
    <p class="lead"><?= web_qtd($total, 'lugar', 'lugares') ?> em <?= h("$nome ($uf)") ?>: endereço, horário e WhatsApp de quem atende na cidade.</p>
    <?= web_form_busca($cidade) ?>
    <?= function_exists('web_abas_cidade') ? web_abas_cidade($cidade, 'comercios') : '' ?>
  </div></section>

  <section class="branco"><div class="conteudo">
    <h2>Escolha a área</h2>
    <div class="areas">
      <?php foreach ($areas as $a): ?>
        <a class="area" href="<?= h(url_area($cidade, $a['slug'])) ?>"><i style="background:<?= h($a['cor']) ?>"><?= emoji_area($a['icone']) ?></i><span><b><?= h($a['nome']) ?></b><small><?= web_qtd($a['total'], 'lugar', 'lugares') ?></small></span></a>
      <?php endforeach ?>
    </div>
  </div></section>

  <?php if ($destaques): ?>
  <section><div class="conteudo">
    <h2>Em destaque em <?= h($nome) ?></h2>
    <?= web_cartoes($destaques) ?>
  </div></section>
  <?php endif ?>

  <?php if ($populares): ?>
  <section<?= $destaques ? ' class="branco"' : '' ?>><div class="conteudo">
    <h2>Conhecidos na cidade</h2>
    <p class="intro">Uma seleção, que muda todo dia, dos lugares mais procurados de <?= h($nome) ?>.</p>
    <?= web_cartoes($populares) ?>
  </div></section>
  <?php endif ?>

  <?php if (count(CIDADES) > 1): ?>
  <section><div class="conteudo">
    <h2>Outras cidades</h2>
    <p class="chips"><?php foreach (CIDADES as $s => [$n, , $u]) if ($s !== $cidade): ?><a href="<?= h(url_cidade($s)) ?>"><?= h("$n - $u") ?></a><?php endif ?></p>
  </div></section>
  <?php endif ?>
  <?= web_baixe_app($cidade) ?>
</main>
<?php
    pagina_fim();
}

/** Página da área ou do tipo (lista paginada com filtro de bairro). */
function web_pagina_lista(string $cidade, array $area, ?string $tipo, ?string $bairro, int $pagina, int $total): void {
    $itens = web_lista($cidade, (int)$area['id'], $tipo, $bairro, $pagina);
    [$nome_cidade, , $uf] = CIDADES[$cidade];
    $base_url = $tipo !== null ? url_tipo($cidade, $area['slug'], $tipo) : url_area($cidade, $area['slug']);
    $paginas = max(1, (int)ceil($total / WEB_POR_PAGINA));
    $titulo_lista = $tipo ?? $area['nome'];
    $trilha_itens = [["$nome_cidade - $uf", url_cidade($cidade)], [$area['nome'], $tipo !== null ? url_area($cidade, $area['slug']) : null]];
    if ($tipo !== null) $trilha_itens[] = [$tipo, null];
    [$trilha, $ld] = web_trilha($trilha_itens);
    $tipos = $tipo === null ? web_tipos($cidade, (int)$area['id']) : [];
    $bairros = web_bairros($cidade, (int)$area['id'], $tipo);
    $qtd = web_qtd($total, 'lugar', 'lugares');
    $titulo = ($tipo !== null ? "$tipo em $nome_cidade - $uf" : "{$area['nome']} em $nome_cidade - $uf") . ($pagina > 1 ? " (página $pagina)" : '') . ' | Acha Fácil!';
    $descricao = "$qtd de " . mb_strtolower($titulo_lista) . " em $nome_cidade ($uf): endereço, horário, WhatsApp e avaliações. Veja quem está aberto e chame direto.";
    // não vai para o Google: filtro de bairro e tipo com poucos comércios
    $noindex = $bairro !== null || ($tipo !== null && $total < WEB_TIPO_MINIMO);
    $canonica = web_absoluta($base_url) . ($pagina > 1 ? '?pagina=' . $pagina : '');
    $lista_ld = ['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => "$titulo_lista em $nome_cidade",
        'itemListElement' => array_map(fn($p, $i) => ['@type' => 'ListItem', 'position' => ($pagina - 1) * WEB_POR_PAGINA + $i + 1,
            'url' => web_absoluta(url_ficha($p)), 'name' => $p['nome']], $itens, array_keys($itens))];
    pagina_inicio($titulo, $descricao, 'web', ['canonica' => $canonica, 'noindex' => $noindex, 'head' => web_json_ld($ld, $lista_ld)]);
    ?>
<main>
  <section class="web-topo"><div class="conteudo">
    <?= $trilha ?>
    <h1><?= h($titulo_lista) ?> em <mark><?= h($nome_cidade) ?></mark></h1>
    <p class="lead"><?= $qtd ?><?= $bairro !== null ? ' no bairro ' . h($bairro) : '' ?>. Quem paga o Destaque aparece primeiro; os outros mudam de lugar todo dia, para ser justo com todos.</p>
    <?= web_form_busca($cidade) ?>
  </div></section>

  <section class="branco lista-web"><div class="conteudo">
    <?php if ($tipos): ?>
      <h2 class="h-pequeno">Tipos de <?= h(mb_strtolower($area['nome'])) ?></h2>
      <p class="chips"><?php foreach ($tipos as $t): ?><a href="<?= h(url_tipo($cidade, $area['slug'], $t['nome'])) ?>"><?= h($t['nome']) ?> <small><?= (int)$t['total'] ?></small></a><?php endforeach ?></p>
    <?php endif ?>
    <?php if (count($bairros) > 1): ?>
      <form class="filtro-bairro" method="get" action="<?= h($base_url) ?>">
        <label for="bairro">Bairro</label>
        <select id="bairro" name="bairro" onchange="this.form.submit()">
          <option value="">Todos os bairros</option>
          <?php foreach ($bairros as $b): ?><option<?= $b === $bairro ? ' selected' : '' ?>><?= h($b) ?></option><?php endforeach ?>
        </select>
        <noscript><button class="botao">Filtrar</button></noscript>
      </form>
    <?php endif ?>
    <?php if ($itens): ?>
      <?= web_cartoes($itens) ?>
    <?php else: ?>
      <p class="aviso erro">Nenhum lugar encontrado<?= $bairro !== null ? ' neste bairro' : '' ?>.</p>
    <?php endif ?>
    <?= web_paginacao($base_url, $pagina, $paginas, $bairro) ?>
    <p class="discreto">Tem um negócio de <?= h(mb_strtolower($titulo_lista)) ?> em <?= h($nome_cidade) ?> e não está aqui? <a href="<?= h(url('incluir.php')) ?>">Inclua de graça</a>.</p>
  </div></section>
  <?= web_baixe_app($cidade) ?>
</main>
<?php
    pagina_fim();
}

/** Ficha do comércio. */
function web_pagina_ficha(array $p): void {
    $cidade = $p['cidade'];
    [$nome_cidade, , $uf] = CIDADES[$cidade];
    $c = prof_completo($p, null);
    $h = horarios_ler($p['horarios'] ?? null);
    $aberto = $h ? aberto_agora($h) : null;
    $tipo = $p['tipo'] ?: null;
    $trilha_itens = [["$nome_cidade - $uf", url_cidade($cidade)], [$p['categoria'], url_area($cidade, $p['categoria_slug'])]];
    if ($tipo) $trilha_itens[] = [$tipo, url_tipo($cidade, $p['categoria_slug'], $tipo)];
    $trilha_itens[] = [$p['nome'], null];
    [$trilha, $ld_trilha] = web_trilha($trilha_itens);
    $url = web_absoluta(url_ficha($p));
    $fotos = $c['fotos'];
    $imagem = $c['foto'] ?? ($fotos[0] ?? null);

    // dados estruturados LocalBusiness
    $ld = ['@context' => 'https://schema.org', '@type' => 'LocalBusiness', '@id' => $url, 'name' => $p['nome'], 'url' => $url];
    if ($imagem) $ld['image'] = $imagem;
    if ($p['descricao']) $ld['description'] = mb_substr($p['descricao'], 0, 300);
    if ($tel = $p['telefone'] ?: $p['whatsapp']) $ld['telephone'] = '+55' . so_digitos($tel);
    $ld['address'] = array_filter(['@type' => 'PostalAddress', 'streetAddress' => $p['endereco'], 'addressLocality' => $nome_cidade,
        'addressRegion' => $uf, 'addressCountry' => 'BR']);
    if ($p['lat'] !== null && $p['lng'] !== null) $ld['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float)$p['lat'], 'longitude' => (float)$p['lng']];
    if ((int)$p['total_avaliacoes'] > 0) $ld['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => round((float)$p['nota_media'], 1),
        'reviewCount' => (int)$p['total_avaliacoes'], 'bestRating' => 5, 'worstRating' => 1];
    $dias = ['semana' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], 'sab' => ['Saturday'], 'dom' => ['Sunday']];
    foreach ($dias as $campo => $nomes) {
        $v = $h[$campo] ?? null;
        if (!$v || $v['sit'] === 'fechado') continue;
        $ld['openingHoursSpecification'][] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $nomes,
            'opens' => $v['sit'] === '24h' ? '00:00' : $v['abre'], 'closes' => $v['sit'] === '24h' ? '23:59' : $v['fecha']];
    }
    $sameAs = array_values(array_filter([$c['instagram'], $p['site'] ?: null]));
    if ($sameAs) $ld['sameAs'] = $sameAs;

    $sub = array_filter([$tipo ?? $p['categoria'], $p['bairro'], "$nome_cidade - $uf"]);
    $descricao = $p['nome'] . ' em ' . $nome_cidade . ' (' . $uf . ')' . ($tipo ? ", $tipo" : '') . '. '
        . ($p['endereco'] ? 'Endereço: ' . $p['endereco'] . '. ' : '') . 'Veja horário, telefone e WhatsApp no Acha Fácil.';
    pagina_inicio($p['nome'] . ' - ' . ($tipo ? "$tipo em " : '') . "$nome_cidade | Acha Fácil!", mb_substr($descricao, 0, 300), 'web',
        ['canonica' => $url, 'imagem' => $imagem, 'head' => web_json_ld($ld, $ld_trilha)]);
    [$parecidos, $mesmo_tipo] = web_parecidos($p);
    ?>
<main class="ficha">
  <div class="conteudo">
    <?= $trilha ?>
    <article class="ficha-caixa">
      <header class="ficha-topo">
        <?= web_logo($p, 'ficha-logo') ?>
        <div>
          <h1><?= h($p['nome']) ?></h1>
          <p class="ficha-sub"><?= h(implode(' · ', $sub)) ?></p>
          <p class="ficha-selos">
            <?php if ($p['destaque_pago']): ?><span class="selo-destaque">Destaque</span><?php endif ?>
            <?php if ($c['verificado']): ?><span class="selo-verificado">✔ Verificado pelo Acha Fácil</span><?php endif ?>
            <?php if ($aberto !== null): ?><span class="selo-<?= $aberto ? 'aberto' : 'fechado' ?>"><?= $aberto ? 'Aberto agora' : 'Fechado agora' ?></span><?php endif ?>
            <?= web_nota($p) ?>
          </p>
        </div>
      </header>

      <div class="ficha-botoes">
        <?php if ($c['whatsapp']): ?><a class="botao whats" href="<?= h($c['whatsapp']) ?>" data-contato="whatsapp" rel="nofollow noopener" target="_blank">WhatsApp</a><?php endif ?>
        <?php if ($p['telefone']): ?><a class="botao" href="tel:+55<?= h(so_digitos($p['telefone'])) ?>" data-contato="ligar" rel="nofollow">Ligar <?= h($c['telefoneFormatado']) ?></a><?php endif ?>
        <?php if ($c['mapa']): ?><a class="botao contorno" href="<?= h($c['mapa']) ?>" data-contato="mapa" rel="nofollow noopener" target="_blank">Como chegar</a><?php endif ?>
        <?php if ($c['instagram']): ?><a class="botao contorno" href="<?= h($c['instagram']) ?>" data-contato="instagram" rel="nofollow noopener" target="_blank">Instagram</a><?php endif ?>
        <?php if ($p['site']): ?><a class="botao contorno" href="<?= h(preg_match('#^https?://#i', $p['site']) ? $p['site'] : 'https://' . $p['site']) ?>" data-contato="site" rel="nofollow noopener" target="_blank">Site</a><?php endif ?>
      </div>

      <?php if ($fotos): ?>
        <div class="ficha-fotos">
          <?php foreach ($fotos as $i => $f): ?><a href="<?= h($f) ?>" target="_blank" rel="noopener"><img src="<?= h($f) ?>" alt="<?= h($p['nome']) ?>, foto <?= $i + 1 ?>" loading="<?= $i ? 'lazy' : 'eager' ?>" height="200"></a><?php endforeach ?>
        </div>
      <?php endif ?>

      <div class="ficha-colunas">
        <div>
          <?php if ($p['descricao']): ?><h2>Sobre</h2><p><?= nl2br(h($p['descricao'])) ?></p><?php endif ?>
          <?php if ($c['servicos']): ?><h2>O que faz ou vende</h2><p class="chips chips-texto"><?php foreach ($c['servicos'] as $s): ?><span><?= h($s) ?></span><?php endforeach ?></p><?php endif ?>
          <h2>Endereço</h2>
          <p><?= $p['endereco'] ? h($p['endereco']) : 'Endereço não informado.' ?><?= $p['bairro'] && !str_contains((string)$p['endereco'], $p['bairro']) ? '<br>Bairro ' . h($p['bairro']) : '' ?><br><?= h("$nome_cidade - $uf") ?></p>
          <?php if ($p['atende_em_casa']): ?><p class="selo-verificado">🏠 Atende em casa</p><?php endif ?>
        </div>
        <div>
          <h2>Horário</h2>
          <?php if ($h): ?>
            <ul class="horarios-web">
              <?php foreach (HORARIO_CAMPOS as $campo => $nome): if (!isset($h[$campo])) continue; ?>
                <li<?= $campo === campo_de_hoje() ? ' class="hoje"' : '' ?>><span><?= h($nome) ?></span><span><?= h(horario_campo_texto($h[$campo])) ?></span></li>
              <?php endforeach ?>
            </ul>
          <?php elseif ($p['horario']): ?>
            <p><?= h($p['horario']) ?></p>
          <?php else: ?>
            <p class="discreto">Horário não informado. Chame antes de ir.</p>
          <?php endif ?>
        </div>
      </div>

      <?php if ($c['comentarios']): ?>
        <h2>Avaliações de quem usa o Acha Fácil</h2>
        <ul class="avaliacoes">
          <?php foreach ($c['comentarios'] as $a): ?>
            <li><p><b><?= h($a['autor']) ?></b> <span class="estrelas" aria-label="<?= $a['estrelas'] ?> de 5 estrelas"><?= str_repeat('★', $a['estrelas']) . str_repeat('☆', 5 - $a['estrelas']) ?></span> <small><?= h($a['data']) ?></small></p>
              <?php if ($a['comentario']): ?><p><?= nl2br(h($a['comentario'])) ?></p><?php endif ?>
              <?php if ($a['resposta']): ?><p class="resposta"><b>Resposta do comércio:</b> <?= nl2br(h($a['resposta'])) ?></p><?php endif ?></li>
          <?php endforeach ?>
        </ul>
        <p class="discreto">Para avaliar, baixe o app Acha Fácil.</p>
      <?php endif ?>
    </article>

    <aside class="caixa ficha-dono">
      <?php if ($p['importado'] && !$p['basico_pago_em'] && !$p['destaque_pago']): // dono ainda não pagou nenhum plano ?>
        <h2 class="h-pequeno">Este comércio é seu?</h2>
        <p>Corrija os dados, coloque fotos, o horário e o que você vende. <a class="botao amarelo" href="<?= h($c['linkAssumir']) ?>">Ver os planos</a></p>
        <p class="discreto">Os dados vieram de informações públicas do comércio na internet. Não quer aparecer? <a href="<?= h($c['linkRemover']) ?>" rel="nofollow">Tirar meu comércio do Acha Fácil</a>.</p>
      <?php else: ?>
        <p class="discreto">Informação errada? Fale com a gente: <?= contatos_html() ?>.</p>
      <?php endif ?>
    </aside>

    <?php if ($parecidos): ?>
      <section class="parecidos">
        <h2>Outras opções de <?= h(mb_strtolower($mesmo_tipo ? $tipo : $p['categoria'])) ?> em <?= h($nome_cidade) ?></h2>
        <?= web_cartoes($parecidos) ?>
        <p><a href="<?= h($mesmo_tipo ? url_tipo($cidade, $p['categoria_slug'], $tipo) : url_area($cidade, $p['categoria_slug'])) ?>">Ver todos</a></p>
      </section>
    <?php endif ?>
  </div>
  <?= web_baixe_app($cidade) ?>
</main>
<script>
// toques em WhatsApp, ligar, mapa... contam nos números do comércio (origem "site")
document.querySelectorAll('[data-contato]').forEach(function (a) {
  a.addEventListener('click', function () {
    try { fetch(<?= json_encode(url('api/contato.php')) ?>, { method: 'POST', keepalive: true, headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: <?= (int)$p['id'] ?>, tipo: a.dataset.contato, origem: 'web' }) }); } catch (e) {}
  });
});
</script>
<?php
    pagina_fim();
}

/** Busca dentro da cidade (não vai para o Google): mesma regra da busca do app (inc/guia.php buscar()). */
function web_pagina_busca(string $cidade, string $termo): void {
    [$nome_cidade, , $uf] = CIDADES[$cidade];
    $achados = [];
    if (mb_strlen($termo) >= 2) {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $termo) . '%';
        $achados = linhas('SELECT ' . CAMPOS_RESUMO . ' FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
            WHERE ' . visivel($cidade) . ' AND (p.nome LIKE ? OR p.tipo LIKE ? OR p.servicos LIKE ? OR p.descricao LIKE ? OR p.bairro LIKE ? OR c.nome LIKE ?)
            ORDER BY (c.nome LIKE ? OR p.tipo LIKE ? OR p.nome LIKE ?) DESC, ' . SQL_DESTAQUE . ' DESC, p.importado ASC, RAND(?) LIMIT 60',
            [...array_fill(0, 9, $like), semente_dia()]);
    }
    [$trilha] = web_trilha([["$nome_cidade - $uf", url_cidade($cidade)], ['Busca', null]]);
    pagina_inicio("Procurar em $nome_cidade | Acha Fácil!", "Procure comércios e serviços em $nome_cidade ($uf).", 'web',
        ['canonica' => web_absoluta(url_cidade($cidade)), 'noindex' => true]);
    ?>
<main>
  <section class="web-topo"><div class="conteudo">
    <?= $trilha ?>
    <h1>Procurar em <mark><?= h($nome_cidade) ?></mark></h1>
    <?= web_form_busca($cidade, $termo) ?>
  </div></section>
  <section class="branco"><div class="conteudo">
    <?php if ($termo !== '' && mb_strlen($termo) < 2): ?>
      <p class="aviso erro">Escreva pelo menos 2 letras.</p>
    <?php elseif ($termo !== '' && !$achados): ?>
      <p class="aviso erro">Nada encontrado com "<?= h($termo) ?>" em <?= h($nome_cidade) ?>. Tente outra palavra ou <a href="<?= h(url_cidade($cidade)) ?>">veja as áreas</a>.</p>
    <?php elseif ($achados): ?>
      <p><?= web_qtd(count($achados), 'resultado', 'resultados') ?> para "<?= h($termo) ?>"<?= count($achados) >= 60 ? ' (os 60 primeiros)' : '' ?>.</p>
      <?= web_cartoes($achados) ?>
    <?php endif ?>
  </div></section>
  <?= web_baixe_app($cidade) ?>
</main>
<?php
    pagina_fim();
}

/** Página não encontrada (404 de verdade, sem ir para o Google). */
function web_pagina_404(): void {
    http_response_code(404);
    pagina_inicio('Página não encontrada | Acha Fácil!', 'Esta página não existe ou o comércio saiu do Acha Fácil.', 'web', ['noindex' => true, 'canonica' => url_publica()]);
    ?>
<main class="documento">
  <h1>Página não encontrada</h1>
  <p>Esta página não existe, ou o comércio saiu do Acha Fácil.</p>
  <p class="chips"><?php foreach (CIDADES as $s => [$n, , $u]): ?><a href="<?= h(url_cidade($s)) ?>"><?= h("$n - $u") ?></a><?php endforeach ?></p>
  <p><a class="botao" href="<?= h(url()) ?>">Ir para o início</a></p>
</main>
<?php
    pagina_fim();
}

// ---------- sitemap ----------
function web_sitemap_indice(): string {
    $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    $x .= '<sitemap><loc>' . h(url_publica('sitemap-paginas.xml')) . "</loc></sitemap>\n";
    foreach (CIDADES as $slug => $_) $x .= '<sitemap><loc>' . h(url_publica('sitemap-' . cidade_web($slug) . '.xml')) . "</loc></sitemap>\n";
    return $x . "</sitemapindex>\n";
}
function web_sitemap_urls(array $urls): string {
    $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as [$loc, $mod]) $x .= '<url><loc>' . h($loc) . '</loc>' . ($mod ? '<lastmod>' . h($mod) . '</lastmod>' : '') . "</url>\n";
    return $x . "</urlset>\n";
}
/** Páginas fixas do site (inicial, planos, incluir, termos, privacidade). */
function web_sitemap_paginas(): string {
    return web_sitemap_urls(array_map(fn($c) => [url_publica($c), null], ['', 'planos.php', 'incluir.php', 'incluir-evento.php', 'termos.php', 'privacidade.php']));
}
/** Cidade, áreas, tipos com 3+ e todas as fichas visíveis da cidade. */
function web_sitemap_cidade(string $cidade): string {
    $urls = [[web_absoluta(url_cidade($cidade)), null]];
    foreach (web_areas($cidade) as $a) {
        $urls[] = [web_absoluta(url_area($cidade, $a['slug'])), null];
        foreach (web_tipos($cidade, (int)$a['id']) as $t) $urls[] = [web_absoluta(url_tipo($cidade, $a['slug'], $t['nome'])), null];
    }
    foreach (q('SELECT p.id, p.nome, p.cidade, DATE(p.atualizado_em) AS mod_em FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE ' . visivel($cidade) . ' ORDER BY p.id') as $p) $urls[] = [web_absoluta(url_ficha($p)), $p['mod_em']];
    if (function_exists('eventos_prontos') && eventos_prontos()) {
        $urls[] = [web_absoluta(url_eventos($cidade)), null];
        foreach (eventos_proximos($cidade) as $e) $urls[] = [web_absoluta(url_evento($e)), substr((string)$e['atualizado_em'], 0, 10)];
    }
    return web_sitemap_urls($urls);
}
