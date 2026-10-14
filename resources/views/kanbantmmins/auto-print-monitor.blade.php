<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Auto Print — {{ $printer !== '' ? $printer : 'PRINTER BELUM DISET' }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f6f9; color: #222; }
        .wrap { max-width: 900px; margin: 30px auto; padding: 0 20px; }
        h1 { margin: 0 0 6px; font-size: 24px; }
        .printer { display: inline-block; margin-bottom: 10px; padding: 6px 12px; border-radius: 6px; background: #212529; color: #fff; font-size: 16px; font-weight: bold; }
        .clock { font-size: 48px; font-weight: bold; margin: 10px 0; }
        .status { padding: 14px 18px; border-radius: 8px; font-size: 18px; font-weight: bold; margin-bottom: 10px; }
        .status.idle { background: #e3f2fd; color: #0d47a1; }
        .status.printing { background: #fff3cd; color: #856404; }
        .status.error { background: #f8d7da; color: #842029; }
        .next { font-size: 16px; margin-bottom: 15px; }
        button { padding: 10px 18px; font-size: 15px; border: 0; border-radius: 6px; background: #0d6efd; color: #fff; cursor: pointer; }
        button:disabled { background: #adb5bd; cursor: not-allowed; }
        ul { list-style: none; padding: 0; margin: 0; background: #fff; border-radius: 8px; max-height: 400px; overflow-y: auto; }
        li { padding: 8px 12px; border-bottom: 1px solid #eee; font-size: 14px; }
        li.success { color: #146c43; }
        li.error { color: #b02a37; }
        #printFrame { position: fixed; right: 0; bottom: 0; width: 0; height: 0; border: 0; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Auto Print Kanban TMMIN</h1>
    <div class="printer">
        🖨️ {{ $printer !== '' ? $printer : 'PRINTER BELUM DISET' }}
    </div>
    <div class="clock" id="clock">--:--:--</div>
    <div class="status idle" id="status">Memulai...</div>
    <div class="next" id="next">Cycle berikutnya: -</div>
    <button type="button" id="btnCheck">Cek Sekarang</button>

    <h3>Log</h3>
    <ul id="log"></ul>
</div>

<iframe id="printFrame"></iframe>

<script>
    const TOKEN = @json(request('token'));
    const PRINTER = @json($printer);
    const CHECK_URL = @json(route('kanbantmmins.autoprint.check'));
    const PRINT_URL = @json(route('kanbantmmins.autoprint.print'));
    const MARK_URL = @json(route('kanbantmmins.autoprint.mark'));
    const PRINT_PATH = new URL(PRINT_URL).pathname;

    const POLL_INTERVAL = 30000;          // cek tiap 30 detik
    const PRINT_PAGE_AUTO_PRINTS = false; // set true kalau printall.blade sudah panggil window.print() sendiri

    let busy = false;

    const statusEl = document.getElementById('status');
    const nextEl = document.getElementById('next');
    const logEl = document.getElementById('log');
    const clockEl = document.getElementById('clock');
    const btnCheck = document.getElementById('btnCheck');

    function updateClock() {
        clockEl.textContent = new Date().toLocaleTimeString('id-ID', { hour12: false });
    }

    function setStatus(text, cls) {
        statusEl.textContent = text;
        statusEl.className = 'status ' + cls;
    }

    function addLog(message, type = 'info') {
        const li = document.createElement('li');
        li.className = type;
        li.textContent = '[' + new Date().toLocaleTimeString('id-ID', { hour12: false }) + '] ' + message;
        logEl.prepend(li);
        while (logEl.children.length > 100) {
            logEl.removeChild(logEl.lastChild);
        }
    }

    function loadAndPrint(ids) {
        return new Promise((resolve, reject) => {
            const frame = document.getElementById('printFrame');

            const timeout = setTimeout(() => {
                reject(new Error('Halaman print gagal dimuat (timeout)'));
            }, 60000);

            frame.onload = () => {
                clearTimeout(timeout);

                // Kalau printSelected error, dia redirect back => jangan print halaman yang salah
                let loadedPath = '';
                try {
                    loadedPath = frame.contentWindow.location.pathname;
                } catch (e) {
                    return reject(new Error('Tidak bisa membaca halaman print'));
                }

                if (loadedPath !== PRINT_PATH) {
                    return reject(new Error('Halaman print error / redirect, print dibatalkan'));
                }

                // Jeda supaya gambar background & barcode selesai render
                setTimeout(() => {
                    try {
                        if (!PRINT_PAGE_AUTO_PRINTS) {
                            frame.contentWindow.focus();
                            frame.contentWindow.print();
                        }
                        setTimeout(resolve, 5000);
                    } catch (e) {
                        reject(e);
                    }
                }, 2000);
            };

            frame.src = PRINT_URL
                + '?token=' + encodeURIComponent(TOKEN)
                + '&ids=' + encodeURIComponent(ids.join(','));
        });
    }

    async function markPrinted(cycle, manifestNos) {
        const res = await fetch(MARK_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                token: TOKEN,
                printer: PRINTER,
                cycle: cycle,
                manifest_nos: manifestNos
            })
        });

        if (!res.ok) {
            throw new Error('Gagal menandai printed (HTTP ' + res.status + ')');
        }
    }

    async function runCheck() {
        if (busy) return;
        busy = true;

        let printedSomething = false;

        try {
            const url = CHECK_URL
                + '?token=' + encodeURIComponent(TOKEN)
                + '&printer=' + encodeURIComponent(PRINTER);

            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });

            if (!res.ok) {
                throw new Error('Cek jadwal gagal (HTTP ' + res.status + ')');
            }

            const data = await res.json();

            if (data.error) {
                throw new Error(data.error);
            }

            nextEl.textContent = data.next
                ? 'Cycle berikutnya: ' + data.next.cycle + ' jam ' + data.next.jam_keluar
                : 'Cycle berikutnya: - (tidak ada lagi hari ini untuk printer ini)';

            if (!data.due) {
                setStatus('Standby — jam server ' + data.server_time, 'idle');
                return;
            }

            const info = 'cycle ' + data.cycle + ' (jam ' + data.jam_keluar + ') — '
                + data.manifest_nos.length + ' manifest, ' + data.ids.length + ' kanban';

            setStatus('Sedang print ' + info, 'printing');
            addLog('Mulai print ' + info);

            await loadAndPrint(data.ids);
            await markPrinted(data.cycle, data.manifest_nos);

            addLog('Selesai kirim ke printer: ' + info, 'success');
            setStatus('Standby — terakhir print cycle ' + data.cycle, 'idle');
            printedSomething = true;

        } catch (e) {
            addLog(e.message, 'error');
            setStatus('Error: ' + e.message, 'error');
        } finally {
            busy = false;
            // Kalau barusan print, langsung cek lagi siapa tahu ada cycle lain yang juga sudah due
            if (printedSomething) {
                setTimeout(runCheck, 3000);
            }
        }
    }

    updateClock();
    setInterval(updateClock, 1000);

    if (!PRINTER) {
        // Pengaman: tanpa parameter printer, kiosk tidak boleh polling
        btnCheck.disabled = true;
        setStatus('Parameter printer belum diisi di URL kiosk. Tambahkan &printer=NAMA PRINTER.', 'error');
        addLog('Polling tidak dijalankan karena parameter printer kosong.', 'error');
    } else {
        btnCheck.addEventListener('click', runCheck);
        addLog('Monitor aktif untuk printer "' + PRINTER + '", cek tiap ' + (POLL_INTERVAL / 1000) + ' detik');
        runCheck();
        setInterval(runCheck, POLL_INTERVAL);
    }
</script>
</body>
</html>