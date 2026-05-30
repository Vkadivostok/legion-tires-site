<?php
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

// Проверяем, что пользователь - администратор
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php?section=in_progress");
    exit;
}
track_user_activity('expenses');

// Обработка добавления нового расхода
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $expense_type = trim($_POST['expense_type']);
    $amount = floatval($_POST['amount']);
    $date = $_POST['date'];
    $location = $_POST['location'] ?? '';
    $notes = trim($_POST['notes']);

    if (!empty($expense_type) && $amount > 0 && !empty($date)) {
        $stmt = $conn->prepare("INSERT INTO expenses (type, amount, date, location, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sdsss", $expense_type, $amount, $date, $location, $notes);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            $add_success_message = "Расход успешно добавлен";
        } else {
            $add_error_message = "Ошибка при добавлении расхода: " . $conn->error;
        }
    } else {
        $add_error_message = "Заполните все обязательные поля правильно";
    }
}

// Обработка удаления расхода
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_expense'])) {
    $expense_id = intval($_POST['expense_id']);

    if ($expense_id > 0) {
        $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");
        $stmt->bind_param("i", $expense_id);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            $delete_success_message = "Расход успешно удален";
        } else {
            $delete_error_message = "Ошибка при удалении расхода: " . $conn->error;
        }
    } else {
        $delete_error_message = "Неверный ID расхода";
    }
}

// Обработка фильтрации по дате и типу
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$expense_type_filter = $_GET['expense_type'] ?? '';
$location_filter = $_GET['location'] ?? '';

// Получение всех типов расходов для выпадающего списка
$expense_types = getExpenseTypes($conn);

$locations = [];
$locations_result = $conn->query("SELECT DISTINCT location FROM expenses WHERE location IS NOT NULL AND location != '' ORDER BY location ASC");
if ($locations_result) {
    while ($row = $locations_result->fetch_assoc()) {
        $locations[] = $row['location'];
    }
}

// Получение всех расходов для отображения с фильтрацией
$where_conditions = [];
$params = [];
$types = "";

if (!empty($start_date)) {
    $where_conditions[] = "date >= ?";
    $params[] = $start_date;
    $types .= "s";
}

if (!empty($end_date)) {
    $where_conditions[] = "date <= ?";
    $params[] = $end_date;
    $types .= "s";
}

if (!empty($expense_type_filter)) {
    $where_conditions[] = "type = ?";
    $params[] = $expense_type_filter;
    $types .= "s";
}

if (!empty($location_filter)) {
    $where_conditions[] = "location = ?";
    $params[] = $location_filter;
    $types .= "s";
}

// Build the query
$query = "SELECT * FROM expenses";
if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
}
$query .= " ORDER BY date DESC, created_at DESC";

// Prepare and execute the statement
if (!empty($params)) {
    $expenses_result = $conn->prepare($query);
    $expenses_result->bind_param($types, ...$params);
    $expenses_result->execute();
    $expenses = $expenses_result->get_result();
    $expenses_array = [];
    while ($row = $expenses->fetch_assoc()) {
        $expenses_array[] = $row;
    }
    $expenses = $expenses_array;
} else {
    $expenses_result = $conn->query($query);
    $expenses = [];
    while ($row = $expenses_result->fetch_assoc()) {
        $expenses[] = $row;
    }
}

// Функция для получения списка уникальных типов расходов
function getExpenseTypes($conn)
{
    $query = "SELECT DISTINCT type FROM expenses ORDER BY type";
    $result = $conn->query($query);
    $types = [];
    while ($row = $result->fetch_assoc()) {
        $types[] = $row['type'];
    }

    // Если в базе нет типов расходов, возвращаем часто используемые типы по умолчанию
    if (empty($types)) {
        $default_types = [
            'Аренда',
            'Зарплата',
            'Коммунальные услуги',
            'Оборудование',
            'Материалы',
            'Транспорт',
            'Реклама',
            'Прочее'
        ];
        return $default_types;
    }

    return $types;
}

// Функция для получения расходов по типу
function getExpensesByType($conn, $type, $start_date = '', $end_date = '', $location = '')
{
    $where_conditions = ["type = ?"];
    $params = [$type];
    $types = "s";

    if (!empty($start_date) && !empty($end_date)) {
        $where_conditions[] = "date BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
        $types .= "ss";
    } elseif (!empty($start_date)) {
        $where_conditions[] = "date >= ?";
        $params[] = $start_date;
        $types .= "s";
    } elseif (!empty($end_date)) {
        $where_conditions[] = "date <= ?";
        $params[] = $end_date;
        $types .= "s";
    }

    if (!empty($location)) {
        $where_conditions[] = "location = ?";
        $params[] = $location;
        $types .= "s";
    }

    $query = "SELECT * FROM expenses WHERE " . implode(" AND ", $where_conditions) . " ORDER BY date DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $expenses = [];
    while ($row = $result->fetch_assoc()) {
        $expenses[] = $row;
    }
    $stmt->close();
    return $expenses;
}

// Функция для получения общего количества и суммы расходов
function getTotalExpenses($conn, $start_date = '', $end_date = '', $expense_type = '', $location = '')
{
    $where_conditions = [];
    $params = [];
    $types = "";

    if (!empty($start_date)) {
        $where_conditions[] = "date >= ?";
        $params[] = $start_date;
        $types .= "s";
    }

    if (!empty($end_date)) {
        $where_conditions[] = "date <= ?";
        $params[] = $end_date;
        $types .= "s";
    }

    if (!empty($expense_type)) {
        $where_conditions[] = "type = ?";
        $params[] = $expense_type;
        $types .= "s";
    }

    if (!empty($location)) {
        $where_conditions[] = "location = ?";
        $params[] = $location;
        $types .= "s";
    }

    $query = "SELECT COUNT(*) as count, SUM(amount) as total FROM expenses";
    if (!empty($where_conditions)) {
        $query .= " WHERE " . implode(" AND ", $where_conditions);
    }

    if (!empty($params)) {
        $result = $conn->prepare($query);
        $result->bind_param($types, ...$params);
        $result->execute();
        return $result->get_result()->fetch_assoc();
    } else {
        $result = $conn->query($query);
        return $result->fetch_assoc();
    }
}

// Функция для получения ежедневных расходов
function getDailyExpenses($conn, $days = 30, $start_date = '', $end_date = '', $expense_type = '', $location = '')
{
    $where_conditions = [];
    $params = [];
    $types = "";

    if (!empty($start_date) && !empty($end_date)) {
        $where_conditions[] = "date BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
        $types .= "ss";
    } elseif (!empty($start_date)) {
        $where_conditions[] = "date >= ?";
        $params[] = $start_date;
        $types .= "s";
    } elseif (!empty($end_date)) {
        $where_conditions[] = "date <= ?";
        $params[] = $end_date;
        $types .= "s";
    }

    if (!empty($expense_type)) {
        $where_conditions[] = "type = ?";
        $params[] = $expense_type;
        $types .= "s";
    }

    if (!empty($location)) {
        $where_conditions[] = "location = ?";
        $params[] = $location;
        $types .= "s";
    }

    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

    if (!empty($params)) {
        $stmt = $conn->prepare("
            SELECT DATE(date) as day, SUM(amount) as daily_total
            FROM expenses
            $where_clause
            GROUP BY DATE(date)
            ORDER BY day DESC
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query("
            SELECT DATE(date) as day, SUM(amount) as daily_total
            FROM expenses
            $where_clause
            GROUP BY DATE(date)
            ORDER BY day DESC
        ");
    }
    $daily = [];
    while ($row = $result->fetch_assoc()) {
        $daily[] = $row;
    }
    return $daily;
}

$total_expenses = getTotalExpenses($conn, $start_date, $end_date, $expense_type_filter, $location_filter);
$daily_expenses = getDailyExpenses($conn, 30, $start_date, $end_date, $expense_type_filter, $location_filter);

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление расходами</title>
    <link rel="icon" type="image/jpeg" href="Logo.png">
    <link rel="apple-touch-icon" href="Logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #1c2526;
            background-image: url('https://www.diskzakaz.ru/1/fn.png');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: cover;
            color: #ecf0f1;
            font-size: 14px;
            padding: 10px;
            margin: 0;
            padding-bottom: 60px;
        }
        .container {
            padding: 10px;
            max-width: 100%;
        }
        .metallic-card {
            background: linear-gradient(145deg, rgba(30, 30, 30, 0.9), rgba(50, 50, 50, 0.9));
            backdrop-filter: blur(10px);
            border: 1px solid #95a5a6;
            border-radius: 8px;
            margin-bottom: 10px;
            padding: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            color: #ecf0f1;
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
        .table-container {
            overflow-x: auto;
        }
        .table {
            background: linear-gradient(145deg, rgba(30, 30, 30, 0.9), rgba(50, 50, 50, 0.9));
            width: 100%;
            min-width: 600px;
        }
        .table th, .table td {
            border: 1px solid #95a5a6;
            font-size: 14px;
            padding: 8px;
        }
        .expense-notes {
            white-space: pre-wrap;
            word-break: break-word;
        }
        .total-row {
            font-weight: bold;
            background: linear-gradient(#3498db, #2980b9);
            color: #fff;
        }
        h1 {
            font-size: 20px;
            margin-bottom: 15px;
        }
        h5 {
            font-size: 16px;
        }
        .stats-card {
            background: linear-gradient(#3498db, #2980b9);
            color: #fff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            text-align: center;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
        }
        .stat-label {
            font-size: 14px;
        }
        
        /* Улучшаем видимость меток на мобильных устройствах */
        @media (max-width: 576px) {
            .form-label {
                color: #ffffff;
                font-weight: 500;
            }
            
            .metallic-card .form-label {
                color: #2c3e50;
            }
            
            .card-body .form-label {
                display: block;
                margin-bottom: 5px;
                font-weight: 500;
            }
            
            /* Обеспечиваем контраст для всех меток в форме */
            .form-control + .form-label,
            .form-select + .form-label {
                color: #ffffff;
            }
            
            /* Для карточек с металлическим фоном */
            .metallic-card .form-label {
                color: #2c3e50;
                font-weight: 600;
            }
            
            /* Улучшаем контраст для элементов управления формой */
            .form-control {
                color: #000000;
                background-color: #ffffff;
            }
            
            select.form-control, select.form-select {
                color: #000000;
            }
            
            /* Для темных элементов формы */
            .form-control::placeholder {
                color: #6c757d;
            }
            
            input.form-control, select.form-control, textarea.form-control {
                color: #000000;
            }
            
            .form-control:focus {
                color: #000000;
            }
            
            /* Дополнительные стили для улучшения контраста меток на мобильных устройствах */
            .form-label {
                color: #ffffff !important;
                text-shadow: 0 0 2px rgba(0, 0, 0, 0.5);
            }
            
            .metallic-card .form-label {
                color: #2c3e50 !important;
                text-shadow: none;
            }
            
            .form-group .form-label {
                display: inline-block;
                margin-bottom: 5px;
                font-weight: 600;
                color: #ffffff;
            }
            
            /* Улучшаем контраст для всех элементов формы */
            .form-control, .form-select {
                color: #000000;
                background-color: #ffffff;
                border: 1px solid #ced4da;
            }
            
            /* Обеспечиваем видимость меток в карточках */
            .card .form-label {
                color: #2c3e50;
                font-weight: 600;
            }
        }
        /* Скрываем верхнюю кнопку меню в мобильной версии */
        .nav-toggle {
            display: none !important;
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
            background: linear-gradient(145deg, #2d3436, #1c2526);
            z-index: 1000;
            padding: 5px 0;
            box-shadow: 0 -4px 15px rgba(0, 0, 0.4);
            overflow-x: auto;
            overflow-y: hidden;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .bottom-nav::-webkit-scrollbar {
            display: none;
        }
        .bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #e0e0e0;
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
            .bottom-nav {
                display: flex;
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
        .autocomplete {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        .autocomplete-items {
            position: absolute;
            border: 1px solid #d4d4d4;
            border-bottom: none;
            border-top: none;
            z-index: 99;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 200px;
            overflow-y: auto;
        }
        .autocomplete-items div {
            padding: 10px;
            cursor: pointer;
            background-color: #fff;
            border-bottom: 1px solid #d4d4d4;
        }
        .autocomplete-items div:hover {
            background-color: #e9e9e9;
        }
        .autocomplete-active {
            background-color: DodgerBlue !important;
            color: #ffffff;
        }
    </style>
    <link rel="stylesheet" href="views-global.css">
</head>
<body class="has-top-nav">
    <?php renderUnifiedNavigation('rashody', ['show_top' => true, 'show_bottom' => true, 'show_spacers' => false, 'toggle_label' => '☰ Меню', 'toggle_class' => 'btn nav-toggle d-none d-lg-flex']); ?>
    <div class="container">
        <h1 class="text-center">Управление расходами</h1>
        <div class="text-end mb-2">
            <span>Пользователь: <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Гость'; ?> (<?php echo isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin' ? 'Админ' : 'Пользователь'; ?>)</span>
            <a href="index.php" class="btn btn-secondary btn-sm ms-2">Главная</a>
            <a href="?logout" class="btn btn-danger btn-sm ms-2">Выйти</a>
        </div>
        
        <?php if (isset($delete_success_message)) : ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($delete_success_message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($delete_error_message)) : ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($delete_error_message); ?></div>
        <?php endif; ?>
        
        <!-- Фильтр по дате и типу -->
        <div class="card metallic-card mb-4">
            <div class="card-body">
                <h5>Фильтр</h5>
                <form method="get" class="row g-3">
                    <input type="hidden" name="section" value="expenses">
                    <div class="col-md-4">
                        <label for="start_date" class="form-label">Начальная дата</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $_GET['start_date'] ?? ''; ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="end_date" class="form-label">Конечная дата</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $_GET['end_date'] ?? ''; ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="expense_type_filter" class="form-label">Статья расхода</label>
                        <select class="form-control" id="expense_type_filter" name="expense_type">
                            <option value="">Все статьи</option>
                            <?php foreach ($expense_types as $type) : ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" <?php echo (isset($_GET['expense_type']) && $_GET['expense_type'] == $type) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="location_filter" class="form-label">Локация</label>
                        <select class="form-control" id="location_filter" name="location">
                            <option value="">Все локации</option>
                            <?php foreach ($locations as $location) : ?>
                                <option value="<?php echo htmlspecialchars($location); ?>" <?php echo $location_filter === $location ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($location); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Применить фильтры</button>
                        <a href="expenses.php" class="btn btn-secondary ms-2">Сбросить</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Форма добавления расхода -->
        <div class="card metallic-card mb-4">
            <div class="card-body">
                <h5>Добавить новый расход</h5>
                <form method="post">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="expense_type" class="form-label">Статья расхода *</label>
                            <div class="autocomplete">
                                <input type="text" class="form-control" id="expense_type" name="expense_type" placeholder="Введите статью расхода" required>
                                <div id="expense_type_list" class="autocomplete-items"></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="amount" class="form-label">Сумма *</label>
                            <input type="number" class="form-control" id="amount" name="amount" step="0.01" placeholder="Введите сумму" min="0" required>
                        </div>
                        <div class="col-md-3">
                            <label for="date" class="form-label">Дата *</label>
                            <input type="date" class="form-control" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label for="location" class="form-label">Локация</label>
                            <input type="text" class="form-control" id="location" name="location" list="expenseLocationOptions" placeholder="Введите локацию">
                            <datalist id="expenseLocationOptions">
                                <?php foreach ($locations as $location) : ?>
                                    <option value="<?php echo htmlspecialchars($location); ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="notes" class="form-label">Примечание</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Дополнительная информация" data-autogrow="true"></textarea>
                        </div>
                    </div>
                    <button type="submit" name="add_expense" class="btn btn-primary w-100">Добавить расход</button>
                </form>
            </div>
        </div>
        
        <!-- Таблица расходов -->
                <div class="card metallic-card">
                    <div class="card-body">
                        <h5>Список расходов</h5>
                        <?php if (empty($expenses)) : ?>
                            <p>Нет записей о расходах</p>
                        <?php else : ?>
                            <div class="table-container">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Статья</th>
                                            <th>Сумма</th>
                                            <th>Дата</th>
                                            <th>Локация</th>
                                            <th>Примечание</th>
                                            <th>Создано</th>
                                            <th>Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($expenses as $expense) : ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($expense['type']); ?></td>
                                                <td><?php echo number_format($expense['amount'], 2); ?> ₽</td>
                                                <td><?php echo date('d.m.Y', strtotime($expense['date'])); ?></td>
                                                <td><?php echo htmlspecialchars($expense['location'] ?? ''); ?></td>
                                                <td><div class="expense-notes"><?php echo htmlspecialchars($expense['notes'] ?? ''); ?></div></td>
                                                <td><?php echo date('d.m.Y H:i', strtotime($expense['created_at'])); ?></td>
                                                <td>
                                                    <form method="post" style="display:inline;" onsubmit="return confirm('Вы уверены, что хотите удалить этот расход?');">
                                                        <input type="hidden" name="expense_id" value="<?php echo $expense['id']; ?>">
                                                        <button type="submit" name="delete_expense" class="btn btn-danger btn-sm">Удалить</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="total-row">
                                            <td colspan="1">Итого</td>
                                            <td><?php echo number_format($total_expenses['total'], 2); ?> ₽</td>
                                            <td colspan="5"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Autocomplete functionality for expense types
        document.addEventListener('DOMContentLoaded', function() {
            function autoGrowTextarea(textarea) {
                if (!textarea) return;
                textarea.style.height = 'auto';
                textarea.style.overflow = 'hidden';
                textarea.style.resize = 'none';
                textarea.style.height = textarea.scrollHeight + 'px';
            }

            function initAutoGrow() {
                const textareas = document.querySelectorAll('textarea[data-autogrow="true"]');
                textareas.forEach(textarea => {
                    autoGrowTextarea(textarea);
                    textarea.addEventListener('input', function() {
                        autoGrowTextarea(textarea);
                    });
                });
            }

            initAutoGrow();

            const expenseTypeInput = document.getElementById('expense_type');
            const expenseTypeList = document.getElementById('expense_type_list');
            let expenseTypes = <?php echo json_encode($expense_types); ?>;
            let currentIndex = -1;
            let isFocused = false;
            
            // Initialize autocomplete
            function initAutocomplete() {
                if (!expenseTypeInput || !expenseTypeList) return;
                
                // Clear previous event listeners
                expenseTypeInput.removeEventListener('input', handleInput);
                expenseTypeInput.removeEventListener('keydown', handleKeydown);
                expenseTypeInput.removeEventListener('focus', handleFocus);
                expenseTypeInput.removeEventListener('blur', handleBlur);
                
                // Add event listeners
                expenseTypeInput.addEventListener('input', handleInput);
                expenseTypeInput.addEventListener('keydown', handleKeydown);
                expenseTypeInput.addEventListener('focus', handleFocus);
                expenseTypeInput.addEventListener('blur', handleBlur);
            }
            
            function handleInput(e) {
                const value = e.target.value.toLowerCase();
                currentIndex = -1;
                
                if (value.length === 0) {
                    hideSuggestions();
                    return;
                }
                
                // Filter expense types
                const filteredTypes = expenseTypes.filter(type =>
                    type.toLowerCase().includes(value)
                );
                
                if (filteredTypes.length > 0) {
                    showSuggestions(filteredTypes);
                } else {
                    hideSuggestions();
                }
            }
            
            function handleKeydown(e) {
                const items = expenseTypeList.getElementsByTagName('div');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    currentIndex = Math.min(currentIndex + 1, items.length - 1);
                    updateActiveItem(items);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    currentIndex = Math.max(currentIndex - 1, -1);
                    updateActiveItem(items);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (currentIndex >= 0 && items[currentIndex]) {
                        selectItem(items[currentIndex].textContent);
                    }
                } else if (e.key === 'Escape') {
                    hideSuggestions();
                }
            }
            
            function handleFocus() {
                isFocused = true;
                // Show suggestions when focused (optional)
            }
            
            function handleBlur() {
                isFocused = false;
                // Hide suggestions with delay to allow click events to register
                setTimeout(() => {
                    if (!isFocused) {
                        hideSuggestions();
                    }
                }, 150);
            }
            
            function updateActiveItem(items) {
                // Remove active class from all items
                for (let i = 0; i < items.length; i++) {
                    items[i].classList.remove('autocomplete-active');
                }
                
                // Add active class to current item
                if (currentIndex >= 0 && items[currentIndex]) {
                    items[currentIndex].classList.add('autocomplete-active');
                    items[currentIndex].scrollIntoView({ block: 'nearest' });
                }
            }
            
            function showSuggestions(types) {
                expenseTypeList.innerHTML = '';
                
                types.forEach(type => {
                    const div = document.createElement('div');
                    div.textContent = type;
                    div.addEventListener('click', function() {
                        selectItem(type);
                    });
                    expenseTypeList.appendChild(div);
                });
                
                expenseTypeList.style.display = 'block';
            }
            
            function hideSuggestions() {
                expenseTypeList.style.display = 'none';
            }
            
            function selectItem(value) {
                expenseTypeInput.value = value;
                hideSuggestions();
                expenseTypeInput.focus();
            }
            
            // Initialize when page loads
            initAutocomplete();
            
            // Handle click outside to close suggestions
            document.addEventListener('click', function(e) {
                if (e.target !== expenseTypeInput && !expenseTypeInput.contains(e.target) &&
                    e.target !== expenseTypeList && !expenseTypeList.contains(e.target)) {
                    hideSuggestions();
                }
            });
        });
    </script>
</body>
</html>


