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
- Responsável: **FSA Designer** (termos e privacidade); contatos fsadesignerbr@gmail.com e Instagram @fsa.designer
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
7. 🟡 Contas no app só com o Google (07/11): o app pede uma entrada ao servidor (api/google.php, acao=iniciar), abre
   /google/entrar.php no navegador, a pessoa entra no Google e /google/retorno.php grava a conta (nome, e-mail, foto);
   enquanto isso o app pergunta ao servidor a cada 1,5 s (acao=verificar, chave secreta só do app) e recebe a sessão.
   Não depende de o celular devolver um link para o app (no emulador o link caía no APK antigo). Sem senha:
   "Recuperar senha", entrar.php e criar-conta.php saíram. Termos e privacidade atualizados. Chaves do Google no
   config e migração 006 aplicadas; servidor testado de ponta a ponta. Emulador ✅ (07/11 19:30: entrou e recebeu a sessão).
   Falta: (a) testar no APK novo (o do site é o 1.0.0, ainda com e-mail e senha); (b) ✅ tela de consentimento OAuth
   "Em produção" (09/11, Branding preenchido sem logo; falta confirmar entrando com uma conta Google qualquer);
   (c) para a App Store: "Entrar com Apple" junto (regra 4.8 da Apple), precisa da conta de desenvolvedor Apple
8. 🟡 Administração web em /admin (07/11): **toda mudança do dono passa por um admin** (contra fraude). Feito: entrar
   (admins; criar com `php servidor/criar-admin.php email "Nome"`, senha sorteada e mostrada uma vez), Pendências
   (alterações dos donos com "como está x como vai ficar", aprovar/recusar com motivo; pedidos de assumir/tirar do app),
   o dono que **paga entra no painel sozinho** (botão "Editar os dados do meu comércio" na página da assinatura; o
   pagamento não precisa de aprovação, só as alterações). Na 1ª alteração de quem entrou pelo pagamento o admin vê o
   aviso para ligar no telefone do cadastro antes de aprovar. Link manual do painel para casos sem pagamento (cortesia),
   cortar acesso, tirar do app (status pausado) e Registro de tudo que cada admin fez. Tabelas na mudança 008
   (acessos_dono, alteracoes, registro_admin); regras em servidor/inc/moderacao.php (só aprovar_alteracao() grava os
   dados do dono). Falta: total de downloads do APK (`/var/lib/acha-facil/apk/downloads.txt`), cadastrar negócio novo,
   selo, cortesia, categorias, ocultar comentários
9. 🟡 Painel do dono em /painel (07/11): entra pela página da assinatura (?c=código do pedido pago) ou pelo link do admin; com plano em dia pede mudança de nome, sobre, serviços,
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
11. 🟡 Publicar nas lojas (Play Store e App Store): guia, textos e formulários em `docs/lojas.md`; app.json pronto
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

20. 🟡 Contador de visualizações em todas as fichas (10/11): código feito e testado numa cópia isolada.
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
21. 🟡 Versão web com páginas de cidade, área, tipo e ficha (10/11, decisões do usuário: nome completo da cidade no
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
22. ⬜ Prospecção Saúde e Veículos: 2 semanas de dados do passo 20 → CSV → grupos A/B/C → WhatsApp manual
   (15–20/dia) com cupom por nicho → metas de 30 dias
23. ⬜ "Recomendados" só com plano Básico e só perto (10/11, pedido do usuário): hoje a faixa "Recomendados em <cidade>"
   da tela inicial sorteia entre os 80 mais procurados (populares() em inc/guia.php), com ou sem plano. Vai passar a mostrar
   **só quem pagou o Básico** (`basico_pago_em`; quem tem Destaque já está na faixa dele) e **só os que estão perto da
   pessoa** (localização do celular, como no "Perto de você"), não sempre, para a seção não ficar lotada. Sem localização
   ou sem ninguém do Básico por perto, a faixa não aparece. "Comércios em destaque" continua **só com quem assina o Destaque**
   (passo 4g). Decidir: raio (sugestão 2 km) e quantos no máximo (sugestão 10). Precisa de APK novo (a tela inicial passa a
   mandar a posição)
- Google Play: já em teste fechado (passo 11). Parceria com o @sanjoanenses: decidido não fazer (10/11)
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
26. 🟡 Plano **Destaque + Site** e Básico a R$ 9,90 (10/11, pedido do usuário): 3º plano, chave `site`, R$ 89,90/mês
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
