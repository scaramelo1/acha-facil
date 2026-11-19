<?php
// Pedidos de informação ("Quero saber...") em página própria, no menu do admin (17/11, pedido do usuário; antes ficava em numeros.php).
// Filtros: cidade, plano, período (área/tipo pela URL). As datas de cada pedido ficam em quem-pediu.php.
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

$pedidos = [];
if (pedidos_info_prontos()) {
    $pedidos = linhas("SELECT p.id, p.nome, p.tipo, p.cidade, p.bairro, p.whatsapp AS fone_whatsapp, p.telefone, c.nome AS categoria, $plano_sql AS plano_atual,
            SUM(IF(pi.tipo = 'horario', pi.total, 0)) AS horario, SUM(IF(pi.tipo = 'fotos', pi.total, 0)) AS fotos,
            SUM(IF(pi.tipo = 'sobre', pi.total, 0)) AS sobre, SUM(IF(pi.tipo = 'whatsapp', pi.total, 0)) AS whatsapp, SUM(pi.total) AS total,
            MAX(pi.dia) AS ultimo
        FROM pedidos_info pi JOIN profissionais p ON p.id = pi.profissional_id JOIN categorias c ON c.id = p.categoria_id
        WHERE pi.dia >= ? AND " . implode(' AND ', $where) . "
        GROUP BY p.id ORDER BY total DESC, ultimo DESC, p.nome LIMIT 500", $params);
}

if (($_GET['csv'] ?? '') !== '') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="acha-facil-pedidos-' . ($cidade ?: 'todas') . '-' . $dias . 'd-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Comércio', 'Tipo', 'Área', 'Cidade', 'Bairro', 'Plano', 'WhatsApp do comércio', 'Telefone', ...array_values(TIPOS_PEDIDO_INFO), 'Total', 'Relatório para o comerciante'], ';');
    foreach ($pedidos as $l) {
        fputcsv($out, [$l['id'], $l['nome'], $l['tipo'], $l['categoria'], nome_cidade($l['cidade']), $l['bairro'], $nome_plano[$l['plano_atual']],
            telefone_formatado($l['fone_whatsapp']), telefone_formatado($l['telefone']),
            ...array_map(fn($k) => (int)$l[$k], array_keys(TIPOS_PEDIDO_INFO)), (int)$l['total'],
            url_publica('admin/relatorio.php?id=' . $l['id'] . '&dias=' . $dias)], ';');
    }
    exit;
}

$link = fn(array $mudar) => '?' . http_build_query(array_filter(array_merge($_GET, $mudar), fn($v) => $v !== '' && $v !== null && $v !== 0));

admin_inicio('Pedidos de informação', $admin);
?>
<h1>Pedidos de informação</h1>
<p class="discreto">Fichas em que alguém tocou em "Quero saber..." (1 por pessoa, por dia). São os comércios para chamar primeiro:
  abra o <strong>Relatório</strong> e mande para o dono. As datas de cada pedido ficam em <strong>Quem pediu</strong>.</p>

<?php // 19/11 (pedido do usuário): acompanhar quantos aceitam o convite "Quer saber quando colocarem...?" (texto novo em 19/11)
if (pedidos_info_pessoas_prontos()):
    $sem = linhas("SELECT YEARWEEK(criado_em, 3) AS s, MIN(DATE(criado_em)) AS de, COUNT(*) AS pedidos, SUM(usuario_id IS NOT NULL) AS com_conta
        FROM pedidos_info_pessoas WHERE criado_em >= CURDATE() - INTERVAL 8 WEEK GROUP BY s ORDER BY s DESC");
    $aceitos = [];
    try { $aceitos = array_column(linhas("SELECT YEARWEEK(criado_em, 3) AS s, COUNT(*) AS n FROM avisos_info WHERE criado_em >= CURDATE() - INTERVAL 8 WEEK GROUP BY s"), 'n', 's'); } catch (PDOException) {}
    if ($sem): ?>
<details class="quadro" open><summary><strong>Convite "Quer saber quando colocarem...?"</strong> (quem pede e depois aceita o aviso)</summary>
  <p class="discreto">Texto novo desde 19/11: diz a vantagem e "1 toque, sem senha" (antes de 19/11 era o texto antigo). Pedir continua sem conta.</p>
  <div class="tabela"><table><thead><tr><th>Semana de</th><th>Pedidos</th><th>Já com conta</th><th>Aceitaram o aviso</th><th>Taxa</th></tr></thead><tbody>
  <?php foreach ($sem as $l): $ac = (int)($aceitos[$l['s']] ?? 0); ?>
    <tr><td><?= h(data_br($l['de'])) ?></td><td><?= num((int)$l['pedidos']) ?></td><td><?= num((int)$l['com_conta']) ?></td><td><?= num($ac) ?></td>
      <td><?= pct($ac, (int)$l['pedidos']) ?></td></tr>
  <?php endforeach ?>
  </tbody></table></div>
  <?php // 19/11 (pedido do usuário): quem aceitou o aviso, de qual comércio e de qual informação
  $inscritos = [];
  try {
      $inscritos = linhas("SELECT ai.id, ai.tipo, ai.criado_em, ai.avisado_em, u.id AS uid, u.nome AS pessoa, u.email,
              p.id AS pid, p.nome AS comercio, p.cidade
          FROM avisos_info ai JOIN usuarios u ON u.id = ai.usuario_id JOIN profissionais p ON p.id = ai.profissional_id
          ORDER BY ai.avisado_em IS NULL DESC, ai.criado_em DESC LIMIT 200");
  } catch (PDOException) {}
  if ($inscritos): ?>
  <h3 style="margin-top:18px">Quem aceitou o aviso (<?= num(count($inscritos)) ?>)</h3>
  <p class="discreto">O aviso no celular sai <strong>sozinho</strong>, uma vez, quando a informação entra na ficha (alteração do dono
    aprovada, edição pelo admin ou lista nova). Para falar antes com a pessoa, abra o nome dela: lá dá para mandar um aviso só para ela.</p>
  <div class="tabela"><table><thead><tr><th>Pessoa</th><th>Comércio</th><th>Quer saber</th><th>Pediu em</th><th>Situação</th></tr></thead><tbody>
  <?php foreach ($inscritos as $i): ?>
    <tr><td><a href="pessoa.php?id=<?= (int)$i['uid'] ?>"><strong><?= h($i['pessoa']) ?></strong></a><br><span class="discreto"><?= h($i['email']) ?></span></td>
      <td><?= adm_comercio((int)$i['pid'], $i['comercio'], $i['cidade']) ?><br><span class="discreto"><?= adm_cidade($i['cidade'], null, true) ?></span></td>
      <td><?= h(TIPOS_PEDIDO_INFO[$i['tipo']] ?? $i['tipo']) ?></td>
      <td><?= h(datahora_br($i['criado_em'])) ?></td>
      <td><?= $i['avisado_em'] ? '✅ Avisado em ' . h(datahora_br($i['avisado_em'])) : '⏳ Esperando o comércio informar' ?></td></tr>
  <?php endforeach ?>
  </tbody></table></div>
  <?php endif ?>
</details>
<?php endif; endif ?>

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
    <?php foreach (TIPOS_PEDIDO_INFO as $n): ?><th><?= $n ?></th><?php endforeach ?><th>Total</th><th></th></tr>
  <?php foreach ($pedidos as $l): ?>
  <tr>
    <td><strong><?= adm_comercio((int)$l['id'], $l['nome'], $l['cidade']) ?></strong><br><span class="discreto"><?= h($l['tipo'] ?: $l['categoria']) ?> · <?= adm_cidade($l['cidade'], null, true) ?><?= $l['bairro'] ? ' · ' . h($l['bairro']) : '' ?></span></td>
    <td><?= $nome_plano[$l['plano_atual']] ?></td>
    <td><?= h(telefone_formatado($l['fone_whatsapp'] ?: $l['telefone']) ?: '—') ?></td>
    <?php foreach (TIPOS_PEDIDO_INFO as $k => $n): ?><td><?= (int)$l[$k] ? num((int)$l[$k]) : '' ?></td><?php endforeach ?>
    <td><strong><?= num((int)$l['total']) ?></strong></td>
    <td><a class="botao pequeno" href="relatorio.php?id=<?= (int)$l['id'] ?>&amp;dias=<?= $dias ?>" target="_blank">Relatório</a>
      <a class="botao pequeno contorno" href="numeros-ficha.php?id=<?= (int)$l['id'] ?>&amp;dias=<?= $dias ?>">Ficha</a>
      <a class="botao pequeno contorno" href="quem-pediu.php?id=<?= (int)$l['id'] ?>">Quem pediu</a></td>
  </tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>
<?php admin_fim();
