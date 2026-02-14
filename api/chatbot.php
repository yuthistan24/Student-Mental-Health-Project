<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed.'], 405);
}

$payload = json_decode(file_get_contents('php://input'), true);
$message = trim((string) ($payload['message'] ?? ''));
if ($message === '') {
    jsonResponse(['error' => 'Message is required.'], 422);
}

try {
    $conn = getDbConnection($dbConfig);

    $statsQuery = "
        SELECT
            COUNT(*) AS total_students,
            SUM(CASE WHEN risk_score >= 70 THEN 1 ELSE 0 END) AS high_risk_count
        FROM (
            SELECT
                s.id,
                LEAST(100,
                    (CASE
                        WHEN COALESCE(att.attendance_pct, 0) < 75 THEN 45
                        WHEN COALESCE(att.attendance_pct, 0) < 85 THEN 25
                        ELSE 0
                    END) +
                    (CASE
                        WHEN COALESCE(sc.avg_score, 0) < 50 THEN 35
                        WHEN COALESCE(sc.avg_score, 0) < 65 THEN 20
                        ELSE 0
                    END) +
                    (CASE
                        WHEN COALESCE(br.behavior_incidents, 0) >= 4 THEN 25
                        WHEN COALESCE(br.behavior_incidents, 0) >= 2 THEN 12
                        ELSE 0
                    END)
                ) AS risk_score
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
        ) t
    ";

    $statsResult = $conn->query($statsQuery);
    if (!$statsResult) {
        throw new RuntimeException('Failed to read student stats.');
    }
    $stats = $statsResult->fetch_assoc();
    $totalStudents = (int) ($stats['total_students'] ?? 0);
    $highRiskCount = (int) ($stats['high_risk_count'] ?? 0);

    $alertsResult = $conn->query("SELECT COUNT(*) AS open_alerts FROM alerts WHERE status IN ('open', 'in_progress')");
    if (!$alertsResult) {
        throw new RuntimeException('Failed to read alerts stats.');
    }
    $openAlerts = (int) (($alertsResult->fetch_assoc())['open_alerts'] ?? 0);

    $q = strtolower($message);
    $reply = '';

    if (strpos($q, 'highest') !== false || strpos($q, 'high risk') !== false || strpos($q, 'dropout') !== false) {
        $topSql = "
            SELECT
                s.full_name,
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
            ORDER BY
                (CASE WHEN COALESCE(att.attendance_pct, 0) < 75 THEN 45 WHEN COALESCE(att.attendance_pct, 0) < 85 THEN 25 ELSE 0 END) +
                (CASE WHEN COALESCE(sc.avg_score, 0) < 50 THEN 35 WHEN COALESCE(sc.avg_score, 0) < 65 THEN 20 ELSE 0 END) +
                (CASE WHEN COALESCE(br.behavior_incidents, 0) >= 4 THEN 25 WHEN COALESCE(br.behavior_incidents, 0) >= 2 THEN 12 ELSE 0 END)
                DESC
            LIMIT 1
        ";

        $topResult = $conn->query($topSql);
        $top = $topResult ? $topResult->fetch_assoc() : null;
        if ($top) {
            $reply = "Top priority student currently appears to be {$top['full_name']} (attendance {$top['attendance_pct']}%, avg score {$top['avg_score']}, incidents {$top['behavior_incidents']}). Immediate actions: counselor check-in, family contact, and targeted recovery modules.";
        } else {
            $reply = 'No student records are available yet. Add attendance, assessments, and behavior logs first.';
        }
    } elseif (strpos($q, 'attendance') !== false) {
        $reply = "Attendance-focused strategy: run daily absentee trigger lists, assign mentors for students below 85% attendance, and pair each with offline micro-lessons. Current high-risk students: {$highRiskCount} of {$totalStudents}.";
    } elseif (strpos($q, 'counsel') !== false || strpos($q, 'mental') !== false || strpos($q, 'wellness') !== false) {
        $reply = "Mental health triage plan: prioritize students with both behavior incidents and low attendance, schedule counselor follow-up in 24-48 hours, and monitor weekly progression. Open/in-progress alerts: {$openAlerts}.";
    } elseif (strpos($q, 'tutor') !== false || strpos($q, 'hub') !== false || strpos($q, 'community') !== false) {
        $reply = 'Community hub recommendation: reserve tutoring slots first for high-risk cases, then medium-risk students with math/literacy gaps. Use hub digital resources for students with unstable home connectivity.';
    } else {
        $reply = "I can help with risk triage, attendance interventions, counseling prioritization, and tutoring hub coordination. Current status: {$highRiskCount} high-risk students, {$openAlerts} active alerts.";
    }

    $insert = $conn->prepare('INSERT INTO chatbot_messages (user_id, user_role, user_message, bot_reply) VALUES (?, ?, ?, ?)');
    if ($insert) {
        $userId = (int) $user['id'];
        $role = (string) $user['role'];
        $insert->bind_param('isss', $userId, $role, $message, $reply);
        $insert->execute();
    }

    jsonResponse([
        'reply' => $reply,
        'meta' => [
            'total_students' => $totalStudents,
            'high_risk' => $highRiskCount,
            'active_alerts' => $openAlerts,
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
