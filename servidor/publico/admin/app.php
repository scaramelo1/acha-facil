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
// contador de downloads do APK (passo 40)
$dias = in_array((int)($_GET['dias'] ?? 30), [1, 7, 30, 90], true) ? (int)($_GET['dias'] ?? 30) : 30;
$hist = apk_historico(desde_dias($dias));
$hoje = $hist['dias'][hoje()] ?? ['inicio' => 0, 'fim' => 0];
$maior = max([1, ...array_values(array_map(fn($d) => $d['inicio'], $hist['dias']))]);
admin_inicio('App e downloads', $admin);
?>
<h1>Downloads do APK</h1>
<p class="discreto">Quem baixou o app pelo botão "Baixar APK" do site (quem instala pela Play Store aparece no Play Console).
  <strong>Começaram</strong> = tocou em baixar; <strong>terminaram</strong> = o arquivo chegou inteiro no celular.
  Robôs não contam. Detalhe por dia e versão desde <?= h($hist['desde_log'] ? data_br($hist['desde_log']) : '14/11/2026') ?>.</p>
<div class="numeros-resumo">
  <div><strong><?= num(apk_downloads()) ?></strong><span>downloads desde o início</span></div>
  <div><strong><?= num($hoje['inicio']) ?></strong><span>hoje</span></div>
  <div><strong><?= num($hist['inicio']) ?></strong><span>começaram (<?= $dias === 1 ? 'hoje' : $dias . ' dias' ?>)</span></div>
  <div><strong><?= num($hist['fim']) ?></strong><span>terminaram (<?= pct($hist['fim'], $hist['inicio']) ?>)</span></div>
</div>
<form method="get" class="filtros-numeros">
  <label>Período <select name="dias" onchange="this.form.submit()">
    <?php foreach ([1, 7, 30, 90] as $d): ?><option value="<?= $d ?>"<?= $d === $dias ? ' selected' : '' ?>><?= $d === 1 ? 'Hoje' : "Últimos $d dias" ?></option><?php endforeach ?>
  </select></label>
</form>
<p class="discreto"><span class="barra-legenda aparicoes"></span> começaram · <span class="barra-legenda vistas"></span> terminaram</p>
<div class="barras-dias" role="img" aria-label="Downloads do APK por dia">
  <?php foreach ($hist['dias'] as $dia => $d): ?>
  <div class="dia" title="<?= h(data_br($dia)) ?>: <?= $d['inicio'] ?> começaram, <?= $d['fim'] ?> terminaram">
    <span class="aparicoes" style="height:<?= round(100 * $d['inicio'] / $maior) ?>%"></span>
    <span class="vistas" style="height:<?= round(100 * $d['fim'] / $maior) ?>%"></span>
  </div>
  <?php endforeach ?>
</div>
<p class="discreto"><?= h(data_br(desde_dias($dias))) ?> a <?= h(data_br(hoje())) ?></p>
<?php if ($hist['versoes']): ?>
<div class="tabela"><table>
  <tr><th>Versão</th><th>Começaram</th><th>Terminaram</th></tr>
  <?php foreach ($hist['versoes'] as $v => $t): ?><tr><td><?= h((string)$v) ?></td><td><?= num($t['inicio']) ?></td><td><?= num($t['fim']) ?></td></tr><?php endforeach ?>
</table></div>
<?php endif ?>

<h1>Versões do app</h1>
<p class="discreto">Ao abrir o app, ele compara a versão instalada com a daqui. Se a dele for mais velha, aparece
  "Nova versão do Acha Fácil" com o botão <strong>Atualizar</strong> (ou Depois).</p>

<div class="quadro">
  <h2>Play Store</h2>
  <p><strong>Automático:</strong> a partir da 1.7.0, o app instalado pela Play pergunta direto para a Play Store se há
    versão nova liberada para aquele celular e mostra o aviso sozinho, com a tela de atualização da própria loja.
    Não precisa preencher nada aqui.</p>
  <p class="discreto">Reserva: usada quando a Play não responde ou ainda não mostra a versão nova no celular (a Play demora horas ou dias; a partir da 1.10.0). Depois de liberar uma versão na Play, coloque o número dela aqui.
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
