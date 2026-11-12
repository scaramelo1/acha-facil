<?php
// Agenda de eventos por cidade (passo 32, mudança 020): cadastrar, editar, esconder e apagar. Aparece no site em
// /<cidade>/eventos/ (inc/eventos.php). Salvar limpa o cache das páginas, então a mudança aparece na hora.
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/eventos.php';
$admin = exigir_admin();
if (!eventos_prontos()) { admin_inicio('Eventos', $admin); echo '<h1>Eventos</h1><p>Falta aplicar a mudança 020 (bash servidor/instalar.sh).</p>'; admin_fim(); exit; }

function data_ok(?string $d): bool { return $d !== null && (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false; }
function hora_ok(?string $h): bool { return $h === null || (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $h); }

if (e_post()) {
    exigir_post();
    $acao = $_POST['acao'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $atual = $id ? linha('SELECT * FROM eventos WHERE id = ?', [$id]) : null;

    if ($acao === 'salvar') {
        $d = [
            'cidade' => (string)($_POST['cidade'] ?? ''), 'titulo' => txt('titulo', 140),
            'data_inicio' => txt('data_inicio', 10), 'hora_inicio' => txt('hora_inicio', 5),
            'data_fim' => txt('data_fim', 10), 'hora_fim' => txt('hora_fim', 5),
            'local_nome' => txt('local_nome', 140), 'endereco' => txt('endereco', 200), 'preco' => txt('preco', 80),
            'descricao' => txt('descricao', 5000), 'link_oficial' => txt('link_oficial', 300),
            'visivel' => isset($_POST['visivel']) ? 1 : 0,
        ];
        if ($d['data_fim'] === $d['data_inicio']) $d['data_fim'] = null;
        $link = $d['link_oficial'];
        if ($link && !preg_match('#^https?://#i', $link)) $d['link_oficial'] = $link = 'https://' . $link;
        $erro = null;
        if (!isset(TODAS_CIDADES[$d['cidade']])) $erro = 'Escolha a cidade.';
        elseif (!$d['titulo']) $erro = 'Preencha o nome do evento.';
        elseif (!data_ok($d['data_inicio'])) $erro = 'Preencha a data do evento.';
        elseif ($d['data_fim'] !== null && (!data_ok($d['data_fim']) || $d['data_fim'] < $d['data_inicio'])) $erro = 'A data final precisa ser depois da data de início.';
        elseif (!hora_ok($d['hora_inicio']) || !hora_ok($d['hora_fim'])) $erro = 'Hora inválida (use 20:00).';
        elseif ($d['hora_fim'] && !$d['hora_inicio']) $erro = 'Preencha a hora de início antes da hora de fim.';
        elseif ($link && !filter_var($link, FILTER_VALIDATE_URL)) $erro = 'O link oficial não parece um endereço de site.';
        if ($erro) { flash($erro, 'erro'); $_SESSION['evento_form'] = $_POST; ir('admin/eventos.php' . ($id ? "?editar=$id" : '?novo=1')); }

        // imagem (opcional): reduzida e sem dados escondidos, como as fotos dos comércios
        $f = $_FILES['imagem'] ?? null;
        if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try { $d['imagem'] = salvar_foto_enviada($f, 0, 1400); }
            catch (RuntimeException $ex) { flash($ex->getMessage(), 'erro'); $_SESSION['evento_form'] = $_POST; ir('admin/eventos.php' . ($id ? "?editar=$id" : '?novo=1')); }
            if ($atual) apagar_foto($atual['imagem']);
        } elseif ($atual && isset($_POST['tirar_imagem'])) { apagar_foto($atual['imagem']); $d['imagem'] = null; }

        if (eventos_envio_pronto() && $d['visivel']) $d['status'] = 'publicado';
        if ($atual) { atualizar('eventos', $id, $d); registrar((int)$admin['id'], 'editou_evento', null, $d['titulo'] . ' (' . nome_cidade($d['cidade']) . ')'); }
        else { $id = inserir('eventos', $d + ['admin_id' => (int)$admin['id']]); registrar((int)$admin['id'], 'criou_evento', null, $d['titulo'] . ' (' . nome_cidade($d['cidade']) . ')'); }
        web_cache_limpar();
        $e = linha('SELECT * FROM eventos WHERE id = ?', [$id]);
        flash('Evento salvo.' . ($e['visivel'] && isset(CIDADES[$e['cidade']]) ? ' Já está no site: ' . url_publica(ltrim(url_evento($e), '/')) : ' (escondido: não aparece no site)'));
        ir('admin/eventos.php?cidade=' . urlencode($d['cidade']));
    }
    if ($acao === 'autorizar' && $atual) {
        flash(autorizar_evento($id, (int)$admin['id']) ? $atual['titulo'] . ' autorizado: já está na agenda do site e do app.' : 'Esse evento já tinha sido decidido.');
        ir('admin/eventos.php');
    }
    if ($acao === 'recusar' && $atual) {
        flash(recusar_evento($id, (int)$admin['id']) ? $atual['titulo'] . ' recusado: não vai aparecer.' : 'Esse evento já tinha sido decidido.');
        ir('admin/eventos.php');
    }
    if ($acao === 'visivel' && $atual) {
        q('UPDATE eventos SET visivel = 1 - visivel WHERE id = ?', [$id]);
        web_cache_limpar();
        flash($atual['titulo'] . ($atual['visivel'] ? ' escondido do site.' : ' aparecendo no site.'));
        ir('admin/eventos.php?cidade=' . urlencode($atual['cidade']));
    }
    if ($acao === 'apagar' && $atual) {
        q('DELETE FROM eventos WHERE id = ?', [$id]);
        apagar_foto($atual['imagem']);
        web_cache_limpar();
        registrar((int)$admin['id'], 'apagou_evento', null, $atual['titulo'] . ' (' . nome_cidade($atual['cidade']) . ')');
        flash($atual['titulo'] . ' apagado.');
        ir('admin/eventos.php?cidade=' . urlencode($atual['cidade']));
    }
    ir('admin/eventos.php');
}

$cidade = (string)($_GET['cidade'] ?? '');
if (!isset(TODAS_CIDADES[$cidade])) $cidade = '';
$passados = isset($_GET['passados']);
$editar = isset($_GET['editar']) ? linha('SELECT * FROM eventos WHERE id = ?', [(int)$_GET['editar']]) : null;
$form = $_SESSION['evento_form'] ?? null; unset($_SESSION['evento_form']);
$v = $form ?? $editar ?? ['cidade' => $cidade, 'visivel' => 1];
if ($editar && !$form && ($editar['status'] ?? '') === 'pendente') $v['visivel'] = 1;   // corrigir e salvar = autorizar
$val = fn(string $k) => h(substr((string)($v[$k] ?? ''), 0, $k === 'hora_inicio' || $k === 'hora_fim' ? 5 : 5000));
$mostrar_form = $editar || $form || isset($_GET['novo']);
$pendentes = eventos_pendentes();

$where = ['1 = 1']; $par = [];
if ($cidade !== '') { $where[] = 'cidade = ?'; $par[] = $cidade; }
if (eventos_envio_pronto()) $where[] = "status = 'publicado'";
$where[] = $passados ? 'COALESCE(data_fim, data_inicio) < CURDATE()' : 'COALESCE(data_fim, data_inicio) >= CURDATE()';
$lista = linhas('SELECT * FROM eventos WHERE ' . implode(' AND ', $where) . ' ORDER BY data_inicio ' . ($passados ? 'DESC' : 'ASC') . ', hora_inicio LIMIT 300', $par);

admin_inicio('Eventos', $admin);
?>
<h1>Eventos</h1>
<p class="discreto">A agenda de cada cidade no site (<code>/cidade/eventos/</code>), em ordem de data. Evento que já passou some da agenda sozinho.</p>

<?php if ($pendentes && !$mostrar_form): ?>
<h2>Esperando autorização (<?= count($pendentes) ?>)</h2>
<p class="discreto">Enviados pelo público em <a href="<?= h(url('incluir-evento.php')) ?>" target="_blank">Divulgar um evento</a>. Só aparecem no site e no app depois de autorizados.</p>
<?php foreach ($pendentes as $e): ?>
<div class="quadro">
  <h3 style="margin-top:0"><?= h($e['titulo']) ?></h3>
  <p><strong><?= h(ucfirst(evento_quando($e))) ?></strong><?= ($hr = evento_horario($e)) ? ' · ' . h($hr) : '' ?> · <?= h(nome_cidade($e['cidade'])) ?><br>
    <?= h(implode(', ', array_filter([$e['local_nome'], $e['endereco']]))) ?><?= $e['preco'] ? '<br>Entrada: ' . h($e['preco']) : '' ?>
    <?php if ($l = evento_link_oficial($e)): ?><br>Página oficial: <a href="<?= h($l) ?>" target="_blank" rel="noopener nofollow"><?= h($l) ?></a><?php endif ?></p>
  <?php if ($e['imagem']): ?><p><a href="<?= h(foto_url($e['imagem'])) ?>" target="_blank"><img src="<?= h(foto_url($e['imagem'])) ?>" alt="Cartaz" style="max-width:260px;border-radius:12px;display:block"></a></p><?php endif ?>
  <?php if ($e['descricao']): ?><p style="white-space:pre-line"><?= h($e['descricao']) ?></p><?php endif ?>
  <p class="discreto">Enviado por <strong><?= h($e['enviado_nome'] ?? '?') ?></strong> · <a href="<?= h(link_whatsapp((string)$e['enviado_contato'], 'Olá! Aqui é do Acha Fácil, sobre o evento ' . $e['titulo'] . ' que você enviou.')) ?>" target="_blank"><?= h(telefone_formatado($e['enviado_contato'])) ?></a>
    · <?= h(datahora_br($e['criado_em'])) ?><?= $e['enviado_recado'] ? '<br>Recado: ' . h($e['enviado_recado']) : '' ?></p>
  <form method="post" style="display:inline"><?= campo_csrf() ?><input type="hidden" name="acao" value="autorizar"><input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
    <button class="botao">Autorizar e publicar</button></form>
  <a class="botao contorno" href="?editar=<?= (int)$e['id'] ?>">Corrigir antes</a>
  <form method="post" style="display:inline" onsubmit="return confirm('Recusar este evento? Ele não vai aparecer.')"><?= campo_csrf() ?><input type="hidden" name="acao" value="recusar"><input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
    <button class="botao contorno">Recusar</button></form>
</div>
<?php endforeach ?>
<?php endif ?>

<?php if ($mostrar_form): ?>
<form method="post" enctype="multipart/form-data" class="quadro">
  <?= campo_csrf() ?><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?= (int)($editar['id'] ?? ($form['id'] ?? 0)) ?>">
  <h2><?= $editar ? 'Editar evento' : 'Novo evento' ?></h2>
  <?php if (($editar['status'] ?? '') === 'pendente'): ?><p class="aviso erro">Enviado por <?= h($editar['enviado_nome'] ?? '?') ?> (<?= h(telefone_formatado($editar['enviado_contato'])) ?>) e ainda não autorizado. Salvar com "Mostrar no site" marcado autoriza e publica.</p><?php endif ?>
  <label for="cidade">Cidade</label>
  <select id="cidade" name="cidade" required>
    <option value="">Escolha</option>
    <?php foreach (TODAS_CIDADES as $s => $c): ?><option value="<?= h($s) ?>"<?= ($v['cidade'] ?? '') === $s ? ' selected' : '' ?>><?= h("$c[0] - $c[2]") ?></option><?php endforeach ?>
  </select>
  <label for="titulo">Nome do evento</label>
  <input id="titulo" name="titulo" maxlength="140" required value="<?= $val('titulo') ?>" placeholder="Ex.: Festa do Peão 2026">
  <div style="display:flex;gap:12px;flex-wrap:wrap">
    <div><label for="di">Data</label><input id="di" type="date" name="data_inicio" required value="<?= $val('data_inicio') ?>"></div>
    <div><label for="hi">Hora (opcional)</label><input id="hi" type="time" name="hora_inicio" value="<?= $val('hora_inicio') ?>"></div>
    <div><label for="df">Até o dia (se durar mais de um dia)</label><input id="df" type="date" name="data_fim" value="<?= $val('data_fim') ?>"></div>
    <div><label for="hf">Hora de fim (opcional)</label><input id="hf" type="time" name="hora_fim" value="<?= $val('hora_fim') ?>"></div>
  </div>
  <label for="local">Local</label>
  <input id="local" name="local_nome" maxlength="140" value="<?= $val('local_nome') ?>" placeholder="Ex.: Recinto de Exposições">
  <label for="end">Endereço</label>
  <input id="end" name="endereco" maxlength="200" value="<?= $val('endereco') ?>" placeholder="Ex.: Av. Dona Gertrudes, 1000 - Centro">
  <label for="preco">Entrada</label>
  <input id="preco" name="preco" maxlength="80" value="<?= $val('preco') ?>" placeholder="Ex.: Gratuito · R$ 40 (meia R$ 20)">
  <label for="link">Link da página oficial</label>
  <input id="link" name="link_oficial" maxlength="300" value="<?= $val('link_oficial') ?>" placeholder="https://...">
  <label for="desc">Descrição (atrações, programação, informações)</label>
  <textarea id="desc" name="descricao" rows="8" maxlength="5000"><?= $val('descricao') ?></textarea>
  <label for="img">Imagem / cartaz (opcional, JPG, PNG ou WEBP até 8 MB)</label>
  <input id="img" type="file" name="imagem" accept="image/jpeg,image/png,image/webp">
  <?php if (!empty($editar['imagem'])): ?>
    <p><img src="<?= h(foto_url($editar['imagem'])) ?>" alt="" style="max-width:220px;border-radius:12px;display:block">
    <label class="marcar"><input type="checkbox" name="tirar_imagem"> Tirar a imagem</label></p>
  <?php endif ?>
  <label class="marcar"><input type="checkbox" name="visivel"<?= !empty($v['visivel']) ? ' checked' : '' ?>> Mostrar no site</label>
  <p><button class="botao">Salvar evento</button> <a class="botao contorno" href="eventos.php<?= $cidade ? '?cidade=' . urlencode($cidade) : '' ?>">Cancelar</a></p>
</form>
<?php else: ?>
<p><a class="botao" href="?novo=1<?= $cidade ? '&cidade=' . urlencode($cidade) : '' ?>">+ Novo evento</a></p>
<?php endif ?>

<form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:18px 0">
  <select name="cidade" onchange="this.form.submit()">
    <option value="">Todas as cidades</option>
    <?php foreach (TODAS_CIDADES as $s => $c): ?><option value="<?= h($s) ?>"<?= $cidade === $s ? ' selected' : '' ?>><?= h("$c[0] - $c[2]") ?></option><?php endforeach ?>
  </select>
  <label class="marcar"><input type="checkbox" name="passados" onchange="this.form.submit()"<?= $passados ? ' checked' : '' ?>> Ver os que já passaram</label>
  <?php if ($cidade && isset(CIDADES[$cidade])): ?><a href="<?= h(url_eventos($cidade)) ?>" target="_blank">Ver a agenda no site ↗</a><?php endif ?>
</form>

<?php if (!$lista): ?>
  <p class="discreto">Nenhum evento <?= $passados ? 'passado' : 'marcado' ?><?= $cidade ? ' em ' . h(nome_cidade($cidade)) : '' ?>.</p>
<?php else: ?>
<div class="tabela"><table>
  <tr><th>Quando</th><th>Evento</th><th>Cidade</th><th>No site</th><th></th></tr>
  <?php foreach ($lista as $e): ?>
  <tr<?= $e['visivel'] ? '' : ' class="discreto"' ?>>
    <td><?= h(evento_quando($e, false)) ?><?= ($hr = evento_horario($e)) ? '<br><small>' . h($hr) . '</small>' : '' ?></td>
    <td><strong><?= h($e['titulo']) ?></strong><?= $e['local_nome'] ? '<br><small>' . h($e['local_nome']) . '</small>' : '' ?></td>
    <td><?= h(nome_cidade($e['cidade'])) ?></td>
    <td><?php if ($e['visivel'] && isset(CIDADES[$e['cidade']])): ?><a href="<?= h(url_evento($e)) ?>" target="_blank">Ver ↗</a><?php else: ?>Escondido<?php endif ?></td>
    <td style="white-space:nowrap">
      <a class="botao contorno" href="?editar=<?= (int)$e['id'] ?>&cidade=<?= h(urlencode($e['cidade'])) ?>">Editar</a>
      <form method="post" style="display:inline"><?= campo_csrf() ?><input type="hidden" name="acao" value="visivel"><input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
        <button class="botao contorno"><?= $e['visivel'] ? 'Esconder' : 'Mostrar' ?></button></form>
      <form method="post" style="display:inline" onsubmit="return confirm('Apagar <?= h(addslashes($e['titulo'])) ?>?')"><?= campo_csrf() ?><input type="hidden" name="acao" value="apagar"><input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
        <button class="botao contorno">Apagar</button></form>
    </td>
  </tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>
<?php admin_fim();
