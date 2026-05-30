<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (ob_get_length()) {
    ob_clean();
}

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

function shift_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function shift_require_admin(): void
{
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        shift_json(['ok' => false, 'error' => 'Доступ разрешен только администратору.'], 403);
    }
}

function shift_text($value, int $max = 255): string
{
    $text = trim((string)($value ?? ''));
    return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
}

function shift_names($value): array
{
    $source = is_array($value) ? $value : [];
    $names = [];
    foreach ($source as $name) {
        $text = shift_text($name, 120);
        if ($text !== '') {
            $names[] = $text;
        }
    }
    return array_values(array_unique($names));
}

function shift_ensure_table(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS zakaz_shifts (
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
        INDEX idx_zakaz_shifts_status_date (status, shift_date),
        INDEX idx_zakaz_shifts_opened_at (opened_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$conn->query($sql)) {
        throw new RuntimeException('Не удалось создать таблицу смен: ' . $conn->error);
    }
}

function shift_row(array $row): array
{
    $tireWorkers = json_decode((string)($row['tire_workers_json'] ?? '[]'), true);
    $argonWorkers = json_decode((string)($row['argon_workers_json'] ?? '[]'), true);
    $snapshot = json_decode((string)($row['snapshot_json'] ?? ''), true);
    return [
        'id' => (int)$row['id'],
        'shift_date' => (string)$row['shift_date'],
        'opened_at' => (string)$row['opened_at'],
        'closed_at' => (string)($row['closed_at'] ?? ''),
        'status' => (string)$row['status'],
        'tire_workers' => is_array($tireWorkers) ? $tireWorkers : [],
        'argon_workers' => is_array($argonWorkers) ? $argonWorkers : [],
        'admin_name' => (string)($row['admin_name'] ?? ''),
        'opened_by_username' => (string)($row['opened_by_username'] ?? ''),
        'closed_by_username' => (string)($row['closed_by_username'] ?? ''),
        'closed_auto' => (int)($row['closed_auto'] ?? 0) === 1,
        'snapshot' => is_array($snapshot) ? $snapshot : null,
    ];
}

function shift_auto_close_old(mysqli $conn): void
{
    $result = $conn->query("SELECT id, shift_date FROM zakaz_shifts WHERE status = 'open' AND shift_date < CURDATE()");
    if (!$result) return;
    while ($row = $result->fetch_assoc()) {
        $id = (int)$row['id'];
        $closedAt = $row['shift_date'] . ' 23:59:59';
        $username = 'автоматически';
        $stmt = $conn->prepare("UPDATE zakaz_shifts SET status = 'closed', closed_at = ?, closed_auto = 1, closed_by_username = ? WHERE id = ? AND status = 'open'");
        if ($stmt) {
            $stmt->bind_param('ssi', $closedAt, $username, $id);
            $stmt->execute();
            $stmt->close();
        }
    }
}

function shift_fetch(mysqli $conn): array
{
    shift_auto_close_old($conn);
    $result = $conn->query('SELECT * FROM zakaz_shifts ORDER BY opened_at DESC, id DESC LIMIT 300');
    if (!$result) {
        throw new RuntimeException('Не удалось загрузить архив смен.');
    }
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = shift_row($row);
    }
    return $rows;
}

try {
    global $conn;
    shift_ensure_table($conn);
    $action = (string)($_GET['action'] ?? 'state');

    if ($action === 'state' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $shifts = shift_fetch($conn);
        $openShift = null;
        foreach ($shifts as $shift) {
            if ($shift['status'] === 'open') {
                $openShift = $shift;
                break;
            }
        }
        shift_json(['ok' => true, 'open_shift' => $openShift, 'shifts' => $shifts]);
    }

    if ($action === 'open' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        shift_require_admin();
        shift_auto_close_old($conn);

        $payload = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($payload)) shift_json(['ok' => false, 'error' => 'Некорректный JSON.'], 400);

        $tireWorkers = shift_names($payload['tire_workers'] ?? []);
        $argonWorkers = shift_names($payload['argon_workers'] ?? []);
        $adminName = shift_text($payload['admin_name'] ?? '', 255);
        if (!$tireWorkers && !$argonWorkers && $adminName === '') {
            shift_json(['ok' => false, 'error' => 'Укажите, кто вышел на смену.'], 400);
        }

        $existing = $conn->query("SELECT * FROM zakaz_shifts WHERE status = 'open' ORDER BY opened_at DESC LIMIT 1");
        $row = $existing ? $existing->fetch_assoc() : null;
        if ($row) shift_json(['ok' => true, 'shift' => shift_row($row), 'already_open' => true]);

        $tireJson = json_encode($tireWorkers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $argonJson = json_encode($argonWorkers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $shiftDate = date('Y-m-d');
        $openedAt = date('Y-m-d H:i:s');
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $username = shift_text($_SESSION['username'] ?? 'admin', 50);

        $stmt = $conn->prepare('INSERT INTO zakaz_shifts (shift_date, opened_at, tire_workers_json, argon_workers_json, admin_name, opened_by, opened_by_username) VALUES (?, ?, ?, ?, ?, ?, ?)');
        if (!$stmt) throw new RuntimeException('Ошибка подготовки открытия смены.');
        $stmt->bind_param('sssssis', $shiftDate, $openedAt, $tireJson, $argonJson, $adminName, $userId, $username);
        if (!$stmt->execute()) throw new RuntimeException('Не удалось открыть смену.');
        $shiftId = (int)$conn->insert_id;
        $stmt->close();

        $result = $conn->query('SELECT * FROM zakaz_shifts WHERE id = ' . $shiftId . ' LIMIT 1');
        shift_json(['ok' => true, 'shift' => shift_row($result->fetch_assoc())]);
    }

    if ($action === 'close' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        shift_require_admin();

        $payload = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($payload)) shift_json(['ok' => false, 'error' => 'Некорректный JSON.'], 400);

        $shiftId = max(0, (int)($payload['id'] ?? 0));
        $snapshot = isset($payload['snapshot']) && is_array($payload['snapshot']) ? $payload['snapshot'] : null;
        $snapshotJson = $snapshot ? json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $closedAt = date('Y-m-d H:i:s');
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $username = shift_text($_SESSION['username'] ?? 'admin', 50);

        $stmt = $conn->prepare("UPDATE zakaz_shifts SET status = 'closed', closed_at = ?, closed_by = ?, closed_by_username = ?, snapshot_json = ? WHERE id = ? AND status = 'open'");
        if (!$stmt) throw new RuntimeException('Ошибка подготовки закрытия смены.');
        $stmt->bind_param('sissi', $closedAt, $userId, $username, $snapshotJson, $shiftId);
        if (!$stmt->execute()) throw new RuntimeException('Не удалось закрыть смену.');
        $affected = $stmt->affected_rows;
        $stmt->close();
        if ($affected <= 0) shift_json(['ok' => false, 'error' => 'Смена не найдена или уже закрыта.'], 404);

        shift_json(['ok' => true, 'shifts' => shift_fetch($conn)]);
    }

    shift_json(['ok' => false, 'error' => 'Метод или action не поддерживается.'], 405);
} catch (Throwable $e) {
    error_log('shift_api.php: ' . $e->getMessage());
    shift_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
