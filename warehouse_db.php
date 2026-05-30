<?php
declare(strict_types=1);

$warehouseConfig = __DIR__ . '/core/config.php';
if (is_file($warehouseConfig)) {
    require_once $warehouseConfig;
} else {
    if (!defined('DB_HOST')) {
        define('DB_HOST', 'localhost');
    }
    if (!defined('DB_USER')) {
        define('DB_USER', getenv('DB_USER') ?: '');
    }
    if (!defined('DB_PASS')) {
        define('DB_PASS', getenv('DB_PASS') ?: '');
    }
    if (!defined('DB_NAME')) {
        define('DB_NAME', getenv('DB_NAME') ?: '');
    }
}

function warehouse_default_state(): array
{
    return [
        'products' => [],
        'sales' => [],
        'seqProduct' => 1,
        'seqSale' => 1,
    ];
}

function warehouse_ensure_database_and_tables(): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');

    warehouse_ensure_tables($conn);

    return $conn;
}

function warehouse_ensure_tables(mysqli $conn): void
{
    $conn->query(
        'CREATE TABLE IF NOT EXISTS app_state (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            state_json LONGTEXT NOT NULL,
            rev INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    // Optimistic-locking column for older installations created before `rev` existed.
    try {
        $hasRev = false;
        $check = $conn->query("SHOW COLUMNS FROM app_state LIKE 'rev'");
        if ($check) {
            $hasRev = (bool)$check->fetch_assoc();
        }
        if (!$hasRev) {
            $conn->query('ALTER TABLE app_state ADD COLUMN rev INT UNSIGNED NOT NULL DEFAULT 0');
        }
    } catch (Throwable $e) {
        error_log('warehouse_db: ensure rev column failed: ' . $e->getMessage());
    }

    warehouse_ensure_default_state_row($conn);
}

function warehouse_ensure_default_state_row(mysqli $conn): void
{
    $json = json_encode(warehouse_default_state(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Не удалось подготовить начальное состояние склада.');
    }

    $stmt = $conn->prepare(
        'INSERT INTO app_state (id, state_json) VALUES (1, ?)
         ON DUPLICATE KEY UPDATE id = id'
    );
    $stmt->bind_param('s', $json);
    $stmt->execute();
    $stmt->close();
}

function warehouse_open_database(): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');

    return $conn;
}

function warehouse_normalize_state(array $state): array
{
    $products = isset($state['products']) && is_array($state['products']) ? array_values($state['products']) : [];
    $sales = isset($state['sales']) && is_array($state['sales']) ? array_values($state['sales']) : [];
    $seqProduct = max(1, (int)($state['seqProduct'] ?? 1));
    $seqSale = max(1, (int)($state['seqSale'] ?? 1));

    // Reconcile the inventory counters so the persisted row can never contain an
    // inconsistent приход/расход/остаток combination (e.g. a hand-crafted request
    // claiming stock that exceeds incoming - outgoing). Mirrors the client's
    // setProductInventory: outgoing/stock are non-negative and incoming always
    // covers outgoing + stock.
    foreach ($products as &$product) {
        if (!is_array($product)) {
            continue;
        }
        $hasInv = array_key_exists('stock', $product)
            || array_key_exists('incoming', $product)
            || array_key_exists('outgoing', $product);
        if (!$hasInv) {
            continue;
        }
        $outgoing = max(0, (int)floor((float)($product['outgoing'] ?? 0)));
        $stock = max(0, (int)floor((float)($product['stock'] ?? 0)));
        $incomingRaw = (array_key_exists('incoming', $product) && $product['incoming'] !== '')
            ? max(0, (int)floor((float)$product['incoming']))
            : 0;
        $incoming = max($incomingRaw, $outgoing + $stock);
        $product['outgoing'] = $outgoing;
        $product['incoming'] = $incoming;
        $product['stock'] = $stock;
    }
    unset($product);

    return [
        'products' => $products,
        'sales' => $sales,
        'seqProduct' => $seqProduct,
        'seqSale' => $seqSale,
    ];
}

function warehouse_externalize_data_url(string $dataUrl): string
{
    if (!preg_match('#^data:image/(jpeg|jpg|png|webp|gif);base64,#i', $dataUrl, $matches)) {
        return $dataUrl;
    }

    $ext = strtolower($matches[1]);
    if ($ext === 'jpeg') {
        $ext = 'jpg';
    }

    $comma = strpos($dataUrl, ',');
    if ($comma === false) {
        return '';
    }

    $binary = base64_decode(substr($dataUrl, $comma + 1), true);
    if ($binary === false || $binary === '') {
        return '';
    }

    $uploadDir = __DIR__ . '/Uploads/warehouse';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        return $dataUrl;
    }

    $hash = sha1($binary);
    $relative = 'Uploads/warehouse/' . $hash . '.' . $ext;
    $target = __DIR__ . '/' . $relative;
    if (!is_file($target)) {
        $written = @file_put_contents($target, $binary, LOCK_EX);
        if ($written === false) {
            return $dataUrl;
        }
    }

    return $relative;
}

function warehouse_externalize_state_photos(array $state, ?bool &$changed = null): array
{
    $changed = false;
    if (empty($state['products']) || !is_array($state['products'])) {
        return $state;
    }

    foreach ($state['products'] as &$product) {
        if (!is_array($product)) {
            continue;
        }
        $photo = (string)($product['photo'] ?? '');
        if ($photo === '' || strpos($photo, 'data:image/') !== 0) {
            continue;
        }
        $external = warehouse_externalize_data_url($photo);
        if ($external !== $photo) {
            $product['photo'] = $external;
            $changed = true;
        }
    }
    unset($product);

    return $state;
}

function warehouse_fetch_state(mysqli $conn): array
{
    try {
        $result = $conn->query('SELECT state_json FROM app_state WHERE id = 1 LIMIT 1');
    } catch (mysqli_sql_exception $e) {
        warehouse_ensure_tables($conn);
        $result = $conn->query('SELECT state_json FROM app_state WHERE id = 1 LIMIT 1');
    }
    $row = $result ? $result->fetch_assoc() : null;
    if (!$row || !isset($row['state_json'])) {
        warehouse_ensure_default_state_row($conn);
        return warehouse_default_state();
    }

    $decoded = json_decode((string)$row['state_json'], true);
    if (!is_array($decoded)) {
        return warehouse_default_state();
    }

    $state = warehouse_normalize_state($decoded);
    $state = warehouse_externalize_state_photos($state, $changed);
    if ($changed) {
        warehouse_save_state($conn, $state);
    }

    return $state;
}

function warehouse_decode_state_json(string $json): array
{
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return warehouse_default_state();
    }

    return warehouse_normalize_state($decoded);
}

function warehouse_fetch_state_meta(mysqli $conn): array
{
    try {
        $result = $conn->query('SELECT state_json, updated_at, rev, CHAR_LENGTH(state_json) AS bytes FROM app_state WHERE id = 1 LIMIT 1');
    } catch (mysqli_sql_exception $e) {
        warehouse_ensure_tables($conn);
        $result = $conn->query('SELECT state_json, updated_at, rev, CHAR_LENGTH(state_json) AS bytes FROM app_state WHERE id = 1 LIMIT 1');
    }

    $row = $result ? $result->fetch_assoc() : null;
    if (!$row || !isset($row['state_json'])) {
        warehouse_ensure_default_state_row($conn);
        $row = [
            'state_json' => json_encode(warehouse_default_state(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => date('Y-m-d H:i:s'),
            'rev' => 0,
            'bytes' => 0,
        ];
    }

    $state = warehouse_decode_state_json((string)$row['state_json']);
    $state = warehouse_externalize_state_photos($state, $changed);
    if ($changed) {
        warehouse_save_state($conn, $state);
        $row['state_json'] = json_encode(warehouse_normalize_state($state), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $row['updated_at'] = date('Y-m-d H:i:s');
        $row['bytes'] = strlen((string)$row['state_json']);
    }

    $updatedAt = (string)($row['updated_at'] ?? '');
    $row['updated_at_ts'] = $updatedAt !== '' ? (strtotime($updatedAt) ?: 0) : 0;
    $row['bytes'] = (int)($row['bytes'] ?? strlen((string)$row['state_json']));
    $row['rev'] = (int)($row['rev'] ?? 0);

    return $row;
}

function warehouse_save_state(mysqli $conn, array $state): void
{
    $state = warehouse_externalize_state_photos($state);
    $json = json_encode(warehouse_normalize_state($state), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Не удалось сериализовать состояние склада.');
    }

    // Bump the revision counter so concurrent readers can detect that the state changed.
    $stmt = $conn->prepare('UPDATE app_state SET state_json = ?, rev = rev + 1 WHERE id = 1');
    $stmt->bind_param('s', $json);
    $stmt->execute();
    $stmt->close();
}

function warehouse_current_rev(mysqli $conn): int
{
    try {
        $result = $conn->query('SELECT rev FROM app_state WHERE id = 1 LIMIT 1');
    } catch (mysqli_sql_exception $e) {
        warehouse_ensure_tables($conn);
        $result = $conn->query('SELECT rev FROM app_state WHERE id = 1 LIMIT 1');
    }
    $row = $result ? $result->fetch_assoc() : null;
    return $row && isset($row['rev']) ? (int)$row['rev'] : 0;
}

/**
 * Conditional save used for read-modify-write flows. Persists only if the row is
 * still at $expectedRev, otherwise reports a conflict so the caller can abort.
 * Returns the new revision on success or null on a version conflict.
 */
function warehouse_save_state_checked(mysqli $conn, array $state, int $expectedRev): ?int
{
    $state = warehouse_externalize_state_photos($state);
    $json = json_encode(warehouse_normalize_state($state), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Не удалось сериализовать состояние склада.');
    }

    $stmt = $conn->prepare('UPDATE app_state SET state_json = ?, rev = rev + 1 WHERE id = 1 AND rev = ?');
    $stmt->bind_param('si', $json, $expectedRev);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected < 1) {
        return null;
    }
    return $expectedRev + 1;
}

function warehouse_reset_state(mysqli $conn): void
{
    warehouse_save_state($conn, warehouse_default_state());
}
