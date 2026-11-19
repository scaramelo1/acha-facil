<?php
// Novidades do robô (passo 56): as listas que chegaram (do PC sozinho ou por "Importar lista") com o que mudou em cada
// uma: comércios novos, os que mudaram e os que fecharam. Importar / descartar, modo automático e a chave do robô.
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/novidades.php';
$admin = exigir_admin();
@set_time_limit(900);

if (e_post()) {
    exigir_post();
    $acao = $_POST['acao'] ?? '';
    $id = (string)($_POST['id'] ?? '');
    if ($acao === 'importar' || $acao === 'descartar') {
        $m = nov_ler($id);
        if (!$m) { flash('Lista não encontrada.', 'erro'); ir('admin/novidades.php'); }
        if ($acao === 'descartar') { nov_descartar($m, (int)$admin['id']); flash('Lista descartada. Nada mudou no app.'); ir('admin/novidades.php'); }
        try {
            $m = nov_importar($m, (int)$admin['id']);
            nov_descartar_antigas($m['cidade'], $m['id']);
            flash(strtok($m['saida_importacao'], "\n") . ' A localização dos novos está sendo procurada.');
            $_SESSION['sitemaps_cidade'] = $m['cidade'];   // passo 80: mostra os sitemaps da cidade logo abaixo
        } catch (RuntimeException $e) {
            flash($e->getMessage(), 'erro');
        }
        ir('admin/novidades.php?id=' . $id);
    }
    if ($acao === 'automatico') {
        $c = nov_config(); $c['automatico'] = ($_POST['ligar'] ?? '') === '1'; nov_config_salvar($c);
        registrar((int)$admin['id'], 'robo_automatico', null, $c['automatico'] ? 'ligado' : 'desligado');
        flash($c['automatico'] ? 'Modo automático ligado: as listas do robô entram sozinhas no app.' : 'Modo automático desligado: as listas esperam você aprovar.');
        ir('admin/novidades.php');
    }
    if ($acao === 'chave') {
        $_SESSION['robo_chave'] = robo_chave_nova();
        registrar((int)$admin['id'], 'robo_chave', null, 'chave nova gerada');
        ir('admin/novidades.php#chave');
    }
    ir('admin/novidades.php');
}

$STATUS = ['aguardando' => '⏳ Esperando você', 'importada' => '✅ Importada', 'descartada' => '✖ Descartada'];
$AREAS = [];
foreach (linhas('SELECT slug, nome FROM categorias') as $c) $AREAS[$c['slug']] = $c['nome'];
$CAMPOS = ['nome' => 'Nome', 'telefone' => 'Telefone', 'whatsapp' => 'WhatsApp', 'endereco' => 'Endereço', 'bairro' => 'Bairro', 'instagram' => 'Instagram', 'site' => 'Site'];
$quando = fn(?string $t) => $t ? date('d/m/Y H:i', strtotime($t)) : '';

// ---------- uma lista ----------
if (isset($_GET['id'])) {
    $m = nov_ler((string)$_GET['id']);
    if (!$m) { flash('Lista não encontrada.', 'erro'); ir('admin/novidades.php'); }
    $d = nov_detalhes($m['id']);
    admin_inicio('Novidades', $admin);
    ?>
<p><a href="novidades.php">← Todas as listas</a></p>
<h1><?= adm_cidade($m['cidade'], nome_cidade($m['cidade']) . ' - ' . uf_cidade($m['cidade'])) ?></h1>
<p class="discreto">Recebida em <?= $quando($m['recebido']) ?> · <?= h($m['origem']) ?> · <?= number_format($m['linhas'], 0, ',', '.') ?> linhas
  · <strong><?= $STATUS[$m['status']] ?? h($m['status']) ?></strong>
  <?php if (!empty($m['importada_em'])): ?> em <?= $quando($m['importada_em']) ?> (<?= h($m['importada_por']) ?>)<?php endif ?></p>

<?php if ($m['status'] === 'importada' && (($_SESSION['sitemaps_cidade'] ?? '') === $m['cidade'] || isset($_GET['sitemaps']))):
    unset($_SESSION['sitemaps_cidade']);
    require_once __DIR__ . '/../../inc/sitemaps.php'; ?>
<?= sitemaps_quadro($m['cidade']) ?>
<?php elseif ($m['status'] === 'importada'): ?>
<p><a class="botao pequeno contorno" href="?id=<?= h(rawurlencode($m['id'])) ?>&amp;sitemaps=1#sitemaps">🗺️ Gerar os sitemaps de <?= h(nome_cidade($m['cidade'])) ?></a></p>
<?php endif ?>

<?php if ($m['status'] === 'aguardando'): ?>
<div class="quadro">
  <p><strong><?= h(nov_resumo($m)) ?>.</strong> Nada mudou no app ainda. Quem assumiu o perfil não é mexido.</p>
  <form method="post" style="display:inline"><?= campo_csrf() ?><input type="hidden" name="acao" value="importar"><input type="hidden" name="id" value="<?= h($m['id']) ?>">
    <button class="botao" onclick="this.disabled=true;this.textContent='Importando, aguarde…';this.form.submit()">Importar para o app</button></form>
  <form method="post" style="display:inline"><?= campo_csrf() ?><input type="hidden" name="acao" value="descartar"><input type="hidden" name="id" value="<?= h($m['id']) ?>">
    <button class="botao contorno" onclick="return confirm('Descartar esta lista? Nada muda no app.')">Descartar</button></form>
</div>
<?php endif ?>

<h2>🆕 Novos (<?= count($d['novos']) ?>)</h2>
<?php if (!$d['novos']): ?><p class="discreto">Nenhum comércio novo.</p><?php else: ?>
<div class="tabela"><table>
  <tr><th>Nome</th><th>Área / tipo</th><th>Telefone</th><th>Endereço</th><th></th></tr>
  <?php foreach ($d['novos'] as $n): ?>
  <tr><td><strong><?= h($n['nome']) ?></strong><?= $n['cidade'] !== $m['cidade'] ? ' <span class="discreto">(' . adm_cidade($n['cidade']) . ')</span>' : '' ?></td>
    <td><?= h($AREAS[$n['area']] ?? $n['area']) ?><?= $n['tipo'] ? '<br><span class="discreto">' . h($n['tipo']) . '</span>' : '' ?></td>
    <td><?= h(trim(($n['telefone'] ?? '') . ($n['whatsapp'] ? ' · WhatsApp' : ''))) ?></td>
    <td><?= h(trim(($n['endereco'] ?? '') . ($n['bairro'] ? ' - ' . $n['bairro'] : ''))) ?></td>
    <td><?= $n['mapa'] ? '<a href="' . h($n['mapa']) . '" target="_blank" rel="noopener">Maps</a>' : '' ?></td></tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>

<h2>✏️ Mudaram (<?= count($d['mudaram']) ?>)</h2>
<?php if (!$d['mudaram']): ?><p class="discreto">Nenhum comércio mudou telefone, endereço ou nome.</p><?php else: ?>
<div class="tabela"><table>
  <tr><th>Comércio</th><th>O que mudou</th></tr>
  <?php foreach ($d['mudaram'] as $x): ?>
  <tr><td><strong><?= adm_comercio((int)$x['id'], $x['nome'], $m['cidade']) ?></strong> <span class="discreto"><a href="numeros-ficha.php?id=<?= (int)$x['id'] ?>">nº <?= (int)$x['id'] ?></a></span></td>
    <td><?php foreach ($x['campos'] as $cp => [$a, $b]): ?>
      <div><?= h($CAMPOS[$cp] ?? $cp) ?>: <s class="discreto"><?= h($a !== '' ? $a : '(vazio)') ?></s> → <strong><?= h($b) ?></strong></div>
    <?php endforeach ?></td></tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>

<h2>🚫 Fecharam (<?= count($d['fecharam']) ?>)</h2>
<?php if (!$d['fecharam']): ?><p class="discreto">Nenhum comércio do app fechou de vez.</p><?php else: ?>
<p class="discreto">“Fechado permanentemente” no Google: saem do app (ficam pausados, dá para voltar).</p>
<div class="tabela"><table>
  <tr><th>Comércio</th><th>Endereço</th></tr>
  <?php foreach ($d['fecharam'] as $x): ?><tr><td><strong><?= adm_comercio((int)$x['id'], $x['nome'], $m['cidade']) ?></strong> <span class="discreto"><a href="numeros-ficha.php?id=<?= (int)$x['id'] ?>">nº <?= (int)$x['id'] ?></a></span></td><td><?= h((string)$x['endereco']) ?></td></tr><?php endforeach ?>
</table></div>
<?php endif ?>

<details><summary>Resultado completo do importador</summary>
<pre style="white-space:pre-wrap;max-height:22em;overflow:auto;font-size:13px"><?= h($m['saida_importacao'] ?? $m['saida']) ?></pre></details>
<?php
    admin_fim();
    exit;
}

// ---------- todas as listas ----------
$cfg = nov_config();
$lista = nov_lista();
$chave_nova = $_SESSION['robo_chave'] ?? null;
unset($_SESSION['robo_chave']);
admin_inicio('Novidades', $admin);
?>
<h1>Novidades do robô</h1>
<p class="discreto">Cada vez que o robô do PC termina uma cidade, a lista chega aqui sozinha e o site mostra o que mudou:
  comércios <strong>novos</strong>, os que <strong>mudaram</strong> telefone/endereço e os que <strong>fecharam</strong>.
  Você recebe um aviso no celular (app, conta de admin). Também dá para enviar à mão em <a href="importar.php">Importar lista</a>.</p>

<div class="tabela"><table>
  <tr><th>Recebida</th><th>Cidade</th><th>O que mudou</th><th>Situação</th><th></th></tr>
  <?php if (!$lista): ?><tr><td colspan="5" class="discreto">Nenhuma lista recebida ainda.</td></tr><?php endif ?>
  <?php foreach ($lista as $m): ?>
  <tr><td><?= $quando($m['recebido']) ?><br><span class="discreto"><?= h($m['origem']) ?></span></td>
    <td><?= adm_cidade($m['cidade']) ?></td>
    <td><?= !empty($m['sem_novidade']) ? '<span class="discreto">nada novo</span>' : h(nov_resumo($m)) ?></td>
    <td><?= $STATUS[$m['status']] ?? h($m['status']) ?></td>
    <td><a class="botao<?= $m['status'] === 'aguardando' ? '' : ' contorno' ?>" href="?id=<?= h($m['id']) ?>"><?= $m['status'] === 'aguardando' ? 'Conferir' : 'Ver' ?></a></td></tr>
  <?php endforeach ?>
</table></div>

<h2>Modo automático</h2>
<div class="quadro">
  <p><?= $cfg['automatico']
      ? '🟢 <strong>Ligado:</strong> a lista do robô entra sozinha no app e você só recebe o aviso do que mudou.'
      : '⚪ <strong>Desligado:</strong> a lista do robô espera aqui até você clicar em “Importar para o app”.' ?></p>
  <form method="post"><?= campo_csrf() ?><input type="hidden" name="acao" value="automatico"><input type="hidden" name="ligar" value="<?= $cfg['automatico'] ? '0' : '1' ?>">
    <button class="botao contorno"><?= $cfg['automatico'] ? 'Desligar (eu aprovo cada lista)' : 'Ligar (entra sozinho)' ?></button></form>
</div>

<h2 id="chave">Chave do robô</h2>
<div class="quadro">
  <?php if ($chave_nova): ?>
  <p>✅ Chave nova. <strong>Copie agora</strong> (ela não aparece de novo) e cole no <code>robo-config.json</code> do PC, no campo <code>"chave"</code>:</p>
  <p><input type="text" readonly value="<?= h($chave_nova) ?>" onclick="this.select()" style="width:100%;font-family:monospace"></p>
  <?php elseif ($cfg['chave_hash']): ?>
  <p>Chave criada em <?= $quando($cfg['chave_criada']) ?>. Perdeu? Gere outra (a antiga para de funcionar).</p>
  <?php else: ?>
  <p>Ainda não há chave: o PC não consegue enviar listas. Gere uma e coloque no <code>robo-config.json</code> do PC.</p>
  <?php endif ?>
  <form method="post"><?= campo_csrf() ?><input type="hidden" name="acao" value="chave">
    <button class="botao contorno" <?= $cfg['chave_hash'] ? "onclick=\"return confirm('A chave antiga para de funcionar. Gerar outra?')\"" : '' ?>>Gerar chave<?= $cfg['chave_hash'] ? ' nova' : '' ?></button></form>
  <p class="discreto">Endereço que o robô usa: <code><?= h(url_publica('admin/robo-enviar.php')) ?></code></p>
</div>
<?php admin_fim();
