<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$student = requireStudentLogin(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed.'], 405);
}

$payload = json_decode(file_get_contents('php://input'), true);
$action = (string) ($payload['action'] ?? 'answer');
$message = trim((string) ($payload['message'] ?? ''));
$studentId = (int) $student['student_id'];
$sessionKey = 'onboarding_interview_' . $studentId;

if (!isset($_SESSION[$sessionKey]) || $action === 'reset') {
    $_SESSION[$sessionKey] = [
        'index' => 0,
        'answers' => [],
        'done' => false,
    ];
}

$state = $_SESSION[$sessionKey];
$flow = buildInterviewFlow($state['answers']);
$state['index'] = max(0, min((int) $state['index'], count($flow)));

if ($action === 'start') {
    if (!empty($state['done'])) {
        $state = [
            'index' => 0,
            'answers' => [],
            'done' => false,
        ];
    }
    $flow = buildInterviewFlow($state['answers']);
    $state['index'] = max(0, min((int) $state['index'], count($flow)));

    if (empty($flow)) {
        $state['done'] = true;
        $_SESSION[$sessionKey] = $state;
        jsonResponse([
            'reply' => 'Your interview is complete.',
            'done' => true,
            'next_question' => null,
            'redirect_to' => 'student-home.php',
        ]);
    }

    $question = $flow[$state['index']]['question'];
    $_SESSION[$sessionKey] = $state;
    jsonResponse([
        'reply' => 'Let us do a short personalized interview. ' . $question,
        'done' => false,
        'next_question' => $question,
        'field' => null,
        'recognized_value' => null,
    ]);
}

if ($state['done']) {
    jsonResponse([
        'reply' => 'Interview already completed.',
        'done' => true,
        'next_question' => null,
        'redirect_to' => 'student-home.php',
    ]);
}

if ($message === '') {
    jsonResponse(['error' => 'Message is required.'], 422);
}

if (!isset($flow[$state['index']])) {
    persistInterviewAnswers($state['answers'], $studentId, $dbConfig);
    $state['done'] = true;
    $_SESSION[$sessionKey] = $state;
    jsonResponse([
        'reply' => 'Great, onboarding interview complete. I have saved your profile.',
        'done' => true,
        'next_question' => null,
        'redirect_to' => 'student-home.php',
    ]);
}

$current = $flow[$state['index']];
$parsed = parseAnswer((string) $current['field'], $message);
if ($parsed['value'] === null) {
    $clarify = $parsed['follow_up'] ?: ('I did not fully catch that. ' . $current['question']);
    jsonResponse([
        'reply' => $clarify,
        'done' => false,
        'next_question' => $current['question'],
        'field' => null,
        'recognized_value' => null,
    ]);
}

if (!empty($current['store'])) {
    $state['answers'][$current['field']] = $parsed['value'];
}

$ack = buildAcknowledgement((string) $current['field'], (string) $parsed['value']);
$state['index']++;
$flow = buildInterviewFlow($state['answers']);

if ($state['index'] >= count($flow)) {
    persistInterviewAnswers($state['answers'], $studentId, $dbConfig);
    $state['done'] = true;
    $_SESSION[$sessionKey] = $state;
    jsonResponse([
        'reply' => trim($ack . ' Great, onboarding interview complete. I have saved your profile.'),
        'done' => true,
        'next_question' => null,
        'redirect_to' => 'student-home.php',
        'field' => !empty($current['store']) ? $current['field'] : null,
        'recognized_value' => !empty($current['store']) ? $parsed['value'] : null,
    ]);
}

$nextQuestion = $flow[$state['index']]['question'];
$state['done'] = false;
$_SESSION[$sessionKey] = $state;

jsonResponse([
    'reply' => trim($ack . ' ' . $nextQuestion),
    'done' => false,
    'next_question' => $nextQuestion,
    'field' => !empty($current['store']) ? $current['field'] : null,
    'recognized_value' => !empty($current['store']) ? $parsed['value'] : null,
]);

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
        question('attempted_exam', 'Have you attempted JEE, NEET, both, or neither?', true),
    ];

    if ($attemptedExam !== '' && $attemptedExam !== 'none') {
        $flow[] = question('exam_attempt_context', 'Thanks for sharing. Could you briefly tell me how those attempts affected your confidence or stress?', false);
    }

    $flow[] = question('target_stream', 'Which stream were you aiming for originally?', true);
    $flow[] = question('current_stream', 'Which stream are you currently in now?', true);
    $flow[] = question('stream_mismatch', 'Are you in a different stream than you hoped for? Please say yes or no.', true);

    if ($streamMismatch === '1') {
        $flow[] = question('stream_mismatch_context', 'Understood. What has been the hardest part of this stream transition?', false);
    }

    $flow[] = question('financial_issues', 'Do financial issues currently affect your studies? Please say yes or no.', true);

    if ($financialIssues === '1') {
        $flow[] = question('financial_issue_context', 'I appreciate your honesty. Is the main issue tuition, living costs, or study resources?', false);
    }

    $flow[] = question('worked_after_school', 'Did you work after school or during your study break? Please say yes or no.', true);

    if ($workedAfterSchool === '1') {
        $flow[] = question('work_history_note', 'What kind of work did you do, and for how long?', true);
    } else {
        $flow[] = question('work_history_note', 'If relevant, you can mention any responsibilities that affected study time.', true);
    }

    $flow[] = question('study_gap_months', 'How many months were you away from regular study?', true);
    $flow[] = question('gap_years', 'How many total gap years do you have in education?', true);

    if ($gapYears > 0 || $studyGapMonths >= 6) {
        $flow[] = question('gap_year_reason', 'What was the main reason for your gap period?', true);
    } else {
        $flow[] = question('gap_year_reason', 'If there was no major gap reason, you can type "none".', true);
    }

    $flow[] = question('feeling_about_studies', 'How are you feeling about studies right now: motivated, neutral, stressed, or burned out?', true);
    $flow[] = question('discomfort_due_to_issues', 'Are you feeling uncomfortable due to personal or academic issues? Please say yes or no.', true);

    if ($discomfort === '1') {
        $flow[] = question('discomfort_reason', 'Thank you for sharing that. What is the main cause of this discomfort?', true);
    } else {
        $flow[] = question('discomfort_reason', 'If comfortable, you can still mention any issue we should keep in mind, otherwise type "none".', true);
    }

    $flow[] = question('confidence_level', 'How confident do you feel now: low, medium, or high?', true);
    $flow[] = question('primary_challenge', 'What is your biggest study challenge currently?', true);
    $flow[] = question('goals', 'What is your main goal for the next three months?', true);

    return $flow;
}

function question(string $field, string $question, bool $store): array
{
    return [
        'field' => $field,
        'question' => $question,
        'store' => $store,
    ];
}

function buildAcknowledgement(string $field, string $value): string
{
    switch ($field) {
        case 'attempted_exam':
            return $value === 'none'
                ? 'Thanks for sharing. It helps to know your exam background clearly.'
                : 'Thank you for sharing your exam journey.';
        case 'stream_mismatch':
            return $value === '1'
                ? 'I understand that stream mismatch can feel frustrating.'
                : 'That is good to hear. Alignment with your stream can reduce stress.';
        case 'financial_issues':
            return $value === '1'
                ? 'Thank you for being open about financial pressure.'
                : 'Understood. That gives us more flexibility in planning.';
        case 'worked_after_school':
            return $value === '1'
                ? 'Balancing work and studies takes real effort.'
                : 'Noted. We will focus directly on your current study rhythm.';
        case 'feeling_about_studies':
            return 'Thank you. Your emotional state is important for a realistic plan.';
        case 'discomfort_due_to_issues':
            return $value === '1'
                ? 'I appreciate your honesty; this helps us support you better.'
                : 'Good to know. We will still keep wellbeing checks active.';
        default:
            return 'Thanks, that is helpful.';
    }
}

function parseAnswer(string $field, string $message): array
{
    $text = strtolower(trim($message));
    $yesNo = parseYesNo($text);

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
            if ($text === '' || strpos($text, 'none') !== false || strpos($text, 'no') !== false || strpos($text, 'neither') !== false) {
                return ['value' => 'none', 'follow_up' => ''];
            }
            return ['value' => null, 'follow_up' => 'Please answer with one of: JEE, NEET, both, or neither.'];

        case 'stream_mismatch':
        case 'financial_issues':
        case 'worked_after_school':
        case 'discomfort_due_to_issues':
            if ($yesNo !== null) {
                return ['value' => $yesNo ? '1' : '0', 'follow_up' => ''];
            }
            return ['value' => null, 'follow_up' => 'Please answer with yes or no.'];

        case 'study_gap_months':
        case 'gap_years':
            if (preg_match('/\d+/', $text, $m)) {
                return ['value' => (string) max(0, (int) $m[0]), 'follow_up' => ''];
            }
            if (strpos($text, 'none') !== false || strpos($text, 'no') !== false || strpos($text, 'zero') !== false) {
                return ['value' => '0', 'follow_up' => ''];
            }
            return ['value' => null, 'follow_up' => 'Please provide a number like 0, 1, or 2.'];

        case 'feeling_about_studies':
            if (strpos($text, 'motivat') !== false || strpos($text, 'good') !== false || strpos($text, 'positive') !== false) {
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
            if (strpos($text, 'medium') !== false || strpos($text, 'moderate') !== false || strpos($text, 'average') !== false) {
                return ['value' => 'medium', 'follow_up' => ''];
            }
            if (strpos($text, 'high') !== false || strpos($text, 'very confident') !== false || strpos($text, 'strong') !== false) {
                return ['value' => 'high', 'follow_up' => ''];
            }
            return ['value' => null, 'follow_up' => 'Please answer low, medium, or high.'];

        default:
            if ($text === '') {
                return ['value' => null, 'follow_up' => 'Please share a short answer so I can personalize your plan.'];
            }
            return ['value' => trim($message), 'follow_up' => ''];
    }
}

function parseYesNo(string $text): ?bool
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

function persistInterviewAnswers(array $answers, int $studentId, array $dbConfig): void
{
    $conn = getDbConnection($dbConfig);

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
        throw new RuntimeException('Failed to save onboarding profile.');
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
