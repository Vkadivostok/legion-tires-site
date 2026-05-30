<?php

require_once __DIR__ . '/config.php';

function app_create_db_connection(): mysqli
{
    // On PHP 8+ mysqli can throw before connect_error is available.
    mysqli_report(MYSQLI_REPORT_OFF);
    $connection = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($connection->connect_error) {
        error_log(sprintf(
            "Ошибка подключения к БД host=%s db=%s user=%s http_host=%s: %s",
            DB_HOST,
            DB_NAME,
            DB_USER,
            (string)($_SERVER['HTTP_HOST'] ?? 'unknown'),
            $connection->connect_error
        ));

        if (!headers_sent()) {
            http_response_code(503);
            header('Content-Type: text/html; charset=UTF-8');
        }

        die("Ошибка подключения к базе данных. Проверьте настройки БД на сервере.");
    }

    $connection->set_charset('utf8mb4');
    $connection->query("SET time_zone = '+03:00'");
    return $connection;
}
