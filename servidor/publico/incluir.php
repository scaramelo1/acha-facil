<?php
// Incluir um negócio que não está no app, de graça: só os dados iniciais (os mesmos que os comércios da lista já têm).
// O comércio entra como 'pendente' (fora do app) e importado = 1 (aparece sem plano, como os da lista); um pedido
// tipo 'incluir' avisa os admins, que conferem e publicam em /admin/pedido.php. Fotos e descrição: pelos planos.
require __DIR__ . '/../inc/base.php';
require __DIR__ . '/../inc/guia.php';
require __DIR__ . '/../inc/pagina.php';
iniciar_sessao();

$areas = linhas("SELECT id, nome FROM categorias WHERE ativa = 1 ORDER BY ordem, nome");
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
    if ($erro_hor) $erro = $erro_hor;
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
        $pedido = inserir('pedidos', ['profissional_id' => $pid, 'tipo' => 'incluir', 'nome' => $nome, 'telefone' => $contato,
            'mensagem' => txt('mensagem', 1000), 'ip' => ip()]);
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
      <label for="tipo">O que é (ex.: padaria, salão de beleza, oficina)</label>
      <input id="tipo" name="tipo" required maxlength="80" value="<?= h($f['tipo'] ?? '') ?>">
      <label for="area">Área</label>
      <select id="area" name="area" required>
        <option value="">Escolha</option>
        <?php foreach ($areas as $a): ?><option value="<?= (int)$a['id'] ?>"<?= $sel('area', $a['id']) ?>><?= h($a['nome']) ?></option><?php endforeach ?>
      </select>
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
      <input id="nome" name="nome" required maxlength="120" value="<?= h($f['nome'] ?? '') ?>">
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
