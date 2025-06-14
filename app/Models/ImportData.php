<?php
namespace App\Models;

class ImportData
{
    protected $pdo;
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function insertBatch($rows)
    {
        $sql = "INSERT INTO import_data (`index`, customer_id, first_name, last_name, company, city, country, phone1, phone2, email, subscription_date, website) VALUES ";

        $placeholders = [];
        $values = [];

        foreach ($rows as $row) {
            $placeholders[] = "(" . rtrim(str_repeat("?,", 12), ",") . ")";
            $values = array_merge($values, array_slice($row, 0, 12));
        }

        $sql .= implode(",", $placeholders);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
    }

    public function truncate()
    {
        $this->pdo->exec("TRUNCATE TABLE import_data");
    }
}
