<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Kanban TMMIN P2</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    @php
        // ===== Sumber data =====
        // print()       -> $note (single)
        // printAll/Sel  -> $orderedItems (sudah urut Plant 1 lalu Plant 2)
        // printGroup    -> $kanbantmmins
        if (isset($orderedItems)) {
            $itemsToProcess = $orderedItems->values();
            $isSingleItem = false;
        } elseif (isset($kanbantmmins)) {
            $itemsToProcess = $kanbantmmins->values();
            $isSingleItem = false;
        } elseif (isset($note)) {
            $itemsToProcess = collect([$note]);
            $isSingleItem = true;
        } else {
            $itemsToProcess = collect();
            $isSingleItem = false;
        }

        $isPreview = request()->boolean('preview');
        $showPlantSeparator = $showPlantSeparator ?? false;
        $separatorAfterIndex = $separatorAfterIndex ?? -1;

        // ===== Run out: 1 query untuk semua item =====
        $runOutUniqueNos = \DB::table('run_outs')
            ->whereIn('unique_no', $itemsToProcess->pluck('unique_no')->filter()->unique()->values())
            ->pluck('unique_no')
            ->flip();

        // ===== Sequence (logic sama seperti print lama) =====
        $groupKey = fn($i) => ($i->manifest_no ?? '') . '|' . ($i->part_no ?? '') . '|' . ($i->part_address ?? '');

        if ($isSingleItem && !empty($note->manifest_no) && !empty($note->part_no)) {
            $sequenceGroups = collect([
                $groupKey($note) => \App\Models\KanbanTmminsP2::where('manifest_no', $note->manifest_no)
                    ->where('part_no', $note->part_no)
                    ->where('part_address', $note->part_address)
                    ->get(),
            ]);
        } else {
            $sequenceGroups = $itemsToProcess->groupBy($groupKey);
        }

        $getSequence = function ($item) use ($sequenceGroups, $groupKey) {
            if (empty($item->manifest_no) || empty($item->part_no)) return '1/1';

            $group = $sequenceGroups->get($groupKey($item), collect([$item]));
            $lastDigits = $group->map(fn($i) => !empty($i->qr_code) ? (int) substr($i->qr_code, -2) : 0)->unique();

            if ($lastDigits->count() <= 1 || empty($item->qr_code)) return '1/1';

            $seq = (int) substr($item->qr_code, -2);
            if ($seq <= 0) $seq = 1;

            return $seq . '/' . $group->count();
        };

        $fmtDate = function ($value) {
            if (!$value) return '';
            $dt = \Carbon\Carbon::parse($value);
            return '<strong style="font-size:1.2em;">' . $dt->format('d/m') . '</strong> ' . $dt->format('Y-H:i');
        };
    @endphp

    <style>
        /*
         * Skala print untuk kertas 200mm x 80mm.
         * Kalau ganti printer/kertas dan perlu fine-tune, ubah angka --print-scale saja.
         * Di dialog print: Scale = Default / Actual size (100%).
         */
        :root {
            --print-scale: 0.93;
            --kanban-w: 794px;     /* lebar desain kanban */
            --kanban-h: 338px;     /* tinggi desain kanban (794 x 850/2000) */
            --offset-x: 25px;      /* geser kanan */
            --offset-y: 5px;       /* geser bawah */
        }

        @page { margin: 0; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* 1 .page = 1 lembar kertas. Ukuran tetap + overflow hidden supaya tidak pernah tumpah ke lembar berikutnya */
        .page {
            position: relative;
            width:  calc(var(--offset-x) + var(--kanban-w) * var(--print-scale));
            height: calc(var(--offset-y) + var(--kanban-h) * var(--print-scale));
            overflow: hidden;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .page + .page { break-before: page; page-break-before: always; }

        /* Kanban: desain tetap 794 x 338, dikecilkan pakai transform */
        .kanban,
        .separator {
            position: absolute;
            left: var(--offset-x);
            top: var(--offset-y);
            width: var(--kanban-w);
            height: var(--kanban-h);
            transform: scale(var(--print-scale));
            transform-origin: top left;
        }

        .kanban { overflow: hidden; }

        .kanban .bg {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        /*
         * Field di atas gambar.
         * --x --y --w --h = koordinat PIXEL di gambar asli (2000 x 850).
         * Kalau ada yang kurang pas, cukup ubah angkanya sesuai pixel di gambar.
         */
        .f {
            position: absolute;
            z-index: 1;
            left:   calc(var(--x) / 2000 * 100%);
            top:    calc(var(--y) / 850 * 100%);
            width:  calc(var(--w) / 2000 * 100%);
            height: calc(var(--h) / 850 * 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-weight: bold;
            line-height: 1.05;
            overflow: hidden;
            color: #000;
        }

        .f-supplier-code { font-size: 30px; }
        .f-date          { font-size: 13px; font-weight: normal; }
        .f-route         { font-size: 45px; }
        .f-cycle         { font-size: 50px; }
        .f-dock          { font-size: 50px; }
        .f-plo           { font-size: 60px; }
        .f-out           { font-size: 11px; font-weight: normal; }
        .f-manifest      { font-size: 13px; }
        .f-unique        { font-size: 50px; }
        .f-unique.runout { background: #000; color: #fff; }
        .f-conveyance    { font-size: 45px; font-weight: bold;}
        .f-pcs           { font-size: 25px; }
        .f-order         { font-size: 20px; }
        .f-order .hl     { font-size: 25px; }
        .f-part-address  { font-size: 25px; }

        .f-partno { flex-direction: column; }
        .f-partno .row  { display: flex; align-items: center; justify-content: center; }
        .f-partno .pno  { font-size: 25px; }
        .f-partno .seq  { font-size: 13px; margin-left: 10px; }
        .f-partno .pname{ font-size: 13px; margin-top: 6px; }

        .f-keterangan span {
            writing-mode: vertical-lr;
            transform: rotate(180deg);
            font-size: 15px;
            white-space: nowrap;
        }

        /* ===== SUPPLIER DATA: barcode + text rack ===== */
        .f-barcode {
            flex-direction: column;
            gap: 2px;            /* jarak barcode ke text rack */
        }

        .f-barcode canvas {
            display: block;
            width: 60%;          /* lebar barcode (persen dari kotak Supplier Data) */
            height: 45%;         /* tinggi barcode (persen dari kotak Supplier Data) */
        }

        .f-barcode .rack-text {
            font-size: 26px;     /* ukuran text rack */
            font-weight: bold;
            letter-spacing: 1px;
            line-height: 1;
        }

        .separator {
            border: 2px dashed #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    @if($itemsToProcess->isEmpty())
        <p style="padding:20px;">Tidak ada data untuk diprint.</p>
    @endif

    @foreach($itemsToProcess as $index => $item)
        @php
            $isRunOut = !empty($item->unique_no) && $runOutUniqueNos->has($item->unique_no);

            // Rack dari tabel addresses. Kosong / "No Address" -> tampil "-" tanpa barcode
            $rackNo = trim((string) ($item->address ?? ''));
            $hasRack = $rackNo !== '' && strtoupper($rackNo) !== 'NO ADDRESS';

            $orderNo = $item->order_no ?? null;
            if ($orderNo && strlen($orderNo) >= 8) {
                $orderHtml = e(substr($orderNo, 0, 4)) . '<span class="hl">' . e(substr($orderNo, 4, 4)) . '</span>' . e(substr($orderNo, 8));
            } else {
                $orderHtml = e($orderNo ?? '-');
            }
        @endphp

        <div class="page">
            <div class="kanban">
                <img class="bg" src="{{ asset('images/kanban/BG_TMMIN_P2.png') }}" alt="">

                {{-- SUPPLIER CODE (nama supplier & KARAWANG 5 sudah ada di gambar) --}}
                <div class="f f-supplier-code" style="--x:40; --y:120; --w:696; --h:76;">
                    {{ $item->supplier_code ?? '5007-1' }}@if(!empty($item->customer_address))-{{ $item->customer_address }}@endif
                </div>

                {{-- DEPARTURE / ARRIVAL --}}
                <div class="f f-date" style="--x:38; --y:258; --w:340; --h:90;">{!! $fmtDate($item->departure_time) !!}</div>
                <div class="f f-date" style="--x:385; --y:258; --w:354; --h:90;">{!! $fmtDate($item->arrival_time) !!}</div>

                {{-- ROUTE / CYCLE --}}
                <div class="f f-route" style="--x:85; --y:369; --w:376; --h:150;">{{ $item->route ?? '' }}</div>
                <div class="f f-cycle" style="--x:519; --y:369; --w:220; --h:150;">{{ $item->cycle ? sprintf('%02d', $item->cycle) : '' }}</div>

                {{-- SUPPLIER DATA (barcode rack + text rack, "-" kalau tidak ada rack) --}}
                <div class="f f-barcode" style="--x:45; --y:585; --w:686; --h:218;">
                    @if($hasRack)
                        <canvas class="barcode" data-value="{{ $rackNo }}"></canvas>
                        <div class="rack-text">{{ $rackNo }}</div>
                    @else
                        <div class="rack-text">-</div>
                    @endif
                </div>

                {{-- DOCK CODE --}}
                <div class="f f-dock" style="--x:1394; --y:30; --w:250; --h:231;">{{ $item->dock_code ?? '-' }}</div>

                {{-- PROGRESS LANE NO --}}
                <div class="f f-plo" style="--x:1712; --y:30; --w:250; --h:231;">{{ $item->plo !== null ? sprintf('%02d', $item->plo) : '' }}</div>

                {{-- OUT --}}
                <div class="f f-out" style="--x:1712; --y:268; --w:250; --h:80;">{!! $fmtDate($item->out_time) !!}</div>

                {{-- PART NO + SEQUENCE + PART NAME --}}
                <div class="f f-partno" style="--x:772; --y:281; --w:872; --h:178;">
                    <div class="row">
                        <span class="pno">{{ $item->part_no ?? '-' }}</span>
                        <span class="seq">{{ $getSequence($item) }}</span>
                    </div>
                    <div class="pname">{{ $item->part_name ?? '-' }}</div>
                </div>

                {{-- MANIFEST NO --}}
                <div class="f f-manifest" style="--x:1676; --y:407; --w:286; --h:67;">{{ $item->manifest_no ?? '-' }}</div>

                {{-- UNIQUE NO --}}
                <div class="f f-unique {{ $isRunOut ? 'runout' : '' }}" style="--x:816; --y:479; --w:535; --h:174;">{{ $item->unique_no ?? '-' }}</div>

                {{-- KETERANGAN (kode kiri QR, contoh K5ULO6) --}}
                <div class="f f-keterangan" style="--x:1400; --y:480; --w:55; --h:172;">
                    <span>{{ $item->keterangan ?? '' }}</span>
                </div>

                {{-- QR CODE --}}
                <div class="f f-qr" style="--x:1425; --y:480; --w:230; --h:172;">
                    <div class="qr" data-qr="{{ $item->qr_code ?? 'NO-QR' }}"></div>
                </div>

                {{-- CONVEYANCE NO (O / H / A ...) --}}
                <div class="f f-conveyance" style="--x:1712; --y:495; --w:250; --h:156;">{{ $item->conveyance_no ?? '' }}</div>

                {{-- PCS/KANBAN --}}
                <div class="f f-pcs" style="--x:772; --y:716; --w:212; --h:90;">{{ $item->pcs ?? '0' }}</div>

                {{-- ORDER NO --}}
                <div class="f f-order" style="--x:1032; --y:669; --w:423; --h:137;">{!! $orderHtml !!}</div>

                {{-- PART ADDRESS --}}
                <div class="f f-part-address" style="--x:1529; --y:669; --w:433; --h:137;">{{ $item->part_address ?? '-' }}</div>
            </div>
        </div>

        @if($showPlantSeparator && $index === $separatorAfterIndex)
            <div class="page">
                <div class="separator">PLANT 1 SELESAI &mdash; PLANT 2</div>
            </div>
        @endif
    @endforeach

    <script>
        // window.onload menunggu gambar background selesai load
        window.onload = function () {
            document.querySelectorAll('canvas.barcode').forEach(function (canvas) {
                try {
                    JsBarcode(canvas, canvas.dataset.value || '-', {
                        format: 'CODE128',
                        width: 2,
                        height: 60,
                        margin: 0,
                        displayValue: false   // text bawaan dimatikan, diganti .rack-text
                    });
                } catch (e) {
                    console.error('Barcode error:', e);
                }
            });

            document.querySelectorAll('.qr').forEach(function (div) {
                try {
                    new QRCode(div, {
                        text: div.dataset.qr || 'NO-QR',
                        width: 60,
                        height: 60,
                        colorDark: '#000000',
                        colorLight: '#ffffff',
                        correctLevel: QRCode.CorrectLevel.L
                    });
                } catch (e) {
                    console.error('QR error:', e);
                }
            });

            @if(!$isPreview)
                setTimeout(function () { window.print(); }, 800);
            @endif
        };
    </script>
</body>
</html>