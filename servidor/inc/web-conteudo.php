<?php
// Passo 119 (4), 20/11: conteúdo próprio nas páginas de cidade, área e tipo, feito com dados reais de cada página
// (números, bairros, mais conhecidos, cidades vizinhas e perguntas), para elas não serem só uma lista que muda todo dia.
// Só aparece na página 1, sem filtro de bairro e sem "Aberto agora" (as páginas que vão para o Google).
// Regra do projeto: nota do Google não é exibida; popularidade (nº de avaliações no Google) só ordena ("mais conhecidos").
declare(strict_types=1);

/** Cidades ativas mais perto (linha reta), sem a própria. */
function web_cidades_perto(string $cidade, int $n = 6): array {
    $c = CIDADES[$cidade] ?? null;
    if (!$c || $c[3] === null) return [];
    $dist = [];
    foreach (CIDADES as $s => $o) {
        if ($s === $cidade || $o[3] === null) continue;
        $dx = ($o[4] - $c[4]) * cos(deg2rad(($o[3] + $c[3]) / 2));
        $dy = $o[3] - $c[3];
        $dist[$s] = sqrt($dx * $dx + $dy * $dy) * 111.2;
    }
    asort($dist);
    return array_slice($dist, 0, $n, true);
}

/** Números de uma lista (cidade, ou cidade + área, ou + tipo). */
function web_numeros_lista(string $cidade, ?int $categoria_id = null, ?string $tipo = null): array {
    $sql = visivel($cidade);
    $par = [];
    if ($categoria_id !== null) { $sql .= ' AND p.categoria_id = ?'; $par[] = $categoria_id; }
    if ($tipo !== null) { $sql .= ' AND p.tipo = ?'; $par[] = $tipo; }
    $r = linha("SELECT COUNT(*) AS total,
        SUM(COALESCE(p.whatsapp, '') <> '') AS whats,
        SUM(COALESCE(p.telefone, '') <> '' OR COALESCE(p.whatsapp, '') <> '') AS contato,
        SUM(COALESCE(p.site, '') <> '' OR COALESCE(p.instagram, '') <> '') AS online,
        SUM(" . SQL_COM_HORARIO . ") AS horario,
        SUM(p.atende_em_casa = 1) AS em_casa,
        COUNT(DISTINCT NULLIF(p.bairro, '')) AS bairros
        FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE $sql", $par) ?? [];
    return array_map('intval', $r);
}

/** Bairros com mais lugares: [[bairro, total]]. */
function web_top_bairros(string $cidade, ?int $categoria_id = null, ?string $tipo = null, int $n = 6): array {
    $sql = visivel($cidade) . " AND p.bairro IS NOT NULL AND p.bairro <> ''";
    $par = [];
    if ($categoria_id !== null) { $sql .= ' AND p.categoria_id = ?'; $par[] = $categoria_id; }
    if ($tipo !== null) { $sql .= ' AND p.tipo = ?'; $par[] = $tipo; }
    return linhas("SELECT p.bairro, COUNT(*) AS total FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE $sql GROUP BY p.bairro HAVING total >= 2 ORDER BY total DESC, p.bairro LIMIT $n", $par);
}

/** Mais conhecidos (mais avaliações no Google + avaliações no Acha Fácil), ordem fixa: nome, bairro, id. */
function web_mais_conhecidos(string $cidade, ?int $categoria_id = null, ?string $tipo = null, int $n = 5): array {
    $sql = visivel($cidade);
    $par = [];
    if ($categoria_id !== null) { $sql .= ' AND p.categoria_id = ?'; $par[] = $categoria_id; }
    if ($tipo !== null) { $sql .= ' AND p.tipo = ?'; $par[] = $tipo; }
    return linhas("SELECT p.id, p.nome, p.cidade, p.bairro, p.tipo FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE $sql AND (p.popularidade > 0 OR p.total_avaliacoes > 0)
        ORDER BY p.popularidade + p.total_avaliacoes * 5 DESC, p.id LIMIT $n", $par);
}

/** Tipos mais comuns da cidade (de todas as áreas), com o slug da área para o link. */
function web_top_tipos_cidade(string $cidade, int $n = 16): array {
    return linhas('SELECT p.tipo AS nome, c.slug AS area, COUNT(*) AS total FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE ' . visivel($cidade) . " AND p.tipo IS NOT NULL AND p.tipo <> '' AND p.tipo NOT IN ('Escritório da empresa', 'Empresa') GROUP BY p.tipo, c.slug
        HAVING total >= " . WEB_TIPO_MINIMO . " ORDER BY total DESC, p.tipo LIMIT $n");
}

/** O mesmo tipo (ou área) nas cidades vizinhas que têm página no Google: [[cidade, km, total]]. */
function web_vizinhas_com(string $cidade, int $categoria_id, ?string $tipo): array {
    $perto = web_cidades_perto($cidade, 8);
    if (!$perto) return [];
    $lista = "'" . implode("','", array_keys($perto)) . "'";   // slugs já validados em carregar_cidades()
    $sql = visivel() . " AND p.cidade IN ($lista) AND p.categoria_id = ?";
    $par = [$categoria_id];
    if ($tipo !== null) { $sql .= ' AND p.tipo = ?'; $par[] = $tipo; }
    $tot = array_column(linhas("SELECT p.cidade, COUNT(*) AS total FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE $sql GROUP BY p.cidade", $par), 'total', 'cidade');
    $r = [];
    foreach ($perto as $s => $km) {
        $t = (int)($tot[$s] ?? 0);
        if ($t >= ($tipo !== null ? WEB_TIPO_MINIMO : 1)) $r[] = [$s, $km, $t];
    }
    return array_slice($r, 0, 6);
}

function web_pct(int $parte, int $total): string { return $total ? (string)round($parte * 100 / $total) . '%' : '0%'; }

/** Junta "A, B e C". */
function web_e(array $itens): string {
    $itens = array_values($itens);
    if (count($itens) <= 1) return $itens[0] ?? '';
    return implode(', ', array_slice($itens, 0, -1)) . ' e ' . end($itens);
}

/** Bloco "Sobre" + mais conhecidos + bairros + vizinhas + perguntas, na página de área ou tipo. */
function web_conteudo_lista(string $cidade, array $area, ?string $tipo): string {
    [$nome_cidade, , $uf] = CIDADES[$cidade];
    $cat = (int)$area['id'];
    $o_que = mb_strtolower($tipo ?? $area['nome']);
    $num = web_numeros_lista($cidade, $cat, $tipo);
    $total = $num['total'] ?? 0;
    if ($total < 1) return '';
    $bairros = web_top_bairros($cidade, $cat, $tipo);
    $conhecidos = web_mais_conhecidos($cidade, $cat, $tipo);
    $vizinhas = web_vizinhas_com($cidade, $cat, $tipo);
    $irmaos = $tipo !== null ? array_values(array_filter(web_tipos($cidade, $cat), fn($t) => $t['nome'] !== $tipo)) : [];

    // texto com os números desta página
    $frases = ['O Acha Fácil reúne <b>' . web_qtd($total, 'lugar', 'lugares') . '</b> de ' . h($o_que) . ' em ' . h("$nome_cidade ($uf)") . '.'];
    $partes = [];
    if ($num['whats']) $partes[] = web_num($num['whats']) . ' atendem pelo WhatsApp';
    if ($num['contato'] > $num['whats']) $partes[] = web_num($num['contato']) . ' têm telefone ou WhatsApp para chamar direto';
    if ($num['online']) $partes[] = web_num($num['online']) . ' têm site ou Instagram';
    if ($partes) $frases[] = 'Desses, ' . web_e($partes) . '.';
    if ($bairros) $frases[] = (count($bairros) > 1 ? 'Os bairros com mais opções são ' : 'O bairro com mais opções é ')
        . web_e(array_map(fn($b) => h($b['bairro']) . ' (' . web_num((int)$b['total']) . ')', array_slice($bairros, 0, 4))) . '.';
    if ($num['em_casa']) $frases[] = web_qtd($num['em_casa'], 'atende', 'atendem') . ' em casa ou a domicílio.';
    if ($num['horario']) $frases[] = web_qtd($num['horario'], 'informou', 'informaram') . ' o horário; use o botão <i>Aberto agora</i> para ver quem está atendendo neste momento.';

    // perguntas com resposta dos dados
    $perguntas = [];
    $perguntas[] = ['Quantos lugares de ' . $o_que . ' há em ' . $nome_cidade . '?',
        'No Acha Fácil há ' . web_qtd($total, 'lugar', 'lugares') . ' de ' . h($o_que) . ' em ' . h($nome_cidade) . ', com endereço e contato.'];
    if ($conhecidos) $perguntas[] = ['Quais lugares de ' . $o_que . ' são mais conhecidos em ' . $nome_cidade . '?',
        'Entre os mais procurados estão ' . web_e(array_map(fn($p) => '<a href="' . h(url_ficha($p)) . '">' . h(nome_curto($p['nome'])) . '</a>', array_slice($conhecidos, 0, 3))) . '.'];
    if ($bairros) $perguntas[] = ['Em que bairro de ' . $nome_cidade . ' tem mais ' . $o_que . '?',
        'O bairro ' . h($bairros[0]['bairro']) . ' tem mais: ' . web_qtd((int)$bairros[0]['total'], 'lugar', 'lugares') . '. Use o filtro de bairro acima da lista para ver só os de um bairro.'];
    $perguntas[] = ['Como falar com um lugar de ' . $o_que . '?',
        'Abra a ficha e toque em WhatsApp, Ligar ou Mapa. ' . ($num['contato'] ? web_pct($num['contato'], $total) . ' dos lugares desta lista têm telefone ou WhatsApp.' : '')];
    if ($vizinhas) $perguntas[] = ['Tem ' . $o_que . ' em cidades perto de ' . $nome_cidade . '?',
        'Sim: ' . web_e(array_map(fn($v) => h(CIDADES[$v[0]][0]) . ' (' . web_num($v[2]) . ', a ' . (int)round($v[1]) . ' km)', array_slice($vizinhas, 0, 3))) . '.'];

    ob_start(); ?>
  <section class="conteudo-extra"><div class="conteudo">
    <h2>Sobre <?= h($o_que) ?> em <?= h($nome_cidade) ?></h2>
    <p class="sobre"><?= implode(' ', $frases) ?></p>
    <?php if ($conhecidos): ?>
      <h3>Mais conhecidos</h3>
      <ol class="conhecidos"><?php foreach ($conhecidos as $p): ?><li><a href="<?= h(url_ficha($p)) ?>"><?= h(nome_curto($p['nome'])) ?></a><?= $p['bairro'] ? ' <small>' . h($p['bairro']) . '</small>' : '' ?></li><?php endforeach ?></ol>
    <?php endif ?>
    <?php if ($irmaos): ?>
      <h3>Veja também em <?= h($nome_cidade) ?></h3>
      <p class="chips"><?php foreach (array_slice($irmaos, 0, 12) as $t): ?><a href="<?= h(url_tipo($cidade, $area['slug'], $t['nome'])) ?>"><?= h($t['nome']) ?> <small><?= (int)$t['total'] ?></small></a><?php endforeach ?></p>
    <?php endif ?>
    <?php if ($vizinhas): ?>
      <h3><?= h($tipo ?? $area['nome']) ?> perto de <?= h($nome_cidade) ?></h3>
      <p class="chips"><?php foreach ($vizinhas as [$s, $km, $t]): ?><a href="<?= h($tipo !== null ? url_tipo($s, $area['slug'], $tipo) : url_area($s, $area['slug'])) ?>"><?= h(CIDADES[$s][0]) ?> <small><?= web_num($t) ?> · <?= (int)round($km) ?> km</small></a><?php endforeach ?></p>
    <?php endif ?>
    <h3>Perguntas frequentes</h3>
    <div class="perguntas"><?php foreach ($perguntas as [$q, $r]): ?><h4><?= h($q) ?></h4><p><?= $r ?></p><?php endforeach ?></div>
  </div></section>
<?php
    return (string)ob_get_clean();
}

/** Bloco da página da cidade: sobre, o que mais tem, bairros, cidades perto e perguntas. */
function web_conteudo_cidade(string $cidade, array $areas): string {
    [$nome, , $uf] = CIDADES[$cidade];
    $num = web_numeros_lista($cidade);
    $total = $num['total'] ?? 0;
    if ($total < 1) return '';
    $top_tipos = web_top_tipos_cidade($cidade);
    $bairros = web_top_bairros($cidade, null, null, 8);
    $perto = web_cidades_perto($cidade, 8);
    $maiores = array_slice(array_values(array_filter($areas, fn($a) => $a['total'] > 0)), 0);
    usort($maiores, fn($a, $b) => $b['total'] <=> $a['total']);
    $maiores = array_slice($maiores, 0, 3);

    $frases = ['<b>' . h($nome) . '</b> (' . h(NOMES_ESTADOS[$uf] ?? $uf) . ') tem <b>' . web_qtd($total, 'comércio e serviço', 'comércios e serviços') . '</b> no Acha Fácil'
        . ($num['bairros'] > 1 ? ', em ' . web_num($num['bairros']) . ' bairros' : '') . '.'];
    if ($maiores) $frases[] = 'As áreas com mais lugares são ' . web_e(array_map(fn($a) => h(mb_strtolower($a['nome'])) . ' (' . web_num((int)$a['total']) . ')', $maiores)) . '.';
    if ($top_tipos) $frases[] = 'O que mais tem na cidade: ' . web_e(array_map(fn($t) => h(mb_strtolower($t['nome'])), array_slice($top_tipos, 0, 4))) . '.';
    if ($num['contato']) $frases[] = web_pct($num['contato'], $total) . ' têm telefone ou WhatsApp para chamar direto' . ($num['online'] ? ' e ' . web_num($num['online']) . ' têm site ou Instagram' : '') . '.';

    $perguntas = [['Quantos comércios há em ' . $nome . ' no Acha Fácil?', web_qtd($total, 'lugar', 'lugares') . ' com endereço e contato, separados por área e tipo de negócio.']];
    if ($bairros) $perguntas[] = ['Quais bairros de ' . $nome . ' têm mais comércios?', web_e(array_map(fn($b) => h($b['bairro']) . ' (' . web_num((int)$b['total']) . ')', array_slice($bairros, 0, 4))) . '.'];
    if ($perto) $perguntas[] = ['Quais cidades ficam perto de ' . $nome . '?', web_e(array_map(fn($s, $km) => h(CIDADES[$s][0]) . ' (' . (int)round($km) . ' km)', array_keys(array_slice($perto, 0, 4, true)), array_slice($perto, 0, 4, true))) . ', em linha reta. Todas têm guia no Acha Fácil.'];
    $perguntas[] = ['Tenho um comércio em ' . $nome . '. Como apareço?', 'Procure o seu negócio na busca acima: se já estiver aqui, toque em "É o dono?". Se não estiver, <a href="' . h(url('incluir.php')) . '">inclua de graça</a>.'];

    ob_start(); ?>
  <section class="conteudo-extra"><div class="conteudo">
    <h2>Sobre o comércio de <?= h($nome) ?></h2>
    <p class="sobre"><?= implode(' ', $frases) ?></p>
    <?php if ($top_tipos): ?>
      <h3>Mais procurados em <?= h($nome) ?></h3>
      <p class="chips"><?php foreach ($top_tipos as $t): ?><a href="<?= h(url_tipo($cidade, $t['area'], $t['nome'])) ?>"><?= h($t['nome']) ?> <small><?= web_num((int)$t['total']) ?></small></a><?php endforeach ?></p>
    <?php endif ?>
    <?php if ($perto): ?>
      <h3>Cidades perto de <?= h($nome) ?></h3>
      <p class="chips"><?php foreach ($perto as $s => $km): ?><a href="<?= h(url_cidade($s)) ?>"><?= h(CIDADES[$s][0] . ' - ' . CIDADES[$s][2]) ?> <small><?= (int)round($km) ?> km</small></a><?php endforeach ?></p>
    <?php endif ?>
    <h3>Perguntas frequentes</h3>
    <div class="perguntas"><?php foreach ($perguntas as [$q, $r]): ?><h4><?= h($q) ?></h4><p><?= $r ?></p><?php endforeach ?></div>
  </div></section>
<?php
    return (string)ob_get_clean();
}
