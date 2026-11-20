# Robô automático (passo 56)

O robô do Google Maps roda **no PC** (a VPS não aguenta o Chromium). O PC roda o robô sozinho de madrugada, uma
cidade por noite, e manda a lista para o site. O site compara com o que já está no app e mostra em
**Admin → Novidades**:

- 🆕 comércios **novos**
- ✏️ comércios que **mudaram** (nome, telefone, WhatsApp, endereço, bairro, Instagram, site)
- 🚫 comércios que **fecharam** de vez no Google (saem do app, ficam pausados)

Os admins recebem um aviso no celular (app, conta de admin). Com o **modo automático desligado** (padrão), a lista
espera você clicar em **Importar para o app**. Ligado, ela entra sozinha e você só recebe o aviso.
Quem assumiu o perfil (dono) nunca é mexido.

## Arquivos

| Onde | O quê |
|---|---|
| `scripts/robo/robo-config.json` | configuração do PC (copiar de `robo-config.exemplo.json`; não vai para o git) |
| `scripts/robo/robo-rodar.ps1` | roda o robô e envia (`-Cidade`, `-Todas`, `-Arquivo`, `-Testar`) |
| `scripts/robo/robo-agendar.ps1` | cria o agendamento no Windows (`-Hora 03:00`, `-Remover`) |
| `scripts/robo/robo-log.txt` | registro de cada execução |
| `servidor/publico/admin/robo-enviar.php` | recebe a lista (chave no cabeçalho `X-Chave-Robo`) |
| `servidor/publico/admin/novidades.php` | página Novidades, modo automático e chave do robô |

## Campos do robo-config.json

- `site`: `https://achafacilapp.com.br`
- `chave`: gerada em Admin → Novidades → Chave do robô (aparece uma vez só)
- `pasta_robo`: pasta onde o robô está
- `comando`: como você roda o robô para uma cidade; `{cidade}` vira o nome da cidade da lista
- `pasta_saida`: pasta onde o robô grava o `leads_*.html`
- `cidades_por_vez`: quantas cidades por execução (1 = uma por noite)
- `cidades`: a lista do rodízio

## Ponto exato no mapa (16/11, passo 63)

O "Perto de você" e o "Como chegar" usam o ponto de cada comércio. Até 16/11 só ~15% estavam no endereço exato: as
listas traziam o link `maps?cid=`, que não tem coordenadas, e o servidor procurava o endereço no OpenStreetMap (quase
sempre cai no meio da rua ou no centro do bairro).

O importador agora aceita o ponto exato de três jeitos (o primeiro que vier):
1. `data-lat="-21.97" data-lng="-46.79"` na `<tr>` da lista;
2. link do Maps com `!3d…!4d…` (o link da página do lugar);
3. link com `query=lat,lng` ou `@lat,lng` (este é só o centro do mapa, vale por último).
Ponto acertado à mão (geo_origem = manual) nunca é trocado.

**Robô da API oficial (`scripts/robo/leads_places_api.py`)**: já manda `data-lat`/`data-lng` (campo `places.location`,
que vem no mesmo pedido, sem custo a mais) e `data-ativo` (fechado de vez sai do app). No PowerShell:

    $env:GOOGLE_MAPS_API_KEY = "sua chave"
    python leads_places_api.py "Mococa" SP

**Robô de raspagem (Playwright)**: ao abrir cada lugar, guarde o endereço da página (`page.url`, que tem `!3d…!4d…`) no
link do nome, ou grave `data-lat`/`data-lng` na `<tr>`. Qualquer um dos dois já basta.

Cada cidade fica exata na próxima vez que a lista dela for importada (Admin → Importar lista, ou o rodízio do robô).

## Rodar as cidades novas em sequência (20/11/2026)
`scripts\robo\rodar-cidades.ps1` roda o `leads_places_api.py` nas 65 cidades desligadas, na ordem de prioridade do
`docs/cronograma-cidades.md`. As que já geraram lista ficam em `D:\robo-listas\feitas.txt` e são puladas da próxima vez.
`-Quantas 5` roda só as 5 próximas. O robô agora **para sem salvar** quando o Google recusa (400/401/403/429: chave
inválida, API desligada, crédito ou limite do dia), em vez de gravar uma lista pela metade, e mostra quantas pesquisas
fez (cada uma é cobrada). Compara a cidade sem acento/hífen ("São João del-Rei" = "São João del Rei") e grava o nome
como está cadastrado.
