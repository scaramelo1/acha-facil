<?php
// Pessoas cadastradas no app (passo 40): conta pelo Google com nome, e-mail e foto. Só leitura, com busca e CSV.
require __DIR__ . '/../../inc/admin.php';
$admin = exigir_admin();
require_once __DIR__ . '/../../inc/avisos.php';

// 17/11 (pedido do usuário): notificação para pessoas escolhidas (marcar na lista e mandar)
if (e_post() && ($_POST['acao'] ?? '') === 'notificar_marcadas') {
    exigir_post();
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
    $titulo = trim((string)($_POST['titulo'] ?? ''));
    $texto = trim((string)($_POST['texto'] ?? ''));
    if (!$ids) flash('Marque pelo menos uma pessoa na lista.', 'erro');
    elseif ($titulo === '' || $texto === '') flash('Preencha o título e o texto.', 'erro');
    elseif (count($ids) > 200) flash('São no máximo 200 pessoas por vez. Para mais gente, use Notificações (por cidade).', 'erro');
    else {
        $entregues = $pessoas_ok = 0; $sem = [];
        foreach (linhas('SELECT id, nome FROM usuarios WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids) as $u) {
            $n = avisar_usuario((int)$u['id'], mb_substr($titulo, 0, 80), mb_substr($texto, 0, 180));
            $entregues += $n;
            if ($n) $pessoas_ok++; else $sem[] = $u['nome'];
        }
        registrar((int)$admin['id'], 'notificou_pessoas', null, mb_substr($titulo, 0, 60) . " ($pessoas_ok de " . count($ids) . " pessoas, $entregues aparelho(s))");
        flash("Notificação entregue para $pessoas_ok de " . count($ids) . ' pessoa(s) (' . $entregues . ' celular(es)).'
            . ($sem ? ' Sem o app com avisos ligados: ' . implode(', ', array_slice($sem, 0, 15)) . (count($sem) > 15 ? '…' : '') . '.' : ''), $pessoas_ok ? 'ok' : 'erro');
    }
    ir('admin/pessoas.php?' . http_build_query(array_filter(['q' => $_POST['q'] ?? '', 'ordem' => $_POST['ordem'] ?? ''])));
}

$busca = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 80));
$ordem = ($_GET['ordem'] ?? '') === 'uso' ? 'uso' : 'novos';
$where = '1';
$params = [];
if ($busca !== '') { $where = '(u.nome LIKE ? OR u.email LIKE ?)'; $params = ["%$busca%", "%$busca%"]; }
$lista = linhas("SELECT u.id, u.nome, u.email, u.foto, u.google_id, u.ativo, u.criado_em,
        (SELECT MAX(s.usado_em) FROM sessoes_app s WHERE s.usuario_id = u.id) AS ultimo_uso,
        (SELECT COUNT(*) FROM favoritos f WHERE f.usuario_id = u.id) AS favoritos,
        (SELECT COUNT(*) FROM avaliacoes a WHERE a.usuario_id = u.id) AS avaliacoes,
        EXISTS(SELECT 1 FROM admins d WHERE d.email = u.email AND d.ativo = 1) AS e_admin
    FROM usuarios u WHERE $where
    ORDER BY " . ($ordem === 'uso' ? 'ultimo_uso IS NULL, ultimo_uso DESC' : 'u.criado_em DESC') . ', u.id DESC', $params);

if (!empty($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="acha-facil-pessoas-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // BOM: o Excel abre com acentos
    fputcsv($out, ['ID', 'Nome', 'E-mail', 'Cadastro', 'Último uso do app', 'Favoritos', 'Avaliações', 'Conta Google', 'Ativa'], ';');
    foreach ($lista as $u) fputcsv($out, [$u['id'], $u['nome'], $u['email'], data_br($u['criado_em']), data_br($u['ultimo_uso']),
        $u['favoritos'], $u['avaliacoes'], $u['google_id'] ? 'sim' : 'não', $u['ativo'] ? 'sim' : 'não'], ';');
    exit;
}

// quem pode receber (celular com o app e avisos ligados nesta conta)
$com_app = [];
foreach (linhas('SELECT usuario_id, COUNT(*) AS n FROM avisos_aparelhos WHERE usuario_id IS NOT NULL GROUP BY usuario_id') as $l) $com_app[(int)$l['usuario_id']] = (int)$l['n'];
try { foreach (linhas('SELECT usuario_id, COUNT(*) AS n FROM avisos_publico WHERE usuario_id IS NOT NULL GROUP BY usuario_id') as $l)
    $com_app[(int)$l['usuario_id']] = ($com_app[(int)$l['usuario_id']] ?? 0) + (int)$l['n']; } catch (PDOException) {}
$total = (int)valor('SELECT COUNT(*) FROM usuarios');
$semana = (int)valor('SELECT COUNT(*) FROM usuarios WHERE criado_em >= CURDATE() - INTERVAL 6 DAY');
$ativos = (int)valor('SELECT COUNT(DISTINCT usuario_id) FROM sessoes_app WHERE usado_em >= CURDATE() - INTERVAL 29 DAY');
// estatística dos itens (passo 41): comércios mais salvos e mais avaliados por todas as pessoas
$mais_salvos = linhas('SELECT p.id, p.nome, p.tipo, p.cidade, c.nome AS categoria, COUNT(*) AS n FROM favoritos f
    JOIN profissionais p ON p.id = f.profissional_id JOIN categorias c ON c.id = p.categoria_id GROUP BY p.id ORDER BY n DESC, p.nome LIMIT 10');
$mais_avaliados = linhas('SELECT p.id, p.nome, p.tipo, p.cidade, c.nome AS categoria, COUNT(*) AS n, AVG(a.estrelas) AS media FROM avaliacoes a
    JOIN profissionais p ON p.id = a.profissional_id JOIN categorias c ON c.id = p.categoria_id GROUP BY p.id ORDER BY n DESC, media DESC, p.nome LIMIT 10');
$areas_salvas = linhas('SELECT c.nome, COUNT(*) AS n FROM favoritos f JOIN profissionais p ON p.id = f.profissional_id
    JOIN categorias c ON c.id = p.categoria_id GROUP BY c.id ORDER BY n DESC LIMIT 10');
$tot_fav = (int)valor('SELECT COUNT(*) FROM favoritos');
$tot_aval = (int)valor('SELECT COUNT(*) FROM avaliacoes');
$media_geral = $tot_aval ? (float)valor('SELECT AVG(estrelas) FROM avaliacoes') : 0;
$iniciais = fn(string $n) => mb_strtoupper(mb_substr(trim($n) ?: '?', 0, 1));

admin_inicio('Pessoas', $admin);
?>
<h1>Pessoas cadastradas</h1>
<p class="discreto">Quem criou conta no app (entrar com o Google). Dados só para a administração: não repasse a terceiros (LGPD).
  Quem usa o app sem conta não aparece aqui.</p>

<div class="numeros-resumo">
  <div><strong><?= num($total) ?></strong><span>pessoas</span></div>
  <div><strong><?= num($semana) ?></strong><span>novas em 7 dias</span></div>
  <div><strong><?= num($ativos) ?></strong><span>usaram o app em 30 dias</span></div>
  <div><strong><?= num($tot_fav) ?></strong><span>favoritos salvos</span></div>
  <div><strong><?= num($tot_aval) ?></strong><span>avaliações (média <?= $tot_aval ? number_format($media_geral, 1, ',', '.') : '—' ?>)</span></div>
</div>

<form method="get" class="filtros-numeros">
  <label>Procurar <input name="q" value="<?= h($busca) ?>" placeholder="nome ou e-mail"></label>
  <label>Ordem <select name="ordem">
    <option value="novos"<?= $ordem === 'novos' ? ' selected' : '' ?>>Cadastro mais novo</option>
    <option value="uso"<?= $ordem === 'uso' ? ' selected' : '' ?>>Usou o app por último</option>
  </select></label>
  <button class="botao pequeno">Filtrar</button>
  <a class="botao pequeno contorno" href="?<?= h(http_build_query(array_filter(['q' => $busca, 'ordem' => $ordem, 'csv' => 1]))) ?>">Baixar CSV</a>
</form>

<?php if (!$lista): ?><p class="discreto">Ninguém encontrado.</p><?php else: ?>
<form method="post" id="form-marcadas">
<?= campo_csrf() ?><input type="hidden" name="acao" value="notificar_marcadas">
<input type="hidden" name="q" value="<?= h($busca) ?>"><input type="hidden" name="ordem" value="<?= h($ordem) ?>">
<div class="quadro" id="notificar">
  <strong>Mandar notificação para pessoas escolhidas</strong>
  <p class="discreto" style="margin:.3em 0">Marque as pessoas na lista abaixo (📱 = recebe no celular). Só chega para quem tem o app com os avisos ligados nesta conta.</p>
  <label for="m-titulo">Título</label>
  <input id="m-titulo" name="titulo" maxlength="80" placeholder="Ex.: Novidade para você">
  <label for="m-texto">Texto</label>
  <textarea id="m-texto" name="texto" rows="2" maxlength="180" placeholder="Ex.: Agora dá para ver os comércios de Monte Verde no app."></textarea>
  <p><button class="botao" id="m-enviar">Mandar para as marcadas (<span id="m-qtd">0</span>)</button>
    <button type="button" class="botao contorno pequeno" id="m-todas-app">Marcar todas com 📱</button>
    <button type="button" class="botao contorno pequeno" id="m-nenhuma">Desmarcar</button></p>
</div>
<div class="tabela"><table class="pessoas">
  <tr><th><input type="checkbox" id="m-todas" aria-label="Marcar todas"></th><th></th><th>Nome</th><th>E-mail</th><th>Cadastro</th><th>Último uso</th><th>Favoritos</th><th>Avaliações</th></tr>
  <?php foreach ($lista as $u): ?>
  <tr<?= $u['ativo'] ? '' : ' class="discreto"' ?>>
    <td><label style="white-space:nowrap"><input type="checkbox" name="ids[]" value="<?= (int)$u['id'] ?>" class="m-pessoa"<?= isset($com_app[(int)$u['id']]) ? ' data-app="1"' : '' ?>>
      <?= isset($com_app[(int)$u['id']]) ? '<span title="Recebe no celular">📱</span>' : '' ?></label></td>
    <td><?php if ($u['foto'] && str_starts_with($u['foto'], 'https://')): ?>
      <img class="pessoa-foto" src="<?= h($u['foto']) ?>" alt="" width="44" height="44" loading="lazy" referrerpolicy="no-referrer"
        onerror="this.replaceWith(Object.assign(document.createElement('span'), {className: 'pessoa-foto pessoa-inicial', textContent: <?= h(json_encode($iniciais($u['nome']))) ?>}))">
    <?php else: ?><span class="pessoa-foto pessoa-inicial"><?= h($iniciais($u['nome'])) ?></span><?php endif ?></td>
    <td><a href="pessoa.php?id=<?= (int)$u['id'] ?>"><strong><?= h($u['nome']) ?></strong></a><?= $u['e_admin'] ? ' <span class="discreto">(admin)</span>' : '' ?><?= $u['ativo'] ? '' : ' <span class="discreto">(desativada)</span>' ?></td>
    <td><a href="mailto:<?= h($u['email']) ?>"><?= h($u['email']) ?></a></td>
    <td><?= h(data_br($u['criado_em'])) ?></td>
    <td><?= h(data_br($u['ultimo_uso'])) ?></td>
    <td><?= $u['favoritos'] ? '<a href="pessoa.php?id=' . (int)$u['id'] . '#favoritos">' . num((int)$u['favoritos']) . '</a>' : '0' ?></td>
    <td><?= $u['avaliacoes'] ? '<a href="pessoa.php?id=' . (int)$u['id'] . '#avaliacoes">' . num((int)$u['avaliacoes']) . '</a>' : '0' ?></td>
  </tr>
  <?php endforeach ?>
</table></div>
</form>
<script>
(function () {
  var caixas = [].slice.call(document.querySelectorAll('.m-pessoa')), qtd = document.getElementById('m-qtd');
  function contar() { qtd.textContent = caixas.filter(function (c) { return c.checked; }).length; }
  caixas.forEach(function (c) { c.addEventListener('change', contar); });
  document.getElementById('m-todas').addEventListener('change', function () { var v = this.checked; caixas.forEach(function (c) { c.checked = v; }); contar(); });
  document.getElementById('m-todas-app').addEventListener('click', function () { caixas.forEach(function (c) { c.checked = !!c.dataset.app; }); contar(); });
  document.getElementById('m-nenhuma').addEventListener('click', function () { caixas.forEach(function (c) { c.checked = false; }); document.getElementById('m-todas').checked = false; contar(); });
  document.getElementById('form-marcadas').addEventListener('submit', function (e) {
    var n = parseInt(qtd.textContent, 10), t = document.getElementById('m-titulo').value.trim(), x = document.getElementById('m-texto').value.trim();
    if (!n) { e.preventDefault(); alert('Marque pelo menos uma pessoa na lista.'); return; }
    if (!t || !x) { e.preventDefault(); alert('Preencha o título e o texto.'); return; }
    if (!confirm('Mandar "' + t + '" para ' + n + ' pessoa(s)?')) e.preventDefault();
  });
})();
</script>
<?php endif ?>

<?php if ($mais_salvos || $mais_avaliados): ?>
<h2>Estatística dos itens</h2>
<?php if ($mais_salvos): ?>
<div class="tabela"><table>
  <tr><th>Mais salvos como favorito</th><th>Pessoas</th><th></th></tr>
  <?php foreach ($mais_salvos as $l): ?><tr><td><strong><?= adm_comercio((int)$l['id'], $l['nome'], $l['cidade']) ?></strong><br><span class="discreto"><?= h($l['tipo'] ?: $l['categoria']) ?> · <?= adm_cidade($l['cidade'], null, true) ?></span></td>
    <td><?= num((int)$l['n']) ?></td><td><a class="botao pequeno contorno" href="numeros-ficha.php?id=<?= (int)$l['id'] ?>">Números</a></td></tr><?php endforeach ?>
</table></div>
<?php endif ?>
<?php if ($areas_salvas): ?><p class="discreto">Favoritos por área: <?= h(implode(' · ', array_map(fn($l) => $l['nome'] . ' ' . $l['n'], $areas_salvas))) ?></p><?php endif ?>
<?php if ($mais_avaliados): ?>
<div class="tabela"><table>
  <tr><th>Mais avaliados</th><th>Avaliações</th><th>Média</th><th></th></tr>
  <?php foreach ($mais_avaliados as $l): ?><tr><td><strong><?= adm_comercio((int)$l['id'], $l['nome'], $l['cidade']) ?></strong><br><span class="discreto"><?= h($l['tipo'] ?: $l['categoria']) ?> · <?= adm_cidade($l['cidade'], null, true) ?></span></td>
    <td><?= num((int)$l['n']) ?></td><td><?= number_format((float)$l['media'], 1, ',', '.') ?> ★</td><td><a class="botao pequeno contorno" href="numeros-ficha.php?id=<?= (int)$l['id'] ?>">Números</a></td></tr><?php endforeach ?>
</table></div>
<?php endif ?>
<?php endif ?>
<style>
.pessoa-foto { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; display: inline-flex; align-items: center; justify-content: center; vertical-align: middle; }
.pessoa-inicial { background: var(--verde); color: #fff; font-weight: 900; font-size: 1.2rem; }
</style>
<?php admin_fim();
