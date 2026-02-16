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

    $stats = loadStudentStats($conn, $studentId);
    $profile = loadStudentProfile($conn, $studentId);

    $sessionKey = 'student_profile_chat_' . $studentId;
    $q = strtolower($message);

    if (!isset($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = [
            'active' => false,
            'index' => 0,
            'answers' => [],
        ];
    }

    $state = $_SESSION[$sessionKey];

    if (isInterviewCancel($q)) {
        $state['active'] = false;
        $state['index'] = 0;
        $state['answers'] = [];
        $_SESSION[$sessionKey] = $state;

        $reply = 'No problem, we can continue later. Ask me any study question, or say "start profile interview" when you are ready.';
        jsonResponse(['reply' => $reply, 'mode' => 'normal', 'stats' => normalizedStats($stats)]);
    }

    if ($state['active']) {
        $response = handleInterviewAnswer($conn, $studentId, $message, $state);
        $_SESSION[$sessionKey] = $response['state'];

        jsonResponse([
            'reply' => $response['reply'],
            'mode' => 'interview',
            'interview_done' => $response['done'],
            'stats' => normalizedStats($stats),
        ]);
    }

    if (isInterviewTrigger($q) || profileNeedsIntake($profile)) {
        $prefill = mapProfileToAnswers($profile);
        $state = [
            'active' => true,
            'index' => 0,
            'answers' => $prefill,
        ];
        $_SESSION[$sessionKey] = $state;

        $flow = buildInterviewFlow($state['answers']);
        $firstQuestion = $flow[0]['question'] ?? 'Tell me about your recent study background.';
        $intro = 'To personalize your support, I will ask a few short questions and clarify where needed. You can say "skip for now" anytime. ';

        jsonResponse([
            'reply' => $intro . $firstQuestion,
            'mode' => 'interview',
            'interview_done' => false,
            'stats' => normalizedStats($stats),
        ]);
    }

    $reply = buildStudentGuidanceReply($q, $stats, $profile);

    jsonResponse([
        'reply' => $reply,
        'mode' => 'normal',
        'stats' => normalizedStats($stats),
    ]);
} catch (Throwable $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}

function loadStudentStats(mysqli $conn, int $studentId): array
{
    $stats = [
        'attendance_pct' => 0.0,
        'avg_score' => 0.0,
        'completion_pct' => 0.0,
        'mastery_score' => 0.0,
        'behavior_incidents' => 0,
    ];

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

    return $stats;
}

function loadStudentProfile(mysqli $conn, int $studentId): ?array
{
    $profileStmt = $conn->prepare('
        SELECT
            attempted_exam, target_stream, current_stream, stream_mismatch,
            financial_issues, worked_after_school, work_history_note, study_gap_months, gap_years, gap_year_reason,
            feeling_about_studies, discomfort_due_to_issues, discomfort_reason,
            confidence_level, primary_challenge, goals
        FROM student_background_profiles
        WHERE student_id = ?
        LIMIT 1
    ');

    if (!$profileStmt) {
        return null;
    }

    $profileStmt->bind_param('i', $studentId);
    $profileStmt->execute();

    $result = $profileStmt->get_result()->fetch_assoc();
    return $result ?: null;
}

function normalizedStats(array $stats): array
{
    return [
        'attendance_pct' => round((float) ($stats['attendance_pct'] ?? 0), 1),
        'avg_score' => round((float) ($stats['avg_score'] ?? 0), 1),
        'completion_pct' => round((float) ($stats['completion_pct'] ?? 0), 1),
        'mastery_score' => round((float) ($stats['mastery_score'] ?? 0), 1),
    ];
}

function profileNeedsIntake(?array $profile): bool
{
    if (!$profile) {
        return true;
    }

    $required = ['attempted_exam', 'confidence_level', 'feeling_about_studies'];
    foreach ($required as $field) {
        if (!isset($profile[$field]) || trim((string) $profile[$field]) === '') {
            return true;
        }
    }

    return false;
}

function isInterviewTrigger(string $q): bool
{
    $phrases = [
        'start profile', 'profile interview', 'ask me questions', 'personalize me',
        'personalise me', 'onboarding', 'background questions', 'neet', 'jee', 'gap year'
    ];

    foreach ($phrases as $phrase) {
        if (strpos($q, $phrase) !== false) {
            return true;
        }
    }

    return false;
}

function isInterviewCancel(string $q): bool
{
    return strpos($q, 'skip for now') !== false || strpos($q, 'cancel interview') !== false || strpos($q, 'stop interview') !== false;
}

function mapProfileToAnswers(?array $profile): array
{
    if (!$profile) {
        return [];
    }

    $answers = [];
    $fields = [
        'attempted_exam', 'target_stream', 'current_stream', 'stream_mismatch',
        'financial_issues', 'worked_after_school', 'work_history_note', 'study_gap_months',
        'gap_years', 'gap_year_reason', 'feeling_about_studies', 'discomfort_due_to_issues',
        'discomfort_reason', 'confidence_level', 'primary_challenge', 'goals'
    ];

    foreach ($fields as $field) {
        if (isset($profile[$field]) && $profile[$field] !== null) {
            $answers[$field] = (string) $profile[$field];
        }
    }

    return $answers;
}

function handleInterviewAnswer(mysqli $conn, int $studentId, string $message, array $state): array
{
    $flow = buildInterviewFlow($state['answers']);
    $index = (int) ($state['index'] ?? 0);

    if (!isset($flow[$index])) {
        persistInterviewAnswers($conn, $studentId, $state['answers']);
        $state['active'] = false;
        $state['index'] = 0;
        return [
            'state' => $state,
            'reply' => 'Your profile has been updated. Ask me for a personalized plan anytime.',
            'done' => true,
        ];
    }

    $current = $flow[$index];
    $parsed = parseInterviewAnswer($current['field'], $message);

    if ($parsed['value'] === null) {
        return [
            'state' => $state,
            'reply' => $parsed['follow_up'] ?: $current['question'],
            'done' => false,
        ];
    }

    if (!empty($current['store'])) {
        $state['answers'][$current['field']] = (string) $parsed['value'];
    }

    $ack = interviewAcknowledgement($current['field'], (string) $parsed['value']);
    $state['index'] = $index + 1;
    $nextFlow = buildInterviewFlow($state['answers']);

    if (!isset($nextFlow[$state['index']])) {
        persistInterviewAnswers($conn, $studentId, $state['answers']);
        $state['active'] = false;
        $state['index'] = 0;

        return [
            'state' => $state,
            'reply' => trim($ack . ' Thanks. I have saved your profile and will now tailor recommendations to your situation.'),
            'done' => true,
        ];
    }

    $nextQuestion = $nextFlow[$state['index']]['question'];

    return [
        'state' => $state,
        'reply' => trim($ack . ' ' . $nextQuestion),
        'done' => false,
    ];
}

function buildInterviewFlow(array $answers): array
{
    $attemptedExam = (string) ($answers['attempted_exam'] ?? '');
    $streamMismatch = (string) ($answers['stream_mismatch'] ?? '0');
    $financialIssues = (string) ($answers['financial_issues'] ?? '0');
    $workedAfterSchool = (string) ($answers['worked_after_school'] ?? '0');
    $studyGapMonths = (int) ($answers['study_gap_months'] ?? 0);
    $gapYears = (int) ($answers['gap_years'] ?? 0);
    $discomfort = (string) ($answers['discomfort_due_to_issues'] ?? '0');

    $flow = [
        q('attempted_exam', 'Have you attempted JEE, NEET, both, or neither?', true),
    ];

    if ($attemptedExam !== '' && $attemptedExam !== 'none') {
        $flow[] = q('exam_attempt_context', 'How did those attempts affect your confidence or stress level?', false);
    }

    $flow[] = q('target_stream', 'Which stream were you aiming for originally?', true);
    $flow[] = q('current_stream', 'Which stream are you currently studying now?', true);
    $flow[] = q('stream_mismatch', 'Are you in a different stream than you hoped for? Please answer yes or no.', true);

    if ($streamMismatch === '1') {
        $flow[] = q('stream_mismatch_context', 'What part of this stream transition feels hardest for you?', false);
    }

    $flow[] = q('financial_issues', 'Do financial issues affect your studies right now? Please answer yes or no.', true);

    if ($financialIssues === '1') {
        $flow[] = q('financial_issue_context', 'Is the pressure mostly tuition, living costs, or study materials?', false);
    }

    $flow[] = q('worked_after_school', 'Did you work after school or during a study gap? Please answer yes or no.', true);

    if ($workedAfterSchool === '1') {
        $flow[] = q('work_history_note', 'What kind of work did you do, and roughly how long?', true);
    } else {
        $flow[] = q('work_history_note', 'If relevant, mention any responsibilities that reduced your study time. You can say none.', true);
    }

    $flow[] = q('study_gap_months', 'How many months were you away from regular study?', true);
    $flow[] = q('gap_years', 'How many total gap years do you have in education?', true);

    if ($gapYears > 0 || $studyGapMonths >= 6) {
        $flow[] = q('gap_year_reason', 'What was the main reason for this gap period?', true);
    } else {
        $flow[] = q('gap_year_reason', 'If there was no major gap reason, say none.', true);
    }

    $flow[] = q('feeling_about_studies', 'How are you feeling about studies now: motivated, neutral, stressed, or burned out?', true);
    $flow[] = q('discomfort_due_to_issues', 'Are you feeling uncomfortable due to personal or academic issues? Please answer yes or no.', true);

    if ($discomfort === '1') {
        $flow[] = q('discomfort_reason', 'What is the main cause of this discomfort?', true);
    } else {
        $flow[] = q('discomfort_reason', 'If there is any issue to keep in mind, mention it. Otherwise say none.', true);
    }

    $flow[] = q('confidence_level', 'How confident do you feel now: low, medium, or high?', true);
    $flow[] = q('primary_challenge', 'What is your biggest study challenge currently?', true);
    $flow[] = q('goals', 'What is your main goal for the next three months?', true);

    return $flow;
}

function q(string $field, string $question, bool $store): array
{
    return ['field' => $field, 'question' => $question, 'store' => $store];
}

function parseInterviewAnswer(string $field, string $message): array
{
    $text = strtolower(trim($message));
    $yesNo = parseYesNoValue($text);

    switch ($field) {
        case 'attempted_exam':
            if (strpos($text, 'both') !== false || (strpos($text, 'neet') !== false && strpos($text, 'jee') !== false)) {
                return ['value' => 'both', 'follow_up' => ''];
            }
            if (strpos($text, 'neet') !== false) {
                return ['value' => 'neet', 'follow_up' => ''];
            }
            if (strpos($text, 'jee') !== false) {
                return ['value' => 'jee', 'follow_up' => ''];
            }
            if (strpos($text, 'none') !== false || strpos($text, 'neither') !== false || strpos($text, 'no') !== false) {
                return ['value' => 'none', 'follow_up' => ''];
            }
            return ['value' => null, 'follow_up' => 'Please answer with one of these: JEE, NEET, both, or neither.'];

        case 'stream_mismatch':
        case 'financial_issues':
        case 'worked_after_school':
        case 'discomfort_due_to_issues':
            if ($yesNo !== null) {
                return ['value' => $yesNo ? '1' : '0', 'follow_up' => ''];
            }
            return ['value' => null, 'follow_up' => 'Please answer yes or no so I can continue.'];

        case 'study_gap_months':
        case 'gap_years':
            if (preg_match('/\d+/', $text, $m)) {
                return ['value' => (string) max(0, (int) $m[0]), 'follow_up' => ''];
            }
            if (strpos($text, 'none') !== false || strpos($text, 'zero') !== false || strpos($text, 'no') !== false) {
                return ['value' => '0', 'follow_up' => ''];
            }
            return ['value' => null, 'follow_up' => 'Please provide a number like 0, 1, or 2.'];

        case 'feeling_about_studies':
            if (strpos($text, 'motivat') !== false || strpos($text, 'positive') !== false || strpos($text, 'good') !== false) {
                return ['value' => 'motivated', 'follow_up' => ''];
            }
            if (strpos($text, 'neutral') !== false || strpos($text, 'okay') !== false || strpos($text, 'fine') !== false) {
                return ['value' => 'neutral', 'follow_up' => ''];
            }
            if (strpos($text, 'stress') !== false || strpos($text, 'anx') !== false || strpos($text, 'pressure') !== false) {
                return ['value' => 'stressed', 'follow_up' => ''];
            }
            if (strpos($text, 'burn') !== false || strpos($text, 'exhaust') !== false || strpos($text, 'drain') !== false) {
                return ['value' => 'burned_out', 'follow_up' => ''];
            }
            return ['value' => null, 'follow_up' => 'Please choose one: motivated, neutral, stressed, or burned out.'];

        case 'confidence_level':
            if (strpos($text, 'low') !== false || strpos($text, 'not confident') !== false) {
                return ['value' => 'low', 'follow_up' => ''];
            }
            if (strpos($text, 'medium') !== false || strpos($text, 'average') !== false || strpos($text, 'moderate') !== false) {
                return ['value' => 'medium', 'follow_up' => ''];
            }
            if (strpos($text, 'high') !== false || strpos($text, 'very confident') !== false || strpos($text, 'strong') !== false) {
                return ['value' => 'high', 'follow_up' => ''];
            }
            return ['value' => null, 'follow_up' => 'Please answer low, medium, or high.'];

        default:
            if ($text === '') {
                return ['value' => null, 'follow_up' => 'Please share a short answer so I can personalize your support.'];
            }
            return ['value' => trim($message), 'follow_up' => ''];
    }
}

function parseYesNoValue(string $text): ?bool
{
    if ($text === '') {
        return null;
    }
    if (preg_match('/\b(yes|yeah|yep|true|correct)\b/', $text)) {
        return true;
    }
    if (preg_match('/\b(no|nope|false|not really)\b/', $text)) {
        return false;
    }
    return null;
}

function interviewAcknowledgement(string $field, string $value): string
{
    switch ($field) {
        case 'attempted_exam':
            return $value === 'none' ? 'Thanks for sharing your exam background.' : 'Thank you for sharing that journey.';
        case 'stream_mismatch':
            return $value === '1' ? 'I understand that stream transitions can be difficult.' : 'That alignment can be an advantage.';
        case 'financial_issues':
            return $value === '1' ? 'I appreciate your honesty about financial pressure.' : 'Understood, that helps planning.';
        case 'worked_after_school':
            return $value === '1' ? 'Balancing work and studies takes effort.' : 'Got it, we will focus on study routines directly.';
        case 'feeling_about_studies':
            return 'Thanks, your current feeling matters for planning.';
        case 'discomfort_due_to_issues':
            return $value === '1' ? 'Thanks for being open about this.' : 'Good to know, we will still keep wellbeing in check.';
        default:
            return 'Thanks, noted.';
    }
}

function persistInterviewAnswers(mysqli $conn, int $studentId, array $answers): void
{
    $attemptedExam = normalizeEnum((string) ($answers['attempted_exam'] ?? 'none'), ['none', 'neet', 'jee', 'both'], 'none');
    $targetStream = trim((string) ($answers['target_stream'] ?? ''));
    $currentStream = trim((string) ($answers['current_stream'] ?? ''));
    $streamMismatch = toBoolInt($answers['stream_mismatch'] ?? '0');
    $financialIssues = toBoolInt($answers['financial_issues'] ?? '0');
    $workedAfterSchool = toBoolInt($answers['worked_after_school'] ?? '0');
    $workHistoryNote = trim((string) ($answers['work_history_note'] ?? ''));
    $studyGapMonths = max(0, (int) ($answers['study_gap_months'] ?? 0));
    $gapYears = max(0, (int) ($answers['gap_years'] ?? 0));
    $gapYearReason = trim((string) ($answers['gap_year_reason'] ?? ''));
    $feelingAboutStudies = normalizeEnum((string) ($answers['feeling_about_studies'] ?? 'neutral'), ['motivated', 'neutral', 'stressed', 'burned_out'], 'neutral');
    $discomfortDueToIssues = toBoolInt($answers['discomfort_due_to_issues'] ?? '0');
    $discomfortReason = trim((string) ($answers['discomfort_reason'] ?? ''));
    $confidenceLevel = normalizeEnum((string) ($answers['confidence_level'] ?? 'medium'), ['low', 'medium', 'high'], 'medium');
    $primaryChallenge = trim((string) ($answers['primary_challenge'] ?? ''));
    $goals = trim((string) ($answers['goals'] ?? ''));

    $sql = '
        INSERT INTO student_background_profiles (
            student_id, attempted_exam, target_stream, current_stream, stream_mismatch,
            financial_issues, worked_after_school, work_history_note, study_gap_months, gap_years, gap_year_reason,
            feeling_about_studies, discomfort_due_to_issues, discomfort_reason,
            confidence_level, primary_challenge, goals
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            attempted_exam = VALUES(attempted_exam),
            target_stream = VALUES(target_stream),
            current_stream = VALUES(current_stream),
            stream_mismatch = VALUES(stream_mismatch),
            financial_issues = VALUES(financial_issues),
            worked_after_school = VALUES(worked_after_school),
            work_history_note = VALUES(work_history_note),
            study_gap_months = VALUES(study_gap_months),
            gap_years = VALUES(gap_years),
            gap_year_reason = VALUES(gap_year_reason),
            feeling_about_studies = VALUES(feeling_about_studies),
            discomfort_due_to_issues = VALUES(discomfort_due_to_issues),
            discomfort_reason = VALUES(discomfort_reason),
            confidence_level = VALUES(confidence_level),
            primary_challenge = VALUES(primary_challenge),
            goals = VALUES(goals),
            updated_at = CURRENT_TIMESTAMP
    ';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Failed to save chatbot interview answers.');
    }

    $stmt->bind_param(
        'isssiiisiississss',
        $studentId,
        $attemptedExam,
        $targetStream,
        $currentStream,
        $streamMismatch,
        $financialIssues,
        $workedAfterSchool,
        $workHistoryNote,
        $studyGapMonths,
        $gapYears,
        $gapYearReason,
        $feelingAboutStudies,
        $discomfortDueToIssues,
        $discomfortReason,
        $confidenceLevel,
        $primaryChallenge,
        $goals
    );
    $stmt->execute();
}

function toBoolInt($value): int
{
    $stringValue = strtolower(trim((string) $value));
    if ($stringValue === '1' || $stringValue === 'true' || $stringValue === 'yes') {
        return 1;
    }
    return 0;
}

function normalizeEnum(string $value, array $allowed, string $fallback): string
{
    $v = strtolower(trim($value));
    if (in_array($v, $allowed, true)) {
        return $v;
    }
    return $fallback;
}

function buildStudentGuidanceReply(string $q, array $stats, ?array $profile): string
{
    if (strpos($q, 'attendance') !== false) {
        $reply = $stats['attendance_pct'] < 85
            ? 'Your attendance is below 85%. Try setting a daily reminder and review one micro-lesson each day you miss class.'
            : 'Your attendance is on track. Keep the momentum by maintaining a consistent schedule.';
    } elseif (strpos($q, 'score') !== false || strpos($q, 'grade') !== false || strpos($q, 'exam') !== false) {
        $reply = $stats['avg_score'] < 65
            ? 'Your current average suggests extra support is needed. Focus on the weakest subject first and do 20-30 minutes of practice daily.'
            : 'Your score trend is stable. Continue targeted practice and weekly revision to improve mastery.';
    } elseif (strpos($q, 'stress') !== false || strpos($q, 'mental') !== false || strpos($q, 'anxious') !== false) {
        $reply = 'If you feel stressed, break study time into short sessions, take 5-minute pauses, and speak to a counselor or trusted adult for support.';
    } elseif (strpos($q, 'gap') !== false || strpos($q, 'out of touch') !== false) {
        $reply = 'For education gaps, restart with fundamentals for 2 weeks, then move to mixed practice. Use a daily recall routine to rebuild memory.';
    } elseif (strpos($q, 'jee') !== false || strpos($q, 'neet') !== false || strpos($q, 'stream') !== false) {
        $reply = 'If your stream changed after JEE or NEET preparation, convert prior strengths into current subjects through bridge topics and weekly mentor feedback.';
    } elseif (strpos($q, 'plan') !== false || strpos($q, 'study') !== false) {
        $reply = 'Suggested plan: 1) 25 minutes focused practice, 2) 10-question quiz, 3) review mistakes, 4) ask for help on difficult topics.';
    } else {
        $reply = 'I can help with study plans, attendance improvement, stress support, and learning goals. If you want, say "start profile interview" for personalized guidance.';
    }

    if ($profile) {
        $context = [];
        if (($profile['attempted_exam'] ?? 'none') !== 'none') {
            $context[] = strtoupper((string) $profile['attempted_exam']) . ' background';
        }
        if ((int) ($profile['stream_mismatch'] ?? 0) === 1 && !empty($profile['target_stream']) && !empty($profile['current_stream'])) {
            $context[] = 'stream transition from ' . $profile['target_stream'] . ' to ' . $profile['current_stream'];
        }
        if ((int) ($profile['financial_issues'] ?? 0) === 1) {
            $context[] = 'financial pressure';
        }
        if ((int) ($profile['worked_after_school'] ?? 0) === 1) {
            $context[] = 'work-study history';
        }
        if ((int) ($profile['study_gap_months'] ?? 0) >= 6) {
            $context[] = 'study gap of ' . (int) $profile['study_gap_months'] . ' months';
        }
        if ((int) ($profile['gap_years'] ?? 0) > 0) {
            $context[] = (int) $profile['gap_years'] . ' gap year(s)';
        }

        if (!empty($context)) {
            $reply .= ' Based on your profile (' . implode(', ', $context) . '), start with foundational revision and short daily consistency goals.';
        }

        if ((int) ($profile['gap_years'] ?? 0) > 0 || (int) ($profile['study_gap_months'] ?? 0) >= 6) {
            $reply .= ' Because you had a study gap, use a bridge plan: basics first, then timed practice after week two.';
        }
        if ((int) ($profile['stream_mismatch'] ?? 0) === 1) {
            $reply .= ' For stream transition, map transferable concepts from your previous preparation to current coursework.';
        }
        if ((int) ($profile['financial_issues'] ?? 0) === 1) {
            $reply .= ' For financial pressure, prioritize low-cost digital resources and community hub tutoring support.';
        }

        if (!empty($profile['goals'])) {
            $reply .= ' Your stated goal: "' . $profile['goals'] . '".';
        }
    }

    return $reply;
}