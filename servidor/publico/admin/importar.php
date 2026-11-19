<?php
// Importar a lista do robô (HTML "leads_<cidade>.html") direto pelo site, sem precisar entrar na VPS.
// Enviar: o importador confere em modo TESTE (nada muda) e a lista vai para Novidades (/admin/novidades.php), que
// mostra os comércios novos, os que mudaram e os que fecharam, com o botão "Importar para o app" (passo 56).
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/novidades.php';
$admin = exigir_admin();
@set_time_limit(900);

$DADOS = config()['dados_dir'];
$PASTA = "$DADOS/leads";
$GEO_LOG = "$DADOS/geocodificar.log";

if (e_post()) {
    exigir_post();
    $f = $_FILES['arquivos'] ?? null;
    $html = ''; $nomes = [];
    if (!$f || !is_array($f['name']) || !$f['name'][0]) { flash('Nenhum arquivo recebido (pode ser grande demais: o limite é 30 MB).', 'erro'); ir('admin/importar.php'); }
    foreach ($f['name'] as $i => $nome) {
        if ($f['error'][$i] !== UPLOAD_ERR_OK) { flash("Não consegui receber $nome (erro {$f['error'][$i]}).", 'erro'); ir('admin/importar.php'); }
        $html .= file_get_contents($f['tmp_name'][$i]) . "\n"; $nomes[] = basename((string)$nome);
    }
    try {
        $m = nov_criar($html, (string)($_POST['cidade'] ?? ''), 'enviada no site', $nomes);
    } catch (RuntimeException $e) {
        flash(implode(', ', $nomes) . ': ' . $e->getMessage(), 'erro'); ir('admin/importar.php');
    }
    nov_descartar_antigas($m['cidade'], $m['id']);
    ir('admin/novidades.php?id=' . $m['id']);
}

$listas = [];
foreach (glob("$PASTA/*.html") ?: [] as $f) if (!str_contains(basename($f), '.antes-')) $listas[basename($f, '.html')] = filemtime($f);

admin_inicio('Importar lista', $admin);
?>
<h1>Importar lista do robô</h1>
<p class="discreto">Envie o arquivo <code>.html</code> que o robô gera no PC (ex.: <code>leads_sao_joao_da_boa_vista.html</code>).
  Primeiro o site <strong>só confere</strong> (nada muda) e mostra em <a href="novidades.php">Novidades</a> os comércios novos, os que mudaram e os que fecharam; depois você confirma. Quem já está no app é atualizado, quem é novo entra,
  e <strong>quem assumiu o perfil não é mexido</strong>. Comércio fechado no Google não entra; se fechou de vez e era importado, sai do app.</p>


<form method="post" enctype="multipart/form-data" class="quadro">
  <?= campo_csrf() ?>
  <label for="arq">Arquivo(s) da lista (pode escolher mais de um da mesma cidade)</label>
  <input id="arq" type="file" name="arquivos[]" accept=".html,text/html" multiple required>
  <label for="cid">Cidade</label>
  <select id="cid" name="cidade">
    <option value="">Descobrir pelo arquivo</option>
    <?php foreach (TODAS_CIDADES as $slug => $c): ?><option value="<?= h($slug) ?>"><?= h("$c[0] - $c[2]") ?></option><?php endforeach ?>
  </select>
  <p class="discreto">Cidade nova? Cadastre antes em <a href="cidades.php">Cidades</a> (desligada), importe aqui e depois ligue.</p>
  <p><button class="botao" onclick="if(this.form.reportValidity()){this.disabled=true;this.textContent='Conferindo…';this.form.submit()}">Enviar e conferir</button></p>
</form>

<h2>Localização dos comércios</h2>
<p><?= geo_rodando() ? '⏳ Procurando agora (OpenStreetMap, ~1,3 s por endereço).' : '✅ Nada rodando.' ?></p>
<?php if (is_file($GEO_LOG)): ?>
<pre style="white-space:pre-wrap;font-size:13px"><?= h(implode("\n", array_slice(file($GEO_LOG, FILE_IGNORE_NEW_LINES) ?: [], -6))) ?></pre>
<?php endif ?>

<h2>Listas guardadas</h2>
<div class="tabela"><table>
  <tr><th>Cidade</th><th>Última lista enviada</th></tr>
  <?php foreach ($listas as $slug => $t): ?>
  <tr><td><?= adm_cidade($slug) ?></td><td><?= date('d/m/Y H:i', $t) ?></td></tr>
  <?php endforeach ?>
</table></div>
<?php admin_fim();
