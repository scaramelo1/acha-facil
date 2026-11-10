# Agente de IA que confere e publica os comércios pagos

Plano de 10/11/2026 (pedido do usuário). **Ainda não programado** — passo 28 em `00-passos.md`.

Hoje toda mudança do dono vira **alteração pendente** e só um admin publica (passo 8, `inc/moderacao.php`).
O agente faz essa conferência sozinho logo depois do pagamento aprovado: se estiver tudo certo, publica na hora;
se tiver dúvida, avisa os administradores.

---

## 1. Como funciona (do pagamento à publicação)

1. **Pagamento aprovado** → o Mercado Pago avisa o site (`mp-webhook.php`, como hoje).
2. **O site coloca o comércio numa fila de verificação** (também quando um dono que já pagou manda uma correção).
3. **O agente pega da fila** e junta: o que o dono enviou, o que já estava no app (veio do Google Maps pela lista
   de leads) e o que ele achar nas fontes externas.
4. **Compara campo por campo** e dá uma nota para cada um: ✅ confere · ⚠️ diverge · ❔ sem fonte.
5. **Decide:**
   - **Tudo certo** → publica automaticamente (pelo mesmo caminho do `aprovar_alteracao()`) e registra o motivo.
   - **Alguma dúvida** → não publica; manda aviso aos admins dizendo exatamente qual é a dúvida.
   - **Sinal de fraude** → bloqueia e avisa com prioridade.
6. **O dono é avisado:** "Seu comércio já está no ar" ou "Estamos conferindo seus dados, em breve fica pronto".

## 2. O que o agente confere

| Dado | Como confere |
|---|---|
| Nome do comércio | Google Maps / lista de leads / CNPJ |
| Endereço e bairro | Google Maps + OpenStreetMap (já usado no site) |
| Cidade | Tem que ser uma das cidades ativas do app |
| Telefone / WhatsApp | Compara com o telefone do Google; confere se o formato é válido |
| Categoria | Tipo do lugar no Google x área escolhida |
| Horário | Horário do Google x informado (pequena diferença é aceita) |
| CNPJ (se tiver) | Consulta pública da Receita (ex.: BrasilAPI): ativa, nome e endereço |
| Descrição / o que vende | Procura palavrão, golpe, link estranho, telefone de outra pessoa |
| Logo e fotos | Imagem imprópria, foto de outro comércio, sem relação com o negócio |
| Site / Instagram | Abre e vê se é mesmo daquele comércio |

## 3. Quando publica sozinho x quando avisa

**Publica sozinho** se:
- nome, endereço, cidade e telefone conferem com pelo menos **uma fonte independente**;
- nenhum texto ou imagem problemática;
- o telefone informado é o mesmo do Google (forte sinal de que quem pagou é o dono de verdade).

**Avisa os admins** se:
- o telefone é diferente do Google (risco principal: alguém paga R$ 9,90 e "assume" um comércio que não é dele);
- o endereço não bate, ou o lugar aparece como **fechado permanentemente** no Google;
- não achou o comércio em nenhuma fonte;
- categoria muito diferente da do Google;
- foto ou texto suspeito;
- o mesmo comércio já tem outro dono, ou o mesmo pagador está assumindo muitos comércios.

## 4. O aviso para os administradores

Pelo **push no app** (já existe, `inc/avisos.php`) e por e-mail:

> 🔎 **Dúvida — Padaria Pão Quente (São João)**
> ⚠️ WhatsApp informado (19 9xxxx) é diferente do Google (19 3xxx)
> ✅ Nome, endereço e categoria conferem
> [Ver no Google Maps] · [Aprovar] · [Recusar] · [Pedir correção ao dono]

No /admin entra a tela **"Fila do agente"**: publicados automaticamente, com dúvida e bloqueados, cada um com o que
o agente viu e por que decidiu.

## 5. Regras de segurança

- O agente **nunca** apaga comércio, não mexe em pagamento e não altera dados de outros comércios.
- Publica pelo mesmo caminho das alterações pendentes → tudo fica no histórico e o admin pode **desfazer**.
- **Botão liga/desliga** do agente no /admin.
- Fonte fora do ar ou com erro = não publica (vira dúvida).
- Termos e privacidade passam a dizer que os dados são conferidos de forma automática (LGPD).

## 6. Fases

| Fase | O que faz |
|---|---|
| **1. Modo sombra** (2 a 4 semanas) | O agente só **sugere**; o admin continua aprovando tudo |
| **2. Publicação parcial** | Publica sozinho só os casos mais seguros (telefone igual ao do Google + tudo conferindo) |
| **3. Completo** | Confere também fotos, CNPJ e site/Instagram |
| **4. Extra (opcional)** | Confirmação de dono: código enviado ao telefone do Google, o dono digita no site |

## 7. Modo sombra

O agente faz a verificação completa de todo comércio pago, mas **não publica nada**: só anota o que faria. O admin
aprova como hoje, e cada pendência ganha uma linha:

> 🤖 **O agente faria:** ✅ Publicar — nome, endereço e telefone conferem com o Google Maps

O sistema guarda as duas decisões lado a lado:

| Agente | Admin | Significado |
|---|---|---|
| Publicaria | Aprovou | ✅ Acertou |
| Perguntaria | Recusou | ✅ Acertou (pegou o problema) |
| Perguntaria | Aprovou | 🟡 Cuidado demais — só dá trabalho ao admin |
| **Publicaria** | **Recusou** | 🔴 **Erro perigoso** — teria publicado algo errado ou fraude |

**Para sair do modo sombra:**
- pelo menos **30 comércios** verificados;
- **nenhum** "Publicaria / Recusou" nos últimos 20;
- a maioria dos casos certos sendo publicada sem perguntar.

Um quadro no /admin mostra esses números; quando estiverem bons, liga a publicação automática no botão.

## 8. Custos

### IA (a maior parte)

Preços da Anthropic (Claude) em 10/11/2026, US$ por milhão de tokens:

| Modelo | Lê | Escreve | Uso |
|---|---|---|---|
| **Opus 5.5** | US$ 4 | US$ 20 | Decisões mais confiáveis |
| **Sonnet 5.5** | US$ 2 | US$ 10 | Metade do preço, ainda muito bom |
| **Haiku 5.5** | US$ 0,10 | US$ 0,50 | Bem mais barato, erra mais em casos difíceis |

Estimativa por verificação (dados + fontes + logo e até 10 fotos), dólar ~R$ 5,50:

| Modelo | Sem fotos | Com logo + 10 fotos |
|---|---|---|
| Opus 5.5 | ~R$ 0,50 | ~R$ 0,85 |
| Sonnet 5.5 | ~R$ 0,25 | ~R$ 0,45 |
| Haiku 5.5 | ~R$ 0,02 | ~R$ 0,05 |

Por mês (Opus 5.5 com fotos): 30 pedidos ~R$ 25 · 100 ~R$ 85 · 300 ~R$ 255.
No Básico (R$ 9,90) é ~9% do valor; no Destaque roda ao assumir e quando o dono muda algo, não todo mês.

São estimativas: o sistema deve guardar o gasto real de cada verificação.

**Para gastar menos:** reverificar só os campos que mudaram; Sonnet 5.5 para tudo e Opus 5.5 só na dúvida;
o agente só roda para quem pagou.

### Google Maps (Places API)

Cota grátis por mês por tipo de consulta (na tabela conhecida, entre mil e dez mil); ~2 consultas por verificação →
na prática **R$ 0** até algumas centenas de pedidos/mês. Exige cartão no Google Cloud. Antes de ligar: confirmar a
tabela atual e pôr **limite de gasto** na conta.

### Resto

- OpenStreetMap, BrasilAPI (CNPJ) e lista de leads: grátis.
- Servidor: a VPS atual (o trabalho pesado é feito na Anthropic).
- IA paga com créditos em dólar (console.anthropic.com, cartão internacional + IOF); pôr limite mensal lá também.

**Recomendação:** começar no modo sombra com o **Opus 5.5** (acertar importa mais que economizar nessa fase);
depois, com os números reais, decidir se troca para o Sonnet 5.5.

## 9. O que precisa ser programado (quando for a hora)

- Servidor: tabela da fila e do resultado de cada verificação (mudança nova), processo que roda a cada minuto,
  chamada à IA e às fontes, tela "Fila do agente" e quadro do modo sombra no /admin, botão liga/desliga.
- Chaves no `/etc/acha-facil/config.php`: Anthropic e Google Places.
- App: só se quiser avisar o dono pelo app (hoje o dono é avisado pelo site/e-mail).

## 10. Decisões pendentes (do usuário)

1. **Google Places API** (cartão no Google Cloud)? Com ela a conferência fica bem mais confiável; sem ela, só lista
   de leads + OpenStreetMap.
2. **Telefone diferente do Google:** sempre vai para os admins, ou faz a confirmação por código (fase 4)?
3. **Prazo** para os admins responderem uma dúvida antes de o dono receber "em análise".
4. Vale para **os três planos** (Básico, Destaque, Destaque + Site) ou só alguns?
5. Começar pelo **modo sombra**? (recomendado)
