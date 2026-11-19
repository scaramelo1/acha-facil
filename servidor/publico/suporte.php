<?php
// Fale com a equipe (passo 53): só com a conta Google. Abre chamados, vê as respostas e responde. ?id= = comércio
// (link "Informação errada?" da ficha); ?c= = um chamado da própria pessoa.
require __DIR__ . '/../inc/base.php';
require_once __DIR__ . '/../inc/guia.php';
require __DIR__ . '/../inc/pagina.php';
require_once __DIR__ . '/../inc/conta-web.php';
require_once __DIR__ . '/../inc/suporte.php';
iniciar_sessao();
header('Cache-Control: no-store');
$u = exigir_login_web('Fale com a equipe', 'Mande uma mensagem para a equipe do Acha Fácil: informação errada, dúvida, problema ou sugestão.');

$pid = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$com = $pid ? linha('SELECT id, nome, cidade FROM profissionais WHERE id = ?', [$pid]) : null;
$cid = (int)($_GET['c'] ?? 0);
$ch = $cid && suporte_pronto() ? linha('SELECT * FROM chamados WHERE id = ? AND usuario_id = ?', [$cid, $u['id']]) : null;
$erro = null;

if (e_post() && suporte_pronto()) {
    exigir_post();
    $acao = (string)($_POST['acao'] ?? '');
    $texto = trim((string)($_POST['texto'] ?? ''));
    $recentes = (int)valor('SELECT COUNT(*) FROM chamados_mensagens m JOIN chamados c ON c.id = m.chamado_id
        WHERE c.usuario_id = ? AND m.admin_id IS NULL AND m.criado_em > NOW() - INTERVAL 1 DAY', [$u['id']]);
    if ($acao === 'encerrar' && $ch) {
        q("UPDATE chamados SET status = 'fechado', atualizado_em = NOW() WHERE id = ?", [$ch['id']]);
        flash('Conversa encerrada. Obrigado!');
        ir('suporte.php');
    } elseif (mb_strlen($texto) < 5) $erro = 'Escreva a sua mensagem.';
    elseif (mb_strlen($texto) > 3000) $erro = 'A mensagem é grande demais (até 3.000 letras).';
    elseif ($recentes >= 20) $erro = 'Muitas mensagens hoje. Tente amanhã.';
    elseif ($acao === 'responder' && $ch) {
        suporte_responder($ch, $texto, null);
        flash('Mensagem enviada. Avisamos você no celular quando a equipe responder.');
        ir('suporte.php?c=' . $ch['id']);
    } elseif ($acao === 'abrir') {
        $assunto = isset(SUPORTE_ASSUNTOS[$_POST['assunto'] ?? '']) ? $_POST['assunto'] : 'outro';
        $novo = suporte_abrir($u, $assunto, $texto, $com ? (int)$com['id'] : null);
        flash('Mensagem enviada! A equipe responde por aqui e você recebe o aviso no celular (se usa o app com esta conta).');
        ir('suporte.php?c=' . $novo);
    }
}

$avisos = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);
$meus = suporte_pronto() ? linhas('SELECT c.*, p.nome AS comercio FROM chamados c LEFT JOIN profissionais p ON p.id = c.profissional_id
    WHERE c.usuario_id = ? ORDER BY c.atualizado_em DESC LIMIT 50', [$u['id']]) : [];

pagina_inicio('Fale com a equipe | Acha Fácil!', 'Mande uma mensagem para a equipe do Acha Fácil.', 'web', ['noindex' => true]);
?>
<main class="documento suporte">
  <?php foreach ($avisos as [$tipo, $msg]): ?><p class="aviso <?= h($tipo) ?>"><?= h($msg) ?></p><?php endforeach ?>
  <?php if (!suporte_pronto()): ?>
    <h1>Fale com a equipe</h1>
    <p class="aviso erro">Esta página ainda está sendo preparada. Enquanto isso: <?= contatos_html() ?>.</p>
  <?php elseif ($ch): $com_ch = $ch['profissional_id'] ? linha('SELECT id, nome, cidade FROM profissionais WHERE id = ?', [$ch['profissional_id']]) : null; ?>
    <p><a href="<?= h(url('suporte.php')) ?>">&larr; Minhas mensagens</a></p>
    <h1><?= h(SUPORTE_ASSUNTOS[$ch['assunto']] ?? 'Mensagem') ?></h1>
    <p class="discreto">Nº <?= (int)$ch['id'] ?> · <?= h(SUPORTE_STATUS[$ch['status']] ?? $ch['status']) ?>
      <?php if ($com_ch): ?> · <a href="<?= h(url_ficha($com_ch)) ?>"><?= h($com_ch['nome']) ?></a><?php endif ?></p>
    <?= suporte_conversa_html(suporte_mensagens((int)$ch['id']), primeiro_nome($u['nome'])) ?>
    <?php if ($erro): ?><p class="aviso erro"><?= h($erro) ?></p><?php endif ?>
    <form method="post">
      <?= campo_csrf() ?><input type="hidden" name="acao" value="responder">
      <label for="texto"><?= $ch['status'] === 'fechado' ? 'Escrever de novo (a conversa volta a ficar aberta)' : 'Responder' ?></label>
      <textarea id="texto" name="texto" rows="4" maxlength="3000" required></textarea>
      <p><button class="botao">Enviar</button></p>
    </form>
    <?php if ($ch['status'] !== 'fechado'): ?>
      <form method="post"><?= campo_csrf() ?><input type="hidden" name="acao" value="encerrar">
        <button class="botao pequeno contorno">Já resolveu? Encerrar conversa</button></form>
    <?php endif ?>
  <?php else: ?>
    <h1>Fale com a equipe</h1>
    <p>Encontrou uma informação errada, teve um problema ou tem uma sugestão? Mande aqui. A equipe responde nesta página e
      você recebe o aviso no celular se usa o app com a mesma conta.</p>
    <?php if ($erro): ?><p class="aviso erro"><?= h($erro) ?></p><?php endif ?>
    <form method="post" class="quadro">
      <?= campo_csrf() ?><input type="hidden" name="acao" value="abrir">
      <?php if ($com): ?><input type="hidden" name="id" value="<?= (int)$com['id'] ?>">
        <p>Sobre: <strong><?= h($com['nome']) ?></strong> (<?= h(nome_cidade($com['cidade'], true)) ?>)</p><?php endif ?>
      <label for="assunto">Assunto</label>
      <select id="assunto" name="assunto">
        <?php foreach (SUPORTE_ASSUNTOS as $k => $n): ?><option value="<?= $k ?>"<?= ($_POST['assunto'] ?? ($com ? 'info_errada' : '')) === $k ? ' selected' : '' ?>><?= h($n) ?></option><?php endforeach ?>
      </select>
      <label for="texto">Mensagem</label>
      <textarea id="texto" name="texto" rows="5" maxlength="3000" required placeholder="<?= $com ? 'Ex.: o telefone mudou para (19) 9...' : 'Conte o que aconteceu' ?>"><?= h($_POST['texto'] ?? '') ?></textarea>
      <p><button class="botao">Enviar para a equipe</button></p>
    </form>

    <h2>Minhas mensagens (<?= count($meus) ?>)</h2>
    <?php if (!$meus): ?><p class="discreto">Nenhuma ainda.</p><?php else: ?>
      <ul class="lista-conta">
        <?php foreach ($meus as $c): ?><li>
          <a href="<?= h(url('suporte.php?c=' . $c['id'])) ?>"><strong><?= h(SUPORTE_ASSUNTOS[$c['assunto']] ?? 'Mensagem') ?></strong></a>
          <span class="discreto"><?= $c['comercio'] ? h($c['comercio']) . ' · ' : '' ?><?= h(datahora_br($c['atualizado_em'])) ?></span>
          <span class="selo-status <?= h($c['status']) ?>"><?= h(SUPORTE_STATUS[$c['status']] ?? $c['status']) ?></span>
        </li><?php endforeach ?>
      </ul>
    <?php endif ?>
  <?php endif ?>
</main>
<?php pagina_fim();
