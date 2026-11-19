<?php
// Oferta da semana do dono (19/11, inc/ofertas.php): escrever, pagar (Checkout do Mercado Pago), acompanhar a conferência,
// corrigir quando recusada (sem pagar de novo) e encerrar antes do prazo. Só para o Destaque em dia.
require __DIR__ . '/../../inc/moderacao.php';
require __DIR__ . '/../../inc/pagina.php';
require_once __DIR__ . '/../../inc/ofertas.php';
require_once __DIR__ . '/../../inc/conta-web.php';
require_once __DIR__ . '/../../inc/mercadopago.php';
iniciar_sessao();
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

// como no painel: quem entrou com a conta Google e é dono abre direto
if (empty($_SESSION['dono_acesso']) && empty($_SESSION['dono_saiu']) && ($conta = usuario_web()) && ($ja = acesso_da_conta((int)$conta['id']))) {
    session_regenerate_id(true);
    $_SESSION['dono_acesso'] = $ja;
}
$p = dono_atual();
if (!$p) ir('painel/');
if (!ofertas_prontas()) { flash('As ofertas ainda não estão ligadas.', 'erro'); ir('painel/'); }
$pid = (int)$p['id'];
$destaque = (int)valor('SELECT COUNT(*) FROM profissionais p WHERE p.id = ? AND ' . SQL_DESTAQUE, [$pid]) > 0;
$conta = usuario_web();

// volta do Mercado Pago (?payment_id=...): confere na hora, sem esperar o aviso
if (isset($_GET['payment_id']) && preg_match('/^\d{1,30}$/', (string)$_GET['payment_id'])) {
    try { mp_conferir_pagamento((string)$_GET['payment_id']); } catch (Throwable $e) { error_log('acha-facil oferta volta: ' . $e->getMessage()); }
    $o = oferta_do_dono($pid);
    if ($o && $o['status'] === 'em_analise') flash('Pagamento confirmado! A equipe confere a oferta e ela entra no ar (normalmente em até 1 dia útil).');
    elseif (($_GET['status'] ?? '') === 'pending' || ($_GET['collection_status'] ?? '') === 'pending') flash('Pagamento em processamento (Pix ou boleto). Assim que o Mercado Pago confirmar, a oferta vai para a conferência.');
    elseif (in_array($_GET['status'] ?? '', ['rejected', 'null'], true)) flash('O pagamento não foi concluído. Tente de novo.', 'erro');
    ir('painel/oferta.php');
}

$o = oferta_do_dono($pid);
$erro = null;
$dados = $o ?: [];
if (e_post()) {
    exigir_post();
    $acao = (string)($_POST['acao'] ?? '');
    if (!$destaque && $acao !== 'encerrar') { flash('A Oferta da semana é do plano Destaque.', 'erro'); ir('painel/oferta.php'); }
    if ($acao === 'encerrar' && $o && $o['status'] === 'no_ar') {
        encerrar_oferta((int)$o['id']);
        flash('Oferta encerrada. Ela já saiu da ficha e da página da cidade.');
        ir('painel/oferta.php');
    }
    if ($acao === 'cancelar' && $o && $o['status'] === 'aguardando_pagamento') {
        q("UPDATE ofertas SET status = 'cancelada', atualizado_em = NOW() WHERE id = ? AND pago_em IS NULL", [$o['id']]);
        apagar_foto($o['foto']);
        flash('Oferta descartada.');
        ir('painel/oferta.php');
    }
    if ($acao === 'pagar' && $o && $o['status'] === 'aguardando_pagamento') {
        try { header('Location: ' . mp_pagar_oferta($o)); exit; }
        catch (Throwable $e) { error_log('acha-facil oferta pagar: ' . $e->getMessage()); $erro = mp_erro_para_dono($e); }
    }
    if ($acao === 'salvar') {
        // pode escrever quando não tem nenhuma em andamento, quando ainda não pagou ou quando foi recusada
        if ($o && in_array($o['status'], ['em_analise', 'no_ar'], true)) { flash('Já existe uma oferta ' . mb_strtolower(OFERTA_STATUS[$o['status']]) . '.', 'erro'); ir('painel/oferta.php'); }
        [$dados, $erro] = oferta_do_post($_POST);
        $foto = null;
        if (!$erro && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try { $foto = salvar_foto_oferta($_FILES['foto'], $pid); } catch (RuntimeException $e) { $erro = 'Foto: ' . $e->getMessage(); }
        }
        if (!$erro && (int)valor('SELECT COUNT(*) FROM ofertas WHERE profissional_id = ? AND criado_em > NOW() - INTERVAL 1 DAY', [$pid]) >= 10) $erro = 'Muitas tentativas hoje. Tente amanhã.';
        if (!$erro) {
            $campos = $dados;
            if ($foto) $campos['foto'] = $foto;
            elseif (!empty($_POST['tirar_foto']) && $o) $campos['foto'] = null;
            if ($o && in_array($o['status'], ['aguardando_pagamento', 'recusada'], true)) {
                if (array_key_exists('foto', $campos) && $o['foto'] && $o['foto'] !== ($campos['foto'] ?? null)) apagar_foto($o['foto']);
                // recusada e já paga: volta direto para a conferência, sem pagar de novo
                $campos['status'] = $o['pago_em'] ? 'em_analise' : 'aguardando_pagamento';
                $campos['motivo'] = null;
                $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($campos)));
                q("UPDATE ofertas SET $sets, atualizado_em = NOW() WHERE id = ?", [...array_values($campos), $o['id']]);
                $id = (int)$o['id'];
                if ($o['pago_em']) {
                    require_once __DIR__ . '/../../inc/avisos.php';
                    avisar_admins('Oferta corrigida', $p['nome'] . ' corrigiu a oferta: ' . $dados['titulo'], ['oferta' => $id]);
                    flash('Oferta corrigida e enviada de novo para a conferência (sem pagar outra vez).');
                    ir('painel/oferta.php');
                }
            } else {
                $id = inserir('ofertas', $campos + ['profissional_id' => $pid, 'usuario_id' => $conta ? (int)$conta['id'] : null,
                    'codigo' => bin2hex(random_bytes(16)), 'valor' => oferta_preco()]);
            }
            $o = linha('SELECT * FROM ofertas WHERE id = ?', [$id]);
            try { header('Location: ' . mp_pagar_oferta($o)); exit; }
            catch (Throwable $e) {
                error_log('acha-facil oferta pagar: ' . $e->getMessage());
                flash('Oferta guardada, mas ' . mb_strtolower(mp_erro_para_dono($e)) . ' Use o botão "Pagar" abaixo.', 'erro');
                ir('painel/oferta.php');
            }
        } elseif ($foto) apagar_foto($foto);
    }
}

pagina_inicio('Oferta da semana | Acha Fácil!', 'Oferta da semana do seu comércio no Acha Fácil.', '', ['noindex' => true]);
$v = fn(string $k) => h((string)($dados[$k] ?? ''));
$preco_campo = fn(string $k) => isset($dados[$k]) && $dados[$k] !== null && $dados[$k] !== 'erro' ? h(number_format((float)$dados[$k], 2, ',', '')) : '';
$pode_escrever = !$o || in_array($o['status'], ['aguardando_pagamento', 'recusada'], true);
?>
<main class="documento">
<?php mostrar_flash(); ?>
<p class="discreto"><a href="<?= h(url('painel/')) ?>">← Voltar ao painel</a></p>
<h1>🏷️ Oferta da semana</h1>
<p>Uma promoção em destaque na <strong>ficha do seu comércio</strong> e na <strong>página da cidade</strong>, na seção
  "Ofertas da semana", por até <?= OFERTA_MAX_DIAS ?> dias. <strong><?= h(oferta_preco_texto()) ?> por oferta</strong>, sem
  mensalidade: você só paga quando tiver uma promoção. A equipe confere antes de publicar.</p>

<?php if (!$destaque): ?>
  <div class="trancado"><p class="trancado-titulo"><span aria-hidden="true">🔒</span> Exclusivo do plano Destaque</p>
    <p class="trancado-texto">Assine o Destaque para colocar ofertas no ar quando quiser.</p>
    <a class="botao amarelo" href="<?= h(url('perfil.php?acao=assumir&plano=destaque&id=' . $pid)) ?>">Quero o Destaque</a></div>
<?php endif ?>

<?php if ($o): ?>
  <div class="quadro">
    <p><strong>Situação: <?= h(OFERTA_STATUS[$o['status']]) ?></strong>
      <?php if ($o['status'] === 'no_ar'): ?> · <?= h(oferta_validade($o)) ?><?php endif ?></p>
    <?php if ($o['status'] === 'recusada'): ?><div class="aviso erro">A equipe pediu um ajuste: <?= h((string)$o['motivo']) ?>
      <?= $o['pago_em'] ? '<br>Corrija abaixo e mande de novo: <strong>não precisa pagar outra vez.</strong>' : '' ?></div><?php endif ?>
    <?php if ($o['status'] === 'em_analise'): ?><p class="discreto">Pagamento confirmado em <?= h(datahora_br((string)$o['pago_em'])) ?>.
      A oferta entra no ar assim que a equipe conferir e fica <?= (int)$o['dias'] ?> dia<?= $o['dias'] > 1 ? 's' : '' ?> a partir daí.</p><?php endif ?>
    <?= oferta_quadro_ficha(array_merge($o, ['fim' => $o['fim'] ?? date('Y-m-d H:i:s', strtotime('+' . (int)$o['dias'] . ' days'))])) ?>
    <?php if ($o['status'] === 'aguardando_pagamento'): ?>
      <form method="post" class="linha-botoes"><?= campo_csrf() ?>
        <button class="botao amarelo" name="acao" value="pagar">Pagar <?= h(dinheiro((string)$o['valor'])) ?> (cartão, Pix ou boleto)</button>
        <button class="botao contorno" name="acao" value="cancelar" onclick="return confirm('Descartar esta oferta?')">Descartar</button></form>
    <?php elseif ($o['status'] === 'no_ar'): ?>
      <form method="post"><?= campo_csrf() ?><button class="botao contorno" name="acao" value="encerrar" onclick="return confirm('Tirar a oferta do ar agora? O valor não é devolvido.')">Encerrar a oferta agora</button></form>
      <p class="discreto">Quando ela terminar, você pode colocar a próxima.</p>
    <?php endif ?>
  </div>
<?php endif ?>

<?php if ($destaque && $pode_escrever): ?>
  <h2><?= $o ? 'Editar a oferta' : 'Nova oferta' ?></h2>
  <?php if ($erro): ?><div class="aviso erro"><?= h($erro) ?></div><?php endif ?>
  <form method="post" enctype="multipart/form-data">
    <?= campo_csrf() ?>
    <input type="hidden" name="acao" value="salvar">
    <label for="titulo">Título da oferta <small class="discreto">(até <?= OFERTA_TITULO_MAX ?> letras: curto e direto, o que é a oferta)</small></label>
    <input id="titulo" name="titulo" required minlength="4" maxlength="<?= OFERTA_TITULO_MAX ?>" data-contar value="<?= $v('titulo') ?>" placeholder="Ex.: Pizza grande + refri 2 L">
    <label for="texto">Detalhes (opcional) <small class="discreto">(até <?= OFERTA_TEXTO_MAX ?> letras: dias, regras, como pedir)</small></label>
    <textarea id="texto" name="texto" rows="3" maxlength="<?= OFERTA_TEXTO_MAX ?>" data-contar placeholder="Ex.: De segunda a quinta, só para retirada. Não vale com outras promoções."><?= $v('texto') ?></textarea>
    <div class="duas-colunas">
      <div><label for="preco_de">Preço normal, "de" (opcional)</label>
        <input id="preco_de" name="preco_de" inputmode="decimal" maxlength="12" placeholder="59,90" value="<?= $preco_campo('preco_de') ?>"></div>
      <div><label for="preco_por">Preço da oferta, "por" (opcional)</label>
        <input id="preco_por" name="preco_por" inputmode="decimal" maxlength="12" placeholder="49,90" value="<?= $preco_campo('preco_por') ?>"></div>
    </div>
    <label for="dias">Quantos dias no ar</label>
    <select id="dias" name="dias"><?php for ($d = 1; $d <= OFERTA_MAX_DIAS; $d++): ?><option value="<?= $d ?>"<?= (int)($dados['dias'] ?? OFERTA_MAX_DIAS) === $d ? ' selected' : '' ?>><?= $d ?> dia<?= $d > 1 ? 's' : '' ?></option><?php endfor ?></select>
    <label for="foto">Foto da oferta (opcional)</label>
    <p class="discreto" style="margin:0 0 6px">📐 Foto <strong>quadrada, 800 x 800 pixels</strong> (mínimo <?= OFERTA_FOTO_MIN ?> x <?= OFERTA_FOTO_MIN ?>). JPG, PNG ou WEBP até 8 MB.
      Se mandar deitada ou em pé, cortamos o meio para ficar quadrada: deixe o produto no centro e evite texto nas bordas.</p>
    <input id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp">
    <div id="foto-previa" class="foto-previa" hidden><img alt="Prévia de como a foto vai aparecer"><small></small></div>
    <?php if (!empty($dados['foto'])): ?><label class="marcar"><input type="checkbox" name="tirar_foto" value="1"> Tirar a foto atual</label><?php endif ?>
    <p><button class="botao amarelo"><?= $o && $o['pago_em'] ? 'Enviar de novo para a conferência' : 'Continuar para o pagamento (' . h(oferta_preco_texto()) . ')' ?></button></p>
    <p class="discreto">Os dias contam a partir de quando a equipe aprovar. Sem links e sem telefone no texto: os botões de
      WhatsApp, ligar e site já ficam na sua ficha.</p>
  </form>
  <?= oferta_campos_ajuda_js() ?>
<?php endif ?>
</main>
<?php pagina_fim();
