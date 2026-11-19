<?php
// Minha conta no site (passo 44): a mesma conta do app. Salvos, avaliações (editar/apagar), sair e excluir a conta.
require __DIR__ . '/../inc/base.php';
require_once __DIR__ . '/../inc/guia.php';
require __DIR__ . '/../inc/pagina.php';
require_once __DIR__ . '/../inc/conta-web.php';
iniciar_sessao();
header('Cache-Control: no-store');
$u = usuario_web();
if (!$u) { header('Location: ' . link_entrar('/minha-conta.php')); exit; }

if (e_post()) {
    exigir_post();
    $acao = (string)($_POST['acao'] ?? '');
    if ($acao === 'cancelar_aviso') {   // passo 65
        q('DELETE FROM avisos_info WHERE usuario_id = ? AND profissional_id = ? AND tipo = ? AND avisado_em IS NULL',
            [$u['id'], (int)($_POST['id'] ?? 0), (string)($_POST['tipo'] ?? '')]);
        flash('Aviso cancelado.');
    } elseif ($acao === 'tirar_salvo') {
        q('DELETE FROM favoritos WHERE usuario_id = ? AND profissional_id = ?', [$u['id'], (int)($_POST['id'] ?? 0)]);
        flash('Tirado dos salvos.');
    } elseif ($acao === 'apagar_avaliacao') {
        $pid = (int)($_POST['id'] ?? 0);
        q('DELETE FROM avaliacoes WHERE usuario_id = ? AND profissional_id = ?', [$u['id'], $pid]);
        recalcular_nota($pid);
        flash('Avaliação apagada.');
    } elseif ($acao === 'excluir_conta' && ($_POST['confirmo'] ?? '') === '1') {
        // igual ao app (api/eu.php DELETE): sessões, avaliações e salvos saem junto
        $profs = array_column(linhas('SELECT profissional_id FROM avaliacoes WHERE usuario_id = ?', [$u['id']]), 'profissional_id');
        q('DELETE FROM usuarios WHERE id = ?', [$u['id']]);
        foreach ($profs as $pid) recalcular_nota((int)$pid);
        sair_web();
        flash('Sua conta foi excluída, com os salvos e as avaliações.');
        header('Location: ' . url(''));
        exit;
    } elseif ($acao === 'excluir_conta') {
        flash('Marque a caixa para confirmar a exclusão.', 'erro');
    }
    header('Location: ' . url('minha-conta.php'));
    exit;
}

$salvos = linhas('SELECT ' . CAMPOS_RESUMO . ' FROM favoritos f JOIN profissionais p ON p.id = f.profissional_id
    JOIN categorias c ON c.id = p.categoria_id WHERE f.usuario_id = ? AND ' . visivel() . ' ORDER BY f.criado_em DESC', [$u['id']]);
$avaliacoes = linhas('SELECT a.estrelas, a.comentario, a.resposta, a.status, a.criado_em, p.id, p.nome, p.tipo, p.cidade, c.nome AS categoria
    FROM avaliacoes a JOIN profissionais p ON p.id = a.profissional_id JOIN categorias c ON c.id = p.categoria_id
    WHERE a.usuario_id = ? ORDER BY a.atualizado_em DESC', [$u['id']]);
$avisos = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);

pagina_inicio('Minha conta | Acha Fácil!', 'Seus comércios salvos e suas avaliações no Acha Fácil.', 'web', ['noindex' => true, 'canonica' => url_publica('minha-conta.php')]);
?>
<main class="documento minha-conta">
  <?php foreach ($avisos as [$tipo, $msg]): ?><p class="aviso <?= h($tipo) ?>"><?= h($msg) ?></p><?php endforeach ?>
  <div class="conta-topo">
    <?php if ($u['foto'] && str_starts_with($u['foto'], 'https://')): ?>
      <img src="<?= h($u['foto']) ?>" alt="" width="72" height="72" referrerpolicy="no-referrer" onerror="this.remove()">
    <?php endif ?>
    <div>
      <h1>Olá, <?= h(primeiro_nome($u['nome'])) ?>!</h1>
      <p class="discreto"><?= h($u['email']) ?> · a mesma conta do app Acha Fácil</p>
      <?php require_once __DIR__ . '/../inc/avisos.php'; if (admin_do_usuario($u)): ?><p><a class="botao amarelo" href="<?= h(url('admin/')) ?>">Painel da administração</a></p><?php endif ?>
    </div>
  </div>

  <?php require_once __DIR__ . '/../inc/moderacao.php';   // 17/11 (passo 92): dono volta ao painel por aqui
  if (($acesso_dono = acesso_da_conta((int)$u['id'])) && ($meu = linha('SELECT p.nome FROM acessos_dono a JOIN profissionais p ON p.id = a.profissional_id WHERE a.id = ?', [$acesso_dono]))): ?>
  <div class="quadro"><strong>Seu comércio: <?= h($meu['nome']) ?></strong><br>Mude os dados, o horário, o logo e as fotos pelo painel.
    <p style="margin:10px 0 0"><a class="botao amarelo" href="<?= h(url('painel/?entrar=1')) ?>">Painel do meu comércio</a></p></div>
  <?php endif ?>
  <p><a class="botao contorno" href="<?= h(url('suporte.php')) ?>">Fale com a equipe (suporte)</a></p>

  <?php require_once __DIR__ . '/../inc/avisos-info.php'; $meus_avisos = avisos_info_do_usuario((int)$u['id']);   // passo 65
  if ($meus_avisos): ?>
  <h2 id="avisos">Avisos que você pediu (<?= count($meus_avisos) ?>)</h2>
  <ul class="lista-conta">
    <?php foreach ($meus_avisos as $a): ?><li>
      <a href="<?= h(url_ficha($a)) ?>"><strong><?= h(nome_curto($a['nome'], 50)) ?></strong></a>
      <?php if ($a['avisado_em']): ?>
        <span>🔔 <strong>Já colocou <?= h(AVISO_INFO_TEXTO[$a['tipo']] ?? $a['tipo']) ?></strong> · <?= h(data_br(substr($a['avisado_em'], 0, 10))) ?></span>
      <?php else: ?>
        <span class="discreto">Esperando <?= h(AVISO_INFO_TEXTO[$a['tipo']] ?? $a['tipo']) ?> · <?= h(nome_cidade($a['cidade'], true)) ?></span>
        <form method="post"><?= campo_csrf() ?><input type="hidden" name="acao" value="cancelar_aviso"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <input type="hidden" name="tipo" value="<?= h($a['tipo']) ?>"><button class="botao pequeno contorno">Cancelar</button></form>
      <?php endif ?>
    </li><?php endforeach ?>
  </ul>
  <?php endif ?>

  <h2 id="salvos">Salvos (<?= count($salvos) ?>)</h2>
  <?php if (!$salvos): ?>
    <p class="discreto">Nenhum comércio salvo ainda. Na página de um comércio, toque em <strong>♡ Salvar</strong>.</p>
  <?php else: ?>
    <ul class="lista-conta">
      <?php foreach ($salvos as $p): ?><li>
        <a href="<?= h(url_ficha($p)) ?>"><strong><?= h($p['nome']) ?></strong></a>
        <span class="discreto"><?= h(($p['tipo'] ?? '') ?: ($p['categoria'] ?? '')) ?> · <?= h(nome_cidade($p['cidade'], true)) ?></span>
        <form method="post"><?= campo_csrf() ?><input type="hidden" name="acao" value="tirar_salvo"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="botao pequeno contorno">Tirar</button></form>
      </li><?php endforeach ?>
    </ul>
  <?php endif ?>

  <h2 id="avaliacoes">Minhas avaliações (<?= count($avaliacoes) ?>)</h2>
  <?php if (!$avaliacoes): ?>
    <p class="discreto">Você ainda não avaliou nenhum comércio. Na página de um comércio, vá até <strong>Avaliar</strong>.</p>
  <?php else: ?>
    <ul class="avaliacoes">
      <?php foreach ($avaliacoes as $a): ?><li>
        <p><a href="<?= h(url_ficha($a)) ?>#avaliar"><b><?= h($a['nome']) ?></b></a>
          <span class="estrelas" aria-label="<?= (int)$a['estrelas'] ?> de 5 estrelas"><?= str_repeat('★', (int)$a['estrelas']) . str_repeat('☆', 5 - (int)$a['estrelas']) ?></span>
          <small><?= h(data_br($a['criado_em'])) ?><?= $a['status'] === 'oculta' ? ' · oculta pela equipe' : '' ?></small></p>
        <?php if ($a['comentario']): ?><p><?= nl2br(h($a['comentario'])) ?></p><?php endif ?>
        <?php if ($a['resposta']): ?><p class="resposta"><b>Resposta do comércio:</b> <?= nl2br(h($a['resposta'])) ?></p><?php endif ?>
        <p><a class="botao pequeno contorno" href="<?= h(url_ficha($a)) ?>#avaliar">Mudar</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Apagar esta avaliação?')"><?= campo_csrf() ?><input type="hidden" name="acao" value="apagar_avaliacao"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <button class="botao pequeno contorno">Apagar</button></form></p>
      </li><?php endforeach ?>
    </ul>
  <?php endif ?>

  <h2>Conta</h2>
  <form method="post" action="<?= h(url('sair.php')) ?>"><?= campo_csrf() ?><input type="hidden" name="volta" value="/">
    <button class="botao contorno">Sair da conta</button></form>
  <details class="quadro" style="margin-top:24px">
    <summary><strong>Excluir minha conta</strong></summary>
    <p>Apaga a conta, os comércios salvos e as suas avaliações, no site e no app. Não dá para desfazer.</p>
    <form method="post"><?= campo_csrf() ?><input type="hidden" name="acao" value="excluir_conta">
      <label><input type="checkbox" name="confirmo" value="1"> Entendi, quero excluir</label>
      <p><button class="botao" style="background:#b3261e">Excluir minha conta</button></p></form>
  </details>
</main>
<?php pagina_fim();
