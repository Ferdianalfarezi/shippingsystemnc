<?php

namespace App\Services;

use App\Models\AddressInoac;
use setasign\Fpdi\Tcpdf\Fpdi;
use Smalot\PdfParser\Element\ElementArray;
use Smalot\PdfParser\Page;
use Smalot\PdfParser\Parser as PdfTextParser;
use Smalot\PdfParser\XObject\Image as PdfImage;
use Zxing\QrReader;

/**
 * Split kanban INOAC (PT. INOAC POLYTECHNO INDONESIA).
 *
 * Beda dari FJI/NTC: teks di PDF INOAC digambar sebagai vector outline,
 * BUKAN teks — jadi gak bisa di-extract pakai pdfparser. Data per label
 * diambil dari QR code-nya (gambar JPEG), isinya:
 *     |PO|LINE|PART_NO|QTY|KANBAN_NO[XofY]|
 * contoh: |L2606547|1|BRA-00-B01|100|DNSA05613102026001[1of4]|
 *
 * Posisi tiap label juga dihitung dari posisi QR-nya di halaman
 * (grid 3 kolom x 5 baris di kertas Letter), jadi halaman terakhir
 * yang gak penuh tetap aman.
 */
class KanbanInoacRackSplitter
{
    // ============================================================
    // KOTAK LABEL RELATIF KE QR — dalam satuan "lebar QR" (QR = 1).
    // Diukur dari clip-path tiap sel di PDF sumber: label ada 1.87
    // QR di kiri titik tengah QR, 0.94 QR di atasnya, ukuran total
    // 2.48 x 2.08 QR.
    // ============================================================
    protected float $cellLeftFromQrCenter = 1.8664;
    protected float $cellTopFromQrCenter = 0.9416;
    protected float $cellWidthInQr = 2.4752;
    protected float $cellHeightInQr = 2.0768;

    // ============================================================
    // UKURAN OUTPUT — disamakan dengan kanban HPM (kanbanhpms/printnew:
    // 645 x 491 px = 170.7 x 129.9 mm). Label di-scale proporsional
    // (gak gepeng) lalu ditaruh di tengah.
    // ============================================================
    protected float $outputPageWidthMm = 170.7;
    protected float $outputPageHeightMm = 129.9;

    // ============================================================
    // URUTAN OUTPUT (sama kayak FJI)
    // 1) Kanban number / DN -> ASC
    // 2) Rack no             -> DESC
    // Label tanpa rack ditaruh paling bawah.
    // ============================================================
    protected bool $sortDnAsc = true;
    protected bool $sortRackDesc = true;

    // ============================================================
    // OVERLAY RACK NO — area kosong di bawah baris "Location - Dock",
    // kiri dari teks "1 of 4". Fraksi (0-1) relatif ke kotak label.
    // ============================================================
    protected float $overlayXFraction = 0.04;
    protected float $overlayYFraction = 0.665;
    protected float $overlayWidthFraction = 0.46;
    protected float $overlayHeightFraction = 0.12;
    protected float $overlayFontSize = 26;
    protected string $overlayFont = 'helvetica';
    protected string $overlayAlign = 'C';

    protected const MM_TO_PT = 72 / 25.4;

    /**
     * @param array<int, array{path: string, original_filename: string}> $sources
     */
    public function splitMultiple(array $sources, string $outputPath): array
    {
        $matched = 0;
        $unmatchedNoQr = 0;
        $unmatchedNoMaster = 0;
        $unmatchedNoRack = 0;
        $unmatchedDetails = [];

        $records = [];
        $seq = 0;
        $sourceFiles = [];

        // ============================================================
        // FASE 1 — SCAN: cari semua QR + posisinya, decode, lookup rack.
        // ============================================================
        foreach ($sources as $sourceIdx => $source) {
            $originalSourcePath = $source['path'];
            $originalFilename = $source['original_filename'] ?? basename($originalSourcePath);

            $resolvedSourcePath = $this->ensureReadable($originalSourcePath);
            $sourceFiles[$sourceIdx] = [
                'resolved' => $resolvedSourcePath,
                'original' => $originalSourcePath,
            ];

            $document = (new PdfTextParser())->parseFile($resolvedSourcePath);

            foreach ($document->getPages() as $pageIndex => $page) {
                $pageNo = $pageIndex + 1;
                $pageHeightPt = $this->pageHeightPt($page);

                foreach ($this->locateQrImages($page, $pageHeightPt) as $qr) {
                    $data = $this->decodeQr($qr['image']);

                    $rackNo = null;
                    $partNo = $data['part_no'] ?? null;
                    $partNoMatched = null;

                    if (!$partNo) {
                        $unmatchedNoQr++;
                        $unmatchedDetails[] = [
                            'source' => $originalFilename, 'page' => $pageNo,
                            'part_no' => null, 'reason' => 'no_qr',
                            'qr_raw' => $data['raw'] ?? null,
                        ];
                    } else {
                        // QR berisi kode INOAC (mis. HIN-01-004), bukan part no customer.
                        // Cocokin ke inoac_code dulu, fallback ke part_no.
                        $address = AddressInoac::where('inoac_code', $partNo)->first()
                            ?? AddressInoac::where('part_no', $partNo)->first();

                        if (!$address) {
                            $unmatchedNoMaster++;
                            $unmatchedDetails[] = [
                                'source' => $originalFilename, 'page' => $pageNo,
                                'part_no' => $partNo, 'reason' => 'no_master',
                            ];
                        } elseif (!$address->rack_no) {
                            $unmatchedNoRack++;
                            $unmatchedDetails[] = [
                                'source' => $originalFilename, 'page' => $pageNo,
                                'part_no' => $partNo, 'reason' => 'no_rack',
                            ];
                        } else {
                            $rackNo = $address->rack_no;
                            $partNoMatched = $address->part_no;
                            $matched++;
                        }
                    }

                    $records[] = [
                        'seq'             => $seq++,
                        'source_idx'      => $sourceIdx,
                        'source_filename' => $originalFilename,
                        'page'            => $pageNo,
                        'qr_cx'           => $qr['cx'],
                        'qr_cy'           => $qr['cy'],
                        'qr_size'         => $qr['size'],
                        'dn_no'           => $data['dn_no'] ?? null,
                        'kanban_no'       => $data['kanban_no'] ?? null,
                        'po_no'           => $data['po_no'] ?? null,
                        'qty'             => $data['qty'] ?? null,
                        'rack_no'         => $rackNo,
                        'part_no'         => $partNoMatched,
                        'part_no_raw'     => $partNo,
                    ];
                }
            }
        }

        // ============================================================
        // SORT — DN ASC, lalu rack no DESC. Null selalu paling bawah.
        // ============================================================
        usort($records, function (array $a, array $b) {
            $da = $a['dn_no'];
            $db = $b['dn_no'];

            if ($da !== $db) {
                if ($da === null) return 1;
                if ($db === null) return -1;

                $c = $this->sortDnAsc ? strnatcmp($da, $db) : strnatcmp($db, $da);
                if ($c !== 0) return $c;
            }

            $ra = $a['rack_no'];
            $rb = $b['rack_no'];

            if ($ra !== $rb) {
                if ($ra === null) return 1;
                if ($rb === null) return -1;

                $c = $this->sortRackDesc ? strnatcasecmp($rb, $ra) : strnatcasecmp($ra, $rb);
                if ($c !== 0) return $c;
            }

            return $a['seq'] <=> $b['seq'];
        });

        // ============================================================
        // FASE 2 — RENDER: crop tiap label, scale ke ukuran HPM.
        // ============================================================
        $pdf = new Fpdi('L', 'pt');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);

        $outputWidthPt  = $this->outputPageWidthMm * self::MM_TO_PT;
        $outputHeightPt = $this->outputPageHeightMm * self::MM_TO_PT;

        $labels = [];
        $outputIndex = 0;
        $templates = [];

        foreach ($records as $r) {
            $key = $r['source_idx'] . ':' . $r['page'];

            if (!isset($templates[$key])) {
                $pdf->setSourceFile($sourceFiles[$r['source_idx']]['resolved']);
                $tid = $pdf->importPage($r['page']);
                $templates[$key] = [
                    'id'   => $tid,
                    'size' => $pdf->getTemplateSize($tid),
                ];
            }

            $templateId = $templates[$key]['id'];
            $fullWidth  = $templates[$key]['size']['width'];
            $fullHeight = $templates[$key]['size']['height'];

            // kotak label di halaman sumber (pt, origin kiri-atas)
            $cellX = $r['qr_cx'] - $this->cellLeftFromQrCenter * $r['qr_size'];
            $cellY = $r['qr_cy'] - $this->cellTopFromQrCenter * $r['qr_size'];
            $cellW = $this->cellWidthInQr * $r['qr_size'];
            $cellH = $this->cellHeightInQr * $r['qr_size'];

            $scale = min($outputWidthPt / $cellW, $outputHeightPt / $cellH);
            $drawW = $cellW * $scale;
            $drawH = $cellH * $scale;
            $drawX = ($outputWidthPt - $drawW) / 2;
            $drawY = ($outputHeightPt - $drawH) / 2;

            $pdf->AddPage('L', [$outputWidthPt, $outputHeightPt]);

            // clip ke kotak label biar label tetangga gak ikut kecetak
            $pdf->StartTransform();
            $pdf->Rect($drawX, $drawY, $drawW, $drawH, 'CNZ');
            $pdf->useTemplate(
                $templateId,
                $drawX - $cellX * $scale,
                $drawY - $cellY * $scale,
                $fullWidth * $scale,
                $fullHeight * $scale
            );
            $pdf->StopTransform();

            if ($r['rack_no']) {
                $this->drawOverlay($pdf, $r['rack_no'], $drawX, $drawY, $drawW, $drawH);
            }

            $outputIndex++;

            $labels[] = [
                'output_page'     => $outputIndex,
                'source_filename' => $r['source_filename'],
                'dn_no'           => $r['dn_no'],
                'kanban_no'       => $r['kanban_no'],
                'po_no'           => $r['po_no'],
                'qty'             => $r['qty'],
                'rack_no'         => $r['rack_no'],
                'part_no'         => $r['part_no'],
                'part_no_raw'     => $r['part_no_raw'],
                'plant'           => $r['rack_no'] === null ? null : (stripos($r['rack_no'], 'K') === 0 ? 'Plant 2' : 'Plant 1'),
                'matched'         => (bool) $r['rack_no'],
            ];
        }

        if ($outputIndex === 0) {
            throw new \RuntimeException('Tidak ada QR kanban INOAC yang ketemu di PDF. Pastikan file-nya kanban INOAC.');
        }

        $pdf->Output($outputPath, 'F');

        foreach ($sourceFiles as $sf) {
            if ($sf['resolved'] !== $sf['original']) {
                @unlink($sf['resolved']);
            }
        }

        $unmatched = $unmatchedNoQr + $unmatchedNoMaster + $unmatchedNoRack;

        \Illuminate\Support\Facades\Log::info('KANBAN INOAC SPLIT DONE', [
            'output' => $outputPath,
            'file_count' => count($sources),
            'total_labels' => $outputIndex,
            'matched' => $matched,
            'unmatched' => $unmatched,
            'unmatched_no_qr' => $unmatchedNoQr,
            'unmatched_no_master' => $unmatchedNoMaster,
            'unmatched_no_rack' => $unmatchedNoRack,
        ]);

        foreach (['no_qr' => 'QR GAGAL DIBACA', 'no_master' => 'PART NO GAK ADA DI MASTER', 'no_rack' => 'RACK NO KOSONG DI MASTER'] as $reason => $title) {
            $details = array_filter($unmatchedDetails, fn ($d) => $d['reason'] === $reason);
            if (!empty($details)) {
                \Illuminate\Support\Facades\Log::info("KANBAN INOAC UNMATCHED - {$title}", [
                    'count' => count($details),
                    'part_no_list' => array_values(array_unique(array_filter(array_column($details, 'part_no')))),
                ]);
            }
        }

        return [
            'output' => $outputPath,
            'total_labels' => $outputIndex,
            'matched' => $matched,
            'unmatched' => $unmatched,
            'unmatched_no_text' => $unmatchedNoQr,
            'unmatched_no_extract' => 0,
            'unmatched_no_master' => $unmatchedNoMaster,
            'unmatched_no_rack' => $unmatchedNoRack,
            'unmatched_details' => $unmatchedDetails,
            'labels' => $labels,
        ];
    }

    protected function ensureReadable(string $sourcePath): string
    {
        try {
            $probe = new Fpdi();
            $probe->setSourceFile($sourcePath);
            return $sourcePath;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('KANBAN INOAC PDF PERLU NORMALIZE', [
                'source' => $sourcePath,
                'error' => $e->getMessage(),
            ]);

            return app(PdfNormalizer::class)->normalize($sourcePath);
        }
    }

    protected function drawOverlay(Fpdi $pdf, string $rackNo, float $labelX, float $labelY, float $labelW, float $labelH): void
    {
        $pdf->SetFont($this->overlayFont, 'B', $this->overlayFontSize);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($labelX + $this->overlayXFraction * $labelW, $labelY + $this->overlayYFraction * $labelH);
        $pdf->Cell(
            $this->overlayWidthFraction * $labelW,
            $this->overlayHeightFraction * $labelH,
            $rackNo, 0, 0, $this->overlayAlign, false, '', 1, false, 'T', 'M'
        );
    }

    /**
     * Decode QR INOAC: |PO|LINE|PART_NO|QTY|KANBAN_NO[XofY]|
     *
     * @return array{raw: ?string, po_no?: string, part_no?: string, qty?: int, kanban_no?: string, dn_no?: string}
     */
    protected function decodeQr(PdfImage $image): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'inoacqr');

        try {
            file_put_contents($tmp, $image->getContent());
            $raw = (new QrReader($tmp))->text();
        } catch (\Throwable $e) {
            $raw = false;
        } finally {
            @unlink($tmp);
        }

        if (!is_string($raw) || trim($raw) === '') {
            return ['raw' => null];
        }

        $parts = array_map('trim', explode('|', trim($raw, "| \t\r\n")));
        if (count($parts) < 5 || $parts[2] === '') {
            return ['raw' => $raw];
        }

        $kanbanNo = $parts[4];

        return [
            'raw'       => $raw,
            'po_no'     => $parts[0],
            'part_no'   => $parts[2],
            'qty'       => (int) $parts[3],
            'kanban_no' => $kanbanNo,
            // "DNSA05613102026001[1of4]" -> "DNSA05613102026001"
            'dn_no'     => preg_replace('/\[.*$/', '', $kanbanNo),
        ];
    }

    protected function pageHeightPt(Page $page): float
    {
        $box = $page->get('MediaBox');
        $values = $box instanceof ElementArray
            ? array_map(fn ($e) => (float) $e->getContent(), $box->getContent())
            : [0, 0, 612, 792];

        return (float) ($values[3] - $values[1]);
    }

    /**
     * Cari semua gambar (QR) yang digambar di halaman beserta posisinya.
     * Content stream dibaca pakai interpreter mini (q / Q / cm / Do)
     * buat ngitung CTM tiap gambar.
     *
     * @return array<int, array{image: PdfImage, cx: float, cy: float, size: float}>
     *         cx/cy = titik tengah QR dalam pt, origin kiri-atas halaman
     */
    protected function locateQrImages(Page $page, float $pageHeightPt): array
    {
        $xObjects = $page->getXObjects();
        $stream = $this->pageContentStream($page);

        $ctm = [1, 0, 0, 1, 0, 0];
        $stack = [];
        $operands = [];
        $found = [];

        foreach (preg_split('/\s+/', $stream, -1, PREG_SPLIT_NO_EMPTY) as $token) {
            if (is_numeric($token) || $token[0] === '/') {
                $operands[] = $token;
                continue;
            }

            switch ($token) {
                case 'q':
                    $stack[] = $ctm;
                    break;
                case 'Q':
                    $ctm = array_pop($stack) ?? [1, 0, 0, 1, 0, 0];
                    break;
                case 'cm':
                    $m = array_map('floatval', array_slice($operands, -6));
                    if (count($m) === 6) {
                        $ctm = $this->multiply($m, $ctm);
                    }
                    break;
                case 'Do':
                    $name = ltrim((string) end($operands), '/');
                    $image = $xObjects[$name] ?? null;
                    if ($image instanceof PdfImage) {
                        [$a, $b, $c, $d, $e, $f] = $ctm;
                        $found[] = [
                            'image' => $image,
                            'cx'    => 0.5 * $a + 0.5 * $c + $e,
                            'cy'    => $pageHeightPt - (0.5 * $b + 0.5 * $d + $f),
                            'size'  => hypot($a, $b),
                        ];
                    }
                    break;
            }

            $operands = [];
        }

        return $found;
    }

    protected function pageContentStream(Page $page): string
    {
        $contents = $page->get('Contents');

        if ($contents instanceof ElementArray) {
            return implode("\n", array_map(fn ($c) => $c->getContent(), $contents->getContent()));
        }

        return (string) $contents?->getContent();
    }

    /** CTM' = M x CTM */
    protected function multiply(array $m, array $t): array
    {
        return [
            $m[0] * $t[0] + $m[1] * $t[2],
            $m[0] * $t[1] + $m[1] * $t[3],
            $m[2] * $t[0] + $m[3] * $t[2],
            $m[2] * $t[1] + $m[3] * $t[3],
            $m[4] * $t[0] + $m[5] * $t[2] + $t[4],
            $m[4] * $t[1] + $m[5] * $t[3] + $t[5],
        ];
    }
}
