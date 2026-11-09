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
    $foto = null;      // logo novo
    $enviadas = [];    // fotos novas da galeria (apagadas se der erro)
    if (!$erro && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try { $foto = salvar_foto_enviada($_FILES['foto'], (int)$p['id'], 600); } catch (RuntimeException $e) { $erro = 'Logo: ' . $e->getMessage(); }
    }
    // galeria: parte da lista que o formulário mostrou (pendente ou do app), tira as marcadas e põe as novas no fim
    $pend_antes = alteracao_pendente((int)$p['id']);
    $base = lista_fotos(array_key_exists('fotos', $pend_antes['novos'] ?? []) ? $pend_antes['novos']['fotos'] : $p['fotos']);
    $tirar = array_map('strval', (array)($_POST['tirar'] ?? []));
    $galeria = array_values(array_filter($base, fn($f) => !in_array($f, $tirar, true)));
    $novas = $_FILES['fotos_novas'] ?? null;
    if (!$erro && $novas && is_array($novas['error'])) {
        foreach ($novas['error'] as $i => $e) {
            if ($e === UPLOAD_ERR_NO_FILE) continue;
            if (count($galeria) + count($enviadas) >= MAX_FOTOS) { $erro = 'São no máximo ' . MAX_FOTOS . ' fotos. Tire alguma antes de colocar outras.'; break; }
            $um = ['error' => $e, 'size' => $novas['size'][$i], 'tmp_name' => $novas['tmp_name'][$i]];
            try { $enviadas[] = salvar_foto_enviada($um, (int)$p['id'], 1600); } catch (RuntimeException $ex) { $erro = 'Foto ' . ($i + 1) . ': ' . $ex->getMessage(); break; }
        }
    }
    $galeria = array_merge($galeria, $enviadas);
    if (!$erro && (int)valor('SELECT COUNT(*) FROM alteracoes WHERE profissional_id = ? AND criado_em > NOW() - INTERVAL 1 DAY', [$p['id']]) >= 20) {
        $erro = 'Muitos envios hoje. Tente amanhã.';
    }
    if ($erro) { apagar_foto($foto); array_map('apagar_foto', $enviadas); }
    else {
        $id = propor_alteracao($p, (int)$p['acesso_id'], $valores, $foto, $galeria);
        if ($id) {
            require_once __DIR__ . '/../../inc/avisos.php';
            $campos = array_map(fn($c) => mb_strtolower(CAMPOS_DONO[$c][0]), array_keys(alteracao_pendente((int)$p['id'])['novos'] ?? []));
            avisar_admins('Alteração para aprovar', $p['nome'] . ' pediu para mudar: ' . implode(', ', $campos) . '.', ['alteracao' => $id]);
        }
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
$galeria_form = lista_fotos($f['fotos'] ?? null);

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
    <p>Para corrigir os dados e colocar o logo e as fotos, o comércio precisa de um plano ativo.</p>
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
      <h2>Logo</h2>
      <p class="dica">Aparece nas listas do app e no círculo da página do comércio. Melhor quadrada, com o logo no meio.</p>
      <?php if (!empty($f['foto'])): ?><p><img class="mini-logo" src="<?= h(foto_url($f['foto'])) ?>" alt="Logo atual"></p><?php endif ?>
      <label for="foto"><?= empty($f['foto']) ? 'Escolher o logo' : 'Trocar o logo' ?> (JPG, PNG ou WEBP)</label>
      <input id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp" data-lado="600">

      <h2>Fotos do comércio</h2>
      <p class="dica">Até <?= MAX_FOTOS ?> fotos: fachada, ambiente, produtos. Elas passam uma a uma no alto da página do
        comércio no app; a primeira também aparece na faixa de destaque. Fotos deitadas ficam melhores.</p>
      <?php if ($galeria_form): ?>
        <div class="fotos-dono">
          <?php foreach ($galeria_form as $i => $arq): ?>
            <figure>
              <img src="<?= h(foto_url($arq)) ?>" alt="Foto <?= $i + 1 ?>">
              <label><input type="checkbox" name="tirar[]" value="<?= h($arq) ?>"> Tirar esta foto</label>
            </figure>
          <?php endforeach ?>
        </div>
      <?php endif ?>
      <?php if (count($galeria_form) < MAX_FOTOS): ?>
        <label for="fotos_novas">Colocar fotos (pode escolher várias de uma vez)</label>
        <input id="fotos_novas" name="fotos_novas[]" type="file" multiple accept="image/jpeg,image/png,image/webp" data-lado="1600" data-max="<?= MAX_FOTOS - count($galeria_form) ?>">
      <?php else: ?>
        <p class="dica">A galeria está cheia. Marque "Tirar esta foto" nas que quiser trocar e envie; depois coloque as novas.</p>
      <?php endif ?>
      <p><button class="botao">Enviar para conferência</button></p>
      <p class="discreto">Nada muda no app até a equipe aprovar.</p>
    </form>
    <script>
    // Reduz as fotos no próprio celular antes de enviar (foto de celular passa fácil de 5 MB e o envio falharia)
    document.querySelectorAll('input[type=file][data-lado]').forEach(function (inp) {
      inp.addEventListener('change', async function () {
        var max = parseInt(inp.dataset.max || '99', 10);
        if (inp.files.length > max) { alert('Escolha no máximo ' + max + ' foto(s).'); inp.value = ''; return; }
        if (!window.DataTransfer || !window.createImageBitmap) return;
        var lado = parseInt(inp.dataset.lado, 10), dt = new DataTransfer();
        try {
          for (var f of inp.files) {
            var img = await createImageBitmap(f, { imageOrientation: 'from-image' });
            var k = Math.min(1, lado / Math.max(img.width, img.height));
            var c = document.createElement('canvas');
            c.width = Math.round(img.width * k); c.height = Math.round(img.height * k);
            var ctx = c.getContext('2d'); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
            ctx.drawImage(img, 0, 0, c.width, c.height);
            var blob = await new Promise(function (r) { c.toBlob(r, 'image/jpeg', 0.88); });
            dt.items.add(new File([blob], f.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }));
          }
          inp.files = dt.files;
        } catch (e) { /* navegador antigo: manda o arquivo original */ }
      });
    });
    </script>
  <?php endif ?>
<?php endif ?>
</main>
<?php pagina_fim();
