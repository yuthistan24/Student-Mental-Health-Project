<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (currentStudent() !== null) {
    header('Location: student-home.php');
    exit;
}

$error = '';
$email = '';
$fullName = '';
$gradeLevel = '';
$communityZone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $gradeLevel = trim($_POST['grade_level'] ?? '');
    $communityZone = trim($_POST['community_zone'] ?? '');

    if ($fullName === '' || $email === '' || $password === '' || $confirmPassword === '' || $gradeLevel === '') {
        $error = 'Full name, email, grade level, and passwords are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $conn = getDbConnection($dbConfig);

            $check = $conn->prepare('SELECT id FROM student_accounts WHERE email = ? LIMIT 1');
            if (!$check) {
                throw new RuntimeException('Unable to validate email uniqueness.');
            }
            $check->bind_param('s', $email);
            $check->execute();
            $exists = $check->get_result();
            if ($exists && $exists->num_rows > 0) {
                $error = 'An account with this email already exists.';
            } else {
                $studentCode = 'STU-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $conn->begin_transaction();

                $studentStmt = $conn->prepare('INSERT INTO students (student_code, full_name, grade_level, community_zone) VALUES (?, ?, ?, ?)');
                if (!$studentStmt) {
                    throw new RuntimeException('Unable to create student profile.');
                }
                $studentStmt->bind_param('ssss', $studentCode, $fullName, $gradeLevel, $communityZone);
                $studentStmt->execute();
                $studentId = (int) $conn->insert_id;

                $accountStmt = $conn->prepare('INSERT INTO student_accounts (student_id, email, password_hash, is_active) VALUES (?, ?, ?, 1)');
                if (!$accountStmt) {
                    throw new RuntimeException('Unable to create student account.');
                }
                $accountStmt->bind_param('iss', $studentId, $email, $hashedPassword);
                $accountStmt->execute();

                $conn->commit();

                $_SESSION['student_user'] = [
                    'student_id' => $studentId,
                    'full_name' => $fullName,
                    'email' => $email,
                    'grade_level' => $gradeLevel,
                ];

                header('Location: student-home.php');
                exit;
            }
        } catch (Throwable $e) {
            if (isset($conn) && $conn instanceof mysqli) {
                $conn->rollback();
            }
            $error = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Register | Learning Support Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <main class="auth-wrapper">
    <section class="auth-card">
      <p class="eyebrow">Student Account</p>
      <h1>Create Student Account</h1>
      <p class="subtitle">Register to access your personalized learning and progress overview.</p>

      <?php if ($error !== ''): ?>
        <p class="error-text"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php endif; ?>

      <form method="post" class="auth-form">
        <label>Full Name
          <input type="text" name="full_name" required value="<?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?>" />
        </label>
        <label>Email
          <input type="email" name="email" required value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" />
        </label>
        <label>Grade Level
          <input type="text" name="grade_level" required placeholder="8" value="<?php echo htmlspecialchars($gradeLevel, ENT_QUOTES, 'UTF-8'); ?>" />
        </label>
        <label>Community Zone (optional)
          <input type="text" name="community_zone" value="<?php echo htmlspecialchars($communityZone, ENT_QUOTES, 'UTF-8'); ?>" />
        </label>
        <label>Password
          <input type="password" name="password" required />
        </label>
        <label>Confirm Password
          <input type="password" name="confirm_password" required />
        </label>
        <button type="submit">Register</button>
      </form>

      <p class="auth-note">Already have an account? <a href="login.php">Login (choose Student role)</a></p>
      <p class="auth-note">Use the same login page for all roles: <a href="login.php">Login</a></p>
    </section>
  </main>
</body>
</html>

