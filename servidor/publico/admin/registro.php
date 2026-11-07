<?php
// O que cada administrador fez (últimas 200 ações)
require __DIR__ . '/../../inc/admin.php';
$admin = exigir_admin();
$itens = linhas('SELECT r.*, a.nome AS admin_nome, p.nome AS comercio FROM registro_admin r
    LEFT JOIN admins a ON a.id = r.admin_id LEFT JOIN profissionais p ON p.id = r.profissional_id
    ORDER BY r.id DESC LIMIT 200');
const ACOES = ['entrou' => 'Entrou', 'acesso_pago' => 'Dono entrou pelo pagamento', 'liberou_acesso' => 'Liberou o painel', 'cortou_acesso' => 'Cortou o acesso',
    'aprovou_alteracao' => 'Aprovou alteração', 'recusou_alteracao' => 'Recusou alteração', 'tirou_do_app' => 'Tirou do app',
    'pedido_feito' => 'Resolveu pedido', 'recusou_pedido' => 'Recusou pedido'];
admin_inicio('Registro', $admin);
?>
<h1>Registro</h1>
<div class="tabela"><table>
  <tr><th>Quando</th><th>Quem</th><th>O quê</th><th>Comércio</th><th>Detalhe</th></tr>
  <?php foreach ($itens as $r): ?>
  <tr><td><?= h(datahora_br($r['criado_em'])) ?></td><td><?= h($r['admin_nome'] ?? '?') ?></td>
    <td><?= h(ACOES[$r['acao']] ?? $r['acao']) ?></td><td><?= h($r['comercio'] ?? '') ?></td><td><?= h($r['detalhe'] ?? '') ?></td></tr>
  <?php endforeach ?>
</table></div>
<?php admin_fim();
