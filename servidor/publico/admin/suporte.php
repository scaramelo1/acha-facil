<?php
// Suporte (passo 53): chamados das pessoas. Lista (abertos primeiro) e conversa com resposta (?c=).
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/suporte.php';
$admin = exigir_admin();
$cid = (int)($_GET['c'] ?? $_POST['c'] ?? 0);
$ch = $cid && suporte_pronto() ? linha('SELECT c.*, u.nome AS pessoa, u.email, u.foto FROM chamados c JOIN usuarios u ON u.id = c.usuario_id WHERE c.id = ?', [$cid]) : null;

if (e_post() && $ch) {
    exigir_post();
    $acao = (string)($_POST['acao'] ?? '');
    $texto = trim((string)($_POST['texto'] ?? ''));
    if ($acao === 'responder' && $texto !== '') {
        suporte_responder($ch, mb_substr($texto, 0, 5000), (int)$admin['id']);
        registrar((int)$admin['id'], 'respondeu_suporte', $ch['profissional_id'] ? (int)$ch['profissional_id'] : null, 'chamado ' . $ch['id']);
        if (!empty($_POST['encerrar'])) q("UPDATE chamados SET status = 'fechado' WHERE id = ?", [$ch['id']]);
        flash('Resposta enviada' . (!empty($_POST['encerrar']) ? ' e conversa encerrada.' : '. A pessoa recebe o aviso no celular (se tem o app com esta conta).'));
    } elseif ($acao === 'encerrar') {
        q("UPDATE chamados SET status = 'fechado', atualizado_em = NOW() WHERE id = ?", [$ch['id']]);
        flash('Conversa encerrada.');
    } elseif ($acao === 'reabrir') {
        q("UPDATE chamados SET status = 'aberto', atualizado_em = NOW() WHERE id = ?", [$ch['id']]);
        flash('Conversa reaberta.');
    }
    ir('admin/suporte.php?c=' . $ch['id']);
}

$filtro = in_array($_GET['ver'] ?? '', ['aberto', 'respondido', 'fechado', 'todos'], true) ? $_GET['ver'] : 'aberto';
admin_inicio('Suporte', $admin);
?>
<?php if (!suporte_pronto()): ?>
  <h1>Suporte</h1>
  <p class="aviso erro">Falta aplicar a mudança 025 do banco (rode o instalar.sh).</p>
<?php elseif ($ch): $com = $ch['profissional_id'] ? linha('SELECT id, nome, cidade FROM profissionais WHERE id = ?', [$ch['profissional_id']]) : null; ?>
  <p><a href="suporte.php">&larr; Suporte</a></p>
  <h1><?= h(SUPORTE_ASSUNTOS[$ch['assunto']] ?? 'Mensagem') ?></h1>
  <p>Nº <?= (int)$ch['id'] ?> · <strong><?= h(SUPORTE_STATUS[$ch['status']] ?? $ch['status']) ?></strong> ·
    <a href="pessoa.php?id=<?= (int)$ch['usuario_id'] ?>"><?= h($ch['pessoa']) ?></a> · <?= h($ch['email']) ?>
    <?php if ($com): ?><br>Comércio: <?= adm_comercio((int)$com['id'], $com['nome'], $com['cidade']) ?> (<?= adm_cidade($com['cidade'], null, true) ?>)
      · <a href="numeros-ficha.php?id=<?= (int)$com['id'] ?>">números</a><?php endif ?></p>
  <?= suporte_conversa_html(suporte_mensagens((int)$ch['id']), primeiro_nome($ch['pessoa'])) ?>
  <form method="post" class="quadro">
    <?= campo_csrf() ?><input type="hidden" name="c" value="<?= (int)$ch['id'] ?>"><input type="hidden" name="acao" value="responder">
    <label for="texto">Responder</label>
    <textarea id="texto" name="texto" rows="5" required></textarea>
    <p><label><input type="checkbox" name="encerrar" value="1"> Encerrar a conversa junto</label></p>
    <p><button class="botao">Enviar resposta</button></p>
  </form>
  <form method="post"><?= campo_csrf() ?><input type="hidden" name="c" value="<?= (int)$ch['id'] ?>">
    <?php if ($ch['status'] === 'fechado'): ?><button class="botao pequeno contorno" name="acao" value="reabrir">Reabrir</button>
    <?php else: ?><button class="botao pequeno contorno" name="acao" value="encerrar">Encerrar sem responder</button><?php endif ?>
  </form>
<?php else:
    $where = $filtro === 'todos' ? '1' : 'c.status = ' . db()->quote($filtro);
    $lista = linhas("SELECT c.*, u.nome AS pessoa, p.nome AS comercio,
        (SELECT texto FROM chamados_mensagens m WHERE m.chamado_id = c.id ORDER BY m.id DESC LIMIT 1) AS ultima
        FROM chamados c JOIN usuarios u ON u.id = c.usuario_id LEFT JOIN profissionais p ON p.id = c.profissional_id
        WHERE $where ORDER BY c.atualizado_em " . ($filtro === 'aberto' ? 'ASC' : 'DESC') . ' LIMIT 200');
    $contas = array_column(linhas('SELECT status, COUNT(*) n FROM chamados GROUP BY status'), 'n', 'status'); ?>
  <h1>Suporte</h1>
  <p class="discreto">Mensagens das pessoas com conta Google (página <a href="<?= h(url('suporte.php')) ?>" target="_blank">Fale com a equipe</a>). Você recebe push a cada mensagem nova.</p>
  <p><?php foreach (['aberto' => 'Esperando resposta', 'respondido' => 'Respondidos', 'fechado' => 'Encerrados', 'todos' => 'Todos'] as $k => $n): ?>
    <a class="botao pequeno<?= $filtro === $k ? '' : ' contorno' ?>" href="?ver=<?= $k ?>"><?= $n ?><?= $k !== 'todos' ? ' (' . (int)($contas[$k] ?? 0) . ')' : '' ?></a>
  <?php endforeach ?></p>
  <?php if (!$lista): ?><p class="discreto">Nada aqui.</p><?php else: ?>
  <div class="tabela"><table>
    <tr><th>Assunto</th><th>Pessoa</th><th>Última mensagem</th><th>Quando</th><th></th></tr>
    <?php foreach ($lista as $c): ?><tr>
      <td><?= h(SUPORTE_ASSUNTOS[$c['assunto']] ?? $c['assunto']) ?><?= $c['comercio'] ? '<br><span class="discreto">' . adm_comercio((int)$c['profissional_id'], $c['comercio']) . '</span>' : '' ?></td>
      <td><?= h($c['pessoa']) ?></td>
      <td><?= h(mb_strimwidth((string)$c['ultima'], 0, 90, '…')) ?></td>
      <td><?= h(datahora_br($c['atualizado_em'])) ?></td>
      <td><a class="botao pequeno" href="?c=<?= (int)$c['id'] ?>">Abrir</a></td>
    </tr><?php endforeach ?>
  </table></div>
  <?php endif ?>
<?php endif ?>
<?php admin_fim();
