<?php
// Procurar um comércio (nome, telefone, endereço ou nº) para editar em /admin/comercio.php.
require __DIR__ . '/../../inc/admin.php';
$admin = exigir_admin();
$q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 80));
$cidade = (string)($_GET['cidade'] ?? '');
if (!isset(TODAS_CIDADES[$cidade])) $cidade = '';
$lista = [];
if ($q !== '') {
    $digitos = preg_replace('/\D/', '', $q);
    $onde = ['(p.nome LIKE ? OR p.endereco LIKE ? OR p.tipo LIKE ?' . (strlen($digitos) >= 4 ? ' OR p.telefone LIKE ? OR p.whatsapp LIKE ?' : '') . (ctype_digit($q) ? ' OR p.id = ?' : '') . ')'];
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $par = [$like, $like, $like];
    if (strlen($digitos) >= 4) array_push($par, "%$digitos%", "%$digitos%");
    if (ctype_digit($q)) $par[] = (int)$q;
    if ($cidade !== '') { $onde[] = 'p.cidade = ?'; $par[] = $cidade; }
    $lista = linhas('SELECT p.id, p.nome, p.tipo, p.cidade, p.bairro, p.status, p.telefone, p.whatsapp, p.importado, c.nome AS area
        FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE ' . implode(' AND ', $onde) . '
        ORDER BY p.status = \'ativo\' DESC, p.nome LIMIT 100', $par);
}
$ultimos = linhas("SELECT r.profissional_id AS id, MAX(r.criado_em) AS quando, p.nome, p.cidade FROM registro_admin r JOIN profissionais p ON p.id = r.profissional_id
    WHERE r.acao = 'editou_comercio' GROUP BY r.profissional_id ORDER BY quando DESC LIMIT 15");

admin_inicio('Comércios', $admin);
?>
<h1>Comércios</h1>
<p class="discreto">Procure pelo nome, telefone, endereço, tipo ou número da ficha. Na ficha do site e do app, o lápis ✏️
  (só aparece para admins) também abre a edição.</p>
<form method="get" class="busca-admin">
  <input name="q" value="<?= h($q) ?>" placeholder="Nome, telefone, endereço ou nº" autofocus>
  <select name="cidade"><option value="">Todas as cidades</option>
    <?php foreach (TODAS_CIDADES as $slug => $c): ?><option value="<?= h($slug) ?>"<?= $cidade === $slug ? ' selected' : '' ?>><?= h($c[0]) ?></option><?php endforeach ?>
  </select>
  <button class="botao">Procurar</button>
</form>

<?php if ($q !== ''): ?>
  <h2><?= count($lista) ?><?= count($lista) === 100 ? '+' : '' ?> encontrado(s)</h2>
  <?php if ($lista): ?>
  <div class="tabela"><table>
    <tr><th>Comércio</th><th>Cidade</th><th>Contato</th><th>Situação</th><th></th></tr>
    <?php foreach ($lista as $p): ?>
    <tr>
      <td><strong><?= adm_comercio((int)$p['id'], $p['nome'], $p['cidade']) ?></strong><br><span class="discreto">Nº <?= (int)$p['id'] ?> · <?= h($p['tipo'] ?: $p['area']) ?></span></td>
      <td><?= adm_cidade($p['cidade'], null, true) ?><?= $p['bairro'] ? '<br><span class="discreto">' . h($p['bairro']) . '</span>' : '' ?></td>
      <td><?= h(telefone_formatado($p['whatsapp'] ?: $p['telefone']) ?: '-') ?></td>
      <td><?= h($p['status']) ?></td>
      <td><a class="botao pequeno" href="comercio.php?id=<?= (int)$p['id'] ?>">✏️ Editar</a></td>
    </tr>
    <?php endforeach ?>
  </table></div>
  <?php endif ?>
<?php endif ?>

<?php if ($ultimos): ?>
<h2>Editados pela equipe recentemente</h2>
<div class="tabela"><table>
  <tr><th>Comércio</th><th>Cidade</th><th>Quando</th><th></th></tr>
  <?php foreach ($ultimos as $p): ?>
  <tr><td><?= adm_comercio((int)$p['id'], $p['nome'], $p['cidade']) ?></td><td><?= adm_cidade($p['cidade'], null, true) ?></td><td><?= h(datahora_br($p['quando'])) ?></td>
    <td><a class="botao pequeno" href="comercio.php?id=<?= (int)$p['id'] ?>">✏️ Editar</a></td></tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>
<?php admin_fim();
