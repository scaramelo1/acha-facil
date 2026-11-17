<?php
// Sitemaps de uma cidade (17/11, passo 80, pedido do usuário): ao terminar a importação de uma cidade (Novidades) ou ao
// ligá-la (Cidades), gera na hora os sitemaps dela (troca o que estava no cache) e mostra os endereços para mandar no
// Search Console. Os arquivos são os mesmos que publico/web.php serve (/sitemap-<cidade>.xml e /sitemap-<cidade>-<tipo>.xml).
require_once __DIR__ . '/web.php';

/** Gera agora os sitemaps da cidade e devolve [índice, [[tipo, nome, url, endereços], ...]], ou null se ela está desligada. */
function sitemaps_gerar_cidade(string $slug): ?array {
    if (!isset(CIDADES[$slug])) return null;   // desligada: web.php não serve sitemap dela
    $dir = web_cache_dir();
    $guardar = function (string $arquivo, callable $gerar) use ($dir): string {
        $chave = 'sitemap2|' . $arquivo;   // mesma chave de publico/web.php
        if ($dir) @unlink("$dir/" . hash('sha256', $chave . '|' . hoje()) . '.html');
        return web_cache($chave, function () use ($gerar) { echo $gerar(); }, 6 * 3600);
    };
    $web = cidade_web($slug);
    $partes = [];
    foreach (web_sitemap_tipos() as $tipo => $nome) {
        $xml = $guardar("sitemap-$web-$tipo.xml", fn() => web_sitemap_cidade($slug, $tipo));
        $partes[] = [$tipo, $nome, web_sitemap_arquivo($slug, $tipo), substr_count($xml, '<url>')];
    }
    $guardar("sitemap-$web.xml", fn() => web_sitemap_indice_cidade($slug));
    // os índices gerais também passam a listar a cidade
    $guardar('sitemap.xml', fn() => web_sitemap_indice());
    foreach (web_sitemap_tipos() as $tipo => $_) $guardar("sitemap-$tipo.xml", fn() => web_sitemap_indice_tipo($tipo));
    return [url_publica("sitemap-$web.xml"), $partes];
}

/** Quadro para o admin: links prontos para copiar no Search Console. */
function sitemaps_quadro(string $slug): string {
    $nome = h(nome_cidade($slug) . ' - ' . uf_cidade($slug));
    $r = sitemaps_gerar_cidade($slug);
    if (!$r) return '<div class="quadro"><strong>Sitemaps de ' . $nome . '</strong><br>A cidade está <strong>desligada</strong>: '
        . 'o Google só recebe os sitemaps dela depois que você ligar em <a href="cidades.php">Cidades</a> (eles aparecem aqui na hora).</div>';
    [$indice, $partes] = $r;
    $total = array_sum(array_column($partes, 3));
    $html = '<div class="quadro" id="sitemaps"><strong>🗺️ Sitemaps de ' . $nome . ' gerados agora</strong> (' . num($total) . ' páginas)'
        . '<p style="margin:.5em 0">Mande este no <a href="https://search.google.com/search-console/sitemaps?resource_id='
        . rawurlencode(url_publica('')) . '" target="_blank" rel="noopener">Search Console → Sitemaps</a> (campo "Adicionar um novo sitemap"):</p>'
        . '<p><input readonly value="' . h($indice) . '" style="width:100%;font-family:monospace" onclick="this.select()"> '
        . '<button type="button" class="botao pequeno" onclick="navigator.clipboard.writeText(\'' . h($indice) . '\');this.textContent=\'Copiado ✔\'">Copiar</button>'
        . ' <a class="botao pequeno contorno" href="' . h($indice) . '" target="_blank" rel="noopener">Abrir</a></p>'
        . '<p class="discreto" style="margin:.4em 0">Ele junta estes (o sitemap.xml geral também já lista a cidade):</p><ul>';
    foreach ($partes as [, $n, $url, $qtd]) $html .= '<li><a href="' . h($url) . '" target="_blank" rel="noopener">' . h($n) . '</a>: ' . num($qtd) . ' ' . ($qtd === 1 ? 'página' : 'páginas') . '</li>';
    // 17/11: a página da cidade foi enviada como sitemap por engano ("O sitemap está em HTML"): deixar claro que ela é outra coisa
    return $html . '</ul><p class="discreto" style="margin:0">Depois, à parte: no Search Console, em <strong>Inspeção de URL</strong> (barra do topo, '
        . '<strong>não</strong> em Sitemaps), cole a página da cidade e peça a indexação: <code>' . h(web_absoluta(url_cidade($slug))) . '</code>. '
        . 'Em Sitemaps vai só o endereço que termina em <strong>.xml</strong>.</p></div>';
}
