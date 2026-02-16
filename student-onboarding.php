<?php
require_once __DIR__ . '/includes/auth.php';
$student = requireStudentLogin(false);
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
    <section class="auth-card onboarding-card">
      <p class="eyebrow">Student Onboarding</p>
      <h1>Tell us your background</h1>
      <p class="subtitle">These answers help personalize your AI guidance and learning path.</p>

      <section class="panel interview-panel">
        <h2>AI Voice Interview</h2>
        <p id="interview-prompt">Preparing your interview...</p>
        <div id="interview-window" class="chat-window">
          <div class="chat-message bot">I will ask your onboarding questions here and save everything automatically.</div>
        </div>
        <div class="voice-row">
          <span id="interview-voice-status" class="voice-status">Voice ready</span>
          <div class="voice-actions">
            <button id="interview-mic" class="ghost-btn" type="button" title="Click to speak your answer" aria-label="Talk"><span class="btn-text">Talk</span></button>
            <button id="interview-voice-toggle" class="ghost-btn" type="button" title="Toggle voice output" aria-label="Voice On"><span class="btn-text">Voice On</span></button>
          </div>
        </div>
        <form id="interview-form" class="chat-form" autocomplete="off">
          <input id="interview-answer" type="text" placeholder="Speak or type your answer..." maxlength="500" required />
          <button type="submit">Submit Answer</button>
        </form>
        <p id="interview-save-hint" class="auth-note" hidden>Interview complete. Redirecting to your student home...</p>
      </section>
    </section>
  </main>
  <script src="assets/js/onboarding.js"></script>
</body>
</html>
