<?php

require_once __DIR__ . '/../helpers/Response.php';

function handleStudentRoute($method, $parts, $pdo) {
    // /api/student/dashboard
    // /api/student/reports
    
    $section = $parts[1] ?? '';

    if ($section === 'dashboard') {
        getStudentDashboard($pdo);
    } elseif ($section === 'reports') {
        if ($method === 'GET') {
            getStudentReports($pdo);
        } elseif ($method === 'POST') {
            createLostReport($pdo);
        }
    } else {
        Response::error('Student endpoint not found', 'NOT_FOUND', 404);
    }
}

function getStudentDashboard($pdo) {
    // Mocked user ID, in real app get from session
    $userId = 1; 
    try {
        $reportCount = $pdo->query("SELECT COUNT(*) FROM lost_reports WHERE student_id = $userId")->fetchColumn();
        $claimCount = $pdo->query("SELECT COUNT(*) FROM claims WHERE student_id = $userId")->fetchColumn();
        
        Response::success([
            'lost_reports' => $reportCount,
            'active_claims' => $claimCount
        ]);
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
}

function getStudentReports($pdo) {
    try {
        // Return all for demo, filter by student_id in production
        $stmt = $pdo->query("SELECT * FROM lost_reports ORDER BY created_at DESC");
        Response::success($stmt->fetchAll());
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
}

function createLostReport($pdo) {
    Response::success(['message' => 'Report submitted', 'ticket_id' => 'REF-' . rand(1000,9999)]);
}