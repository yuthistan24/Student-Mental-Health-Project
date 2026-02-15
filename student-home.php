<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$student = requireStudentLogin(false);

$stats = [
    'attendance_pct' => 0,
    'avg_score' => 0,
    'behavior_incidents' => 0,
    'completion_pct' => 0,
    'mastery_score' => 0,
];

try {
    $conn = getDbConnection($dbConfig);
    $studentId = (int) $student['student_id'];

    $profileCheck = $conn->prepare('SELECT student_id FROM student_background_profiles WHERE student_id = ? LIMIT 1');
    if ($profileCheck) {
        $profileCheck->bind_param('i', $studentId);
        $profileCheck->execute();
        $profile = $profileCheck->get_result()->fetch_assoc();
        if (!$profile) {
            header('Location: student-onboarding.php');
            exit;
        }
    }

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
} catch (Throwable $e) {
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Home | Learning Support Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <header class="topbar">
    <div class="brand"><a href="index.php" style="text-decoration:none;color:inherit;">Learning Support Portal</a></div>
    <nav>
      <a href="student-home.php">My Home</a>
      <a href="index.php">Public Home</a>
    </nav>
    <div class="user-chip">
      <strong><?php echo htmlspecialchars($student['full_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
      <span>Student (Grade <?php echo htmlspecialchars($student['grade_level'], ENT_QUOTES, 'UTF-8'); ?>)</span>
      <a class="logout-link" href="logout.php">Logout</a>
    </div>
  </header>

  <main>
    <section class="hero">
      <div>
        <p class="eyebrow">Student Portal</p>
        <h1>Welcome, <?php echo htmlspecialchars($student['full_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="subtitle">Track your attendance, learning progress, and support indicators in one place.</p>
      </div>
      <div class="hero-card">
        <h2>Your Snapshot</h2>
        <div class="risk-summary">
          <div><strong><?php echo number_format($stats['attendance_pct'], 1); ?>%</strong> attendance</div>
          <div><strong><?php echo number_format($stats['avg_score'], 1); ?></strong> average score</div>
          <div><strong><?php echo number_format($stats['completion_pct'], 1); ?>%</strong> module completion</div>
        </div>
      </div>
    </section>

    <section class="panel">
      <h2>My Learning & Wellbeing Indicators</h2>
      <div class="grid three">
        <article class="metric">
          <h3><?php echo number_format($stats['mastery_score'], 1); ?></h3>
          <span>Mastery Score</span>
        </article>
        <article class="metric">
          <h3><?php echo (int) $stats['behavior_incidents']; ?></h3>
          <span>Behavior Incidents (Moderate/High)</span>
        </article>
        <article class="metric">
          <h3><?php echo htmlspecialchars($student['grade_level'], ENT_QUOTES, 'UTF-8'); ?></h3>
          <span>Current Grade Level</span>
        </article>
      </div>
    </section>

    <section class="panel">
      <h2>AI Study Assistant</h2>
      <p>Ask for study plans, attendance tips, and wellbeing guidance.</p>
      <div class="chat-shell">
        <div id="chat-window" class="chat-window">
          <div class="chat-message bot">I am your study assistant. Ask: "How can I improve my attendance?"</div>
        </div>
        <form id="chat-form" class="chat-form">
          <input id="chat-input" type="text" placeholder="Type your question..." maxlength="500" required />
          <button type="submit">Send</button>
        </form>
      </div>
    </section>
  </main>

  <footer>
    <p>Learning Support Portal Student Portal | Keep learning and check your progress regularly.</p>
  </footer>
  <script src="assets/js/student-home.js"></script>
</body>
</html>

