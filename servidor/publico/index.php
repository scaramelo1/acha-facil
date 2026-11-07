<?php
// Página de divulgação do app: números reais, áreas, como funciona, área do dono do negócio, perguntas e lojas
require __DIR__ . '/../inc/base.php';
require __DIR__ . '/../inc/guia.php';
require __DIR__ . '/../inc/pagina.php';

$areas = categorias_ativas();
$total = (int)valor('SELECT COUNT(*) FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE ' . visivel());
$base = config()['url'];
$num = fn(int $n) => number_format($n, 0, ',', '.');
// emoji de enfeite por ícone (o site não carrega a fonte de ícones do app)
$emoji = ['silverware-fork-knife' => '🍽️', 'cart-outline' => '🛒', 'tools' => '🛠️', 'heart-pulse' => '🩺', 'lipstick' => '💄',
    'tshirt-crew-outline' => '👕', 'home-roof' => '🏠', 'car-wrench' => '🚗', 'shopping-outline' => '🛍️', 'school-outline' => '🎓',
    'dumbbell' => '🏋️', 'paw' => '🐾', 'bed-outline' => '🛏️', 'bank-outline' => '🏦', 'factory' => '🏭', 'hand-heart-outline' => '🤝'];

pagina_inicio('Acha Fácil! O comércio da sua cidade no seu celular',
    "App gratuito com $total comércios e serviços de São João da Boa Vista, Águas da Prata (SP) e Poços de Caldas (MG): restaurantes, mercados, oficinas, clínicas, lojas e muito mais. Chame no WhatsApp com um toque.");
?>
<main>
  <section class="hero">
    <div class="conteudo">
      <div>
        <h1>Tudo o que tem na <mark>sua cidade</mark>, num toque.</h1>
        <p class="lead">São João da Boa Vista e Águas da Prata (SP) e Poços de Caldas (MG). Restaurante, mercado, oficina, clínica, loja ou quem faz aquele conserto. Procure no Acha Fácil e chame no WhatsApp, ligue ou veja como chegar.</p>
        <ul class="contador">
          <li><b><?= $num($total) ?></b> comércios e serviços</li>
          <li><b><?= count(CIDADES) ?></b> cidades</li>
        </ul>
        <div class="lojas" id="baixar"><?= botoes_lojas() ?></div>
        <p class="discreto" style="margin-top:14px">Grátis. Não precisa criar conta para procurar.</p>
      </div>
      <div class="celular" aria-hidden="true">
        <div class="ola"><small>Boa tarde!</small>O que você procura hoje?</div>
        <div class="busca">🔍 pizza, borracharia, farmácia…</div>
        <div class="grade"><span>🍽️</span><span>🛒</span><span>🛠️</span><span>🩺</span><span>💄</span><span>🚗</span></div>
        <div class="item"><b>Conhecidos na cidade</b>Os mais procurados da cidade, todo dia uma seleção nova</div>
      </div>
    </div>
    <svg class="onda" viewBox="0 0 1200 48" preserveAspectRatio="none" aria-hidden="true"><path d="M0 24 C200 0 400 48 600 24 S1000 0 1200 24 V48 H0Z" fill="#fff"/></svg>
  </section>

  <section class="branco" id="areas" style="padding-top:24px">
    <div class="conteudo">
      <h2>De padaria a oficina</h2>
      <p class="intro">Os comércios de cada cidade organizados por área. Dentro de cada uma, filtre pelo tipo: pizzaria, academia, pet shop…</p>
      <div class="areas">
        <?php foreach ($areas as $a): ?>
          <div class="area"><i style="background:<?= h($a['cor']) ?>"><?= $emoji[$a['icone']] ?? '📍' ?></i><span><b><?= h($a['nome']) ?></b><small><?= $num($a['total']) ?> lugares</small></span></div>
        <?php endforeach ?>
      </div>
    </div>
  </section>

  <section class="verde" id="como-funciona">
    <div class="conteudo">
      <h2>Do celular até a porta da loja</h2>
      <p class="intro">Feito para todo mundo usar, com letras grandes e botões fáceis de tocar.</p>
      <div class="passos">
        <div class="passo"><h3>Procure</h3><p>Escreva o que precisa ou toque numa área da tela inicial.</p></div>
        <div class="passo"><h3>Escolha</h3><p>Veja endereço, telefone, Instagram e as estrelas dadas por outros moradores.</p></div>
        <div class="passo"><h3>Chame ou vá</h3><p>WhatsApp, ligação ou rota no mapa, direto com o comércio, sem intermediário.</p></div>
      </div>
    </div>
  </section>

  <section id="seu-negocio">
    <div class="conteudo duas">
      <div>
        <h2>Seu negócio já pode estar no Acha Fácil</h2>
        <p class="intro">Montamos o guia com os dados públicos dos comércios das cidades atendidas. Procure o seu e confira os planos para corrigir os dados e colocar fotos.</p>
        <ul class="lista-check">
          <li>Corrija telefone, WhatsApp, endereço e horário</li>
          <li>Coloque foto, descrição e o que você vende ou faz</li>
          <li>Ganhe o selo "Verificado pelo Acha Fácil"</li>
          <li>Veja quantas pessoas abriram sua página e chamaram no WhatsApp</li>
        </ul>
        <p class="discreto">Não quer aparecer? Procure o seu negócio e toque em "Tirar do app". Retiramos sem perguntas.</p>
      </div>
      <div class="caixa">
        <h3>Procure seu negócio</h3>
        <form class="procurar" action="<?= $base ?>/busca.php" method="get">
          <input name="q" placeholder="Nome do seu comércio" aria-label="Nome do seu comércio" required minlength="2">
          <button class="botao amarelo">Procurar</button>
        </form>
        <div class="planos">
          <div class="plano"><h3>Básico <span class="preco">valor em breve</span></h3><p>Corrija os dados, coloque fotos e ganhe o selo de verificado.</p></div>
          <div class="plano destaque"><h3>Destaque <span class="preco">valor em breve</span></h3><p>Aparece no topo da tela inicial e na frente da sua área, para a cidade inteira ver.</p></div>
        </div>
        <p style="margin:16px 0 0"><a class="botao" href="<?= $base ?>/planos.php">Conferir os planos</a></p>
      </div>
    </div>
  </section>

  <section class="branco">
    <div class="conteudo" style="max-width:820px">
      <h2>Perguntas frequentes</h2>
      <details><summary>Quanto custa para usar o app?</summary><p>Nada. Procurar, ver avaliações e chamar no WhatsApp é grátis.</p></details>
      <details><summary>Preciso criar conta?</summary><p>Não. A conta (só nome, e-mail e senha) serve para quem quer avaliar, comentar ou salvar lugares. Você pode excluir sua conta a qualquer momento pelo próprio app.</p></details>
      <details><summary>De onde vêm os comércios?</summary><p>De informações públicas (nome, endereço, telefone e redes sociais que o próprio comércio divulga na internet). O dono pode assumir o perfil e corrigir tudo, ou pedir para tirar do app pela <a href="<?= $base ?>/busca.php">busca do site</a>.</p></details>
      <details><summary>O Acha Fácil vende ou faz os serviços?</summary><p>Não. O Acha Fácil só mostra quem é quem. Preço, prazo e garantia são combinados direto com o comércio.</p></details>
      <details><summary>Em que ordem aparecem?</summary><p>Quem paga o Destaque aparece primeiro. Depois vêm os perfis assumidos pelos donos e os demais. Dentro de cada grupo, a ordem muda todo dia, para ser justa com todos.</p></details>
      <details id="instalar-apk"><summary>Como instalar o APK no Android?</summary>
        <p>1. Toque em <strong>Baixar APK</strong> e espere terminar o download.<br>
        2. Toque na notificação do download (ou abra o arquivo <strong>acha-facil.apk</strong> na pasta Downloads).<br>
        3. Se o celular avisar que é de "fonte desconhecida", toque em <strong>Configurações</strong>, ligue <strong>Permitir desta fonte</strong> e volte.<br>
        4. Toque em <strong>Instalar</strong> e depois em <strong>Abrir</strong>.<br>
        Para atualizar, é só baixar de novo e instalar por cima. No iPhone, o app virá pela App Store.</p></details>
      <details><summary>Achei uma informação errada. O que faço?</summary><p>Fale com a gente: <?= contatos_html() ?>. Se for o dono, <a href="<?= $base ?>/planos.php">confira os planos</a> para corrigir os dados e colocar fotos.</p></details>
    </div>
  </section>

  <section class="verde">
    <div class="conteudo" style="text-align:center">
      <h2>Baixe o Acha Fácil!</h2>
      <p class="intro" style="margin-left:auto;margin-right:auto">O comércio da sua cidade, pertinho de você.</p>
      <div class="lojas centro"><?= botoes_lojas() ?></div>
    </div>
  </section>
</main>
<?php pagina_fim();
