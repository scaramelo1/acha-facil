<?php
// Pedidos de informação ("Quero saber...") em página própria (17/11, pedido do usuário; antes ficava em numeros.php).
// Mesmos filtros de numeros.php (cidade, área, tipo, plano, período) + as datas em que cada comércio recebeu pedidos.
require __DIR__ . '/../../inc/admin.php';
$admin = exigir_admin();
exigir_numeros_prontos();

$cidade = isset(TODAS_CIDADES[$_GET['cidade'] ?? '']) ? (string)$_GET['cidade'] : '';
$cat = (int)($_GET['categoria'] ?? 0);
$tipo = trim(mb_substr((string)($_GET['tipo'] ?? ''), 0, 80));
$dias = in_array((int)($_GET['dias'] ?? 30), [1, 7, 30, 90], true) ? (int)($_GET['dias'] ?? 30) : 30;
$plano = in_array($_GET['plano'] ?? '', ['sem', 'basico', 'destaque'], true) ? (string)$_GET['plano'] : '';
$desde = desde_dias($dias);

$plano_sql = "CASE WHEN " . SQL_DESTAQUE . " THEN 'destaque' WHEN " . SQL_EM_DIA . " THEN 'basico' ELSE 'sem' END";
$nome_plano = ['sem' => 'Sem plano', 'basico' => 'Básico', 'destaque' => 'Destaque'];
$where = ["p.status = 'ativo'"];
$params = [$desde];
if ($cidade !== '') { $where[] = 'p.cidade = ?'; $params[] = $cidade; }
if ($cat) { $where[] = 'p.categoria_id = ?'; $params[] = $cat; }
if ($tipo !== '') { $where[] = 'p.tipo = ?'; $params[] = $tipo; }
if ($plano !== '') { $where[] = "$plano_sql = ?"; $params[] = $plano; }

$pedidos = $datas = [];
if (pedidos_info_prontos()) {
    $pedidos = linhas("SELECT p.id, p.nome, p.tipo, p.cidade, p.bairro, p.whatsapp AS fone_whatsapp, p.telefone, c.nome AS categoria, $plano_sql AS plano_atual,
            SUM(IF(pi.tipo = 'horario', pi.total, 0)) AS horario, SUM(IF(pi.tipo = 'fotos', pi.total, 0)) AS fotos,
            SUM(IF(pi.tipo = 'sobre', pi.total, 0)) AS sobre, SUM(IF(pi.tipo = 'whatsapp', pi.total, 0)) AS whatsapp, SUM(pi.total) AS total,
            MAX(pi.dia) AS ultimo
        FROM pedidos_info pi JOIN profissionais p ON p.id = pi.profissional_id JOIN categorias c ON c.id = p.categoria_id
        WHERE pi.dia >= ? AND " . implode(' AND ', $where) . "
        GROUP BY p.id ORDER BY total DESC, ultimo DESC, p.nome LIMIT 500", $params);
    // datas de cada pedido: por comércio, dia a dia, o que foi pedido e quantas vezes
    if ($pedidos) {
        $ids = array_map(fn($l) => (int)$l['id'], $pedidos);
        foreach (linhas('SELECT profissional_id, dia, tipo, SUM(total) AS t FROM pedidos_info
                WHERE dia >= ? AND profissional_id IN (' . implode(',', $ids) . ')
                GROUP BY profissional_id, dia, tipo ORDER BY dia DESC, tipo', [$desde]) as $d) {
            $datas[(int)$d['profissional_id']][$d['dia']][$d['tipo']] = (int)$d['t'];
        }
    }
}
// texto de um dia: "Horário, WhatsApp (2)"
$texto_dia = fn(array $tipos) => implode(', ', array_map(
    fn($k, $t) => h(TIPOS_PEDIDO_INFO[$k] ?? $k) . ($t > 1 ? ' (' . $t . ')' : ''), array_keys($tipos), $tipos));

if (($_GET['csv'] ?? '') !== '') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="acha-facil-pedidos-' . ($cidade ?: 'todas') . '-' . $dias . 'd-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Comércio', 'Tipo', 'Área', 'Cidade', 'Bairro', 'Plano', 'WhatsApp do comércio', 'Telefone', ...array_values(TIPOS_PEDIDO_INFO), 'Total', 'Datas dos pedidos', 'Relatório para o comerciante'], ';');
    foreach ($pedidos as $l) {
        $dt = [];
        foreach ($datas[(int)$l['id']] ?? [] as $dia => $tipos) $dt[] = date('d/m/Y', strtotime($dia)) . ': ' . html_entity_decode(strip_tags($texto_dia($tipos)), ENT_QUOTES, 'UTF-8');
        fputcsv($out, [$l['id'], $l['nome'], $l['tipo'], $l['categoria'], nome_cidade($l['cidade']), $l['bairro'], $nome_plano[$l['plano_atual']],
            telefone_formatado($l['fone_whatsapp']), telefone_formatado($l['telefone']),
            ...array_map(fn($k) => (int)$l[$k], array_keys(TIPOS_PEDIDO_INFO)), (int)$l['total'], implode(' | ', $dt),
            url_publica('admin/relatorio.php?id=' . $l['id'] . '&dias=' . $dias)], ';');
    }
    exit;
}

$link = fn(array $mudar) => '?' . http_build_query(array_filter(array_merge($_GET, $mudar), fn($v) => $v !== '' && $v !== null && $v !== 0));
$volta = 'numeros.php' . (($q = http_build_query(array_filter(['cidade' => $cidade, 'categoria' => $cat ?: null, 'tipo' => $tipo, 'dias' => $dias, 'plano' => $plano]))) ? '?' . $q : '');

admin_inicio('Pedidos de informação', $admin);
?>
<p><a href="<?= h($volta) ?>">&larr; Números</a></p>
<h1>Pedidos de informação</h1>
<p class="discreto">Fichas em que alguém tocou em "Quero saber..." (1 por pessoa, por dia). São os comércios para chamar primeiro:
  abra o <strong>Relatório</strong> e mande para o dono. A coluna <strong>Datas</strong> mostra em que dia cada pedido chegou.</p>

<form method="get" class="filtros-numeros">
  <label>Cidade <select name="cidade"><option value="">Todas</option>
    <?php foreach (TODAS_CIDADES as $slug => $c): ?><option value="<?= h($slug) ?>"<?= $slug === $cidade ? ' selected' : '' ?>><?= h($c[0]) ?></option><?php endforeach ?>
  </select></label>
  <label>Período <select name="dias">
    <?php foreach ([1, 7, 30, 90] as $d): ?><option value="<?= $d ?>"<?= $d === $dias ? ' selected' : '' ?>><?= $d === 1 ? 'Hoje' : "Últimos $d dias" ?></option><?php endforeach ?>
  </select></label>
  <label>Plano <select name="plano"><option value="">Todos</option>
    <?php foreach ($nome_plano as $k => $n): ?><option value="<?= $k ?>"<?= $k === $plano ? ' selected' : '' ?>><?= $n ?></option><?php endforeach ?>
  </select></label>
  <?php if ($cat): ?><input type="hidden" name="categoria" value="<?= $cat ?>"><?php endif ?>
  <?php if ($tipo !== ''): ?><input type="hidden" name="tipo" value="<?= h($tipo) ?>"><?php endif ?>
  <button class="botao pequeno">Filtrar</button>
  <?php if ($pedidos): ?><a class="botao pequeno contorno" href="<?= h($link(['csv' => 1])) ?>">Baixar CSV</a><?php endif ?>
  <a class="botao pequeno contorno" href="quem-pediu.php">Ver quem pediu</a>
</form>

<?php if (!pedidos_info_prontos()): ?>
<p class="aviso">Os pedidos ("Quero saber o horário", "Quero ver fotos"...) começam a contar depois de aplicar a mudança 028
  (bash servidor/instalar.sh, como root).</p>
<?php elseif (!$pedidos): ?>
<p class="discreto">Ninguém pediu informação no período<?= $cidade !== '' ? ' nesta cidade' : '' ?>. Aparecem aqui as fichas em que alguém
  tocou em "Quero saber o horário", "Quero ver fotos", "Quero saber mais" ou "Quero o WhatsApp".</p>
<?php else: ?>
<div class="numeros-resumo">
  <div><strong><?= num(count($pedidos)) ?></strong><span>comércios com pedidos</span></div>
  <div><strong><?= num(array_sum(array_column($pedidos, 'total'))) ?></strong><span>pedidos no período</span></div>
</div>
<div class="tabela"><table>
  <tr><th>Comércio</th><th>Plano</th><th>Contato do comércio</th>
    <?php foreach (TIPOS_PEDIDO_INFO as $n): ?><th><?= $n ?></th><?php endforeach ?><th>Total</th><th>Datas</th><th></th></tr>
  <?php foreach ($pedidos as $l): ?>
  <tr>
    <td><strong><?= h($l['nome']) ?></strong><br><span class="discreto"><?= h($l['tipo'] ?: $l['categoria']) ?> · <?= h(nome_cidade($l['cidade'], true)) ?><?= $l['bairro'] ? ' · ' . h($l['bairro']) : '' ?></span></td>
    <td><?= $nome_plano[$l['plano_atual']] ?></td>
    <td><?= h(telefone_formatado($l['fone_whatsapp'] ?: $l['telefone']) ?: '—') ?></td>
    <?php foreach (TIPOS_PEDIDO_INFO as $k => $n): ?><td><?= (int)$l[$k] ? num((int)$l[$k]) : '' ?></td><?php endforeach ?>
    <td><strong><?= num((int)$l['total']) ?></strong></td>
    <td style="white-space:nowrap"><?php foreach ($datas[(int)$l['id']] ?? [] as $dia => $tipos): ?>
      <strong><?= h(data_br($dia)) ?></strong> <span class="discreto"><?= $texto_dia($tipos) ?></span><br>
    <?php endforeach ?></td>
    <td><a class="botao pequeno" href="relatorio.php?id=<?= (int)$l['id'] ?>&amp;dias=<?= $dias ?>" target="_blank">Relatório</a>
      <a class="botao pequeno contorno" href="numeros-ficha.php?id=<?= (int)$l['id'] ?>&amp;dias=<?= $dias ?>">Ficha</a>
      <a class="botao pequeno contorno" href="quem-pediu.php?id=<?= (int)$l['id'] ?>">Quem pediu</a></td>
  </tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>
<?php admin_fim();
