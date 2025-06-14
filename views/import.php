<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import CSV ke Database (PHP Native)</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        h1 {
            color: #2c3e50;
            text-align: center;
        }

        .button-container {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        button {
            padding: 10px 15px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #2980b9;
        }

        button:disabled {
            background-color: #95a5a6;
            cursor: not-allowed;
        }

        #progress-container {
            width: 100%;
            background-color: #ecf0f1;
            border-radius: 4px;
            margin: 20px 0;
            overflow: hidden;
            display: none;
        }

        #progress-bar {
            height: 30px;
            background-color: #2ecc71;
            width: 0%;
            text-align: center;
            line-height: 30px;
            color: white;
            transition: width 0.5s ease;
        }

        #status {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
            font-family: monospace;
        }

        .status-item {
            margin-bottom: 8px;
        }

        .status-label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }

        #error {
            color: #e74c3c;
            background-color: #fadbd8;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
            display: none;
        }
    </style>
</head>

<body>
    <h1>Import CSV ke Database (PHP Native)</h1>

    <div class="button-container">
        <button data-file="customers-500000.csv">Import 500,000 rows</button>
        <button data-file="customers-1000000.csv">Import 1,000,000 rows</button>
        <button data-file="1_5m.csv">Import 1,500,000 rows</button>
    </div>

    <div style="display: flex; justify-content: center; margin-bottom: 20px;">
        <button id="truncate-btn" style="background-color: #e74c3c;">Clear Data</button>
    </div>

    <div id="progress-container">
        <div id="progress-bar">0%</div>
    </div>

    <div id="status">Pilih file untuk memulai proses import...</div>
    <div id="error"></div>

    <script>
        document.querySelectorAll('button[data-file]').forEach(button => {
            button.addEventListener('click', async () => {
                const file = button.getAttribute('data-file');

                try {
                    const response = await fetch('/import/start', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ file })
                    });

                    const data = await response.json();

                    if (data.import_id) {
                        document.getElementById('progress-container').style.display = 'block';
                        document.getElementById('error').style.display = 'none';
                        pollStatus(data.import_id);
                    } else if (data.error) {
                        document.getElementById('error').innerText = data.error;
                        document.getElementById('error').style.display = 'block';
                    }
                } catch (e) {
                    document.getElementById('error').innerText = 'Gagal menghubungi server.';
                    document.getElementById('error').style.display = 'block';
                }
            });
        });

        function pollStatus(importId) {
            const statusEl = document.getElementById('status');
            const bar = document.getElementById('progress-bar');

            const interval = setInterval(async () => {
                try {
                    const res = await fetch(`/import/status/${importId}`);
                    const data = await res.json();

                    if (data.error) {
                        clearInterval(interval);
                        document.getElementById('error').innerText = data.error;
                        document.getElementById('error').style.display = 'block';
                        return;
                    }

                    const processed = parseInt(data.processed ?? 0);
                    const total = parseInt(data.total ?? 0);
                    const percent = total > 0 ? Math.floor((processed / total) * 100) : 0;

                    bar.style.width = percent + '%';
                    bar.innerText = percent + '%';


                    statusEl.innerHTML = `
                        <div class="status-item"><span class="status-label">Status:</span> ${data.status || '-'}</div>
                        <div class="status-item"><span class="status-label">Diproses:</span> ${data.processed || '-'} / ${data.total || '-'}</div>
                        <div class="status-item"><span class="status-label">Waktu Eksekusi:</span> ${data.stats?.total_time || '-'}</div>
                        <div class="status-item"><span class="status-label">Rata-rata per 100 baris:</span> ${data.stats?.average_time_per_100_rows || '-'}</div>
                        <div class="status-item"><span class="status-label">Memori Saat Ini:</span> ${data.stats?.memory_usage || '-'}</div>
                        <div class="status-item"><span class="status-label">Puncak Memori:</span> ${data.stats?.peak_memory || '-'}</div>
                    `;

                    if (['done', 'failed', 'completed'].includes(data.status)) {
                        clearInterval(interval);
                    }
                } catch (e) {
                    clearInterval(interval);
                    document.getElementById('error').innerText = 'Gagal mendapatkan status import.';
                    document.getElementById('error').style.display = 'block';
                }
            }, 1000);
        }

        document.getElementById('truncate-btn').addEventListener('click', async () => {
            if (!confirm('Apakah kamu yakin ingin menghapus semua data? Ini tidak bisa dibatalkan!')) return;

            try {
                const response = await fetch('/import/truncate', {
                    method: 'POST'
                });

                const data = await response.json();

                if (data.success) {
                    alert('Semua data berhasil dihapus!');
                    document.getElementById('status').innerText = 'Data telah dihapus.';
                    document.getElementById('progress-bar').style.width = '0%';
                    document.getElementById('progress-bar').innerText = '0%';
                } else if (data.error) {
                    document.getElementById('error').innerText = data.error;
                    document.getElementById('error').style.display = 'block';
                }
            } catch (e) {
                document.getElementById('error').innerText = 'Gagal menghubungi server.';
                document.getElementById('error').style.display = 'block';
            }
        });
    </script>
</body>

</html>