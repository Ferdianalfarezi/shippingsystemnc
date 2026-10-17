<?php

namespace App\Console\Commands;

use App\Models\KbntmminPrintLog;
use App\Models\KbntmminSchedule;
use App\Services\KanbanTmminAutoPrintPlanner;
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

        $allSchedules = KbntmminSchedule::orderBy('jam_keluar')->get();
        $dueSchedules = $allSchedules->filter(fn ($schedule) => $schedule->jam_keluar <= $nowTime);

        if ($dueSchedules->isEmpty()) {
            $this->line("{$stamp} Belum ada cycle yang waktunya keluar.");
            return self::SUCCESS;
        }

        // Kanban dipasangkan ke schedule per dock + cycle. Manifest+cycle yang sudah pernah
        // diproses (printed maupun failed) di-skip; yang failed di-retry lewat "Print Ulang".
        $plan = app(KanbanTmminAutoPrintPlanner::class)->plan($dueSchedules, $allSchedules);

        foreach ($plan as $row) {
            $schedule = $row['schedule'];
            $cycle = KbntmminSchedule::normalizeCycle($schedule->cycle);
            $label = $schedule->label . ' (' . substr($schedule->jam_keluar, 0, 5) . ')';

            if ($row['kanbans']->isEmpty()) {
                $this->line("{$stamp} {$label}: tidak ada data kanban, dilewati.");
                continue;
            }

            if ($row['pending']->isEmpty()) {
                $totalManifest = $row['kanbans']->pluck('manifest_no')->unique()->count();
                $this->line("{$stamp} {$label}: semua {$totalManifest} manifest sudah pernah diproses, dilewati.");
                continue;
            }

            if (empty(trim((string) $schedule->printer_name))) {
                $this->warn("{$stamp} {$label}: sudah waktunya tapi printer belum diset, dilewati.");
                continue;
            }

            $this->processCycle($stamp, $schedule->label, $cycle, trim($schedule->printer_name), $row['pending']);
        }

        return self::SUCCESS;
    }

    private function processCycle(string $stamp, string $label, string $cycle, string $printer, Collection $pending): void
    {
        $ids = $pending->pluck('id')->implode(',');
        $manifestNos = $pending->pluck('manifest_no')->unique()->values();

        $this->info("{$stamp} {$label} → {$printer} ({$manifestNos->count()} manifest, {$pending->count()} kanban)");

        $status = 'printed';
        $error = null;

        try {
            $pdfPath = $this->renderPdf($ids, $label);
            $this->line("  PDF: {$pdfPath}");

            $this->sendToPrinter($pdfPath, $printer);
            $this->info('  Terkirim ke printer.');
        } catch (\Throwable $e) {
            $status = 'failed';
            $error = mb_substr($e->getMessage(), 0, 1000);

            $this->error('  GAGAL: ' . $error);
            Log::error("Auto print {$label} ke {$printer} gagal: " . $e->getMessage());
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
    private function renderPdf(string $ids, string $label): string
    {
        $chrome = config('services.auto_print.chrome_path');
        if (!is_file($chrome)) {
            throw new RuntimeException("Chrome tidak ditemukan di: {$chrome}");
        }

        $dir = $this->winPath(storage_path('app/autoprint/pdf/' . now()->format('Y-m-d')));
        File::ensureDirectoryExists($dir);

        // mis. "Dock 4P cycle 4" -> Dock_4P_cycle_4_120300.pdf (dock beda tidak saling timpa)
        $safeLabel = trim(preg_replace('/[^A-Za-z0-9]+/', '_', $label), '_') ?: 'cycle';
        $pdfPath = $dir . DIRECTORY_SEPARATOR . $safeLabel . '_' . now()->format('His') . '.pdf';

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