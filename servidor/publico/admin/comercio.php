<?php
// Editar um comércio direto (só admins). Diferente do painel do dono, aqui não passa por conferência: o que o admin
// salva vai para o app e o site na hora. Chega pelo ícone de lápis da ficha (site e app) ou por /admin/comercios.php.
// Tudo fica no registro (acao 'editou_comercio'); comércio editado pelo admin não é mais mudado pela importação de
// listas nem pela limpeza automática (editado_pelo_admin() em inc/moderacao.php).
require __DIR__ . '/../../inc/admin.php';
$admin = exigir_admin();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$p = linha('SELECT * FROM profissionais WHERE id = ?', [$id]);
if (!$p) { http_response_code(404); admin_inicio('Comércio', $admin); echo '<h1>Comércio não encontrado</h1><p><a href="comercios.php">Procurar outro</a></p>'; admin_fim(); exit; }

const STATUS_NOME = ['ativo' => 'No app (ativo)', 'pausado' => 'Fora do app (pausado)', 'pendente' => 'Esperando aprovação', 'recusado' => 'Recusado'];
const ASSINATURA_NOME = ['sem' => 'Sem assinatura', 'ativa' => 'Ativa (paga)', 'atrasada' => 'Atrasada', 'cancelada' => 'Cancelada (vale até a data)', 'cortesia' => 'Cortesia (grátis, sem data)'];
$categorias = linhas('SELECT id, nome, ativa FROM categorias WHERE ativa = 1 OR id = ? ORDER BY ordem, nome', [$p['categoria_id']]);
require_once __DIR__ . '/../../inc/tipos.php';   // passo 96: tipo na caixa da área, como no "Incluir meu negócio"
$tipos_area = tipos_por_area();
$erro = null;
$valores = [];

if (e_post()) {
    exigir_post();
    [$valores, $erro] = limpar_campos_dono($_POST);   // nome, descrição, telefones, Instagram, site, endereço... (mesmas regras do painel)
    if (!$erro) [$valores['horarios'], $erro] = horarios_do_post((array)($_POST['hor'] ?? []));
    // o que só o admin muda
    $valores['responsavel'] = txt('responsavel', 120);
    $cat = (int)($_POST['categoria_id'] ?? 0);
    // tipo: da caixa da área; o tipo atual do comércio continua valendo mesmo fora da lista; novo só com "Não encontrei"
    [$valores['tipo'], , $erro_tipo] = tipo_do_post($tipos_area, $cat, (string)$p['tipo']);
    if (!$erro && $erro_tipo) $erro = $erro_tipo;
    if (!$erro && !in_array($cat, array_map('intval', array_column($categorias, 'id')), true)) $erro = 'Escolha a área.';
    $valores['categoria_id'] = (string)$cat;
    $cid = (string)($_POST['cidade'] ?? '');
    if (!$erro && !isset(TODAS_CIDADES[$cid])) $erro = 'Escolha a cidade.';
    $valores['cidade'] = $cid;
    $st = (string)($_POST['status'] ?? '');
    $valores['status'] = isset(STATUS_NOME[$st]) ? $st : $p['status'];
    $valores['verificado'] = empty($_POST['verificado']) ? '0' : '1';
    $valores['mapa'] = txt('mapa', 500);
    if (!$erro && $valores['mapa'] !== null && !preg_match('#^https://#i', $valores['mapa'])) $erro = 'Link do mapa: precisa começar com https://';
    // ponto no mapa: "-21.97, -46.79" ou os dois campos
    $lat = trim(str_replace(',', '.', (string)($_POST['lat'] ?? '')));
    $lng = trim(str_replace(',', '.', (string)($_POST['lng'] ?? '')));
    if ($lat === '' && $lng === '') { $valores['lat'] = $valores['lng'] = null; }
    elseif (!$erro && (!is_numeric($lat) || !is_numeric($lng) || $lat < -34 || $lat > 6 || $lng < -74 || $lng > -34)) $erro = 'Ponto no mapa: latitude e longitude do Brasil (ex.: -21.9691 e -46.7983).';
    else { $valores['lat'] = number_format((float)$lat, 6, '.', ''); $valores['lng'] = number_format((float)$lng, 6, '.', ''); }
    // plano e pagamento
    $pl = (string)($_POST['plano'] ?? '');
    $valores['plano'] = isset(PLANOS_NOME[$pl]) ? $pl : $p['plano'];
    $as = (string)($_POST['assinatura'] ?? '');
    $valores['assinatura'] = isset(ASSINATURA_NOME[$as]) ? $as : $p['assinatura'];
    $ate = (string)($_POST['assinatura_ate'] ?? '');
    $valores['assinatura_ate'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate) ? $ate : null;
    $valores['basico_pago_em'] = empty($_POST['basico_pago']) ? null : ($p['basico_pago_em'] ?: date('Y-m-d H:i:s'));

    // logo e fotos
    $foto = null; $enviadas = [];
    if (!$erro && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try { $foto = salvar_foto_enviada($_FILES['foto'], $id, 600, true); } catch (RuntimeException $e) { $erro = 'Logo: ' . $e->getMessage(); }
    }
    $tirar = array_map('strval', (array)($_POST['tirar'] ?? []));
    $galeria = array_values(array_filter(lista_fotos($p['fotos']), fn($f) => !in_array($f, $tirar, true)));
    $novas = $_FILES['fotos_novas'] ?? null;
    if (!$erro && $novas && is_array($novas['error'])) {
        foreach ($novas['error'] as $i => $e) {
            if ($e === UPLOAD_ERR_NO_FILE) continue;
            if (count($galeria) + count($enviadas) >= MAX_FOTOS) { $erro = 'São no máximo ' . MAX_FOTOS . ' fotos. Tire alguma antes de colocar outras.'; break; }
            $um = ['error' => $e, 'size' => $novas['size'][$i], 'tmp_name' => $novas['tmp_name'][$i]];
            try { $enviadas[] = salvar_foto_enviada($um, $id, 1600); } catch (RuntimeException $ex) { $erro = 'Foto ' . ($i + 1) . ': ' . $ex->getMessage(); break; }
        }
    }
    if ($erro) { apagar_foto($foto); array_map('apagar_foto', $enviadas); }
    else {
        $valores['foto'] = $foto ?? (!empty($_POST['tirar_logo']) ? null : $p['foto']);
        $valores['fotos'] = fotos_json(array_merge($galeria, $enviadas));
        // só o que mudou
        $dados = [];
        foreach ($valores as $c => $v) {
            $agora = $p[$c] === null ? null : (string)$p[$c];
            if ($v !== $agora) $dados[$c] = $v;
        }
        if (array_key_exists('horarios', $dados)) $dados['horario'] = $dados['horarios'] ? horarios_texto(horarios_ler($dados['horarios'])) : null;
        if (isset($dados['lat']) || isset($dados['lng'])) $dados['geo_origem'] = 'manual';   // ponto acertado à mão: ninguém muda depois
        elseif (array_key_exists('lat', $dados)) $dados += ['geo_origem' => null, 'geo_tentado' => null];   // apagou o ponto: geocodificar acha de novo
        elseif (array_key_exists('endereco', $dados) || array_key_exists('bairro', $dados) || array_key_exists('cidade', $dados)) {
            // endereço novo sem ponto novo: a localização antiga não vale mais (menos ponto acertado à mão)
            if ($p['geo_origem'] !== 'manual') $dados += ['lat' => null, 'lng' => null, 'geo_origem' => null, 'geo_tentado' => null];
        }
        if (!$dados) { flash('Nada mudou.'); ir('admin/comercio.php?id=' . $id); }
        foreach (['atende_em_casa', 'verificado'] as $c) if (array_key_exists($c, $dados)) $dados[$c] = (int)$dados[$c];
        try {
            atualizar('profissionais', $id, $dados);
        } catch (Throwable $e) {
            apagar_foto($foto); array_map('apagar_foto', $enviadas);
            error_log('acha-facil admin editar comercio ' . $id . ': ' . $e->getMessage());
            flash('Não foi possível salvar. Confira os dados.', 'erro');
            ir('admin/comercio.php?id=' . $id);
        }
        $agora = linha('SELECT foto, fotos FROM profissionais WHERE id = ?', [$id]);
        $pend = alteracao_pendente($id);   // arquivos de um pedido do dono ainda pendente ficam
        apagar_fotos_sobrando(arquivos_dos_campos($p), array_merge(arquivos_dos_campos($agora), arquivos_dos_campos($pend['novos'] ?? [])));
        registrar((int)$admin['id'], 'editou_comercio', $id, implode(', ', array_keys($dados)));
        web_cache_limpar();
        // 19/11: quem pediu "Quer saber quando colocarem...?" é avisado também quando o admin completa a ficha
        $avisados = 0;
        try { require_once __DIR__ . '/../../inc/avisos-info.php'; $avisados = avisar_infos_completas([$id]); }
        catch (Throwable $e) { error_log('acha-facil avisos-info admin: ' . $e->getMessage()); }
        flash('Salvo. Já está no app e no site.' . ($avisados ? " $avisados pessoa(s) que pediram a informação foram avisadas." : ''));
        ir('admin/comercio.php?id=' . $id);
    }
}

$f = $erro ? array_merge($p, $valores) : $p;
$val = fn(string $c) => h((string)($f[$c] ?? ''));
$galeria_form = lista_fotos($p['fotos']);
$pend = alteracao_pendente($id);
$em_dia = (int)valor('SELECT COUNT(*) FROM profissionais p WHERE p.id = ? AND ' . SQL_EM_DIA, [$id]) > 0;
$destaque = (int)valor('SELECT COUNT(*) FROM profissionais p WHERE p.id = ? AND ' . SQL_DESTAQUE, [$id]) > 0;
$historico = linhas("SELECT r.criado_em, r.acao, r.detalhe, a.nome FROM registro_admin r LEFT JOIN admins a ON a.id = r.admin_id
    WHERE r.profissional_id = ? ORDER BY r.id DESC LIMIT 10", [$id]);

admin_inicio('Editar ' . $p['nome'], $admin);
?>
<p><a href="comercios.php">&larr; Comércios</a> · <a href="<?= h(url_ficha($p)) ?>" target="_blank" rel="noopener">ver a ficha no site</a>
  · <a href="numeros-ficha.php?id=<?= $id ?>">números</a></p>
<h1>✏️ <?= adm_comercio($id, $p['nome'], $p['cidade']) ?></h1>
<p class="discreto">Nº <?= $id ?> · <?= adm_cidade($p['cidade']) ?> · <?= h(STATUS_NOME[$p['status']] ?? $p['status']) ?>
  · <?= (int)$p['importado'] ? 'veio da lista do Google' : 'cadastrado pelo dono' ?>
  · <?= $destaque ? 'Destaque em dia' : ($em_dia ? 'plano em dia' : 'sem plano pago') ?>
  · atualizado em <?= h(datahora_br($p['atualizado_em'])) ?></p>
<?php mostrar_flash(); ?>
<div class="quadro">Aqui o que você salva vai <strong>direto</strong> para o app e o site, sem conferência. Depois de editado
  por um admin, o comércio não é mais mudado pela importação das listas do robô nem pela limpeza automática.</div>
<?php if ($pend): ?>
  <div class="aviso erro">O dono pediu uma alteração que ainda espera conferência (<?= h(implode(', ', array_map(fn($c) => CAMPOS_DONO[$c][0] ?? $c, array_keys($pend['novos'])))) ?>).
    <a href="alteracao.php?id=<?= (int)$pend['id'] ?>">Conferir o pedido</a> — se aprovar depois de editar aqui, o pedido do dono vale por cima.</div>
<?php endif ?>
<?php if ($erro): ?><div class="aviso erro"><?= h($erro) ?></div><?php endif ?>

<form method="post" enctype="multipart/form-data" class="form-admin-comercio">
  <?= campo_csrf() ?><input type="hidden" name="id" value="<?= $id ?>">

  <h2>Situação</h2>
  <label for="status">No app?</label>
  <select id="status" name="status">
    <?php foreach (STATUS_NOME as $k => $n): ?><option value="<?= $k ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= h($n) ?></option><?php endforeach ?>
  </select>
  <label class="marcar"><input type="checkbox" name="verificado" value="1"<?= (string)$f['verificado'] === '1' ? ' checked' : '' ?>> Selo "Verificado pelo Acha Fácil"</label>

  <h2>Dados</h2>
  <label for="nome">Nome do comércio</label>
  <input id="nome" name="nome" required maxlength="120" value="<?= $val('nome') ?>">
  <label for="cidade">Cidade</label>
  <select id="cidade" name="cidade">
    <?php foreach (TODAS_CIDADES as $slug => $c): ?><option value="<?= h($slug) ?>"<?= $f['cidade'] === $slug ? ' selected' : '' ?>><?= h($c[0]) ?><?= isset(CIDADES[$slug]) ? '' : ' (desligada)' ?></option><?php endforeach ?>
  </select>
  <label for="categoria_id">Área</label>
  <select id="categoria_id" name="categoria_id">
    <?php foreach ($categorias as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (int)$f['categoria_id'] === (int)$c['id'] ? ' selected' : '' ?>><?= h($c['nome']) ?><?= $c['ativa'] ? '' : ' (inativa)' ?></option><?php endforeach ?>
  </select>
  <?php $tipo_form_novo = $erro && !empty($_POST['tipo_novo']);
  echo tipo_campos_html($tipos_area, 'categoria_id', $tipo_form_novo ? '' : (string)($f['tipo'] ?? ''), $tipo_form_novo, (string)($_POST['tipo_outro'] ?? ''),
      [(int)$p['categoria_id'] => (string)$p['tipo']], 'Tipo novo (aparece para todos depois que 3 comércios usarem)'); ?>
  <label for="responsavel">Responsável</label>
  <input id="responsavel" name="responsavel" maxlength="120" value="<?= $val('responsavel') ?>">
  <label for="descricao">Sobre o comércio</label>
  <textarea id="descricao" name="descricao" rows="4" maxlength="2000"><?= $val('descricao') ?></textarea>
  <label for="servicos">Produtos e serviços (separe por vírgula)</label>
  <input id="servicos" name="servicos" maxlength="500" value="<?= $val('servicos') ?>">
  <label for="whatsapp">WhatsApp (com DDD)</label>
  <input id="whatsapp" name="whatsapp" inputmode="tel" maxlength="20" value="<?= h(telefone_formatado($f['whatsapp'] ?? null) ?: '') ?>">
  <label for="telefone">Telefone (com DDD)</label>
  <input id="telefone" name="telefone" inputmode="tel" maxlength="20" value="<?= h(telefone_formatado($f['telefone'] ?? null) ?: '') ?>">
  <label for="instagram">Instagram</label>
  <input id="instagram" name="instagram" maxlength="60" placeholder="@comercio" value="<?= $val('instagram') ?>">
  <label for="site">Site</label>
  <input id="site" name="site" maxlength="200" inputmode="url" value="<?= $val('site') ?>">
  <label for="endereco">Endereço</label>
  <input id="endereco" name="endereco" maxlength="200" value="<?= $val('endereco') ?>">
  <label for="bairro">Bairro</label>
  <input id="bairro" name="bairro" maxlength="80" value="<?= $val('bairro') ?>">
  <?= horarios_form(horarios_ler($f['horarios'] ?? null)) ?>
  <?php if (empty($f['horarios']) && !empty($f['horario'])): ?><p class="dica">Hoje no app: <?= h($f['horario']) ?></p><?php endif ?>
  <label class="marcar"><input type="checkbox" name="atende_em_casa" value="1"<?= (string)($f['atende_em_casa'] ?? '0') === '1' ? ' checked' : '' ?>> Atende em casa / faz entrega</label>

  <h2>Mapa</h2>
  <p class="dica">Ponto atual: <?= $p['lat'] !== null ? h($p['lat'] . ', ' . $p['lng']) . ' (' . h($p['geo_origem'] ?? '?') . ')' : 'sem ponto' ?>.
    Para acertar, copie as coordenadas no Google Maps (toque e segure no lugar) e cole abaixo. Se mudar só o endereço,
    o ponto é procurado de novo sozinho.</p>
  <div class="duas-colunas">
    <div><label for="lat">Latitude</label><input id="lat" name="lat" inputmode="decimal" maxlength="20" value="<?= $val('lat') ?>"></div>
    <div><label for="lng">Longitude</label><input id="lng" name="lng" inputmode="decimal" maxlength="20" value="<?= $val('lng') ?>"></div>
  </div>
  <label for="mapa">Link do "Como chegar" (Google Maps)</label>
  <input id="mapa" name="mapa" maxlength="500" inputmode="url" value="<?= $val('mapa') ?>">

  <h2>Plano e pagamento</h2>
  <p class="dica">Mude só em casos combinados (cortesia, acerto de pagamento). O Mercado Pago continua atualizando a assinatura sozinho.</p>
  <label class="marcar"><input type="checkbox" name="basico_pago" value="1"<?= !empty($f['basico_pago_em']) ? ' checked' : '' ?>> Básico pago<?= $p['basico_pago_em'] ? ' (em ' . h(datahora_br($p['basico_pago_em'])) . ')' : '' ?></label>
  <label for="plano">Plano</label>
  <select id="plano" name="plano">
    <?php foreach (PLANOS_NOME as $k => $n): ?><option value="<?= $k ?>"<?= $f['plano'] === $k ? ' selected' : '' ?>><?= h($n) ?></option><?php endforeach ?>
  </select>
  <label for="assinatura">Assinatura do Destaque</label>
  <select id="assinatura" name="assinatura">
    <?php foreach (ASSINATURA_NOME as $k => $n): ?><option value="<?= $k ?>"<?= $f['assinatura'] === $k ? ' selected' : '' ?>><?= h($n) ?></option><?php endforeach ?>
  </select>
  <label for="assinatura_ate">Paga até</label>
  <input id="assinatura_ate" name="assinatura_ate" type="date" value="<?= $val('assinatura_ate') ?>">

  <h2>Logo</h2>
  <?php if (!empty($p['foto'])): ?>
    <p><img class="mini-logo" src="<?= h(foto_url($p['foto'])) ?>" alt="Logo atual"></p>
    <label class="marcar"><input type="checkbox" name="tirar_logo" value="1"> Tirar o logo</label>
  <?php endif ?>
  <label for="foto"><?= empty($p['foto']) ? 'Escolher o logo' : 'Trocar o logo' ?> (JPG, PNG ou WEBP; fica quadrado)</label>
  <input id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp" data-lado="1200" data-max="1">

  <h2>Fotos do comércio</h2>
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
    <label for="fotos_novas">Colocar fotos (até <?= MAX_FOTOS - count($galeria_form) ?>; deitadas ficam melhor)</label>
    <input id="fotos_novas" name="fotos_novas[]" type="file" multiple accept="image/jpeg,image/png,image/webp" data-lado="1600" data-max="<?= MAX_FOTOS - count($galeria_form) ?>">
  <?php else: ?>
    <p class="dica">Galeria cheia (<?= MAX_FOTOS ?>). Marque "Tirar esta foto" e salve antes de colocar outras.</p>
  <?php endif ?>

  <p class="salvar-fixo"><button class="botao">Salvar e publicar</button></p>
</form>

<?php if ($historico): ?>
<h2>Últimas ações da equipe neste comércio</h2>
<div class="tabela"><table>
  <tr><th>Quando</th><th>Quem</th><th>O quê</th></tr>
  <?php foreach ($historico as $r): ?>
  <tr><td><?= h(datahora_br($r['criado_em'])) ?></td><td><?= h($r['nome'] ?? '?') ?></td><td><?= h(str_replace('_', ' ', $r['acao'])) ?><?= $r['detalhe'] ? ': ' . h($r['detalhe']) : '' ?></td></tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>

<script>
// Reduz as fotos no próprio navegador antes de enviar (foto de celular passa fácil do limite do envio)
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
<?php admin_fim();
