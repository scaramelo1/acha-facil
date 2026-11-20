# Acha Facil - envia TODAS as listas do robo de uma vez para o site e ja importa (20/11/2026).
# Pega cada leads_<cidade>.html de D:\robo-listas (junto com o leads_<cidade>_novos.html, se houver), manda para
# /admin/robo-enviar.php com a chave do robo, o site descobre a cidade pelo arquivo, importa, procura o mapa e liga a cidade.
# As listas enviadas vao para D:\robo-listas\enviadas (nao sao mandadas de novo).
#
#   .\scripts\robo\enviar-listas.ps1               -> envia, importa e LIGA todas no app (20/11, pedido do usuario)
#   .\scripts\robo\enviar-listas.ps1 -NaoLigar     -> envia e importa, mas deixa as cidades desligadas
#   .\scripts\robo\enviar-listas.ps1 -SoConferir   -> so manda para Admin > Novidades (voce aprova uma por uma)
#
# Chave do robo: gere em Admin > Novidades > "Chave do robo" (mostrada uma vez so). O script pede na 1a vez e guarda em
# D:\robo-listas\chave-robo.txt (so neste PC). Chave nova = apague esse arquivo.
param([string]$Pasta = 'D:\robo-listas', [switch]$SoConferir, [switch]$NaoLigar, [string]$Site = 'https://achafacilapp.com.br')
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8   # respostas do site com acento ("São" saía "S├úo")
$curl = (Get-Command curl.exe -ErrorAction SilentlyContinue).Source
if (-not $curl) { Write-Host 'Nao achei o curl.exe (vem no Windows 10/11).' -ForegroundColor Red; return }
$chaveArq = Join-Path $Pasta 'chave-robo.txt'
if (Test-Path $chaveArq) { $chave = (Get-Content $chaveArq -Raw).Trim() }
else {
  $chave = (Read-Host 'Cole a chave do robo (Admin > Novidades > Chave do robo)').Trim().Trim('"')
  if (-not $chave) { Write-Host 'Sem chave.' -ForegroundColor Red; return }
  Set-Content -Path $chaveArq -Value $chave -Encoding ASCII
}
$enviadas = Join-Path $Pasta 'enviadas'
New-Item -ItemType Directory -Force $enviadas | Out-Null

# agrupa: leads_lindóia.html + leads_lindóia_novos.html = uma cidade. Vale leads_ e leads- (robo antigo: leads-socorro-sp.html)
$grupos = [ordered]@{}
foreach ($f in Get-ChildItem (Join-Path $Pasta 'leads*.html') | Sort-Object Name) {
  $base = $f.BaseName.ToLower() -replace '\s*\(\d+\)$', '' -replace '[-_ ](novos|completo)$', '' -replace '[-_ ](sp|mg)$', '' -replace '[-_ ](novos|completo)$', '' -replace '^leads[-_ ]*', ''
  if (-not $grupos.Contains($base)) { $grupos[$base] = @() }
  $grupos[$base] += $f
}
if ($grupos.Count -eq 0) { Write-Host "Nenhuma lista leads_*.html em $Pasta." -ForegroundColor Yellow; return }
Write-Host "Listas para enviar: $($grupos.Count) cidades" -ForegroundColor Cyan
foreach ($b in $grupos.Keys) { Write-Host "  $b : $($grupos[$b].Name -join ' + ')" }

$i = 0; $ok = 0; $erros = @()
foreach ($base in $grupos.Keys) {
  $i++
  $arqs = $grupos[$base]
  Write-Host "`n[$i de $($grupos.Count)] $base  ($($arqs.Name -join ' + '))  $(Get-Date -Format HH:mm)" -ForegroundColor Green
  # copia com nome sem acento (o curl.exe do Windows se perde com "ó" no caminho); o site acha a cidade pelo conteudo
  $temps = @(); $n = 0
  foreach ($a in $arqs) { $n++; $t = Join-Path $env:TEMP "acha-facil-lista-$i-$n.html"; Copy-Item $a.FullName $t -Force; $temps += $t }
  $parametros = @('-sS', '--max-time', '1800', '-w', "`n%{http_code}", '-H', "X-Chave-Robo: $chave")
  for ($k = 0; $k -lt $temps.Count; $k++) {
    $nomeSeguro = ($arqs[$k].Name -replace '[;"]', '')   # o nome original vai junto: o site usa para achar a cidade se o arquivo nao disser
    $parametros += @('-F', "arquivos[]=@$($temps[$k]);type=text/html;filename=$nomeSeguro")
  }
  if (-not $SoConferir) { $parametros += @('-F', 'importar=1') }
  if (-not $SoConferir -and -not $NaoLigar) { $parametros += @('-F', 'ligar=1') }
  $parametros += "$Site/admin/robo-enviar.php"
  Write-Host '  Enviando e importando (cidade grande pode levar alguns minutos)...'
  $resp = & $curl @parametros 2>&1 | Out-String
  $temps | ForEach-Object { Remove-Item $_ -Force -ErrorAction SilentlyContinue }
  $linhas = $resp.TrimEnd() -split "`n"
  $codigo = $linhas[-1].Trim()
  $texto = ($linhas[0..($linhas.Count - 2)] -join "`n").Trim()
  if ($codigo -eq '200') {
    Write-Host "  $texto"
    foreach ($a in $arqs) { Move-Item $a.FullName (Join-Path $enviadas $a.Name) -Force }
    $ok++
  } else {
    Write-Host "  ERRO ($codigo): $texto" -ForegroundColor Red
    $erros += "$base ($codigo): $texto"
    if ($codigo -eq '403') { Write-Host '  Chave do robo errada: apague chave-robo.txt, gere outra em Admin > Novidades e rode de novo.' -ForegroundColor Red; break }
  }
}
Write-Host "`nEnviadas: $ok de $($grupos.Count)." -ForegroundColor Cyan
if ($erros) { Write-Host 'Com erro (continuam na pasta para tentar de novo):' -ForegroundColor Red; $erros | ForEach-Object { Write-Host "  $_" } }
if (-not $SoConferir -and -not $NaoLigar) { Write-Host 'Cidades importadas ja estao LIGADAS no app. Envie os sitemaps mostrados acima no Search Console.' -ForegroundColor Yellow }
elseif (-not $SoConferir) { Write-Host 'As cidades continuam DESLIGADAS: ligue em Admin > Cidades.' -ForegroundColor Yellow }
