<?php
require_once __DIR__ . '/includes/auth.php';

$user = requireLogin(false);
$role = $user['role'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Learning Support Portal | Student Hub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <header class="topbar">
    <div class="brand"><a href="index.php" style="text-decoration:none;color:inherit;">Learning Support Portal</a></div>
    <nav>
      <a href="students.php">Students</a>
      <a href="dashboard.php">Analytics</a>
      <a href="index.php">Home</a>
    </nav>
    <div class="user-chip">
      <strong><?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
      <span><?php echo htmlspecialchars(ucfirst($role), ENT_QUOTES, 'UTF-8'); ?></span>
      <a class="logout-link" href="logout.php">Logout</a>
    </div>
  </header>

  <main>
    <section class="hero">
      <div>
        <p class="eyebrow">Main Workspace</p>
        <h1>Student Intervention Hub</h1>
        <p class="subtitle">This is the main working page for identifying at-risk students, reviewing core indicators, and prioritizing support actions.</p>
      </div>
      <div class="hero-card">
        <h2>Quick Snapshot</h2>
        <div id="students-summary" class="risk-summary">Loading student summary...</div>
      </div>
    </section>

    <section class="panel">
      <h2>Students</h2>
      <div class="student-controls">
        <input id="student-search" type="text" placeholder="Search by student name or grade..." />
        <select id="risk-filter">
          <option value="all">All Risk Levels</option>
          <option value="high">High Risk</option>
          <option value="medium">Medium Risk</option>
          <option value="low">Low Risk</option>
        </select>
      </div>
      <div id="student-cards" class="student-cards">Loading students...</div>
    </section>
  </main>

  <footer>
    <p>Learning Support Portal Student Hub | Prioritize learners by risk and intervention urgency.</p>
  </footer>

  <script src="assets/js/students.js"></script>
</body>
</html>

