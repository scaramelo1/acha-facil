# Acha Facil - robo automatico (passo 56). Roda o robo do Google Maps no PC e manda a lista pronta para o site,
# que mostra em Admin > Novidades os comercios novos, os que mudaram e os que fecharam (e avisa no celular).
#
#   .\scripts\robo\robo-rodar.ps1                      -> proxima cidade do rodizio (o que o agendador roda)
#   .\scripts\robo\robo-rodar.ps1 -Cidade "Mococa - SP" -> so essa cidade
#   .\scripts\robo\robo-rodar.ps1 -Todas               -> todas as cidades da lista, uma depois da outra
#   .\scripts\robo\robo-rodar.ps1 -Arquivo C:\...\leads_mococa.html   -> so envia um arquivo pronto (sem rodar o robo)
#   .\scripts\robo\robo-rodar.ps1 -Testar              -> so confere a configuracao e a chave (nao roda nem envia nada)
#
# Configuracao: scripts\robo\robo-config.json (copie de robo-config.exemplo.json). Registro: scripts\robo\robo-log.txt
# Agendar no Windows: .\scripts\robo\robo-agendar.ps1   Explicado em docs/robo-automatico.md
param(
  [string]$Cidade = '',
  [switch]$Todas,
  [string]$Arquivo = '',
  [switch]$Testar
)
$ErrorActionPreference = 'Continue'   # 'Stop' faria o PowerShell 5 parar com qualquer aviso do python/curl
$aqui = $PSScriptRoot
$log = Join-Path $aqui 'robo-log.txt'
$estadoArq = Join-Path $aqui 'robo-estado.json'

function Log([string]$msg, [string]$cor = 'Gray') {
  $linha = (Get-Date -Format 'dd/MM/yyyy HH:mm:ss') + '  ' + $msg
  Write-Host $linha -ForegroundColor $cor
  Add-Content -Path $log -Value $linha -Encoding UTF8
}
function Parar([string]$msg) { Log "ERRO: $msg" 'Red'; exit 1 }

# ---------- configuracao ----------
$cfgArq = Join-Path $aqui 'robo-config.json'
if (-not (Test-Path $cfgArq)) { Parar "Falta $cfgArq. Copie robo-config.exemplo.json para robo-config.json e preencha (docs/robo-automatico.md)." }
$cfg = Get-Content $cfgArq -Raw -Encoding UTF8 | ConvertFrom-Json
foreach ($c in 'site', 'chave', 'pasta_robo', 'comando', 'pasta_saida') {
  if (-not $cfg.$c) { Parar "robo-config.json sem o campo `"$c`"." }
}
if ($cfg.chave -like 'COLE_AQUI*') { Parar 'Coloque a chave do robo no robo-config.json (gere em Admin > Novidades > Chave do robo).' }
$endereco = $cfg.site.TrimEnd('/') + '/admin/robo-enviar.php'
$curl = (Get-Command curl.exe -ErrorAction SilentlyContinue).Source
if (-not $curl) { Parar 'Nao achei o curl.exe (vem no Windows 10/11). Atualize o Windows.' }

# guarda o registro curto (ultimas 2000 linhas)
if ((Test-Path $log) -and ((Get-Item $log).Length -gt 500KB)) {
  $ult = Get-Content $log -Encoding UTF8 | Select-Object -Last 2000
  Set-Content -Path $log -Value $ult -Encoding UTF8
}

# ---------- envio ----------
function Enviar([string[]]$arquivos, [switch]$SoChave) {
  $opc = @('-sS', '--max-time', '1500', '-w', '\n%{http_code}', '-H', "X-Chave-Robo: $($cfg.chave)")
  foreach ($a in $arquivos) { $opc += @('-F', "arquivos[]=@$a;type=text/html") }
  if ($arquivos.Count -eq 0) { $opc += @('-F', 'teste=1') }   # so testa a chave (POST sem arquivo)
  $opc += $endereco
  for ($tentativa = 1; $tentativa -le 3; $tentativa++) {
    $saida = (& $curl @opc 2>&1 | Out-String).Trim()
    $linhas = $saida -split "`n"
    $codigo = $linhas[-1].Trim()
    $texto = (($linhas | Select-Object -SkipLast 1) -join ' ').Trim()
    if ($codigo -match '^\d{3}$' -and $codigo -ne '000') {
      if ($codigo -eq '200') { Log "Site: $texto" 'Green'; return $true }
      if ($SoChave -and $codigo -eq '400') { return $true }   # chave aceita (so faltou o arquivo, de proposito)
      Log "Site respondeu $codigo : $texto" 'Red'
      return $false
    }
    Log "Falha de rede ao enviar (tentativa $tentativa de 3): $saida" 'Yellow'
    Start-Sleep -Seconds (30 * $tentativa)
  }
  return $false
}

if ($Testar) {
  Log "Testando a chave em $endereco ..."
  if (Enviar @() -SoChave) { Log 'Chave OK: o site aceita os envios deste PC.' 'Green' } else { Parar 'O site recusou (veja a mensagem acima).' }
  foreach ($c in 'pasta_robo', 'pasta_saida') {
    if (-not (Test-Path -LiteralPath $cfg.$c -PathType Container)) { Parar "A pasta `"$($cfg.$c)`" (campo $c) nao existe." }
    Log "Pasta OK ($c): $($cfg.$c)" 'Green'
  }
  Log "Comando do robo: $($cfg.comando)"
  exit 0
}

if ($Arquivo) {
  if (-not (Test-Path $Arquivo)) { Parar "Arquivo nao existe: $Arquivo" }
  Log "Enviando $Arquivo ..."
  if (-not (Enviar @((Resolve-Path $Arquivo).Path))) { exit 1 }
  exit 0
}

# ---------- rodar o robo ----------
$cidades = @($cfg.cidades)
if (-not $Cidade -and $cidades.Count -eq 0) { Parar 'robo-config.json sem a lista "cidades".' }
$estado = if (Test-Path $estadoArq) { Get-Content $estadoArq -Raw | ConvertFrom-Json } else { [pscustomobject]@{ proxima = 0 } }

if ($Cidade) { $fila = @($Cidade) }
elseif ($Todas) { $fila = $cidades }
else {
  $n = [Math]::Max(1, [int]($cfg.cidades_por_vez))
  $fila = @()
  for ($i = 0; $i -lt $n; $i++) { $fila += $cidades[([int]$estado.proxima + $i) % $cidades.Count] }
}

# as pastas precisam existir antes de rodar (senao o robo rodaria na pasta errada)
foreach ($c in 'pasta_robo', 'pasta_saida') {
  if (-not (Test-Path -LiteralPath $cfg.$c -PathType Container)) {
    Parar "A pasta `"$($cfg.$c)`" (campo $c do robo-config.json) nao existe. Coloque a pasta onde o robo esta (docs/robo-automatico.md)."
  }
}

$erros = 0
foreach ($cid in $fila) {
  Log "=== $cid : rodando o robo ===" 'Cyan'
  $inicio = (Get-Date).AddSeconds(-5)
  $cmd = $cfg.comando.Replace('{cidade}', $cid)
  Push-Location -LiteralPath $cfg.pasta_robo -ErrorAction Stop
  try {
    & cmd.exe /c $cmd 2>&1 | ForEach-Object { Add-Content -Path $log -Value ('    ' + $_) -Encoding UTF8 }
    $rc = $LASTEXITCODE
  } finally { Pop-Location }
  if ($rc -ne 0) { Log "O robo terminou com erro (codigo $rc) em $cid. Veja as linhas acima no robo-log.txt." 'Red'; $erros++; continue }

  # listas HTML que o robo criou/atualizou agora
  $novos = @(Get-ChildItem -Path $cfg.pasta_saida -Filter '*.html' -Recurse -ErrorAction SilentlyContinue |
    Where-Object { $_.LastWriteTime -ge $inicio } | Sort-Object LastWriteTime)
  $novos = @($novos | Where-Object { (Get-Content $_.FullName -Raw -Encoding UTF8) -match '<tr data-status=' })
  if ($novos.Count -eq 0) { Log "O robo nao gerou nenhuma lista nova em $($cfg.pasta_saida) para $cid." 'Red'; $erros++; continue }
  Log ("Enviando " + (($novos | ForEach-Object { $_.Name }) -join ', ') + ' ...')
  if (-not (Enviar @($novos | ForEach-Object { $_.FullName }))) { $erros++ }
}

# avanca o rodizio (so no modo normal)
if (-not $Cidade -and -not $Todas) {
  $estado.proxima = ([int]$estado.proxima + $fila.Count) % $cidades.Count
  $estado | ConvertTo-Json | Set-Content -Path $estadoArq -Encoding UTF8
  Log "Proxima vez: $($cidades[$estado.proxima])"
}
if ($erros) { exit 1 }
