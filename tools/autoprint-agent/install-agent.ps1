<#
    Daftarkan autoprint-agent.ps1 ke Windows Task Scheduler (jalan tiap 1 menit).

    Jalankan:
      powershell -ExecutionPolicy Bypass -File install-agent.ps1

    - Run as Administrator : task tetap jalan walau user belum login (S4U)
    - Tanpa Administrator  : task jalan selama user ini login (tanpa jendela muncul)

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

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
    [Security.Principal.WindowsBuiltInRole]::Administrator)
$userId = "$env:USERDOMAIN\$env:USERNAME"

if ($isAdmin) {
    # S4U: jalan tanpa perlu user login & tanpa jendela muncul
    $action = New-ScheduledTaskAction -Execute 'powershell.exe' `
        -Argument ('-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "{0}"' -f $agent) `
        -WorkingDirectory $PSScriptRoot
    $principal = New-ScheduledTaskPrincipal -UserId $userId -LogonType S4U -RunLevel Limited
    $mode = 'tetap jalan walau user belum login'
} else {
    # Interactive: lewat wscript supaya jendela PowerShell tidak kedip tiap menit
    $launcher = Join-Path $PSScriptRoot 'run-hidden.vbs'
    $vbs = @(
        'Set sh = CreateObject("WScript.Shell")'
        ('sh.Run "powershell.exe -NoProfile -ExecutionPolicy Bypass -File ""{0}""", 0, True' -f $agent)
    )
    Set-Content -Path $launcher -Value $vbs -Encoding ASCII

    $action = New-ScheduledTaskAction -Execute 'wscript.exe' -Argument ('"{0}"' -f $launcher) -WorkingDirectory $PSScriptRoot
    $principal = New-ScheduledTaskPrincipal -UserId $userId -LogonType Interactive -RunLevel Limited
    $mode = 'jalan selama user ini login (install ulang sebagai Administrator supaya jalan tanpa login)'
}

$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).Date `
    -RepetitionInterval (New-TimeSpan -Minutes 1) `
    -RepetitionDuration (New-TimeSpan -Days 3650)

$settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 30) `
    -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries

Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger `
    -Principal $principal -Settings $settings -Force | Out-Null

Write-Host "Task '$TaskName' terdaftar, jalan tiap 1 menit sebagai $userId ($mode)."
Write-Host "Log: $(Join-Path $PSScriptRoot 'logs')"
