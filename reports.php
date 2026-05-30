<?php
require_once 'db.php';
require_once 'views.php';
require_once 'tcpdf/tcpdf.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit;
}
if (!isAdminUser()) {
    header("Location: index.php?section=new_order");
    exit;
}

if (isset($_GET['logout'])) {
    log_change("Выход из системы");
    session_destroy();
    header("Location: index.php");
    exit;
}
track_user_activity('reports');

function getOrdersReport($conn, $start_date, $end_date, $location_filter = '')
{
    // Нормализация дат
    $start_date = date('Y-m-d', strtotime($start_date));
    $end_date = date('Y-m-d', strtotime($end_date));

    $query = "SELECT o.*
              FROM orders o
              WHERE o.created_at BETWEEN ? AND ?";

    $params = [$start_date . ' 0:00:00', $end_date . ' 23:59:59'];
    $types = "ss";

    if ($location_filter) {
        $query .= " AND location = ?";
        $params[] = $location_filter;
        $types .= "s";
    }

    $query .= " ORDER BY o.created_at DESC";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Ошибка подготовки запроса: " . $conn->error);
        die("Ошибка подготовки запроса: " . $conn->error);
    }

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        error_log("Ошибка выполнения запроса: " . $stmt->error);
        die("Ошибка выполнения запроса: " . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result === false) {
        error_log("Ошибка получения результата: " . $conn->error);
        die("Ошибка получения результата: " . $conn->error);
    }

    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }

    $stmt->close();

    $total_amount = 0;
    $total_worker_earn = 0;
    $total_net_profit = 0;
    $filtered_orders = [];

    foreach ($orders as $order) {
        $worker_earn = 0;
        $order['worker_earn'] = $worker_earn;
        $order['net_profit'] = ($order['price'] ?? 0) - $worker_earn;
        $filtered_orders[] = $order;
        $total_amount += $order['price'] ?? 0;
        $total_worker_earn += $worker_earn;
        $total_net_profit += $order['net_profit'];
    }

    return [
        'orders' => $filtered_orders,
        'total_amount' => $total_amount,
        'total_worker_earn' => $total_worker_earn,
        'total_net_profit' => $total_net_profit
    ];
}

function renderReportsPage($conn)
{
    $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    $location_filter = $_GET['location_filter'] ?? '';

    if (isset($_GET['download_pdf'])) {
        generatePdfReport($conn, $start_date, $end_date, $location_filter);
        exit;
    }

    // Проверка порядка дат
    if (strtotime($end_date) < strtotime($start_date)) {
        $temp = $start_date;
        $start_date = $end_date;
        $end_date = $temp;
    }

    $cache = new CacheService();
    $cacheKey = 'report_' . md5(serialize([$start_date, $end_date, $location_filter]));

    $report_data = $cache->remember($cacheKey, 3600, function () use ($conn, $start_date, $end_date, $location_filter) {
        return getOrdersReport($conn, $start_date, $end_date, $location_filter);
    });
    $orders = $report_data['orders'];
    $total_amount = $report_data['total_amount'];
    $total_worker_earn = $report_data['total_worker_earn'];
    $total_net_profit = $report_data['total_net_profit'];

    $locations_list = [];
    $locations_result = $conn->query("SELECT DISTINCT location FROM orders WHERE location IS NOT NULL AND location != '' ORDER BY location ASC");
    if ($locations_result) {
        while ($row = $locations_result->fetch_assoc()) {
            $locations_list[] = $row['location'];
        }
    }


    // Соответствие статусов для отображения
    $status_labels = [
        'new' => 'Новый',
        'in_progress' => 'В работе',
        'completed' => 'Готово',
        'archive' => 'Архив'
    ];
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отчеты по заказам</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { 
            background-color: #f4f6f8;
            background-image: url('https://www.diskzakaz.ru/1/fn.png');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: cover;
            color: #1f2a37; 
            font-size: 14px; 
            padding: 10px; 
            margin: 0;
            padding-bottom: 60px; /* Добавляем отступ снизу для нижней навигации */
        }
        .container { 
            padding: 10px; 
            max-width: 100%; 
        }
        .metallic-card { 
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.96), rgba(245, 247, 250, 0.96));
            /* backdrop-filter: blur(10px); */
            border: 1px solid #95a5a6;
            border-radius: 8px; 
            margin-bottom: 10px;
            padding: 8px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.2); 
            color: #1f2a37;
        }
        .form-control, .btn {
            font-size: 14px;
            padding: 8px;
            min-height: 40px;
            border-radius: 5px;
        }
        .btn-primary { 
            background: linear-gradient(#3498db, #2980b9); 
            border: none; 
        }
        .btn-primary:hover { 
            background: linear-gradient(#2980b9, #1f618d); 
        }
        .btn-exit {
            background: linear-gradient(#e74c3c, #c0392b);
            border: none;
        }
        .btn-exit:hover {
            background: linear-gradient(#c0392b, #a93226);
        }
        .table-container {
            overflow-x: auto;
        }
        .table { 
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.96), rgba(245, 247, 250, 0.96));
            width: 100%; 
            min-width: 600px;
        }
        .table th, .table td {
            border: 1px solid #95a5a6;
            font-size: 14px;
            padding: 8px;
        }
        .total-row { 
            font-weight: bold; 
            background: linear-gradient(#3498db, #2980b9); 
            color: #fff; 
        }
        .client-link { 
            color: #3498db; 
            text-decoration: none; 
        }
        .client-link:hover { 
            text-decoration: underline; 
        }
        .order-link {
            color: #3498db;
            text-decoration: none;
        }
        .order-link:hover {
            text-decoration: underline;
        }
        h1 {
            font-size: 20px;
            margin-bottom: 15px;
        }
        h5 {
            font-size: 16px;
        }
        /* Добавляем стили для мобильной навигации внизу */
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
            color: #ffffff;
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
            flex: 0 0 auto;
        }
        .bottom-nav .nav-item.active {
            background: linear-gradient(145deg, #00b4d8, #0077b6);
            color: #ffffff;
        }
        
        @media (max-width: 992px) {
            body {
                padding-bottom: 74px;
            }
            .bottom-nav {
                display: flex;
            }
        }
        @media (max-width: 576px) {
            body {
                font-size: 14px;
                padding: 5px;
            }
            .container { 
                padding: 5px; 
            }
            .metallic-card {
                padding: 6px;
                margin-bottom: 8px;
            }
            .form-control, .btn {
                font-size: 14px;
                padding: 6px;
                min-height: 36px;
            }
        .table th, .table td {
            font-size: 14px;
            padding: 6px;
        }
        h1 {
            font-size: 20px;
        }
        h5 {
            font-size: 16px;
        }
        .row.mb-2 > div {
            margin-bottom: 10px;
        }
    }
    .reports-toolbar {
        background: linear-gradient(145deg, rgba(255, 255, 255, 0.96), rgba(245, 247, 250, 0.96));
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 12px;
        margin-bottom: 12px;
    }
    .report-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
        margin: 12px 0;
    }
    .report-stat-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 8px;
        text-align: center;
    }
    .report-stat-value {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.1;
    }
    .report-stat-label {
        font-size: 12px;
        color: #475569;
        margin-top: 2px;
    }
    .reports-mobile-list {
        display: none;
        margin-top: 12px;
    }
    .report-mobile-card {
        background: linear-gradient(145deg, rgba(255, 255, 255, 0.96), rgba(245, 247, 250, 0.96));
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 10px;
        margin-bottom: 10px;
    }
    .report-mobile-row {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 6px;
        font-size: 13px;
    }
    .report-mobile-row:last-child {
        margin-bottom: 0;
    }
    .report-mobile-label {
        color: #64748b;
        min-width: 90px;
        flex-shrink: 0;
    }
    .report-mobile-value {
        color: #0f172a;
        text-align: right;
        word-break: break-word;
    }
    .report-mobile-note {
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid rgba(15, 23, 42, 0.12);
        color: #1f2a37;
        font-size: 13px;
        line-height: 1.35;
        word-break: break-word;
    }
    @media (max-width: 768px) {
        .reports-page .table-container {
            display: none;
        }
        .reports-mobile-list {
            display: block;
        }
        .report-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .reports-page h1 {
            font-size: 22px;
            margin-top: 8px;
            margin-bottom: 10px;
        }
    }
        @media (min-width: 577px) and (max-width: 768px) {
            .container { 
                max-width: 540px; 
            }
            .form-control, .btn {
                font-size: 14px;
                padding: 7px;
            }
            .table th, .table td {
                font-size: 14px;
            }
        }
    </style>
    <link rel="stylesheet" href="views-global.css">
</head>
<body>
    <?php renderUnifiedNavigation('', ['show_top' => true, 'show_bottom' => true, 'show_spacers' => false, 'toggle_label' => '☰ Меню', 'toggle_class' => 'btn nav-toggle d-none d-lg-flex']); ?>
    <div class="container reports-page">
        <h1 class="text-center">Отчеты по заказам</h1>
        
        <div class="card metallic-card mb-3 reports-toolbar">
            <div class="card-body">
                <h5>Фильтры отчета</h5>
                <form method="get">
                    <div class="row mb-2">
                        <div class="col-12 col-md-3">
                            <label for="start_date">Дата начала</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" required>
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="end_date">Дата окончания</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" required>
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="location_filter">Локация</label>
                            <select class="form-control" id="location_filter" name="location_filter">
                                <option value="">Все локации</option>
                                <?php foreach ($locations_list as $location) : ?>
                                    <option value="<?= htmlspecialchars($location) ?>" <?= $location_filter === $location ? 'selected' : '' ?>><?= htmlspecialchars($location) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Сформировать отчет</button>
                        <button type="submit" name="download_pdf" value="1" class="btn btn-secondary">Выгрузить в PDF</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card metallic-card">
            <div class="card-body">
                <h5>Результаты отчета</h5>
                <?php if (empty($orders)) : ?>
                    <p>Нет заказов за выбранный период</p>
                <?php else : ?>
                    <div class="report-stats">
                        <div class="report-stat-card">
                            <div class="report-stat-value"><?= number_format($total_amount, 2) ?></div>
                            <div class="report-stat-label">Выручка</div>
                        </div>
                        <div class="report-stat-card">
                            <div class="report-stat-value"><?= number_format($total_worker_earn, 2) ?></div>
                            <div class="report-stat-label">Выплаты</div>
                        </div>
                        <div class="report-stat-card">
                            <div class="report-stat-value"><?= number_format($total_net_profit, 2) ?></div>
                            <div class="report-stat-label">Прибыль</div>
                        </div>
                    </div>
                    <div class="table-container">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Клиент</th>
                                    <th>Примечания</th>
                                    <th>Стоимость (руб.)</th>
                                    <th>Оплачено работникам</th>
                                    <th>Чистая прибыль</th>
                                    <th>Статус</th>
                                    <th>Дата создания</th>
                                    <th>Локация</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order) :
                                    $order_section = $order['status'] === 'new' ? 'new_order' : $order['status'];
                                    ?>
                                    <tr>
                                        <td><a href="index.php?section=search&query=%23<?= $order['id'] ?>" class="order-link">#<?= htmlspecialchars($order['id']) ?></a></td>
                                        <td><a href="index.php?section=<?= htmlspecialchars($order_section) ?>&order_id=<?= $order['id'] ?>" class="client-link"><?= htmlspecialchars($order['client_name']) ?></a></td>
                                        <td><?= htmlspecialchars($order['notes'] ?? 'не указаны') ?></td>
                                        <td>
                                            <?php
                                            if (is_null($order['price']) || $order['price'] == 0) {
                                                echo 'Стоимость не указана';
                                            } else {
                                                echo number_format($order['price'], 2);
                                            }
                                            ?>
                                        </td>
                                        <td><?= number_format($order['worker_earn'], 2) ?></td>
                                        <td><?= number_format($order['net_profit'], 2) ?></td>
                                        <td><?= htmlspecialchars($status_labels[$order['status']] ?? 'Неизвестно') ?></td>
                                        <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                                        <td><?= htmlspecialchars($order['location'] ?? 'не указана') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td colspan="3">Итого</td>
                                    <td><?= number_format($total_amount, 2) ?></td>
                                    <td><?= number_format($total_worker_earn, 2) ?></td>
                                    <td><?= number_format($total_net_profit, 2) ?></td>
                                    <td colspan="3"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="reports-mobile-list">
                        <?php foreach ($orders as $order) :
                            $order_section = $order['status'] === 'new' ? 'new_order' : $order['status'];
                            ?>
                            <div class="report-mobile-card">
                                <div class="report-mobile-row">
                                    <span class="report-mobile-label">Заказ</span>
                                    <span class="report-mobile-value">#<?= htmlspecialchars($order['id']) ?></span>
                                </div>
                                <div class="report-mobile-row">
                                    <span class="report-mobile-label">Клиент</span>
                                    <span class="report-mobile-value">
                                        <a href="index.php?section=<?= htmlspecialchars($order_section) ?>&order_id=<?= $order['id'] ?>" class="client-link"><?= htmlspecialchars($order['client_name']) ?></a>
                                    </span>
                                </div>
                                <div class="report-mobile-row">
                                    <span class="report-mobile-label">Стоимость</span>
                                    <span class="report-mobile-value">
                                        <?php
                                        if (is_null($order['price']) || $order['price'] == 0) {
                                            echo 'Не указана';
                                        } else {
                                            echo number_format($order['price'], 2);
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="report-mobile-row">
                                    <span class="report-mobile-label">Выплаты</span>
                                    <span class="report-mobile-value"><?= number_format($order['worker_earn'], 2) ?></span>
                                </div>
                                <div class="report-mobile-row">
                                    <span class="report-mobile-label">Прибыль</span>
                                    <span class="report-mobile-value"><?= number_format($order['net_profit'], 2) ?></span>
                                </div>
                                <div class="report-mobile-row">
                                    <span class="report-mobile-label">Статус</span>
                                    <span class="report-mobile-value"><?= htmlspecialchars($status_labels[$order['status']] ?? 'Неизвестно') ?></span>
                                </div>
                                <div class="report-mobile-row">
                                    <span class="report-mobile-label">Дата</span>
                                    <span class="report-mobile-value"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></span>
                                </div>
                                <div class="report-mobile-row">
                                    <span class="report-mobile-label">Локация</span>
                                    <span class="report-mobile-value"><?= htmlspecialchars($order['location'] ?? 'не указана') ?></span>
                                </div>
                                <div class="report-mobile-note">
                                    Примечание: <?= htmlspecialchars($order['notes'] ?? 'не указаны') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
    <?php
}

function generatePdfReport($conn, $start_date, $end_date, $location_filter)
{
    $report_data = getOrdersReport($conn, $start_date, $end_date, $location_filter);
    $orders = $report_data['orders'];
    $total_amount = $report_data['total_amount'];
    $total_worker_earn = $report_data['total_worker_earn'];
    $total_net_profit = $report_data['total_net_profit'];

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('DiskZakaz');
    $pdf->SetTitle('Отчет по заказам');
    $pdf->SetSubject('Отчет по заказам');

    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    $pdf->AddPage('L', 'A4');

    $pdf->SetFont('dejavusans', '', 8);

    $html = '<h1>Отчет по заказам с ' . date('d.m.Y', strtotime($start_date)) . ' по ' . date('d.m.Y', strtotime($end_date)) . '</h1>';
    $html .= '<table border="1" cellpadding="4">
        <thead>
            <tr style="background-color:#c9c9c9;">
                <th>#</th>
                <th>Клиент</th>
                <th>Стоимость</th>
                <th>Оплачено работникам</th>
                <th>Чистая прибыль</th>
                <th>Статус</th>
                <th>Дата</th>
                <th>Локация</th>
            </tr>
        </thead>
        <tbody>';

    $status_labels = [
        'new' => 'Новый',
        'in_progress' => 'В работе',
        'completed' => 'Готово',
        'archive' => 'Архив'
    ];

    foreach ($orders as $order) {
        $price = (is_null($order['price']) || $order['price'] == 0) ? 'Не указана' : number_format($order['price'], 2);

        $html .= '<tr>
                    <td>#' . htmlspecialchars($order['id']) . '</td>
                    <td>' . htmlspecialchars($order['client_name']) . '</td>
                    <td>' . $price . '</td>
                    <td>' . number_format($order['worker_earn'], 2) . '</td>
                    <td>' . number_format($order['net_profit'], 2) . '</td>
                    <td>' . htmlspecialchars($status_labels[$order['status']] ?? 'Неизвестно') . '</td>
                    <td>' . date('d.m.Y H:i', strtotime($order['created_at'])) . '</td>
                    <td>' . htmlspecialchars($order['location'] ?? 'не указана') . '</td>
                  </tr>';
    }

    $html .= '</tbody>
        <tfoot>
            <tr style="background-color:#c9c9c9; font-weight:bold;">
                <td colspan="4">Итого</td>
                <td>' . number_format($total_amount, 2) . '</td>
                <td>' . number_format($total_worker_earn, 2) . '</td>
                <td>' . number_format($total_net_profit, 2) . '</td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>';

    $pdf->writeHTML($html, true, false, true, false, '');

    $pdf->Output('report.pdf', 'D');
}

renderReportsPage($conn);
?>

