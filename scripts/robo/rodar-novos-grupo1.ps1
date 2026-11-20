# Acha Facil - USO UNICO (20/11/2026): o grupo 1 foi rodado antes dos termos novos (igrejas, cemiterios, velorios,
# orgaos publicos). Este script pesquisa SO esses 25 termos nas 6 cidades do grupo 1 e grava leads_<cidade>_novos.html
# em D:\robo-listas, ao lado das listas que ja existem. Nao mexe no feitas.txt.
# Importar: em Admin > Importar lista, envie JUNTOS os dois arquivos da cidade (leads_lindóia.html + leads_lindóia_novos.html).
#
#   .\scripts\robo\rodar-novos-grupo1.ps1
param([string]$Pasta = 'D:\robo-listas')
$robo = Join-Path $PSScriptRoot 'leads_places_api.py'
$cidades = @("Lindóia;SP", "Amparo;SP", "Monte Sião;MG", "Jacutinga;MG", "Ouro Fino;MG", "Borda da Mata;MG")
New-Item -ItemType Directory -Force $Pasta | Out-Null
Set-Location $Pasta
$env:PYTHONUTF8 = '1'
$chaveArq = Join-Path $Pasta 'chave-google.txt'
if (-not $env:GOOGLE_MAPS_API_KEY -and (Test-Path $chaveArq)) { $env:GOOGLE_MAPS_API_KEY = (Get-Content $chaveArq -Raw).Trim() }
if (-not $env:GOOGLE_MAPS_API_KEY) { Write-Host "Sem chave: rode antes o rodar-cidades.ps1 uma vez (ele guarda a chave)." -ForegroundColor Red; return }
$i = 0
foreach ($linha in $cidades) {
  $i++
  $nome, $uf = $linha.Split(';')
  Write-Host "`n===== Termos novos $i de $($cidades.Count): $nome - $uf  (comecou $(Get-Date -Format HH:mm)) =====" -ForegroundColor Green
  python $robo $nome $uf --so-novos
  if ($LASTEXITCODE -eq 130) { Write-Host "`nParado com Ctrl+C. Rode de novo para refazer." -ForegroundColor Yellow; break }
  if ($LASTEXITCODE -eq 2) { Write-Host "`nO Google recusou (veja acima). Parei." -ForegroundColor Red; break }
  if ($LASTEXITCODE -ne 0) { Write-Host "`nErro em $nome. Parei." -ForegroundColor Red; break }
}
Write-Host "`nArquivos (envie os 2 de cada cidade JUNTOS em Admin > Importar lista):" -ForegroundColor Green
Get-ChildItem (Join-Path $Pasta 'leads_*.html') | Sort-Object Name | Select-Object Name, @{n='KB';e={[int]($_.Length/1KB)}}, LastWriteTime
