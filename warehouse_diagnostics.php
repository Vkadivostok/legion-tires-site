<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: text/html; charset=utf-8');

function diag_row(string $name, bool $ok, string $details = ''): void
{
    $color = $ok ? '#0f7a45' : '#b42318';
    $status = $ok ? 'OK' : 'ERROR';
    echo '<tr>';
    echo '<td>' . htmlspecialchars($name) . '</td>';
    echo '<td style="color:' . $color . ';font-weight:700;">' . $status . '</td>';
    echo '<td><pre>' . htmlspecialchars($details) . '</pre></td>';
    echo '</tr>';
}

echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
echo '<title>Диагностика склада</title>';
echo '<style>body{font-family:Arial,sans-serif;background:#f6f8fb;color:#1f2937;padding:20px}table{border-collapse:collapse;width:100%;background:#fff}td,th{border:1px solid #d8dee8;padding:10px;vertical-align:top}pre{margin:0;white-space:pre-wrap}h1{font-size:22px}</style>';
echo '</head><body><h1>Диагностика склада</h1><table><thead><tr><th>Проверка</th><th>Статус</th><th>Детали</th></tr></thead><tbody>';

diag_row('PHP version', version_compare(PHP_VERSION, '7.4.0', '>='), PHP_VERSION);
diag_row('mysqli extension', extension_loaded('mysqli'), extension_loaded('mysqli') ? 'mysqli loaded' : 'mysqli not loaded');
diag_row('JSON extension', extension_loaded('json'), extension_loaded('json') ? 'json loaded' : 'json not loaded');

$files = [
    'db.php',
    'views.php',
    'index.php',
    'warehouse_db.php',
    'warehouse_api.php',
    'warehouse_embed.php',
    'warehouse_app.html',
    'Резина.xlsx',
    'Диски.xlsx',
];

foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    diag_row('Файл: ' . $file, is_file($path), is_file($path) ? ('size=' . filesize($path)) : 'not found: ' . $path);
}

$sessionStarted = false;
try {
    $sessionStarted = session_status() === PHP_SESSION_ACTIVE;
    diag_row('Session', $sessionStarted, 'session_id=' . session_id() . "\nlogged_in=" . var_export($_SESSION['logged_in'] ?? null, true));
} catch (Throwable $e) {
    diag_row('Session', false, $e->getMessage());
}

try {
    require_once __DIR__ . '/warehouse_db.php';
    diag_row('Include warehouse_db.php', true, 'loaded');
} catch (Throwable $e) {
    diag_row('Include warehouse_db.php', false, get_class($e) . ': ' . $e->getMessage());
}

if (function_exists('warehouse_ensure_database_and_tables')) {
    try {
        $conn = warehouse_ensure_database_and_tables();
        diag_row('Warehouse DB connect/table', true, 'connected; server=' . $conn->server_info);

        $state = warehouse_fetch_state($conn);
        diag_row('Warehouse state fetch', true, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    } catch (Throwable $e) {
        diag_row('Warehouse DB connect/table', false, get_class($e) . ': ' . $e->getMessage());
    }
}

$warehouseHtmlPath = __DIR__ . '/warehouse_app.html';
$warehouseHtml = is_file($warehouseHtmlPath) ? file_get_contents($warehouseHtmlPath) : false;
diag_row(
    'warehouse_app.html render source',
    $warehouseHtml !== false && $warehouseHtml !== '',
    $warehouseHtml === false
        ? 'not readable: ' . $warehouseHtmlPath
        : 'output length=' . strlen($warehouseHtml) . "\nfirst bytes=" . substr($warehouseHtml, 0, 120)
);

echo '</tbody></table></body></html>';
