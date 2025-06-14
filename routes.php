<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Controllers/ImportController.php';

$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

use App\Controllers\ImportController;

$controller = new ImportController($pdo);

if ($uri === '/' && $method === 'GET') {
    $controller->index();
} elseif ($uri === '/import/start' && $method === 'POST') {
    $controller->startImport();
} elseif (preg_match('#^/import/status/(\d+)$#', $uri, $matches)) {
    $controller->status((int) $matches[1]);
} elseif ($uri === '/import/truncate') {
    $controller->truncate();
} else {
    http_response_code(404);
    echo '404 Not Found';
}
