<?php
// Incluir um negócio que não está no app, de graça: só os dados iniciais (os mesmos que os comércios da lista já têm).
// O comércio entra como 'pendente' (fora do app) e importado = 1 (aparece sem plano, como os da lista); um pedido
// tipo 'incluir' avisa os admins, que conferem e publicam em /admin/pedido.php. Fotos e descrição: pelos planos.
require __DIR__ . '/../inc/base.php';
require_once __DIR__ . '/../inc/guia.php';
require __DIR__ . '/../inc/pagina.php';
iniciar_sessao();
// incluir negócio: só com a conta Google (para receber o aviso de "aceito" no celular)
require_once __DIR__ . '/../inc/conta-web.php';
$conta = exigir_login_web('Incluir meu negócio', 'Inclua o seu negócio no Acha Fácil de graça.');

$areas = linhas("SELECT id, nome FROM categorias WHERE ativa = 1 ORDER BY ordem, nome");
// 17/11 (passo 94, pedido do usuário): o tipo sai da lista da área (tipos já usados por 3+ comércios), para não criar
// tipos repetidos. Fora da lista só marcando "Não achei o tipo" (a equipe confere ao publicar).
$tipos_area = [];
foreach (linhas("SELECT categoria_id, tipo FROM profissionais WHERE status = 'ativo' AND tipo IS NOT NULL AND tipo <> ''
    GROUP BY categoria_id, tipo HAVING COUNT(*) >= 3 ORDER BY tipo") as $t) $tipos_area[(int)$t['categoria_id']][] = $t['tipo'];
/** Tipo da lista da área igual ao digitado (sem ligar para acento, maiúscula e espaços), ou null. */
$tipo_da_lista = function (int $area, string $tipo) use ($tipos_area): ?string {
    $k = sem_acento(trim(preg_replace('/\s+/u', ' ', $tipo)));
    foreach ($tipos_area[$area] ?? [] as $t) if (sem_acento($t) === $k) return $t;
    return null;
};
$pronto = valor("SELECT 1 FROM migracoes WHERE nome = '012-pedido-incluir.sql'");
$erro = null;
$enviado = false;
$f = $_POST;

if (e_post() && $pronto) {
    exigir_post();
    $d = [
        'nome' => txt('comercio', 120), 'tipo' => txt('tipo', 80), 'categoria_id' => (int)($_POST['area'] ?? 0),
        'cidade' => (string)($_POST['cidade'] ?? ''), 'endereco' => txt('endereco', 200), 'bairro' => txt('bairro', 80),
        'telefone' => telefone_normalizado(txt('telefone_comercio', 20)), 'whatsapp' => telefone_normalizado(txt('whatsapp', 20)),
        'instagram' => ltrim((string)txt('instagram', 60), '@') ?: null,
    ];
    [$d['horarios'], $erro_hor] = horarios_do_post((array)($_POST['hor'] ?? []));
    $d['horario'] = horarios_texto(horarios_ler($d['horarios']));
    $nome = txt('nome', 120);
    $contato = telefone_normalizado(txt('contato', 20));
    // tipo: o da lista da área (grafia oficial); fora da lista só com "Não achei o tipo"
    $tipo_novo = false;
    if (!empty($_POST['tipo_novo'])) {   // passo 95: "Não encontrei o tipo na lista" → o que ela digitou no campo que aparece
        $d['tipo'] = txt('tipo_outro', 80);
        if (!$d['tipo']) $erro_tipo = 'Escreva qual é o tipo do seu negócio no campo que apareceu.';
        elseif ($d['categoria_id'] && ($t = $tipo_da_lista($d['categoria_id'], $d['tipo']))) $d['tipo'] = $t;   // já existia
        else { $tipo_novo = true; $d['tipo'] = mb_strtoupper(mb_substr($d['tipo'], 0, 1)) . mb_substr($d['tipo'], 1); }
    } elseif ($d['tipo'] && $d['categoria_id']) {
        if ($t = $tipo_da_lista($d['categoria_id'], $d['tipo'])) $d['tipo'] = $t;
        else $erro_tipo = 'Escolha o tipo na caixa. Se o seu não estiver lá, marque "Não encontrei o tipo na lista".';
    }
    if ($erro_hor) $erro = $erro_hor;
    elseif (!empty($erro_tipo)) $erro = $erro_tipo;
    elseif (!$d['nome'] || !$d['tipo'] || !$d['endereco']) $erro = 'Preencha o nome do negócio, o que ele é e o endereço.';
    elseif (!in_array($d['categoria_id'], array_map('intval', array_column($areas, 'id')), true)) $erro = 'Escolha a área do negócio.';
    elseif (!isset(CIDADES[$d['cidade']])) $erro = 'Escolha a cidade.';
    elseif (!$d['telefone'] && !$d['whatsapp']) $erro = 'Coloque o telefone ou o WhatsApp do negócio (com DDD).';
    elseif (!$nome || !$contato) $erro = 'Preencha o seu nome e um telefone com DDD para a equipe confirmar.';
    elseif (!empty($_POST['site_web'])) $enviado = true;   // campo escondido preenchido = robô: finge que deu certo
    elseif ((int)valor('SELECT COUNT(*) FROM pedidos WHERE ip = ? AND criado_em > NOW() - INTERVAL 1 DAY', [ip()]) >= 10) $erro = 'Muitos pedidos hoje. Tente amanhã.';
    elseif (valor("SELECT 1 FROM profissionais WHERE cidade = ? AND LOWER(nome) = LOWER(?) AND status IN ('ativo','pendente')", [$d['cidade'], $d['nome']])) {
        $erro = 'Já existe um negócio com esse nome nessa cidade (no app ou esperando conferência). Procure por ele na busca.';
    } else {
        db()->beginTransaction();
        $pid = inserir('profissionais', $d + ['status' => 'pendente', 'importado' => 1]);
        require_once __DIR__ . '/../inc/avisos.php';
        $uid = pedido_usuario_atual();
        $pedido = inserir('pedidos', ['profissional_id' => $pid, 'tipo' => 'incluir', 'nome' => $nome, 'telefone' => $contato,
            'mensagem' => trim(($tipo_novo ? 'TIPO NOVO (fora da lista): ' . $d['tipo'] . "\n" : '') . (txt('mensagem', 900) ?? '')) ?: null, 'ip' => ip()] + ($uid ? ['usuario_id' => $uid] : []));
        db()->commit();
        require_once __DIR__ . '/../inc/avisos.php';
        avisar_admins('Negócio novo para incluir', "$nome quer incluir {$d['nome']} (" . nome_cidade($d['cidade'], true) . ').', ['pedido' => $pedido]);
        $enviado = true;
    }
}

$sel = fn(string $campo, $valor) => (string)($f[$campo] ?? '') === (string)$valor ? ' selected' : '';
pagina_inicio('Incluir meu negócio · Acha Fácil!', 'Inclua o seu negócio no Acha Fácil de graça.');
?>
<main class="documento">
  <h1>Incluir meu negócio</h1>
  <?php if ($enviado): ?>
    <div class="aviso ok">Recebemos! A equipe do Acha Fácil vai conferir os dados (normalmente em até 2 dias úteis) e o seu negócio
      aparece no app assim que for aprovado.</div>
    <p>Quer colocar fotos, descrição e o que você vende? Depois que aparecer no app, <a href="<?= h(config()['url']) ?>/busca.php">procure o seu negócio</a> e confira os planos.</p>
  <?php elseif (!$pronto): ?>
    <div class="aviso erro">Esta página ainda está sendo preparada. Tente de novo mais tarde.</div>
  <?php else: ?>
    <p>Incluir é <strong>de graça</strong>. Coloque os dados básicos do seu negócio; a equipe confere antes de aparecer no app.
      Fotos, descrição e a lista do que você vende ou faz ficam nos <a href="<?= h(config()['url']) ?>/planos.php">planos</a>.</p>
    <?php if ($erro): ?><div class="aviso erro"><?= h($erro) ?></div><?php endif ?>
    <form method="post">
      <?= campo_csrf() ?>
      <h2>O negócio</h2>
      <label for="comercio">Nome do negócio</label>
      <input id="comercio" name="comercio" required maxlength="120" value="<?= h($f['comercio'] ?? '') ?>">
      <label for="area">Área</label>
      <select id="area" name="area" required>
        <option value="">Escolha</option>
        <?php foreach ($areas as $a): ?><option value="<?= (int)$a['id'] ?>"<?= $sel('area', $a['id']) ?>><?= h($a['nome']) ?></option><?php endforeach ?>
      </select>
      <label for="tipo">Tipo do negócio</label>
      <select id="tipo" name="tipo" required data-valor="<?= h(empty($f['tipo_novo']) ? ($f['tipo'] ?? '') : '') ?>">
        <option value="">Escolha a área primeiro</option>
      </select>
      <label class="marcar"><input type="checkbox" id="tipo_novo" name="tipo_novo" value="1"<?= !empty($f['tipo_novo']) ? ' checked' : '' ?>> Não encontrei o tipo na lista</label>
      <div id="tipo-outro-caixa"<?= empty($f['tipo_novo']) ? ' hidden' : '' ?>>
        <label for="tipo_outro">Qual é o tipo do seu negócio? (a equipe confere)</label>
        <input id="tipo_outro" name="tipo_outro" maxlength="80" value="<?= h($f['tipo_outro'] ?? '') ?>">
      </div>
      <script>
      (function () {   // passos 94/95: a caixa de tipos se monta sozinha com os tipos da área escolhida
        var tipos = <?= json_encode($tipos_area, JSON_UNESCAPED_UNICODE) ?>, area = document.getElementById('area'),
            sel = document.getElementById('tipo'), novo = document.getElementById('tipo_novo'),
            caixa = document.getElementById('tipo-outro-caixa'), outro = document.getElementById('tipo_outro');
        function montar(manter) {
          var t = tipos[area.value] || [], antes = manter ? sel.dataset.valor : '';
          sel.innerHTML = '';
          var o = document.createElement('option'); o.value = '';
          o.textContent = area.value ? (t.length ? 'Escolha o tipo' : 'Nenhum tipo nesta área: marque abaixo') : 'Escolha a área primeiro';
          sel.appendChild(o);
          t.forEach(function (n) { var op = document.createElement('option'); op.value = n; op.textContent = n; if (n === antes) op.selected = true; sel.appendChild(op); });
          estado();
        }
        function estado() {
          var n = novo.checked;
          caixa.hidden = !n; outro.required = n;
          sel.disabled = n || !area.value; sel.required = !n;
          if (n) sel.value = '';
        }
        area.addEventListener('change', function () { montar(false); });
        novo.addEventListener('change', function () { estado(); if (novo.checked) outro.focus(); });
        montar(true);
      })();
      </script>
      <label for="cidade">Cidade</label>
      <select id="cidade" name="cidade" required>
        <option value="">Escolha</option>
        <?php foreach (CIDADES as $slug => [$nomeCidade, , $uf]): ?><option value="<?= h($slug) ?>"<?= $sel('cidade', $slug) ?>><?= h("$nomeCidade ($uf)") ?></option><?php endforeach ?>
      </select>
      <label for="endereco">Endereço (rua e número)</label>
      <input id="endereco" name="endereco" required maxlength="200" value="<?= h($f['endereco'] ?? '') ?>">
      <label for="bairro">Bairro</label>
      <input id="bairro" name="bairro" maxlength="80" value="<?= h($f['bairro'] ?? '') ?>">
      <label for="telefone_comercio">Telefone do negócio (com DDD)</label>
      <input id="telefone_comercio" name="telefone_comercio" inputmode="tel" maxlength="20" value="<?= h($f['telefone_comercio'] ?? '') ?>">
      <label for="whatsapp">WhatsApp do negócio (com DDD)</label>
      <input id="whatsapp" name="whatsapp" inputmode="tel" maxlength="20" value="<?= h($f['whatsapp'] ?? '') ?>">
      <?= horarios_form(horarios_ler(horarios_do_post((array)($f['hor'] ?? []))[0])) ?>
      <label for="instagram">Instagram (opcional)</label>
      <input id="instagram" name="instagram" maxlength="60" placeholder="@seunegocio" value="<?= h($f['instagram'] ?? '') ?>">
      <div hidden><label for="site_web">Não preencha</label><input id="site_web" name="site_web" tabindex="-1" autocomplete="off"></div>

      <h2>Você</h2>
      <label for="nome">Seu nome</label>
      <input id="nome" name="nome" required maxlength="120" value="<?= h($f['nome'] ?? ($conta['nome'] ?? '')) ?>">
      <label for="contato">Seu telefone ou WhatsApp (com DDD), para a equipe confirmar</label>
      <input id="contato" name="contato" required inputmode="tel" maxlength="20" value="<?= h($f['contato'] ?? '') ?>">
      <label for="mensagem">Recado para a equipe (opcional)</label>
      <textarea id="mensagem" name="mensagem" maxlength="1000" rows="3"><?= h($f['mensagem'] ?? '') ?></textarea>
      <p><button class="botao amarelo">Enviar para conferência</button></p>
      <p class="discreto">Ao enviar, você confirma que é responsável pelo negócio e concorda com os <a href="<?= h(config()['url']) ?>/termos.php">termos</a>.</p>
    </form>
  <?php endif ?>
</main>
<?php pagina_fim();
