<?php
// Cupons de desconto (inc/cupons.php): criar, ligar/desligar e ver quem usou. 100% = cortesia (não passa pelo
// Mercado Pago); a cortesia do Destaque pode ser encerrada aqui (o comércio volta ao Básico se já pagou o Básico).
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/cupons.php';
require_once __DIR__ . '/../../inc/moderacao.php';
$admin = exigir_admin();
if (!cupons_prontos()) { admin_inicio('Cupons', $admin); echo '<h1>Cupons</h1><p>Falta aplicar a mudança 014 (bash servidor/instalar.sh).</p>'; admin_fim(); exit; }

if (e_post()) {
    exigir_post();
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'criar') {
        $codigo = normalizar_cupom((string)txt('codigo', 30));
        $pct = (int)($_POST['percentual'] ?? 0);
        $planos = array_values(array_intersect(planos_disponiveis(), (array)($_POST['planos'] ?? [])));
        $ate = txt('valido_ate', 10);
        $max = trim((string)($_POST['max_usos'] ?? '')) === '' ? null : max(1, (int)$_POST['max_usos']);
        if (strlen($codigo) < 4) flash('O código precisa de pelo menos 4 letras ou números.', 'erro');
        elseif ($pct < 1 || $pct > 100) flash('O desconto vai de 1% a 100%.', 'erro');
        elseif (!$planos) flash('Marque pelo menos um plano.', 'erro');
        elseif ($ate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) flash('Data de validade inválida.', 'erro');
        elseif (valor('SELECT 1 FROM cupons WHERE codigo = ?', [$codigo])) flash('Já existe um cupom com esse código.', 'erro');
        else {
            inserir('cupons', ['codigo' => $codigo, 'percentual' => $pct, 'planos' => implode(',', $planos), 'valido_ate' => $ate,
                'max_usos' => $max, 'observacao' => txt('observacao', 200), 'admin_id' => $admin['id']]);
            registrar((int)$admin['id'], 'criou_cupom', null, "$codigo ($pct%)");
            flash("Cupom $codigo criado.");
        }
    } elseif ($acao === 'ativo') {
        $c = linha('SELECT * FROM cupons WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        if ($c) {
            q('UPDATE cupons SET ativo = 1 - ativo WHERE id = ?', [$c['id']]);
            registrar((int)$admin['id'], $c['ativo'] ? 'desligou_cupom' : 'ligou_cupom', null, $c['codigo']);
            flash('Cupom ' . $c['codigo'] . ($c['ativo'] ? ' desligado.' : ' ligado de novo.'));
        }
    } elseif ($acao === 'encerrar') {
        $pid = (int)($_POST['profissional_id'] ?? 0);
        $n = q("UPDATE profissionais SET assinatura = 'sem', plano = 'basico' WHERE id = ? AND assinatura = 'cortesia'", [$pid])->rowCount();
        if ($n) registrar((int)$admin['id'], 'encerrou_cortesia', $pid, 'Destaque de cortesia (cupom)');
        flash($n ? 'Cortesia do Destaque encerrada.' : 'Este comércio não está mais em cortesia.', $n ? 'ok' : 'erro');
    }
    ir('admin/cupons.php');
}

$cupons = linhas("SELECT c.*, (SELECT COUNT(*) FROM pedidos pe WHERE pe.cupom_id = c.id AND pe.mp_status IN ('authorized','approved')) AS usos
    FROM cupons c ORDER BY c.ativo DESC, c.criado_em DESC");
$usos = linhas("SELECT pe.id, pe.nome, pe.plano, pe.desconto, pe.valor, pe.atualizado_em, c.codigo, p.id AS pid, p.nome AS comercio, p.assinatura
    FROM pedidos pe JOIN cupons c ON c.id = pe.cupom_id LEFT JOIN profissionais p ON p.id = pe.profissional_id
    WHERE pe.mp_status IN ('authorized','approved') ORDER BY pe.atualizado_em DESC LIMIT 100");

admin_inicio('Cupons', $admin);
?>
<h1>Cupons de desconto</h1>
<?php mostrar_flash(); ?>
<p class="discreto">O dono digita o cupom na página do pagamento, antes de pagar. No Destaque o desconto vale em todas as
  mensalidades. Cupom de 100% é cortesia: libera o plano na hora, sem Mercado Pago (no Destaque, sem prazo, até você encerrar abaixo).</p>

<div class="tabela"><table>
  <tr><th>Código</th><th>Desconto</th><th>Planos</th><th>Validade</th><th>Usos</th><th>Obs.</th><th></th></tr>
  <?php foreach ($cupons as $c): ?>
  <tr<?= $c['ativo'] ? '' : ' class="discreto"' ?>>
    <td><strong><?= h($c['codigo']) ?></strong></td>
    <td><?= (int)$c['percentual'] ?>%<?= (int)$c['percentual'] === 100 ? ' (cortesia)' : '' ?></td>
    <td><?= h(implode(' e ', array_map(fn($x) => PLANOS_NOME[$x] ?? $x, explode(',', $c['planos'])))) ?></td>
    <td><?= $c['valido_ate'] ? h(data_br($c['valido_ate'])) : 'sem prazo' ?></td>
    <td><?= (int)$c['usos'] ?><?= $c['max_usos'] !== null ? ' de ' . (int)$c['max_usos'] : '' ?></td>
    <td><?= h($c['observacao'] ?? '') ?></td>
    <td><form method="post"><?= campo_csrf() ?><input type="hidden" name="acao" value="ativo"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
      <button class="botao contorno"><?= $c['ativo'] ? 'Desligar' : 'Ligar' ?></button></form></td>
  </tr>
  <?php endforeach ?>
  <?php if (!$cupons): ?><tr><td colspan="7">Nenhum cupom ainda.</td></tr><?php endif ?>
</table></div>

<h2>Criar cupom</h2>
<form method="post" class="quadro">
  <?= campo_csrf() ?><input type="hidden" name="acao" value="criar">
  <label for="codigo">Código (letras, números e hífen)</label>
  <input id="codigo" name="codigo" maxlength="30" required style="text-transform:uppercase" placeholder="INAUGURA10">
  <label for="percentual">Desconto (%)</label>
  <input id="percentual" name="percentual" type="number" min="1" max="100" required>
  <p><strong>Vale para</strong></p>
  <label class="marcar"><input type="checkbox" name="planos[]" value="basico" checked> Básico (<?= h(preco_plano_texto('basico')) ?>)</label>
  <label class="marcar"><input type="checkbox" name="planos[]" value="destaque" checked> Destaque (<?= h(preco_plano_texto('destaque')) ?>)</label>
  <?php if (plano_site_pronto()): ?><label class="marcar"><input type="checkbox" name="planos[]" value="site"> Destaque + Site (<?= h(preco_plano_texto('site')) ?>)</label><?php endif ?>
  <label for="valido_ate">Vale até (vazio = sem prazo)</label>
  <input id="valido_ate" name="valido_ate" type="date">
  <label for="max_usos">Máximo de usos (vazio = sem limite)</label>
  <input id="max_usos" name="max_usos" type="number" min="1">
  <label for="observacao">Observação (só a equipe vê)</label>
  <input id="observacao" name="observacao" maxlength="200">
  <p><button class="botao">Criar cupom</button></p>
</form>

<h2>Quem usou</h2>
<div class="tabela"><table>
  <tr><th>Quando</th><th>Cupom</th><th>Comércio</th><th>Quem</th><th>Plano</th><th>Valor</th><th></th></tr>
  <?php foreach ($usos as $u): ?>
  <tr><td><?= h(datahora_br($u['atualizado_em'])) ?></td><td><?= h($u['codigo']) ?></td><td><?= h($u['comercio'] ?? '-') ?></td><td><?= h($u['nome']) ?></td>
    <td><?= h(PLANOS_NOME[$u['plano']] ?? '') ?></td>
    <td><?= (int)$u['desconto'] === 100 ? 'cortesia' : h(reais((float)$u['valor'])) . (plano_unico((string)$u['plano']) ? '' : '/mês') ?></td>
    <td><?php if ($u['assinatura'] === 'cortesia' && (int)$u['desconto'] === 100 && !plano_unico((string)$u['plano'])): ?>
      <form method="post" onsubmit="return confirm('Encerrar o Destaque de cortesia deste comércio?')"><?= campo_csrf() ?>
        <input type="hidden" name="acao" value="encerrar"><input type="hidden" name="profissional_id" value="<?= (int)$u['pid'] ?>">
        <button class="botao contorno">Encerrar cortesia</button></form>
    <?php endif ?></td></tr>
  <?php endforeach ?>
  <?php if (!$usos): ?><tr><td colspan="7">Ninguém usou cupom ainda.</td></tr><?php endif ?>
</table></div>
<?php admin_fim();
