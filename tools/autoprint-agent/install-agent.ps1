<#
    Daftarkan autoprint-agent.ps1 ke Windows Task Scheduler (jalan tiap 1 menit,
    tetap jalan walau user belum login / PC baru restart).

    Jalankan di PowerShell "Run as Administrator":
      powershell -ExecutionPolicy Bypass -File install-agent.ps1

    Hapus task:
      powershell -ExecutionPolicy Bypass -File install-agent.ps1 -Uninstall
#>
param(
    [switch]$Uninstall,
    [string]$TaskName = 'Kanban TMMIN Auto Print Agent'
)

$ErrorActionPreference = 'Stop'

if ($Uninstall) {
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
    Write-Host "Task '$TaskName' dihapus."
    exit 0
}

$agent = Join-Path $PSScriptRoot 'autoprint-agent.ps1'
if (-not (Test-Path (Join-Path $PSScriptRoot 'config.psd1'))) {
    throw 'config.psd1 belum ada. Copy config.example.psd1 jadi config.psd1 lalu isi dulu.'
}

$action = New-ScheduledTaskAction -Execute 'powershell.exe' `
    -Argument ('-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "{0}"' -f $agent) `
    -WorkingDirectory $PSScriptRoot

$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).Date `
    -RepetitionInterval (New-TimeSpan -Minutes 1) `
    -RepetitionDuration (New-TimeSpan -Days 3650)

# S4U: jalan tanpa perlu user login & tanpa jendela muncul, pakai akun user ini
$principal = New-ScheduledTaskPrincipal -UserId "$env:USERDOMAIN\$env:USERNAME" -LogonType S4U -RunLevel Limited

$settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 30) `
    -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries

Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger `
    -Principal $principal -Settings $settings -Force | Out-Null

Write-Host "Task '$TaskName' terdaftar, jalan tiap 1 menit sebagai $env:USERDOMAIN\$env:USERNAME."
Write-Host "Log: $(Join-Path $PSScriptRoot 'logs')"
