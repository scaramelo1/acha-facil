<?php
// Versões do app (passo 34): o APK do site (publicado por publicar-apk.sh) e a versão disponível na Play Store.
// Quem instalou pela Play é avisado sozinho (o app pergunta à Play, expo-in-app-updates); a versão daqui é só reserva.
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/apk.php';
$admin = exigir_admin();

if (e_post()) {
    exigir_post();
    $v = trim((string)($_POST['versao_play'] ?? ''));
    if ($v !== '' && !preg_match('/^\d{1,4}(\.\d{1,4}){0,2}$/', $v)) flash('Versão inválida. Use o formato 1.7.0.', 'erro');
    elseif (!salvar_versao_play($v === '' ? null : $v)) flash('Não consegui salvar (permissão da pasta do APK).', 'erro');
    else {
        registrar((int)$admin['id'], 'versao_play', null, $v === '' ? 'sem aviso' : $v);
        flash($v === '' ? 'Aviso da Play desligado.' : "Pronto: quem tem uma versão mais velha que a $v pela Play Store vai ser avisado.");
    }
    ir('admin/app.php');
}

$apk = apk_info();
admin_inicio('App', $admin);
?>
<h1>Versões do app</h1>
<p class="discreto">Ao abrir o app, ele compara a versão instalada com a daqui. Se a dele for mais velha, aparece
  "Nova versão do Acha Fácil" com o botão <strong>Atualizar</strong> (ou Depois).</p>

<div class="quadro">
  <h2>Play Store</h2>
  <p><strong>Automático:</strong> a partir da 1.7.0, o app instalado pela Play pergunta direto para a Play Store se há
    versão nova liberada para aquele celular e mostra o aviso sozinho, com a tela de atualização da própria loja.
    Não precisa preencher nada aqui.</p>
  <p class="discreto">Reserva (opcional): só é usado quando a Play não responde (ex.: app instalado por fora da loja).
    Se for usar, preencha depois que a versão estiver disponível na loja.</p>
  <form method="post"><?= campo_csrf() ?>
    <label for="v">Versão disponível na Play Store</label>
    <input id="v" name="versao_play" value="<?= h(versao_play() ?? '') ?>" placeholder="1.7.0" style="max-width:10em">
    <p><button class="botao">Salvar</button></p>
  </form>
  <p class="discreto">Página na loja: <a href="<?= h(link_play()) ?>" target="_blank" rel="noopener"><?= h(link_play()) ?></a>.
    Deixe vazio para não avisar ninguém da Play. Só os apps a partir da 1.7.0 sabem fazer esse aviso.</p>
</div>

<div class="quadro">
  <h2>APK do site</h2>
  <?php if ($apk): ?>
    <p>Versão <strong><?= h($apk['versao'] ?: '?') ?></strong>, de <?= h($apk['atualizado']) ?> (<?= h($apk['mb']) ?>, <?= h(apk_downloads_texto($apk['downloads'])) ?>).</p>
  <?php else: ?><p>Nenhum APK publicado.</p><?php endif ?>
  <p class="discreto">Muda sozinho quando roda <code>bash servidor/publicar-apk.sh &lt;link do EAS&gt;</code> na VPS. Quem instalou pelo site
    é avisado quando a versão daqui for mais nova que a dele.</p>
</div>
<?php admin_fim();
