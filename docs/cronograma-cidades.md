# Cronograma das próximas cidades

**Desde 20/11/2026 a ordem é a lista de prioridades abaixo** (pedido do usuário; substituiu as 4 levas de 17/11, que
ficam no fim só como histórico). Vale para as 65 cidades cadastradas e desligadas (amarelo em /admin/mapa.php).
Critérios: volume de turistas, pouca concorrência no Google, distância da base e porte (cidade grande = geocodificação
demorada, ~1,3 s por comércio). Cidades vizinhas na mesma prioridade: raspar juntas (uma viagem só do robô).
Fazer só quando o usuário pedir; ao terminar uma, marcar [x] aqui e na lista de passos.

**Temporada:** Ilhabela e Ubatuba precisam estar importadas até o começo de dezembro para o Google ler antes do verão.

## 🔴 Prioridade 1 — fazer primeiro
1. [x] Olímpia (SP) — cidade pequena com milhões de turistas/ano; melhor aposta
2. [x] Monte Sião (MG) — colada na base; excursões de compras o ano todo
3. [x] Jacutinga (MG) — junto com Monte Sião
4. [x] Lindóia (SP) — pequena; fecha o Circuito das Águas
5. [x] Amparo (SP) — mesmo circuito
6. [x] Ilhabela (SP) — temporada (até início de dezembro)
7. [x] Ubatuba (SP) — temporada; grande
8. [x] Capitólio (MG) — turismo em alta, comércios quase sem site
9. [x] Santo Antônio do Pinhal (SP)
10. [x] São Tomé das Letras (MG)
11. [ ] Joanópolis (SP)
12. [x] Águas de São Pedro (SP) — pequena
13. [x] São Pedro (SP) — junto com Águas de São Pedro
14. [x] Tiradentes (MG)
15. [x] Ouro Preto (MG)
16. [x] São Lourenço (MG)
17. [x] Caxambu (MG) — junto com São Lourenço

## 🟠 Prioridade 2 — em seguida
18. [ ] Atibaia (SP) — grande
19. [ ] Itu (SP) — grande
20. [ ] Embu das Artes (SP)
21. [x] Guararema (SP)
22. [ ] Boituva (SP)
23. [x] Gonçalves (MG)
24. [x] Passa Quatro (MG)
25. [x] Cunha (SP)
26. [x] São Luiz do Paraitinga (SP)
27. [x] Caraguatatuba (SP) — grande
28. [x] São Sebastião (SP)
29. [x] Mariana (MG) — junto com Ouro Preto
30. [ ] Brumadinho (MG)
31. [x] Lambari (MG)
32. [x] Baependi (MG) — ao lado de Caxambu
33. [x] Barra Bonita (SP)
34. [ ] Vinhedo (SP)
35. [x] Ouro Fino (MG)
36. [ ] Diamantina (MG) — longe
37. [ ] Araxá (MG) — grande e longe

## 🟡 Prioridade 3 — quando der
38. [x] Borda da Mata (MG)
39. [x] Cambuquira (MG)
40. [x] Maria da Fé (MG)
41. [x] Aiuruoca (MG)
42. [x] Itamonte (MG)
43. [x] São José da Barra (MG) — ao lado de Capitólio
44. [ ] São Roque de Minas (MG)
45. [ ] Delfinópolis (MG)
46. [ ] Vargem Bonita (MG)
47. [x] São João del Rei (MG) — grande
48. [x] Congonhas (MG)
49. [ ] Bananal (SP)
50. [x] Igaraçu do Tietê (SP) — ao lado de Barra Bonita
51. [ ] Peruíbe (SP)
52. [ ] Iguape (SP)
53. [ ] Ilha Comprida (SP)
54. [ ] Valinhos (SP)
55. [ ] Louveira (SP)
56. [ ] Indaiatuba (SP) — grande
57. [ ] Jundiaí (SP) — muito grande
58. [ ] Santana de Parnaíba (SP) — muito grande, região metropolitana

## ⚪ Prioridade 4 — cidades da região, sem turismo
59. [ ] Itapira (SP)
60. [ ] Mogi Mirim (SP)
61. [ ] Santo Antônio de Posse (SP)
62. [ ] Estiva Gerbi (SP)
63. [ ] São Sebastião da Grama (SP)
64. [ ] Tapiratiba (SP)
65. [ ] Itobi (SP)

---

# Histórico: levas de 17/11 (substituídas pela lista acima em 20/11)

16 cidades turísticas enviadas pelo usuário em 17/11, em 4 levas por região.

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
- [ ] Monte Alegre do Sul · [x] Águas de Lindóia (774 comércios, todos com mapa, ligada) · [x] Pedreira (19/11, 1262 comércios, todos com mapa, ligada, sitemap no ar; só 14 em "Outros") · [x] Socorro (19/11, 1661 comércios, todos com mapa, ligada)
- [ ] Santo Antônio do Pinhal · [ ] São Tomé das Letras · [ ] Joanópolis · [x] Monte Verde (17/11, 842 comércios, ligada, sitemap no Search Console)
- [ ] Águas de São Pedro · [x] Brotas (19/11, 970 importados, todos com mapa, ligada, sitemap no ar; 19 bairros com tipo "Brotas - SP" pausados → 951 no ar) · [ ] Boituva · [ ] Itu
- [ ] Guararema · [ ] Atibaia · [ ] Santana de Parnaíba · [ ] Embu das Artes

## Cidades turísticas sugeridas (20/11, pedido do usuário) — cadastradas DESLIGADAS (amarelo no mapa)
44 cidades na tabela `cidades`, `ativa = 0`, ordem 48–91, nome oficial do IBGE (o mapa casa pelo nome), centro pelo
OpenStreetMap. Backup antes: /var/lib/acha-facil/backups/cidades.antes-44-turisticas.202611200802.sql.
Ordem: ver a lista de prioridades no topo deste arquivo.

| Região | Cidades |
|---|---|
| Circuito das Malhas (MG) | Monte Sião, Jacutinga, Ouro Fino, Borda da Mata |
| Circuito das Águas (MG) | São Lourenço, Caxambu, Lambari, Cambuquira, Baependi |
| Mantiqueira mineira | Gonçalves, Passa Quatro, Maria da Fé, Aiuruoca, Itamonte |
| Vale Histórico (SP) | Cunha, São Luiz do Paraitinga, Bananal |
| Olímpia (SP) | Olímpia |
| Circuito das Frutas (SP) | Vinhedo, Valinhos, Louveira, Indaiatuba, Jundiaí |
| Rio Tietê (SP) | Barra Bonita, São Pedro, Igaraçu do Tietê |
| Mar de Minas (MG) | Capitólio, São José da Barra (Escarpas do Lago fica em Capitólio: não é município) |
| Serra da Canastra (MG) | São Roque de Minas, Delfinópolis, Vargem Bonita |
| Araxá (MG) | Araxá |
| Estrada Real (MG) | Tiradentes, São João del Rei, Mariana, Congonhas, Brumadinho (Ouro Preto e Diamantina já estavam) |
| Litoral Norte (SP) | Ubatuba, Ilhabela, São Sebastião, Caraguatatuba |
| Litoral Sul (SP) | Peruíbe, Ilha Comprida, Iguape |

- Grandes (muitos comércios, geocodificação demorada): Jundiaí, Indaiatuba, Araxá, Caraguatatuba, São João del Rei, Ubatuba.
- Litoral e Estrada Real ficam longe da base: conferir a CAIXA do geocodificador antes de importar.

## Grupos de cidades vizinhas (20/11) — `.\scripts\robo\rodar-cidades.ps1 -Grupo N`
1 ✅ (20/11, importadas e ligadas: 8.014 comércios) Lindóia, Amparo, Monte Sião, Jacutinga, Ouro Fino, Borda da Mata · 2 ✅ (20/11, ligadas: 15.490 comércios) Ilhabela, Ubatuba, Caraguatatuba, São Sebastião ·
3 ✅ (20/11, ligadas: 7.353 comércios) São Lourenço, Caxambu, Baependi, São Tomé das Letras, Lambari, Cambuquira, Aiuruoca · 4 ✅ (20/11, ligadas: 2.368) Águas de São Pedro, São Pedro ·
5 Tiradentes, São João del Rei · 6 ✅ (20/11, ligadas: 6.710) Ouro Preto, Mariana, Congonhas · 7 ✅ (20/11, ligadas: 1.455) Capitólio, São José da Barra ·
8 ✅ (20/11, ligadas: 3.188) Santo Antônio do Pinhal, Gonçalves, Maria da Fé, Passa Quatro, Itamonte · 9 Atibaia, Joanópolis · 10 Itu, Boituva, Indaiatuba ·
11 Vinhedo, Valinhos, Louveira, Jundiaí · 12 ✅ (20/11, ligadas: 3.235) Cunha, São Luiz do Paraitinga, Guararema · 13 Embu das Artes, Santana de Parnaíba ·
14 Barra Bonita, Igaraçu do Tietê · 15 São Roque de Minas, Delfinópolis, Vargem Bonita · 16 Peruíbe, Iguape, Ilha Comprida ·
17 Itapira, Mogi Mirim, Santo Antônio de Posse, Estiva Gerbi · 18 São Sebastião da Grama, Tapiratiba, Itobi ·
19 (sozinhas) Olímpia ✅ (20/11, 2.919), Brumadinho, Araxá, Diamantina, Bananal.
