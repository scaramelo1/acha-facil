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
//   /sitemap.xml, /sitemap-<cidade>-<tipo>.xml, /sitemap-<tipo>.xml e /sitemap-<cidade>.xml (ver "sitemap" abaixo)
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
/** Uma página da lista, na mesma ordem justa do app (Destaque, ficha paga, com avaliação, sorteio do dia). */
function web_contar(string $cidade, int $categoria_id, ?string $tipo, ?string $bairro): int {
    [$sql, $par] = web_filtro($cidade, $categoria_id, $tipo, $bairro);
    return (int)valor("SELECT COUNT(*) FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE $sql", $par);
}
function web_lista(string $cidade, int $categoria_id, ?string $tipo, ?string $bairro, int $pagina): array {
    [$sql, $par] = web_filtro($cidade, $categoria_id, $tipo, $bairro);
    return linhas('SELECT ' . CAMPOS_RESUMO . ", p.endereco FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE $sql ORDER BY " . SQL_DESTAQUE . ' DESC, ' . SQL_FICHA_PAGA . ' DESC, p.total_avaliacoes > 0 DESC, RAND(?)
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
    $ordem = ' ORDER BY ' . SQL_DESTAQUE . ' DESC, ' . SQL_FICHA_PAGA . " DESC, RAND(?) LIMIT $limite";
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
        . ($p['destaque_pago'] ? '<span class="selo-destaque">Destaque</span>'
            : (ficha_paga($p) ? '<span class="selo-completa">✓ Ficha completa</span>'
            : (!empty($p['tem_dono']) ? '<span class="selo-completa">✓ Confirmado pelo dono</span>' : '')))   // passo 78
        . web_nota($p) . '</span></a></li>';
}
/**
 * Espaço trancado na ficha incompleta (passo 59): mostra o que falta e o botão "Quero saber...", que conta o pedido
 * (api/quero.php). O dono vê esses pedidos como motivo para completar a ficha.
 */
function web_trancado(string $tipo, string $titulo, string $texto, string $botao): string {
    return '<div class="trancado" data-trancado="' . h($tipo) . '"><p class="trancado-titulo"><span aria-hidden="true">🔒</span> ' . h($titulo) . '</p>'
        . '<p class="trancado-texto">' . h($texto) . '</p>'
        . '<button type="button" class="botao pequeno contorno" data-quero="' . h($tipo) . '"'
        . (web_avisos_ligados() ? ' data-aviso-texto="' . h(AVISO_INFO_TEXTO[$tipo] ?? 'isso') . '"' : '') . '>🙋 ' . h($botao) . '</button>'
        // "Pedido anotado", "Quer ser avisado?" e "Combinado" são criados pelo JS da ficha (passo 70): o mesmo texto
        // escondido em todas as fichas deixava as páginas parecidas demais para o Google
        . '</div>';
}
function web_avisos_ligados(): bool {
    static $ok = null;
    if ($ok === null) { require_once __DIR__ . '/avisos-info.php'; $ok = avisos_info_prontos(); }
    return $ok;
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
        'web', ['canonica' => web_absoluta(url_cidade($cidade)), 'head' => web_json_ld($ld), 'anuncios' => true]);
    ?>
<main>
  <section class="web-topo"><div class="conteudo">
    <?= $trilha ?>
    <h1>Comércios e serviços em <mark><?= h($nome) ?></mark></h1>
    <p class="lead"><?= web_qtd($total, 'lugar', 'lugares') ?> em <?= h("$nome ($uf)") ?>: endereço, horário e WhatsApp de quem atende na cidade.</p>
    <?= web_form_busca($cidade) ?>
    <?= function_exists('web_abas_cidade') ? web_abas_cidade($cidade, 'comercios') : '' ?>
  </div></section>

  <?php if ($destaques): // 17/11 (pedido do usuário): Destaque ANTES das áreas, para ficar em destaque de verdade ?>
  <section><div class="conteudo">
    <h2>Em destaque em <?= h($nome) ?></h2>
    <?= web_cartoes($destaques) ?>
  </div></section>
  <?php endif ?>

  <section class="branco"><div class="conteudo">
    <h2>Escolha a área</h2>
    <div class="areas">
      <?php foreach ($areas as $a): ?>
        <a class="area" href="<?= h(url_area($cidade, $a['slug'])) ?>"><i style="background:<?= h($a['cor']) ?>"><?= emoji_area($a['icone']) ?></i><span><b><?= h($a['nome']) ?></b><small><?= web_qtd($a['total'], 'lugar', 'lugares') ?></small></span></a>
      <?php endforeach ?>
    </div>
  </div></section>

  <?php if ($populares): ?>
  <section><div class="conteudo">
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
    pagina_inicio($titulo, $descricao, 'web', ['canonica' => $canonica, 'noindex' => $noindex, 'head' => web_json_ld($ld, $lista_ld), 'anuncios' => true]);
    ?>
<main>
  <section class="web-topo"><div class="conteudo">
    <?= $trilha ?>
    <h1><?= h($titulo_lista) ?> em <mark><?= h($nome_cidade) ?></mark></h1>
    <p class="lead"><?= $qtd ?><?= $bairro !== null ? ' no bairro ' . h($bairro) : '' ?>. Destaque e fichas completas aparecem primeiro; os outros mudam de lugar todo dia, para ser justo com todos.</p>
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
    // conta no site (passo 44): salvar e avaliar com a mesma conta do app
    require_once __DIR__ . '/conta-web.php';
    $eu = usuario_web();
    $salvo = $eu && valor('SELECT 1 FROM favoritos WHERE usuario_id = ? AND profissional_id = ?', [$eu['id'], $p['id']]);
    $minha = $eu ? linha('SELECT estrelas, comentario FROM avaliacoes WHERE profissional_id = ? AND usuario_id = ?', [$p['id'], $eu['id']]) : null;
    if ($eu) header('Cache-Control: private, no-store');
    $h = horarios_ler($p['horarios'] ?? null);
    $aberto = $h ? aberto_agora($h) : null;
    $tipo = $p['tipo'] ?: null;
    $trilha_itens = [["$nome_cidade - $uf", url_cidade($cidade)], [$p['categoria'], url_area($cidade, $p['categoria_slug'])]];
    if ($tipo) $trilha_itens[] = [$tipo, url_tipo($cidade, $p['categoria_slug'], $tipo)];
    $nome = nome_curto($p['nome']);   // 16/11: título, destaque e Google usam o nome curto
    $trilha_itens[] = [$nome, null];
    [$trilha, $ld_trilha] = web_trilha($trilha_itens);
    $url = web_absoluta(url_ficha($p));
    $fotos = $c['fotos'];
    $imagem = $c['foto'] ?? ($fotos[0] ?? null);

    // dados estruturados LocalBusiness
    $ld = ['@context' => 'https://schema.org', '@type' => 'LocalBusiness', '@id' => $url, 'name' => $nome, 'url' => $url];
    if ($nome !== $p['nome']) $ld['alternateName'] = $p['nome'];
    if ($imagem) $ld['image'] = $imagem;
    if ($p['descricao']) $ld['description'] = mb_substr($p['descricao'], 0, 300);
    if ($tel = $p['telefone'] ?: $p['whatsapp']) $ld['telephone'] = telefone_discar($tel);
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
    $descricao = $nome . ' em ' . $nome_cidade . ' (' . $uf . ')' . ($tipo ? ", $tipo" : '') . '. '
        . ($p['endereco'] ? 'Endereço: ' . $p['endereco'] . '. ' : '') . 'Veja horário, telefone e WhatsApp no Acha Fácil.';
    pagina_inicio($nome . ' - ' . ($tipo ? "$tipo em " : '') . "$nome_cidade | Acha Fácil!", mb_substr($descricao, 0, 300), 'web',
        ['canonica' => $url, 'imagem' => $imagem, 'head' => web_json_ld($ld, $ld_trilha), 'noindex' => !ficha_no_google($p),
            'anuncios' => $p['importado'] && !$p['destaque_pago']]);   // 17/11 (pedido do usuário): anúncio também no Básico; só o Destaque fica sem
    [$parecidos, $mesmo_tipo] = web_parecidos($p);
    $sem_dono = $p['importado'] && !tem_dono($p);
    $link_dono = $sem_dono ? $c['linkAssumir'] : null;
    $tem_horario = $h || $p['horario'];
    $tem_sobre = $p['descricao'] || $c['servicos'];
    ?>
<main class="ficha">
  <div class="conteudo">
    <?= $trilha ?>
    <article class="ficha-caixa">
      <header class="ficha-topo">
        <?= web_logo($p, 'ficha-logo') ?>
        <div>
          <h1><?= h($nome) ?> <a class="ficha-editar" href="<?= h(url('admin/comercio.php?id=' . (int)$p['id'])) ?>" data-so-admin hidden rel="nofollow" title="Editar este comércio (admin)" aria-label="Editar este comércio"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M3 17.25V21h3.75L17.8 9.94l-3.75-3.75L3 17.25zm17.7-10.2a1 1 0 0 0 0-1.42l-2.33-2.33a1 1 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg></a></h1>
          <?php if ($nome !== $p['nome']): ?><p class="ficha-nome-completo"><?= h($p['nome']) ?></p><?php endif ?>
          <p class="ficha-sub"><?= h(implode(' · ', $sub)) ?></p>
          <p class="ficha-selos">
            <?php if ($p['destaque_pago']): ?><span class="selo-destaque">Destaque</span><?php endif ?>
            <?php if ($c['verificado']): ?><span class="selo-verificado">✔ Verificado pelo Acha Fácil</span>
            <?php elseif (!$p['destaque_pago'] && !$sem_dono && $p['importado']): // passo 78: assumiu (grátis ou Básico) ?><span class="selo-verificado">✓ Dados confirmados pelo dono</span><?php endif ?>
            <?php if ($aberto !== null): ?><span class="selo-<?= $aberto ? 'aberto' : 'fechado' ?>"><?= $aberto ? 'Aberto agora' : 'Fechado agora' ?></span><?php endif ?>
            <?= web_nota($p) ?>
          </p>
        </div>
      </header>

      <div class="ficha-botoes">
        <?php if ($c['whatsapp']): ?><a class="botao whats" href="<?= h($c['whatsapp']) ?>" data-contato="whatsapp" rel="nofollow noopener" target="_blank">WhatsApp</a><?php endif ?>
        <?php if ($p['telefone']): ?><a class="botao" href="tel:<?= h(telefone_discar($p['telefone'])) ?>" data-contato="ligar" rel="nofollow">Ligar <?= h($c['telefoneFormatado']) ?><?= telefone_central($p['telefone']) ? ' (central)' : '' ?></a><?php endif ?>
        <?php if ($c['mapa']): ?><a class="botao contorno" href="<?= h($c['mapa']) ?>" data-contato="mapa" rel="nofollow noopener" target="_blank">Como chegar</a><?php endif ?>
        <?php if ($c['instagram']): ?><a class="botao contorno" href="<?= h($c['instagram']) ?>" data-contato="instagram" rel="nofollow noopener" target="_blank">Instagram</a><?php endif ?>
        <?php if ($p['site']): ?><a class="botao contorno" href="<?= h(preg_match('#^https?://#i', $p['site']) ? $p['site'] : 'https://' . $p['site']) ?>" data-contato="site" rel="nofollow noopener" target="_blank">Site</a><?php endif ?>
        <?php if ($eu): ?><button type="button" class="botao contorno botao-salvar<?= $salvo ? ' salvo' : '' ?>" id="botao-salvar" aria-pressed="<?= $salvo ? 'true' : 'false' ?>"><?= $salvo ? '♥ Salvo' : '♡ Salvar' ?></button>
        <?php else: ?><a class="botao contorno botao-salvar" href="<?= h(link_entrar(url_ficha($p))) ?>" rel="nofollow" title="Entre com o Google para salvar">♡ Salvar</a><?php endif ?>
      </div>

      <?php if (!$c['whatsapp'] || !$fotos): // passo 59: o que falta, com o botão de pedir ?>
        <div class="trancados">
          <?php if (!$c['whatsapp']): ?><?= web_trancado('whatsapp', 'WhatsApp', 'O comércio ainda não informou o WhatsApp.', 'Pedir o WhatsApp ao comércio') ?><?php endif ?>
          <?php if (!$fotos): ?><?= web_trancado('fotos', 'Fotos', 'Ainda sem fotos do lugar, dos produtos ou do cardápio.', 'Pedir fotos ao comércio') ?><?php endif ?>
        </div>
      <?php endif ?>

      <?php if ($fotos): ?>
        <div class="ficha-fotos">
          <?php foreach ($fotos as $i => $f): ?><a href="<?= h($f) ?>" target="_blank" rel="noopener"><img src="<?= h($f) ?>" alt="<?= h($p['nome']) ?>, foto <?= $i + 1 ?>" loading="<?= $i ? 'lazy' : 'eager' ?>" height="200"></a><?php endforeach ?>
        </div>
      <?php endif ?>

      <div class="ficha-colunas">
        <div>
          <?php if ($p['descricao']): ?><h2>Sobre</h2><p><?= nl2br(h($p['descricao'])) ?></p><?php endif ?>
          <?php if ($c['servicos']): ?><h2>O que faz ou vende</h2><p class="chips chips-texto"><?php foreach ($c['servicos'] as $s): ?><span><?= h($s) ?></span><?php endforeach ?></p><?php endif ?>
          <?php if (!$tem_sobre): ?><h2>O que faz ou vende</h2><?= web_trancado('sobre', 'Produtos e serviços', 'O comércio ainda não contou o que vende ou faz.', 'Pedir detalhes ao comércio') ?><?php endif ?>
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
            <?= web_trancado('horario', 'Horário de funcionamento', 'O comércio ainda não informou o horário.', 'Pedir o horário ao comércio') ?>
          <?php endif ?>
        </div>
      </div>

      <?php if ($link_dono && (!$c['whatsapp'] || !$fotos || !$tem_sobre || !$tem_horario)): // convite uma vez só, abaixo dos quadros trancados ?>
        <p class="trancado-dono">🔒 Faltam dados nesta ficha. É o dono? <a href="<?= h(url('perfil.php?acao=assumir&id=' . (int)$p['id'])) ?>" rel="nofollow">Cadastre-se grátis</a></p>
      <?php endif ?>

      <h2>Avaliações de quem usa o Acha Fácil</h2>
      <?php if (!$c['comentarios']): ?><p class="discreto">Ainda sem avaliações. Seja o primeiro a avaliar!</p><?php endif ?>
      <?php if ($c['comentarios']): ?>
        <ul class="avaliacoes">
          <?php foreach ($c['comentarios'] as $a): ?>
            <li><p><b><?= h($a['autor']) ?></b> <span class="estrelas" aria-label="<?= $a['estrelas'] ?> de 5 estrelas"><?= str_repeat('★', $a['estrelas']) . str_repeat('☆', 5 - $a['estrelas']) ?></span> <small><?= h($a['data']) ?></small></p>
              <?php if ($a['comentario']): ?><p><?= nl2br(h($a['comentario'])) ?></p><?php endif ?>
              <?php if ($a['resposta']): ?><p class="resposta"><b>Resposta do comércio:</b> <?= nl2br(h($a['resposta'])) ?></p><?php endif ?></li>
          <?php endforeach ?>
        </ul>
      <?php endif ?>
      <section class="avaliar" id="avaliar">
        <h3><?= $minha ? 'Sua avaliação' : 'Avaliar este comércio' ?></h3>
        <?php if (!$eu): ?>
          <p>Entre com a sua conta Google para dar estrelas e comentar. É a mesma conta do app.</p>
          <?= botao_google(url_ficha($p) . '#avaliar') ?>
        <?php else: ?>
          <form id="form-avaliar">
            <div class="estrelas-escolher" role="radiogroup" aria-label="Estrelas">
              <?php for ($i = 5; $i >= 1; $i--): // de trás para frente: o CSS (row-reverse + ~) pinta a escolhida e as de antes ?><label><input type="radio" name="estrelas" value="<?= $i ?>"<?= (int)($minha['estrelas'] ?? 0) === $i ? ' checked' : '' ?> required><span aria-hidden="true">★</span><b class="so-leitor"><?= $i ?> estrela<?= $i > 1 ? 's' : '' ?></b></label><?php endfor ?>
            </div>
            <label for="comentario">Comentário (opcional)</label>
            <textarea id="comentario" name="comentario" maxlength="1000" rows="3" placeholder="Conte como foi o atendimento"><?= h((string)($minha['comentario'] ?? '')) ?></textarea>
            <p class="discreto">Aparece com o seu primeiro nome (<?= h(primeiro_nome($eu['nome'])) ?>).</p>
            <p><button class="botao"><?= $minha ? 'Salvar mudança' : 'Enviar avaliação' ?></button>
              <?php if ($minha): ?><button type="button" class="botao contorno" id="apagar-avaliacao">Apagar</button><?php endif ?></p>
            <p class="aviso erro" id="erro-avaliar" hidden></p>
          </form>
        <?php endif ?>
      </section>
    </article>

    <aside class="caixa ficha-dono">
      <?php if ($sem_dono): // sem dono: não pagou plano nem teve o painel liberado ?>
        <h2 class="h-pequeno">Este comércio é seu?</h2>
        <p>Cadastre-se <strong>grátis</strong> com a sua conta Google e corrija o telefone, o WhatsApp, o endereço e o horário.
          <a class="botao amarelo" href="<?= h(url('perfil.php?acao=assumir&id=' . (int)$p['id'])) ?>" rel="nofollow">Cadastrar grátis</a>
          <a class="botao contorno" href="<?= h($c['linkAssumir']) ?>" rel="nofollow">Ver os planos</a></p>
        <p class="discreto">Os dados vieram de informações públicas do comércio na internet. Não quer aparecer? <a href="<?= h($c['linkRemover']) ?>" rel="nofollow">Tirar meu comércio do Acha Fácil</a>.</p>
        <p class="discreto">Informação errada? <a href="<?= h(url('suporte.php?id=' . (int)$p['id'])) ?>" rel="nofollow">Envie uma mensagem para a equipe</a> (precisa entrar com a conta Google).</p>
      <?php else: ?>
        <h2 class="h-pequeno">Informação errada ou algum problema?</h2>
        <p>Mande uma mensagem para a equipe do Acha Fácil. É preciso <strong>entrar com a conta Google</strong>: assim a
          gente responde e você recebe o aviso no celular.</p>
        <p><a class="botao contorno" href="<?= h(url('suporte.php?id=' . (int)$p['id'])) ?>" rel="nofollow">Enviar mensagem para a equipe</a></p>
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
<?php if ($eu): ?>
// conta no site (passo 44): mesmos endereços do app, com o cookie da conta + X-AF-Web
function contaApi(caminho, metodo, corpo) {
  return fetch(<?= json_encode(url('api/')) ?> + caminho, { method: metodo, credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-AF-Web': '1' }, body: corpo ? JSON.stringify(corpo) : undefined })
    .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.erro || 'Não deu certo. Tente de novo.'); return j; }); });
}
var bs = document.getElementById('botao-salvar');
bs.addEventListener('click', function () {
  var salvar = bs.getAttribute('aria-pressed') !== 'true';
  bs.disabled = true;
  contaApi('favoritos.php', 'POST', { id: <?= (int)$p['id'] ?>, salvar: salvar }).then(function () {
    bs.setAttribute('aria-pressed', salvar ? 'true' : 'false'); bs.classList.toggle('salvo', salvar);
    bs.textContent = salvar ? '♥ Salvo' : '♡ Salvar';
  }).catch(function (e) { alert(e.message); }).finally(function () { bs.disabled = false; });
});
var fa = document.getElementById('form-avaliar'), erroA = document.getElementById('erro-avaliar');
function falhaA(e) { erroA.textContent = e.message; erroA.hidden = false; }
fa.addEventListener('submit', function (ev) {
  ev.preventDefault();
  var est = fa.querySelector('input[name=estrelas]:checked');
  if (!est) { falhaA(new Error('Escolha de 1 a 5 estrelas.')); return; }
  fa.querySelector('button').disabled = true;
  contaApi('avaliar.php', 'POST', { id: <?= (int)$p['id'] ?>, estrelas: +est.value, comentario: fa.comentario.value })
    .then(function () { location.hash = 'avaliar'; location.reload(); })
    .catch(function (e) { falhaA(e); fa.querySelector('button').disabled = false; });
});
var ap = document.getElementById('apagar-avaliacao');
if (ap) ap.addEventListener('click', function () {
  if (!confirm('Apagar a sua avaliação?')) return;
  contaApi('avaliar.php?id=<?= (int)$p['id'] ?>', 'DELETE').then(function () { location.hash = 'avaliar'; location.reload(); }).catch(falhaA);
});
<?php endif ?>
// passo 59: "Quero saber o horário" etc. Lembra neste navegador quem já pediu (a página fica guardada em cache)
// passo 65: depois do pedido, pergunta se quer ser avisado (precisa entrar com o Google; volta para cá com ?avisar=)
var logado = <?= $eu ? 'true' : 'false' ?>, entrarPara = <?= json_encode(link_entrar(url_ficha($p) . '?avisar=TIPO')) ?>;
function lerLocal(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
function gravarLocal(k) { try { localStorage.setItem(k, '1'); } catch (e) {} }
// mensagens que aparecem depois do toque (passo 70: fora do HTML, para as fichas não ficarem iguais para o Google)
document.querySelectorAll('[data-quero]').forEach(function (b) {
  var cx = b.parentNode, p = document.createElement('p'), nota = document.createElement('p');
  // 17/11 (pedido do usuário): o botão diz "Pedir..." e esta linha deixa claro que a informação ainda não existe
  nota.className = 'trancado-nota'; nota.textContent = 'Vamos avisar o dono que você quer essa informação.';
  b.insertAdjacentElement('afterend', nota);
  p.className = 'quero-ok'; p.hidden = true; p.textContent = '✓ Pedido anotado! Quando o comércio completar a ficha, a informação aparece aqui.';
  cx.appendChild(p);
  if (!b.dataset.avisoTexto) return;
  var q = document.createElement('div'); q.className = 'quero-avisar'; q.hidden = true;
  q.innerHTML = '<p></p><button type="button" class="botao pequeno" data-avisar>🔔 Sim, me avise</button> '
    + '<button type="button" class="botao pequeno contorno" data-nao-avisar>Não, obrigado</button>';
  q.querySelector('p').textContent = 'Quer ser avisado quando o comércio colocar ' + b.dataset.avisoTexto + '?';
  var ok = document.createElement('p'); ok.className = 'avisar-ok'; ok.hidden = true;
  ok.innerHTML = '🔔 Combinado! Você recebe um aviso no celular (app Acha Fácil) e em <a href="' + <?= json_encode(url('minha-conta.php')) ?> + '">Minha conta</a> quando a informação entrar.';
  cx.appendChild(q); cx.appendChild(ok);
});
function avisoFeito(caixa) { var q = caixa.querySelector('.quero-avisar'), ok = caixa.querySelector('.avisar-ok'); if (q) q.hidden = true; if (ok) ok.hidden = false; }
function pedirAviso(tipo, caixa) {
  if (!logado) { gravarLocal('af_avisar_volta'); location.href = entrarPara.replace('TIPO', encodeURIComponent(tipo)); return; }
  fetch(<?= json_encode(url('api/avisar-info.php')) ?>, { method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-AF-Web': '1' }, body: JSON.stringify({ id: <?= (int)$p['id'] ?>, tipo: tipo }) })
    .then(function (r) { if (!r.ok) throw new Error(); gravarLocal('af_avisar_<?= (int)$p['id'] ?>_' + tipo); avisoFeito(caixa); })
    .catch(function () { alert('Não deu certo agora. Tente de novo.'); });
}
document.querySelectorAll('[data-quero]').forEach(function (b) {
  var chave = 'af_quero_<?= (int)$p['id'] ?>_' + b.dataset.quero, caixa = b.parentNode, ok = caixa.querySelector('.quero-ok'),
      perg = caixa.querySelector('.quero-avisar'), jaAvisa = lerLocal('af_avisar_<?= (int)$p['id'] ?>_' + b.dataset.quero);
  function feito(perguntar) { b.hidden = true; if (b.nextElementSibling && b.nextElementSibling.className === 'trancado-nota') b.nextElementSibling.hidden = true; ok.hidden = false; if (jaAvisa) avisoFeito(caixa); else if (perg && perguntar) perg.hidden = false; }
  if (lerLocal(chave)) feito(false);
  if (perg) {
    perg.querySelector('[data-avisar]').addEventListener('click', function () { pedirAviso(b.dataset.quero, caixa); });
    perg.querySelector('[data-nao-avisar]').addEventListener('click', function () { perg.hidden = true; });
  }
  b.addEventListener('click', function () {
    feito(true);
    try { localStorage.setItem(chave, '1'); } catch (e) {}
    try { fetch(<?= json_encode(url('api/quero.php')) ?>, { method: 'POST', keepalive: true, credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-AF-Web': '1' },
      body: JSON.stringify({ id: <?= (int)$p['id'] ?>, tipo: b.dataset.quero, origem: 'web' }) }); } catch (e) {}
  });
});
// voltou do login do Google com ?avisar=TIPO: grava o pedido de aviso e tira o ?avisar do endereço
(function () {
  var m = location.search.match(/[?&]avisar=(horario|fotos|sobre|whatsapp)/);
  if (!m || !logado) return;
  var b = document.querySelector('[data-quero="' + m[1] + '"]');
  if (b) { b.hidden = true; var nt = b.parentNode.querySelector('.trancado-nota'); if (nt) nt.hidden = true; b.parentNode.querySelector('.quero-ok').hidden = false; pedirAviso(m[1], b.parentNode); b.parentNode.scrollIntoView({ block: 'center' }); }
  history.replaceState(null, '', location.pathname + location.hash);
})();
document.querySelectorAll('[data-contato]').forEach(function (a) {
  a.addEventListener('click', function () {
    try { fetch(<?= json_encode(url('api/contato.php')) ?>, { method: 'POST', keepalive: true, headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: <?= (int)$p['id'] ?>, tipo: a.dataset.contato, origem: 'web' }) }); } catch (e) {}
  });
});
</script>
<?php
    echo visita_adiada_js((int)$p['id'], rawurldecode((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH)));   // 16/11
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
            ORDER BY (c.nome LIKE ? OR p.tipo LIKE ? OR p.nome LIKE ?) DESC, ' . SQL_DESTAQUE . ' DESC, ' . SQL_FICHA_PAGA . ' DESC, RAND(?) LIMIT 60',
            [...array_fill(0, 9, $like), semente_dia()]);
    }
    [$trilha] = web_trilha([["$nome_cidade - $uf", url_cidade($cidade)], ['Busca', null]]);
    pagina_inicio("Procurar em $nome_cidade | Acha Fácil!", "Procure comércios e serviços em $nome_cidade ($uf).", 'web',
        ['canonica' => web_absoluta(url_cidade($cidade)), 'noindex' => true, 'anuncios' => true]);
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
// Dividido por cidade e por tipo (pedido do usuário, 16/11) para ver no Search Console qual grupo indexa melhor:
//   /sitemap.xml                       índice com TODOS os arquivos abaixo (o que já está cadastrado no Google)
//   /sitemap-<cidade>-<tipo>.xml       um arquivo por cidade e tipo (cidades, categorias, comercios, eventos)
//   /sitemap-<tipo>.xml                índice de um tipo em todas as cidades (ex.: sitemap-comercios.xml)
//   /sitemap-<cidade>.xml              índice de uma cidade com os tipos dela (endereço antigo continua valendo)
//   /sitemap-paginas.xml               páginas fixas (inicial, planos, termos...)
const WEB_SITEMAP_TIPOS = [
    'cidades' => 'Páginas das cidades',
    'categorias' => 'Áreas e tipos de negócio',
    'comercios' => 'Fichas dos comércios',
    'eventos' => 'Agenda e eventos',
];
/** Tipos que existem hoje (eventos só com a mudança 020 aplicada). */
function web_sitemap_tipos(): array {
    $t = WEB_SITEMAP_TIPOS;
    if (!(function_exists('eventos_prontos') && eventos_prontos())) unset($t['eventos']);
    return $t;
}
function web_sitemap_arquivo(string $cidade, string $tipo): string {
    return url_publica('sitemap-' . cidade_web($cidade) . "-$tipo.xml");
}
function web_sitemap_lista(array $arquivos): string {
    $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($arquivos as $a) $x .= '<sitemap><loc>' . h($a) . "</loc></sitemap>\n";
    return $x . "</sitemapindex>\n";
}
/** Índice geral: páginas fixas + cada cidade × tipo (um índice não pode apontar para outro índice). */
function web_sitemap_indice(): string {
    $a = [url_publica('sitemap-paginas.xml')];
    foreach (CIDADES as $slug => $_) foreach (web_sitemap_tipos() as $tipo => $_n) $a[] = web_sitemap_arquivo($slug, $tipo);
    return web_sitemap_lista($a);
}
/** Índice de um tipo em todas as cidades. */
function web_sitemap_indice_tipo(string $tipo): string {
    return web_sitemap_lista(array_map(fn($slug) => web_sitemap_arquivo($slug, $tipo), array_keys(CIDADES)));
}
/** Índice de uma cidade com os tipos dela. */
function web_sitemap_indice_cidade(string $cidade): string {
    return web_sitemap_lista(array_map(fn($tipo) => web_sitemap_arquivo($cidade, $tipo), array_keys(web_sitemap_tipos())));
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
/** Endereços de uma cidade num tipo: cidades = página da cidade; categorias = áreas e tipos com 3+;
 *  comercios = fichas visíveis com telefone ou WhatsApp (as sem contato ficam fora do Google); eventos = agenda e próximos eventos. */
function web_sitemap_cidade(string $cidade, string $tipo): string {
    $urls = [];
    if ($tipo === 'cidades') $urls[] = [web_absoluta(url_cidade($cidade)), null];
    if ($tipo === 'categorias') foreach (web_areas($cidade) as $a) {
        $urls[] = [web_absoluta(url_area($cidade, $a['slug'])), null];
        foreach (web_tipos($cidade, (int)$a['id']) as $t) $urls[] = [web_absoluta(url_tipo($cidade, $a['slug'], $t['nome'])), null];
    }
    if ($tipo === 'comercios') foreach (q('SELECT p.id, p.nome, p.cidade, DATE(p.atualizado_em) AS mod_em FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE ' . visivel($cidade) . ' AND ' . SQL_TEM_CONTATO . ' ORDER BY p.id') as $p) $urls[] = [web_absoluta(url_ficha($p)), $p['mod_em']];   // sem contato: fora do Google
    if ($tipo === 'eventos' && isset(web_sitemap_tipos()['eventos'])) {
        if (eventos_proximos($cidade)) $urls[] = [web_absoluta(url_eventos($cidade)), null];   // agenda vazia fica fora (noindex)
        foreach (eventos_proximos($cidade) as $e) $urls[] = [web_absoluta(url_evento($e)), substr((string)$e['atualizado_em'], 0, 10)];
    }
    return web_sitemap_urls($urls);
}
