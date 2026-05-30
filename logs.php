<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isAdminUser()) {
    header("Location: index.php");
    exit;
}

if (isset($_GET['logout'])) {
    log_change("Выход из системы");
    session_destroy();
    header("Location: index.php");
    exit;
}
track_user_activity('logs');

function contains_text(string $haystack, string $needle): bool {
    if ($needle === '') return true;
    if (function_exists('mb_stripos')) {
        return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
    }
    return stripos($haystack, $needle) !== false;
}

function get_recent_logs(int $max_lines = 6000): array {
    $logs_file = 'debug.log';
    if (!file_exists($logs_file)) {
        return [];
    }
    $max_lines = max(200, min(30000, $max_lines));
    $file = new SplFileObject($logs_file, 'r');
    $file->seek(PHP_INT_MAX);
    $last = $file->key();
    $start = max(0, $last - $max_lines + 1);
    $logs = [];
    for ($i = $last; $i >= $start; $i--) {
        $file->seek($i);
        $line = trim((string)$file->current());
        if ($line !== '') {
            $logs[] = $line;
        }
    }
    return $logs;
}

function log_context_value(string $message, string $key): string {
    if (preg_match('/(?:^|\|)\s*' . preg_quote($key, '/') . '=([^|]+)/u', $message, $matches)) {
        return trim($matches[1]);
    }
    return '';
}

function human_action_label(string $action): string {
    $labels = [
        'login' => 'Вход в систему',
        'add_user' => 'Добавил пользователя',
        'edit_user' => 'Изменил пользователя',
        'delete_user' => 'Удалил пользователя',
        'add_expense' => 'Добавил расход или доход',
        'delete_expense' => 'Удалил расход или доход',
        'create_order' => 'Создал заказ',
        'update_order' => 'Изменил заказ',
        'archive_order' => 'Перенес заказ в архив',
        'create_storage' => 'Добавил запись склада',
        'update_storage' => 'Изменил запись склада',
        'delete_storage' => 'Удалил запись склада',
        'create_equipment' => 'Добавил оборудование',
        'update_equipment' => 'Изменил оборудование',
        'archive_equipment' => 'Архивировал оборудование',
        'restore_equipment' => 'Восстановил оборудование',
        'delete_equipment' => 'Удалил оборудование',
    ];
    return $labels[$action] ?? 'Выполнил действие';
}

function detect_event_type(string $message): string {
    $message_l = function_exists('mb_strtolower') ? mb_strtolower($message, 'UTF-8') : strtolower($message);
    if (strpos($message_l, 'успешный вход') !== false) return 'auth_login_success';
    if (strpos($message_l, 'неудачная попытка входа') !== false || strpos($message_l, 'попытка входа') !== false) return 'auth_login_fail';
    if (strpos($message_l, 'выход из системы') !== false) return 'auth_logout';
    if (strpos($message_l, 'склад:') !== false || strpos($message_l, 'хранен') !== false) return 'warehouse';
    if (strpos($message_l, 'смен') !== false) return 'shift';
    if (strpos($message_l, 'заказ-наряд') !== false || strpos($message_l, 'операци') !== false) return 'sale';
    if (strpos($message_l, 'прайс') !== false) return 'price';
    if (strpos($message_l, 'расход') !== false || strpos($message_l, 'доход') !== false) return 'expense';
    if (strpos($message_l, 'пользовател') !== false) return 'user';
    if (strpos($message_l, 'заказ') !== false) return 'order';
    return 'other';
}

function event_type_label(string $type): string {
    $labels = [
        'auth_login_success' => 'Вход',
        'auth_login_fail' => 'Неудачный вход',
        'auth_logout' => 'Выход',
        'sale' => 'Продажи',
        'warehouse' => 'Склад',
        'shift' => 'Смены',
        'price' => 'Прайс',
        'expense' => 'Расходы',
        'user' => 'Пользователи',
        'order' => 'Заказы',
        'other' => 'Другое',
    ];
    return $labels[$type] ?? 'Другое';
}

function normalize_log_message(string $message): array {
    $type = detect_event_type($message);
    $action = 'Действие';
    $object = '';
    $details = trim($message);

    if (contains_text($message, 'Аудит действия:')) {
        $auditAction = '';
        if (preg_match('/Аудит действия:\s*([^|]+)/u', $message, $matches)) {
            $auditAction = trim($matches[1]);
        }
        if ($auditAction === 'page_view' || $auditAction === 'post_request') {
            return ['skip' => true];
        }
        $area = log_context_value($message, 'area');
        $id = log_context_value($message, 'order_id') ?: log_context_value($message, 'record_id') ?: log_context_value($message, 'expense_id') ?: log_context_value($message, 'equipment_id') ?: log_context_value($message, 'user_id');
        $action = human_action_label($auditAction);
        $object = $id !== '' ? "#{$id}" : ($area !== '' ? $area : '');
        $details = $action . ($object !== '' ? " ({$object})" : '');
        $type = detect_event_type($details . ' ' . $area);
    } elseif ($type === 'auth_login_success') {
        $action = 'Вход';
        $details = 'Пользователь вошел в систему';
    } elseif ($type === 'auth_login_fail') {
        $action = 'Неудачный вход';
        $details = 'Неудачная попытка входа';
    } elseif ($type === 'auth_logout') {
        $action = 'Выход';
        $details = 'Пользователь вышел из системы';
    } elseif (preg_match('/заказ-наряда?\s*#?(\d+)/ui', $message, $matches)) {
        $object = 'Заказ-наряд #' . $matches[1];
        if (contains_text($message, 'сохранил')) $action = 'Создал продажу';
        elseif (contains_text($message, 'исправил')) $action = 'Изменил продажу';
        elseif (contains_text($message, 'удалил')) $action = 'Удалил продажу';
        elseif (contains_text($message, 'погасил долг')) $action = 'Погасил долг';
        elseif (contains_text($message, 'примечание')) $action = 'Изменил примечание';
        $details = $action . ' - ' . $object;
        if (preg_match('/документ\s+([^)]+)/ui', $message, $doc)) {
            $details .= ', документ ' . trim($doc[1]);
        }
    } elseif (contains_text($message, 'Склад:')) {
        $action = contains_text($message, 'удал') ? 'Удалил на складе' : (contains_text($message, 'обнов') || contains_text($message, 'измен') ? 'Изменил склад' : 'Изменил склад');
        $details = preg_replace('/^Склад:\s*/u', '', $message);
        $object = 'Склад';
    } elseif (contains_text($message, 'смен')) {
        $action = contains_text($message, 'открыл') ? 'Открыл смену' : (contains_text($message, 'закрыл') ? 'Закрыл смену' : 'Изменил смену');
        $object = preg_match('/#(\d+)/u', $message, $m) ? 'Смена #' . $m[1] : 'Смена';
        $details = $action . ' - ' . $object;
    } elseif (contains_text($message, 'прайс')) {
        $action = contains_text($message, 'сбросил') ? 'Сбросил прайс' : 'Изменил прайс';
        $object = 'Прайс';
        $details = $action;
    } elseif (contains_text($message, 'пользовател')) {
        $action = contains_text($message, 'добавил') ? 'Добавил пользователя' : (contains_text($message, 'удалил') ? 'Удалил пользователя' : (contains_text($message, 'заблокировал') ? 'Заблокировал пользователя' : (contains_text($message, 'разблокировал') ? 'Разблокировал пользователя' : 'Изменил пользователя')));
        $object = preg_match('/ID:\s*(\d+)/u', $message, $m) ? 'Пользователь #' . $m[1] : 'Пользователь';
        $details = $message;
    }

    return [
        'skip' => false,
        'event_type' => $type,
        'event_label' => event_type_label($type),
        'action' => $action,
        'object' => $object,
        'details' => $details,
    ];
}

function is_relevant_log(array $normalized): bool {
    if (!empty($normalized['skip'])) return false;
    return in_array($normalized['event_type'] ?? 'other', [
        'auth_login_success',
        'auth_login_fail',
        'auth_logout',
        'sale',
        'warehouse',
        'shift',
        'price',
        'expense',
        'user',
        'order',
    ], true);
}

function get_column_options(array $rows, string $key, int $limit = 400): array {
    $values = [];
    foreach ($rows as $row) {
        $value = trim((string)($row[$key] ?? ''));
        if ($value !== '') {
            $values[$value] = true;
        }
    }
    $options = array_keys($values);
    natcasesort($options);
    return array_slice(array_values($options), 0, $limit);
}

function get_date_options(array $rows, int $limit = 400): array {
    $values = [];
    foreach ($rows as $row) {
        $ts = strtotime((string)($row['timestamp'] ?? ''));
        if ($ts !== false) {
            $values[date('Y-m-d', $ts)] = true;
        }
    }
    $options = array_keys($values);
    rsort($options);
    return array_slice($options, 0, $limit);
}

function render_filter_options(array $options, string $selected, string $empty_label = 'Все'): void {
    echo '<option value="">' . htmlspecialchars($empty_label) . '</option>';
    if ($selected !== '' && !in_array($selected, $options, true)) {
        echo '<option value="' . htmlspecialchars($selected) . '" selected>' . htmlspecialchars($selected) . '</option>';
    }
    foreach ($options as $option) {
        $isSelected = $selected === $option ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($option) . '"' . $isSelected . '>' . htmlspecialchars($option) . '</option>';
    }
}

$limit = intval($_GET['limit'] ?? 300);
if ($limit <= 0) $limit = 300;
if ($limit > 2000) $limit = 2000;
$scan_limit = max(3000, min(30000, intval($_GET['scan_limit'] ?? ($limit * 20))));
$logs = get_recent_logs($scan_limit);
$parsed_logs = [];
foreach ($logs as $log) {
    $parts = [
        'timestamp' => 'N/A',
        'username' => 'N/A',
        'ip' => 'N/A',
        'event_type' => 'other',
        'event_label' => 'Другое',
        'action' => 'Действие',
        'object' => '',
        'message' => $log,
        'raw_message' => $log,
    ];

    if (preg_match('/^\[(.*?)\] \[(.*?)\] \[(.*?)\] \[(.*?)\] \[(.*?)\] (.*)$/', $log, $matches)) {
        $parts['timestamp'] = $matches[1];
        $parts['username'] = $matches[2];
        $parts['ip'] = $matches[3];
        $parts['message'] = $matches[6];
    } elseif (preg_match('/^\[(.*?)\] \[(.*?)\] \[(.*?)\] \[(.*?)\] (.*)$/', $log, $matches)) {
        $parts['timestamp'] = $matches[1];
        $parts['username'] = $matches[2];
        $parts['ip'] = $matches[3];
        $parts['message'] = $matches[5];
    } elseif (preg_match('/^\[(.*?)\] \[(.*?)\] \[(.*?)\] (.*)$/', $log, $matches)) {
        $parts['timestamp'] = $matches[1];
        $parts['username'] = $matches[2];
        $parts['ip'] = $matches[3];
        $parts['message'] = $matches[4];
    } elseif (preg_match('/^\[(.*?)\] \[(.*?)\] (.*)$/', $log, $matches)) {
        $parts['timestamp'] = $matches[1];
        $parts['username'] = $matches[2];
        $parts['message'] = $matches[3];
    }

    $normalized = normalize_log_message($parts['message']);
    if (!is_relevant_log($normalized)) {
        continue;
    }
    $parts['event_type'] = $normalized['event_type'];
    $parts['event_label'] = $normalized['event_label'];
    $parts['action'] = $normalized['action'];
    $parts['object'] = $normalized['object'];
    $parts['message'] = $normalized['details'];
    $parsed_logs[] = $parts;
}

$f_user = trim($_GET['f_user'] ?? '');
$f_ip = trim($_GET['f_ip'] ?? '');
$f_type = trim($_GET['f_type'] ?? '');
$f_action = trim($_GET['f_action'] ?? '');
$f_object = trim($_GET['f_object'] ?? '');
$f_q = trim($_GET['f_q'] ?? '');
$f_date = trim($_GET['f_date'] ?? '');
$f_from = trim($_GET['f_from'] ?? '');
$f_to = trim($_GET['f_to'] ?? '');

$filter_source = $parsed_logs;
$date_options = get_date_options($filter_source);
$user_options = get_column_options($filter_source, 'username');
$ip_options = get_column_options($filter_source, 'ip');
$action_options = get_column_options($filter_source, 'action');
$object_options = get_column_options($filter_source, 'object');
$message_options = get_column_options($filter_source, 'message', 600);

$parsed_logs = array_values(array_filter($parsed_logs, function ($row) use ($f_user, $f_ip, $f_type, $f_action, $f_object, $f_q, $f_date, $f_from, $f_to) {
    if ($f_user !== '' && !contains_text($row['username'], $f_user)) return false;
    if ($f_ip !== '' && !contains_text($row['ip'], $f_ip)) return false;
    if ($f_type !== '' && $row['event_type'] !== $f_type) return false;
    if ($f_action !== '' && !contains_text($row['action'], $f_action)) return false;
    if ($f_object !== '' && !contains_text($row['object'], $f_object)) return false;
    if ($f_q !== '' && !contains_text($row['message'] . ' ' . $row['raw_message'], $f_q)) return false;

    $ts = strtotime($row['timestamp']);
    if ($f_date !== '' && $ts !== false && date('Y-m-d', $ts) !== $f_date) return false;
    if ($f_from !== '' && $ts !== false && $ts < strtotime($f_from . ' 00:00:00')) return false;
    if ($f_to !== '' && $ts !== false && $ts > strtotime($f_to . ' 23:59:59')) return false;
    return true;
}));

if (count($parsed_logs) > $limit) {
    $parsed_logs = array_slice($parsed_logs, 0, $limit);
}

$stats_total = count($parsed_logs);
$stats_users = count(array_unique(array_column($parsed_logs, 'username')));
$stats_ips = count(array_unique(array_column($parsed_logs, 'ip')));
$stats_actions = count(array_unique(array_column($parsed_logs, 'action')));
?>
<!DOCTYPE html>
<html>
<head>
    <title>Logs</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #f4f6f8;
            color: #1f2a37;
        }
        .nav-menu {
            position: fixed;
            top: 0;
            left: -280px;
            width: 280px;
            height: 100%;
            background: linear-gradient(145deg, #ffffff, #eef2f5);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            padding: 20px;
            transition: left 0.3s ease-in-out;
            overflow-y: auto;
        }
        .nav-menu.open {
            left: 0;
        }
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
        .nav-menu a:hover, .nav-menu button:hover {
            background: rgba(15, 23, 42, 0.06);
            color: #1f2a37;
        }
        .nav-menu a.active {
            background: linear-gradient(145deg, #00b4d8, #0077b6);
            color: #ffffff;
            box-shadow: 0 2px 10px rgba(0, 180, 216, 0.3);
        }
        .nav-toggle {
            z-index: 1100;
            background: linear-gradient(145deg, #00b4d8, #0077b6);
            border: none;
            border-radius: 8px;
            padding: 10px 14px;
            box-shadow: 0 4px 15px rgba(0, 180, 216, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
        }
        .nav-toggle:hover {
            box-shadow: 0 6px 20px rgba(0, 180, 216, 0.5);
        }
        @media (min-width: 993px) {
            .nav-toggle {
                display: flex !important;
            }
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
            box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.4);
            overflow-x: auto;
            overflow-y: hidden;
            -ms-overflow-style: none; /* IE and Edge */
            scrollbar-width: none; /* Firefox */
        }
        .bottom-nav::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera*/
        }
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
        
        @media (max-width: 576px) {
            .bottom-nav {
                display: flex;
            }
        }
        .table {
            color: #1f2a37;
        }
        .table-dark {
            background-color: #2c3e50;
        }
        a {
            color: #00b4d8;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .logs-page .table {
            background: #ffffff;
            border: 1px solid #cbd5e1;
        }
        .logs-page .table thead th {
            background: #1f2937;
            color: #ffffff;
            vertical-align: top;
            white-space: nowrap;
        }
        .logs-page .table tbody td {
            background: #ffffff;
            color: #1f2a37;
            border-color: #e5e7eb;
            vertical-align: top;
        }
        .logs-page .table-striped > tbody > tr:nth-of-type(odd) > * {
            background: #f8fafc;
            color: #1f2a37;
        }
        .column-filter {
            margin-top: 6px;
            min-width: 120px;
            font-size: 12px;
        }
        .log-message-cell {
            min-width: 280px;
            max-width: 520px;
            white-space: normal;
        }
        .logs-toolbar {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.96), rgba(245, 247, 250, 0.96));
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 12px;
        }
        .log-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 12px;
        }
        .log-stat-card {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px;
            text-align: center;
        }
        .log-stat-value {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.1;
        }
        .log-stat-label {
            font-size: 12px;
            color: #475569;
            margin-top: 2px;
        }
        .logs-mobile-list {
            display: none;
            margin-top: 12px;
        }
        .log-mobile-card {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.96), rgba(245, 247, 250, 0.96));
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 10px;
        }
        .log-mobile-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 6px;
            font-size: 13px;
        }
        .log-mobile-row:last-child {
            margin-bottom: 0;
        }
        .log-mobile-label {
            color: #64748b;
            min-width: 84px;
            flex-shrink: 0;
        }
        .log-mobile-value {
            color: #0f172a;
            text-align: right;
            word-break: break-word;
        }
        .log-mobile-message {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid rgba(15, 23, 42, 0.12);
            color: #1f2a37;
            font-size: 13px;
            line-height: 1.35;
            word-break: break-word;
        }
        @media (max-width: 768px) {
            .container {
                padding-left: 4px;
                padding-right: 4px;
            }
            .log-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .container h1 {
                font-size: 24px;
                margin-top: 8px;
                margin-bottom: 10px;
            }
            .table-responsive {
                display: none;
            }
            .logs-mobile-list {
                display: block;
            }
        }
        @media (max-width: 992px) {
            body {
                padding-bottom: 74px;
            }
            .bottom-nav {
                display: flex;
            }
        }
    </style>
    <link rel="stylesheet" href="views-global.css">
</head>
<body>
    <?php renderUnifiedNavigation('', ['show_top' => true, 'show_bottom' => true, 'show_spacers' => false, 'toggle_label' => '☰ Меню', 'toggle_class' => 'btn nav-toggle d-none d-lg-flex']); ?>
    <div class="container mt-4 logs-page">
        <h1>Логи</h1>
        <div class="log-stats">
            <div class="log-stat-card">
                <div class="log-stat-value"><?php echo $stats_total; ?></div>
                <div class="log-stat-label">Записей</div>
            </div>
            <div class="log-stat-card">
                <div class="log-stat-value"><?php echo $stats_users; ?></div>
                <div class="log-stat-label">Пользователей</div>
            </div>
            <div class="log-stat-card">
                <div class="log-stat-value"><?php echo $stats_ips; ?></div>
                <div class="log-stat-label">IP адресов</div>
            </div>
            <div class="log-stat-card">
                <div class="log-stat-value"><?php echo $stats_actions; ?></div>
                <div class="log-stat-label">Типов действий</div>
            </div>
        </div>
        <div class="logs-toolbar">
            <form id="logsFilterForm" method="get" class="row g-2">
                <div class="col-md-3 col-6">
                    <select class="form-select" name="f_user">
                        <?php render_filter_options($user_options, $f_user, 'Пользователь'); ?>
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <select class="form-select" name="f_type">
                        <option value="">Тип события</option>
                        <option value="auth_login_success" <?php echo $f_type === 'auth_login_success' ? 'selected' : ''; ?>>Вход успешный</option>
                        <option value="auth_login_fail" <?php echo $f_type === 'auth_login_fail' ? 'selected' : ''; ?>>Вход неуспешный</option>
                        <option value="auth_logout" <?php echo $f_type === 'auth_logout' ? 'selected' : ''; ?>>Выход</option>
                        <option value="sale" <?php echo $f_type === 'sale' ? 'selected' : ''; ?>>Продажи</option>
                        <option value="warehouse" <?php echo $f_type === 'warehouse' ? 'selected' : ''; ?>>Склад</option>
                        <option value="shift" <?php echo $f_type === 'shift' ? 'selected' : ''; ?>>Смены</option>
                        <option value="price" <?php echo $f_type === 'price' ? 'selected' : ''; ?>>Прайс</option>
                        <option value="expense" <?php echo $f_type === 'expense' ? 'selected' : ''; ?>>Расходы</option>
                        <option value="user" <?php echo $f_type === 'user' ? 'selected' : ''; ?>>Пользователи</option>
                        <option value="order" <?php echo $f_type === 'order' ? 'selected' : ''; ?>>Заказы</option>
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <select class="form-select" name="f_ip">
                        <?php render_filter_options($ip_options, $f_ip, 'IP'); ?>
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <select class="form-select" name="f_date">
                        <?php render_filter_options($date_options, $f_date, 'Дата'); ?>
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <input type="date" class="form-control" name="f_from" value="<?php echo htmlspecialchars($f_from); ?>">
                </div>
                <div class="col-md-2 col-6">
                    <input type="date" class="form-control" name="f_to" value="<?php echo htmlspecialchars($f_to); ?>">
                </div>
                <div class="col-md-1 col-6">
                    <input type="number" class="form-control" name="limit" value="<?php echo htmlspecialchars((string)$limit); ?>" min="50" max="2000">
                </div>
                <div class="col-md-3 col-6">
                    <select class="form-select" name="f_action">
                        <?php render_filter_options($action_options, $f_action, 'Действие'); ?>
                    </select>
                </div>
                <div class="col-md-3 col-6">
                    <select class="form-select" name="f_object">
                        <?php render_filter_options($object_options, $f_object, 'Объект'); ?>
                    </select>
                </div>
                <div class="col-md-6 col-12">
                    <select class="form-select" name="f_q">
                        <?php render_filter_options($message_options, $f_q, 'Описание'); ?>
                    </select>
                </div>
                <div class="col-md-4 col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Применить</button>
                    <a href="logs.php" class="btn btn-secondary">Сброс</a>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-striped mt-3">
                <thead>
                    <tr>
                        <th>
                            Время
                            <select class="form-select column-filter" data-filter-name="f_date">
                                <?php render_filter_options($date_options, $f_date); ?>
                            </select>
                        </th>
                        <th>
                            Пользователь
                            <select class="form-select column-filter" data-filter-name="f_user">
                                <?php render_filter_options($user_options, $f_user); ?>
                            </select>
                        </th>
                        <th>
                            IP
                            <select class="form-select column-filter" data-filter-name="f_ip">
                                <?php render_filter_options($ip_options, $f_ip); ?>
                            </select>
                        </th>
                        <th>
                            Раздел
                            <select class="form-select column-filter" data-filter-name="f_type">
                                <option value="">Все</option>
                                <option value="auth_login_success" <?php echo $f_type === 'auth_login_success' ? 'selected' : ''; ?>>Вход</option>
                                <option value="auth_login_fail" <?php echo $f_type === 'auth_login_fail' ? 'selected' : ''; ?>>Неудачный вход</option>
                                <option value="auth_logout" <?php echo $f_type === 'auth_logout' ? 'selected' : ''; ?>>Выход</option>
                                <option value="sale" <?php echo $f_type === 'sale' ? 'selected' : ''; ?>>Продажи</option>
                                <option value="warehouse" <?php echo $f_type === 'warehouse' ? 'selected' : ''; ?>>Склад</option>
                                <option value="shift" <?php echo $f_type === 'shift' ? 'selected' : ''; ?>>Смены</option>
                                <option value="price" <?php echo $f_type === 'price' ? 'selected' : ''; ?>>Прайс</option>
                                <option value="expense" <?php echo $f_type === 'expense' ? 'selected' : ''; ?>>Расходы</option>
                                <option value="user" <?php echo $f_type === 'user' ? 'selected' : ''; ?>>Пользователи</option>
                                <option value="order" <?php echo $f_type === 'order' ? 'selected' : ''; ?>>Заказы</option>
                            </select>
                        </th>
                        <th>
                            Действие
                            <select class="form-select column-filter" data-filter-name="f_action">
                                <?php render_filter_options($action_options, $f_action); ?>
                            </select>
                        </th>
                        <th>
                            Объект
                            <select class="form-select column-filter" data-filter-name="f_object">
                                <?php render_filter_options($object_options, $f_object); ?>
                            </select>
                        </th>
                        <th>
                            Описание
                            <select class="form-select column-filter" data-filter-name="f_q">
                                <?php render_filter_options($message_options, $f_q); ?>
                            </select>
                        </th>
                    </tr>
            </thead>
            <tbody>
                <?php foreach ($parsed_logs as $parts): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($parts['timestamp']); ?></td>
                        <td><?php echo htmlspecialchars($parts['username']); ?></td>
                        <td><?php echo htmlspecialchars($parts['ip']); ?></td>
                        <td><?php echo htmlspecialchars($parts['event_label']); ?></td>
                        <td><?php echo htmlspecialchars($parts['action']); ?></td>
                        <td><?php echo htmlspecialchars($parts['object']); ?></td>
                        <td class="log-message-cell"><?php echo htmlspecialchars($parts['message']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            </table>
        </div>
        <div class="logs-mobile-list">
            <?php foreach ($parsed_logs as $parts): ?>
                <div class="log-mobile-card">
                    <div class="log-mobile-row">
                        <span class="log-mobile-label">Время</span>
                        <span class="log-mobile-value"><?php echo htmlspecialchars($parts['timestamp']); ?></span>
                    </div>
                    <div class="log-mobile-row">
                        <span class="log-mobile-label">Пользователь</span>
                        <span class="log-mobile-value"><?php echo htmlspecialchars($parts['username']); ?></span>
                    </div>
                    <div class="log-mobile-row">
                        <span class="log-mobile-label">IP</span>
                        <span class="log-mobile-value"><?php echo htmlspecialchars($parts['ip']); ?></span>
                    </div>
                    <div class="log-mobile-row">
                        <span class="log-mobile-label">Раздел</span>
                        <span class="log-mobile-value"><?php echo htmlspecialchars($parts['event_label']); ?></span>
                    </div>
                    <div class="log-mobile-row">
                        <span class="log-mobile-label">Действие</span>
                        <span class="log-mobile-value"><?php echo htmlspecialchars($parts['action']); ?></span>
                    </div>
                    <div class="log-mobile-row">
                        <span class="log-mobile-label">Объект</span>
                        <span class="log-mobile-value"><?php echo htmlspecialchars($parts['object']); ?></span>
                    </div>
                    <div class="log-mobile-message"><?php echo htmlspecialchars($parts['message']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        function toggleNav() {
            const navMenu = document.getElementById('navMenu');
            navMenu.classList.toggle('open');
        }

        document.addEventListener('click', function(event) {
            const navMenu = document.getElementById('navMenu');
            const navToggleBtn = document.querySelector('.nav-toggle');
            if (navMenu.classList.contains('open') && !navMenu.contains(event.target) && !navToggleBtn.contains(event.target)) {
                navMenu.classList.remove('open');
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const bottomNav = document.querySelector('.bottom-nav');
            const filterForm = document.getElementById('logsFilterForm');
            const submitColumnFilter = (control) => {
                if (!filterForm || !control.dataset.filterName) return;
                if ((control.dataset.initialValue || '') === control.value) return;
                const target = filterForm.querySelector(`[name="${control.dataset.filterName}"]`);
                if (target) {
                    target.value = control.value;
                    filterForm.submit();
                }
            };
            document.querySelectorAll('.column-filter').forEach(control => {
                control.dataset.initialValue = control.value;
                if (control.tagName === 'SELECT') {
                    control.addEventListener('change', () => submitColumnFilter(control));
                    return;
                }
                control.addEventListener('keydown', event => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        submitColumnFilter(control);
                    }
                });
                control.addEventListener('blur', () => submitColumnFilter(control));
            });

            // Restore scroll position on page load
            if (bottomNav && localStorage.getItem('bottomNavScrollLeft')) {
                bottomNav.scrollLeft = localStorage.getItem('bottomNavScrollLeft');
            }

            // Save scroll position on scroll
            if (bottomNav) {
                bottomNav.addEventListener('scroll', function() {
                    localStorage.setItem('bottomNavScrollLeft', bottomNav.scrollLeft);
                });
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const activeNavItem = document.querySelector('.bottom-nav .nav-item.active');
            if (activeNavItem) {
                const navContainer = document.querySelector('.bottom-nav');
                const containerWidth = navContainer.offsetWidth;
                const itemWidth = activeNavItem.offsetWidth;
                const itemLeft = activeNavItem.offsetLeft;

                const scrollLeft = itemLeft - (containerWidth / 2) + (itemWidth / 2);
                navContainer.scrollLeft = scrollLeft;

                // Add glow effect
                activeNavItem.classList.add('active-glow');
            }
        });
    </script>
</body>
</html>

