<?php
require_once __DIR__ . '/includes/auth.php';

$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>EarEyes | Public Homepage</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <header class="topbar">
    <div class="brand">EarEyes</div>
    <nav>
      <a href="#overview">Overview</a>
      <a href="#features">Features</a>
      <a href="#access">Access</a>
    </nav>
    <div class="user-chip">
      <?php if ($user): ?>
        <strong><?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
        <span><?php echo htmlspecialchars(ucfirst($user['role']), ENT_QUOTES, 'UTF-8'); ?></span>
        <a class="logout-link" href="dashboard.php">Dashboard</a>
      <?php else: ?>
        <strong>Guest</strong>
        <span>Public Access</span>
        <a class="logout-link" href="login.php">Login</a>
      <?php endif; ?>
    </div>
  </header>

  <main>
    <section id="overview" class="hero">
      <div>
        <p class="eyebrow">Public Preview</p>
        <h1>AI-Driven Personalized Learning with Mental Health Intelligence</h1>
        <p class="subtitle">This homepage shows the platform concept. Full dashboards, alerts, and AI assistant are restricted to authenticated users.</p>
        <p><a class="cta-link" href="login.php">Login to access full system</a></p>
      </div>
      <div class="hero-card">
        <h2>What This Platform Does</h2>
        <ul>
          <li>Predicts dropout risk from attendance, grades, and behavior patterns</li>
          <li>Provides personalized learning pathways with offline support</li>
          <li>Coordinates community tutors and counselor interventions</li>
        </ul>
      </div>
    </section>

    <section id="features" class="panel">
      <h2>Feature Preview (Limited Access)</h2>
      <p>Click any restricted feature below. You must log in to open the full module.</p>
      <div class="grid three">
        <article class="hub-card locked-feature" data-feature="Early Warning Dashboard" role="button" tabindex="0">
          <h3>Early Warning Dashboard</h3>
          <p>Risk scoring, trends, and student intervention queue.</p>
          <span class="lock-tag">Locked</span>
        </article>
        <article class="hub-card locked-feature" data-feature="Actionable Alerts Center" role="button" tabindex="0">
          <h3>Actionable Alerts Center</h3>
          <p>Open alerts, recommendations, and case status tracking.</p>
          <span class="lock-tag">Locked</span>
        </article>
        <article class="hub-card locked-feature" data-feature="AI Support Chatbot" role="button" tabindex="0">
          <h3>AI Support Chatbot</h3>
          <p>Ask for intervention strategies and risk summaries.</p>
          <span class="lock-tag">Locked</span>
        </article>
      </div>
    </section>

    <section id="access" class="panel accent">
      <h2>Role-Based Access</h2>
      <div class="grid three">
        <article class="metric"><h3>Admin</h3><span>System-wide access and governance controls</span></article>
        <article class="metric"><h3>Educator</h3><span>Learning analytics, interventions, and tutoring workflows</span></article>
        <article class="metric"><h3>Counselor</h3><span>Mental wellness signals and counseling prioritization</span></article>
      </div>
      <p style="margin-top: 14px;"><a class="cta-link" href="login.php">Proceed to Login</a></p>
    </section>
  </main>

  <div id="login-toast" class="login-toast" aria-live="polite"></div>

  <footer>
    <p>EarEyes Ideathon Prototype | Public sample homepage with restricted feature access.</p>
  </footer>

  <script src="assets/js/public.js"></script>
</body>
</html>
