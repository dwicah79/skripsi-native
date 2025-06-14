<?php
namespace App\Controllers;

use App\Models\ImportLog;
use App\Models\ImportData;

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

        // Hitung total baris
        $rows = count(file($path)) - 1;

        // Simpan log awal
        $log = new ImportLog($this->pdo);
        $logId = $log->create($filename, $rows);

        // Jalankan job di background
        $phpPath = PHP_BINARY; // Path ke PHP CLI
        $jobPath = escapeshellarg(__DIR__ . '/../Jobs/ImportCsvRunner.php');
        $cmd = "$phpPath $jobPath " . escapeshellarg($logId) . " > /dev/null 2>&1 &";
        exec($cmd); // Jalankan tanpa menunggu selesai

        // Langsung balikan import_id ke frontend
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
            echo json_encode(['error' => 'Import not found']);
            return;
        }

        // Hitung waktu eksekusi
        $executionTime = $data['execution_time'] ?? 0;
        $processed = $data['processed_rows'] ?? 0;
        $total = $data['total_rows'] ?? 0;

        // Format response
        $response = [
            'status' => $data['status'] ?? 'unknown',
            'processed' => $processed,
            'total' => $total,
            'stats' => [
                'total_time' => $this->formatTime($executionTime),
                'average_time_per_100_rows' => $processed > 0
                    ? $this->formatTime(($executionTime / $processed) * 100)
                    : '-',
                'memory_usage' => $this->formatMemory(memory_get_usage()),
                'peak_memory' => $this->formatMemory(memory_get_peak_usage())
            ]
        ];

        echo json_encode($response);
    }

    private function formatTime($seconds)
    {
        if ($seconds < 60) {
            return round($seconds, 2) . ' detik';
        }
        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;
        return $minutes . ' menit ' . round($seconds, 2) . ' detik';
    }

    private function calculateAverageTime($data)
    {
        if (empty($data['processed_rows']) || empty($data['execution_time'])) {
            return '-';
        }
        $timePer100 = ($data['execution_time'] / $data['processed_rows']) * 100;
        return round($timePer100, 4) . ' detik';
    }

    private function formatMemory($bytes)
    {
        if ($bytes < 1024) {
            return $bytes . ' bytes';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return round($bytes / 1048576, 2) . ' MB';
        }
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
