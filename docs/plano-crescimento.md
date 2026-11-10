# Acha Fácil! — plano de crescimento

Documento-base para elaborar e acompanhar as prioridades sugeridas em 10/11/2026.
Os passos práticos entram em `docs/00-passos.md` (passos 20 a 22) e são marcados lá.

Legenda: ✅ feito · 🟡 parcial · ⬜ a fazer · ❌ fora do plano

| # | Prioridade | Situação |
|---|------------|----------|
| 1 | Contador de visualizações em todas as fichas | ⬜ passo 20 |
| 2 | Versão web com páginas de cidade e categoria | ⬜ passo 21 |
| 3 | Publicar na Google Play | ✅ teste fechado aprovado (09/11); falta só pedir produção após os 14 dias com 12+ testadores (passo 11) |
| 4 | Prospecção por nicho: Saúde e Veículos | ⬜ passo 22 (depende do passo 20 ter pelo menos 2 semanas de dados) |
| 5 | Parceria com o @sanjoanenses | ❌ decidido não fazer (10/11). O Acha Fácil segue sem a chancela da página |

Ordem de execução: **1 → 2 → 4**. O contador começa a juntar dados já; enquanto os dados acumulam, faz-se a
versão web; a prospecção usa os números das duas.

---

## Situação de partida (10/11/2026)

- **10.097 comércios** em 5 cidades: São João (2.762), Vargem Grande do Sul (2.118), Poços de Caldas (1.674),
  Aguaí (1.442), Águas da Prata (101). Quase todos `importado = 1` (vindos da lista de leads).
- Áreas maiores: Alimentação 1.426 · Serviços 1.043 · **Veículos 778** · **Saúde 717** · Lojas 657 · Casa 615 · Beleza 588.
- Planos: Básico R$ 5,99 uma vez só; Destaque R$ 29,90/mês (Mercado Pago em produção desde 08/11).
- O site só tem páginas para o **dono** (busca, assumir, planos, pagamento, painel). Não existe página pública
  da ficha, da cidade ou da categoria: quem procura no Google não encontra o Acha Fácil.

### O que já se conta hoje

- Tabela `contatos` (profissional, tipo, dia, total). Tipos: `perfil`, `whatsapp`, `ligar`, `instagram`, `mapa`, `site`.
- `perfil` é gravado em `api/profissional.php` sempre que **qualquer** ficha é aberta no app — então o dado bruto
  **já existe para todas as fichas**, não só Destaque (10/11: 133 aberturas em 76 fichas desde 07/11).
- Limitações:
  1. Conta cada abertura: a mesma pessoa abrindo 10 vezes vira 10 visualizações.
  2. Não diz de onde veio (app, futuro site, busca, categoria, "Perto de você", destaque).
  3. Não conta **aparições** (a ficha mostrada numa lista ou busca sem ser aberta).
  4. Não há relatório: o admin não vê os números, e o painel do dono ainda não mostra (passo 9, "Falta").

---

## 1. Contador de visualizações em todas as fichas (passo 20)

**Objetivo:** ter, para qualquer comércio, um número confiável para abrir a conversa de venda:
"Em outubro, 84 pessoas abriram a ficha da sua oficina no Acha Fácil e 12 tocaram no WhatsApp".

### O que fazer

1. **Origem da visualização** — mudança 017: coluna `origem` em `contatos`
   (`app`, `web`, `busca`, `categoria`, `perto`, `destaque`, `favoritos`, `link`) e chave primária com ela.
   O app manda a origem ao abrir a ficha (`api/profissional.php?id=…&origem=…`); o site grava `web`.
2. **Pessoas únicas por dia** — tabela `vistas_unicas (profissional_id, dia, visitante)` com `visitante` =
   hash (SHA-256 com sal do config) de um código anônimo gerado no app na 1ª abertura (sem dado pessoal; no
   site, hash de IP + navegador do dia). `contatos` continua com o total; o relatório mostra os dois.
   Apagar `vistas_unicas` com mais de 90 dias (cron diário) — só os totais ficam.
3. **Aparições** — tabela `aparicoes (profissional_id, dia, onde, total)`: somar quando a ficha volta em
   `inicio.php`, `categoria.php`, `busca.php` e `perto.php` (uma gravação em lote por chamada, não uma por ficha,
   para não pesar na VPS de 1 GB).
4. **Filtrar robôs** no site (User-Agent de buscadores não conta) e limitar a 1 visualização por visitante por
   ficha a cada 30 min.
5. **Relatório no admin** — `/admin/numeros.php`:
   - filtros: cidade, área, tipo, período (7, 30, 90 dias), plano (sem plano / Básico / Destaque);
   - colunas: aparições, visualizações, pessoas únicas, WhatsApp, ligar, mapa, site, taxa de contato;
   - ordenar por qualquer coluna; baixar CSV (vira a lista de prospecção do item 4);
   - por ficha: gráfico por dia e de onde vieram.
6. **Ficha para mostrar ao comerciante** — `/admin/relatorio.php?id=` gera uma página/PDF de 1 folha com a marca
   do Acha Fácil: período, visualizações, contatos, posição na categoria ("3ª oficina mais vista em São João") e
   o que muda com o Destaque. É o material que vai no WhatsApp na abordagem.
7. **Painel do dono** — mostrar os números (fecha o "Falta" do passo 9): Básico vê o total do mês;
   Destaque vê tudo (por dia, origem, contatos).
8. **Privacidade** — atualizar `privacidade.php`: o app guarda um código anônimo para contar visitas;
   não liga a visita a conta, nome ou localização.

### Pronto quando

- Abrir uma ficha no app grava origem e pessoa única; abrir 5 vezes seguidas conta 1 pessoa.
- `/admin/numeros.php` mostra Saúde e Veículos de São João ordenados por visualizações e baixa o CSV.
- O relatório de uma ficha cabe numa folha e pode ser mandado como imagem/PDF.
- Precisa de APK novo (origem e código anônimo saem do app): subir o `version` do app.json.

---

## 2. Versão web com páginas de cidade e categoria (passo 21)

**Objetivo:** quem pesquisa "dentista em São João da Boa Vista" ou "oficina mecânica Aguaí" no Google cair numa
página do Acha Fácil. É o que mais traz gente nova sem gastar com anúncio, e cada visita da web também conta
no item 1.

### Endereços

| Página | Endereço | Exemplo |
|--------|----------|---------|
| Cidade | `/acha-facil/<cidade>/` | `/acha-facil/sao-joao/` |
| Área | `/acha-facil/<cidade>/<area>/` | `/acha-facil/sao-joao/saude/` |
| Tipo | `/acha-facil/<cidade>/<area>/<tipo>/` | `/acha-facil/sao-joao/saude/dentista/` |
| Ficha | `/acha-facil/<cidade>/c/<slug>` | `/acha-facil/sao-joao/c/oficina-do-ze` |
| Busca | `/acha-facil/<cidade>/buscar?q=` | (não indexada) |

As páginas atuais do dono (`busca.php`, `planos.php`, `perfil.php`…) continuam como estão.
Rotas por `.htaccess` em `servidor/publico/` (RewriteRule para `web.php`), sem mexer no Apache geral.

### Conteúdo de cada página

- **Cidade:** título "Comércios e serviços em São João da Boa Vista", áreas com o total, destaques pagos,
  os mais procurados (vem do item 1), botão do app na Play Store.
- **Área / tipo:** lista paginada (30 por página) na mesma ordem justa do app (Destaque primeiro); filtro por
  bairro; texto curto próprio da página ("97 dentistas em São João da Boa Vista…") para não ser página vazia.
- **Ficha:** nome, tipo, endereço, bairro, horário com "aberto agora", botões WhatsApp/ligar/mapa (contam no
  item 1 com origem `web`), fotos e logo se tiver plano, avaliações do app, "Este comércio é seu?" → planos,
  "Pedir para tirar do Acha Fácil" (link para `perfil.php`).
- Tudo com a marca e o visual atual do site (Nunito, verde/amarelo, cartões brancos).

### Para o Google achar

- `<title>` e `meta description` próprios em cada página; um `<h1>` por página; `canonical`.
- Dados estruturados `schema.org/LocalBusiness` na ficha e `BreadcrumbList` nas listas.
- `sitemap.xml` gerado (índice + um por cidade) e `robots.txt`; cadastrar no **Google Search Console**
  (propriedade `fsadesigners.com.br`, prefixo `/acha-facil/`).
- **Não indexar** (`noindex`): busca, páginas de tipo com menos de 3 comércios, fichas pendentes/pausadas/
  tiradas, cidade desligada. Página que não existe → 404 de verdade.
- Ligações internas: cidade → áreas → tipos → fichas; na ficha, "Outros dentistas em São João".
- Páginas leves: sem framework, HTML pronto no servidor, imagens com `loading="lazy"`.

### Cuidado com a VPS (1 GB de RAM, sistema_pm em produção)

- Cache em arquivo das páginas de lista (`/var/lib/acha-facil/cache/`, 1 h; apagado quando o admin aprova
  alteração ou liga cidade).
- Robôs de busca podem pedir milhares de páginas: `Crawl-delay` não vale para o Google, então o sitemap e o
  cache são a proteção; acompanhar `fsadesigners_access.log` na 1ª semana.

### Cuidados com os dados

- As fichas vieram do Google Maps pela lista de leads. Publicar na web aumenta a exposição: cada ficha precisa
  do link visível "Tirar meu comércio" (já existe o fluxo em `perfil.php`) e os termos devem dizer de onde vêm os
  dados. Não mostrar a nota nem o texto de avaliações do Google (regra que já vale no app).
- Só aparece o que já aparece no app (`profissional_visivel`).

### Pronto quando

- As 5 cidades, as 17 áreas e os tipos com 3+ comércios têm página; as fichas visíveis têm página.
- Sitemap enviado no Search Console sem erro; teste de dados estruturados do Google sem erro numa ficha.
- Página de lista abre em menos de 1 s com cache; a VPS não fica sem memória com 3 robôs em paralelo.
- Toques em WhatsApp/ligar no site aparecem no `/admin/numeros.php` como `web`.

---

## 3. Google Play ✅

Feito: conta, ficha, declarações e teste fechado 1.3.0 aprovado (09/11). Falta só, no passo 11, completar os
14 dias com 12+ testadores e pedir o acesso à produção. Depois disso, trocar o "em breve" pelo link da Play no
site (`link_play` no config) e nas páginas do item 2.

---

## 4. Prospecção por nicho: Saúde e Veículos (passo 22)

**Por que esses dois:** muitas fichas (717 e 778), ticket alto (consulta, tratamento, conserto, carro) — um único
cliente novo paga meses de Destaque — e boa parte sem site próprio (Saúde: 210 com site; Veículos: 142).

Tipos mais numerosos:

- **Saúde:** farmácia 106, dentista 97, ótica 74, psicólogo 37, clínica odontológica 32, drogaria 26,
  fisioterapia 23, médico 22, clínica 22.
- **Veículos:** oficina mecânica 159, lava-rápido 75, posto 68, autopeças 48+31, oficina de motos 45,
  mecânica para carros 28, pneus 25, revenda de usados 25, concessionária 24.

Começar por **dentistas/clínicas odontológicas, óticas e fisioterapia** (Saúde) e **oficinas mecânicas, autopeças
e revenda de usados** (Veículos): ticket alto e concorrência entre eles (posição na lista importa). Farmácias de
rede, postos e concessionárias de marca decidem fora da cidade: deixar para depois.

### Como medir com o robô

1. Aguardar **2 semanas** de dados do item 1 (de preferência com o item 2 no ar, que traz volume).
2. `/admin/numeros.php` → Saúde e Veículos, por cidade, 30 dias → CSV.
3. Cruzar com a lista de leads (tem site? tem WhatsApp? nº de avaliações do Google = tamanho do negócio).
4. Montar 3 grupos por tipo: **A** (muito visto, sem plano), **B** (pouco visto, negócio grande), **C** (resto).
   Abordar o A primeiro: o número já vende sozinho.

### Abordagem

- **Canal:** WhatsApp do próprio comércio, uma mensagem por vez, escrita à mão a partir de um modelo — nada de
  disparo em massa (o WhatsApp bane números por isso). Ritmo de 15 a 20 por dia.
- **Mensagem (modelo):** quem é → o número da ficha ("84 pessoas viram a [Nome] no Acha Fácil em outubro") →
  o que falta na ficha (sem foto, horário, WhatsApp) → oferta → link da ficha.
- **Oferta de entrada:** cupom de 1º mês do Destaque (ex.: 50%, criado em `/admin/cupons.php` com código por
  nicho, `SAUDE50` e `VEICULO50`, para medir qual nicho converte).
- **Quem disse não:** anotar e não insistir; quem pedir para sair → `perfil.php` (tirar do app).
- Registrar cada abordagem — planilha ou tabela `prospeccao (profissional_id, dia, canal, resultado, obs)`
  com tela simples no admin (decidir no início do passo 22).

### Metas para avaliar depois de 30 dias

| Indicador | Meta inicial |
|-----------|-------------|
| Comércios abordados | 200 (100 de cada nicho) |
| Responderam | 30% |
| Assumiram a ficha (grátis) | 15% |
| Pagaram Básico | 5% |
| Pagaram Destaque | 2% (4 comércios) |
| Destaque ainda pago no 2º mês | 75% |

Se um nicho converter claramente melhor, concentrar nele e testar o próximo (Beleza 588 e Casa e construção 615
são os candidatos seguintes).

---

## 5. Parceria com o @sanjoanenses ❌

Decidido em 10/11 não fazer. A divulgação em São João fica com o item 2 (Google) e as redes da FSA Designer.

---

## Decisões pendentes

- Item 1: o painel do dono no Básico mostra o número do mês ou só o Destaque vê números? (sugestão: Básico vê o
  total, como isca para o Destaque).
- Item 2: endereço final — continuar em `fsadesigners.com.br/acha-facil/` ou registrar domínio próprio
  (ex.: `achafacil.app.br`) antes de o Google indexar (trocar depois custa posições).
- Item 4: valor e prazo do cupom de entrada; quem faz as abordagens; registro em planilha ou no admin.
