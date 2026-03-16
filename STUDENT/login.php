<?php
/**
 * Student login - students@ub.edu.ph / Students lands on student dashboard.
 */
session_start();
if (!empty($_SESSION['student_id'])) {
    header('Location: StudentDashboard.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($email) || $password === '') {
        $error = 'Please enter email and password.';
    } else {
        try {
            require_once dirname(__DIR__) . '/config/database.php';
        } catch (Throwable $e) {
            $error = 'Invalid Credentials.';
            $email = htmlspecialchars($email);
        }

        if ($error === '') {
            $stmt = $pdo->prepare('SELECT id, email, password_hash, name FROM students WHERE LOWER(email) = LOWER(?) LIMIT 1');
            $stmt->execute([$email]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                // Accept both student@ub.edu.ph and students@ub.edu.ph for testing
                if ((strtolower($email) === 'students@ub.edu.ph' || strtolower($email) === 'student@ub.edu.ph') && 
                    ($password === 'Students' || $password === 'student123')) {
                    $hash = password_hash('student123', PASSWORD_DEFAULT);
                    $ins = $pdo->prepare('INSERT INTO students (email, password_hash, name, student_id) VALUES (?, ?, ?, ?)');
                    $ins->execute(['student@ub.edu.ph', $hash, 'Student User', 'STU-001']);
                    $student = ['id' => (int) $pdo->lastInsertId(), 'email' => 'student@ub.edu.ph', 'name' => 'Student User'];
                } else {
                    $error = 'Invalid Credentials.';
                }
            } elseif (!password_verify($password, $student['password_hash'])) {
                // Accept both old and new passwords for testing
                if ((strtolower($email) === 'students@ub.edu.ph' || strtolower($email) === 'student@ub.edu.ph') && 
                    ($password === 'Students' || $password === 'student123')) {
                    $hash = password_hash('student123', PASSWORD_DEFAULT);
                    $upd = $pdo->prepare('UPDATE students SET password_hash = ?, name = COALESCE(name, ?), student_id = COALESCE(student_id, ?) WHERE id = ?');
                    $upd->execute([$hash, 'Student User', 'STU-001', $student['id']]);
                    $student['password_hash'] = $hash;
                } else {
                    $error = 'Invalid Credentials.';
                }
            }

            if ($error === '' && $student) {
                $_SESSION['student_id'] = (int) $student['id'];
                $_SESSION['student_email'] = $student['email'];
                $_SESSION['student_name'] = $student['name'] ?? '';
                header('Location: StudentDashboard.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Log In</title>
  <link rel="stylesheet" href="../ADMIN/login.css?v=2">
  <style>
    /* UBBG.jpg with maroon overlay */
    body {
      background-color: #5a0000;
      background-image:
        linear-gradient(
          rgba(139, 0, 0, 0.70),
          rgba(139, 0, 0, 0.70)
        ),
        url('../ADMIN/UBBG.jpg');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      background-attachment: fixed;
    }
    .login-error-popup {
      position: fixed;
      top: 24px;
      left: 50%;
      transform: translateX(-50%);
      padding: 12px 24px;
      background: #b91c1c;
      color: #fff;
      font-size: 14px;
      font-weight: 500;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.25);
      z-index: 9999;
      transition: opacity 0.4s ease, visibility 0.4s ease;
    }
    .login-error-popup.hide {
      opacity: 0;
      visibility: hidden;
    }
    @media (max-width: 480px) {
      .login-error-popup {
        left: 16px;
        right: 16px;
        transform: none;
        max-width: calc(100% - 32px);
        text-align: center;
        padding: 14px 16px;
      }
    }
  </style>
</head>
<body>
  <?php if ($error): ?>
  <p id="loginErrorPopup" class="login-error-popup" role="alert"><?php echo htmlspecialchars($error); ?></p>
  <?php endif; ?>
  <div class="login-wrapper">
    <div class="login-box">
      <h1 class="login-title">Welcome to UB Lost and Found!</h1>

      <form class="login-form" method="post" action="login.php">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="" value="<?php echo htmlspecialchars($email); ?>" required autocomplete="email">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="" required autocomplete="current-password">

        <div class="login-options">
          <label class="remember-me">
            <input type="checkbox" name="remember" value="1">
            <span>Remember me</span>
          </label>
          <a class="forgot-link" href="#">Forgot Password?</a>
        </div>

        <button type="submit" class="login-btn">Login</button>
      </form>

      <button class="ubmail-btn" type="button">
        <span class="ubmail-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18">
            <path fill="currentColor" d="M20 4H4C2.9 4 2 4.9 2 6v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4.2-8 5-8-5V6l8 5 8-5v2.2z"/>
          </svg>
        </span>
        <span>Login using UB Mail</span>
      </button>

      <div style="text-align:center;margin-top:20px;font-size:13px;color:#6b7280;">
        <a href="../login.php" style="color:#8b0000;text-decoration:underline;margin-right:15px;">← Back to Main</a>
        <a href="../ADMIN/login.php" style="color:#8b0000;text-decoration:underline;">Admin Login</a>
      </div>
    </div>
  </div>
  <?php if ($error): ?>
  <script>
    (function () {
      var el = document.getElementById('loginErrorPopup');
      if (el) setTimeout(function () { el.classList.add('hide'); }, 2500);
    })();
  </script>
  <?php endif; ?>
</body>
</html>
