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
$destaque = $p && (int)valor('SELECT COUNT(*) FROM profissionais p WHERE p.id = ? AND ' . SQL_DESTAQUE, [$p['id']]) > 0;
// números da ficha (passo 20): Destaque vê os últimos 30 dias completos; os outros, o total do mês
$numeros = $p && numeros_prontos() ? numeros_ficha((int)$p['id'], $destaque ? desde_dias(30) : date('Y-m-01')) : null;
$erro = null;

if ($p && $em_dia && e_post()) {
    exigir_post();
    [$valores, $erro] = limpar_campos_dono($_POST);
    if (!$erro) [$valores['horarios'], $erro] = horarios_do_post((array)($_POST['hor'] ?? []));
    $foto = null;      // logo novo
    $enviadas = [];    // fotos novas da galeria (apagadas se der erro)
    if (!$erro && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try { $foto = salvar_foto_enviada($_FILES['foto'], (int)$p['id'], 600, true); } catch (RuntimeException $e) { $erro = 'Logo: ' . $e->getMessage(); }
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

  <?php if ($numeros): ?>
  <h2>Números do seu comércio</h2>
  <?php if ($destaque): ?>
    <p class="discreto">Últimos 30 dias.</p>
    <div class="numeros-resumo">
      <div><strong><?= num($numeros['aparicoes']) ?></strong><span>vezes em listas e buscas</span></div>
      <div><strong><?= num($numeros['visualizacoes']) ?></strong><span>vezes que abriram a ficha</span></div>
      <div><strong><?= num($numeros['pessoas']) ?></strong><span>pessoas diferentes</span></div>
      <?php foreach ($numeros['por_tipo'] as $k => $t): if (!$t) continue; ?><div><strong><?= num($t) ?></strong><span>toques em <?= TIPOS_CONTATO[$k] ?></span></div><?php endforeach ?>
    </div>
    <?php if ($numeros['origens']): ?><p class="discreto">De onde abriram:
      <?= h(implode(' · ', array_map(fn($o, $t) => (ORIGENS[$o] ?? $o) . ' ' . num($t), array_keys($numeros['origens']), $numeros['origens']))) ?></p><?php endif ?>
  <?php else: ?>
    <div class="numeros-resumo">
      <div><strong><?= num($numeros['visualizacoes']) ?></strong><span>vezes que abriram a sua ficha neste mês</span></div>
      <div><strong><?= num($numeros['pessoas']) ?></strong><span>pessoas diferentes</span></div>
    </div>
    <p class="discreto">No plano Destaque você vê também quantas vezes apareceu nas buscas, de onde as pessoas vieram e quantas
      tocaram no WhatsApp, em ligar e no mapa. <a href="<?= h(config()['url']) ?>/planos.php?id=<?= (int)$p['id'] ?>">Conferir os planos</a></p>
  <?php endif ?>
  <?php endif ?>

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
      <?= horarios_form(horarios_ler($f['horarios'] ?? null)) ?>
      <?php if (empty($f['horarios']) && !empty($f['horario'])): ?><p class="dica">Hoje no app: <?= h($f['horario']) ?></p><?php endif ?>
      <label class="marcar"><input type="checkbox" name="atende_em_casa" value="1"<?= ($f['atende_em_casa'] ?? '0') == '1' ? ' checked' : '' ?>> Atende em casa / faz entrega</label>
      <h2>Logo</h2>
      <p class="dica">Aparece nas listas do app e no círculo da página do comércio (como a foto de perfil). Use uma imagem
        <strong>quadrada, de pelo menos 500 x 500 pixels</strong>, com o logo no meio. Se a sua for de outro formato, depois de
        escolher você arrasta e aproxima para enquadrar no círculo.</p>
      <?php if (!empty($f['foto'])): ?><p><img class="mini-logo" src="<?= h(foto_url($f['foto'])) ?>" alt="Logo atual"></p><?php endif ?>
      <label for="foto"><?= empty($f['foto']) ? 'Escolher o logo' : 'Trocar o logo' ?> (JPG, PNG ou WEBP)</label>
      <input id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp">
      <div id="recorte" class="recorte" hidden>
        <p class="dica">Arraste a imagem para posicionar e use a barra para aproximar. O que fica dentro do círculo é o que aparece no app.</p>
        <div class="recorte-area"><canvas id="recorte-tela" width="560" height="560"></canvas></div>
        <label for="recorte-zoom">Aproximar</label>
        <input id="recorte-zoom" type="range" min="0.5" max="4" step="0.01" value="0.85">
        <p><button type="button" class="link" id="recorte-inteiro">Mostrar o logo inteiro</button> ·
          <button type="button" class="link" id="recorte-preencher">Preencher o círculo</button></p>
      </div>

      <h2>Fotos do comércio</h2>
      <p class="dica">Até <?= MAX_FOTOS ?> fotos: fachada, ambiente, produtos. Elas passam uma a uma no alto da página do
        comércio no app; a primeira também aparece na faixa de destaque. Use fotos <strong>deitadas (horizontais), de pelo menos
        1200 x 750 pixels</strong>: o app mostra nesse formato e corta um pouco das bordas se a foto for em pé.</p>
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
    // Logo: recorte quadrado no próprio navegador (arrastar e aproximar). Começa mostrando o logo inteiro, com branco em volta;
    // ao enviar, troca o arquivo escolhido por um JPG 600 x 600 só com o que ficou no quadro.
    (function () {
      var inp = document.getElementById('foto'), caixa = document.getElementById('recorte'), tela = document.getElementById('recorte-tela');
      var zoom = document.getElementById('recorte-zoom'), ctx = tela.getContext('2d'), L = tela.width;
      var img = null, base = 1, esc = 1, x = 0, y = 0, arrasto = null, nomeArq = 'logo.jpg';
      function limitar() {   // a imagem nunca sai toda do quadro
        var w = img.width * esc, h = img.height * esc;
        x = Math.min(Math.max(x, Math.min(0, L - w)), Math.max(0, L - w));
        y = Math.min(Math.max(y, Math.min(0, L - h)), Math.max(0, L - h));
      }
      function desenhar(alvo, lado) {
        var c = alvo.getContext('2d'), k = lado / L;
        c.fillStyle = '#fff'; c.fillRect(0, 0, lado, lado);
        c.drawImage(img, x * k, y * k, img.width * esc * k, img.height * esc * k);
      }
      function mostrar() {
        desenhar(tela, L);
        ctx.save(); ctx.fillStyle = 'rgba(0,0,0,0.45)'; ctx.beginPath(); ctx.rect(0, 0, L, L);
        ctx.arc(L / 2, L / 2, L / 2 - 2, 0, Math.PI * 2, true); ctx.fill('evenodd'); ctx.restore();
        ctx.strokeStyle = '#F7C600'; ctx.lineWidth = 4; ctx.beginPath(); ctx.arc(L / 2, L / 2, L / 2 - 2, 0, Math.PI * 2); ctx.stroke();
      }
      function escala(nova) {   // aproxima mantendo o centro do quadro no lugar
        var cx = (L / 2 - x) / esc, cy = (L / 2 - y) / esc;
        esc = nova; x = L / 2 - cx * esc; y = L / 2 - cy * esc; limitar(); mostrar();
      }
      function inteiro() { zoom.value = 0.85; esc = base * 0.85; x = (L - img.width * esc) / 2; y = (L - img.height * esc) / 2; mostrar(); }   // inteiro, com margem dentro do círculo
      inp.addEventListener('change', function () {
        var f = inp.files[0]; caixa.hidden = true; img = null;
        if (!f || !window.createImageBitmap) return;
        nomeArq = f.name.replace(/\.[^.]+$/, '') + '.jpg';
        createImageBitmap(f, { imageOrientation: 'from-image' }).then(function (b) {
          img = b; base = L / Math.max(b.width, b.height);
          zoom.min = 0.5; zoom.max = Math.max(4, (L / Math.min(b.width, b.height)) / base * 1.5);
          caixa.hidden = false; inteiro();
        }).catch(function () {});
      });
      zoom.addEventListener('input', function () { if (img) escala(base * parseFloat(zoom.value)); });
      document.getElementById('recorte-inteiro').addEventListener('click', function () { if (img) inteiro(); });
      document.getElementById('recorte-preencher').addEventListener('click', function () {
        if (!img) return; var z = (L / Math.min(img.width, img.height)) / base; zoom.value = z; escala(base * z);
        x = (L - img.width * esc) / 2; y = (L - img.height * esc) / 2; mostrar();
      });
      function ponto(e) { var r = tela.getBoundingClientRect(); return { x: (e.clientX - r.left) * L / r.width, y: (e.clientY - r.top) * L / r.height }; }
      tela.addEventListener('pointerdown', function (e) { if (!img) return; tela.setPointerCapture(e.pointerId); var p = ponto(e); arrasto = { x: p.x - x, y: p.y - y }; });
      tela.addEventListener('pointermove', function (e) { if (!arrasto) return; var p = ponto(e); x = p.x - arrasto.x; y = p.y - arrasto.y; limitar(); mostrar(); });
      tela.addEventListener('pointerup', function () { arrasto = null; });
      tela.addEventListener('pointercancel', function () { arrasto = null; });
      // ao enviar: troca o arquivo pelo recorte
      inp.form.addEventListener('submit', function (e) {
        if (!img || !window.DataTransfer || inp.dataset.recortado) return;
        e.preventDefault();
        var final = document.createElement('canvas'); final.width = final.height = 600; desenhar(final, 600);
        final.toBlob(function (blob) {
          var dt = new DataTransfer(); dt.items.add(new File([blob], nomeArq, { type: 'image/jpeg' }));
          inp.files = dt.files; inp.dataset.recortado = '1'; inp.form.submit();
        }, 'image/jpeg', 0.9);
      });
    })();

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
