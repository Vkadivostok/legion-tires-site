<?php
$coreBootstrap = __DIR__ . '/core/bootstrap.php';
if (is_file($coreBootstrap)) {
    require_once $coreBootstrap;
} elseif (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data) || !isset($data['action'])) {
    echo json_encode(['status' => 'error', 'message' => 'No action specified']);
    exit;
}

$section_map = [
    'new_order' => 'Новый',
    'in_progress' => 'В работе',
    'completed' => 'Готово',
    'archive' => 'Архив',
    'storage' => 'Хранение',
    'search' => 'Поиск',
    'history' => 'История',
    'settings' => 'Настройки',
    'reports' => 'Отчеты',
    'expenses' => 'Расходы',
    'zp' => 'З/П'
];

$action = (string)$data['action'];
foreach ($section_map as $key => $value) {
    if (strpos($action, $key) !== false) {
        $action = str_replace($key, $value, $action);
        break;
    }
}

echo json_encode(['status' => 'success']);
