<?php
// Relatório de 1 folha para mandar ao comerciante (passo 20): quantas pessoas viram a ficha, contatos, posição entre os
// concorrentes, o que falta na ficha e o que o Destaque traz. Abrir, "Imprimir / salvar PDF" ou tirar print e mandar no WhatsApp.
require __DIR__ . '/../../inc/admin.php';
$admin = exigir_admin();
exigir_numeros_prontos();
header('Cache-Control: no-store');

$p = linha('SELECT p.*, c.nome AS categoria, ' . SQL_DESTAQUE . ' AS destaque_pago FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE p.id = ?', [(int)($_GET['id'] ?? 0)]);
if (!$p) { http_response_code(404); exit('Comércio não encontrado.'); }

const MESES = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
if (preg_match('/^(\d{4})-(\d{2})$/', (string)($_GET['mes'] ?? ''), $m) && checkdate((int)$m[2], 1, (int)$m[1])) {
    $desde = "$m[1]-$m[2]-01";
    $ate = min(hoje(), date('Y-m-t', strtotime($desde)));
    $periodo = MESES[(int)$m[2]] . ' de ' . $m[1];
} else {
    $dias = in_array((int)($_GET['dias'] ?? 30), [7, 30, 90], true) ? (int)($_GET['dias'] ?? 30) : 30;
    $desde = desde_dias($dias);
    $ate = hoje();
    $periodo = 'últimos ' . $dias . ' dias (' . data_br($desde) . ' a ' . data_br($ate) . ')';
}
$n = numeros_ficha((int)$p['id'], $desde, $ate);
$pos = posicao_ficha($p, $desde, $ate);
$cidade = nome_cidade($p['cidade']);

$falta = [];
if (!$p['foto']) $falta[] = 'Logo do comércio';
if (!lista_fotos($p['fotos'] ?? null)) $falta[] = 'Fotos do comércio, dos produtos ou do serviço';
if (!$p['horarios'] && !$p['horario']) $falta[] = 'Horário de funcionamento ("Aberto agora" no app)';
if (!$p['whatsapp']) $falta[] = 'Botão do WhatsApp';
if (!$p['descricao']) $falta[] = 'Descrição do que o comércio faz';
if (!$p['servicos']) $falta[] = 'Lista de produtos e serviços';
$base = config()['url'];
$meses = [];
for ($i = 0; $i < 4; $i++) { $t = strtotime(date('Y-m-01') . " -$i month"); $meses[date('Y-m', $t)] = MESES[(int)date('n', $t)]; }
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h($p['nome']) ?> no Acha Fácil · <?= h($periodo) ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@500;700;800;900&display=swap">
<style>
  :root { --verde: #005C38; --amarelo: #F7C600; --texto: #1d2a24; --texto2: #5b6b63; --borda: #e3e8e5; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #eef2f0; font-family: Nunito, system-ui, sans-serif; color: var(--texto); }
  .folha { max-width: 760px; margin: 24px auto; background: #fff; border-radius: 18px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
  .topo { background: var(--verde); color: #fff; padding: 22px 28px; display: flex; align-items: center; gap: 14px; }
  .topo img { width: 48px; height: 48px; }
  .topo .marca { font-size: 1.5rem; font-weight: 900; }
  .topo .marca b { color: var(--amarelo); }
  .topo .lema { font-size: .9rem; opacity: .85; }
  .corpo { padding: 24px 28px 28px; }
  h1 { margin: 0 0 2px; font-size: 1.6rem; }
  h2 { font-size: 1.1rem; margin: 22px 0 8px; color: var(--verde); }
  .sub { color: var(--texto2); margin: 0 0 18px; }
  .grandes { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
  .grandes div { background: #f6f8f7; border-radius: 14px; padding: 14px 10px; text-align: center; }
  .grandes strong { display: block; font-size: 2rem; font-weight: 900; color: var(--verde); line-height: 1.1; }
  .grandes span { font-size: .85rem; color: var(--texto2); }
  .posicao { margin-top: 14px; background: var(--amarelo); border-radius: 14px; padding: 12px 16px; font-weight: 800; }
  ul { margin: 6px 0 0; padding-left: 20px; } li { margin: 3px 0; }
  .duas { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
  .chamada { margin-top: 22px; border: 2px solid var(--verde); border-radius: 14px; padding: 14px 16px; }
  .rodape { color: var(--texto2); font-size: .85rem; margin-top: 18px; }
  .acoes { max-width: 760px; margin: 0 auto; padding: 0 16px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
  .acoes a, .acoes button { font: inherit; font-weight: 800; background: var(--verde); color: #fff; border: 0; border-radius: 10px; padding: 8px 14px; cursor: pointer; text-decoration: none; }
  .acoes a.claro { background: #fff; color: var(--verde); }
  @media (max-width: 600px) { .grandes { grid-template-columns: repeat(2, 1fr); } .duas { grid-template-columns: 1fr; } .folha { margin: 12px; } .corpo { padding: 20px 16px; } }
  @media print { body { background: #fff; } .acoes { display: none; } .folha { box-shadow: none; margin: 0; max-width: none; border-radius: 0; }
    .topo, .grandes div, .posicao { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
</head>
<body>
<div class="acoes" style="padding-top:16px">
  <button onclick="print()">Imprimir / salvar PDF</button>
  <?php foreach ($meses as $k => $nome): ?><a class="claro" href="?id=<?= (int)$p['id'] ?>&amp;mes=<?= $k ?>"><?= ucfirst($nome) ?></a><?php endforeach ?>
  <a class="claro" href="?id=<?= (int)$p['id'] ?>&amp;dias=30">30 dias</a>
</div>
<div class="folha">
  <div class="topo">
    <img src="<?= $base ?>/assets/logo.png" alt="">
    <div><div class="marca">Acha <b>Fácil!</b></div><div class="lema">O comércio da sua cidade, pertinho de você.</div></div>
  </div>
  <div class="corpo">
    <h1><?= h($p['nome']) ?></h1>
    <p class="sub"><?= h($p['tipo'] ?: $p['categoria']) ?> · <?= h($cidade) ?> · <?= h($periodo) ?></p>

    <div class="grandes">
      <div><strong><?= num($n['aparicoes']) ?></strong><span>vezes que apareceu em listas e buscas</span></div>
      <div><strong><?= num($n['visualizacoes']) ?></strong><span>vezes que abriram a ficha</span></div>
      <div><strong><?= num($n['pessoas']) ?></strong><span>pessoas diferentes</span></div>
      <div><strong><?= num($n['contatos']) ?></strong><span>toques para falar ou ir até lá</span></div>
    </div>
    <?php if ($pos && $pos['de'] > 1): ?>
      <div class="posicao"><?= $pos['posicao'] ?>º mais visto entre <?= num($pos['de']) ?> de "<?= h($pos['grupo']) ?>" em <?= h($cidade) ?></div>
    <?php endif ?>

    <div class="duas">
      <div>
        <h2>Como chegaram até você</h2>
        <?php if (!$n['origens'] && !$n['onde']): ?><p class="sub">Ainda sem visitas no período.</p><?php else: ?>
        <ul>
          <?php foreach (array_slice($n['onde'], 0, 4, true) as $o => $t): ?><li>Apareceu <?= num($t) ?>× em <?= h(mb_strtolower(ONDE_APARICAO[$o] ?? $o)) ?></li><?php endforeach ?>
          <?php foreach ($n['por_tipo'] as $k => $t): if (!$t) continue; ?><li><?= num($t) ?> toque<?= $t > 1 ? 's' : '' ?> em <?= TIPOS_CONTATO[$k] ?></li><?php endforeach ?>
        </ul>
        <?php endif ?>
      </div>
      <div>
        <h2>O que falta na sua ficha</h2>
        <?php if (!$falta): ?><p class="sub">Sua ficha está completa. 👏</p><?php else: ?>
        <ul><?php foreach ($falta as $f): ?><li><?= h($f) ?></li><?php endforeach ?></ul>
        <?php endif ?>
      </div>
    </div>

    <?php if (!$p['destaque_pago']): ?>
    <div class="chamada">
      <strong>Com o plano Destaque (<?= h(preco_plano_texto('destaque')) ?>):</strong>
      <ul>
        <li>Seu comércio no topo da tela inicial e da sua categoria</li>
        <li>Selo "Verificado pelo Acha Fácil"</li>
        <li>Logo, fotos, horário e dados corrigidos por você</li>
        <li>Responder às avaliações e acompanhar estes números todo mês</li>
      </ul>
    </div>
    <?php endif ?>

    <p class="rodape">Veja os planos em <strong><?= h(preg_replace('#^https?://#', '', url_publica('planos.php'))) ?></strong>
      · App Acha Fácil para Android · FSA Designer, <?= h(config()['contato_email'] ?? 'fsadesignerbr@gmail.com') ?></p>
  </div>
</div>
</body>
</html>
