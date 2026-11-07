<?php
// Página de divulgação do app: o que é, como funciona, para profissionais, perguntas e links das lojas
require __DIR__ . '/../inc/base.php';
require __DIR__ . '/../inc/pagina.php';

$categorias = linhas('SELECT nome FROM categorias WHERE ativa = 1 ORDER BY ordem, nome');
$base = config()['url'];

pagina_inicio('Acha Fácil: os profissionais de São João da Boa Vista',
    'App gratuito com encanadores, eletricistas, diaristas e muitos outros profissionais de São João da Boa Vista. Chame no WhatsApp com um toque.');
?>
<main>
  <section class="hero">
    <div class="conteudo">
      <div>
        <span class="selo">✔ Profissionais <b>de São João da Boa Vista</b></span>
        <h1>Precisa de alguém de confiança em <em>São João</em>?</h1>
        <p class="lead">Encanador, eletricista, diarista, mecânico e muito mais. Ache pelo app, veja as avaliações e chame no WhatsApp com um toque.</p>
        <div class="lojas" id="baixar"><?= botoes_lojas() ?></div>
        <p style="color:#9a9aa2;font-size:.95rem;margin-top:14px">Grátis para quem procura. Não precisa criar conta para usar.</p>
      </div>
      <img class="logo-grande" src="<?= $base ?>/assets/logo.png" alt="Logo do Acha Fácil">
    </div>
  </section>

  <section id="como-funciona">
    <div class="conteudo">
      <h2>Simples assim</h2>
      <p class="intro">Feito para todo mundo usar, com letras grandes e botões fáceis de tocar.</p>
      <div class="passos">
        <div class="cartao"><h3>Escolha o serviço</h3><p>Toque em uma categoria na tela inicial ou escreva o que precisa na busca.</p></div>
        <div class="cartao"><h3>Compare</h3><p>Veja fotos, serviços, bairro, horários e as estrelas dadas por outros moradores.</p></div>
        <div class="cartao"><h3>Chame</h3><p>Fale no WhatsApp, ligue ou veja no mapa. O contato é direto com o profissional, sem intermediário.</p></div>
        <div class="cartao"><h3>Avalie</h3><p>Depois do serviço, dê estrelas e comente. Assim a cidade toda sabe quem faz bem feito.</p></div>
      </div>
    </div>
  </section>

  <?php if ($categorias): ?>
  <section class="cinza">
    <div class="conteudo">
      <h2>Tem de tudo um pouco</h2>
      <p class="intro">Algumas das categorias do Acha Fácil:</p>
      <div class="categorias"><?php foreach ($categorias as $c): ?><span><?= h($c['nome']) ?></span><?php endforeach ?></div>
    </div>
  </section>
  <?php endif ?>

  <section class="cinza claro" id="profissionais">
    <div class="conteudo duas">
      <div>
        <h2>É profissional ou tem um comércio em São João?</h2>
        <p class="intro">Apareça para quem está procurando exatamente o que você faz.</p>
        <ul class="lista-check">
          <li>Sua página no app, com foto, serviços, bairro e horários</li>
          <li>Botões de WhatsApp, ligar, Instagram e mapa</li>
          <li>Avaliações com estrelas, e você pode responder</li>
          <li>Veja quantas pessoas abriram sua página e tocaram no WhatsApp</li>
          <li>Selo "Verificado pelo Acha Fácil" para quem é aprovado</li>
        </ul>
        <a class="botao" href="<?= $base ?>/painel/">Quero aparecer no Acha Fácil</a>
      </div>
      <div class="planos">
        <div class="plano"><h3>Plano Básico <span class="preco">valor em breve</span></h3><p>Sua página completa no app, na categoria do seu serviço e na busca.</p></div>
        <div class="plano destaque"><h3>Plano Destaque <span class="preco">valor em breve</span></h3><p>Tudo do Básico e mais: aparece no carrossel do topo da tela inicial, para a cidade inteira ver.</p></div>
        <p class="discreto">Assinatura mensal no cartão, pelo Mercado Pago. Cancele quando quiser. Todo cadastro passa pela aprovação da equipe do Acha Fácil antes de aparecer.</p>
      </div>
    </div>
  </section>

  <section>
    <div class="conteudo" style="max-width:820px">
      <h2>Perguntas frequentes</h2>
      <details><summary>Quanto custa para usar o app?</summary><p>Nada. Procurar profissionais, ver avaliações e chamar no WhatsApp é grátis.</p></details>
      <details><summary>Preciso criar conta?</summary><p>Não. A conta (só nome, e-mail e senha) serve apenas para quem quer avaliar, comentar ou salvar profissionais. Você pode excluir sua conta a qualquer momento pelo próprio app.</p></details>
      <details><summary>O Acha Fácil faz o serviço ou cobra pelo serviço?</summary><p>Não. O Acha Fácil só mostra os profissionais. O combinado (preço, prazo, garantia) é feito direto entre você e o profissional.</p></details>
      <details><summary>Como os profissionais são escolhidos?</summary><p>Cada cadastro é conferido pela equipe do Acha Fácil antes de aparecer. A ordem da lista muda todos os dias, para ser justa com todos, e quem assina o plano Destaque aparece no carrossel do topo.</p></details>
      <details id="instalar-apk"><summary>Como instalar o APK no Android?</summary>
        <p>1. Toque em <strong>Baixar APK</strong> e espere terminar o download.<br>
        2. Toque na notificação do download (ou abra o arquivo <strong>acha-facil.apk</strong> na pasta Downloads).<br>
        3. Se o celular avisar que é de "fonte desconhecida", toque em <strong>Configurações</strong>, ligue <strong>Permitir desta fonte</strong> e volte.<br>
        4. Toque em <strong>Instalar</strong> e depois em <strong>Abrir</strong>.<br>
        Para atualizar, é só baixar de novo e instalar por cima: suas informações continuam lá. No iPhone, o app virá pela App Store.</p></details>
      <details><summary>Tive um problema com um profissional. O que faço?</summary><p>Conte como foi na avaliação (com respeito) e fale com a gente: <?= contatos_html() ?>. Comentários ofensivos ou falsos são retirados.</p></details>
    </div>
  </section>

  <section class="cinza claro">
    <div class="conteudo" style="text-align:center">
      <h2>Baixe o Acha Fácil</h2>
      <p class="intro" style="margin-left:auto;margin-right:auto">Grátis para Android e iPhone.</p>
      <div class="lojas" style="justify-content:center"><?= botoes_lojas() ?></div>
    </div>
  </section>
</main>
<?php pagina_fim();
