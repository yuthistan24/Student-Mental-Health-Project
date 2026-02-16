<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

startAppSession();
if (currentUser() !== null) {
    header('Location: students.php');
    exit;
}
if (currentStudent() !== null) {
    header('Location: student-home.php');
    exit;
}

$error = '';
$role = trim($_GET['role'] ?? '');
$fullName = '';
$email = '';
$phoneNumber = '';
$addressLine = '';
$gradeLevel = '';
$communityZone = '';
$allowedRoles = ['admin', 'educator', 'counselor', 'student'];
if (!in_array($role, $allowedRoles, true)) {
    $role = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = trim($_POST['role_name'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phoneNumber = trim($_POST['phone_number'] ?? '');
    $addressLine = trim($_POST['address_line'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $gradeLevel = trim($_POST['grade_level'] ?? '');
    $communityZone = trim($_POST['community_zone'] ?? '');

    if (!in_array($role, $allowedRoles, true)) {
        $error = 'Please select a valid role.';
    } elseif ($fullName === '' || $email === '' || $phoneNumber === '' || $addressLine === '' || $password === '' || $confirmPassword === '') {
        $error = 'Full name, role, email, phone number, address, and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^[0-9+\\-\\s()]{7,20}$/', $phoneNumber)) {
        $error = 'Please enter a valid phone number.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif ($role === 'student' && $gradeLevel === '') {
        $error = 'Grade level is required for student registration.';
    } else {
        try {
            $conn = getDbConnection($dbConfig);

            if ($role === 'student') {
                $check = $conn->prepare('SELECT id FROM student_accounts WHERE email = ? LIMIT 1');
                if (!$check) {
                    throw new RuntimeException('Unable to validate student email.');
                }
                $check->bind_param('s', $email);
                $check->execute();
                $exists = $check->get_result();
                if ($exists && $exists->num_rows > 0) {
                    $error = 'A student account with this email already exists.';
                } else {
                    $studentCode = 'STU-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                    $conn->begin_transaction();

                    $studentStmt = $conn->prepare('INSERT INTO students (student_code, full_name, grade_level, phone_number, address_line, community_zone) VALUES (?, ?, ?, ?, ?, ?)');
                    if (!$studentStmt) {
                        throw new RuntimeException('Unable to create student profile.');
                    }
                    $studentStmt->bind_param('ssssss', $studentCode, $fullName, $gradeLevel, $phoneNumber, $addressLine, $communityZone);
                    $studentStmt->execute();
                    $studentId = (int) $conn->insert_id;

                    $accountStmt = $conn->prepare('INSERT INTO student_accounts (student_id, email, password_hash, is_active) VALUES (?, ?, ?, 1)');
                    if (!$accountStmt) {
                        throw new RuntimeException('Unable to create student account.');
                    }
                    $accountStmt->bind_param('iss', $studentId, $email, $hashedPassword);
                    $accountStmt->execute();

                    $conn->commit();

                    session_regenerate_id(true);
                    $_SESSION['student_user'] = [
                        'student_id' => $studentId,
                        'full_name' => $fullName,
                        'email' => $email,
                        'grade_level' => $gradeLevel,
                        'phone_number' => $phoneNumber,
                        'address_line' => $addressLine,
                    ];
                    unset($_SESSION['user']);

                    header('Location: student-onboarding.php');
                    exit;
                }
            } else {
                $check = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                if (!$check) {
                    throw new RuntimeException('Unable to validate staff email.');
                }
                $check->bind_param('s', $email);
                $check->execute();
                $exists = $check->get_result();
                if ($exists && $exists->num_rows > 0) {
                    $error = 'A staff account with this email already exists.';
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare('INSERT INTO users (full_name, email, phone_number, address_line, role_name, password_hash, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)');
                    if (!$stmt) {
                        throw new RuntimeException('Unable to create staff account.');
                    }
                    $stmt->bind_param('ssssss', $fullName, $email, $phoneNumber, $addressLine, $role, $hashedPassword);
                    $stmt->execute();

                    session_regenerate_id(true);
                    $_SESSION['user'] = [
                        'id' => (int) $conn->insert_id,
                        'full_name' => $fullName,
                        'email' => $email,
                        'role' => $role,
                        'phone_number' => $phoneNumber,
                        'address_line' => $addressLine,
                    ];
                    unset($_SESSION['student_user']);

                    header('Location: students.php');
                    exit;
                }
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
  <title>Register | Learning Support Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <main class="auth-wrapper">
    <section class="auth-card">
      <p class="eyebrow">Portal Registration</p>
      <h1>Create Account</h1>
      <p class="subtitle">Register as Admin, Educator, Counselor, or Student.</p>

      <?php if ($error !== ''): ?>
        <p class="error-text"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php endif; ?>

      <form method="post" class="auth-form">
        <label>Register As
          <select id="role_name" name="role_name" required>
            <option value="">Select role</option>
            <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
            <option value="educator" <?php echo $role === 'educator' ? 'selected' : ''; ?>>Educator</option>
            <option value="counselor" <?php echo $role === 'counselor' ? 'selected' : ''; ?>>Counselor</option>
            <option value="student" <?php echo $role === 'student' ? 'selected' : ''; ?>>Student</option>
          </select>
        </label>

        <label>Full Name
          <input type="text" name="full_name" required value="<?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?>" />
        </label>
        <label>Email
          <input type="email" name="email" required value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" />
        </label>
        <label>Phone Number
          <input type="text" name="phone_number" required value="<?php echo htmlspecialchars($phoneNumber, ENT_QUOTES, 'UTF-8'); ?>" placeholder="+91-9000000000" />
        </label>
        <label>Address
          <input type="text" name="address_line" required value="<?php echo htmlspecialchars($addressLine, ENT_QUOTES, 'UTF-8'); ?>" placeholder="House/Street/Area" />
        </label>

        <div id="student-extra-fields" style="display: <?php echo $role === 'student' ? 'block' : 'none'; ?>;">
          <label>Grade Level
            <input type="text" name="grade_level" value="<?php echo htmlspecialchars($gradeLevel, ENT_QUOTES, 'UTF-8'); ?>" placeholder="11" />
          </label>
          <label>Community Zone (optional)
            <input type="text" name="community_zone" value="<?php echo htmlspecialchars($communityZone, ENT_QUOTES, 'UTF-8'); ?>" />
          </label>
        </div>

        <label>Password
          <input type="password" name="password" required />
        </label>
        <label>Confirm Password
          <input type="password" name="confirm_password" required />
        </label>
        <button type="submit">Register</button>
      </form>

      <p class="auth-note">Already registered? <a href="login.php">Login</a></p>
    </section>
  </main>

  <script>
    (function () {
      const roleSelect = document.getElementById('role_name');
      const extraFields = document.getElementById('student-extra-fields');
      if (!roleSelect || !extraFields) return;

      function toggleFields() {
        extraFields.style.display = roleSelect.value === 'student' ? 'block' : 'none';
      }

      roleSelect.addEventListener('change', toggleFields);
      toggleFields();
    })();
  </script>
</body>
</html>
