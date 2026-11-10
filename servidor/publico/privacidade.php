<?php
// Política de privacidade (LGPD; exigida pela Play Store e pela App Store), com a seção "Excluir minha conta"
// usada como link de exclusão de conta na Play Store: .../privacidade.php#excluir-conta
require __DIR__ . '/../inc/base.php';
require __DIR__ . '/../inc/pagina.php';
$base = config()['url'];
pagina_inicio('Privacidade · Acha Fácil', 'Quais dados o Acha Fácil usa, para quê, e como excluir sua conta.');
?>
<main class="documento">
  <h1>Política de privacidade</h1>
  <p class="atualizado">Última atualização: 10 de novembro de 2026</p>

  <div class="quadro">
    <strong>Resumo:</strong> dá para usar o Acha Fácil sem dar nenhum dado pessoal. Se você entrar com a sua conta Google, guardamos só nome, e-mail,
    foto do perfil e o que você fizer no app (avaliações, comentários e salvos). Não vendemos dados, não mostramos anúncios de
    terceiros e não rastreamos você. Pode excluir tudo pelo próprio app.
  </div>

  <h2>1. Quem cuida dos seus dados</h2>
  <p>O Acha Fácil é mantido pela <strong>FSA Designer</strong>, responsável (controladora) pelos dados tratados no app e neste site,
  conforme a Lei Geral de Proteção de Dados (LGPD, Lei 13.709/2018). Fale com a gente sobre privacidade: <?= contatos_html() ?>.</p>

  <h2>2. Quais dados usamos e para quê</h2>
  <table>
    <thead><tr><th>Situação</th><th>Dados</th><th>Para quê</th></tr></thead>
    <tbody>
      <tr><td>Usar sem conta</td><td>Nenhum dado pessoal. Contamos, sem saber quem foi, quantas vezes cada perfil foi aberto e quantos toques teve em WhatsApp, ligar, Instagram e mapa.</td><td>Mostrar ao profissional o retorno do anúncio.</td></tr>
      <tr><td>Entrar com o Google</td><td>Nome, e-mail e foto do perfil, enviados pelo Google quando você escolhe entrar. Não recebemos a sua senha do Google nem outros dados da sua conta.</td><td>Entrar no app, avaliar, comentar e salvar profissionais.</td></tr>
      <tr><td>Avaliar e salvar</td><td>Estrelas, comentário, data e a lista de salvos.</td><td>Mostrar as avaliações (com primeiro nome e inicial, ex.: "Maria S.") e seus salvos.</td></tr>
      <tr><td>Segurança</td><td>Endereço IP de tentativas de entrada que falharam (apagado em 1 dia) e registros de acesso ao servidor (data, hora e IP).</td><td>Proteger contas contra invasão. Os registros de acesso são guardados por 6 meses, como manda o Marco Civil da Internet.</td></tr>
      <tr><td>Profissionais</td><td>Nome, telefone, WhatsApp, endereço, Instagram, foto, serviços e horários informados no cadastro; nome e telefone de quem cuida do painel do comércio.</td><td>Montar a página pública do profissional no guia (essas informações ficam visíveis para todos, por escolha do profissional) e dar acesso ao painel.</td></tr>
      <tr><td>Assinatura (profissionais)</td><td>Situação da assinatura e identificador do Mercado Pago. Os dados do cartão são digitados no Mercado Pago e <strong>nunca passam pelo Acha Fácil</strong>.</td><td>Saber se o plano está em dia.</td></tr>
    </tbody>
  </table>
  <p>As bases legais são: execução do contrato (sua conta e o anúncio do profissional), legítimo interesse (segurança e estatísticas sem identificação)
  e cumprimento de obrigação legal (registros de acesso).</p>

  <h3>Sua localização</h3>
  <p>Só se você tocar em "Usar minha localização", o app pega a posição do celular para mostrar os comércios perto de você.
  Ela é enviada apenas para calcular as distâncias naquele momento e <strong>não é guardada</strong>. Você pode desligar
  a qualquer momento nas configurações do celular. A localização aproximada dos comércios vem do endereço deles, com
  dados do OpenStreetMap (© colaboradores do OpenStreetMap, licença ODbL).</p>

  <h3>Dados dos comércios mostrados no app e no site</h3>
  <p>Parte dos comércios foi incluída a partir de informações que eles mesmos tornam públicas na internet (nome,
  endereço, telefone, WhatsApp, site e redes sociais do negócio), para que os moradores encontrem o comércio local.
  Os mesmos dados aparecem no app e nas páginas de cada cidade neste site, que podem aparecer em buscadores como o Google;
  o nome de quem avalia aparece só com o primeiro nome e a inicial do sobrenome.
  O responsável pode <a href="<?= h(config()['url']) ?>/busca.php">procurar o negócio</a> e assumir o perfil para corrigir os dados,
  ou pedir para tirar do app; o pedido é atendido em até 2 dias úteis. Quem faz o pedido informa nome e telefone,
  usados só para confirmar e responder.</p>

  <h2>3. O que fica no seu celular</h2>
  <p>O app guarda no seu celular apenas a cidade escolhida e, se você entrar, a chave de acesso da sua conta e o seu nome, para você não precisar entrar toda vez.
  Ao tocar em "Sair", a chave e o nome são apagados.
  O app também guarda um <strong>código aleatório</strong>, criado no próprio celular, que serve só para contar quantas pessoas
  diferentes viram cada comércio por dia (e não contar duas vezes quem abre a mesma página seguida). Ele não é ligado à sua conta,
  ao seu nome nem à sua localização; o servidor guarda apenas uma versão embaralhada dele, por no máximo 2 dias, e depois só os totais. Fora a localização (só quando você pede, veja acima), o app não pede acesso a contatos, fotos, câmera ou microfone,
  e não usa ferramentas de propaganda ou rastreamento.</p>

  <h2>4. Com quem compartilhamos</h2>
  <ul>
    <li><strong>Ninguém compra seus dados.</strong> Não vendemos nem alugamos dados.</li>
    <li>Hospedagem: os dados ficam em servidor contratado pelo Acha Fácil, com acesso protegido.</li>
    <li>Mercado Pago: apenas para as assinaturas dos profissionais.</li>
    <li>Autoridades: somente quando a lei ou uma ordem judicial exigir.</li>
  </ul>
  <p>Quando você toca em WhatsApp, ligar, Instagram ou mapa, você sai para outro serviço, que tem a sua própria política de privacidade.</p>

  <h2>5. Por quanto tempo</h2>
  <p>Os dados da conta ficam guardados enquanto a conta existir. Ao excluir a conta, apagamos na hora o nome, e-mail, foto,
  avaliações, comentários e salvos. Registros de acesso são guardados pelo prazo que a lei exige (6 meses); tentativas de entrada que falharam são apagadas após 1 dia.</p>

  <h2>6. Seus direitos</h2>
  <p>Pela LGPD você pode, a qualquer momento: saber quais dados temos sobre você, receber uma cópia, corrigir dados errados,
  pedir a exclusão, retirar seu consentimento e tirar dúvidas sobre o uso dos seus dados. Basta falar com a gente: <?= contatos_html() ?>.
  Você também pode reclamar à Autoridade Nacional de Proteção de Dados (ANPD).</p>

  <h2 id="excluir-conta">7. Como excluir sua conta</h2>
  <div class="quadro">
    <p style="margin-top:0"><strong>Pelo app (na hora):</strong></p>
    <ol>
      <li>Abra o Acha Fácil e entre na sua conta.</li>
      <li>Toque na aba <strong>Conta</strong>, embaixo.</li>
      <li>Toque em <strong>Excluir minha conta</strong> e confirme.</li>
    </ol>
    <p><strong>Sem o app:</strong> peça a exclusão por <?= contatos_html() ?>, informando o e-mail da conta. Respondemos em até 15 dias.</p>
    <p style="margin-bottom:0">São apagados: nome, e-mail, foto, avaliações, comentários e salvos. Nada mais fica guardado, exceto os registros de acesso que a lei obriga a manter por 6 meses.</p>
  </div>

  <h2>8. Segurança</h2>
  <p>Usamos conexão segura (HTTPS), entrada pela conta Google, limite de tentativas de entrada e acesso restrito ao servidor.
  Nenhum sistema é 100% invulnerável; se acontecer um incidente que possa trazer risco a você, avisaremos e comunicaremos a ANPD.</p>

  <h2>9. Crianças</h2>
  <p>O Acha Fácil é para o público em geral. Para criar conta é preciso ter 18 anos ou mais, ou autorização de pai, mãe ou responsável.</p>

  <h2>10. Mudanças</h2>
  <p>Esta política pode ser atualizada. A data no topo mostra a última mudança; mudanças importantes serão avisadas no app.
  Veja também os <a href="<?= $base ?>/termos.php">Termos de uso</a>.</p>
</main>
<?php pagina_fim();
