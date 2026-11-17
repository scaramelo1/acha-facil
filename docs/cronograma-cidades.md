# Cronograma das próximas cidades (pedido do usuário em 17/11/2026)

16 cidades turísticas enviadas pelo usuário. Divididas em 4 levas por região (o robô do PC raspa uma região por vez) e da
menor para a maior dentro de cada leva (as grandes levam mais tempo para geocodificar, ~1,3 s por comércio).
Nenhuma estava na tabela `cidades` em 17/11.

| Leva | Semana | Região | Cidades (ordem) |
|---|---|---|---|
| 1 | 18 a 21/11 | Circuito das Águas (perto da base) | Monte Alegre do Sul (SP), Águas de Lindóia (SP), Pedreira (SP), Socorro (SP) |
| 2 | 24 a 28/11 | Serra da Mantiqueira | Santo Antônio do Pinhal (SP), São Thomé das Letras (MG), Joanópolis (SP), Monte Verde (MG) |
| 3 | 01 a 05/12 | Interior turístico | Águas de São Pedro (SP), Brotas (SP), Boituva (SP), Itu (SP) |
| 4 | 08 a 12/12 | Grande SP e arredores | Guararema (SP), Atibaia (SP), Santana de Parnaíba (SP), Embu das Artes (SP) |

Porte (para planejar o tempo): pequenas = Monte Alegre do Sul, Águas de São Pedro, Santo Antônio do Pinhal,
São Thomé das Letras, Joanópolis, Águas de Lindóia; médias = Pedreira, Socorro, Brotas, Boituva, Guararema;
grandes (vários milhares de comércios) = Atibaia, Itu, Santana de Parnaíba, Embu das Artes.

## Atenção
- **Monte Verde não é município**: é distrito de **Camanducaia (MG)**. Decidir antes da leva 2: cadastrar como
  "Monte Verde" (slug próprio, coordenadas do distrito, CAIXA do Nominatim só do distrito) ou como Camanducaia inteira.
  Sugestão: "Monte Verde" com o nome que o turista procura no Google.
- Cidades grandes da leva 4 são longe da base (região metropolitana): conferir a CAIXA do geocodificador e o raio de
  "Cidade atual" (25 km) para não confundir com cidades vizinhas.

## Passos de cada cidade (sem mexer em código desde o passo 19)
1. /admin/cidades.php: cadastrar **desligada** (nome, UF, lat/lng).
2. PC: robô raspa a cidade → /admin/importar.php (ou Novidades) → conferência (--teste); se muita coisa em "Outros",
   chamar o Claude para ajustar PALAVRAS_AREA / NAO_COMERCIO.
3. Importar de verdade (backup automático) → geocodificar em segundo plano (log em /var/lib/acha-facil/geocodificar.log).
4. Conferir algumas fichas e o mapa → **ligar** a cidade → limpar cache → Search Console: pedir indexação da página da cidade.
5. Marcar aqui ✅ e na lista de passos.

## Andamento
- [ ] Monte Alegre do Sul · [ ] Águas de Lindóia · [ ] Pedreira · [ ] Socorro
- [ ] Santo Antônio do Pinhal · [ ] São Thomé das Letras · [ ] Joanópolis · [ ] Monte Verde
- [ ] Águas de São Pedro · [ ] Brotas · [ ] Boituva · [ ] Itu
- [ ] Guararema · [ ] Atibaia · [ ] Santana de Parnaíba · [ ] Embu das Artes
