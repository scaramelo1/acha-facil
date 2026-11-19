<?php
// Detalhe de uma origem do site (passo 39): quem veio do Google (ou de outra origem) entrou por quais páginas,
// abriu quais fichas e tocou no WhatsApp/ligar/mapa de quais comércios. Só totais por dia, nada por pessoa.
require __DIR__ . '/../../inc/admin.php';
$admin = exigir_admin();
exigir_numeros_prontos();

$origem = in_array($_GET['origem'] ?? '', ORIGENS_SITE, true) ? (string)$_GET['origem'] : 'google';
$cidade = isset(TODAS_CIDADES[$_GET['cidade'] ?? '']) ? (string)$_GET['cidade'] : '';
$dias = in_array((int)($_GET['dias'] ?? 30), [1, 7, 30, 90], true) ? (int)($_GET['dias'] ?? 30) : 30;
$desde = desde_dias($dias);
$link = fn(array $mudar) => '?' . http_build_query(array_filter(array_merge(['origem' => $origem, 'cidade' => $cidade, 'dias' => $dias], $mudar), fn($v) => $v !== '' && $v !== null));

// 1. páginas onde entraram
$paginas = [];
if (entradas_prontas()) {
    $w = $cidade !== '' ? ' AND cidade = ?' : '';
    $paginas = linhas("SELECT caminho, pagina, cidade, SUM(total) AS total FROM entradas WHERE origem = ? AND dia >= ?$w
        GROUP BY caminho, pagina, cidade ORDER BY total DESC, caminho LIMIT 500", array_merge([$origem, $desde], $cidade !== '' ? [$cidade] : []));
}
$total_entradas = array_sum(array_map(fn($l) => (int)$l['total'], $paginas));
// pré-carregamento do Chrome (passo 55, mudança 027): quantas dessas entradas foram o Chrome baixando antes do clique
$pre = null;
if ($paginas && pre_carregamento_pronto()) {
    $wp = $cidade !== '' ? ' AND cidade = ?' : '';
    $pre = (int)valor("SELECT COUNT(*) FROM entradas_hora WHERE origem = ? AND quando >= ? AND pre = 1$wp",
        array_merge([$origem, "$desde 00:00:00"], $cidade !== '' ? [$cidade] : []));
    // 16/11: pré-carregadas que a pessoa abriu de verdade (pre = 2) contam como gente; pre = 1 = só baixada
    $pre_vistas = (int)valor("SELECT COUNT(*) FROM entradas_hora WHERE origem = ? AND quando >= ? AND pre = 2$wp",
        array_merge([$origem, "$desde 00:00:00"], $cidade !== '' ? [$cidade] : []));
}
// quando foi (passo 42): data e hora de cada entrada (mudança 023); antes dela, ou sem hora, só o dia
$quando = $ultimo = [];   // $ultimo[caminho] = data/hora mais recente, para ordenar
if ($paginas) {
    $cw = $cidade !== '' ? ' AND cidade = ?' : '';
    $com_hora = [];
    try {
        foreach (linhas("SELECT caminho, quando FROM entradas_hora WHERE origem = ? AND quando >= ?$cw ORDER BY quando DESC LIMIT 5000",
                array_merge([$origem, "$desde 00:00:00"], $cidade !== '' ? [$cidade] : [])) as $l) {
            $quando[$l['caminho']][] = date('d/m H:i', strtotime($l['quando']));
            $ultimo[$l['caminho']] ??= $l['quando'];
            $com_hora[$l['caminho']][substr($l['quando'], 0, 10)] = ($com_hora[$l['caminho']][substr($l['quando'], 0, 10)] ?? 0) + 1;
        }
    } catch (PDOException) {}
    // entradas sem hora (antes da 023): mostra o dia
    foreach (linhas("SELECT caminho, dia, SUM(total) AS total FROM entradas WHERE origem = ? AND dia >= ?$cw GROUP BY caminho, dia ORDER BY dia DESC",
            array_merge([$origem, $desde], $cidade !== '' ? [$cidade] : [])) as $l) {
        $falta = (int)$l['total'] - ($com_hora[$l['caminho']][$l['dia']] ?? 0);
        if ($falta > 0) $ultimo[$l['caminho']] = max($ultimo[$l['caminho']] ?? '', $l['dia'] . ' 00:00:00');
        if ($falta > 0) $quando[$l['caminho']][] = date('d/m', strtotime($l['dia'])) . ($falta > 1 ? " ({$falta}x)" : '') . ' <span class="discreto">sem hora</span>';
    }
}
$ordem = ($_GET['ordem'] ?? '') === 'total' ? 'total' : 'recentes';
if ($ordem === 'recentes') {
    usort($paginas, fn($a, $b) => strcmp($ultimo[$b['caminho']] ?? '', $ultimo[$a['caminho']] ?? '') ?: (int)$b['total'] <=> (int)$a['total']);
}

// 2 e 3. fichas abertas e toques de quem veio dessa origem
$w = $cidade !== '' ? ' AND p.cidade = ?' : '';
$fichas = linhas("SELECT p.id, p.nome, p.tipo, p.cidade, p.bairro, c.nome AS categoria,
        SUM(IF(k.tipo = 'perfil', k.total, 0)) AS visualizacoes, SUM(IF(k.tipo = 'perfil', k.pessoas, 0)) AS pessoas,
        SUM(IF(k.tipo = 'whatsapp', k.total, 0)) AS whatsapp, SUM(IF(k.tipo = 'ligar', k.total, 0)) AS ligar,
        SUM(IF(k.tipo = 'mapa', k.total, 0)) AS mapa, SUM(IF(k.tipo = 'site', k.total, 0)) AS site_t,
        SUM(IF(k.tipo = 'instagram', k.total, 0)) AS instagram, SUM(IF(k.tipo NOT IN ('perfil','compartilhar'), k.total, 0)) AS toques
    FROM contatos k JOIN profissionais p ON p.id = k.profissional_id JOIN categorias c ON c.id = p.categoria_id
    WHERE k.origem = ? AND k.dia >= ?$w
    GROUP BY p.id ORDER BY visualizacoes DESC, toques DESC, p.nome LIMIT 500", array_merge([$origem, $desde], $cidade !== '' ? [$cidade] : []));
$abertas = array_values(array_filter($fichas, fn($l) => (int)$l['visualizacoes'] > 0));
$tocadas = array_values(array_filter($fichas, fn($l) => (int)$l['toques'] > 0));
usort($tocadas, fn($a, $b) => (int)$b['toques'] <=> (int)$a['toques'] ?: strcmp($a['nome'], $b['nome']));
$soma = fn(array $l, string $k) => array_sum(array_map(fn($x) => (int)$x[$k], $l));
$comercio = fn(array $l) => '<strong>' . adm_comercio((int)$l['id'], $l['nome'], $l['cidade']) . '</strong><br><span class="discreto">' . h($l['tipo'] ?: $l['categoria'])
    . ' · ' . adm_cidade($l['cidade'], null, true) . ($l['bairro'] ? ' · ' . h($l['bairro']) : '') . '</span>';
$ver = fn(array $l) => '<a class="botao pequeno contorno" href="numeros-ficha.php?id=' . (int)$l['id'] . '&amp;dias=' . $dias . '">Ver</a>'
    . ' <a class="botao pequeno contorno" href="' . h(url_ficha($l)) . '" target="_blank" rel="noopener">Abrir</a>';

// comparação com o período anterior (passo 71, pedido do usuário): "Hoje" x ontem, 7 dias x os 7 anteriores, etc.
// Os mesmos 5 números dos quadros, contados por dia, para um intervalo qualquer (dia inicial e final inclusive).
const ENTRADAS_DESDE = '2026-11-11';   // entradas tiradas do registro do servidor a partir daqui
const ORIGEM_FICHAS_DESDE = '2026-11-14';   // visualizações e toques separados por origem desde aqui
$totais_por_dia = function (string $ini, string $fim) use ($origem, $cidade): array {
    $dias_lista = [];
    for ($d = $ini; $d <= $fim; $d = date('Y-m-d', strtotime("$d +1 day")))
        $dias_lista[$d] = ['entradas' => 0, 'gente' => 0, 'fichas' => 0, 'vis' => 0, 'toques' => 0, 'tocados' => 0];
    $wc = $cidade !== '' ? ' AND cidade = ?' : '';
    $pc = $cidade !== '' ? [$cidade] : [];
    if (entradas_prontas()) {
        foreach (linhas("SELECT dia, SUM(total) AS t FROM entradas WHERE origem = ? AND dia BETWEEN ? AND ?$wc GROUP BY dia",
                array_merge([$origem, $ini, $fim], $pc)) as $l) {
            $dias_lista[$l['dia']]['entradas'] = $dias_lista[$l['dia']]['gente'] = (int)$l['t'];
        }
        if (pre_carregamento_pronto()) {
            foreach (linhas("SELECT DATE(quando) AS dia, COUNT(*) AS t FROM entradas_hora WHERE origem = ? AND quando BETWEEN ? AND ? AND pre = 1$wc GROUP BY DATE(quando)",
                    array_merge([$origem, "$ini 00:00:00", "$fim 23:59:59"], $pc)) as $l) {
                if (isset($dias_lista[$l['dia']])) $dias_lista[$l['dia']]['gente'] = max(0, $dias_lista[$l['dia']]['gente'] - (int)$l['t']);
            }
        }
    }
    $wp = $cidade !== '' ? ' AND p.cidade = ?' : '';
    foreach (linhas("SELECT k.dia, COUNT(DISTINCT IF(k.tipo = 'perfil' AND k.total > 0, k.profissional_id, NULL)) AS fichas,
            SUM(IF(k.tipo = 'perfil', k.total, 0)) AS vis, SUM(IF(k.tipo NOT IN ('perfil','compartilhar'), k.total, 0)) AS toques,
            COUNT(DISTINCT IF(k.tipo NOT IN ('perfil','compartilhar') AND k.total > 0, k.profissional_id, NULL)) AS tocados
        FROM contatos k JOIN profissionais p ON p.id = k.profissional_id
        WHERE k.origem = ? AND k.dia BETWEEN ? AND ?$wp GROUP BY k.dia", array_merge([$origem, $ini, $fim], $pc)) as $l) {
        foreach (['fichas', 'vis', 'toques', 'tocados'] as $k) $dias_lista[$l['dia']][$k] = (int)$l[$k];
    }
    return $dias_lista;
};
$somar = function (array $dl): array {
    $s = ['entradas' => 0, 'gente' => 0, 'fichas' => 0, 'vis' => 0, 'toques' => 0, 'tocados' => 0];
    foreach ($dl as $d) foreach ($s as $k => $_) $s[$k] += $d[$k];
    return $s;
};
$hoje_d = hoje();
$ant_fim = date('Y-m-d', strtotime("$desde -1 day"));
$ant_ini = date('Y-m-d', strtotime("$desde -$dias days"));
$agora_t = $somar($totais_por_dia($desde, $hoje_d));
$antes_t = $somar($totais_por_dia($ant_ini, $ant_fim));
// fichas abertas e comércios tocados no período = comércios DIFERENTES (não a soma dos dias)
$diferentes = function (string $ini, string $fim) use ($origem, $cidade): array {
    $wp = $cidade !== '' ? ' AND p.cidade = ?' : '';
    $l = linhas("SELECT COUNT(DISTINCT IF(k.tipo = 'perfil' AND k.total > 0, k.profissional_id, NULL)) AS fichas,
            COUNT(DISTINCT IF(k.tipo NOT IN ('perfil','compartilhar') AND k.total > 0, k.profissional_id, NULL)) AS tocados
        FROM contatos k JOIN profissionais p ON p.id = k.profissional_id WHERE k.origem = ? AND k.dia BETWEEN ? AND ?$wp",
        array_merge([$origem, $ini, $fim], $cidade !== '' ? [$cidade] : []))[0] ?? [];
    return ['fichas' => (int)($l['fichas'] ?? 0), 'tocados' => (int)($l['tocados'] ?? 0)];
};
$agora_t = array_merge($agora_t, $diferentes($desde, $hoje_d));
$antes_t = array_merge($antes_t, $diferentes($ant_ini, $ant_fim));
$nome_antes = $dias === 1 ? 'ontem' : "os $dias dias anteriores";
/** "+8 desde ontem" (verde), "−4 ..." (vermelho), "igual a ontem"; sem comparação se o período anterior é de antes dos dados. */
$diferenca = function (string $k) use ($agora_t, $antes_t, $ant_ini, $ant_fim, $dias, $nome_antes): string {
    $inicio_dados = in_array($k, ['entradas', 'gente'], true) ? ENTRADAS_DESDE : ORIGEM_FICHAS_DESDE;
    if ($ant_fim < $inicio_dados) return '<small class="dif sem">sem dados ' . ($dias === 1 ? 'de ontem' : "dos $dias dias anteriores") . ' para comparar</small>';
    $d = $agora_t[$k] - $antes_t[$k];
    $base = num($antes_t[$k]);
    $parcial = $ant_ini < $inicio_dados ? '*' : '';
    if ($d === 0) return '<small class="dif igual">igual ' . ($dias === 1 ? 'a ontem' : 'aos ' . $dias . ' dias anteriores') . " ($base)$parcial</small>";
    return '<small class="dif ' . ($d > 0 ? 'sobe' : 'desce') . '">' . ($d > 0 ? '+' : '−') . num(abs($d))
        . ($dias === 1 ? ' desde ontem' : ' x ' . h($nome_antes)) . " ($base)$parcial</small>";
};
$tem_parcial = $ant_ini < ORIGEM_FICHAS_DESDE && $ant_fim >= ENTRADAS_DESDE;
// dia a dia (19/11, pedido do usuário): uma semana por vez, de segunda a domingo, com paginação (?semana=<segunda>);
// cada dia abre o relatório completo do dia (entradas-dia.php). Diferença sempre para o dia anterior.
$seg_atual = date('Y-m-d', strtotime('monday this week', strtotime($hoje_d)));
$seg_primeira = date('Y-m-d', strtotime('monday this week', strtotime(ENTRADAS_DESDE)));
$semana = (string)($_GET['semana'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $semana) || $semana > $seg_atual) $semana = $seg_atual;
$semana = max($seg_primeira, date('Y-m-d', strtotime('monday this week', strtotime($semana))));
$sem_fim = date('Y-m-d', strtotime("$semana +6 days"));
$dia_a_dia = $totais_por_dia(date('Y-m-d', strtotime("$semana -1 day")), $sem_fim);
$sem_ant = $semana > $seg_primeira ? date('Y-m-d', strtotime("$semana -7 days")) : null;
$sem_prox = $semana < $seg_atual ? date('Y-m-d', strtotime("$semana +7 days")) : null;

// relatório (passo 43): cidades, áreas, comércios, dias e horários mais procurados por quem veio desta origem
$rel = ['cidades' => [], 'areas' => [], 'comercios' => [], 'dias' => [], 'horas' => array_fill(0, 24, 0), 'horas_antes' => array_fill(0, 24, 0)];
if (entradas_prontas()) {
    $pe = array_merge([$origem, $desde], $cidade !== '' ? [$cidade] : []);
    $we = $cidade !== '' ? ' AND cidade = ?' : '';
    $cam = linhas("SELECT caminho, pagina, cidade, SUM(total) AS total FROM entradas WHERE origem = ? AND dia >= ?$we GROUP BY caminho, pagina, cidade", $pe);
    // área de cada entrada: ficha -> área do comércio; lista -> área do endereço
    $ids = [];
    foreach ($cam as $l) if ($l['pagina'] === 'ficha' && preg_match('/-(\d{1,9})$/', $l['caminho'], $m)) $ids[(int)$m[1]] = true;
    $info = [];
    foreach (array_chunk(array_keys($ids), 500) as $lote)
        foreach (linhas('SELECT p.id, p.nome, p.tipo, p.cidade, c.nome AS categoria FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE p.id IN ('
            . implode(',', array_fill(0, count($lote), '?')) . ')', $lote) as $p) $info[(int)$p['id']] = $p;
    $area_slug = array_column(linhas('SELECT slug, nome FROM categorias'), 'nome', 'slug');
    foreach ($cam as $l) {
        $t = (int)$l['total'];
        $c = $l['cidade'] ?? '';
        $rel['cidades'][$c] ??= ['entradas' => 0, 'fichas' => 0, 'areas' => [], 'visualizacoes' => 0, 'pessoas' => 0, 'toques' => 0];
        $rel['cidades'][$c]['entradas'] += $t;
        $area = null;
        if ($l['pagina'] === 'ficha' && preg_match('/-(\d{1,9})$/', $l['caminho'], $m) && isset($info[(int)$m[1]])) {
            $p = $info[(int)$m[1]];
            $area = $p['categoria'];
            $rel['cidades'][$c]['fichas']++;
            $rel['comercios'][$p['id']] = ($rel['comercios'][$p['id']] ?? ['p' => $p, 'total' => 0, 'caminho' => $l['caminho']]);
            $rel['comercios'][$p['id']]['total'] += $t;
        } elseif ($l['pagina'] === 'lista' && preg_match('#^/[^/]+/([^/]+)/#', $l['caminho'], $m)) {
            $area = $area_slug[$m[1]] ?? null;
        }
        if ($area !== null) {
            $rel['areas'][$area] = ($rel['areas'][$area] ?? 0) + $t;
            $rel['cidades'][$c]['areas'][$area] = ($rel['cidades'][$c]['areas'][$area] ?? 0) + $t;
        }
    }
    // visualizações e toques por cidade (contados desde 14/11)
    $wc = $cidade !== '' ? ' AND p.cidade = ?' : '';
    foreach (linhas("SELECT p.cidade, SUM(IF(k.tipo = 'perfil', k.total, 0)) AS v, SUM(IF(k.tipo = 'perfil', k.pessoas, 0)) AS pe,
            SUM(IF(k.tipo NOT IN ('perfil','compartilhar'), k.total, 0)) AS t FROM contatos k JOIN profissionais p ON p.id = k.profissional_id
            WHERE k.origem = ? AND k.dia >= ?$wc GROUP BY p.cidade", $pe) as $l) {
        $rel['cidades'][$l['cidade']] ??= ['entradas' => 0, 'fichas' => 0, 'areas' => [], 'visualizacoes' => 0, 'pessoas' => 0, 'toques' => 0];
        $rel['cidades'][$l['cidade']]['visualizacoes'] = (int)$l['v'];
        $rel['cidades'][$l['cidade']]['pessoas'] = (int)$l['pe'];
        $rel['cidades'][$l['cidade']]['toques'] = (int)$l['t'];
    }
    uasort($rel['cidades'], fn($a, $b) => $b['entradas'] <=> $a['entradas'] ?: $b['visualizacoes'] <=> $a['visualizacoes']);
    arsort($rel['areas']);
    uasort($rel['comercios'], fn($a, $b) => $b['total'] <=> $a['total']);
    for ($d = $desde; $d <= hoje(); $d = date('Y-m-d', strtotime("$d +1 day"))) $rel['dias'][$d] = 0;
    foreach (linhas("SELECT dia, SUM(total) AS t FROM entradas WHERE origem = ? AND dia >= ?$we GROUP BY dia", $pe) as $l) $rel['dias'][$l['dia']] = (int)$l['t'];
    try {
        foreach (linhas("SELECT HOUR(quando) AS h, COUNT(*) AS t FROM entradas_hora WHERE origem = ? AND quando >= ?$we GROUP BY h",
                array_merge([$origem, "$desde 00:00:00"], $cidade !== '' ? [$cidade] : [])) as $l) $rel['horas'][(int)$l['h']] = (int)$l['t'];
        // 17/11 (pedido do usuário): mesmas horas no período anterior (ontem inteiro; N dias anteriores), barra verde ao lado
        foreach (linhas("SELECT HOUR(quando) AS h, COUNT(*) AS t FROM entradas_hora WHERE origem = ? AND quando BETWEEN ? AND ?$we GROUP BY h",
                array_merge([$origem, "$ant_ini 00:00:00", "$ant_fim 23:59:59"], $cidade !== '' ? [$cidade] : [])) as $l) $rel['horas_antes'][(int)$l['h']] = (int)$l['t'];
    } catch (PDOException) {}
}
$rel_total = array_sum(array_column($rel['cidades'], 'entradas'));
$maior_dia = max([1, ...array_values($rel['dias'])]);
$maior_hora = max([1, ...$rel['horas'], ...$rel['horas_antes']]);
const HORAS_DESDE = '2026-11-14';   // hora de cada entrada gravada desde aqui (mudança 023)

// paginação das listas (17/11, pedido do usuário): 15 por página, cada lista com a sua página (?pg_<lista>=)
const ENTRADAS_POR_PAGINA = 15;
/** [itens da página, html da navegação, posição do 1º]. */
$paginar = function (array $lista, string $nome) : array {
    $total = count($lista);
    $paginas = max(1, (int)ceil($total / ENTRADAS_POR_PAGINA));
    $pag = min($paginas, max(1, (int)($_GET["pg_$nome"] ?? 1)));
    $ini = ($pag - 1) * ENTRADAS_POR_PAGINA;
    $itens = array_slice($lista, $ini, ENTRADAS_POR_PAGINA, true);
    if ($paginas < 2) return [$itens, '', $ini];
    $ir = fn(int $n) => '?' . h(http_build_query(array_filter(array_merge($_GET, ["pg_$nome" => $n > 1 ? $n : null]), fn($v) => $v !== '' && $v !== null))) . "#lista-$nome";
    $html = '<nav class="paginacao" aria-label="Páginas">' . ($pag > 1 ? '<a class="botao pequeno contorno" href="' . $ir($pag - 1) . '">‹ Anterior</a>' : '');
    $ultima = 0;
    for ($n = 1; $n <= $paginas; $n++) {   // primeira, última e 2 de cada lado da atual
        if ($n !== 1 && $n !== $paginas && abs($n - $pag) > 2) continue;
        if ($ultima && $n - $ultima > 1) $html .= '<span>…</span>';
        $html .= $n === $pag ? '<strong>' . $n . '</strong>' : '<a href="' . $ir($n) . '">' . $n . '</a>';
        $ultima = $n;
    }
    $html .= ($pag < $paginas ? '<a class="botao pequeno contorno" href="' . $ir($pag + 1) . '">Próxima ›</a>' : '')
        . '<span class="discreto">' . num($ini + 1) . '–' . num(min($total, $ini + ENTRADAS_POR_PAGINA)) . ' de ' . num($total) . '</span></nav>';
    return [$itens, $html, $ini];
};

admin_inicio(ORIGENS[$origem], $admin);
?>
<p><a href="numeros.php?<?= h(http_build_query(array_filter(['cidade' => $cidade, 'dias' => $dias]))) ?>">‹ Números</a></p>
<h1><?= h(ORIGENS[$origem]) ?></h1>
<p class="discreto">O que fizeram no site as pessoas que chegaram por esta origem. São totais por dia: não dá para seguir uma pessoa
  (o site não guarda isso). As palavras pesquisadas no Google ficam só no
  <a href="https://search.google.com/search-console" target="_blank" rel="noopener">Search Console</a>. Separado desde 14/11/2026.</p>

<form method="get" class="filtros-numeros">
  <label>Origem <select name="origem">
    <?php foreach (ORIGENS_SITE as $o): ?><option value="<?= $o ?>"<?= $o === $origem ? ' selected' : '' ?>><?= h(ORIGENS[$o]) ?></option><?php endforeach ?>
  </select></label>
  <label>Cidade <select name="cidade"><option value="">Todas</option>
    <?php foreach (TODAS_CIDADES as $slug => $c): ?><option value="<?= h($slug) ?>"<?= $slug === $cidade ? ' selected' : '' ?>><?= h($c[0]) ?></option><?php endforeach ?>
  </select></label>
  <label>Período <select name="dias">
    <?php foreach ([1, 7, 30, 90] as $d): ?><option value="<?= $d ?>"<?= $d === $dias ? ' selected' : '' ?>><?= $d === 1 ? 'Hoje' : "Últimos $d dias" ?></option><?php endforeach ?>
  </select></label>
  <button class="botao pequeno">Filtrar</button>
</form>

<?php if ($pre !== null): ?><p class="discreto"><strong>Pré-carregamento:</strong> quando o site aparece bem colocado no Google, o Chrome baixa a
  página antes do clique. <?= num($pre) ?> de <?= num($total_entradas) ?> entradas foram só baixadas e ninguém abriu<?= $pre_vistas ? '; ' . num($pre_vistas) . ' foram pré-carregadas e a pessoa abriu' : '' ?>.
  Desde 16/11 a ficha pré-carregada só conta como aberta (e nas visualizações) quando a pessoa abre de verdade; antes disso
  os downloads entravam também. Os <strong>toques</strong> são sempre de gente de verdade.</p><?php endif ?>
<div class="numeros-resumo">
  <?php if (entradas_prontas()): ?><div><strong><?= num($agora_t['entradas']) ?></strong><span>entradas no site</span><?= $diferenca('entradas') ?></div><?php endif ?>
  <?php if ($pre !== null || pre_carregamento_pronto()): ?><div title="Cliques normais + pré-carregadas que a pessoa abriu (desde 16/11). Tira só o que o Chrome baixou e ninguém viu.">
    <strong><?= num($agora_t['gente']) ?></strong><span>entradas de pessoas (sem pré-carregamento)</span><?= $diferenca('gente') ?></div><?php endif ?>
  <div><strong><?= num($agora_t['fichas']) ?></strong><span>fichas abertas</span><?= $diferenca('fichas') ?></div>
  <div><strong><?= num($agora_t['vis']) ?></strong><span>visualizações</span><?= $diferenca('vis') ?></div>
  <div><strong><?= num($agora_t['toques']) ?></strong><span>toques em <?= num($agora_t['tocados']) ?> comércios</span><?= $diferenca('toques') ?></div>
</div>
<p class="discreto">Entre parênteses, o número de <?= h($nome_antes) ?><?= $dias === 1 ? ' (o dia inteiro; hoje ainda não acabou)' : '' ?>.
  <?= $tem_parcial ? '* O período anterior começa antes de os dados existirem (entradas desde 11/11, fichas e toques por origem desde 14/11): a comparação fica a favor de agora.' : '' ?>
  Os dias 15 e 16/11 têm robôs do AdSense contados como gente (separados só a partir de 16/11).</p>

<h2 id="dia-a-dia">Dia a dia</h2>
<div class="paginacao-semana">
  <?php if ($sem_ant): ?><a class="botao pequeno contorno" href="<?= h($link(['semana' => $sem_ant, 'ordem' => $_GET['ordem'] ?? ''])) ?>#dia-a-dia">← Semana anterior</a><?php else: ?><span></span><?php endif ?>
  <strong>Semana de <?= date('d/m', strtotime($semana)) ?> a <?= date('d/m', strtotime($sem_fim)) ?><?= $semana === $seg_atual ? ' · esta semana' : '' ?></strong>
  <?php if ($sem_prox): ?><a class="botao pequeno contorno" href="<?= h($link(['semana' => $sem_prox === $seg_atual ? '' : $sem_prox, 'ordem' => $_GET['ordem'] ?? ''])) ?>#dia-a-dia">Próxima semana →</a><?php else: ?><span></span><?php endif ?>
</div>
<div class="tabela"><table class="dia-a-dia">
  <tr><th>Dia</th><th>Entradas</th><th>De pessoas</th><th>Fichas abertas</th><th>Visualizações</th><th>Toques</th></tr>
  <?php $dds = array_keys($dia_a_dia); $soma_sem = ['entradas' => 0, 'gente' => 0, 'fichas' => 0, 'vis' => 0, 'toques' => 0];
  // 19/11 (pedido do usuário): mais novo em cima (hoje primeiro); dias que ainda não chegaram e antes dos dados não aparecem
  for ($i = count($dds) - 1; $i >= 1; $i--): $d = $dds[$i]; $v = $dia_a_dia[$d]; $a = $dia_a_dia[$dds[$i - 1]];
    $nome_dia = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'][(int)date('w', strtotime($d))];
    if ($d > $hoje_d || $d < ENTRADAS_DESDE) continue;
    foreach ($soma_sem as $k => $_) $soma_sem[$k] += $v[$k]; ?><tr<?= $d === $hoje_d ? ' class="dia-hoje"' : '' ?>>
    <td><a href="entradas-dia.php?<?= h(http_build_query(array_filter(['dia' => $d, 'origem' => $origem, 'cidade' => $cidade]))) ?>" title="Relatório completo do dia"><strong><?= date('d/m', strtotime($d)) ?></strong> <?= $nome_dia ?></a><?= $d === $hoje_d ? ' <span class="discreto">· hoje</span>' : '' ?></td>
    <?php foreach (['entradas', 'gente', 'fichas', 'vis', 'toques'] as $k):
        $desde_k = in_array($k, ['entradas', 'gente'], true) ? ENTRADAS_DESDE : ORIGEM_FICHAS_DESDE;
        $dif = $v[$k] - $a[$k]; ?>
    <td><?= num($v[$k]) ?><?php if ($dds[$i - 1] >= $desde_k): ?> <small class="dif <?= $dif > 0 ? 'sobe' : ($dif < 0 ? 'desce' : 'igual') ?>"><?= $dif > 0 ? '+' . num($dif) : ($dif < 0 ? '−' . num(-$dif) : '=') ?></small><?php endif ?></td>
    <?php endforeach ?>
  </tr><?php endfor ?>
  <tr class="total-semana"><td><strong>Semana</strong></td><?php foreach ($soma_sem as $k => $n): ?><td><strong><?= num($n) ?></strong><?= $k === 'fichas' ? ' <small class="discreto">(soma dos dias)</small>' : '' ?></td><?php endforeach ?></tr>
</table></div>
<p class="discreto">Toque no dia para ver o relatório completo dele. Ao lado de cada número, a diferença para o dia anterior. Hoje ainda não acabou.</p>
<style>
  .numeros-resumo .dif { display: block; margin-top: 4px; font-size: .8rem; font-weight: 700; }
  .dif.sobe { color: #12803c; } .dif.desce { color: #c0392b; } .dif.igual, .dif.sem { color: var(--texto2); font-weight: 400; }
  .dia-a-dia .dif { font-size: .78rem; }
  .paginacao-semana { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin: 0 0 12px; }
  .dia-a-dia tr.dia-hoje td { background: var(--amarelo-claro); }
  .dia-a-dia tr.total-semana td { border-top: 2px solid var(--verde); }
</style>

<?php if ($rel_total): ?>
<h2>Relatório: cidades mais procuradas</h2>
<p class="discreto">Cada entrada é uma pessoa chegando ao site por esta origem. Pelo Google, quase sempre é alguém que pesquisou o nome
  de um comércio (ou "tipo + cidade") e caiu na ficha. Inclui as entradas de 11/11 a 14/11 tiradas do registro do servidor.
  <strong>Atenção:</strong> uma parte é o Chrome carregando a página antes do clique (quando o site aparece bem colocado),
  então leia como "vezes que o site apareceu e foi aberto ou pré-carregado". <a href="#" onclick="print();return false">Imprimir</a></p>
<div class="tabela"><table>
  <tr><th>#</th><th>Cidade</th><th>Entradas</th><th>%</th><th>Comércios diferentes</th><th>Áreas mais procuradas</th><th>Visualizações*</th><th>Toques*</th></tr>
  <?php $i = 0; foreach ($rel['cidades'] as $c => $r): if (!$r['entradas'] && !$r['visualizacoes']) continue; $i++; arsort($r['areas']); ?><tr>
    <td><?= $i ?></td>
    <td><strong><?= $c !== '' ? adm_cidade((string)$c) : 'Sem cidade (página inicial, busca)' ?></strong></td>
    <td><strong><?= num($r['entradas']) ?></strong></td><td><?= pct($r['entradas'], $rel_total) ?></td>
    <td><?= num($r['fichas']) ?></td>
    <td><?= h(implode(' · ', array_map(fn($a, $n) => "$a $n", array_keys(array_slice($r['areas'], 0, 3, true)), array_slice($r['areas'], 0, 3, true)))) ?: '—' ?></td>
    <td><?= num($r['visualizacoes']) ?></td><td><?= num($r['toques']) ?></td>
  </tr><?php endforeach ?>
</table></div>
<p class="discreto">* Visualizações e toques por origem contam desde 14/11/2026 (o registro do servidor não mostra quem abriu a ficha de novo nem os toques).</p>

<?php if (array_sum($rel['horas']) || array_sum($rel['horas_antes'])):
  $rot_agora = $dias === 1 ? 'Hoje' : "Últimos $dias dias";
  $rot_antes = $dias === 1 ? 'Ontem' : "$dias dias anteriores"; ?>
<h3>Horários de chegada</h3>
<p class="discreto"><span class="barra-legenda vistas"></span> <?= h($rot_antes) ?> (<?= num(array_sum($rel['horas_antes'])) ?>)
  · <span class="barra-legenda aparicoes"></span> <?= h($rot_agora) ?> (<?= num(array_sum($rel['horas'])) ?>)</p>
<div class="barras-dias" style="gap:clamp(3px, 1vw, 10px)" role="img" aria-label="Entradas por hora do dia, <?= h(mb_strtolower($rot_agora)) ?> x <?= h(mb_strtolower($rot_antes)) ?>">
  <?php foreach ($rel['horas'] as $h => $n): $a = $rel['horas_antes'][$h]; ?><div class="dia" title="<?= $h ?>h: <?= h(mb_strtolower($rot_antes)) ?> <?= $a ?>, <?= h(mb_strtolower($rot_agora)) ?> <?= $n ?>"><span class="vistas" style="height:<?= round(100 * $a / $maior_hora) ?>%"></span><span class="aparicoes" style="height:<?= round(100 * $n / $maior_hora) ?>%"></span></div><?php endforeach ?>
</div>
<div class="barras-rotulos" style="gap:clamp(3px, 1vw, 10px)" aria-hidden="true"><?php foreach (array_keys($rel['horas']) as $h): ?><span><?= $h ?>h</span><?php endforeach ?></div>
<p class="discreto">0h a 23h (horário de Brasília) · horário com mais entradas: <?= array_search(max($rel['horas']), $rel['horas'], true) ?>h
  <?php if (array_sum($rel['horas_antes'])): ?>(<?= h(mb_strtolower($rot_antes)) ?>: <?= array_search(max($rel['horas_antes']), $rel['horas_antes'], true) ?>h)<?php endif ?>
  <?php if ($ant_ini < HORAS_DESDE): ?>· a hora conta desde 14/11/2026, o período anterior pode estar incompleto<?php endif ?></p>
<?php endif ?>

<?php if ($rel['areas']): ?>
<h3>Áreas mais procuradas</h3>
<div class="tabela"><table>
  <tr><th>Área</th><th>Entradas</th><th>%</th></tr>
  <?php $ta = array_sum($rel['areas']); foreach ($rel['areas'] as $a => $n): ?><tr><td><?= h($a) ?></td><td><?= num($n) ?></td><td><?= pct($n, $ta) ?></td></tr><?php endforeach ?>
</table></div>
<?php endif ?>

<?php if ($rel['comercios']): ?>
<h3 id="lista-comercios">Comércios mais procurados</h3>
<?php [$itens, $nav, $i] = $paginar($rel['comercios'], 'comercios'); ?>
<div class="tabela"><table>
  <tr><th>#</th><th>Comércio</th><th>Entradas</th><th></th></tr>
  <?php foreach ($itens as $id => $r): $i++; ?><tr>
    <td><?= $i ?></td>
    <td><strong><?= adm_comercio((int)$r['p']['id'], $r['p']['nome'], $r['p']['cidade']) ?></strong><br><span class="discreto"><?= h($r['p']['tipo'] ?: $r['p']['categoria']) ?> · <?= adm_cidade($r['p']['cidade'], null, true) ?></span></td>
    <td><?= num($r['total']) ?></td>
    <td><a class="botao pequeno contorno" href="numeros-ficha.php?id=<?= (int)$id ?>&amp;dias=<?= $dias ?>">Números</a>
      <a class="botao pequeno contorno" href="<?= h(url_publica(ltrim($r['caminho'], '/'))) ?>" target="_blank" rel="noopener">Abrir</a></td>
  </tr><?php endforeach ?>
</table></div>
<?= $nav ?>
<?php endif ?>

<?php if (count($rel['dias']) > 1): ?>
<h3>Entradas por dia</h3>
<div class="barras-dias" role="img" aria-label="Entradas por dia">
  <?php foreach ($rel['dias'] as $d => $n): ?><div class="dia" title="<?= h(data_br($d)) ?>: <?= $n ?> entradas"><span class="vistas" style="height:<?= round(100 * $n / $maior_dia) ?>%"></span></div><?php endforeach ?>
</div>
<?php $passo = (int)ceil(count($rel['dias']) / 12); $i = 0; ?>
<div class="barras-rotulos datas" aria-hidden="true"><?php foreach (array_keys($rel['dias']) as $d): ?><span><?= $i++ % $passo === 0 ? date('d/m', strtotime((string)$d)) : '' ?></span><?php endforeach ?></div>
<p class="discreto"><?= h(data_br($desde)) ?> a <?= h(data_br(hoje())) ?> · dia com mais entradas:
  <?php $top = array_search(max($rel['dias']), $rel['dias'], true); ?><?= h(data_br((string)$top)) ?> (<?= num(max($rel['dias'])) ?>)</p>
<?php endif ?>

<?php endif ?>

<h2 id="lista-paginas">Páginas onde entraram</h2>
<?php if (!entradas_prontas()): ?>
<p class="aviso">Começa a contar depois de aplicar a mudança 022 (bash servidor/instalar.sh, como root).</p>
<?php elseif (!$paginas): ?>
<p class="discreto">Nenhuma entrada por esta origem no período.</p>
<?php else: ?>
<p class="discreto">Ordem: <?= $ordem === 'recentes' ? '<strong>mais recentes</strong> · <a href="' . h($link(['ordem' => 'total'])) . '">mais entradas</a>'
  : '<a href="' . h($link(['ordem' => null])) . '">mais recentes</a> · <strong>mais entradas</strong>' ?>. Horário de Brasília; a hora conta desde 14/11/2026.</p>
<div class="tabela"><table>
  <tr><th>Página</th><th>Tipo</th><th>Quando</th><th>Entradas</th><th>%</th></tr>
  <?php [$itens, $nav] = $paginar($paginas, 'paginas'); foreach ($itens as $l): ?><tr>
    <td><a href="<?= h(url_publica(ltrim($l['caminho'], '/'))) ?>" target="_blank" rel="noopener"><?= h($l['caminho']) ?></a></td>
    <td><?= h(PAGINAS_ENTRADA[$l['pagina']] ?? $l['pagina']) ?><?= $l['cidade'] ? ' · ' . adm_cidade($l['cidade'], null, true) : '' ?></td>
    <td style="white-space:nowrap"><?php $q = $quando[$l['caminho']] ?? []; ?><?= implode('<br>', array_slice($q, 0, 5)) ?: '—' ?><?= count($q) > 5 ? '<br><span class="discreto">e mais ' . (count($q) - 5) . '</span>' : '' ?></td>
    <td><?= num((int)$l['total']) ?></td><td><?= pct((int)$l['total'], $total_entradas) ?></td>
  </tr><?php endforeach ?>
</table></div>
<?= $nav ?>
<?php endif ?>

<h2 id="lista-fichas">Fichas de comércio abertas</h2>
<?php if (!$abertas): ?><p class="discreto">Nenhuma ficha aberta por quem veio desta origem no período.</p><?php else: ?>
<div class="tabela"><table>
  <tr><th>Comércio</th><th>Visualizações</th><th>Pessoas</th><th>Toques</th><th></th></tr>
  <?php [$itens, $nav] = $paginar($abertas, 'fichas'); foreach ($itens as $l): ?><tr>
    <td><?= $comercio($l) ?></td><td><?= num((int)$l['visualizacoes']) ?></td><td><?= num((int)$l['pessoas']) ?></td>
    <td><?= num((int)$l['toques']) ?></td><td><?= $ver($l) ?></td>
  </tr><?php endforeach ?>
</table></div>
<?= $nav ?>
<?php endif ?>

<h2 id="lista-toques">Toques em WhatsApp, Ligar, Mapa, Site e Instagram</h2>
<?php if (!$tocadas): ?><p class="discreto">Nenhum toque de quem veio desta origem no período.</p><?php else: ?>
<div class="tabela"><table>
  <tr><th>Comércio</th><th>WhatsApp</th><th>Ligar</th><th>Mapa</th><th>Site</th><th>Instagram</th><th>Total</th><th></th></tr>
  <?php [$itens, $nav] = $paginar($tocadas, 'toques'); foreach ($itens as $l): ?><tr>
    <td><?= $comercio($l) ?></td>
    <?php foreach (['whatsapp', 'ligar', 'mapa', 'site_t', 'instagram'] as $k): ?><td><?= num((int)$l[$k]) ?></td><?php endforeach ?>
    <td><strong><?= num((int)$l['toques']) ?></strong></td><td><?= $ver($l) ?></td>
  </tr><?php endforeach ?>
</table></div>
<?= $nav ?>
<?php endif ?>
<?php admin_fim();
