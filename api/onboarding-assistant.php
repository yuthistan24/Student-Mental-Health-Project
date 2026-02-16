<?php
require_once __DIR__ . '/../includes/auth.php';

$student = requireStudentLogin(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed.'], 405);
}

$payload = json_decode(file_get_contents('php://input'), true);
$step = max(0, (int) ($payload['step'] ?? 0));
$message = trim((string) ($payload['message'] ?? ''));

$questions = [
    ['field' => 'attempted_exam', 'question' => 'Have you attempted JEE, NEET, both, or neither?'],
    ['field' => 'target_stream', 'question' => 'Which stream were you aiming for originally?'],
    ['field' => 'current_stream', 'question' => 'Which stream are you currently in now?'],
    ['field' => 'stream_mismatch', 'question' => 'Are you in a different stream than you hoped for? Say yes or no.'],
    ['field' => 'financial_issues', 'question' => 'Do financial issues currently affect your studies? Say yes or no.'],
    ['field' => 'worked_after_school', 'question' => 'Did you work after school or during your study break? Say yes or no.'],
    ['field' => 'work_history_note', 'question' => 'If you worked, what kind of work did you do?'],
    ['field' => 'study_gap_months', 'question' => 'How many months were you away from regular study?'],
    ['field' => 'gap_years', 'question' => 'How many total gap years do you have in education?'],
    ['field' => 'gap_year_reason', 'question' => 'What is the main reason for your gap years?'],
    ['field' => 'feeling_about_studies', 'question' => 'How are you feeling about studies right now: motivated, neutral, stressed, or burned out?'],
    ['field' => 'discomfort_due_to_issues', 'question' => 'Are you feeling uncomfortable due to personal or academic issues? Say yes or no.'],
    ['field' => 'discomfort_reason', 'question' => 'If yes, what is causing this discomfort?'],
    ['field' => 'confidence_level', 'question' => 'How confident do you feel now: low, medium, or high?'],
    ['field' => 'primary_challenge', 'question' => 'What is your biggest study challenge currently?'],
    ['field' => 'goals', 'question' => 'What is your main goal for the next three months?'],
];

if ($step < 0 || $step >= count($questions)) {
    jsonResponse([
        'reply' => 'Your onboarding interview is ready. You can now save your responses.',
        'done' => true,
        'next_step' => count($questions),
    ]);
}

$field = $questions[$step]['field'];
$parsed = parseAnswer($field, $message);
if ($parsed['value'] === null) {
    jsonResponse([
        'reply' => $parsed['follow_up'] ?: $questions[$step]['question'],
        'done' => false,
        'field' => $field,
        'step' => $step,
        'next_step' => $step,
        'recognized_value' => null,
        'next_question' => $questions[$step]['question'],
    ]);
}

$nextStep = $step + 1;
$done = $nextStep >= count($questions);
$reply = $done
    ? 'Great, onboarding interview complete. Please review the form and click Save and Continue.'
    : $questions[$nextStep]['question'];

jsonResponse([
    'reply' => $reply,
    'done' => $done,
    'field' => $field,
    'step' => $step,
    'next_step' => $nextStep,
    'recognized_value' => $parsed['value'],
    'next_question' => $done ? null : $questions[$nextStep]['question'],
    'confidence_hint' => $parsed['hint'],
]);

function parseAnswer(string $field, string $message): array
{
    $text = strtolower(trim($message));
    $yesNo = parseYesNo($text);

    switch ($field) {
        case 'attempted_exam':
            if (strpos($text, 'both') !== false || (strpos($text, 'neet') !== false && strpos($text, 'jee') !== false)) {
                return ['value' => 'both', 'follow_up' => '', 'hint' => 'high'];
            }
            if (strpos($text, 'neet') !== false) {
                return ['value' => 'neet', 'follow_up' => '', 'hint' => 'high'];
            }
            if (strpos($text, 'jee') !== false) {
                return ['value' => 'jee', 'follow_up' => '', 'hint' => 'high'];
            }
            if ($text === '' || strpos($text, 'none') !== false || strpos($text, 'no') !== false || strpos($text, 'neither') !== false) {
                return ['value' => 'none', 'follow_up' => '', 'hint' => 'medium'];
            }
            return ['value' => null, 'follow_up' => 'Please say JEE, NEET, both, or neither.', 'hint' => 'low'];

        case 'stream_mismatch':
        case 'financial_issues':
        case 'worked_after_school':
        case 'discomfort_due_to_issues':
            if ($yesNo !== null) {
                return ['value' => $yesNo ? '1' : '0', 'follow_up' => '', 'hint' => 'high'];
            }
            return ['value' => null, 'follow_up' => 'Please answer with yes or no.', 'hint' => 'low'];

        case 'study_gap_months':
        case 'gap_years':
            if (preg_match('/\d+/', $text, $m)) {
                return ['value' => (string) max(0, (int) $m[0]), 'follow_up' => '', 'hint' => 'high'];
            }
            if (strpos($text, 'none') !== false || strpos($text, 'no') !== false || strpos($text, 'zero') !== false) {
                return ['value' => '0', 'follow_up' => '', 'hint' => 'medium'];
            }
            return ['value' => null, 'follow_up' => 'Please provide a number like 0, 1, or 2.', 'hint' => 'low'];

        case 'feeling_about_studies':
            if (strpos($text, 'motivat') !== false || strpos($text, 'good') !== false || strpos($text, 'positive') !== false) {
                return ['value' => 'motivated', 'follow_up' => '', 'hint' => 'medium'];
            }
            if (strpos($text, 'neutral') !== false || strpos($text, 'okay') !== false || strpos($text, 'fine') !== false) {
                return ['value' => 'neutral', 'follow_up' => '', 'hint' => 'medium'];
            }
            if (strpos($text, 'stress') !== false || strpos($text, 'anx') !== false || strpos($text, 'pressure') !== false) {
                return ['value' => 'stressed', 'follow_up' => '', 'hint' => 'medium'];
            }
            if (strpos($text, 'burn') !== false || strpos($text, 'exhaust') !== false || strpos($text, 'drain') !== false) {
                return ['value' => 'burned_out', 'follow_up' => '', 'hint' => 'medium'];
            }
            return ['value' => null, 'follow_up' => 'Please choose one: motivated, neutral, stressed, or burned out.', 'hint' => 'low'];

        case 'confidence_level':
            if (strpos($text, 'low') !== false || strpos($text, 'not confident') !== false) {
                return ['value' => 'low', 'follow_up' => '', 'hint' => 'medium'];
            }
            if (strpos($text, 'medium') !== false || strpos($text, 'moderate') !== false || strpos($text, 'average') !== false) {
                return ['value' => 'medium', 'follow_up' => '', 'hint' => 'medium'];
            }
            if (strpos($text, 'high') !== false || strpos($text, 'very confident') !== false || strpos($text, 'strong') !== false) {
                return ['value' => 'high', 'follow_up' => '', 'hint' => 'medium'];
            }
            return ['value' => null, 'follow_up' => 'Please answer low, medium, or high.', 'hint' => 'low'];

        default:
            if ($text === '') {
                return ['value' => '', 'follow_up' => '', 'hint' => 'medium'];
            }
            return ['value' => trim($message), 'follow_up' => '', 'hint' => 'high'];
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
