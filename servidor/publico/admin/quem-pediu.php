<?php
// Quem tocou em "Quero saber..." (passo 68). Com conta mostra a pessoa (link para /admin/pessoa.php); sem conta, anônimo.
// ?id= mostra só um comércio. Embaixo, quem pediu para ser avisado (avisos_info, passo 65: sempre com conta).
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/numeros.php';
require_once __DIR__ . '/../../inc/avisos-info.php';
$admin = exigir_admin();
$id = (int)($_GET['id'] ?? 0);
$so = ($_GET['so'] ?? '') === 'conta';
$p = $id ? linha('SELECT id, nome, cidade FROM profissionais WHERE id = ?', [$id]) : null;
if ($id && !$p) { flash('Comércio não encontrado.', 'erro'); ir('admin/quem-pediu.php'); }

$lista = $resumo = [];
if (pedidos_info_pessoas_prontos()) {
    $onde = ['1 = 1']; $par = [];
    if ($p) { $onde[] = 'q.profissional_id = ?'; $par[] = $id; }
    if ($so) $onde[] = 'q.usuario_id IS NOT NULL';
    $lista = linhas('SELECT q.*, p.nome AS comercio, p.cidade, u.nome AS pessoa, u.email, u.foto
        FROM pedidos_info_pessoas q JOIN profissionais p ON p.id = q.profissional_id LEFT JOIN usuarios u ON u.id = q.usuario_id
        WHERE ' . implode(' AND ', $onde) . ' ORDER BY q.id DESC LIMIT 300', $par);
    $resumo = linha('SELECT COUNT(*) AS total, SUM(usuario_id IS NOT NULL) AS com_conta FROM pedidos_info_pessoas q WHERE '
        . ($p ? 'q.profissional_id = ?' : '1 = 1'), $p ? [$id] : []);
}
// datas de todos os pedidos (17/11, pedido do usuário): vêm da contagem por dia, então mostram também os de antes de 16/11
$datas = pedidos_info_prontos() ? linhas('SELECT d.profissional_id, d.dia, d.tipo, d.origem, d.total, p.nome AS comercio, p.cidade
    FROM pedidos_info d JOIN profissionais p ON p.id = d.profissional_id' . ($p ? ' WHERE d.profissional_id = ?' : '')
    . ' ORDER BY d.dia DESC, p.nome, d.tipo LIMIT 300', $p ? [$id] : []) : [];
$avisos = avisos_info_prontos() ? linhas('SELECT a.*, p.nome AS comercio, p.cidade, u.nome AS pessoa, u.email
    FROM avisos_info a JOIN profissionais p ON p.id = a.profissional_id JOIN usuarios u ON u.id = a.usuario_id'
    . ($p ? ' WHERE a.profissional_id = ?' : '') . ' ORDER BY a.id DESC LIMIT 200', $p ? [$id] : []) : [];

$pessoa = function (array $l): string {
    if (!$l['usuario_id']) return '<span class="discreto">Anônimo (sem cadastro)</span>';
    if ($l['pessoa'] === null) return '<span class="discreto">Conta excluída</span>';
    return '<a href="pessoa.php?id=' . (int)$l['usuario_id'] . '"><strong>' . h($l['pessoa']) . '</strong></a><br><span class="discreto">' . h($l['email']) . '</span>';
};
$comercio = fn(array $l) => adm_comercio((int)$l['profissional_id'], $l['comercio'], $l['cidade']) . ' <a class="discreto" href="numeros-ficha.php?id=' . (int)$l['profissional_id'] . '">(números)</a><br><span class="discreto">' . adm_cidade($l['cidade'], null, true) . '</span>';

admin_inicio('Quem pediu informação', $admin);
?>
<p><a href="pedidos-info.php">&larr; Pedidos de informação</a><?php if ($p): ?> · <a href="quem-pediu.php">todos os comércios</a><?php endif ?></p>
<h1>Quem pediu informação<?= $p ? ': ' . adm_comercio((int)$p['id'], $p['nome'], $p['cidade'] ?? null) : '' ?></h1>
<p class="discreto">Toques em "Quero saber o horário / ver fotos / saber mais / o WhatsApp". Conta 1 por pessoa, comércio e
  tipo no dia. Quem estava com a conta Google entrada (no app ou no site) aparece com o nome; quem não tinha, como anônimo.</p>

<h2>Datas dos pedidos</h2>
<?php if (!$datas): ?><p class="discreto">Nenhum pedido<?= $p ? ' para este comércio' : '' ?>.</p><?php else: ?>
<div class="tabela"><table>
  <tr><th>Data</th><th>Pediu</th><?php if (!$p): ?><th>Comércio</th><?php endif ?><th>Onde</th><th>Vezes</th></tr>
  <?php foreach ($datas as $l): ?><tr>
    <td><?= h(data_br($l['dia'])) ?></td>
    <td><?= h(TIPOS_PEDIDO_INFO[$l['tipo']] ?? $l['tipo']) ?></td>
    <?php if (!$p): ?><td><?= $comercio($l) ?></td><?php endif ?>
    <td><?= $l['origem'] === 'web' ? 'Site' : 'App' ?></td>
    <td><?= num((int)$l['total']) ?></td>
  </tr><?php endforeach ?>
</table></div>
<?php endif ?>

<h2>Quem pediu</h2>

<?php if (!pedidos_info_pessoas_prontos()): ?>
  <div class="aviso erro">Falta aplicar a mudança 030 (rode o instalar.sh). Até lá só a contagem é guardada, sem saber quem pediu.</div>
<?php else: ?>
  <p><strong><?= num((int)$resumo['total']) ?></strong> pedidos guardados · <strong><?= num((int)$resumo['com_conta']) ?></strong> com cadastro ·
    <strong><?= num((int)$resumo['total'] - (int)$resumo['com_conta']) ?></strong> anônimos
    · <?= $so ? '<a href="?id=' . ($p ? $id : '') . '">mostrar todos</a>' : '<a href="?so=conta' . ($p ? '&id=' . $id : '') . '">só com cadastro</a>' ?></p>
  <?php if (!$lista): ?><p class="discreto">Nenhum nome guardado ainda: quem pediu só é guardado desde 16/11 (as datas de antes estão acima).</p><?php else: ?>
  <div class="tabela"><table>
    <tr><th>Quando</th><th>Quem</th><th>Pediu</th><?php if (!$p): ?><th>Comércio</th><?php endif ?><th>Onde</th></tr>
    <?php foreach ($lista as $l): ?><tr>
      <td><?= h(datahora_br($l['criado_em'])) ?></td>
      <td><?= $pessoa($l) ?></td>
      <td><?= h(TIPOS_PEDIDO_INFO[$l['tipo']] ?? $l['tipo']) ?></td>
      <?php if (!$p): ?><td><?= $comercio($l) ?></td><?php endif ?>
      <td><?= $l['origem'] === 'web' ? 'Site' : 'App' ?></td>
    </tr><?php endforeach ?>
  </table></div>
  <?php endif ?>
<?php endif ?>

<?php if ($avisos): ?>
<h2>Pediram para ser avisados</h2>
<p class="discreto">Disseram "sim" no "Quer ser avisado?" (sempre com conta). Recebem a notificação quando a informação entra na ficha.</p>
<div class="tabela"><table>
  <tr><th>Quando</th><th>Quem</th><th>Quer saber</th><?php if (!$p): ?><th>Comércio</th><?php endif ?><th>Avisado</th></tr>
  <?php foreach ($avisos as $a): ?><tr>
    <td><?= h(datahora_br($a['criado_em'])) ?></td>
    <td><?= $pessoa($a) ?></td>
    <td><?= h(TIPOS_PEDIDO_INFO[$a['tipo']] ?? $a['tipo']) ?></td>
    <?php if (!$p): ?><td><?= $comercio($a) ?></td><?php endif ?>
    <td><?= $a['avisado_em'] ? h(datahora_br($a['avisado_em'])) : '<span class="discreto">ainda não</span>' ?></td>
  </tr><?php endforeach ?>
</table></div>
<?php endif ?>
<?php admin_fim();
