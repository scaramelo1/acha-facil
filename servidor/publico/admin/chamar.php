<?php
// Quem chamar hoje (19/11, passo 116): comércios sem dono mais vistos, com WhatsApp e mensagem prontos (inc/prospeccao.php).
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/prospeccao.php';
$admin = exigir_admin();

if (e_post()) {
    exigir_post();
    $id = (int)($_POST['id'] ?? 0);
    $acao = (string)($_POST['acao'] ?? '');
    if ($id > 0 && in_array($acao, ['chamei', 'desfazer'], true)) {
        prospeccao_marcar($id, $acao === 'chamei' ? (string)$admin['nome'] : null, (string)($_POST['como'] ?? 'whatsapp'));
        if ($acao === 'chamei') registrar((int)$admin['id'], 'chamou_comercio', $id, (string)($_POST['como'] ?? 'whatsapp'));
    }
    if (!empty($_POST['ajax'])) { header('Content-Type: application/json'); echo '{"ok":true}'; exit; }
    ir('admin/chamar.php?' . http_build_query(array_filter(['cidade' => $_POST['cidade'] ?? '', 'dias' => $_POST['dias'] ?? '', 'ver' => $_POST['ver'] ?? ''])));
}

$cidade = isset(TODAS_CIDADES[$_GET['cidade'] ?? '']) ? (string)$_GET['cidade'] : '';
$dias = in_array((int)($_GET['dias'] ?? 7), [7, 30], true) ? (int)($_GET['dias'] ?? 7) : 7;
$ver = ($_GET['ver'] ?? '') === 'chamados' ? 'chamados' : 'chamar';
$desde = desde_dias($dias);
$chamados = prospeccao_chamados();
$recentes = array_filter($chamados, fn($c) => strtotime((string)$c['em']) >= strtotime('-' . PROSPECCAO_ESCONDER_DIAS . ' days'));

$wc = $cidade !== '' ? ' AND p.cidade = ?' : '';
$ped = '';
try {
    valor('SELECT 1 FROM pedidos_info LIMIT 1');
    $ped = "LEFT JOIN (SELECT profissional_id, SUM(total) AS pedidos, SUM(IF(tipo = 'whatsapp', total, 0)) AS ped_whatsapp,
            SUM(IF(tipo = 'horario', total, 0)) AS ped_horario, SUM(IF(tipo = 'fotos', total, 0)) AS ped_fotos, SUM(IF(tipo = 'sobre', total, 0)) AS ped_sobre
        FROM pedidos_info WHERE dia >= ? GROUP BY profissional_id) pi ON pi.profissional_id = p.id";
} catch (PDOException) {}
$lista = linhas("SELECT p.id, p.nome, p.tipo, p.cidade, p.bairro, p.whatsapp, p.telefone, c.nome AS categoria,
        SUM(IF(k.tipo = 'perfil', k.total, 0)) AS vis, SUM(IF(k.tipo = 'perfil', k.pessoas, 0)) AS pessoas,
        SUM(IF(k.tipo IN ('whatsapp','ligar','mapa','site','instagram'), k.total, 0)) AS toques"
    . ($ped ? ', MAX(pi.pedidos) AS pedidos, MAX(pi.ped_whatsapp) AS ped_whatsapp, MAX(pi.ped_horario) AS ped_horario, MAX(pi.ped_fotos) AS ped_fotos, MAX(pi.ped_sobre) AS ped_sobre' : ', 0 AS pedidos') . "
    FROM contatos k JOIN profissionais p ON p.id = k.profissional_id JOIN categorias c ON c.id = p.categoria_id $ped
    WHERE k.dia >= ? AND p.status = 'ativo' AND NOT " . SQL_TEM_DONO . " AND (COALESCE(p.whatsapp, '') <> '' OR COALESCE(p.telefone, '') <> '')$wc
    GROUP BY p.id HAVING vis > 0 OR pedidos > 0
    ORDER BY (SUM(IF(k.tipo = 'perfil', k.total, 0)) + 3 * COALESCE(MAX(" . ($ped ? 'pi.pedidos' : '0') . "), 0)) DESC, toques DESC, p.nome LIMIT 300",
    array_merge($ped ? [$desde] : [], [$desde], $cidade !== '' ? [$cidade] : []));

if ($ver === 'chamados') $lista = array_values(array_filter($lista, fn($l) => isset($recentes[(int)$l['id']])));
else $lista = array_slice(array_values(array_filter($lista, fn($l) => !isset($recentes[(int)$l['id']]))), 0, 60);

$link = fn(array $m) => '?' . http_build_query(array_filter(array_merge(['cidade' => $cidade, 'dias' => $dias, 'ver' => $ver === 'chamados' ? 'chamados' : ''], $m)));

admin_inicio('Quem chamar hoje', $admin);
?>
<h1>📞 Quem chamar hoje</h1>
<p class="discreto">Comércios <strong>sem dono</strong> que mais apareceram nos últimos <?= $dias ?> dias (vistas + 3 x pedidos de informação).
  O botão <strong>WhatsApp</strong> abre a conversa com a mensagem pronta, com os números da ficha e o link para ele cadastrar grátis.
  Depois de chamar, toque em <strong>✓ Chamei</strong>: o comércio sai da lista por <?= PROSPECCAO_ESCONDER_DIAS ?> dias.</p>

<form method="get" class="filtros-numeros">
  <label>Cidade <select name="cidade"><option value="">Todas</option>
    <?php foreach (TODAS_CIDADES as $slug => $c): ?><option value="<?= h($slug) ?>"<?= $slug === $cidade ? ' selected' : '' ?>><?= h($c[0]) ?></option><?php endforeach ?>
  </select></label>
  <label>Período <select name="dias"><option value="7"<?= $dias === 7 ? ' selected' : '' ?>>Últimos 7 dias</option><option value="30"<?= $dias === 30 ? ' selected' : '' ?>>Últimos 30 dias</option></select></label>
  <?php if ($ver === 'chamados'): ?><input type="hidden" name="ver" value="chamados"><?php endif ?>
  <button class="botao pequeno">Filtrar</button>
  <?php if ($ver === 'chamados'): ?><a class="botao pequeno contorno" href="<?= h($link(['ver' => ''])) ?>">← Quem chamar</a>
  <?php else: ?><a class="botao pequeno contorno" href="<?= h($link(['ver' => 'chamados'])) ?>">Já chamados (<?= count($recentes) ?>)</a><?php endif ?>
</form>

<?php if (!$lista): ?><p class="discreto"><?= $ver === 'chamados' ? 'Ninguém chamado nos últimos ' . PROSPECCAO_ESCONDER_DIAS . ' dias.' : 'Ninguém para chamar neste filtro. 🎉' ?></p><?php else: ?>
<div class="tabela"><table class="tabela-chamar">
  <tr><th>Comércio</th><th>Vistas</th><th>Pessoas</th><th>Pedidos</th><th>Toques</th><th>Contato</th><th></th></tr>
  <?php foreach ($lista as $l):
    $cel = prospeccao_celular($l['whatsapp']) ?? prospeccao_celular($l['telefone']);
    $fone = $l['whatsapp'] ?: $l['telefone'];
    $msg = prospeccao_mensagem($l, $dias);
    $ch = $chamados[(int)$l['id']] ?? null; ?>
  <tr id="c<?= (int)$l['id'] ?>">
    <td><strong><?= adm_comercio((int)$l['id'], $l['nome'], $l['cidade']) ?></strong><br><span class="discreto"><?= h($l['tipo'] ?: $l['categoria']) ?> · <?= adm_cidade($l['cidade'], null, true) ?><?= $l['bairro'] ? ' · ' . h($l['bairro']) : '' ?></span>
      <?php if ($ch): ?><br><small class="discreto">✓ chamado em <?= h(datahora_br($ch['em'])) ?> por <?= h($ch['admin']) ?> (<?= h($ch['como']) ?>)</small><?php endif ?></td>
    <td><strong><?= num((int)$l['vis']) ?></strong></td>
    <td><?= (int)$l['pessoas'] ? num((int)$l['pessoas']) : '—' ?></td>
    <td><?= (int)$l['pedidos'] ? '<strong>' . num((int)$l['pedidos']) . '</strong>' : '—' ?></td>
    <td><?= (int)$l['toques'] ? num((int)$l['toques']) : '—' ?></td>
    <td><?= h(telefone_formatado($fone)) ?><br><small class="discreto"><?= $cel ? 'celular' : 'fixo' ?></small></td>
    <td class="acoes-chamar">
      <?php if ($cel): ?><a class="botao pequeno" href="<?= h(link_whatsapp($cel, $msg)) ?>" target="_blank" rel="noopener" data-chamar="<?= (int)$l['id'] ?>" data-como="whatsapp">WhatsApp</a><?php endif ?>
      <a class="botao pequeno contorno" href="tel:<?= h(so_digitos($fone)) ?>" data-chamar="<?= (int)$l['id'] ?>" data-como="ligacao">Ligar</a>
      <button type="button" class="botao pequeno contorno" data-copiar="<?= h($msg) ?>">Copiar mensagem</button>
      <form method="post" style="display:inline"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
        <input type="hidden" name="cidade" value="<?= h($cidade) ?>"><input type="hidden" name="dias" value="<?= $dias ?>"><input type="hidden" name="ver" value="<?= $ver === 'chamados' ? 'chamados' : '' ?>">
        <?php if ($ch): ?><button class="botao pequeno contorno" name="acao" value="desfazer">Desfazer</button>
        <?php else: ?><button class="botao pequeno contorno" name="acao" value="chamei">✓ Chamei</button><?php endif ?></form>
    </td>
  </tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>
<style>
  .tabela-chamar td { vertical-align: top; }
  .acoes-chamar { display: flex; flex-wrap: wrap; gap: 6px; min-width: 220px; }
  .acoes-chamar .feito { opacity: .55; }
</style>
<script>
// "Copiar mensagem" e: ao abrir o WhatsApp ou ligar, o botão "✓ Chamei" fica piscando para lembrar de marcar
document.querySelectorAll('[data-copiar]').forEach(function (b) {
  b.addEventListener('click', function () {
    navigator.clipboard.writeText(b.dataset.copiar).then(function () { b.textContent = 'Copiada ✓'; setTimeout(function () { b.textContent = 'Copiar mensagem'; }, 2000); });
  });
});
document.querySelectorAll('[data-chamar]').forEach(function (a) {
  a.addEventListener('click', function () {
    var tr = document.getElementById('c' + a.dataset.chamar), bt = tr && tr.querySelector('button[value="chamei"]');
    if (bt) { bt.classList.remove('contorno'); bt.textContent = '✓ Chamei (marcar)'; }
    a.classList.add('feito');
  });
});
</script>
<?php
admin_fim();
