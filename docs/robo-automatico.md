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
