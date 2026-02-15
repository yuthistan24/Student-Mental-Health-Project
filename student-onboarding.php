<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$student = requireStudentLogin(false);
$studentId = (int) $student['student_id'];

$error = '';
$existing = null;

try {
    $conn = getDbConnection($dbConfig);
    $stmt = $conn->prepare('SELECT * FROM student_background_profiles WHERE student_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
    }
} catch (Throwable $e) {
}

$form = [
    'attempted_exam' => $existing['attempted_exam'] ?? 'none',
    'target_stream' => $existing['target_stream'] ?? '',
    'current_stream' => $existing['current_stream'] ?? '',
    'stream_mismatch' => isset($existing['stream_mismatch']) ? (string) $existing['stream_mismatch'] : '0',
    'financial_issues' => isset($existing['financial_issues']) ? (string) $existing['financial_issues'] : '0',
    'worked_after_school' => isset($existing['worked_after_school']) ? (string) $existing['worked_after_school'] : '0',
    'work_history_note' => $existing['work_history_note'] ?? '',
    'study_gap_months' => isset($existing['study_gap_months']) ? (string) $existing['study_gap_months'] : '0',
    'confidence_level' => $existing['confidence_level'] ?? 'medium',
    'primary_challenge' => $existing['primary_challenge'] ?? '',
    'goals' => $existing['goals'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $value) {
        $form[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    if (!in_array($form['attempted_exam'], ['none', 'neet', 'jee', 'both'], true)) {
        $error = 'Please select a valid exam history.';
    } elseif (!in_array($form['confidence_level'], ['low', 'medium', 'high'], true)) {
        $error = 'Please select a valid confidence level.';
    } else {
        try {
            $conn = getDbConnection($dbConfig);
            $streamMismatch = $form['stream_mismatch'] === '1' ? 1 : 0;
            $financialIssues = $form['financial_issues'] === '1' ? 1 : 0;
            $workedAfterSchool = $form['worked_after_school'] === '1' ? 1 : 0;
            $studyGapMonths = max(0, (int) $form['study_gap_months']);

            $sql = '
                INSERT INTO student_background_profiles (
                    student_id, attempted_exam, target_stream, current_stream, stream_mismatch,
                    financial_issues, worked_after_school, work_history_note, study_gap_months,
                    confidence_level, primary_challenge, goals
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    attempted_exam = VALUES(attempted_exam),
                    target_stream = VALUES(target_stream),
                    current_stream = VALUES(current_stream),
                    stream_mismatch = VALUES(stream_mismatch),
                    financial_issues = VALUES(financial_issues),
                    worked_after_school = VALUES(worked_after_school),
                    work_history_note = VALUES(work_history_note),
                    study_gap_months = VALUES(study_gap_months),
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
                'isssiiisisss',
                $studentId,
                $form['attempted_exam'],
                $form['target_stream'],
                $form['current_stream'],
                $streamMismatch,
                $financialIssues,
                $workedAfterSchool,
                $form['work_history_note'],
                $studyGapMonths,
                $form['confidence_level'],
                $form['primary_challenge'],
                $form['goals']
            );
            $stmt->execute();

            header('Location: student-home.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Unable to save your responses: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Onboarding | Learning Support Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <main class="auth-wrapper">
    <section class="auth-card" style="width:min(760px,95vw);">
      <p class="eyebrow">Student Onboarding</p>
      <h1>Tell us your background</h1>
      <p class="subtitle">These answers help personalize your AI guidance and learning path.</p>

      <?php if ($error !== ''): ?>
        <p class="error-text"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php endif; ?>

      <form method="post" class="auth-form">
        <label>Have you previously prepared for NEET/JEE?
          <select name="attempted_exam" required>
            <option value="none" <?php echo $form['attempted_exam'] === 'none' ? 'selected' : ''; ?>>No</option>
            <option value="neet" <?php echo $form['attempted_exam'] === 'neet' ? 'selected' : ''; ?>>NEET</option>
            <option value="jee" <?php echo $form['attempted_exam'] === 'jee' ? 'selected' : ''; ?>>JEE</option>
            <option value="both" <?php echo $form['attempted_exam'] === 'both' ? 'selected' : ''; ?>>Both</option>
          </select>
        </label>

        <label>Which stream did you hope for?
          <input type="text" name="target_stream" value="<?php echo htmlspecialchars($form['target_stream'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g., Medicine / Engineering" />
        </label>

        <label>Which stream are you currently in?
          <input type="text" name="current_stream" value="<?php echo htmlspecialchars($form['current_stream'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g., B.Sc / Commerce / Arts" />
        </label>

        <label>Are you in a different stream than you hoped for?
          <select name="stream_mismatch" required>
            <option value="0" <?php echo $form['stream_mismatch'] === '0' ? 'selected' : ''; ?>>No</option>
            <option value="1" <?php echo $form['stream_mismatch'] === '1' ? 'selected' : ''; ?>>Yes</option>
          </select>
        </label>

        <label>Do financial issues affect your studies?
          <select name="financial_issues" required>
            <option value="0" <?php echo $form['financial_issues'] === '0' ? 'selected' : ''; ?>>No</option>
            <option value="1" <?php echo $form['financial_issues'] === '1' ? 'selected' : ''; ?>>Yes</option>
          </select>
        </label>

        <label>Did you work after school / during study gap?
          <select name="worked_after_school" required>
            <option value="0" <?php echo $form['worked_after_school'] === '0' ? 'selected' : ''; ?>>No</option>
            <option value="1" <?php echo $form['worked_after_school'] === '1' ? 'selected' : ''; ?>>Yes</option>
          </select>
        </label>

        <label>If yes, what kind of work did you do?
          <input type="text" name="work_history_note" value="<?php echo htmlspecialchars($form['work_history_note'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Part-time shop work, tutoring, etc." />
        </label>

        <label>How many months were you out of regular study?
          <input type="number" min="0" name="study_gap_months" value="<?php echo htmlspecialchars($form['study_gap_months'], ENT_QUOTES, 'UTF-8'); ?>" />
        </label>

        <label>Current confidence level
          <select name="confidence_level" required>
            <option value="low" <?php echo $form['confidence_level'] === 'low' ? 'selected' : ''; ?>>Low</option>
            <option value="medium" <?php echo $form['confidence_level'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
            <option value="high" <?php echo $form['confidence_level'] === 'high' ? 'selected' : ''; ?>>High</option>
          </select>
        </label>

        <label>Primary challenge right now
          <input type="text" name="primary_challenge" value="<?php echo htmlspecialchars($form['primary_challenge'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Memory gaps, stress, low motivation, etc." />
        </label>

        <label>Main goal for next 3 months
          <input type="text" name="goals" value="<?php echo htmlspecialchars($form['goals'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Improve basics, clear backlog, exam prep" />
        </label>

        <button type="submit">Save and Continue</button>
      </form>
    </section>
  </main>
</body>
</html>
