<?php
// Planos para o dono do comércio: corrigir os dados, colocar fotos e aparecer em destaque.
// Aberto pelo app ("É o dono deste lugar?") com ?id= do comércio, ou pelo site sem id.
require __DIR__ . '/../inc/base.php';
require_once __DIR__ . '/../inc/guia.php';
require __DIR__ . '/../inc/pagina.php';
require_once __DIR__ . '/../inc/ofertas.php';   // 19/11: preço da Oferta da semana

$id = (int)($_GET['id'] ?? 0);
$p = $id ? linha('SELECT id, nome, tipo, endereco FROM profissionais WHERE id = ? AND status = \'ativo\'', [$id]) : null;
$base = config()['url'];
// sem comércio escolhido, primeiro acha (ou inclui de graça) o negócio na busca; o plano é sempre de um comércio do app
$pedir = fn(string $plano) => $p ? $base . '/perfil.php?acao=assumir' . ($plano === 'gratis' ? '' : '&plano=' . $plano) . '&id=' . $p['id'] : $base . '/busca.php';

// 17/11 (passo 78): plano Grátis na frente. Grátis = o que já vem do Google; Básico = o que só o dono tem.
$planos = [
    'gratis' => ['Grátis', 'Assuma a sua ficha com a conta Google', [
        'Corrigir nome, telefone, WhatsApp e endereço',
        'Informar o horário de funcionamento e o site',
        'Selo "Dados confirmados pelo dono"',
    ]],
    'basico' => ['Básico', 'Sua ficha completa, com fotos', [
        'Tudo o que tem no Grátis',
        'Colocar o logo e 2 fotos do lugar ou dos produtos',
        'Descrição e lista do que você vende ou faz',
        'Instagram e "atende em casa / faz entrega" na sua página',
        'Ver quantas pessoas abriram a sua ficha no mês',
        'Relatório do mês no celular, todo dia 1',
    ]],
    'destaque' => ['Destaque', 'Tudo do Básico e mais visibilidade', [
        'Tudo o que tem no Básico',
        'Até 10 fotos na sua página',
        'Sem anúncios na sua página',
        'Selo "Verificado pelo Acha Fácil"',
        'Responder as avaliações dos clientes',
        'Ver quantas pessoas abriram sua página e chamaram no WhatsApp',
        'Aparece em "Em destaque" no topo da tela inicial',
        'Aparece na frente dos outros na sua área e na busca',
        'Pode colocar Ofertas da semana na ficha e na página da cidade (' . oferta_preco_texto() . ' por oferta, até ' . OFERTA_MAX_DIAS . ' dias)',
    ]],
    'site' => ['Destaque + Site', 'O Destaque e um site só seu, feito por nós', [
        'Tudo o que tem no Destaque',
        'Site próprio do seu negócio (landing page) feito pela equipe do Acha Fácil',
        'Domínio incluso (ex.: seunegocio.com.br), registro e renovação por nossa conta',
        'Botões de WhatsApp, mapa e horário no site, ligado à sua página no app',
        'Mudanças de texto e fotos do site quando você precisar',
    ]],
];
if (!plano_site_pronto()) unset($planos['site']);

pagina_inicio('Planos para o seu negócio | Acha Fácil!', 'Corrija os dados, coloque fotos, apareça em destaque e tenha um site próprio com o Acha Fácil.');
?>
<main class="documento" style="max-width:1200px">
  <h1>Planos para o seu negócio</h1>
  <?= numeros_publicos_html() /* 19/11 (passo 116) */ ?>
  <p>Comece de graça: assuma a sua ficha e corrija os dados. Depois, se quiser, coloque fotos e apareça em destaque para toda a cidade ver.</p>
  <?php if (!$p): ?>
    <div class="quadro">Primeiro <a href="<?= $base ?>/busca.php">procure o seu negócio</a> e toque em "É meu: cadastrar grátis".
      Ele ainda não está no app? <a href="<?= $base ?>/incluir.php">Inclua de graça</a>.</div>
  <?php endif ?>
  <?php if ($p): ?>
    <div class="quadro"><strong><?= h($p['nome']) ?></strong><br><?= h($p['tipo'] ?? '') ?><?= $p['endereco'] ? ' · ' . h($p['endereco']) : '' ?></div>
  <?php endif ?>

  <div class="tabela-planos">
    <?php foreach ($planos as $chave => [$nome, $sub, $itens]): ?>
      <div class="plano-cartao<?= $chave === 'destaque' ? ' destaque' : '' ?>">
        <?php if ($chave === 'destaque'): ?><span class="fita">Mais visto</span><?php endif ?>
        <h2><?= h($nome) ?></h2>
        <p class="sub"><?= h($sub) ?></p>
        <?php if ($chave === 'gratis'): ?>
          <p class="valor">R$ 0<small>sem cartão, sem mensalidade</small></p>
        <?php elseif (plano_unico($chave)): ?>
          <p class="valor"><?= h(preco_plano($chave)) ?> uma vez só<small>pagamento único, sem mensalidade (cartão, Pix ou boleto)</small></p>
        <?php else: ?>
          <p class="valor"><?= h(preco_plano($chave)) ?> por mês<small>assinatura mensal no cartão, cancele quando quiser</small></p>
        <?php endif ?>
        <ul class="lista-check">
          <?php foreach ($itens as $i): ?><li><?= h($i) ?></li><?php endforeach ?>
        </ul>
        <?php if ($chave === 'site'): ?><p class="discreto" style="margin:0 0 14px">Depois do pagamento, chamamos você no WhatsApp para montar o site.
          O site e o domínio continuam enquanto a assinatura estiver em dia.</p><?php endif ?>
        <a class="botao<?= $chave === 'destaque' ? ' amarelo' : ($chave === 'gratis' ? ' contorno' : '') ?>" href="<?= h($pedir($chave)) ?>"><?= $chave === 'gratis' ? 'Cadastrar grátis' : 'Quero o ' . h($nome) ?></a>
      </div>
    <?php endforeach ?>
  </div>

  <p class="discreto" style="margin-top:28px">Toda mudança passa pela conferência da equipe antes de aparecer no app, para ninguém mudar o telefone de um comércio para enganar clientes.
    <?php if ($p): ?>Não quer aparecer no app? <a href="<?= $base ?>/perfil.php?id=<?= $p['id'] ?>&amp;acao=remover">Pedir para tirar</a> (sem custo).<?php endif ?></p>
</main>
<?php pagina_fim();
