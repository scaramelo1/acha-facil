<?php
// Uma pessoa do app (passo 41): o que salvou como favorito e o que avaliou. Só leitura, para controle e estatística.
require __DIR__ . '/../../inc/admin.php';
$admin = exigir_admin();

$u = linha('SELECT u.*, (SELECT MAX(s.usado_em) FROM sessoes_app s WHERE s.usuario_id = u.id) AS ultimo_uso FROM usuarios u WHERE u.id = ?', [(int)($_GET['id'] ?? 0)]);
if (!$u) { flash('Pessoa não encontrada.', 'erro'); ir('admin/pessoas.php'); }

// notificação só para esta pessoa (passo 54): vai para todos os celulares em que ela entrou no app com esta conta
require_once __DIR__ . '/../../inc/avisos.php';
$aparelhos = (int)valor('SELECT COUNT(*) FROM avisos_aparelhos WHERE usuario_id = ?', [$u['id']]);
try { $aparelhos += (int)valor('SELECT COUNT(*) FROM avisos_publico WHERE usuario_id = ?', [$u['id']]); } catch (PDOException) {}
if (e_post() && ($_POST['acao'] ?? '') === 'notificar') {
    exigir_post();
    $titulo = trim((string)($_POST['titulo'] ?? ''));
    $texto = trim((string)($_POST['texto'] ?? ''));
    if ($titulo === '' || $texto === '') flash('Preencha o título e o texto.', 'erro');
    else {
        $n = avisar_usuario((int)$u['id'], mb_substr($titulo, 0, 80), mb_substr($texto, 0, 180));
        registrar((int)$admin['id'], 'notificou_pessoa', null, $u['nome'] . ': ' . mb_substr($titulo, 0, 60) . " ($n aparelho(s))");
        flash($n ? "Notificação entregue em $n celular(es)." : 'Não foi entregue: a pessoa não tem o app com avisos ligados nesta conta. Mande pelo e-mail ou WhatsApp.', $n ? 'ok' : 'erro');
    }
    ir('admin/pessoa.php?id=' . $u['id'] . '#notificar');
}

$favoritos = linhas('SELECT f.criado_em, p.id, p.nome, p.tipo, p.cidade, p.bairro, p.status, c.nome AS categoria
    FROM favoritos f JOIN profissionais p ON p.id = f.profissional_id JOIN categorias c ON c.id = p.categoria_id
    WHERE f.usuario_id = ? ORDER BY f.criado_em DESC', [$u['id']]);
$avaliacoes = linhas('SELECT a.estrelas, a.comentario, a.status, a.resposta, a.criado_em, a.atualizado_em,
        p.id, p.nome, p.tipo, p.cidade, p.bairro, p.status AS status_ficha, c.nome AS categoria
    FROM avaliacoes a JOIN profissionais p ON p.id = a.profissional_id JOIN categorias c ON c.id = p.categoria_id
    WHERE a.usuario_id = ? ORDER BY a.criado_em DESC', [$u['id']]);
// passo 68: toques em "Quero saber..." feitos com esta conta (com a mudança 030)
require_once __DIR__ . '/../../inc/numeros.php';
$pedidos_info = pedidos_info_pessoas_prontos() ? linhas('SELECT q.tipo AS pedido, q.origem, q.criado_em AS quando,
        p.id, p.nome, p.tipo, p.cidade, p.bairro, p.status, c.nome AS categoria
    FROM pedidos_info_pessoas q JOIN profissionais p ON p.id = q.profissional_id JOIN categorias c ON c.id = p.categoria_id
    WHERE q.usuario_id = ? ORDER BY q.id DESC LIMIT 100', [$u['id']]) : [];
$media = $avaliacoes ? array_sum(array_column($avaliacoes, 'estrelas')) / count($avaliacoes) : 0;
$por_area = [];
foreach ($favoritos as $f) $por_area[$f['categoria']] = ($por_area[$f['categoria']] ?? 0) + 1;
arsort($por_area);

$comercio = fn(array $l) => '<strong>' . adm_comercio((int)$l['id'], $l['nome'], $l['cidade']) . '</strong><br><span class="discreto">' . h($l['tipo'] ?: $l['categoria'])
    . ' · ' . adm_cidade($l['cidade'], null, true) . ($l['bairro'] ? ' · ' . h($l['bairro']) : '') . '</span>';
$botoes = fn(array $l) => '<a class="botao pequeno contorno" href="numeros-ficha.php?id=' . (int)$l['id'] . '">Números</a>'
    . (($l['status_ficha'] ?? $l['status']) === 'ativo' ? ' <a class="botao pequeno contorno" href="' . h(url_ficha($l)) . '" target="_blank" rel="noopener">Abrir</a>' : '');
$estrelas = fn(int $n) => '<span title="' . $n . ' de 5" style="color:var(--amarelo);white-space:nowrap">' . str_repeat('★', $n) . '</span><span style="color:#ccc">' . str_repeat('★', 5 - $n) . '</span>';

admin_inicio($u['nome'], $admin);
?>
<p><a href="pessoas.php">‹ Pessoas</a></p>
<div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
  <?php if ($u['foto'] && str_starts_with($u['foto'], 'https://')): ?>
  <img src="<?= h($u['foto']) ?>" alt="" width="64" height="64" style="border-radius:50%;object-fit:cover" referrerpolicy="no-referrer" onerror="this.remove()">
  <?php endif ?>
  <div>
    <h1 style="margin:0"><?= h($u['nome']) ?></h1>
    <p class="discreto" style="margin:4px 0 0"><a href="mailto:<?= h($u['email']) ?>"><?= h($u['email']) ?></a> · cadastro <?= h(data_br($u['criado_em'])) ?>
      · último uso <?= h(data_br($u['ultimo_uso'])) ?><?= $u['ativo'] ? '' : ' · <strong>conta desativada</strong>' ?></p>
  </div>
</div>

<div class="numeros-resumo">
  <div><strong><?= num(count($favoritos)) ?></strong><span>favoritos</span></div>
  <div><strong><?= num(count($avaliacoes)) ?></strong><span>avaliações</span></div>
  <div><strong><?= $avaliacoes ? number_format($media, 1, ',', '.') : '—' ?></strong><span>média de estrelas</span></div>
</div>

<h2 id="notificar">Mandar notificação para esta pessoa</h2>
<?php if (!$aparelhos): ?>
  <p class="discreto">Ela não tem o app com os avisos ligados nesta conta (só usou o site, ou não permitiu as notificações). Use o e-mail
    <a href="mailto:<?= h($u['email']) ?>"><?= h($u['email']) ?></a>.</p>
<?php else: ?>
  <form method="post" class="quadro">
    <?= campo_csrf() ?><input type="hidden" name="acao" value="notificar">
    <p class="discreto">Vai para <?= $aparelhos ?> celular(es) dela. Só ela recebe.</p>
    <label for="n-titulo">Título</label>
    <input id="n-titulo" name="titulo" maxlength="80" required placeholder="Ex.: Seu pagamento não foi concluído">
    <label for="n-texto">Texto</label>
    <textarea id="n-texto" name="texto" rows="3" maxlength="180" required placeholder="Ex.: Fale com a gente no WhatsApp para terminar o plano Básico."></textarea>
    <p><button class="botao">Enviar notificação</button></p>
  </form>
<?php endif ?>

<h2 id="favoritos">Favoritos</h2>
<?php if (!$favoritos): ?><p class="discreto">Não salvou nenhum comércio.</p><?php else: ?>
<?php if (count($por_area) > 1): ?><p class="discreto">Por área: <?= h(implode(' · ', array_map(fn($a, $n) => "$a $n", array_keys($por_area), $por_area))) ?></p><?php endif ?>
<div class="tabela"><table>
  <tr><th>Comércio</th><th>Salvo em</th><th></th></tr>
  <?php foreach ($favoritos as $f): ?><tr>
    <td><?= $comercio($f) ?><?= $f['status'] === 'ativo' ? '' : ' <span class="discreto">(fora do app)</span>' ?></td>
    <td><?= h(data_br($f['criado_em'])) ?></td><td><?= $botoes($f) ?></td>
  </tr><?php endforeach ?>
</table></div>
<?php endif ?>

<?php if ($pedidos_info): ?>
<h2 id="quero-saber">Pediu informação ("Quero saber")</h2>
<div class="tabela"><table>
  <tr><th>Comércio</th><th>Pediu</th><th>Quando</th><th></th></tr>
  <?php foreach ($pedidos_info as $q): ?><tr>
    <td><?= $comercio($q) ?></td><td><?= h(TIPOS_PEDIDO_INFO[$q['pedido']] ?? $q['pedido']) ?><?= $q['origem'] === 'web' ? ' <span class="discreto">(site)</span>' : '' ?></td>
    <td><?= h(datahora_br($q['quando'])) ?></td><td><?= $botoes($q) ?></td>
  </tr><?php endforeach ?>
</table></div>
<?php endif ?>

<h2 id="avaliacoes">Avaliações</h2>
<?php if (!$avaliacoes): ?><p class="discreto">Não avaliou nenhum comércio.</p><?php else: ?>
<div class="tabela"><table>
  <tr><th>Comércio</th><th>Nota</th><th>Comentário</th><th>Data</th><th></th></tr>
  <?php foreach ($avaliacoes as $a): ?><tr>
    <td><?= $comercio($a) ?></td>
    <td><?= $estrelas((int)$a['estrelas']) ?><?= $a['status'] === 'oculta' ? '<br><span class="discreto">(oculta)</span>' : '' ?></td>
    <td><?= $a['comentario'] !== null && $a['comentario'] !== '' ? nl2br(h($a['comentario'])) : '<span class="discreto">sem comentário</span>' ?>
      <?php if ($a['resposta']): ?><br><span class="discreto"><strong>Resposta do comércio:</strong> <?= h($a['resposta']) ?></span><?php endif ?></td>
    <td><?= h(data_br($a['criado_em'])) ?><?= $a['atualizado_em'] && substr($a['atualizado_em'], 0, 10) !== substr($a['criado_em'], 0, 10) ? '<br><span class="discreto">editada ' . h(data_br($a['atualizado_em'])) . '</span>' : '' ?></td>
    <td><?= $botoes($a) ?></td>
  </tr><?php endforeach ?>
</table></div>
<?php endif ?>
<?php admin_fim();
