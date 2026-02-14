<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin(true);

try {
    $conn = getDbConnection($dbConfig);

    $sql = "
        SELECT
            al.id,
            al.student_id,
            s.full_name,
            al.alert_level,
            al.alert_message,
            al.recommended_action,
            al.status,
            DATE_FORMAT(al.created_at, '%Y-%m-%d %H:%i') AS created_at
        FROM alerts al
        INNER JOIN students s ON s.id = al.student_id
        WHERE al.status IN ('open', 'in_progress')
        ORDER BY FIELD(al.alert_level, 'high', 'medium', 'low'), al.created_at DESC
        LIMIT 12
    ";

    $result = $conn->query($sql);
    if (!$result) {
        throw new RuntimeException('Query failed: ' . $conn->error);
    }

    $alerts = [];
    while ($row = $result->fetch_assoc()) {
        $alerts[] = [
            'id' => (int) $row['id'],
            'student_id' => (int) $row['student_id'],
            'full_name' => $row['full_name'],
            'alert_level' => ucfirst($row['alert_level']),
            'alert_message' => $row['alert_message'],
            'recommended_action' => $row['recommended_action'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
        ];
    }

    jsonResponse(['alerts' => $alerts]);
} catch (Throwable $e) {
    jsonResponse([
        'error' => $e->getMessage(),
        'hint' => 'Run SQL scripts and ensure alerts table has seeded data.',
    ], 500);
}
