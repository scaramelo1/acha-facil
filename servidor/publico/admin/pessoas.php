<?php
// Pessoas cadastradas no app (passo 40): conta pelo Google com nome, e-mail e foto. Só leitura, com busca e CSV.
require __DIR__ . '/../../inc/admin.php';
$admin = exigir_admin();

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

$total = (int)valor('SELECT COUNT(*) FROM usuarios');
$semana = (int)valor('SELECT COUNT(*) FROM usuarios WHERE criado_em >= CURDATE() - INTERVAL 6 DAY');
$ativos = (int)valor('SELECT COUNT(DISTINCT usuario_id) FROM sessoes_app WHERE usado_em >= CURDATE() - INTERVAL 29 DAY');
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
<div class="tabela"><table class="pessoas">
  <tr><th></th><th>Nome</th><th>E-mail</th><th>Cadastro</th><th>Último uso</th><th>Favoritos</th><th>Avaliações</th></tr>
  <?php foreach ($lista as $u): ?>
  <tr<?= $u['ativo'] ? '' : ' class="discreto"' ?>>
    <td><?php if ($u['foto'] && str_starts_with($u['foto'], 'https://')): ?>
      <img class="pessoa-foto" src="<?= h($u['foto']) ?>" alt="" width="44" height="44" loading="lazy" referrerpolicy="no-referrer">
    <?php else: ?><span class="pessoa-foto pessoa-inicial"><?= h($iniciais($u['nome'])) ?></span><?php endif ?></td>
    <td><strong><?= h($u['nome']) ?></strong><?= $u['e_admin'] ? ' <span class="discreto">(admin)</span>' : '' ?><?= $u['ativo'] ? '' : ' <span class="discreto">(desativada)</span>' ?></td>
    <td><a href="mailto:<?= h($u['email']) ?>"><?= h($u['email']) ?></a></td>
    <td><?= h(data_br($u['criado_em'])) ?></td>
    <td><?= h(data_br($u['ultimo_uso'])) ?></td>
    <td><?= num((int)$u['favoritos']) ?></td>
    <td><?= num((int)$u['avaliacoes']) ?></td>
  </tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>
<style>
.pessoa-foto { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; display: inline-flex; align-items: center; justify-content: center; vertical-align: middle; }
.pessoa-inicial { background: var(--verde); color: #fff; font-weight: 900; font-size: 1.2rem; }
</style>
<?php admin_fim();
