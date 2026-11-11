<?php
// Notificações para os usuários do app (inc/notificacoes.php): escrever, escolher a cidade e mandar; histórico embaixo.
// Chega em todo aparelho com o app 1.6.0 ou mais novo que aceitou os avisos. Tocar abre o app (ou a ficha do comércio).
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/notificacoes.php';
$admin = exigir_admin();
if (!notificacoes_prontas()) {
    admin_inicio('Notificações', $admin);
    echo '<h1>Notificações</h1><p>Falta aplicar a mudança 019 (bash servidor/instalar.sh).</p>';
    admin_fim();
    exit;
}

if (e_post()) {
    exigir_post();
    $cid = (string)($_POST['cidade'] ?? '');
    try {
        $r = enviar_notificacao((string)($_POST['titulo'] ?? ''), (string)($_POST['texto'] ?? ''), $cid === '' ? null : $cid,
            (string)($_POST['publico'] ?? 'todos'), notif_id_comercio((string)($_POST['profissional'] ?? '')), (int)$admin['id'], 'site');
        flash("Notificação enviada: chegou em {$r['entregues']} de {$r['aparelhos']} aparelho(s)" . ($r['falhas'] ? " ({$r['falhas']} falharam, quase sempre app desinstalado)." : '.'));
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'erro');
        $_SESSION['notif_rascunho'] = array_intersect_key($_POST, array_flip(['titulo', 'texto', 'cidade', 'publico', 'profissional']));
    }
    ir('admin/notificacoes.php');
}

$rascunho = $_SESSION['notif_rascunho'] ?? [];
unset($_SESSION['notif_rascunho']);
$t = notif_totais();
$historico = notif_historico(50);

admin_inicio('Notificações', $admin);
?>
<h1>Notificações para os usuários</h1>
<p class="discreto">Aparece no celular de quem tem o app (versão 1.6.0 ou mais nova) e aceitou receber avisos, com ou sem conta.
  Cada aparelho fica na última cidade que a pessoa escolheu no app. Hoje: <strong><?= (int)$t['total'] ?></strong> aparelho(s),
  <?= (int)$t['contas'] ?> com conta do Google. Use com cuidado: aviso demais faz a pessoa desligar ou desinstalar.</p>

<form method="post" class="quadro" id="form-notif">
  <?= campo_csrf() ?>
  <label for="titulo">Título (até 60 letras)</label>
  <input id="titulo" name="titulo" maxlength="60" required value="<?= h($rascunho['titulo'] ?? '') ?>" placeholder="Novidade em São João!">
  <label for="texto">Texto (até 178 letras) <span class="discreto" id="conta-texto"></span></label>
  <textarea id="texto" name="texto" maxlength="178" rows="3" required placeholder="Ex.: 200 comércios novos no app. Veja quem está perto de você."><?= h($rascunho['texto'] ?? '') ?></textarea>

  <label for="cidade">Para qual cidade</label>
  <select id="cidade" name="cidade">
    <option value="" data-n="<?= (int)$t['total'] ?>" data-c="<?= (int)$t['contas'] ?>">Todas as cidades (<?= (int)$t['total'] ?> aparelhos)</option>
    <?php foreach (TODAS_CIDADES as $slug => $c): $n = $t['cidades'][$slug] ?? ['total' => 0, 'contas' => 0];
        if (!($c[5] ?? true) && !$n['total']) continue; ?>
    <option value="<?= h($slug) ?>" data-n="<?= (int)$n['total'] ?>" data-c="<?= (int)$n['contas'] ?>"<?= ($rascunho['cidade'] ?? '') === $slug ? ' selected' : '' ?>>
      <?= h($c[0]) ?> (<?= h($c[2]) ?>) · <?= (int)$n['total'] ?> aparelho(s)</option>
    <?php endforeach ?>
  </select>

  <p><strong>Quem recebe</strong></p>
  <?php foreach (NOTIF_PUBLICOS as $k => $rot): ?>
  <label class="marcar"><input type="radio" name="publico" value="<?= h($k) ?>"<?= ($rascunho['publico'] ?? 'todos') === $k ? ' checked' : '' ?>> <?= h($rot) ?></label>
  <?php endforeach ?>

  <label for="profissional">Ao tocar, abrir a ficha de um comércio (opcional): cole o link da ficha no site ou o número dela</label>
  <input id="profissional" name="profissional" maxlength="300" value="<?= h($rascunho['profissional'] ?? '') ?>" placeholder="vazio = só abre o app · ex.: https://achafacilapp.com.br/sao-joao-da-boa-vista/c/padaria-123">

  <p><button class="botao" id="enviar">Enviar notificação</button></p>
</form>

<h2>Já enviadas</h2>
<div class="tabela"><table>
  <tr><th>Quando</th><th>Notificação</th><th>Para</th><th>Abre</th><th>Chegou</th><th>Quem mandou</th></tr>
  <?php foreach ($historico as $n): ?>
  <tr>
    <td><?= h($n['quando']) ?></td>
    <td><strong><?= h($n['titulo']) ?></strong><br><?= h($n['texto']) ?></td>
    <td><?= h($n['cidade']) ?><br><span class="discreto"><?= h($n['publico']) ?></span></td>
    <td><?= $n['comercio'] ? h($n['comercio']) . ' <span class="discreto">(nº ' . (int)$n['profissionalId'] . ')</span>' : 'o app' ?></td>
    <td><?= (int)$n['entregues'] ?> de <?= (int)$n['aparelhos'] ?><?= $n['falhas'] ? '<br><span class="discreto">' . (int)$n['falhas'] . ' falha(s)</span>' : '' ?></td>
    <td><?= h($n['admin'] ?? '-') ?><br><span class="discreto">pelo <?= $n['pelo'] === 'app' ? 'app' : 'site' ?></span></td>
  </tr>
  <?php endforeach ?>
  <?php if (!$historico): ?><tr><td colspan="6">Nenhuma notificação enviada ainda.</td></tr><?php endif ?>
</table></div>

<script>
(function () {
  var f = document.getElementById('form-notif'), texto = document.getElementById('texto'), conta = document.getElementById('conta-texto');
  function contar() { conta.textContent = '(' + texto.value.length + '/178)'; }
  texto.addEventListener('input', contar); contar();
  f.addEventListener('submit', function (e) {
    var op = document.getElementById('cidade').selectedOptions[0];
    var contas = f.querySelector('input[name=publico]:checked').value === 'contas';
    var n = contas ? op.dataset.c : op.dataset.n;
    var para = op.value ? op.textContent.split(' · ')[0].trim() : 'todas as cidades';
    if (!confirm('Mandar esta notificação para ' + n + ' aparelho(s) de ' + para + '?\n\nNão dá para desfazer.')) e.preventDefault();
    else document.getElementById('enviar').disabled = true;
  });
})();
</script>
<?php admin_fim();
