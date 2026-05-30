<?php
require_once 'db.php';

// Проверка авторизации
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
track_user_activity('zp');

// Инициализация переменной для ошибки
$error_message = '';

// Параметры сортировки (берутся из $_GET, если существуют)
$sort_executor = $_GET['sort_executor'] ?? '';
$sort_status = $_GET['sort_status'] ?? '';
$sort_date_from = $_GET['sort_date_from'] ?? '';
$sort_date_to = $_GET['sort_date_to'] ?? '';

// Функция для создания строки запроса с сохранением фильтров
function buildQueryString($exclude = [])
{
    $params = [];
    if (!in_array('sort_executor', $exclude) && isset($_GET['sort_executor']) && $_GET['sort_executor'] !== '') {
        $params['sort_executor'] = $_GET['sort_executor'];
    }
    if (!in_array('sort_status', $exclude) && isset($_GET['sort_status']) && $_GET['sort_status'] !== '') {
        $params['sort_status'] = $_GET['sort_status'];
    }
    if (!in_array('sort_date_from', $exclude) && isset($_GET['sort_date_from']) && $_GET['sort_date_from'] !== '') {
        $params['sort_date_from'] = $_GET['sort_date_from'];
    }
    if (!in_array('sort_date_to', $exclude) && isset($_GET['sort_date_to']) && $_GET['sort_date_to'] !== '') {
        $params['sort_date_to'] = $_GET['sort_date_to'];
    }
    // Всегда добавляем apply_filters, чтобы фильтры применились после перенаправления
    $params['apply_filters'] = 1;
    return $params ? http_build_query($params) : '';
}

// Функция для получения уникальных исполнителей
function getUniqueExecutors()
{
    global $conn;
    $result = $conn->query("SELECT DISTINCT executor FROM salary_records ORDER BY executor ASC");
    $executors = [];
    while ($row = $result->fetch_assoc()) {
        $executors[] = $row['executor'];
    }
    return $executors;
}

// Обработка формы создания записи
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_salary_record'])) {
    $execution_date = $_POST['execution_date'] ?? null;
    $executor = $_SESSION['username']; // Исполнитель всегда текущий пользователь
    $amount = floatval($_POST['amount'] ?? 0);
    $comments = $_POST['comments'] ?? '';
    $status = $_POST['status'] ?? 'waiting_payment';

    // Проверка обязательных полей
    if (!$executor || !$execution_date || $amount <= 0) {
        $error_message = 'Заполните все обязательные поля (Исполнитель, Дата выполнения, Стоимость).';
    } else {
        // Вызов функции сохранения
        $data = $_POST;
        $data['executor'] = $executor;
        $data['amount'] = $amount;
        $result = addSalaryRecord($data, $_FILES);
        if ($result) {
            $query_string = buildQueryString();
            header("Location: zp.php" . ($query_string ? "?$query_string" : ""));
            exit;
        } else {
            global $conn;
            $error_message = 'Ошибка при сохранении записи: ' . htmlspecialchars($conn->error);
        }
    }
}

// Обработка формы редактирования записи
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_salary_record'])) {
    $record_id = intval($_POST['record_id']);
    $execution_date = $_POST['execution_date'] ?? null;
    $executor = $_POST['executor'] ?? null;
    $amount = floatval($_POST['amount'] ?? 0);
    $comments = $_POST['comments'] ?? '';
    $status = $_POST['status'] ?? 'waiting_payment';

    // Проверка обязательных полей
    if (!$executor || !$execution_date || $amount <= 0) {
        $error_message = 'Заполните все обязательные поля (Исполнитель, Дата выполнения, Стоимость).';
    } else {
        // Вызов функции обновления
        $data = $_POST;
        $data['amount'] = $amount;
        $result = updateSalaryRecord($record_id, $data, $_FILES);
        if ($result) {
            $query_string = buildQueryString();
            header("Location: zp.php" . ($query_string ? "?$query_string" : ""));
            exit;
        } else {
            global $conn;
            $error_message = 'Ошибка при обновлении записи: ' . htmlspecialchars($conn->error);
        }
    }
}

// Обработка изменения статуса
if (isset($_GET['update_status']) && isset($_GET['record_id'])) {
    $record_id = intval($_GET['record_id']);
    $new_status = $_GET['update_status'];
    error_log("Attempting to update status for record_id=$record_id to status=$new_status with GET: " . json_encode($_GET));
    if (in_array($new_status, ['waiting_payment', 'paid'])) {
        if (function_exists('updateSalaryRecordStatus')) {
            $result = updateSalaryRecordStatus($record_id, $new_status);
            if ($result) {
                error_log("Successfully updated status for record_id=$record_id with filters: " . json_encode($_GET));
                $query_string = buildQueryString();
                header("Location: zp.php" . ($query_string ? "?$query_string" : ""));
                exit;
            } else {
                global $conn;
                $error_message = 'Ошибка при обновлении статуса: ' . htmlspecialchars($conn->error);
                error_log("Failed to update status for record_id=$record_id: " . $conn->error);
            }
        } else {
            $error_message = 'Функция updateSalaryRecordStatus не найдена.';
            error_log("Function updateSalaryRecordStatus not found.");
        }
    } else {
        $error_message = 'Недопустимый статус: ' . htmlspecialchars($new_status);
        error_log("Invalid status provided: $new_status");
    }
}

// Получение записей с учетом сортировки
function getFilteredSalaryRecords($executor = '', $status = '', $date_from = '', $date_to = '')
{
    global $conn;
    $username = $_SESSION['username'];
    $is_admin = $_SESSION['user_role'] === 'admin';

    $query = "SELECT * FROM salary_records WHERE 1=1";
    $params = [];
    $types = '';

    if (!$is_admin) {
        $query .= " AND executor = ?";
        $params[] = $username;
        $types .= 's';
    }

    if ($executor) {
        $query .= " AND executor LIKE ?";
        $params[] = "%$executor%";
        $types .= 's';
    }

    if ($status) {
        $query .= " AND status = ?";
        $params[] = $status;
        $types .= 's';
    }

    if ($date_from) {
        $query .= " AND execution_date >= ?";
        $params[] = $date_from;
        $types .= 's';
    }

    if ($date_to) {
        $query .= " AND execution_date <= ?";
        $params[] = $date_to;
        $types .= 's';
    }

    $query .= " ORDER BY execution_date DESC, id DESC";

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $records = [];
    $total_amount = 0;
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
        $total_amount += floatval($row['amount'] ?? 0);
    }

    $stmt->close();
    return ['records' => $records, 'total_amount' => $total_amount];
}

$records_data = getFilteredSalaryRecords($sort_executor, $sort_status, $sort_date_from, $sort_date_to);
$records = $records_data['records'];
$total_amount = $records_data['total_amount'];
$executors = getUniqueExecutors(); // Получение списка уникальных исполнителей
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Заработная плата</title>
    <link rel="icon" type="image/jpeg" href="Logo.png">
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
            font-size: 14px;
            padding: 10px;
            padding-bottom: 60px; /* Добавляем отступ снизу для нижней навигации */
        }
        .container {
            padding: 10px;
            max-width: 100%;
        }
        .metallic-card {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.96), rgba(245, 247, 250, 0.96));
            backdrop-filter: blur(10px);
            border: 1px solid #95a5a6;
            border-radius: 8px;
            margin-bottom: 10px;
            padding: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            color: #1f2a37;
            min-height: 250px; /* Устанавливаем фиксированную минимальную высоту */
            display: flex;
            flex-direction: column;
        }
        .card-content {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .card-header {
            flex: 0 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .card-body {
            flex: 1 1 auto;
            overflow-y: auto; /* Добавляем прокрутку, если контент превышает высоту */
            display: flex;
            flex-direction: column;
        }
        .photos-container-wrapper {
            flex: 0 0 auto;
            overflow: hidden;
        }
        .action-buttons {
            flex: 0 0 auto;
        }
        .metallic-card:hover {
            transform: translateY(-2px) translateX(0);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .nav-tabs {
            background: linear-gradient(#f1f3f5, #e2e8f0);
            border: none;
            border-radius: 8px;
            padding: 5px;
            overflow-x: auto;
            white-space: nowrap;
            flex-wrap: nowrap;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .nav-tabs .nav-link {
            color: #1f2a37;
            padding: 8px 12px;
            font-size: 12px;
            border-radius: 5px;
            transition: background 0.3s ease;
        }
        .nav-tabs .nav-link.active {
            background: linear-gradient(#3498db, #2980b9);
            color: #fff;
        }
        .nav-tabs .nav-link:hover {
            background: rgba(255,255,255,0.1);
        }
        .form-control {
            background: #2c3e50;
            border: 1px solid #7f8c8d;
            border-radius: 5px;
            padding: 8px;
            font-size: 12px;
            color: #ffffff;
        }
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
        }
        .btn {
            padding: 8px 12px;
            font-size: 12px;
            border-radius: 5px;
            border: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: transform 0.2s ease, background 0.3s ease;
        }
        .btn:hover {
            transform: scale(1.05);
        }
        .btn-primary {
            background: linear-gradient(#3498db, #2980b9);
            color: #fff;
        }
        .btn-primary:hover {
            background: linear-gradient(#2980b9, #1f618d);
        }
        .btn-success {
            background: linear-gradient(#2ecc71, #27ae60);
            color: #fff;
        }
        .btn-warning {
            background: linear-gradient(#f1c40f, #e67e22);
            color: #fff;
        }
        .btn:disabled {
            background: linear-gradient(#95a5a6, #7f8c8d);
            cursor: not-allowed;
            transform: none;
        }
        .photo-preview {
            height: 100px;
            width: auto;
            object-fit: contain;
            border: 1px solid #95a5a6;
            border-radius: 4px;
            cursor: pointer;
            transition: transform 0.2s ease;
            margin: 0 5px;
        }
        .photo-preview:hover {
            transform: scale(1.05);
        }
        .photos-container {
            display: flex;
            flex-direction: row;
            overflow-x: auto;
            padding: 10px 0;
            white-space: nowrap;
            width: 100%;
            gap: 5px;
            max-height: 120px; /* Ограничиваем высоту контейнера фотографий */
        }
        .collapse-content {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.96), rgba(245, 247, 250, 0.96));
            backdrop-filter: blur(5px);
            color: #1f2a37;
            padding: 8px;
            border-radius: 5px;
            font-size: 12px;
            border: 1px solid #95a5a6;
        }
        .collapse-content .info-row {
            margin: 2px 0;
            padding: 2px 0;
            border-bottom: 1px solid #95a5a6;
        }
        .collapse-content .info-row:last-child {
            border-bottom: none;
        }
        .info-divider {
            border: 0;
            height: 1px;
            background: #95a5a6;
            margin: 2px 0;
        }
        h1 {
            font-size: 22px;
            margin-bottom: 10px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        h5 {
            font-size: 16px;
            margin-bottom: 8px;
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
        
        .alert {
            margin-bottom: 15px;
            font-size: 14px;
            background: #e74c3c;
            color: white;
            border: none;
        }
        .existing-photo-checkbox {
            margin-right: 10px;
        }
        .existing-photo-preview {
            height: 60px;
            width: auto;
            margin: 5px;
            border: 1px solid #95a5a6;
            border-radius: 4px;
        }
        @media (max-width: 768px) {
            .container { padding: 5px; }
            .nav-tabs { padding: 3px; }
            .nav-tabs .nav-link { padding: 6px 10px; font-size: 11px; }
            .btn { padding: 6px 10px; font-size: 11px; }
            .form-control { font-size: 11px; padding: 6px; }
            .photo-preview { height: 80px; }
            h1 { font-size: 20px; }
            h5 { font-size: 14px; }
            .row.mb-2 > div {
                margin-bottom: 10px;
            }
            .bottom-nav {
                display: flex;
            }
            .metallic-card {
                min-height: 220px; /* Уменьшаем высоту для мобильных устройств */
            }
        }
        @media (max-width: 576px) {
            .photo-preview { height: 60px; }
            .btn { padding: 5px 8px; font-size: 10px; }
            .form-control { font-size: 10px; padding: 5px; }
            h1 { font-size: 18px; }
            h5 { font-size: 12px; }
            .bottom-nav {
                display: flex;
            }
            .metallic-card {
                min-height: 200px; /* Еще меньше для маленьких экранов */
            }
        }
    </style>
    <link rel="stylesheet" href="views-global.css">
</head>
<body>
    <?php renderUnifiedNavigation('zp', ['show_top' => true, 'show_bottom' => true, 'show_spacers' => false, 'toggle_label' => '☰ Меню', 'toggle_class' => 'btn nav-toggle d-none d-lg-flex']); ?>
    <div class="container">
        <h1 class="text-center">Заработная плата</h1>
        <div class="card metallic-card">
            <div class="card-body">
                <h5 class="card-title">Правила расчета зарплаты</h5>
                <div class="collapse" id="salaryRules">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Услуга</th>
                                <th>Правило</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Покрасить диск</td>
                                <td>250 руб.</td>
                            </tr>
                            <tr>
                                <td>Правка</td>
                                <td>25%</td>
                            </tr>
                            <tr>
                                <td>Шиномонтаж</td>
                                <td>25%</td>
                            </tr>
                            <tr>
                                <td>Песочка диск</td>
                                <td>250 руб.</td>
                            </tr>
                            <tr>
                                <td>Сварка</td>
                                <td>25%</td>
                            </tr>
                            <tr>
                                <td>Проточка тормозных дисков</td>
                                <td>25%</td>
                            </tr>
                            <tr>
                                <td>ЧПУ Алмазная проточка</td>
                                <td>250 руб.</td>
                            </tr>
                            <tr>
                                <td>Покраска суппортов</td>
                                <td>250 руб.</td>
                            </tr>
                            <tr>
                                <td>Покраска ступиц</td>
                                <td>250 руб.</td>
                            </tr>
                            <tr>
                                <td>Покраска насадок глушителя</td>
                                <td>250 руб.</td>
                            </tr>
                            <tr>
                                <td>Покраска поводков</td>
                                <td>250 руб.</td>
                            </tr>
                            <tr>
                                <td>продажа болтов на диски</td>
                                <td>25%</td>
                            </tr>
                            <tr>
                                <td>Аргоновая наварка</td>
                                <td>25%</td>
                            </tr>
                            <tr>
                                <td>Димет Алюминиевое напыление</td>
                                <td>250 руб.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#salaryRules" aria-expanded="false" aria-controls="salaryRules">
                    Показать правила
                </button>
            </div>
        </div>

        <div class="card metallic-card">
            <div class="card-body">
                <h5 class="card-title">Новая запись З/П</h5>
                <?php if ($error_message) : ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
                <form method="post" enctype="multipart/form-data" id="salaryForm">
                    <div class="mb-2">
                        <label for="executor" class="form-label">Исполнитель *</label>
                        <input type="text" class="form-control" id="executor" name="executor" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" readonly required>
                    </div>
                    <div class="mb-2">
                        <label for="execution_date" class="form-label">Дата выполнения *</label>
                        <input type="date" class="form-control" id="execution_date" name="execution_date" required>
                    </div>
                    <div class="mb-2">
                        <label for="amount" class="form-label">Стоимость (руб.) *</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="amount" name="amount" required>
                    </div>
                    <div class="mb-2">
                        <label for="photos" class="form-label">Фотографии *</label>
                        <input type="file" class="form-control" id="photos" name="photos[]" multiple accept="image/*" required>
                    </div>
                    <div class="mb-2">
                        <label for="comments" class="form-label">Комментарий</label>
                        <textarea class="form-control" id="comments" name="comments" rows="3"></textarea>
                    </div>
                    <div class="mb-2">
                        <label for="status" class="form-label">Статус</label>
                        <select class="form-control" id="status" name="status">
                            <option value="waiting_payment">Жду оплаты</option>
                            <option value="paid">Выплачено</option>
                        </select>
                    </div>
                    <button type="submit" name="create_salary_record" class="btn btn-primary" id="submitButton" disabled>Сохранить</button>
                </form>
            </div>
        </div>

        <div class="card metallic-card">
            <div class="card-body">
                <h5 class="card-title">Фильтры</h5>
                <form method="get" id="filterForm">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <label for="sort_executor" class="form-label">Исполнитель</label>
                            <input type="text" class="form-control" id="sort_executor" name="sort_executor" list="executors_list" value="<?php echo htmlspecialchars($sort_executor); ?>">
                            <datalist id="executors_list">
                                <?php foreach ($executors as $executor) : ?>
                                    <option value="<?php echo htmlspecialchars($executor); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="sort_status" class="form-label">Статус</label>
                            <select class="form-control" id="sort_status" name="sort_status">
                                <option value="">Все</option>
                                <option value="waiting_payment" <?php echo $sort_status === 'waiting_payment' ? 'selected' : ''; ?>>Жду оплаты</option>
                                <option value="paid" <?php echo $sort_status === 'paid' ? 'selected' : ''; ?>>Выплачено</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="sort_date_from" class="form-label">Дата от</label>
                            <input type="date" class="form-control" id="sort_date_from" name="sort_date_from" value="<?php echo htmlspecialchars($sort_date_from); ?>">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="sort_date_to" class="form-label">Дата до</label>
                            <input type="date" class="form-control" id="sort_date_to" name="sort_date_to" value="<?php echo htmlspecialchars($sort_date_to); ?>">
                        </div>
                    </div>
                    <button type="submit" name="apply_filters" class="btn btn-primary mt-2">Применить</button>
                    <a href="zp.php?reset=1" class="btn btn-warning mt-2">Сбросить</a>
                </form>
            </div>
        </div>

        <div class="card metallic-card">
            <div class="card-body">
                <h5 class="card-title">Записи З/П</h5>
                <?php if (empty($records)) : ?>
                    <p>Нет записей</p>
                <?php else : ?>
                    <?php foreach ($records as $record) : ?>
                        <div class="card mb-2 metallic-card" id="record<?php echo htmlspecialchars($record['id']); ?>">
                            <div class="card-body">
                                <div class="order-header">
                                    <div class="client-info">
                                        <strong>#<?php echo htmlspecialchars($record['id']); ?> <?php echo htmlspecialchars($record['executor'] ?? 'Не указан'); ?></strong>
                                        <hr class="info-divider">
                                        <small class="text-muted">Стоимость: <?php echo number_format($record['amount'] ?? 0, 2); ?> руб.</small>
                                    </div>
                                </div>
                                <?php if (!empty($record['photos'])) : ?>
                                    <div class="photos-container">
                                        <?php
                                        $photos = array_values(array_filter(array_map('resolvePhotoPath', explode(',', $record['photos'])), function ($photo) {
                                            return $photo !== '' && file_exists($photo);
                                        }));
                                        foreach ($photos as $photo) : ?>
                                            <img src="<?php echo htmlspecialchars($photo); ?>" class="photo-preview" onclick="viewPhoto('<?php echo htmlspecialchars($photo); ?>')" tabindex="0" onkeydown="if(event.key === 'Enter') viewPhoto('<?php echo htmlspecialchars($photo); ?>')">
                                        <?php endforeach; ?>
                                    </div>
                                <?php else : ?>
                                    <div style="height: 30px;"></div>
                                <?php endif; ?>
                                <div class="action-buttons">
                                    <button class="btn btn-info details-toggle" data-bs-toggle="collapse" data-bs-target="#recordDetails<?php echo htmlspecialchars($record['id']); ?>">Подробности</button>
                                </div>
                                <div class="collapse mt-2" id="recordDetails<?php echo htmlspecialchars($record['id']); ?>">
                                    <div class="collapse-content">
                                        <p class="info-row"><strong>Статус:</strong> <?php echo $record['status'] === 'waiting_payment' ? 'Жду оплаты' : 'Выплачено'; ?></p>
                                        <p class="info-row"><strong>Дата выполнения:</strong> <?php echo date('d.m.Y', strtotime($record['execution_date'])); ?></p>
                                        <p class="info-row"><strong>Комментарий:</strong> <?php echo nl2br(htmlspecialchars($record['comments'] ?? 'не указан')); ?></p>
                                        <p class="info-row"><strong>Создано:</strong> <?php echo date('d.m.Y H:i', strtotime($record['created_at'])); ?></p>
                                        <div class="info-row">
                                            <button class="btn btn-primary btn-sm" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($record)); ?>)">Редактировать</button>
                                            <?php if ($_SESSION['user_role'] === 'admin' && $record['status'] === 'waiting_payment') : ?>
                                                <?php $query_string = buildQueryString(); ?>
                                                <a href="?record_id=<?php echo htmlspecialchars($record['id']); ?>&update_status=paid<?php echo $query_string ? '&' . htmlspecialchars($query_string) : ''; ?>" class="btn btn-success btn-sm ms-2" onclick="return confirm('Изменить статус на Выплачено?');">Выплачено</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="alert alert-info">
                        <strong>Общая сумма:</strong> <?php echo number_format($total_amount, 2); ?> руб.
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Модальное окно для просмотра фото -->
    <div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="photoModalLabel">Просмотр фото</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalPhoto" src="" class="img-fluid" alt="Фото записи">
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно для редактирования записи -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Редактировать запись З/П</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" enctype="multipart/form-data" id="editForm">
                        <input type="hidden" name="record_id" id="edit_record_id">
                        <div class="mb-2">
                            <label for="edit_executor" class="form-label">Исполнитель *</label>
                            <input type="text" class="form-control" id="edit_executor" name="executor" required <?php echo $_SESSION['user_role'] !== 'admin' ? 'readonly' : ''; ?>>
                        </div>
                        <div class="mb-2">
                            <label for="edit_execution_date" class="form-label">Дата выполнения *</label>
                            <input type="date" class="form-control" id="edit_execution_date" name="execution_date" required>
                        </div>
                        <div class="mb-2">
                            <label for="edit_amount" class="form-label">Стоимость (руб.) *</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="edit_amount" name="amount" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Текущие фотографии</label>
                            <div id="existing_photos_container" class="photos-container"></div>
                        </div>
                        <div class="mb-2">
                            <label for="edit_photos" class="form-label">Новые фотографии</label>
                            <input type="file" class="form-control" id="edit_photos" name="photos[]" multiple accept="image/*">
                        </div>
                        <div class="mb-2">
                            <label for="edit_comments" class="form-label">Комментарий</label>
                            <textarea class="form-control" id="edit_comments" name="comments" rows="3"></textarea>
                        </div>
                        <div class="mb-2">
                            <label for="edit_status" class="form-label">Статус</label>
                            <select class="form-control" id="edit_status" name="status">
                                <option value="waiting_payment">Жду оплаты</option>
                                <option value="paid">Выплачено</option>
                            </select>
                        </div>
                        <button type="submit" name="update_salary_record" class="btn btn-primary">Сохранить</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewPhoto(src) {
            document.getElementById('modalPhoto').src = src;
            const modal = new bootstrap.Modal(document.getElementById('photoModal'));
            modal.show();
        }

        // Открытие календаря при клике на поля даты
        document.getElementById('execution_date').addEventListener('click', function() {
            this.showPicker();
        });
        document.getElementById('sort_date_from').addEventListener('click', function() {
            this.showPicker();
        });
        document.getElementById('sort_date_to').addEventListener('click', function() {
            this.showPicker();
        });

        // Валидация формы создания записи
        document.addEventListener('DOMContentLoaded', function() {
            const executionDateInput = document.getElementById('execution_date');
            const amountInput = document.getElementById('amount');
            const photosInput = document.getElementById('photos');
            const submitButton = document.getElementById('submitButton');

            function validateForm() {
                const isDateFilled = executionDateInput.value !== '';
                const isAmountFilled = amountInput.value > 0;
                const arePhotosSelected = photosInput.files.length > 0;
                submitButton.disabled = !(isDateFilled && isAmountFilled && arePhotosSelected);
            }

            executionDateInput.addEventListener('change', validateForm);
            amountInput.addEventListener('input', validateForm);
            photosInput.addEventListener('change', validateForm);

            validateForm();
        });

        // Открытие модального окна для редактирования
        function openEditModal(record) {
            document.getElementById('edit_record_id').value = record.id;
            document.getElementById('edit_executor').value = record.executor;
            document.getElementById('edit_execution_date').value = record.execution_date;
            document.getElementById('edit_amount').value = parseFloat(record.amount).toFixed(2);
            document.getElementById('edit_comments').value = record.comments || '';
            document.getElementById('edit_status').value = record.status;

            // Очистка контейнера существующих фотографий
            const photosContainer = document.getElementById('existing_photos_container');
            photosContainer.innerHTML = '';

            // Отображение существующих фотографий
            if (record.photos) {
                record.photos.split(',').forEach((photo, index) => {
                    if (photo) {
                        const photoDiv = document.createElement('div');
                        photoDiv.innerHTML = `
                            <input type="checkbox" class="existing-photo-checkbox" name="existing_photos[]" value="${photo}" checked>
                            <img src="${photo}" class="existing-photo-preview" onclick="viewPhoto('${photo}')">
                        `;
                        photosContainer.appendChild(photoDiv);
                    }
                });
            }

            const modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal.show();
        }

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



