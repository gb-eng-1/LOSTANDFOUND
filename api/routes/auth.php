<?php

require_once __DIR__ . '/../helpers/Response.php';

function handleAuthRoute($method, $parts, $pdo) {
    // Routes:
    // POST /api/auth/admin/login
    // POST /api/auth/student/login
    // POST /api/auth/logout

    if ($method === 'POST' && isset($parts[2]) && $parts[2] === 'login') {
        $type = $parts[1]; // 'admin' or 'student'
        if ($type !== 'admin' && $type !== 'student') {
            Response::error('Invalid auth type', 'INVALID_TYPE', 400);
        }
        loginUser($pdo, $type);
    } elseif ($method === 'POST' && isset($parts[1]) && $parts[1] === 'logout') {
        // Destroy session if using PHP sessions
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_destroy();
        Response::success(['message' => 'Logged out successfully']);
    } else {
        Response::error('Auth endpoint not found', 'NOT_FOUND', 404);
    }
}

function loginUser($pdo, $type) {
    $data = json_decode(file_get_contents("php://input"), true);
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    if (empty($email) || empty($password)) {
        Response::error('Email and password required', 'VALIDATION_ERROR', 400);
    }

    $table = ($type === 'admin') ? 'admins' : 'students';
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM $table WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Note: For production, ensure passwords are hashed with password_hash() and verified with password_verify()
        // Assuming simple comparison or password_verify logic here:
        if ($user && ($user['password'] === $password || password_verify($password, $user['password']))) {
            unset($user['password']); // Don't send password back
            
            // Start session or generate token
            if (session_status() === PHP_SESSION_NONE) session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $type;

            Response::success(['user' => $user, 'role' => $type, 'token' => session_id()]);
        } else {
            Response::error('Invalid credentials', 'AUTH_FAILED', 401);
        }
    } catch (Exception $e) {
        Response::error('Login failed: ' . $e->getMessage(), 'SERVER_ERROR', 500);
    }
}