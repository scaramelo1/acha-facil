<?php
// Cidades atendidas (tabela cidades, mudança 016). O app baixa a lista em api/cidades.php, então cidade cadastrada e
// ligada aqui aparece no app sem APK novo. Cidade nova nasce desligada: importa os comércios, confere e liga.
require __DIR__ . '/../../inc/admin.php';
$admin = exigir_admin();
$pronta = (bool)valor("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'cidades'");
if (!$pronta) { admin_inicio('Cidades', $admin); echo '<h1>Cidades</h1><p>Falta aplicar a mudança 016 (bash servidor/instalar.sh).</p>'; admin_fim(); exit; }

/** "Vargem Grande do Sul" → "vargem-grande-do-sul" */
function slug_cidade(string $nome): string {
    $s = mb_strtolower(trim($nome));
    $s = strtr($s, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ü'=>'u','ç'=>'c']);
    return trim(preg_replace('/[^a-z0-9]+/', '-', $s), '-');
}
/** Centro da cidade pelo OpenStreetMap (um pedido só, na hora do cadastro). */
function centro_cidade(string $nome, string $uf): ?array {
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'city' => $nome, 'state' => NOMES_ESTADOS[$uf] ?? $uf, 'country' => 'Brasil', 'format' => 'json', 'limit' => 1]);
    $ctx = stream_context_create(['http' => ['timeout' => 10, 'header' => "User-Agent: AchaFacil/1.0 (cadastro de cidade)\r\nAccept-Language: pt-BR\r\n"]]);
    $j = json_decode((string)@file_get_contents($url, false, $ctx), true);
    return isset($j[0]['lat']) ? [round((float)$j[0]['lat'], 6), round((float)$j[0]['lon'], 6)] : null;
}
$municipios = json_decode((string)file_get_contents(__DIR__ . '/../../dados/municipios.json'), true)['cidades'] ?? [];

if (e_post()) {
    exigir_post();
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'criar') {
        $uf = strtoupper((string)txt('uf', 2));
        $digitado = (string)txt('nome', 80);
        // nome oficial do IBGE (com acento certo), aceitando digitado sem acento
        $nome = null;
        foreach ($municipios[$uf] ?? [] as $m) if (slug_cidade($m) === slug_cidade($digitado)) { $nome = $m; break; }
        $curto = trim((string)txt('curto', 30)) ?: ($nome !== null && mb_strlen($nome) <= 30 ? $nome : null);
        $slug = $nome !== null ? slug_cidade($nome) : '';
        if (!isset(NOMES_ESTADOS[$uf])) flash('Escolha o estado.', 'erro');
        elseif ($nome === null) flash("Não achei \"$digitado\" na lista de cidades do IBGE para $uf. Confira o nome.", 'erro');
        elseif ($curto === null) flash('O nome é comprido: preencha o nome curto (até 30 letras), usado no cabeçalho do app.', 'erro');
        elseif (valor('SELECT 1 FROM cidades WHERE slug = ?', [$slug])) flash("$nome já está cadastrada.", 'erro');
        else {
            $centro = centro_cidade($nome, $uf);
            inserir('cidades', ['slug' => $slug, 'nome' => $nome, 'curto' => $curto, 'uf' => $uf, 'lat' => $centro[0] ?? null, 'lng' => $centro[1] ?? null,
                'ativa' => isset($_POST['ativa']) ? 1 : 0, 'ordem' => (int)valor('SELECT COALESCE(MAX(ordem), 0) + 1 FROM cidades')]);
            registrar((int)$admin['id'], 'criou_cidade', null, "$nome - $uf ($slug)");
            flash("$nome - $uf cadastrada" . (isset($_POST['ativa']) ? ' e já aparece no app.' : ' (desligada).') . " Código: $slug."
                . ($centro ? '' : ' Não achei o centro da cidade: a "Cidade atual" pela localização não vai funcionar para ela até preencher.'));
        }
    } elseif ($acao === 'ativa') {
        $c = linha('SELECT * FROM cidades WHERE slug = ?', [(string)($_POST['slug'] ?? '')]);
        if ($c && $c['ativa'] && (int)valor('SELECT COUNT(*) FROM cidades WHERE ativa = 1') <= 1) flash('O app precisa de pelo menos uma cidade ligada.', 'erro');
        elseif ($c) {
            q('UPDATE cidades SET ativa = 1 - ativa WHERE slug = ?', [$c['slug']]);
            registrar((int)$admin['id'], $c['ativa'] ? 'desligou_cidade' : 'ligou_cidade', null, $c['nome']);
            flash($c['nome'] . ($c['ativa'] ? ' desligada: some do app.' : ' ligada: já aparece no app.'));
        }
    } elseif ($acao === 'editar') {
        $c = linha('SELECT * FROM cidades WHERE slug = ?', [(string)($_POST['slug'] ?? '')]);
        $curto = trim((string)txt('curto', 30));
        $lat = trim((string)($_POST['lat'] ?? '')); $lng = trim((string)($_POST['lng'] ?? ''));
        $num = fn($v, $lim) => $v === '' ? null : (is_numeric(str_replace(',', '.', $v)) && abs((float)str_replace(',', '.', $v)) <= $lim ? (float)str_replace(',', '.', $v) : false);
        $la = $num($lat, 90); $ln = $num($lng, 180);
        if (!$c) flash('Cidade não encontrada.', 'erro');
        elseif ($curto === '') flash('Preencha o nome curto.', 'erro');
        elseif ($la === false || $ln === false) flash('Latitude/longitude inválidas (ex.: -21.9696 e -46.7981).', 'erro');
        else {
            q('UPDATE cidades SET curto = ?, lat = ?, lng = ? WHERE slug = ?', [$curto, $la, $ln, $c['slug']]);
            registrar((int)$admin['id'], 'editou_cidade', null, $c['nome']);
            flash($c['nome'] . ' atualizada.');
        }
    }
    ir('admin/cidades.php');
}

$cidades = linhas("SELECT c.*, (SELECT COUNT(*) FROM profissionais p WHERE p.cidade = c.slug AND p.status = 'ativo') AS comercios,
    (SELECT COUNT(*) FROM profissionais p WHERE p.cidade = c.slug AND p.status = 'ativo' AND p.lat IS NOT NULL) AS localizados
    FROM cidades c ORDER BY c.ordem, c.nome");

admin_inicio('Cidades', $admin);
?>
<h1>Cidades</h1>
<p class="discreto">O app baixa esta lista sempre que abre: cidade ligada aqui aparece no app na hora, <strong>sem APK novo</strong>.
  Cidade nova nasce desligada: primeiro entram os comércios (a lista do robô, importada na VPS com o código da cidade), depois você liga.</p>

<div class="tabela"><table>
  <tr><th>Cidade</th><th>Código</th><th>Comércios</th><th>No app</th><th>Nome curto e centro</th></tr>
  <?php foreach ($cidades as $c): ?>
  <tr<?= $c['ativa'] ? '' : ' class="discreto"' ?>>
    <td><strong><?= h($c['nome']) ?></strong> - <?= h($c['uf']) ?></td>
    <td><code><?= h($c['slug']) ?></code></td>
    <td><?= number_format((int)$c['comercios'], 0, ',', '.') ?><br><small><?= number_format((int)$c['localizados'], 0, ',', '.') ?> com localização</small></td>
    <td><form method="post"<?= $c['ativa'] ? " onsubmit=\"return confirm('Desligar " . h($c['nome']) . "? Ela some do app.')\"" : '' ?>><?= campo_csrf() ?>
      <input type="hidden" name="acao" value="ativa"><input type="hidden" name="slug" value="<?= h($c['slug']) ?>">
      <?= $c['ativa'] ? '✅ Ligada ' : '⏸️ Desligada ' ?><button class="botao contorno"><?= $c['ativa'] ? 'Desligar' : 'Ligar' ?></button></form></td>
    <td><form method="post" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center"><?= campo_csrf() ?>
      <input type="hidden" name="acao" value="editar"><input type="hidden" name="slug" value="<?= h($c['slug']) ?>">
      <input name="curto" value="<?= h($c['curto']) ?>" maxlength="30" style="width:11em" aria-label="Nome curto">
      <input name="lat" value="<?= h($c['lat'] ?? '') ?>" style="width:8em" placeholder="latitude" aria-label="Latitude">
      <input name="lng" value="<?= h($c['lng'] ?? '') ?>" style="width:8em" placeholder="longitude" aria-label="Longitude">
      <button class="botao contorno">Salvar</button></form></td>
  </tr>
  <?php endforeach ?>
</table></div>

<h2>Cadastrar cidade</h2>
<form method="post" class="quadro">
  <?= campo_csrf() ?><input type="hidden" name="acao" value="criar">
  <label for="uf">Estado</label>
  <select id="uf" name="uf" required>
    <option value="">Escolha</option>
    <?php foreach (NOMES_ESTADOS as $uf => $nomeUf): ?><option value="<?= h($uf) ?>"><?= h("$nomeUf ($uf)") ?></option><?php endforeach ?>
  </select>
  <label for="nome">Cidade (nome como no IBGE; acento pode faltar)</label>
  <input id="nome" name="nome" maxlength="80" required placeholder="Casa Branca">
  <label for="curto">Nome curto para o cabeçalho do app (vazio = o nome todo)</label>
  <input id="curto" name="curto" maxlength="30" placeholder="ex.: São João">
  <label class="marcar"><input type="checkbox" name="ativa"> Já mostrar no app (deixe desmarcado até importar os comércios)</label>
  <p class="discreto">O centro da cidade (para a "Cidade atual" pela localização do celular) é procurado sozinho no OpenStreetMap.</p>
  <p><button class="botao">Cadastrar</button></p>
</form>
<?php admin_fim();
