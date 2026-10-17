<?php

namespace App\Console\Commands;

use App\Models\KanbanTmmins;
use App\Models\KbntmminPrintLog;
use App\Models\KbntmminSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class KanbanTmminAutoPrint extends Command
{
    protected $signature = 'kanban:tmmin-auto-print';

    protected $description = 'Auto print kanban TMMIN per cycle ke printer sesuai master schedule (headless Chrome + SumatraPDF)';

    public function handle(): int
    {
        $this->cleanupOldPdf();

        $now = now();
        $nowTime = $now->format('H:i:s');
        $stamp = '[' . $now->format('Y-m-d H:i:s') . ']';

        $dueSchedules = KbntmminSchedule::where('jam_keluar', '<=', $nowTime)
            ->orderBy('jam_keluar')
            ->get();

        if ($dueSchedules->isEmpty()) {
            $this->line("{$stamp} Belum ada cycle yang waktunya keluar.");
            return self::SUCCESS;
        }

        // Semua manifest+cycle yang sudah pernah diproses (printed maupun failed) di-skip.
        // Yang failed di-retry manual lewat tombol "Print Ulang" di halaman schedule.
        $loggedKeys = KbntmminPrintLog::all(['manifest_no', 'cycle'])
            ->mapWithKeys(fn ($log) => [$log->manifest_no . '|' . $log->cycle => true])
            ->all();

        $kanbans = KanbanTmmins::all(['id', 'manifest_no', 'cycle']);

        foreach ($dueSchedules as $schedule) {
            $cycle = KbntmminSchedule::normalizeCycle($schedule->cycle);
            $jam = substr($schedule->jam_keluar, 0, 5);

            $cycleKanbans = $kanbans->filter(
                fn ($kanban) => KbntmminSchedule::normalizeCycle($kanban->cycle) === $cycle
            );

            if ($cycleKanbans->isEmpty()) {
                $this->line("{$stamp} Cycle {$cycle} ({$jam}): tidak ada data kanban dengan cycle ini, dilewati.");
                continue;
            }

            $pending = $cycleKanbans->filter(
                fn ($kanban) => !isset($loggedKeys[$kanban->manifest_no . '|' . $cycle])
            );

            if ($pending->isEmpty()) {
                $totalManifest = $cycleKanbans->pluck('manifest_no')->unique()->count();
                $this->line("{$stamp} Cycle {$cycle} ({$jam}): semua {$totalManifest} manifest sudah pernah diproses, dilewati.");
                continue;
            }

            if (empty(trim((string) $schedule->printer_name))) {
                $this->warn("{$stamp} Cycle {$cycle} ({$jam}): sudah waktunya tapi printer belum diset, dilewati.");
                continue;
            }

            $this->processCycle($stamp, $cycle, trim($schedule->printer_name), $pending);
        }

        return self::SUCCESS;
    }

    private function processCycle(string $stamp, string $cycle, string $printer, Collection $pending): void
    {
        $ids = $pending->pluck('id')->implode(',');
        $manifestNos = $pending->pluck('manifest_no')->unique()->values();

        $this->info("{$stamp} Cycle {$cycle} → {$printer} ({$manifestNos->count()} manifest, {$pending->count()} kanban)");

        $status = 'printed';
        $error = null;

        try {
            $pdfPath = $this->renderPdf($ids, $cycle);
            $this->line("  PDF: {$pdfPath}");

            $this->sendToPrinter($pdfPath, $printer);
            $this->info('  Terkirim ke printer.');
        } catch (\Throwable $e) {
            $status = 'failed';
            $error = mb_substr($e->getMessage(), 0, 1000);

            $this->error('  GAGAL: ' . $error);
            Log::error("Auto print cycle {$cycle} ke {$printer} gagal: " . $e->getMessage());
        }

        foreach ($manifestNos as $manifestNo) {
            KbntmminPrintLog::updateOrCreate(
                ['manifest_no' => $manifestNo, 'cycle' => $cycle],
                [
                    'printer_name' => $printer,
                    'status' => $status,
                    'error_message' => $error,
                    'printed_at' => now(),
                    'printed_by' => 'SCHEDULER',
                ]
            );
        }
    }

    /**
     * Buka halaman print (route auto-print.print) pakai Chrome headless
     * lalu simpan jadi PDF. Return: path file PDF.
     */
    private function renderPdf(string $ids, string $cycle): string
    {
        $chrome = config('services.auto_print.chrome_path');
        if (!is_file($chrome)) {
            throw new RuntimeException("Chrome tidak ditemukan di: {$chrome}");
        }

        $dir = $this->winPath(storage_path('app/autoprint/pdf/' . now()->format('Y-m-d')));
        File::ensureDirectoryExists($dir);

        $safeCycle = preg_replace('/[^A-Za-z0-9]/', '', $cycle) ?: 'X';
        $pdfPath = $dir . DIRECTORY_SEPARATOR . 'cycle' . $safeCycle . '_' . now()->format('His') . '.pdf';

        $profileDir = $this->winPath(storage_path('app/autoprint/chrome-profile'));
        File::ensureDirectoryExists($profileDir);

        $url = rtrim(config('services.auto_print.base_url'), '/')
            . route('kanbantmmins.autoprint.print', [
                'token' => config('services.auto_print.token'),
                'ids' => $ids,
                'pdf' => 1,
            ], false);

        $result = Process::timeout(120)->run([
            $chrome,
            '--headless=new',
            '--disable-gpu',
            '--no-first-run',
            '--no-default-browser-check',
            '--no-pdf-header-footer',
            '--run-all-compositor-stages-before-draw',
            '--virtual-time-budget=15000',
            '--user-data-dir=' . $profileDir,
            '--print-to-pdf=' . $pdfPath,
            $url,
        ]);

        if (!is_file($pdfPath) || filesize($pdfPath) === 0) {
            $detail = trim($result->errorOutput() ?: $result->output());
            throw new RuntimeException('Gagal render PDF. ' . mb_substr($detail, 0, 500));
        }

        return $pdfPath;
    }

    /**
     * Kirim PDF ke printer berdasarkan nama printer Windows.
     */
    private function sendToPrinter(string $pdfPath, string $printer): void
    {
        $sumatra = config('services.auto_print.sumatra_path');
        if (!is_file($sumatra)) {
            throw new RuntimeException("SumatraPDF tidak ditemukan di: {$sumatra}");
        }

        // Driver SATO bisa >2 menit untuk spool beberapa label
        $result = Process::timeout(600)->run([
            $sumatra,
            '-print-to', $printer,
            '-print-settings', config('services.auto_print.sumatra_settings', 'noscale'),
            '-silent',
            $pdfPath,
        ]);

        if ($result->failed()) {
            $detail = trim($result->errorOutput() ?: $result->output());
            throw new RuntimeException("SumatraPDF gagal (exit {$result->exitCode()}). " . mb_substr($detail, 0, 500));
        }
    }

    /**
     * Hapus folder PDF yang lebih lama dari X hari.
     */
    private function cleanupOldPdf(): void
    {
        $base = storage_path('app/autoprint/pdf');
        if (!is_dir($base)) {
            return;
        }

        $limit = now()->subDays(config('services.auto_print.keep_pdf_days', 7))->format('Y-m-d');

        foreach (File::directories($base) as $dir) {
            if (basename($dir) < $limit) {
                File::deleteDirectory($dir);
            }
        }
    }

    private function winPath(string $path): string
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}