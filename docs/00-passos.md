# Acha Fácil! — passos

App para achar profissionais e comércios de **São João da Boa Vista**.
Lema: *"O comércio da sua cidade, pertinho de você."*
Criado em 07/11/2026 a partir do **Guia Sanjoanense** (`/var/www/guia-sanjoanense`), sem a chancela da página
Sanjoanenses e sem a aba Notícias.

- **App:** Expo (TypeScript), na raiz do repositório (`src/`), pacote `br.com.fsadesigner.achafacil`.
- **Servidor:** PHP 8.3 + MariaDB em `servidor/`, publicado em https://fsadesigners.com.br/acha-facil/
  (`bash servidor/instalar.sh` como root; config em `/etc/acha-facil/config.php`; banco `acha_facil`).
- **Pagamento:** Mercado Pago, assinatura recorrente no cartão (o cartão é digitado no Mercado Pago).
- **Marca:** verde `#005C38` e amarelo `#F7C600`; símbolo (pino + loja + lupa) em `assets/logo-simbolo.svg`.
- **Visual próprio (07/11):** fundo creme, cabeçalho verde com saudação e busca amarela, barra de abas flutuante
  (Início, Áreas, Salvos, Conta), cada área com a sua cor, cartões com faixa colorida. Site com fonte Nunito e o mesmo estilo.

Legenda: ✅ feito · 🟡 parcial · ⬜ a fazer

## Regras combinadas

- Navegar, buscar e chamar no WhatsApp **não pede conta**. A conta (e-mail e senha) serve para avaliar,
  comentar e salvar profissionais. A pessoa pode excluir a conta pelo app (exigência das lojas).
- Uma avaliação por pessoa por profissional (pode mudar depois). O comentário mostra só "Maria S.".
- O profissional aparece se estiver **aprovado** e com a **assinatura em dia** (ou cortesia dada pelo admin).
- Ordem justa: destaques primeiro, depois quem tem avaliação; dentro de cada grupo a ordem muda uma vez por dia.
- Selo "Verificado pelo Acha Fácil" é dado pelo admin, não vem com o pagamento.
- Os comércios vêm da **lista de leads** (fsadesigners.com.br/leads, coleta do Google Maps): `importado = 1`, aparecem
  **sem assinatura** até o dono assumir o perfil. Reimportar: `php servidor/importar-leads.php` (atualiza pelo nome; não mexe em
  quem assumiu nem em quem foi tirado). Os 10 exemplos fictícios foram apagados.
- O dono procura o negócio em `/busca.php` e pede para **assumir** (grátis) ou **tirar do app** (`/perfil.php`); os pedidos
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
5. ⬜ Testar no PC (`npm install`, `npx tsc --noEmit`, `npx expo lint`, emulador)
6. 🟡 Projeto EAS próprio (ligado em 07/11 ✅) e 1º APK (falta); publicar com `bash servidor/publicar-apk.sh <link do EAS>`
7. ⬜ Contas no app: conferir se entrar/criar conta funciona (no Guia Sanjoanense isso ainda está em aberto)
8. ⬜ Administração web: pedidos de assumir/tirar do app, aprovar/recusar cadastros, editar, selo, cortesia, categorias, ocultar comentários
9. ⬜ Painel do profissional (web): autocadastro, foto, dados, serviços, responder avaliações, ver contatos recebidos
10. ⬜ Assinatura recorrente no Mercado Pago (planos Básico e Destaque; webhook; atraso tira do ar)
11. ⬜ Publicar nas lojas (Play Store e App Store) — conferir se o nome "Acha Fácil" está livre nas duas

## Decisões pendentes

- Quem é o dono/responsável do Acha Fácil (aparece nos termos e na privacidade) e os contatos
  (`contato_email`, `contato_whatsapp`, `instagram` no config).
- Arquivo da logo em alta (PNG/SVG) para trocar o redesenho, se quiser fidelidade total à arte.
- Preço dos planos Básico e Destaque (mensal) e quantos lugares no carrossel de destaque.
- Se volta a ter notícias no futuro.
