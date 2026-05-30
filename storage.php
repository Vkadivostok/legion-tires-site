<?php
ob_start();
require_once 'db.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if (isset($_GET['logout'])) {
    log_change("Выход из системы");
    session_destroy();
    header("Location: index.php");
    exit;
}
track_user_activity('storage');

$section = 'storage';
$search_query = $_GET['query'] ?? '';
$status_filter = $_GET['status'] ?? '';
$location_filter = $_GET['location'] ?? '';
$storage_error = '';
$storage_success = '';

if (isset($_GET['action']) && $_GET['action'] === 'export_storage') {
    exportStorageOrders($search_query, $status_filter, $location_filter);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['import_storage'])) {
        $import_result = importStorageOrdersFromFile($_FILES['storage_import_file'] ?? []);
        if ($import_result['success']) {
            header("Location: storage.php?imported=" . (int)$import_result['count']);
            exit;
        }
        $storage_error = $import_result['message'];
    } elseif (isset($_POST['create_storage'])) {
        if (createStorageOrder($_POST, $_FILES)) {
            header("Location: ?section=storage");
            exit;
        } else {
            $storage_error = "Ошибка при создании заказа. Пожалуйста, проверьте данные и попробуйте снова.";
        }
    } elseif (isset($_POST['update_storage'])) {
        if (updateStorageOrder($_POST, $_FILES)) {
            header("Location: ?section=storage");
            exit;
        } else {
            $storage_error = "Ошибка при обновлении заказа. Пожалуйста, проверьте данные и попробуйте снова.";
        }
    } elseif (isset($_POST['delete_storage'])) {
        if (deleteStorageOrder($_POST['order_id'])) {
            header("Location: ?section=storage");
            exit;
        } else {
            $storage_error = "Ошибка при удалении заказа. Пожалуйста, попробуйте снова.";
        }
    }
}

if (isset($_GET['imported'])) {
    $storage_success = 'Добавлено в базу записей хранения: ' . (int)$_GET['imported'];
}

// Функция для создания заказа хранения
function createStorageOrder($data, $files)
{
    global $conn;

    $client_name = htmlspecialchars(trim($data['client_name'] ?? ''));
    $phone = htmlspecialchars(trim($data['phone'] ?? ''));
    $notes = htmlspecialchars(trim($data['notes'] ?? ''));
    $status = in_array($data['status'] ?? 'На хранении', ['На хранении', 'Выдано']) ? $data['status'] : 'На хранении';
    $storage_start_date = $data['storage_start_date'] ?? null;
    $storage_end_date = $data['storage_end_date'] ?? null;
    $storage_location = htmlspecialchars(trim($data['storage_location'] ?? ''));

    if (empty($client_name)) {
        return false;
    }

    $photos = [];
    if (!empty($files['photos']['name'][0])) {
        $upload_dir = 'Uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB

        for ($i = 0; $i < min(4, count($files['photos']['name'])); $i++) {
            if ($files['photos']['size'][$i] > 0 && $files['photos']['size'][$i] <= $max_size) {
                $file_type = mime_content_type($files['photos']['tmp_name'][$i]);
                if (in_array($file_type, $allowed_types)) {
                    $file_name = uniqid() . '_' . basename($files['photos']['name'][$i]);
                    $temp_file = $files['photos']['tmp_name'][$i];
                    $target_file = $upload_dir . $file_name;

                    if (compressImage($temp_file, $target_file)) {
                        $photos[] = $target_file;
                    }
                }
            }
        }
    }

    $photos_str = implode(',', $photos);

    $stmt = $conn->prepare("INSERT INTO storage_orders (client_name, phone, notes, status, storage_start_date, storage_end_date, storage_location, photos, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    if ($stmt === false) {
        error_log("Ошибка подготовки запроса: " . $conn->error);
        return false;
    }

    $stmt->bind_param("ssssssss", $client_name, $phone, $notes, $status, $storage_start_date, $storage_end_date, $storage_location, $photos_str);
    $result = $stmt->execute();
    $order_id = $conn->insert_id;
    $stmt->close();

    if ($result && $order_id) {
        $inventory_number = sprintf("ST-%04d", $order_id);
        $stmt = $conn->prepare("UPDATE storage_orders SET inventory_number = ? WHERE id = ?");
        $stmt->bind_param("si", $inventory_number, $order_id);
        $stmt->execute();
        $stmt->close();

        $message = "<b>Новый заказ хранения #$inventory_number</b>\n" .
                   "Клиент: $client_name\n" .
                   "Телефон: " . ($phone ?: 'не указан') . "\n" .
                   "Статус: $status\n" .
                   "Место: " . ($storage_location ?: 'не указано') . "\n" .
                   "Примечание: " . ($notes ?: 'нет') . "\n" .
                   "Создан: " . date('d.m.Y H:i');
        sendTelegramNotification($message, $photos);

        $user_id = $_SESSION['user_id'];
        $details = "Создан заказ: $client_name, $inventory_number";
        logStorageHistory($order_id, $user_id, 'Создание', $details);
    } else {
        error_log("Ошибка выполнения запроса: " . $stmt->error);
    }

    return $result;
}

// Функция для обновления заказа хранения
function updateStorageOrder($data, $files)
{
    global $conn;

    $order_id = intval($data['order_id'] ?? 0);
    $client_name = htmlspecialchars(trim($data['client_name'] ?? ''));
    $phone = htmlspecialchars(trim($data['phone'] ?? '')); // Исправлено: убрано FILTER_SANITIZE_STRING
    $notes = htmlspecialchars(trim($data['notes'] ?? ''));
    $status = in_array($data['status'] ?? 'На хранении', ['На хранении', 'Выдано']) ? $data['status'] : 'На хранении';
    $storage_start_date = $data['storage_start_date'] ?? null;
    $storage_end_date = $data['storage_end_date'] ?? null;
    $storage_location = htmlspecialchars(trim($data['storage_location'] ?? ''));
    $existing_photos = $data['existing_photos'] ?? [];

    if (empty($client_name) || $order_id <= 0) {
        return false;
    }

    $stmt = $conn->prepare("SELECT client_name, phone, notes, status, storage_start_date, storage_end_date, storage_location, inventory_number, photos FROM storage_orders WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_order = $result->fetch_assoc();
    $stmt->close();

    if (!$current_order) {
        return false;
    }

    $current_photos = $current_order['photos'] ? explode(',', $current_order['photos']) : [];
    $photos_to_keep = [];
    $photos_to_delete = [];
    foreach ($current_photos as $photo) {
        if ($photo && in_array($photo, $existing_photos) && file_exists($photo)) {
            $photos_to_keep[] = $photo;
        } elseif (file_exists($photo)) {
            $photos_to_delete[] = $photo;
        }
    }

    $new_photos = [];
    if (!empty($files['new_photos']['name'][0])) {
        $upload_dir = 'Uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB

        $available_slots = max(0, 4 - count($photos_to_keep));
        for ($i = 0; $i < min($available_slots, count($files['new_photos']['name'])); $i++) {
            if ($files['new_photos']['size'][$i] > 0 && $files['new_photos']['size'][$i] <= $max_size) {
                $file_type = mime_content_type($files['new_photos']['tmp_name'][$i]);
                if (in_array($file_type, $allowed_types)) {
                    $file_name = uniqid() . '_' . basename($files['new_photos']['name'][$i]);
                    $temp_file = $files['new_photos']['tmp_name'][$i];
                    $target_file = $upload_dir . $file_name;

                    if (compressImage($temp_file, $target_file)) {
                        $new_photos[] = $target_file;
                    }
                }
            }
        }
    }

    $all_photos = array_merge($photos_to_keep, $new_photos);
    $photos_str = implode(',', $all_photos);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE storage_orders SET client_name = ?, phone = ?, notes = ?, status = ?, storage_start_date = ?, storage_end_date = ?, storage_location = ?, photos = ? WHERE id = ?");
        if ($stmt === false) {
            throw new Exception("Ошибка подготовки запроса: " . $conn->error);
        }

        $stmt->bind_param("ssssssssi", $client_name, $phone, $notes, $status, $storage_start_date, $storage_end_date, $storage_location, $photos_str, $order_id);
        $result = $stmt->execute();
        $stmt->close();
        if (!$result) {
            throw new Exception("Ошибка обновления заказа хранения");
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        foreach ($new_photos as $photo) {
            if (file_exists($photo)) {
                unlink($photo);
            }
        }
        error_log("Ошибка обновления хранения #$order_id: " . $e->getMessage());
        return false;
    }

    foreach ($photos_to_delete as $photo) {
        if (file_exists($photo)) {
            unlink($photo);
        }
    }

    if ($result) {
        $message = "<b>Заказ хранения #{$current_order['inventory_number']} обновлён</b>\n" .
                   "Клиент: $client_name\n" .
                   "Телефон: " . ($phone ?: 'не указан') . "\n" .
                   "Статус: $status\n" .
                   "Место: " . ($storage_location ?: 'не указано') . "\n" .
                   "Примечание: " . ($notes ?: 'нет') . "\n" .
                   "Обновлён: " . date('d.m.Y H:i');
        sendTelegramNotification($message, $new_photos);

        $user_id = $_SESSION['user_id'];
        $changes = [];
        if ($current_order['client_name'] !== $client_name) {
            $changes[] = "Имя: {$current_order['client_name']} → $client_name";
        }
        if ($current_order['phone'] !== $phone) {
            $changes[] = "Телефон: " . ($current_order['phone'] ?: 'не указан') . " → " . ($phone ?: 'не указан');
        }
        if ($current_order['notes'] !== $notes) {
            $changes[] = "Примечание: " . ($current_order['notes'] ?: 'не указано') . " → " . ($notes ?: 'не указано');
        }
        if ($current_order['status'] !== $status) {
            $changes[] = "Статус: {$current_order['status']} → $status";
        }
        if ($current_order['storage_start_date'] !== $storage_start_date) {
            $changes[] = "Дата начала: " . ($current_order['storage_start_date'] ?: 'не указана') . " → " . ($storage_start_date ?: 'не указана');
        }
        if ($current_order['storage_end_date'] !== $storage_end_date) {
            $changes[] = "Дата окончания: " . ($current_order['storage_end_date'] ?: 'не указана') . " → " . ($storage_end_date ?: 'не указана');
        }
        if ($current_order['storage_location'] !== $storage_location) {
            $changes[] = "Место: " . ($current_order['storage_location'] ?: 'не указано') . " → " . ($storage_location ?: 'не указано');
        }
        if (!empty($new_photos)) {
            $changes[] = "Добавлено " . count($new_photos) . " новых фото";
        }
        if (count($current_photos) > count($photos_to_keep)) {
            $changes[] = "Удалено " . (count($current_photos) - count($photos_to_keep)) . " фото";
        }

        if (!empty($changes)) {
            $details = implode("; ", $changes);
            logStorageHistory($order_id, $user_id, 'Обновление', $details);
        }
    }

    return $result;
}

// Функция для удаления заказа хранения
function deleteStorageOrder($order_id)
{
    global $conn;

    $order_id = intval($order_id);
    if ($order_id <= 0) {
        return false;
    }

    $stmt = $conn->prepare("SELECT client_name, inventory_number, photos FROM storage_orders WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();

    if (!$order) {
        return false;
    }

    $photos = $order['photos'] ? explode(',', $order['photos']) : [];
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("DELETE FROM storage_orders WHERE id = ?");
        if ($stmt === false) {
            throw new Exception("Ошибка подготовки удаления: " . $conn->error);
        }
        $stmt->bind_param("i", $order_id);
        $result = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if (!$result || $affected < 1) {
            throw new Exception("Заказ хранения не удален");
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log("Ошибка удаления хранения #$order_id: " . $e->getMessage());
        return false;
    }

    foreach ($photos as $photo) {
        if (file_exists($photo)) {
            unlink($photo);
        }
    }

    if (function_exists('log_change')) {
        log_change("Удален заказ хранения {$order['inventory_number']} ({$order['client_name']})");
    }

    if ($result) {
        $message = "<b>Заказ хранения #{$order['inventory_number']} удалён</b>\n" .
                   "Клиент: {$order['client_name']}\n" .
                   "Удалён: " . date('d.m.Y H:i');
        sendTelegramNotification($message, []);
    }

    return $result;
}

// Функция для логирования изменений
function logStorageHistory($order_id, $user_id, $action, $details)
{
    global $conn;
    $stmt = $conn->prepare("INSERT INTO storage_history (storage_order_id, user_id, action, details, created_at) VALUES (?, ?, ?, ?, NOW())");
    if ($stmt === false) {
        error_log("Ошибка подготовки запроса для лога: " . $conn->error);
        return;
    }
    $stmt->bind_param("iiss", $order_id, $user_id, $action, $details);
    $result = $stmt->execute();
    if (!$result) {
        error_log("Ошибка записи лога: " . $stmt->error);
    }
    $stmt->close();
}

// Функция для получения истории изменений
function getStorageHistory($order_id)
{
    global $conn;
    $stmt = $conn->prepare("SELECT sh.*, u.username FROM storage_history sh LEFT JOIN users u ON sh.user_id = u.id WHERE sh.storage_order_id = ? ORDER BY sh.created_at DESC");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
    return $history;
}

// Функция для получения заказов хранения
function getStorageOrders()
{
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM storage_orders ORDER BY created_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
    return $orders;
}

function storage_excel_escape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function storage_excel_date($value): string
{
    $raw = trim((string)$value);
    if ($raw === '' || $raw === '0000-00-00' || $raw === '0000-00-00 00:00:00') {
        return '';
    }
    $ts = strtotime($raw);
    return $ts ? date('Y-m-d', $ts) : $raw;
}

function exportStorageOrders($query, $status_filter, $location_filter): void
{
    $orders = ($query || $status_filter || $location_filter)
        ? searchStorageOrders($query, $status_filter, $location_filter)
        : getStorageOrders();

    $file_name = 'Хранение_' . date('Y-m-d_H-i') . '.xls';
    if (ob_get_length()) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . rawurlencode($file_name) . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo "\xEF\xBB\xBF";
    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<table border="1">';
    echo '<tr>';
    foreach (['Инв. номер', 'ФИО клиента', 'Телефон', 'Статус', 'Место хранения', 'Дата начала', 'Дата окончания', 'Примечание', 'Создано'] as $header) {
        echo '<th>' . storage_excel_escape($header) . '</th>';
    }
    echo '</tr>';
    foreach ($orders as $order) {
        echo '<tr>';
        echo '<td>' . storage_excel_escape($order['inventory_number'] ?? '') . '</td>';
        echo '<td>' . storage_excel_escape($order['client_name'] ?? '') . '</td>';
        echo '<td>' . storage_excel_escape($order['phone'] ?? '') . '</td>';
        echo '<td>' . storage_excel_escape($order['status'] ?? '') . '</td>';
        echo '<td>' . storage_excel_escape($order['storage_location'] ?? '') . '</td>';
        echo '<td>' . storage_excel_escape(storage_excel_date($order['storage_start_date'] ?? '')) . '</td>';
        echo '<td>' . storage_excel_escape(storage_excel_date($order['storage_end_date'] ?? '')) . '</td>';
        echo '<td>' . storage_excel_escape($order['notes'] ?? '') . '</td>';
        echo '<td>' . storage_excel_escape($order['created_at'] ?? '') . '</td>';
        echo '</tr>';
    }
    echo '</table></body></html>';
    exit;
}

function storage_normalize_header($value): string
{
    $text = mb_strtolower(trim((string)$value), 'UTF-8');
    $text = str_replace(['ё', '.', ',', ':', ';', '-', '_', '№'], ['е', ' ', ' ', ' ', ' ', ' ', ' ', 'n'], $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim($text);
}

function storage_header_key($header): string
{
    $h = storage_normalize_header($header);
    if (in_array($h, ['инв номер', 'номер', 'n', 'id'], true)) return 'inventory_number';
    if (in_array($h, ['фио клиента', 'клиент', 'имя', 'фио', 'client name'], true)) return 'client_name';
    if (in_array($h, ['телефон', 'phone'], true)) return 'phone';
    if (in_array($h, ['статус', 'status'], true)) return 'status';
    if (in_array($h, ['место хранения', 'место', 'локация', 'location'], true)) return 'storage_location';
    if (in_array($h, ['дата начала', 'начало', 'дата начала хранения', 'storage start date'], true)) return 'storage_start_date';
    if (in_array($h, ['дата окончания', 'окончание', 'дата окончания хранения', 'storage end date'], true)) return 'storage_end_date';
    if (in_array($h, ['примечание', 'заметка', 'notes', 'note'], true)) return 'notes';
    return '';
}

function storage_normalize_date_for_db($value): ?string
{
    $raw = trim((string)$value);
    if ($raw === '') return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return $raw;
    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $raw, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
    }
    if (is_numeric($raw)) {
        $days = (float)$raw;
        if ($days > 20000 && $days < 80000) {
            return gmdate('Y-m-d', (int)(($days - 25569) * 86400));
        }
    }
    $ts = strtotime($raw);
    return $ts ? date('Y-m-d', $ts) : null;
}

function storage_import_rows_from_csv(string $path): array
{
    $content = (string)file_get_contents($path);
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    $lines = preg_split('/\r\n|\n|\r/', $content);
    $sample = implode("\n", array_slice($lines ?: [], 0, 5));
    $delimiter = substr_count($sample, ';') >= substr_count($sample, ',') ? ';' : ',';
    if (substr_count($sample, "\t") > substr_count($sample, $delimiter)) {
        $delimiter = "\t";
    }
    $rows = [];
    foreach ($lines as $line) {
        if (trim((string)$line) === '') continue;
        $rows[] = str_getcsv($line, $delimiter);
    }
    return $rows;
}

function storage_import_rows_from_html_xls(string $path): array
{
    $html = (string)file_get_contents($path);
    $rows = [];
    if (preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/isu', $html, $trMatches)) {
        foreach ($trMatches[1] as $tr) {
            $cells = [];
            if (preg_match_all('/<t[dh]\b[^>]*>(.*?)<\/t[dh]>/isu', $tr, $tdMatches)) {
                foreach ($tdMatches[1] as $cell) {
                    $cells[] = trim(html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                }
            }
            if ($cells) $rows[] = $cells;
        }
    }
    return $rows;
}

function storage_import_rows_from_xlsx(string $path): array
{
    if (!class_exists('ZipArchive')) {
        return [];
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return [];
    }

    $shared = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $xml = @simplexml_load_string($sharedXml);
        if ($xml) {
            foreach ($xml->si as $si) {
                $parts = [];
                if (isset($si->t)) {
                    $parts[] = (string)$si->t;
                }
                if (isset($si->r)) {
                    foreach ($si->r as $run) {
                        $parts[] = (string)$run->t;
                    }
                }
                $shared[] = implode('', $parts);
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false) {
        return [];
    }
    $xml = @simplexml_load_string($sheetXml);
    if (!$xml) {
        return [];
    }

    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $cell) {
            $ref = (string)$cell['r'];
            $colLetters = preg_replace('/[^A-Z]/', '', strtoupper($ref));
            $colIndex = 0;
            for ($i = 0; $i < strlen($colLetters); $i++) {
                $colIndex = $colIndex * 26 + (ord($colLetters[$i]) - 64);
            }
            $idx = max(0, $colIndex - 1);
            $type = (string)$cell['t'];
            $value = '';
            if ($type === 's') {
                $value = $shared[(int)$cell->v] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = (string)($cell->is->t ?? '');
            } else {
                $value = (string)($cell->v ?? '');
            }
            $cells[$idx] = $value;
        }
        if ($cells) {
            ksort($cells);
            $max = max(array_keys($cells));
            $out = [];
            for ($i = 0; $i <= $max; $i++) {
                $out[] = $cells[$i] ?? '';
            }
            $rows[] = $out;
        }
    }
    return $rows;
}

function storage_import_rows_from_file(array $file): array
{
    $path = (string)($file['tmp_name'] ?? '');
    $name = mb_strtolower((string)($file['name'] ?? ''), 'UTF-8');
    if ($path === '' || !is_uploaded_file($path)) {
        return [];
    }
    if (str_ends_with($name, '.xlsx')) {
        return storage_import_rows_from_xlsx($path);
    }
    if (str_ends_with($name, '.xls')) {
        $rows = storage_import_rows_from_html_xls($path);
        return $rows ?: storage_import_rows_from_csv($path);
    }
    return storage_import_rows_from_csv($path);
}

function importStorageOrdersFromFile(array $file): array
{
    global $conn;
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'count' => 0, 'message' => 'Выберите файл для добавления в базу.'];
    }

    $rows = storage_import_rows_from_file($file);
    if (count($rows) < 2) {
        return ['success' => false, 'count' => 0, 'message' => 'В файле не найдены строки для добавления в базу.'];
    }

    $headers = array_shift($rows);
    $map = [];
    foreach ($headers as $i => $header) {
        $key = storage_header_key($header);
        if ($key !== '') $map[$i] = $key;
    }
    if (!in_array('client_name', $map, true)) {
        return ['success' => false, 'count' => 0, 'message' => 'В файле должна быть колонка "ФИО клиента" или "Клиент".'];
    }

    $stmt = $conn->prepare("INSERT INTO storage_orders (client_name, phone, notes, status, storage_start_date, storage_end_date, storage_location, photos, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, '', NOW())");
    if (!$stmt) {
        return ['success' => false, 'count' => 0, 'message' => 'Не удалось подготовить добавление в базу.'];
    }

    $count = 0;
    $conn->begin_transaction();
    try {
        foreach ($rows as $row) {
            $data = [
                'client_name' => '',
                'phone' => '',
                'notes' => '',
                'status' => 'На хранении',
                'storage_start_date' => null,
                'storage_end_date' => null,
                'storage_location' => '',
            ];
            foreach ($map as $i => $key) {
                $data[$key] = trim((string)($row[$i] ?? ''));
            }
            if ($data['client_name'] === '') {
                continue;
            }
            $data['status'] = in_array($data['status'], ['На хранении', 'Выдано'], true) ? $data['status'] : 'На хранении';
            $data['storage_start_date'] = storage_normalize_date_for_db($data['storage_start_date']);
            $data['storage_end_date'] = storage_normalize_date_for_db($data['storage_end_date']);
            $client_name = htmlspecialchars($data['client_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $phone = htmlspecialchars($data['phone'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $notes = htmlspecialchars($data['notes'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $status = $data['status'];
            $start = $data['storage_start_date'];
            $end = $data['storage_end_date'];
            $location = htmlspecialchars($data['storage_location'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $stmt->bind_param("sssssss", $client_name, $phone, $notes, $status, $start, $end, $location);
            if (!$stmt->execute()) {
                throw new Exception('Ошибка вставки строки при добавлении в базу.');
            }
            $order_id = $conn->insert_id;
            $inventory_number = sprintf("ST-%04d", $order_id);
            $upd = $conn->prepare("UPDATE storage_orders SET inventory_number = ? WHERE id = ?");
            $upd->bind_param("si", $inventory_number, $order_id);
            $upd->execute();
            $upd->close();
            logStorageHistory($order_id, (int)($_SESSION['user_id'] ?? 0), 'Добавление в базу', "Добавлен заказ из файла: $client_name, $inventory_number");
            $count++;
        }
        $stmt->close();
        $conn->commit();
    } catch (Throwable $e) {
        $stmt->close();
        $conn->rollback();
        return ['success' => false, 'count' => 0, 'message' => 'Ошибка добавления в базу: ' . $e->getMessage()];
    }

    if ($count === 0) {
        return ['success' => false, 'count' => 0, 'message' => 'В файле нет строк с заполненным клиентом.'];
    }
    log_change("Добавлено в базу заказов хранения: $count");
    return ['success' => true, 'count' => $count, 'message' => ''];
}

// Функция для поиска и фильтрации заказов хранения
function searchStorageOrders($query, $status_filter, $location_filter)
{
    global $conn;
    $query = "%" . htmlspecialchars(trim($query)) . "%";
    $sql = "SELECT * FROM storage_orders WHERE (client_name LIKE ? OR phone LIKE ? OR notes LIKE ? OR inventory_number LIKE ?)";
    $params = [$query, $query, $query, $query];
    $types = "ssss";

    if ($status_filter) {
        $sql .= " AND status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }

    if ($location_filter) {
        $sql .= " AND storage_location LIKE ?";
        $params[] = "%" . htmlspecialchars(trim($location_filter)) . "%";
        $types .= "s";
    }

    $sql .= " ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Ошибка подготовки запроса для поиска: " . $conn->error);
        return [];
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
    return $orders;
}

// Получение уникальных мест хранения для фильтра
function getUniqueStorageLocations()
{
    global $conn;
    $result = $conn->query("SELECT DISTINCT storage_location FROM storage_orders WHERE storage_location IS NOT NULL AND storage_location != '' ORDER BY storage_location");
    $locations = [];
    while ($row = $result->fetch_assoc()) {
        $locations[] = $row['storage_location'];
    }
    return $locations;
}

$orders = ($search_query || $status_filter || $location_filter) ? searchStorageOrders($search_query, $status_filter, $location_filter) : getStorageOrders();
$storage_locations = getUniqueStorageLocations();

function format_storage_date($value)
{
    $raw = trim((string)$value);
    if ($raw === '' || $raw === '0000-00-00' || $raw === '0000-00-00 00:00:00') {
        return 'не указана';
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false || (int)date('Y', $timestamp) <= 1) {
        return 'не указана';
    }

    return date('d.m.Y', $timestamp);
}

function format_storage_datetime($value)
{
    $raw = trim((string)$value);
    if ($raw === '' || $raw === '0000-00-00' || $raw === '0000-00-00 00:00:00') {
        return 'не указана';
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false || (int)date('Y', $timestamp) <= 1) {
        return 'не указана';
    }

    return date('d.m.Y H:i', $timestamp);
}

function get_storage_until_date($created_at): ?DateTimeImmutable
{
    $raw = trim((string)$created_at);
    if ($raw === '' || $raw === '0000-00-00' || $raw === '0000-00-00 00:00:00') {
        return null;
    }

    try {
        return (new DateTimeImmutable($raw))->modify('+6 months');
    } catch (Throwable $e) {
        return null;
    }
}

$orders_view = [];
foreach ($orders as $order) {
    $raw_photos = [];
    if (!empty($order['photos'])) {
        $raw_photos = array_values(array_filter(array_map('trim', explode(',', (string)$order['photos']))));
    }

    $photo_entries = [];
    foreach ($raw_photos as $raw_photo) {
        $resolved_photo = resolvePhotoPath($raw_photo);
        if ($resolved_photo !== '' && file_exists($resolved_photo)) {
            $photo_entries[] = [
                'raw' => $raw_photo,
                'src' => $resolved_photo,
            ];
        }
    }

    $history_rows = getStorageHistory((int)$order['id']);
    $history_view = [];
    foreach ($history_rows as $entry) {
        $history_view[] = [
            'created_at_fmt' => !empty($entry['created_at']) ? date('d.m.Y H:i', strtotime($entry['created_at'])) : '',
            'username' => (string)($entry['username'] ?? 'Неизвестный'),
            'action' => (string)($entry['action'] ?? ''),
            'details' => (string)($entry['details'] ?? ''),
        ];
    }

    $storage_until = get_storage_until_date($order['created_at'] ?? '');
    $storage_until_fmt = $storage_until ? $storage_until->format('d.m.Y') : 'не указана';
    $storage_expired = false;
    if ($storage_until && (($order['status'] ?? '') !== 'Выдано')) {
        $storage_expired = (new DateTimeImmutable('today')) > $storage_until->setTime(23, 59, 59);
    }

    $phone = (string)($order['phone'] ?? '');
    $phone_href = preg_replace('/[^0-9+]/', '', $phone);

    $orders_view[] = [
        'id' => (int)$order['id'],
        'inventory_number' => (string)($order['inventory_number'] ?? ''),
        'client_name' => (string)($order['client_name'] ?? ''),
        'status' => (string)($order['status'] ?? ''),
        'phone' => $phone,
        'phone_href' => $phone_href !== '' ? $phone_href : $phone,
        'notes' => (string)($order['notes'] ?? ''),
        'storage_location' => (string)($order['storage_location'] ?? ''),
        'storage_start_date' => (string)($order['storage_start_date'] ?? ''),
        'storage_end_date' => (string)($order['storage_end_date'] ?? ''),
        'created_at' => (string)($order['created_at'] ?? ''),
        'storage_start_date_fmt' => format_storage_date($order['storage_start_date'] ?? ''),
        'storage_end_date_fmt' => format_storage_date($order['storage_end_date'] ?? ''),
        'storage_until_fmt' => $storage_until_fmt,
        'storage_expired' => $storage_expired,
        'created_at_fmt' => format_storage_datetime($order['created_at'] ?? ''),
        'status_class' => (($order['status'] ?? '') === 'Выдано') ? 'status-issued' : 'status-active',
        'photos' => $photo_entries,
        'history' => $history_view,
    ];
}
?>


<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Хранение</title>
    <link rel="icon" type="image/jpeg" href="Logo.png">
    <link rel="apple-touch-icon" href="Logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="views-global.css">
    <style>
        :root {
            --bg: #f3f5f8;
            --card: #ffffff;
            --line: #c9d1dc;
            --text: #142033;
            --muted: #5d6a7e;
            --accent: #0f5db8;
            --accent-soft: #eaf3ff;
            --danger: #b82626;
            --ok: #0f7a33;
            --shadow: 0 8px 26px rgba(23, 43, 77, 0.1);
        }

        * { box-sizing: border-box; }

        html, body {
            max-width: 100%;
            overflow-x: hidden;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: radial-gradient(circle at top left, #fbfdff 0%, var(--bg) 60%);
            color: var(--text);
            min-height: 100vh;
            padding: 20px;
            margin-top: 50px;
            margin-bottom: 80px;
        }

        .app {
            width: 100%;
            max-width: 1180px;
            margin: 0 auto;
            display: grid;
            gap: 16px;
        }

        .panel {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            box-shadow: var(--shadow);
            padding: 16px;
            overflow: hidden;
        }

        .spoiler {
            width: 100%;
        }

        .spoiler-title {
            list-style: none;
            cursor: pointer;
            user-select: none;
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0.4px;
            color: #163f76;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .spoiler-title::-webkit-details-marker {
            display: none;
        }

        .spoiler-title::before {
            content: "▸";
            font-size: 16px;
            transition: transform 0.2s ease;
            color: var(--accent);
        }

        .spoiler[open] .spoiler-title::before {
            transform: rotate(90deg);
        }

        .spoiler-content {
            margin-top: 12px;
        }

        .inline-group {
            display: grid;
            gap: 8px;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .field {
            display: grid;
            gap: 4px;
        }

        .field label {
            font-size: 12px;
            color: var(--muted);
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 8px 10px;
            font-size: 14px;
            color: var(--text);
            background: #fff;
        }

        .field textarea {
            min-height: 110px;
            resize: vertical;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            border-color: var(--accent);
            outline: 2px solid var(--accent-soft);
        }

        .grid-2 {
            margin-top: 10px;
            display: grid;
            gap: 8px;
            grid-template-columns: 1.5fr 1fr;
        }

        .photo-box {
            border: 2px dashed var(--line);
            border-radius: 10px;
            padding: 10px;
            display: grid;
            gap: 8px;
            align-content: start;
            min-height: 220px;
            background: #fbfcff;
        }

        .preview {
            width: 100%;
            height: 170px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fff;
            object-fit: cover;
            display: none;
        }

        .preview.visible {
            display: block;
        }

        .thumbs {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .thumb {
            width: 56px;
            height: 56px;
            border: 1px solid var(--line);
            border-radius: 8px;
            object-fit: cover;
            background: #fff;
        }

        .thumb-btn {
            padding: 0;
            border: 2px solid transparent;
            border-radius: 10px;
            background: transparent;
            cursor: pointer;
            line-height: 0;
        }

        .thumb-btn.active {
            border-color: var(--accent);
        }

        .photo-actions {
            display: grid;
            gap: 8px;
            grid-template-columns: 1fr 1fr;
        }

        .photo-hint {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.35;
        }

        .btn {
            border: 1px solid var(--accent);
            background: var(--accent);
            color: #fff;
            border-radius: 9px;
            padding: 9px 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
        }

        .btn:hover {
            filter: brightness(1.05);
            color: #fff;
        }

        .btn-secondary {
            background: #fff;
            color: var(--accent);
        }

        .btn-secondary:hover {
            color: var(--accent);
        }

        .btn-danger {
            border-color: var(--danger);
            background: #fff;
            color: var(--danger);
        }

        .btn-danger:hover {
            color: var(--danger);
        }

        .actions {
            margin-top: 10px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .status {
            border: 1px solid #f5c2c7;
            background: #fef2f3;
            color: #842029;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 14px;
            font-weight: 600;
        }

        .status-success {
            border-color: #badbcc;
            background: #eefaf2;
            color: #0f5132;
        }

        .filters {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 10px;
            background: #fafcff;
        }

        .filters-inline {
            display: grid;
            gap: 8px;
            grid-template-columns: 2fr 1fr 1fr;
            align-items: end;
        }

        .filter-input-wrap {
            position: relative;
        }

        .filter-input-wrap input {
            padding-right: 30px;
        }

        .filter-clear {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            border: 1px solid #c9d1dc;
            border-radius: 50%;
            background: #fff;
            color: #5d6a7e;
            font-size: 13px;
            line-height: 16px;
            text-align: center;
            cursor: pointer;
            padding: 0;
        }

        .filter-clear.hidden {
            display: none;
        }

        .filters-actions {
            margin-top: 10px;
        }

        .filters-meta {
            margin-top: 8px;
            font-size: 13px;
            color: var(--muted);
        }

        .table-wrap {
            width: 100%;
            overflow: auto;
            border: 1px solid #d7deea;
            border-radius: 12px;
            background: #fff;
            box-shadow: inset 0 0 0 1px #eef2f8;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
            background: #fff;
            border: 1px solid #ccd7e6;
        }

        th,
        td {
            padding: 11px 12px;
            border: 1px solid #d4ddeb;
            font-size: 14px;
            text-align: left;
            vertical-align: top;
        }

        th {
            position: sticky;
            top: 0;
            background: linear-gradient(180deg, #f8fbff 0%, #f1f6ff 100%);
            color: #253753;
            font-weight: 700;
            z-index: 1;
        }

        .row-item {
            cursor: pointer;
            transition: background-color 0.18s ease;
        }

        .row-item:nth-child(even) {
            background: #fcfdff;
        }

        .row-item:hover {
            background: #eef5ff;
        }

        .row-item.selected {
            background: #e0ecff;
        }

        .row-item.storage-expired {
            background: #fff8d6;
        }

        .row-item.storage-expired:nth-child(even) {
            background: #fff2a8;
        }

        .row-item.storage-expired:hover,
        .row-item.storage-expired.selected {
            background: #ffec80;
        }

        .inline-detail-row td {
            padding: 0;
            border-top: 0;
            background: #f7fbff;
        }

        .inline-detail-cell {
            padding: 12px;
        }

        .inline-detail-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 10px;
        }

        .inline-detail-card {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fff;
            padding: 10px;
        }

        .inline-detail-title {
            margin: 0 0 8px;
            font-size: 14px;
            font-weight: 700;
            color: #1f2f49;
        }

        .inline-detail-list {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 6px;
        }

        .inline-detail-list li {
            font-size: 13px;
            line-height: 1.35;
            color: #2e3e55;
        }

        .inline-detail-history {
            max-height: 180px;
            overflow: auto;
            border: 1px solid #e0e7f0;
            border-radius: 8px;
            padding: 6px 8px;
            background: #fbfdff;
        }

        .inline-detail-actions {
            margin-top: 10px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .inline-photos {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
        }

        .mobile-inline-detail {
            margin-top: 8px;
            border-top: 1px dashed #d3dceb;
            padding-top: 8px;
        }

        .order-line {
            font-size: 14px;
            font-weight: 800;
            color: #1f2f49;
            line-height: 1.25;
            white-space: normal;
        }

        .row-sub {
            margin-top: 4px;
            font-size: 12px;
            color: var(--muted);
            line-height: 1.25;
            white-space: normal;
        }

        .order-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .status-active {
            background: #eaf7ee;
            border-color: #9fdfb3;
            color: #1f7a3e;
        }

        .status-issued {
            background: #f6f7f9;
            border-color: #c9d1dc;
            color: #475569;
        }

        .storage-until-line {
            margin-top: 3px;
            color: #1f7a3e;
            font-weight: 800;
        }

        .storage-until-line.is-expired {
            color: #7a4b00;
        }

        .mobile-list {
            display: none;
            gap: 8px;
        }

        .mobile-card {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fff;
            padding: 8px;
            display: grid;
            gap: 6px;
            cursor: pointer;
        }

        .mobile-card.selected {
            border-color: #0f5db8;
            box-shadow: inset 0 0 0 1px #0f5db8;
            background: #f1f7ff;
        }

        .mobile-card.storage-expired {
            border-color: #d6a900;
            background: #fff8d6;
        }

        .mobile-card.storage-expired.selected {
            background: #fff2a8;
            border-color: #d6a900;
            box-shadow: inset 0 0 0 1px #d6a900;
        }

        .mobile-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 8px;
            align-items: center;
        }

        .mobile-title {
            font-size: 14px;
            font-weight: 700;
            color: #1f2f49;
            line-height: 1.25;
        }

        .mobile-meta {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.3;
        }

        .detail {
            display: none;
            grid-template-columns: minmax(0, 1fr) 136px;
            gap: 8px;
            align-items: start;
        }

        .detail.visible {
            display: grid;
        }

        .detail-main {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 8px;
            background: #fff;
        }

        .detail-status {
            margin-bottom: 8px;
        }

        .detail-section-title {
            margin: 8px 0 6px;
            font-size: 12px;
            font-weight: 700;
            color: #2a3a52;
        }

        .card-grid {
            display: grid;
            gap: 8px;
            grid-template-columns: repeat(3, minmax(70px, 1fr));
        }

        .chip {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 6px;
            background: #f9fbff;
            min-height: 46px;
        }

        .chip .k {
            color: var(--muted);
            font-size: 10px;
            margin-bottom: 2px;
        }

        .chip .v {
            font-size: 14px;
            font-weight: 700;
            white-space: normal;
            word-break: break-word;
        }

        .detail .field textarea {
            min-height: 76px;
        }

        .history-list {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fbfcff;
            padding: 6px 8px;
            max-height: 240px;
            overflow: auto;
        }

        .history-item {
            margin: 0;
            padding: 6px 0;
            border-bottom: 1px solid #e8eef6;
            font-size: 12px;
            line-height: 1.35;
            color: #324257;
            white-space: normal;
        }

        .history-item:last-child {
            border-bottom: 0;
        }

        .detail-photo-btn {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fff;
            padding: 4px;
            cursor: zoom-in;
        }

        .detail-photo {
            width: 100%;
            height: 136px;
            object-fit: cover;
            border: 0;
            border-radius: 9px;
            background: #fff;
        }

        .detail-thumbs {
            margin-top: 6px;
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            justify-content: flex-start;
        }

        .detail-actions {
            margin-top: 8px;
        }

        #editSelected {
            background: #0b67c2;
            border-color: #0b67c2;
            color: #ffffff;
        }

        #collapseDetail {
            background: #4b5563;
            border-color: #4b5563;
            color: #ffffff;
        }

        #deleteSelected {
            background: #c62828;
            border-color: #c62828;
            color: #ffffff;
        }

        #editSelected:hover,
        #collapseDetail:hover,
        #deleteSelected:hover {
            filter: brightness(1.08);
        }

        .hidden {
            display: none !important;
        }

        .empty-orders {
            border: 1px dashed var(--line);
            border-radius: 10px;
            padding: 16px;
            color: var(--muted);
            background: #fbfdff;
            text-align: center;
        }

        .live-search-empty {
            margin-top: 10px;
            border: 1px dashed var(--line);
            border-radius: 10px;
            padding: 12px;
            color: var(--muted);
            background: #fbfdff;
            text-align: center;
            display: none;
        }

        .search-highlight {
            display: inline;
            padding: 0 2px;
            border-radius: 3px;
            background: #fff176;
            color: #111827;
            font-weight: 800;
            box-shadow: 0 0 0 1px rgba(198, 148, 0, 0.2);
        }

        .muted {
            color: var(--muted);
            font-size: 12px;
        }

        .phone-link {
            color: #0b67c2;
            text-decoration: none;
        }

        .phone-link:hover {
            text-decoration: underline;
        }

        .photos-container {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 10px;
        }

        .photo-preview {
            width: 88px;
            height: 66px;
            object-fit: cover;
            border: 1px solid var(--line);
            border-radius: 8px;
            cursor: zoom-in;
            background: #fff;
        }

        .photo-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-right: 8px;
            margin-bottom: 6px;
        }

        .photo-item .photo-checkbox {
            width: 16px;
            height: 16px;
        }

        .top-nav {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1001;
            background: linear-gradient(145deg, #ffffff, #eef2f5);
            border-bottom: 1px solid #d9e2ec;
            padding: 4px 8px;
            overflow-x: auto;
            white-space: nowrap;
            gap: 8px;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .top-nav::-webkit-scrollbar { display: none; }

        .top-nav a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 10px;
            text-decoration: none;
            color: #1f2a37;
            border-radius: 8px;
            font-size: 12px;
            flex: 0 0 auto;
        }

        .top-nav a.active {
            background: linear-gradient(145deg, #00b4d8, #0077b6);
            color: #ffffff;
        }

        .top-nav-spacer {
            display: none;
        }

        .desktop-nav-spacer {
            display: none;
        }

        .bottom-nav {
            display: none;
            justify-content: space-around;
            align-items: center;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: linear-gradient(145deg, #ffffff, #eef2f5);
            z-index: 1000;
            padding: 5px 0;
            box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.25);
            overflow-x: auto;
            overflow-y: hidden;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .bottom-nav::-webkit-scrollbar { display: none; }

        .bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #1f2a37;
            text-decoration: none;
            font-size: 12px;
            padding: 5px 10px;
            border-radius: 8px;
            min-width: 80px;
            min-height: 50px;
            flex: 0 auto;
        }

        .bottom-nav .nav-item.active {
            background: linear-gradient(145deg, #00b4d8, #0077b6);
            color: #ffffff;
        }

        .nav-menu {
            position: fixed;
            top: 0;
            left: -280px;
            width: 280px;
            height: 100%;
            background: linear-gradient(145deg, #ffffff, #eef2f5);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25);
            z-index: 1100;
            display: flex;
            flex-direction: column;
            padding: 20px;
            transition: left 0.3s ease-in-out;
            overflow-y: auto;
        }

        .nav-menu.open { left: 0; }

        .nav-menu a, .nav-menu button {
            color: #1f2a37;
            text-decoration: none;
            padding: 12px;
            font-size: 16px;
            border-radius: 8px;
            margin-bottom: 8px;
            display: block;
            text-align: left;
            border: none;
            background: transparent;
            width: 100%;
            box-sizing: border-box;
        }

        .nav-menu a.active {
            background: linear-gradient(145deg, #00b4d8, #0077b6);
            color: #ffffff;
            box-shadow: 0 2px 10px rgba(0, 180, 216, 0.3);
        }

        .nav-toggle {
            position: fixed;
            top: 6px;
            left: 6px;
            z-index: 1200;
            border: none;
            border-radius: 10px;
            width: 30px;
            height: 30px;
            min-height: 30px;
            min-width: 30px;
            padding: 0;
            font-size: 16px;
            line-height: 1;
            background: linear-gradient(145deg, #00b4d8, #0077b6);
            color: #fff;
            box-shadow: 0 4px 15px rgba(0, 180, 216, 0.3);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        @media (max-width: 992px) {
            .top-nav { display: none !important; }
            .bottom-nav { display: none !important; }
            .nav-toggle,
            .nav-toggle.d-none,
            .nav-toggle.d-none.d-lg-flex {
                position: fixed !important;
                top: 16px !important;
                left: 16px !important;
                z-index: 1301 !important;
                width: auto !important;
                height: auto !important;
                min-width: 0 !important;
                min-height: 42px !important;
                padding: 8px 12px !important;
                border: 1px solid rgba(80, 62, 44, 0.32) !important;
                border-radius: 10px !important;
                background: linear-gradient(145deg, #fffdf9, #efe2cf) !important;
                color: #1f2a37 !important;
                font-size: 14px !important;
                font-weight: 800 !important;
                line-height: 1.2 !important;
                box-shadow: 0 10px 24px rgba(76, 55, 31, 0.18), inset 0 1px 0 rgba(255, 255, 255, 0.9) !important;
                opacity: 0.8 !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                overflow: visible !important;
                cursor: pointer !important;
            }
            .nav-toggle::before,
            .nav-toggle::after {
                content: none !important;
                display: none !important;
                animation: none !important;
            }
            body {
                padding-bottom: 12px;
                margin-top: 0;
            }
            .top-nav-spacer {
                display: none !important;
                height: 0;
            }
            .desktop-nav-spacer {
                display: none;
            }
        }

        @media (min-width: 993px) {
            .bottom-nav { display: none; }
            .top-nav { display: none !important; }
            .nav-toggle { display: inline-flex !important; }
            .desktop-nav-spacer {
                display: block;
                height: 40px;
            }
        }

        @media (max-width: 900px) {
            body { padding: 12px; }
            .app { gap: 12px; }
            .panel { padding: 12px; }
            .inline-group { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .grid-2,
            .photo-actions,
            .filters-inline {
                grid-template-columns: 1fr;
            }
            .detail {
                grid-template-columns: minmax(0, 1fr) 112px;
            }
            .card-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .inline-detail-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 520px) {
            body { padding: 8px; }
            .panel {
                padding: 10px;
                border-radius: 10px;
            }
            .spoiler-title {
                font-size: 17px;
            }
            .inline-group { grid-template-columns: 1fr; }
            .field input,
            .field select,
            .field textarea,
            .btn { font-size: 16px; }
            .table-wrap {
                display: none;
            }
            .mobile-list {
                display: grid;
            }
            .detail {
                grid-template-columns: minmax(0, 1fr) 92px;
                gap: 4px;
            }
            .card-grid {
                grid-template-columns: 1fr 1fr;
                gap: 4px;
            }
            .chip {
                min-height: 38px;
                padding: 5px;
            }
            .chip .k {
                font-size: 9px;
            }
            .chip .v {
                font-size: 12px;
            }
            .detail-photo {
                height: 92px;
            }
            .detail-actions {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 4px;
            }
            .detail-actions .btn {
                width: auto;
                padding: 6px 4px;
                font-size: 11px;
                border-radius: 6px;
                white-space: nowrap;
            }
            .photo-preview {
                width: 74px;
                height: 56px;
            }
        }

        @media (min-width: 993px) {
            .nav-toggle,
            .nav-toggle.d-none,
            .nav-toggle.d-none.d-lg-flex {
                position: fixed !important;
                top: 16px !important;
                left: 16px !important;
                z-index: 1301 !important;
                width: auto !important;
                height: auto !important;
                min-width: 0 !important;
                min-height: 42px !important;
                padding: 8px 12px !important;
                border: 1px solid rgba(80, 62, 44, 0.32) !important;
                border-radius: 10px !important;
                background: linear-gradient(145deg, #fffdf9, #efe2cf) !important;
                color: #1f2a37 !important;
                font-size: 14px !important;
                font-weight: 800 !important;
                line-height: 1.2 !important;
                box-shadow: 0 10px 24px rgba(76, 55, 31, 0.18), inset 0 1px 0 rgba(255, 255, 255, 0.9) !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                overflow: visible !important;
            }

            .nav-toggle::before,
            .nav-toggle::after {
                content: none !important;
                display: none !important;
                animation: none !important;
            }
        }

        .nav-toggle,
        .nav-toggle.d-none,
        .nav-toggle.d-none.d-lg-flex {
            top: 6px !important;
            left: 6px !important;
            width: 30px !important;
            height: 30px !important;
            min-width: 30px !important;
            min-height: 30px !important;
            max-width: 30px !important;
            max-height: 30px !important;
            padding: 0 !important;
            border-radius: 7px !important;
            font-size: 20px !important;
            line-height: 1 !important;
            overflow: hidden !important;
        }
    </style>
</head>
<body class="has-top-nav">
    <?php renderUnifiedNavigation('storage', ['show_top' => true, 'show_bottom' => true, 'show_spacers' => true, 'toggle_label' => '☰', 'toggle_class' => 'btn nav-toggle d-none d-lg-flex']); ?>

    <div class="app">
        <?php if ($storage_error) : ?>
            <div class="status"><?php echo htmlspecialchars($storage_error); ?></div>
        <?php endif; ?>
        <?php if ($storage_success) : ?>
            <div class="status status-success"><?php echo htmlspecialchars($storage_success); ?></div>
        <?php endif; ?>

        <section class="panel">
            <details class="spoiler" id="storageCreateSpoiler">
                <summary class="spoiler-title">НОВЫЙ ЗАКАЗ ХРАНЕНИЯ</summary>
                <div class="spoiler-content">
                    <form method="post" enctype="multipart/form-data" id="storageOrderForm">
                        <div class="inline-group">
                            <div class="field">
                                <label for="client_name">ФИО клиента *</label>
                                <input type="text" id="client_name" name="client_name" required>
                            </div>
                            <div class="field">
                                <label for="phone">Телефон</label>
                                <input type="tel" id="phone" name="phone">
                            </div>
                            <div class="field">
                                <label for="status">Статус *</label>
                                <select id="status" name="status" required>
                                    <option value="На хранении">На хранении</option>
                                    <option value="Выдано">Выдано</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="storage_start_date">Дата начала хранения</label>
                                <input type="date" id="storage_start_date" name="storage_start_date">
                            </div>
                            <div class="field">
                                <label for="storage_end_date">Дата окончания хранения</label>
                                <input type="date" id="storage_end_date" name="storage_end_date">
                            </div>
                            <div class="field">
                                <label for="storage_location">Место хранения</label>
                                <input type="text" id="storage_location" name="storage_location" list="storageLocationOptions" placeholder="Например: Склад 1, Полка А-12">
                            </div>
                        </div>

                        <div class="grid-2">
                            <div class="field">
                                <label for="notes">Примечание</label>
                                <textarea id="notes" name="notes"></textarea>
                            </div>
                            <div class="photo-box">
                                <img id="preview" class="preview" alt="Предпросмотр фото">
                                <div id="previewThumbs" class="thumbs"></div>
                                <div class="photo-actions">
                                    <label class="btn btn-secondary" for="photos">Добавить фото</label>
                                    <label class="btn btn-secondary" for="cameraPhotos">Камера</label>
                                </div>
                                <input type="file" id="photos" name="photos[]" multiple accept="image/jpeg,image/png,image/gif" hidden>
                                <input type="file" id="cameraPhotos" name="photos[]" accept="image/jpeg,image/png,image/gif" capture="environment" hidden>
                                <div class="photo-hint">До 4 фото, только JPG/PNG/GIF, до 5 МБ каждое.</div>
                            </div>
                        </div>

                        <datalist id="storageLocationOptions">
                            <?php foreach ($storage_locations as $location) : ?>
                                <option value="<?php echo htmlspecialchars($location); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>

                        <div class="actions">
                            <button type="submit" name="create_storage" class="btn">Сохранить заказ</button>
                            <button type="button" id="clearStorageForm" class="btn btn-secondary">Очистить форму</button>
                        </div>
                    </form>
                </div>
            </details>
        </section>

        <section class="panel">
            <div class="filters">
                <form method="get" id="filtersForm">
                    <input type="hidden" name="section" value="storage">
                    <div class="filters-inline">
                        <div class="field">
                            <label for="queryInput">Поиск</label>
                            <div class="filter-input-wrap">
                                <input type="text" id="queryInput" name="query" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Имя, телефон, номер, примечание">
                                <button id="clearQueryBtn" class="filter-clear <?php echo $search_query === '' ? 'hidden' : ''; ?>" type="button" aria-label="Очистить поиск">×</button>
                            </div>
                        </div>
                        <div class="field">
                            <label for="status_filter">Статус</label>
                            <select id="status_filter" name="status" onchange="this.form.submit()">
                                <option value="">Все статусы</option>
                                <option value="На хранении" <?php echo $status_filter === 'На хранении' ? 'selected' : ''; ?>>На хранении</option>
                                <option value="Выдано" <?php echo $status_filter === 'Выдано' ? 'selected' : ''; ?>>Выдано</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="location_filter">Место хранения</label>
                            <select id="location_filter" name="location" onchange="this.form.submit()">
                                <option value="">Все места</option>
                                <?php foreach ($storage_locations as $location) : ?>
                                    <option value="<?php echo htmlspecialchars($location); ?>" <?php echo $location_filter === $location ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($location); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="actions filters-actions">
                        <button type="submit" class="btn">Найти</button>
                        <a href="storage.php" class="btn btn-secondary">Сбросить</a>
                    </div>
                </form>
                <details class="spoiler" style="margin-top:10px;">
                    <summary>Добавить в базу и выгрузить</summary>
                    <div class="actions filters-actions" style="padding:8px 0;border-bottom:1px solid var(--line);">
                        <a href="storage.php?action=export_storage&query=<?php echo urlencode($search_query); ?>&status=<?php echo urlencode($status_filter); ?>&location=<?php echo urlencode($location_filter); ?>" class="btn btn-secondary">Выгрузить в Excel</a>
                    </div>
                    <form method="post" enctype="multipart/form-data" class="actions filters-actions" style="padding-top:8px;">
                        <input type="file" name="storage_import_file" accept=".xlsx,.xls,.csv,.tsv" required>
                        <button type="submit" name="import_storage" class="btn btn-secondary">Добавить в базу из Excel</button>
                    </form>
                </details>
                <div class="filters-meta" id="filtersMeta">Найдено заказов: <?php echo count($orders_view); ?></div>
            </div>

            <?php if (empty($orders_view)) : ?>
                <div class="empty-orders">Нет заказов хранения по выбранным условиям.</div>
            <?php else : ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Заказ</th>
                                <th>Статус</th>
                                <th>Место</th>
                                <th>Даты хранения</th>
                            </tr>
                        </thead>
                        <tbody id="ordersBody">
                            <?php foreach ($orders_view as $order) : ?>
                                <tr class="row-item<?php echo $order['storage_expired'] ? ' storage-expired' : ''; ?>" data-id="<?php echo (int)$order['id']; ?>">
                                    <td>
                                        <div class="order-line">#<?php echo htmlspecialchars($order['inventory_number']); ?> <?php echo htmlspecialchars($order['client_name']); ?></div>
                                        <div class="row-sub">
                                            <?php if ($order['phone'] !== '') : ?>
                                                Тел: <a href="tel:<?php echo htmlspecialchars($order['phone_href']); ?>" class="phone-link"><?php echo htmlspecialchars($order['phone']); ?></a> ·
                                            <?php endif; ?>
                                            Создан: <?php echo htmlspecialchars($order['created_at_fmt']); ?>
                                            <div class="storage-until-line<?php echo $order['storage_expired'] ? ' is-expired' : ''; ?>">Хранится до: <?php echo htmlspecialchars($order['storage_until_fmt']); ?></div>
                                        </div>
                                    </td>
                                    <td><span class="order-status <?php echo htmlspecialchars($order['status_class']); ?>"><?php echo htmlspecialchars($order['status']); ?></span></td>
                                    <td><?php echo htmlspecialchars($order['storage_location'] !== '' ? $order['storage_location'] : 'не указано'); ?></td>
                                    <td><?php echo htmlspecialchars($order['storage_start_date_fmt']); ?> - <?php echo htmlspecialchars($order['storage_end_date_fmt']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div id="mobileOrders" class="mobile-list">
                    <?php foreach ($orders_view as $order) : ?>
                        <div class="mobile-card<?php echo $order['storage_expired'] ? ' storage-expired' : ''; ?>" data-id="<?php echo (int)$order['id']; ?>">
                            <div class="mobile-row">
                                <div class="mobile-title">#<?php echo htmlspecialchars($order['inventory_number']); ?> <?php echo htmlspecialchars($order['client_name']); ?></div>
                                <span class="order-status <?php echo htmlspecialchars($order['status_class']); ?>"><?php echo htmlspecialchars($order['status']); ?></span>
                            </div>
                            <div class="mobile-meta">
                                <span class="storage-until-line<?php echo $order['storage_expired'] ? ' is-expired' : ''; ?>">Хранится до: <?php echo htmlspecialchars($order['storage_until_fmt']); ?></span><br>
                                <?php if ($order['phone'] !== '') : ?>
                                    Тел: <a href="tel:<?php echo htmlspecialchars($order['phone_href']); ?>" class="phone-link"><?php echo htmlspecialchars($order['phone']); ?></a><br>
                                <?php endif; ?>
                                Место: <?php echo htmlspecialchars($order['storage_location'] !== '' ? $order['storage_location'] : 'не указано'); ?><br>
                                Период: <?php echo htmlspecialchars($order['storage_start_date_fmt']); ?> - <?php echo htmlspecialchars($order['storage_end_date_fmt']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div id="liveSearchEmpty" class="live-search-empty">По вашему запросу ничего не найдено.</div>
            <?php endif; ?>
        </section>

        <section class="panel" style="display:none;">
            <div id="detail" class="detail">
                <div class="detail-main">
                    <div id="detailStatusBadge" class="order-status detail-status hidden"></div>
                    <div id="detailChips" class="card-grid"></div>
                    <div class="field" id="detailPhoneWrap" style="margin-top: 8px;">
                        <label>Телефон</label>
                        <input id="detailPhone" readonly>
                    </div>
                    <div class="field" style="margin-top: 8px;">
                        <label>Примечание</label>
                        <textarea id="detailNotes" readonly></textarea>
                    </div>
                    <div class="detail-section-title">История изменений</div>
                    <div id="detailHistory" class="history-list"></div>
                    <div class="actions detail-actions">
                        <button id="editSelected" class="btn btn-secondary" type="button">Редактировать</button>
                        <button id="collapseDetail" class="btn btn-secondary" type="button">Свернуть</button>
                        <button id="deleteSelected" class="btn btn-danger" type="button">Удалить</button>
                    </div>
                </div>
                <div>
                    <button id="detailPhotoBtn" class="detail-photo-btn hidden" type="button" title="Открыть фото">
                        <img id="detailPhoto" class="detail-photo" alt="Фото заказа">
                    </button>
                    <div id="detailPhotoThumbs" class="detail-thumbs"></div>
                </div>
            </div>
            <div id="detailEmpty" style="color: var(--muted); font-size: 14px;">Выберите строку в таблице, чтобы открыть карточку заказа.</div>
        </section>
    </div>

    <div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="photoModalLabel">Просмотр фото</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalPhoto" src="" class="img-fluid" alt="Фото заказа">
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editStorageModal" tabindex="-1" aria-labelledby="editStorageModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editStorageModalLabel">Редактировать заказ хранения</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <form method="post" enctype="multipart/form-data" id="editStorageForm">
                        <input type="hidden" name="order_id" id="edit_order_id">
                        <div class="mb-2">
                            <label for="edit_client_name" class="form-label">ФИО клиента *</label>
                            <input type="text" class="form-control" id="edit_client_name" name="client_name" required>
                        </div>
                        <div class="mb-2">
                            <label for="edit_phone" class="form-label">Телефон</label>
                            <input type="tel" class="form-control" id="edit_phone" name="phone">
                        </div>
                        <div class="mb-2">
                            <label for="edit_status" class="form-label">Статус *</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="На хранении">На хранении</option>
                                <option value="Выдано">Выдано</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label for="edit_storage_start_date" class="form-label">Дата начала хранения</label>
                            <input type="date" class="form-control" id="edit_storage_start_date" name="storage_start_date">
                        </div>
                        <div class="mb-2">
                            <label for="edit_storage_end_date" class="form-label">Дата окончания хранения</label>
                            <input type="date" class="form-control" id="edit_storage_end_date" name="storage_end_date">
                        </div>
                        <div class="mb-2">
                            <label for="edit_storage_location" class="form-label">Место хранения</label>
                            <input type="text" class="form-control" id="edit_storage_location" name="storage_location" placeholder="Например: Склад 1, Полка А-12">
                        </div>
                        <div class="mb-2">
                            <label for="edit_notes" class="form-label">Примечание</label>
                            <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Текущие фотографии</label>
                            <div class="photos-container" id="edit_photos_container"></div>
                            <small class="text-muted">Снимите галочку, чтобы удалить фото</small>
                        </div>
                        <div class="mb-2">
                            <label for="edit_new_photos" class="form-label">Добавить новые фотографии (до 4, только JPG/PNG/GIF, до 5МБ)</label>
                            <input type="file" class="form-control" id="edit_new_photos" name="new_photos[]" multiple accept="image/jpeg,image/png,image/gif">
                        </div>
                        <button type="submit" name="update_storage" class="btn">Сохранить</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteStorageModal" tabindex="-1" aria-labelledby="deleteStorageModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteStorageModalLabel">Подтверждение удаления</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <p>Вы уверены, что хотите удалить заказ <strong id="delete_order_number"></strong>?</p>
                    <form method="post" id="deleteStorageForm">
                        <input type="hidden" name="order_id" id="delete_order_id">
                        <button type="submit" name="delete_storage" class="btn btn-danger">Удалить</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const ordersData = <?php echo json_encode($orders_view, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const ordersMap = new Map(ordersData.map((order) => [Number(order.id), order]));
        let selectedOrderId = null;
        let detailPhotos = [];
        let detailPhotoIndex = 0;

        const refs = {
            queryInput: document.getElementById('queryInput'),
            clearQueryBtn: document.getElementById('clearQueryBtn'),
            filtersForm: document.getElementById('filtersForm'),
            filtersMeta: document.getElementById('filtersMeta'),
            liveSearchEmpty: document.getElementById('liveSearchEmpty'),
            photosInput: document.getElementById('photos'),
            cameraPhotosInput: document.getElementById('cameraPhotos'),
            preview: document.getElementById('preview'),
            previewThumbs: document.getElementById('previewThumbs'),
            clearStorageFormBtn: document.getElementById('clearStorageForm'),
            storageOrderForm: document.getElementById('storageOrderForm'),
            detail: document.getElementById('detail'),
            detailEmpty: document.getElementById('detailEmpty'),
            detailStatusBadge: document.getElementById('detailStatusBadge'),
            detailChips: document.getElementById('detailChips'),
            detailPhoneWrap: document.getElementById('detailPhoneWrap'),
            detailPhone: document.getElementById('detailPhone'),
            detailNotes: document.getElementById('detailNotes'),
            detailHistory: document.getElementById('detailHistory'),
            detailPhotoBtn: document.getElementById('detailPhotoBtn'),
            detailPhoto: document.getElementById('detailPhoto'),
            detailPhotoThumbs: document.getElementById('detailPhotoThumbs'),
            editSelected: document.getElementById('editSelected'),
            collapseDetail: document.getElementById('collapseDetail'),
            deleteSelected: document.getElementById('deleteSelected')
        };

        function syncTopNavSpacer() {
            const topNav = document.querySelector('.top-nav');
            const spacer = document.getElementById('topNavSpacer');
            if (!topNav || !spacer) return;
            if (window.innerWidth <= 992) {
                const height = Math.ceil(topNav.getBoundingClientRect().height) + 6;
                spacer.style.height = `${height}px`;
            } else {
                spacer.style.height = '0px';
            }
        }

        function syncDesktopNavSpacer() {
            const toggleBtn = document.querySelector('.nav-toggle');
            const spacer = document.getElementById('desktopNavSpacer');
            if (!toggleBtn || !spacer) return;
            if (window.innerWidth >= 993) {
                const rect = toggleBtn.getBoundingClientRect();
                spacer.style.height = `${Math.ceil(rect.bottom) + 8}px`;
            } else {
                spacer.style.height = '0px';
            }
        }

        function toggleNav() {
            const navMenu = document.getElementById('navMenu');
            if (navMenu) navMenu.classList.toggle('open');
        }

        document.addEventListener('click', function (event) {
            const navMenu = document.getElementById('navMenu');
            const navToggleBtn = document.querySelector('.nav-toggle');
            if (!navMenu || !navToggleBtn) return;
            if (navMenu.classList.contains('open') && !navMenu.contains(event.target) && !navToggleBtn.contains(event.target)) {
                navMenu.classList.remove('open');
            }
        });

        function updateClearQueryButton() {
            if (!refs.queryInput || !refs.clearQueryBtn) return;
            refs.clearQueryBtn.classList.toggle('hidden', !refs.queryInput.value.trim());
        }

        if (refs.queryInput && refs.clearQueryBtn) {
            refs.queryInput.addEventListener('input', updateClearQueryButton);
            refs.clearQueryBtn.addEventListener('click', () => {
                refs.queryInput.value = '';
                updateClearQueryButton();
                applyLiveSearch();
            });
        }

        let previewObjectUrls = [];
        const PHOTO_COMPRESS = {
            maxSide: 720,
            jpegQuality: 0.45,
            minSize: 250 * 1024
        };
        const PHOTO_COMPRESS_SKIP_TYPES = new Set(['image/gif', 'image/svg+xml']);

        function clearPreviewUrls() {
            previewObjectUrls.forEach((url) => URL.revokeObjectURL(url));
            previewObjectUrls = [];
        }

        function replaceFileExtension(name, ext) {
            const safeName = String(name || 'photo').trim() || 'photo';
            const dotIndex = safeName.lastIndexOf('.');
            const base = dotIndex > 0 ? safeName.slice(0, dotIndex) : safeName;
            return `${base}.${ext}`;
        }

        function shouldSkipCompression(file) {
            if (!file || !/^image\//.test(file.type)) return true;
            return PHOTO_COMPRESS_SKIP_TYPES.has(file.type);
        }

        function canvasToBlob(canvas, type, quality) {
            return new Promise((resolve) => {
                if (!canvas || typeof canvas.toBlob !== 'function') {
                    resolve(null);
                    return;
                }
                canvas.toBlob((blob) => resolve(blob), type, quality);
            });
        }

        async function loadImageSource(file) {
            if (!file) return null;

            if (window.createImageBitmap) {
                try {
                    const bitmap = await createImageBitmap(file);
                    return {
                        source: bitmap,
                        width: bitmap.width,
                        height: bitmap.height,
                        cleanup() {
                            bitmap.close();
                        }
                    };
                } catch (error) {
                    // fallback below
                }
            }

            return new Promise((resolve) => {
                const url = URL.createObjectURL(file);
                const img = new Image();
                img.onload = () => resolve({
                    source: img,
                    width: img.naturalWidth || img.width,
                    height: img.naturalHeight || img.height,
                    cleanup() {
                        URL.revokeObjectURL(url);
                    }
                });
                img.onerror = () => {
                    URL.revokeObjectURL(url);
                    resolve(null);
                };
                img.src = url;
            });
        }

        async function compressPhotoFile(file) {
            if (shouldSkipCompression(file)) return file;

            const loaded = await loadImageSource(file);
            if (!loaded || !loaded.width || !loaded.height) return file;

            const largestSide = Math.max(loaded.width, loaded.height);
            const scale = largestSide > PHOTO_COMPRESS.maxSide ? PHOTO_COMPRESS.maxSide / largestSide : 1;
            const targetWidth = Math.max(1, Math.round(loaded.width * scale));
            const targetHeight = Math.max(1, Math.round(loaded.height * scale));

            if (scale === 1 && file.size <= PHOTO_COMPRESS.minSize) {
                loaded.cleanup();
                return file;
            }

            const canvas = document.createElement('canvas');
            canvas.width = targetWidth;
            canvas.height = targetHeight;
            const ctx = canvas.getContext('2d', { alpha: file.type === 'image/png' });
            if (!ctx) {
                loaded.cleanup();
                return file;
            }

            ctx.imageSmoothingEnabled = true;
            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(loaded.source, 0, 0, targetWidth, targetHeight);
            loaded.cleanup();

            const outputType = file.type === 'image/png' ? 'image/png' : 'image/jpeg';
            const outputQuality = outputType === 'image/jpeg' ? PHOTO_COMPRESS.jpegQuality : undefined;
            const blob = await canvasToBlob(canvas, outputType, outputQuality);
            if (!blob) return file;

            if (scale === 1 && blob.size >= file.size * 0.98) return file;

            const ext = outputType === 'image/png' ? 'png' : 'jpg';
            const outputName = replaceFileExtension(file.name, ext);
            return new File([blob], outputName, { type: outputType, lastModified: Date.now() });
        }

        async function compressPhotoFiles(files) {
            const result = [];
            for (let i = 0; i < files.length; i += 1) {
                const file = files[i];
                try {
                    const compressed = await compressPhotoFile(file);
                    result.push(compressed);
                } catch (error) {
                    result.push(file);
                }
            }
            return result;
        }

        async function compressInputFiles(input) {
            if (!input || !input.files || !input.files.length) return;
            const originalFiles = Array.from(input.files);
            const compressedFiles = await compressPhotoFiles(originalFiles);
            const transfer = new DataTransfer();
            compressedFiles.forEach((file) => transfer.items.add(file));
            input.files = transfer.files;
        }

        function getCreateFiles() {
            const files = [];
            if (refs.photosInput && refs.photosInput.files) {
                files.push(...Array.from(refs.photosInput.files));
            }
            if (refs.cameraPhotosInput && refs.cameraPhotosInput.files) {
                files.push(...Array.from(refs.cameraPhotosInput.files));
            }
            return files.slice(0, 4);
        }

        function setPreviewImage(url) {
            if (!refs.preview) return;
            if (!url) {
                refs.preview.removeAttribute('src');
                refs.preview.classList.remove('visible');
                return;
            }
            refs.preview.src = url;
            refs.preview.classList.add('visible');
        }

        function renderCreatePreview() {
            if (!refs.previewThumbs) return;
            clearPreviewUrls();
            refs.previewThumbs.innerHTML = '';

            const files = getCreateFiles();
            if (!files.length) {
                setPreviewImage('');
                return;
            }

            files.forEach((file, index) => {
                const url = URL.createObjectURL(file);
                previewObjectUrls.push(url);

                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'thumb-btn';
                if (index === 0) button.classList.add('active');

                const img = document.createElement('img');
                img.src = url;
                img.className = 'thumb';
                img.alt = `Фото ${index + 1}`;

                button.appendChild(img);
                button.addEventListener('click', () => {
                    refs.previewThumbs.querySelectorAll('.thumb-btn').forEach((el) => el.classList.remove('active'));
                    button.classList.add('active');
                    setPreviewImage(url);
                });

                refs.previewThumbs.appendChild(button);
            });

            setPreviewImage(previewObjectUrls[0]);
        }

        if (refs.photosInput) {
            refs.photosInput.addEventListener('change', async () => {
                await compressInputFiles(refs.photosInput);
                renderCreatePreview();
            });
        }

        if (refs.cameraPhotosInput) {
            refs.cameraPhotosInput.addEventListener('change', async () => {
                await compressInputFiles(refs.cameraPhotosInput);
                renderCreatePreview();
            });
        }

        const editNewPhotosInput = document.getElementById('edit_new_photos');
        if (editNewPhotosInput) {
            editNewPhotosInput.addEventListener('change', async () => {
                await compressInputFiles(editNewPhotosInput);
            });
        }

        if (refs.clearStorageFormBtn && refs.storageOrderForm) {
            refs.clearStorageFormBtn.addEventListener('click', () => {
                refs.storageOrderForm.reset();
                renderCreatePreview();
            });
        }

        window.addEventListener('beforeunload', clearPreviewUrls);

        function viewPhoto(src) {
            const modalElement = document.getElementById('photoModal');
            const modalPhoto = document.getElementById('modalPhoto');
            if (!modalElement || !modalPhoto) return;

            modalPhoto.src = src;
            const modal = bootstrap.Modal.getOrCreateInstance(modalElement, {
                keyboard: true,
                focus: true
            });
            modal.show();
        }

        function getOrderById(id) {
            return ordersMap.get(Number(id)) || null;
        }

        let activeInlineRow = null;
        let activeMobileDetail = null;
        let activeMobileCard = null;

        function closeInlineDetail() {
            if (activeInlineRow && activeInlineRow.parentNode) {
                activeInlineRow.parentNode.removeChild(activeInlineRow);
            }
            activeInlineRow = null;

            if (activeMobileDetail && activeMobileDetail.parentNode) {
                activeMobileDetail.parentNode.removeChild(activeMobileDetail);
            }
            activeMobileDetail = null;
            activeMobileCard = null;

            selectedOrderId = null;
            document.querySelectorAll('.row-item.selected').forEach((row) => row.classList.remove('selected'));
            document.querySelectorAll('.mobile-card.selected').forEach((card) => card.classList.remove('selected'));
        }

        function buildInlineHistory(historyRows, query = '') {
            if (!Array.isArray(historyRows) || historyRows.length === 0) {
                return '<p class="history-item">Нет изменений</p>';
            }

            return historyRows.map((entry) => {
                const createdAt = highlightMatch(entry.created_at_fmt || '', query);
                const username = highlightMatch(entry.username || 'Неизвестный', query);
                const action = highlightMatch(entry.action || '', query);
                const details = highlightMultiline(entry.details || '', query);
                return `<p class="history-item"><strong>${createdAt}</strong> (${username}): ${action} - ${details}</p>`;
            }).join('');
        }

        function buildInlineDetailContent(order, query = '') {
            const photos = Array.isArray(order.photos) ? order.photos : [];
            const photosHtml = photos.length
                ? `<div class="inline-photos">${photos.map((photo, index) => {
                    const src = String(photo && photo.src ? photo.src : '');
                    return src
                        ? `<img class="photo-preview" src="${escapeHtml(src)}" alt="Фото ${index + 1}" data-inline-photo="${encodeURIComponent(src)}">`
                        : '';
                }).join('')}</div>`
                : '<div class="muted">Фото не добавлены</div>';

            const phoneValue = order.phone ? `<a href="tel:${escapeHtml(order.phone_href || order.phone)}" class="phone-link">${highlightMatch(order.phone, query)}</a>` : 'не указан';
            const notesValue = order.notes ? highlightMultiline(order.notes, query) : 'нет';

            return `
                <div class="inline-detail-grid">
                    <div class="inline-detail-card">
                        <h4 class="inline-detail-title">#${highlightMatch(order.inventory_number || '', query)} ${highlightMatch(order.client_name || '', query)}</h4>
                        <ul class="inline-detail-list">
                            <li><strong>Статус:</strong> <span class="order-status ${order.status === 'Выдано' ? 'status-issued' : 'status-active'}">${highlightMatch(order.status || 'не указан', query)}</span></li>
                            <li><strong>Телефон:</strong> ${phoneValue}</li>
                            <li><strong>Место хранения:</strong> ${highlightMatch(order.storage_location || 'не указано', query)}</li>
                            <li><strong>Хранится до:</strong> <span class="storage-until-line${order.storage_expired ? ' is-expired' : ''}">${highlightMatch(order.storage_until_fmt || 'не указана', query)}</span></li>
                            <li><strong>Дата начала:</strong> ${highlightMatch(order.storage_start_date_fmt || 'не указана', query)}</li>
                            <li><strong>Дата окончания:</strong> ${highlightMatch(order.storage_end_date_fmt || 'не указана', query)}</li>
                            <li><strong>Создан:</strong> ${highlightMatch(order.created_at_fmt || 'не указан', query)}</li>
                            <li><strong>Примечание:</strong> ${notesValue}</li>
                        </ul>
                        ${photosHtml}
                        <div class="inline-detail-actions">
                            <button type="button" class="btn btn-secondary" data-inline-edit="${order.id}">Редактировать</button>
                            <button type="button" class="btn btn-danger" data-inline-delete="${order.id}">Удалить</button>
                        </div>
                    </div>
                    <div class="inline-detail-card">
                        <h4 class="inline-detail-title">История изменений</h4>
                        <div class="inline-detail-history">
                            ${buildInlineHistory(order.history || [], query)}
                        </div>
                    </div>
                </div>
            `;
        }

        function bindInlineControls(container, order) {
            container.querySelectorAll('[data-inline-photo]').forEach((img) => {
                img.addEventListener('click', (event) => {
                    event.stopPropagation();
                    const src = decodeURIComponent(img.dataset.inlinePhoto || '');
                    if (src) viewPhoto(src);
                });
            });

            const editBtn = container.querySelector('[data-inline-edit]');
            if (editBtn) {
                editBtn.addEventListener('click', (event) => {
                    event.stopPropagation();
                    editStorageOrder(order.id);
                });
            }

            const deleteBtn = container.querySelector('[data-inline-delete]');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', (event) => {
                    event.stopPropagation();
                    confirmDeleteStorageOrder(order.id, order.inventory_number || '');
                });
            }
        }

        function openInlineDetail(order, sourceRow, query = '') {
            if (!order || !sourceRow) return;

            closeInlineDetail();
            selectedOrderId = Number(order.id);
            sourceRow.classList.add('selected');

            const tr = document.createElement('tr');
            tr.className = 'inline-detail-row';
            tr.innerHTML = `
                <td colspan="4" class="inline-detail-cell">
                    ${buildInlineDetailContent(order, query)}
                </td>
            `;

            sourceRow.insertAdjacentElement('afterend', tr);
            activeInlineRow = tr;
            bindInlineControls(tr, order);
        }

        function openInlineDetailInCard(order, card, query = '') {
            if (!order || !card) return;

            closeInlineDetail();
            selectedOrderId = Number(order.id);
            card.classList.add('selected');

            const container = document.createElement('div');
            container.className = 'mobile-inline-detail';
            container.innerHTML = buildInlineDetailContent(order, query);
            card.appendChild(container);

            activeMobileDetail = container;
            activeMobileCard = card;
            bindInlineControls(container, order);
        }

        function bindOrderSelection() {
            document.querySelectorAll('.row-item[data-id]').forEach((row) => {
                row.addEventListener('click', () => {
                    const orderId = Number(row.dataset.id);
                    const order = getOrderById(orderId);
                    if (!order) return;

                    if (selectedOrderId === orderId && activeInlineRow) {
                        closeInlineDetail();
                        return;
                    }

                    openInlineDetail(order, row, refs.queryInput ? refs.queryInput.value : '');
                });
            });

            document.querySelectorAll('.mobile-card[data-id]').forEach((card) => {
                card.addEventListener('click', () => {
                    const orderId = Number(card.dataset.id);
                    const order = getOrderById(orderId);
                    if (!order) return;

                    if (selectedOrderId === orderId && activeMobileDetail) {
                        closeInlineDetail();
                        return;
                    }

                    openInlineDetailInCard(order, card, refs.queryInput ? refs.queryInput.value : '');
                });
            });
        }

        function normalizeSearch(value) {
            return String(value || '').toLowerCase().trim();
        }

        function getOrderSearchText(order) {
            if (!order) return '';
            return normalizeSearch([
                order.inventory_number,
                order.client_name,
                order.status,
                order.phone,
                order.storage_location,
                order.storage_start_date_fmt,
                order.storage_end_date_fmt,
                order.storage_until_fmt,
                order.created_at_fmt,
                order.notes
            ].join(' '));
        }

        function renderOrderRow(row, order, query) {
            if (!row || !order) return;
            const cells = row.querySelectorAll('td');
            if (cells.length < 4) return;

            const phonePart = order.phone
                ? `Тел: <a href="tel:${escapeHtml(order.phone_href || order.phone)}" class="phone-link">${highlightMatch(order.phone, query)}</a> · `
                : '';
            row.classList.toggle('storage-expired', Boolean(order.storage_expired));
            cells[0].innerHTML = `
                <div class="order-line">#${highlightMatch(order.inventory_number || '', query)} ${highlightMatch(order.client_name || '', query)}</div>
                <div class="row-sub">
                    ${phonePart}
                    Создан: ${highlightMatch(order.created_at_fmt || '', query)}
                    <div class="storage-until-line${order.storage_expired ? ' is-expired' : ''}">Хранится до: ${highlightMatch(order.storage_until_fmt || 'не указана', query)}</div>
                </div>
            `;
            cells[1].innerHTML = `<span class="order-status ${escapeHtml(order.status_class || '')}">${highlightMatch(order.status || '', query)}</span>`;
            cells[2].innerHTML = highlightMatch(order.storage_location || 'не указано', query);
            cells[3].innerHTML = `${highlightMatch(order.storage_start_date_fmt || '', query)} - ${highlightMatch(order.storage_end_date_fmt || '', query)}`;
        }

        function renderMobileCard(card, order, query) {
            if (!card || !order) return;
            card.classList.toggle('storage-expired', Boolean(order.storage_expired));
            card.innerHTML = `
                <div class="mobile-row">
                    <div class="mobile-title">#${highlightMatch(order.inventory_number || '', query)} ${highlightMatch(order.client_name || '', query)}</div>
                    <span class="order-status ${escapeHtml(order.status_class || '')}">${highlightMatch(order.status || '', query)}</span>
                </div>
                <div class="mobile-meta">
                    <span class="storage-until-line${order.storage_expired ? ' is-expired' : ''}">Хранится до: ${highlightMatch(order.storage_until_fmt || 'не указана', query)}</span><br>
                    ${order.phone ? `Тел: <a href="tel:${escapeHtml(order.phone_href || order.phone)}" class="phone-link">${highlightMatch(order.phone, query)}</a><br>` : ''}
                    Место: ${highlightMatch(order.storage_location || 'не указано', query)}<br>
                    Период: ${highlightMatch(order.storage_start_date_fmt || '', query)} - ${highlightMatch(order.storage_end_date_fmt || '', query)}
                </div>
            `;
        }

        function applyLiveSearch() {
            const rawQuery = refs.queryInput ? refs.queryInput.value : '';
            const query = normalizeSearch(rawQuery);
            let visibleCount = 0;
            let firstMatchedRow = null;
            let firstMatchedCard = null;
            let firstMatchedOrder = null;

            closeInlineDetail();

            document.querySelectorAll('.row-item[data-id]').forEach((row) => {
                const orderId = Number(row.dataset.id);
                const order = getOrderById(orderId);
                const matched = !query || getOrderSearchText(order).includes(query);
                row.style.display = matched ? '' : 'none';
                renderOrderRow(row, order, matched ? rawQuery : '');
                if (matched) {
                    visibleCount += 1;
                    if (query && !firstMatchedRow) {
                        firstMatchedRow = row;
                        firstMatchedOrder = order;
                    }
                }
            });

            document.querySelectorAll('.mobile-card[data-id]').forEach((card) => {
                const orderId = Number(card.dataset.id);
                const order = getOrderById(orderId);
                const matched = !query || getOrderSearchText(order).includes(query);
                card.style.display = matched ? '' : 'none';
                renderMobileCard(card, order, matched ? rawQuery : '');
                if (matched && query && !firstMatchedCard) {
                    firstMatchedCard = card;
                }
            });

            if (query && firstMatchedOrder) {
                if (firstMatchedRow && window.matchMedia('(min-width: 769px)').matches) {
                    openInlineDetail(firstMatchedOrder, firstMatchedRow, rawQuery);
                } else if (firstMatchedCard) {
                    openInlineDetailInCard(firstMatchedOrder, firstMatchedCard, rawQuery);
                } else if (firstMatchedRow) {
                    openInlineDetail(firstMatchedOrder, firstMatchedRow, rawQuery);
                }
            }

            if (refs.filtersMeta) {
                refs.filtersMeta.textContent = `Найдено заказов: ${visibleCount} из ${ordersData.length}`;
            }
            if (refs.liveSearchEmpty) {
                refs.liveSearchEmpty.style.display = visibleCount === 0 ? 'block' : 'none';
            }
        }

        function editStorageOrder(id) {
            const order = getOrderById(id);
            if (!order) return;

            document.getElementById('edit_order_id').value = order.id;
            document.getElementById('edit_client_name').value = order.client_name || '';
            document.getElementById('edit_phone').value = order.phone || '';
            document.getElementById('edit_status').value = order.status || 'На хранении';
            document.getElementById('edit_storage_start_date').value = order.storage_start_date || '';
            document.getElementById('edit_storage_end_date').value = order.storage_end_date || '';
            document.getElementById('edit_storage_location').value = order.storage_location || '';
            document.getElementById('edit_notes').value = order.notes || '';

            const photosContainer = document.getElementById('edit_photos_container');
            photosContainer.innerHTML = '';

            const photos = Array.isArray(order.photos) ? order.photos : [];
            if (!photos.length) {
                const emptyLabel = document.createElement('span');
                emptyLabel.className = 'muted';
                emptyLabel.textContent = 'Нет сохраненных фото';
                photosContainer.appendChild(emptyLabel);
            } else {
                photos.forEach((photoEntry) => {
                    const rawPath = String(photoEntry && photoEntry.raw ? photoEntry.raw : '');
                    const displayPath = String(photoEntry && photoEntry.src ? photoEntry.src : rawPath);
                    if (!rawPath || !displayPath) return;

                    const wrapper = document.createElement('label');
                    wrapper.className = 'photo-item';

                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.className = 'photo-checkbox';
                    checkbox.name = 'existing_photos[]';
                    checkbox.value = rawPath;
                    checkbox.checked = true;

                    const image = document.createElement('img');
                    image.src = displayPath;
                    image.className = 'photo-preview';
                    image.alt = 'Фото заказа';
                    image.addEventListener('click', () => viewPhoto(displayPath));

                    wrapper.appendChild(checkbox);
                    wrapper.appendChild(image);
                    photosContainer.appendChild(wrapper);
                });
            }

            const modalElement = document.getElementById('editStorageModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalElement, {
                keyboard: true,
                focus: true
            });
            modal.show();
        }

        function confirmDeleteStorageOrder(id, inventoryNumber) {
            const modalElement = document.getElementById('deleteStorageModal');
            document.getElementById('delete_order_id').value = id;
            document.getElementById('delete_order_number').textContent = '#' + inventoryNumber;

            const modal = bootstrap.Modal.getOrCreateInstance(modalElement, {
                keyboard: true,
                focus: true
            });
            modal.show();
        }

        function escapeHtml(value) {
            return String(value || '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function escapeRegExp(value) {
            return String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        function highlightMatch(value, query) {
            const text = String(value || '');
            const cleanQuery = String(query || '').trim();
            if (!cleanQuery) return escapeHtml(text);

            const escaped = escapeHtml(text);
            const pattern = new RegExp(`(${escapeRegExp(cleanQuery)})`, 'giu');
            return escaped.replace(pattern, '<span class="search-highlight">$1</span>');
        }

        function highlightMultiline(value, query) {
            return highlightMatch(value, query).replace(/\n/g, '<br>');
        }

        document.addEventListener('DOMContentLoaded', function () {
            updateClearQueryButton();
            renderCreatePreview();
            syncTopNavSpacer();
            syncDesktopNavSpacer();
            bindOrderSelection();
            applyLiveSearch();

            if (refs.queryInput) {
                refs.queryInput.addEventListener('input', applyLiveSearch);
            }

            const bottomNav = document.querySelector('.bottom-nav');
            const activeNavItem = document.querySelector('.bottom-nav .nav-item.active');

            if (bottomNav && localStorage.getItem('bottomNavScrollLeft')) {
                bottomNav.scrollLeft = Number(localStorage.getItem('bottomNavScrollLeft'));
            }

            if (bottomNav) {
                bottomNav.addEventListener('scroll', function () {
                    localStorage.setItem('bottomNavScrollLeft', String(bottomNav.scrollLeft));
                });
            }

            if (activeNavItem && bottomNav) {
                const containerWidth = bottomNav.offsetWidth;
                const itemWidth = activeNavItem.offsetWidth;
                const itemLeft = activeNavItem.offsetLeft;
                bottomNav.scrollLeft = itemLeft - (containerWidth / 2) + (itemWidth / 2);
            }
        });

        window.addEventListener('resize', syncTopNavSpacer);
        window.addEventListener('resize', syncDesktopNavSpacer);
        window.addEventListener('orientationchange', syncTopNavSpacer);
        window.addEventListener('orientationchange', syncDesktopNavSpacer);
    </script>
</body>
</html>



