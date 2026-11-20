<?php
// Google por cidade (passo 69): quantas fichas de cada cidade o robô do Google já visitou e quantas pessoas chegaram
// pela pesquisa. Os dados do robô vêm de $DADOS/google/visitas.json (google-visitas.php, de hora em hora pelo cron).
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/numeros.php';
$admin = exigir_admin();

$arq = config()['dados_dir'] . '/google/visitas.json';
$d = is_file($arq) ? (json_decode((string)file_get_contents($arq), true) ?: []) : [];
$fichas_vistas = $d['fichas'] ?? [];
$dias = $d['dias'] ?? [];
$hoje = hoje();
$ult7 = array_filter($dias, fn($k) => $k >= date('Y-m-d', strtotime('-6 days')), ARRAY_FILTER_USE_KEY);

// fichas no sitemap (mesma regra do web_sitemap_cidade: SQL_NO_GOOGLE), por cidade
$no_sitemap = $visitadas = [];
foreach (q('SELECT p.id, p.cidade FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE ' . visivel() . ' AND ' . SQL_NO_GOOGLE) as $p) {
    $no_sitemap[$p['cidade']] = ($no_sitemap[$p['cidade']] ?? 0) + 1;
    if (isset($fichas_vistas[$p['id']])) $visitadas[$p['cidade']] = ($visitadas[$p['cidade']] ?? 0) + 1;
}
$entradas = [];
if (entradas_prontas()) {
    foreach (linhas("SELECT cidade, SUM(total) AS t, SUM(IF(dia >= CURDATE() - INTERVAL 6 DAY, total, 0)) AS t7 FROM entradas
        WHERE origem = 'google' AND cidade IS NOT NULL GROUP BY cidade") as $l) $entradas[$l['cidade']] = $l;
}
$desde = array_column(linhas('SELECT slug, criado_em FROM cidades'), 'criado_em', 'slug');
$primeira_ficha = array_column(linhas('SELECT cidade, MIN(criado_em) AS d FROM profissionais GROUP BY cidade'), 'd', 'cidade');

$linhas = [];
foreach (CIDADES as $slug => $c) {
    $ultima = null; $pag7 = 0;
    foreach ($dias as $dia => $pc) if (isset($pc[$slug])) $ultima = $dia;
    foreach ($ult7 as $pc) $pag7 += (int)($pc[$slug]['paginas'] ?? 0);
    $n = (int)($no_sitemap[$slug] ?? 0); $v = (int)($visitadas[$slug] ?? 0);
    $linhas[] = ['slug' => $slug, 'nome' => $c[0], 'desde' => min(array_filter([$desde[$slug] ?? null, $primeira_ficha[$slug] ?? null])) ?: null,
        'sitemap' => $n, 'visitadas' => $v, 'pct' => $n ? $v / $n : 0, 'pag7' => $pag7, 'ultima' => $ultima,
        'ent' => (int)($entradas[$slug]['t'] ?? 0), 'ent7' => (int)($entradas[$slug]['t7'] ?? 0)];
}
usort($linhas, fn($a, $b) => [$b['ent'], $b['visitadas']] <=> [$a['ent'], $a['visitadas']]);
$tot = fn(string $k) => array_sum(array_column($linhas, $k));
// precisam de ajuda: menos de 10% visitadas, as com mais fichas primeiro
$ajuda = array_filter($linhas, fn($l) => $l['sitemap'] > 0 && $l['pct'] < 0.10);
usort($ajuda, fn($a, $b) => $b['sitemap'] <=> $a['sitemap']);
$dias_tab = array_slice(array_keys($dias), -7);

admin_inicio('Google por cidade', $admin);
?>
<p><a href="numeros.php">&larr; Números</a></p>
<h1>Google por cidade</h1>
<p class="discreto">O Google só mostra na pesquisa as fichas que o robô dele já visitou. Aqui está, por cidade, quanto do
  sitemap ele já leu e quantas pessoas chegaram pela pesquisa. Só conta o robô do Google de verdade (IP conferido).
  <?php if (!empty($d['atualizado'])): ?>Robô atualizado em <?= h(datahora_br($d['atualizado'])) ?> (de hora em hora).<?php endif ?></p>

<?php if (!$d): ?>
  <div class="aviso erro">Ainda sem dados do robô. Rode como root: <code>php /var/www/acha-facil/servidor/google-visitas.php</code>
    (o instalar.sh deixa isso de hora em hora).</div>
<?php elseif (strtotime((string)($d['atualizado'] ?? '')) < time() - 3 * 3600): ?>
  <div class="aviso erro">Os dados do robô não são atualizados há mais de 3 horas. Confira o cron (rode o instalar.sh de novo).</div>
<?php endif ?>

<div class="numeros-resumo">
  <div><strong><?= num($tot('sitemap')) ?></strong><span>fichas no sitemap</span></div>
  <div><strong><?= num($tot('visitadas')) ?></strong><span>já visitadas pelo Google (<?= pct($tot('visitadas'), $tot('sitemap')) ?>)</span></div>
  <div><strong><?= num($tot('pag7')) ?></strong><span>páginas lidas pelo Google nos últimos 7 dias</span></div>
  <div><strong><?= num($tot('ent')) ?></strong><span>entradas pela pesquisa do Google</span></div>
</div>

<div class="tabela"><table>
  <tr><th>#</th><th>Cidade</th><th>No app desde</th><th>Fichas no sitemap</th><th>Visitadas pelo Google</th><th>%</th>
    <th>Páginas lidas (7 dias)</th><th>Última visita do Google</th><th>Entradas pelo Google</th><th>Últimos 7 dias</th></tr>
  <?php foreach ($linhas as $i => $l): $baixo = $l['sitemap'] > 0 && $l['pct'] < 0.10; ?>
  <tr<?= $baixo ? ' style="background:#fde2e1"' : '' ?>>
    <td><?= $i + 1 ?></td>
    <td><strong><?= adm_cidade($l['slug'], $l['nome']) ?></strong></td>
    <td><?= $l['desde'] ? h(date('d/m', strtotime($l['desde']))) : '-' ?></td>
    <td><?= num($l['sitemap']) ?></td>
    <td><strong><?= num($l['visitadas']) ?></strong></td>
    <td><?= $l['sitemap'] ? number_format(100 * $l['pct'], 0) . '%' : '-' ?></td>
    <td><?= $l['pag7'] ? num($l['pag7']) : '<span class="discreto">0</span>' ?></td>
    <td><?= $l['ultima'] ? ($l['ultima'] === $hoje ? 'hoje' : h(date('d/m', strtotime($l['ultima'])))) : '<span class="discreto">nunca</span>' ?></td>
    <td><strong><?= num($l['ent']) ?></strong></td>
    <td><?= num($l['ent7']) ?></td>
  </tr>
  <?php endforeach ?>
  <tr><td></td><td><strong>Total</strong></td><td></td><td><strong><?= num($tot('sitemap')) ?></strong></td><td><strong><?= num($tot('visitadas')) ?></strong></td>
    <td><?= pct($tot('visitadas'), $tot('sitemap')) ?></td><td><?= num($tot('pag7')) ?></td><td></td><td><strong><?= num($tot('ent')) ?></strong></td><td><?= num($tot('ent7')) ?></td></tr>
</table></div>
<p class="discreto">Em vermelho: menos de 10% das fichas visitadas. "Fichas no sitemap" = fichas no app com telefone ou WhatsApp
  (as sem contato ficam fora do Google). Visitas do robô contadas desde 10/11/2026.</p>

<?php if ($ajuda): ?>
<h2>Para empurrar no Search Console</h2>
<p class="discreto">Cidades com menos de 10% visitadas, as maiores primeiro. Em cada uma: <strong>Sitemaps</strong> → enviar o
  sitemap de comércios; <strong>Inspeção de URL</strong> → colar a página da cidade e "Solicitar indexação" (o Google aceita
  umas 10 por dia). <a href="https://search.google.com/search-console" target="_blank" rel="noopener">Abrir o Search Console</a></p>
<div class="tabela"><table>
  <tr><th>Cidade</th><th>Sitemap de comércios (enviar)</th><th>Página da cidade (pedir indexação)</th></tr>
  <?php foreach ($ajuda as $l): $w = cidade_web($l['slug']); ?>
  <tr><td><strong><?= adm_cidade($l['slug'], $l['nome']) ?></strong><br><span class="discreto"><?= num($l['visitadas']) ?> de <?= num($l['sitemap']) ?></span></td>
    <td><code><?= h(url_publica("sitemap-$w-comercios.xml")) ?></code></td>
    <td><code><?= h(url_publica("$w/")) ?></code></td></tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>

<?php if ($dias_tab): ?>
<h2>Páginas lidas pelo Google por dia</h2>
<div class="tabela"><table>
  <tr><th>Cidade</th><?php foreach ($dias_tab as $dia): ?><th><?= h(date('d/m', strtotime($dia))) ?></th><?php endforeach ?></tr>
  <?php foreach ($linhas as $l): ?>
  <tr><td><?= adm_cidade($l['slug'], $l['nome']) ?></td>
    <?php foreach ($dias_tab as $dia): $n = (int)($dias[$dia][$l['slug']]['paginas'] ?? 0); ?><td><?= $n ? '<strong>' . num($n) . '</strong>' : '<span class="discreto">·</span>' ?></td><?php endforeach ?></tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>
<?php admin_fim();
