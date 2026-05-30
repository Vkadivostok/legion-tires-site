<?php
require_once 'db.php';

define('HOLIDAYS', ['2025-01-01', '2025-01-02', '2025-01-03', '2025-01-04', '2025-01-05', '2025-01-06', '2025-01-07', '2025-01-08', '2025-02-23', '2025-03-08', '2025-05-01', '2025-05-09', '2025-06-12', '2025-11-04']);
define('MONTHS_RU', [1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель', 5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август', 9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь']);

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit;
}
track_user_activity('calendar');

$month = sprintf("%02d", $_GET['month'] ?? date('m'));
$year = (int)($_GET['year'] ?? date('Y'));
$selected_date = $_GET['date'] ?? null;
$order_id = (int)($_GET['order_id'] ?? null);
$select_date = ($_GET['select_date'] ?? 0) == 1;
$view_only = ($_GET['view_only'] ?? 0) == 1;
$form_data = isset($_GET['form_data']) ? json_decode(base64_decode($_GET['form_data']), true) : null;
$calendar_type = $_GET['calendar_type'] ?? 'orders';
$is_tire_calendar = $calendar_type === 'tire';
$is_express_calendar = $calendar_type === 'express';
$is_note_calendar = $is_tire_calendar || $is_express_calendar;

if (!$view_only && !$select_date && !$is_note_calendar && $selected_date && $order_id) {
    $stmt = $conn->prepare("UPDATE orders SET queue_date = ? WHERE id = ?") or die("Ошибка: " . $conn->error);
    $stmt->bind_param("si", $selected_date, $order_id);
    $stmt->execute();
    $stmt->close();
    log_change("Календарь: изменена очередь заказа #{$order_id} на {$selected_date}");
    header("Location: index.php?section=in_progress");
    exit;
} elseif ($select_date && $selected_date) {
    log_change("Календарь: выбрана дата {$selected_date} для нового заказа");
    $location = "Location: index.php?section=new_order&queue_date=" . urlencode($selected_date);
    if ($form_data) {
        $location .= "&form_data=" . urlencode(base64_encode(json_encode($form_data)));
    }
    header($location);
    exit;
}

function buildUrl($month, $year, $view_only, $select_date, $order_id, $calendar_type, $form_data)
{
    $params = ["month=$month", "year=$year", "view_only=" . ($view_only ? 1 : 0), "select_date=" . ($select_date ? 1 : 0), "order_id=$order_id", "calendar_type=$calendar_type"];
    if ($form_data) {
        $params[] = 'form_data=' . urlencode(base64_encode(json_encode($form_data)));
    }
    return "calendar.php?" . implode('&', array_filter($params));
}

function getCalendarOrders($month, $year, $conn)
{
    $start_date = "$year-$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    $stmt = $conn->prepare("SELECT id, queue_date, client_name, license_plate, phone, color, price, notes, location FROM orders WHERE queue_date BETWEEN ? AND ?") or die("Ошибка: " . $conn->error);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[date('Y-m-d', strtotime($row['queue_date']))][] = $row;
    }
    $stmt->close();
    return $orders;
}

function saveTireNote($date, $notes, $is_express, $conn)
{
    $table = $is_express ? 'expert_notes' : 'tire_notes';
    $notes_json = json_encode($notes, JSON_UNESCAPED_UNICODE);
    $stmt = $conn->prepare("INSERT INTO $table (note_date, note) VALUES (?, ?) ON DUPLICATE KEY UPDATE note = ?") or error_log("Ошибка: " . $conn->error);
    $stmt->bind_param("sss", $date, $notes_json, $notes_json);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function getTireNotes($month, $year, $is_express, $conn)
{
    $table = $is_express ? 'expert_notes' : 'tire_notes';
    $start_date = "$year-$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    $stmt = $conn->prepare("SELECT note_date, note FROM $table WHERE note_date BETWEEN ? AND ?") or die("Ошибка: " . $conn->error);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $notes = [];
    while ($row = $result->fetch_assoc()) {
        $notes[$row['note_date']] = json_decode($row['note'], true) ?: array_fill(0, 5, '');
    }
    $stmt->close();
    return $notes;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_note']) && $is_note_calendar) {
    $note_date = $_POST['note_date'];
    $notes = [];
    for ($i = 0; isset($_POST["note_$i"]); $i++) {
        $notes[] = trim($_POST["note_$i"] ?? '');
    }
    if (saveTireNote($note_date, $notes, $is_express_calendar, $conn)) {
        header("Location: calendar.php?month=$month&year=$year&calendar_type=$calendar_type");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_note']) && $is_note_calendar) {
    $note_date = $_POST['note_date'];
    if (saveTireNote($note_date, array_fill(0, 5, ''), $is_express_calendar, $conn)) {
        header("Location: calendar.php?month=$month&year=$year&calendar_type=$calendar_type");
        exit;
    }
}

$calendar_orders = !$is_note_calendar ? getCalendarOrders($month, $year, $conn) : [];
$tire_notes = $is_note_calendar ? getTireNotes($month, $year, $is_express_calendar, $conn) : [];
$first_day = date('N', strtotime("$year-$month-01")) - 1;
$days_in_month = date('t', strtotime("$year-$month-01"));
$prev_month = $month == '01' ? '12' : sprintf("%02d", (int)$month - 1);
$prev_year = $month == '01' ? $year - 1 : $year;
$next_month = $month == '12' ? '01' : sprintf("%02d", (int)$month + 1);
$next_year = $month == '12' ? $year + 1 : $year;
$current_date = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_tire_calendar ? 'ШИНОМОНТАЖ' : ($is_express_calendar ? 'ЭКСПРЕСС' : 'ЗАКАЗЫ') ?></title>
    <link rel="icon" type="image/jpeg" href="Logo.png">
    <link rel="apple-touch-icon" href="Logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f8;
            background-image: url('https://www.diskzakaz.ru/1/fn.png');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: cover;
            color: #1f2a37;
            font-family: Arial, sans-serif;
            padding: 10px;
        }
        .container { max-width: 100%; }
        .calendar {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.96), rgba(245, 247, 250, 0.96));
            backdrop-filter: blur(10px);
            border-radius: 8px;
            padding: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 5px;
        }
        .calendar-header h2 { color: #000; font-weight: 700; font-size: 20px; margin: 0; }
        .calendar-table { width: 100%; border-collapse: separate; border-spacing: 2px; table-layout: fixed; }
        .calendar-table th, .calendar-table td { border: 1px solid #95a5a6; padding: 3px; text-align: center; vertical-align: top; font-size: 12px; box-shadow: inset 0 1px 3px rgba(0,0,0,0.1); width: calc((100% - 12px) / 7); min-width: calc((100% - 12px) / 7); height: 120px; box-sizing: border-box; overflow: hidden; }
        .calendar-table th { background: linear-gradient(#f1f3f5, #e2e8f0); color: #1f2a37; font-weight: 600; }
        .calendar-table td { background: #d4edda; color: #2c3e50; cursor: pointer; position: relative; }
        .calendar-table td.has-notes, .calendar-table td.has-orders { background: #fff3cd; }
        .calendar-table td:hover { background: #d5d8dc; }
        .calendar-table td.empty { background: #bdc3c7; cursor: default; }
        .calendar-table td.weekend, .calendar-table td.holiday { color: #e74c3c; }
        .calendar-table td.overloaded { background: #e74c3c; color: #fff; }
        .calendar-table td.current-day { font-weight: bold; border: 2px solid #3498db; }
        .order-text { color: #3498db; display: block; font-size: 10px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .btn { padding: 6px 10px; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); border: none; background: linear-gradient(#3498db, #2980b9); color: #fff; font-size: 12px; min-width: 44px; min-height: 44px; }
        .btn:hover { background: linear-gradient(#2980b9, #1f618d); }
        .btn-danger { background: linear-gradient(#e74c3c, #c0392b); }
        .btn-danger:hover { background: linear-gradient(#c0392b, #a93226); }
        .btn-secondary { background: linear-gradient(#95a5a6, #7f8c8d); }
        .btn-warning { background: linear-gradient(#f1c40f, #e67e22); }
        .button-group { display: flex; gap: 5px; flex-wrap: wrap; }
        .calendar-footer { margin-top: 10px; display: flex; justify-content: center; gap: 5px; flex-wrap: wrap; }
        .note-container, .order-container { margin-top: 3px; font-size: 10px; max-height: 80px; overflow-y: auto; overflow-x: hidden; width: 100%; box-sizing: border-box; }
        .note-line, .order-line { border-bottom: 1px solid #ccc; line-height: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .edit-icon { position: absolute; top: 2px; right: 2px; font-size: 10px; color: #3498db; }
        .modal-content { background: linear-gradient(#ecf0f1, #bdc3c7); color: #2c3e50; }
        .modal-body textarea, .modal-body .form-control {
            width: 100%;
            resize: vertical;
            font-size: 12px;
            padding: 5px;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
            margin-bottom: 5px;
            background: #ffffff;
            color: #0f172a;
        }
        .modal-body textarea::placeholder { color: #64748b; }
        .modal-body textarea:focus, .modal-body .form-control:focus {
            border-color: #00b4d8;
            box-shadow: 0 0 0 3px rgba(0, 180, 216, 0.25);
            background: #ffffff;
            color: #0f172a;
        }
        .modal-body .form-control:disabled,
        .modal-body textarea:disabled {
            background-color: #f1f5f9;
            color: #0f172a;
            opacity: 1;
        }
        .calendar-selector { max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 12px; }
        .calendar-selector option { min-width: 250px; padding: 5px; }
        .btn-add-note { padding: 6px 10px; font-size: 16px; }
        @media (max-width: 768px) {
            .calendar-table th, .calendar-table td { font-size: 10px; height: 100px; width: calc((100% - 12px) / 7); min-width: calc((100% - 12px) / 7); box-sizing: border-box; }
            .calendar-table { margin: 0; width: 100%; }
            .note-container, .order-container { max-height: 60px; }
            .btn { font-size: 10px; padding: 5px 8px; }
            .calendar-header h2 { font-size: 18px; }
            .calendar-selector { max-width: 200px; font-size: 10px; }
            .calendar-selector option { min-width: 200px; }
        }
        @media (max-width: 576px) {
            .calendar-table th, .calendar-table td { font-size: 8px; height: 80px; width: calc((100% - 12px) / 7); min-width: calc((100% - 12px) / 7); box-sizing: border-box; }
            .calendar-table { margin: 0; width: 100%; }
            .note-container, .order-container { max-height: 50px; }
            .btn { font-size: 8px; padding: 4px 6px; }
            .calendar-header { flex-direction: column; align-items: flex-start; }
            .calendar-header h2 { font-size: 16px; }
            .button-group { flex-direction: row; width: 100%; flex-wrap: nowrap; }
            .button-group .btn, .button-group select { flex: 1; text-align: center; }
            .modal-footer { flex-direction: row; justify-content: space-between; }
            .note-container, .order-container, .order-text { font-size: 8px; }
            .calendar-selector { max-width: 100%; font-size: 8px; }
            .calendar-selector option { min-width: 150px; }
        }
    </style>
    <link rel="stylesheet" href="views-global.css">
</head>
<body>
    <div class="container">
        <div class="calendar">
            <div class="calendar-header">
                <h2><?= MONTHS_RU[(int)$month] . ' ' . $year ?></h2>
                <div class="button-group">
                    <select class="form-select calendar-selector" onchange="window.location.href='<?= buildUrl($month, $year, $view_only, $select_date, $order_id, '', $form_data) ?>&calendar_type=' + this.value">
                        <option value="orders" <?= $calendar_type === 'orders' ? 'selected' : '' ?>>ЗАКАЗЫ</option>
                        <option value="tire" <?= $calendar_type === 'tire' ? 'selected' : '' ?>>ШИНОМОНТАЖ</option>
                        <option value="express" <?= $calendar_type === 'express' ? 'selected' : '' ?>>ЭКСПРЕСС</option>
                    </select>
                    <button class="btn" aria-label="Предыдущий месяц" onclick="window.location.href='<?= buildUrl($prev_month, $prev_year, $view_only, $select_date, $order_id, $calendar_type, $form_data) ?>'"><?= MONTHS_RU[(int)$prev_month] ?></button>
                    <button class="btn" aria-label="Следующий месяц" onclick="window.location.href='<?= buildUrl($next_month, $next_year, $view_only, $select_date, $order_id, $calendar_type, $form_data) ?>'"><?= MONTHS_RU[(int)$next_month] ?></button>
                    <button class="btn btn-danger" aria-label="Выход" onclick="window.location.href='index.php?section=new_order'">Выход</button>
                </div>
            </div>
            <table class="calendar-table">
                <thead><tr><th>Пн</th><th>Вт</th><th>Ср</th><th>Чт</th><th>Пт</th><th>Сб</th><th>Вс</th></tr></thead>
                <tbody>
                    <?php
                    $day = 1;
                    for ($i = 0; $i < 6 && $day <= $days_in_month; $i++) {
                        echo '<tr>';
                        for ($j = 0; $j < 7; $j++) {
                            if (($i == 0 && $j < $first_day) || $day > $days_in_month) {
                                echo '<td class="empty"></td>';
                            } else {
                                $current_date = sprintf("%d-%02d-%02d", $year, $month, $day);
                                $is_current_day = $current_date === date('Y-m-d');
                                $orders = $calendar_orders[$current_date] ?? [];
                                $is_weekend = $j == 5 || $j == 6;
                                $is_holiday = in_array($current_date, HOLIDAYS);
                                $class = ($is_weekend ? 'weekend' : ($is_holiday ? 'holiday' : '')) . ($is_current_day ? ' current-day' : '');
                                $onclick = $view_only || $is_note_calendar ? ($is_note_calendar ? "editNote('$current_date')" : "showOrderDetails('$current_date')") : ($select_date ? "window.location.href='calendar.php?month=$month&year=$year&date=$current_date&select_date=1" . ($form_data ? '&form_data=' . urlencode(base64_encode(json_encode($form_data))) : '') . "&calendar_type=$calendar_type'" : ($order_id ? "assignOrder('$current_date', $order_id)" : "showOrderDetails('$current_date')"));
                                $day_notes = $is_note_calendar && isset($tire_notes[$current_date]) ? $tire_notes[$current_date] : array_fill(0, 5, '');
                                $non_empty_notes = array_filter($day_notes, fn($note) => !empty(trim($note)));
                                $is_overloaded = ($is_note_calendar && count($non_empty_notes) > 5) || (!$is_note_calendar && count($orders) > 11) ? 'overloaded' : '';
                                $has_content = $is_note_calendar ? (!empty($non_empty_notes) ? 'has-notes' : '') : (count($orders) > 0 ? 'has-orders' : '');

                                echo "<td class='$class $is_overloaded $has_content' onclick=\"$onclick\" data-date='$current_date' aria-label='Дата $current_date'>$day<br>";
                                echo $is_note_calendar ? '<div class="note-container">' . implode('', array_map(fn($note) => "<div class='note-line'>" . (strlen($note = htmlspecialchars($note)) > 10 ? substr($note, 0, 10) . '...' : $note) . "</div>", array_slice($day_notes, 0, 5))) . (!empty($non_empty_notes) ? '<span class="edit-icon">✎</span>' : '') . '</div>' : '<div class="order-container">' . implode('', array_map(fn($order) => "<div class='order-line'><span class='order-text'>" . htmlspecialchars($order['client_name']) . "</span></div>", $orders)) . (count($orders) > 0 ? '<span class="edit-icon">✎</span>' : '') . '</div>';
                                echo '</td>';
                                $day++;
                            }
                        }
                        echo '</tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="noteModal" tabindex="-1" aria-labelledby="noteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="noteModalLabel">Редактировать заметки</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="noteForm" method="post">
                        <input type="hidden" name="note_date" id="note_date">
                        <input type="hidden" name="save_note" value="1">
                        <div id="noteFields">
                            <?php for ($i = 0; $i < 5; $i++) : ?>
                            <textarea name="note_<?= $i ?>" rows="2" maxlength="100" placeholder="Заметка <?= $i + 1 ?>"></textarea>
                            <?php endfor; ?>
                        </div>
                        <button type="button" class="btn btn-warning btn-add-note" onclick="addNoteField()">+</button>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" onclick="submitNoteForm()">Сохранить</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderModalLabel">Детали заказов</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="orderModalBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedDate = null;
        let noteCount = 5;

        <?php if (!$view_only && !$select_date && !$is_note_calendar && $order_id) : ?>
        function assignOrder(date, orderId) {
            if (confirm(`Назначить заказ на ${date}?`)) window.location.href = `calendar.php?month=<?= $month ?>&year=<?= $year ?>&date=${date}&order_id=${orderId}&calendar_type=<?= $calendar_type ?>`;
        }
        <?php endif; ?>

        function editNote(date) {
            selectedDate = date;
            const notes = <?= json_encode($tire_notes) ?>;
            document.getElementById('note_date').value = date;
            const noteFields = document.getElementById('noteFields');
            noteFields.innerHTML = '';
            noteCount = Math.max(5, (notes[date] || []).length);
            for (let i = 0; i < noteCount; i++) {
                const textarea = document.createElement('textarea');
                textarea.name = `note_${i}`;
                textarea.rows = 2;
                textarea.maxLength = 100;
                textarea.placeholder = `Заметка ${i + 1}`;
                textarea.value = (notes[date] || Array(noteCount).fill(''))[i] || '';
                noteFields.appendChild(textarea);
            }
            new bootstrap.Modal(document.getElementById('noteModal')).show();
        }

        function addNoteField() {
            const noteFields = document.getElementById('noteFields');
            const textarea = document.createElement('textarea');
            textarea.name = `note_${noteCount}`;
            textarea.rows = 2;
            textarea.maxLength = 100;
            textarea.placeholder = `Заметка ${noteCount + 1}`;
            noteFields.appendChild(textarea);
            noteCount++;
        }

        function showOrderDetails(date) {
            const orders = <?= json_encode($calendar_orders) ?>;
            const dayOrders = orders[date] || [];
            const modalBody = document.getElementById('orderModalBody');
            modalBody.innerHTML = dayOrders.length ? dayOrders.map(order => {
                const phoneText = String(order.phone || '').trim();
                const phoneHref = phoneText.replace(/[^\d+]/g, '') || phoneText;
                const phoneHtml = phoneText
                    ? `<a href="tel:${phoneHref}" class="phone-link">${phoneText}</a>`
                    : 'не указан';

                return `
                <div class="mb-3 metallic-card">
                    <label class="form-label fw-bold">Заказ #${order.id}</label>
                    <label class="form-label">ФИО:</label><input type="text" class="form-control" value="${order.client_name || ''}" disabled>
                    <label class="form-label">Госномер:</label><input type="text" class="form-control" value="${order.license_plate || ''}" disabled>
                    <label class="form-label">Телефон:</label><div class="form-control" style="height: auto;">${phoneHtml}</div>
                    <label class="form-label">Цвет:</label><input type="text" class="form-control" value="${order.color || ''}" disabled>
                    <label class="form-label">Стоимость:</label><input type="text" class="form-control" value="${order.price || ''}" disabled>
                    <label class="form-label">Локация:</label><input type="text" class="form-control" value="${order.location || ''}" disabled>
                    <label class="form-label">Примечания:</label><textarea class="form-control" rows="2" disabled>${order.notes || ''}</textarea>
                    <hr>
                </div>`;
            }).join('') : '<p>Заказов на эту дату нет</p>';
            document.getElementById('orderModalLabel').textContent = `Детали заказов за ${date}`;
            new bootstrap.Modal(document.getElementById('orderModal')).show();
        }

        function submitNoteForm() {
            document.getElementById('noteForm').submit();
        }

        // Fix for aria-hidden issue: Move focus when modal is hidden
        document.getElementById('orderModal').addEventListener('hidden.bs.modal', function () {
            const closeButton = this.querySelector('.btn-close');
            if (closeButton === document.activeElement) {
                closeButton.blur();
                // Move focus to a safe element, e.g., the calendar container
                document.querySelector('.calendar').focus();
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



