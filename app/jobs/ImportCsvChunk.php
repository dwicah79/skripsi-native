<?php
namespace App\Jobs;

use App\Models\ImportData;
use App\Models\ImportLog;

class ImportCsvChunk
{
    protected $pdo;
    protected $logId;

    public function __construct($pdo, $logId)
    {
        $this->pdo = $pdo;
        $this->logId = $logId;
    }

    public function handle()
    {
        $logModel = new ImportLog($this->pdo);
        $dataModel = new ImportData($this->pdo);

        $log = $logModel->find($this->logId);
        if (!$log)
            return;

        $path = __DIR__ . '/../../storage/imports/' . basename($log['file_name']);
        if (!file_exists($path)) {
            $logModel->markFailed($this->logId);
            return;
        }

        $handle = fopen($path, 'r');
        fgetcsv($handle); // skip header

        $batch = [];
        $processed = 0;
        $batchSize = 1000;

        $memoryStats = [];
        $executionTimes = [];
        $startTime = microtime(true);

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 12)
                continue;
            $batch[] = $data;
            $processed++;

            if ($processed % $batchSize === 0) {
                $chunkStart = microtime(true);
                $dataModel->insertBatch($batch);
                $executionTimes[] = microtime(true) - $chunkStart;
                $memoryStats[] = memory_get_peak_usage();
                $batch = [];

                $logModel->updateStatus($this->logId, [
                    'inserted_rows' => $processed,
                    'status' => 'processing',
                    'execution_stats' => ''
                ]);
            }
        }

        if (!empty($batch)) {
            $dataModel->insertBatch($batch);
        }

        fclose($handle);

        $totalTime = microtime(true) - $startTime;
        $stats = json_encode([
            'total_execution_time' => round($totalTime, 2) . ' seconds',
            'average_per_100' => round($totalTime / ($processed / 100), 4) . ' seconds',
            'execution_times' => array_map(fn($t) => ['time' => round($t, 4)], $executionTimes),
            'memory_usage' => array_map(fn($m) => ['memory_peak' => round($m / 1048576, 2) . ' MB'], $memoryStats),
        ]);

        $logModel->updateStatus($this->logId, [
            'inserted_rows' => $processed,
            'status' => 'completed',
            'execution_stats' => $stats
        ]);
    }
}
