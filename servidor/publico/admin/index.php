<?php
// Pendências: alterações pedidas pelos donos (nada vai ao app sem aprovação) e pedidos de assumir/tirar do app
require __DIR__ . '/../../inc/admin.php';
$admin = exigir_admin();

// apagar uma cidade da lista do "Quero minha Cidade!" (todos os pedidos dela)
if (e_post() && ($_POST['acao'] ?? '') === 'apagar_cidade') {
    exigir_post();
    $cid = (string)($_POST['cidade'] ?? '');
    $uf = (string)($_POST['uf'] ?? '');
    $n = q('DELETE FROM cidades_pedidas WHERE LOWER(cidade) = LOWER(?) AND uf <=> ?', [$cid, $uf === '' ? null : $uf])->rowCount();
    registrar((int)$admin['id'], 'apagou_cidade', null, "$cid" . ($uf ? " ($uf)" : '') . ": $n pedido(s)");
    flash("$cid saiu da lista.");
    ir('admin/');
}
// pedido "visto, aguardando retorno" (passo 89): sai da contagem sem resolver nem recusar
require_once __DIR__ . '/../../inc/pedidos-vistos.php';
if (e_post() && in_array($_POST['acao'] ?? '', ['visto', 'desfazer_visto'], true)) {
    exigir_post();
    $pid_ped = (int)($_POST['id'] ?? 0);
    pedido_marcar_visto($pid_ped, $_POST['acao'] === 'visto');
    registrar((int)$admin['id'], $_POST['acao'] === 'visto' ? 'pedido_visto' : 'pedido_visto_desfeito', null, 'pedido ' . $pid_ped);
    flash($_POST['acao'] === 'visto' ? 'Pedido marcado como visto. Ele fica em "Vistos, aguardando retorno" e volta sozinho se for pago.' : 'Pedido voltou para as pendências.');
    ir('admin/');
}

$alteracoes = linhas("SELECT a.id, a.criado_em, a.novos, p.id AS pid, p.nome, p.cidade, d.nome AS dono
    FROM alteracoes a JOIN profissionais p ON p.id = a.profissional_id LEFT JOIN acessos_dono d ON d.id = a.acesso_id
    WHERE a.status = 'pendente' ORDER BY a.criado_em");
$todos_pedidos = linhas("SELECT pe.id, pe.tipo, pe.plano, pe.mp_status, pe.status, pe.nome, pe.telefone, pe.criado_em, p.nome AS comercio, p.cidade, p.id AS pid
    FROM pedidos pe LEFT JOIN profissionais p ON p.id = pe.profissional_id WHERE pe.status = 'novo' ORDER BY pe.criado_em");
$pedidos = array_values(array_filter($todos_pedidos, 'pedido_pendente'));
$vistos = array_values(array_filter($todos_pedidos, fn($pe) => !pedido_pendente($pe)));
$cidades = linhas('SELECT MIN(cidade) AS cidade, uf, COUNT(*) AS total, MAX(criado_em) AS ultimo FROM cidades_pedidas
    GROUP BY LOWER(cidade), uf ORDER BY total DESC, ultimo DESC LIMIT 50');
$acessos = linhas("SELECT d.id, d.nome, d.telefone, d.criado_em, d.ultimo_uso, p.id AS pid, p.nome AS comercio
    FROM acessos_dono d JOIN profissionais p ON p.id = d.profissional_id WHERE d.ativo = 1 ORDER BY d.criado_em DESC LIMIT 100");

admin_inicio('Pendências', $admin);
?>
<h1>Pendências</h1>
<p class="discreto">Olá, <?= h($admin['nome']) ?>. Tudo o que um dono muda fica aqui até alguém aprovar.</p>

<?php require_once __DIR__ . '/../../inc/eventos.php'; if ($ev_pend = eventos_pendentes_contar()): ?>
<div class="aviso ok"><strong><?= $ev_pend ?> <?= $ev_pend === 1 ? 'evento enviado' : 'eventos enviados' ?> pelo público esperando autorização.</strong>
  <a class="botao" href="eventos.php" style="margin-left:8px">Ver e autorizar</a></div>
<?php endif ?>
<?php require_once __DIR__ . '/../../inc/ofertas.php'; if (ofertas_prontas() && ($of_pend = (int)valor("SELECT COUNT(*) FROM ofertas WHERE status = 'em_analise'"))): ?>
<div class="aviso ok"><strong><?= $of_pend ?> <?= $of_pend === 1 ? 'oferta paga esperando' : 'ofertas pagas esperando' ?> conferência.</strong>
  <a class="botao" href="ofertas.php" style="margin-left:8px">Conferir</a></div>
<?php endif ?>
<h2>Alterações dos donos (<?= count($alteracoes) ?>)</h2>
<?php if (!$alteracoes): ?><p class="discreto">Nenhuma alteração esperando.</p><?php else: ?>
<div class="tabela"><table>
  <tr><th>Comércio</th><th>Cidade</th><th>O que muda</th><th>Pedido por</th><th>Quando</th><th></th></tr>
  <?php foreach ($alteracoes as $a): $campos = array_map(fn($c) => CAMPOS_DONO[$c][0] ?? $c, array_keys(json_decode($a['novos'], true) ?: [])); ?>
  <tr>
    <td><strong><?= adm_comercio((int)$a['pid'], $a['nome'], $a['cidade']) ?></strong></td>
    <td><?= adm_cidade($a['cidade'], null, true) ?></td>
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
    <td><?= h(TIPO_PEDIDO[$pe['tipo']] ?? $pe['tipo']) ?><?= $pe['plano'] ? ' · plano ' . h(PLANOS_NOME[$pe['plano']] ?? $pe['plano']) : '' ?><?= in_array($pe['mp_status'], ['authorized', 'approved'], true) ? ' <span class="contador">pago</span>' : ($pe['plano'] ? ' <span class="discreto">(não pago)</span>' : '') ?></td>
    <td><?= $pe['comercio'] ? adm_comercio((int)$pe['pid'], $pe['comercio'], $pe['cidade']) . ' <span class="discreto">(' . adm_cidade($pe['cidade'], null, true) . ')</span>' : '<em>Negócio novo</em>' ?></td>
    <td><?= h($pe['nome']) ?><br><span class="discreto"><?= h(telefone_formatado($pe['telefone'])) ?></span></td>
    <td><?= h(datahora_br($pe['criado_em'])) ?></td>
    <td><a class="botao pequeno" href="pedido.php?id=<?= (int)$pe['id'] ?>">Abrir</a>
      <?php if (!in_array($pe['mp_status'], ['authorized', 'approved'], true)): ?>
      <form method="post" style="margin-top:6px"><?= campo_csrf() ?><input type="hidden" name="acao" value="visto"><input type="hidden" name="id" value="<?= (int)$pe['id'] ?>">
        <button class="botao pequeno contorno" title="Já vi; estou esperando retorno da pessoa. Sai da contagem de pendências.">✓ Visto, aguardando</button></form>
      <?php endif ?></td>
  </tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>

<?php if ($vistos): ?>
<details style="margin-top:12px">
  <summary><strong>Vistos, aguardando retorno (<?= count($vistos) ?>)</strong> <span class="discreto">— não contam nas pendências; se a pessoa pagar, voltam sozinhos</span></summary>
  <div class="tabela"><table>
    <tr><th>Pedido</th><th>Comércio</th><th>Quem pediu</th><th>Quando</th><th>Visto em</th><th></th></tr>
    <?php foreach ($vistos as $pe): ?>
    <tr>
      <td><?= h(TIPO_PEDIDO[$pe['tipo']] ?? $pe['tipo']) ?><?= $pe['plano'] ? ' · plano ' . h(PLANOS_NOME[$pe['plano']] ?? $pe['plano']) . ' <span class="discreto">(não pago)</span>' : '' ?></td>
      <td><?= $pe['comercio'] ? adm_comercio((int)$pe['pid'], $pe['comercio'], $pe['cidade']) . ' <span class="discreto">(' . adm_cidade($pe['cidade'], null, true) . ')</span>' : '<em>Negócio novo</em>' ?></td>
      <td><?= h($pe['nome']) ?><br><span class="discreto"><?= h(telefone_formatado($pe['telefone'])) ?></span></td>
      <td><?= h(datahora_br($pe['criado_em'])) ?></td>
      <td><?= h(datahora_br(pedidos_vistos()[(int)$pe['id']] ?? null)) ?></td>
      <td><a class="botao pequeno" href="pedido.php?id=<?= (int)$pe['id'] ?>">Abrir</a>
        <form method="post" style="margin-top:6px"><?= campo_csrf() ?><input type="hidden" name="acao" value="desfazer_visto"><input type="hidden" name="id" value="<?= (int)$pe['id'] ?>">
          <button class="botao pequeno contorno">Voltar às pendências</button></form></td>
    </tr>
    <?php endforeach ?>
  </table></div>
</details>
<?php endif ?>

<h2>Donos com acesso ao painel (<?= count($acessos) ?>)</h2>
<?php if (!$acessos): ?><p class="discreto">Ninguém ainda.</p><?php else: ?>
<div class="tabela"><table>
  <tr><th>Comércio</th><th>Dono</th><th>Liberado em</th><th>Último uso</th><th></th></tr>
  <?php foreach ($acessos as $d): ?>
  <tr>
    <td><?= adm_comercio((int)$d['pid'], $d['comercio']) ?></td>
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

<h2>"Quero minha Cidade!" (<?= array_sum(array_column($cidades, 'total')) ?> pedidos)</h2>
<?php if (!$cidades): ?><p class="discreto">Ninguém pediu ainda.</p><?php else: ?>
<div class="tabela"><table>
  <tr><th>Cidade</th><th>Estado</th><th>Pedidos</th><th>Último</th><th></th></tr>
  <?php foreach ($cidades as $c): ?>
  <tr><td><?= h($c['cidade']) ?></td><td><?= h($c['uf'] ?? '-') ?></td><td><strong><?= (int)$c['total'] ?></strong></td><td><?= h(datahora_br($c['ultimo'])) ?></td>
    <td><form method="post" onsubmit="return confirm('Tirar <?= h(addslashes($c['cidade'])) ?> da lista?')"><?= campo_csrf() ?>
      <input type="hidden" name="acao" value="apagar_cidade"><input type="hidden" name="cidade" value="<?= h($c['cidade']) ?>">
      <input type="hidden" name="uf" value="<?= h($c['uf'] ?? '') ?>">
      <button class="botao contorno" style="padding:6px 12px;font-size:14px">Excluir</button></form></td></tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>
<?php admin_fim();
