<?php
// Versões do app (passo 34): o APK do site (publicado por publicar-apk.sh) e a versão disponível na Play Store.
// Quem instalou pela Play e está numa versão mais velha que a daqui recebe o aviso "Atualizar" que abre a loja.
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
  <p>Depois que a versão nova for <strong>aprovada e estiver disponível</strong> na Play Store, escreva aqui o número dela
    (o "version" do app.json, ex.: 1.7.0). O botão Atualizar abre a página do app na loja.</p>
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
