<?php
/**
 * Root login - choose Admin or Student.
 * Admin: admin@ub.edu.ph → AdminDashboard.php
 * Student: students@ub.edu.ph → STUDENT/dashboard.php
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>UB Lost and Found - Login</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: system-ui, -apple-system, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(rgba(139,0,0,0.85), rgba(139,0,0,0.85)), url('ADMIN/UBBG.jpg') center/cover;
    }
    .container {
      background: rgba(255,255,255,0.96);
      padding: 48px;
      border-radius: 16px;
      text-align: center;
      max-width: 400px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    }
    h1 { color: #8b0000; font-size: 24px; margin-bottom: 8px; }
    p { color: #6b7280; font-size: 14px; margin-bottom: 32px; }
    .btn {
      display: block;
      width: 100%;
      padding: 14px 24px;
      margin-bottom: 12px;
      font-size: 16px;
      font-weight: 600;
      text-decoration: none;
      border-radius: 10px;
      transition: background 0.2s, color 0.2s;
    }
    .btn-admin {
      background: #8b0000;
      color: #fff;
      border: none;
    }
    .btn-admin:hover { background: #6e0000; color: #fff; }
    .btn-student {
      background: #fff;
      color: #8b0000;
      border: 2px solid #8b0000;
    }
    .btn-student:hover { background: #fef2f2; color: #8b0000; }
  </style>
</head>
<body>
  <div class="container">
    <h1>UB Lost and Found</h1>
    <p>Choose your login</p>
    <a href="ADMIN/login.php" class="btn btn-admin">Admin Login</a>
    <a href="STUDENT/login.php" class="btn btn-student">Student Login</a>
  </div>
</body>
</html>
