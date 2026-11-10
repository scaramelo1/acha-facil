<?php
// Números de todas as fichas (passo 20): aparições, visualizações, pessoas e contatos, com filtros e CSV.
// O CSV é a lista de prospecção (passo 22).
require __DIR__ . '/../../inc/admin.php';
$admin = exigir_admin();
exigir_numeros_prontos();

$cidade = isset(TODAS_CIDADES[$_GET['cidade'] ?? '']) ? (string)$_GET['cidade'] : '';
$cat = (int)($_GET['categoria'] ?? 0);
$tipo = trim(mb_substr((string)($_GET['tipo'] ?? ''), 0, 80));
$dias = in_array((int)($_GET['dias'] ?? 30), [7, 30, 90], true) ? (int)($_GET['dias'] ?? 30) : 30;
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
$params = [$desde, $desde];
if ($cidade !== '') { $where[] = 'p.cidade = ?'; $params[] = $cidade; }
if ($cat) { $where[] = 'p.categoria_id = ?'; $params[] = $cat; }
if ($tipo !== '') { $where[] = 'p.tipo = ?'; $params[] = $tipo; }
if ($plano !== '') { $where[] = "$plano_sql = ?"; $params[] = $plano; }
if ($so_vistos) $where[] = 'COALESCE(k.visualizacoes, 0) > 0';

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
            SUM(IF(tipo = 'instagram', total, 0)) AS instagram, SUM(IF(tipo <> 'perfil', total, 0)) AS contatos
        FROM contatos WHERE dia >= ? GROUP BY profissional_id) k ON k.profissional_id = p.id
    LEFT JOIN (SELECT profissional_id, SUM(total) AS aparicoes FROM aparicoes WHERE dia >= ? GROUP BY profissional_id) a ON a.profissional_id = p.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY $ordem DESC, visualizacoes DESC, aparicoes DESC, p.nome";
$lista = linhas($sql, $params);

$soma = array_fill_keys(array_keys(COLUNAS), 0);
foreach ($lista as $l) foreach ($soma as $k => $_) $soma[$k] += (int)$l[$k];
$nome_plano = ['sem' => 'Sem plano', 'basico' => 'Básico', 'destaque' => 'Destaque'];

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
$MOSTRAR = 300;

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
    <?php foreach ([7, 30, 90] as $d): ?><option value="<?= $d ?>"<?= $d === $dias ? ' selected' : '' ?>>Últimos <?= $d ?> dias</option><?php endforeach ?>
  </select></label>
  <label>Plano <select name="plano"><option value="">Todos</option>
    <?php foreach ($nome_plano as $k => $n): ?><option value="<?= $k ?>"<?= $k === $plano ? ' selected' : '' ?>><?= $n ?></option><?php endforeach ?>
  </select></label>
  <label class="caixa"><input type="checkbox" name="vistos" value="1"<?= $so_vistos ? ' checked' : '' ?>> Só quem foi visto</label>
  <input type="hidden" name="ordem" value="<?= h($ordem) ?>">
  <button class="botao pequeno">Filtrar</button>
  <a class="botao pequeno contorno" href="<?= h($link(['csv' => 1])) ?>">Baixar CSV</a>
</form>

<div class="numeros-resumo">
  <div><strong><?= num(count($lista)) ?></strong><span>fichas</span></div>
  <div><strong><?= num($soma['aparicoes']) ?></strong><span>aparições</span></div>
  <div><strong><?= num($soma['visualizacoes']) ?></strong><span>visualizações</span></div>
  <div><strong><?= num($soma['pessoas']) ?></strong><span>pessoas</span></div>
  <div><strong><?= num($soma['contatos']) ?></strong><span>contatos (<?= pct($soma['contatos'], $soma['visualizacoes']) ?>)</span></div>
</div>

<div class="tabela"><table>
  <tr><th>Comércio</th><th>Plano</th>
    <?php foreach (COLUNAS as $k => $n): ?><th><a href="<?= h($link(['ordem' => $k])) ?>"><?= $n ?><?= $k === $ordem ? ' ▼' : '' ?></a></th><?php endforeach ?>
    <th>Taxa</th><th></th></tr>
  <?php foreach (array_slice($lista, 0, $MOSTRAR) as $l): ?>
  <tr>
    <td><strong><?= h($l['nome']) ?></strong><br><span class="discreto"><?= h($l['tipo'] ?: $l['categoria']) ?> · <?= h(nome_cidade($l['cidade'], true)) ?><?= $l['bairro'] ? ' · ' . h($l['bairro']) : '' ?></span></td>
    <td><?= $nome_plano[$l['plano_atual']] ?></td>
    <?php foreach (COLUNAS as $k => $n): ?><td><?= num((int)$l[$k]) ?></td><?php endforeach ?>
    <td><?= pct((int)$l['contatos'], (int)$l['visualizacoes']) ?></td>
    <td><a class="botao pequeno contorno" href="numeros-ficha.php?id=<?= (int)$l['id'] ?>&amp;dias=<?= $dias ?>">Ver</a></td>
  </tr>
  <?php endforeach ?>
</table></div>
<?php if (count($lista) > $MOSTRAR): ?><p class="discreto">Mostrando as <?= $MOSTRAR ?> primeiras de <?= num(count($lista)) ?>. O CSV traz todas.</p><?php endif ?>
<?php admin_fim();
