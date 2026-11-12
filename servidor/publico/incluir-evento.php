<?php
// Divulgar um evento, de graça (passo 36, mudança 021): mesmo modelo do incluir.php. O evento entra escondido
// (visivel = 0, status 'pendente'); os administradores recebem aviso no celular e autorizam no /admin/eventos.php
// ou na Administração do app. Só aparece no site e no app depois de autorizado.
require __DIR__ . '/../inc/base.php';
require_once __DIR__ . '/../inc/guia.php';
require_once __DIR__ . '/../inc/moderacao.php';   // salvar_foto_enviada()
require_once __DIR__ . '/../inc/eventos.php';
require __DIR__ . '/../inc/pagina.php';
iniciar_sessao();

$pronto = eventos_envio_pronto();
$erro = null;
$enviado = false;
$f = $_POST ?: ['cidade' => (string)($_GET['cidade'] ?? '')];

if (e_post() && $pronto) {
    exigir_post();
    $d = [
        'cidade' => (string)($_POST['cidade'] ?? ''), 'titulo' => txt('titulo', 140),
        'data_inicio' => txt('data_inicio', 10), 'hora_inicio' => txt('hora_inicio', 5),
        'data_fim' => txt('data_fim', 10), 'hora_fim' => txt('hora_fim', 5),
        'local_nome' => txt('local_nome', 140), 'endereco' => txt('endereco', 200), 'preco' => txt('preco', 80),
        'descricao' => txt('descricao', 5000), 'link_oficial' => txt('link_oficial', 300),
    ];
    if ($d['data_fim'] === $d['data_inicio']) $d['data_fim'] = null;
    if ($d['link_oficial'] && !preg_match('#^https?://#i', $d['link_oficial'])) $d['link_oficial'] = 'https://' . $d['link_oficial'];
    $nome = txt('nome', 120);
    $contato = telefone_normalizado(txt('contato', 20));
    $data_ok = fn(?string $x) => $x !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $x) && strtotime($x) !== false;
    $hora_ok = fn(?string $x) => $x === null || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $x);
    $foto = $_FILES['imagem'] ?? null;
    $tem_foto = $foto && ($foto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if (!isset(CIDADES[$d['cidade']])) $erro = 'Escolha a cidade do evento.';
    elseif (!$d['titulo']) $erro = 'Preencha o nome do evento.';
    elseif (!$data_ok($d['data_inicio'])) $erro = 'Preencha a data do evento.';
    elseif ($d['data_inicio'] < hoje()) $erro = 'A data do evento já passou.';
    elseif ($d['data_fim'] !== null && (!$data_ok($d['data_fim']) || $d['data_fim'] < $d['data_inicio'])) $erro = 'A data final precisa ser depois da data de início.';
    elseif (!$hora_ok($d['hora_inicio']) || !$hora_ok($d['hora_fim'])) $erro = 'Hora inválida.';
    elseif ($d['hora_fim'] && !$d['hora_inicio']) $erro = 'Preencha a hora de início antes da hora de fim.';
    elseif (!$d['local_nome'] && !$d['endereco']) $erro = 'Preencha o local ou o endereço do evento.';
    elseif ($d['link_oficial'] && !filter_var($d['link_oficial'], FILTER_VALIDATE_URL)) $erro = 'O link da página oficial não parece um endereço de site.';
    elseif (!$nome || !$contato) $erro = 'Preencha o seu nome e um telefone com DDD para a equipe confirmar.';
    elseif (!empty($_POST['site_web'])) $enviado = true;   // campo escondido preenchido = robô: finge que deu certo
    elseif ((int)valor('SELECT COUNT(*) FROM eventos WHERE enviado_ip = ? AND criado_em > NOW() - INTERVAL 1 DAY', [ip()]) >= 5) $erro = 'Muitos eventos enviados hoje. Tente amanhã.';
    elseif (valor("SELECT 1 FROM eventos WHERE cidade = ? AND data_inicio = ? AND LOWER(titulo) = LOWER(?) AND status IN ('publicado','pendente')", [$d['cidade'], $d['data_inicio'], $d['titulo']])) {
        $erro = 'Esse evento já está na agenda (ou esperando a conferência).';
    } else {
        if ($tem_foto) {
            try { $d['imagem'] = salvar_foto_enviada($foto, 0, 1400); }
            catch (RuntimeException $ex) { $erro = $ex->getMessage(); }
        }
        if (!$erro) {
            $id = inserir('eventos', $d + ['visivel' => 0, 'status' => 'pendente', 'enviado_nome' => $nome, 'enviado_contato' => $contato,
                'enviado_recado' => txt('mensagem', 1000), 'enviado_ip' => ip()]);
            require_once __DIR__ . '/../inc/avisos.php';
            avisar_admins('Evento para autorizar', "$nome quer divulgar {$d['titulo']} (" . nome_cidade($d['cidade'], true) . ', ' . evento_quando($d + ['id' => $id], false) . ').', ['evento' => $id]);
            $enviado = true;
        }
    }
}

$sel = fn(string $campo, $valor) => (string)($f[$campo] ?? '') === (string)$valor ? ' selected' : '';
$v = fn(string $campo) => h((string)($f[$campo] ?? ''));
pagina_inicio('Divulgar um evento · Acha Fácil!', 'Divulgue de graça o seu show, festa, feira ou encontro na agenda de eventos do Acha Fácil.');
?>
<main class="documento">
  <h1>Divulgar um evento</h1>
  <?php if ($enviado): ?>
    <div class="aviso ok">Recebemos! A equipe do Acha Fácil vai conferir (normalmente em até 2 dias úteis) e o evento aparece na
      agenda do site e do app assim que for autorizado.</div>
    <p><a class="botao contorno" href="<?= h(isset(CIDADES[$f['cidade'] ?? '']) ? url_eventos($f['cidade']) : url('#eventos')) ?>">Ver a agenda</a>
      <a class="botao contorno" href="<?= h(url('incluir-evento.php')) ?>">Divulgar outro evento</a></p>
  <?php elseif (!$pronto): ?>
    <div class="aviso erro">Esta página ainda está sendo preparada. Tente de novo mais tarde.</div>
  <?php else: ?>
    <p>Divulgar é <strong>de graça</strong>. Show, festa, feira, quermesse, encontro ou curso aberto: coloque os dados e a equipe
      confere antes de aparecer na agenda do site e do app.</p>
    <?php if ($erro): ?><div class="aviso erro"><?= h($erro) ?></div><?php endif ?>
    <form method="post" enctype="multipart/form-data">
      <?= campo_csrf() ?>
      <h2>O evento</h2>
      <label for="cidade">Cidade</label>
      <select id="cidade" name="cidade" required>
        <option value="">Escolha</option>
        <?php foreach (CIDADES as $slug => [$nomeCidade, , $uf]): ?><option value="<?= h($slug) ?>"<?= $sel('cidade', $slug) ?>><?= h("$nomeCidade ($uf)") ?></option><?php endforeach ?>
      </select>
      <label for="titulo">Nome do evento</label>
      <input id="titulo" name="titulo" required maxlength="140" placeholder="Ex.: Festa Junina da Paróquia" value="<?= $v('titulo') ?>">
      <label for="data_inicio">Data</label>
      <input id="data_inicio" name="data_inicio" type="date" required min="<?= h(hoje()) ?>" value="<?= $v('data_inicio') ?>">
      <label for="hora_inicio">Horário de início (opcional)</label>
      <input id="hora_inicio" name="hora_inicio" type="time" value="<?= $v('hora_inicio') ?>">
      <label for="data_fim">Até o dia (só se durar mais de um dia)</label>
      <input id="data_fim" name="data_fim" type="date" min="<?= h(hoje()) ?>" value="<?= $v('data_fim') ?>">
      <label for="hora_fim">Horário de término (opcional)</label>
      <input id="hora_fim" name="hora_fim" type="time" value="<?= $v('hora_fim') ?>">
      <label for="local_nome">Local</label>
      <input id="local_nome" name="local_nome" maxlength="140" placeholder="Ex.: Praça da Matriz, Clube de Campo" value="<?= $v('local_nome') ?>">
      <label for="endereco">Endereço (rua e número)</label>
      <input id="endereco" name="endereco" maxlength="200" value="<?= $v('endereco') ?>">
      <label for="preco">Entrada</label>
      <input id="preco" name="preco" maxlength="80" placeholder="Ex.: Gratuito · R$ 20 (meia R$ 10)" value="<?= $v('preco') ?>">
      <label for="link_oficial">Link da página oficial (site, Instagram ou venda de ingressos)</label>
      <input id="link_oficial" name="link_oficial" maxlength="300" inputmode="url" placeholder="https://..." value="<?= $v('link_oficial') ?>">
      <label for="descricao">Sobre o evento (atrações, programação, informações)</label>
      <textarea id="descricao" name="descricao" maxlength="5000" rows="6"><?= $v('descricao') ?></textarea>
      <label for="imagem">Cartaz ou foto (opcional, JPG, PNG ou WEBP até 8 MB)</label>
      <input id="imagem" name="imagem" type="file" accept="image/jpeg,image/png,image/webp">
      <div hidden><label for="site_web">Não preencha</label><input id="site_web" name="site_web" tabindex="-1" autocomplete="off"></div>

      <h2>Você</h2>
      <label for="nome">Seu nome</label>
      <input id="nome" name="nome" required maxlength="120" value="<?= $v('nome') ?>">
      <label for="contato">Seu telefone ou WhatsApp (com DDD), para a equipe confirmar</label>
      <input id="contato" name="contato" required inputmode="tel" maxlength="20" value="<?= $v('contato') ?>">
      <label for="mensagem">Recado para a equipe (opcional)</label>
      <textarea id="mensagem" name="mensagem" maxlength="1000" rows="3"><?= $v('mensagem') ?></textarea>
      <p><button class="botao amarelo">Enviar para conferência</button></p>
      <p class="discreto">Ao enviar, você confirma que as informações são verdadeiras e concorda com os <a href="<?= h(config()['url']) ?>/termos.php">termos</a>.
        O seu nome e telefone não aparecem no site.</p>
    </form>
  <?php endif ?>
</main>
<?php pagina_fim();
