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
require_once __DIR__ . '/ofertas.php';   // 19/11: Oferta da semana na ficha e na página da cidade
require_once __DIR__ . '/web-conteudo.php';   // 20/11 (passo 119): sobre, mais conhecidos, vizinhas e perguntas

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
        WHERE $sql ORDER BY " . SQL_DESTAQUE . ' DESC, ' . SQL_FICHA_PAGA . ' DESC, ' . SQL_COM_HORARIO . ' DESC, p.total_avaliacoes > 0 DESC, RAND(?)
        LIMIT ' . WEB_POR_PAGINA . ' OFFSET ' . (max(1, $pagina) - 1) * WEB_POR_PAGINA, [...$par, semente_dia()]);
}
/**
 * 19/11: "Aberto agora". Só dá para saber de quem informou o horário (painel do dono), então a lista com o filtro mostra
 * só esses, abertos neste minuto, na mesma ordem justa. Não vai para o cache nem para o Google (muda a cada minuto).
 */
function web_abertos(string $cidade, int $categoria_id, ?string $tipo, ?string $bairro): array {
    [$sql, $par] = web_filtro($cidade, $categoria_id, $tipo, $bairro);
    $todos = linhas('SELECT ' . CAMPOS_RESUMO . ", p.endereco, p.horarios FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE $sql AND p.horarios IS NOT NULL AND p.horarios NOT IN ('', '[]', '{}') ORDER BY " . SQL_DESTAQUE . ' DESC, ' . SQL_FICHA_PAGA . ' DESC, p.total_avaliacoes > 0 DESC, RAND(?)
        LIMIT 500', [...$par, semente_dia()]);
    return array_values(array_filter($todos, fn($p) => aberto_agora(horarios_ler($p['horarios'])) === true));
}
/** Botão liga/desliga "Aberto agora" (mantém o bairro e o termo da busca). */
function web_botao_aberto(string $base, bool $ligado, array $manter = []): string {
    $q = array_filter($manter, fn($v) => $v !== null && $v !== '');
    if (!$ligado) $q['aberto'] = 1;
    $href = $base . ($q ? '?' . http_build_query($q) : '');
    return '<a class="filtro-aberto' . ($ligado ? ' ligado' : '') . '" href="' . h($href) . '" rel="nofollow">'
        . ($ligado ? '✕ Ver todos (aberto ou não)' : '🟢 Aberto agora') . '</a>';
}
/** Aviso da lista "Aberto agora": poucos informaram o horário, e o dono pode informar o dele. */
function web_aviso_aberto(int $achados): string {
    return '<p class="aviso-aberto">' . ($achados ? '<b>' . web_qtd($achados, 'lugar aberto', 'lugares abertos') . ' agora</b> entre os que informaram o horário.'
            : '<b>Nenhum lugar com horário informado está aberto agora.</b>')
        . ' Muitos comércios ainda não informaram o horário no Acha Fácil.'
        . ' É dono? <a href="' . h(url('busca.php')) . '" rel="nofollow">Informe o seu horário de graça</a> e apareça aqui.</p>';
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
    $ordem = ' ORDER BY ' . SQL_DESTAQUE . ' DESC, ' . SQL_FICHA_PAGA . ' DESC, ' . SQL_COM_HORARIO . " DESC, RAND(?) LIMIT $limite";
    $r = $p['tipo'] ? linhas("$campos AND p.tipo = ?$ordem", [$p['id'], $p['tipo'], semente_dia() + (int)$p['id']]) : [];
    if (count($r) >= 3) return [$r, true];
    return [linhas("$campos AND p.categoria_id = ?$ordem", [$p['id'], $p['categoria_id'], semente_dia() + (int)$p['id']]), false];
}

// ---------- horário ----------
// aberto_agora() foi para inc/horarios.php (19/11)
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
    return '<i class="' . $classe . '" style="background:' . h(cor_comercio($p)) . '" aria-hidden="true">' . emoji_area(icone_comercio($p)) . '</i>';
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
        . (!$p['destaque_pago'] && web_tem_horario($p) ? '<span class="selo-horario">🕒 Horário informado</span>' : '')   // passo 116
        . web_nota($p) . '</span></a></li>';
}
function web_tem_horario(array $p): bool {
    $h = (string)($p['horarios'] ?? '');
    return $h !== '' && !in_array($h, ['[]', '{}'], true);
}
/**
 * 19/11 (avaliação do site): tudo o que falta na ficha num bloco só, em vez de um quadro 🔒 por item.
 * Cada linha tem o seu "Pedir" (mesmo data-quero de web_trancado, contado em api/quero.php).
 * $faltam: tipo => rótulo, na ordem em que aparecem.
 */
function web_faltam(array $faltam): string {
    if (!$faltam) return '';
    $itens = '';
    foreach ($faltam as $tipo => $rotulo) {
        $itens .= '<li class="trancado" data-trancado="' . h($tipo) . '"><span class="faltam-item">' . h($rotulo) . '</span>'
            . '<button type="button" class="botao pequeno contorno" data-quero="' . h($tipo) . '"'
            . (web_avisos_ligados() ? ' data-aviso-texto="' . h(AVISO_INFO_TEXTO[$tipo] ?? 'isso') . '"' : '') . '>🙋 Pedir</button></li>';
    }
    $lista = array_map(fn($r) => $r === 'WhatsApp' ? $r : mb_strtolower($r), array_values($faltam));
    $txt = count($lista) > 1 ? implode(', ', array_slice($lista, 0, -1)) . ' e ' . end($lista) : $lista[0];
    return '<section class="faltam" aria-label="Informações que faltam"><p class="faltam-titulo"><span aria-hidden="true">🔒</span> '
        . 'Faltam nesta ficha: ' . h($txt) . '</p>'
        . '<p class="faltam-texto">Toque em <b>Pedir</b> e a gente avisa o comércio que você quer essa informação.</p>'
        . '<ul class="faltam-lista">' . $itens . '</ul></section>';
}
function web_avisos_ligados(): bool {
    static $ok = null;
    if ($ok === null) { require_once __DIR__ . '/avisos-info.php'; $ok = avisos_info_prontos(); }
    return $ok;
}
/** 19/11: cartões das Ofertas da semana na página da cidade (oferta + comércio, leva à ficha). */
function web_cartoes_ofertas(array $lista): string {
    $html = '';
    foreach ($lista as $o) {
        $html .= '<li><a class="oferta-cartao" href="' . h(url_ficha($o)) . '">'
            . ($o['oferta_foto'] ? '<img src="' . h(foto_url($o['oferta_foto'])) . '" alt="" loading="lazy">' : '<span class="oferta-cartao-icone" aria-hidden="true">🏷️</span>')
            . '<span class="oferta-cartao-info"><b>' . h($o['oferta_titulo']) . '</b>'
            . '<span>' . h(nome_curto($o['nome'])) . '</span>'
            . ($o['oferta_por'] ? '<span class="oferta-precos">' . ($o['oferta_de'] ? '<s>' . h(dinheiro($o['oferta_de'])) . '</s> ' : '') . '<b>' . h(dinheiro($o['oferta_por'])) . '</b></span>' : '')
            . '</span></a></li>';   // 19/11: sem a data de fim (pedido do usuário)
    }
    return '<ul class="ofertas-lista">' . $html . '</ul>';
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

  <?php if ($ofertas = ofertas_da_cidade($cidade)): // 19/11: Ofertas da semana (benefício pago do Destaque), antes de tudo ?>
  <section class="secao-ofertas"><div class="conteudo">
    <h2>🏷️ Ofertas da semana em <?= h($nome) ?></h2>
    <?= web_cartoes_ofertas($ofertas) ?>
  </div></section>
  <?php endif ?>

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

  <?= web_conteudo_cidade($cidade, $areas) ?>

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
function web_pagina_lista(string $cidade, array $area, ?string $tipo, ?string $bairro, int $pagina, int $total, bool $aberto = false): void {
    $itens = $aberto ? web_abertos($cidade, (int)$area['id'], $tipo, $bairro) : web_lista($cidade, (int)$area['id'], $tipo, $bairro, $pagina);
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
    $noindex = $aberto || $bairro !== null || ($tipo !== null && $total < WEB_TIPO_MINIMO);
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
        <?php if ($aberto): ?><input type="hidden" name="aberto" value="1"><?php endif ?>
        <noscript><button class="botao">Filtrar</button></noscript>
      </form>
    <?php endif ?>
    <p class="filtros-linha"><?= web_botao_aberto($base_url, $aberto, ['bairro' => $bairro]) ?></p>
    <?php if ($aberto): ?><?= web_aviso_aberto(count($itens)) ?><?php endif ?>
    <?php if ($itens): ?>
      <?= web_cartoes($itens) ?>
    <?php elseif (!$aberto): ?>
      <p class="aviso erro">Nenhum lugar encontrado<?= $bairro !== null ? ' neste bairro' : '' ?>.</p>
    <?php endif ?>
    <?= $aberto ? '' : web_paginacao($base_url, $pagina, $paginas, $bairro) ?>
    <p class="discreto">Tem um negócio de <?= h(mb_strtolower($titulo_lista)) ?> em <?= h($nome_cidade) ?> e não está aqui? <a href="<?= h(url('incluir.php')) ?>">Inclua de graça</a>.</p>
  </div></section>
  <?= ($pagina === 1 && $bairro === null && !$aberto) ? web_conteudo_lista($cidade, $area, $tipo) : '' ?>
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
    // 19/11 (avaliação do site): "Endereço: Atendimento a domicílio" ficava estranho no Google; sem rua de verdade vira "Atende a domicílio"
    $tem_rua = $p['endereco'] && !preg_match('/domic[ií]lio|em casa|delivery|online|sem endere/iu', $p['endereco']);
    $descricao = $nome . ' em ' . $nome_cidade . ' (' . $uf . ')' . ($tipo ? ", $tipo" : '') . '. '
        . ($tem_rua ? 'Endereço: ' . $p['endereco'] . '. ' : ($p['atende_em_casa'] || $p['endereco'] ? 'Atende a domicílio. ' : ''))
        . 'Veja horário, telefone e WhatsApp no Acha Fácil.';
    pagina_inicio(web_titulo_ficha((string)$nome, (string)$tipo, (string)$nome_cidade), mb_substr($descricao, 0, 300), 'web',
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
        <?php // 19/11: compartilhar no WhatsApp ("Olha esse lugar: ..." com o link da ficha); conta como 'compartilhar'
          $msg = 'Olha esse lugar: ' . $nome . ($tipo ? " ($tipo)" : '') . ", em $nome_cidade\n$url"; ?>
        <a class="botao contorno botao-compartilhar" href="https://wa.me/?text=<?= h(rawurlencode($msg)) ?>" data-contato="compartilhar" rel="nofollow noopener" target="_blank">Compartilhar no WhatsApp</a>
      </div>

      <?php if ($oferta = oferta_no_ar((int)$p['id'])) echo oferta_quadro_ficha($oferta);   // 19/11: Oferta da semana (só Destaque) ?>

      <?= web_faltam(array_filter([   // passo 59 + 19/11: o que falta, num bloco só, cada item com o seu "Pedir"
          'whatsapp' => $c['whatsapp'] ? null : 'WhatsApp',
          'fotos' => $fotos ? null : 'Fotos',
          'horario' => $tem_horario ? null : 'Horário',
          'sobre' => $tem_sobre ? null : 'O que vende ou faz',
      ])) ?>

      <?php if ($fotos): ?>
        <div class="ficha-fotos">
          <?php foreach ($fotos as $i => $f): // 17/11: abre na janela de fotos (JS abaixo); o link é reserva sem JS ?><a href="<?= h($f) ?>" data-foto="<?= $i ?>"><img src="<?= h($f) ?>" alt="<?= h($p['nome']) ?>, foto <?= $i + 1 ?>" loading="<?= $i ? 'lazy' : 'eager' ?>" height="200"></a><?php endforeach ?>
        </div>
        <div class="foto-janela" id="foto-janela" role="dialog" aria-modal="true" aria-label="Fotos de <?= h($p['nome']) ?>" hidden>
          <button type="button" class="fj-fechar" aria-label="Fechar">&times;</button>
          <div class="fj-trilho"><?php foreach ($fotos as $i => $f): ?><div class="fj-item"><img data-src="<?= h($f) ?>" alt="<?= h($p['nome']) ?>, foto <?= $i + 1 ?>"></div><?php endforeach ?></div>
          <?php if (count($fotos) > 1): ?>
          <button type="button" class="fj-seta fj-ant" aria-label="Foto anterior">&lsaquo;</button>
          <button type="button" class="fj-seta fj-prox" aria-label="Próxima foto">&rsaquo;</button>
          <?php endif ?>
          <div class="fj-conta" aria-live="polite"></div>
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
            <p class="discreto">Não informado.</p>
          <?php endif ?>
        </div>
      </div>

      <?php // 19/11: o convite ao dono fica só na caixa "Este comércio é seu?" (antes repetia aqui) ?>

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
        <?php // 19/11 (passo 116): números de verdade da própria ficha, para o dono ver que tem gente procurando
        $vistas30 = (int)(valor("SELECT SUM(total) FROM contatos WHERE profissional_id = ? AND tipo = 'perfil' AND dia >= CURDATE() - INTERVAL 29 DAY", [$p['id']]) ?? 0);
        $pedidos30 = 0;
        try { $pedidos30 = (int)(valor('SELECT SUM(total) FROM pedidos_info WHERE profissional_id = ? AND dia >= CURDATE() - INTERVAL 29 DAY', [$p['id']]) ?? 0); } catch (PDOException) {}
        if ($vistas30 >= 3 || $pedidos30): ?>
        <p class="numeros-dono">👀 Esta página foi vista <strong><?= web_num($vistas30) ?> vezes</strong> nos últimos 30 dias<?= $pedidos30 ? ' e <strong>' . web_num($pedidos30) . ($pedidos30 === 1 ? ' pessoa pediu' : ' pessoas pediram') . '</strong> informações que faltam' : '' ?>.</p>
        <?php endif ?>
        <p>Cadastre-se <strong>grátis</strong> com a sua conta Google e corrija o telefone, o WhatsApp, o endereço e o horário.
          <?php if (!web_tem_horario($p)): ?><br>🕒 Informando o horário, sua ficha aparece no filtro <strong>"Aberto agora"</strong> e antes das outras nas listas.<?php endif ?>
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
    .then(function () { afMarcarAvaliar(true); location.hash = 'avaliar'; location.reload(); })
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
  var cx = b.parentNode, p = document.createElement('p'), nota = document.createElement('p'), noBloco = !!cx.closest('.faltam');
  // 17/11 (pedido do usuário): o botão diz "Pedir..." e esta linha deixa claro que a informação ainda não existe
  // 19/11: no bloco "Faltam nesta ficha" essa explicação já está no topo, uma vez só
  nota.className = 'trancado-nota'; nota.textContent = 'Vamos avisar o dono que você quer essa informação.';
  if (!noBloco) b.insertAdjacentElement('afterend', nota);
  p.className = 'quero-ok'; p.hidden = true; p.textContent = noBloco ? '✓ Pedido anotado' : '✓ Pedido anotado! Quando o comércio completar a ficha, a informação aparece aqui.';
  cx.appendChild(p);
  if (!b.dataset.avisoTexto) return;
  var q = document.createElement('div'); q.className = 'quero-avisar'; q.hidden = true;
  // 19/11 (convite mais claro, para acompanhar quantos aceitam): diz a vantagem e que é 1 toque, sem senha
  q.innerHTML = '<p></p><p class="quero-avisar-sub"></p><button type="button" class="botao pequeno" data-avisar></button> '
    + '<button type="button" class="botao pequeno contorno" data-nao-avisar>Agora não</button>';
  q.querySelector('p').textContent = '🔔 Quer saber quando colocarem ' + b.dataset.avisoTexto + '?';
  q.querySelector('.quero-avisar-sub').textContent = logado
    ? 'A gente te avisa no celular e em Minha conta assim que a informação entrar.'
    : 'Entre com o Google e receba o aviso no celular e em Minha conta. É 1 toque, sem senha.';
  q.querySelector('[data-avisar]').textContent = logado ? '🔔 Sim, me avise' : 'Entrar com o Google e me avisar';
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
// 17/11 (pedido do usuário): fotos abrem numa janela por cima da página, em carrossel (setas, dedo, teclado)
(function () {
  var j = document.getElementById('foto-janela');
  if (!j) return;
  var trilho = j.querySelector('.fj-trilho'), itens = trilho.children, conta = j.querySelector('.fj-conta'), total = itens.length, voltarFoco = null;
  function carregar(i) { var im = itens[i] && itens[i].querySelector('img'); if (im && !im.src) im.src = im.dataset.src; }
  function atual() { return Math.round(trilho.scrollLeft / (trilho.clientWidth || 1)); }
  function ir(i, suave) {
    i = (i + total) % total;
    carregar(i); carregar((i + 1) % total); carregar((i - 1 + total) % total);
    trilho.scrollTo({ left: i * trilho.clientWidth, behavior: suave ? 'smooth' : 'auto' });
    conta.textContent = total > 1 ? (i + 1) + ' de ' + total : '';
  }
  function abrir(i) {
    voltarFoco = document.activeElement;
    j.hidden = false; document.documentElement.classList.add('fj-aberta');
    ir(i, false); j.querySelector('.fj-fechar').focus();
  }
  function fechar() { j.hidden = true; document.documentElement.classList.remove('fj-aberta'); if (voltarFoco) voltarFoco.focus(); }
  document.querySelectorAll('[data-foto]').forEach(function (a) {
    a.addEventListener('click', function (e) { e.preventDefault(); abrir(+a.dataset.foto); });
  });
  j.querySelector('.fj-fechar').addEventListener('click', fechar);
  var ant = j.querySelector('.fj-ant'), prox = j.querySelector('.fj-prox');
  if (ant) ant.addEventListener('click', function () { ir(atual() - 1, true); });
  if (prox) prox.addEventListener('click', function () { ir(atual() + 1, true); });
  j.addEventListener('click', function (e) { if (e.target === j || e.target.classList.contains('fj-item')) fechar(); });
  var t;
  trilho.addEventListener('scroll', function () { clearTimeout(t); t = setTimeout(function () { var i = atual(); carregar(i); carregar(i + 1 < total ? i + 1 : i); conta.textContent = total > 1 ? (i + 1) + ' de ' + total : ''; }, 80); });
  document.addEventListener('keydown', function (e) {
    if (j.hidden) return;
    if (e.key === 'Escape') fechar();
    else if (e.key === 'ArrowRight') ir(atual() + 1, true);
    else if (e.key === 'ArrowLeft') ir(atual() - 1, true);
  });
  window.addEventListener('resize', function () { if (!j.hidden) ir(atual(), false); });
})();
// 19/11 (passo 107): quem toca em WhatsApp/Ligar recebe depois o convite "Como foi?" (barra em pagina_fim(), inc/pagina.php)
function afMarcarAvaliar(fechar) {
  try {
    var id = <?= (int)$p['id'] ?>, l = JSON.parse(localStorage.getItem('af_avaliar') || '[]').filter(function (c) { return c.id !== id; });
    if (fechar) { var a = JSON.parse(localStorage.getItem('af_avaliar') || '[]'); a.forEach(function (c) { if (c.id === id) c.f = 1; }); localStorage.setItem('af_avaliar', JSON.stringify(a)); return; }
    l.unshift({ id: id, n: <?= json_encode((string)$p['nome'], JSON_UNESCAPED_UNICODE) ?>, u: location.pathname, em: Date.now() });
    localStorage.setItem('af_avaliar', JSON.stringify(l.slice(0, 20)));
  } catch (e) {}
}
<?php if ($minha): ?>afMarcarAvaliar(true);<?php endif ?>
document.querySelectorAll('[data-contato]').forEach(function (a) {
  a.addEventListener('click', function () {
    if ((a.dataset.contato === 'whatsapp' || a.dataset.contato === 'ligar') && !<?= $minha ? 'true' : 'false' ?>) afMarcarAvaliar(false);
    try { fetch(<?= json_encode(url('api/contato.php')) ?>, { method: 'POST', keepalive: true, headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: <?= (int)$p['id'] ?>, tipo: a.dataset.contato, origem: 'web' }) }); } catch (e) {}
  });
});
</script>
<?php
    echo visita_adiada_js((int)$p['id'], rawurldecode((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH)));   // 16/11
    pagina_fim();
}

/**
 * Título da ficha para o Google (19/11, passo 117, avaliação item 4): até ~65 letras, que é o que o Google mostra sem cortar.
 * Tira a cidade do fim do nome ("Spani Atacadista São João da Boa Vista" -> "Spani Atacadista") e vai encurtando:
 *   "Nome - Tipo em Cidade | Acha Fácil!" -> "Nome - Tipo em Cidade" -> "Nome em Cidade" -> "Nome - Tipo" -> "Nome" (cortado).
 */
function web_titulo_ficha(string $nome, string $tipo, string $cidade, int $max = 65): string {
    $nome = trim($nome);
    $sem = trim((string)preg_replace('/[\s\-–|,]*' . preg_quote($cidade, '/') . '(\s*[-–]?\s*(SP|MG))?\s*$/iu', '', $nome));
    if (mb_strlen($sem) >= 3) $nome = $sem;
    $tipo = trim($tipo);
    $opcoes = [];
    if ($tipo !== '' && mb_stripos($nome, $tipo) === false) {
        $opcoes[] = "$nome - $tipo em $cidade | Acha Fácil!";
        $opcoes[] = "$nome - $tipo em $cidade";
    }
    $opcoes[] = "$nome em $cidade | Acha Fácil!";
    $opcoes[] = "$nome em $cidade";
    if ($tipo !== '' && mb_stripos($nome, $tipo) === false) $opcoes[] = "$nome - $tipo";
    foreach ($opcoes as $o) if (mb_strlen($o) <= $max) return $o;
    return mb_strlen($nome) <= $max ? $nome : rtrim(mb_substr($nome, 0, $max - 1)) . '…';
}

/** Busca dentro da cidade (não vai para o Google): mesma regra da busca do app (inc/busca.php busca_executar()). */
function web_pagina_busca(string $cidade, string $termo, bool $aberto = false): void {
    require_once __DIR__ . '/busca.php';
    [$nome_cidade, , $uf] = CIDADES[$cidade];
    $achados = [];
    $r = ['lista' => [], 'corrigido' => null, 'parcial' => false];
    if (mb_strlen($termo) >= 2) {
        $r = busca_executar($termo, $cidade, 60, $aberto);
        $achados = $r['lista'];
        // 19/11: "Aberto agora" (só quem informou o horário)
        if ($aberto) $achados = array_values(array_filter($achados, fn($p) => aberto_agora(horarios_ler($p['horarios'])) === true));
        registrar_busca($termo, $cidade, count($achados), $r['corrigido'], 'site');   // 19/11 (passo 106): /admin/buscas.php
    }
    $url_busca = url(cidade_web($cidade) . '/buscar');
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
    <?php elseif ($termo !== '' && !$achados && !$aberto): ?>
      <?= web_busca_sem_resultado($cidade, $termo) ?>
    <?php elseif ($termo !== ''): ?>
      <?php if ($r['corrigido'] !== null && !$r['parcial']): ?>
        <p class="aviso-busca">Não achamos “<?= h($termo) ?>”. Mostrando resultados para <b>“<?= h($r['corrigido']) ?>”</b>.</p>
      <?php elseif ($r['parcial']): ?>
        <p class="aviso-busca">Nenhum lugar tem tudo de “<?= h($termo) ?>”. Mostrando os que têm <b>parte das palavras</b>.</p>
      <?php endif ?>
      <p class="filtros-linha"><?= web_botao_aberto($url_busca, $aberto, ['q' => $termo]) ?></p>
      <?php if ($aberto): ?>
        <?= web_aviso_aberto(count($achados)) ?>
      <?php else: ?>
        <p><?= web_qtd(count($achados), 'resultado', 'resultados') ?> para "<?= h($termo) ?>"<?= count($achados) >= 60 ? ' (os 60 primeiros)' : '' ?>.</p>
      <?php endif ?>
      <?= $achados ? web_cartoes($achados) : '' ?>
    <?php endif ?>
  </div></section>
  <?= web_baixe_app($cidade) ?>
</main>
<?php
    pagina_fim();
}

/**
 * Página da busca sem nenhum resultado (19/11, passo 106): em vez de só "tente outra palavra", mostra o que tem em outras
 * cidades, buscas que deram certo na cidade, as áreas e o convite para incluir o negócio de graça.
 */
function web_busca_sem_resultado(string $cidade, string $termo): string {
    [$nome_cidade] = CIDADES[$cidade];
    $outras = busca_outras_cidades($termo, $cidade);
    $sugestoes = buscas_populares($cidade);
    if (count($sugestoes) < 6) {
        foreach (['pizzaria', 'farmácia', 'mecânica', 'supermercado', 'dentista', 'barbearia', 'padaria', 'pet shop'] as $s)
            if (count($sugestoes) < 8 && !in_array($s, $sugestoes, true)) $sugestoes[] = $s;
    }
    $url_busca = url(cidade_web($cidade) . '/buscar');
    ob_start();
    ?>
<div class="sem-resultado">
  <p class="aviso erro">Nada encontrado com “<?= h($termo) ?>” em <?= h($nome_cidade) ?>.</p>
  <p class="discreto">Dica: escreva só o principal, como <b>pizzaria</b>, <b>mecânica</b> ou <b>farmácia</b>.</p>

  <?php if ($outras): ?>
  <h2>Tem em outras cidades</h2>
  <p class="chips"><?php foreach ($outras as $s => $n): ?><a href="<?= h(url(cidade_web($s) . '/buscar') . '?' . http_build_query(['q' => $termo])) ?>" rel="nofollow"><?= h(nome_cidade($s, true)) ?> · <?= web_num($n) ?></a><?php endforeach ?></p>
  <?php endif ?>

  <h2>O pessoal de <?= h($nome_cidade) ?> procura</h2>
  <p class="chips"><?php foreach ($sugestoes as $s): ?><a href="<?= h($url_busca . '?' . http_build_query(['q' => $s])) ?>" rel="nofollow"><?= h($s) ?></a><?php endforeach ?></p>

  <h2>Ou veja por área</h2>
  <div class="areas">
    <?php foreach (web_areas($cidade) as $a): ?>
      <a class="area" href="<?= h(url_area($cidade, $a['slug'])) ?>"><i style="background:<?= h($a['cor']) ?>"><?= emoji_area($a['icone']) ?></i><span><b><?= h($a['nome']) ?></b><small><?= web_qtd($a['total'], 'lugar', 'lugares') ?></small></span></a>
    <?php endforeach ?>
  </div>

  <div class="convite-incluir">
    <p><b>Conhece um lugar assim em <?= h($nome_cidade) ?>?</b> Inclua o negócio no Acha Fácil de graça. Pode ser o seu ou de alguém que você indica.</p>
    <a class="botao" href="<?= h(url('incluir.php') . '?' . http_build_query(['cidade' => $cidade])) ?>" rel="nofollow">Incluir um negócio de graça</a>
  </div>
</div>
<?php
    return (string)ob_get_clean();
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
 *  comercios = fichas visíveis que passam em SQL_NO_GOOGLE (as outras ficam fora do Google); eventos = agenda e próximos eventos. */
function web_sitemap_cidade(string $cidade, string $tipo): string {
    $urls = [];
    if ($tipo === 'cidades') $urls[] = [web_absoluta(url_cidade($cidade)), null];
    if ($tipo === 'categorias') foreach (web_areas($cidade) as $a) {
        $urls[] = [web_absoluta(url_area($cidade, $a['slug'])), null];
        foreach (web_tipos($cidade, (int)$a['id']) as $t) $urls[] = [web_absoluta(url_tipo($cidade, $a['slug'], $t['nome'])), null];
    }
    if ($tipo === 'comercios') foreach (q('SELECT p.id, p.nome, p.cidade, DATE(p.atualizado_em) AS mod_em FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE ' . visivel($cidade) . ' AND ' . SQL_NO_GOOGLE . ' ORDER BY p.id') as $p) $urls[] = [web_absoluta(url_ficha($p)), $p['mod_em']];   // 20/11: regra SQL_NO_GOOGLE (guia.php)
    if ($tipo === 'eventos' && isset(web_sitemap_tipos()['eventos'])) {
        if (eventos_proximos($cidade)) $urls[] = [web_absoluta(url_eventos($cidade)), null];   // agenda vazia fica fora (noindex)
        foreach (eventos_proximos($cidade) as $e) $urls[] = [web_absoluta(url_evento($e)), substr((string)$e['atualizado_em'], 0, 10)];
    }
    return web_sitemap_urls($urls);
}
