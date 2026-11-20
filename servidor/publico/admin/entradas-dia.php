<?php
// Relatório completo de um dia (19/11, pedido do usuário): aberto pelo "Dia a dia" de /admin/entradas.php.
// ?dia=AAAA-MM-DD&origem=<origem do site ou vazio = todas, site e app>&cidade=<slug ou vazio>
// Mostra: resumo, de onde vieram (por origem), por hora, cidades, páginas onde entraram (com a hora), fichas abertas e toques.
require __DIR__ . '/../../inc/admin.php';
$admin = exigir_admin();
exigir_numeros_prontos();

$hoje_d = hoje();
$dia = (string)($_GET['dia'] ?? $hoje_d);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia) || $dia > $hoje_d || !strtotime($dia)) $dia = $hoje_d;
$origem = in_array($_GET['origem'] ?? '', ORIGENS_SITE, true) ? (string)$_GET['origem'] : '';
$cidade = isset(TODAS_CIDADES[$_GET['cidade'] ?? '']) ? (string)$_GET['cidade'] : '';
$link = fn(array $mudar) => '?' . http_build_query(array_filter(array_merge(['dia' => $dia, 'origem' => $origem, 'cidade' => $cidade], $mudar),
    fn($v) => $v !== '' && $v !== null));
$ontem = date('Y-m-d', strtotime("$dia -1 day"));
$amanha = date('Y-m-d', strtotime("$dia +1 day"));
$nome_dia = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'][(int)date('w', strtotime($dia))];
$nome_origem = fn(string $o) => ORIGENS[$o] ?? $o;

// filtros comuns: entradas/entradas_hora (coluna cidade) e contatos (cidade do comércio)
$we = ($origem !== '' ? ' AND origem = ?' : '') . ($cidade !== '' ? ' AND cidade = ?' : '');
$pe = array_merge($origem !== '' ? [$origem] : [], $cidade !== '' ? [$cidade] : []);
$wk = ($origem !== '' ? ' AND k.origem = ?' : '') . ($cidade !== '' ? ' AND p.cidade = ?' : '');

// 1. entradas no site
$entradas = $pre = $pre_vistas = 0;
$por_origem = [];
if (entradas_prontas()) {
    foreach (linhas("SELECT origem, SUM(total) AS t FROM entradas WHERE dia = ?$we GROUP BY origem", array_merge([$dia], $pe)) as $l) {
        $por_origem[$l['origem']]['entradas'] = (int)$l['t'];
        $entradas += (int)$l['t'];
    }
    if (pre_carregamento_pronto()) {
        $pre = (int)valor("SELECT COUNT(*) FROM entradas_hora WHERE quando BETWEEN ? AND ? AND pre = 1$we", array_merge(["$dia 00:00:00", "$dia 23:59:59"], $pe));
        $pre_vistas = (int)valor("SELECT COUNT(*) FROM entradas_hora WHERE quando BETWEEN ? AND ? AND pre = 2$we", array_merge(["$dia 00:00:00", "$dia 23:59:59"], $pe));
    }
}

// 2. fichas e toques (contatos do dia), por comércio
$fichas = linhas("SELECT p.id, p.nome, p.tipo, p.cidade, p.bairro, c.nome AS categoria,
        SUM(IF(k.tipo = 'perfil', k.total, 0)) AS vis, SUM(IF(k.tipo = 'perfil', k.pessoas, 0)) AS pessoas,
        SUM(IF(k.tipo = 'whatsapp', k.total, 0)) AS whatsapp, SUM(IF(k.tipo = 'ligar', k.total, 0)) AS ligar,
        SUM(IF(k.tipo = 'mapa', k.total, 0)) AS mapa, SUM(IF(k.tipo IN ('site', 'instagram'), k.total, 0)) AS outros,
        SUM(IF(k.tipo = 'compartilhar', k.total, 0)) AS compartilhar
    FROM contatos k JOIN profissionais p ON p.id = k.profissional_id JOIN categorias c ON c.id = p.categoria_id
    WHERE k.dia = ?$wk GROUP BY p.id ORDER BY (SUM(k.total) - SUM(IF(k.tipo = 'perfil', k.total, 0))) DESC, vis DESC, p.nome LIMIT 500",
    array_merge([$dia], $pe));
$tot = ['vis' => 0, 'pessoas' => 0, 'whatsapp' => 0, 'ligar' => 0, 'mapa' => 0, 'outros' => 0, 'compartilhar' => 0];
foreach ($fichas as $f) foreach ($tot as $k => $_) $tot[$k] += (int)$f[$k];
$toques = $tot['whatsapp'] + $tot['ligar'] + $tot['mapa'] + $tot['outros'];
$abertas = count(array_filter($fichas, fn($f) => (int)$f['vis'] > 0));
$tocados = count(array_filter($fichas, fn($f) => (int)$f['whatsapp'] + (int)$f['ligar'] + (int)$f['mapa'] + (int)$f['outros'] > 0));
foreach (linhas("SELECT k.origem, SUM(IF(k.tipo = 'perfil', k.total, 0)) AS vis, SUM(IF(k.tipo NOT IN ('perfil', 'compartilhar'), k.total, 0)) AS toques
        FROM contatos k JOIN profissionais p ON p.id = k.profissional_id WHERE k.dia = ?$wk GROUP BY k.origem", array_merge([$dia], $pe)) as $l) {
    $por_origem[$l['origem']]['vis'] = (int)$l['vis'];
    $por_origem[$l['origem']]['toques'] = (int)$l['toques'];
}
uasort($por_origem, fn($a, $b) => (($b['entradas'] ?? 0) + ($b['vis'] ?? 0)) <=> (($a['entradas'] ?? 0) + ($a['vis'] ?? 0)));

// 3. por hora e páginas onde entraram (com a hora; pre = 1 é o Chrome baixando antes do clique)
$horas = array_fill(0, 24, 0);
$lista_entradas = [];
try {
    $lista_entradas = linhas("SELECT quando, origem, caminho, pagina, cidade, pre FROM entradas_hora WHERE quando BETWEEN ? AND ?$we ORDER BY quando DESC LIMIT 1000",
        array_merge(["$dia 00:00:00", "$dia 23:59:59"], $pe));
    foreach ($lista_entradas as $l) if ((int)$l['pre'] !== 1) $horas[(int)date('G', strtotime($l['quando']))]++;
} catch (PDOException) {}
$max_hora = max(1, max($horas));

// 19/11 (passo 112): tabela por hora completa. Entradas vêm de entradas_hora; visualizações e toques de contatos_hora
// (mudança 034, só a partir de quando ela foi aplicada; antes disso essas colunas ficam com "—").
$h0 = ['entradas' => 0, 'pre' => 0, 'gente' => 0, 'fichas' => 0, 'vis' => 0, 'pessoas' => 0, 'whatsapp' => 0, 'ligar' => 0, 'mapa' => 0, 'outros' => 0, 'compartilhar' => 0];
$por_hora = array_fill(0, 24, $h0);
foreach ($lista_entradas as $l) {
    $hh = (int)date('G', strtotime($l['quando']));
    $por_hora[$hh]['entradas']++;
    if ((int)$l['pre'] === 1) $por_hora[$hh]['pre']++; else $por_hora[$hh]['gente']++;
}
$tem_hora_contatos = false;
$inicio_hora = null;   // hora em que a 034 foi aplicada: antes dela, no mesmo dia, as colunas ficam "—"
if (contatos_hora_prontos()) {
    try {
        $inicio_hora = (string)valor("SELECT aplicada_em FROM migracoes WHERE nome = '034-contatos-hora.sql'");
        $tem_hora_contatos = $inicio_hora !== '' && substr($inicio_hora, 0, 10) <= $dia;
        $wh = ($origem !== '' ? ' AND k.origem = ?' : '') . ($cidade !== '' ? ' AND p.cidade = ?' : '');
        foreach (linhas("SELECT HOUR(k.quando) AS h, COUNT(DISTINCT IF(k.tipo = 'perfil', k.profissional_id, NULL)) AS fichas,
                SUM(k.tipo = 'perfil') AS vis, SUM(k.tipo = 'perfil' AND k.pessoa = 1) AS pessoas,
                SUM(k.tipo = 'whatsapp') AS whatsapp, SUM(k.tipo = 'ligar') AS ligar, SUM(k.tipo = 'mapa') AS mapa,
                SUM(k.tipo IN ('site', 'instagram')) AS outros, SUM(k.tipo = 'compartilhar') AS compartilhar
            FROM contatos_hora k JOIN profissionais p ON p.id = k.profissional_id
            WHERE k.quando BETWEEN ? AND ?$wh GROUP BY HOUR(k.quando)", array_merge(["$dia 00:00:00", "$dia 23:59:59"], $pe)) as $l)
            foreach (['fichas', 'vis', 'pessoas', 'whatsapp', 'ligar', 'mapa', 'outros', 'compartilhar'] as $k) $por_hora[(int)$l['h']][$k] = (int)$l[$k];
    } catch (PDOException) {}
}
$tot_hora = $h0;
foreach ($por_hora as $l) foreach ($h0 as $k => $_) $tot_hora[$k] += $l[$k];
$max_entradas = max(1, max(array_column($por_hora, 'entradas')));   // barra: a hora com mais entradas = 100%

// 4. cidades: entradas e fichas/toques
$cidades = [];
if (entradas_prontas()) foreach (linhas("SELECT cidade, SUM(total) AS t FROM entradas WHERE dia = ?$we GROUP BY cidade", array_merge([$dia], $pe)) as $l)
    $cidades[(string)$l['cidade']]['entradas'] = (int)$l['t'];
foreach ($fichas as $f) {
    $c = (string)$f['cidade'];
    $cidades[$c]['vis'] = ($cidades[$c]['vis'] ?? 0) + (int)$f['vis'];
    $cidades[$c]['toques'] = ($cidades[$c]['toques'] ?? 0) + (int)$f['whatsapp'] + (int)$f['ligar'] + (int)$f['mapa'] + (int)$f['outros'];
}
uasort($cidades, fn($a, $b) => (($b['entradas'] ?? 0) + ($b['vis'] ?? 0)) <=> (($a['entradas'] ?? 0) + ($a['vis'] ?? 0)));

admin_inicio('Relatório do dia ' . date('d/m', strtotime($dia)), $admin);
?>
<p><a href="entradas.php?<?= h(http_build_query(array_filter(['origem' => $origem ?: 'google', 'cidade' => $cidade, 'semana' => date('Y-m-d', strtotime('monday this week', strtotime($dia)))]))) ?>#dia-a-dia">&larr; Dia a dia</a></p>
<h1>Relatório de <?= date('d/m/Y', strtotime($dia)) ?> <small class="discreto"><?= $nome_dia ?><?= $dia === $hoje_d ? ' · hoje (ainda não acabou)' : '' ?></small></h1>

<div class="paginacao-semana">
  <a class="botao pequeno contorno" href="<?= h($link(['dia' => $ontem])) ?>">← <?= date('d/m', strtotime($ontem)) ?></a>
  <form method="get" class="filtros-numeros" style="margin:0">
    <input type="hidden" name="dia" value="<?= h($dia) ?>">
    <label>Origem <select name="origem"><option value="">Todas (site e app)</option>
      <?php foreach (ORIGENS_SITE as $o): if ($o === 'web') continue; ?><option value="<?= h($o) ?>"<?= $o === $origem ? ' selected' : '' ?>><?= h($nome_origem($o)) ?></option><?php endforeach ?>
    </select></label>
    <label>Cidade <select name="cidade"><option value="">Todas</option>
      <?php foreach (TODAS_CIDADES as $slug => $c): ?><option value="<?= h($slug) ?>"<?= $slug === $cidade ? ' selected' : '' ?>><?= h($c[0]) ?></option><?php endforeach ?>
    </select></label>
    <button class="botao pequeno">Ver</button>
  </form>
  <?php if ($amanha <= $hoje_d): ?><a class="botao pequeno contorno" href="<?= h($link(['dia' => $amanha])) ?>"><?= date('d/m', strtotime($amanha)) ?> →</a><?php else: ?><span></span><?php endif ?>
</div>

<div class="numeros-resumo">
  <div><strong><?= num($entradas) ?></strong><span>entradas no site</span></div>
  <div><strong><?= num(max(0, $entradas - $pre)) ?></strong><span>entradas de pessoas<?= $pre ? ' (' . num($pre) . ' pré-carregadas pelo Chrome)' : '' ?></span></div>
  <div><strong><?= num($abertas) ?></strong><span>fichas abertas</span></div>
  <div><strong><?= num($tot['vis']) ?></strong><span>visualizações</span></div>
  <div><strong><?= num($toques) ?></strong><span>toques em <?= num($tocados) ?> comércios</span></div>
  <div><strong><?= num($tot['whatsapp']) ?> · <?= num($tot['ligar']) ?> · <?= num($tot['mapa']) ?></strong><span>WhatsApp · Ligar · Mapa</span></div>
</div>
<?php if ($origem === ''): ?><p class="discreto">"Todas" junta o site (Google, direto, redes...) e o app (busca, categoria, perto...). Entradas são só do site.</p><?php endif ?>

<?php if ($origem === '' && $por_origem): ?>
<h2>De onde vieram</h2>
<div class="tabela"><table>
  <tr><th>Origem</th><th>Entradas</th><th>Visualizações</th><th>Toques</th></tr>
  <?php foreach ($por_origem as $o => $l): ?>
  <tr><td><?= in_array($o, ORIGENS_SITE, true) ? '<a href="' . h($link(['origem' => $o])) . '">' . h($nome_origem($o)) . '</a>' : h($nome_origem($o)) . ' <span class="discreto">(app)</span>' ?></td>
    <td><?= isset($l['entradas']) ? num($l['entradas']) : '—' ?></td><td><?= num($l['vis'] ?? 0) ?></td><td><?= num($l['toques'] ?? 0) ?></td></tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>

<h2>Por hora</h2>
<p class="discreto">Entradas no site com a hora registrada; "Chegadas" tira o Chrome baixando a página antes do clique (a diferença para Entradas é o pré-carregamento); "Pessoas" conta 1 por pessoa em cada ficha aberta. Onde não teve movimento aparece "—".
  <?php if ($tem_hora_contatos): ?>Fichas abertas, visualizações e toques contam site e app<?= $origem !== '' ? ' (só desta origem)' : '' ?>.<?php endif ?></p>
<?php $ini_txt = $inicio_hora ? date('d/m \à\s H:i', strtotime($inicio_hora)) : null;
if (!$tem_hora_contatos): ?>
<p class="aviso erro">Neste dia o servidor guardou <strong>só o total</strong> de fichas abertas, visualizações e toques (os quadros lá em cima),
  não a hora de cada um. Por isso a tabela abaixo mostra só as entradas. A hora começou a ser guardada <?= $ini_txt ? "em $ini_txt" : 'quando a mudança 034 for aplicada' ?>.
  Entradas no site já tinham hora e aparecem normalmente.</p>
<?php endif ?>
<div class="tabela"><table class="por-hora">
  <tr><th>Hora</th><th>Entradas</th><th title="Chegadas ao site vindas de fora (Google, endereço, links), sem o pré-carregamento do Chrome">Chegadas</th><?php if ($tem_hora_contatos): ?><th title="Fichas abertas">Fichas</th><th title="Visualizações">Visual.</th><th title="1 por pessoa em cada ficha no dia (site e app)">Pessoas</th><th title="WhatsApp">Whats</th><th>Ligar</th><th>Mapa</th><th>Site/Insta</th><th>Toques</th><th>Compart.</th><?php endif ?></tr>
  <?php foreach ($por_hora as $hh => $l):
    $toq = $l['whatsapp'] + $l['ligar'] + $l['mapa'] + $l['outros'];
    $vazia = !$l['entradas'] && !$l['vis'] && !$toq && !$l['compartilhar'];
    // hora antes do início do registro (no dia em que a 034 entrou) também não tem dado: "—" em vez de 0
    $sem_dado = !$tem_hora_contatos || ($inicio_hora && $dia === substr($inicio_hora, 0, 10) && $hh < (int)substr($inicio_hora, 11, 2));
    $cel = fn(string $k) => $sem_dado ? '<span class="discreto">—</span>' : ($l[$k] ? num($l[$k]) : '<span class="discreto">—</span>'); ?>
  <tr<?= $vazia ? ' class="discreto"' : '' ?><?= $dia === $hoje_d && $hh === (int)date('G') ? ' style="background:var(--amarelo-claro)"' : '' ?>>
    <td><strong><?= sprintf('%02dh', $hh) ?></strong></td>
    <td class="com-barra"><span class="barra" aria-hidden="true"><i style="width:<?= $l['entradas'] ? max(3, round(100 * $l['entradas'] / $max_entradas)) : 0 ?>%"></i></span><span class="n"><?= $l['entradas'] ? '<strong>' . num($l['entradas']) . '</strong>' : '<span class="discreto">—</span>' ?></span></td>
    <td><?= $l['gente'] ? num($l['gente']) : '<span class="discreto">—</span>' ?></td>
    <?php if ($tem_hora_contatos): ?><td><?= $cel('fichas') ?></td><td><?= $cel('vis') ?></td><td><?= $cel('pessoas') ?></td>
    <td><?= $cel('whatsapp') ?></td><td><?= $cel('ligar') ?></td><td><?= $cel('mapa') ?></td><td><?= $cel('outros') ?></td>
    <td><?= $sem_dado ? '<span class="discreto">—</span>' : ($toq ? '<strong>' . num($toq) . '</strong>' : '<span class="discreto">—</span>') ?></td>
    <td><?= $cel('compartilhar') ?></td><?php endif ?>
  </tr>
  <?php endforeach;
  $tt = $tot_hora['whatsapp'] + $tot_hora['ligar'] + $tot_hora['mapa'] + $tot_hora['outros'];
  $ct = fn(string $k) => $tem_hora_contatos ? ($tot_hora[$k] ? '<strong>' . num($tot_hora[$k]) . '</strong>' : '—') : '—'; ?>
  <tr class="total-semana"><td><strong>Dia</strong></td><td><strong><?= num($tot_hora['entradas']) ?></strong></td><td><strong><?= num($tot_hora['gente']) ?></strong></td>
<?php if ($tem_hora_contatos): ?><td><?= $ct('fichas') ?></td><td><?= $ct('vis') ?></td><td><?= $ct('pessoas') ?></td>
    <td><?= $ct('whatsapp') ?></td><td><?= $ct('ligar') ?></td><td><?= $ct('mapa') ?></td><td><?= $ct('outros') ?></td>
    <td><?= $tem_hora_contatos ? ($tt ? '<strong>' . num($tt) . '</strong>' : '—') : '—' ?></td><td><?= $ct('compartilhar') ?></td><?php endif ?></tr>
</table></div>

<?php if ($cidades): ?>
<h2>Cidades</h2>
<div class="tabela"><table>
  <tr><th>Cidade</th><th>Entradas</th><th>Visualizações</th><th>Toques</th></tr>
  <?php foreach ($cidades as $c => $l): ?>
  <tr><td><?= $c !== '' ? adm_cidade($c) : 'Sem cidade (página inicial, busca)' ?></td><td><?= num($l['entradas'] ?? 0) ?></td><td><?= num($l['vis'] ?? 0) ?></td><td><?= num($l['toques'] ?? 0) ?></td></tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>

<h2>Fichas abertas e toques (<?= num(count($fichas)) ?> comércios)</h2>
<?php if (!$fichas): ?><p class="discreto">Nenhuma ficha aberta neste dia<?= $origem !== '' ? ' por esta origem' : '' ?>.</p><?php else: ?>
<div class="tabela"><table class="fichas-dia">
  <tr><th>Comércio</th><th>Vistas</th><th>Pessoas</th><th>WhatsApp</th><th>Ligar</th><th>Mapa</th><th>Site/Insta</th><th>Compart.</th><th></th></tr>
  <?php foreach ($fichas as $f): ?>
  <tr><td><strong><?= adm_comercio((int)$f['id'], $f['nome'], $f['cidade']) ?></strong><br><span class="discreto"><?= h($f['tipo'] ?: $f['categoria']) ?> · <?= adm_cidade($f['cidade'], null, true) ?><?= $f['bairro'] ? ' · ' . h($f['bairro']) : '' ?></span></td>
    <?php foreach (['vis', 'pessoas', 'whatsapp', 'ligar', 'mapa', 'outros', 'compartilhar'] as $k): ?><td><?= (int)$f[$k] ? '<strong>' . num((int)$f[$k]) . '</strong>' : '<span class="discreto">—</span>' ?></td><?php endforeach ?>
    <td><a class="botao pequeno contorno" href="numeros-ficha.php?id=<?= (int)$f['id'] ?>">Números</a></td></tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>

<h2>Páginas onde entraram (<?= num(count($lista_entradas)) ?>)</h2>
<?php if (!$lista_entradas): ?><p class="discreto">Nenhuma entrada com hora neste dia<?= $origem !== '' ? ' por esta origem' : '' ?>.</p><?php else: ?>
<div class="tabela"><table>
  <tr><th>Hora</th><th>Página</th><th>Tipo</th><th>Cidade</th><?php if ($origem === ''): ?><th>Origem</th><?php endif ?></tr>
  <?php foreach ($lista_entradas as $l): ?>
  <tr<?= (int)$l['pre'] === 1 ? ' class="discreto" title="Chrome baixou antes do clique"' : '' ?>><td><?= date('H:i', strtotime($l['quando'])) ?><?= (int)$l['pre'] === 1 ? ' <small>(pré)</small>' : '' ?></td>
    <td><a href="<?= h(url_publica(ltrim((string)$l['caminho'], '/'))) ?>" target="_blank" rel="noopener"><?= h(rawurldecode((string)$l['caminho'])) ?></a></td>
    <td><?= h(PAGINAS_ENTRADA[$l['pagina']] ?? $l['pagina']) ?></td><td><?= $l['cidade'] ? adm_cidade($l['cidade'], null, true) : '—' ?></td>
    <?php if ($origem === ''): ?><td><?= h($nome_origem((string)$l['origem'])) ?></td><?php endif ?></tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>

<style>
  .paginacao-semana { display: flex; align-items: flex-end; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin: 0 0 16px; }
  .horas-dia { display: grid; gap: 3px; max-width: 640px; }
  .hora-linha { display: grid; grid-template-columns: 40px 1fr 40px; align-items: center; gap: 8px; font-size: .85rem; }
  .hora-linha i { display: block; height: 12px; border-radius: 6px; background: var(--verde); min-width: 2px; }
  .hora-linha b { text-align: right; }
  .por-hora td, .por-hora th { text-align: center; white-space: nowrap; }
  .por-hora th { font-size: 17px; } .por-hora td { font-size: 16px; }   /* 19/11 (pedido do usuário): cabeçalho 17, com movimento 16, sem movimento 14 */
  .por-hora tr.discreto td, .por-hora tr.discreto td * { font-size: 14px; }
  .fichas-dia td, .fichas-dia th { text-align: center; } .fichas-dia td:first-child, .fichas-dia th:first-child { text-align: left; }
  /* barra de tamanho fixo (60px): a hora com mais pessoas enche 100%, as outras proporcionais; o número fica sempre no mesmo lugar */
  .por-hora .com-barra .barra { display: inline-block; width: 60px; height: 10px; border-radius: 5px; background: rgba(0, 92, 56, .1); margin-right: 8px; vertical-align: middle; overflow: hidden; text-align: left; }
  .por-hora .com-barra .n { display: inline-block; width: 36px; text-align: right; }   /* "9" e "12" ocupam o mesmo espaço: as barras não andam */
  .por-hora .com-barra .barra i { display: block; height: 100%; border-radius: 5px; background: var(--verde); }
  .por-hora tr.total-semana td { border-top: 2px solid var(--verde); }
</style>
<?php
admin_fim();
