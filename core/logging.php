<?php

function get_user_ip()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

function log_change($message)
{
    $logs_file = 'debug.log';
    $username = $_SESSION['username'] ?? 'guest';
    $ip = get_user_ip();
    $timestamp = date('Y-m-d H:i:s');
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';
    $session_id = session_id();
    $log_entry = "[{$timestamp}] [{$username}] [{$ip}] [{$session_id}] [{$user_agent}] {$message}\n";
    file_put_contents($logs_file, $log_entry, FILE_APPEND);
}

function format_log_context($context = [])
{
    if (!is_array($context) || empty($context)) {
        return '';
    }

    $safe_context = [];
    $skip_keys = ['password', 'new_password', 'edit_password', 'token', 'csrf_token'];
    foreach ($context as $key => $value) {
        if (in_array(strtolower((string)$key), $skip_keys, true)) {
            continue;
        }
        if (is_array($value)) {
            $value = implode(',', array_map(function ($item) {
                return is_scalar($item) ? (string)$item : '[complex]';
            }, $value));
        } elseif (!is_scalar($value) && $value !== null) {
            $value = '[complex]';
        }
        $safe_context[] = $key . '=' . (string)$value;
    }

    if (empty($safe_context)) {
        return '';
    }
    return ' | ' . implode(' | ', $safe_context);
}

function detect_post_action_name()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST)) {
        return '';
    }

    $action_keys = [
        'login', 'create_order', 'update_order', 'archive_order', 'add_note', 'mark_completed',
        'add_user', 'edit_user', 'delete_user', 'create_storage', 'update_storage', 'delete_storage',
        'create_equipment', 'update_equipment', 'archive_equipment', 'restore_equipment', 'delete_equipment', 'add_payment', 'add_expense',
        'delete_expense'
    ];
    foreach ($action_keys as $key) {
        if (isset($_POST[$key])) {
            return $key;
        }
    }

    foreach (array_keys($_POST) as $key) {
        if (preg_match('/^(action|submit|do)_/i', $key)) {
            return $key;
        }
    }
    return 'post_request';
}

function track_user_activity($area = '')
{
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return;
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $section = $_GET['section'] ?? '';
    $action = '';
    $context = [
        'area' => $area ?: basename($path),
        'method' => $method,
        'path' => $path
    ];

    if (!empty($section)) {
        $context['section'] = $section;
    }

    if ($method === 'POST') {
        $action = detect_post_action_name();
        if (!empty($action)) {
            $context['action'] = $action;
        }
    } else {
        $action = 'page_view';
    }

    if ($action === 'page_view') {
        return;
    }

    $tracked_query_keys = ['query', 'status', 'location', 'open_details', 'start_date', 'end_date', 'expense_type', 'page', 'sort', 'sort_value'];
    foreach ($tracked_query_keys as $key) {
        if (isset($_GET[$key]) && $_GET[$key] !== '') {
            $context[$key] = $_GET[$key];
        }
    }

    $tracked_post_keys = ['order_id', 'equipment_id', 'record_id', 'expense_id', 'user_id'];
    foreach ($tracked_post_keys as $key) {
        if (isset($_POST[$key]) && $_POST[$key] !== '') {
            $context[$key] = $_POST[$key];
        }
    }

    if (!empty($_SERVER['HTTP_REFERER'])) {
        $context['ref'] = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
    }

    $fingerprint = md5($method . '|' . $path . '|' . json_encode($context));
    $now = time();
    $last_fp = $_SESSION['last_activity_fp'] ?? '';
    $last_ts = intval($_SESSION['last_activity_ts'] ?? 0);
    if ($fingerprint === $last_fp && ($now - $last_ts) < 4) {
        return;
    }
    $_SESSION['last_activity_fp'] = $fingerprint;
    $_SESSION['last_activity_ts'] = $now;

    log_change("Аудит действия: {$action}" . format_log_context($context));
}
