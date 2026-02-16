<?php
require_once __DIR__ . '/includes/auth.php';

$user = requireLogin(false);
$role = $user['role'];
$canViewLearning = in_array($role, ['admin', 'educator'], true);
$canViewAlerts = in_array($role, ['admin', 'educator', 'counselor'], true);
$isAdmin = $role === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Learning Support Portal | AI Learning + Mental Health Intelligence</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css" />
  <link rel="manifest" href="manifest.json" />
</head>
<body data-role="<?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>">
  <header class="topbar">
    <div class="brand"><a href="index.php" style="text-decoration:none;color:inherit;">Learning Support Portal</a></div>
    <nav>
      <a href="students.php">Students</a>
      <a href="#early-warning">Early Warning</a>
      <?php if ($canViewLearning): ?><a href="#learning">Personalized Learning</a><?php endif; ?>
      <a href="#hubs">Community Hubs</a>
      <?php if ($canViewAlerts): ?><a href="#alerts">Alerts</a><?php endif; ?>
      <a href="#chatbot">AI Chatbot</a>
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
        <p class="eyebrow">AI-Driven Personalized Learning + Mental Health Intelligence</p>
        <h1>Predict dropout risk early. Close learning gaps fast. Scale support equitably.</h1>
        <p class="subtitle">An integrated system that combines attendance, grades, and behavior signals to detect students at risk, then activates personalized learning paths and community tutoring resources.</p>
      </div>
      <div class="hero-card">
        <h2>Live Risk Monitor</h2>
        <div id="risk-summary" class="risk-summary">Loading...</div>
      </div>
    </section>

    <section id="early-warning" class="panel">
      <h2>Early Warning Intelligence</h2>
      <p>Risk scoring engine analyzes attendance trends, academic decline, and behavior incidents to trigger actionable interventions.</p>
      <div class="grid three">
        <article class="metric"><h3 id="metric-students">0</h3><span>Total Students</span></article>
        <article class="metric"><h3 id="metric-high-risk">0</h3><span>High Risk</span></article>
        <article class="metric"><h3 id="metric-active-alerts">0</h3><span>Active Alerts</span></article>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Student</th>
              <th>Attendance %</th>
              <th>Average Score</th>
              <th>Behavior Incidents</th>
              <th>Mental Wellness Signal</th>
              <th>Dropout Risk</th>
            </tr>
          </thead>
          <tbody id="students-table"></tbody>
        </table>
      </div>
    </section>

    <?php if ($canViewLearning): ?>
    <section id="learning" class="panel accent">
      <h2>Personalized Mobile Learning (Offline Ready)</h2>
      <p>Students receive adaptive content and quizzes aligned with their gaps. The app caches lessons and syncs progress when internet becomes available.</p>
      <div class="grid two">
        <article>
          <h3>Adaptive Content Paths</h3>
          <ul>
            <li>Diagnostic based lesson sequencing</li>
            <li>Micro-content with mastery checks</li>
            <li>AI tutor hints tailored by misconception type</li>
          </ul>
        </article>
        <article>
          <h3>Offline Learning Features</h3>
          <ul>
            <li>Installable progressive web app</li>
            <li>Local content cache + background sync</li>
            <li>Low-bandwidth mode for underserved areas</li>
          </ul>
        </article>
      </div>
    </section>
    <?php endif; ?>

    <section id="hubs" class="panel">
      <h2>Community Learning Hub Platform</h2>
      <p>Virtual tutoring, digital libraries, and mentor scheduling for learners without reliable in-home support.</p>
      <div class="grid three">
        <article class="hub-card">
          <h3>Virtual Tutoring</h3>
          <p>Book sessions by subject and risk profile priority.</p>
        </article>
        <article class="hub-card">
          <h3>Digital Resource Bank</h3>
          <p>Curated multilingual resources by grade and competency.</p>
        </article>
        <article class="hub-card">
          <h3>Case Coordination</h3>
          <p>Educators, counselors, and administrators align interventions.</p>
        </article>
      </div>
    </section>

    <?php if ($canViewAlerts): ?>
    <section id="alerts" class="panel">
      <h2>Actionable Alerts</h2>
      <p>Alerts include suggested intervention plans and responsible staff assignments.</p>
      <div id="alerts-list" class="alerts-list">Loading alerts...</div>
    </section>
    <?php endif; ?>

    <section id="chatbot" class="panel">
      <h2>AI Assistant for Educators and Counselors</h2>
      <p>Ask for intervention suggestions, risk patterns, counseling priorities, and hub support recommendations.</p>
      <div class="chat-shell">
        <div id="chat-window" class="chat-window">
          <div class="chat-message bot">I am your platform assistant. Ask: "Who is highest risk?" or "Give attendance intervention ideas."</div>
        </div>
        <div class="voice-row">
          <span id="chat-voice-status" class="voice-status">Voice ready</span>
          <div class="voice-actions">
            <button id="chat-mic" class="ghost-btn" type="button">Talk</button>
            <button id="chat-voice-toggle" class="ghost-btn" type="button">Voice On</button>
          </div>
        </div>
        <form id="chat-form" class="chat-form">
          <input id="chat-input" type="text" placeholder="Type a question..." maxlength="500" required />
          <button type="submit">Send</button>
        </form>
      </div>
    </section>

    <?php if ($isAdmin): ?>
    <section class="panel accent">
      <h2>Admin Controls</h2>
      <p>Admins can manage users, role assignments, and institution-wide policy defaults in the next iteration.</p>
    </section>
    <?php endif; ?>
  </main>

  <footer>
    <p>Learning Support Portal | Built with PHP, MySQL, and offline-capable web components.</p>
  </footer>

  <script src="assets/js/app.js"></script>
</body>
</html>
