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
            'conversation_history' => [],
            'asked_questions' => [],
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

    $reply = buildSmartGuidanceReply($conn, $studentId, $q, $stats, $profile, $state);
    
    // Track the conversation
    $state['conversation_history'][] = [
        'role' => 'user',
        'message' => $message,
        'timestamp' => time(),
    ];
    $state['conversation_history'][] = [
        'role' => 'bot',
        'message' => $reply,
        'timestamp' => time(),
    ];
    
    $_SESSION[$sessionKey] = $state;

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

function saveConversationMessage(mysqli $conn, int $studentId, string $userMessage, string $botReply, string $topic = 'general'): void
{
    $stmt = $conn->prepare('
        INSERT INTO student_chatbot_conversations (student_id, user_message, bot_reply, message_type, topic_category)
        VALUES (?, ?, ?, "question", ?)
    ');
    
    if ($stmt) {
        $stmt->bind_param('isss', $studentId, $userMessage, $botReply, $topic);
        $stmt->execute();
    }
}

function loadPastConversationPatterns(mysqli $conn, int $studentId): array
{
    $patterns = [
        'frequently_discussed' => [],
        'recent_concerns' => [],
        'resolved_issues' => [],
        'recurring_topics' => [],
    ];
    
    // Get the 50 most recent conversations
    $result = $conn->query("
        SELECT user_message, bot_reply, topic_category, created_at
        FROM student_chatbot_conversations
        WHERE student_id = {$studentId}
        ORDER BY created_at DESC
        LIMIT 50
    ");
    
    if (!$result) {
        return $patterns;
    }
    
    $topicCounts = [];
    $allTopics = [];
    
    while ($row = $result->fetch_assoc()) {
        $topic = $row['topic_category'] ?? 'general';
        $allTopics[] = $topic;
        
        $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;
    }
    
    // Find frequently discussed topics
    arsort($topicCounts);
    $patterns['frequently_discussed'] = array_keys(array_slice($topicCounts, 0, 3, true));
    
    // Find recurring topics (appeared more than once)
    $patterns['recurring_topics'] = array_keys(array_filter($topicCounts, function($count) { return $count > 1; }));
    
    return $patterns;
}

function buildSmartGuidanceReply(mysqli $conn, int $studentId, string $q, array $stats, ?array $profile, array &$state): string
{
    // Analyze gaps in student profile
    $profileGaps = identifyProfileGaps($profile);
    $performanceIssues = identifyPerformanceIssues($stats);
    $recentTopics = extractConversationTopics($state['conversation_history']);
    
    // Get conversation patterns from database (recent conversations)
    $pastPatterns = loadPastConversationPatterns($conn, $studentId);
    
    // First, check if user is asking about something specific
    $directReply = handleDirectQuestions($q, $stats, $profile);
    if ($directReply) {
        // Save to database
        saveConversationMessage($conn, $studentId, htmlspecialchars($q, ENT_QUOTES), htmlspecialchars($directReply, ENT_QUOTES), 'question');
        return $directReply;
    }
    
    // Generate personalized follow-up questions based on what we know
    $nextQuestion = generateSmartFollowUp($studentId, $stats, $profile, $state, $profileGaps, $performanceIssues, $recentTopics, $pastPatterns);
    
    // Save to database
    saveConversationMessage($conn, $studentId, htmlspecialchars($q, ENT_QUOTES), htmlspecialchars($nextQuestion, ENT_QUOTES), 'question');
    
    return $nextQuestion;
}

function identifyProfileGaps(?array $profile): array
{
    $gaps = [];
    
    if (!$profile) {
        return ['all' => ['attempted_exam', 'confidence_level', 'goals', 'primary_challenge']];
    }
    
    $criticalFields = [
        'attempted_exam' => 'none',
        'confidence_level' => null,
        'feeling_about_studies' => null,
        'primary_challenge' => null,
        'goals' => null,
    ];
    
    foreach ($criticalFields as $field => $emptyValue) {
        $value = $profile[$field] ?? $emptyValue;
        if ($value === null || $value === '' || $value === $emptyValue) {
            $gaps[] = $field;
        }
    }
    
    return $gaps;
}

function identifyPerformanceIssues(array $stats): array
{
    $issues = [];
    
    if ($stats['attendance_pct'] < 85) {
        $issues[] = ['type' => 'attendance', 'severity' => 'high', 'value' => $stats['attendance_pct']];
    }
    
    if ($stats['avg_score'] < 65) {
        $issues[] = ['type' => 'low_scores', 'severity' => 'high', 'value' => $stats['avg_score']];
    }
    
    if ($stats['completion_pct'] < 50) {
        $issues[] = ['type' => 'low_completion', 'severity' => 'medium', 'value' => $stats['completion_pct']];
    }
    
    if ($stats['behavior_incidents'] > 2) {
        $issues[] = ['type' => 'behavior', 'severity' => 'medium', 'value' => $stats['behavior_incidents']];
    }
    
    return $issues;
}

function extractConversationTopics(array $history): array
{
    $topics = [];
    $keywords = [
        'attendance' => ['attend', 'absent', 'present', 'class', 'miss'],
        'scores' => ['score', 'grade', 'mark', 'exam', 'test', 'perform'],
        'stress' => ['stress', 'anxious', 'pressure', 'overwhelm', 'burden'],
        'gap' => ['gap', 'break', 'break study', 'took time off'],
        'stream' => ['stream', 'subject', 'course', 'discipline'],
        'financial' => ['money', 'cost', 'fee', 'financial', 'afford'],
        'sleep' => ['sleep', 'tired', 'exhausted', 'rest', 'fatigue'],
        'motivation' => ['motiv', 'interest', 'bored', 'engaged', 'focus'],
    ];
    
    foreach ($history as $entry) {
        if ($entry['role'] !== 'user') continue;
        
        $msg = strtolower($entry['message']);
        foreach ($keywords as $topic => $words) {
            foreach ($words as $word) {
                if (strpos($msg, $word) !== false) {
                    if (!in_array($topic, $topics)) {
                        $topics[] = $topic;
                    }
                }
            }
        }
    }
    
    return $topics;
}

function handleDirectQuestions(string $q, array $stats, ?array $profile): ?string
{
    if (strpos($q, 'attendance') !== false) {
        $reply = $stats['attendance_pct'] < 85
            ? 'Your attendance is at ' . round($stats['attendance_pct'], 1) . '%. Try setting daily reminders to attend all classes. Even one day off impacts your consistency. What\'s causing you to miss class?'
            : 'Your attendance (' . round($stats['attendance_pct'], 1) . '%) is excellent! How are you managing to maintain this consistency?';
        return $reply;
    }
    
    if (strpos($q, 'score') !== false || strpos($q, 'grade') !== false) {
        $reply = $stats['avg_score'] < 65
            ? 'Your average score is ' . round($stats['avg_score'], 1) . '%. Let\'s focus on your weakest subject first. Which subject is hardest for you right now?'
            : 'Your average score is ' . round($stats['avg_score'], 1) . '%, which shows decent progress. What topic would you like to strengthen the most?';
        return $reply;
    }
    
    if (strpos($q, 'plan') !== false || (strpos($q, 'study') !== false && strpos($q, 'plan') !== false)) {
        return 'A good daily plan: Start with your weakest subject (25 min focused study), then take 5-min break, follow with 10-question quiz, review mistakes, ask for help. Would you like to create one together?';
    }
    
    if (strpos($q, 'stress') !== false || strpos($q, 'anxious') !== false || strpos($q, 'pressure') !== false) {
        return 'Stress is common. Try the Pomodoro technique (25-min study + 5-min break), take short walks, and talk to a counselor. What specifically is stressing you most?';
    }
    
    if (strpos($q, 'sleep') !== false || strpos($q, 'tired') !== false || strpos($q, 'exhausted') !== false) {
        return 'Sleep is crucial for learning. Aim for 7-8 hours and keep a consistent sleep schedule. Are you getting enough sleep, or is something keeping you awake?';
    }
    
    if (strpos($q, 'motivation') !== false || strpos($q, 'interested') !== false || strpos($q, 'bored') !== false) {
        return 'Motivation often comes from connecting learning to your goals. What do you actually want to achieve after your studies?';
    }
    
    return null;
}

function generateSmartFollowUp(int $studentId, array $stats, ?array $profile, array $state, array $profileGaps, array $performanceIssues, array $recentTopics, array $pastPatterns = []): string
{
    // Priority order: 1. Address critical gaps, 2. Address performance issues, 3. Dig deeper into recent topics
    // But avoid topics that have been discussed extensively already
    
    $frequentlyAsked = $pastPatterns['frequently_discussed'] ?? [];
    
    // If we haven't covered attendance issues, ask about it  
    if (!in_array('attendance', $recentTopics) && !in_array('attendance', $frequentlyAsked) && $stats['attendance_pct'] < 85) {
        return 'I notice your attendance is ' . round($stats['attendance_pct'], 1) . '%. What\'s the main reason you\'re missing classes? Is it personal issues, health, or something else?';
    }
    
    // If scores are low and not discussed extensively
    if (!in_array('scores', $recentTopics) && !in_array('scores', $frequentlyAsked) && $stats['avg_score'] < 65) {
        $weakestArea = $stats['avg_score'] < 50 ? 'critical' : 'needs improvement';
        return 'Your scores are in a ' . $weakestArea . ' range. What\'s the biggest topic or concept that\'s confusing for you?';
    }
    
    // Fill critical profile gaps (these are important and should be asked)
    if (in_array('primary_challenge', $profileGaps)) {
        return 'To help you better, what would you say is your single biggest challenge in studies right now?';
    }
    
    if (in_array('goals', $profileGaps)) {
        return 'What\'s your main goal for the next 3 months? Are you aiming for better grades, completing modules, or something else?';
    }
    
    if (in_array('confidence_level', $profileGaps)) {
        return 'How confident do you feel about achieving your academic goals right now: low, medium, or high?';
    }
    
    if (in_array('feeling_about_studies', $profileGaps) && !in_array('motivation', $recentTopics) && !in_array('stress', $frequentlyAsked)) {
        return 'Overall, how are you feeling about your studies? Are you motivated, neutral, stressed, or burned out?';
    }
    
    // If profile has gaps in exam attempts
    if ($profile && ($profile['attempted_exam'] ?? 'none') === 'none' && !in_array('stream', $recentTopics) && !in_array('stream', $frequentlyAsked)) {
        return 'Have you prepared for or attempted any competitive exams (JEE or NEET)? This helps me understand your goals better.';
    }
    
    // Deep dive into noted challenges with contextual follow-ups
    if ($profile && !empty($profile['primary_challenge'])) {
        $challenge = strtolower($profile['primary_challenge']);
        
        // Rotate through different follow-up types to avoid repetition
        $rand = rand(1, 3);
        
        if (strpos($challenge, 'time') !== false) {
            if ($rand === 1) {
                return 'For time management, how many hours per day can you realistically study?';
            } elseif ($rand === 2) {
                return 'Do you have a fixed schedule for studying, or is it more flexible/random?';
            } else {
                return 'What time of day are you most productive for studying?';
            }
        }
        
        if (strpos($challenge, 'concept') !== false || strpos($challenge, 'understand') !== false) {
            if ($rand === 1) {
                return 'For understanding concepts, breaking them into smaller parts helps. Which subject feels the hardest to grasp?';
            } elseif ($rand === 2) {
                return 'Do you learn better with examples, diagrams, or step-by-step explanations?';
            } else {
                return 'Have you tried explaining concepts to someone else or teaching topics to deepen understanding?';
            }
        }
        
        if (strpos($challenge, 'focus') !== false || strpos($challenge, 'concentration') !== false || strpos($challenge, 'distraction') !== false) {
            if ($rand === 1) {
                return 'For focus and concentration, what\'s your biggest distraction: phone, noise, fatigue, or something else?';
            } elseif ($rand === 2) {
                return 'Do you use any techniques to stay focused, like Pomodoro timer (25 min focus + 5 min break)?';
            } else {
                return 'Where do you study best: at home, library, community hub, or elsewhere?';
            }
        }
    }
    
    // If student has financial or work-study history, check multiple angles
    if ($profile && ((int)($profile['financial_issues'] ?? 0) || (int)($profile['worked_after_school'] ?? 0))) {
        if (!in_array('stress', $recentTopics) && !in_array('financial', $frequentlyAsked)) {
            return 'Managing finances or work alongside studies is challenging. How is this affecting your ability to focus and sleep?';
        }
        if (!in_array('financial', $recentTopics)) {
            return 'For financial challenges, are there low-cost resources (community hubs, free online courses) that could help?';
        }
    }
    
    // If stream mismatch, explore multiple dimensions
    if ($profile && (int)($profile['stream_mismatch'] ?? 0) === 1) {
        if (!in_array('stream', $recentTopics)) {
            $favResponse = rand(1, 2) === 1 
                ? 'Since you changed streams, which subjects from your previous stream do you miss?' 
                : 'How are you adjusting to your current stream? Are you enjoying the new subjects?';
            return $favResponse;
        }
    }
    
    // If study gap, explore readiness and progress
    if ($profile && ((int)($profile['study_gap_months'] ?? 0) >= 6 || (int)($profile['gap_years'] ?? 0) > 0)) {
        if (!in_array('gap', $recentTopics) && !in_array('gap', $frequentlyAsked)) {
            return 'After your study gap, what feels the rustiest: specific subjects or general problem-solving skills?';
        }
        if (!in_array('motivation', $recentTopics)) {
            return 'Since returning to studies, what\'s been the hardest adjustment?';
        }
    }
    
    // Monitor progress on known issues
    if ($stats['completion_pct'] < 50 && !in_array('completion', $frequentlyAsked)) {
        return 'I see you\'ve completed about ' . round($stats['completion_pct'], 0) . '% of your modules. What\'s the main blocker: difficulty, time, or interest?';
    }
    
    // Reinforce positive progress
    if ($stats['avg_score'] >= 75 && $stats['attendance_pct'] >= 85 && !in_array('celebration', $frequentlyAsked)) {
        return 'Your scores and attendance are excellent! What\'s your secret to maintaining such good performance?';
    }
    
    // Exploratory questions - rotate through different angles to avoid repetition
    $exploratoryQuestions = [
        'What do you find most interesting to study?',
        'If you could change one thing about how you learn, what would it be?',
        'Do you have a mentor, tutor, or peer study group?',
        'What\'s one small win you\'ve achieved in your studies recently?',
        'Are there any subjects where you feel naturally talented?',
        'What would make studying feel less overwhelming?',
        'Do you have someone you can talk to when struggling?',
        'How do you typically prepare for exams or assessments?',
        'What\'s one thing you wish teachers explained better?',
        'Are there any personal circumstances affecting your studies that I should know?',
    ];
    
    // Return a question not yet asked recently
    $askedQuestions = $state['asked_questions'] ?? [];
    $unused = array_filter($exploratoryQuestions, function($q) use ($askedQuestions) {
        return !in_array($q, $askedQuestions);
    });
    
    if (!empty($unused)) {
        $nextQuestion = reset($unused);
        $state['asked_questions'][] = $nextQuestion;
        return $nextQuestion;
    }
    
    // If all questions have been asked, reset and return a fresh one
    if (count($askedQuestions) > 5) {
        $state['asked_questions'] = [];
    }
    
    return 'How can I help you with your studies today? Ask me about scores, attendance, focus, stress, or your learning goals.';
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