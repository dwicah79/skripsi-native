<?php
namespace App\Controllers;

use App\Models\ImportLog;
use App\Models\ImportData;
use App\Jobs\ImportCsvChunk;

class ImportController
{
    protected $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function index()
    {
        require __DIR__ . '../../../views/import.php';
    }

    public function startImport()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $filename = basename($input['file'] ?? '');

        if (!$filename || !preg_match('/\.csv$/i', $filename)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid CSV file']);
            return;
        }

        $path = __DIR__ . '/../../storage/imports/' . $filename;
        if (!file_exists($path)) {
            http_response_code(404);
            echo json_encode(['error' => 'File not found']);
            return;
        }

        $rows = count(file($path)) - 1;
        $log = new ImportLog($this->pdo);
        $logId = $log->create($filename, $rows);

        exec("php " . __DIR__ . "/../../jobs/ImportCsvChunk.php {$logId} > /dev/null 2>&1 &");


        echo json_encode([
            'import_id' => $logId,
            'message' => 'Import started'
        ]);
    }

    public function status($id)
    {
        $log = new ImportLog($this->pdo);
        $data = $log->find($id);

        if (!$data) {
            http_response_code(404);
            echo json_encode(['error' => 'Import log not found']);
            return;
        }

        $processed = (int) ($data['inserted_rows'] ?? 0);
        $total = (int) ($data['total_rows'] ?? 0);
        $executionStats = json_decode($data['execution_stats'] ?? '{}', true);

        $averagePer100 = '-';
        if (!empty($executionStats['execution_times'])) {
            $times = array_column($executionStats['execution_times'], 'time');
            if (count($times) > 0) {
                $averagePer100 = round(array_sum($times) / count($times), 2) . ' s';
            }
        }

        $peakMemory = '-';
        if (!empty($executionStats['memory_usage'])) {
            $peaks = array_column($executionStats['memory_usage'], 'memory_peak');
            $peakMemory = round(max(array_map(fn($m) => floatval(str_replace(' MB', '', $m)), $peaks)), 2) . ' MB';
        }

        echo json_encode([
            'status' => $data['status'],
            'processed' => $processed,
            'total' => $total,
            'stats' => [
                'total_time' => $executionStats['total_execution_time'] ?? '-',
                'average_time_per_100_rows' => $averagePer100,
                'memory_usage' => '-',
                'peak_memory' => $peakMemory,
            ]
        ]);
    }

    public function truncate()
    {
        $data = new ImportData($this->pdo);
        $log = new ImportLog($this->pdo);

        $data->truncate();
        $log->truncate();

        echo json_encode(['success' => true]);
    }
}
