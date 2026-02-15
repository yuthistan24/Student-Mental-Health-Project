<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$student = requireStudentLogin(true);

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
    $studentId = (int) $student['student_id'];

    $stats = [
        'attendance_pct' => 0.0,
        'avg_score' => 0.0,
        'completion_pct' => 0.0,
        'mastery_score' => 0.0,
        'behavior_incidents' => 0,
    ];
    $profile = null;

    $attendanceRes = $conn->query("SELECT AVG(CASE WHEN status='present' THEN 1 ELSE 0 END) * 100 AS attendance_pct FROM attendance_records WHERE student_id = {$studentId}");
    $scoreRes = $conn->query("SELECT AVG(score) AS avg_score FROM assessment_scores WHERE student_id = {$studentId}");
    $behaviorRes = $conn->query("SELECT SUM(CASE WHEN severity IN ('moderate','high') THEN 1 ELSE 0 END) AS behavior_incidents FROM behavior_records WHERE student_id = {$studentId}");
    $progressRes = $conn->query("SELECT AVG(completion_pct) AS completion_pct, AVG(mastery_score) AS mastery_score FROM module_progress WHERE student_id = {$studentId}");

    if ($attendanceRes && ($row = $attendanceRes->fetch_assoc())) {
        $stats['attendance_pct'] = (float) ($row['attendance_pct'] ?? 0);
    }
    if ($scoreRes && ($row = $scoreRes->fetch_assoc())) {
        $stats['avg_score'] = (float) ($row['avg_score'] ?? 0);
    }
    if ($behaviorRes && ($row = $behaviorRes->fetch_assoc())) {
        $stats['behavior_incidents'] = (int) ($row['behavior_incidents'] ?? 0);
    }
    if ($progressRes && ($row = $progressRes->fetch_assoc())) {
        $stats['completion_pct'] = (float) ($row['completion_pct'] ?? 0);
        $stats['mastery_score'] = (float) ($row['mastery_score'] ?? 0);
    }

    $profileStmt = $conn->prepare('
        SELECT
            attempted_exam, target_stream, current_stream, stream_mismatch,
            financial_issues, worked_after_school, work_history_note, study_gap_months,
            confidence_level, primary_challenge, goals
        FROM student_background_profiles
        WHERE student_id = ?
        LIMIT 1
    ');
    if ($profileStmt) {
        $profileStmt->bind_param('i', $studentId);
        $profileStmt->execute();
        $profile = $profileStmt->get_result()->fetch_assoc();
    }

    $q = strtolower($message);
    $reply = '';

    if (strpos($q, 'attendance') !== false) {
        if ($stats['attendance_pct'] < 85) {
            $reply = 'Your attendance is below 85%. Try setting a daily reminder and review one micro-lesson each day you miss class.';
        } else {
            $reply = 'Your attendance is on track. Keep the momentum by maintaining a consistent schedule.';
        }
    } elseif (strpos($q, 'score') !== false || strpos($q, 'grade') !== false || strpos($q, 'exam') !== false) {
        if ($stats['avg_score'] < 65) {
            $reply = 'Your current average suggests you may need extra support. Focus on your weakest subject first and complete 20-30 minutes of practice daily.';
        } else {
            $reply = 'Your score trend is stable. Continue targeted practice and weekly revision to improve mastery.';
        }
    } elseif (strpos($q, 'stress') !== false || strpos($q, 'mental') !== false || strpos($q, 'anxious') !== false) {
        $reply = 'If you feel stressed, break study time into short sessions, take 5-minute pauses, and speak to a counselor or trusted adult for support.';
    } elseif (strpos($q, 'plan') !== false || strpos($q, 'study') !== false) {
        $reply = 'Suggested plan: 1) 25 minutes focused practice, 2) 10-question quiz, 3) review mistakes, 4) ask for help on difficult topics.';
    } else {
        $reply = 'I can help with study plans, attendance improvement, stress support, and learning goals. Ask: "How can I improve my attendance?"';
    }

    if ($profile) {
        $context = [];
        if ($profile['attempted_exam'] !== 'none') {
            $context[] = strtoupper((string) $profile['attempted_exam']) . ' background';
        }
        if ((int) $profile['stream_mismatch'] === 1 && $profile['target_stream'] !== '' && $profile['current_stream'] !== '') {
            $context[] = 'stream transition from ' . $profile['target_stream'] . ' to ' . $profile['current_stream'];
        }
        if ((int) $profile['financial_issues'] === 1) {
            $context[] = 'financial pressure';
        }
        if ((int) $profile['worked_after_school'] === 1) {
            $context[] = 'work-study history';
        }
        if ((int) $profile['study_gap_months'] >= 6) {
            $context[] = 'study gap of ' . (int) $profile['study_gap_months'] . ' months';
        }
        if ($profile['confidence_level'] === 'low') {
            $context[] = 'low confidence currently';
        }

        if (!empty($context)) {
            $reply .= ' Based on your profile (' . implode(', ', $context) . '), start with foundational revision and short daily consistency goals.';
        }

        if (!empty($profile['goals'])) {
            $reply .= ' Your stated goal: "' . $profile['goals'] . '".';
        }
    } else {
        $reply .= ' To get more personalized guidance, complete your onboarding questionnaire.';
    }

    jsonResponse([
        'reply' => $reply,
        'stats' => [
            'attendance_pct' => round($stats['attendance_pct'], 1),
            'avg_score' => round($stats['avg_score'], 1),
            'completion_pct' => round($stats['completion_pct'], 1),
            'mastery_score' => round($stats['mastery_score'], 1),
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
