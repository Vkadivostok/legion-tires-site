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

function zakaz_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function zakaz_require_admin(): void
{
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        zakaz_json_response(['ok' => false, 'error' => 'Доступ к настройкам разрешен только администратору.'], 403);
    }
}

function zakaz_money($value): float
{
    $number = is_numeric($value) ? (float)$value : 0.0;
    return max(0.0, round($number, 2));
}

function zakaz_text($value, int $max = 255): string
{
    $text = trim((string)($value ?? ''));
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $max, 'UTF-8');
    }
    return substr($text, 0, $max);
}

function zakaz_discount_percent($value): float
{
    $number = is_numeric($value) ? (float)$value : 0.0;
    $number = max(0.0, min(100.0, $number));
    return round($number, 1);
}

function zakaz_payroll_category($value, string $fallback = ''): string
{
    $category = strtolower(trim((string)($value ?? '')));
    if (in_array($category, ['tire', 'shinomontazh', 'шиномонтаж'], true)) {
        return 'tire';
    }
    if (in_array($category, ['argon', 'argon_welding', 'аргон'], true)) {
        return 'argon';
    }
    if (in_array($category, ['storage', 'хранение'], true)) {
        return 'storage';
    }
    if (in_array($category, ['warehouse_admin', 'warehouse-admin', 'склад', 'продажа шин/дисков', 'продажа со склада'], true)) {
        return 'warehouse_admin';
    }
    return $fallback;
}

function zakaz_payroll_rate($value, float $fallback = 0.0): float
{
    if (is_string($value)) {
        $value = str_replace([' ', ','], ['', '.'], trim($value));
        $value = rtrim($value, '%');
    }
    $number = is_numeric($value) ? (float)$value : $fallback;
    if ($number > 1.0) {
        $number = $number / 100.0;
    }
    return max(0.0, min(1.0, round($number, 4)));
}

function zakaz_normalize_warehouse_commission_key(array $item): string
{
    $category = str_replace('ё', 'е', strtolower(zakaz_text($item['category'] ?? '', 120)));
    $name = str_replace('ё', 'е', strtolower(zakaz_text($item['name'] ?? '', 255)));
    $sku = str_replace('ё', 'е', strtolower(zakaz_text($item['sku'] ?? '', 120)));
    $size = str_replace('ё', 'е', strtolower(zakaz_text($item['size'] ?? '', 120)));
    $haystack = trim($category . ' ' . $name . ' ' . $sku . ' ' . $size);

    if ($category === 'disk' || preg_match('/(^|[^a-zа-я0-9])(disk|disc|диск|диски)([^a-zа-я0-9]|$)/iu', $haystack)) {
        return 'disk';
    }
    if (strpos($category, 'tire_used_winter') !== false) return 'tire_used_winter';
    if (strpos($category, 'tire_used_summer') !== false) return 'tire_used_summer';
    if (strpos($category, 'tire_new_winter') !== false) return 'tire_new_winter';
    if (strpos($category, 'tire_new_summer') !== false) return 'tire_new_summer';

    $hasUsed = (bool)preg_match('/(^|[^a-zа-я0-9])(used|б\s*\/\s*у|б\.?\s*у\.?|бу|бэу)([^a-zа-я0-9]|$)|(^|[_-])used([_-]|$)/iu', $haystack);
    $hasNew = (bool)preg_match('/(^|[^a-zа-я0-9])(new|новые|новая|новый)([^a-zа-я0-9]|$)|(^|[_-])new([_-]|$)/iu', $haystack);
    $hasWinter = (bool)preg_match('/(^|[^a-zа-я0-9])(winter|зима|зимние|шип|липуч)([^a-zа-я0-9]|$)/iu', $haystack);
    $hasSummer = (bool)preg_match('/(^|[^a-zа-я0-9])(summer|лето|летние)([^a-zа-я0-9]|$)/iu', $haystack);

    if ($hasUsed && $hasWinter) return 'tire_used_winter';
    if ($hasUsed && $hasSummer) return 'tire_used_summer';
    if ($hasNew && $hasWinter) return 'tire_new_winter';
    if ($hasNew && $hasSummer) return 'tire_new_summer';
    if ($hasUsed) return 'tire_used';
    if ($hasNew) return 'tire_new';

    $hasTire = preg_match('/(^|[^a-zа-я0-9])(tire|tires|tyre|tyres|шина|шины|резина|резины)([^a-zа-я0-9]|$)/iu', $haystack)
        || preg_match('/^tire[_-]/i', $category)
        || preg_match('/(^|[_\s-])(winter|summer|зима|лето|шип|липуч)/iu', $haystack);

    return $hasTire ? 'tire' : '';
}

function zakaz_warehouse_commission_rule(array $item, array $prices): ?array
{
    $source = strtolower(zakaz_text($item['source'] ?? '', 40));
    $key = zakaz_normalize_warehouse_commission_key($item);
    if ($source === 'warehouse' && $key === '') {
        $key = 'tire';
    }
    if ($source !== 'warehouse' && $key === '') {
        return null;
    }

    $commissions = isset($prices['warehouseCommissions']) && is_array($prices['warehouseCommissions'])
        ? $prices['warehouseCommissions']
        : zakaz_default_prices()['warehouseCommissions'];
    $row = $key !== '' && isset($commissions[$key]) && is_array($commissions[$key])
        ? $commissions[$key]
        : null;
    if (!$row) {
        return null;
    }

    $category = zakaz_payroll_category($row['payrollCategory'] ?? ($row['payroll_category'] ?? ''), 'warehouse_admin');
    $rate = zakaz_payroll_rate($row['adminPayrollRate'] ?? ($row['admin_payroll_rate'] ?? ($row['rate'] ?? 0)), 0.0);
    if ($category !== 'warehouse_admin' || $rate <= 0) {
        return null;
    }

    return [
        'key' => $key,
        'rate' => $rate,
        'label' => zakaz_text($row['label'] ?? $key, 120),
    ];
}

function zakaz_normalize_operation_items(array $items, ?array $prices = null): array
{
    $normalized = [];
    $subtotal = 0.0;
    $discountTotal = 0.0;
    $total = 0.0;
    $priceRules = is_array($prices) ? $prices : zakaz_default_prices();

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $name = zakaz_text($item['name'] ?? '', 255);
        $baseSum = zakaz_money($item['base_sum'] ?? ($item['baseSum'] ?? ($item['sum'] ?? 0)));
        if ($name === '' || $baseSum <= 0) {
            continue;
        }

        $discountPercent = zakaz_discount_percent($item['discount_percent'] ?? ($item['discountPercent'] ?? 0));
        $discountAmount = min($baseSum, round($baseSum * $discountPercent / 100, 2));
        $lineTotal = max(0.0, round($baseSum - $discountAmount, 2));

        $warehouseRule = zakaz_warehouse_commission_rule($item, $priceRules);
        $payrollCategory = zakaz_payroll_category($item['payroll_category'] ?? ($item['payrollCategory'] ?? ''), $warehouseRule ? 'warehouse_admin' : '');
        $defaultWorkerRate = $payrollCategory === 'argon' ? 0.35 : ($payrollCategory === 'tire' ? 0.32 : 0.0);
        $defaultAdminRate = $payrollCategory === 'storage' ? 0.20 : (($payrollCategory === 'tire' || $payrollCategory === 'argon') ? 0.03 : 0.0);
        $hasPayrollRate = array_key_exists('payroll_rate', $item) || array_key_exists('payrollRate', $item);
        $hasAdminPayrollRate = array_key_exists('admin_payroll_rate', $item) || array_key_exists('adminPayrollRate', $item) || array_key_exists('warehouse_admin_rate', $item);
        $adminPayrollRateValue = $item['admin_payroll_rate'] ?? ($item['adminPayrollRate'] ?? ($item['warehouse_admin_rate'] ?? null));
        $adminPayrollRate = zakaz_payroll_rate($adminPayrollRateValue, $hasAdminPayrollRate ? 0.0 : $defaultAdminRate);
        $warehouseAdminRate = zakaz_payroll_rate($item['warehouse_admin_rate'] ?? $adminPayrollRateValue, $hasAdminPayrollRate ? 0.0 : $defaultAdminRate);
        $warehouseCommissionLabel = zakaz_text($item['warehouse_commission_label'] ?? ($item['payroll_label'] ?? ''), 120);
        if ($warehouseRule && ($adminPayrollRate <= 0 || $warehouseAdminRate <= 0 || $warehouseCommissionLabel === '')) {
            $payrollCategory = 'warehouse_admin';
            $adminPayrollRate = $warehouseRule['rate'];
            $warehouseAdminRate = $warehouseRule['rate'];
            $warehouseCommissionLabel = $warehouseRule['label'] . ' ' . rtrim(rtrim(number_format($warehouseRule['rate'] * 100, 2, '.', ''), '0'), '.') . '%';
        }

        $normalized[] = [
            'source' => zakaz_text($item['source'] ?? '', 40),
            'payroll_category' => $payrollCategory,
            'payroll_rate' => zakaz_payroll_rate($item['payroll_rate'] ?? ($item['payrollRate'] ?? null), $hasPayrollRate ? 0.0 : $defaultWorkerRate),
            'admin_payroll_rate' => $adminPayrollRate,
            'warehouse_admin_rate' => $warehouseAdminRate,
            'warehouse_commission_label' => $warehouseCommissionLabel,
            'productId' => zakaz_text($item['productId'] ?? ($item['product_id'] ?? ''), 120),
            'category' => zakaz_text($item['category'] ?? '', 40),
            'size' => zakaz_text($item['size'] ?? '', 120),
            'sku' => zakaz_text($item['sku'] ?? '', 120),
            'price' => zakaz_money($item['price'] ?? 0),
            'name' => $name,
            'qty' => zakaz_text($item['qty'] ?? '', 80),
            'base_sum' => $baseSum,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'sum' => $lineTotal,
        ];

        $subtotal += $baseSum;
        $discountTotal += $discountAmount;
        $total += $lineTotal;
    }

    return [
        'items' => $normalized,
        'subtotal' => round($subtotal, 2),
        'discount' => round($discountTotal, 2),
        'total' => round($total, 2),
    ];
}

function zakaz_default_prices(): array
{
    return [
        'base' => [
            'passenger' => [
                'R14' => ['1' => 750, '4' => 2900],
                'R15' => ['1' => 775, '4' => 3000],
                'R16' => ['1' => 850, '4' => 3300],
                'R17' => ['1' => 950, '4' => 3700],
                'R18' => ['1' => 1050, '4' => 4000],
                'R19' => ['1' => 1100, '4' => 4300],
                'R20' => ['1' => 1150, '4' => 4500],
                'R21' => ['1' => 1300, '4' => 5000],
                'R22' => ['1' => 1400, '4' => 5500],
                'R23' => ['1' => 1550, '4' => 6000],
                'R24' => ['1' => 1650, '4' => 6500],
            ],
            'suv' => [
                'R16' => ['1' => 900, '4' => 3500],
                'R17' => ['1' => 975, '4' => 3900],
                'R18' => ['1' => 1100, '4' => 4300],
                'R19' => ['1' => 1150, '4' => 4500],
                'R20' => ['1' => 1300, '4' => 5000],
                'R21' => ['1' => 1400, '4' => 5500],
                'R22' => ['1' => 1550, '4' => 6000],
                'R23' => ['1' => 1650, '4' => 6500],
                'R24' => ['1' => 1800, '4' => 7000],
            ],
        ],
        'extras' => [
            'runFlat' => ['name' => 'Шины RUN FLAT', 'price' => 150, 'unit' => 'руб/шт', 'payrollCategory' => 'tire'],
            'wash' => ['name' => 'Мойка колес', 'price' => 75, 'unit' => 'руб/шт', 'payrollCategory' => 'tire'],
            'sealant' => ['name' => 'Промазка герметиком', 'price' => 150, 'unit' => 'руб/шт', 'payrollCategory' => 'tire'],
            'hub' => ['name' => 'Обработка ступицы', 'price' => 100, 'unit' => 'руб/шт', 'payrollCategory' => 'tire'],
            'valveBlack' => ['name' => 'Вентиль черный', 'price' => 125, 'unit' => 'руб/шт', 'payrollCategory' => 'tire'],
            'valveChrome' => ['name' => 'Вентиль хром', 'price' => 150, 'unit' => 'руб/шт', 'payrollCategory' => 'tire'],
            'bags' => ['name' => 'Пакет для колес', 'price' => 50, 'unit' => 'руб/шт', 'payrollCategory' => 'tire'],
            'weightsBlack' => ['name' => 'Груза черные', 'price' => 75, 'unit' => 'руб/колесо', 'payrollCategory' => 'tire'],
            'weightsPainted' => ['name' => 'Груза крашеные', 'price' => 100, 'unit' => 'руб/колесо', 'payrollCategory' => 'tire'],
            'puncture' => ['name' => 'Ремонт бескамерной шины жгутом', 'price' => 300, 'unit' => 'руб/шт', 'payrollCategory' => 'tire'],
            'freon' => ['name' => 'Фреон', 'price' => 0, 'unit' => 'руб', 'payrollCategory' => 'tire'],
            'argonWelding' => ['name' => 'Аргоновая сварка', 'price' => 0, 'unit' => 'руб', 'payrollCategory' => 'argon'],
        ],
        'storage' => [
            ['label' => 'R13–R15', 'maxR' => 15, 'tires' => 3000, 'wheels' => 3500],
            ['label' => 'R16–R17', 'maxR' => 17, 'tires' => 3500, 'wheels' => 4000],
            ['label' => 'R18–R19', 'maxR' => 19, 'tires' => 4000, 'wheels' => 4500],
            ['label' => 'R20–R22', 'maxR' => 22, 'tires' => 4500, 'wheels' => 5000],
            ['label' => 'R23 и выше', 'maxR' => 999, 'tires' => 5500, 'wheels' => 6000],
        ],
        'warehouseCommissions' => [
            'disk' => ['label' => 'Диски', 'payrollCategory' => 'warehouse_admin', 'payrollRate' => 0.0, 'adminPayrollRate' => 0.03, 'rate' => 0.03],
            'tire_new_winter' => ['label' => 'Шины зима новые', 'payrollCategory' => 'warehouse_admin', 'payrollRate' => 0.0, 'adminPayrollRate' => 0.03, 'rate' => 0.03],
            'tire_new_summer' => ['label' => 'Шины лето новые', 'payrollCategory' => 'warehouse_admin', 'payrollRate' => 0.0, 'adminPayrollRate' => 0.03, 'rate' => 0.03],
            'tire_used_winter' => ['label' => 'Шины зима б/у', 'payrollCategory' => 'warehouse_admin', 'payrollRate' => 0.0, 'adminPayrollRate' => 0.04, 'rate' => 0.04],
            'tire_used_summer' => ['label' => 'Шины лето б/у', 'payrollCategory' => 'warehouse_admin', 'payrollRate' => 0.0, 'adminPayrollRate' => 0.04, 'rate' => 0.04],
            'tire_new' => ['label' => 'Шины новые', 'payrollCategory' => 'warehouse_admin', 'payrollRate' => 0.0, 'adminPayrollRate' => 0.03, 'rate' => 0.03],
            'tire_used' => ['label' => 'Шины б/у', 'payrollCategory' => 'warehouse_admin', 'payrollRate' => 0.0, 'adminPayrollRate' => 0.04, 'rate' => 0.04],
            'tire' => ['label' => 'Шины прочие', 'payrollCategory' => 'warehouse_admin', 'payrollRate' => 0.0, 'adminPayrollRate' => 0.03, 'rate' => 0.03],
        ],
    ];
}

function zakaz_format_document_number(int $value): string
{
    return str_pad((string)max(1, $value), 4, '0', STR_PAD_LEFT);
}

function zakaz_get_setting(mysqli $conn, string $key): ?string
{
    $stmt = $conn->prepare('SELECT setting_value FROM zakaz_settings WHERE setting_key = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Ошибка подготовки чтения настроек.');
    }
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? (string)$row['setting_value'] : null;
}

function zakaz_save_setting(mysqli $conn, string $key, string $value): void
{
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $username = zakaz_text($_SESSION['username'] ?? 'guest', 50);
    $stmt = $conn->prepare(
        'INSERT INTO zakaz_settings (setting_key, setting_value, updated_by, updated_by_username)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value),
                                 updated_by = VALUES(updated_by),
                                 updated_by_username = VALUES(updated_by_username)'
    );
    if (!$stmt) {
        throw new RuntimeException('Ошибка подготовки сохранения настроек.');
    }
    $stmt->bind_param('ssis', $key, $value, $userId, $username);
    if (!$stmt->execute()) {
        throw new RuntimeException('Не удалось сохранить настройки.');
    }
    $stmt->close();
}

function zakaz_normalize_prices(array $prices): array
{
    $fallback = zakaz_default_prices();
    $base = isset($prices['base']) && is_array($prices['base']) ? $prices['base'] : $fallback['base'];
    $extras = isset($prices['extras']) && is_array($prices['extras'])
        ? array_replace($fallback['extras'], $prices['extras'])
        : $fallback['extras'];
    $storage = isset($prices['storage']) && is_array($prices['storage']) ? array_values($prices['storage']) : $fallback['storage'];
    $warehouseCommissionsRaw = $prices['warehouseCommissions'] ?? ($prices['warehouse_commissions'] ?? []);
    $warehouseCommissions = is_array($warehouseCommissionsRaw)
        ? array_replace($fallback['warehouseCommissions'], $warehouseCommissionsRaw)
        : $fallback['warehouseCommissions'];

    foreach ($fallback['extras'] as $id => $fallbackRow) {
        if (isset($extras[$id]) && is_array($extras[$id])) {
            $extras[$id] = array_replace($fallbackRow, $extras[$id]);
        }
    }

    foreach (['passenger', 'suv'] as $carType) {
        if (!isset($base[$carType]) || !is_array($base[$carType])) {
            $base[$carType] = $fallback['base'][$carType];
        }
        foreach ($base[$carType] as $radius => $row) {
            if (!is_array($row)) {
                unset($base[$carType][$radius]);
                continue;
            }
            $defaultCategory = 'tire';
            $hasPayrollCategory = array_key_exists('payrollCategory', $row) || array_key_exists('payroll_category', $row);
            $base[$carType][$radius] = [
                '1' => max(0, (int)($row['1'] ?? $row[1] ?? 0)),
                '4' => max(0, (int)($row['4'] ?? $row[4] ?? 0)),
                'payrollCategory' => zakaz_payroll_category($row['payrollCategory'] ?? ($row['payroll_category'] ?? ''), $hasPayrollCategory ? '' : $defaultCategory),
                'payrollRate' => zakaz_payroll_rate($row['payrollRate'] ?? ($row['payroll_rate'] ?? null), 0.32),
                'adminPayrollRate' => zakaz_payroll_rate($row['adminPayrollRate'] ?? ($row['admin_payroll_rate'] ?? null), 0.03),
            ];
        }
    }

    foreach ($extras as $id => $row) {
        if (!is_array($row)) {
            unset($extras[$id]);
            continue;
        }
        $defaultCategory = $id === 'argonWelding' ? 'argon' : 'tire';
        $defaultWorkerRate = $defaultCategory === 'argon' ? 0.35 : 0.32;
        $hasPayrollCategory = array_key_exists('payrollCategory', $row) || array_key_exists('payroll_category', $row);
        $extras[$id] = [
            'name' => zakaz_text($row['name'] ?? $id, 160),
            'unit' => zakaz_text($row['unit'] ?? 'руб', 60),
            'price' => max(0, (int)($row['price'] ?? 0)),
            'payrollCategory' => zakaz_payroll_category($row['payrollCategory'] ?? ($row['payroll_category'] ?? ''), $hasPayrollCategory ? '' : $defaultCategory),
            'payrollRate' => zakaz_payroll_rate($row['payrollRate'] ?? ($row['payroll_rate'] ?? null), $defaultWorkerRate),
            'adminPayrollRate' => zakaz_payroll_rate($row['adminPayrollRate'] ?? ($row['admin_payroll_rate'] ?? null), 0.03),
        ];
    }

    $storage = array_values(array_filter(array_map(static function ($row): ?array {
        if (!is_array($row)) {
            return null;
        }
        return [
            'label' => zakaz_text($row['label'] ?? '', 80),
            'maxR' => max(0, (int)($row['maxR'] ?? 0)),
            'tires' => max(0, (int)($row['tires'] ?? 0)),
            'wheels' => max(0, (int)($row['wheels'] ?? 0)),
            'payrollCategory' => zakaz_payroll_category($row['payrollCategory'] ?? ($row['payroll_category'] ?? ''), (array_key_exists('payrollCategory', $row) || array_key_exists('payroll_category', $row)) ? '' : 'storage'),
            'payrollRate' => zakaz_payroll_rate($row['payrollRate'] ?? ($row['payroll_rate'] ?? null), 0.0),
            'adminPayrollRate' => zakaz_payroll_rate($row['adminPayrollRate'] ?? ($row['admin_payroll_rate'] ?? null), 0.20),
        ];
    }, $storage)));

    foreach ($warehouseCommissions as $key => $row) {
        if (!is_array($row)) {
            unset($warehouseCommissions[$key]);
            continue;
        }
        $fallbackRow = $fallback['warehouseCommissions'][$key] ?? ['label' => (string)$key, 'rate' => 0.0];
        $hasPayrollCategory = array_key_exists('payrollCategory', $row) || array_key_exists('payroll_category', $row);
        $legacyRate = $row['rate'] ?? ($row['warehouse_admin_rate'] ?? ($fallbackRow['rate'] ?? 0.0));
        $payrollCategory = zakaz_payroll_category(
            $row['payrollCategory'] ?? ($row['payroll_category'] ?? ''),
            $hasPayrollCategory ? '' : 'warehouse_admin'
        );
        $adminPayrollRate = zakaz_payroll_rate(
            $row['adminPayrollRate'] ?? ($row['admin_payroll_rate'] ?? $legacyRate),
            (float)$legacyRate
        );
        $warehouseCommissions[$key] = [
            'label' => zakaz_text($row['label'] ?? $fallbackRow['label'], 120),
            'payrollCategory' => $payrollCategory,
            'payrollRate' => zakaz_payroll_rate($row['payrollRate'] ?? ($row['payroll_rate'] ?? null), 0.0),
            'adminPayrollRate' => $adminPayrollRate,
            'rate' => $payrollCategory === 'warehouse_admin' ? $adminPayrollRate : 0.0,
        ];
    }

    return [
        'base' => $base,
        'extras' => $extras ?: $fallback['extras'],
        'storage' => $storage ?: $fallback['storage'],
        'warehouseCommissions' => $warehouseCommissions ?: $fallback['warehouseCommissions'],
    ];
}

function zakaz_get_prices(mysqli $conn): array
{
    $raw = zakaz_get_setting($conn, 'prices');
    if ($raw === null || $raw === '') {
        return zakaz_default_prices();
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return zakaz_default_prices();
    }
    return zakaz_normalize_prices($decoded);
}

function zakaz_create_storage_order_from_payload(mysqli $conn, array $payload, string $documentNumber, ?int $userId): ?array
{
    if (empty($payload['storage_enabled'])) {
        return null;
    }

    $clientName = zakaz_text($payload['client_name'] ?? '', 255);
    if ($clientName === '') {
        return null;
    }

    $phone = zakaz_text($payload['phone'] ?? '', 30);
    $plate = zakaz_text($payload['plate_number'] ?? '', 50);
    $carBrand = zakaz_text($payload['car_brand'] ?? '', 120);
    $carModel = zakaz_text($payload['car_model'] ?? '', 120);
    $radius = zakaz_text($payload['radius'] ?? '', 20);
    $storage = isset($payload['storage']) && is_array($payload['storage']) ? $payload['storage'] : [];
    $storageType = zakaz_text($payload['storage_type'] ?? '', 30);
    $storageTypeLabel = $storageType === 'wheels' ? 'Колеса в сборе' : 'Шины';
    $tireBrand = zakaz_text($storage['tire_brand'] ?? '', 160);
    $tireSize = zakaz_text($storage['tire_size'] ?? '', 80);
    $complectation = zakaz_text($storage['complectation'] ?? '', 160);
    $total = zakaz_money($payload['total'] ?? 0);

    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime('+6 months'));
    $status = 'На хранении';
    $location = '';
    $photos = '';

    $notesParts = [
        'Создано автоматически из заказ-наряда' . ($documentNumber !== '' ? " №{$documentNumber}" : ''),
        'Тип хранения: ' . $storageTypeLabel,
    ];
    if ($tireBrand !== '') {
        $notesParts[] = 'Марка резины: ' . $tireBrand;
    }
    if ($tireSize !== '' || $radius !== '') {
        $notesParts[] = 'Размер: ' . ($tireSize !== '' ? $tireSize : $radius);
    }
    if ($complectation !== '') {
        $notesParts[] = 'Комплектность: ' . $complectation;
    }
    $car = trim($carBrand . ' ' . $carModel);
    if ($car !== '') {
        $notesParts[] = 'Авто: ' . $car;
    }
    if ($plate !== '') {
        $notesParts[] = 'Гос. номер: ' . $plate;
    }
    $notesParts[] = 'Сумма заказ-наряда: ' . number_format($total, 2, '.', '') . ' руб.';
    $notes = implode("\n", $notesParts);

    $stmt = $conn->prepare(
        'INSERT INTO storage_orders
         (client_name, phone, notes, status, storage_start_date, storage_end_date, storage_location, photos, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    if (!$stmt) {
        throw new RuntimeException('Ошибка подготовки создания записи хранения.');
    }
    $stmt->bind_param('ssssssss', $clientName, $phone, $notes, $status, $startDate, $endDate, $location, $photos);
    if (!$stmt->execute()) {
        throw new RuntimeException('Не удалось создать запись хранения.');
    }
    $storageOrderId = (int)$conn->insert_id;
    $stmt->close();

    $inventoryNumber = sprintf('ST-%04d', $storageOrderId);
    $stmt = $conn->prepare('UPDATE storage_orders SET inventory_number = ? WHERE id = ?');
    if (!$stmt) {
        throw new RuntimeException('Ошибка подготовки номера хранения.');
    }
    $stmt->bind_param('si', $inventoryNumber, $storageOrderId);
    if (!$stmt->execute()) {
        throw new RuntimeException('Не удалось присвоить номер хранения.');
    }
    $stmt->close();

    $details = 'Автоматически создано из заказ-наряда' . ($documentNumber !== '' ? " №{$documentNumber}" : '');
    $action = 'Создание';
    $historyUserId = $userId ?? 0;
    $stmt = $conn->prepare('INSERT INTO storage_history (storage_order_id, user_id, action, details, created_at) VALUES (?, ?, ?, ?, NOW())');
    if ($stmt) {
        $stmt->bind_param('iiss', $storageOrderId, $historyUserId, $action, $details);
        $stmt->execute();
        $stmt->close();
    }

    if (function_exists('sendTelegramNotification')) {
        $message = "<b>Новый заказ хранения #{$inventoryNumber}</b>\n" .
            "Клиент: {$clientName}\n" .
            'Телефон: ' . ($phone !== '' ? $phone : 'не указан') . "\n" .
            "Источник: заказ-наряд" . ($documentNumber !== '' ? " №{$documentNumber}" : '') . "\n" .
            "Статус: {$status}\n" .
            'Создан: ' . date('d.m.Y H:i');
        sendTelegramNotification($message, []);
    }

    return [
        'id' => $storageOrderId,
        'inventory_number' => $inventoryNumber,
    ];
}

function zakaz_decode_json_field($value, $fallback)
{
    $decoded = json_decode((string)($value ?? ''), true);
    return is_array($decoded) ? $decoded : $fallback;
}

function zakaz_normalize_operation_payload(array $payload): array
{
    $items = $payload['items'] ?? [];
    if (!is_array($items) || count($items) === 0) {
        zakaz_json_response(['ok' => false, 'error' => 'Нет товаров или услуг для сохранения.'], 400);
    }

    global $conn;
    $prices = isset($conn) && $conn instanceof mysqli ? zakaz_get_prices($conn) : zakaz_default_prices();
    $normalizedOperation = zakaz_normalize_operation_items($items, $prices);
    $items = $normalizedOperation['items'];
    if (count($items) === 0) {
        zakaz_json_response(['ok' => false, 'error' => 'Нет корректных товаров или услуг для сохранения.'], 400);
    }

    $clientName = zakaz_text($payload['client_name'] ?? '', 255);
    if ($clientName === '') {
        zakaz_json_response(['ok' => false, 'error' => 'Не указано ФИО клиента.'], 400);
    }

    $total = zakaz_money($normalizedOperation['total'] ?? 0);
    $paidAmount = min($total, zakaz_money($payload['paid_amount'] ?? $total));

    return [
        'document_number' => zakaz_text($payload['document_number'] ?? '', 50),
        'client_name' => $clientName,
        'phone' => zakaz_text($payload['phone'] ?? '', 30),
        'car_brand' => zakaz_text($payload['car_brand'] ?? '', 120),
        'car_model' => zakaz_text($payload['car_model'] ?? '', 120),
        'plate_number' => zakaz_text($payload['plate_number'] ?? '', 50),
        'car_type' => zakaz_text($payload['car_type'] ?? '', 80),
        'radius' => zakaz_text($payload['radius'] ?? '', 20),
        'qty_mode' => zakaz_text($payload['qty_mode'] ?? '', 30),
        'wheel_count' => max(0, (int)($payload['wheel_count'] ?? 0)),
        'items' => $items,
        'subtotal' => zakaz_money($normalizedOperation['subtotal'] ?? 0),
        'discount' => zakaz_money($normalizedOperation['discount'] ?? 0),
        'total' => $total,
        'paid_amount' => $paidAmount,
        'debt_amount' => max(0.0, round($total - $paidAmount, 2)),
        'storage_enabled' => !empty($payload['storage_enabled']) ? 1 : 0,
        'storage_type' => zakaz_text($payload['storage_type'] ?? '', 30),
        'storage' => isset($payload['storage']) && is_array($payload['storage']) ? $payload['storage'] : null,
        'note' => zakaz_text($payload['note'] ?? '', 3000),
    ];
}

function zakaz_format_diff_value($value): string
{
    if (is_bool($value)) {
        return $value ? 'да' : 'нет';
    }
    if (is_array($value)) {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? '[данные]' : $encoded;
    }
    if ($value === null || $value === '') {
        return 'пусто';
    }
    return (string)$value;
}

function zakaz_build_operation_changes(array $old, array $new): array
{
    $fields = [
        'document_number' => 'номер заказ-наряда',
        'client_name' => 'клиент',
        'phone' => 'телефон',
        'car_brand' => 'марка авто',
        'car_model' => 'модель авто',
        'plate_number' => 'гос. номер',
        'car_type' => 'тип авто',
        'radius' => 'радиус',
        'qty_mode' => 'режим',
        'wheel_count' => 'количество колес',
        'subtotal' => 'сумма до скидки',
        'discount' => 'скидка',
        'total' => 'итого',
        'paid_amount' => 'оплачено',
        'debt_amount' => 'долг',
        'storage_enabled' => 'хранение',
        'storage_type' => 'тип хранения',
        'note' => 'примечание',
    ];

    $changes = [];
    foreach ($fields as $field => $label) {
        $oldValue = $old[$field] ?? null;
        $newValue = $new[$field] ?? null;
        if (is_numeric($oldValue) || is_numeric($newValue)) {
            $oldValue = round((float)$oldValue, 2);
            $newValue = round((float)$newValue, 2);
        }
        if ((string)$oldValue !== (string)$newValue) {
            $changes[] = [
                'field' => $field,
                'label' => $label,
                'from' => zakaz_format_diff_value($oldValue),
                'to' => zakaz_format_diff_value($newValue),
            ];
        }
    }

    $oldItems = zakaz_decode_json_field($old['items_json'] ?? '', []);
    if (json_encode($oldItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !== json_encode($new['items'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) {
        $changes[] = [
            'field' => 'items',
            'label' => 'позиции',
            'from' => count($oldItems) . ' поз.',
            'to' => count($new['items']) . ' поз.',
        ];
    }

    $oldStorage = zakaz_decode_json_field($old['storage_json'] ?? '', null);
    if (json_encode($oldStorage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !== json_encode($new['storage'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) {
        $changes[] = [
            'field' => 'storage',
            'label' => 'данные хранения',
            'from' => $oldStorage ? 'заполнено' : 'пусто',
            'to' => $new['storage'] ? 'заполнено' : 'пусто',
        ];
    }

    return $changes;
}

function zakaz_shift_names($value): array
{
    $source = is_array($value) ? $value : [];
    $names = [];
    foreach ($source as $name) {
        $text = zakaz_text($name, 120);
        if ($text !== '') {
            $names[] = $text;
        }
    }
    return array_values(array_unique($names));
}

function zakaz_shift_row(array $row): array
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

function zakaz_auto_close_old_shifts(mysqli $conn): void
{
    $result = $conn->query("SELECT id, shift_date FROM zakaz_shifts WHERE status = 'open' AND shift_date < CURDATE()");
    if (!$result) {
        return;
    }
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

function zakaz_fetch_shifts(mysqli $conn, int $limit = 100): array
{
    zakaz_auto_close_old_shifts($conn);
    $limit = min(300, max(1, $limit));
    $stmt = $conn->prepare('SELECT * FROM zakaz_shifts ORDER BY opened_at DESC, id DESC LIMIT ?');
    if (!$stmt) {
        throw new RuntimeException('Ошибка подготовки архива смен.');
    }
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $shifts = [];
    while ($row = $result->fetch_assoc()) {
        $shifts[] = zakaz_shift_row($row);
    }
    $stmt->close();
    return $shifts;
}

function zakaz_has_open_shift(mysqli $conn): bool
{
    zakaz_auto_close_old_shifts($conn);
    $result = $conn->query("SELECT id FROM zakaz_shifts WHERE status = 'open' ORDER BY opened_at DESC LIMIT 1");
    if (!$result) {
        throw new RuntimeException('Ошибка проверки открытой смены.');
    }
    $hasOpenShift = (bool)$result->fetch_assoc();
    $result->free();
    return $hasOpenShift;
}

try {
    $action = (string)($_GET['action'] ?? 'list');

    if ($action === 'state' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        global $conn;

        $counterRaw = zakaz_get_setting($conn, 'doc_counter');
        $counter = is_numeric($counterRaw) ? max(0, (int)$counterRaw) : 0;
        $pricesRaw = zakaz_get_setting($conn, 'prices');
        $prices = zakaz_get_prices($conn);
        if ($pricesRaw === null || $pricesRaw === '') {
            $encodedDefaultPrices = json_encode($prices, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encodedDefaultPrices !== false) {
                zakaz_save_setting($conn, 'prices', $encodedDefaultPrices);
            }
        }
        zakaz_json_response([
            'ok' => true,
            'prices' => $prices,
            'next_document_number' => zakaz_format_document_number($counter + 1),
            'user' => [
                'id' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
                'username' => zakaz_text($_SESSION['username'] ?? 'guest', 50),
                'role' => zakaz_text($_SESSION['user_role'] ?? 'user', 20),
                'is_admin' => ($_SESSION['user_role'] ?? '') === 'admin',
            ],
        ]);
    }

    if (($action === 'shift_state' || $action === 'shiftstate') && $_SERVER['REQUEST_METHOD'] === 'GET') {
        global $conn;

        $shifts = zakaz_fetch_shifts($conn, 100);
        $openShift = null;
        foreach ($shifts as $shift) {
            if ($shift['status'] === 'open') {
                $openShift = $shift;
                break;
            }
        }
        zakaz_json_response([
            'ok' => true,
            'open_shift' => $openShift,
            'shifts' => $shifts,
            'user' => [
                'id' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
                'username' => zakaz_text($_SESSION['username'] ?? 'guest', 50),
                'role' => zakaz_text($_SESSION['user_role'] ?? 'user', 20),
                'is_admin' => ($_SESSION['user_role'] ?? '') === 'admin',
            ],
        ]);
    }

    if (($action === 'open_shift' || $action === 'openshift') && $_SERVER['REQUEST_METHOD'] === 'POST') {
        global $conn;
        zakaz_require_admin();
        zakaz_auto_close_old_shifts($conn);

        $payload = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            zakaz_json_response(['ok' => false, 'error' => 'Некорректный JSON.'], 400);
        }
        $tireWorkers = zakaz_shift_names($payload['tire_workers'] ?? []);
        $argonWorkers = zakaz_shift_names($payload['argon_workers'] ?? []);
        $adminName = zakaz_text($payload['admin_name'] ?? '', 255);
        if (!$tireWorkers && !$argonWorkers && $adminName === '') {
            zakaz_json_response(['ok' => false, 'error' => 'Укажите, кто вышел на смену.'], 400);
        }

        // Serialize shift opening so two simultaneous requests cannot both pass the
        // "is a shift already open?" check and create two open shifts at once.
        $shiftLock = 'zakaz_open_shift';
        $lockStmt = $conn->prepare('SELECT GET_LOCK(?, 10)');
        if ($lockStmt) {
            $lockStmt->bind_param('s', $shiftLock);
            $lockStmt->execute();
            $lockStmt->get_result();
            $lockStmt->close();
        } else {
            $shiftLock = null;
        }

        $releaseShiftLock = static function () use ($conn, &$shiftLock): void {
            if ($shiftLock === null) {
                return;
            }
            if ($rel = $conn->prepare('SELECT RELEASE_LOCK(?)')) {
                $rel->bind_param('s', $shiftLock);
                $rel->execute();
                $rel->get_result();
                $rel->close();
            }
            $shiftLock = null;
        };

        $stmt = $conn->prepare("SELECT * FROM zakaz_shifts WHERE status = 'open' ORDER BY opened_at DESC LIMIT 1");
        if (!$stmt) {
            $releaseShiftLock();
            throw new RuntimeException('Ошибка проверки открытой смены.');
        }
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($existing) {
            $releaseShiftLock();
            zakaz_json_response(['ok' => true, 'shift' => zakaz_shift_row($existing), 'already_open' => true]);
        }

        $tireJson = json_encode($tireWorkers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $argonJson = json_encode($argonWorkers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($tireJson === false || $argonJson === false) {
            zakaz_json_response(['ok' => false, 'error' => 'Не удалось подготовить данные смены.'], 400);
        }
        $shiftDate = date('Y-m-d');
        $openedAt = date('Y-m-d H:i:s');
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $username = zakaz_text($_SESSION['username'] ?? 'admin', 50);
        $stmt = $conn->prepare('INSERT INTO zakaz_shifts (shift_date, opened_at, tire_workers_json, argon_workers_json, admin_name, opened_by, opened_by_username) VALUES (?, ?, ?, ?, ?, ?, ?)');
        if (!$stmt) {
            throw new RuntimeException('Ошибка подготовки открытия смены.');
        }
        $stmt->bind_param('sssssis', $shiftDate, $openedAt, $tireJson, $argonJson, $adminName, $userId, $username);
        if (!$stmt->execute()) {
            $releaseShiftLock();
            throw new RuntimeException('Не удалось открыть смену.');
        }
        $shiftId = (int)$conn->insert_id;
        $stmt->close();
        $releaseShiftLock();

        $stmt = $conn->prepare('SELECT * FROM zakaz_shifts WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $shiftId);
        $stmt->execute();
        $shift = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        log_change("Открыл смену шиномонтажа #{$shiftId}");
        zakaz_json_response(['ok' => true, 'shift' => zakaz_shift_row($shift)]);
    }

    if (($action === 'close_shift' || $action === 'closeshift') && $_SERVER['REQUEST_METHOD'] === 'POST') {
        global $conn;
        zakaz_require_admin();

        $payload = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            zakaz_json_response(['ok' => false, 'error' => 'Некорректный JSON.'], 400);
        }
        $shiftId = max(0, (int)($payload['id'] ?? 0));
        if ($shiftId <= 0) {
            zakaz_json_response(['ok' => false, 'error' => 'Смена не найдена.'], 400);
        }
        $snapshot = isset($payload['snapshot']) && is_array($payload['snapshot']) ? $payload['snapshot'] : null;
        $snapshotJson = $snapshot ? json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        if ($snapshot && $snapshotJson === false) {
            zakaz_json_response(['ok' => false, 'error' => 'Не удалось подготовить итог смены.'], 400);
        }
        $closedAt = date('Y-m-d H:i:s');
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $username = zakaz_text($_SESSION['username'] ?? 'admin', 50);
        $stmt = $conn->prepare("UPDATE zakaz_shifts SET status = 'closed', closed_at = ?, closed_by = ?, closed_by_username = ?, snapshot_json = ? WHERE id = ? AND status = 'open'");
        if (!$stmt) {
            throw new RuntimeException('Ошибка подготовки закрытия смены.');
        }
        $stmt->bind_param('sissi', $closedAt, $userId, $username, $snapshotJson, $shiftId);
        if (!$stmt->execute()) {
            throw new RuntimeException('Не удалось закрыть смену.');
        }
        $affected = $stmt->affected_rows;
        $stmt->close();
        if ($affected <= 0) {
            zakaz_json_response(['ok' => false, 'error' => 'Смена не найдена или уже закрыта.'], 404);
        }
        log_change("Закрыл смену шиномонтажа #{$shiftId}");
        zakaz_json_response(['ok' => true, 'shifts' => zakaz_fetch_shifts($conn, 100)]);
    }

    if ($action === 'save_prices' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        global $conn;
        zakaz_require_admin();

        $payload = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            zakaz_json_response(['ok' => false, 'error' => 'Некорректный JSON прайса.'], 400);
        }
        $prices = zakaz_normalize_prices($payload['prices'] ?? $payload);
        $encoded = json_encode($prices, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            zakaz_json_response(['ok' => false, 'error' => 'Не удалось подготовить прайс к сохранению.'], 400);
        }
        zakaz_save_setting($conn, 'prices', $encoded);
        log_change('Обновил прайс заказ-наряда');
        zakaz_json_response([
            'ok' => true,
            'prices' => $prices,
            'updated_by_username' => zakaz_text($_SESSION['username'] ?? 'guest', 50),
        ]);
    }

    if ($action === 'reset_prices' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        global $conn;
        zakaz_require_admin();

        $prices = zakaz_default_prices();
        $encoded = json_encode($prices, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            zakaz_json_response(['ok' => false, 'error' => 'Не удалось подготовить прайс к сбросу.'], 400);
        }
        zakaz_save_setting($conn, 'prices', $encoded);
        log_change('Сбросил прайс заказ-наряда к заводским значениям');
        zakaz_json_response([
            'ok' => true,
            'prices' => $prices,
            'updated_by_username' => zakaz_text($_SESSION['username'] ?? 'guest', 50),
        ]);
    }

    if ($action === 'issue_document_number' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        global $conn;

        $initKey = 'doc_counter';
        $initValue = '0';
        $initStmt = $conn->prepare('INSERT IGNORE INTO zakaz_settings (setting_key, setting_value) VALUES (?, ?)');
        if (!$initStmt) {
            throw new RuntimeException('Ошибка подготовки счетчика документов.');
        }
        $initStmt->bind_param('ss', $initKey, $initValue);
        if (!$initStmt->execute()) {
            throw new RuntimeException('Не удалось инициализировать счетчик документов.');
        }
        $initStmt->close();

        $conn->begin_transaction();
        try {
            $key = 'doc_counter';
            $stmt = $conn->prepare('SELECT setting_value FROM zakaz_settings WHERE setting_key = ? FOR UPDATE');
            if (!$stmt) {
                throw new RuntimeException('Ошибка подготовки выдачи номера.');
            }
            $stmt->bind_param('s', $key);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            $current = $row && is_numeric($row['setting_value']) ? max(0, (int)$row['setting_value']) : 0;
            $next = $current + 1;
            zakaz_save_setting($conn, $key, (string)$next);
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }

        zakaz_json_response([
            'ok' => true,
            'document_number' => zakaz_format_document_number($next),
            'counter' => $next,
        ]);
    }

    if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        global $conn;

        $limit = (int)($_GET['limit'] ?? 50);
        $limit = min(5000, max(1, $limit));

        $sql = "SELECT zo.id, zo.document_number, zo.client_name, zo.phone, zo.car_brand, zo.car_model, zo.plate_number,
                       zo.car_type, zo.radius, zo.qty_mode, zo.wheel_count, zo.items_json, zo.subtotal, zo.discount, zo.total,
                       zo.paid_amount, zo.debt_amount,
                       zo.note, zo.updated_by_username, zo.updated_at, zo.edit_history,
                       zo.storage_enabled, zo.storage_type, zo.storage_json, zo.storage_order_id,
                       so.inventory_number AS storage_inventory_number,
                       zo.created_by_username, zo.created_at
                FROM zakaz_operations zo
                LEFT JOIN storage_orders so ON so.id = zo.storage_order_id
                WHERE zo.deleted_at IS NULL
                ORDER BY zo.created_at DESC, zo.id DESC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Ошибка подготовки запроса архива.');
        }
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $operations = [];
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['subtotal'] = (float)$row['subtotal'];
            $row['discount'] = (float)$row['discount'];
            $row['total'] = (float)$row['total'];
            $row['paid_amount'] = (float)($row['paid_amount'] ?? 0);
            $row['debt_amount'] = (float)($row['debt_amount'] ?? max(0, $row['total'] - $row['paid_amount']));
            $row['note'] = (string)($row['note'] ?? '');
            $row['updated_by_username'] = (string)($row['updated_by_username'] ?? '');
            $row['storage_enabled'] = (int)$row['storage_enabled'] === 1;
            $row['storage_order_id'] = isset($row['storage_order_id']) ? (int)$row['storage_order_id'] : null;
            $items = json_decode((string)$row['items_json'], true);
            $storage = json_decode((string)($row['storage_json'] ?? ''), true);
            $history = json_decode((string)($row['edit_history'] ?? ''), true);
            $row['items'] = is_array($items) ? $items : [];
            $row['storage'] = is_array($storage) ? $storage : null;
            $row['edit_history'] = is_array($history) ? $history : [];
            unset($row['items_json'], $row['storage_json']);
            $operations[] = $row;
        }
        $stmt->close();

        zakaz_json_response(['ok' => true, 'operations' => $operations]);
    }

    if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        global $conn;

        if (!zakaz_has_open_shift($conn)) {
            zakaz_json_response(['ok' => false, 'error' => 'Нельзя осуществить продажу: сначала откройте смену.'], 409);
        }

        $payload = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            zakaz_json_response(['ok' => false, 'error' => 'Некорректный JSON.'], 400);
        }

        $normalized = zakaz_normalize_operation_payload($payload);
        $itemsJson = json_encode($normalized['items'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $storageJson = json_encode($normalized['storage'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($itemsJson === false || $storageJson === false) {
            zakaz_json_response(['ok' => false, 'error' => 'Не удалось подготовить данные операции.'], 400);
        }

        $payload = array_merge($payload, $normalized);
        $createdBy = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $createdByUsername = zakaz_text($_SESSION['username'] ?? 'guest', 50);

        // Serialize concurrent creates that share the same document number so two
        // simultaneous requests cannot both pass the duplicate check and insert
        // twin rows (there is no unique index on document_number).
        $lockName = null;
        if ($normalized['document_number'] !== '') {
            $lockName = 'zakaz_docnum_' . substr(sha1($normalized['document_number']), 0, 32);
            $lockStmt = $conn->prepare('SELECT GET_LOCK(?, 10)');
            if ($lockStmt) {
                $lockStmt->bind_param('s', $lockName);
                $lockStmt->execute();
                $lockStmt->get_result();
                $lockStmt->close();
            } else {
                $lockName = null;
            }
        }

        $conn->begin_transaction();
        try {
            if ($normalized['document_number'] !== '') {
                $stmt = $conn->prepare('SELECT id, created_by_username FROM zakaz_operations WHERE document_number = ? AND deleted_at IS NULL LIMIT 1');
                if (!$stmt) {
                    throw new RuntimeException('Ошибка проверки номера заказ-наряда.');
                }
                $stmt->bind_param('s', $normalized['document_number']);
                $stmt->execute();
                $existing = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($existing) {
                    $conn->commit();
                    zakaz_json_response([
                        'ok' => true,
                        'duplicate' => true,
                        'operation' => [
                            'id' => (int)$existing['id'],
                            'created_by_username' => zakaz_text($existing['created_by_username'] ?? '', 50),
                            'storage_order' => null,
                        ],
                    ]);
                }
            }

            $storageOrder = zakaz_create_storage_order_from_payload($conn, $payload, $normalized['document_number'], $createdBy);
            $storageOrderId = $storageOrder ? (int)$storageOrder['id'] : null;

            $sql = "INSERT INTO zakaz_operations
                    (document_number, client_name, phone, car_brand, car_model, plate_number,
                     car_type, radius, qty_mode, wheel_count, items_json, subtotal, discount, total,
                     paid_amount, debt_amount, note, storage_enabled, storage_type, storage_json, storage_order_id, created_by, created_by_username, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException('Ошибка подготовки сохранения операции.');
            }
            $documentNumber = $normalized['document_number'];
            $clientName = $normalized['client_name'];
            $phone = $normalized['phone'];
            $carBrand = $normalized['car_brand'];
            $carModel = $normalized['car_model'];
            $plateNumber = $normalized['plate_number'];
            $carType = $normalized['car_type'];
            $radius = $normalized['radius'];
            $qtyMode = $normalized['qty_mode'];
            $wheelCount = $normalized['wheel_count'];
            $subtotal = $normalized['subtotal'];
            $discount = $normalized['discount'];
            $total = $normalized['total'];
            $paidAmount = $normalized['paid_amount'];
            $debtAmount = $normalized['debt_amount'];
            $note = $normalized['note'];
            $storageEnabled = $normalized['storage_enabled'];
            $storageType = $normalized['storage_type'];
            $createdAt = date('Y-m-d H:i:s');
            $stmt->bind_param(
                'sssssssssisdddddsissiiss',
                $documentNumber,
                $clientName,
                $phone,
                $carBrand,
                $carModel,
                $plateNumber,
                $carType,
                $radius,
                $qtyMode,
                $wheelCount,
                $itemsJson,
                $subtotal,
                $discount,
                $total,
                $paidAmount,
                $debtAmount,
                $note,
                $storageEnabled,
                $storageType,
                $storageJson,
                $storageOrderId,
                $createdBy,
                $createdByUsername,
                $createdAt
            );
            if (!$stmt->execute()) {
                throw new RuntimeException('Не удалось сохранить операцию.');
            }
            $operationId = (int)$conn->insert_id;
            $stmt->close();
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            if ($lockName !== null) {
                if ($rel = $conn->prepare('SELECT RELEASE_LOCK(?)')) {
                    $rel->bind_param('s', $lockName);
                    $rel->execute();
                    $rel->get_result();
                    $rel->close();
                }
            }
            throw $e;
        }

        if ($lockName !== null) {
            if ($rel = $conn->prepare('SELECT RELEASE_LOCK(?)')) {
                $rel->bind_param('s', $lockName);
                $rel->execute();
                $rel->get_result();
                $rel->close();
            }
        }

        log_change("Сохранил операцию заказ-наряда #{$operationId}" . ($normalized['document_number'] !== '' ? " (документ {$normalized['document_number']})" : ''));

        zakaz_json_response([
            'ok' => true,
            'operation' => [
                'id' => $operationId,
                'created_by_username' => $createdByUsername,
                'storage_order' => $storageOrder,
            ],
        ]);
    }

    if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        global $conn;
        zakaz_require_admin();

        $payload = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            zakaz_json_response(['ok' => false, 'error' => 'Некорректный JSON.'], 400);
        }

        $operationId = max(0, (int)($payload['id'] ?? 0));
        if ($operationId <= 0) {
            zakaz_json_response(['ok' => false, 'error' => 'Операция не найдена.'], 400);
        }

        $normalized = zakaz_normalize_operation_payload($payload);
        $itemsJson = json_encode($normalized['items'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $storageJson = json_encode($normalized['storage'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($itemsJson === false || $storageJson === false) {
            zakaz_json_response(['ok' => false, 'error' => 'Не удалось подготовить данные операции.'], 400);
        }

        $stmt = $conn->prepare('SELECT * FROM zakaz_operations WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('Ошибка подготовки чтения операции.');
        }
        $stmt->bind_param('i', $operationId);
        $stmt->execute();
        $old = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$old) {
            zakaz_json_response(['ok' => false, 'error' => 'Операция не найдена.'], 404);
        }

        // If the client did not explicitly send paid_amount, keep what was already
        // paid instead of letting the normalizer default it to the full total and
        // silently wipe an outstanding debt. Re-clamp against the (possibly new) total.
        if (!array_key_exists('paid_amount', $payload)) {
            $existingPaid = zakaz_money($old['paid_amount'] ?? 0);
            $normalized['paid_amount'] = min($normalized['total'], $existingPaid);
            $normalized['debt_amount'] = max(0.0, round($normalized['total'] - $normalized['paid_amount'], 2));
        }

        if ($normalized['document_number'] !== '' && $normalized['document_number'] !== (string)($old['document_number'] ?? '')) {
            $stmt = $conn->prepare('SELECT id FROM zakaz_operations WHERE document_number = ? AND id <> ? AND deleted_at IS NULL LIMIT 1');
            if (!$stmt) {
                throw new RuntimeException('Ошибка проверки номера заказ-наряда.');
            }
            $stmt->bind_param('si', $normalized['document_number'], $operationId);
            $stmt->execute();
            $duplicate = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($duplicate) {
                zakaz_json_response(['ok' => false, 'error' => 'Такой номер заказ-наряда уже есть в архиве.'], 409);
            }
        }

        $changes = zakaz_build_operation_changes($old, $normalized);
        if (!$changes) {
            zakaz_json_response(['ok' => true, 'operation' => ['id' => $operationId], 'changes' => []]);
        }

        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $username = zakaz_text($_SESSION['username'] ?? 'admin', 50);
        $history = json_decode((string)($old['edit_history'] ?? ''), true);
        $history = is_array($history) ? $history : [];
        $history[] = [
            'at' => date('Y-m-d H:i:s'),
            'by_user_id' => $userId,
            'by_username' => $username,
            'changes' => $changes,
        ];
        $historyJson = json_encode($history, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($historyJson === false) {
            zakaz_json_response(['ok' => false, 'error' => 'Не удалось подготовить историю правок.'], 500);
        }

        $sql = "UPDATE zakaz_operations
                SET document_number = ?, client_name = ?, phone = ?, car_brand = ?, car_model = ?, plate_number = ?,
                    car_type = ?, radius = ?, qty_mode = ?, wheel_count = ?, items_json = ?, subtotal = ?, discount = ?,
                    total = ?, paid_amount = ?, debt_amount = ?, note = ?, storage_enabled = ?, storage_type = ?,
                    storage_json = ?, updated_by = ?, updated_by_username = ?, edit_history = ?, updated_at = NOW()
                WHERE id = ? AND deleted_at IS NULL";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Ошибка подготовки обновления операции.');
        }
        $documentNumber = $normalized['document_number'];
        $clientName = $normalized['client_name'];
        $phone = $normalized['phone'];
        $carBrand = $normalized['car_brand'];
        $carModel = $normalized['car_model'];
        $plateNumber = $normalized['plate_number'];
        $carType = $normalized['car_type'];
        $radius = $normalized['radius'];
        $qtyMode = $normalized['qty_mode'];
        $wheelCount = $normalized['wheel_count'];
        $subtotal = $normalized['subtotal'];
        $discount = $normalized['discount'];
        $total = $normalized['total'];
        $paidAmount = $normalized['paid_amount'];
        $debtAmount = $normalized['debt_amount'];
        $note = $normalized['note'];
        $storageEnabled = $normalized['storage_enabled'];
        $storageType = $normalized['storage_type'];
        $stmt->bind_param(
            'sssssssssisdddddsississi',
            $documentNumber,
            $clientName,
            $phone,
            $carBrand,
            $carModel,
            $plateNumber,
            $carType,
            $radius,
            $qtyMode,
            $wheelCount,
            $itemsJson,
            $subtotal,
            $discount,
            $total,
            $paidAmount,
            $debtAmount,
            $note,
            $storageEnabled,
            $storageType,
            $storageJson,
            $userId,
            $username,
            $historyJson,
            $operationId
        );
        if (!$stmt->execute()) {
            throw new RuntimeException('Не удалось обновить операцию.');
        }
        $stmt->close();

        $changeText = implode('; ', array_map(static fn($change): string => $change['label'] . ': ' . $change['from'] . ' -> ' . $change['to'], $changes));
        log_change("Исправил заказ-наряд #{$operationId}: {$changeText}");
        zakaz_json_response([
            'ok' => true,
            'operation' => [
                'id' => $operationId,
                'updated_by_username' => $username,
                'updated_at' => date('Y-m-d H:i:s'),
                'edit_history' => $history,
            ],
            'changes' => $changes,
        ]);
    }

    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        global $conn;
        zakaz_require_admin();

        $payload = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            zakaz_json_response(['ok' => false, 'error' => 'Некорректный JSON.'], 400);
        }

        $operationId = max(0, (int)($payload['id'] ?? 0));
        if ($operationId <= 0) {
            zakaz_json_response(['ok' => false, 'error' => 'Операция не найдена.'], 400);
        }

        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $username = zakaz_text($_SESSION['username'] ?? 'admin', 50);
        $stmt = $conn->prepare('UPDATE zakaz_operations SET deleted_at = NOW(), deleted_by = ?, deleted_by_username = ? WHERE id = ? AND deleted_at IS NULL');
        if (!$stmt) {
            throw new RuntimeException('Ошибка подготовки удаления операции.');
        }
        $stmt->bind_param('isi', $userId, $username, $operationId);
        if (!$stmt->execute()) {
            throw new RuntimeException('Не удалось удалить операцию.');
        }
        $affected = $stmt->affected_rows;
        $stmt->close();
        if ($affected <= 0) {
            zakaz_json_response(['ok' => false, 'error' => 'Операция не найдена или уже удалена.'], 404);
        }

        log_change("Удалил операцию заказ-наряда #{$operationId}");
        zakaz_json_response(['ok' => true, 'deleted_id' => $operationId, 'deleted_by_username' => $username]);
    }

    if ($action === 'pay_debt' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        global $conn;

        $payload = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            zakaz_json_response(['ok' => false, 'error' => 'Некорректный JSON.'], 400);
        }

        $operationId = max(0, (int)($payload['id'] ?? 0));
        $amount = zakaz_money($payload['amount'] ?? 0);
        if ($operationId <= 0 || $amount <= 0) {
            zakaz_json_response(['ok' => false, 'error' => 'Укажите сумму погашения долга.'], 400);
        }

        // Lock the row for the whole read-modify-write so two concurrent debt
        // payments cannot read the same paid_amount and overwrite each other.
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('SELECT id, document_number, total, paid_amount, debt_amount FROM zakaz_operations WHERE id = ? AND deleted_at IS NULL LIMIT 1 FOR UPDATE');
            if (!$stmt) {
                throw new RuntimeException('Ошибка подготовки чтения долга.');
            }
            $stmt->bind_param('i', $operationId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row) {
                $conn->rollback();
                zakaz_json_response(['ok' => false, 'error' => 'Операция не найдена.'], 404);
            }

            $total = zakaz_money($row['total'] ?? 0);
            $paid = zakaz_money($row['paid_amount'] ?? 0);
            $currentDebt = zakaz_money($row['debt_amount'] ?? max(0, $total - $paid));
            $newPaid = min($total, round($paid + min($amount, $currentDebt), 2));
            $newDebt = max(0.0, round($total - $newPaid, 2));

            $stmt = $conn->prepare('UPDATE zakaz_operations SET paid_amount = ?, debt_amount = ? WHERE id = ? AND deleted_at IS NULL');
            if (!$stmt) {
                throw new RuntimeException('Ошибка подготовки погашения долга.');
            }
            $stmt->bind_param('ddi', $newPaid, $newDebt, $operationId);
            if (!$stmt->execute()) {
                throw new RuntimeException('Не удалось погасить долг.');
            }
            $stmt->close();

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }

        log_change("Погасил долг по заказ-наряду #{$operationId} на сумму " . number_format($amount, 2, '.', '') . ' руб.');
        zakaz_json_response([
            'ok' => true,
            'operation' => [
                'id' => $operationId,
                'paid_amount' => $newPaid,
                'debt_amount' => $newDebt,
            ],
        ]);
    }

    if ($action === 'save_note' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        global $conn;

        $payload = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            zakaz_json_response(['ok' => false, 'error' => 'Некорректный JSON.'], 400);
        }

        $operationId = max(0, (int)($payload['id'] ?? 0));
        $note = zakaz_text($payload['note'] ?? '', 3000);
        if ($operationId <= 0) {
            zakaz_json_response(['ok' => false, 'error' => 'Операция не найдена.'], 400);
        }

        $stmt = $conn->prepare('UPDATE zakaz_operations SET note = ? WHERE id = ? AND deleted_at IS NULL');
        if (!$stmt) {
            throw new RuntimeException('Ошибка подготовки сохранения примечания.');
        }
        $stmt->bind_param('si', $note, $operationId);
        if (!$stmt->execute()) {
            throw new RuntimeException('Не удалось сохранить примечание.');
        }
        $affected = $stmt->affected_rows;
        $stmt->close();
        if ($affected < 1) {
            // affected_rows can be 0 when the note value is unchanged, so only
            // treat a missing/deleted operation as an error after confirming it.
            $check = $conn->prepare('SELECT id FROM zakaz_operations WHERE id = ? AND deleted_at IS NULL LIMIT 1');
            if ($check) {
                $check->bind_param('i', $operationId);
                $check->execute();
                $exists = (bool)$check->get_result()->fetch_assoc();
                $check->close();
                if (!$exists) {
                    zakaz_json_response(['ok' => false, 'error' => 'Операция не найдена или удалена.'], 404);
                }
            }
        }

        log_change("Обновил примечание к заказ-наряду #{$operationId}");
        zakaz_json_response([
            'ok' => true,
            'operation' => [
                'id' => $operationId,
                'note' => $note,
            ],
        ]);
    }

    zakaz_json_response(['ok' => false, 'error' => 'Метод или action не поддерживается.'], 405);
} catch (Throwable $e) {
    error_log('zakaz_api.php: ' . $e->getMessage());
    zakaz_json_response(['ok' => false, 'error' => $e->getMessage()], 500);
}
