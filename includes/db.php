<?php
$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    $configPath = __DIR__ . '/../config.sample.php';
}

$dbConfig = require $configPath;

function getDbConnection(array $dbConfig): mysqli
{
    $conn = @new mysqli(
        $dbConfig['db_host'],
        $dbConfig['db_user'],
        $dbConfig['db_pass'],
        $dbConfig['db_name'],
        $dbConfig['db_port']
    );

    if ($conn->connect_errno) {
        throw new RuntimeException('Database connection failed: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

function jsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}
