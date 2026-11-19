# Cronograma das próximas cidades (pedido do usuário em 17/11/2026)

16 cidades turísticas enviadas pelo usuário. Divididas em 4 levas por região (o robô do PC raspa uma região por vez) e da
menor para a maior dentro de cada leva (as grandes levam mais tempo para geocodificar, ~1,3 s por comércio).
Nenhuma estava na tabela `cidades` em 17/11.

| Leva | Semana | Região | Cidades (ordem) |
|---|---|---|---|
| 1 | 18 a 21/11 | Circuito das Águas (perto da base) | Monte Alegre do Sul (SP), Águas de Lindóia (SP), Pedreira (SP), Socorro (SP) |
| 2 | 24 a 28/11 | Serra da Mantiqueira | Santo Antônio do Pinhal (SP), São Tomé das Letras (MG), Joanópolis (SP), Monte Verde (MG) |
| 3 | 01 a 05/12 | Interior turístico | Águas de São Pedro (SP), Brotas (SP), Boituva (SP), Itu (SP) |
| 4 | 08 a 12/12 | Grande SP e arredores | Guararema (SP), Atibaia (SP), Santana de Parnaíba (SP), Embu das Artes (SP) |

Porte (para planejar o tempo): pequenas = Monte Alegre do Sul, Águas de São Pedro, Santo Antônio do Pinhal,
São Thomé das Letras, Joanópolis, Águas de Lindóia; médias = Pedreira, Socorro, Brotas, Boituva, Guararema;
grandes (vários milhares de comércios) = Atibaia, Itu, Santana de Parnaíba, Embu das Artes.

## Cadastro (17/11, pedido do usuário) ✅
As 16 estão na tabela `cidades`, **desligadas**, com o centro pelo OpenStreetMap (backup da tabela em
/var/lib/acha-facil/backups/cidades.antes-16-novas.*.sql). Slugs: monte-alegre-do-sul, aguas-de-lindoia, pedreira,
socorro, santo-antonio-do-pinhal, sao-tome-das-letras, joanopolis, monte-verde, aguas-de-sao-pedro, brotas, boituva, itu,
guararema, atibaia, santana-de-parnaiba, embu-das-artes.
- São Thomé das Letras: o IBGE escreve **São Tomé das Letras** (nome oficial usado).
- **Monte Verde** (decisão do usuário): cadastrada com esse nome, UF MG, centro do distrito (-22.872, -46.067). Importador
  corta "Camanducaia" do endereço, e só corta o nome da cidade quando ele abre um pedaço do endereço ("Av. Monte Verde, 100"
  fica inteira).

## Atenção
- Monte Verde não é município (distrito de Camanducaia): no robô, raspar "Monte Verde, Camanducaia - MG".
- Cidades grandes da leva 4 são longe da base (região metropolitana): conferir a CAIXA do geocodificador e o raio de
  "Cidade atual" (25 km) para não confundir com cidades vizinhas.

## Passos de cada cidade (sem mexer em código desde o passo 19)
1. ~~Cadastrar desligada~~ (feito para as 16 em 17/11).
2. PC: robô raspa a cidade → /admin/importar.php (ou Novidades) → conferência (--teste); se muita coisa em "Outros",
   chamar o Claude para ajustar PALAVRAS_AREA / NAO_COMERCIO.
3. Importar de verdade (backup automático) → geocodificar em segundo plano (log em /var/lib/acha-facil/geocodificar.log).
4. Conferir algumas fichas e o mapa → **ligar** a cidade → limpar cache → Search Console: pedir indexação da página da cidade.
5. Marcar aqui ✅ e na lista de passos.

## Andamento
- [ ] Monte Alegre do Sul · [x] Águas de Lindóia (774 comércios, todos com mapa, ligada) · [x] Pedreira (19/11, 1262 comércios, todos com mapa, ligada, sitemap no ar; só 14 em "Outros") · [ ] Socorro
- [ ] Santo Antônio do Pinhal · [ ] São Tomé das Letras · [ ] Joanópolis · [x] Monte Verde (17/11, 842 comércios, ligada, sitemap no Search Console)
- [ ] Águas de São Pedro · [x] Brotas (19/11, 970 importados, todos com mapa, ligada, sitemap no ar; 19 bairros com tipo "Brotas - SP" pausados → 951 no ar) · [ ] Boituva · [ ] Itu
- [ ] Guararema · [ ] Atibaia · [ ] Santana de Parnaíba · [ ] Embu das Artes
