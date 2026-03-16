<?php
session_start();
if (!empty($_SESSION['student_id'])) {
    header('Location: StudentDashboard.php');
} else {
    header('Location: login.php');
}
exit;
