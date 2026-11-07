<?php
// Painel do dono do comércio. Entra pela página da assinatura depois de pagar (?c=código do pedido) ou pelo link que a
// administração manda (?t=..., casos sem pagamento). Aqui o dono só PEDE
// mudanças: tudo vira uma alteração pendente (inc/moderacao.php) e só aparece no app depois que um admin aprova.
require __DIR__ . '/../../inc/moderacao.php';
require __DIR__ . '/../../inc/pagina.php';
iniciar_sessao();
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

// entrada pelo link: guarda na sessão e tira o código do endereço
if (isset($_GET['t'])) {
    $t = (string)$_GET['t'];
    $acesso = preg_match('/^[a-f0-9]{48}$/', $t) && !login_bloqueado()
        ? linha('SELECT id FROM acessos_dono WHERE token_hash = ? AND ativo = 1', [hash('sha256', $t)]) : null;
    if ($acesso) {
        session_regenerate_id(true);
        $_SESSION['dono_acesso'] = (int)$acesso['id'];
        q('UPDATE acessos_dono SET ultimo_uso = NOW() WHERE id = ?', [$acesso['id']]);
    } else {
        login_falhou();
        flash('Este link não vale mais. Peça um novo para a equipe do Acha Fácil.', 'erro');
    }
    ir('painel/');
}
if (isset($_GET['c'])) {
    $acesso_id = login_bloqueado() ? null : acesso_pelo_pagamento((string)$_GET['c']);
    if ($acesso_id) {
        session_regenerate_id(true);
        $_SESSION['dono_acesso'] = $acesso_id;
        q('UPDATE acessos_dono SET ultimo_uso = NOW() WHERE id = ?', [$acesso_id]);
    } else {
        login_falhou();
        flash('A assinatura ainda não foi confirmada pelo Mercado Pago. Volte à página da assinatura e tente de novo em instantes.', 'erro');
    }
    ir('painel/');
}
if (isset($_GET['sair'])) { unset($_SESSION['dono_acesso']); session_regenerate_id(true); ir('painel/'); }

$p = dono_atual();
$em_dia = $p && (int)valor('SELECT COUNT(*) FROM profissionais p WHERE p.id = ? AND ' . SQL_EM_DIA, [$p['id']]) > 0;
$erro = null;

if ($p && $em_dia && e_post()) {
    exigir_post();
    [$valores, $erro] = limpar_campos_dono($_POST);
    $foto = null;
    if (!$erro && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try { $foto = salvar_foto_enviada($_FILES['foto'], (int)$p['id']); } catch (RuntimeException $e) { $erro = $e->getMessage(); }
    }
    if (!$erro && (int)valor('SELECT COUNT(*) FROM alteracoes WHERE profissional_id = ? AND criado_em > NOW() - INTERVAL 1 DAY', [$p['id']]) >= 20) {
        $erro = 'Muitos envios hoje. Tente amanhã.';
    }
    if ($erro) apagar_foto($foto);
    else {
        $id = propor_alteracao($p, (int)$p['acesso_id'], $valores, $foto);
        flash($id ? 'Recebemos suas mudanças. Elas aparecem no app assim que a equipe conferir (normalmente em até 1 dia útil).'
                  : 'Nada mudou em relação ao que já está no app.');
        ir('painel/');
    }
}

$pend = $p ? alteracao_pendente((int)$p['id']) : null;
$recusada = $p ? linha("SELECT motivo, decidido_em FROM alteracoes WHERE profissional_id = ? AND status = 'recusada'
    AND decidido_em > NOW() - INTERVAL 30 DAY AND NOT EXISTS (SELECT 1 FROM alteracoes b WHERE b.profissional_id = ? AND b.id > alteracoes.id)
    ORDER BY id DESC LIMIT 1", [$p['id'], $p['id']]) : null;
// o formulário começa com o que foi pedido (pendente) ou com o que está no app
$f = $p ? array_merge(array_intersect_key($p, CAMPOS_DONO), $pend['novos'] ?? []) : [];
if ($erro) $f = array_merge($f, $valores);
$val = fn(string $c) => h($f[$c] ?? '');

pagina_inicio('Painel do comércio · Acha Fácil!', 'Painel do dono do comércio no Acha Fácil.');
?>
<main class="documento">
<?php mostrar_flash(); ?>
<?php if (!$p): ?>
  <h1>Painel do comércio</h1>
  <p>O painel abre depois que você assina um plano: na página da sua assinatura, toque em <strong>Editar os dados do
    meu comércio</strong>. Tudo o que você mudar aparece no app depois da conferência da equipe.</p>
  <p><a class="botao amarelo" href="<?= h(config()['url']) ?>/busca.php">Procurar meu negócio</a></p>
<?php else: ?>
  <h1><?= h($p['nome']) ?></h1>
  <p class="discreto">Olá, <?= h($p['dono_nome']) ?> · <a href="?sair=1">Sair</a></p>

  <div class="quadro"><strong>Como funciona:</strong> você pede as mudanças aqui e a equipe do Acha Fácil confere antes de
    publicar. Assim ninguém consegue mudar o telefone ou o endereço de um comércio para enganar clientes.</div>

  <?php if ($pend): ?>
    <div class="aviso ok">Esperando a conferência desde <?= h(datahora_br($pend['criado_em'])) ?>:
      <?= h(implode(', ', array_map(fn($c) => CAMPOS_DONO[$c][0] ?? $c, array_keys($pend['novos'])))) ?>.
      Se enviar de novo, o pedido anterior é substituído.</div>
  <?php elseif ($recusada): ?>
    <div class="aviso erro">Seu último pedido não foi aprovado: <?= h($recusada['motivo']) ?></div>
  <?php endif ?>

  <?php if (!$em_dia): ?>
    <p>Para corrigir os dados e colocar foto, o comércio precisa de um plano ativo.</p>
    <p><a class="botao amarelo" href="<?= h(config()['url']) ?>/planos.php?id=<?= (int)$p['id'] ?>">Conferir os planos</a></p>
  <?php else: ?>
    <?php if ($erro): ?><div class="aviso erro"><?= h($erro) ?></div><?php endif ?>
    <form method="post" enctype="multipart/form-data">
      <?= campo_csrf() ?>
      <label for="nome">Nome do comércio</label>
      <input id="nome" name="nome" required maxlength="120" value="<?= $val('nome') ?>">
      <label for="descricao">Sobre o comércio</label>
      <textarea id="descricao" name="descricao" rows="4" maxlength="2000"><?= $val('descricao') ?></textarea>
      <label for="servicos">Produtos e serviços (separe por vírgula)</label>
      <input id="servicos" name="servicos" maxlength="500" value="<?= $val('servicos') ?>">
      <label for="whatsapp">WhatsApp (com DDD)</label>
      <input id="whatsapp" name="whatsapp" inputmode="tel" maxlength="20" value="<?= h(telefone_formatado($f['whatsapp'] ?? null) ?: '') ?>">
      <label for="telefone">Telefone (com DDD)</label>
      <input id="telefone" name="telefone" inputmode="tel" maxlength="20" value="<?= h(telefone_formatado($f['telefone'] ?? null) ?: '') ?>">
      <label for="instagram">Instagram</label>
      <input id="instagram" name="instagram" maxlength="60" placeholder="@seucomercio" value="<?= $val('instagram') ?>">
      <label for="site">Site</label>
      <input id="site" name="site" maxlength="200" inputmode="url" value="<?= $val('site') ?>">
      <label for="endereco">Endereço</label>
      <input id="endereco" name="endereco" maxlength="200" value="<?= $val('endereco') ?>">
      <label for="bairro">Bairro</label>
      <input id="bairro" name="bairro" maxlength="80" value="<?= $val('bairro') ?>">
      <label for="horario">Horário de funcionamento</label>
      <input id="horario" name="horario" maxlength="200" placeholder="Seg a sex 8h às 18h · Sáb 8h às 12h" value="<?= $val('horario') ?>">
      <label class="marcar"><input type="checkbox" name="atende_em_casa" value="1"<?= ($f['atende_em_casa'] ?? '0') == '1' ? ' checked' : '' ?>> Atende em casa / faz entrega</label>
      <label for="foto">Foto do comércio (JPG ou PNG, até 8 MB)</label>
      <?php if (!empty($f['foto'])): ?><p><img class="mini-foto" src="<?= h(foto_url($f['foto'])) ?>" alt="Foto atual"></p><?php endif ?>
      <input id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp">
      <p><button class="botao">Enviar para conferência</button></p>
      <p class="discreto">Nada muda no app até a equipe aprovar.</p>
    </form>
  <?php endif ?>
<?php endif ?>
</main>
<?php pagina_fim();
