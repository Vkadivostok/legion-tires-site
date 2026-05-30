<?php
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$json = json_decode($raw, true);
$action = getAction($json);

if (!ensureRashodyTables($conn) || !migrateRashodyUsersToMainUsers($conn)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_init_failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'session') {
    $auth = getRashodyAuth();
    if ($auth['user_id'] > 0 && isUserBlockedById((int)$auth['user_id'])) {
        clearRashodyAuth();
        echo json_encode([
            'ok' => true,
            'logged_in' => false,
            'username' => '',
            'is_super' => false,
            'blocked' => true
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $isSuper = isRashodySuperUser($auth);
    echo json_encode([
        'ok' => true,
        'logged_in' => $auth['user_id'] > 0,
        'username' => $auth['username'],
        'is_super' => $isSuper
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'register') {
    handleRegister($conn, $json);
}

if ($action === 'login') {
    handleLogin($conn, $json);
}

if ($action === 'logout') {
    clearRashodyAuth();
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'remind_password') {
    handleRashodyPasswordReminder($conn, $json);
}

if ($action === 'complete_password_reset') {
    handleRashodyCompletePasswordReset($conn, $json);
}

$auth = getRashodyAuth();
if ($auth['user_id'] > 0 && isUserBlockedById((int)$auth['user_id'])) {
    clearRashodyAuth();
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'blocked_user'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($auth['user_id'] <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}
$user_id = (int)$auth['user_id'];
$isSuper = isRashodySuperUser($auth);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($action === 'orders') {
    echo json_encode([
        'ok' => true,
        'orders' => fetchRashodyOrders($conn)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'users') {
    if (!$isSuper) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode([
        'ok' => true,
        'users' => fetchRashodyUsers($conn)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'GET') {
    echo json_encode([
        'ok' => true,
        'expenses' => $isSuper ? fetchRashodyAllExpenses($conn) : fetchRashodyExpenses($conn, $user_id),
        'rev' => $isSuper ? 0 : rashodyCurrentRev($conn, $user_id)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($content_type, 'multipart/form-data') !== false) {
        handlePhotoUploadRequest($conn, $user_id, $isSuper);
    }
    if ($isSuper) {
        if ($action === 'super_create') {
            handleRashodySuperCreate($conn, $json);
        }
        if ($action === 'super_delete') {
            handleRashodySuperDelete($conn, $json);
        }
        if ($action === 'super_update') {
            handleRashodySuperUpdate($conn, $json);
        }
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'super_read_only'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!is_array($json) || !isset($json['expenses']) || !is_array($json['expenses'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_payload'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $normalized = normalizeIncomingExpenses($json['expenses']);
    if ($normalized === null) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_expenses'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Optimistic lock: if the client sent the revision it last saw and the server
    // has since moved on (another device/tab saved, or an admin/photo change bumped
    // it), reject instead of blindly overwriting. Older clients omit rev (null) and
    // keep the legacy last-write-wins behavior.
    $expectedRev = array_key_exists('rev', $json) && $json['rev'] !== null ? (int)$json['rev'] : null;
    $result = saveRashodyExpenses($conn, $user_id, $normalized, $expectedRev);

    if (!$result['ok']) {
        if (!empty($result['conflict'])) {
            http_response_code(409);
            echo json_encode([
                'ok' => false,
                'conflict' => true,
                'error' => 'rev_conflict',
                'expenses' => fetchRashodyExpenses($conn, $user_id),
                'rev' => rashodyCurrentRev($conn, $user_id)
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'save_failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'expenses' => fetchRashodyExpenses($conn, $user_id),
        'rev' => (int)$result['rev']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);
exit;

function getAction($json): string
{
    if (isset($_GET['action'])) return (string)$_GET['action'];
    if (isset($_POST['action'])) return (string)$_POST['action'];
    if (is_array($json) && isset($json['action'])) return (string)$json['action'];
    return '';
}

function ensureRashodyTables(mysqli $conn): bool
{
    $sql_expenses = "CREATE TABLE IF NOT EXISTS rashody_expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rashody_user_id INT NOT NULL,
        client_uid VARCHAR(80) NOT NULL,
        entry_type ENUM('expense', 'income') NOT NULL DEFAULT 'expense',
        entry_category VARCHAR(64) NOT NULL DEFAULT 'expense',
        amount DECIMAL(12,2) NOT NULL,
        entry_date DATE NOT NULL,
        note LONGTEXT,
        order_id VARCHAR(20) DEFAULT NULL,
        owner_username VARCHAR(50) DEFAULT NULL,
        photos_json TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_rashody_user_date (rashody_user_id, entry_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($sql_expenses)) {
        error_log('rashody_expenses create error: ' . $conn->error);
        return false;
    }

    if (!ensureColumnExists($conn, 'rashody_expenses', 'photos_json', 'TEXT DEFAULT NULL')) {
        return false;
    }
    if (!ensureColumnExists($conn, 'rashody_expenses', 'order_id', 'VARCHAR(20) DEFAULT NULL')) {
        return false;
    }
    if (!ensureColumnExists($conn, 'rashody_expenses', 'owner_username', 'VARCHAR(50) DEFAULT NULL')) {
        return false;
    }
    if (!ensureColumnExists($conn, 'rashody_expenses', 'rashody_user_id', 'INT NOT NULL DEFAULT 0')) {
        return false;
    }
    if (!ensureColumnExists($conn, 'rashody_expenses', 'entry_category', "VARCHAR(64) NOT NULL DEFAULT 'expense'")) {
        return false;
    }
    if (!ensureColumnType($conn, 'rashody_expenses', 'note', 'LONGTEXT', ['text', 'mediumtext', 'longtext'])) {
        return false;
    }

    // Per-user revision counter for optimistic locking of the full-state save.
    $sql_state = "CREATE TABLE IF NOT EXISTS rashody_state (
        rashody_user_id INT NOT NULL PRIMARY KEY,
        rev INT UNSIGNED NOT NULL DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($sql_state)) {
        error_log('rashody_state create error: ' . $conn->error);
        return false;
    }

    // Best-effort: collapse duplicate (user, client_uid) rows and add a unique key so
    // a single payload can never persist twin records. Failures must not break init.
    try {
        $check = $conn->query("SHOW INDEX FROM rashody_expenses WHERE Key_name = 'uniq_rashody_user_uid'");
        $hasUnique = $check && $check->num_rows > 0;
        if (!$hasUnique) {
            $conn->query(
                "DELETE e1 FROM rashody_expenses e1
                 JOIN rashody_expenses e2
                   ON e1.rashody_user_id = e2.rashody_user_id
                  AND e1.client_uid = e2.client_uid
                  AND e1.id < e2.id"
            );
            $conn->query("ALTER TABLE rashody_expenses ADD UNIQUE KEY uniq_rashody_user_uid (rashody_user_id, client_uid)");
        }
    } catch (Throwable $e) {
        error_log('rashody_expenses unique index migration skipped: ' . $e->getMessage());
    }

    return true;
}

function rashodyCurrentRev(mysqli $conn, int $user_id): int
{
    $stmt = $conn->prepare("SELECT rev FROM rashody_state WHERE rashody_user_id = ? LIMIT 1");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)($row['rev'] ?? 0) : 0;
}

function rashodyBumpRev(mysqli $conn, int $user_id): void
{
    if ($user_id <= 0) {
        return;
    }
    $stmt = $conn->prepare(
        "INSERT INTO rashody_state (rashody_user_id, rev) VALUES (?, 1)
         ON DUPLICATE KEY UPDATE rev = rev + 1"
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

function ensureRashodyAuthTables(mysqli $conn): bool
{
    return true;
}

function ensureRashodySuperUser(mysqli $conn): bool
{
    return true;
}

function migrateRashodyUsersToMainUsers(mysqli $conn): bool
{
    $legacy = $conn->query("SHOW TABLES LIKE 'rashody_users'");
    if (!$legacy || $legacy->num_rows === 0) {
        return true;
    }

    $sql = "UPDATE rashody_expenses e
        JOIN rashody_users ru ON ru.id = e.rashody_user_id
        JOIN users u ON u.username = ru.username
        SET e.rashody_user_id = u.id
        WHERE e.rashody_user_id <> u.id";

    if (!$conn->query($sql)) {
        error_log('rashody user migration error: ' . $conn->error);
        return false;
    }

    return true;
}

function ensureColumnExists(mysqli $conn, string $table, string $column, string $definition): bool
{
    $table_safe = str_replace('`', '``', $table);
    $column_safe = str_replace('`', '``', $column);
    $check = $conn->query("SHOW COLUMNS FROM `$table_safe` LIKE '$column_safe'");
    if ($check === false) return false;
    if ($check->num_rows > 0) return true;
    $sql = "ALTER TABLE `$table_safe` ADD `$column_safe` $definition";
    return $conn->query($sql) === true;
}

function ensureColumnType(mysqli $conn, string $table, string $column, string $targetType, array $acceptableTypes = []): bool
{
    $stmt = $conn->prepare("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return false;
    }

    $dataType = strtolower((string)($row['DATA_TYPE'] ?? ''));
    $allowed = array_map('strtolower', $acceptableTypes);
    $allowed[] = strtolower($targetType);
    if (in_array($dataType, $allowed, true)) {
        return true;
    }

    $table_safe = str_replace('`', '``', $table);
    $column_safe = str_replace('`', '``', $column);
    $sql = "ALTER TABLE `$table_safe` MODIFY `$column_safe` $targetType";
    return $conn->query($sql) === true;
}

function getRashodyAuth(): array
{
    $id = isset($_SESSION['rashody_user_id']) ? (int)$_SESSION['rashody_user_id'] : 0;
    $username = isset($_SESSION['rashody_username']) ? (string)$_SESSION['rashody_username'] : '';
    // Adopt the shared app login only if the user has not explicitly logged out of
    // the Расходы section (otherwise logout here could never take effect).
    if ($id <= 0 && empty($_SESSION['rashody_logged_out'])
        && isset($_SESSION['logged_in'], $_SESSION['user_id'], $_SESSION['username'])
        && $_SESSION['logged_in'] === true) {
        $id = (int)$_SESSION['user_id'];
        $username = (string)$_SESSION['username'];
        setRashodyAuth($id, $username);
    }
    return ['user_id' => $id, 'username' => $username];
}

function isRashodySuperUser(array $auth): bool
{
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        return true;
    }
    return isRashodySuperUsername((string)($auth['username'] ?? ''));
}

function isRashodySuperUsername(string $username): bool
{
    return in_array(strtolower(trim($username)), ['admin', 'admin1'], true);
}

function setRashodyAuth(int $user_id, string $username): void
{
    $_SESSION['rashody_user_id'] = $user_id;
    $_SESSION['rashody_username'] = $username;
    unset(
        $_SESSION['rashody_reset_user_id'],
        $_SESSION['rashody_reset_username'],
        $_SESSION['rashody_logged_out']
    );
}

function clearRashodyAuth(): void
{
    // Log out of the Расходы section only. The shared app session (used by Склад,
    // Шиномонтаж и т.д.) is left intact; the opt-out flag stops getRashodyAuth from
    // immediately re-adopting that login on the next request.
    unset($_SESSION['rashody_user_id'], $_SESSION['rashody_username']);
    $_SESSION['rashody_logged_out'] = true;
}

function handleRegister(mysqli $conn, $json): void
{
    $username = trim((string)($json['username'] ?? ''));
    $password = (string)($json['password'] ?? '');

    if (!preg_match('/^[a-zA-Z0-9_\-]{3,32}$/', $username)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_username'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'password_too_short'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $role = 'user';
    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'db_prepare_failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmt->bind_param("sss", $username, $password, $role);
    if (!$stmt->execute()) {
        $stmt->close();
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'username_exists'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $user_id = (int)$conn->insert_id;
    $stmt->close();
    setRashodyAuth($user_id, $username);
    echo json_encode([
        'ok' => true,
        'logged_in' => true,
        'username' => $username,
        'is_super' => isRashodySuperUsername($username)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function handleLogin(mysqli $conn, $json): void
{
    $username = trim((string)($json['username'] ?? ''));
    $password = (string)($json['password'] ?? '');
    if ($username === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'missing_credentials'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, username, password, role, is_blocked, is_deleted FROM users WHERE username = ? LIMIT 1");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'db_prepare_failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user && ((int)($user['is_blocked'] ?? 0) === 1 || (int)($user['is_deleted'] ?? 0) === 1)) {
        http_response_code(200);
        echo json_encode(['ok' => false, 'error' => 'blocked_user'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!$user || !hash_equals((string)$user['password'], $password)) {
        // Keep HTTP 200 here so invalid credentials are handled as a UI-level auth error
        // without noisy Unauthorized entries in browser console/network errors.
        http_response_code(200);
        echo json_encode(['ok' => false, 'error' => 'invalid_credentials'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    setRashodyAuth((int)$user['id'], (string)$user['username']);
    $_SESSION['logged_in'] = true;
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_role'] = (string)($user['role'] ?? 'user');
    $_SESSION['username'] = (string)$user['username'];
    $usernameSafe = (string)$user['username'];
    echo json_encode([
        'ok' => true,
        'logged_in' => true,
        'username' => $usernameSafe,
        'is_super' => (string)($user['role'] ?? '') === 'admin' || isRashodySuperUsername($usernameSafe)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function handleRashodyPasswordReminder(mysqli $conn, $json): void
{
    $username = trim((string)($json['username'] ?? ''));
    if ($username === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'missing_username'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, username, is_blocked, is_deleted FROM users WHERE username = ? LIMIT 1");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'db_prepare_failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'username_not_found'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ((int)($user['is_blocked'] ?? 0) === 1 || (int)($user['is_deleted'] ?? 0) === 1) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'blocked_user'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $temporaryPassword = generateRashodyTemporaryPassword();
    $userId = (int)$user['id'];

    $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    if (!$updateStmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'db_prepare_failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $updateStmt->bind_param("si", $temporaryPassword, $userId);
    $updated = $updateStmt->execute();
    $updateStmt->close();

    if (!$updated) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'password_update_failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Новый пароль создан.',
        'temporary_password' => $temporaryPassword
    ], JSON_UNESCAPED_UNICODE);
    $_SESSION['rashody_reset_user_id'] = $userId;
    $_SESSION['rashody_reset_username'] = (string)$user['username'];
    exit;
}

function handleRashodyCompletePasswordReset(mysqli $conn, $json): void
{
    $username = trim((string)($json['username'] ?? ''));
    $newPassword = (string)($json['new_password'] ?? '');
    $resetUserId = isset($_SESSION['rashody_reset_user_id']) ? (int)$_SESSION['rashody_reset_user_id'] : 0;
    $resetUsername = isset($_SESSION['rashody_reset_username']) ? (string)$_SESSION['rashody_reset_username'] : '';

    if ($username === '' || $resetUserId <= 0 || $resetUsername === '' || $resetUsername !== $username) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'reset_not_allowed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (strlen($newPassword) < 6) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'password_too_short'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'db_prepare_failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmt->bind_param("si", $newPassword, $resetUserId);
    $updated = $stmt->execute();
    $stmt->close();

    if (!$updated) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'password_update_failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    unset($_SESSION['rashody_reset_user_id'], $_SESSION['rashody_reset_username']);

    echo json_encode([
        'ok' => true,
        'message' => 'Новый пароль сохранён.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function generateRashodyTemporaryPassword(int $length = 10): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $maxIndex = strlen($alphabet) - 1;
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, $maxIndex)];
    }
    return $password;
}


function fetchRashodyExpenses(mysqli $conn, int $user_id): array
{
    $stmt = $conn->prepare("SELECT e.client_uid, e.entry_type, e.entry_category, e.amount, e.entry_date, e.note, e.order_id, e.photos_json, e.created_at
        FROM rashody_expenses e
        WHERE e.rashody_user_id = ?
        ORDER BY e.entry_date DESC, e.created_at DESC");
    if (!$stmt) {
        error_log('fetchRashodyExpenses prepare error: ' . $conn->error);
        return [];
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $entryType = $row['entry_type'] === 'income' ? 'income' : 'expense';
        $entryCategory = normalizeEntryCategoryValue($row['entry_category'] ?? '', $entryType);
        $rows[] = [
            'id' => (string)$row['client_uid'],
            'type' => deriveEntryTypeByCategory($entryCategory, $entryType),
            'category' => $entryCategory,
            'amount' => (float)$row['amount'],
            'date' => $row['entry_date'],
            'note' => (string)($row['note'] ?? ''),
            'orderId' => (string)($row['order_id'] ?? ''),
            'photos' => decodePhotoJson($row['photos_json'] ?? '', $user_id),
            'createdAt' => isset($row['created_at']) ? date(DATE_ATOM, strtotime((string)$row['created_at'])) : date(DATE_ATOM)
        ];
    }
    $stmt->close();
    return $rows;
}

function fetchRashodyAllExpenses(mysqli $conn): array
{
    $sql = "SELECT e.client_uid, e.entry_type, e.entry_category, e.amount, e.entry_date, e.note, e.order_id, e.photos_json, e.created_at, e.rashody_user_id, COALESCE(NULLIF(e.owner_username, ''), u.username) AS username
        FROM rashody_expenses e
        LEFT JOIN users u ON u.id = e.rashody_user_id
        ORDER BY e.entry_date DESC, e.created_at DESC";
    $res = $conn->query($sql);
    if (!$res) {
        error_log('fetchRashodyAllExpenses query error: ' . $conn->error);
        return [];
    }

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $entryType = $row['entry_type'] === 'income' ? 'income' : 'expense';
        $entryCategory = normalizeEntryCategoryValue($row['entry_category'] ?? '', $entryType);
        $rows[] = [
            'id' => (string)$row['client_uid'],
            'type' => deriveEntryTypeByCategory($entryCategory, $entryType),
            'category' => $entryCategory,
            'amount' => (float)$row['amount'],
            'date' => $row['entry_date'],
            'note' => (string)($row['note'] ?? ''),
            'orderId' => (string)($row['order_id'] ?? ''),
            'photos' => decodePhotoJson($row['photos_json'] ?? '', (int)($row['rashody_user_id'] ?? 0)),
            'createdAt' => isset($row['created_at']) ? date(DATE_ATOM, strtotime((string)$row['created_at'])) : date(DATE_ATOM),
            'owner' => (string)($row['username'] ?? ''),
            'owner_id' => (int)($row['rashody_user_id'] ?? 0)
        ];
    }
    return $rows;
}

function fetchRashodyOrders(mysqli $conn): array
{
    $sql = "SELECT id, client_name, status, notes, created_at FROM orders WHERE status IN ('in_progress', 'completed') ORDER BY created_at DESC";
    $res = $conn->query($sql);

    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = [
                'id' => (string)($row['id'] ?? ''),
                'source' => 'orders',
                'client_name' => (string)($row['client_name'] ?? ''),
                'status' => (string)($row['status'] ?? ''),
                'notes' => (string)($row['notes'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? '')
            ];
        }
    }

    $zakazSql = "SELECT id, document_number, client_name, phone, car_brand, car_model, plate_number,
                        car_type, radius, qty_mode, wheel_count, items_json, subtotal, discount, total,
                        paid_amount, debt_amount, note, created_by_username, created_at
                 FROM zakaz_operations
                 ORDER BY created_at DESC, id DESC
                 LIMIT 300";
    $zakazRes = $conn->query($zakazSql);
    if ($zakazRes) {
        while ($row = $zakazRes->fetch_assoc()) {
            $items = json_decode((string)($row['items_json'] ?? ''), true);
            $doc = (string)($row['document_number'] ?? '');
            $rows[] = [
                'id' => 'zakaz-' . (string)($row['id'] ?? ''),
                'source' => 'zakaz',
                'operation_id' => (int)($row['id'] ?? 0),
                'document_number' => $doc,
                'client_name' => (string)($row['client_name'] ?? ''),
                'status' => 'zakaz',
                'notes' => trim('Заказ-наряд' . ($doc !== '' ? ' №' . $doc : '') . "\n" . (string)($row['note'] ?? '')),
                'created_at' => (string)($row['created_at'] ?? ''),
                'phone' => (string)($row['phone'] ?? ''),
                'car_brand' => (string)($row['car_brand'] ?? ''),
                'car_model' => (string)($row['car_model'] ?? ''),
                'plate_number' => (string)($row['plate_number'] ?? ''),
                'car_type' => (string)($row['car_type'] ?? ''),
                'radius' => (string)($row['radius'] ?? ''),
                'qty_mode' => (string)($row['qty_mode'] ?? ''),
                'wheel_count' => (int)($row['wheel_count'] ?? 0),
                'items' => is_array($items) ? $items : [],
                'subtotal' => (float)($row['subtotal'] ?? 0),
                'discount' => (float)($row['discount'] ?? 0),
                'total' => (float)($row['total'] ?? 0),
                'paid_amount' => (float)($row['paid_amount'] ?? 0),
                'debt_amount' => (float)($row['debt_amount'] ?? 0),
                'created_by_username' => (string)($row['created_by_username'] ?? ''),
            ];
        }
    }

    usort($rows, static function (array $a, array $b): int {
        return strtotime((string)($b['created_at'] ?? '')) <=> strtotime((string)($a['created_at'] ?? ''));
    });
    return $rows;
}

function fetchRashodyUsers(mysqli $conn): array
{
    $res = $conn->query("SELECT id, username FROM users WHERE is_blocked = 0 AND is_deleted = 0 ORDER BY username ASC");
    if (!$res) return [];
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'id' => (int)($row['id'] ?? 0),
            'username' => (string)($row['username'] ?? '')
        ];
    }
    return $rows;
}

function getRashodyUsernameById(mysqli $conn, int $userId): string
{
    if ($userId <= 0) {
        return '';
    }
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return '';
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (string)($row['username'] ?? '');
}

function handleRashodySuperDelete(mysqli $conn, $json): void
{
    $auth = getRashodyAuth();
    if (!isRashodySuperUser($auth)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!is_array($json)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_payload'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $client_uid = trim((string)($json['id'] ?? ''));
    $owner = trim((string)($json['owner'] ?? ''));
    $ownerId = isset($json['owner_id']) ? (int)$json['owner_id'] : 0;
    if ($client_uid === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_payload'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($ownerId <= 0 && $owner !== '') {
        $stmtUser = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        if (!$stmtUser) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'db_prepare_failed'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $stmtUser->bind_param("s", $owner);
        $stmtUser->execute();
        $userRow = $stmtUser->get_result()->fetch_assoc();
        $stmtUser->close();
        if (!$userRow) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'owner_not_found'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $ownerId = (int)$userRow['id'];
    }

    $photos = [];
    if ($ownerId > 0) {
        $stmtSelect = $conn->prepare("SELECT photos_json FROM rashody_expenses WHERE rashody_user_id = ? AND client_uid = ?");
        if ($stmtSelect) {
            $stmtSelect->bind_param("is", $ownerId, $client_uid);
            $stmtSelect->execute();
            $res = $stmtSelect->get_result();
            while ($row = $res->fetch_assoc()) {
                $photos = array_merge($photos, decodePhotoJson($row['photos_json'] ?? '', $ownerId));
            }
            $stmtSelect->close();
        }

        $stmtDelete = $conn->prepare("DELETE FROM rashody_expenses WHERE rashody_user_id = ? AND client_uid = ?");
        if (!$stmtDelete) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'db_prepare_failed'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $stmtDelete->bind_param("is", $ownerId, $client_uid);
        $ok = $stmtDelete->execute();
        $stmtDelete->close();
    } else {
        $stmtSelect = $conn->prepare("SELECT rashody_user_id, photos_json FROM rashody_expenses WHERE client_uid = ?");
        if (!$stmtSelect) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'db_prepare_failed'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $stmtSelect->bind_param("s", $client_uid);
        $stmtSelect->execute();
        $res = $stmtSelect->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
            $photos = array_merge($photos, decodePhotoJson($row['photos_json'] ?? '', (int)($row['rashody_user_id'] ?? 0)));
        }
        $stmtSelect->close();

        if (count($rows) === 0) {
            echo json_encode(['ok' => true, 'message' => 'not_found'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (count($rows) > 1) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => 'ambiguous_delete'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $ownerId = (int)($rows[0]['rashody_user_id'] ?? 0);
        $stmtDelete = $conn->prepare("DELETE FROM rashody_expenses WHERE client_uid = ?");
        if (!$stmtDelete) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'db_prepare_failed'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $stmtDelete->bind_param("s", $client_uid);
        $ok = $stmtDelete->execute();
        $stmtDelete->close();
    }

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'delete_failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    foreach (array_unique($photos) as $path) {
        safeDeleteRashodyPhoto($path);
    }

    if ($ownerId > 0) {
        rashodyBumpRev($conn, $ownerId);
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

function handleRashodySuperUpdate(mysqli $conn, $json): void
{
    $auth = getRashodyAuth();
    if (!isRashodySuperUser($auth)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!is_array($json)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_payload'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $client_uid = trim((string)($json['id'] ?? ''));
    $owner = trim((string)($json['owner'] ?? ''));
    $ownerId = isset($json['owner_id']) ? (int)$json['owner_id'] : 0;
    $typeRaw = ($json['type'] ?? '') === 'income' ? 'income' : 'expense';
    $category = normalizeEntryCategoryValue($json['category'] ?? '', $typeRaw);
    $type = deriveEntryTypeByCategory($category, $typeRaw);
    $amount = isset($json['amount']) ? (float)$json['amount'] : 0.0;
    $date = trim((string)($json['date'] ?? ''));
    $note = trim((string)($json['note'] ?? ''));
    $orderId = normalizeOrderIdValue($json['orderId'] ?? ($json['order_id'] ?? ''));

    if ($client_uid === '' || !is_finite($amount) || $amount <= 0 || $date === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_payload'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_payload'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $photos = [];
    if (isset($json['photos']) && is_array($json['photos'])) {
        foreach ($json['photos'] as $item) {
            if (!is_string($item)) continue;
            $clean = sanitizePhotoPath($item);
            if ($clean !== '') $photos[] = $clean;
        }
    }
    $photos = array_slice(array_values(array_unique($photos)), 0, 6);

    if ($ownerId <= 0 && $owner !== '') {
        $stmtUser = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        if (!$stmtUser) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'db_prepare_failed'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $stmtUser->bind_param("s", $owner);
        $stmtUser->execute();
        $userRow = $stmtUser->get_result()->fetch_assoc();
        $stmtUser->close();
        if (!$userRow) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'owner_not_found'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $ownerId = (int)$userRow['id'];
    }

    if ($ownerId > 0) {
        $stmtSelect = $conn->prepare("SELECT photos_json FROM rashody_expenses WHERE rashody_user_id = ? AND client_uid = ?");
        if (!$stmtSelect) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'db_prepare_failed'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $stmtSelect->bind_param("is", $ownerId, $client_uid);
        $stmtSelect->execute();
        $res = $stmtSelect->get_result();
        $rowCount = 0;
        while ($row = $res->fetch_assoc()) {
            $rowCount += 1;
        }
        $stmtSelect->close();

        if ($rowCount === 0) {
            echo json_encode(['ok' => true, 'message' => 'not_found'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        $stmtSelect = $conn->prepare("SELECT rashody_user_id, photos_json FROM rashody_expenses WHERE client_uid = ?");
        if (!$stmtSelect) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'db_prepare_failed'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $stmtSelect->bind_param("s", $client_uid);
        $stmtSelect->execute();
        $res = $stmtSelect->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmtSelect->close();

        if (count($rows) === 0) {
            echo json_encode(['ok' => true, 'message' => 'not_found'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (count($rows) > 1) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => 'ambiguous_update'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $ownerId = (int)($rows[0]['rashody_user_id'] ?? 0);
        if ($ownerId <= 0) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'owner_not_found'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $old_map = getExistingExpensePhotosMap($conn, $ownerId);
    $photos_json = json_encode($photos, JSON_UNESCAPED_UNICODE);
    $amount = round($amount, 2);
    $stmtUpdate = $conn->prepare("UPDATE rashody_expenses SET entry_type = ?, entry_category = ?, amount = ?, entry_date = ?, note = ?, photos_json = ?, order_id = ? WHERE rashody_user_id = ? AND client_uid = ?");
    if (!$stmtUpdate) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'db_prepare_failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmtUpdate->bind_param("ssdssssis", $type, $category, $amount, $date, $note, $photos_json, $orderId, $ownerId, $client_uid);
    $ok = $stmtUpdate->execute();
    $stmtUpdate->close();

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'update_failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $new_map = $old_map;
    $new_map[$client_uid] = $photos;
    removeDeletedPhotos($old_map, $new_map);
    if ($ownerId > 0) {
        rashodyBumpRev($conn, $ownerId);
    }
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

function handleRashodySuperCreate(mysqli $conn, $json): void
{
    $auth = getRashodyAuth();
    if (!isRashodySuperUser($auth)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!is_array($json)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_payload'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $client_uid = trim((string)($json['id'] ?? ''));
    $owner = trim((string)($json['owner'] ?? ''));
    $ownerId = isset($json['owner_id']) ? (int)$json['owner_id'] : 0;
    $typeRaw = ($json['type'] ?? '') === 'income' ? 'income' : 'expense';
    $category = normalizeEntryCategoryValue($json['category'] ?? '', $typeRaw);
    $type = deriveEntryTypeByCategory($category, $typeRaw);
    $amount = isset($json['amount']) ? (float)$json['amount'] : 0.0;
    $date = trim((string)($json['date'] ?? ''));
    $note = trim((string)($json['note'] ?? ''));
    $orderId = normalizeOrderIdValue($json['orderId'] ?? ($json['order_id'] ?? ''));

    if (!is_finite($amount) || $amount <= 0 || $date === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_payload'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_payload'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $photos = [];
    if (isset($json['photos']) && is_array($json['photos'])) {
        foreach ($json['photos'] as $item) {
            if (!is_string($item)) continue;
            $clean = sanitizePhotoPath($item);
            if ($clean !== '') $photos[] = $clean;
        }
    }
    $photos = array_slice(array_values(array_unique($photos)), 0, 6);

    if ($ownerId <= 0 && $owner !== '') {
        $stmtUser = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        if (!$stmtUser) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'db_prepare_failed'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $stmtUser->bind_param("s", $owner);
        $stmtUser->execute();
        $userRow = $stmtUser->get_result()->fetch_assoc();
        $stmtUser->close();
        if (!$userRow) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'owner_not_found'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $ownerId = (int)$userRow['id'];
    }

    if ($ownerId <= 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'owner_not_found'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($client_uid === '') {
        $client_uid = uniqid('', true);
    }
    $client_uid = substr($client_uid, 0, 80);

    $stmtCheck = $conn->prepare("SELECT id FROM rashody_expenses WHERE rashody_user_id = ? AND client_uid = ? LIMIT 1");
    if ($stmtCheck) {
        $stmtCheck->bind_param("is", $ownerId, $client_uid);
        $stmtCheck->execute();
        $exists = $stmtCheck->get_result()->fetch_assoc();
        $stmtCheck->close();
        if ($exists) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => 'duplicate_id'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $photos_json = json_encode($photos, JSON_UNESCAPED_UNICODE);
    $amount = round($amount, 2);
    $stmtInsert = $conn->prepare("INSERT INTO rashody_expenses (rashody_user_id, client_uid, entry_type, entry_category, amount, entry_date, note, photos_json, order_id, owner_username) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmtInsert) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'db_prepare_failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $ownerUsername = getRashodyUsernameById($conn, $ownerId);
    $stmtInsert->bind_param("isssdsssss", $ownerId, $client_uid, $type, $category, $amount, $date, $note, $photos_json, $orderId, $ownerUsername);
    $ok = $stmtInsert->execute();
    $stmtInsert->close();

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'create_failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($ownerId > 0) {
        rashodyBumpRev($conn, $ownerId);
    }

    echo json_encode(['ok' => true, 'id' => $client_uid], JSON_UNESCAPED_UNICODE);
    exit;
}

function saveRashodyExpenses(mysqli $conn, int $user_id, array $expenses, ?int $expectedRev = null): array
{
    $old_map = getExistingExpensePhotosMap($conn, $user_id);
    $new_map = [];
    foreach ($expenses as $item) {
        $new_map[$item['id']] = $item['photos'] ?? [];
    }

    $newRev = 0;
    $conn->begin_transaction();
    try {
        // Lock (or create) the per-user revision row first so concurrent saves are
        // serialized and a stale client cannot clobber a newer state.
        $conn->query("INSERT IGNORE INTO rashody_state (rashody_user_id, rev) VALUES (" . (int)$user_id . ", 0)");
        $lockStmt = $conn->prepare("SELECT rev FROM rashody_state WHERE rashody_user_id = ? FOR UPDATE");
        if (!$lockStmt) throw new RuntimeException('rev lock prepare failed');
        $lockStmt->bind_param("i", $user_id);
        $lockStmt->execute();
        $revRow = $lockStmt->get_result()->fetch_assoc();
        $lockStmt->close();
        $currentRev = $revRow ? (int)($revRow['rev'] ?? 0) : 0;

        if ($expectedRev !== null && $expectedRev !== $currentRev) {
            $conn->rollback();
            return ['ok' => false, 'conflict' => true, 'rev' => $currentRev];
        }

        $stmt_delete = $conn->prepare("DELETE FROM rashody_expenses WHERE rashody_user_id = ?");
        if (!$stmt_delete) throw new RuntimeException('delete prepare failed');
        $stmt_delete->bind_param("i", $user_id);
        if (!$stmt_delete->execute()) {
            $err = $stmt_delete->error;
            $stmt_delete->close();
            throw new RuntimeException('delete execute failed: ' . $err);
        }
        $stmt_delete->close();

        if (!empty($expenses)) {
            $ownerUsername = getRashodyUsernameById($conn, $user_id);
            $stmt_insert = $conn->prepare("INSERT INTO rashody_expenses (rashody_user_id, client_uid, entry_type, entry_category, amount, entry_date, note, photos_json, order_id, owner_username) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt_insert) throw new RuntimeException('insert prepare failed');
            foreach ($expenses as $item) {
                $client_uid = $item['id'];
                $entry_type = $item['type'];
                $entry_category = normalizeEntryCategoryValue($item['category'] ?? '', $entry_type);
                $entry_type = deriveEntryTypeByCategory($entry_category, $entry_type);
                $amount = $item['amount'];
                $entry_date = $item['date'];
                $note = $item['note'];
                $photos_json = json_encode(array_values($item['photos'] ?? []), JSON_UNESCAPED_UNICODE);
                $order_id = normalizeOrderIdValue($item['orderId'] ?? ($item['order_id'] ?? ''));
                $stmt_insert->bind_param("isssdsssss", $user_id, $client_uid, $entry_type, $entry_category, $amount, $entry_date, $note, $photos_json, $order_id, $ownerUsername);
                if (!$stmt_insert->execute()) {
                    $err = $stmt_insert->error;
                    $stmt_insert->close();
                    throw new RuntimeException('insert execute failed: ' . $err);
                }
            }
            $stmt_insert->close();
        }

        $bump = $conn->prepare("UPDATE rashody_state SET rev = rev + 1 WHERE rashody_user_id = ?");
        if (!$bump) throw new RuntimeException('rev bump prepare failed');
        $bump->bind_param("i", $user_id);
        if (!$bump->execute()) {
            $bump->close();
            throw new RuntimeException('rev bump execute failed');
        }
        $bump->close();
        $newRev = $currentRev + 1;

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('saveRashodyExpenses error: ' . $e->getMessage());
        return ['ok' => false, 'conflict' => false, 'rev' => 0];
    }

    removeDeletedPhotos($old_map, $new_map);
    return ['ok' => true, 'conflict' => false, 'rev' => $newRev];
}

function getExistingExpensePhotosMap(mysqli $conn, int $user_id): array
{
    $stmt = $conn->prepare("SELECT client_uid, photos_json FROM rashody_expenses WHERE rashody_user_id = ?");
    if (!$stmt) return [];
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $map = [];
    while ($row = $res->fetch_assoc()) {
        $map[(string)$row['client_uid']] = decodePhotoJson($row['photos_json'] ?? '', $user_id);
    }
    $stmt->close();
    return $map;
}

function removeDeletedPhotos(array $old_map, array $new_map): void
{
    foreach ($old_map as $client_uid => $old_photos) {
        $new_photos = $new_map[$client_uid] ?? [];
        $to_remove = array_diff($old_photos, $new_photos);
        foreach ($to_remove as $path) {
            safeDeleteRashodyPhoto($path);
        }
    }
}


function decodePhotoJson(string $raw, int $ownerId = 0): array
{
    if ($raw === '') return [];
    $decoded = json_decode($raw, true);
    $items = [];
    if (is_array($decoded)) {
        $items = $decoded;
    } else {
        $trimmed = trim($raw);
        if ($trimmed !== '') {
            $parts = preg_split('/[,\s]+/', $trimmed, -1, PREG_SPLIT_NO_EMPTY);
            if (is_array($parts)) {
                $items = $parts;
            }
        }
    }
    if (empty($items)) return [];
    $result = [];
    foreach ($items as $item) {
        if (!is_string($item)) continue;
        $clean = sanitizePhotoPath($item);
        if ($clean !== '' && file_exists(__DIR__ . '/' . $clean)) {
            $result[] = $clean;
            continue;
        }
        $fallback = '';
        if ($ownerId > 0) {
            $fallback = buildLegacyRashodyPhotoPath($item, $ownerId);
            if ($fallback !== '' && file_exists(__DIR__ . '/' . $fallback)) {
                $result[] = $fallback;
                continue;
            }
        }
        $found = findRashodyPhotoByName($item);
        if ($found !== '') {
            $result[] = $found;
        }
    }
    return array_values(array_unique($result));
}

function buildLegacyRashodyPhotoPath(string $value, int $ownerId): string
{
    $name = trim($value);
    if ($name === '' || $ownerId <= 0) return '';
    $name = str_replace('\\', '/', $name);
    if (strpos($name, '/') !== false) {
        $name = basename($name);
    }
    if ($name === '' || strpos($name, '..') !== false) return '';
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $name)) return '';
    return 'uploads/rashody/ru_' . $ownerId . '/' . $name;
}

function findRashodyPhotoByName(string $value): string
{
    $name = trim($value);
    if ($name === '') return '';
    $name = str_replace('\\', '/', $name);
    $name = basename($name);
    if ($name === '' || strpos($name, '..') !== false) return '';
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $name)) return '';
    $pattern = __DIR__ . '/uploads/rashody/*/' . $name;
    $matches = glob($pattern);
    if (!$matches) return '';
    $full = $matches[0];
    $dir = basename(dirname($full));
    if ($dir === '') return '';
    return 'uploads/rashody/' . $dir . '/' . $name;
}

function sanitizePhotoPath(string $path): string
{
    $clean = str_replace('\\', '/', trim($path));
    if ($clean === '') return '';
    $clean = preg_replace('#^https?://[^/]+/#i', '', $clean);
    $clean = ltrim($clean, '/');
    $prefix = 'uploads/rashody/';
    $lower = strtolower($clean);
    $pos = strpos($lower, $prefix);
    if ($pos === false) return '';
    $clean = $prefix . substr($clean, $pos + strlen($prefix));
    if (strpos($clean, '..') !== false) return '';
    return substr($clean, 0, 255);
}

function normalizeOrderIdValue($value): string
{
    $raw = strtolower(trim((string)$value));
    if ($raw === '') return '';
    if (preg_match('/^zakaz[-_]?(\d{1,14})$/', $raw, $m)) {
        return 'zakaz-' . $m[1];
    }
    $digits = preg_replace('/\D+/', '', $raw);
    if ($digits === '') return '';
    return substr($digits, 0, 20);
}

function allowedEntryCategories(): array
{
    return ['expense', 'income'];
}

function normalizeCategoryAliasKey(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $lower = function_exists('mb_strtolower')
        ? mb_strtolower($value, 'UTF-8')
        : strtolower($value);
    $normalized = preg_replace('/[\s_-]+/u', '', $lower);
    return is_string($normalized) ? $normalized : '';
}

function resolveKnownEntryCategory($value): string
{
    $category = trim((string)$value);
    if ($category === '') {
        return '';
    }
    $allowed = allowedEntryCategories();
    if (in_array($category, $allowed, true)) {
        return $category;
    }

    $aliases = [
        'expense' => 'expense',
        'расход' => 'expense',
        'расходы' => 'expense',
        'income' => 'income',
        'доход' => 'income',
        'доходы' => 'income'
    ];
    $aliasKey = normalizeCategoryAliasKey($category);
    return $aliases[$aliasKey] ?? '';
}

function normalizeEntryCategoryValue($value, string $fallbackType = 'expense'): string
{
    $category = trim((string)$value);
    if ($category === '') {
        return $fallbackType === 'income' ? 'income' : 'expense';
    }
    $known = resolveKnownEntryCategory($category);
    if ($known !== '') {
        if ($known === 'expense' && $fallbackType === 'income') {
            // Legacy fix: old rows could keep entry_type=income with default entry_category=expense.
            return 'income';
        }
        return $known;
    }
    return $fallbackType === 'income' ? 'income' : 'expense';
}

function deriveEntryTypeByCategory(string $category, string $fallbackType = 'expense'): string
{
    $known = resolveKnownEntryCategory($category);
    return $known === 'income' ? 'income' : 'expense';
}

function normalizeIncomingExpenses(array $source): ?array
{
    $result = [];
    foreach ($source as $row) {
        if (!is_array($row)) return null;
        $id = isset($row['id']) ? trim((string)$row['id']) : '';
        $typeRaw = isset($row['type']) && $row['type'] === 'income' ? 'income' : 'expense';
        $category = normalizeEntryCategoryValue($row['category'] ?? '', $typeRaw);
        $type = deriveEntryTypeByCategory($category, $typeRaw);
        $amount = isset($row['amount']) ? (float)$row['amount'] : 0.0;
        $date = isset($row['date']) ? trim((string)$row['date']) : '';
        $note = isset($row['note']) ? trim((string)$row['note']) : '';
        $orderId = normalizeOrderIdValue($row['orderId'] ?? ($row['order_id'] ?? ''));

        $photos = [];
        if (isset($row['photos']) && is_array($row['photos'])) {
            foreach ($row['photos'] as $item) {
                if (!is_string($item)) continue;
                $clean = sanitizePhotoPath($item);
                if ($clean !== '') $photos[] = $clean;
            }
        }
        $photos = array_slice(array_values(array_unique($photos)), 0, 6);

        if ($id === '') $id = uniqid('', true);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return null;
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) return null;
        if (!is_finite($amount) || $amount <= 0) return null;

        $result[substr($id, 0, 80)] = [
            'id' => substr($id, 0, 80),
            'type' => $type,
            'category' => $category,
            'amount' => round($amount, 2),
            'date' => $date,
            'note' => $note,
            'photos' => $photos,
            'orderId' => $orderId
        ];
    }
    // Keyed by id so a payload that repeats the same client_uid collapses to one row
    // (last occurrence wins) and cannot violate the new unique (user, client_uid) key.
    return array_values($result);
}

function handlePhotoUploadRequest(mysqli $conn, int $user_id, bool $isSuper): void
{
    $logId = date('Ymd_His') . '_' . bin2hex(random_bytes(3));
    $action = $_POST['action'] ?? '';
    if ($action !== 'upload_photos') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_action'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $expense_id = isset($_POST['expense_id']) ? substr(trim((string)$_POST['expense_id']), 0, 80) : '';
    if ($expense_id === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'missing_expense_id'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $target_user_id = $user_id;
    if ($isSuper) {
        $ownerId = isset($_POST['owner_id']) ? (int)$_POST['owner_id'] : 0;
        if ($ownerId > 0) {
            $ownerStmt = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
            if (!$ownerStmt) {
                http_response_code(500);
                echo json_encode(['ok' => false, 'error' => 'db_prepare_failed'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $ownerStmt->bind_param("i", $ownerId);
            $ownerStmt->execute();
            $ownerRow = $ownerStmt->get_result()->fetch_assoc();
            $ownerStmt->close();
            if (!$ownerRow) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'owner_not_found'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $target_user_id = $ownerId;
        } else {
            // Admin without owner_id: resolve by expense id across users.
            $resolvedOwnerId = 0;
            $stmtResolve = $conn->prepare("SELECT rashody_user_id FROM rashody_expenses WHERE client_uid = ? LIMIT 2");
            if ($stmtResolve) {
                $stmtResolve->bind_param("s", $expense_id);
                $stmtResolve->execute();
                $res = $stmtResolve->get_result();
                $rows = [];
                while ($row = $res->fetch_assoc()) {
                    $rows[] = $row;
                }
                $stmtResolve->close();
                if (count($rows) === 1) {
                    $resolvedOwnerId = (int)($rows[0]['rashody_user_id'] ?? 0);
                }
            }
            if ($resolvedOwnerId <= 0 && ctype_digit($expense_id)) {
                $expensePk = (int)$expense_id;
                $stmtResolveId = $conn->prepare("SELECT rashody_user_id FROM rashody_expenses WHERE id = ? LIMIT 2");
                if ($stmtResolveId) {
                    $stmtResolveId->bind_param("i", $expensePk);
                    $stmtResolveId->execute();
                    $res = $stmtResolveId->get_result();
                    $rows = [];
                    while ($row = $res->fetch_assoc()) {
                        $rows[] = $row;
                    }
                    $stmtResolveId->close();
                    if (count($rows) === 1) {
                        $resolvedOwnerId = (int)($rows[0]['rashody_user_id'] ?? 0);
                    }
                }
            }
            if ($resolvedOwnerId > 0) {
                $target_user_id = $resolvedOwnerId;
            }
        }
    }

    rashodyUploadLog($logId, [
        'stage' => 'start',
        'expense_id' => $expense_id,
        'user_id' => $user_id,
        'target_user_id' => $target_user_id,
        'is_super' => $isSuper ? 1 : 0,
        'has_files' => isset($_FILES) ? array_keys($_FILES) : [],
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
    ]);

    $files = reformatFilesArray($_FILES['photos'] ?? ($_FILES['photo'] ?? ($_FILES['photos[]'] ?? null)));
    if (empty($files)) {
        rashodyUploadLog($logId, [
            'stage' => 'missing_files',
            'files_keys' => isset($_FILES) ? array_keys($_FILES) : [],
        ]);
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'missing_files'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $scope_folder = 'ru_' . $target_user_id;
    $upload_dir = __DIR__ . '/uploads/rashody/' . $scope_folder;
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0777, true)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'upload_dir_failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $allowed_mime = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif', 'image/heic', 'image/heif'];
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $saved = [];
    $rejected = [];

    foreach ($files as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $rejected[] = 'upload_error';
            continue;
        }
        $tmp = $file['tmp_name'] ?? '';
        if (!is_string($tmp) || $tmp === '' || !is_uploaded_file($tmp)) {
            $rejected[] = 'invalid_tmp_file';
            continue;
        }
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > AppConstants::IMAGE_MAX_SIZE) {
            $rejected[] = 'too_large';
            continue;
        }
        $mime = $finfo ? finfo_file($finfo, $tmp) : mime_content_type($tmp);
        $ext = '';
        if (is_string($mime) && in_array($mime, $allowed_mime, true)) {
            $ext = mimeToExt($mime);
        }
        if ($ext === '') {
            $name = strtolower((string)($file['name'] ?? ''));
            if (preg_match('/\.(jpe?g)$/i', $name)) $ext = 'jpg';
            elseif (preg_match('/\.(png)$/i', $name)) $ext = 'png';
            elseif (preg_match('/\.(webp)$/i', $name)) $ext = 'webp';
            elseif (preg_match('/\.(gif)$/i', $name)) $ext = 'gif';
            elseif (preg_match('/\.(heic)$/i', $name)) $ext = 'heic';
            elseif (preg_match('/\.(heif)$/i', $name)) $ext = 'heif';
        }
        if ($ext === '') {
            $clientType = strtolower((string)($file['type'] ?? ''));
            if ($clientType !== '' && strpos($clientType, 'image/') === 0) {
                $ext = mimeToExt($clientType);
            }
        }
        if ($ext === '' && function_exists('exif_imagetype')) {
            $imgType = @exif_imagetype($tmp);
            if ($imgType === IMAGETYPE_JPEG) $ext = 'jpg';
            elseif ($imgType === IMAGETYPE_PNG) $ext = 'png';
            elseif ($imgType === IMAGETYPE_GIF) $ext = 'gif';
            elseif (defined('IMAGETYPE_WEBP') && $imgType === IMAGETYPE_WEBP) $ext = 'webp';
        }
        if ($ext === '') {
            $rejected[] = 'unsupported_type';
            continue;
        }

        $safe_id = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $expense_id);
        $file_name = date('YmdHis') . '_' . $safe_id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $target = $upload_dir . '/' . $file_name;
        if (!move_uploaded_file($tmp, $target)) {
            $rejected[] = 'save_failed';
            continue;
        }
        if ($ext === 'heic' || $ext === 'heif') {
            $converted = tryConvertHeicToJpg($target);
            if ($converted !== '') {
                $target = $converted;
                $file_name = basename($converted);
                $ext = 'jpg';
            }
        }
        $saved[] = 'uploads/rashody/' . $scope_folder . '/' . $file_name;
    }

    if ($finfo) finfo_close($finfo);

    if (empty($saved)) {
        rashodyUploadLog($logId, [
            'stage' => 'no_saved',
            'rejected' => array_values(array_unique($rejected)),
        ]);
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'error' => 'photo_save_failed',
            'details' => array_values(array_unique($rejected))
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $bound = bindUploadedPhotosToExpense($conn, $target_user_id, $expense_id, $saved, $isSuper);
    rashodyUploadLog($logId, [
        'stage' => 'bound',
        'saved_count' => count($saved),
        'bound' => $bound ? 1 : 0,
        'saved' => $saved,
        'rejected' => array_values(array_unique($rejected)),
    ]);

    echo json_encode([
        'ok' => true,
        'photos' => $saved,
        'bound_to_expense' => $bound,
        'rev' => $isSuper ? 0 : rashodyCurrentRev($conn, $target_user_id),
        'rejected' => array_values(array_unique($rejected))
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function rashodyUploadLog(string $logId, array $data): void
{
    $path = __DIR__ . '/rashody_upload.log';
    $payload = [
        'ts' => date('Y-m-d H:i:s'),
        'id' => $logId,
        'data' => $data
    ];
    @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
}

function bindUploadedPhotosToExpense(mysqli $conn, int $ownerId, string $expenseId, array $uploaded, bool $allowGlobalLookup = false): bool
{
    $expenseId = trim($expenseId);
    if ($expenseId === '' || empty($uploaded)) {
        return false;
    }

    $cleanUploaded = [];
    foreach ($uploaded as $path) {
        if (!is_string($path)) {
            continue;
        }
        $clean = sanitizePhotoPath($path);
        if ($clean !== '') {
            $cleanUploaded[] = $clean;
        }
    }
    $cleanUploaded = array_values(array_unique($cleanUploaded));
    if (empty($cleanUploaded)) {
        return false;
    }

    $resolvedOwnerId = $ownerId;
    $resolvedClientUid = $expenseId;
    $currentJson = null;

    if ($ownerId > 0) {
        $select = $conn->prepare("SELECT photos_json FROM rashody_expenses WHERE rashody_user_id = ? AND client_uid = ? LIMIT 1");
        if ($select) {
            $select->bind_param("is", $ownerId, $expenseId);
            $select->execute();
            $row = $select->get_result()->fetch_assoc();
            $select->close();
            if ($row) {
                $currentJson = (string)($row['photos_json'] ?? '');
            }
        }
    }

    if ($currentJson === null && ctype_digit($expenseId)) {
        $expensePk = (int)$expenseId;
        if ($ownerId > 0) {
            $selectById = $conn->prepare("SELECT client_uid, photos_json FROM rashody_expenses WHERE rashody_user_id = ? AND id = ? LIMIT 1");
            if ($selectById) {
                $selectById->bind_param("ii", $ownerId, $expensePk);
                $selectById->execute();
                $row = $selectById->get_result()->fetch_assoc();
                $selectById->close();
                if ($row) {
                    $resolvedClientUid = (string)($row['client_uid'] ?? '');
                    $currentJson = (string)($row['photos_json'] ?? '');
                }
            }
        }
    }

    if ($currentJson === null && $allowGlobalLookup) {
        $selectAny = $conn->prepare("SELECT rashody_user_id, photos_json FROM rashody_expenses WHERE client_uid = ? LIMIT 2");
        if ($selectAny) {
            $selectAny->bind_param("s", $expenseId);
            $selectAny->execute();
            $res = $selectAny->get_result();
            $rows = [];
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
            $selectAny->close();
            if (count($rows) === 1) {
                $resolvedOwnerId = (int)($rows[0]['rashody_user_id'] ?? 0);
                $currentJson = (string)($rows[0]['photos_json'] ?? '');
            }
        }

        if ($currentJson === null && ctype_digit($expenseId)) {
            $expensePk = (int)$expenseId;
            $selectAnyById = $conn->prepare("SELECT rashody_user_id, client_uid, photos_json FROM rashody_expenses WHERE id = ? LIMIT 2");
            if ($selectAnyById) {
                $selectAnyById->bind_param("i", $expensePk);
                $selectAnyById->execute();
                $res = $selectAnyById->get_result();
                $rows = [];
                while ($r = $res->fetch_assoc()) {
                    $rows[] = $r;
                }
                $selectAnyById->close();
                if (count($rows) === 1) {
                    $resolvedOwnerId = (int)($rows[0]['rashody_user_id'] ?? 0);
                    $resolvedClientUid = (string)($rows[0]['client_uid'] ?? '');
                    $currentJson = (string)($rows[0]['photos_json'] ?? '');
                }
            }
        }
    }

    if ($currentJson === null || $resolvedOwnerId <= 0 || $resolvedClientUid === '') {
        return false;
    }

    // Lock the target row and re-read its photos inside the transaction so two
    // concurrent uploads cannot read the same list and overwrite each other.
    $conn->begin_transaction();
    try {
        $lock = $conn->prepare("SELECT photos_json FROM rashody_expenses WHERE rashody_user_id = ? AND client_uid = ? LIMIT 1 FOR UPDATE");
        if (!$lock) throw new RuntimeException('photo lock prepare failed');
        $lock->bind_param("is", $resolvedOwnerId, $resolvedClientUid);
        $lock->execute();
        $lockedRow = $lock->get_result()->fetch_assoc();
        $lock->close();
        if (!$lockedRow) {
            $conn->rollback();
            return false;
        }

        $existing = decodePhotoJson((string)($lockedRow['photos_json'] ?? ''), $resolvedOwnerId);
        $merged = array_slice(array_values(array_unique(array_merge($existing, $cleanUploaded))), 0, 6);
        $newJson = json_encode($merged, JSON_UNESCAPED_UNICODE);
        if (!is_string($newJson)) {
            $conn->rollback();
            return false;
        }

        $update = $conn->prepare("UPDATE rashody_expenses SET photos_json = ? WHERE rashody_user_id = ? AND client_uid = ?");
        if (!$update) throw new RuntimeException('photo update prepare failed');
        $update->bind_param("sis", $newJson, $resolvedOwnerId, $resolvedClientUid);
        $ok = $update->execute();
        $update->close();
        if (!$ok) {
            $conn->rollback();
            return false;
        }

        // Bump the owner's revision so a client editing the same expense reloads the
        // freshly attached photo instead of saving a stale list that drops the file.
        $bump = $conn->prepare("INSERT INTO rashody_state (rashody_user_id, rev) VALUES (?, 1) ON DUPLICATE KEY UPDATE rev = rev + 1");
        if ($bump) {
            $bump->bind_param("i", $resolvedOwnerId);
            $bump->execute();
            $bump->close();
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('bindUploadedPhotosToExpense error: ' . $e->getMessage());
        return false;
    }
    return true;
}

function reformatFilesArray($input): array
{
    if (!is_array($input) || !isset($input['name'])) return [];
    $out = [];
    if (!is_array($input['name'])) {
        $out[] = [
            'name' => $input['name'] ?? '',
            'type' => $input['type'] ?? '',
            'tmp_name' => $input['tmp_name'] ?? '',
            'error' => $input['error'] ?? UPLOAD_ERR_NO_FILE,
            'size' => $input['size'] ?? 0,
        ];
        return $out;
    }
    foreach ($input['name'] as $idx => $name) {
        $out[] = [
            'name' => $name,
            'type' => $input['type'][$idx] ?? '',
            'tmp_name' => $input['tmp_name'][$idx] ?? '',
            'error' => $input['error'][$idx] ?? UPLOAD_ERR_NO_FILE,
            'size' => $input['size'][$idx] ?? 0,
        ];
    }
    return $out;
}

function mimeToExt(string $mime): string
{
    if ($mime === 'image/jpg') return 'jpg';
    if ($mime === 'image/jpeg') return 'jpg';
    if ($mime === 'image/png') return 'png';
    if ($mime === 'image/webp') return 'webp';
    if ($mime === 'image/gif') return 'gif';
    if ($mime === 'image/heic') return 'heic';
    if ($mime === 'image/heif') return 'heif';
    return '';
}

function safeDeleteRashodyPhoto(string $path): void
{
    $clean = sanitizePhotoPath($path);
    if ($clean === '') return;
    $full = __DIR__ . '/' . $clean;
    if (is_file($full)) @unlink($full);
}

function tryConvertHeicToJpg(string $inputPath): string
{
    if (!is_file($inputPath)) {
        return '';
    }
    if (!class_exists('Imagick')) {
        return '';
    }
    try {
        $outputPath = preg_replace('/\.(heic|heif)$/i', '.jpg', $inputPath);
        if (!is_string($outputPath) || $outputPath === $inputPath) {
            $outputPath = $inputPath . '.jpg';
        }
        $image = new Imagick();
        $image->readImage($inputPath);
        $image->setImageFormat('jpeg');
        $image->setImageCompression(Imagick::COMPRESSION_JPEG);
        $image->setImageCompressionQuality(92);
        $image->stripImage();
        $ok = $image->writeImage($outputPath);
        $image->clear();
        $image->destroy();
        if ($ok && is_file($outputPath)) {
            @unlink($inputPath);
            return $outputPath;
        }
    } catch (Throwable $e) {
        error_log('HEIC convert failed: ' . $e->getMessage());
    }
    return '';
}
