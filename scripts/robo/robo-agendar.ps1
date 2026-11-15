# Acha Facil - agenda o robo automatico no Agendador de Tarefas do Windows (passo 56).
#   .\scripts\robo\robo-agendar.ps1                 -> todo dia as 03:00, uma cidade por vez (rodizio)
#   .\scripts\robo\robo-agendar.ps1 -Hora 02:30     -> outro horario
#   .\scripts\robo\robo-agendar.ps1 -Remover        -> tira o agendamento
# O PC precisa estar ligado (ou em suspensao: ele acorda) e com o seu usuario conectado no Windows.
param(
  [string]$Hora = '03:00',
  [switch]$Remover
)
$nome = 'Acha Facil - robo'
$script = Join-Path $PSScriptRoot 'robo-rodar.ps1'

if ($Remover) {
  Unregister-ScheduledTask -TaskName $nome -Confirm:$false -ErrorAction SilentlyContinue
  Write-Host "Agendamento '$nome' removido." -ForegroundColor Green
  exit 0
}
if (-not (Test-Path (Join-Path $PSScriptRoot 'robo-config.json'))) {
  Write-Host 'Antes, crie o robo-config.json (copie robo-config.exemplo.json) e rode: .\scripts\robo\robo-rodar.ps1 -Testar' -ForegroundColor Red
  exit 1
}

$acao = New-ScheduledTaskAction -Execute 'powershell.exe' `
  -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$script`"" `
  -WorkingDirectory (Split-Path -Parent (Split-Path -Parent $PSScriptRoot))
$quando = New-ScheduledTaskTrigger -Daily -At $Hora
$opcoes = New-ScheduledTaskSettingsSet -WakeToRun -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
  -ExecutionTimeLimit (New-TimeSpan -Hours 6) -MultipleInstances IgnoreNew
$quem = New-ScheduledTaskPrincipal -UserId ([System.Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType Interactive -RunLevel Limited

Register-ScheduledTask -TaskName $nome -Action $acao -Trigger $quando -Settings $opcoes -Principal $quem -Force | Out-Null
Write-Host "Pronto: '$nome' roda todo dia as $Hora (uma cidade por vez)." -ForegroundColor Green
Write-Host 'Para testar agora: Start-ScheduledTask -TaskName "Acha Facil - robo"   (e veja scripts\robo\robo-log.txt)'
