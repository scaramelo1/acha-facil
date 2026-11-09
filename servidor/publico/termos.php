<?php
// Termos de uso do app e do site (exigidos pelas lojas). Texto simples, em linguagem do dia a dia.
require __DIR__ . '/../inc/base.php';
require __DIR__ . '/../inc/pagina.php';
$base = config()['url'];
pagina_inicio('Termos de uso · Acha Fácil', 'Regras de uso do app Acha Fácil, para quem procura e para profissionais.');
?>
<main class="documento">
  <h1>Termos de uso</h1>
  <p class="atualizado">Última atualização: 8 de novembro de 2026</p>

  <div class="quadro">
    <strong>Resumo:</strong> o Acha Fácil é uma vitrine de profissionais e comércios de São João da Boa Vista, Águas da Prata e Vargem Grande do Sul (SP) e Poços de Caldas (MG).
    Usar é grátis. O Acha Fácil não faz nem cobra pelos serviços: o combinado é direto entre você e o profissional.
    Avalie com respeito e verdade.
  </div>

  <h2>1. O que é o Acha Fácil</h2>
  <p>O Acha Fácil, mantido pela <strong>FSA Designer</strong>, é um aplicativo e site que reúne profissionais e comércios de São João da Boa Vista, Águas da Prata e Vargem Grande do Sul (SP) e Poços de Caldas (MG).
  Ele mostra informações de contato, serviços e avaliações.
  Ao usar o app ou o site, você concorda com estes termos e com a <a href="<?= $base ?>/privacidade.php">Política de Privacidade</a>.</p>

  <h2>2. Usar sem conta e com conta</h2>
  <p>Procurar, ver perfis e chamar profissionais não precisa de conta. A conta é feita com a sua conta Google (o app recebe só nome, e-mail e foto) e serve para avaliar,
  comentar e salvar profissionais. Para criar uma conta, você precisa ter 18 anos ou mais (ou autorização de pai, mãe ou responsável),
  manter a sua conta Google protegida. Você pode excluir sua conta quando quiser, pelo app
  (aba Conta → Excluir minha conta).</p>

  <h2>3. Avaliações e comentários</h2>
  <ul>
    <li>Avalie só quem você realmente contratou ou atendeu você.</li>
    <li>Nada de ofensas, palavrões, preconceito, ameaças, dados pessoais de outras pessoas, propaganda ou informações falsas.</li>
    <li>Cada pessoa tem uma avaliação por profissional e pode mudá-la depois.</li>
    <li>No app, o comentário aparece com o seu primeiro nome e a inicial do sobrenome (ex.: "Maria S.").</li>
    <li>O profissional pode responder publicamente à sua avaliação.</li>
    <li>O Acha Fácil pode ocultar comentários que desrespeitem estas regras e suspender contas que insistam nisso.</li>
  </ul>

  <h2>4. A contratação é entre você e o profissional</h2>
  <p>O Acha Fácil não presta os serviços anunciados, não participa do combinado e não recebe pagamento pelos serviços.
  Preço, prazo, qualidade, garantia e nota fiscal são de responsabilidade do profissional. A aprovação do cadastro e o selo
  "Verificado pelo Acha Fácil" indicam que o cadastro foi conferido, mas não são uma garantia do serviço.
  Antes de contratar, converse, peça orçamento e veja as avaliações.</p>

  <h2>5. Para profissionais e comércios</h2>
  <ul>
    <li>Você é responsável pelas informações, fotos e textos da sua página, e garante que tem direito de usá-los.</li>
    <li>Todo cadastro passa pela aprovação da equipe do Acha Fácil, que pode recusar ou retirar cadastros falsos, enganosos,
    ilegais ou que recebam reclamações graves.</li>
    <li>O plano Básico é pago uma vez só, pelo Mercado Pago, e não tem mensalidade: a página fica no app enquanto seguir estes termos.
    O plano Destaque é uma assinatura mensal no cartão, pelo Mercado Pago, que pode ser cancelada a qualquer momento e vale até
    o fim do mês já pago. Com o Destaque em atraso ou cancelado, a página perde os recursos do Destaque; se o Básico foi pago,
    continua no app com o Básico, e se não foi, sai do ar até a regularização.</li>
    <li>A ordem em que os profissionais aparecem muda todos os dias. Quem assina o plano Destaque aparece também no carrossel
    do topo da tela inicial. Avaliações não podem ser compradas, trocadas nem apagadas a pedido.</li>
    <li>O selo "Verificado pelo Acha Fácil", responder as avaliações e ver os números da página são recursos do plano Destaque.
    O selo aparece depois que a equipe confere o comércio; não vem automaticamente com o pagamento.</li>
  </ul>

  <h2>6. Uso correto</h2>
  <p>Não é permitido usar o Acha Fácil para enviar spam, copiar em massa os dados dos profissionais, tentar invadir ou sobrecarregar o sistema,
  ou se passar por outra pessoa. A marca, o nome e o visual do Acha Fácil não podem ser usados sem autorização.</p>

  <h2>7. Disponibilidade</h2>
  <p>Fazemos o possível para manter o app funcionando, mas ele pode ficar fora do ar por manutenção ou problemas técnicos.
  Os telefones, endereços e horários são informados pelos próprios profissionais e podem mudar.</p>

  <h2>8. Mudanças nestes termos</h2>
  <p>Estes termos podem ser atualizados. A data no topo da página mostra a última mudança. Se a mudança for importante,
  avisaremos no app.</p>

  <h2>9. Lei e foro</h2>
  <p>Estes termos seguem as leis do Brasil, incluindo o Código de Defesa do Consumidor, o Marco Civil da Internet e a
  Lei Geral de Proteção de Dados. Fica eleito o foro da Comarca de São João da Boa Vista – SP, sem prejuízo do direito do
  consumidor de usar o foro do seu domicílio.</p>

  <h2>10. Fale conosco</h2>
  <p>Dúvidas, reclamações ou pedidos: <?= contatos_html() ?>.</p>
</main>
<?php pagina_fim();
