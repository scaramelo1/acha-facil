<?php
// Pendências: alterações pedidas pelos donos (nada vai ao app sem aprovação) e pedidos de assumir/tirar do app
require __DIR__ . '/../../inc/admin.php';
$admin = exigir_admin();

$alteracoes = linhas("SELECT a.id, a.criado_em, a.novos, p.id AS pid, p.nome, p.cidade, d.nome AS dono
    FROM alteracoes a JOIN profissionais p ON p.id = a.profissional_id LEFT JOIN acessos_dono d ON d.id = a.acesso_id
    WHERE a.status = 'pendente' ORDER BY a.criado_em");
$pedidos = linhas("SELECT pe.id, pe.tipo, pe.plano, pe.nome, pe.telefone, pe.criado_em, p.nome AS comercio, p.cidade
    FROM pedidos pe LEFT JOIN profissionais p ON p.id = pe.profissional_id WHERE pe.status = 'novo' ORDER BY pe.criado_em");
$acessos = linhas("SELECT d.id, d.nome, d.telefone, d.criado_em, d.ultimo_uso, p.id AS pid, p.nome AS comercio
    FROM acessos_dono d JOIN profissionais p ON p.id = d.profissional_id WHERE d.ativo = 1 ORDER BY d.criado_em DESC LIMIT 100");

admin_inicio('Pendências', $admin);
?>
<h1>Pendências</h1>
<p class="discreto">Olá, <?= h($admin['nome']) ?>. Tudo o que um dono muda fica aqui até alguém aprovar.</p>

<h2>Alterações dos donos (<?= count($alteracoes) ?>)</h2>
<?php if (!$alteracoes): ?><p class="discreto">Nenhuma alteração esperando.</p><?php else: ?>
<div class="tabela"><table>
  <tr><th>Comércio</th><th>Cidade</th><th>O que muda</th><th>Pedido por</th><th>Quando</th><th></th></tr>
  <?php foreach ($alteracoes as $a): $campos = array_map(fn($c) => CAMPOS_DONO[$c][0] ?? $c, array_keys(json_decode($a['novos'], true) ?: [])); ?>
  <tr>
    <td><strong><?= h($a['nome']) ?></strong></td>
    <td><?= h(nome_cidade($a['cidade'], true)) ?></td>
    <td><?= h(implode(', ', $campos)) ?></td>
    <td><?= h($a['dono'] ?? '?') ?></td>
    <td><?= h(datahora_br($a['criado_em'])) ?></td>
    <td><a class="botao pequeno" href="alteracao.php?id=<?= (int)$a['id'] ?>">Conferir</a></td>
  </tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>

<h2>Pedidos de donos (<?= count($pedidos) ?>)</h2>
<?php if (!$pedidos): ?><p class="discreto">Nenhum pedido novo.</p><?php else: ?>
<div class="tabela"><table>
  <tr><th>Pedido</th><th>Comércio</th><th>Quem pediu</th><th>Quando</th><th></th></tr>
  <?php foreach ($pedidos as $pe): ?>
  <tr>
    <td><?= h(TIPO_PEDIDO[$pe['tipo']] ?? $pe['tipo']) ?><?= $pe['plano'] ? ' · plano ' . h(PLANOS_NOME[$pe['plano']] ?? $pe['plano']) : '' ?></td>
    <td><?= $pe['comercio'] ? h($pe['comercio']) . ' <span class="discreto">(' . h(nome_cidade($pe['cidade'], true)) . ')</span>' : '<em>Negócio novo</em>' ?></td>
    <td><?= h($pe['nome']) ?><br><span class="discreto"><?= h(telefone_formatado($pe['telefone'])) ?></span></td>
    <td><?= h(datahora_br($pe['criado_em'])) ?></td>
    <td><a class="botao pequeno" href="pedido.php?id=<?= (int)$pe['id'] ?>">Abrir</a></td>
  </tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>

<h2>Donos com acesso ao painel (<?= count($acessos) ?>)</h2>
<?php if (!$acessos): ?><p class="discreto">Ninguém ainda.</p><?php else: ?>
<div class="tabela"><table>
  <tr><th>Comércio</th><th>Dono</th><th>Liberado em</th><th>Último uso</th><th></th></tr>
  <?php foreach ($acessos as $d): ?>
  <tr>
    <td><?= h($d['comercio']) ?></td>
    <td><?= h($d['nome']) ?><br><span class="discreto"><?= h(telefone_formatado($d['telefone'])) ?></span></td>
    <td><?= h(datahora_br($d['criado_em'])) ?></td>
    <td><?= h(datahora_br($d['ultimo_uso'])) ?></td>
    <td><form method="post" action="acesso.php" onsubmit="return confirm('Cortar o acesso deste dono ao painel?')">
      <?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
      <button class="botao pequeno contorno">Cortar acesso</button></form></td>
  </tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>
<?php admin_fim();
