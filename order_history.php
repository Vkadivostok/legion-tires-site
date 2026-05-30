<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

if (isset($_GET['logout'])) {
    log_change("Выход из системы");
    session_destroy();
    header("Location: index.php");
    exit;
}

track_user_activity('order_history');

function get_logs_raw()
{
    $logs_file = 'debug.log';
    if (!file_exists($logs_file)) {
        return [];
    }
    $logs = file($logs_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return array_reverse($logs); // newest first
}

function parse_log_line($log)
{
    $parts = [
        'timestamp' => 'N/A',
        'username' => 'N/A',
        'ip' => 'N/A',
        'session_id' => 'N/A',
        'message' => $log
    ];

    if (preg_match('/^\[(.*?)\] \[(.*?)\] \[(.*?)\] \[(.*?)\] \[(.*?)\] (.*)$/', $log, $m)) {
        $parts['timestamp'] = $m[1];
        $parts['username'] = $m[2];
        $parts['ip'] = $m[3];
        $parts['session_id'] = $m[4];
        $parts['message'] = $m[6];
        return $parts;
    }

    if (preg_match('/^\[(.*?)\] \[(.*?)\] \[(.*?)\] \[(.*?)\] (.*)$/', $log, $m)) {
        $parts['timestamp'] = $m[1];
        $parts['username'] = $m[2];
        $parts['ip'] = $m[3];
        $parts['message'] = $m[5];
        return $parts;
    }

    if (preg_match('/^\[(.*?)\] \[(.*?)\] \[(.*?)\] (.*)$/', $log, $m)) {
        $parts['timestamp'] = $m[1];
        $parts['username'] = $m[2];
        $parts['ip'] = $m[3];
        $parts['message'] = $m[4];
        return $parts;
    }

    if (preg_match('/^\[(.*?)\] \[(.*?)\] (.*)$/', $log, $m)) {
        $parts['timestamp'] = $m[1];
        $parts['username'] = $m[2];
        $parts['message'] = $m[3];
        return $parts;
    }

    return $parts;
}

function extract_order_open_event($message)
{
    $event = null;

    if (preg_match('/Открытие карточки заказа\s*#\s*(\d+)/ui', $message, $m)) {
        $event = [
            'order_id' => (int)$m[1],
            'source' => 'manual'
        ];
    } elseif (
        stripos($message, 'Аудит действия: page_view') !== false &&
        preg_match('/(?:^|\|)\s*open_details=(\d+)/ui', $message, $m)
    ) {
        $event = [
            'order_id' => (int)$m[1],
            'source' => 'audit'
        ];
    }

    if ($event === null) {
        return null;
    }

    $section = '';
    if (preg_match('/(?:^|\|)\s*section=([a-z_]+)/i', $message, $s)) {
        $section = trim((string)$s[1]);
    }
    $event['section'] = $section;

    return $event;
}

$current_user = (string)($_SESSION['username'] ?? '');

$events = [];
foreach (get_logs_raw() as $line) {
    $parts = parse_log_line($line);
    $event = extract_order_open_event($parts['message']);
    if ($event === null) {
        continue;
    }
    $events[] = [
        'timestamp' => $parts['timestamp'],
        'username' => $parts['username'],
        'ip' => $parts['ip'],
        'session_id' => $parts['session_id'],
        'order_id' => $event['order_id'],
        'section' => $event['section'],
        'source' => $event['source'],
        'message' => $parts['message']
    ];
}

$events = array_values(array_filter($events, function ($row) use ($current_user) {
    if ($current_user === '' || (string)$row['username'] !== $current_user) return false;
    return true;
}));

// Подтягиваем имя клиента и актуальный раздел заказа для ссылки перехода.
$order_meta = [];
$order_ids = array_values(array_unique(array_map(function ($e) {
    return (int)($e['order_id'] ?? 0);
}, $events)));
$order_ids = array_values(array_filter($order_ids, function ($id) {
    return $id > 0;
}));

if (!empty($order_ids)) {
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $types = str_repeat('i', count($order_ids));
    $sql = "SELECT id, client_name, status FROM orders WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $bind = [$types];
        foreach ($order_ids as $i => $value) {
            $bind[] = &$order_ids[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $status = (string)($row['status'] ?? '');
            $section = 'in_progress';
            if (in_array($status, ['new', 'in_progress', 'completed', 'archive'], true)) {
                $section = $status;
            }
            $order_meta[(int)$row['id']] = [
                'client_name' => (string)($row['client_name'] ?? ''),
                'section' => $section
            ];
        }
        $stmt->close();
    }
}

foreach ($events as &$e) {
    $oid = (int)($e['order_id'] ?? 0);
    $meta = $order_meta[$oid] ?? null;
    $client_name = $meta['client_name'] ?? '';
    $section = $meta['section'] ?? ((string)($e['section'] ?? '') ?: 'in_progress');
    $e['client_name'] = $client_name !== '' ? $client_name : 'Клиент не найден';
    $e['order_url'] = 'index.php?section=' . rawurlencode($section) . '&open_details=' . rawurlencode((string)$oid);
}
unset($e);

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>История карточек заказов</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="views-global.css">
    <style>
        body { background: #f4f6f8; color: #1f2a37; }
        .nav-menu {
            position: fixed;
            top: 14px;
            left: -290px;
            width: 244px;
            height: calc(100% - 28px);
            background:
                linear-gradient(165deg, rgba(250, 252, 255, 0.95), rgba(224, 231, 239, 0.94)),
                radial-gradient(120% 130% at 10% 0%, rgba(255,255,255,0.95), rgba(226,232,240,0.86));
            border: 1px solid rgba(148, 163, 184, 0.55);
            box-shadow:
                0 18px 36px rgba(15, 23, 42, 0.22),
                inset 0 1px 0 rgba(255, 255, 255, 0.95),
                inset 0 -1px 0 rgba(148, 163, 184, 0.3);
            backdrop-filter: blur(5px);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            padding: 14px;
            transition: left .3s ease-in-out;
            overflow-y: auto;
            border-radius: 16px;
        }
        .nav-menu.open { left: 12px; }
        .nav-menu a {
            color: #0f172a;
            text-decoration: none;
            padding: 9px 11px;
            border-radius: 10px;
            margin-bottom: 6px;
            display: block;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid transparent;
            background: rgba(241, 245, 249, 0.72);
        }
        .nav-menu a:hover {
            border-color: rgba(148, 163, 184, 0.55);
            background: rgba(226, 232, 240, 0.9);
        }
        .nav-menu a.active {
            background: linear-gradient(145deg, #64748b, #334155);
            border-color: rgba(148, 163, 184, 0.75);
            color: #fff;
            box-shadow: 0 6px 14px rgba(15, 23, 42, 0.22);
        }
        .menu-head {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.45);
        }
        .menu-user {
            color: #334155;
            font-size: 12px;
            margin-top: 7px;
            display: block;
        }
        .wheel-toggle {
            width: 54px;
            height: 54px;
            border: none;
            border-radius: 50%;
            background: transparent;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .wheel-ring {
            position: relative;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #0f172a;
            background-image: url('Logo.png');
            background-size: 88%;
            background-position: center;
            background-repeat: no-repeat;
            filter: saturate(1.55) contrast(1.35) brightness(1.12);
            border: 2px solid #0b1220;
            box-shadow:
                0 10px 20px rgba(2, 6, 23, 0.52),
                0 0 0 2px rgba(148, 163, 184, 0.45),
                inset 0 2px 3px rgba(255,255,255,0.65),
                inset 0 -3px 5px rgba(15,23,42,0.62);
            animation: wheel-spin-idle 5s linear infinite;
        }
        .wheel-ring::before {
            content: "";
            position: absolute;
            inset: -2px;
            border-radius: 50%;
            border: 1px solid rgba(226, 232, 240, 0.7);
            pointer-events: none;
        }
        .wheel-center {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 10px;
            height: 10px;
            transform: translate(-50%, -50%);
            border-radius: 50%;
            background: linear-gradient(145deg, #f8fafc, #94a3b8);
            border: 1px solid rgba(71, 85, 105, 0.9);
            box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.5);
        }
        .wheel-toggle.spinning .wheel-ring {
            animation: wheel-spin-boost 700ms linear;
        }
        .nav-toggle-mobile {
            display: none;
            position: fixed;
            top: 10px;
            left: 10px;
            z-index: 1201;
            width: 45px;
            height: 45px;
            border: none;
            border-radius: 50%;
            background: transparent;
            padding: 0;
            align-items: center;
            justify-content: center;
            opacity: 0.55;
            transition: opacity 0.2s ease;
        }
        .nav-toggle-mobile:hover,
        .nav-toggle-mobile:focus-visible {
            opacity: 1;
        }
        .nav-toggle-mobile .wheel-ring {
            position: relative;
            width: 41px;
            height: 41px;
        }
        .nav-toggle-mobile .wheel-ring::before {
            inset: 4px;
        }
        .nav-toggle-mobile .wheel-center {
            width: 8px;
            height: 8px;
        }
        .nav-toggle-mobile.spinning .wheel-ring {
            animation: wheel-spin-boost 700ms linear;
        }
        .sparks {
            position: absolute;
            left: 50%;
            bottom: -2px;
            width: 0;
            height: 0;
            pointer-events: none;
            z-index: 2;
        }
        .spark {
            position: absolute;
            width: 3px;
            height: 3px;
            border-radius: 50%;
            opacity: 0;
            box-shadow: 0 0 8px currentColor;
        }
        .spark.s1 { color: #f59e0b; }
        .spark.s2 { color: #fb7185; }
        .spark.s3 { color: #22c55e; }
        .spark.s4 { color: #38bdf8; }
        .spark.s5 { color: #fde047; }
        .spark.s6 { color: #a78bfa; }
        .wheel-toggle.burst .spark,
        .nav-toggle-mobile.burst .spark {
            animation: spark-fly 620ms ease-out forwards;
        }
        .wheel-toggle.burst .spark.s1,
        .nav-toggle-mobile.burst .spark.s1 { --tx: -18px; --ty: 18px; --d: 0ms; }
        .wheel-toggle.burst .spark.s2,
        .nav-toggle-mobile.burst .spark.s2 { --tx: -8px; --ty: 24px; --d: 40ms; }
        .wheel-toggle.burst .spark.s3,
        .nav-toggle-mobile.burst .spark.s3 { --tx: 4px; --ty: 20px; --d: 20ms; }
        .wheel-toggle.burst .spark.s4,
        .nav-toggle-mobile.burst .spark.s4 { --tx: 14px; --ty: 25px; --d: 70ms; }
        .wheel-toggle.burst .spark.s5,
        .nav-toggle-mobile.burst .spark.s5 { --tx: -2px; --ty: 30px; --d: 55ms; }
        .wheel-toggle.burst .spark.s6,
        .nav-toggle-mobile.burst .spark.s6 { --tx: 22px; --ty: 17px; --d: 85ms; }
        @keyframes wheel-spin-idle {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @keyframes wheel-spin-boost {
            from { transform: rotate(0deg); }
            to { transform: rotate(1080deg); }
        }
        @keyframes spark-fly {
            0% {
                opacity: 0;
                transform: translate(0, 0) scale(0.6);
            }
            20% {
                opacity: 1;
            }
            100% {
                opacity: 0;
                transform: translate(var(--tx), var(--ty)) scale(0.2);
            }
        }
        .table-wrap { margin-top: 12px; }
        .mobile-list { display: none; margin-top: 12px; }
        .mobile-card {
            --d: 0ms;
            position: relative;
            overflow: hidden;
            background:
                radial-gradient(140% 120% at 0% 0%, rgba(255, 255, 255, 0.95) 0%, rgba(236, 240, 245, 0.92) 45%, rgba(220, 226, 234, 0.92) 100%),
                linear-gradient(145deg, #f3f6fa, #d9e0e8);
            border: 1px solid rgba(133, 150, 171, 0.45);
            border-radius: 14px;
            padding: 12px;
            margin-bottom: 12px;
            box-shadow:
                0 10px 22px rgba(15, 23, 42, 0.16),
                inset 0 1px 0 rgba(255, 255, 255, 0.9),
                inset 0 -1px 0 rgba(148, 163, 184, 0.25);
            animation: card-in 460ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
            animation-delay: var(--d);
        }
        .mobile-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: -130%;
            width: 65%;
            height: 100%;
            transform: skewX(-18deg);
            background: linear-gradient(
                90deg,
                rgba(255, 255, 255, 0) 0%,
                rgba(255, 255, 255, 0.42) 45%,
                rgba(255, 255, 255, 0) 100%
            );
            transition: left 700ms ease;
            pointer-events: none;
        }
        .mobile-card:active::before,
        .mobile-card:hover::before {
            left: 145%;
        }
        .mobile-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 13px;
            padding-bottom: 7px;
            border-bottom: 1px solid rgba(100, 116, 139, 0.18);
        }
        .mobile-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        .mobile-label {
            color: #475569;
            min-width: 96px;
            font-weight: 700;
            letter-spacing: 0.2px;
            text-transform: uppercase;
            font-size: 11px;
        }
        .mobile-value {
            text-align: right;
            word-break: break-word;
            color: #0f172a;
            font-weight: 600;
        }
        .mobile-value a {
            color: #0f172a;
            text-decoration: none;
            padding: 3px 8px;
            border-radius: 8px;
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.9), rgba(226, 232, 240, 0.9));
            border: 1px solid rgba(148, 163, 184, 0.45);
            box-shadow: 0 3px 8px rgba(15, 23, 42, 0.12);
        }
        .mobile-value a:active,
        .mobile-value a:hover {
            background: linear-gradient(145deg, rgba(241, 245, 249, 1), rgba(203, 213, 225, 1));
        }
        @keyframes card-in {
            from {
                opacity: 0;
                transform: translateY(12px) scale(0.985);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        .empty-state {
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 16px;
            color: #475569;
        }
        @media (max-width: 992px) { body { padding-bottom: 74px; } }
        @media (max-width: 992px) {
            .container { padding-left: 8px; padding-right: 8px; }
            h1 { font-size: 22px; margin-bottom: 10px; }
            .table-responsive { display: none; }
            .mobile-list { display: block; }
            .nav-toggle-mobile { display: inline-flex; }
            .nav-menu {
                top: 0;
                left: -290px;
                width: 244px;
                height: 100%;
                border-radius: 0;
            }
            .nav-menu.open {
                left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <?php renderUnifiedNavigation('history', ['show_top' => true, 'show_bottom' => true, 'show_spacers' => false, 'toggle_label' => '☰ Меню', 'toggle_class' => 'btn nav-toggle d-none d-lg-flex']); ?>

        <h1>История открытия карточек заказов</h1>

        <?php if (empty($events)): ?>
        <div class="empty-state">История открытий пока пуста.</div>
        <?php else: ?>
        <div class="table-responsive table-wrap">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Дата и время</th>
                        <th>Номер заказа</th>
                        <th>Клиент</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($events as $e): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($e['timestamp']); ?></td>
                        <td><a href="<?php echo htmlspecialchars($e['order_url']); ?>">#<?php echo htmlspecialchars((string)$e['order_id']); ?></a></td>
                        <td><?php echo htmlspecialchars($e['client_name']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mobile-list">
            <?php foreach ($events as $idx => $e): ?>
            <div class="mobile-card" style="--d: <?php echo (int)min($idx, 12) * 60; ?>ms;">
                <div class="mobile-row"><span class="mobile-label">Время</span><span class="mobile-value"><?php echo htmlspecialchars($e['timestamp']); ?></span></div>
                <div class="mobile-row"><span class="mobile-label">Заказ</span><span class="mobile-value"><a href="<?php echo htmlspecialchars($e['order_url']); ?>">#<?php echo htmlspecialchars((string)$e['order_id']); ?></a></span></div>
                <div class="mobile-row"><span class="mobile-label">Клиент</span><span class="mobile-value"><?php echo htmlspecialchars($e['client_name']); ?></span></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleNav() {
            const navMenu = document.getElementById('navMenu');
            if (navMenu) navMenu.classList.toggle('open');
        }
        document.addEventListener('click', function(event) {
            const navMenu = document.getElementById('navMenu');
            const navToggleButtons = Array.from(document.querySelectorAll('.nav-toggle'));
            if (!navMenu || navToggleButtons.length === 0) return;
            const isOnToggle = navToggleButtons.some((btn) => btn.contains(event.target));
            if (navMenu.classList.contains('open') && !navMenu.contains(event.target) && !isOnToggle) {
                navMenu.classList.remove('open');
            }
        });
    </script>
</body>
</html>
