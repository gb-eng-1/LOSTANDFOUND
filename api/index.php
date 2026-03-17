<?php
// Main API Entry Point

// Handle CORS
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    exit(0);
}

require_once __DIR__ . '/helpers/Database.php';
require_once __DIR__ . '/helpers/Response.php';

// Load Route Handlers
require_once __DIR__ . '/routes/support.php';
require_once __DIR__ . '/routes/auth.php';
require_once __DIR__ . '/routes/admin.php';
require_once __DIR__ . '/routes/student.php';
require_once __DIR__ . '/routes/notifications.php';

// Connect to Database
$database = new Database();
try {
    $pdo = $database->getConnection();
} catch (Exception $e) {
    Response::error("Service Unavailable", "DB_CONN_ERROR", 503);
}

// Parse URI: /LOSTANDFOUND/api/resource/action -> ['resource', 'action']
$request_uri = $_SERVER['REQUEST_URI'];
$script_path = dirname($_SERVER['SCRIPT_NAME']); // /LOSTANDFOUND/api
$path = str_replace($script_path, '', $request_uri);
$path = trim(strtok($path, '?'), '/'); // Remove query string and trim slashes
$parts = explode('/', $path);

$resource = $parts[0] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Dispatch
switch ($resource) {
    case 'support': handleSupportRoute($method, $parts, $pdo); break;
    case 'auth':    handleAuthRoute($method, $parts, $pdo); break;
    case 'admin':   handleAdminRoute($method, $parts, $pdo); break;
    case 'student':       handleStudentRoute($method, $parts, $pdo); break;
    case 'notifications': handleNotificationRoute($method, $parts, $pdo); break;
    default:              Response::error('API Endpoint not found: ' . $resource, 'NOT_FOUND', 404);
}