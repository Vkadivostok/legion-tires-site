<?php

// Buffer output so header redirects work even if a template echoes early.
if (!ob_get_level()) {
    ob_start();
}

require_once 'db.php';
require_once 'views.php';

// Проверка авторизации
if (!isset($_SESSION['logged_in']) || !isset($_SESSION['user_role'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['login'])) {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
            $stmt->bind_param("ss", $username, $password);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user && ((int)($user['is_blocked'] ?? 0) === 1 || (int)($user['is_deleted'] ?? 0) === 1)) {
                $login_error = "Этот пользователь заблокирован";
                $_SESSION['username'] = $username;
                log_change("Попытка входа заблокированного пользователя");
                unset($_SESSION['username']);
            } elseif ($user) {
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['username'] = $user['username'];
                log_change("Успешный вход");
                if (ob_get_level()) {
                    ob_end_clean();
                }
                header("Location: shinomontazh");
                exit;
            } else {
                $login_error = "Неверный логин или пароль";
                $_SESSION['username'] = $username; // Temporarily set username for logging
                log_change("Неудачная попытка входа");
                unset($_SESSION['username']);
            }
        }
    }
}

if (isset($_SESSION['logged_in'], $_SESSION['user_id']) && $_SESSION['logged_in'] === true && isUserBlockedById((int)$_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    $login_error = "Этот пользователь заблокирован";
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    renderLoginPage($login_error ?? null, $login_success ?? null);
    if (ob_get_level()) {
        ob_end_flush();
    }
    exit;
}

// Обработка выхода
if (isset($_GET['logout'])) {
    log_change("Выход из системы");
    session_destroy();
    if (ob_get_level()) {
        ob_end_clean();
    }
    header("Location: index.php");
    exit;
}

// Обработка действий администратора
if ($_SESSION['user_role'] === 'admin') {
    handleAdminActions($conn);
}

if (!isset($_GET['section']) && !isset($_GET['action']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (ob_get_level()) {
        ob_end_clean();
    }
    header("Location: shinomontazh");
    exit;
}

// Обработка заказов
$section = $_GET['section'] ?? 'new_order';
$queue_date = $_GET['queue_date'] ?? null;
$form_data = isset($_GET['form_data']) ? json_decode(base64_decode($_GET['form_data']), true) : null;
track_user_activity('index');

// Серверные редиректы на отдельные страницы разделов.
if ($section === 'storage') {
    if (ob_get_level()) {
        ob_end_clean();
    }
    header("Location: storage.php");
    exit;
}
if ($section === 'tires') {
    if (ob_get_level()) {
        ob_end_clean();
    }
    header("Location: tires.php");
    exit;
}
if ($section === 'zp') {
    if (ob_get_level()) {
        ob_end_clean();
    }
    header("Location: zp.php");
    exit;
}
if ($section === 'reports' && ($_SESSION['user_role'] ?? '') === 'admin') {
    if (ob_get_level()) {
        ob_end_clean();
    }
    header("Location: reports.php");
    exit;
}

// Обработка AJAX запроса для получения значений сортировки
if (isset($_GET['action']) && $_GET['action'] === 'get_sort_values') {
    // Проверяем авторизацию
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $sort_type = $_GET['sort_type'] ?? '';
    $current_section = $_GET['section'] ?? '';

    // Логируем запрос для отладки
    error_log("AJAX запрос: sort_type=$sort_type, section=$current_section");

    header('Content-Type: application/json');

    if ($sort_type === 'queue') {
        // Получаем уникальные даты очереди для текущей секции
        $status_map = [
            'in_progress' => 'in_progress',
            'completed' => 'completed',
            'archive' => 'archive'
        ];
        $status = $status_map[$current_section] ?? 'in_progress';

        error_log("Ищем даты для статуса: $status");

        $stmt = $conn->prepare("SELECT DISTINCT queue_date FROM orders WHERE status = ? AND queue_date IS NOT NULL AND queue_date != '0000-00-00' ORDER BY queue_date ASC");
        $stmt->bind_param("s", $status);
        $stmt->execute();
        $result = $stmt->get_result();

        $values = [];
        while ($row = $result->fetch_assoc()) {
            $values[] = [
                'value' => $row['queue_date'],
                'label' => date('d.m.Y', strtotime($row['queue_date']))
            ];
        }
        $stmt->close();

        error_log("Найдено дат: " . count($values));
        echo json_encode(['values' => $values]);
    } elseif ($sort_type === 'location') {
        // Получаем уникальные локации для текущей секции
        $status_map = [
            'in_progress' => 'in_progress',
            'completed' => 'completed',
            'archive' => 'archive'
        ];
        $status = $status_map[$current_section] ?? 'in_progress';

        error_log("Ищем локации для статуса: $status");

        $stmt = $conn->prepare("SELECT DISTINCT location FROM orders WHERE status = ? AND location IS NOT NULL AND location != '' ORDER BY location ASC");
        $stmt->bind_param("s", $status);
        $stmt->execute();
        $result = $stmt->get_result();

        $values = [];
        while ($row = $result->fetch_assoc()) {
            $values[] = [
                'value' => $row['location'],
                'label' => $row['location']
            ];
        }
        $stmt->close();

        error_log("Найдено локаций: " . count($values));
        echo json_encode(['values' => $values]);
    } else {
        echo json_encode(['values' => []]);
    }
    exit;
}

// Обработка AJAX запроса для живого поиска заказов
if (isset($_GET['action']) && $_GET['action'] === 'search_orders') {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(401);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Unauthorized';
        exit;
    }

    header('Content-Type: text/html; charset=UTF-8');
    $query = trim($_GET['query'] ?? '');
    $is_id_only_query = strpos($query, '#') === 0;

    if ($query === '') {
        echo '<p class="text-muted">Введите текст для поиска</p>';
        exit;
    }

    $orders = searchOrders($query);

    $allowed_statuses = ['new', 'in_progress', 'completed', 'archive'];
    $status_filter_raw = trim((string)($_GET['statuses'] ?? ''));
    if ($status_filter_raw !== '') {
        $requested_statuses = array_filter(array_map('trim', explode(',', $status_filter_raw)));
        $requested_statuses = array_values(array_intersect($requested_statuses, $allowed_statuses));
        if (!empty($requested_statuses)) {
            $orders = array_values(array_filter($orders, function ($order) use ($requested_statuses) {
                return in_array((string)($order['status'] ?? ''), $requested_statuses, true);
            }));
        }
    }

    usort($orders, function ($a, $b) {
        $a_ts = !empty($a['created_at']) ? strtotime((string)$a['created_at']) : 0;
        $b_ts = !empty($b['created_at']) ? strtotime((string)$b['created_at']) : 0;
        if ($a_ts !== $b_ts) {
            return $b_ts <=> $a_ts;
        }
        $a_id = (int)($a['id'] ?? 0);
        $b_id = (int)($b['id'] ?? 0);
        return $b_id <=> $a_id;
    });

    ob_start();
    $title = $is_id_only_query ? 'Результаты поиска по номеру заказа' : 'Результаты поиска';
    displayOrders($orders, $title, 'search');
    echo ob_get_clean();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_note'])) {
        $order_id = $_POST['order_id'];
        $section = $_POST['section'];
        $open_details = $_POST['open_details'] ?? '';

        // Вызываем функцию, которая обрабатывает добавление примечания и фото
        addNoteAndPhotos($_POST, $_FILES);

        $redirect = "?section=$section";
        if ($open_details) {
            $redirect .= "&open_details=$open_details";
        }
        header("Location: $redirect");
        exit;
    }
    handlePostRequests($conn, $section, $queue_date);
}

if (isset($_GET['complete'])) {
    updateOrderStatus($_GET['complete'], 'completed');
    header("Location: ?section=in_progress");
    exit;
}

if (isset($_GET['reopen'])) {
    updateOrderStatus($_GET['reopen'], 'in_progress');
    header("Location: ?section=" . ($section === 'completed' ? 'completed' : 'archive'));
    exit;
}
if ($section === 'expenses' && $_SESSION['user_role'] !== 'admin') {
    $section = 'new_order';
}

// Рендеринг страницы
renderMainPage($conn, $section, $queue_date, $form_data);
?>
<link rel="manifest" href="/manifest.json">
<?php
if (ob_get_level()) {
    ob_end_flush();
}
?>

