# Acha Fácil! — publicar na Play Store e na App Store

Pacote/bundle: `br.com.fsadesigner.achafacil` · Projeto EAS: `scaramelo/acha-facil` · versão 1.1.0

## 0. Antes de tudo (contas e custos)

| Loja | Conta | Custo | Observação |
|---|---|---|---|
| Google Play | https://play.google.com/console | US$ 25, uma vez | Conta **pessoal** nova: precisa de **teste fechado com 12 testadores por 14 dias** antes de liberar a produção. Conta de **organização** (precisa de CNPJ + número D-U-N-S) não tem essa exigência |
| App Store | https://developer.apple.com/programs | US$ 99 por ano | Não precisa de Mac: o EAS compila e envia o iOS na nuvem |

## 1. Links que as lojas pedem

- Política de privacidade: https://fsadesigners.com.br/acha-facil/privacidade.php
- Excluir conta (Play Store): https://fsadesigners.com.br/acha-facil/privacidade.php#excluir-conta
- Termos: https://fsadesigners.com.br/acha-facil/termos.php
- Site / suporte: https://fsadesigners.com.br/acha-facil/ · e-mail fsadesignerbr@gmail.com

## 2. Textos da ficha

**Nome:** Acha Fácil! (App Store: até 30 caracteres; se o nome estiver ocupado, usar "Acha Fácil - Comércio Local")

**Subtítulo (App Store, até 30):** O comércio da sua cidade

**Descrição curta (Play Store, até 80):** Ache comércios e serviços da sua cidade e chame no WhatsApp em um toque.

**Descrição completa:**

> O comércio da sua cidade, pertinho de você.
>
> O Acha Fácil! reúne os comércios e serviços de São João da Boa Vista, Águas da Prata (SP) e Poços de Caldas (MG) em um só lugar. Procure por categoria ou pelo nome, veja quem está perto de você e fale direto no WhatsApp.
>
> • Mais de 4.500 comércios em 17 categorias: alimentação, mercados, farmácias, beleza, pet shops, oficinas, saúde e muito mais
> • Perto de você: lista do mais perto ao mais longe, usando a localização só quando você pede
> • Busca com filtros por categoria e bairro
> • Avaliações e comentários de quem já foi
> • Favoritos para guardar os seus preferidos
> • Não precisa de conta para procurar e chamar no WhatsApp
>
> Tem um comércio? Procure o seu negócio no app, assuma o perfil de graça ou conheça os planos para ganhar mais destaque.

**Palavras-chave (App Store, até 100):** comércio,guia,lojas,serviços,whatsapp,são joão da boa vista,poços de caldas,águas da prata

**Categoria:** Estilo de vida (Play: "Estilo de vida"; Apple: "Lifestyle"); secundária na Apple: "Navegação" ou "Negócios"

## 3. Imagens

- Ícone: `assets/icon.png` (1024×1024; a Play pede 512×512, gerar com `convert assets/icon.png -resize 512x512 icone-512.png`)
- Play Store: 2 a 8 capturas de celular (ex.: 1080×1920) + **imagem de destaque 1024×500**
- App Store: capturas de **iPhone 6,9"** (1320×2868 ou 1290×2796); como `supportsTablet` está ligado, também **iPad 13"** (2064×2752). Se não quiser fazer as do iPad, desligar `supportsTablet` no app.json
- Telas sugeridas: Início, Categorias, comércio com botão do WhatsApp, Perto de você, Busca com filtros

## 4. Formulários

**Segurança dos dados (Play) / Privacidade do app (Apple):**
- Coleta: nome e e-mail (conta), avaliações e comentários (conteúdo do usuário), localização aproximada/precisa (só no momento, para "Perto de você", **não é guardada**)
- Finalidade: funcionalidade do app e gerenciamento da conta
- Não compartilha com terceiros, não tem anúncios, não rastreia (Apple: "Not used for tracking")
- Dados criptografados em trânsito: sim (HTTPS) · Usuário pode pedir exclusão: sim
- Login com Google: o e-mail/nome vêm do Google, mesma finalidade

**Classificação etária:** questionário IARC (Play) e Apple → sem violência, sem conteúdo adulto; marcar que tem **conteúdo gerado por usuários** (comentários) com moderação → fica Livre/4+ (Apple pode dar 12+ por causa dos comentários)

**Anúncios:** "não contém anúncios" · **Público-alvo:** 18+ (evita as regras de apps infantis)

**Exportação/criptografia (Apple):** já respondido no app.json (`usesNonExemptEncryption: false`)

## 5. Revisão da Apple: pontos de atenção

- **Conta de demonstração:** a Apple pede login e senha de teste nas notas da revisão (a conta precisa funcionar: passo 7)
- **Pagamentos:** a tela de Planos só abre um e-mail para a FSA (sem preço e sem link de pagamento dentro do app). Manter assim no iOS: link para pagar fora do app pode ser recusado (regra 3.1.1)
- **Login com Google:** o app já tem "Entrar com Google", então a Apple exige também **"Entrar com Apple"** (regra 4.8). Sem isso o iOS é recusado: ou adiciona o botão da Apple, ou esconde o Google no iOS
- **Comentários:** precisa haver como denunciar comentário e o admin ocultar (regra 1.2); a ocultação está no passo 8
- **Dados dos comércios:** vêm do Google Maps; deixar claro no app/termos que o dono pode corrigir ou tirar do app (já existe)

## 6. Comandos (PC, PowerShell, em C:\Users\Fellipe\acha-facil)

```powershell
git pull
npm install
npx eas login

# Android: gera o .aab (formato da Play Store)
npx eas build -p android --profile production
# 1º envio é manual: Play Console > criar app > Teste fechado (ou interno) > enviar o .aab
# depois, com a chave de conta de serviço do Google configurada:
npx eas submit -p android --latest

# iOS: o EAS pede login da Apple e cria certificados sozinho
npx eas build -p ios --profile production
npx eas submit -p ios --latest     # vai para o TestFlight; depois "Enviar para revisão" no App Store Connect
```
