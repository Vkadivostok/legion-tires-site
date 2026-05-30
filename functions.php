<?php
function handleAdminActions($conn) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_user'])) {
            $new_username = $_POST['new_username'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $new_role = $_POST['new_role'] ?? 'user';
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $new_username, $new_password, $new_role);
            $stmt->execute();
            $stmt->close();
        } elseif (isset($_POST['edit_user'])) {
            $user_id = $_POST['user_id'];
            $username = $_POST['edit_username'];
            $password = $_POST['edit_password'];
            $role = $_POST['edit_role'];
            
            $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
            $stmt->bind_param("sssi", $username, $password, $role, $user_id);
            $stmt->execute();
            $stmt->close();
        } elseif (isset($_POST['delete_user'])) {
            $user_id = (int)($_POST['user_id'] ?? 0);
            if (function_exists('deleteUser')) {
                deleteUser($user_id);
            } elseif ($user_id > 0) {
                $stmt = $conn->prepare("UPDATE users SET is_deleted = 1, is_blocked = 1, deleted_at = NOW() WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();
            }
        } elseif (isset($_POST['delete_order'])) {
            $order_id = $_POST['order_id'];
            
            $stmt = $conn->prepare("DELETE FROM orders WHERE id = ? AND status = 'archive'");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->close();
            header("Location: ?section=archive");
            exit;
        }
    }
}

function handlePostRequests($conn, $section, $queue_date) {
    global $archive_error;
    
    if ($section === 'new_order' && isset($_POST['create_order'])) {
        $queue_date = $_POST['queue_date'] ?? null;
        if ($order_id = createOrder($_POST, $_FILES, $queue_date)) {
            header("Location: ?section=in_progress");
            exit;
        }
    } elseif (isset($_POST['add_note'])) {
        addNoteAndPhotos($_POST, $_FILES);
        header("Location: ?section=" . $_POST['section']);
        exit;
    } elseif (isset($_POST['archive_order'])) {
        $order_id = $_POST['order_id'];
        $note = $_POST['note'] ?? '';
        $final_amount = floatval($_POST['final_amount'] ?? 0);
        
        if ($final_amount >= 0) {
            $archive_note = "Итоговая сумма: " . number_format($final_amount, 2) . " руб.";
            if (!empty($note)) {
                $archive_note .= " | " . $note;
            }
            $_POST['note'] = $archive_note;
            addNoteAndPhotos($_POST, $_FILES);
            
            $stmt = $conn->prepare("UPDATE orders SET status = 'archive' WHERE id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->close();
            
            $order = getOrderById($order_id);
            $photos = !empty($order['photos']) ? explode(',', $order['photos']) : [];
            $message = "<b>Заказ #$order_id перемещён в архив</b>\n" .
                       "Клиент: " . $order['client_name'] . "\n" .
                       "Госномер: " . ($order['license_plate'] ?: 'не указан') . "\n" .
                       "Телефон: " . ($order['phone'] ?: 'не указан') . "\n" .
                       "Цвет: " . ($order['color'] ?: 'не указан') . "\n" .
                       "Стоимость: " . ($order['price'] ? number_format($order['price'], 2) . ' руб.' : 'не указана') . "\n" .
                       "Итоговая сумма: " . number_format($final_amount, 2) . " руб.\n" .
                       "Примечание: " . ($order['notes'] ?: 'нет') . "\n" .
                       "Создан: " . date('d.m.Y H:i', strtotime($order['created_at'])) . "\n" .
                       "Обновлён: " . date('d.m.Y H:i');
            sendTelegramNotification($message, $photos);
            
            header("Location: ?section=completed");
            exit;
        } else {
            $archive_error = "Пожалуйста, укажите корректную итоговую сумму.";
        }
    }
}

function addNoteAndPhotos($data, $files) {
    global $conn;
    
    $order_id = $data['order_id'];
    $note = $data['note'] ?? '';
    $current_section = $data['section'];
    $username = $_SESSION['username'];
    
    $status_map = [
        'in_progress' => 'В работе',
        'completed' => 'Готово',
        'archive' => 'Архив'
    ];
    $human_readable_status = $status_map[$current_section] ?? $current_section;
    
    $photos = [];
    if (!empty($files['additional_photos']['name'][0])) {
        $upload_dir = 'Uploads/';
        for ($i = 0; $i < count($files['additional_photos']['name']); $i++) {
            if ($files['additional_photos']['size'][$i] > 0) {
                $file_name = uniqid() . '_' . basename($files['additional_photos']['name'][$i]);
                $temp_file = $files['additional_photos']['tmp_name'][$i];
                $target_file = $upload_dir . $file_name;
                
                if (compressImage($temp_file, $target_file)) {
                    $photos[] = $target_file;
                }
            }
        }
    }
    
    $photos_str = implode(',', $photos);
    $stmt = $conn->prepare("SELECT notes, photos FROM orders WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $existing_notes = $row['notes'] ? explode("\n", $row['notes']) : [];
    $existing_photos = $row['photos'] ? explode(',', $row['photos']) : [];
    
    if (!empty($note) || !empty($photos)) {
        $new_note = "[" . date('d.m.Y H:i') . " - $human_readable_status - $username] ";
        if (!empty($note)) {
            $new_note .= $note;
        }
        if (!empty($photos)) {
            $new_note .= (!empty($note) ? " | " : "") . "Добавлено " . count($photos) . " фото";
        }
        $existing_notes[] = $new_note;
    }
    
    $updated_notes = implode("\n", $existing_notes);
    $updated_photos = array_merge($existing_photos, $photos);
    $updated_photos_str = implode(',', $updated_photos);
    
    $stmt = $conn->prepare("UPDATE orders SET notes = ?, photos = ? WHERE id = ?");
    $stmt->bind_param("ssi", $updated_notes, $updated_photos_str, $order_id);
    $stmt->execute();
    $stmt->close();
}
?>
