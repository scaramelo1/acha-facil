<?php
// Importar a lista do robô (HTML "leads_<cidade>.html") direto pelo site, sem precisar entrar na VPS.
// 1) Enviar: guarda o arquivo e roda o importador em modo TESTE (nada muda) para conferir os números.
// 2) Confirmar: backup dos comércios da cidade + da lista anterior, importa de verdade, limpa o cache do site
//    e começa a procurar a localização dos novos (geocodificar.php) em segundo plano.
require __DIR__ . '/../../inc/admin.php';
$admin = exigir_admin();
@set_time_limit(900);

$RAIZ = dirname(__DIR__, 2);
$DADOS = config()['dados_dir'];
$PASTA = "$DADOS/leads";
$BACKUPS = "$DADOS/backups";
$GEO_LOG = "$DADOS/geocodificar.log";

/** roda um script do servidor/ com o php de linha de comando e devolve [código, saída] */
function rodar(array $args): array {
    $cmd = 'php ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';
    exec($cmd, $out, $rc);
    return [$rc, trim(implode("\n", $out))];
}
function geo_rodando(): bool {
    exec('ps -C php -o args=', $out);
    foreach ($out as $l) if (str_contains($l, 'geocodificar.php')) return true;
    return false;
}
/** "São João da Boa Vista - SP · atualizado em ..." no cabeçalho → slug da cidade cadastrada */
function cidade_do_arquivo(string $html): ?string {
    if (!preg_match('/<div class="muted">\s*([^<·]+?) - ([A-Z]{2})\s*·/u', $html, $m)) return null;
    $norm = fn($s) => strtr(mb_strtolower(trim(html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
        ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ü'=>'u','ç'=>'c']);
    foreach (TODAS_CIDADES as $slug => $c) if ($norm($c[0]) === $norm($m[1]) && $c[2] === $m[2]) return $slug;
    return null;
}
function apagar_envio(): void {
    $e = $_SESSION['importar'] ?? null;
    if ($e && is_file($e['arquivo'])) @unlink($e['arquivo']);
    unset($_SESSION['importar']);
}

if (e_post()) {
    exigir_post();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'conferir') {
        apagar_envio();
        $f = $_FILES['arquivos'] ?? null;
        $html = ''; $nomes = [];
        if (!$f || !is_array($f['name']) || !$f['name'][0]) { flash('Nenhum arquivo recebido (pode ser grande demais: o limite é 30 MB).', 'erro'); ir('admin/importar.php'); }
        foreach ($f['name'] as $i => $nome) {
            if ($f['error'][$i] !== UPLOAD_ERR_OK) { flash("Não consegui receber $nome (erro {$f['error'][$i]}).", 'erro'); ir('admin/importar.php'); }
            $c = (string)file_get_contents($f['tmp_name'][$i]);
            if (!str_contains($c, '<tr data-status=')) { flash("$nome não parece a lista do robô (não achei a tabela de comércios).", 'erro'); ir('admin/importar.php'); }
            $html .= $c . "\n"; $nomes[] = $nome;
        }
        $slug = (string)($_POST['cidade'] ?? '');
        if ($slug === '') $slug = (string)cidade_do_arquivo($html);
        if (!isset(TODAS_CIDADES[$slug])) {
            flash('Não sei de qual cidade é a lista. Escolha a cidade (se ela não aparece, cadastre antes em Cidades).', 'erro');
            ir('admin/importar.php');
        }
        if (!is_dir($PASTA)) mkdir($PASTA, 0755, true);
        $tmp = "$PASTA/.envio-" . bin2hex(random_bytes(6)) . '.html';   // começa com ponto: o instalar.sh --leads não pega
        file_put_contents($tmp, $html);
        [$rc, $saida] = rodar([$RAIZ . '/importar-leads.php', $tmp, $slug, '--teste']);
        if ($rc !== 0 || !str_contains($saida, '✔')) { @unlink($tmp); flash('O importador recusou a lista: ' . $saida, 'erro'); ir('admin/importar.php'); }
        $_SESSION['importar'] = ['arquivo' => $tmp, 'cidade' => $slug, 'nomes' => $nomes, 'saida' => $saida,
            'linhas' => substr_count($html, '<tr data-status=')];
        ir('admin/importar.php');
    }

    if ($acao === 'cancelar') { apagar_envio(); flash('Envio descartado. Nada mudou.'); ir('admin/importar.php'); }

    if ($acao === 'importar') {
        $e = $_SESSION['importar'] ?? null;
        if (!$e || !is_file($e['arquivo']) || ($_POST['cidade'] ?? '') !== $e['cidade']) { flash('Esse envio expirou. Envie o arquivo de novo.', 'erro'); ir('admin/importar.php'); }
        $slug = $e['cidade']; $quando = date('Ymd-His');
        // backup dos comércios desta cidade (para voltar atrás se precisar)
        if (!is_dir($BACKUPS)) mkdir($BACKUPS, 0750, true);
        $c = config(); $bk = "$BACKUPS/profissionais-$slug-$quando.sql";
        $cmd = 'mysqldump --single-transaction --skip-lock-tables --no-create-info -h ' . escapeshellarg($c['db_host']) . ' -u ' . escapeshellarg($c['db_usuario'])
             . ' ' . escapeshellarg($c['db_nome']) . ' profissionais --where=' . escapeshellarg("cidade = '$slug'") . ' > ' . escapeshellarg($bk) . ' 2>&1';
        $p = proc_open($cmd, [], $pipes, null, ['MYSQL_PWD' => $c['db_senha'], 'PATH' => '/usr/bin:/bin']);
        $rc = is_resource($p) ? proc_close($p) : 1;
        if ($rc !== 0 || !filesize($bk)) { flash('Não consegui fazer o backup antes de importar; nada foi mudado. ' . mb_substr((string)@file_get_contents($bk), 0, 300), 'erro'); @unlink($bk); ir('admin/importar.php'); }
        // a lista anterior desta cidade vira <slug>.antes-<data>.html
        $final = "$PASTA/$slug.html";
        if (is_file($final)) rename($final, "$PASTA/$slug.antes-$quando.html");
        rename($e['arquivo'], $final);
        unset($_SESSION['importar']);
        [$rc, $saida] = rodar([$RAIZ . '/importar-leads.php', $final, $slug]);
        if ($rc !== 0 || !str_contains($saida, '✔')) { flash('Erro ao importar (o banco não mudou): ' . $saida, 'erro'); ir('admin/importar.php'); }
        web_cache_limpar();
        registrar((int)$admin['id'], 'importou_lista', null, nome_cidade($slug) . ': ' . strtok($saida, "\n"));
        // localização dos novos, em segundo plano (OpenStreetMap: ~1,3 s por endereço sem coordenadas no link)
        if (!geo_rodando()) exec('nohup php ' . escapeshellarg("$RAIZ/geocodificar.php") . ' > ' . escapeshellarg($GEO_LOG) . ' 2>&1 &');
        flash(strtok($saida, "\n") . ' A localização dos novos está sendo procurada (acompanhe abaixo).');
        ir('admin/importar.php');
    }
    ir('admin/importar.php');
}

$envio = $_SESSION['importar'] ?? null;
$listas = [];
foreach (glob("$PASTA/*.html") ?: [] as $f) if (!str_contains(basename($f), '.antes-')) $listas[basename($f, '.html')] = filemtime($f);

admin_inicio('Importar lista', $admin);
?>
<h1>Importar lista do robô</h1>
<p class="discreto">Envie o arquivo <code>.html</code> que o robô gera no PC (ex.: <code>leads_sao_joao_da_boa_vista.html</code>).
  Primeiro o site <strong>só confere</strong> (nada muda); depois você confirma. Quem já está no app é atualizado, quem é novo entra,
  e <strong>quem assumiu o perfil não é mexido</strong>. Comércio fechado no Google não entra; se fechou de vez e era importado, sai do app.</p>

<?php if ($envio): ?>
<div class="quadro">
  <h2>Conferência: <?= h(nome_cidade($envio['cidade'])) ?> - <?= h(uf_cidade($envio['cidade'])) ?></h2>
  <p class="discreto"><?= h(implode(', ', $envio['nomes'])) ?> · <?= number_format($envio['linhas'], 0, ',', '.') ?> linhas na lista</p>
  <pre style="white-space:pre-wrap;max-height:22em;overflow:auto;font-size:13px"><?= h($envio['saida']) ?></pre>
  <p class="discreto">“Em Outros” são tipos que o importador não soube encaixar numa área; eles entram mesmo assim, na área Outros.</p>
  <form method="post" style="display:inline"><?= campo_csrf() ?><input type="hidden" name="acao" value="importar">
    <input type="hidden" name="cidade" value="<?= h($envio['cidade']) ?>">
    <button class="botao" onclick="this.disabled=true;this.textContent='Importando, aguarde…';this.form.submit()">Importar de verdade</button></form>
  <form method="post" style="display:inline"><?= campo_csrf() ?><input type="hidden" name="acao" value="cancelar">
    <button class="botao contorno">Cancelar</button></form>
</div>
<?php else: ?>
<form method="post" enctype="multipart/form-data" class="quadro">
  <?= campo_csrf() ?><input type="hidden" name="acao" value="conferir">
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
<?php endif ?>

<h2>Localização dos comércios</h2>
<p><?= geo_rodando() ? '⏳ Procurando agora (OpenStreetMap, ~1,3 s por endereço).' : '✅ Nada rodando.' ?></p>
<?php if (is_file($GEO_LOG)): ?>
<pre style="white-space:pre-wrap;font-size:13px"><?= h(implode("\n", array_slice(file($GEO_LOG, FILE_IGNORE_NEW_LINES) ?: [], -6))) ?></pre>
<?php endif ?>

<h2>Listas guardadas</h2>
<div class="tabela"><table>
  <tr><th>Cidade</th><th>Última lista enviada</th></tr>
  <?php foreach ($listas as $slug => $t): ?>
  <tr><td><?= h(isset(TODAS_CIDADES[$slug]) ? nome_cidade($slug) : $slug) ?></td><td><?= date('d/m/Y H:i', $t) ?></td></tr>
  <?php endforeach ?>
</table></div>
<?php admin_fim();
