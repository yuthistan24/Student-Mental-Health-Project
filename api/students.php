<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(true);

try {
    $conn = getDbConnection($dbConfig);

    $sql = "
        SELECT
            s.id,
            s.full_name,
            s.grade_level,
            ROUND(COALESCE(att.attendance_pct, 0), 1) AS attendance_pct,
            ROUND(COALESCE(sc.avg_score, 0), 1) AS avg_score,
            COALESCE(br.behavior_incidents, 0) AS behavior_incidents
        FROM students s
        LEFT JOIN (
            SELECT student_id, AVG(CASE WHEN status = 'present' THEN 1 ELSE 0 END) * 100 AS attendance_pct
            FROM attendance_records
            GROUP BY student_id
        ) att ON att.student_id = s.id
        LEFT JOIN (
            SELECT student_id, AVG(score) AS avg_score
            FROM assessment_scores
            GROUP BY student_id
        ) sc ON sc.student_id = s.id
        LEFT JOIN (
            SELECT student_id, SUM(CASE WHEN severity IN ('moderate', 'high') THEN 1 ELSE 0 END) AS behavior_incidents
            FROM behavior_records
            GROUP BY student_id
        ) br ON br.student_id = s.id
        ORDER BY s.full_name ASC
    ";

    $result = $conn->query($sql);
    if (!$result) {
        throw new RuntimeException('Query failed: ' . $conn->error);
    }

    $students = [];
    $summary = [
        'total_students' => 0,
        'high_risk' => 0,
        'active_alerts' => 0,
    ];

    while ($row = $result->fetch_assoc()) {
        $attendance = (float) $row['attendance_pct'];
        $score = (float) $row['avg_score'];
        $incidents = (int) $row['behavior_incidents'];

        $risk = 0;
        $reasons = [];

        if ($attendance < 75) {
            $risk += 45;
            $reasons[] = 'critical attendance decline';
        } elseif ($attendance < 85) {
            $risk += 25;
            $reasons[] = 'attendance below threshold';
        }

        if ($score < 50) {
            $risk += 35;
            $reasons[] = 'severe academic gap';
        } elseif ($score < 65) {
            $risk += 20;
            $reasons[] = 'academic underperformance';
        }

        if ($incidents >= 4) {
            $risk += 25;
            $reasons[] = 'escalating behavior concerns';
        } elseif ($incidents >= 2) {
            $risk += 12;
            $reasons[] = 'behavior incidents rising';
        }

        $risk = min(100, $risk);

        if ($risk >= 70) {
            $riskLabel = 'High';
            $wellnessSignal = 'Needs urgent counselor follow-up';
            $summary['high_risk']++;
            $summary['active_alerts']++;
        } elseif ($risk >= 40) {
            $riskLabel = 'Medium';
            $wellnessSignal = 'Monitor and mentor engagement';
            $summary['active_alerts']++;
        } else {
            $riskLabel = 'Low';
            $wellnessSignal = 'Stable';
        }

        $students[] = [
            'id' => (int) $row['id'],
            'full_name' => $row['full_name'],
            'grade_level' => $row['grade_level'],
            'attendance_pct' => $attendance,
            'avg_score' => $score,
            'behavior_incidents' => $incidents,
            'risk_score' => $risk,
            'risk_label' => $riskLabel,
            'wellness_signal' => $wellnessSignal,
            'reasons' => $reasons,
        ];

        $summary['total_students']++;
    }

    jsonResponse([
        'user' => [
            'id' => $user['id'],
            'role' => $user['role'],
        ],
        'summary' => $summary,
        'students' => $students,
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'error' => $e->getMessage(),
        'hint' => 'Import database/schema.sql and database/seed.sql first, then verify config.php credentials.',
    ], 500);
}
