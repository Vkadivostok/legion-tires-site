<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/warehouse_db.php';

$warehouseLogging = __DIR__ . '/core/logging.php';
if (is_file($warehouseLogging)) {
    require_once $warehouseLogging;
}
if (!function_exists('get_user_ip')) {
    function get_user_ip()
    {
        return $_SERVER['REMOTE_ADDR'] ?? 'N/A';
    }
}
if (!function_exists('log_change')) {
    function log_change($message)
    {
        $logs_file = __DIR__ . '/debug.log';
        $username = $_SESSION['username'] ?? 'guest';
        $timestamp = date('Y-m-d H:i:s');
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';
        $session_id = session_id();
        $log_entry = "[{$timestamp}] [{$username}] [" . get_user_ip() . "] [{$session_id}] [{$user_agent}] {$message}\n";
        file_put_contents($logs_file, $log_entry, FILE_APPEND);
    }
}

function warehouse_api_text($value, int $max = 255): string
{
    $text = trim((string)($value ?? ''));
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $max, 'UTF-8');
    }
    return substr($text, 0, $max);
}

function warehouse_api_money($value): float
{
    $number = is_numeric($value) ? (float)$value : 0.0;
    return max(0.0, round($number, 2));
}

function warehouse_api_qty($value): int
{
    $number = is_numeric($value) ? (float)$value : 1.0;
    return max(1, (int)floor($number));
}

function warehouse_api_category_label(string $category): string
{
    $cat = function_exists('mb_strtolower') ? mb_strtolower(trim($category), 'UTF-8') : strtolower(trim($category));
    if (in_array($cat, ['tire_new_winter', 'new_winter_tire', 'new_winter_tires', 'шины зима новые', 'зимние шины новые', 'новые зимние шины'], true)) {
        return 'tire_new_winter';
    }
    if (in_array($cat, ['tire_new_summer', 'new_summer_tire', 'new_summer_tires', 'шины лето новые', 'летние шины новые', 'новые летние шины'], true)) {
        return 'tire_new_summer';
    }
    if (in_array($cat, ['tire_used_winter', 'used_winter_tire', 'used_winter_tires', 'шины зима б/у', 'зимние шины б/у', 'б/у зимние шины', 'бу зимние шины'], true)) {
        return 'tire_used_winter';
    }
    if (in_array($cat, ['tire_used_summer', 'used_summer_tire', 'used_summer_tires', 'шины лето б/у', 'летние шины б/у', 'б/у летние шины', 'бу летние шины'], true)) {
        return 'tire_used_summer';
    }
    if (in_array($cat, ['tire_winter', 'tires_winter', 'winter_tire', 'winter_tires', 'шины зима', 'зима', 'зимние шины'], true)) {
        return 'tire_winter';
    }
    if (in_array($cat, ['tire_summer', 'tires_summer', 'summer_tire', 'summer_tires', 'шины лето', 'лето', 'летние шины'], true)) {
        return 'tire_summer';
    }
    if (in_array($cat, ['tire_used', 'tires_used', 'used_tire', 'used_tires', 'шины б/у', 'б/у шины', 'бу шины'], true)) {
        return 'tire_used';
    }
    if (in_array($cat, ['tire_new', 'tires_new', 'new_tire', 'new_tires', 'шины новые', 'новые шины'], true)) {
        return 'tire_new';
    }
    if (in_array($cat, ['tire', 'tires', 'tyre', 'tyres', 'шины'], true)) {
        return 'tire';
    }
    if (in_array($cat, ['disk', 'disks', 'disc', 'discs', 'диски'], true)) {
        return 'disk';
    }
    if (in_array($cat, ['misc', 'mixed', 'other', 'product', 'солянка', 'товар', 'товары'], true)) {
        return 'misc';
    }
    return $category !== '' ? $category : 'product';
}

function warehouse_api_product_title(array $product, array $fallback): string
{
    $parts = [
        $fallback['name'] ?? '',
        $product['manufacturer'] ?? '',
        $product['parameters'] ?? '',
        $product['radius'] ?? '',
        $product['name'] ?? '',
        $product['size'] ?? '',
        $product['sku'] ?? '',
    ];
    $parts = array_values(array_filter(array_map(static fn($v): string => trim((string)$v), $parts)));
    return $parts ? implode(' ', array_unique($parts)) : 'Товар со склада';
}

function warehouse_api_is_admin(): bool
{
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function warehouse_api_current_user(): array
{
    return [
        'id' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0,
        'username' => warehouse_api_text($_SESSION['username'] ?? 'guest', 120),
    ];
}

function warehouse_api_json($value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function warehouse_api_product_qty($value): int
{
    return max(0, (int)floor((float)($value ?? 0)));
}

function warehouse_api_product_outgoing(array $product): int
{
    return warehouse_api_product_qty($product['outgoing'] ?? 0);
}

function warehouse_api_product_stock(array $product): int
{
    return warehouse_api_product_qty($product['stock'] ?? 0);
}

function warehouse_api_product_incoming(array $product): int
{
    $stock = warehouse_api_product_stock($product);
    $outgoing = warehouse_api_product_outgoing($product);
    $incoming = (array_key_exists('incoming', $product) && $product['incoming'] !== '')
        ? warehouse_api_product_qty($product['incoming'])
        : $stock + $outgoing;
    return max($incoming, $stock + $outgoing);
}

function warehouse_api_product_kind(array $product): string
{
    $category = warehouse_api_category_label((string)($product['category'] ?? ''));
    return $category === 'disk' ? 'диск' : (strpos($category, 'tire') === 0 ? 'шина' : ($category === 'misc' ? 'товар солянки' : 'товар'));
}

function warehouse_api_product_name_for_log(array $product): string
{
    $title = warehouse_api_product_title($product, []);
    return warehouse_api_text($title, 160);
}

function warehouse_api_source_label(string $source): string
{
    $labels = [
        'manual_create' => 'ручное добавление',
        'manual_edit' => 'ручная правка',
        'import_disks_excel' => 'добавление дисков из Excel',
        'import_tires_excel' => 'добавление шин из Excel',
        'import_smart_file' => 'умное добавление из файла',
        'warehouse_sale' => 'продажа со склада',
        'shinomontazh_sale' => 'продажа через шиномонтаж',
    ];
    return $labels[$source] ?? ($source !== '' ? $source : 'изменение');
}

function warehouse_api_recent_qty_source(array $product, string $logKey, int $delta): string
{
    $entries = isset($product[$logKey]) && is_array($product[$logKey]) ? $product[$logKey] : [];
    for ($i = count($entries) - 1; $i >= 0; $i -= 1) {
        $entry = $entries[$i];
        if (!is_array($entry)) {
            continue;
        }
        $qty = warehouse_api_product_qty($entry['qty'] ?? 0);
        if ($delta <= 0 || $qty === $delta) {
            return warehouse_api_source_label(warehouse_api_text($entry['source'] ?? '', 80));
        }
    }
    return 'изменение количества';
}

function warehouse_api_audit_log(string $message): void
{
    try {
        log_change('Склад: ' . $message);
    } catch (Throwable $e) {
        error_log('warehouse audit log failed: ' . $e->getMessage());
    }
}

function warehouse_api_log_state_changes(array $before, array $after): void
{
    $beforeProducts = [];
    foreach (($before['products'] ?? []) as $product) {
        if (is_array($product) && isset($product['id'])) {
            $beforeProducts[(string)$product['id']] = $product;
        }
    }

    $afterProducts = [];
    foreach (($after['products'] ?? []) as $product) {
        if (is_array($product) && isset($product['id'])) {
            $afterProducts[(string)$product['id']] = $product;
        }
    }

    $messages = [];
    $limit = 80;

    foreach ($afterProducts as $id => $product) {
        $kind = warehouse_api_product_kind($product);
        $name = warehouse_api_product_name_for_log($product);
        $stock = warehouse_api_product_qty($product['stock'] ?? 0);
        $incoming = warehouse_api_product_qty($product['incoming'] ?? 0);
        $outgoing = warehouse_api_product_qty($product['outgoing'] ?? 0);

        if (!isset($beforeProducts[$id])) {
            $source = warehouse_api_recent_qty_source($product, 'incomingLog', $incoming);
            $messages[] = "добавлен {$kind} \"{$name}\"; остаток {$stock} шт; приход {$incoming} шт; источник: {$source}";
            if (count($messages) >= $limit) {
                break;
            }
            continue;
        }

        $old = $beforeProducts[$id];
        $oldStock = warehouse_api_product_qty($old['stock'] ?? 0);
        $oldIncoming = warehouse_api_product_qty($old['incoming'] ?? 0);
        $oldOutgoing = warehouse_api_product_qty($old['outgoing'] ?? 0);
        $changes = [];

        if ($incoming !== $oldIncoming) {
            $delta = $incoming - $oldIncoming;
            $source = warehouse_api_recent_qty_source($product, 'incomingLog', abs($delta));
            $changes[] = 'приход ' . ($delta >= 0 ? '+' : '') . $delta . " шт ({$source})";
        }
        if ($outgoing !== $oldOutgoing) {
            $delta = $outgoing - $oldOutgoing;
            $source = warehouse_api_recent_qty_source($product, 'outgoingLog', abs($delta));
            $changes[] = 'расход ' . ($delta >= 0 ? '+' : '') . $delta . " шт ({$source})";
        }
        if ($stock !== $oldStock) {
            $delta = $stock - $oldStock;
            $changes[] = 'остаток ' . $oldStock . ' -> ' . $stock . ' шт (' . ($delta >= 0 ? '+' : '') . $delta . ')';
        }

        if ($changes) {
            $messages[] = "изменен {$kind} \"{$name}\": " . implode('; ', $changes);
            if (count($messages) >= $limit) {
                break;
            }
        }
    }

    if (count($messages) < $limit) {
        foreach ($beforeProducts as $id => $product) {
            if (isset($afterProducts[$id])) {
                continue;
            }
            $kind = warehouse_api_product_kind($product);
            $name = warehouse_api_product_name_for_log($product);
            $stock = warehouse_api_product_qty($product['stock'] ?? 0);
            $incoming = warehouse_api_product_qty($product['incoming'] ?? 0);
            $outgoing = warehouse_api_product_qty($product['outgoing'] ?? 0);
            $messages[] = "удален {$kind} \"{$name}\"; остаток был {$stock} шт; приход {$incoming} шт; расход {$outgoing} шт";
            if (count($messages) >= $limit) {
                break;
            }
        }
    }

    foreach ($messages as $message) {
        warehouse_api_audit_log($message);
    }

    $totalChanges = count($messages);
    if ($totalChanges >= $limit) {
        warehouse_api_audit_log("изменений больше {$limit}; часть записей скрыта для защиты лога от перегрузки");
    }
}

function warehouse_api_non_admin_save_allowed(array $current, array $next): bool
{
    $currentProducts = array_values($current['products'] ?? []);
    $nextProducts = array_values($next['products'] ?? []);
    if (count($currentProducts) !== count($nextProducts)) {
        return false;
    }

    $currentSales = array_values($current['sales'] ?? []);
    $nextSales = array_values($next['sales'] ?? []);
    if (count($nextSales) < count($currentSales)) {
        return false;
    }

    $currentSalesById = [];
    foreach ($currentSales as $sale) {
        if (!is_array($sale) || !isset($sale['id'])) {
            return false;
        }
        $currentSalesById[(string)$sale['id']] = warehouse_api_json($sale);
    }

    $addedSales = [];
    $changedExistingSales = 0;
    foreach ($nextSales as $sale) {
        if (!is_array($sale) || !isset($sale['id'])) {
            return false;
        }
        $saleId = (string)$sale['id'];
        if (isset($currentSalesById[$saleId])) {
            $currentSale = null;
            foreach ($currentSales as $candidate) {
                if (is_array($candidate) && (string)($candidate['id'] ?? '') === $saleId) {
                    $currentSale = $candidate;
                    break;
                }
            }
            $ignoredPaymentKeys = ['paidAmount' => true, 'debtAmount' => true, 'paid_amount' => true, 'debt_amount' => true];
            $currentComparable = is_array($currentSale) ? array_diff_key($currentSale, $ignoredPaymentKeys) : [];
            $nextComparable = array_diff_key($sale, $ignoredPaymentKeys);
            $currentTotal = warehouse_api_money($currentSale['total'] ?? 0);
            $currentPaid = warehouse_api_money($currentSale['paidAmount'] ?? $currentSale['paid_amount'] ?? 0);
            $nextPaid = warehouse_api_money($sale['paidAmount'] ?? $sale['paid_amount'] ?? 0);
            $nextDebt = warehouse_api_money($sale['debtAmount'] ?? $sale['debt_amount'] ?? 0);
            $paymentOnlyChangeAllowed = warehouse_api_json($currentComparable) === warehouse_api_json($nextComparable)
                && $nextPaid >= $currentPaid
                && $nextPaid <= $currentTotal
                && abs($nextDebt - max(0.0, $currentTotal - $nextPaid)) < 0.01;
            if (!$paymentOnlyChangeAllowed && $currentSalesById[$saleId] !== warehouse_api_json($sale)) {
                return false;
            }
            if ($paymentOnlyChangeAllowed && $currentSalesById[$saleId] !== warehouse_api_json($sale)) {
                $changedExistingSales += 1;
            }
            unset($currentSalesById[$saleId]);
        } else {
            $addedSales[] = $sale;
        }
    }
    if ($currentSalesById || (!$addedSales && $changedExistingSales === 0)) {
        return false;
    }

    $soldByProduct = [];
    foreach ($addedSales as $sale) {
        foreach (($sale['items'] ?? []) as $item) {
            if (!is_array($item) || ($item['type'] ?? 'product') === 'shinomontazh') {
                continue;
            }
            $productId = warehouse_api_text($item['productId'] ?? '', 120);
            if ($productId === '') {
                continue;
            }
            $soldByProduct[$productId] = ($soldByProduct[$productId] ?? 0) + warehouse_api_qty($item['qty'] ?? 1);
        }
    }

    $nextProductsById = [];
    foreach ($nextProducts as $product) {
        if (!is_array($product) || !isset($product['id'])) {
            return false;
        }
        $nextProductsById[(string)$product['id']] = $product;
    }

    $ignoredProductKeys = ['stock' => true, 'outgoing' => true, 'incoming' => true, 'outgoingLog' => true];
    foreach ($currentProducts as $product) {
        if (!is_array($product) || !isset($product['id'])) {
            return false;
        }
        $productId = (string)$product['id'];
        if (!isset($nextProductsById[$productId])) {
            return false;
        }
        $nextProduct = $nextProductsById[$productId];

        $currentCore = array_diff_key($product, $ignoredProductKeys);
        $nextCore = array_diff_key($nextProduct, $ignoredProductKeys);
        if (warehouse_api_json($currentCore) !== warehouse_api_json($nextCore)) {
            return false;
        }

        $soldQty = (int)($soldByProduct[$productId] ?? 0);
        $currentOutgoing = warehouse_api_product_outgoing($product);
        $nextOutgoing = warehouse_api_product_outgoing($nextProduct);
        if ($nextOutgoing !== $currentOutgoing + $soldQty) {
            return false;
        }

        $currentIncoming = warehouse_api_product_incoming($product);
        $nextIncoming = warehouse_api_product_incoming($nextProduct);
        // Non-admins may only sell existing stock. Receiving goods (raising the
        // incoming/приход counter) is an admin-only operation, so the incoming
        // value must stay exactly as it was.
        if ($nextIncoming !== $currentIncoming) {
            return false;
        }
        // Prevent overselling: the cumulative outgoing can never exceed what was
        // actually received. Without this a crafted request could drive stock
        // negative (it would silently floor to 0 below).
        if ($nextOutgoing > $nextIncoming) {
            return false;
        }

        $expectedStock = max(0, $nextIncoming - $nextOutgoing);
        $nextStock = warehouse_api_product_stock($nextProduct);
        if ($nextStock !== $expectedStock) {
            return false;
        }
    }

    $expectedSeqSale = max(1, (int)($current['seqSale'] ?? 1)) + count($addedSales);
    return max(1, (int)($next['seqSale'] ?? 1)) === $expectedSeqSale
        && max(1, (int)($next['seqProduct'] ?? 1)) === max(1, (int)($current['seqProduct'] ?? 1));
}

try {
    $conn = warehouse_open_database();
    $action = (string)($_GET['action'] ?? 'state');

    if ($action === 'state' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $currentUser = warehouse_api_current_user();
        $meta = warehouse_fetch_state_meta($conn);
        $etag = '"' . md5(($meta['updated_at'] ?? '') . '|' . (string)($meta['bytes'] ?? 0)) . '"';
        if (!headers_sent()) {
            header('Cache-Control: private, max-age=20, must-revalidate');
            header('ETag: ' . $etag);
            if (!empty($meta['updated_at_ts'])) {
                header('Last-Modified: ' . gmdate('D, d M Y H:i:s', (int)$meta['updated_at_ts']) . ' GMT');
            }
        }
        if ((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'rev' => (int)($meta['rev'] ?? 0),
            'state' => warehouse_decode_state_json((string)$meta['state_json']),
            'user' => [
                'is_admin' => warehouse_api_is_admin(),
                'id' => $currentUser['id'],
                'username' => $currentUser['username'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Некорректный JSON.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $state = isset($data['state']) && is_array($data['state']) ? $data['state'] : $data;
        $currentState = warehouse_fetch_state($conn);
        // Read the revision AFTER fetch_state (which may bump it once when migrating
        // inline base64 photos) so the comparison reflects the real current row.
        $expectedRev = warehouse_current_rev($conn);
        $clientRev = (isset($data['rev']) && is_numeric($data['rev'])) ? (int)$data['rev'] : null;
        if ($clientRev !== null && $clientRev !== $expectedRev) {
            http_response_code(409);
            echo json_encode([
                'ok' => false,
                'conflict' => true,
                'rev' => $expectedRev,
                'error' => 'Данные склада были изменены в другом сеансе. Обновите страницу и повторите.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $nextState = warehouse_normalize_state($state);
        if (!warehouse_api_is_admin()) {
            if (!warehouse_api_non_admin_save_allowed($currentState, $nextState)) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Редактирование склада доступно только администратору.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        $newRev = warehouse_save_state_checked($conn, $nextState, $expectedRev);
        if ($newRev === null) {
            http_response_code(409);
            echo json_encode([
                'ok' => false,
                'conflict' => true,
                'rev' => warehouse_current_rev($conn),
                'error' => 'Данные склада были изменены в другом сеансе. Обновите страницу и повторите.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        warehouse_api_log_state_changes($currentState, $nextState);
        echo json_encode(['ok' => true, 'rev' => $newRev], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'log' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Некорректный JSON.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $event = warehouse_api_text($data['event'] ?? '', 80);
        $category = warehouse_api_text($data['category'] ?? '', 80);
        $count = warehouse_api_product_qty($data['count'] ?? 0);
        if ($event !== 'export') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Неизвестное событие склада.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $categoryText = $category === 'disks' ? 'дисков' : ($category === 'tires' ? 'шин' : 'товаров');
        warehouse_api_audit_log("выгрузка {$categoryText}; строк {$count}");
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'finalize_combined_sale' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Некорректный JSON.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $warehouseItems = isset($data['warehouse_items']) && is_array($data['warehouse_items'])
            ? array_values($data['warehouse_items'])
            : [];
        if (!$warehouseItems) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Нет складских товаров для реализации.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $state = warehouse_fetch_state($conn);
        $expectedRev = warehouse_current_rev($conn);
        $beforeState = $state;
        $documentNumber = warehouse_api_text($data['document_number'] ?? '', 80);
        // Add a short unique suffix to auto-generated numbers so two sales created
        // within the same second are not mistaken for one another (the per-second
        // 'НК-YmdHis' value used to collide and be treated as a duplicate).
        $docNo = $documentNumber !== '' ? $documentNumber : 'НК-' . date('YmdHis') . '-' . substr(uniqid('', true), -5);
        $operationId = warehouse_api_text($data['operation_id'] ?? '', 80);
        $customer = warehouse_api_text($data['customer'] ?? '', 255);
        if ($customer === '') {
            $customer = 'Без покупателя';
        }
        $createdAt = warehouse_api_text($data['created_at'] ?? '', 80);
        $saleDate = strtotime($createdAt) ? date('c', strtotime($createdAt)) : date('c');
        $soldBy = warehouse_api_current_user();

        $existingSaleIndex = null;
        foreach ($state['sales'] as $saleIndex => $sale) {
            $linked = isset($sale['shinomontazhOperation']) && is_array($sale['shinomontazhOperation'])
                ? $sale['shinomontazhOperation']
                : [];
            $linkedOperationId = warehouse_api_text($linked['id'] ?? '', 80);
            if (($operationId !== '' && $linkedOperationId === $operationId) || warehouse_api_text($sale['docNo'] ?? '', 80) === $docNo) {
                $existingSaleIndex = $saleIndex;
                break;
            }
        }

        $productIndex = [];
        foreach ($state['products'] as $idx => $product) {
            if (is_array($product) && isset($product['id'])) {
                $productIndex[(string)$product['id']] = $idx;
            }
        }

        $skipStockMutation = false;
        if ($existingSaleIndex !== null) {
            $previousItems = isset($state['sales'][$existingSaleIndex]['items']) && is_array($state['sales'][$existingSaleIndex]['items'])
                ? $state['sales'][$existingSaleIndex]['items']
                : [];
            $previousWarehouseQty = [];
            $nextWarehouseQty = [];
            foreach ($previousItems as $item) {
                if (warehouse_api_text($item['type'] ?? '', 40) !== 'product') {
                    continue;
                }
                $pid = warehouse_api_text($item['productId'] ?? '', 120);
                if ($pid !== '') {
                    $previousWarehouseQty[$pid] = ($previousWarehouseQty[$pid] ?? 0) + warehouse_api_qty($item['qty'] ?? 1);
                }
            }
            foreach ($warehouseItems as $line) {
                if (!is_array($line)) {
                    continue;
                }
                $pid = warehouse_api_text($line['productId'] ?? '', 120);
                if ($pid !== '') {
                    $nextWarehouseQty[$pid] = ($nextWarehouseQty[$pid] ?? 0) + warehouse_api_qty($line['qty'] ?? 1);
                }
            }
            ksort($previousWarehouseQty);
            ksort($nextWarehouseQty);
            if ($previousWarehouseQty !== $nextWarehouseQty) {
                throw new RuntimeException('Складская продажа уже отражена. Измените количество складских товаров отдельной корректировкой на складе.');
            }
            $skipStockMutation = true;
        }

        $saleItems = [];
        foreach ($warehouseItems as $line) {
            if (!is_array($line)) {
                continue;
            }
            $productId = warehouse_api_text($line['productId'] ?? '', 120);
            if ($productId === '' || !array_key_exists($productId, $productIndex)) {
                throw new RuntimeException('Один из товаров склада не найден.');
            }
            $idx = $productIndex[$productId];
            $product = is_array($state['products'][$idx]) ? $state['products'][$idx] : [];
            // Ignore zero/negative quantity lines instead of silently coercing them to 1.
            $rawQty = $line['qty'] ?? 1;
            if (is_numeric($rawQty) && (float)$rawQty <= 0) {
                continue;
            }
            $qty = warehouse_api_qty($rawQty);
            $stock = warehouse_api_product_stock($product);
            if (!$skipStockMutation && $qty > $stock) {
                $name = warehouse_api_product_title($product, $line);
                throw new RuntimeException("Недостаточный остаток товара: {$name}.");
            }
            $price = warehouse_api_money($line['price'] ?? ($product['price'] ?? 0));
            $sum = warehouse_api_money($qty * $price);
            $category = warehouse_api_category_label((string)($product['category'] ?? ($line['category'] ?? 'product')));
            $item = [
                'type' => 'product',
                'productId' => $productId,
                'category' => $category,
                'name' => warehouse_api_product_title($product, $line),
                'size' => warehouse_api_text($product['radius'] ?? $product['size'] ?? $line['size'] ?? '', 120),
                'sku' => warehouse_api_text($product['sku'] ?? $product['diskNo'] ?? $product['cartonNo'] ?? $line['sku'] ?? '', 120),
                'qty' => $qty,
                'price' => $price,
                'sum' => $sum,
            ];
            $saleItems[] = $item;

            if (!$skipStockMutation) {
                $nextOutgoing = warehouse_api_product_outgoing($product) + $qty;
                $nextStock = max(0, $stock - $qty);
                $state['products'][$idx]['outgoing'] = $nextOutgoing;
                $state['products'][$idx]['incoming'] = max(warehouse_api_product_incoming($product), $nextOutgoing + $nextStock);
                $state['products'][$idx]['stock'] = $nextStock;
                if (!isset($state['products'][$idx]['outgoingLog']) || !is_array($state['products'][$idx]['outgoingLog'])) {
                    $state['products'][$idx]['outgoingLog'] = [];
                }
                $state['products'][$idx]['outgoingLog'][] = [
                    'id' => 'OUT-' . str_replace('.', '', uniqid('', true)),
                    'qty' => $qty,
                    'source' => 'shinomontazh_sale',
                    'date' => $saleDate,
                    'soldBy' => $soldBy,
                ];
            }
        }

        $operationItems = isset($data['items']) && is_array($data['items']) ? array_values($data['items']) : [];
        foreach ($operationItems as $line) {
            if (!is_array($line)) {
                continue;
            }
            if (warehouse_api_text($line['source'] ?? '', 40) === 'warehouse') {
                continue;
            }
            $name = warehouse_api_text($line['name'] ?? '', 255);
            $sum = warehouse_api_money($line['sum'] ?? 0);
            if ($name === '' || $sum <= 0) {
                continue;
            }
            $saleItems[] = [
                'type' => 'shinomontazh',
                'productId' => '',
                'category' => 'shinomontazh',
                'name' => $name,
                'size' => '',
                'sku' => 'услуга',
                'qty' => 1,
                'price' => $sum,
                'sum' => $sum,
            ];
        }

        if (!$saleItems) {
            throw new RuntimeException('Нет позиций для складской истории продажи.');
        }

        $total = array_reduce($saleItems, static fn(float $sum, array $item): float => $sum + (float)($item['sum'] ?? 0), 0.0);
        $saleTotal = warehouse_api_money($data['total'] ?? $total);
        $paidAmount = min($saleTotal, warehouse_api_money($data['paid_amount'] ?? $saleTotal));
        $debtAmount = max(0.0, round($saleTotal - $paidAmount, 2));
        $saleId = $existingSaleIndex !== null
            ? warehouse_api_text($state['sales'][$existingSaleIndex]['id'] ?? '', 80)
            : 'S-' . max(1, (int)($state['seqSale'] ?? 1));
        if ($saleId === '') {
            $saleId = 'S-' . max(1, (int)($state['seqSale'] ?? 1));
        }
        if ($existingSaleIndex === null) {
            $state['seqSale'] = max(1, (int)($state['seqSale'] ?? 1)) + 1;
        }
        $sale = [
            'id' => $saleId,
            'docNo' => $docNo,
            'customer' => $customer,
            'date' => $saleDate,
            'items' => $saleItems,
            'total' => $saleTotal,
            'paidAmount' => $paidAmount,
            'debtAmount' => $debtAmount,
            'soldBy' => $soldBy,
            'shinomontazhOperation' => [
                'id' => $operationId,
                'documentNumber' => $documentNumber,
            ],
        ];
        if ($existingSaleIndex !== null) {
            // Warehouse-quantity equality was already verified above (which set
            // $skipStockMutation); here we only need to persist the refreshed sale,
            // keeping the original date.
            $sale['date'] = $state['sales'][$existingSaleIndex]['date'] ?? $saleDate;
            $state['sales'][$existingSaleIndex] = $sale;
        } else {
            array_unshift($state['sales'], $sale);
        }
        if (warehouse_save_state_checked($conn, $state, $expectedRev) === null) {
            http_response_code(409);
            echo json_encode([
                'ok' => false,
                'conflict' => true,
                'error' => 'Данные склада были изменены в другом сеансе. Повторите операцию.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        warehouse_api_log_state_changes($beforeState, $state);

        echo json_encode([
            'ok' => true,
            'sale' => $sale,
            'duplicate' => false,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'pay_debt' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Некорректный JSON.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $state = warehouse_fetch_state($conn);
        $expectedRev = warehouse_current_rev($conn);
        $saleId = warehouse_api_text($data['sale_id'] ?? '', 80);
        $operationId = warehouse_api_text($data['operation_id'] ?? '', 80);
        $documentNumber = warehouse_api_text($data['document_number'] ?? '', 80);
        $amount = warehouse_api_money($data['amount'] ?? 0);
        if ($amount <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Укажите сумму погашения долга.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $found = false;
        foreach ($state['sales'] as &$sale) {
            if (!is_array($sale)) {
                continue;
            }
            $linked = isset($sale['shinomontazhOperation']) && is_array($sale['shinomontazhOperation'])
                ? $sale['shinomontazhOperation']
                : [];
            $matches = ($saleId !== '' && warehouse_api_text($sale['id'] ?? '', 80) === $saleId)
                || ($operationId !== '' && warehouse_api_text($linked['id'] ?? '', 80) === $operationId)
                || ($documentNumber !== '' && warehouse_api_text($sale['docNo'] ?? '', 80) === $documentNumber);
            if (!$matches) {
                continue;
            }

            $total = warehouse_api_money($sale['total'] ?? 0);
            $paid = warehouse_api_money($sale['paidAmount'] ?? $sale['paid_amount'] ?? 0);
            $debt = warehouse_api_money($sale['debtAmount'] ?? $sale['debt_amount'] ?? max(0, $total - $paid));
            $nextPaid = min($total, round($paid + min($amount, $debt), 2));
            $appliedAmount = round($nextPaid - $paid, 2);
            $sale['paidAmount'] = $nextPaid;
            $sale['debtAmount'] = max(0.0, round($total - $nextPaid, 2));
            $paidSaleDocNo = warehouse_api_text($sale['docNo'] ?? $sale['id'] ?? '', 80);
            $paidSaleRemainingDebt = $sale['debtAmount'];
            $found = true;
            break;
        }
        unset($sale);

        if (!$found) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Продажа склада не найдена.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (warehouse_save_state_checked($conn, $state, $expectedRev) === null) {
            http_response_code(409);
            echo json_encode([
                'ok' => false,
                'conflict' => true,
                'error' => 'Данные склада были изменены в другом сеансе. Повторите операцию.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $payActor = warehouse_api_current_user();
        warehouse_api_audit_log(sprintf(
            'погашение долга по продаже "%s"; внесено %s; остаток долга %s; пользователь %s',
            $paidSaleDocNo,
            number_format((float)($appliedAmount ?? 0), 2, '.', ''),
            number_format((float)($paidSaleRemainingDebt ?? 0), 2, '.', ''),
            $payActor['username']
        ));
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!warehouse_api_is_admin()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Сброс склада доступен только администратору.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $beforeState = warehouse_fetch_state($conn);
        warehouse_reset_state($conn);
        warehouse_api_audit_log('полный сброс склада');
        warehouse_api_log_state_changes($beforeState, warehouse_default_state());
        echo json_encode([
            'ok' => true,
            'state' => warehouse_fetch_state($conn),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'clear_imported' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!warehouse_api_is_admin()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Удаление записей, добавленных из файла, доступно только администратору.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $state = warehouse_fetch_state($conn);
        $expectedRev = warehouse_current_rev($conn);
        $beforeState = $state;
        $products = isset($state['products']) && is_array($state['products']) ? array_values($state['products']) : [];
        $importSources = [
            'import_tires_excel' => true,
            'import_disks_excel' => true,
            'import_smart_file' => true,
        ];
        $keptProducts = [];
        $removed = 0;

        foreach ($products as $product) {
            if (!is_array($product)) {
                continue;
            }

            $isImported = false;
            $incomingLog = isset($product['incomingLog']) && is_array($product['incomingLog']) ? $product['incomingLog'] : [];
            foreach ($incomingLog as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $source = warehouse_api_text($entry['source'] ?? '', 80);
                if (isset($importSources[$source])) {
                    $isImported = true;
                    break;
                }
            }

            if ($isImported) {
                $removed += 1;
                continue;
            }

            $keptProducts[] = $product;
        }

        $state['products'] = $keptProducts;
        if (warehouse_save_state_checked($conn, $state, $expectedRev) === null) {
            http_response_code(409);
            echo json_encode([
                'ok' => false,
                'conflict' => true,
                'error' => 'Данные склада были изменены в другом сеансе. Повторите операцию.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        warehouse_api_audit_log("удалены позиции, добавленные из файла; удалено {$removed}");
        warehouse_api_log_state_changes($beforeState, $state);
        echo json_encode([
            'ok' => true,
            'removed' => $removed,
            'state' => warehouse_fetch_state($conn),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Метод или action не поддерживается.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('warehouse_api.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
