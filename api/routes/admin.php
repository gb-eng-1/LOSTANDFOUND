<?php

require_once __DIR__ . '/../helpers/Response.php';

function handleAdminRoute($method, $parts, $pdo) {
    // /api/admin/dashboard/stats
    // /api/admin/items
    // /api/admin/matches

    $section = $parts[1] ?? '';

    // Check Auth (Simplified)
    // if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { Response::error('Unauthorized', 401); }

    if ($section === 'dashboard') {
        if (isset($parts[2]) && $parts[2] === 'stats') {
            getAdminStats($pdo);
        } else {
            Response::error('Dashboard endpoint not found', '404');
        }
    } elseif ($section === 'items') {
        if ($method === 'GET') {
            getFoundItems($pdo);
        } elseif ($method === 'POST') {
            createFoundItem($pdo);
        }
    } else {
        Response::error('Admin endpoint not found', 'NOT_FOUND', 404);
    }
}

function getAdminStats($pdo) {
    try {
        // Example stats aggregation
        $found = $pdo->query("SELECT COUNT(*) FROM found_items WHERE status='Found'")->fetchColumn();
        $lost = $pdo->query("SELECT COUNT(*) FROM lost_reports WHERE status='Lost'")->fetchColumn();
        $resolved = $pdo->query("SELECT COUNT(*) FROM claims WHERE status='Resolved'")->fetchColumn();
        
        Response::success(['found' => $found, 'lost' => $lost, 'resolved' => $resolved]);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 'DB_ERROR', 500);
    }
}

function getFoundItems($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM found_items ORDER BY created_at DESC LIMIT 50");
        Response::success($stmt->fetchAll());
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
}

function createFoundItem($pdo) {
    $data = json_decode(file_get_contents("php://input"), true);
    // Implementation for inserting item
    // $stmt = $pdo->prepare("INSERT INTO found_items ...");
    Response::success(['message' => 'Item created (simulation)', 'id' => 'UB' . rand(1000,9999)]);
}