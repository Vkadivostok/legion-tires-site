<?php
ob_start();
require_once 'db.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
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
track_user_activity('equipment');

$section = 'equipment';
$search_query = $_GET['query'] ?? '';
$status_filter = $_GET['status'] ?? '';
$location_filter = $_GET['location'] ?? '';
$open_details = intval($_GET['open_details'] ?? 0);
$equipment_error = '';
$is_archive_view = $status_filter === 'Архив';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_equipment'])) {
        $new_id = createEquipment($_POST, $_FILES);
        if ($new_id) {
            header("Location: equipment.php?open_details=" . intval($new_id));
            exit;
        } else {
            if (isset($_SESSION['temp_error'])) {
                $equipment_error = $_SESSION['temp_error'];
                unset($_SESSION['temp_error']);
            } else {
                $equipment_error = "Ошибка при добавлении оборудования. Проверьте данные.";
            }
        }
    } elseif (isset($_POST['update_equipment'])) {
        if (updateEquipment($_POST, $_FILES)) {
            $open_id = intval($_POST['open_details'] ?? ($_POST['equipment_id'] ?? 0));
            $redirect = "Location: equipment.php" . ($open_id > 0 ? "?open_details=$open_id" : "");
            header($redirect);
            exit;
        } else {
            $equipment_error = "Ошибка при обновлении оборудования.";
        }
    } elseif (isset($_POST['delete_equipment'])) {
        if (deleteEquipment($_POST['equipment_id'])) {
            header("Location: equipment.php");
            exit;
        } else {
            $equipment_error = "Ошибка при удалении оборудования.";
        }
    } elseif (isset($_POST['archive_equipment'])) {
        if (archiveEquipment($_POST['equipment_id'])) {
            header("Location: equipment.php");
            exit;
        } else {
            $equipment_error = "Ошибка при перемещении оборудования в архив.";
        }
    } elseif (isset($_POST['restore_equipment'])) {
        if (restoreEquipment($_POST['equipment_id'])) {
            $open_id = intval($_POST['equipment_id'] ?? 0);
            header("Location: equipment.php" . ($open_id > 0 ? "?open_details=$open_id" : ""));
            exit;
        } else {
            $equipment_error = "Ошибка при возврате оборудования в работу.";
        }
    } elseif (isset($_POST['add_payment'])) {
        if (addEquipmentPayment($_POST)) {
            $open_id = intval($_POST['open_details'] ?? ($_POST['equipment_id'] ?? 0));
            $redirect = "Location: equipment.php" . ($open_id > 0 ? "?open_details=$open_id" : "");
            header($redirect);
            exit;
        } else {
            $equipment_error = "Ошибка при добавлении платежа.";
        }
    }
}

function createEquipment($data, $files) {
    global $conn;
    $name = trim($data['name'] ?? '');
    $serial_number = trim($data['serial_number'] ?? '');
    $notes = trim($data['notes'] ?? '');
    $status = $data['status'] ?? 'В работе';
    $purchase_date = $data['purchase_date'] ?? date('Y-m-d');
    $next_service_date = trim($data['next_service_date'] ?? '');
    $next_service_date = $next_service_date !== '' ? $next_service_date : null;
    $location = trim($data['location'] ?? '');
    $total_cost = floatval($data['total_cost'] ?? 0);

    if (empty($name)) return false;

    $photos = [];
    if (!empty($files['photos']['name'][0])) {
        $upload_dir = 'Uploads/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
        for ($i = 0; $i < min(4, count($files['photos']['name'])); $i++) {
            if ($files['photos']['size'][$i] > 0) {
                $file_name = uniqid() . '_' . basename($files['photos']['name'][$i]);
                if (compressImage($files['photos']['tmp_name'][$i], $upload_dir . $file_name)) {
                    $photos[] = $upload_dir . $file_name;
                }
            }
        }
    }
    $photos_str = implode(',', $photos);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO equipment (name, serial_number, notes, status, purchase_date, next_service_date, location, total_cost, photos, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        if (!$stmt) {
            throw new Exception('Prepare insert failed');
        }
        $stmt->bind_param("sssssssss", $name, $serial_number, $notes, $status, $purchase_date, $next_service_date, $location, $total_cost, $photos_str);
        $result = $stmt->execute();
        $equipment_id = $conn->insert_id;
        $stmt->close();

        if (!$result || !$equipment_id) {
            throw new Exception('Insert failed');
        }

        $inventory_number = sprintf("EQ-%04d", $equipment_id);
        $order_number = sprintf("ORD-%04d", $equipment_id);
        $stmt_upd = $conn->prepare("UPDATE equipment SET inventory_number = ?, order_number = ? WHERE id = ?");
        if (!$stmt_upd) {
            throw new Exception('Prepare update numbers failed');
        }
        $stmt_upd->bind_param("ssi", $inventory_number, $order_number, $equipment_id);
        $upd_ok = $stmt_upd->execute();
        $stmt_upd->close();
        if (!$upd_ok) {
            throw new Exception('Update numbers failed');
        }

        logEquipmentHistory($equipment_id, $_SESSION['user_id'], 'Создание', "Добавлено: $name, $inventory_number, $order_number");
        $conn->commit();
        return $equipment_id;
    } catch (Throwable $e) {
        $conn->rollback();
        foreach ($photos as $photo) {
            if (file_exists($photo)) {
                unlink($photo);
            }
        }
        return false;
    }
}

function updateEquipment($data, $files) {
    global $conn;
    $id = intval($data['equipment_id'] ?? 0);
    $name = trim($data['name'] ?? '');
    $serial_number = trim($data['serial_number'] ?? '');
    $notes = trim($data['notes'] ?? '');
    $status = $data['status'] ?? 'В работе';
    $purchase_date = $data['purchase_date'] ?? null;
    $next_service_date = trim($data['next_service_date'] ?? '');
    $next_service_date = $next_service_date !== '' ? $next_service_date : null;
    $location = trim($data['location'] ?? '');
    $existing_photos = $data['existing_photos'] ?? [];
    $total_cost = floatval($data['total_cost'] ?? 0);

    if (empty($name) || $id <= 0) return false;

    $stmt = $conn->prepare("SELECT photos FROM equipment WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$current) return false;

    $current_photos = $current['photos'] ? explode(',', $current['photos']) : [];
    $photos_to_keep = array_intersect($current_photos, $existing_photos);
    $photos_to_delete = array_diff($current_photos, $photos_to_keep);

    $new_photos = [];
    if (!empty($files['new_photos']['name'][0])) {
        $upload_dir = 'Uploads/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
        $available_slots = max(0, 4 - count($photos_to_keep));
        for ($i = 0; $i < min($available_slots, count($files['new_photos']['name'])); $i++) {
            if ($files['new_photos']['size'][$i] > 0) {
                $file_name = uniqid() . '_' . basename($files['new_photos']['name'][$i]);
                if (compressImage($files['new_photos']['tmp_name'][$i], $upload_dir . $file_name)) {
                    $new_photos[] = $upload_dir . $file_name;
                }
            }
        }
    }

    $all_photos = array_merge($photos_to_keep, $new_photos);
    $photos_str = implode(',', $all_photos);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE equipment SET name=?, serial_number=?, notes=?, status=?, purchase_date=?, next_service_date=?, location=?, total_cost=?, photos=? WHERE id=?");
        if (!$stmt) {
            throw new Exception('Prepare equipment update failed');
        }
        $stmt->bind_param("sssssssssi", $name, $serial_number, $notes, $status, $purchase_date, $next_service_date, $location, $total_cost, $photos_str, $id);
        $result = $stmt->execute();
        $stmt->close();
        if (!$result) {
            throw new Exception('Equipment update failed');
        }

        if (isset($data['payments']) && is_array($data['payments'])) {
            foreach ($data['payments'] as $p_id => $p_data) {
                $p_id = intval($p_id);
                $amount = floatval($p_data['amount']);
                $date = $p_data['date'];

                if ($p_id > 0 && $amount > 0 && !empty($date)) {
                    $stmt_pay = $conn->prepare("UPDATE equipment_payments SET amount = ?, payment_date = ? WHERE id = ? AND equipment_id = ?");
                    if (!$stmt_pay) {
                        throw new Exception('Prepare payment update failed');
                    }
                    $stmt_pay->bind_param("dsii", $amount, $date, $p_id, $id);
                    $pay_ok = $stmt_pay->execute();
                    $stmt_pay->close();
                    if (!$pay_ok) {
                        throw new Exception('Payment update failed');
                    }
                }
            }
        }

        if (isset($data['payments_to_delete']) && is_array($data['payments_to_delete'])) {
            foreach ($data['payments_to_delete'] as $p_id) {
                $p_id = intval($p_id);
                if ($p_id > 0) {
                    $stmt_del_pay = $conn->prepare("DELETE FROM equipment_payments WHERE id = ? AND equipment_id = ?");
                    if (!$stmt_del_pay) {
                        throw new Exception('Prepare payment delete failed');
                    }
                    $stmt_del_pay->bind_param("ii", $p_id, $id);
                    $del_ok = $stmt_del_pay->execute();
                    $stmt_del_pay->close();
                    if (!$del_ok) {
                        throw new Exception('Payment delete failed');
                    }
                    logEquipmentHistory($id, $_SESSION['user_id'], 'Удаление платежа', "Удален платеж ID: $p_id");
                }
            }
        }

        logEquipmentHistory($id, $_SESSION['user_id'], 'Обновление', "Обновлены данные оборудования");
        $conn->commit();

        foreach ($photos_to_delete as $photo) {
            if (file_exists($photo)) unlink($photo);
        }
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        foreach ($new_photos as $photo) {
            if (file_exists($photo)) {
                unlink($photo);
            }
        }
        return false;
    }
}

function deleteEquipment($id) {
    global $conn;
    $id = intval($id);
    $stmt = $conn->prepare("SELECT photos, name, inventory_number FROM equipment WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($item) {
        $photos = $item['photos'] ? explode(',', $item['photos']) : [];
        $conn->begin_transaction();
        try {
            $stmt_del = $conn->prepare("DELETE FROM equipment WHERE id = ?");
            if (!$stmt_del) {
                throw new Exception('Prepare equipment delete failed');
            }
            $stmt_del->bind_param("i", $id);
            $del_ok = $stmt_del->execute();
            $affected = $stmt_del->affected_rows;
            $stmt_del->close();

            if (!$del_ok || $affected < 1) {
                throw new Exception('Equipment delete failed');
            }

            logEquipmentHistory($id, $_SESSION['user_id'], 'Удаление', "Удалено: {$item['name']} ({$item['inventory_number']})");
            $conn->commit();

            foreach ($photos as $photo) if (file_exists($photo)) unlink($photo);
            return true;
        } catch (Throwable $e) {
            $conn->rollback();
            return false;
        }
    }
    return false;
}

function setEquipmentStatus($id, $status, $action, $details) {
    global $conn;
    $id = intval($id);
    if ($id <= 0) return false;

    $check = $conn->prepare("SELECT id FROM equipment WHERE id = ? LIMIT 1");
    if (!$check) return false;
    $check->bind_param("i", $id);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    $check->close();
    if (!$exists) return false;

    $stmt = $conn->prepare("UPDATE equipment SET status = ? WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("si", $status, $id);
    $result = $stmt->execute();
    $stmt->close();

    if ($result) {
        logEquipmentHistory($id, $_SESSION['user_id'], $action, $details);
        return true;
    }
    return false;
}

function archiveEquipment($id) {
    return setEquipmentStatus($id, 'Архив', 'Архив', 'Оборудование перемещено в архив');
}

function restoreEquipment($id) {
    return setEquipmentStatus($id, 'В работе', 'Возврат из архива', 'Оборудование возвращено в работу');
}

function logEquipmentHistory($equipment_id, $user_id, $action, $details) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO equipment_history (equipment_id, user_id, action, details, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiss", $equipment_id, $user_id, $action, $details);
    $stmt->execute();
    $stmt->close();
}

function getEquipmentHistory($id) {
    global $conn;
    $stmt = $conn->prepare("SELECT h.*, u.username FROM equipment_history h LEFT JOIN users u ON h.user_id = u.id WHERE h.equipment_id = ? ORDER BY h.created_at DESC");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $history = [];
    while ($row = $result->fetch_assoc()) $history[] = $row;
    return $history;
}

function getEquipmentPayments($id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM equipment_payments WHERE equipment_id = ? ORDER BY payment_date DESC");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $payments = [];
    while ($row = $result->fetch_assoc()) $payments[] = $row;
    $stmt->close();
    return $payments;
}

function addEquipmentPayment($data) {
    global $conn;
    $id = intval($data['equipment_id']);
    $amount = floatval($data['payment_amount']);
    $date = $data['payment_date'] ?: date('Y-m-d');
    if ($id <= 0 || $amount <= 0) return false;
    $stmt = $conn->prepare("INSERT INTO equipment_payments (equipment_id, amount, payment_date) VALUES (?, ?, ?)");
    $stmt->bind_param("ids", $id, $amount, $date);
    $res = $stmt->execute();
    $stmt->close();
    if ($res) logEquipmentHistory($id, $_SESSION['user_id'], 'Оплата', "Внесена оплата: $amount руб.");
    return $res;
}

function searchEquipment($query, $status, $location) {
    global $conn;
    $search_term = "%" . trim($query) . "%";
    $sql = "SELECT DISTINCT e.* FROM equipment e
        LEFT JOIN equipment_payments ep ON ep.equipment_id = e.id
        WHERE (
            e.id LIKE ?
            OR e.name LIKE ?
            OR e.serial_number LIKE ?
            OR e.order_number LIKE ?
            OR e.notes LIKE ?
            OR e.inventory_number LIKE ?
            OR e.status LIKE ?
            OR e.location LIKE ?
            OR e.purchase_date LIKE ?
            OR DATE_FORMAT(e.purchase_date, '%d.%m.%Y') LIKE ?
            OR e.next_service_date LIKE ?
            OR DATE_FORMAT(e.next_service_date, '%d.%m.%Y') LIKE ?
            OR e.total_cost LIKE ?
            OR (e.total_cost - COALESCE((SELECT SUM(ep2.amount) FROM equipment_payments ep2 WHERE ep2.equipment_id = e.id), 0)) LIKE ?
            OR ep.amount LIKE ?
            OR ep.payment_date LIKE ?
            OR DATE_FORMAT(ep.payment_date, '%d.%m.%Y') LIKE ?
        )";
    $params = [
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term
    ];
    $types = "sssssssssssssssss";

    if ($status) {
        $sql .= " AND e.status = ?";
        $params[] = $status;
        $types .= "s";
    } else {
        $sql .= " AND e.status <> 'Архив'";
    }
    if ($location) {
        $sql .= " AND e.location LIKE ?";
        $params[] = "%$location%";
        $types .= "s";
    }
    $sql .= " ORDER BY e.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) $items[] = $row;
    return $items;
}

function highlightEquipmentMatch($value, $query) {
    $text = (string)($value ?? '');
    $query = trim((string)$query);
    if ($query === '') {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    $tokens = preg_split('/\s+/u', mb_strtolower($query, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
    $tokens = array_values(array_unique(array_filter($tokens, function ($token) {
        return mb_strlen($token, 'UTF-8') > 0;
    })));
    if (!$tokens) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    usort($tokens, function ($a, $b) {
        return mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8');
    });
    $pattern = '/(' . implode('|', array_map(function ($token) {
        return preg_quote($token, '/');
    }, $tokens)) . ')/iu';

    $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    $html = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        if (preg_match($pattern, $part)) {
            $html .= '<mark class="search-hit">' . htmlspecialchars($part, ENT_QUOTES, 'UTF-8') . '</mark>';
        } else {
            $html .= htmlspecialchars($part, ENT_QUOTES, 'UTF-8');
        }
    }
    return $html;
}

$items = searchEquipment($search_query, $status_filter, $location_filter);
$locations = [];
$res = $conn->query("SELECT DISTINCT location FROM equipment WHERE location != ''");
while ($row = $res->fetch_assoc()) $locations[] = $row['location'];

if ($open_details > 0) {
    $found = false;
    foreach ($items as $item) {
        if ((int)$item['id'] === $open_details) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $stmt = $conn->prepare("SELECT * FROM equipment WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $open_details);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                array_unshift($items, $row);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Оборудование</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap">
    <style>
        :root {
            --bg-1: #f4f7fb;
            --bg-2: #eaf0f7;
            --surface: #ffffff;
            --surface-2: #f8fbff;
            --border: #d2dbe7;
            --text: #1f2937;
            --muted: #6b7280;
            --accent: #0ea5a5;
            --accent-2: #3b82f6;
            --accent-warm: #f59e0b;
            --shadow: 0 16px 40px rgba(15, 23, 42, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            background:
                radial-gradient(1200px 600px at 10% 10%, rgba(59, 130, 246, 0.12), transparent 55%),
                radial-gradient(900px 500px at 90% 15%, rgba(14, 165, 165, 0.12), transparent 60%),
                linear-gradient(145deg, var(--bg-1), var(--bg-2));
            color: var(--text);
            font-family: "Manrope", "Segoe UI", sans-serif;
            font-size: 14px;
            padding: 14px;
            padding-bottom: 72px;
            margin: 0;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: url('https://www.diskzakaz.ru/1/fn.png') center / cover no-repeat;
            opacity: 0.04;
            pointer-events: none;
            z-index: 0;
        }

        body::after {
            content: "";
            position: fixed;
            inset: -30% auto auto -20%;
            width: 70vw;
            height: 70vw;
            background: radial-gradient(circle, rgba(245, 158, 11, 0.12), transparent 60%);
            filter: blur(0);
            opacity: 0.7;
            pointer-events: none;
            z-index: 0;
        }

        .container {
            max-width: 100% !important;
            width: 100% !important;
            margin: 0 auto;
            padding-left: 0;
            padding-right: 0;
            position: relative;
            z-index: 1;
        }

        .metallic-card {
            background: linear-gradient(165deg, #ffffff, #f4f8fd);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 16px;
            margin-bottom: 14px;
            padding: 14px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            animation: fadeUp 0.5s ease both;
        }

        .metallic-card::after {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.6);
            pointer-events: none;
        }

        .card-title {
            font-size: 18px;
            font-weight: 700;
            color: #16213a;
            margin-bottom: 12px;
        }

        .form-label {
            color: #1e293b;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .form-control,
        .form-select {
            background-color: var(--surface-2) !important;
            border: 1.5px solid var(--border);
            color: var(--text) !important;
            font-size: 14px;
            border-radius: 12px;
            padding: 12px 14px;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .form-control::placeholder {
            color: #94a3b8;
        }

        textarea.form-control {
            resize: none;
            overflow: hidden;
            min-height: 90px;
            white-space: pre-wrap;
        }

        .form-control:focus,
        .form-select:focus {
            background-color: #ffffff !important;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(14, 165, 165, 0.2);
            color: var(--text) !important;
        }

        .btn {
            border-radius: 12px;
            font-weight: 600;
            letter-spacing: 0.2px;
            border: 1px solid rgba(15, 23, 42, 0.12);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.12);
            transition: transform 0.15s ease, box-shadow 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 22px rgba(15, 23, 42, 0.16);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent), #0f8f93);
            border: none;
            color: #fff;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
            color: #1f2937;
            border: 1px solid #cbd5e1;
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--accent-warm), #f97316);
            border: none;
            color: #1f2937;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border: none;
            color: #fff;
        }

        .btn-info {
            background: linear-gradient(135deg, var(--accent-2), #2563eb);
            border: none;
            color: #fff;
        }

        .input-group,
        .row {
            border: 1px solid rgba(148, 163, 184, 0.4);
            border-radius: 12px;
            padding: 8px;
            background: rgba(255, 255, 255, 0.6);
        }

        .equipment-add-form {
            border: 1px solid rgba(148, 163, 184, 0.5);
            border-radius: 16px;
            padding: 14px;
            background: linear-gradient(180deg, #ffffff, #f7fbff);
        }

        .equipment-add-spoiler summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            cursor: pointer;
            user-select: none;
            list-style: none;
            padding: 12px 14px;
            border: 1px solid rgba(14, 165, 165, 0.32);
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(14, 165, 165, 0.12), rgba(59, 130, 246, 0.12));
            color: #0f3f46;
        }

        .equipment-add-spoiler summary::-webkit-details-marker {
            display: none;
        }

        .equipment-add-spoiler summary::after {
            content: "Развернуть";
            flex: 0 0 auto;
            font-size: 12px;
            font-weight: 700;
            color: #0f766e;
            padding: 4px 8px;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid rgba(14, 165, 165, 0.28);
        }

        .equipment-add-spoiler[open] summary::after {
            content: "Свернуть";
        }

        .equipment-add-spoiler[open] summary {
            margin-bottom: 12px;
        }

        .equipment-add-form .mb-2,
        .equipment-add-form .row {
            background: #ffffff;
            border: 1px solid rgba(203, 213, 225, 0.6);
            border-radius: 12px;
            padding: 10px;
            margin-bottom: 10px;
        }

        .equipment-search-row {
            display: flex;
            gap: 8px;
        }

        .equipment-search-row .form-control {
            flex: 1 1 auto;
            min-width: 0;
        }

        .equipment-search-row .btn {
            flex: 0 0 auto;
        }

        .search-hit {
            background: #fde68a;
            color: inherit;
            padding: 0 2px;
            border-radius: 3px;
        }

        .badge.bg-info {
            background: rgba(14, 165, 165, 0.15) !important;
            color: #0f766e !important;
            border: 1px solid rgba(14, 165, 165, 0.4);
            font-weight: 600;
        }

        .photos-container {
            display: flex;
            overflow-x: auto;
            padding: 10px;
            border: 1px dashed rgba(148, 163, 184, 0.6);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.8);
            gap: 8px;
        }

        .photo-preview {
            height: 110px;
            width: auto;
            object-fit: contain;
            border: 1px solid rgba(148, 163, 184, 0.6);
            border-radius: 10px;
            padding: 4px;
            background: #ffffff;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
            cursor: pointer;
        }

        .collapse-content {
            background: #ffffff;
            color: var(--text);
            padding: 12px;
            border-radius: 12px;
            margin-top: 10px;
            border: 1px solid rgba(203, 213, 225, 0.8);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.6);
        }

        .collapse-content h6 {
            font-weight: 700;
            color: #1e293b;
        }

        .nav-menu {
            background: linear-gradient(165deg, #ffffff, #eef2f7);
            border-right: 1px solid rgba(148, 163, 184, 0.4);
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.12);
        }

        .nav-menu a,
        .nav-menu button {
            border-radius: 10px;
        }

        .nav-menu a.active {
            background: linear-gradient(135deg, rgba(14, 165, 165, 0.16), rgba(59, 130, 246, 0.18));
            color: #0f766e;
            box-shadow: none;
        }

        .nav-toggle {
            background: linear-gradient(135deg, var(--accent), #0f8f93);
            border: none;
            border-radius: 12px;
            color: #fff;
        }

        .bottom-nav {
            background: linear-gradient(165deg, #ffffff, #eef2f7);
            border-top: 1px solid rgba(148, 163, 184, 0.4);
            box-shadow: 0 -10px 24px rgba(15, 23, 42, 0.12);
        }

        .bottom-nav .nav-item.active {
            background: linear-gradient(135deg, rgba(14, 165, 165, 0.16), rgba(59, 130, 246, 0.18));
            color: #0f766e;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .nav-menu {
            position: fixed;
            top: 0;
            left: -280px;
            width: 280px;
            height: 100%;
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

        .nav-menu a {
            color: #1f2937;
            text-decoration: none;
            padding: 12px 14px;
            display: block;
            border-radius: 10px;
            font-weight: 600;
        }

        .nav-menu a:hover {
            background: rgba(14, 165, 165, 0.12);
            color: #0f766e;
        }

        .nav-menu a.active {
            background: linear-gradient(135deg, rgba(14, 165, 165, 0.18), rgba(59, 130, 246, 0.2));
            color: #0f766e;
        }

        .nav-toggle {
            z-index: 1100;
            padding: 10px 12px;
            min-height: 44px;
        }

        .top-nav {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 46px;
            background: linear-gradient(165deg, #ffffff, #eef2f7);
            z-index: 1001;
            padding: 6px 10px;
            overflow-x: auto;
            white-space: nowrap;
            gap: 6px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.4);
        }

        .top-nav a {
            display: inline-block;
            color: #1f2937;
            text-decoration: none;
            font-size: 12px;
            padding: 6px 10px;
            border-radius: 8px;
        }

        .top-nav a.active {
            color: #0f766e;
            font-weight: 600;
            background: rgba(14, 165, 165, 0.12);
        }

        .bottom-nav {
            display: flex;
            align-items: center;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 62px;
            z-index: 1000;
            padding: 6px 0;
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
            color: #1f2937;
            text-decoration: none;
            font-size: 12px;
            padding: 6px 10px;
            border-radius: 10px;
            min-width: 80px;
            min-height: 50px;
            flex: 0 0 auto;
        }

        @media (max-width: 992px) {
            .bottom-nav {
                display: flex;
            }
            .top-nav {
                display: flex;
            }
            .nav-toggle {
                display: none;
            }
            body {
                padding: 58px 0 70px 0;
            }
            .container {
                max-width: 100% !important;
                width: 100% !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
            }
            .metallic-card {
                width: 100%;
                margin-left: 0;
                margin-right: 0;
                margin-bottom: 8px;
                padding: 7px;
                border-radius: 12px;
                box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
            }
            .metallic-card .card-body {
                padding: 6px;
            }
            .card-title {
                font-size: 16px;
                margin-bottom: 8px;
            }
            .btn {
                border-radius: 9px;
                box-shadow: none;
                letter-spacing: 0;
            }
            .btn:hover {
                transform: none;
                box-shadow: none;
            }
            .btn-sm,
            .btn-group-sm > .btn {
                --bs-btn-padding-y: 0.22rem;
                --bs-btn-padding-x: 0.42rem;
                --bs-btn-font-size: 0.78rem;
                line-height: 1.15;
            }
            .form-control,
            .form-select {
                min-height: 34px;
                padding: 7px 9px;
                border-radius: 9px;
                font-size: 13px;
            }
            .form-label {
                margin-bottom: 3px;
                font-size: 13px;
            }
            .input-group,
            .row {
                padding: 5px;
                border-radius: 10px;
            }
            .equipment-add-form {
                padding: 8px;
                border-radius: 12px;
            }
            .equipment-add-form .mb-2,
            .equipment-add-form .row {
                padding: 7px;
                margin-bottom: 7px;
                border-radius: 10px;
            }
            .equipment-add-spoiler summary {
                padding: 9px 10px;
                border-radius: 10px;
            }
            .equipment-add-spoiler summary::after {
                font-size: 11px;
                padding: 3px 7px;
            }
            .photos-container {
                padding: 6px;
                gap: 5px;
                margin-top: 6px;
            }
            .photo-preview {
                height: 72px;
                border-radius: 8px;
                padding: 2px;
            }
            .collapse-content {
                padding: 8px;
                margin-top: 7px;
                border-radius: 10px;
            }
            .collapse-content p,
            .collapse-content ul {
                margin-bottom: 0.45rem;
            }
            .badge {
                font-size: 11px;
                padding: 4px 6px;
            }
        }

        @media (max-width: 576px) {
            body {
                font-size: 13px;
            }
            .container > .metallic-card {
                border-left: 0;
                border-right: 0;
                border-radius: 0;
            }
            .metallic-card .card-body > div.mt-2 {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
                margin-top: 6px !important;
            }
            .metallic-card .card-body > div.mt-2 .btn,
            .metallic-card .card-body > div.mt-2 form {
                flex: 1 1 calc(50% - 5px);
                min-width: 0;
            }
            .metallic-card .card-body > div.mt-2 form .btn {
                width: 100%;
            }
            .btn:not(.btn-close) {
                padding: 5px 7px;
                font-size: 12px;
                line-height: 1.15;
            }
            .btn-group {
                display: grid;
                grid-template-columns: 1fr 1fr;
                width: 100%;
            }
            .btn-group .btn {
                white-space: normal;
            }
            #equipmentFilterForm .input-group {
                gap: 5px;
            }
            #equipmentFilterForm .equipment-search-row .btn {
                flex: 0 0 auto;
            }
            .small,
            small {
                font-size: 12px;
            }
        }
    </style>
    <link rel="stylesheet" href="views-global.css">
</head>
<body class="has-top-nav">
    <?php renderUnifiedNavigation('equipment', ['show_top' => true, 'show_bottom' => true, 'show_spacers' => false, 'toggle_label' => '☰ Меню', 'toggle_class' => 'btn nav-toggle d-none d-lg-flex']); ?>
    <div class="container">

        <?php if ($equipment_error) : ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($equipment_error); ?></div>
        <?php endif; ?>

        <?php if (!$is_archive_view) : ?>
            <div class="card metallic-card">
                <div class="card-body">
                    <details class="equipment-add-spoiler">
                        <summary class="card-title mb-0">Добавить оборудование</summary>
                        <form method="post" enctype="multipart/form-data" class="equipment-add-form">
                            <div class="mb-2">
                                <label class="form-label">ФИО клиента *</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Номер телефона</label>
                                <input type="text" class="form-control" name="serial_number">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Общая стоимость заказа</label>
                                <input type="number" step="0.01" class="form-control" name="total_cost" value="">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Статус</label>
                                <select class="form-select" name="status">
                                    <option value="В работе">В работе</option>
                                    <option value="Выдан">Выдан</option>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-2">
                                    <label class="form-label">Дата заказа</label>
                                    <input type="date" class="form-control" name="purchase_date" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-6 mb-2">
                                    <label class="form-label">Когда нужно отдать</label>
                                    <input type="date" class="form-control" name="next_service_date">
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Местоположение</label>
                                <input type="text" class="form-control" name="location" placeholder="Например: Цех 1">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Примечание</label>
                                <textarea class="form-control" name="notes" rows="2"></textarea>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Фотографии</label>
                                <input type="file" class="form-control" name="photos[]" multiple accept="image/*">
                            </div>
                            <button type="submit" name="create_equipment" class="btn btn-primary">Сохранить</button>
                        </form>
                    </details>
                </div>
            </div>
        <?php endif; ?>

        <div class="card metallic-card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <h5 class="card-title mb-0"><?php echo $is_archive_view ? 'Архив оборудования' : 'Список оборудования'; ?></h5>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Переключение списка оборудования">
                        <a href="equipment.php" class="btn <?php echo $is_archive_view ? 'btn-outline-primary' : 'btn-primary'; ?>">Рабочий список</a>
                        <a href="equipment.php?status=<?php echo urlencode('Архив'); ?>" class="btn <?php echo $is_archive_view ? 'btn-primary' : 'btn-outline-secondary'; ?>">Архив оборудования</a>
                    </div>
                </div>
                <form method="get" class="mb-3" id="equipmentFilterForm">
                    <div class="input-group equipment-search-row mb-2">
                        <input type="text" class="form-control" name="query" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Поиск по ФИО, телефону, номеру заказа, примечанию...">
                        <button type="button" class="btn btn-secondary" onclick="resetEquipmentFilters()">Сбросить</button>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <select class="form-select" name="status" onchange="this.form.submit()">
                                <option value="">Рабочий список</option>
                                <option value="В работе" <?php echo $status_filter === 'В работе' ? 'selected' : ''; ?>>В работе</option>
                                <option value="Выдан" <?php echo $status_filter === 'Выдан' ? 'selected' : ''; ?>>Выдан</option>
                                <option value="Архив" <?php echo $status_filter === 'Архив' ? 'selected' : ''; ?>>Архив</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <select class="form-select" name="location" onchange="this.form.submit()">
                                <option value="">Все места</option>
                                <?php foreach ($locations as $loc) : ?>
                                    <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo $location_filter === $loc ? 'selected' : ''; ?>><?php echo htmlspecialchars($loc); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>

                <?php if (empty($items)) : ?>
                    <p>Нет оборудования</p>
                <?php else : ?>
                    <?php foreach ($items as $item) :
                        $photos = $item['photos'] ? explode(',', $item['photos']) : [];
                        $photos = array_values(array_filter(array_map('resolvePhotoPath', $photos), function ($photo) {
                            return $photo !== '' && file_exists($photo);
                        }));
                        $payments = getEquipmentPayments($item['id']);
                        $item['payments'] = $payments; // Передаем платежи в JS
                        $total_paid = array_sum(array_column($payments, 'amount'));
                        $debt = $item['total_cost'] - $total_paid;
                        $payment_search_parts = [];
                        foreach ($payments as $p) {
                            $payment_search_parts[] = date('d.m.Y', strtotime($p['payment_date']));
                            $payment_search_parts[] = number_format($p['amount'], 2);
                            $payment_search_parts[] = $p['payment_date'];
                            $payment_search_parts[] = $p['amount'];
                        }
                        $card_search_text = implode(' ', array_filter([
                            $item['id'],
                            $item['inventory_number'],
                            $item['name'],
                            $item['status'],
                            $item['serial_number'],
                            $item['location'],
                            $item['order_number'],
                            $item['purchase_date'],
                            $item['purchase_date'] ? date('d.m.Y', strtotime($item['purchase_date'])) : '',
                            $item['next_service_date'],
                            (!empty($item['next_service_date']) && $item['next_service_date'] !== '0000-00-00' && strtotime($item['next_service_date']) !== false) ? date('d.m.Y', strtotime($item['next_service_date'])) : 'Дата не установлена',
                            $item['notes'],
                            number_format($item['total_cost'], 2),
                            $item['total_cost'],
                            number_format($debt, 2),
                            $debt,
                            implode(' ', $payment_search_parts)
                        ], function ($value) {
                            return trim((string)$value) !== '';
                        }));
                    ?>
                        <div class="card mb-2 metallic-card equipment-item-card" data-search-text="<?php echo htmlspecialchars(mb_strtolower($card_search_text, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="card-body">
                                <div>
                                    <strong>#<?php echo highlightEquipmentMatch($item['inventory_number'], $search_query); ?> <?php echo highlightEquipmentMatch($item['name'], $search_query); ?></strong>
                                    <span class="badge bg-info float-end"><?php echo highlightEquipmentMatch($item['status'], $search_query); ?></span>
                                </div>
                                <div class="small mt-1"><strong>Телефон:</strong> <?php echo highlightEquipmentMatch($item['serial_number'] ?: '-', $search_query); ?></div>
                                <div class="small mt-1"><strong>Местоположение:</strong> <?php echo highlightEquipmentMatch($item['location'] ?: '-', $search_query); ?></div>
                                <div class="<?php echo $debt > 0 ? 'text-danger' : 'text-success'; ?> mt-1"><strong>Долг: <?php echo highlightEquipmentMatch(number_format($debt, 2), $search_query); ?> руб.</strong></div>
                                <?php if (!empty($photos)) : ?>
                                    <div class="photos-container">
                                        <?php foreach ($photos as $photo) : ?>
                                            <img src="<?php echo htmlspecialchars($photo); ?>" class="photo-preview" onclick='viewPhoto(<?php echo json_encode($photo); ?>)'>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-2">
                                    <button class="btn btn-info btn-sm" data-bs-toggle="collapse" data-bs-target="#details<?php echo $item['id']; ?>">Подробности</button>
                                    <button class="btn btn-warning btn-sm" onclick="editEquipment(<?php echo htmlspecialchars(json_encode($item)); ?>)">Редактировать</button>
                                    <?php if (($item['status'] ?? '') === 'Архив') : ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="equipment_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" name="restore_equipment" class="btn btn-success btn-sm">Вернуть в работу</button>
                                        </form>
                                    <?php else : ?>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Переместить это оборудование в архив?');">
                                            <input type="hidden" name="equipment_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" name="archive_equipment" class="btn btn-secondary btn-sm">В архив</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <div class="collapse collapse-content" id="details<?php echo $item['id']; ?>">
                                    <p><strong>Номер заказа:</strong> <?php echo highlightEquipmentMatch($item['order_number'] ?: '-', $search_query); ?></p>
                                    <p><strong>Дата заказа:</strong> <?php echo highlightEquipmentMatch($item['purchase_date'] ? date('d.m.Y', strtotime($item['purchase_date'])) : '-', $search_query); ?></p>
                                    <p><strong>Когда нужно отдать:</strong> <?php echo highlightEquipmentMatch((!empty($item['next_service_date']) && $item['next_service_date'] !== '0000-00-00' && strtotime($item['next_service_date']) !== false) ? date('d.m.Y', strtotime($item['next_service_date'])) : 'Дата не установлена', $search_query); ?></p>
                                    <p><strong>Примечание:</strong> <?php echo nl2br(highlightEquipmentMatch($item['notes'] ?: '-', $search_query)); ?></p>
                                    <hr>
                                    <h6>Оплата:</h6>
                                    <p><strong>Общая стоимость:</strong> <?php echo highlightEquipmentMatch(number_format($item['total_cost'], 2), $search_query); ?> руб.</p>
                                    <div class="mb-2">
                                        <strong>Внесенные суммы:</strong>
                                        <?php if (empty($payments)) : ?>
                                            <span class="text-muted">Платежей нет</span>
                                        <?php else : ?>
                                            <ul class="list-unstyled small">
                                                <?php foreach ($payments as $p) : ?>
                                                    <li><?php echo highlightEquipmentMatch(date('d.m.Y', strtotime($p['payment_date'])), $search_query); ?>: <?php echo highlightEquipmentMatch(number_format($p['amount'], 2), $search_query); ?> руб.</li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                    <form method="post" class="row g-2 mb-2">
                                        <input type="hidden" name="equipment_id" value="<?php echo $item['id']; ?>">
                                        <input type="hidden" name="open_details" value="<?php echo $item['id']; ?>">
                                        <div class="col-6">
                                            <input type="number" step="0.01" class="form-control" name="payment_amount" placeholder="Сумма" required>
                                        </div>
                                        <div class="col-6">
                                            <input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" name="add_payment" class="btn btn-success btn-sm w-100">Внести оплату</button>
                                        </div>
                                    </form>
                                    <p class="text-danger"><strong>Задолженность: <?php echo highlightEquipmentMatch(number_format($debt, 2), $search_query); ?> руб.</strong></p>
                                    <form method="post" class="mt-2" onsubmit="return confirm('Удалить это оборудование?');">
                                        <input type="hidden" name="equipment_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="delete_equipment" class="btn btn-danger btn-sm">Удалить</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <p id="equipmentNoMatches" style="display:none;">Нет оборудования</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Модальное окно редактирования -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Редактировать</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post" enctype="multipart/form-data" id="editForm">
                        <input type="hidden" name="equipment_id" id="edit_id">
                        <input type="hidden" name="update_equipment" value="1">
                        <input type="hidden" name="open_details" id="edit_open_details">
                        <div class="mb-2">
                            <label class="form-label">ФИО клиента</label>
                            <input type="text" class="form-control" name="name" id="edit_name" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Номер телефона</label>
                            <input type="text" class="form-control" name="serial_number" id="edit_serial">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Номер заказа</label>
                            <input type="text" class="form-control" name="order_number" id="edit_order_number" readonly>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Общая стоимость заказа</label>
                            <input type="number" step="0.01" class="form-control" name="total_cost" id="edit_total_cost">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Статус</label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="В работе">В работе</option>
                                <option value="Выдан">Выдан</option>
                                <option value="Архив">Архив</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-2">
                                <label class="form-label">Дата заказа</label>
                                <input type="date" class="form-control" name="purchase_date" id="edit_purchase">
                            </div>
                            <div class="col-6 mb-2">
                                <label class="form-label">Когда нужно отдать</label>
                                <input type="date" class="form-control" name="next_service_date" id="edit_service">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Местоположение</label>
                            <input type="text" class="form-control" name="location" id="edit_location">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Примечание</label>
                            <textarea class="form-control" name="notes" id="edit_notes" rows="2"></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Текущие фото</label>
                            <div id="edit_photos_container" class="d-flex flex-wrap gap-2"></div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Платежи (редактирование)</label>
                            <div id="edit_payments_container"></div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Добавить фото</label>
                            <input type="file" class="form-control" name="new_photos[]" multiple accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Сохранить изменения</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно просмотра фото -->
    <div class="modal fade" id="photoModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-dark">
                <div class="modal-body p-0 text-center">
                    <img id="modalPhoto" src="" class="img-fluid" style="max-height: 90vh; touch-action: none; transform-origin: center center;">
                </div>
                <div class="modal-footer border-0 p-1">
                    <button type="button" class="btn btn-secondary btn-sm w-100" data-bs-dismiss="modal">Закрыть</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const modalPhotoEl = document.getElementById('modalPhoto');
        const photoModalEl = document.getElementById('photoModal');
        let pinchScale = 1;
        let pinchStartDistance = 0;
        let panX = 0;
        let panY = 0;
        let panStartX = 0;
        let panStartY = 0;
        let isPanning = false;

        function applyModalPhotoTransform() {
            modalPhotoEl.style.transform = `translate(${panX}px, ${panY}px) scale(${pinchScale})`;
        }

        function resetModalPhotoZoom() {
            pinchScale = 1;
            pinchStartDistance = 0;
            panX = 0;
            panY = 0;
            panStartX = 0;
            panStartY = 0;
            isPanning = false;
            applyModalPhotoTransform();
        }

        function getTouchDistance(touches) {
            const dx = touches[0].clientX - touches[1].clientX;
            const dy = touches[0].clientY - touches[1].clientY;
            return Math.hypot(dx, dy);
        }

        function toggleNav() {
            document.getElementById('navMenu').classList.toggle('open');
        }
        function resetEquipmentFilters() {
            const form = document.getElementById('equipmentFilterForm');
            if (form) {
                const queryInput = form.querySelector('input[name="query"]');
                const statusSelect = form.querySelector('select[name="status"]');
                const locationSelect = form.querySelector('select[name="location"]');
                if (queryInput) queryInput.value = '';
                if (statusSelect) statusSelect.value = '';
                if (locationSelect) locationSelect.value = '';
            }
            window.location.href = 'equipment.php';
        }

        const equipmentFilterForm = document.getElementById('equipmentFilterForm');
        if (equipmentFilterForm) {
            const queryInput = equipmentFilterForm.querySelector('input[name="query"]');
            const cards = Array.from(document.querySelectorAll('.equipment-item-card'));
            const noMatches = document.getElementById('equipmentNoMatches');
            const originalCardHtml = new Map(cards.map((card) => [card, card.innerHTML]));

            equipmentFilterForm.addEventListener('submit', (event) => {
                event.preventDefault();
                filterEquipmentCards(queryInput ? queryInput.value : '');
            });

            if (queryInput) {
                queryInput.addEventListener('input', () => filterEquipmentCards(queryInput.value));
                filterEquipmentCards(queryInput.value);
            }

            function filterEquipmentCards(query) {
                const normalizedQuery = normalizeSearchText(query);
                const tokens = getSearchTokens(query);
                let visibleCount = 0;

                cards.forEach((card) => {
                    card.innerHTML = originalCardHtml.get(card);
                    const haystack = card.dataset.searchText || normalizeSearchText(card.textContent);
                    const isVisible = !normalizedQuery || tokens.every((token) => haystack.includes(token));
                    card.style.display = isVisible ? '' : 'none';
                    if (isVisible) {
                        visibleCount += 1;
                        highlightCardMatches(card, tokens);
                        revealHighlightedDetails(card, tokens);
                    }
                });

                if (noMatches) {
                    noMatches.style.display = visibleCount ? 'none' : '';
                }
            }
        }

        function normalizeSearchText(value) {
            return String(value || '').toLocaleLowerCase('ru-RU').trim();
        }

        function getSearchTokens(query) {
            return [...new Set(normalizeSearchText(query).split(/\s+/).filter(Boolean))];
        }

        function highlightCardMatches(card, tokens) {
            if (!tokens.length) return;
            const blockedTags = new Set(['SCRIPT', 'STYLE', 'INPUT', 'TEXTAREA', 'SELECT', 'BUTTON']);
            const walker = document.createTreeWalker(card, NodeFilter.SHOW_TEXT, {
                acceptNode(node) {
                    const parent = node.parentElement;
                    if (!parent || blockedTags.has(parent.tagName) || parent.closest('mark')) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    return tokens.some((token) => normalizeSearchText(node.nodeValue).includes(token))
                        ? NodeFilter.FILTER_ACCEPT
                        : NodeFilter.FILTER_REJECT;
                }
            });
            const nodes = [];
            while (walker.nextNode()) nodes.push(walker.currentNode);

            nodes.forEach((node) => {
                const text = node.nodeValue || '';
                const lower = text.toLocaleLowerCase('ru-RU');
                const fragment = document.createDocumentFragment();
                let index = 0;

                while (index < text.length) {
                    let bestToken = '';
                    let bestIndex = -1;
                    tokens.forEach((token) => {
                        const found = lower.indexOf(token, index);
                        if (found !== -1 && (bestIndex === -1 || found < bestIndex || (found === bestIndex && token.length > bestToken.length))) {
                            bestIndex = found;
                            bestToken = token;
                        }
                    });

                    if (bestIndex === -1) {
                        fragment.appendChild(document.createTextNode(text.slice(index)));
                        break;
                    }
                    if (bestIndex > index) {
                        fragment.appendChild(document.createTextNode(text.slice(index, bestIndex)));
                    }
                    const mark = document.createElement('mark');
                    mark.className = 'search-hit';
                    mark.textContent = text.slice(bestIndex, bestIndex + bestToken.length);
                    fragment.appendChild(mark);
                    index = bestIndex + bestToken.length;
                }

                node.parentNode.replaceChild(fragment, node);
            });
        }

        function revealHighlightedDetails(card, tokens) {
            if (!tokens.length || typeof bootstrap === 'undefined') return;
            card.querySelectorAll('.collapse').forEach((details) => {
                if (!details.querySelector('.search-hit')) return;
                bootstrap.Collapse.getOrCreateInstance(details, { toggle: false }).show();
            });
        }

        function viewPhoto(src) {
            document.getElementById('modalPhoto').src = src;
            resetModalPhotoZoom();
            new bootstrap.Modal(document.getElementById('photoModal')).show();
        }
        function editEquipment(item) {
            document.getElementById('edit_id').value = item.id;
            document.getElementById('edit_open_details').value = item.id;
            document.getElementById('edit_name').value = item.name;
            document.getElementById('edit_serial').value = item.serial_number || '';
            document.getElementById('edit_order_number').value = item.order_number || '';
            document.getElementById('edit_total_cost').value = item.total_cost || '0.00';
            document.getElementById('edit_status').value = item.status;
            document.getElementById('edit_purchase').value = item.purchase_date || '';
            document.getElementById('edit_service').value = item.next_service_date || '';
            document.getElementById('edit_location').value = item.location || '';
            document.getElementById('edit_notes').value = item.notes || '';
            
            const container = document.getElementById('edit_photos_container');
            container.innerHTML = '';
            if (item.photos) {
                item.photos.split(',').forEach(photo => {
                    if (photo) {
                        container.innerHTML += `
                            <div>
                                <input type="checkbox" name="existing_photos[]" value="${photo}" checked>
                                <img src="${photo}" style="height:50px;">
                            </div>`;
                    }
                });
            }

            const paymentsContainer = document.getElementById('edit_payments_container');
            paymentsContainer.innerHTML = '';
            if (item.payments && item.payments.length > 0) {
                item.payments.forEach(p => {
                    paymentsContainer.innerHTML += `
                        <div class="input-group mb-1 align-items-center">
                            <input type="checkbox" name="payments_to_delete[]" value="${p.id}" class="form-check-input me-2" id="delete_payment_${p.id}">
                            <span class="input-group-text">₽</span>
                            <input type="number" step="0.01" class="form-control" name="payments[${p.id}][amount]" value="${parseFloat(p.amount).toFixed(2)}" placeholder="Сумма">
                            <input type="date" class="form-control" name="payments[${p.id}][date]" value="${p.payment_date}">
                        </div>`;
                });
            } else {
                paymentsContainer.innerHTML = '<div class="text-muted small">Нет платежей для редактирования</div>';
            }

            new bootstrap.Modal(document.getElementById('editModal')).show();
        }

        document.addEventListener('DOMContentLoaded', function () {
            const openDetailsId = <?php echo $open_details; ?>;
            if (!openDetailsId) return;

            const detailsEl = document.getElementById('details' + openDetailsId);
            if (!detailsEl) return;

            if (!detailsEl.classList.contains('show')) {
                new bootstrap.Collapse(detailsEl, { toggle: true });
            }

            const cardEl = detailsEl.closest('.card');
            if (cardEl) {
                setTimeout(() => {
                    cardEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 120);
            }
        });

        function autoGrowTextarea(textarea) {
            if (!textarea) return;
            textarea.style.height = 'auto';
            textarea.style.overflow = 'hidden';
            textarea.style.height = textarea.scrollHeight + 'px';
        }

        function initAutoGrow() {
            document.querySelectorAll('textarea.form-control').forEach((textarea) => {
                autoGrowTextarea(textarea);
                textarea.addEventListener('input', () => autoGrowTextarea(textarea));
            });
        }

        document.addEventListener('DOMContentLoaded', initAutoGrow);

        modalPhotoEl.addEventListener('touchstart', function (e) {
            if (e.touches.length === 2) {
                pinchStartDistance = getTouchDistance(e.touches);
                isPanning = false;
            } else if (e.touches.length === 1 && pinchScale > 1) {
                isPanning = true;
                panStartX = e.touches[0].clientX - panX;
                panStartY = e.touches[0].clientY - panY;
            }
        }, { passive: true });

        modalPhotoEl.addEventListener('touchmove', function (e) {
            if (e.touches.length === 2 && pinchStartDistance > 0) {
                const currentDistance = getTouchDistance(e.touches);
                const delta = currentDistance / pinchStartDistance;
                const nextScale = Math.min(4, Math.max(1, pinchScale * delta));
                pinchScale = nextScale;
                pinchStartDistance = currentDistance;
                applyModalPhotoTransform();
                e.preventDefault();
            } else if (e.touches.length === 1 && isPanning && pinchScale > 1) {
                panX = e.touches[0].clientX - panStartX;
                panY = e.touches[0].clientY - panStartY;
                applyModalPhotoTransform();
                e.preventDefault();
            }
        }, { passive: false });

        modalPhotoEl.addEventListener('touchend', function (e) {
            if (e.touches.length < 2) {
                pinchStartDistance = 0;
            }
            if (e.touches.length === 0) {
                isPanning = false;
            }
        }, { passive: true });

        photoModalEl.addEventListener('hidden.bs.modal', function () {
            resetModalPhotoZoom();
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
