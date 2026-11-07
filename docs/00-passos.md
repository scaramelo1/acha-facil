# Acha Fácil! — passos

App para achar profissionais e comércios de **São João da Boa Vista** e **Águas da Prata** (SP) e **Poços de Caldas** (MG).
Lema: *"O comércio da sua cidade, pertinho de você."*
Criado em 07/11/2026 a partir do **Guia Sanjoanense** (`/var/www/guia-sanjoanense`), sem a chancela da página
Sanjoanenses e sem a aba Notícias.

- **App:** Expo (TypeScript), na raiz do repositório (`src/`), pacote `br.com.fsadesigner.achafacil`.
- **Servidor:** PHP 8.3 + MariaDB em `servidor/`, publicado em https://fsadesigners.com.br/acha-facil/
  (`bash servidor/instalar.sh` como root; config em `/etc/acha-facil/config.php`; banco `acha_facil`).
- **Pagamento:** Mercado Pago, assinatura recorrente no cartão (o cartão é digitado no Mercado Pago).
- **Marca:** verde `#005C38` e amarelo `#F7C600`; símbolo (pino + loja + lupa) em `assets/logo-simbolo.svg`.
- **Visual (07/11, modelo do usuário):** fundo claro, cartões brancos com sombra suave, abas com pílula verde
  (Início, Categorias, Favoritos, Perfil), cidade em pílula amarela, cada área com a sua cor. Site com fonte Nunito e o mesmo estilo.

Legenda: ✅ feito · 🟡 parcial · ⬜ a fazer

## Regras combinadas

- Navegar, buscar e chamar no WhatsApp **não pede conta**. A conta (e-mail e senha) serve para avaliar,
  comentar e salvar profissionais. A pessoa pode excluir a conta pelo app (exigência das lojas).
- Uma avaliação por pessoa por profissional (pode mudar depois). O comentário mostra só "Maria S.".
- O profissional aparece se estiver **aprovado** e com a **assinatura em dia** (ou cortesia dada pelo admin).
- Ordem justa: destaques primeiro, depois quem tem avaliação; dentro de cada grupo a ordem muda uma vez por dia.
- Responsável: **FSA Designer** (termos e privacidade); contatos fsadesignerbr@gmail.com e Instagram @fsa.designer
  em `/etc/acha-facil/config.php`.
- Selo "Verificado pelo Acha Fácil" é dado pelo admin, não vem com o pagamento.
- Os comércios vêm da **lista de leads** (fsadesigners.com.br/leads, coleta do Google Maps): `importado = 1`, aparecem
  **sem assinatura** até o dono assumir o perfil. Reimportar: `php servidor/importar-leads.php [arquivo] [cidade]` (ou `bash servidor/instalar.sh --leads`, que faz as duas cidades) (atualiza pelo nome; não mexe em
  quem assumiu nem em quem foi tirado). Os 10 exemplos fictícios foram apagados.
- O dono procura o negócio em `/busca.php` e pede para **assumir** (grátis) ou **tirar do app** (`/perfil.php`); para corrigir dados e pôr fotos, o dono confere os planos em `/planos.php` (Básico e Destaque, valores em breve); os pedidos
  ficam na tabela `pedidos` até a administração web existir (passo 8).
- Nota do Google **não aparece** no app: o número de avaliações do Google só ordena os "Conhecidos na cidade".

## Passos

1. ✅ Base copiada do Guia Sanjoanense (07/11): banco, instalador, API, contas, avaliações, salvos, telas do app,
   site de divulgação, termos, privacidade e botão Baixar APK — tudo com o nome Acha Fácil
2. ✅ Sem notícias (07/11): abas Início, Salvos e Conta; sem coleta (cron) e sem notícias no site e nos termos
3. ✅ Marca (07/11): ícone do app, ícone adaptativo do Android, splash e abertura no fundo verde
   ("Acha **Fácil!**" + lema), logo da tela inicial, favicon do site, cores verde/amarelo no app e no site.
   O símbolo foi redesenhado em vetor a partir da arte enviada (`assets/logo-simbolo.svg`)
4. ✅ Instalado na VPS (`bash servidor/instalar.sh --exemplos`); https://fsadesigners.com.br/acha-facil/ no ar
4b. ✅ Visual completamente diferente do Guia Sanjoanense (07/11): app (inicial, áreas, área com filtros por tipo, busca,
   perfil do comércio, abas) e site (inicial com números reais, busca do dono, assumir/tirar do app, privacidade)
4c. ✅ 2.754 comércios da lista de leads no banco, em 17 áreas (07/11) — `bash servidor/instalar.sh --leads`
4d. ✅ Duas cidades (07/11): ao abrir, o app pergunta "Onde você está?" (quadrados com o pino de localização:
   São João da Boa Vista ou Águas da Prata; a última fica marcada; troca pelo cabeçalho ou na aba Conta).
   101 comércios de Águas da Prata importados (`/var/lib/acha-facil/leads/aguas-da-prata.html`)
4e. ✅ Visual no estilo do modelo enviado (07/11): telas claras com cartões brancos, abas Início/Categorias/Favoritos/Perfil
   com pílula verde, "Encontre o que precisa, perto de você", faixa "Explore o melhor da cidade", busca e filtros dentro da
   categoria, comércio com capa, "Conversar no WhatsApp" e "Este comércio é seu?". Sem fotos: o espaço mostra o ícone da área
   até o dono colocar fotos pelo plano
4f. ✅ "Perto de você" com a localização do celular (07/11): pede só ao tocar, lista do mais perto ao mais longe, com
   atalhos Restaurantes, Lanches, Pizzarias, Padarias, Mercados, Farmácias, Floriculturas, Pet shops, Beleza, Sorvetes,
   Oficinas, Postos e Saúde (lista em `ATALHOS_PERTO`, servidor/inc/guia.php). Localização dos comércios: link do Google
   ou endereço no OpenStreetMap (`php servidor/geocodificar.php`, ~1 h na 1ª vez; aproximada, muitas vezes pela rua).
   Novo pacote no app: expo-location
4g. ✅ "Comércios em destaque" na tela inicial (07/11): **só quem paga o plano Destaque** (fita amarela); sem pagantes,
   aparece só o cartão "Seu comércio aqui → Ver planos"
4h. ✅ Poços de Caldas (MG), 07/11: 1.674 comércios (lista atualizada) (`/var/lib/acha-facil/leads/pocos-de-caldas.html`); a escolha ao abrir
   agora é em dois passos, estado (SP, MG) e depois a cidade. Cidade nova: `CIDADES` em servidor/inc/guia.php e src/cidade.tsx
4i. ✅ "Perto de você" só da cidade escolhida (07/11): olhando Poços estando em São João, não aparecem mais os comércios de
   São João. Se não há nada da cidade a até 15 km, o app avisa "Você não está em …" e mostra os conhecidos de lá
4j. ✅ Visual novo pelas 3 imagens do modelo (07/11, v1.1.0): cabeçalho verde com a paisagem da cidade (morros e igreja
   amarela, `assets/ilustracoes/cabecalho.png`, desenhada por `paisagem.py`), "Acha Fácil!" com traço amarelo, cidade
   em pílula branca e busca amarela; abas com tracinho verde. Telas: Início (categorias, destaque, recomendados, perto,
   "Tem um comércio?"), Categorias, área, Busca com Filtros (categoria, bairro, ordem, com telefone), comércio, Avaliar
   (tela própria), Favoritos, Perfil (visitante e conectado), Entrar, Criar conta (confirmar senha + aceite), Recuperar
   senha (abre o e-mail pronto para a FSA, o servidor ainda não manda e-mail), Escolher cidade (busca, estado em pílulas,
   cidades do estado e confirmar), Procurar meu negócio, Planos e Sobre. Sem fotos dos comércios: a cor e o ícone da área no lugar.
   Ficou de fora (não existe no servidor): "Editar meus dados" e "Minhas avaliações"
5. ✅ Testar no PC (`npm install`, `npx tsc --noEmit`, `npx expo lint`, emulador)
6. ✅ Projeto EAS próprio e 1º APK publicado no site (07/11, v1.0.0); publicar com `bash servidor/publicar-apk.sh <link do EAS>`
7. ⬜ Contas no app: conferir se entrar/criar conta funciona (no Guia Sanjoanense isso ainda está em aberto)
8. ⬜ Administração web: pedidos de assumir/tirar do app, aprovar/recusar cadastros, editar, selo, cortesia, categorias, ocultar comentários
9. ⬜ Painel do profissional (web): autocadastro, foto, dados, serviços, responder avaliações, ver contatos recebidos
10. ⬜ Assinatura recorrente no Mercado Pago (planos Básico e Destaque; webhook; atraso tira do ar)
11. ⬜ Publicar nas lojas (Play Store e App Store) — conferir se o nome "Acha Fácil" está livre nas duas

## Decisões pendentes

- WhatsApp de contato (`contato_whatsapp` no config); vazio, não aparece no site.
- Arquivo da logo em alta (PNG/SVG) para trocar o redesenho, se quiser fidelidade total à arte.
- Preço dos planos Básico e Destaque (mensal) e quantos lugares no carrossel de destaque.
- Se volta a ter notícias no futuro.
