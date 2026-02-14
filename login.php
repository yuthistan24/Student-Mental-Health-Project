<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

startAppSession();
if (currentUser() !== null) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$selectedRole = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedRole = trim($_POST['role_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $allowedRoles = ['admin', 'educator', 'counselor'];

    if (!in_array($selectedRole, $allowedRoles, true)) {
        $error = 'Please select a role.';
    } elseif ($email === '' || $password === '') {
        $error = 'Role, email, and password are required.';
    } else {
        try {
            $conn = getDbConnection($dbConfig);
            $stmt = $conn->prepare('SELECT id, full_name, email, role_name, password_hash, is_active FROM users WHERE email = ? LIMIT 1');
            if (!$stmt) {
                throw new RuntimeException('Failed to prepare user lookup.');
            }

            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result ? $result->fetch_assoc() : null;

            if (
                !$user ||
                (int) $user['is_active'] !== 1 ||
                !password_verify($password, $user['password_hash']) ||
                $user['role_name'] !== $selectedRole
            ) {
                $error = 'Invalid credentials.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => (int) $user['id'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'role' => $user['role_name'],
                ];

                header('Location: dashboard.php');
                exit;
            }
        } catch (Throwable $e) {
            $error = 'Login failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login | EarEyes</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <main class="auth-wrapper">
    <section class="auth-card">
      <p class="eyebrow">EarEyes Access</p>
      <h1>Sign in to Dashboard</h1>
      <p class="subtitle">Role-based access for administrators, educators, and counselors.</p>

      <?php if ($error !== ''): ?>
        <p class="error-text"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php endif; ?>

      <form method="post" class="auth-form">
        <label>Login As
          <select name="role_name" required>
            <option value="">Select role</option>
            <option value="admin" <?php echo $selectedRole === 'admin' ? 'selected' : ''; ?>>Admin</option>
            <option value="educator" <?php echo $selectedRole === 'educator' ? 'selected' : ''; ?>>Educator</option>
            <option value="counselor" <?php echo $selectedRole === 'counselor' ? 'selected' : ''; ?>>Counselor</option>
          </select>
        </label>
        <label>Email
          <input type="email" name="email" required placeholder="admin@eareyes.local" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" />
        </label>
        <label>Password
          <input type="password" name="password" required placeholder="Enter password" />
        </label>
        <button type="submit">Login</button>
      </form>

      <p class="auth-note">Default users: admin, educator, counselor (see README for credentials).</p>
    </section>
  </main>
</body>
</html>

