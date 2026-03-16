<?php
/**
 * Require this at the top of student pages. Redirects to student login if not authenticated.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['student_id']) || empty($_SESSION['student_email'])) {
    header('Location: login.php');
    exit;
}
