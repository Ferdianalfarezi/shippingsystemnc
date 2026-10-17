<#
    Auto Print Agent - Kanban TMMIN

    Dijalankan Task Scheduler tiap 1 menit di PC yang terhubung ke printer.
    Alur per run:
      1. Kirim ulang laporan "printed" yang sempat gagal terkirim (pending-mark.json)
      2. Tanya server cycle mana yang sudah waktunya keluar untuk printer ini (/check)
      3. Render halaman print jadi PDF pakai Edge headless (pdf=1 -> ukuran pas kanban)
      4. Print ke printer berdasarkan nama pakai SumatraPDF
      5. Lapor ke server (/mark-printed) -> manifest ditandai printed

    Kalau langkah 3/4 gagal, tidak dilapor ke server -> otomatis dicoba lagi menit berikutnya.
    Konfigurasi: config.psd1 di folder yang sama (lihat config.example.psd1).

    Tes tanpa print & tanpa lapor ke server:
      powershell -ExecutionPolicy Bypass -File autoprint-agent.ps1 -DryRun
#>
param(
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$root = $PSScriptRoot
$logDir = Join-Path $root 'logs'
$pdfRoot = Join-Path $root 'pdf'
$pendingFile = Join-Path $root 'pending-mark.json'
New-Item -ItemType Directory -Force -Path $logDir, $pdfRoot | Out-Null

$logFile = Join-Path $logDir ('agent-' + (Get-Date -Format 'yyyy-MM-dd') + '.log')

function Write-Log([string]$message, [string]$level = 'INFO') {
    $line = '[{0}] {1} {2}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $level, $message
    Add-Content -Path $logFile -Value $line -Encoding UTF8
    Write-Host $line
}

function Quote([string]$value) {
    return '"' + $value + '"'
}

# ---------- Konfigurasi ----------
$configFile = Join-Path $root 'config.psd1'
if (-not (Test-Path $configFile)) {
    Write-Log "config.psd1 tidak ditemukan di $root (copy dari config.example.psd1 lalu isi)" 'ERROR'
    exit 1
}

$config = Import-PowerShellDataFile $configFile
$serverUrl = ([string]$config.ServerUrl).TrimEnd('/')
$token = [string]$config.Token
$printer = [string]$config.Printer
$edgePath = [string]$config.EdgePath
$sumatraPath = [string]$config.SumatraPath
$sumatraSettings = [string]$config.SumatraSettings
$keepDays = if ($config.KeepDays) { [int]$config.KeepDays } else { 7 }

$baseApi = "$serverUrl/kanbantmmins/auto-print"

# ---------- Bersih-bersih file lama ----------
$limit = (Get-Date).AddDays(-$keepDays)
Get-ChildItem $pdfRoot -Directory -ErrorAction SilentlyContinue | Where-Object { $_.LastWriteTime -lt $limit } | Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
Get-ChildItem $logDir -File -ErrorAction SilentlyContinue | Where-Object { $_.LastWriteTime -lt $limit.AddDays(-7) } | Remove-Item -Force -ErrorAction SilentlyContinue

# ---------- Lapor printed ke server ----------
function Send-MarkPrinted($mark) {
    $body = @{
        token        = $token
        printer      = $printer
        cycle        = [string]$mark.cycle
        manifest_nos = @($mark.manifest_nos)
        source       = 'AGENT'
    } | ConvertTo-Json -Compress

    Invoke-RestMethod -Uri "$baseApi/mark-printed" -Method Post -ContentType 'application/json' `
        -Headers @{ Accept = 'application/json' } -Body ([Text.Encoding]::UTF8.GetBytes($body)) -TimeoutSec 30 | Out-Null
}

# Laporan yang gagal terkirim sebelumnya harus beres dulu, supaya tidak print dobel
if (Test-Path $pendingFile) {
    $pending = Get-Content $pendingFile -Raw -Encoding UTF8 | ConvertFrom-Json
    try {
        Send-MarkPrinted $pending
        Remove-Item $pendingFile -Force
        Write-Log "Laporan tertunda cycle $($pending.cycle) berhasil dikirim ke server"
    } catch {
        Write-Log "Server masih belum bisa dihubungi untuk laporan tertunda cycle $($pending.cycle): $($_.Exception.Message)" 'ERROR'
        exit 1
    }
}

# ---------- Cek prasyarat ----------
if (-not (Get-Printer -Name $printer -ErrorAction SilentlyContinue)) {
    Write-Log "Printer '$printer' tidak ter-install di PC ini" 'ERROR'
    exit 1
}
foreach ($exe in @($edgePath, $sumatraPath)) {
    if (-not (Test-Path $exe)) {
        Write-Log "File tidak ditemukan: $exe" 'ERROR'
        exit 1
    }
}

# ---------- Proses cycle yang due (maks 10 per run) ----------
for ($i = 0; $i -lt 10; $i++) {
    $checkUrl = "$baseApi/check?token=$([uri]::EscapeDataString($token))&printer=$([uri]::EscapeDataString($printer))"

    try {
        $check = Invoke-RestMethod -Uri $checkUrl -Headers @{ Accept = 'application/json' } -TimeoutSec 30
    } catch {
        Write-Log "Gagal cek jadwal ke server: $($_.Exception.Message)" 'ERROR'
        exit 1
    }

    if ($check.error) {
        Write-Log "Server: $($check.error)" 'ERROR'
        exit 1
    }

    if (-not $check.due) {
        break
    }

    $cycle = [string]$check.cycle
    $ids = @($check.ids) -join ','
    $manifestNos = @($check.manifest_nos)
    $info = "cycle $cycle (jam $($check.jam_keluar)) -> $printer ($($manifestNos.Count) manifest, $(@($check.ids).Count) kanban)"
    Write-Log "Mulai $info"

    try {
        # 1. Pastikan halaman print OK (bukan halaman error/403) sebelum dijadikan PDF
        $printUrl = "$baseApi/print?token=$([uri]::EscapeDataString($token))&ids=$ids&pdf=1"
        $probe = Invoke-WebRequest -Uri $printUrl -UseBasicParsing -TimeoutSec 60
        if ($probe.StatusCode -ne 200 -or $probe.Content -notmatch '</html>') {
            throw "Halaman print tidak valid (HTTP $($probe.StatusCode))"
        }

        # 2. Render PDF pakai Edge headless
        $pdfDir = Join-Path $pdfRoot (Get-Date -Format 'yyyy-MM-dd')
        New-Item -ItemType Directory -Force -Path $pdfDir | Out-Null
        $safeCycle = ($cycle -replace '[^A-Za-z0-9]', '')
        $pdfPath = Join-Path $pdfDir ("cycle{0}_{1}.pdf" -f $safeCycle, (Get-Date -Format 'HHmmss'))
        $profileDir = Join-Path $root 'edge-profile'

        $edgeArgs = @(
            '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
            '--no-pdf-header-footer', '--run-all-compositor-stages-before-draw', '--virtual-time-budget=15000',
            ('--user-data-dir=' + (Quote $profileDir)),
            ('--print-to-pdf=' + (Quote $pdfPath)),
            (Quote $printUrl)
        )
        $edge = Start-Process -FilePath $edgePath -ArgumentList $edgeArgs -PassThru -WindowStyle Hidden
        if (-not $edge.WaitForExit(120000)) {
            $edge.Kill()
            throw 'Edge tidak selesai render PDF dalam 2 menit'
        }
        if (-not (Test-Path $pdfPath) -or (Get-Item $pdfPath).Length -eq 0) {
            throw 'PDF gagal dibuat oleh Edge'
        }
        Write-Log "PDF: $pdfPath"

        if ($DryRun) {
            Write-Log "DRY RUN: tidak di-print & tidak dilapor ke server" 'WARN'
            break
        }

        # 3. Print pakai SumatraPDF
        $sumatraArgs = @('-print-to', (Quote $printer), '-print-settings', (Quote $sumatraSettings), '-silent', (Quote $pdfPath))
        $sumatra = Start-Process -FilePath $sumatraPath -ArgumentList $sumatraArgs -PassThru -WindowStyle Hidden
        if (-not $sumatra.WaitForExit(600000)) {
            $sumatra.Kill()
            throw 'SumatraPDF tidak selesai dalam 10 menit'
        }
        if ($sumatra.ExitCode -ne 0) {
            throw "SumatraPDF gagal (exit $($sumatra.ExitCode))"
        }
        Write-Log 'Terkirim ke printer'
    } catch {
        Write-Log "GAGAL $info : $($_.Exception.Message) (dicoba lagi menit berikutnya)" 'ERROR'
        exit 1
    }

    # 4. Lapor printed. Kalau gagal, simpan dulu supaya run berikutnya tidak print ulang.
    $mark = @{ cycle = $cycle; manifest_nos = $manifestNos }
    try {
        Send-MarkPrinted $mark
        Write-Log "Selesai $info"
    } catch {
        ($mark | ConvertTo-Json -Compress) | Set-Content -Path $pendingFile -Encoding UTF8
        Write-Log "Sudah ter-print tapi gagal lapor ke server, disimpan untuk dikirim ulang: $($_.Exception.Message)" 'ERROR'
        exit 1
    }
}

exit 0
