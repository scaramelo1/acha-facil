# Acha Facil - roda o robo do Google Places nas cidades desligadas, na ordem de prioridade (docs/cronograma-cidades.md).
# 20/11/2026. Uma cidade depois da outra; as que ja geraram lista ficam em feitas.txt e sao puladas na proxima vez.
# Chave do Google: pedida na 1a vez e guardada em <Pasta>\chave-google.txt (fora do git). Trocar a chave = apagar o arquivo.
# Para tudo (sem salvar lista pela metade) se o Google recusar: chave invalida, credito acabou ou limite do dia.
#
#   .\scripts\robo\rodar-cidades.ps1                  -> todas, na ordem
#   .\scripts\robo\rodar-cidades.ps1 -Quantas 5       -> so as 5 proximas
#   .\scripts\robo\rodar-cidades.ps1 -Grupo 1         -> so as cidades vizinhas do grupo 1 (grupos 1 a 19, docs/cronograma-cidades.md)
#   .\scripts\robo\rodar-cidades.ps1 -Pasta E:\listas -> outra pasta para os arquivos (padrao D:\robo-listas)
param([int]$Quantas = 0, [int]$Grupo = 0, [string]$Pasta = 'D:\robo-listas')
$robo = Join-Path $PSScriptRoot 'leads_places_api.py'
$cidades = @(
  "Olímpia;SP",
  "Lindóia;SP",
  "Amparo;SP",
  "Monte Sião;MG",
  "Jacutinga;MG",
  "Ilhabela;SP",
  "Ubatuba;SP",
  "Capitólio;MG",
  "Santo Antônio do Pinhal;SP",
  "São Tomé das Letras;MG",
  "Joanópolis;SP",
  "Águas de São Pedro;SP",
  "São Pedro;SP",
  "Tiradentes;MG",
  "Ouro Preto;MG",
  "São Lourenço;MG",
  "Caxambu;MG",
  "Atibaia;SP",
  "Itu;SP",
  "Embu das Artes;SP",
  "Guararema;SP",
  "Boituva;SP",
  "Gonçalves;MG",
  "Passa Quatro;MG",
  "Cunha;SP",
  "São Luiz do Paraitinga;SP",
  "Caraguatatuba;SP",
  "São Sebastião;SP",
  "Mariana;MG",
  "Brumadinho;MG",
  "Lambari;MG",
  "Baependi;MG",
  "Barra Bonita;SP",
  "Vinhedo;SP",
  "Ouro Fino;MG",
  "Diamantina;MG",
  "Araxá;MG",
  "Borda da Mata;MG",
  "Cambuquira;MG",
  "Maria da Fé;MG",
  "Aiuruoca;MG",
  "Itamonte;MG",
  "São José da Barra;MG",
  "São Roque de Minas;MG",
  "Delfinópolis;MG",
  "Vargem Bonita;MG",
  "São João del Rei;MG",
  "Congonhas;MG",
  "Bananal;SP",
  "Igaraçu do Tietê;SP",
  "Peruíbe;SP",
  "Iguape;SP",
  "Ilha Comprida;SP",
  "Valinhos;SP",
  "Louveira;SP",
  "Indaiatuba;SP",
  "Jundiaí;SP",
  "Santana de Parnaíba;SP",
  "Itapira;SP",
  "Mogi Mirim;SP",
  "Santo Antônio de Posse;SP",
  "Estiva Gerbi;SP",
  "São Sebastião da Grama;SP",
  "Tapiratiba;SP",
  "Itobi;SP"
)
$grupos = @{
  1 = @("Lindóia;SP", "Amparo;SP", "Monte Sião;MG", "Jacutinga;MG", "Ouro Fino;MG", "Borda da Mata;MG")
  2 = @("Ilhabela;SP", "Ubatuba;SP", "Caraguatatuba;SP", "São Sebastião;SP")
  3 = @("São Lourenço;MG", "Caxambu;MG", "Baependi;MG", "São Tomé das Letras;MG", "Lambari;MG", "Cambuquira;MG", "Aiuruoca;MG")
  4 = @("Águas de São Pedro;SP", "São Pedro;SP")
  5 = @("Tiradentes;MG", "São João del Rei;MG")
  6 = @("Ouro Preto;MG", "Mariana;MG", "Congonhas;MG")
  7 = @("Capitólio;MG", "São José da Barra;MG")
  8 = @("Santo Antônio do Pinhal;SP", "Gonçalves;MG", "Maria da Fé;MG", "Passa Quatro;MG", "Itamonte;MG")
  9 = @("Atibaia;SP", "Joanópolis;SP")
  10 = @("Itu;SP", "Boituva;SP", "Indaiatuba;SP")
  11 = @("Vinhedo;SP", "Valinhos;SP", "Louveira;SP", "Jundiaí;SP")
  12 = @("Cunha;SP", "São Luiz do Paraitinga;SP", "Guararema;SP")
  13 = @("Embu das Artes;SP", "Santana de Parnaíba;SP")
  14 = @("Barra Bonita;SP", "Igaraçu do Tietê;SP")
  15 = @("São Roque de Minas;MG", "Delfinópolis;MG", "Vargem Bonita;MG")
  16 = @("Peruíbe;SP", "Iguape;SP", "Ilha Comprida;SP")
  17 = @("Itapira;SP", "Mogi Mirim;SP", "Santo Antônio de Posse;SP", "Estiva Gerbi;SP")
  18 = @("São Sebastião da Grama;SP", "Tapiratiba;SP", "Itobi;SP")
  19 = @("Olímpia;SP", "Brumadinho;MG", "Araxá;MG", "Diamantina;MG", "Bananal;SP")
}
if ($Grupo -gt 0) {
  if (-not $grupos.ContainsKey($Grupo)) { Write-Host "Grupo $Grupo nao existe (1 a 19)." -ForegroundColor Red; return }
  $cidades = $grupos[$Grupo]
}
New-Item -ItemType Directory -Force $Pasta | Out-Null
Set-Location $Pasta
$env:PYTHONUTF8 = '1'
# a chave fica guardada so neste PC, fora do projeto e do git (D:\robo-listas\chave-google.txt): pede uma vez so
$chaveArq = Join-Path $Pasta 'chave-google.txt'
if (-not $env:GOOGLE_MAPS_API_KEY -and (Test-Path $chaveArq)) { $env:GOOGLE_MAPS_API_KEY = (Get-Content $chaveArq -Raw).Trim() }
if (-not $env:GOOGLE_MAPS_API_KEY) {
  $env:GOOGLE_MAPS_API_KEY = (Read-Host 'Cole a chave do Google Places').Trim().Trim('"')
  if ($env:GOOGLE_MAPS_API_KEY) { Set-Content -Path $chaveArq -Value $env:GOOGLE_MAPS_API_KEY -Encoding ASCII; Write-Host "Chave guardada em $chaveArq (so neste PC)." }
}
if (-not $env:GOOGLE_MAPS_API_KEY) { Write-Host 'Sem chave.' -ForegroundColor Red; return }
$feitasArq = Join-Path $Pasta 'feitas.txt'
$feitas = @(); if (Test-Path $feitasArq) { $feitas = Get-Content $feitasArq -Encoding UTF8 }
$rodadas = 0
$fila = @($cidades | Where-Object { $feitas -notcontains $_ })
if ($Quantas -gt 0) { $fila = @($fila | Select-Object -First $Quantas) }
Write-Host "Cidades na fila: $($fila.Count)" -ForegroundColor Cyan
foreach ($linha in $cidades) {
  $nome, $uf = $linha.Split(';')
  if ($feitas -contains $linha) { continue }
  if ($Quantas -gt 0 -and $rodadas -ge $Quantas) { break }
  Write-Host "`n===== Cidade $($rodadas + 1) de $($fila.Count): $nome - $uf  (comecou $(Get-Date -Format HH:mm)) =====" -ForegroundColor Green
  python $robo $nome $uf
  if ($LASTEXITCODE -eq 2) { Write-Host "`nO Google recusou (veja a mensagem acima). Parei; $nome NAO foi salva." -ForegroundColor Red; break }
  if ($LASTEXITCODE -eq 130) { Write-Host "`nParado com Ctrl+C. $nome NAO foi salva." -ForegroundColor Yellow; break }
  if ($LASTEXITCODE -ne 0) { Write-Host "`nErro em $nome. Parei." -ForegroundColor Red; break }
  Add-Content -Path $feitasArq -Value $linha -Encoding UTF8
  $rodadas++
}
Write-Host "`nCidades rodadas agora: $rodadas. Listas em $Pasta" -ForegroundColor Green
Get-ChildItem (Join-Path $Pasta 'leads_*.html') | Sort-Object LastWriteTime | Select-Object Name, @{n='KB';e={[int]($_.Length/1KB)}}, LastWriteTime
