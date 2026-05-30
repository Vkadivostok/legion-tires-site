<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function warehouse_embed_send_error(int $statusCode, string $message): void
{
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: text/plain; charset=utf-8');
    }

    echo $message;
    exit;
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    warehouse_embed_send_error(401, 'Unauthorized');
}

$source = __DIR__ . '/warehouse_app.html';
if (!is_file($source)) {
    warehouse_embed_send_error(500, 'Не найден интерфейс склада.');
}

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}

$html = file_get_contents($source);
if ($html === false) {
    warehouse_embed_send_error(500, 'Не удалось прочитать интерфейс склада.');
}
echo $html;
