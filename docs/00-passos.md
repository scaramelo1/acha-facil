# Acha Fácil! — passos

App para achar profissionais e comércios de **São João da Boa Vista** e **Águas da Prata** (SP) e **Poços de Caldas** (MG).
Lema: *"O comércio da sua cidade, pertinho de você."*
Criado em 07/11/2026 a partir do **Guia Sanjoanense** (`/var/www/guia-sanjoanense`), sem a chancela da página
Sanjoanenses e sem a aba Notícias.

- **App:** Expo (TypeScript), na raiz do repositório (`src/`), pacote `br.com.fsadesigner.achafacil`.
- **Servidor:** PHP 8.3 + MariaDB em `servidor/`, publicado em https://achafacilapp.com.br/ (desde 10/11; antes fsadesigners.com.br/acha-facil/)
  (`bash servidor/instalar.sh` como root; config em `/etc/acha-facil/config.php`; banco `acha_facil`).
- **Pagamento:** Mercado Pago (08/11): Básico R$ 9,90 pagamento único (era 5,99 até 10/11; Checkout: cartão, Pix ou boleto); Destaque R$ 29,90/mês e Destaque + Site R$ 89,90/mês (10/11), assinaturas no cartão.
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
- Responsável: **FSA Designer** (termos e privacidade); contatos achafacilcontato@gmail.com (desde 17/11) e Instagram @fsa.designer
  em `/etc/acha-facil/config.php`.
- Selo "Verificado pelo Acha Fácil" é do plano Destaque (08/11): o admin marca `verificado`, mas o selo só aparece com o Destaque em dia.
- Os comércios vêm da **lista de leads** (fsadesigners.com.br/leads, coleta do Google Maps): `importado = 1`, aparecem
  **sem assinatura** até o dono assumir o perfil. Reimportar: `php servidor/importar-leads.php [arquivo] [cidade]` (ou `bash servidor/instalar.sh --leads`, que faz as duas cidades) (atualiza pelo nome; não mexe em
  quem assumiu nem em quem foi tirado). Os 10 exemplos fictícios foram apagados.
- O dono procura o negócio em `/busca.php` e pede para **assumir** (grátis) ou **tirar do app** (`/perfil.php`); para corrigir dados e pôr fotos, o dono confere os planos em `/planos.php` (08/11: Básico R$ 5,99 uma vez só = corrigir dados, fotos, descrição e o que vende; Destaque R$ 29,90/mês = tudo do Básico + selo, responder avaliações, números da página e topo); os pedidos
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
4k. ✅ "Quero minha Cidade!" na tela de escolher a cidade (07/11): a pessoa digita a cidade e a sigla do estado
   (api/quero-cidade.php, tabela cidades_pedidas, mudança 009); a lista com o total por cidade fica no /admin
   (08/11) Estado primeiro (27 siglas) e cidade com autocompletar da lista do IBGE (servidor/dados/municipios.json,
   api/municipios.php); o servidor só aceita cidade da lista e grava o nome oficial. Admin exclui cidades da lista (site e app)
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
   Aviso de versão nova (08/11, igual ao ReadyTAF): o app pergunta api/versao.php ao abrir (no máximo a cada 6 h) e,
   se o APK do site for mais novo, mostra "Nova versão" com Baixar. A versão vem de dentro do APK (publicar-apk.sh).
   Sempre subir o "version" do app.json antes de gerar APK novo (1.2.0 desde 08/11)
7. ✅ Contas no app só com o Google (07/11): o app pede uma entrada ao servidor (api/google.php, acao=iniciar), abre
   /google/entrar.php no navegador, a pessoa entra no Google e /google/retorno.php grava a conta (nome, e-mail, foto);
   enquanto isso o app pergunta ao servidor a cada 1,5 s (acao=verificar, chave secreta só do app) e recebe a sessão.
   Não depende de o celular devolver um link para o app (no emulador o link caía no APK antigo). Sem senha:
   "Recuperar senha", entrar.php e criar-conta.php saíram. Termos e privacidade atualizados. Chaves do Google no
   config e migração 006 aplicadas; servidor testado de ponta a ponta. Emulador ✅ (07/11 19:30: entrou e recebeu a sessão).
   Falta: (a) testar no APK novo (o do site é o 1.0.0, ainda com e-mail e senha); (b) ✅ tela de consentimento OAuth
   "Em produção" (09/11, Branding preenchido sem logo; falta confirmar entrando com uma conta Google qualquer);
   (c) para a App Store: "Entrar com Apple" junto (regra 4.8 da Apple), precisa da conta de desenvolvedor Apple
8. ✅ Administração web em /admin (07/11): **toda mudança do dono passa por um admin** (contra fraude). Feito: entrar
   (admins; criar com `php servidor/criar-admin.php email "Nome"`, senha sorteada e mostrada uma vez), Pendências
   (alterações dos donos com "como está x como vai ficar", aprovar/recusar com motivo; pedidos de assumir/tirar do app),
   o dono que **paga entra no painel sozinho** (botão "Editar os dados do meu comércio" na página da assinatura; o
   pagamento não precisa de aprovação, só as alterações). Na 1ª alteração de quem entrou pelo pagamento o admin vê o
   aviso para ligar no telefone do cadastro antes de aprovar. Link manual do painel para casos sem pagamento (cortesia),
   cortar acesso, tirar do app (status pausado) e Registro de tudo que cada admin fez. Tabelas na mudança 008
   (acessos_dono, alteracoes, registro_admin); regras em servidor/inc/moderacao.php (só aprovar_alteracao() grava os
   dados do dono). Falta: total de downloads do APK (`/var/lib/acha-facil/apk/downloads.txt`), cadastrar negócio novo,
   selo, cortesia, categorias, ocultar comentários
9. ✅ Painel do dono em /painel (07/11): entra pela página da assinatura (?c=código do pedido pago) ou pelo link do admin; com plano em dia pede mudança de nome, sobre, serviços,
   WhatsApp, telefone, Instagram, site, endereço, bairro, horário, atende em casa e foto (sem dados escondidos da foto,
   reduzida). Nada vai ao app sem aprovação; endereço novo zera a localização para o geocodificar.php achar de novo.
   Falta: responder avaliações e ver contatos recebidos
10. ✅ Assinatura recorrente no Mercado Pago (07/11; em produção desde 08/11, ver 10c): planos.php -> perfil.php (nome, telefone, e-mail) -> assinatura.php?c=
   (página secreta do pedido) -> Mercado Pago (assinatura mensal no cartão, "preapproval"); avisos em mp-webhook.php,
   sempre conferidos na API (inc/mercadopago.php). Assinatura autorizada liga o plano (3 dias até a 1ª mensalidade);
   cada mensalidade paga estende 1 mês, uma vez só; cancelada vale até o fim do mês pago; vencida sai do destaque
   (e do app, se não é importado). Testado numa cópia isolada (18 testes + páginas). Mudança 007, aplicação do
   Mercado Pago, sandbox e produção: feitos (08/11).
   O app não mostra preço nem botão de contratar (regra das lojas): "Este comércio é seu?" -> "Saiba mais no site"
10b. ✅ Planos novos (08/11, pedido do usuário): **Básico R$ 5,99 pagamento único** (Checkout Pro, aviso `payment` no
   mp-webhook.php -> mp_conferir_pagamento; aprovado grava `profissionais.basico_pago_em` e o comércio fica no app para
   sempre) e **Destaque R$ 29,90/mês** (assinatura, como antes). Selo, responder avaliações e números da página passaram
   para o Destaque; Destaque vencido volta ao Básico se ele foi pago. Mudança 011 (aplicada pelo instalar.sh). Site
   (planos, inicial, pedido, página do pagamento, termos) e app (tela "Este comércio é seu?") ajustados.
   No Mercado Pago, marcar também o evento "Pagamentos" no Webhook
10c. 🟡 Sem conta no Mercado Pago (08/11, pedido do usuário): Básico pelo Checkout Pro (cartão, Pix ou boleto como
   convidado); Destaque com o cartão digitado na própria assinatura.php (Card Payment Brick; token -> /preapproval já
   autorizado, mp_assinar_cartao) e botão de cancelar na mesma página. Liga com `mp_public_key` no config (sem ela, volta
   ao botão que abre o Mercado Pago). Testado no sandbox (Destaque autorizado; Webhook com assinatura conferida).
   **Produção ligada em 08/11** (conta AGNESAMARILIS); testes apagados (pedidos 6-11, Destaque da academia I9).
   Falta: 1º pagamento real do Básico (Checkout sem conta) e devolver
10d. ✅ Incluir negócio de graça (08/11, pedido do usuário): /incluir.php com os dados iniciais (nome, o que é, área,
   cidade, endereço, bairro, telefone, WhatsApp, horário, Instagram) + nome e telefone de quem pede. O comércio entra
   'pendente' e importado = 1; pedido tipo 'incluir' (mudança 012) avisa os admins; /admin/pedido.php publica ou recusa.
   A busca e os planos sem comércio levam para a busca/inclusão (plano é sempre de um comércio do app)
11. ✅ Publicar na Play Store (a App Store foi para o passo 102): guia, textos e formulários em `docs/lojas.md`; app.json pronto
   para o iOS (07/11). Nome "Acha Fácil" não achado em nenhuma das duas. Faltam: contas de desenvolvedor, capturas de
   tela, builds de produção (.aab e iOS), teste fechado de 14 dias na Play (conta pessoal) e conta de demonstração (passo 7).
   09/11: conta da Play ativa; passo a passo da Play no topo de `docs/lojas.md`; build "production" (Play) sem AD_ID (blockedPermissions; declaração "Não") e sem o aviso
   de APK do site (APP_LOJA=play no eas.json); ícone 512 em assets/loja/.
   09/11: Play Console preenchido (ficha, capturas, declarações) e teste fechado (versão 4 / 1.3.0, Brasil, lista
   "Testadores Acha Fácil") **aprovado e publicado** (09/11). Falta: 12+ testadores inscritos por 14 dias → pedir produção

## Decisões pendentes

- WhatsApp de contato (`contato_whatsapp` no config); vazio, não aparece no site.
- Arquivo da logo em alta (PNG/SVG) para trocar o redesenho, se quiser fidelidade total à arte.
- Quantos lugares no carrossel de destaque (preços desde 10/11: Básico R$ 9,90 uma vez só, Destaque R$ 29,90/mês, Destaque + Site R$ 89,90/mês).
- Se volta a ter notícias no futuro.
12. ✅ Administrador no app com avisos no celular (07/11): fellipealexandre1@gmail.com é admin (tabela admins; também
   entra no /admin do site). Conta do app com e-mail de admin vê "Administração" no Perfil (aprovar/recusar alterações
   com antes x depois, pedidos novos, cidades pedidas) e recebe aviso quando alguém **paga um plano**, **pede uma cidade**
   ou **manda alterações**; tocar no aviso abre a tela. Desde 08/11 também avisa **no momento do pedido** (plano escolhido,
   tirar do app, assumir/incluir), sem esperar o Mercado Pago. Servidor: inc/avisos.php (Expo Push), api/avisos.php,
   api/admin.php, tabela avisos_aparelhos (mudança 010). App: src/avisos.ts, src/app/admin.tsx, pacotes expo-notifications
   e expo-device. Firebase: google-services.json no projeto (08/11, projeto acha-facil-e7bfd). Chave FCM V1 no EAS ✅ (08/11). Testado pelo usuário ✅ (09/11).
   No iPhone, depois da conta Apple
13. ✅ Logo + carrossel de fotos (09/11, pedido do usuário, "igual o Facebook"): `foto` virou o **logo** (listas do app
   e círculo por cima da capa) e a coluna nova `fotos` (mudança 013, lista JSON) guarda até 10 fotos que passam como
   carrossel na capa do perfil (bolinhas, contador, toque abre em tela cheia: src/componentes/CapaComercio.tsx). A 1ª foto
   também é a faixa do Destaque (`capa` na API). Painel do dono: trocar logo, tirar fotos e colocar várias de uma vez
   (o navegador reduz antes de enviar; painel/.htaccess sobe o limite para 10 MB/64 MB). Tudo passa pela moderação
   (admin vê as miniaturas no site e no app) e arquivos que saem do app são apagados. Testado numa cópia isolada
   (12 testes + envio real pelo formulário). Vai no APK 1.3.0
   09/11 (2): logo sempre quadrado: no painel o dono enquadra num círculo (arrastar + aproximar, sai 600x600) e o
   servidor completa com branco + margem quem mandar sem o recorte; dicas de tamanho (logo 500x500, fotos deitadas
   1200x750). Conferido pelo usuário ✅ (09/11).
14. ✅ Cupons de desconto (09/11, pedido do usuário): desconto em % para Básico e/ou Destaque; o dono digita na página
   do pagamento (assinatura.php, "Tem cupom de desconto?") antes de pagar. No Destaque a assinatura do Mercado Pago já
   nasce com o valor com desconto, então vale **em todas as mensalidades**; a conferência dos avisos do Mercado Pago usa
   o valor do pedido (preco_pedido em inc/cupons.php). 100% = cortesia sem Mercado Pago (Básico para sempre; Destaque
   com assinatura 'cortesia', que o admin pode encerrar). Cupom que daria menos de R$ 1,00 não vale (mínimo do MP).
   Admin em /admin/cupons.php (criar, ligar/desligar, quem usou). Mudança 014 aplicada. Cupom de cortesia criado:
   CORTESIA-MKS7 (100%, os dois planos, até 10 usos). Testado numa cópia isolada (16 testes + páginas). Falta: 1º uso
   real de cupom parcial no Mercado Pago (conferir o valor na assinatura)
15. ✅ Horário por campo + logo no Destaque (09/11, pedido do usuário, modelo do Google Maps): no painel do dono e no
   /incluir.php o horário virou 4 linhas (Segunda a sexta, Sábado, Domingo, Feriados), cada uma Aberto (abre/fecha,
   pode fechar depois da meia-noite), Fechado, 24 horas ou Não informar. Coluna `horarios` (JSON, mudança 015 aplicada);
   o texto `horario` é gerado dela (app antigo, páginas do site). App: src/componentes/Horarios.tsx mostra cada campo
   numa linha, o de hoje em negrito e "Aberto agora · fecha às ..." (feriado o app não sabe: usa o dia da semana).
   Spani convertido para o formato novo. Faixa do Destaque: logo à esquerda + nome (não usa mais a 1a foto).
   Testado numa cópia isolada e conferido pelo usuário ✅ (09/11). Vai no APK 1.3.0
16. ✅ Vargem Grande do Sul - SP (09/11, pedido do usuário): 4ª cidade (slug vargem-grande-do-sul, curto "Vargem Grande").
   Lista do robô em /var/lib/acha-facil/leads/vargem-grande-do-sul.html: 1.037 comércios importados (10 repetidos
   pulados), todos com localização pelo link do Google. Cidade em servidor/inc/guia.php e src/cidade.tsx (o app precisa
   do APK novo para mostrar a cidade na escolha); textos do site, termos e lojas atualizados. Backup da tabela antes:
   /root/acha-facil-profissionais.antes-vargem.*.sql.gz
17. ✅ Aguaí - SP (09/11, pedido do usuário): 5ª cidade (slug aguai). Lista em /var/lib/acha-facil/leads/aguai.html
   (categorias com o nome completo do Google): 1.442 comércios importados; 85 lugares públicos ficaram de fora (ponto de
   ônibus, parque, prefeitura, delegacia, bairros soltos: NAO_COMERCIO e SO_LUGAR em importar-leads.php) e o resto foi
   para a área certa por palavras-chave (só 11 em "Outros"). A cidade de cada linha agora é a última citada no endereço.
   Os links dessa lista não trazem coordenadas: localização pelo OpenStreetMap (geocodificar.php, ~30 min).
   Backup antes: /root/acha-facil-profissionais.antes-aguai.*.sql.gz
18. ✅ Vargem Grande do Sul atualizada (09/11, pedido do usuário, lista nova com igrejas): 2.118 comércios (1.094 novos,
   71 igrejas). Reimportar não piora quem já estava: linha nova sem área mantém a área/tipo antigos e link do Maps sem
   coordenadas não troca o que tinha. Mais palavras-chave de área e mais lugares públicos (praça, represa, SAAE, Detran,
   SAMU, Tiro de Guerra) fora. Lista anterior guardada em /var/lib/acha-facil/leads/vargem-grande-do-sul.antes-20261109.html.
   Corrigido: geocodificar.php pedia ao OpenStreetMap só a área antiga (viewbox fixo) e Aguaí não era achada; agora usa a
   CAIXA. Backup: /root/acha-facil-profissionais.antes-vargem2.*.sql.gz
19. ✅ Cidades pelo banco, sem APK novo (10/11, pedido do usuário): tabela `cidades` (mudança 016 aplicada) com nome,
   nome curto, estado, centro (lat/lng) e liga/desliga. O app baixa a lista em api/cidades.php ao abrir e ao trocar de
   cidade, guarda a última no celular (abre sem internet) e só usa a lista embutida na 1ª abertura sem internet. Cidade
   desligada some do app (quem estava nela cai na primeira). Admin: /admin/cidades.php (cadastrar pelo nome do IBGE,
   centro achado sozinho no OpenStreetMap, nasce desligada; ligar/desligar; nome curto e centro). Servidor inteiro lê
   do banco: importador (aceita cidade ainda desligada), geocodificação (área = centros das cidades + 25 km), textos
   do site/termos e instalar.sh --leads (todo arquivo /var/lib/acha-facil/leads/<slug>.html). Testado: cidade de teste
   desligada não aparece, ligada aparece na API e no site, apagada some. Vai no APK 1.4.0 (o último que precisa sair
   por causa de cidade).
   Cidade nova daqui em diante: 1) /admin/cidades.php → Cadastrar (desligada); 2) mandar a lista do robô para a VPS e
   rodar `php servidor/importar-leads.php <arquivo> <código>`; 3) `nohup php servidor/geocodificar.php ...`; 4) Ligar.

## Plano de crescimento (10/11) — detalhes em `docs/plano-crescimento.md`

20. ✅ Contador de visualizações em todas as fichas (10/11): código feito e testado numa cópia isolada.
   Servidor: `servidor/inc/numeros.php` (contar_visita com origem e pessoas diferentes por dia; quem reabre em menos de
   30 min não conta de novo; robôs não contam; contar_aparicoes nas listas: início, categoria e busca até 40, perto 10;
   contar_contato com origem app/web). Mudança 017: `contatos.origem` e `contatos.pessoas`, tabelas `vistas` (código
   anônimo embaralhado, apagado depois de 2 dias) e `aparicoes`. Admin: **Números** (`/admin/numeros.php`: filtros cidade,
   área, tipo, 7/30/90 dias, plano, só vistos; ordenar por coluna; CSV com WhatsApp/telefone), `/admin/numeros-ficha.php`
   (dia a dia, origem, onde apareceu, contatos, posição entre os do mesmo tipo) e `/admin/relatorio.php?id=&mes=AAAA-MM`
   (1 folha para o comerciante: imprimir/salvar PDF ou print). Painel do dono: Destaque vê 30 dias completos; os outros,
   visualizações e pessoas do mês + convite ao Destaque. App 1.5.0: código anônimo (`X-Visitante`) e `?origem=` em cada
   lista; recarga e tela de avaliar mandam `origem=nenhuma`. Privacidade atualizada. Enquanto a 017 não é aplicada, a API
   conta do jeito antigo e as páginas de números/painel ficam escondidas (nada quebra).
   Mudança 017 já aplicada (conferido em 10/11). Falta: gerar/publicar o APK 1.5.0 + versão na Play
21. ✅ Versão web com páginas de cidade, área, tipo e ficha (10/11, decisões do usuário: nome completo da cidade no
   endereço, fotos do Básico no site, avaliações do app na ficha). **No ar** em https://achafacilapp.com.br:
   `/<cidade>/` (ex.: /sao-joao-da-boa-vista/; o slug curto /sao-joao/ redireciona), `/<cidade>/<area>/`,
   `/<cidade>/<area>/<tipo>/` e a ficha `/<cidade>/c/<nome>-<id>` (nome trocado redireciona para o endereço certo);
   busca `/<cidade>/buscar?q=` (não indexada). Código: servidor/inc/web.php (páginas, dados estruturados LocalBusiness,
   BreadcrumbList e ItemList, sitemap) e servidor/publico/web.php (rotas); .htaccess manda para web.php o que não é
   arquivo nem pasta, só no host achafacilapp.com.br (regra também em servidor/dominio/htaccess-redirecionar).
   Listas: 30 por página (?pagina=N), mesma ordem do app (Destaque primeiro, sorteio do dia), filtro de bairro (não
   indexado), tipos com 3+ comércios como atalhos; tipo com menos de 3 = noindex. Ficha: logo, fotos, botões WhatsApp/
   ligar/mapa/Instagram/site (toques contam no passo 20 com origem "web" via api/contato.php), horário com "Aberto agora",
   avaliações do app, "Este comércio é seu?" (só quem ainda não pagou plano) e "Outras opções de ...". A visita conta
   como origem "web" (robôs não contam). /sitemap.xml (índice + sitemap-paginas.xml + sitemap-<cidade>.xml, guardado 6 h),
   /robots.txt. Cache das listas em /var/lib/acha-facil/cache/web (1 h; apagado ao aprovar alteração e ao ligar/desligar
   cidade). Inicial com "Escolha a sua cidade", menu "Cidades" e cidades no rodapé. Termos e privacidade atualizados.
   Falta (usuário): **Google Search Console** — propriedade de domínio achafacilapp.com.br (registro TXT no Registro.br),
   enviar https://achafacilapp.com.br/sitemap.xml e testar uma ficha no "Teste de pesquisa aprimorada" do Google;
   acompanhar o log de acesso na 1ª semana (robôs) e, depois de 2 a 8 semanas, as buscas que trazem gente
   Search Console FEITO pelo usuário (10/11): sitemap processado, 15.924 páginas; com Santa Rita (11/11) são 18.025.
   Agora é só acompanhar: Páginas (indexação) na 1ª semana e Desempenho (buscas) depois de 2 a 8 semanas
22. ⬜ Prospecção Saúde e Veículos: 2 semanas de dados do passo 20 → CSV → grupos A/B/C → WhatsApp manual
   (15–20/dia) com cupom por nicho → metas de 30 dias
23. ✅ "Recomendados" só com plano Básico e Destaque (10/11, pedido do usuário; feito em 19/11): a faixa "Recomendados em
   <cidade>" da tela inicial do app mostra **só quem pagou o Básico ou assina o Destaque** (inclui Destaque + Site),
   sorteados uma vez por dia (recomendados() e SQL_RECOMENDADO em inc/guia.php, chamada em api/inicio.php; a chave do
   JSON continua `populares` para os apps já instalados). Sem nenhum pagante na cidade, a faixa não aparece.
   A ideia de "só perto" (localização do celular) ficou de fora, por isso **não precisa de APK novo**.
   Antes: sorteio entre os 80 mais procurados, com ou sem plano (populares(), que saiu). O site continua com
   "Conhecidos na cidade" (web_pagina_cidade em inc/web.php), sem mudança. Testado em 19/11: São João 2 (Destaque), Poços 1 (Básico)
24. ✅ Poços de Caldas atualizada (10/11, pedido do usuário): lista nova com 8.449 linhas → 8.375 comércios no app
   (6.701 novos, 1.353 atualizados; os antigos que não vieram na lista continuam). 390 lugares públicos fora.
   Importador: `--teste` (faz tudo e desfaz, mostra áreas e o que cai em "Outros"); filiais com o mesmo nome em outro
   endereço entram separadas (chave "nome @ endereço"; a 1ª continua com a chave antiga); "Sem categoria" = sem tipo;
   nome sem letra ou igual ao de uma cidade fica fora; lista sem número de avaliações não zera a ordem.
   Localização dos novos pelo OpenStreetMap (~2h30). Lista anterior em
   /var/lib/acha-facil/leads/pocos-de-caldas.antes-20261110.html; backup /root/acha-facil-profissionais.antes-pocos2.*
25. ✅ Domínio próprio achafacilapp.com.br (10/11, pedido do usuário): o site passa a abrir em https://achafacilapp.com.br
   (mesma pasta servidor/publico; na raiz, sem /acha-facil — inc/base.php zera `url` quando o endereço é o domínio novo).
   fsadesigners.com.br/acha-facil/... redireciona (301) para o domínio novo, **menos** `api/` (APKs instalados) e
   `mp-webhook.php` (cadastrado no Mercado Pago, POST). Arquivos em servidor/dominio/; ligar com `bash servidor/dominio/ativar.sh`
   (confere o DNS, instala o site no Apache, certbot, testa e só então cria publico/.htaccess).
   Ligado em 10/11: DNS A no Registro.br → 177.153.68.212, certificado Let's Encrypt (renova sozinho, vence 08/02/2027).
   Tudo trocado para o domínio novo (10/11, pedido do usuário): `endereco_publico` em /etc/acha-facil/config.php (backup
   /root/acha-facil-config.php.antes-dominio), links do app em src/api/cliente.ts (vale a partir do próximo APK), scripts,
   textos e docs. Exceção: retorno do Google = `google_retorno()` em inc/google.php, que segue no endereço antigo (cadastrado
   no Google; ele redireciona). Falta: cadastrar https://achafacilapp.com.br/google/retorno.php no Google Cloud e pôr
   `'google_retorno' => 'https://achafacilapp.com.br/google/retorno.php',` no config.
26. ✅ Plano **Destaque + Site** e Básico a R$ 9,90 (10/11, pedido do usuário): 3º plano, chave `site`, R$ 89,90/mês
   (assinatura como o Destaque): tudo do Destaque + landing page própria feita pela FSA + domínio incluso, para o site virar
   receita recorrente da FSA. Preços em PLANOS_PRECO (inc/guia.php); SQL_DESTAQUE conta `destaque` e `site`. Site: card em
   /planos.php, na inicial, no relatório e nos termos; perfil.php pede o endereço desejado do site; aviso de pagamento aos
   admins diz "Fazer o site e registrar o domínio"; cupons valem para ele. App: 3º cartão em src/app/planos.tsx (sem preço).
   preco_pedido() agora usa o `valor` guardado no pedido: pedido antigo do Básico a 5,99 ainda sem pagar continua valendo.
   O plano novo só aparece depois da **mudança 018** (`bash servidor/instalar.sh`, usuário); o Básico a 9,90 vale na hora.
   Falta: aplicar a 018; APK 1.5.0 (já pendente do passo 20) leva o cartão novo; depois que alguém assinar, a FSA faz o site
   e põe o endereço no campo Site da ficha
27. ✅ Busca do site pede a cidade primeiro (10/11, pedido do usuário): em /busca.php e no "Procure seu negócio" da
   inicial, escolher a cidade (lista das ativas por estado, opcoes_cidades() em inc/guia.php) antes do nome; a busca só
   traz comércios daquela cidade, para não aparecerem vários com o mesmo nome. Sem cidade, avisa e não busca
28. ⬜ Agente de IA que confere os dados e publica sozinho (10/11, pedido do usuário; só plano, sem código): depois do
   pagamento aprovado, o agente compara os dados do dono com Google Maps, OpenStreetMap, CNPJ e a lista de leads; se tudo
   confere, publica pelo mesmo caminho da moderação; com dúvida, avisa os admins (push + e-mail) dizendo qual é a dúvida.
   Começa em **modo sombra** (só sugere, admin aprova como hoje). Custo estimado ~R$ 0,85 por verificação (Opus 5.5 com
   fotos). Plano completo, custos e decisões pendentes em `docs/plano-agente-ia.md`
29. ✅ Notificações para os usuários (11/11, pedido do usuário): os administradores mandam uma notificação no celular de
   quem tem o app, **pelo site** (/admin/notificacoes.php, menu "Notificações") e **pelo app** (Administração → "Enviar
   notificação aos usuários", tela src/app/notificar.tsx). Escolhe: título (60) e texto (178), cidade (ou todas), quem
   recebe (todos os aparelhos ou só quem entrou com o Google) e, se quiser, a ficha que o toque abre (número ou link da
   ficha no site). Confirma com o número de aparelhos antes de mandar; a mesma notificação não sai 2x em 10 minutos;
   histórico com quantos receberam; cada envio vai para o Registro.
   Como funciona: desde o app **1.6.0** todo aparelho pede a permissão ao escolher a cidade e entrega o endereço de aviso
   (Expo Push) com a cidade escolhida (api/avisos.php, agora sem precisar de conta); fica em `avisos_publico` (mudança
   **019**, com `notificacoes` = histórico). Canal Android "Novidades" (o de admin continua "Administração"). Código em
   servidor/inc/notificacoes.php; envio de 100 em 100 por expo_enviar() em inc/avisos.php (app desinstalado sai da lista
   sozinho); api/notificar.php para o app. Privacidade atualizada. Testado no banco de teste (11/11).
   Falta (usuário): aplicar a 019 (`bash servidor/instalar.sh`) e gerar/publicar o APK 1.6.0 (leva também o 1.5.0 pendente).
   APKs antigos não recebem: só quem atualizar para a 1.6.0 entra na lista
30. ✅ Santa Rita do Sapucaí - MG (11/11, pedido do usuário): 6ª cidade, slug `santa-rita-do-sapucai` (já estava cadastrada e
   ligada em /admin/cidades.php, sem comércios). Lista do robô em /var/lib/acha-facil/leads/santa-rita-do-sapucai.html
   (1.943 linhas) → **1.919 comércios** importados, 24 lugares públicos pulados; todos com localização (link do Google).
   PALAVRAS_AREA ganhou tipos da lista (bistrô, delicatessen, móveis, teatro...): só 36 em "Outros". Sem APK novo (cidades
   vêm do banco). Backup da tabela antes: /root/acha-facil-profissionais.antes-santa-rita.20261111-0814.sql
31. ✅ Importar a lista do robô pelo site, sem passar pelo Claude (11/11, pedido do usuário): /admin/importar.php (menu
   "Importar lista"). Envia o `leads_<cidade>.html` (um ou vários da mesma cidade); a cidade sai do cabeçalho do arquivo
   ("Cidade - UF ·") ou é escolhida na lista. 1º passo **só confere** (importar-leads.php --teste: novos, atualizados,
   pulados, por área, "Em Outros"); 2º "Importar de verdade": backup dos comércios da cidade (mysqldump em
   /var/lib/acha-facil/backups/profissionais-<slug>-<data>.sql), lista anterior vira leads/<slug>.antes-<data>.html, importa,
   limpa o cache do site, registra e começa o geocodificar.php em segundo plano (log em /var/lib/acha-facil/geocodificar.log,
   acompanhado na própria página). Limite de envio 30 MB (publico/admin/.htaccess). Importador aceita o formato novo do robô
   (data-ativo="ativo|confirmar|inativo"): fechado no Google não entra, e quem fechou de vez e era importado sem dono sai do
   app (pausado). instalar.sh --leads usa leads/sao-joao.html quando existe. Testado numa cópia com o banco de teste (lista
   de São João de 11/11: 1.716 novos, 1.915 atualizados, 48 fechados). Cidade nova: cadastrar desligada em Cidades → importar
   → ligar
32. ✅ Agenda de eventos por cidade (12/11, pedido do usuário): no site, aba **📅 Eventos** ao lado de "🏪 Comércios" no
   topo da página da cidade e linha "Agenda de eventos" com todas as cidades no rodapé. `/<cidade>/eventos/` mostra os
   eventos que ainda não terminaram em ordem de data, agrupados por mês ("Acontecendo agora" no topo), cada um num cartão
   com a folhinha do calendário (dia da semana, dia, mês), nome, data/horário, local, entrada e miniatura do cartaz.
   Tocar abre `/<cidade>/eventos/<nome>-<id>`: cartaz, quando, onde, entrada, descrição e os botões **Página oficial do
   evento**, Salvar na minha agenda (Google Agenda) e Como chegar; dados estruturados Event para o Google; evento que já
   passou continua abrindo com o aviso "já aconteceu" (noindex). Eventos entram no sitemap da cidade.
   Cadastro em **/admin/eventos.php** (menu "Eventos"): cidade, nome, data e hora (início/fim, fim opcional), local,
   endereço, entrada, link oficial, descrição, cartaz (reduzido como as fotos), mostrar/esconder, editar e apagar; salvar
   limpa o cache do site. Código em inc/eventos.php, tabela `eventos` (**mudança 020**). Testado numa cópia com o banco de
   teste (12/11). Falta (usuário): aplicar a 020 (`bash servidor/instalar.sh`) — até lá a aba e o rodapé não aparecem
   e /eventos/ dá 404. Ideia para depois: mostrar os eventos também no app
33. ✅ Eventos no app (12/11, pedido do usuário): aba **Eventos** na barra de baixo (entre Categorias e Favoritos; com 5
   abas os nomes ficaram em 13 px e encolhem se não couberem). `src/app/(abas)/eventos.tsx`: agenda da cidade escolhida
   em ordem de data, separada por mês ("Acontecendo agora" primeiro), cartões com a folhinha (src/componentes/Folhinha.tsx),
   nome, data/horário, local, entrada e miniatura do cartaz; puxar para baixo atualiza. Tocar abre `src/app/evento/[id].tsx`:
   cartaz, folhinha, botões **Página oficial do evento**, Salvar na minha agenda (Google Agenda), Como chegar e
   Compartilhar (link da página do evento no site), quadro Quando/Onde/Entrada e a descrição. Dados de
   `api/eventos.php` (mesma tabela do passo 32; datas já escritas em português pelo servidor). Eventos são cadastrados
   só no /admin/eventos.php do site. Versão **1.7.0** (app.json). Testado: endereço do app no banco de teste e sintaxe
   das telas (transpile do TypeScript); telas ainda não abertas no emulador. Falta (usuário): aplicar a 020, testar no
   emulador e gerar/publicar o APK 1.7.0
34. 🟡 Aviso de atualização para quem instalou pela Play Store (12/11, problema relatado pelo usuário: "não está sendo
   avisado nem levado para a Play Store"). Causa: src/versao.ts saía logo no começo quando o app vinha da Play
   (extra.loja = 'play'), e o servidor só conhecia a versão do APK do site (versao.txt = 1.1.0). Agora: /admin/app.php
   (menu "App") guarda a **versão disponível na Play Store** (dados_dir/apk/versao-play.txt, informar depois da
   aprovação da loja) e mostra a do APK do site; api/versao.php devolve também `play: {versao, link, pacote}`; o app da
   Play compara e mostra "Nova versão do Acha Fácil" com **Depois / Atualizar**, que abre market://details?id=
   br.com.fsadesigner.achafacil (sem a loja, a página no navegador). O APK do site continua igual (botão Baixar).
   Limite: só apps a partir da **1.7.0** sabem avisar pela Play (as versões antigas já instaladas não têm esse código).
   Falta (usuário): APK/AAB 1.7.0 e, quando ela estiver disponível na Play, preencher 1.7.0 em /admin/app.php
   **Automático (12/11, pedido do usuário "não dá para ser automático?"):** o app da Play pergunta direto à Play Store
   (atualização no app do Google, biblioteca **expo-in-app-updates 0.13**, compatível com o SDK 58) e só avisa quando a
   versão nova já está liberada para aquele celular; "Atualizar" abre a tela de atualização da própria Play (tela
   cheia), e se falhar abre a página do app na loja. O módulo é carregado só na versão da Play (import dinâmico em
   src/versao.ts), então o modo de desenvolvimento não precisa dele. O campo de /admin/app.php virou reserva (só quando a
   Play não responde). Falta (usuário, no PC): `npx expo install expo-in-app-updates` e enviar package.json +
   package-lock.json; teste real só com o app instalado pela Play (vale o teste interno/fechado)
35. ✅ Página inicial e rodapé mais enxutos (12/11, pedido do usuário: "muito grande com todos esses nomes de cidades").
   Topo: "Em 11 cidades de São Paulo e Minas Gerais" com link para #cidades (resumo_cidades() em inc/guia.php; a lista
   completa texto_cidades() ficou só nos termos). "Escolha a sua cidade" separada por estado (São Paulo 8 cidades, Minas
   Gerais 3). Rodapé: "Atendemos 11 cidades de SP e MG · Ver as cidades" no lugar das 11 cidades e da linha da agenda de
   eventos; última linha "Acha Fácil · FSA Designer · ano". Eventos ganharam a seção **Próximos eventos na região** na
   inicial (6 próximos de todas as cidades, eventos_proximos_todas(), e um seletor "Agenda completa da cidade" por
   estado); sem eventos, mostra o aviso "Nenhum evento marcado por enquanto" (antes sumia e o usuário não achou a seção). A aba Comércios | Eventos da página da cidade continua
36. 🟡 Divulgar um evento pelo público, com autorização dos admins (12/11, pedido do usuário: "uma página igual ao
   incluir.php" e "os administradores recebem a solicitação no site e notificação via app para autorizar").
   **/incluir-evento.php** (mesmo modelo do incluir.php): cidade (vem marcada com ?cidade=), nome, data/hora de início e
   fim, local, endereço, entrada, link oficial, descrição, cartaz (até 8 MB; <Files> no .htaccess sobe o limite) e
   nome/telefone de quem envia + recado; campo escondido contra robô, 5 envios por IP por dia, recusa data passada e
   repetido. Entra com visivel = 0 e status 'pendente' (**mudança 021**: status, enviado_nome/contato/recado/ip) e
   avisa os admins no celular ("Evento para autorizar", abre a Administração do app). Site: /admin/eventos.php mostra
   "Esperando autorização" com tudo, quem enviou (link do WhatsApp), Autorizar e publicar / Corrigir antes / Recusar;
   contador no menu (Mais, Eventos e Pendências) e aviso em Pendências. App: seção **Eventos para autorizar** na
   Administração (cartaz, dados, quem enviou, Autorizar / Falar no WhatsApp / Recusar), api/admin.php
   (autorizar_evento / recusar_evento; dois admins ao mesmo tempo: o segundo recebe "já foi decidido"). O aviso com
   e-mail e Instagram saiu da inicial e da agenda: no lugar, "Vai ter um evento? Divulgar um evento". Testado no
   banco de teste (12/11). App **1.7.1**. Falta (usuário): aplicar a 021 e gerar o app novo
37. ✅ Gerar o app no PC, sem a cota do EAS (12/11: a cota grátis de builds Android acabou, volta em 01/12; usuário
   escolheu gerar no PC). `scripts/gerar-local.ps1 -Tipo play|apk` (prebuild + Gradle do Android Studio, saída em
   saida\), assinado com a chave do EAS pelo plugin `plugins/assinatura-local.js` (credentials.json baixado com
   `npx eas credentials`, fora do git); versionCode = último do EAS + 1 (`ACHAFACIL_VERSION_CODE` no app.config.js).
   Passo a passo em docs/build-local.md. Plugin testado na VPS com o build.gradle do modelo do Expo 58; o script
   PowerShell só roda no PC (não testado aqui). Falta (usuário): baixar a chave e gerar a 1.7.1
38. ✅ De onde vêm as pessoas do site (14/11, pedido do usuário: "as pesquisas que vêm pelo Google aparecem em
   numeros.php?"). A origem "Site" foi separada em Google, outro buscador, redes sociais e WhatsApp, link de outro
   site e endereço digitado (origem_do_referer() em inc/numeros.php, pelo Referer; cookie af_origem de 30 min, sem dado
   pessoal, guarda a origem para a ficha e os toques em WhatsApp/ligar). **Entradas no site** (mudança 022, tabela
   `entradas`): conta quem chega de fora por qualquer página (inicial, cidade, área, ficha, eventos), sem recarga nem
   navegação interna, só páginas 200 e sem robôs (registrar_entrada_site()). /admin/numeros.php ganhou "De onde vêm
   as pessoas": visualizações por origem, entradas por origem e por página, páginas por onde mais entram pelo Google.
   Os termos pesquisados no Google não chegam ao site: ficam no Search Console (Desempenho → Consultas). Testado no
   banco de teste (14/11). A origem nas fichas já vale no site; falta (usuário): aplicar a 022 para as entradas
39. ✅ Detalhe por origem do site (14/11, pedido do usuário: "ao clicar em entradas do Google ver para onde a pessoa
   foi"). /admin/entradas.php?origem=google (ou outra origem do site, com cidade e período): 1) todas as páginas onde
   entraram (precisa da 022), 2) fichas abertas por quem veio dessa origem, 3) toques em WhatsApp/Ligar/Mapa/Site/
   Instagram por comércio. Links nas origens e no "pelo Google" de /admin/numeros.php. Só totais por dia (não segue
   pessoa). Sem mudança no banco. Testado no banco de teste (14/11)
40. ✅ Pessoas cadastradas e contador de downloads do APK (14/11, pedido do usuário). /admin/pessoas.php (menu
   "Pessoas"): todas as contas do app com foto do Google, nome, e-mail, cadastro, último uso do app (sessoes_app),
   favoritos e avaliações; busca por nome/e-mail, ordem por cadastro ou uso, CSV. /admin/app.php (menu "App e
   downloads"): total desde o início (downloads.txt, 12 em 14/11), hoje, começaram x terminaram no período, gráfico por
   dia e tabela por versão. Histórico novo em dados_dir/apk/downloads.log (data;versão;inicio|fim, sem IP), só desde
   14/11; robôs não contam mais (e_robo()). Sem mudança no banco. Testado com APK falso no banco de teste (14/11)
41. ✅ O que cada pessoa salvou e avaliou (14/11, pedido do usuário: "link no número... só para controle e
   estatística"). Em /admin/pessoas.php o nome e os números de Favoritos/Avaliações abrem /admin/pessoa.php?id=
   (favoritos com data e área; avaliações com estrelas, comentário, resposta do comércio, oculta/editada; botões
   Números e Abrir). Pessoas ganhou "Estatística dos itens": mais salvos, favoritos por área, mais avaliados com
   média, e totais de favoritos/avaliações no topo. Foto do Google que não carrega vira a inicial. Só leitura
42. ✅ Data e hora das entradas (14/11, pedido do usuário: "nessa tabela consegue colocar a data/hora?"). Coluna
   **Quando** em "Páginas onde entraram" (/admin/entradas.php): até 5 horários por página (dd/mm HH:MM) e "e mais N";
   ordem "mais recentes" (padrão) ou "mais entradas". **Mudança 023**: tabela entradas_hora, uma linha por entrada,
   gravada junto com a 022 em registrar_entrada_site(). Entradas de antes da 023 mostram só o dia ("sem hora").
   Testado no banco de teste (14/11). 023 aplicada; marcado como concluído pelo usuário em 15/11
43. ✅ Relatório de cidades mais procuradas (14/11, pedido do usuário). Em /admin/entradas.php, acima da tabela de
   páginas: ranking de cidades (entradas, %, comércios diferentes, 3 áreas mais procuradas, visualizações e toques),
   áreas mais procuradas, 20 comércios mais procurados, entradas por dia e por hora; botão Imprimir. Vale para
   qualquer origem/cidade/período. Entradas de 11/11 a 14/11 trazidas do registro do Apache por
   servidor/importar-logs-entradas.php (roda uma vez, marca 'logs-entradas-antes-022' em migracoes; --teste só
   conta): 332 do Google (Poços 206, Mococa 83, Aguaí 42). Backup das tabelas em /var/lib/acha-facil/backups
44. ✅ Entrar no site com o Google e fazer o mesmo que no app (14/11, pedido do usuário). Mesma conta do app:
   /entrar.php?volta= cria a entrada em logins_google com volta "web:/página" e cookie af_estado (google/retorno.php
   confere que é o mesmo navegador), depois o mesmo caminho do Google do app (mesmo endereço de retorno cadastrado).
   Na volta: sessão em sessoes_app guardada no cookie httponly af_conta (180 dias) + af_logado=1 para o JS
   (inc/conta-web.php). api/ aceita o cookie só com o cabeçalho X-AF-Web: 1 (usuario_app() em inc/api.php), então o
   site usa os mesmos endereços do app (favoritos, avaliar, eu). Ficha: botão ♡ Salvar e "Avaliar este comércio"
   (estrelas + comentário, mudar/apagar); sem conta mostra "Entrar com o Google". Topo de todas as páginas: "Entrar"
   ou a foto da pessoa (descoberto por JS, as páginas em cache continuam iguais para todos). /minha-conta.php: salvos
   (tirar), minhas avaliações (mudar/apagar), sair (POST com CSRF) e excluir conta. robots.txt e privacidade.php
   atualizados. Sem mudança no banco. Testado no banco de teste (14/11); o vaivém real com o Google só no navegador
45. ✅ Painel do admin pela conta Google do site (14/11, pedido do usuário). Quem entrou no site com o e-mail de um
   admin (tabela admins) abre /admin sem a senha: admin_atual() em inc/moderacao.php liga a sessão pela conta do site
   (registro: "entrou pela conta Google do site"). Topo do site mostra "Administração" (JS, j.usuario.admin), Minha
   conta mostra "Painel da administração", e /admin/entrar.php ganhou "Entrar com o Google (conta de admin)".
   Sair da administração sai também da conta do site. E-mail e senha continuam valendo
46. 🟡 (19/11: a, b e c feitos pelo usuário; projeto do Google Cloud "Acha Facil" é só do Acha Fácil; branding preenchido e
   enviado para verificação da marca; logo 120x120 em publico/assets/logo-google-120.png; google_retorno no config e o
   Google já recebe https://achafacilapp.com.br/google/retorno.php (1º teste deu redirect_uri_mismatch: o Google demora uns minutos
   para valer o URI novo; depois funcionou, conferido pelo usuário em 19/11). Login no app testado pelo usuário (19/11). Falta: esperar o
   Google aprovar a marca, quando aparece "Acha Fácil!" com a logo. Faturamento NÃO precisa.) **Para amanhã (15/11):** login do Google com o nome **Acha Fácil!** em vez de **FSA Designer** (pedido do usuário).
   Hoje a tela do Google mostra "FSA Designer" e volta pelo endereço antigo (fsadesigners.com.br). A fazer:
   a) Google Cloud → APIs e serviços → Tela de consentimento OAuth (Branding): nome do app "Acha Fácil!", logo do
      Acha Fácil, e-mail de suporte, página inicial https://achafacilapp.com.br/, privacidade
      https://achafacilapp.com.br/privacidade.php, termos https://achafacilapp.com.br/termos.php e domínio autorizado
      achafacilapp.com.br. Se o projeto do Google Cloud for compartilhado com outros sistemas da FSA, criar um projeto
      só do Acha Fácil (novo client_id/secret no /etc/acha-facil/config.php).
   b) Credenciais → cliente OAuth → URIs de redirecionamento: adicionar https://achafacilapp.com.br/google/retorno.php
      (manter o antigo enquanto houver APK antigo usando).
   c) No /etc/acha-facil/config.php: 'google_retorno' => 'https://achafacilapp.com.br/google/retorno.php' (ver passo 25).
   d) Testar a entrada no site e no app (o app usa o mesmo retorno). Mudar o nome/logo pode pedir nova verificação do Google.
47. ✅ Anúncios do Google AdSense no site (15/11, pedido do usuário). Código ca-pub-6554734640437999 (ADSENSE_PADRAO em
   inc/pagina.php; 'adsense_cliente' no config troca). Script só onde pagina_inicio() recebe 'anuncios' => true:
   inicial, cidade, listas por área/tipo, busca da cidade, agenda e páginas de eventos, e fichas de quem NÃO paga
   (importado e sem basico_pago_em e sem destaque_pago). Sem anúncio: fichas pagas, planos, pagamento, incluir, entrar,
   minha conta, painel, admin, termos, privacidade. publico/ads.txt criado. Liga/desliga em /admin/anuncios.php
   (arquivo anuncios-desligados no dados_dir + limpa o cache das páginas). Privacidade atualizada (cookies do Google,
   o app não tem anúncios). Testado no ar: ficha Destaque paga e Básico pago sem anúncio; ficha sem plano com anúncio
48. ⬜ AdSense: o usuário marca "Adicionei o código" → Verificar → Pedir revisão e espera a aprovação (dias a ~2 semanas).
   Depois de aprovado: em Anúncios → Por site, ligar anúncios automáticos e DESLIGAR a vinheta (tela cheia) e os
   anúncios "âncora" se cobrirem o botão de WhatsApp. Se recusar por "conteúdo de baixo valor": melhorar texto das
   páginas de cidade/área e pedir de novo
49. ⬜ Depois de 1 mês de anúncios: ver o ganho no AdSense e decidir sobre o app (AdMob, banner só nas listas;
   precisa de react-native-google-mobile-ads, APK novo e "contém anúncios" na Play Console). Opcional: nos planos,
   dizer "com o plano, sua página fica sem propaganda"
50. ✅ Pedidos de donos mais claros + aviso de "aceito" (15/11, pedido do usuário: pedido 15, Arte Tintas, chegou como
   "plano Básico" mas parecia comércio novo e não estava pago). O aviso de plano agora diz "Plano escolhido (ainda sem
   pagamento)" e que o comércio já está no app; /admin e /admin/pedido.php mostram "não pago" e um alerta antes de
   liberar. Ao publicar um "Incluir negócio" ou liberar um painel, a pessoa recebe push (canal novidades, abre a ficha)
   se fez o pedido logada com o Google no site (pedidos.usuario_id, **mudança 024 — usuário aplica**), e o pedido
   mostra o botão "Mandar pelo WhatsApp" com a mensagem de aceito + link da ficha e dos planos. 024 aplicada 15/11.
51. ✅ Login com o Google obrigatório nos pedidos de donos (15/11, pedido do usuário). perfil.php?acao=assumir (com ou
   sem plano) e incluir.php mostram "Entrar com o Google" (exigir_login_web() em inc/conta-web.php) e voltam para a
   mesma página; o nome vem preenchido da conta. "Tirar do app" continua sem login.
52. ✅ Comércio com dono + login para tirar do app (15/11, pedido do usuário). tem_dono() / SQL_TEM_DONO em inc/guia.php:
   Básico pago, Destaque em dia ou painel liberado (acessos_dono ativo). Com dono, some "Este comércio é seu?" no site
   (web.php) e no app (linkAssumir/linkRemover = null, cadastrado = true; sem APK novo) e perfil.php mostra "Este
   comércio já tem responsável" (menos para o próprio dono: painel aberto ou pedido feito pela mesma conta Google).
   "Tirar do app" exige login Google; o admin recebe o aviso "Pedido para tirar do app". Cache web limpo ao liberar
   painel, pagar o Básico e cortar acesso. Corrigido também pedido_pago() repetido (avisos.php x mercadopago.php).

53. ✅ Suporte / Fale com a equipe (15/11, pedido do usuário: mensagens para os admins como chamados, só com login).
   /suporte.php (exige conta Google; ?id= = comércio, ?c= = chamado) e /admin/suporte.php (menu "Suporte" com contador
   dos que esperam resposta). inc/suporte.php; tabelas chamados e chamados_mensagens (mudança 025 aplicada 15/11).
   Admins recebem push a cada mensagem; a pessoa recebe push quando a equipe responde (avisar_usuario). Ficha: o quadro
   "Informação errada?" leva ao suporte (com e sem dono); botão também em Minha conta. Testado no banco de teste.
   Falta (opcional): tela de suporte dentro do app (hoje o push só abre o app; a conversa fica no site).
54. ✅ Avisos de plano: só pago + "Cadastro sem pagamento" + notificação para uma pessoa (15/11, pedido do usuário).
   Escolher plano não avisa mais na hora; pago = "Novo plano pago" (como antes). avisar-sem-pagamento.php (cron
   /etc/cron.d/acha-facil a cada 10 min, criado pelo instalar.sh) avisa uma vez "Cadastro sem pagamento" quando o
   plano não é pago em 30 min (coluna pedidos.aviso_sem_pagamento_em, mudança 026 aplicada 15/11). /admin/pedido.php
   tem "Lembrar do pagamento" (WhatsApp com o link da assinatura) e /admin/pessoa.php tem "Mandar notificação para esta
   pessoa" (avisar_usuario, só os celulares dela). Testado no banco de teste. instalar.sh rodado (cron ligado).
55. ✅ Entradas com e sem pré-carregamento do Chrome (15/11, pedido do usuário). e_pre_carregamento() em inc/numeros.php
   (cabeçalho Sec-Purpose/Purpose prefetch, ou IP 162.120./193.186./72.14. com nome *.googlezip.net); coluna
   entradas_hora.pre (**mudança 027 — usuário aplica**). /admin/entradas.php ganhou a caixa "entradas sem
   pré-carregamento" e a explicação; /admin/numeros.php, "pelo Google sem pré-carregamento". marcar-pre-carregamento.php
   (rodado pelo instalar.sh) marca as entradas antigas pelo registro do Apache. Testado no banco de teste com os dados
   de produção: hoje 61 de 77 entradas do Google foram pré-carregamento. Falta: rodar o instalar.sh.
56. ✅ Robô automático + Novidades (15/11, pedido do usuário: "sistema que atualize as listagens para ver se tem algo
   novo", opção A = robô no PC). Servidor: inc/novidades.php, /admin/novidades.php (menu "Novidades" com contador) e
   /admin/robo-enviar.php (recebe a lista do PC com a chave do robô no cabeçalho X-Chave-Robo; sem login). Cada lista é
   conferida em modo TESTE (importar-leads.php --detalhes=) e mostra os comércios **novos**, os que **mudaram**
   (nome/telefone/WhatsApp/endereço/bairro/Instagram/site, sem contar maiúsculas e acentos) e os que **fecharam**;
   "Importar para o app" ou "Descartar"; lista nova da mesma cidade descarta a anterior que esperava. Modo automático
   (liga/desliga na página) importa sozinho. Push para os admins quando há novidade. "Importar lista" agora manda para
   Novidades. Tudo em arquivos ($DADOS/novidades), sem mudança no banco. PC: scripts/robo/robo-rodar.ps1 (rodízio de
   cidades, -Cidade, -Todas, -Arquivo, -Testar; registro em robo-log.txt) e robo-agendar.ps1 (Agendador de Tarefas,
   todo dia 03:00). Guia em docs/robo-automatico.md. Testado numa cópia com o banco de teste (envio por HTTP, chave
   errada, aprovar, automático). Parte do PC adiada (15/11, pedido do usuário): falta achar a pasta do robô no PC
   (D:\leads-sem-site não existe) e o comando dele para o robo-config.json; depois -Testar, -Cidade e robo-agendar.ps1.
57. ✅ Sitemap dividido por cidade e por tipo (16/11, pedido do usuário: ver no Search Console qual grupo indexa melhor).
   /sitemap.xml continua sendo o índice, agora com um arquivo por cidade × tipo: sitemap-<cidade>-cidades.xml (página
   da cidade), -categorias.xml (áreas e tipos com 3+), -comercios.xml (fichas) e -eventos.xml (agenda). Índices extras:
   sitemap-cidades.xml / sitemap-categorias.xml / sitemap-comercios.xml / sitemap-eventos.xml (um tipo em todas as
   cidades) e sitemap-<cidade>.xml (os tipos de uma cidade; endereço antigo continua valendo). Cache 6 h. No ar e
   conferido (Poços: 8.375 fichas e 489 áreas/tipos). Falta (usuário): enviar os 4 índices de tipo no Search Console.
58. ✅ Busca de verdade no topo da inicial do site (16/11, pedido do usuário). No lugar do celular desenhado: [Cidade ▾]
   [O que você procura?] [🔍 Buscar] → /<cidade>/buscar?q= (sem texto → página da cidade; sem JavaScript passa por
   /?cidade=&q=, que redireciona). Última cidade guardada no navegador (localStorage af_cidade, também ao tocar numa
   cidade da lista); cidade com 0 comércios não entra na lista. O celular desenhado e os botões de baixar foram para o
   bloco "Baixe o Acha Fácil!" do fim (#baixar). CSS site.css?v=27. Backup para voltar: /root/acha-facil-inicio.antes-busca.20261116-0722
   (index.php, site.css, pagina.php). Campo da cidade com a largura do maior nome cadastrado (--cidade-largura, máx. 34ch;
   cresce sozinho com cidade nova); abaixo de 860 px os campos ficam um embaixo do outro. CSS v=28.
   Falta: o usuário olhar no celular e no PC e aprovar.
59. 🟡 Ficha incompleta que "vende" o plano (16/11, pedido do usuário; itens 1, 2 e 3 da avaliação). A ficha sem dados
   continua sem dados (o dono paga para completar), mas agora mostra o que falta:
   (1) Botões "Quero o WhatsApp", "Quero ver fotos", "Quero saber mais" e "Quero saber o horário" (api/quero.php,
   pedir_info() em inc/numeros.php; 1 por pessoa, ficha e tipo no dia; robô não conta). Mudança 028 (pedidos_info e
   pedidos_info_vistos) — USUÁRIO APLICA; antes dela os botões aparecem mas nada é guardado. Os pedidos aparecem em
   /admin/numeros.php (seção própria "Pedidos de informação", só as fichas com pedido, com contato do comércio,
   botão Relatório e CSV próprio; desde 16/11 a tabela "Fichas" mostra só as fichas com algum número no período),
   /admin/numeros-ficha.php,
   no relatório para o comerciante ("3 pessoas pediram o seu horário") e no painel do dono.
   (2) Espaços trancados (🔒, borda tracejada) no lugar de "Horário não informado" e onde faltam WhatsApp, fotos e
   "O que faz ou vende", com "É o dono? Complete a sua ficha" só em ficha sem dono. Site: web_trancado() em inc/web.php;
   app: src/componentes/Trancado.tsx (lembra quem já pediu no celular).
   (3) Ficha paga logo depois do Destaque nas listas e buscas (SQL_FICHA_PAGA em inc/guia.php: importado = 0 ou
   Básico pago), com o selo "✓ Ficha completa" (site: .selo-completa; app: Etiqueta no CartaoProfissional).
   Testado numa cópia com o banco de teste (contagem, repetido, robô, sem a 028, admin, relatório, CSV, ordem e selo).
   App 1.8.0: falta gerar e testar no emulador.
60. ✅ Fichas sem contato fora do Google (16/11, pedido do usuário). Ficha sem telefone e sem WhatsApp continua no site e
   no app, mas sai com `noindex` e fica fora do sitemap (SQL_TEM_CONTATO / ficha_no_google() em inc/guia.php). Volta
   sozinha quando ganhar telefone ou WhatsApp (dono aprovado, robô ou admin): a regra é conferida a cada página, sem
   marcação guardada; lastmod do sitemap = atualizado_em. ~6.700 de 44.866 fichas saem. Conferido no ar (Mococa:
   2.193 de 2.610 no sitemap; ficha sem contato com noindex, com contato sem).
61. ✅ Avaliação do site 16/11, correções (pedido do usuário):
   - Convite "É o dono? Complete a sua ficha" uma vez só, abaixo dos quadros trancados (site); no app fica só o cartão
     "Este comércio é seu?" (vai no 1.8.0).
   - Agenda de eventos sem evento: `noindex` e fora do sitemap; volta sozinha quando tiver evento.
   - Textos da ordem: "Destaque e fichas completas aparecem primeiro" (área) e pergunta da ordem na inicial.
   - Inicial: totais e eventos em cache de 1 h (web_cache 'inicio-dados'; 0,65 s → ~0,1 s), descrição com ~125
     caracteres e dados do site (WebSite + Organization). Obs.: o Google tirou a "caixa de busca" dos resultados em 2024;
     os dados servem para o nome do site e a marca.
   - Limpeza de bairros e tipos: regras em servidor/inc/limpeza.php (limpar_bairro, TIPO_IGUAL, TIPO_AREA_CERTA), usadas
     pelo importador em toda lista nova e por `php servidor/limpar-dados.php [--aplicar]` no que já está no banco. Só
     ficha importada sem dono. Aplicado: 3.667 fichas; bairros por cidade 3.967 → 2.752 (tirados ~1.100 que não eram
     bairro, incl. "Região Urbana Homogênea"; padronizados Jd./Conj. Hab./acentos); 10 tipos juntados (Bares → Bar...);
     81 tipos (1.004 fichas) para a área certa. Backups: /var/lib/acha-facil/backups/profissionais.antes-limpeza.20261116-0901.sql.gz
     e arquivos limpeza-bairros-tipos-*.sql (desfazer: mysql acha_facil < arquivo). Sobram nomes de rua soltos
     ("da Silva", "Antônio Frizzo de Mendonça") que a regra não separa de bairro com nome de pessoa.
62. ✅ Qualidade dos dados, 2ª rodada (16/11, pedido do usuário). `php servidor/limpar-dados.php [--aplicar]` agora faz tudo:
   bairros que aparecem 1 vez na cidade e não têm cara de bairro saem (bairro_raro_suspeito, com lista de nomes comuns
   como Alvorada/Planalto); tipo que é endereço ("R. Ademar de Barros, 490") fica vazio; mais 60 tipos de "Outros" na
   área certa (Outros 764 → 530); lugar que não é comércio sai do app (TIPO_NAO_COMERCIO: ponte, presídio, cemitério,
   repartição pública, condomínio... → pausado; o importador também pula); fichas repetidas (mesmo nome e endereço na
   cidade) ficam só na mais completa (as outras pausadas, favoritos/avaliações passam). Aplicado: 967 fichas, 14
   repetidas e 20 não-comércio pausados. Backup: /var/lib/acha-facil/backups/profissionais.antes-limpeza2.20261116-0910.sql.gz
   e limpeza-dados-20261116-091059.sql (desfazer). A importação pelo admin (Novidades) roda a limpeza sozinha depois.
   Telefone de central (4003/4004/3003/0800): mostrado sem DDD ("4004-4828 (central)"), tel: sem DDD, API com
   telefoneCentral (app 1.8.0 mostra "Central de atendimento da rede"). Telefones divididos (8 hotéis de Campos com o
   mesmo número) conferidos: são da mesma rede (central de reservas), não é erro — mantidos.
63. ✅ Ponto exato no mapa vindo do robô (16/11, pedido do usuário). Importador aceita data-lat/data-lng na `<tr>` ou o
   link do Maps com !3d…!4d… (prioridade), query=lat,lng ou @lat,lng; grava geo_origem = 'google'; ponto manual fica.
   scripts/robo/leads_places_api.py (cópia do robô da API oficial enviado pelo usuário) agora pede places.location e
   manda data-lat/data-lng e data-ativo. Testado com lista de exemplo e com o robô simulado no banco de teste.
   Falta (usuário, no PC): usar o robô novo (ou ajustar o de raspagem: ver docs/robo-automatico.md) e reimportar as
   cidades; hoje 15% exato.
64. ✅ Nome curto e grafias de bairro (16/11, pedido do usuário). nome_curto() em inc/guia.php: a ficha do site usa a parte
   antes do primeiro "|" (e no máx. 60 letras) no título da página, no nome em destaque, na descrição e no Google
   (name; o completo vai em alternateName e numa linha abaixo do nome). Bairros: BAIRRO_ACENTOS / acentuar_bairro() em
   inc/limpeza.php (Sao → São, Jose → José, Braz → Brás...), também no importador; aplicado em 3.306 fichas. Backup:
   /var/lib/acha-facil/backups/profissionais.antes-acentos.20261116-0932.sql.gz e limpeza-dados-20261116-093218.sql.
65. ✅ Admin edita qualquer comércio (16/11, pedido do usuário). /admin/comercio.php?id= muda tudo direto, sem conferência:
   situação (ativo/pausado...), selo Verificado, nome, cidade, área, tipo, responsável, descrição, serviços, telefones,
   Instagram, site, endereço, bairro, horário, ponto no mapa (vira geo_origem manual) e link do "Como chegar", plano e
   pagamento (Básico pago, plano, assinatura, paga até), logo e fotos. /admin/comercios.php procura (nome, telefone,
   endereço, tipo ou nº) e mostra os editados recentemente (menu Mais > Comércios). Registro: acao 'editou_comercio'.
   Lápis ✏️ na ficha: no site fica escondido na página em cache e só aparece para conta admin (data-so-admin, ligado
   pelo topo em inc/pagina.php); no app (1.8.0) aparece com usuario.admin e abre a edição no navegador já entrado
   (api/admin.php acao link_editar → /admin/do-app.php, código de uso único de 2 min em dados_dir/links-admin).
   Comércio editado pelo admin não é mais mudado pela importação das listas nem pela limpeza (editados_pelo_admin()).
   Sem mudança no banco. Testado em cópia isolada (salvar, erro de telefone, logo/fotos, link do app, não-admin barrado).
65. ✅ "Quer ser avisado?" depois do "Quero saber" (16/11, pedido do usuário, opção A). Depois do toque, aparece a pergunta
   [Sim, me avise] / [Não, obrigado]; o pedido continua contando sem conta. Sim → precisa da conta Google (site: entra e
   volta com ?avisar=TIPO e o aviso é gravado sozinho; app: "Entrar e me avisar", e ao voltar logado o aviso é pedido).
   Mudança 029 (avisos_info) — USUÁRIO APLICA; antes dela a pergunta não aparece. inc/avisos-info.php
   (pedir_aviso_info, avisar_infos_completas, avisos_info_do_usuario, avisos_info_esperando), api/avisar-info.php.
   Quando a informação entra (alteração aprovada em aprovar_alteracao, ou lista importada), quem pediu recebe a
   notificação no celular (avisar_usuario, dados tela=profissional: até o app atual abre a ficha) uma vez só, e o aviso
   aparece em Minha conta → "Avisos que você pediu" (com Cancelar). Relatório para o comerciante: "· N estão esperando o
   aviso". App: src/componentes/Trancado.tsx (vai no 1.8.0, ainda não gerado). Testado numa cópia com o banco de teste
   (pedir sem/com conta, aprovar alteração avisa 1 vez, horário segue esperando, Minha conta, ficha com e sem login).
   16/11: app 1.8.0 gerado no PC (saida\acha-facil-1.8.0-9.aab, versionCode 9). Falta: enviar à Play, `npx eas build:version:set`
   (9), gerar o APK do site (-Tipo apk) e publicar com servidor/publicar-apk.sh.
66. ✅ Números sem o robô do AdSense (16/11). Depois de ligar o AdSense (15/11), o robô Mediapartners-Google e o
   "Chrome" dos servidores do Google (rate-limited-proxy-74-125-*.google.com, sem Referer) contavam como gente que
   "entrou direto": o recorde de 15/11 (354 entradas, 241 diretas) era quase todo robô. e_robo() em inc/numeros.php
   agora ignora mediapartners/adsbot/googleother/google-/PlayStore-Google e esses IPs do Google sem Referer. Os dias
   15 e 16/11 ficam inflados no histórico (não dá para separar depois). Gente de verdade vinda do Google: 15/11 = 29
   cliques, 14/11 = 31 (o resto é pré-carregamento do Chrome).
67. ✅ Ficha pré-carregada só conta quando a pessoa abre (16/11, pedido do usuário). web.php não chama contar_visita
   quando e_pre_carregamento(); a página leva visita_adiada_js (inc/numeros.php), que só roda quando a pessoa vê a
   página (prerenderingchange) e chama api/visita.php (sinal HMAC do dia): conta a visita com a origem certa e marca a
   entrada como entradas_hora.pre = 2 (pré-carregada e vista; 1 = só baixada; 0 = clique normal). /admin/entradas.php:
   "entradas de pessoas" = total − só baixadas. Testado na cópia (pré-carregada sem abrir: 0 visitas; aberta: 1 visita e
   pre 2; sinal falso recusado; clique normal conta direto). Dias antes de 16/11 continuam com os downloads somados.
68. ✅ Ver quem pediu no "Quero saber" (16/11, pedido do usuário). Antes só havia a contagem por dia, sem a pessoa.
   Mudança 030 (pedidos_info_pessoas: comércio, tipo, usuario_id ou NULL = anônimo, origem app/site, data). api/quero.php
   guarda a conta de quem pediu (X-Token no app; no site o botão agora manda o cookie + X-AF-Web). Telas: /admin/quem-pediu.php
   (todos ou ?id= um comércio; filtro "só com cadastro"; embaixo quem pediu para ser avisado, da avisos_info), link "Quem pediu"
   em Números > Pedidos de informação e nos números da ficha, e seção "Pediu informação" em /admin/pessoa.php.
   Sem a 030 aplicada, nada quebra (só a contagem continua). Pedidos antigos (antes de 16/11) não têm a pessoa.
   Falta (usuário): aplicar a 030 com o instalar.sh.
69. ✅ Google por cidade (16/11, pedido do usuário). /admin/google.php (Números > "Google por cidade" e menu Mais): por cidade,
   fichas no sitemap, fichas já visitadas pelo robô do Google, %, páginas lidas nos últimos 7 dias, última visita, entradas
   pela pesquisa (total e 7 dias); em vermelho as cidades com menos de 10%, lista "Para empurrar no Search Console" (sitemap
   e página da cidade de cada uma) e tabela de páginas lidas por dia. Dados do robô: servidor/google-visitas.php lê o
   registro do Apache (Googlebot com IP conferido nos dois sentidos) e guarda em $DADOS/google/visitas.json (fichas pelo nº,
   primeira e última visita; páginas e fichas por dia). Sem mudança no banco. Cron de hora em hora (root) no instalar.sh;
   já rodado uma vez à mão em 16/11 (4.298 fichas visitadas desde 10/11).
   Falta (usuário): rodar o instalar.sh para ligar o cron.
70. 🟡 Google: "Rastreada, mas não indexada" (16/11, relatório de cobertura enviado pelo usuário). Das 730 páginas (até 12/11):
   526 fichas, 104 planos.php?id=, 79 tipos, 17 áreas. Fichas recusadas x aceitas têm o mesmo conteúdo (sem descrição/fotos/
   horário); diferença é a fama (popularidade 36 x 76). Correções: robots.txt bloqueia /planos.php?, /suporte.php e
   /incluir.php? (eram ~76 mil endereços de cada ficha gastando o rastreamento; /planos.php puro continua); links "Ver os
   planos" e "Complete a sua ficha" com nofollow; mensagens "Pedido anotado", "Quer ser avisado?" e "Combinado" dos espaços
   trancados agora são criadas pelo JS (antes iam escondidas no HTML de toda ficha e deixavam as páginas parecidas).
   Detectada, mas não indexada: 13.402 (fila do Google, inclusive São João). JS conferido só por sintaxe (sem navegador na VPS).
   Falta (usuário): testar no navegador o toque em "Quero saber..." numa ficha; daqui a 1 semana exportar de novo a cobertura.
   Resumo da cobertura de 16/11 (todos esperados): canônica alternativa 32 = planos.php?id= (aponta para /planos.php, certo;
   agora também bloqueado no robots, então vai migrar para "Bloqueada pelo robots.txt", que vai crescer — normal);
   robots 22 = admin/painel/api/assinatura/entrar etc. (de propósito); noindex 17 = incluir/suporte, tipo com menos de 3,
   bairro, ficha sem telefone, agenda vazia, 404 (de propósito). NÃO clicar em "Validar correção" nesses três.
   /favicon.ico na raiz (dava 404 para o Googlebot) copiado de assets/.
71. ✅ Comparação com o período anterior em /admin/entradas.php (16/11, pedido do usuário: "134, se ontem foi 130, +4").
   Cada quadro (entradas, de gente, fichas abertas, visualizações, toques) mostra a diferença: Hoje x ontem (dia inteiro),
   7/30/90 dias x os 7/30/90 anteriores, em verde (+), vermelho (−) ou "igual", com o número anterior entre parênteses.
   Período anterior antes dos dados (entradas desde 11/11, fichas/toques por origem desde 14/11) = "sem dados para comparar".
   Tabela nova "Dia a dia" (período escolhido, no mínimo 7 dias) com a diferença para o dia anterior em cada coluna.
   Sem mudança no banco. Testado numa cópia com o banco real (só leitura): Google, direto, cidade e 1/7/30 dias.
72. ✅ Pedidos de informação em página própria (17/11, pedido do usuário). /admin/pedidos-info.php, no MENU do admin
   (ao lado de Números; no celular, em "Mais"). Saiu de /admin/numeros.php. Filtros de cidade, plano e período; CSV junto.
   As datas NÃO ficam na tabela: ficam em /admin/quem-pediu.php, seção nova "Datas dos pedidos" (data, o que pediu, app/site,
   vezes), tirada da contagem por dia (pedidos_info), por isso mostra também os pedidos de antes de 16/11. Sem mudança no banco.
73. ✅ Botões da ficha incompleta com "Pedir" (17/11, pedido do usuário: "Quero" dava a impressão de que era só tocar para aparecer).
   Pedir o WhatsApp / fotos / detalhes / o horário **ao comércio** + linha embaixo "Vamos avisar o dono que você quer essa
   informação." (some depois do toque). Site: inc/web.php (a linha é criada pelo JS, como as mensagens do passo 70) + site.css v32,
   cache limpo. App: src/app/profissional/[id].tsx + src/componentes/Trancado.tsx, vai no APK 1.8.0.
74. ✅ "Horários de chegada" comparativo em /admin/entradas.php (17/11, pedido do usuário: "hoje 10, ontem 9, barras lado a
   lado"). Barra amarela = período atual (Hoje / últimos N dias), verde = anterior (ontem inteiro / N dias anteriores), na mesma
   hora, com legenda e totais. Sem mudança no banco nem no CSS (reaproveita .aparicoes/.vistas). A hora existe desde 14/11.
   Ordem (pedido do usuário, 17/11): verde (anterior) à esquerda, amarela (atual) à direita.
75. ✅ Nome "FSA Designer" fora do Acha Fácil (17/11, pedido do usuário: "não quero misturar os dois projetos"). Rodapé
   ("Acha Fácil · ano"), Termos, Privacidade (o controlador agora é "o Acha Fácil"), planos (site, index, relatório do comércio):
   "feito pela equipe do Acha Fácil". App (vai no próximo APK): Sobre sem "Feito pela FSA Designer", planos.tsx. Cache web limpo.
   17/11: e-mail trocado para achafacilcontato@gmail.com (config + src/api/cliente.ts, app 1.9.0).
   🟡 Falta: Instagram de contato ainda é o da FSA (@fsa.designer) em
   /etc/acha-facil/config.php (contato_email, instagram) e em src/api/cliente.ts. Trocar quando o usuário tiver os do Acha Fácil.
76. ✅ Plano Básico no relatório do comércio (17/11, pedido do usuário: "o mais atrativo é o de 9,90"). /admin/relatorio.php
   mostra Básico (quadro amarelo, "uma vez só, vale para sempre") e Destaque lado a lado (um embaixo do outro no celular);
   Básico some se basico_pago_em; tudo some com Destaque em dia. Linha do Destaque + Site continua embaixo.
77. ✅ Relatório do comércio cabe numa folha (17/11, pedido do usuário). /admin/relatorio.php: @page A4 margem 8 mm; ao
   imprimir/salvar PDF o script liga a classe .imprimindo (versão compacta, colunas lado a lado), mede a altura e, se ainda
   passar de uma folha, aplica zoom até caber.
78. ✅ Plano Grátis (17/11, pedido do usuário). O dono assume a ficha com a conta Google, sem pagar e sem esperar o admin,
   e corrige o que já vem do Google: nome, telefone, WhatsApp, endereço, bairro, site, horário, atende em casa
   (CAMPOS_GRATIS em inc/moderacao.php). Básico (R$ 9,90 único) libera logo, fotos, descrição, produtos e Instagram
   (CAMPOS_SO_BASICO). Destaque continua igual. **Sem mudança no banco**: Grátis = pedido 'assumir' sem plano (status feito)
   + acessos_dono (assumir_gratis()). Toda MUDANÇA continua passando pela conferência do admin.
   - perfil.php?acao=assumir&id= sem plano = cadastro grátis (login Google, nome + telefone) → painel na hora; aviso aos
     admins "Comércio assumido (grátis)". Ficha com dono bloqueia outras contas ("Este comércio já tem responsável").
   - painel/: entra sozinho pela conta Google (acesso_da_conta(), pelo pedidos.usuario_id); no Grátis mostra só os campos
     liberados e um quadro trancado "Liberar no Básico"; ao enviar, campos do Básico ficam como estão.
   - Selos: ficha = "✓ Dados confirmados pelo dono" (quem assumiu, sem Destaque; Destaque mantém o Verificado); listas =
     Destaque / "Ficha completa" (Básico pago) / "Confirmado pelo dono" (Grátis). API: fichaCompleta, donoConfirmado.
   - planos.php (4 cartões: Grátis, Básico, Destaque, + Site), inicial, busca ("É meu: cadastrar grátis"), ficha ("Cadastrar
     grátis"), termos e relatório do comércio atualizados.
   - App 1.9.0 (falta gerar): tela de planos com o Grátis, selos novos, convite "Cadastre-se grátis". Apps antigos mostram
     "Ficha completa" também para quem assumiu grátis (usam `cadastrado`), até atualizar.
   Testado numa cópia isolada (banco de teste): cadastro, painel Grátis, envio só do WhatsApp, volta pela conta Google,
   sair/entrar, outra conta bloqueada, Básico pago com painel completo, selos e API.
79. 🟡 Cronograma das próximas 16 cidades (17/11, pedido do usuário): docs/cronograma-cidades.md, 4 levas de 18/11 a 12/12.
   Começa quando o usuário pedir. 17/11: as 16 cadastradas DESLIGADAS em `cidades` (Monte Verde com esse nome, decisão do
   usuário; "São Tomé das Letras" pela grafia do IBGE). importar-leads.php: corta "Camanducaia" e só corta o nome da cidade
   no começo de um pedaço do endereço ("Av. Monte Verde, 100" e "Av. Itu, 300" ficam inteiras).
80. ✅ Sitemaps da cidade ao importar (17/11, pedido do usuário: "assim que adicionar uma cidade, gerar os sitemaps e me
   mostrar"). inc/sitemaps.php: sitemaps_gerar_cidade() troca no cache e gera na hora sitemap-<cidade>.xml e os por tipo
   (+ sitemap.xml e os índices por tipo); sitemaps_quadro() mostra o link para o Search Console com a contagem de páginas.
   Aparece: em /admin/novidades.php logo depois de Importar (e botão "Gerar os sitemaps" nas listas importadas), em
   /admin/cidades.php ao LIGAR a cidade e no botão "🗺️ Sitemaps" de cada cidade ligada. Cidade desligada: o quadro avisa
   para ligar (web.php só serve sitemap de cidade ligada). Monte Verde (842 comércios, ligada): 1 + 81 + 650 páginas,
   https://achafacilapp.com.br/sitemap-monte-verde.xml.
81. ✅ Listas de /admin/entradas.php sem número e com páginas (17/11, pedido do usuário). Títulos "Páginas onde entraram",
   "Fichas de comércio abertas" e "Toques em ..." sem 1./2./3.; elas e "Comércios mais procurados" (antes só os 20 primeiros,
   agora todos) mostram 15 por página, cada uma com a sua página (?pg_paginas=, pg_fichas=, pg_toques=, pg_comercios=,
   volta para a lista pela âncora #lista-...).
82. ✅ Nada de dados antes do pagamento (17/11, pedido do usuário; caso do psicólogo, pedidos 16 Grátis + 17 Básico não pago).
   perfil.php: Básico/Destaque e Grátis sem o campo de texto ("Depois do pagamento, você preenche ... no painel"); só o
   + Site (domínio), incluir e tirar do app têm texto. admin/pedido.php: plano não pago não tem mais "liberar o painel"
   (bloqueado também no POST; cortesia = cupom de 100%), mostra "Já é dono pelo Grátis desde..." e marca a mensagem como
   "escrita antes do pagamento". avisar-sem-pagamento.php: "Troca de plano sem pagamento ... já é dono pelo Grátis ... Nada para ativar".
83. ✅ Ajuste dos planos (17/11, pedido do usuário). "Atende em casa / faz entrega" sai do Grátis (fica no Básico e no Destaque;
   CAMPOS_GRATIS / CAMPOS_SO_BASICO). Fotos: Básico até 2 (MAX_FOTOS_BASICO), Destaque até 10; a ficha mostra só as 2
   primeiras fora do Destaque (max_fotos_plano() em inc/guia.php; as outras ficam guardadas). Anúncios do Google também na ficha
   do Básico (só o Destaque fica sem). Painel do Grátis sem números (quadro "Liberar no Básico"). Textos: planos.php, inicial,
   termos, privacidade (anúncios), relatório, cadastro grátis e app 1.9.0 (planos.tsx). Testado numa cópia nos 3 planos.
84. ✅ Admin vê o painel como o dono (17/11, pedido do usuário). /painel/?ver=<id do comércio> só com sessão de admin: mostra o
   painel do plano atual (Grátis/Básico/Destaque) com aviso "Visualização do administrador", botões e campos desligados e POST
   bloqueado; não entra como o dono nem mexe na sessão dele. Sem admin, o ?ver= é ignorado. Botão "👁 Ver o painel como o dono"
   em /admin/pedido.php.
85. ✅ Notificação para pessoas escolhidas (17/11, pedido do usuário). /admin/pessoas.php: caixinha em cada pessoa (📱 = tem o
   app com avisos ligados na conta), quadro com título e texto, "Marcar todas com 📱", contador e confirmação; manda com
   avisar_usuario() para cada uma (máx. 200 por vez) e diz quem não recebeu. Registro: notificou_pessoas. Testado no banco de
   teste (aparelho falso não recebe, como esperado).
86. ✅ Caixinhas e bolinhas no tamanho normal no site inteiro (17/11, pedido do usuário). A regra geral de input (100% de
   largura, 58 px) pegava checkbox/radio: agora 20 px, cor verde (accent-color). site.css v33, cache limpo. As estrelas da
   avaliação continuam escondidas (regra própria vem depois).
87. ✅ Pagamento do psicólogo não ligou o Básico (17/11). Pix 190189997312 (R$ 9,90, pedido 17) aprovado 17:19, mas o
   mp-webhook.php recusou os 5 avisos do Mercado Pago com 401: "assinatura não bate" (mp_webhook_secret do config não confere
   com a do painel do MP; avisos novos vêm como "payments_via_orders"). Aplicado à mão pela API (mp_conferir_pagamento):
   basico_pago_em 17:19, pedido 17 feito, pedido 18 (repetido, sem pagamento) recusado; backup em
   /var/lib/acha-facil/backups/antes-ativar-pedido17.*. Aceitar aviso sem assinatura foi NEGADO ao Claude pelo modo
   automático. Falta: o usuário conferir a chave secreta dos Webhooks no Mercado Pago e trocar no /etc/acha-facil/config.php.
   RESOLVIDO 17/11: o webhook NUNCA funcionou (13/11 também deu 401; o Básico da Rosa ligou porque ela voltou ao site pela
   página da assinatura). Causa: mp_webhook_secret do config era de outra chave (ef7f…); trocado pela "Assinatura secreta" da
   aplicação 6833461186412723 (ff62…), backup em /root/config.php.antes-webhook.*. Teste com aviso assinado: certa = 200,
   errada = 401. Pix pago no app do banco (sem voltar ao site) agora liga o plano sozinho.
88. ✅ Notificação só quando paga ou assume no Grátis (17/11, pedido do usuário: "se a pessoa entrar em pagamento, não
   aparecesse a notificação"). avisar-sem-pagamento.php desligado (exit no topo; o cron continua mas não faz nada).
   Avisos que ficam: "Novo plano pago" (Mercado Pago aprovado/assinatura autorizada), "Cupom de cortesia usado",
   "Comércio assumido (grátis)", incluir/tirar do app. Escolher plano e abrir o pagamento não avisa. O pedido segue em
   /admin como "não pago" para consulta.
89. ✅ Botão "✓ Visto, aguardando retorno" nos pedidos (17/11, pedido do usuário: pendência já vista continuava contando).
   inc/pedidos-vistos.php guarda {id: data} em <dados_dir>/pedidos-vistos.json (sem mudança no banco). Pedido visto e não
   pago sai da contagem do menu Pendências, da lista "Pedidos de donos" e da Administração do app (api/admin.php); fica em
   "Vistos, aguardando retorno" (recolhido) em /admin/ com "Voltar às pendências". Se for pago, volta a contar sozinho.
   Botão na lista de /admin/ e no topo de /admin/pedido.php; registro_admin pedido_visto / pedido_visto_desfeito.
90. ✅ "Em destaque" antes das áreas (17/11, pedido do usuário: "tem que aparecer em cima das categorias"). Site: página da
   cidade (web_pagina_cidade em inc/web.php) mostra Em destaque logo abaixo da busca, depois Escolha a área e Conhecidos
   (cache limpo). App (src/app/(abas)/index.tsx): Vitrine com Destaque pago antes de "Explore por categoria"; sem Destaque
   pago, o convite continua depois das categorias. App vai no próximo APK.
91. ✅ Fotos da ficha em janela com carrossel (17/11, pedido do usuário: tocar na foto abria outra aba). web_pagina_ficha
   (inc/web.php): links das fotos com data-foto, janela #foto-janela (fundo escuro, setas, arrastar com o dedo, teclado
   ← → Esc, contador "1 de N", fecha no X ou tocando fora); imagens grandes só carregam ao abrir. CSS .foto-janela/.fj-*
   em site.css (v=34). Sem JS o link ainda abre a imagem. App já tinha carrossel na capa (CapaComercio), sem mudança.
92. ✅ Dono volta ao painel por Minha conta (17/11, pedido do usuário: "onde ela entra para editar o painel"). Falha achada:
   quem pagava (ex.: Pix no app do banco, ligado pelo webhook) e não clicava em "Editar os dados" ficava sem acessos_dono e
   o login com o Google não achava o painel. acesso_da_conta() (inc/moderacao.php) agora cria o acesso pelo pedido pago da
   conta (acesso_pelo_pagamento). /minha-conta.php mostra "Seu comércio: X" + botão "Painel do meu comércio" (/painel/?entrar=1).
   Testado em cópia isolada.
93. ✅ Dono edita pelo app e quem incluiu o negócio vira dono (17/11, caso Agnes Veterinária id 103614, conta
   vet.clinica4patas@gmail.com: incluiu o negócio (pedido 24), admin publicou e deu Destaque cortesia direto na ficha, mas
   o "incluir" nunca criava acessos_dono → nenhum caminho para o painel). acesso_do_incluir() (inc/moderacao.php): publicar
   em /admin/pedido.php já cria o acesso; acesso_da_conta() cria para incluídos antigos ao entrar. App: Meu perfil mostra
   "Editar meu comércio" (nome do comércio) para quem é dono → api/dono.php (GET comercio / POST link_painel) →
   /painel/do-app.php?c= (uso único, 2 min) entra no site e abre o painel. App vai no próximo APK; site já no ar.
94. ✅ Tipo do negócio pela lista da área no "Incluir meu negócio" (17/11, pedido do usuário: tipo era texto livre e ia
   criar tipos repetidos). /incluir.php: Área vem primeiro; o campo Tipo sugere (datalist) só os tipos daquela área usados
   por 3+ comércios ativos; o servidor aceita só tipo da lista (sem ligar para acento/maiúscula, grava a grafia oficial).
   Fora da lista: caixinha "Não achei o tipo na lista" → pedido com "TIPO NOVO (fora da lista): X" na mensagem para o admin
   conferir ao publicar. App não tem formulário próprio (abre o site), sem APK.
95. ✅ Tipo como caixa de seleção (17/11, pedido do usuário: "não poderia digitar"). /incluir.php: <select id="tipo"> se monta
   sozinho com os tipos da área escolhida (desligado até escolher a área). Caixinha "Não encontrei o tipo na lista" desliga a
   caixa e mostra o campo tipo_outro (obrigatório); se o digitado já existir na área, grava o oficial; senão vai como
   "TIPO NOVO (fora da lista)" na mensagem do pedido. Valor que não é da lista é recusado no servidor.
96. ✅ Tipo em caixa de seleção também na edição do admin (17/11, pedido do usuário). inc/tipos.php (tipos_por_area,
   tipo_da_lista, tipo_do_post, tipo_campos_html) usado por /incluir.php e /admin/comercio.php. No admin o tipo atual do
   comércio entra na lista da área dele (continua valendo mesmo com menos de 3 usos); tipo novo só por "Não encontrei o
   tipo na lista". Testado em cópia isolada (lista, forjado, vazio, novo, salvar sem mexer).
97. ✅ Barbearia com tesoura no lugar do batom (19/11, pedido do usuário: continua dentro de Beleza). icone_comercio() e
   ICONES_TIPO em inc/guia.php trocam o ícone da área pelo do tipo (barbear -> content-cut); usado em prof_resumo (app,
   todas as listas) e web_logo (site, emoji ✂️ em EMOJI_AREAS). Vale na hora, sem APK novo. Cache da web limpo.
   Ajuste 19/11 (usuário: fundo rosa não combinava): fundo marrom #8B5A3C (cor_comercio(); ICONES_TIPO = tipo => [ícone, cor]).
98. ✅ Aviso de versão nova da Play não aparecia (19/11, pedido do usuário). Causas: (1) quando a Play do celular dizia
   "sem atualização" (ela leva horas ou dias para ver a versão nova, e no teste fechado só aparece para testadores) o
   app parava ali e nunca olhava a reserva do servidor; (2) a reserva (/admin/app.php, versao-play.txt) estava vazia.
   src/versao.ts: avisoPelaPlay() devolve 'avisou'|'sem'; com 'sem' confere a versão do /admin/app.php. App 1.10.0.
   Depois de liberar cada versão na Play: pôr o número em /admin/app.php. Obs.: APK do site parado na 1.7.1 (12/11).
   Ajuste 19/11: o .aab saiu com versionCode 10 já usado (EAS não foi atualizado após o envio anterior); gerar-local.ps1
   agora usa o maior entre o EAS e os .aab de saida\.
99. ✅ Pedreira no ar (19/11, leva 1 do cronograma): 1262 comércios importados (backup profissionais-pedreira-20261119),
   todos com mapa, cidade ligada, https://achafacilapp.com.br/pedreira/ e sitemap-pedreira.xml respondendo. Só 14 em
   "Outros". Águas de Lindóia também ligada (774). Falta da leva 1: Monte Alegre do Sul e Socorro.
100. ✅ Aviso da Play "screen.portrait restringe a disponibilidade" (19/11, ao enviar a 1.10.0 nº 11). O "orientation":
   "portrait" do app.json vira recurso obrigatório no manifesto; plugins/tela-opcional.js marca screen.portrait e
   screen.landscape como android:required="false" (app continua em pé, instala em tablet/Chromebook/XR). Vale do
   próximo .aab em diante; o nº 11 foi enviado com "Continuar mesmo assim" (só aviso).
100. ✅ Brotas no ar (19/11, leva 3 do cronograma, adiantada): 970 importados (backup profissionais-brotas-20261119), todos
   com mapa, cidade ligada, https://achafacilapp.com.br/brotas/ e sitemap-brotas.xml respondendo. 19 resultados eram
   BAIRROS/endereço (nome "Centro", "Jardim Regina"..., tipo "Brotas - SP", sem endereço): pausados (backup
   brotas-bairros.antes-pausar.*.sql) → 951 no ar. Importador (importar-leads.php) agora pula tipo no formato
   "Cidade - UF". Em "Outros" ficaram ~22 (atração turística, rafting, agência de turismo esportivo...).
101. ✅ Destaque da inicial com espaço e girando sozinho (19/11, pedido do usuário: cartão grudado no cabeçalho).
   (abas)/index.tsx: margem de 20 em cima do Destaque. Vitrine.tsx: passa para o próximo a cada 4 s (TROCA_MS), em
   volta (destaques + "Seu comércio aqui", depois volta ao 1º); só com a tela aberta (useFocusEffect) e com algum
   destaque; para enquanto a pessoa arrasta e espera 4 s depois de soltar. App 1.11.0 (precisa de build novo).
102. ⬜ Publicar na App Store (19/11, pedido do usuário; separado do passo 11): conta de desenvolvedor Apple (US$ 99/ano),
   "Entrar com Apple" junto do Google (regra 4.8 da Apple, ver passo 7c), build iOS de produção pelo EAS, capturas de
   tela do iPhone, ficha e conta de demonstração. Guia e textos em `docs/lojas.md`; app.json já pronto para o iOS (07/11)
103. ✅ Avaliação do site, 1ª rodada de mudanças (19/11, pedido do usuário):
   (1) Instagram @fsa.designer fora do rodapé (config `instagram` vazio; volta quando existir o @ do Acha Fácil).
   (2) Inicial: "Próximos eventos" só aparece com evento marcado e some sozinha quando o último passa (cache por dia; o
   admin limpa ao aprovar); sem evento fica só a linha "Divulgue o evento de graça". Saiu a 3ª lista com as 22 cidades.
   (3) Ficha incompleta: os 4 quadros 🔒 viraram um bloco só, "Faltam nesta ficha: WhatsApp, fotos, horário e o que vende
   ou faz", cada item com o seu "🙋 Pedir" (mesma contagem de api/quero.php e o "Quer ser avisado?"); web_faltam() em
   inc/web.php. O convite ao dono ficou só na caixa "Este comércio é seu?".
   (4) Descrição do Google sem rua de verdade: "Atende a domicílio." no lugar de "Endereço: Atendimento a domicílio".
   (5) Imagem de compartilhar 1200x630 por cidade e da inicial ("4.464 comércios e serviços em São João da Boa Vista - SP"),
   com a paisagem do app: publico/og.php gera com og-imagem.py (Pillow, fonte Nunito em servidor/fontes/) na 1ª vez e de
   novo a cada 7 dias, em /var/lib/acha-facil/og/. Cidade nova ganha a sua sozinha. Ficha/evento com foto continuam com a foto.
   (6) Cache no navegador (.htaccess, também em dominio/htaccess-redirecionar): CSS 1 ano (sempre com ?v=, agora 35),
   imagens 7 dias. (7) Títulos de todas as páginas com " | Acha Fácil!".
   (8) Nomes em caixa irregular: limpar_nome() em inc/limpeza.php ("REDE DROGÃO" → "Rede Drogão", "Canhotos BAR" →
   "Canhotos Bar", "Point Vip Sjbv" → "Point Vip SJBV"); marca em maiúsculas no meio do nome (FLAGRA, APPLE) e siglas
   (SENAI, AEF, RC) ficam. 4.208 fichas importadas sem dono arrumadas com `php servidor/limpar-nomes.php --aplicar`
   (desfazer: /var/lib/acha-facil/backups/nomes-antes-20261119-104447.sql e -104509.sql); o importador já usa a regra.
   O endereço das fichas não muda (só 1, Lumière, e o antigo redireciona). Google Play "em breve" continua até sair do
   teste fechado (9 de 14 dias em 19/11): aí é só pôr o `link_play` no config.
104. ✅ Relatório mensal, compartilhar, "Aberto agora" e Oferta da semana (19/11, pedido do usuário, da avaliação do site):
   (1) **Relatório mensal automático** para quem paga (Básico ou Destaque): cron no dia 1 às 10:13
   (`servidor/relatorio-mensal.php`, regras em inc/relatorio-mensal.php, tabela relatorios_mensais da mudança 031). Aviso no
   celular "📊 Seu outubro no Acha Fácil: Sua ficha foi vista 230 vezes e teve 18 toques no WhatsApp" para as contas ligadas ao
   comércio; o link abre o relatório de 1 folha (/painel/relatorio.php, link assinado, sem login; o mesmo do admin). Atalho
   "📊 Relatório do mês" no painel. Conferir antes: `php servidor/relatorio-mensal.php --ver`. App: tocar no aviso abre o link
   a partir do próximo APK (src/app/_layout.tsx, dados.url); os apps de hoje abrem a ficha.
   (2) **Compartilhar no WhatsApp** na ficha do site ("Olha esse lugar: Nome (tipo), em Cidade" + link). Conta como
   'compartilhar' (mudança 032), fora do total de contatos.
   (3) **"🟢 Aberto agora"** nas páginas de área, tipo e busca do site (?aberto=1, sem cache e fora do Google). Só entra quem
   informou o horário (19/11: 4 fichas), com aviso "É dono? Informe o seu horário de graça". O app ainda não tem o filtro.
   (4) **Oferta da semana** (benefício pago do Destaque): **R$ 9,90 por oferta** (pagamento único, sem mensalidade;
   'preco_oferta' no config muda o valor), no ar 1 a 7 dias a partir da aprovação. Dono: /painel/oferta.php (título, texto,
   preço de/por, foto, dias) → Mercado Pago → admin confere em /admin/ofertas.php (aviso no celular dos admins; contador em
   Pendências) → no ar na ficha e em "🏷️ Ofertas da semana em <cidade>" no topo da página da cidade. Recusada: o dono corrige
   e manda de novo sem pagar. Uma no ar por vez; some sozinha no fim ou com o Destaque vencido. Regras em inc/ofertas.php,
   pagamento pelo mp-webhook.php (external_reference "oferta:N"). Termos e planos atualizados. A API já manda `ofertas`
   (inicio.php) e `oferta` (profissional.php); o app ainda não mostra (falta tela no app).
   Testado em 19/11: oferta de teste no ar (ficha, cidade, API) e apagada; fluxo pago → recusa → correção → aprovação →
   encerrar simulado no servidor (sem avisos de verdade); link de pagamento do Mercado Pago criado (não pago).
105. ✅ App 1.12.0: oferta, "Aberto agora" e compartilhar (19/11, pedido do usuário; testado e build gerado pelo usuário em 19/11):
   (1) Ficha: quadro amarelo "Oferta da semana · até domingo, 22/11" com foto, detalhes e preço de/por (`oferta` da API).
   Início: faixa "Ofertas da semana" antes do Destaque, só quando tem oferta no ar (`ofertas` de inicio.php).
   (2) Pílula "Aberto agora" na busca e na categoria (filtra `abertoAgora === true`, com o aviso "Só aparecem os comércios que
   informaram o horário") e etiqueta "Aberto agora" nos cartões. O servidor manda `abertoAgora` em todas as listas
   (prof_resumo; aberto_agora() foi para inc/horarios.php) e a busca da API aceita ?aberto=1.
   (3) Compartilhar: "Olha esse lugar: Nome (tipo), em Cidade" + link da ficha no site (`linkSite` da API); conta como
   'compartilhar' quando a pessoa de fato compartilha.
   (4) Aviso do relatório mensal abre o relatório no navegador (dados.url, passo 104).
   Falta: `npx tsc --noEmit` e `npx expo lint` no PC, testar no emulador, build (EAS volta em 01/12 ou no PC, passo 37) e enviar à Play.
106. ✅ Busca melhor, "O que procuram" e página sem resultado (19/11, pedido do usuário, itens 1, 2 e 6 da 2ª avaliação):
   (1) **Busca nova** (inc/busca.php, a mesma no site, no app e em "Procure seu negócio", sem APK novo): cada palavra
   procurada separada, pela raiz ("mecanico" acha mecânica), como começo de palavra ("unha" não acha "Cunha"), palavra curta
   só inteira ("bar" não acha barbearia), sinônimos (BUSCA_SINONIMOS/BUSCA_FRASES: "lava jato" = lava-rápido, "conserto
   celular" = assistência/reparo) e correção de digitação pelos tipos cadastrados ("pizaria" → "Mostrando resultados para
   pizzaria"; vocabulário em cache por 1 dia em <dados_dir>/cache/busca-vocabulario.json). Sem nada com todas as palavras:
   mostra quem tem parte delas ("farmácia de plantão" → farmácias). Antes: pizaria 0, lava jato 0, conserto celular 0,
   mecanico 3; agora 43, 49, 30 e 60. A API manda também `corrigido` e `parcial` (o app ainda não mostra o aviso).
   (2) **O que procuram** (/admin/buscas.php, menu Mais): sem resultado, corrigidas e mais procuradas, por cidade, período e
   site/app. Tabela buscas (**mudança 033, o usuário aplica com instalar.sh**; até lá nada é guardado e a página avisa).
   No app, o que a pessoa digita aos poucos ("piz", "pizza") vira 1 linha; repetida em 30 min não conta; robôs não contam.
   Termo sem resultado = pedir ao Claude o sinônimo ou é comércio que falta na cidade (chance de venda).
   (3) **Página sem resultado** do site: dica, "Tem em outras cidades" (com quantos), "O pessoal de <cidade> procura"
   (buscas que deram certo, com 2+ pessoas, completadas por uma lista fixa), áreas e "Incluir um negócio de graça"
   (/incluir.php?cidade= já vem com a cidade escolhida). CSS v=37.
107. 🟡 Buscas recentes no app e pedir avaliação depois do contato (19/11, pedido do usuário, itens 8 e 9 da 2ª avaliação):
   (1) **App, busca:** com o campo vazio mostra "Buscas recentes" (as 8 últimas que acharam algo, guardadas no celular,
   com "Limpar"; grava ao abrir um resultado/sair da tela ou ao tocar em buscar) e "Mais procurados em <cidade>"
   (api/sugestoes.php: buscas_populares() da tabela buscas, completada com lista fixa). Mostra também o aviso da busca
   corrigida/parcial que o servidor já mandava (passo 106).
   (2) **App, avaliação:** tocar em WhatsApp ou Ligar guarda o contato (src/pedirAvaliacao.ts) e agenda um aviso LOCAL
   para o dia seguinte às 18h, "Como foi no X?" (só com avisos permitidos; 1 agendado por vez; mesmo comércio no máximo
   1 vez a cada 30 dias). Tocar abre a ficha com ?avaliar=1 e o quadro amarelo "Como foi no X? Avaliar agora". Quem volta
   à ficha de 2 h a 14 dias depois do contato também vê o quadro. Fechar ou avaliar tira o quadro e o aviso.
   (3) **Site:** tocar em WhatsApp/Ligar na ficha guarda no navegador (localStorage af_avaliar); de 2 h a 14 dias depois,
   qualquer página (menos a própria ficha) mostra a barra "★ Como foi no X? Avaliar" que leva a ficha#avaliar
   (pagina_fim() em inc/pagina.php). Fechou ou avaliou, some. Já no ar (CSS v=38).
   Falta: app 1.13.0 (app.json já com 1.13.0): `npx tsc --noEmit`, `npx expo lint`, testar no emulador e gerar o build.
108. ✅ Convite "Quer ser avisado?" mais claro, sem obrigar conta (19/11, pedido do usuário). Desde 16/11: 33 pedidos, 85% anônimos,
   1 aceitou o aviso; decisão: pedir continua sem conta. Texto novo no site (inc/web.php) e no app (Trancado.tsx, vai no 1.13.0):
   "Quer saber quando colocarem o horário? Entre com o Google e receba o aviso no celular. É 1 toque, sem senha." e
   "Agora não" no lugar de "Não, obrigado". Acompanhar em /admin/pedidos-info.php, quadro "Convite" (por semana: pedidos,
   já com conta, aceitaram o aviso e taxa; base 16/11 = 3,0%). Rever em ~3 semanas (10/12).
108. ✅ Administradores (19/11, pedido do usuário): fsadesignerbr@gmail.com desativado (ativo=0 na tabela admins, linha
   mantida para o histórico; não entra mais no /admin nem recebe avisos de administração) e achafacilcontato@gmail.com
   criado como admin "Acha Fácil" (entra pelo Google, no site e no app). Continuam: fellipealexandre1 e agnesamarilis51.
109. 🟡 Nome do comércio e da cidade viram links para os administradores (19/11, pedido do usuário):
   **Site (/admin, no ar):** adm_comercio() e adm_cidade() em inc/admin.php: nome do comércio abre a ficha no site e o nome da
   cidade abre a página da cidade (nova aba, sublinhado pontilhado, CSS v=39); cidade desligada e negócio novo ficam só texto.
   Trocado em Pendências, Pedido, Alteração, Comércios (lista e edição), Cidades, Números, Números da ficha, Pedidos de
   informação, Quem pediu, Pessoas, Pessoa, Entradas, Google por cidade, Registro, Cupons, Ofertas, Eventos, Suporte,
   Novidades, Notificações e Importar ("Números" continua como link pequeno ao lado onde já existia). O relatório para o
   comerciante (relatorio.php) não muda. Testado em cópia: 25 páginas sem erro.
   **App (Administração, vai no 1.13.0):** comércio abre a ficha no app, cidade abre a página da cidade no navegador
   (api/admin.php manda profissionalId e linkCidade).
110. ✅ Quem aceitou o aviso "Quer saber quando colocarem...?" (19/11, pedido do usuário): em /admin/pedidos-info.php, dentro do
   quadro do convite, tabela "Quem aceitou o aviso" (avisos_info): pessoa (link para /admin/pessoa.php, onde dá para mandar
   aviso só para ela), comércio e cidade (links), o que quer saber, quando pediu e situação (⏳ esperando o comércio informar
   / ✅ avisado em). O aviso sai sozinho quando a informação entra; agora também quando o ADMIN edita a ficha em
   /admin/comercio.php (antes só alteração do dono aprovada e lista nova). Em 19/11 havia 1 inscrito, já avisado em 17/11.
111. ✅ Mapa das cidades (19/11, pedido do usuário): /admin/mapa.php (só admin; menu "Mais" > "Mapa das cidades" e botões
   em /admin/cidades.php). Abas SP e MG, cada município desenhado (malha do IBGE em servidor/dados/mapas/<UF>.json, gerada
   por scripts/gerar-mapas.py). Verde = ligada no app, amarelo = cadastrada e desligada, cinza = fora. Passar o mouse mostra
   nome e comércios; tocar mostra a cidade do projeto mais perto (em linha reta, contando o outro estado). Busca de município, zoom (roda, botões, pinça) e arrasto. Tabela com cada cidade e a vizinha mais perto.
   Cidade que não é município (Monte Verde) entra pelo centro (lat/lng) e pinta o município onde fica (Camanducaia).
   19/11: o raio em volta das cidades foi tirado a pedido do usuário (atrapalhava a visualização); não pôr de volta.
   19/11: aba "SP e MG juntos" (padrão, dados/mapas/SP-MG.json, divisa dos estados em linha escura); SP e MG separados continuam.
   19/11: cadastradas DESLIGADAS (amarelas no mapa, sem comércios ainda), pedido do usuário: São Sebastião da Grama, Tapiratiba,
   Itobi, Estiva Gerbi, Itapira e Santo Antônio do Jardim (SP). Backup da tabela em /root/cidades.antes-amarelas.*.sql.
   Depois, também desligadas: Lindóia, Santo Antônio de Posse, Amparo e Mogi Mirim (SP); backup em /root/cidades.antes-amarelas2.*.sql.
   Depois, também desligadas: Diamantina e Ouro Preto (MG); backup em /root/cidades.antes-amarelas3.*.sql.
111. ✅ Entradas: "Dia a dia" por semana e relatório do dia (19/11, pedido do usuário): em /admin/entradas.php o Dia a dia mostra
   uma semana de segunda a domingo (?semana=<segunda>, "← Semana anterior" / "Próxima semana →", desde a semana de 11/11),
   hoje em cima e destacado (mais novo primeiro; dias que ainda não chegaram não aparecem) e linha "Semana" com a soma. Cada dia abre /admin/entradas-dia.php
   (?dia=&origem=&cidade=; origem vazia = todas, site e app): resumo (entradas, de pessoas, pré-carregadas, fichas, vistas,
   toques por tipo), de onde vieram, entradas por hora, cidades, fichas abertas com toques por comércio e páginas onde
   entraram com a hora; navega para o dia anterior/seguinte. Conferido com os dados de 18/11.
112. 🟡 Relatório do dia: tabela "Por hora" completa (19/11, pedido do usuário): no lugar das barrinhas, tabela de 00h a 23h com
   Entradas, De pessoas (com barrinha), Pré, Fichas abertas, Visualizações, Pessoas, WhatsApp, Ligar, Mapa, Site/Insta, Toques e
   Compartilhar, mais a linha "Dia" com os totais; a hora atual fica destacada. Entradas por hora já existiam (entradas_hora);
   visualizações e toques por hora são novos: tabela contatos_hora (**mudança 034, o usuário aplica com instalar.sh**), gravada
   por contar_hora() em inc/numeros.php. Dias antes da 034 mostram "—" nessas colunas. Testado em cópia.
   19/11 (2): dias antes da 034 (aplicada 19/11 14:51) não têm hora de visualização/toque (só o total do dia; o registro do
   Apache também não tem id nem tipo, são POST): a tabela mostra só Entradas/De pessoas/Pré com aviso explicando; no dia 19/11
   as horas antes das 14h ficam "—". Quadros do topo arrumados (número em cima do texto) e coluna "Pessoas nas fichas".
113. ✅ Ofertas: pôr no ar sem pagamento e editar pelo admin (19/11, pedido do usuário): /admin/ofertas.php ganhou a seção
   "Esperando pagamento" (aguardando pagamento ou recusada) com "Pôr no ar sem pagamento" (liberar_oferta_sem_pagamento() em
   inc/ofertas.php: valor 0, mp_status 'cortesia', registro 'liberou_oferta_sem_pagar', depois aprova normal e avisa o dono) e
   "✏️ Editar" em todas as situações (?editar=ID: título, texto, preços, dias, foto; editar_oferta_admin(); no ar, mudar os dias
   refaz o fim a partir do início). Oferta da Agnes Veterinária (nº 3, "Vacinação para cães e gatos") no ar sem pagamento para
   teste, de 19/11 18:31 até 26/11 18:31 (ficha, página de São João e API conferidas).
114. ✅ Tamanhos da oferta (19/11, pedido do usuário): título até 50 letras (OFERTA_TITULO_MAX, era 80), detalhes até 250
   (OFERTA_TEXTO_MAX, era 400), com contador "x de 50 letras" (vermelho perto do limite) no painel do dono e no admin.
   Foto sempre QUADRADA 800 x 800 (salvar_foto_oferta(): mínimo 400 x 400, corta o meio se vier deitada ou em pé), com a
   orientação escrita no formulário e prévia quadrada antes de enviar. Site no celular e app (1.13.0) mostram a foto quadrada.
115. ✅ Oferta sem a data e foto maior (19/11, pedido do usuário): o "até quinta, 26/11" (prazo no ar, não o da promoção) saiu
   da ficha, da página da cidade e do app; o prazo da promoção o dono escreve no texto ou na foto (painel e admin continuam
   mostrando até quando fica no ar). API: validade = "aproveite!" na ficha e "Oferta da semana" no início (apps antigos
   ficam sem data). Foto maior: ficha do site 200 px (era 120), celular até 360, cartões da cidade 110 (era 76); app 1.13.0
   mostra a foto quadrada no cartão do início (antes não tinha foto) e até 360 na ficha. CSS v=41.
116. ✅ Donos: "Quem chamar hoje", números de verdade e prioridade para quem tem horário (19/11, pedido do usuário, itens 1–3 da
   3ª avaliação, notas no chat: média 6,6):
   (1) **/admin/chamar.php** ("📞 Quem chamar" no menu): comércios SEM dono (SQL_TEM_DONO) com telefone, mais vistos em 7 ou 30
   dias (vistas + 3 x pedidos de informação), até 60; colunas vistas, pessoas, pedidos, toques, contato (celular/fixo);
   botões WhatsApp (wa.me com a mensagem pronta: vistas, pedidos e link da ficha para "Cadastrar grátis"), Ligar, Copiar
   mensagem e "✓ Chamei" (sai da lista por 30 dias; "Já chamados" com Desfazer). Marcados em <dados_dir>/prospeccao-chamados.json
   (inc/prospeccao.php) + registro 'chamou_comercio'. Sem migração.
   (2) **Números de verdade** (numeros_publicos() em inc/numeros.php, cache 1 h): na seção "Seu negócio" da inicial e no
   topo de /planos.php, "visitas de pessoas, páginas abertas e toques nos últimos 30 dias" (só aparece com 100+ vistas). Na
   ficha sem dono, o quadro "Este comércio é seu?" mostra "Esta página foi vista X vezes nos últimos 30 dias e N pessoas
   pediram informações" (com 3+ vistas ou algum pedido).
   (3) **Horário dá vantagem**: SQL_COM_HORARIO (inc/guia.php) logo depois das fichas pagas na ordem das listas do site e do
   app, da busca e dos "parecidos"; selo "🕒 Horário informado" nos cartões do site; convite no quadro do dono: "Informando o
   horário, sua ficha aparece no Aberto agora e antes das outras". CSS v=43.
117. ✅ Títulos das fichas mais curtos e menu do admin compacto (19/11, pedido do usuário, item 4 da 3ª avaliação):
   (1) web_titulo_ficha() em inc/web.php: até 65 letras (o que o Google mostra sem cortar); tira a cidade do fim do nome
   ("Spani Atacadista São João da Boa Vista" -> "Spani Atacadista - Supermercado em São João da Boa Vista") e encurta na
   ordem "Nome - Tipo em Cidade | Acha Fácil!" -> sem a marca -> "Nome em Cidade" -> "Nome - Tipo" -> nome cortado.
   Exceção à regra do passo 103: fichas longas ficam sem " | Acha Fácil!" no título. O h1 da ficha não muda.
   (2) Menu do admin em 4 grupos + Pendências (inc/admin.php): 🏪 Comércios (Quem chamar, Comércios, Ofertas, Cupons,
   Novidades, Importar, Cidades, Mapa), 📊 Números (Números, Entradas, O que procuram, Pedidos de informação, Google),
   👥 Pessoas (Pessoas, Suporte, Notificações), ⚙️ Mais (Eventos, App, Anúncios, Registro, Senha, Sair). O contador de cada
   grupo soma as pendências de dentro; grupo e página atuais destacados; só um aberto por vez; no celular até 520 px só ícones.
119. 🟡 Recuperar o tráfego do Google (20/11, pedido do usuário): às 11:52 de 20/11 as visitas vindas do Google caíram de
   ~20-26/h para 0-3/h de uma vez (site no ar e indexado, Navegação Segura limpa, certificado ok, Googlebot normal). No mesmo
   dia entraram 59.165 fichas novas (37 cidades; total 110.895): suspeita de rebaixamento automático por conteúdo em massa.
   (1) ✅ Fichas fracas fora do Google (20/11): SQL_NO_GOOGLE em inc/guia.php = telefone/WhatsApp E (dono ou pagou, 5+
       avaliações no Google [popularidade], site/Instagram, ou visita vinda do Google nos últimos 30 dias). Usada no sitemap
       de comércios, no noindex da ficha (ficha_no_google) e em /admin/google.php. No Google: 64.658 (antes 93.474). As
       outras continuam no site e no app e voltam sozinhas. Backups em /var/lib/acha-facil/backups/*.antes-regra-google.20261120.
   (2) ✅ Pausa combinada com o usuário em 20/11: cidades novas e lotes 2–4 do Search Console parados até o tráfego voltar (~20/h por 1–2 semanas); depois 2–3 cidades/semana.
   (3) ✅ Não precisa (20/11): o Google relê os sitemaps sozinho todo dia (48–125 leituras/dia); a pausa é de não enviar nada novo. Não usar Remoções.
   (4) ✅ Páginas de cidade, área e tipo mais fortes (20/11): inc/web-conteudo.php, bloco "Sobre" no fim da página 1 (sem
       bairro nem Aberto agora), tudo com números reais da página: quantos, com WhatsApp/telefone/site, bairros com mais,
       atende em casa, horário; "Mais conhecidos" (5, por popularidade + avaliações, ordem fixa; nota do Google não aparece);
       "Veja também" (outros tipos da área); mesmo tipo nas cidades vizinhas (até 6, só onde a página existe, com km);
       perguntas frequentes respondidas com os dados (sem FAQPage no schema). Cidade: sobre, "Mais procurados" (16 tipos,
       sem "Escritório da empresa"), cidades perto (8, km em linha reta) e perguntas. CSS v=50. Testado em cópia.
   (5) ⬜ Divulgação fora do Google (Quem chamar, Instagram por cidade, app).
   (6) ⬜ Conferir toda semana: Search Console Desempenho 7 dias + Páginas; registros do servidor.
   Diagnóstico (20/11 ~15h30, prints do usuário): sem ação manual, Navegação Segura limpa, páginas indexadas, mas nem
   "achafacilapp" aparece na 1ª página em janela anônima = rebaixamento algorítmico do site inteiro. Causa provável: fichas
   copiadas do Google Maps (robô/Places API) em grande escala.
   (7) ⬜ 27/11 (agendado): revisar os números; se não voltou, montar o plano da opção D com números de cada cidade:
       mesmo domínio, modelo novo (no Google só cidades, categorias e fichas com algo próprio; 2-3 cidades trabalhadas a
       fundo com os donos; lançar o app). Domínio novo com os mesmos dados = não (repetiria a queda).
