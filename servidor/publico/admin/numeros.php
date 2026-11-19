<?php
// Números de todas as fichas (passo 20): aparições, visualizações, pessoas e contatos, com filtros e CSV.
// O CSV é a lista de prospecção (passo 22).
require __DIR__ . '/../../inc/admin.php';
$admin = exigir_admin();
exigir_numeros_prontos();

$cidade = isset(TODAS_CIDADES[$_GET['cidade'] ?? '']) ? (string)$_GET['cidade'] : '';
$cat = (int)($_GET['categoria'] ?? 0);
$tipo = trim(mb_substr((string)($_GET['tipo'] ?? ''), 0, 80));
$dias = in_array((int)($_GET['dias'] ?? 30), [1, 7, 30, 90], true) ? (int)($_GET['dias'] ?? 30) : 30;
$plano = in_array($_GET['plano'] ?? '', ['sem', 'basico', 'destaque'], true) ? (string)$_GET['plano'] : '';
$so_vistos = !empty($_GET['vistos']);
const COLUNAS = [
    'aparicoes' => 'Aparições', 'visualizacoes' => 'Visualizações', 'pessoas' => 'Pessoas', 'whatsapp' => 'WhatsApp',
    'ligar' => 'Ligar', 'mapa' => 'Mapa', 'site_t' => 'Site', 'instagram' => 'Instagram', 'contatos' => 'Contatos',
];
$ordem = isset(COLUNAS[$_GET['ordem'] ?? '']) ? (string)$_GET['ordem'] : 'visualizacoes';
$desde = desde_dias($dias);

$plano_sql = "CASE WHEN " . SQL_DESTAQUE . " THEN 'destaque' WHEN " . SQL_EM_DIA . " THEN 'basico' ELSE 'sem' END";
$where = ["p.status = 'ativo'"];
$filtro_params_ini = 2;   // os 2 primeiros $params são as datas da tabela de fichas
$params = [$desde, $desde];
if ($cidade !== '') { $where[] = 'p.cidade = ?'; $params[] = $cidade; }
if ($cat) { $where[] = 'p.categoria_id = ?'; $params[] = $cat; }
if ($tipo !== '') { $where[] = 'p.tipo = ?'; $params[] = $tipo; }
if ($plano !== '') { $where[] = "$plano_sql = ?"; $params[] = $plano; }
if ($so_vistos) $where[] = 'COALESCE(k.visualizacoes, 0) > 0';
// só fichas com algum número no período (16/11, pedido do usuário): as zeradas não aparecem; entram quando tiverem movimento
$where[] = '(COALESCE(a.aparicoes, 0) + COALESCE(k.visualizacoes, 0) + COALESCE(k.contatos, 0)) > 0';

$sql = "SELECT p.id, p.nome, p.tipo, p.cidade, p.bairro, p.whatsapp AS fone_whatsapp, p.telefone, p.site AS tem_site, p.importado,
        c.nome AS categoria, $plano_sql AS plano_atual,
        COALESCE(a.aparicoes, 0) AS aparicoes, COALESCE(k.visualizacoes, 0) AS visualizacoes, COALESCE(k.pessoas, 0) AS pessoas,
        COALESCE(k.whatsapp, 0) AS whatsapp, COALESCE(k.ligar, 0) AS ligar, COALESCE(k.mapa, 0) AS mapa,
        COALESCE(k.site_t, 0) AS site_t, COALESCE(k.instagram, 0) AS instagram, COALESCE(k.contatos, 0) AS contatos
    FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
    LEFT JOIN (SELECT profissional_id,
            SUM(IF(tipo = 'perfil', total, 0)) AS visualizacoes, SUM(IF(tipo = 'perfil', pessoas, 0)) AS pessoas,
            SUM(IF(tipo = 'whatsapp', total, 0)) AS whatsapp, SUM(IF(tipo = 'ligar', total, 0)) AS ligar,
            SUM(IF(tipo = 'mapa', total, 0)) AS mapa, SUM(IF(tipo = 'site', total, 0)) AS site_t,
            SUM(IF(tipo = 'instagram', total, 0)) AS instagram, SUM(IF(tipo NOT IN ('perfil','compartilhar'), total, 0)) AS contatos
        FROM contatos WHERE dia >= ? GROUP BY profissional_id) k ON k.profissional_id = p.id
    LEFT JOIN (SELECT profissional_id, SUM(total) AS aparicoes FROM aparicoes WHERE dia >= ? GROUP BY profissional_id) a ON a.profissional_id = p.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY $ordem DESC, visualizacoes DESC, aparicoes DESC, p.nome";
$lista = linhas($sql, $params);

$nome_plano = ['sem' => 'Sem plano', 'basico' => 'Básico', 'destaque' => 'Destaque'];
$soma = array_fill_keys(array_keys(COLUNAS), 0);
foreach ($lista as $l) foreach ($soma as $k => $_) $soma[$k] += (int)$l[$k];

if (!empty($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="acha-facil-numeros-' . ($cidade ?: 'todas') . '-' . $dias . 'd-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // BOM: o Excel abre com acentos
    fputcsv($out, ['ID', 'Comércio', 'Tipo', 'Área', 'Cidade', 'Bairro', 'Plano', 'WhatsApp do comércio', 'Telefone', 'Tem site', ...array_values(COLUNAS), 'Taxa de contato', 'Ficha no admin'], ';');
    foreach ($lista as $l) {
        fputcsv($out, [$l['id'], $l['nome'], $l['tipo'], $l['categoria'], nome_cidade($l['cidade']), $l['bairro'], $nome_plano[$l['plano_atual']],
            telefone_formatado($l['fone_whatsapp']), telefone_formatado($l['telefone']), $l['tem_site'] ? 'sim' : 'não',
            ...array_map(fn($k) => (int)$l[$k], array_keys(COLUNAS)), pct((int)$l['contatos'], (int)$l['visualizacoes']),
            url_publica('admin/numeros-ficha.php?id=' . $l['id'])], ';');
    }
    exit;
}

$categorias = linhas('SELECT id, nome FROM categorias WHERE ativa = 1 ORDER BY nome');
$tp = ['p.status = \'ativo\''];
$tparams = [];
if ($cidade !== '') { $tp[] = 'p.cidade = ?'; $tparams[] = $cidade; }
if ($cat) { $tp[] = 'p.categoria_id = ?'; $tparams[] = $cat; }
$tipos = linhas('SELECT p.tipo, COUNT(*) AS n FROM profissionais p WHERE p.tipo IS NOT NULL AND ' . implode(' AND ', $tp) . ' GROUP BY p.tipo ORDER BY n DESC, p.tipo LIMIT 150', $tparams);
$link = fn(array $mudar) => '?' . http_build_query(array_filter(array_merge($_GET, $mudar), fn($v) => $v !== '' && $v !== null && $v !== 0));
// paginação (pedido do usuário, 10/11): 15, 50, 100 ou 500 por página
const POR_PAGINA = [15, 50, 100, 500];
$por = in_array((int)($_GET['por'] ?? 15), POR_PAGINA, true) ? (int)($_GET['por'] ?? 15) : 15;
$total = count($lista);
$paginas = max(1, (int)ceil($total / $por));
$pag = min($paginas, max(1, (int)($_GET['pag'] ?? 1)));
$link_pag = fn(int $n) => $link(['pag' => $n > 1 ? $n : null]);
$paginacao = function () use ($pag, $paginas, $total, $por, $link_pag): string {
    if ($paginas < 2) return '';
    $html = '<nav class="paginacao" aria-label="Páginas">';
    $html .= $pag > 1 ? '<a class="botao pequeno contorno" href="' . h($link_pag($pag - 1)) . '">‹ Anterior</a>' : '';
    // primeira, última e 2 de cada lado da atual
    $ultima = 0;
    for ($n = 1; $n <= $paginas; $n++) {
        if ($n !== 1 && $n !== $paginas && abs($n - $pag) > 2) continue;
        if ($ultima && $n - $ultima > 1) $html .= '<span>…</span>';
        $html .= $n === $pag ? '<strong>' . $n . '</strong>' : '<a href="' . h($link_pag($n)) . '">' . $n . '</a>';
        $ultima = $n;
    }
    $html .= $pag < $paginas ? '<a class="botao pequeno contorno" href="' . h($link_pag($pag + 1)) . '">Próxima ›</a>' : '';
    $de = ($pag - 1) * $por + 1;
    return $html . '<span class="discreto">' . num($de) . '–' . num(min($total, $pag * $por)) . ' de ' . num($total) . '</span></nav>';
};

// de onde vêm as pessoas (passo 38): visualizações das fichas por origem e entradas no site
$cw = $cidade !== '' ? ' AND p.cidade = ?' : '';
$cp = $cidade !== '' ? [$desde, $cidade] : [$desde];
$por_origem = array_column(linhas("SELECT k.origem, SUM(k.total) AS total FROM contatos k JOIN profissionais p ON p.id = k.profissional_id
    WHERE k.tipo = 'perfil' AND k.dia >= ?$cw GROUP BY k.origem ORDER BY total DESC", $cp), 'total', 'origem');
$vis_site = array_sum(array_intersect_key($por_origem, array_flip(ORIGENS_SITE)));
$vis_todas = array_sum($por_origem);
$entradas = $entradas_pag = $entradas_google = [];
if (entradas_prontas()) {
    $ew = $cidade !== '' ? ' AND cidade = ?' : '';
    $entradas = array_column(linhas("SELECT origem, SUM(total) AS total FROM entradas WHERE dia >= ?$ew GROUP BY origem ORDER BY total DESC", $cp), 'total', 'origem');
    $entradas_pag = linhas("SELECT pagina, SUM(total) AS total, SUM(IF(origem = 'google', total, 0)) AS google FROM entradas
        WHERE dia >= ?$ew GROUP BY pagina ORDER BY total DESC", $cp);
    $entradas_google = linhas("SELECT caminho, SUM(total) AS total FROM entradas WHERE origem = 'google' AND dia >= ?$ew
        GROUP BY caminho ORDER BY total DESC LIMIT 15", $cp);
}
$entradas_total = array_sum($entradas);
$google_pre = null;   // passo 55: entradas do Google que foram pré-carregamento do Chrome
if ($entradas && pre_carregamento_pronto()) {
    $google_pre = (int)valor("SELECT COUNT(*) FROM entradas_hora WHERE origem = 'google' AND pre = 1 AND quando >= ?" . ($cidade !== '' ? ' AND cidade = ?' : ''),
        array_merge(["$desde 00:00:00"], $cidade !== '' ? [$cidade] : []));
}
// detalhe de uma origem do site (passo 39)
$link_origem = fn(string $o) => in_array($o, ORIGENS_SITE, true)
    ? '<a href="entradas.php?' . h(http_build_query(array_filter(['origem' => $o, 'cidade' => $cidade, 'dias' => $dias]))) . '">' . h(ORIGENS[$o] ?? $o) . '</a>'
    : h(ORIGENS[$o] ?? $o);

admin_inicio('Números', $admin);
?>
<h1>Números das fichas</h1>
<p class="discreto">Todas as fichas, com ou sem plano. <strong>Aparições</strong> = vezes que a ficha foi mostrada numa lista
  (início, categoria, busca, perto); <strong>visualizações</strong> = vezes que foi aberta (quem reabre em menos de 30 min não conta de novo);
  <strong>pessoas</strong> = pessoas diferentes por dia, somadas no período; <strong>contatos</strong> = toques em WhatsApp, ligar, mapa, site e Instagram.
  Origem e pessoas contam desde 10/11/2026 (app 1.5.0); antes disso, cada abertura contava como uma pessoa.</p>

<form method="get" class="filtros-numeros">
  <label>Cidade <select name="cidade"><option value="">Todas</option>
    <?php foreach (TODAS_CIDADES as $slug => $c): ?><option value="<?= h($slug) ?>"<?= $slug === $cidade ? ' selected' : '' ?>><?= h($c[0]) ?></option><?php endforeach ?>
  </select></label>
  <label>Área <select name="categoria" onchange="this.form.tipo.value=''"><option value="">Todas</option>
    <?php foreach ($categorias as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (int)$c['id'] === $cat ? ' selected' : '' ?>><?= h($c['nome']) ?></option><?php endforeach ?>
  </select></label>
  <label>Tipo <select name="tipo"><option value="">Todos</option>
    <?php foreach ($tipos as $t): ?><option value="<?= h($t['tipo']) ?>"<?= $t['tipo'] === $tipo ? ' selected' : '' ?>><?= h($t['tipo']) ?> (<?= (int)$t['n'] ?>)</option><?php endforeach ?>
  </select></label>
  <label>Período <select name="dias">
    <?php foreach ([1, 7, 30, 90] as $d): ?><option value="<?= $d ?>"<?= $d === $dias ? ' selected' : '' ?>><?= $d === 1 ? 'Hoje' : "Últimos $d dias" ?></option><?php endforeach ?>
  </select></label>
  <label>Plano <select name="plano"><option value="">Todos</option>
    <?php foreach ($nome_plano as $k => $n): ?><option value="<?= $k ?>"<?= $k === $plano ? ' selected' : '' ?>><?= $n ?></option><?php endforeach ?>
  </select></label>
  <label class="caixa"><input type="checkbox" name="vistos" value="1"<?= $so_vistos ? ' checked' : '' ?>> Só quem foi visto</label>
  <label>Por página <select name="por" onchange="this.form.submit()">
    <?php foreach (POR_PAGINA as $n): ?><option value="<?= $n ?>"<?= $n === $por ? ' selected' : '' ?>><?= $n ?></option><?php endforeach ?>
  </select></label>
  <input type="hidden" name="ordem" value="<?= h($ordem) ?>">
  <button class="botao pequeno">Filtrar</button>
  <a class="botao pequeno contorno" href="<?= h($link(['csv' => 1])) ?>">Baixar CSV</a>
</form>

<div class="numeros-resumo">
  <div><strong><?= num(count($lista)) ?></strong><span>fichas com movimento</span></div>
  <div><strong><?= num($soma['aparicoes']) ?></strong><span>aparições</span></div>
  <div><strong><?= num($soma['visualizacoes']) ?></strong><span>visualizações</span></div>
  <div><strong><?= num($soma['pessoas']) ?></strong><span>pessoas</span></div>
  <div><strong><?= num($soma['contatos']) ?></strong><span>contatos (<?= pct($soma['contatos'], $soma['visualizacoes']) ?>)</span></div>
</div>

<h2>De onde vêm as pessoas</h2>
<p class="discreto">As palavras que a pessoa pesquisou no Google não chegam ao site (o Google não manda):
  veja em <a href="https://search.google.com/search-console" target="_blank" rel="noopener">Search Console → Desempenho → Consultas</a>.
  Aqui aparece de onde a pessoa chegou; clique numa origem do site para ver as páginas, fichas abertas e toques. Separado desde 14/11/2026; antes disso tudo do site aparece como "Site (sem origem)".</p>
<div class="tabela"><table>
  <tr><th>Visualizações das fichas por origem</th><th>Vezes</th><th>%</th></tr>
  <?php foreach ($por_origem as $o => $t): ?><tr><td><?= $link_origem((string)$o) ?></td><td><?= num((int)$t) ?></td><td><?= pct((int)$t, $vis_todas) ?></td></tr><?php endforeach ?>
  <tr><td><strong>Total pelo site</strong> / pelo app</td><td><strong><?= num($vis_site) ?></strong> / <?= num($vis_todas - $vis_site) ?></td><td><?= pct($vis_site, $vis_todas) ?></td></tr>
</table></div>
<?php if (!entradas_prontas()): ?>
<p class="aviso">As <strong>entradas no site</strong> (por qual página a pessoa chega, inclusive cidade, área e eventos) começam a contar
  depois de aplicar a mudança 022 (bash servidor/instalar.sh, como root).</p>
<?php else: ?>
<div class="numeros-resumo">
  <div><strong><?= num($entradas_total) ?></strong><span>entradas no site</span></div>
  <div><strong><?= num((int)($entradas['google'] ?? 0)) ?></strong><span><a href="entradas.php?<?= h(http_build_query(array_filter(['origem' => 'google', 'cidade' => $cidade, 'dias' => $dias]))) ?>">pelo Google</a> (<?= pct((int)($entradas['google'] ?? 0), $entradas_total) ?>)</span></div>
  <?php if ($google_pre !== null): ?><div title="Tirando o Chrome baixando a página antes do clique"><strong><?= num(max(0, (int)($entradas['google'] ?? 0) - $google_pre)) ?></strong><span>pelo Google sem pré-carregamento</span></div><?php endif ?>
</div>
<p><a class="botao pequeno" href="google.php">Google por cidade</a> <span class="discreto">quanto de cada cidade o Google já leu e quantas pessoas chegaram pela pesquisa</span></p>
<p class="discreto"><strong>Entrada</strong> = pessoa que chegou ao site vindo de fora; quem continua navegando (até 30 min) não conta de novo.</p>
<div class="tabela"><table>
  <tr><th>Entradas por origem</th><th>Vezes</th><th>%</th></tr>
  <?php foreach ($entradas as $o => $t): ?><tr><td><?= $link_origem((string)$o) ?></td><td><?= num((int)$t) ?></td><td><?= pct((int)$t, $entradas_total) ?></td></tr><?php endforeach ?>
</table></div>
<div class="tabela"><table>
  <tr><th>Página onde entrou</th><th>Entradas</th><th>Pelo Google</th></tr>
  <?php foreach ($entradas_pag as $l): ?><tr><td><?= h(PAGINAS_ENTRADA[$l['pagina']] ?? $l['pagina']) ?></td><td><?= num((int)$l['total']) ?></td><td><?= num((int)$l['google']) ?></td></tr><?php endforeach ?>
</table></div>
<?php if ($entradas_google): ?>
<div class="tabela"><table>
  <tr><th>Páginas por onde mais entram pelo Google (<a href="entradas.php?<?= h(http_build_query(array_filter(['origem' => 'google', 'cidade' => $cidade, 'dias' => $dias]))) ?>">ver todas, fichas abertas e toques</a>)</th><th>Entradas</th></tr>
  <?php foreach ($entradas_google as $l): ?><tr><td><a href="<?= h(url_publica(ltrim($l['caminho'], '/'))) ?>" target="_blank" rel="noopener"><?= h($l['caminho']) ?></a></td><td><?= num((int)$l['total']) ?></td></tr><?php endforeach ?>
</table></div>
<?php endif ?>
<?php endif ?>

<h2>Fichas</h2>
<p class="discreto">Só as fichas com algum número no período (apareceu numa lista, foi aberta ou teve contato). As zeradas não aparecem
  e entram sozinhas quando tiverem movimento.</p>
<?= $paginacao() ?>
<div class="tabela"><table>
  <tr><th>Comércio</th><th>Plano</th>
    <?php foreach (COLUNAS as $k => $n): ?><th><a href="<?= h($link(['ordem' => $k, 'pag' => null])) ?>"><?= $n ?><?= $k === $ordem ? ' ▼' : '' ?></a></th><?php endforeach ?>
    <th>Taxa</th><th></th></tr>
  <?php foreach (array_slice($lista, ($pag - 1) * $por, $por) as $l): ?>
  <tr>
    <td><strong><?= adm_comercio((int)$l['id'], $l['nome'], $l['cidade']) ?></strong><br><span class="discreto"><?= h($l['tipo'] ?: $l['categoria']) ?> · <?= adm_cidade($l['cidade'], null, true) ?><?= $l['bairro'] ? ' · ' . h($l['bairro']) : '' ?></span></td>
    <td><?= $nome_plano[$l['plano_atual']] ?></td>
    <?php foreach (COLUNAS as $k => $n): ?><td><?= num((int)$l[$k]) ?></td><?php endforeach ?>
    <td><?= pct((int)$l['contatos'], (int)$l['visualizacoes']) ?></td>
    <td><a class="botao pequeno contorno" href="numeros-ficha.php?id=<?= (int)$l['id'] ?>&amp;dias=<?= $dias ?>">Ver</a></td>
  </tr>
  <?php endforeach ?>
</table></div>
<?= $paginacao() ?>
<?php admin_fim();
