<?php

$coreBootstrap = __DIR__ . '/core/bootstrap.php';
$coreConfig = __DIR__ . '/core/config.php';
$coreDatabase = __DIR__ . '/core/database.php';
$coreAuth = __DIR__ . '/core/auth.php';
$coreLogging = __DIR__ . '/core/logging.php';

if (is_file($coreBootstrap)) {
    require_once $coreBootstrap;
} else {
    if (!ob_get_level()) {
        ob_start();
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    date_default_timezone_set('Europe/Moscow');
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}

if (is_file($coreConfig)) {
    require_once $coreConfig;
} else {
    if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
    if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: '');
    if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '');
    if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: '');
    if (!defined('PASSWORD_REMINDER_EMAIL')) define('PASSWORD_REMINDER_EMAIL', getenv('PASSWORD_REMINDER_EMAIL') ?: '');
    if (!defined('TELEGRAM_BOT_TOKEN')) define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: '');
    if (!defined('TELEGRAM_CHAT_ID')) define('TELEGRAM_CHAT_ID', getenv('TELEGRAM_CHAT_ID') ?: '');
}

if (is_file($coreDatabase)) {
    require_once $coreDatabase;
} elseif (!function_exists('app_create_db_connection')) {
    function app_create_db_connection(): mysqli
    {
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
}

if (is_file($coreAuth)) {
    require_once $coreAuth;
} else {
    if (!function_exists('isLoggedIn')) {
        function isLoggedIn(): bool
        {
            return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
        }
    }
    if (!function_exists('isAdminUser')) {
        function isAdminUser(): bool
        {
            return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
        }
    }
}

if (is_file($coreLogging)) {
    require_once $coreLogging;
} else {
    if (!function_exists('get_user_ip')) {
        function get_user_ip()
        {
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
            return $_SERVER['REMOTE_ADDR'] ?? '';
        }
    }
    if (!function_exists('log_change')) {
        function log_change($message)
        {
            $username = $_SESSION['username'] ?? 'guest';
            $timestamp = date('Y-m-d H:i:s');
            $log_entry = "[{$timestamp}] [{$username}] [" . get_user_ip() . "] [" . session_id() . "] [" . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A') . "] {$message}\n";
            file_put_contents('debug.log', $log_entry, FILE_APPEND);
        }
    }
    if (!function_exists('format_log_context')) {
        function format_log_context($context = []) { return ''; }
    }
    if (!function_exists('detect_post_action_name')) {
        function detect_post_action_name() { return $_SERVER['REQUEST_METHOD'] === 'POST' ? 'post_request' : ''; }
    }
    if (!function_exists('track_user_activity')) {
        function track_user_activity($area = '') { return; }
    }
}

// Подключаем все основные классы из одного файла
require_once __DIR__ . '/classes.php';
// Флаг для определения, доступны ли новые классы
define('NEW_CLASSES_AVAILABLE', class_exists('AppConstants') && class_exists('ImageService'));

// Регистрируем обработчик ошибок
if (class_exists('ErrorHandler')) {
    ErrorHandler::register();
}

$conn = app_create_db_connection();

// Функция для сжатия изображения (обертка для обратной совместимости)
// Использует ImageService
function compressImage($source, $destination, $maxWidth = null, $quality = null)
{
    // Используем значения по умолчанию, если не указаны
    $maxWidth = $maxWidth ?? 720;
    $quality = $quality ?? AppConstants::IMAGE_QUALITY;

    static $imageService = null;

    if ($imageService === null) {
        $imageService = new ImageService();
    }

    try {
        return $imageService->compressImage($source, $destination, $maxWidth, $quality);
    } catch (Exception $e) {
        error_log("Ошибка сжатия изображения: " . $e->getMessage());
        return false;
    }
}

// Функция для отправки уведомления в Telegram (обертка для обратной совместимости)
// Использует TelegramService
function sendTelegramNotification($message, $photos = [])
{
    static $telegramService = null;

    if ($telegramService === null) {
        $telegramService = new TelegramService();
    }

    return $telegramService->sendMessageWithPhotos($message, $photos);
}

function sendPasswordReminder($username)
{
    global $conn;

    $username = trim((string)$username);
    if ($username === '') {
        return [
            'success' => false,
            'message' => 'Укажите логин.'
        ];
    }

    $stmt = $conn->prepare("SELECT username, password FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
    if ($stmt === false) {
        error_log("Ошибка подготовки напоминания пароля: " . $conn->error);
        return [
            'success' => false,
            'message' => 'Не удалось обработать запрос.'
        ];
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$user) {
        return [
            'success' => false,
            'message' => 'Логин администратора не найден.'
        ];
    }

    $subject = 'Напоминание пароля для страницы расходов';
    $messageLines = [
        "Запрошено напоминание пароля для раздела \"Расходы\".",
        "",
        "Логин: " . $user['username'],
        "Пароль: " . $user['password'],
        "",
        "Дата: " . date('d.m.Y H:i:s')
    ];
    $message = implode("\r\n", $messageLines);

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    ];

    $sent = mail(PASSWORD_REMINDER_EMAIL, $encodedSubject, $message, implode("\r\n", $headers));

    if (!$sent) {
        error_log("Не удалось отправить письмо с напоминанием пароля для пользователя {$user['username']}");
        return [
            'success' => false,
            'message' => 'Не удалось отправить письмо. Проверьте настройки почты на сервере.'
        ];
    }

    log_change("Отправлено напоминание пароля для раздела расходов пользователю {$user['username']}");

    return [
        'success' => true,
        'message' => 'Пароль отправлен на почту ' . PASSWORD_REMINDER_EMAIL . '.'
    ];
}

// Функция для определения, является ли заказ удаленным (remote)
// Использует AppConstants
function isRemoteOrder($order)
{
    $location = $order['location'] ?? '';
    return AppConstants::isRemoteLocation($location);
}

// Функция для создания заказа (обертка для обратной совместимости)
// Использует OrderService или старую реализацию
function createOrder($data, $files, $queue_date = null)
{
    global $conn;


    // Если новые классы доступны, используем их
    if (defined('NEW_CLASSES_AVAILABLE') && NEW_CLASSES_AVAILABLE && class_exists('OrderService')) {
        $orderService = new OrderService($conn);
        return $orderService->create($data, $files, $queue_date);
    }

    // Старая реализация (fallback)
    // ... (остальной код функции без изменений)
    $client_name = htmlspecialchars(trim($data['client_name']));
    $license_plate = htmlspecialchars(trim($data['license_plate'] ?? ''));
    $phone = htmlspecialchars(trim($data['phone'] ?? ''));
    $color = htmlspecialchars(trim($data['color'] ?? ''));
    $location = htmlspecialchars(trim($data['location'] ?? ''));
    $notes = htmlspecialchars(trim($data['notes'] ?? ''));
    $status = 'in_progress';

    $price = 0;
    $manual_price = str_replace(',', '.', trim((string)($data['price'] ?? '')));
    if ($manual_price !== '' && is_numeric($manual_price)) {
        $price = max(0, (float)$manual_price);
    }

    $photos = [];
    if (!empty($files['photos']['name'][0])) {
        $upload_dir = 'Uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        for ($i = 0; $i < min(4, count($files['photos']['name'])); $i++) {
            if ($files['photos']['size'][$i] > 0) {
                $file_name = uniqid() . '_' . basename($files['photos']['name'][$i]);
                $temp_file = $files['photos']['tmp_name'][$i];
                $target_file = $upload_dir . $file_name;

                if (compressImage($temp_file, $target_file)) {
                    $photos[] = $target_file;
                }
            }
        }
    }

    $photos_str = implode(',', $photos);
    $queue_date = ($queue_date === null || empty($queue_date)) ? null : $queue_date;

    $stmt = $conn->prepare("INSERT INTO orders (client_name, license_plate, phone, color, location, price, notes, status, photos, queue_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt === false) {
        error_log("Ошибка подготовки запроса: " . $conn->error);
        return false;
    }

    $stmt->bind_param("sssssdssss", $client_name, $license_plate, $phone, $color, $location, $price, $notes, $status, $photos_str, $queue_date);
    $result = $stmt->execute();

    if ($result) {
        $order_id = $conn->insert_id;
        // ... (код уведомления в Telegram)
    } else {
        error_log("Ошибка выполнения запроса: " . $stmt->error);
    }

    $stmt->close();
    return $result ? $order_id : false;
}

// Функция для обновления заказа (обертка для обратной совместимости)
// Использует OrderService или старую реализацию
function updateOrder($order_id, $data, $files)
{
    global $conn;

    if (defined('NEW_CLASSES_AVAILABLE') && NEW_CLASSES_AVAILABLE && class_exists('OrderService')) {
        $orderService = new OrderService($conn);
        $result = $orderService->update($order_id, $data, $files);
        if ($result) {
            $cache = new CacheService();
            $cache->clear('order_' . $order_id);
        }
        return $result;
    }

    // Старая реализация очень большая, поэтому просто возвращаем ошибку
    // Рекомендуется загрузить файлы классов на сервер
    error_log("OrderService не доступен для updateOrder. Загрузите файлы классов на сервер!");
    return false;
}

// Функция для получения заказов по статусу с сортировкой (обертка для обратной совместимости)
// Использует OrderService или старую реализацию
function getOrdersByStatus($status, $sort = 'default', $sort_value = '', $limit = 20, $offset = 0)
{
    global $conn;

    if (defined('NEW_CLASSES_AVAILABLE') && NEW_CLASSES_AVAILABLE && class_exists('OrderService')) {
        $orderService = new OrderService($conn);
        return $orderService->findByStatus($status, $sort, $sort_value, $limit, $offset);
    }

    $params = [];
    $types = "";
    $query = "SELECT * FROM orders WHERE status = ?";
    $params[] = $status;
    $types .= "s";

    if ($sort === 'queue' && $sort_value) {
        $query .= " AND queue_date = ?";
        $params[] = $sort_value;
        $types .= "s";
    } elseif ($sort === 'location' && $sort_value) {
        $query .= " AND location = ?";
        $params[] = $sort_value;
        $types .= "s";
    }

    if ($sort === 'queue') {
        $query .= " ORDER BY queue_date ASC";
    } else {
        $query .= " ORDER BY created_at DESC";
    }

    $query .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($query);

    if ($stmt) {
        $bind_names = [$types];
        for ($i = 0; $i < count($params); $i++) {
            $bind_name = 'param' . $i;
            $$bind_name = $params[$i];
            $bind_names[] = &$$bind_name;
        }
        call_user_func_array(array($stmt,'bind_param'), $bind_names);

        $stmt->execute();
        $result = $stmt->get_result();
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        $stmt->close();
        return $orders;
    }
    return [];
}

// Новая функция для подсчета заказов
function countOrdersByStatus($status, $sort = 'default', $sort_value = '')
{
    global $conn;

    $params = [];
    $types = "";
    $query = "SELECT COUNT(*) as total FROM orders WHERE status = ?";
    $params[] = $status;
    $types .= "s";

    if ($sort === 'queue' && $sort_value) {
        $query .= " AND queue_date = ?";
        $params[] = $sort_value;
        $types .= "s";
    } elseif ($sort === 'location' && $sort_value) {
        $query .= " AND location = ?";
        $params[] = $sort_value;
        $types .= "s";
    }

    $stmt = $conn->prepare($query);

    if ($stmt) {
        if (!empty($params)) {
            $bind_names = [$types];
            for ($i = 0; $i < count($params); $i++) {
                $bind_name = 'param' . $i;
                $$bind_name = $params[$i];
                $bind_names[] = &$$bind_name;
            }
            call_user_func_array(array($stmt,'bind_param'), $bind_names);
        }

        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['total'] ?? 0;
    }

    return 0;
}

// Функция для получения заказа по ID (обертка для обратной совместимости)
// Использует OrderService или старую реализацию
function getOrderById($order_id)
{
    global $conn;

    if (defined('NEW_CLASSES_AVAILABLE') && NEW_CLASSES_AVAILABLE && class_exists('OrderService')) {
        $orderService = new OrderService($conn);
        return $orderService->findById($order_id);
    }

    // Старая реализация с кешированием
    $cache = new CacheService();
    $cacheKey = 'order_' . $order_id;

    return $cache->remember($cacheKey, 3600, function () use ($conn, $order_id) {
        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();
        $stmt->close();
        return $order;
    });
}

// Функция для обновления статуса заказа (обертка для обратной совместимости)
// Использует OrderService или старую реализацию
function updateOrderStatus($order_id, $new_status)
{
    global $conn;

    if (defined('NEW_CLASSES_AVAILABLE') && NEW_CLASSES_AVAILABLE && class_exists('OrderService')) {
        $orderService = new OrderService($conn);
        return $orderService->updateStatus($order_id, $new_status);
    }

    // Старая реализация
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $order_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Функция для поиска заказов (обертка для обратной совместимости)
// Использует OrderService или старую реализацию
function searchOrders($query)
{
    global $conn;

    if (defined('NEW_CLASSES_AVAILABLE') && NEW_CLASSES_AVAILABLE && class_exists('OrderService')) {
        $orderService = new OrderService($conn);
        return $orderService->search($query);
    }

    $query = trim((string)$query);
    if ($query === '') {
        return [];
    }

    $is_id_only_search = strpos($query, '#') === 0;
    if ($is_id_only_search) {
        $id_query = trim(ltrim($query, '#'));
        if ($id_query === '') {
            return [];
        }
        $id_search_term = "%$id_query%";
        $stmt = $conn->prepare("SELECT * FROM orders WHERE CAST(id AS CHAR) LIKE ? ORDER BY created_at DESC");
        $stmt->bind_param("s", $id_search_term);
        $stmt->execute();
        $result = $stmt->get_result();
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        $stmt->close();
        return $orders;
    }

    $search_term = "%$query%";
    $id_query = $query;
    $id_search_term = "%$id_query%";
    $stmt = $conn->prepare(
        "SELECT * FROM orders
         WHERE CAST(id AS CHAR) LIKE ?
            OR client_name LIKE ?
            OR license_plate LIKE ?
            OR phone LIKE ?
            OR color LIKE ?
            OR location LIKE ?
            OR notes LIKE ?
            OR CAST(price AS CHAR) LIKE ?
            OR status LIKE ?
            OR CAST(queue_date AS CHAR) LIKE ?
            OR DATE_FORMAT(queue_date, '%d.%m.%Y') LIKE ?
            OR CAST(created_at AS CHAR) LIKE ?
            OR DATE_FORMAT(created_at, '%d.%m.%Y %H:%i') LIKE ?
            OR DATE_FORMAT(created_at, '%d.%m.%Y') LIKE ?
            OR photos LIKE ?
         ORDER BY created_at DESC"
    );
    $stmt->bind_param(
        "sssssssssssssss",
        $id_search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term
    );

    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
    return $orders;
}

// Функция для добавления пользователя
function addUser($username, $password, $role)
{
    global $conn;

    $username = trim((string)$username);
    $password = (string)$password;
    $role = ($role === 'admin') ? 'admin' : 'user';
    $GLOBALS['last_user_operation_error'] = '';

    if ($username === '' || $password === '') {
        $GLOBALS['last_user_operation_error'] = 'Заполните логин и пароль.';
        return false;
    }

    $stmt = $conn->prepare("SELECT id, is_deleted FROM users WHERE username = ? LIMIT 1");
    if (!$stmt) {
        $GLOBALS['last_user_operation_error'] = 'Ошибка подготовки проверки пользователя.';
        error_log('addUser prepare select failed: ' . $conn->error);
        return false;
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $existingId = (int)($existing['id'] ?? 0);
        if ((int)($existing['is_deleted'] ?? 0) !== 1) {
            $GLOBALS['last_user_operation_error'] = 'Пользователь с таким логином уже существует.';
            return false;
        }

        $stmt = $conn->prepare("UPDATE users SET password = ?, role = ?, is_deleted = 0, is_blocked = 0, deleted_at = NULL WHERE id = ?");
        if (!$stmt) {
            $GLOBALS['last_user_operation_error'] = 'Ошибка подготовки восстановления пользователя.';
            error_log('addUser prepare restore failed: ' . $conn->error);
            return false;
        }
        $stmt->bind_param("ssi", $password, $role, $existingId);
        $result = $stmt->execute();
        if (!$result) {
            $GLOBALS['last_user_operation_error'] = 'Не удалось восстановить пользователя.';
            error_log('addUser restore failed: ' . $stmt->error);
        }
        $stmt->close();

        if ($result) {
            $cache = new CacheService();
            $cache->clear('users_list');
        }

        return $result;
    }

    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    if (!$stmt) {
        $GLOBALS['last_user_operation_error'] = 'Ошибка подготовки добавления пользователя.';
        error_log('addUser prepare insert failed: ' . $conn->error);
        return false;
    }
    $stmt->bind_param("sss", $username, $password, $role);
    $result = $stmt->execute();
    if (!$result) {
        $GLOBALS['last_user_operation_error'] = 'Не удалось добавить пользователя.';
        error_log('addUser insert failed: ' . $stmt->error);
    }
    $stmt->close();

    if ($result) {
        $cache = new CacheService();
        $cache->clear('users_list');
    }

    return $result;
}

function isProtectedDefaultAdminUsername(string $username): bool
{
    return mb_strtolower(trim($username), 'UTF-8') === 'admin';
}

function isProtectedDefaultAdminUserId(int $user_id): bool
{
    global $conn;

    if ($user_id <= 0) {
        return false;
    }

    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return isProtectedDefaultAdminUsername((string)($row['username'] ?? ''));
}

function ensureProtectedDefaultAdmin(): bool
{
    global $conn;

    $username = 'admin';
    $password = 'admin123';
    $role = 'admin';

    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $userId = (int)$row['id'];
        $stmt = $conn->prepare("UPDATE users SET password = ?, role = ?, is_deleted = 0, is_blocked = 0, deleted_at = NULL WHERE id = ?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("ssi", $password, $role, $userId);
        $result = $stmt->execute();
        $stmt->close();
        if ($result) {
            persistUsersCacheClear();
        }
        return $result;
    }

    $stmt = $conn->prepare("INSERT INTO users (username, password, role, is_blocked, is_deleted, deleted_at) VALUES (?, ?, ?, 0, 0, NULL)");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("sss", $username, $password, $role);
    $result = $stmt->execute();
    $stmt->close();
    if ($result) {
        persistUsersCacheClear();
    }
    return $result;
}

// Функция для получения пользователей
function getUsers()
{
    global $conn;
    $cache = new CacheService();

    return $cache->remember('users_list', 3600, function () use ($conn) {
        $result = $conn->query("SELECT * FROM users WHERE is_deleted = 0 ORDER BY username ASC");
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        return $users;
    });
}

function ensureRashodyUsersTable(): bool
{
    return true;
}

function getRashodyUsers(): array
{
    global $conn;
    $result = $conn->query("SELECT id, username, created_at, role, is_blocked FROM users WHERE is_deleted = 0 ORDER BY username ASC");
    if (!$result) {
        return [];
    }
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    return $users;
}

function addRashodyUser($username, $password): bool
{
    global $conn;
    $role = 'user';
    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("sss", $username, $password, $role);
    $result = $stmt->execute();
    $stmt->close();
    if ($result) {
        $cache = new CacheService();
        $cache->clear('users_list');
    }
    return $result;
}

function getUserPasswordById(int $user_id): string
{
    global $conn;
    if ($user_id <= 0) {
        return '';
    }
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return '';
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (string)($row['password'] ?? '');
}

function persistUsersCacheClear(): void
{
    $cache = new CacheService();
    $cache->clear('users_list');
}

function isUserBlockedById(int $user_id): bool
{
    global $conn;
    if ($user_id <= 0) {
        return false;
    }
    $stmt = $conn->prepare("SELECT is_blocked, is_deleted FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['is_blocked'] ?? 0) === 1 || (int)($row['is_deleted'] ?? 0) === 1;
}

function setUserBlockedState(int $user_id, bool $blocked): bool
{
    global $conn;
    if ($user_id <= 0) {
        return false;
    }
    if (isProtectedDefaultAdminUserId($user_id)) {
        return false;
    }
    $currentUserId = (int)($_SESSION['user_id'] ?? 0);
    if ($currentUserId > 0 && $currentUserId === $user_id && $blocked) {
        return false;
    }
    $value = $blocked ? 1 : 0;
    $stmt = $conn->prepare("UPDATE users SET is_blocked = ? WHERE id = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("ii", $value, $user_id);
    $result = $stmt->execute();
    $stmt->close();
    if ($result) {
        persistUsersCacheClear();
    }
    return $result;
}

function updateRashodyUser($user_id, $username, $password): bool
{
    return updateUserRecord((int)$user_id, (string)$username, (string)$password, null);
}

function deleteRashodyUser($user_id): bool
{
    return deleteUserRecord((int)$user_id, true);
}

// Функция для обновления пользователя
function updateUser($user_id, $username, $password, $role)
{
    return updateUserRecord((int)$user_id, (string)$username, (string)$password, (string)$role);
}

function updateUserRecord(int $user_id, string $username, string $password, ?string $role = null): bool
{
    global $conn;

    $username = trim($username);
    if ($user_id <= 0 || $username === '') {
        return false;
    }
    if (isProtectedDefaultAdminUserId($user_id)) {
        return false;
    }

    if ($password === '') {
        $password = getUserPasswordById($user_id);
    }
    if ($password === '') {
        return false;
    }

    if ($role !== null) {
        $role = ($role === 'admin') ? 'admin' : 'user';
        $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("sssi", $username, $password, $role, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("ssi", $username, $password, $user_id);
    }

    $result = $stmt->execute();
    $stmt->close();
    if ($result) {
        persistUsersCacheClear();
    }
    return $result;
}

// Функция для удаления пользователя
function deleteUser($user_id)
{
    return deleteUserRecord((int)$user_id, true);
}

function deleteUserRecord(int $user_id, bool $removeRashodyData = false): bool
{
    global $conn;

    if ($user_id <= 0) {
        return false;
    }

    $currentUserId = (int)($_SESSION['user_id'] ?? 0);
    if ($currentUserId > 0 && $currentUserId === $user_id) {
        return false;
    }
    if (isProtectedDefaultAdminUserId($user_id)) {
        return false;
    }

    $conn->begin_transaction();
    try {
        $username = getUsernameById($user_id);
        if ($username === '') {
            $conn->rollback();
            return false;
        }

        if (rashodyBaseTableExists($conn)) {
            ensureRashodyOwnerSnapshotColumn($conn);
            $stmtSnapshot = $conn->prepare("UPDATE rashody_expenses SET owner_username = ? WHERE rashody_user_id = ? AND (owner_username IS NULL OR owner_username = '')");
            if (!$stmtSnapshot) {
                throw new RuntimeException($conn->error);
            }
            $stmtSnapshot->bind_param("si", $username, $user_id);
            if (!$stmtSnapshot->execute()) {
                $stmtSnapshot->close();
                throw new RuntimeException($conn->error);
            }
            $stmtSnapshot->close();
        }

        $stmt = $conn->prepare("UPDATE users SET is_deleted = 1, is_blocked = 1, deleted_at = NOW() WHERE id = ?");
        if (!$stmt) {
            throw new RuntimeException($conn->error);
        }
        $stmt->bind_param("i", $user_id);
        $result = $stmt->execute();
        $stmt->close();

        if (!$result) {
            throw new RuntimeException($conn->error);
        }

        $conn->commit();
        persistUsersCacheClear();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('deleteUserRecord failed for user ' . $user_id . ': ' . $e->getMessage());
        return false;
    }
}

function getUsernameById(int $user_id): string
{
    global $conn;
    if ($user_id <= 0) {
        return '';
    }
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return '';
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (string)($row['username'] ?? '');
}

function rashodyBaseTableExists(mysqli $conn): bool
{
    $result = $conn->query("SHOW TABLES LIKE 'rashody_expenses'");
    return $result && $result->num_rows > 0;
}

function ensureRashodyOwnerSnapshotColumn(mysqli $conn): bool
{
    return addColumnIfNotExists('rashody_expenses', 'owner_username', 'VARCHAR(50) DEFAULT NULL');
}

// Функция для удаления заказа (обертка для обратной совместимости)
// Использует OrderService или старую реализацию
function deleteOrder($order_id)
{
    global $conn;

    if (defined('NEW_CLASSES_AVAILABLE') && NEW_CLASSES_AVAILABLE && class_exists('OrderService')) {
        $orderService = new OrderService($conn);
        $result = $orderService->delete($order_id);
        if ($result) {
            $cache = new CacheService();
            $cache->clear('order_' . $order_id);
        }
        return $result;
    }

    // Старая реализация с транзакцией
    $transactionManager = new TransactionManager($conn);

    try {
        $result = $transactionManager->transaction(function ($conn) use ($order_id) {
            $order = getOrderById($order_id);
            if (!$order) {
                return false;
            }

            // 1. Удаление связанных записей о зарплате
            $comments_pattern = "%в заказе #$order_id%";
            $stmt = $conn->prepare("DELETE FROM salary_records WHERE comments LIKE ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("s", $comments_pattern);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $stmt->close();

            // 2. Удаление фотографий
            $photos = !empty($order['photos']) ? explode(',', $order['photos']) : [];
            foreach ($photos as $photo) {
                if (file_exists($photo)) {
                    if (!unlink($photo)) {
                        // Можно бросить исключение, если удаление файла критично
                        error_log("Не удалось удалить файл: $photo");
                    }
                }
            }

            // 3. Удаление самого заказа
            $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("i", $order_id);
            $result = $stmt->execute();
            if (!$result) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $stmt->close();

            return $result;
        });

        if ($result) {
            $cache = new CacheService();
            $cache->clear('order_' . $order_id);
        }
        return $result;
    } catch (Exception $e) {
        error_log("Ошибка при удалении заказа #$order_id: " . $e->getMessage());
        return false;
    }
}

// Функция для добавления записи о заработной плате
function addSalaryRecord($data, $files)
{
    global $conn;

    $executor = htmlspecialchars(trim($data['executor']));
    $execution_date = $data['execution_date'];
    $amount = floatval($data['amount'] ?? 0);
    $comments = htmlspecialchars(trim($data['comments'] ?? ''));
    $status = 'waiting_payment';

    $photos = [];
    if (!empty($files['photos']['name'][0])) {
        $upload_dir = 'Uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        for ($i = 0; $i < count($files['photos']['name']); $i++) {
            if ($files['photos']['size'][$i] > 0) {
                $file_name = uniqid() . '_' . basename($files['photos']['name'][$i]);
                $temp_file = $files['photos']['tmp_name'][$i];
                $target_file = $upload_dir . $file_name;

                if (compressImage($temp_file, $target_file)) {
                    $photos[] = $target_file;
                }
            }
        }
    }

    $photos_str = implode(',', $photos);

    $stmt = $conn->prepare("INSERT INTO salary_records (executor, execution_date, amount, comments, status, photos) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdsss", $executor, $execution_date, $amount, $comments, $status, $photos_str);
    $result = $stmt->execute();

    if ($result) {
        $record_id = $conn->insert_id;
        $message = "<b>Новая запись З/П #$record_id</b>\n" .
                   "Исполнитель: $executor\n" .
                   "Дата выполнения: " . date('d.m.Y', strtotime($execution_date)) . "\n" .
                   "Стоимость: " . number_format($amount, 2) . " руб.\n" .
                   "Комментарий: " . ($comments ?: 'не указан') . "\n" .
                   "Статус: Жду оплаты\n" .
                   "Создано: " . date('d.m.Y H:i');
        sendTelegramNotification($message, $photos);
    }

    $stmt->close();
    return $result;
}

// Функция для обновления записи о заработной плате
function updateSalaryRecord($record_id, $data, $files)
{
    global $conn;

    $executor = htmlspecialchars(trim($data['executor']));
    $execution_date = $data['execution_date'];
    $amount = floatval($data['amount'] ?? 0);
    $comments = htmlspecialchars(trim($data['comments'] ?? ''));
    $status = $data['status'] ?? 'waiting_payment';

    $existing_photos = $data['existing_photos'] ?? [];
    $photos_to_keep = [];
    $photos_to_delete = [];
    $record = getSalaryRecordById($record_id);
    $old_photos = !empty($record['photos']) ? explode(',', $record['photos']) : [];

    if (!empty($existing_photos)) {
        foreach ($old_photos as $photo) {
            if (in_array($photo, $existing_photos)) {
                $photos_to_keep[] = $photo;
            } else {
                if (file_exists($photo)) {
                    $photos_to_delete[] = $photo;
                }
            }
        }
    } else {
        foreach ($old_photos as $photo) {
            if (file_exists($photo)) {
                $photos_to_delete[] = $photo;
            }
        }
        $photos_to_keep = [];
    }

    $new_photos = [];
    if (!empty($files['photos']['name'][0])) {
        $upload_dir = 'Uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $available_slots = max(0, 4 - count($photos_to_keep));
        for ($i = 0; $i < min($available_slots, count($files['photos']['name'])); $i++) {
            if ($files['photos']['size'][$i] > 0) {
                $file_name = uniqid() . '_' . basename($files['photos']['name'][$i]);
                $temp_file = $files['photos']['tmp_name'][$i];
                $target_file = $upload_dir . $file_name;

                if (compressImage($temp_file, $target_file)) {
                    $new_photos[] = $target_file;
                }
            }
        }
    }

    $all_photos = array_merge($photos_to_keep, $new_photos);
    $photos_str = implode(',', $all_photos);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE salary_records SET executor = ?, execution_date = ?, amount = ?, comments = ?, status = ?, photos = ? WHERE id = ?");
        if ($stmt === false) {
            throw new Exception("Ошибка подготовки запроса обновления З/П: " . $conn->error);
        }
        $stmt->bind_param("ssdsssi", $executor, $execution_date, $amount, $comments, $status, $photos_str, $record_id);
        $result = $stmt->execute();
        $stmt->close();

        if (!$result) {
            throw new Exception("Ошибка обновления записи З/П");
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        foreach ($new_photos as $photo) {
            if (file_exists($photo)) {
                unlink($photo);
            }
        }
        error_log("Ошибка updateSalaryRecord #$record_id: " . $e->getMessage());
        return false;
    }

    foreach ($photos_to_delete as $photo) {
        if (file_exists($photo)) {
            unlink($photo);
        }
    }

    if ($result) {
        $message = "<b>Запись З/П #$record_id обновлена</b>\n" .
                   "Исполнитель: $executor\n" .
                   "Дата выполнения: " . date('d.m.Y', strtotime($execution_date)) . "\n" .
                   "Стоимость: " . number_format($amount, 2) . " руб.\n" .
                   "Комментарий: " . ($comments ?: 'не указан') . "\n" .
                   "Статус: " . ($status === 'waiting_payment' ? 'Жду оплаты' : 'Выплачено') . "\n" .
                   "Обновлено: " . date('d.m.Y H:i');
        sendTelegramNotification($message, $new_photos);
    }

    return $result;
}

// Функция для получения записей о заработной плате
function getSalaryRecords($status = null)
{
    global $conn;

    $username = $_SESSION['username'];
    $is_admin = $_SESSION['user_role'] === 'admin';

    if ($status) {
        if ($is_admin) {
            $stmt = $conn->prepare("SELECT * FROM salary_records WHERE status = ? ORDER BY created_at DESC");
            $stmt->bind_param("s", $status);
        } else {
            $stmt = $conn->prepare("SELECT * FROM salary_records WHERE status = ? AND executor = ? ORDER BY created_at DESC");
            $stmt->bind_param("ss", $status, $username);
        }
    } else {
        if ($is_admin) {
            $stmt = $conn->prepare("SELECT * FROM salary_records ORDER BY created_at DESC");
        } else {
            $stmt = $conn->prepare("SELECT * FROM salary_records WHERE executor = ? ORDER BY created_at DESC");
            $stmt->bind_param("s", $username);
        }
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }

    $stmt->close();
    return $records;
}

// Функция для обновления статуса записи о заработной плате
function updateSalaryRecordStatus($record_id, $new_status)
{
    global $conn;

    $stmt = $conn->prepare("UPDATE salary_records SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $record_id);
    $result = $stmt->execute();

    if ($result) {
        $record = getSalaryRecordById($record_id);
        $message = "<b>Статус записи З/П #$record_id обновлён</b>\n" .
                   "Исполнитель: " . $record['executor'] . "\n" .
                   "Дата выполнения: " . date('d.m.Y', strtotime($record['execution_date'])) . "\n" .
                   "Стоимость: " . number_format($record['amount'], 2) . " руб.\n" .
                   "Новый статус: " . ($new_status === 'waiting_payment' ? 'Жду оплаты' : 'Выплачено') . "\n" .
                   "Обновлено: " . date('d.m.Y H:i');
        sendTelegramNotification($message);
    }

    $stmt->close();
    return $result;
}

// Функция для получения записи о заработной плате по ID
function getSalaryRecordById($record_id)
{
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM salary_records WHERE id = ?");
    $stmt->bind_param("i", $record_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $record = $result->fetch_assoc();
    $stmt->close();

    return $record;
}

// Функция для проверки и добавления столбца, если он отсутствует
function addColumnIfNotExists($table, $column, $definition)
{
    global $conn;

    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($result->num_rows == 0) {
        $sql = "ALTER TABLE `$table` ADD `$column` $definition";
        if ($conn->query($sql) === false) {
            error_log("Ошибка добавления столбца $column в таблицу $table: " . $conn->error);
            return false;
        }
        return true;
    }
    return true; // Столбец уже существует
}

function addIndexIfNotExists($table, $indexName, $columnsDefinition)
{
    global $conn;
    $tableEscaped = $conn->real_escape_string($table);
    $indexEscaped = $conn->real_escape_string($indexName);

    $checkQuery = "SHOW INDEX FROM `$tableEscaped` WHERE Key_name = '$indexEscaped'";
    $indexExists = $conn->query($checkQuery);
    if ($indexExists === false) {
        error_log("Ошибка проверки индекса $indexName в таблице $table: " . $conn->error);
        return false;
    }
    if ($indexExists->num_rows > 0) {
        return true;
    }

    $alterQuery = "ALTER TABLE `$tableEscaped` ADD INDEX `$indexEscaped` $columnsDefinition";
    if (!$conn->query($alterQuery)) {
        error_log("Ошибка добавления индекса $indexName в таблицу $table: " . $conn->error);
        return false;
    }
    return true;
}

// Создаем таблицы и обновляем структуру, если необходимо
function createTables()
{
    global $conn;

    $sql_orders = "CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_name VARCHAR(255) NOT NULL,
        license_plate VARCHAR(20),
        phone VARCHAR(20),
        services TEXT,
        color VARCHAR(50),
        location VARCHAR(50),
        price DECIMAL(10, 2),
        notes TEXT,
        status ENUM('new', 'in_progress', 'completed', 'archive') DEFAULT 'in_progress',
        photos TEXT,
        queue_date DATE DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        final_amount DECIMAL(10, 2) DEFAULT NULL
    )";

    $sql_users = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'user') DEFAULT 'user',
        is_blocked TINYINT(1) NOT NULL DEFAULT 0,
        is_deleted TINYINT(1) NOT NULL DEFAULT 0,
        deleted_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    $sql_tire_notes = "CREATE TABLE IF NOT EXISTS tire_notes (
        note_date DATE PRIMARY KEY,
        note TEXT
    )";

    $sql_expert_notes = "CREATE TABLE IF NOT EXISTS expert_notes (
        note_date DATE PRIMARY KEY,
        note TEXT
    )";

    $sql_storage_orders = "CREATE TABLE IF NOT EXISTS storage_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_name VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        notes TEXT,
        status ENUM('На хранении', 'Выдано') DEFAULT 'На хранении',
        inventory_number VARCHAR(10) UNIQUE,
        storage_start_date DATE,
        storage_end_date DATE,
        storage_location VARCHAR(255),
        photos TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    $sql_storage_history = "CREATE TABLE IF NOT EXISTS storage_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        storage_order_id INT NOT NULL,
        user_id INT,
        action VARCHAR(50) NOT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (storage_order_id) REFERENCES storage_orders(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )";

    $sql_salary_records = "CREATE TABLE IF NOT EXISTS salary_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        executor VARCHAR(255) NOT NULL,
        execution_date DATE NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        comments TEXT,
        status ENUM('waiting_payment', 'paid') DEFAULT 'waiting_payment',
        photos TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    $sql_expenses = "CREATE TABLE IF NOT EXISTS expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(255) NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        date DATE NOT NULL,
        location VARCHAR(50),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    $sql_equipment = "CREATE TABLE IF NOT EXISTS equipment (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name TEXT NOT NULL,
        serial_number VARCHAR(100),
        order_number VARCHAR(50) UNIQUE,
        notes TEXT,
        status VARCHAR(50) DEFAULT 'В работе',
        inventory_number VARCHAR(50) UNIQUE,
        purchase_date DATE,
        next_service_date DATE,
        location VARCHAR(255),
        total_cost DECIMAL(10, 2) DEFAULT 0.00,
        photos TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    $sql_equipment_history = "CREATE TABLE IF NOT EXISTS equipment_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        equipment_id INT NOT NULL,
        user_id INT,
        action VARCHAR(50) NOT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )";

    $sql_equipment_payments = "CREATE TABLE IF NOT EXISTS equipment_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        equipment_id INT NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        payment_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE
    )";

    $sql_tires_inventory = "CREATE TABLE IF NOT EXISTS tires_inventory (
        id VARCHAR(80) PRIMARY KEY,
        item_data LONGTEXT NOT NULL,
        photos TEXT,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )";

    $sql_zakaz_operations = "CREATE TABLE IF NOT EXISTS zakaz_operations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        document_number VARCHAR(50) DEFAULT NULL,
        client_name VARCHAR(255) NOT NULL,
        phone VARCHAR(30) DEFAULT NULL,
        car_brand VARCHAR(120) DEFAULT NULL,
        car_model VARCHAR(120) DEFAULT NULL,
        plate_number VARCHAR(50) DEFAULT NULL,
        car_type VARCHAR(80) DEFAULT NULL,
        radius VARCHAR(20) DEFAULT NULL,
        qty_mode VARCHAR(30) DEFAULT NULL,
        wheel_count INT DEFAULT NULL,
        items_json LONGTEXT NOT NULL,
        subtotal DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        discount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        paid_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        debt_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        note TEXT DEFAULT NULL,
        storage_enabled TINYINT(1) NOT NULL DEFAULT 0,
        storage_type VARCHAR(30) DEFAULT NULL,
        storage_json LONGTEXT DEFAULT NULL,
        storage_order_id INT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        created_by_username VARCHAR(50) DEFAULT NULL,
        updated_by INT DEFAULT NULL,
        updated_by_username VARCHAR(50) DEFAULT NULL,
        edit_history LONGTEXT DEFAULT NULL,
        deleted_at DATETIME DEFAULT NULL,
        deleted_by INT DEFAULT NULL,
        deleted_by_username VARCHAR(50) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (storage_order_id) REFERENCES storage_orders(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL
    )";

    $sql_zakaz_settings = "CREATE TABLE IF NOT EXISTS zakaz_settings (
        setting_key VARCHAR(80) PRIMARY KEY,
        setting_value LONGTEXT NOT NULL,
        updated_by INT DEFAULT NULL,
        updated_by_username VARCHAR(50) DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
    )";

    $sql_zakaz_shifts = "CREATE TABLE IF NOT EXISTS zakaz_shifts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        shift_date DATE NOT NULL,
        opened_at DATETIME NOT NULL,
        closed_at DATETIME DEFAULT NULL,
        status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
        tire_workers_json LONGTEXT NOT NULL,
        argon_workers_json LONGTEXT NOT NULL,
        admin_name VARCHAR(255) DEFAULT NULL,
        opened_by INT DEFAULT NULL,
        opened_by_username VARCHAR(50) DEFAULT NULL,
        closed_by INT DEFAULT NULL,
        closed_by_username VARCHAR(50) DEFAULT NULL,
        closed_auto TINYINT(1) NOT NULL DEFAULT 0,
        snapshot_json LONGTEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (opened_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
    )";

    $success = $conn->query($sql_orders) &&
               $conn->query($sql_users) &&
               $conn->query($sql_tire_notes) &&
               $conn->query($sql_expert_notes) &&
               $conn->query($sql_storage_orders) &&
               $conn->query($sql_storage_history) &&
               $conn->query($sql_salary_records) &&
               $conn->query($sql_expenses) &&
               $conn->query($sql_equipment) &&
               $conn->query($sql_equipment_history) &&
               $conn->query($sql_equipment_payments) &&
               $conn->query($sql_tires_inventory) &&
               $conn->query($sql_zakaz_operations) &&
               $conn->query($sql_zakaz_settings) &&
               $conn->query($sql_zakaz_shifts);

    if (!$success) {
        error_log("Ошибка создания таблиц: " . $conn->error);
        return false;
    }

    // Проверяем и добавляем столбец amount в таблицу salary_records, если он отсутствует
    $success = addColumnIfNotExists('salary_records', 'amount', 'DECIMAL(10, 2) NOT NULL DEFAULT 0.00');

    // Проверяем и добавляем столбец location в таблицу orders, если он отсутствует
    $success = $success && addColumnIfNotExists('orders', 'location', 'VARCHAR(50) DEFAULT NULL');

    // Проверяем и добавляем столбец location в таблицу expenses, если он отсутствует
    $success = $success && addColumnIfNotExists('expenses', 'location', 'VARCHAR(50) DEFAULT NULL');

    // Проверяем и добавляем столбец order_number в таблицу equipment, если он отсутствует
    $success = $success && addColumnIfNotExists('equipment', 'order_number', 'VARCHAR(50) UNIQUE DEFAULT NULL');

    // Проверяем и добавляем столбец total_cost в таблицу equipment, если он отсутствует
    $success = $success && addColumnIfNotExists('equipment', 'total_cost', 'DECIMAL(10, 2) DEFAULT 0.00');

    // Статус блокировки учетной записи.
    $success = $success && addColumnIfNotExists('users', 'is_blocked', 'TINYINT(1) NOT NULL DEFAULT 0');
    $success = $success && addColumnIfNotExists('users', 'is_deleted', 'TINYINT(1) NOT NULL DEFAULT 0');
    $success = $success && addColumnIfNotExists('users', 'deleted_at', 'DATETIME DEFAULT NULL');
    if (rashodyBaseTableExists($conn)) {
        $success = $success && ensureRashodyOwnerSnapshotColumn($conn);
    }

    // Индексы для быстрых переходов между разделами заказов.
    // Особенно важны для in_progress/completed/archive с сортировкой по created_at.
    $success = $success && addIndexIfNotExists('orders', 'idx_orders_status_created_at', '(status, created_at)');
    $success = $success && addIndexIfNotExists('orders', 'idx_orders_status_queue_date', '(status, queue_date)');
    $success = $success && addIndexIfNotExists('orders', 'idx_orders_status_location_created_at', '(status, location, created_at)');
    $success = $success && addIndexIfNotExists('zakaz_operations', 'idx_zakaz_operations_created_at', '(created_at)');
    $success = $success && addIndexIfNotExists('zakaz_operations', 'idx_zakaz_operations_created_by', '(created_by, created_at)');
    $success = $success && addColumnIfNotExists('zakaz_operations', 'storage_order_id', 'INT DEFAULT NULL');
    $success = $success && addColumnIfNotExists('zakaz_operations', 'paid_amount', 'DECIMAL(10, 2) NOT NULL DEFAULT 0.00');
    $success = $success && addColumnIfNotExists('zakaz_operations', 'debt_amount', 'DECIMAL(10, 2) NOT NULL DEFAULT 0.00');
    $success = $success && addColumnIfNotExists('zakaz_operations', 'note', 'TEXT DEFAULT NULL');
    $success = $success && addColumnIfNotExists('zakaz_operations', 'updated_by', 'INT DEFAULT NULL');
    $success = $success && addColumnIfNotExists('zakaz_operations', 'updated_by_username', 'VARCHAR(50) DEFAULT NULL');
    $success = $success && addColumnIfNotExists('zakaz_operations', 'edit_history', 'LONGTEXT DEFAULT NULL');
    $success = $success && addColumnIfNotExists('zakaz_operations', 'deleted_at', 'DATETIME DEFAULT NULL');
    $success = $success && addColumnIfNotExists('zakaz_operations', 'deleted_by', 'INT DEFAULT NULL');
    $success = $success && addColumnIfNotExists('zakaz_operations', 'deleted_by_username', 'VARCHAR(50) DEFAULT NULL');
    $success = $success && addColumnIfNotExists('zakaz_operations', 'updated_at', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    $success = $success && addIndexIfNotExists('zakaz_operations', 'idx_zakaz_operations_storage_order_id', '(storage_order_id)');
    $success = $success && addIndexIfNotExists('zakaz_operations', 'idx_zakaz_operations_deleted_created_at', '(deleted_at, created_at)');
    $success = $success && addIndexIfNotExists('zakaz_shifts', 'idx_zakaz_shifts_status_date', '(status, shift_date)');
    $success = $success && addIndexIfNotExists('zakaz_shifts', 'idx_zakaz_shifts_opened_at', '(opened_at)');

    if (!$success) {
        error_log("Ошибка обновления структуры таблиц");
        return false;
    }

    return true;
}

function ensureSchemaReady(): bool
{
    global $conn;

    $cacheDir = __DIR__ . '/cache';
    $schemaVersion = '2026-05-23-schema-cache-v2';
    $marker = $cacheDir . '/schema_ready_' . $schemaVersion . '.flag';
    $sessionKey = 'schema_ready_' . $schemaVersion;

    if (is_file($marker) || (($_SESSION[$sessionKey] ?? false) === true)) {
        return true;
    }

    $isWarehousePage = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
        && (($_GET['section'] ?? '') === 'warehouse' || preg_match('#/warehouse/?$#', (string)($_SERVER['REQUEST_URI'] ?? '')));
    if ($isWarehousePage) {
        $usersCheck = $conn->query("SHOW TABLES LIKE 'users'");
        if ($usersCheck && $usersCheck->num_rows > 0) {
            $_SESSION[$sessionKey] = true;
            return true;
        }
    }

    $ok = createTables();
    if (!$ok) {
        return false;
    }

    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }

    if (is_dir($cacheDir) && is_writable($cacheDir)) {
        foreach (glob($cacheDir . '/schema_ready_*.flag') ?: [] as $oldMarker) {
            @unlink($oldMarker);
        }
        $written = @file_put_contents($marker, date('c'));
        if ($written === false) {
            error_log("Не удалось записать маркер готовности схемы: {$marker}");
        }
    } else {
        error_log("Каталог cache недоступен для записи, проверки схемы будут выполняться повторно: {$cacheDir}");
    }

    $_SESSION[$sessionKey] = true;

    return true;
}

ensureSchemaReady();

// Служебный администратор для аварийного входа. Он скрыт в настройках и защищен от удаления.
ensureProtectedDefaultAdmin();

$coreNavigation = __DIR__ . '/core/navigation.php';
if (is_file($coreNavigation)) {
    require_once $coreNavigation;
} else {
    $menu_items = [
        'new_order' => ['label' => 'Новый', 'icon' => 'fa-plus-circle', 'url' => 'index.php?section=new_order'],
        'in_progress' => ['label' => 'В работе', 'icon' => 'fa-tasks', 'url' => 'index.php?section=in_progress'],
        'completed' => ['label' => 'Готово', 'icon' => 'fa-check-circle', 'url' => 'index.php?section=completed'],
        'history' => ['label' => 'История', 'icon' => 'fa-history', 'url' => 'order_history.php'],
        'archive' => ['label' => 'Архив', 'icon' => 'fa-archive', 'url' => 'index.php?section=archive'],
        'calendar' => ['label' => 'Календарь', 'icon' => 'fa-calendar-alt', 'url' => 'calendar.php', 'class' => 'd-none d-lg-flex'],
        'zakaz' => ['label' => 'Шиномонтаж', 'short_label' => 'Шины', 'icon' => 'fa-file-invoice', 'url' => 'shinomontazh'],
        'warehouse' => ['label' => 'Склад', 'icon' => 'fa-warehouse', 'url' => 'index.php?section=warehouse'],
        'storage' => ['label' => 'Хранение', 'icon' => 'fa-box', 'url' => 'storage.php'],
        'rashody' => ['label' => 'Расходы', 'icon' => 'fa-coins', 'url' => 'Расходы.html?v=2026050102'],
        'settings' => ['label' => 'Настройки', 'icon' => 'fa-sliders-h', 'url' => 'index.php?section=settings', 'admin_only' => true],
    ];

    if (!function_exists('getVisibleMenuItems')) {
        function getVisibleMenuItems(): array
        {
            global $menu_items;
            $visible = [];
            foreach ($menu_items as $key => $item) {
                if (!empty($item['admin_only']) && !isAdminUser()) continue;
                $visible[$key] = $item;
            }
            return $visible;
        }
    }

    if (!function_exists('renderUnifiedNavigation')) {
        function renderUnifiedNavigation(string $activeKey = '', array $options = []): void
        {
            foreach (getVisibleMenuItems() as $key => $item) {
                $class = $key === $activeKey ? ' class="nav-item active"' : ' class="nav-item"';
                echo '<a href="' . htmlspecialchars((string)$item['url']) . '"' . $class . '>' . htmlspecialchars((string)$item['label']) . '</a>';
            }
        }
    }
}

// Функции обработки запросов и работы с заказами
function handleAdminActions($conn)
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_user'])) {
            $new_username = trim((string)($_POST['new_username'] ?? ''));
            $new_password = (string)($_POST['new_password'] ?? '');
            $new_role = (($_POST['new_role'] ?? 'user') === 'admin') ? 'admin' : 'user';

            if ($new_username !== '' && $new_password !== '' && addUser($new_username, $new_password, $new_role)) {
                log_change("Добавил нового пользователя: {$new_username}");
                $GLOBALS['settings_success_message'] = 'Пользователь добавлен.';
            } else {
                $GLOBALS['settings_error_message'] = $GLOBALS['last_user_operation_error'] ?: 'Не удалось добавить пользователя.';
            }
        } elseif (isset($_POST['edit_user'])) {
            $user_id = (int)($_POST['user_id'] ?? 0);
            $username = trim((string)($_POST['edit_username'] ?? ''));
            $password = (string)($_POST['edit_password'] ?? '');
            $role = (($_POST['edit_role'] ?? 'user') === 'admin') ? 'admin' : 'user';

            if ($user_id > 0 && $username !== '' && $password !== '' && updateUser($user_id, $username, $password, $role)) {
                log_change("Отредактировал пользователя ID: {$user_id}");
                $GLOBALS['settings_success_message'] = 'Данные пользователя сохранены.';
            } elseif ($user_id > 0 && $username !== '' && $password === '' && updateUser($user_id, $username, '', $role)) {
                log_change("Отредактировал пользователя ID: {$user_id}");
                $GLOBALS['settings_success_message'] = 'Данные пользователя сохранены.';
            } else {
                $GLOBALS['settings_error_message'] = 'Не удалось сохранить изменения пользователя.';
            }
        } elseif (isset($_POST['delete_user'])) {
            $user_id = (int)($_POST['user_id'] ?? 0);

            if ($user_id > 0 && deleteUser($user_id)) {
                log_change("Удалил пользователя ID: {$user_id}");
                $GLOBALS['settings_success_message'] = 'Пользователь удалён.';
            } else {
                $GLOBALS['settings_error_message'] = 'Не удалось удалить пользователя. Текущего администратора удалить нельзя.';
            }
        } elseif (isset($_POST['add_rashody_user'])) {
            $new_username = trim($_POST['new_rashody_username'] ?? '');
            $new_password = $_POST['new_rashody_password'] ?? '';
            if ($new_username !== '' && $new_password !== '') {
                if (addRashodyUser($new_username, $new_password)) {
                    log_change("Добавил пользователя расходов: {$new_username}");
                    $GLOBALS['settings_success_message'] = 'Пользователь расходов добавлен.';
                } else {
                    $GLOBALS['settings_error_message'] = 'Не удалось добавить пользователя расходов.';
                }
            }
        } elseif (isset($_POST['edit_rashody_user'])) {
            $user_id = (int)($_POST['rashody_user_id'] ?? 0);
            $username = trim((string)($_POST['edit_rashody_username'] ?? ''));
            $password = (string)($_POST['edit_rashody_password'] ?? '');
            if ($user_id > 0 && $username !== '') {
                if (updateRashodyUser($user_id, $username, $password)) {
                    log_change("Отредактировал пользователя расходов ID: {$user_id}");
                    $GLOBALS['settings_success_message'] = 'Пользователь расходов сохранён.';
                } else {
                    $GLOBALS['settings_error_message'] = 'Не удалось сохранить пользователя расходов.';
                }
            }
        } elseif (isset($_POST['delete_rashody_user'])) {
            $user_id = (int)($_POST['rashody_user_id'] ?? 0);
            if ($user_id > 0) {
                if (deleteRashodyUser($user_id)) {
                    log_change("Удалил пользователя расходов ID: {$user_id}");
                    $GLOBALS['settings_success_message'] = 'Пользователь расходов удалён.';
                } else {
                    $GLOBALS['settings_error_message'] = 'Не удалось удалить пользователя расходов. Текущего администратора удалить нельзя.';
                }
            }
        } elseif (isset($_POST['toggle_user_block'])) {
            $user_id = (int)($_POST['user_id'] ?? 0);
            $blocked = (int)($_POST['blocked_state'] ?? 0) === 1;
            if ($user_id > 0 && setUserBlockedState($user_id, $blocked)) {
                log_change(($blocked ? "Заблокировал" : "Разблокировал") . " пользователя ID: {$user_id}");
                $GLOBALS['settings_success_message'] = $blocked ? 'Пользователь заблокирован.' : 'Пользователь разблокирован.';
            } else {
                $GLOBALS['settings_error_message'] = 'Не удалось изменить статус пользователя.';
            }
        } elseif (isset($_POST['delete_order'])) {
            $order_id = $_POST['order_id'];

            // Используем функцию deleteOrder, которая также удаляет связанные записи о зарплате
            if (deleteOrder($order_id)) {
                log_change("Удалил заказ #{$order_id}");
            }

            header("Location: ?section=archive");
            exit;
        }
    }
}

function handlePostRequests($conn, $section, $queue_date)
{
    global $archive_error;

    if ($section === 'new_order' && isset($_POST['create_order'])) {
        $queue_date = $_POST['queue_date'] ?? null;
        if ($order_id = createOrder($_POST, $_FILES, $queue_date)) {
            log_change("Создал новый заказ #{$order_id}");
            unset($_SESSION['form_data'], $_SESSION['form_errors']);
            header("Location: ?section=in_progress");
            exit;
        } else {
            // Если создание не удалось из-за ошибок валидации
            header("Location: ?section=new_order");
            exit;
        }
    } elseif (isset($_POST['archive_order'])) {
        $order_id = $_POST['order_id'];
        $note = $_POST['note'] ?? '';

        if (!empty($note)) {
            $_POST['note'] = $note;
            addNoteAndPhotos($_POST, $_FILES);
        }

        log_change("Переместил в архив заказ #{$order_id}");
        $stmt = $conn->prepare("UPDATE orders SET status = 'archive' WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $stmt->close();

        // Если Telegram отключен, не тратим время на чтение большого заказа/фото и сборку текста.
        if (defined('AppConstants::TELEGRAM_ENABLED') && AppConstants::TELEGRAM_ENABLED) {
            $order = getOrderById($order_id);
            $photos = !empty($order['photos']) ? explode(',', $order['photos']) : [];
            $photos_count = count(array_filter($photos, function ($p) {
                return is_string($p) && trim($p) !== '';
            }));
            $message = "<b>Заказ #$order_id перемещён в архив</b>\n" .
                       "Клиент: " . $order['client_name'] . "\n" .
                       "Госномер: " . ($order['license_plate'] ?: 'не указан') . "\n" .
                       "Телефон: " . ($order['phone'] ?: 'не указан') . "\n" .
                       "Цвет: " . ($order['color'] ?: 'не указан') . "\n" .
                       "Стоимость: " . ($order['price'] ? number_format($order['price'], 2) . ' руб.' : 'не указана') . "\n" .
                       "Примечание: " . ($order['notes'] ?: 'нет') . "\n" .
                       "Фото в заказе: " . $photos_count . "\n" .
                       "Создан: " . date('d.m.Y H:i', strtotime($order['created_at'])) . "\n" .
                       "Обновлён: " . date('d.m.Y H:i');
            sendTelegramNotification($message, []);
        }

        header("Location: ?section=completed");
        exit;
    }
}

function addNoteAndPhotos($data, $files)
{
    global $conn;

    $order_id = $data['order_id'];
    $note = $data['note'] ?? '';
    $current_section = $data['section'];
    $username = $_SESSION['username'];

    $status_map = [
        'in_progress' => 'В работе',
        'completed' => 'Готово',
        'archive' => 'Архив'
    ];
    $human_readable_status = $status_map[$current_section] ?? $current_section;

    $photos = [];
    if (!empty($files['additional_photos']['name'][0])) {
        $upload_dir = 'Uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        for ($i = 0; $i < count($files['additional_photos']['name']); $i++) {
            if ($files['additional_photos']['size'][$i] > 0) {
                $file_name = uniqid() . '_' . basename($files['additional_photos']['name'][$i]);
                $temp_file = $files['additional_photos']['tmp_name'][$i];
                $target_file = $upload_dir . $file_name;

                if (compressImage($temp_file, $target_file)) {
                    $photos[] = $target_file;
                }
            }
        }
    }

    $photos_str = implode(',', $photos);
    $stmt = $conn->prepare("SELECT notes, photos FROM orders WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $existing_notes = $row['notes'] ? explode("\n", $row['notes']) : [];
    $existing_photos = $row['photos'] ? explode(',', $row['photos']) : [];

    if (!empty($note) || !empty($photos)) {
        $new_note = "[" . date('d.m.Y H:i') . " - $human_readable_status - $username] ";
        if (!empty($note)) {
            $new_note .= $note;
        }
        if (!empty($photos)) {
            $new_note .= (!empty($note) ? " | " : "") . "Добавлено " . count($photos) . " фото";
        }
        $existing_notes[] = $new_note;
    }

    $updated_notes = implode("\n", $existing_notes);
    $updated_photos = array_merge($existing_photos, $photos);
    $updated_photos_str = implode(',', $updated_photos);

    $stmt = $conn->prepare("UPDATE orders SET notes = ?, photos = ? WHERE id = ?");
    $stmt->bind_param("ssi", $updated_notes, $updated_photos_str, $order_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Нормализует путь к фото, учитывая возможный регистр папки uploads.
 */
function resolvePhotoPath($photo)
{
    $photo = trim((string)$photo);
    if ($photo === '') {
        return '';
    }

    $photo = str_replace('\\', '/', $photo);
    if (file_exists($photo)) {
        return $photo;
    }

    if (strpos($photo, 'Uploads/') === 0) {
        $candidate = 'uploads/' . substr($photo, 8);
        if (file_exists($candidate)) {
            return $candidate;
        }
    } elseif (strpos($photo, 'uploads/') === 0) {
        $candidate = 'Uploads/' . substr($photo, 8);
        if (file_exists($candidate)) {
            return $candidate;
        }
    }

    return $photo;
}

function rashodyTableExists(mysqli $conn): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $resultExpenses = $conn->query("SHOW TABLES LIKE 'rashody_expenses'");
    if (!$resultExpenses || $resultExpenses->num_rows === 0) {
        $cached = false;
        return $cached;
    }
    $resultOrderId = $conn->query("SHOW COLUMNS FROM `rashody_expenses` LIKE 'order_id'");
    $cached = $resultOrderId && $resultOrderId->num_rows > 0;
    return $cached;
}

function getRashodyExpensesByOrders(array $orderIds): array
{
    global $conn;
    if (empty($orderIds)) {
        return [];
    }
    if (!rashodyTableExists($conn)) {
        return [];
    }

    $ids = array_values(array_filter(array_map(function ($id) {
        $id = trim((string)$id);
        return $id !== '' ? $id : null;
    }, $orderIds)));

    if (empty($ids)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('s', count($ids));
    $sql = "SELECT e.order_id, e.client_uid, e.entry_type, e.entry_category, e.amount, e.entry_date, e.note, e.rashody_user_id, COALESCE(NULLIF(e.owner_username, ''), u.username) AS username
        FROM rashody_expenses e
        LEFT JOIN users u ON u.id = e.rashody_user_id
        WHERE e.order_id IN ($placeholders)
        ORDER BY e.entry_date DESC, e.created_at DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $bind = [$types];
    foreach ($ids as $i => $value) {
        $bind[] = &$ids[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
    $stmt->execute();
    $res = $stmt->get_result();
    $map = [];
    while ($row = $res->fetch_assoc()) {
        $orderId = (string)($row['order_id'] ?? '');
        if ($orderId === '') {
            continue;
        }
        if (!isset($map[$orderId])) {
            $map[$orderId] = [];
        }
        $map[$orderId][] = [
            'id' => (string)($row['client_uid'] ?? ''),
            'type' => ($row['entry_type'] ?? '') === 'income' ? 'income' : 'expense',
            'category' => (string)($row['entry_category'] ?? ''),
            'amount' => (float)($row['amount'] ?? 0),
            'date' => (string)($row['entry_date'] ?? ''),
            'note' => (string)($row['note'] ?? ''),
            'owner' => (string)($row['username'] ?? '')
        ];
    }
    $stmt->close();
    return $map;
}
