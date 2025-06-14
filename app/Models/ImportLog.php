<?php
namespace App\Models;

class ImportLog
{
    protected $pdo;
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function create($file, $rows)
    {
        $stmt = $this->pdo->prepare("INSERT INTO import_logs (file_name, total_rows, inserted_rows, status) VALUES (?, ?, 0, 'queued')");
        $stmt->execute([$file, $rows]);
        return $this->pdo->lastInsertId();
    }

    public function find($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM import_logs WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function updateStatus($id, $data)
    {
        $stmt = $this->pdo->prepare("UPDATE import_logs SET inserted_rows = ?, status = ?, execution_stats = ? WHERE id = ?");
        $stmt->execute([$data['inserted_rows'], $data['status'], $data['execution_stats'], $id]);
    }

    public function markFailed($id)
    {
        $stmt = $this->pdo->prepare("UPDATE import_logs SET status = 'failed' WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function truncate()
    {
        $this->pdo->exec("TRUNCATE TABLE import_logs");
    }
}
