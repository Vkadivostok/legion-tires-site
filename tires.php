<?php
require_once 'db.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

if (isset($_GET['logout'])) {
    log_change("Выход из системы");
    session_destroy();
    header("Location: index.php");
    exit;
}

track_user_activity('tires');

function tires_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function tires_upload_dir(): string
{
    $dir = __DIR__ . '/Uploads/tires';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir;
}

function tires_save_data_url(string $dataUrl): ?string
{
    if (!preg_match('#^data:image/(png|jpe?g|webp|gif);base64,(.+)$#i', $dataUrl, $m)) {
        return null;
    }

    $ext = strtolower($m[1]);
    if ($ext === 'jpeg') {
        $ext = 'jpg';
    }

    $binary = base64_decode($m[2], true);
    if ($binary === false || $binary === '') {
        return null;
    }

    $name = uniqid('tire_', true) . '.' . $ext;
    $fullPath = tires_upload_dir() . '/' . $name;
    if (file_put_contents($fullPath, $binary) === false) {
        return null;
    }

    return 'Uploads/tires/' . $name;
}

function tires_safe_path(string $photo): string
{
    return str_replace('\\', '/', trim($photo));
}

function tires_delete_local_photo(string $photo): void
{
    $photo = tires_safe_path($photo);
    if (strpos($photo, 'Uploads/tires/') !== 0) {
        return;
    }
    $full = __DIR__ . '/' . $photo;
    if (is_file($full)) {
        @unlink($full);
    }
}

if (isset($_GET['action'])) {
    $action = (string)$_GET['action'];

    if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $items = [];
        $result = $conn->query("SELECT id, item_data, photos FROM tires_inventory ORDER BY updated_at DESC");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $item = json_decode((string)$row['item_data'], true);
                if (!is_array($item)) {
                    continue;
                }
                $item['id'] = (string)$row['id'];
                if (!isset($item['photos']) || !is_array($item['photos'])) {
                    $item['photos'] = !empty($row['photos']) ? array_values(array_filter(array_map('trim', explode(',', (string)$row['photos'])))) : [];
                }
                $items[] = $item;
            }
            $result->free();
        }
        tires_json_response(['success' => true, 'items' => $items]);
    }

    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $payload = json_decode((string)file_get_contents('php://input'), true);
        $item = is_array($payload['item'] ?? null) ? $payload['item'] : [];
        $rawId = (string)($item['id'] ?? '');
        $id = preg_replace('/[^a-zA-Z0-9\-_]/', '', $rawId);
        if ($id === '') {
            $id = uniqid('tires_', true);
        }
        $id = str_replace('.', '_', $id);
        $item['id'] = $id;

        $oldPhotos = [];
        $oldAccessoryPhotos = [];
        $stmt = $conn->prepare("SELECT photos, item_data FROM tires_inventory WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("s", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $oldPhotos = !empty($row['photos']) ? array_values(array_filter(array_map('trim', explode(',', (string)$row['photos'])))) : [];
                $oldItem = json_decode((string)($row['item_data'] ?? ''), true);
                if (is_array($oldItem) && is_array($oldItem['accessories'] ?? null)) {
                    foreach ($oldItem['accessories'] as $oldAccessory) {
                        $oldPhoto = trim((string)($oldAccessory['photo'] ?? ''));
                        if ($oldPhoto !== '') {
                            $oldAccessoryPhotos[] = $oldPhoto;
                        }
                    }
                }
            }
            $stmt->close();
        }

        $newPhotos = [];
        $incomingPhotos = is_array($item['photos'] ?? null) ? $item['photos'] : [];
        foreach ($incomingPhotos as $photo) {
            $photo = trim((string)$photo);
            if ($photo === '') {
                continue;
            }
            if (strpos($photo, 'data:image/') === 0) {
                $saved = tires_save_data_url($photo);
                if ($saved !== null) {
                    $newPhotos[] = $saved;
                }
                continue;
            }

            $normalized = tires_safe_path($photo);
            $absolute = __DIR__ . '/' . ltrim($normalized, '/');
            if (strpos($normalized, 'Uploads/') === 0 && is_file($absolute)) {
                $newPhotos[] = $normalized;
            }
        }
        $newPhotos = array_values(array_unique($newPhotos));

        $newAccessoryPhotos = [];
        $incomingAccessories = is_array($item['accessories'] ?? null) ? $item['accessories'] : [];
        $normalizedAccessories = [];
        foreach ($incomingAccessories as $accessory) {
            if (!is_array($accessory)) {
                continue;
            }

            $type = trim((string)($accessory['type'] ?? ''));
            if ($type === '') {
                continue;
            }

            $photo = trim((string)($accessory['photo'] ?? ''));
            $savedPhoto = '';
            if ($photo !== '') {
                if (strpos($photo, 'data:image/') === 0) {
                    $savedPhoto = tires_save_data_url($photo) ?? '';
                } else {
                    $normalized = tires_safe_path($photo);
                    $absolute = __DIR__ . '/' . ltrim($normalized, '/');
                    if (strpos($normalized, 'Uploads/') === 0 && is_file($absolute)) {
                        $savedPhoto = $normalized;
                    }
                }
            }

            if ($savedPhoto !== '') {
                $newAccessoryPhotos[] = $savedPhoto;
            }

            $normalizedAccessories[] = [
                'type' => $type,
                'qty' => trim((string)($accessory['qty'] ?? '')),
                'note' => trim((string)($accessory['note'] ?? '')),
                'photo' => $savedPhoto
            ];
        }

        $newAccessoryPhotos = array_values(array_unique($newAccessoryPhotos));
        $item['accessories'] = $normalizedAccessories;

        $oldAllPhotos = array_values(array_unique(array_merge($oldPhotos, $oldAccessoryPhotos)));
        $newAllPhotos = array_values(array_unique(array_merge($newPhotos, $newAccessoryPhotos)));
        foreach ($oldAllPhotos as $oldPhoto) {
            if (!in_array($oldPhoto, $newAllPhotos, true)) {
                tires_delete_local_photo($oldPhoto);
            }
        }

        $item['photos'] = $newPhotos;
        $itemData = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($itemData === false) {
            tires_json_response(['success' => false, 'message' => 'Ошибка сериализации данных.'], 500);
        }
        $photosStr = implode(',', $newPhotos);
        $createdBy = (int)($_SESSION['user_id'] ?? 0);

        $stmt = $conn->prepare("
            INSERT INTO tires_inventory (id, item_data, photos, created_by)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE item_data = VALUES(item_data), photos = VALUES(photos), updated_at = CURRENT_TIMESTAMP
        ");
        if (!$stmt) {
            tires_json_response(['success' => false, 'message' => 'Ошибка подготовки запроса.'], 500);
        }
        $stmt->bind_param("sssi", $id, $itemData, $photosStr, $createdBy);
        if (!$stmt->execute()) {
            $stmt->close();
            tires_json_response(['success' => false, 'message' => 'Ошибка сохранения в БД.'], 500);
        }
        $stmt->close();
        tires_json_response(['success' => true, 'item' => $item]);
    }

    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $payload = json_decode((string)file_get_contents('php://input'), true);
        $id = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($payload['id'] ?? ''));
        if ($id === '') {
            tires_json_response(['success' => false, 'message' => 'Некорректный ID.'], 400);
        }

        $oldPhotos = [];
        $stmt = $conn->prepare("SELECT photos FROM tires_inventory WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("s", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $oldPhotos = !empty($row['photos']) ? array_values(array_filter(array_map('trim', explode(',', (string)$row['photos'])))) : [];
            }
            $stmt->close();
        }

        $stmt = $conn->prepare("DELETE FROM tires_inventory WHERE id = ?");
        if (!$stmt) {
            tires_json_response(['success' => false, 'message' => 'Ошибка подготовки удаления.'], 500);
        }
        $stmt->bind_param("s", $id);
        if (!$stmt->execute()) {
            $stmt->close();
            tires_json_response(['success' => false, 'message' => 'Ошибка удаления.'], 500);
        }
        $stmt->close();

        foreach ($oldPhotos as $oldPhoto) {
            tires_delete_local_photo($oldPhoto);
        }

        tires_json_response(['success' => true]);
    }

    tires_json_response(['success' => false, 'message' => 'Неизвестное действие.'], 400);
}
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Учет шин и дисков</title>
  <link rel="icon" type="image/jpeg" href="Logo.png">
    <link rel="apple-touch-icon" href="Logo.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="views-global.css">
  <style>
    :root {
      --bg: #f3f5f8;
      --card: #ffffff;
      --line: #c9d1dc;
      --text: #142033;
      --muted: #5d6a7e;
      --accent: #0f5db8;
      --accent-soft: #eaf3ff;
      --danger: #b82626;
      --ok: #0f7a33;
      --shadow: 0 8px 26px rgba(23, 43, 77, 0.1);
    }

    * { box-sizing: border-box; }

    html, body {
      max-width: 100%;
      overflow-x: hidden;
    }

    body {
      margin: 0;
      font-family: "Segoe UI", Tahoma, sans-serif;
      background: radial-gradient(circle at top left, #fbfdff 0%, var(--bg) 60%);
      color: var(--text);
      min-height: 100vh;
      padding: 20px;
      margin-top: 50px;
      margin-bottom: 80px;
    }

    .app {
      width: 100%;
      max-width: 1180px;
      margin: 0 auto;
      display: grid;
      gap: 16px;
    }

    .panel {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 14px;
      box-shadow: var(--shadow);
      padding: 16px;
      overflow: hidden;
    }

    .panel-title {
      margin: 0 0 12px;
      font-size: 18px;
      font-weight: 700;
    }

    .spoiler {
      width: 100%;
    }

    .spoiler-title {
      list-style: none;
      cursor: pointer;
      user-select: none;
      margin: 0;
      font-size: 20px;
      font-weight: 800;
      letter-spacing: 0.4px;
      color: #163f76;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .spoiler-title::-webkit-details-marker {
      display: none;
    }

    .spoiler-title::before {
      content: "▸";
      font-size: 16px;
      transition: transform 0.2s ease;
      color: var(--accent);
    }

    .spoiler[open] .spoiler-title::before {
      transform: rotate(90deg);
    }

    .spoiler-content {
      margin-top: 12px;
    }

    .inline-group {
      display: grid;
      gap: 8px;
      grid-template-columns: repeat(8, minmax(70px, 1fr));
    }

    .section-block {
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 10px;
      margin-bottom: 8px;
    }

    .section-heading {
      margin: 0 0 8px;
      font-size: 14px;
      font-weight: 700;
      padding: 6px 10px;
      border-radius: 8px;
      display: inline-block;
    }

    .section-tire {
      background: #eef6ff;
      border-color: #bcd7fb;
    }

    .section-tire .section-heading {
      background: #dcecff;
      color: #164a8a;
      border: 1px solid #bcd7fb;
    }

    .section-disk {
      background: #fff4eb;
      border-color: #f4d1b2;
    }

    .section-disk .section-heading {
      background: #ffe6d2;
      color: #8a4b1c;
      border: 1px solid #f4d1b2;
    }

    .field {
      display: grid;
      gap: 4px;
    }

    .field label {
      font-size: 12px;
      color: var(--muted);
    }

    .field input,
    .field select,
    .field textarea {
      width: 100%;
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 8px 10px;
      font-size: 14px;
      color: var(--text);
      background: #fff;
    }

    .field textarea {
      min-height: 90px;
      resize: vertical;
    }

    .field input:focus,
    .field select:focus,
    .field textarea:focus {
      border-color: var(--accent);
      outline: 2px solid var(--accent-soft);
    }

    .grid-2 {
      margin-top: 10px;
      display: grid;
      gap: 8px;
      grid-template-columns: 1.8fr 1fr;
    }

    .photo-box {
      border: 2px dashed var(--line);
      border-radius: 10px;
      padding: 10px;
      display: grid;
      gap: 8px;
      align-content: start;
      min-height: 220px;
      background: #fbfcff;
    }

    .preview {
      width: 100%;
      height: 170px;
      border: 1px solid var(--line);
      border-radius: 10px;
      background: #fff;
      object-fit: cover;
      cursor: zoom-in;
    }

    .thumbs {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }

    .thumb {
      width: 56px;
      height: 56px;
      border: 1px solid var(--line);
      border-radius: 8px;
      object-fit: cover;
      background: #fff;
    }

    .thumb-btn {
      padding: 0;
      border: 2px solid transparent;
      border-radius: 10px;
      background: transparent;
      cursor: pointer;
      line-height: 0;
    }

    .thumb-btn.active {
      border-color: var(--accent);
    }

    .photo-actions {
      display: grid;
      gap: 8px;
      grid-template-columns: 1fr 1fr;
    }

    .accessory-buttons {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-top: 8px;
    }

    .accessory-list {
      display: grid;
      gap: 8px;
      margin-top: 8px;
    }

    .accessory-row {
      display: grid;
      grid-template-columns: minmax(130px, 0.8fr) minmax(90px, 0.45fr) minmax(180px, 1.2fr) minmax(120px, 0.6fr) auto;
      gap: 8px;
      align-items: end;
      padding: 8px;
      border: 1px solid var(--line);
      border-radius: 10px;
      background: #fbfcff;
    }

    .accessory-remove {
      min-width: 40px;
      padding-left: 10px;
      padding-right: 10px;
    }

    .accessory-title {
      align-self: center;
      font-weight: 700;
      color: #24364f;
    }

    .accessory-photo-preview {
      display: block;
      width: 64px;
      height: 46px;
      margin-top: 5px;
      border: 1px solid var(--line);
      border-radius: 8px;
      object-fit: cover;
      cursor: zoom-in;
      background: #fff;
    }

    .btn {
      border: 1px solid var(--accent);
      background: var(--accent);
      color: #fff;
      border-radius: 9px;
      padding: 9px 12px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
    }

    .btn:hover { filter: brightness(1.05); }

    .btn-secondary {
      background: #fff;
      color: var(--accent);
    }

    .btn-danger {
      border-color: var(--danger);
      background: #fff;
      color: var(--danger);
    }

    .filter-input-wrap {
      position: relative;
    }

    .filter-input-wrap input {
      padding-right: 30px;
    }

    .filter-clear {
      position: absolute;
      right: 6px;
      top: 50%;
      transform: translateY(-50%);
      width: 20px;
      height: 20px;
      border: 1px solid #c9d1dc;
      border-radius: 50%;
      background: #fff;
      color: #5d6a7e;
      font-size: 13px;
      line-height: 16px;
      text-align: center;
      cursor: pointer;
      padding: 0;
    }

    .filter-clear.hidden {
      display: none;
    }

    .actions {
      margin-top: 10px;
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .detail-actions {
      margin-top: 6px;
    }

    #editSelected {
      background: #0b67c2;
      border-color: #0b67c2;
      color: #ffffff;
    }

    #collapseDetail {
      background: #4b5563;
      border-color: #4b5563;
      color: #ffffff;
    }

    #deleteSelected {
      background: #c62828;
      border-color: #c62828;
      color: #ffffff;
    }

    #archiveSelected {
      background: #fff;
      border-color: #6b7280;
      color: #374151;
    }

    #editSelected:hover,
    #collapseDetail:hover,
    #archiveSelected:hover,
    #deleteSelected:hover {
      filter: brightness(1.08);
    }

    .table-wrap {
      width: 100%;
      overflow: auto;
      border: 1px solid #d7deea;
      border-radius: 12px;
      background: #fff;
      box-shadow: inset 0 0 0 1px #eef2f8;
    }

    .filters {
      border: 1px solid var(--line);
      border-radius: 10px;
      padding: 10px;
      margin-bottom: 10px;
      background: #fafcff;
    }

    .filters-inline {
      display: flex;
      gap: 8px;
      align-items: end;
    }

    .list-switch {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-bottom: 10px;
    }

    .list-switch .btn {
      text-decoration: none;
    }

    .filters-inline .field {
      flex: 1;
    }

    .filters-meta {
      margin-top: 8px;
      font-size: 13px;
      color: var(--muted);
    }

    .mobile-list {
      display: none;
      gap: 8px;
    }

    .mobile-card {
      border: 1px solid var(--line);
      border-radius: 10px;
      background: #fff;
      padding: 8px;
      display: grid;
      gap: 6px;
    }

    .mobile-card.is-assembly {
      background: #ebf9ef;
      border-color: #9fdfb3;
    }

    .mobile-title {
      font-size: 14px;
      font-weight: 700;
      color: #1f2f49;
      line-height: 1.25;
    }

    .mobile-meta {
      font-size: 12px;
      color: var(--muted);
      line-height: 1.25;
    }

    .mobile-row {
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 8px;
      align-items: center;
    }

    .inline-detail-row td {
      padding: 0;
      border-bottom: 1px solid #e8eef6;
      background: #f9fbff;
    }

    .inline-detail-cell {
      padding: 8px;
    }

    .mobile-inline-detail {
      margin-top: 6px;
      border-top: 1px dashed #d3dceb;
      padding-top: 6px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 850px;
      background: #fff;
    }

    th,
    td {
      padding: 11px 12px;
      border-bottom: 1px solid #e8eef6;
      font-size: 14px;
      text-align: left;
      white-space: nowrap;
    }

    th {
      position: sticky;
      top: 0;
      background: linear-gradient(180deg, #f8fbff 0%, #f1f6ff 100%);
      color: #253753;
      font-weight: 700;
      z-index: 1;
    }

    tr.row-item { cursor: pointer; }
    tr.row-item:nth-child(even) { background: #fcfdff; }
    tr.row-item:hover { background: #eef5ff; }
    tr.row-item.is-assembly { background: #ebf9ef; }
    tr.row-item.is-assembly:hover { background: #dff4e6; }

    .tire-line {
      font-weight: 800;
      white-space: normal;
      line-height: 1.25;
    }

    .tire-param {
      display: inline-block;
      margin-right: 8px;
    }

    .tire-radius { color: #1d4ed8; }
    .tire-width { color: #0f766e; }
    .tire-profile { color: #7c3aed; }
    .tire-brand { color: #be185d; }
    .tire-season { color: #b45309; }
    .tire-condition { color: #b91c1c; }
    .tire-cost { color: #047857; }

    .qty {
      display: inline-block;
      min-width: 28px;
      text-align: center;
      border: 1px solid var(--line);
      border-radius: 999px;
      padding: 2px 8px;
      background: #f8fbff;
      font-weight: 700;
    }


    mark.hit {
      background: #ffe58f;
      color: #142033;
      padding: 0 2px;
      border-radius: 3px;
    }

    .detail {
      display: none;
      grid-template-columns: minmax(0, 1fr) 136px;
      gap: 8px;
      align-items: start;
    }

    .detail.visible { display: grid; }

    .detail-main {
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 8px;
      background: #fff;
    }

    .detail.assembly .detail-main {
      border-color: #9fdfb3;
      background: #eefaf1;
    }

    .detail.accessory .detail-main {
      border-color: #b9c9e8;
      background: #f6f9ff;
    }

    .assembly-badge {
      display: inline-block;
      margin-bottom: 8px;
      padding: 5px 10px;
      border-radius: 999px;
      border: 1px solid #9fdfb3;
      background: #e6f7ea;
      color: #1f7a3e;
      font-size: 12px;
      font-weight: 700;
    }

    .assembly-badge.hidden { display: none; }

    .detail-section-title {
      margin: 6px 0 4px;
      font-size: 12px;
      font-weight: 700;
      color: #2a3a52;
    }

    .card-grid {
      display: grid;
      gap: 8px;
      grid-template-columns: repeat(4, minmax(70px, 1fr));
    }

    #detailDiskChips {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .chip {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 6px;
      background: #f9fbff;
      min-height: 46px;
    }

    .chip .k {
      color: var(--muted);
      font-size: 10px;
      margin-bottom: 2px;
    }

    .chip .v {
      font-size: 14px;
      font-weight: 700;
    }

    .detail-notes-highlight {
      min-height: 52px;
      padding: 10px 12px;
      border: 1px solid var(--line);
      border-radius: 10px;
      background: #f9fbff;
      white-space: pre-wrap;
      font-size: 14px;
      color: var(--text);
    }

    .detail-photo-btn {
      width: 100%;
      border: 1px solid var(--line);
      border-radius: 12px;
      background: #fff;
      padding: 4px;
      cursor: zoom-in;
    }

    .detail-photo-btn.hidden {
      display: none;
    }

    .detail-photo {
      width: 100%;
      height: 136px;
      object-fit: cover;
      border: 0;
      border-radius: 9px;
      background: #fff;
      cursor: zoom-in;
    }

    .detail-thumbs {
      margin-top: 6px;
      display: flex;
      flex-wrap: wrap;
      gap: 5px;
      justify-content: flex-start;
    }

    .photo-modal {
      position: fixed;
      inset: 0;
      display: none;
      place-items: center;
      background: rgba(8, 16, 32, 0.82);
      z-index: 2000;
      padding: 14px;
    }

    .photo-modal.visible {
      display: grid;
    }

    .photo-modal img {
      max-width: min(96vw, 1080px);
      max-height: 90vh;
      border-radius: 10px;
      border: 1px solid #c9d1dc;
      background: #fff;
      object-fit: contain;
    }

    .photo-modal-close {
      position: absolute;
      top: 10px;
      right: 10px;
      border: 1px solid #d7deea;
      background: #fff;
      color: #10233f;
      border-radius: 8px;
      padding: 6px 10px;
      cursor: pointer;
      font-size: 13px;
      font-weight: 700;
    }

    .status {
      font-size: 13px;
      font-weight: 600;
      color: var(--ok);
      min-height: 18px;
    }

    .top-nav {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 1001;
      background: linear-gradient(145deg, #ffffff, #eef2f5);
      border-bottom: 1px solid #d9e2ec;
      padding: 4px 8px;
      overflow-x: auto;
      white-space: nowrap;
      gap: 8px;
      -ms-overflow-style: none;
      scrollbar-width: none;
    }

    .top-nav::-webkit-scrollbar { display: none; }

    .top-nav a {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 6px 10px;
      text-decoration: none;
      color: #1f2a37;
      border-radius: 8px;
      font-size: 12px;
      flex: 0 0 auto;
    }

    .top-nav a.active {
      background: linear-gradient(145deg, #00b4d8, #0077b6);
      color: #ffffff;
    }

    .top-nav-spacer {
      display: none;
    }

    .desktop-nav-spacer {
      display: none;
    }

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
      box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.25);
      overflow-x: auto;
      overflow-y: hidden;
      -ms-overflow-style: none;
      scrollbar-width: none;
    }

    .bottom-nav::-webkit-scrollbar { display: none; }

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
      flex: 0 auto;
    }

    .bottom-nav .nav-item.active {
      background: linear-gradient(145deg, #00b4d8, #0077b6);
      color: #ffffff;
    }

    .nav-menu {
      position: fixed;
      top: 0;
      left: -280px;
      width: 280px;
      height: 100%;
      background: linear-gradient(145deg, #ffffff, #eef2f5);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25);
      z-index: 1100;
      display: flex;
      flex-direction: column;
      padding: 20px;
      transition: left 0.3s ease-in-out;
      overflow-y: auto;
    }

    .nav-menu.open { left: 0; }

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

    .nav-menu a.active {
      background: linear-gradient(145deg, #00b4d8, #0077b6);
      color: #ffffff;
      box-shadow: 0 2px 10px rgba(0, 180, 216, 0.3);
    }

    .nav-toggle {
      position: fixed;
      top: 6px;
      left: 6px;
      z-index: 1200;
      border: none;
      border-radius: 10px;
      width: 30px;
      height: 30px;
      min-height: 30px;
      min-width: 30px;
      padding: 0;
      font-size: 16px;
      line-height: 1;
      background: linear-gradient(145deg, #00b4d8, #0077b6);
      color: #fff;
      box-shadow: 0 4px 15px rgba(0, 180, 216, 0.3);
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    @media (max-width: 900px) {
      body { padding: 12px; }
      .app { gap: 12px; }
      .panel { padding: 12px; }
      .inline-group { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .grid-2,
      .photo-actions,
      .card-grid {
        grid-template-columns: 1fr;
      }
      .accessory-row {
        grid-template-columns: 1fr;
      }
      .detail {
        grid-template-columns: minmax(0, 1fr) 112px;
      }
      .filters-inline {
        flex-direction: column;
        align-items: stretch;
      }
      .detail {
        gap: 8px;
      }
      .detail-main {
        padding: 6px;
      }
      .detail .card-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 4px;
      }
      .chip {
        padding: 5px;
        min-height: 40px;
      }
      .chip .v {
        font-size: 14px;
      }
      .detail-photo { height: 112px; }
      .detail-section-title {
        margin: 4px 0 3px;
      }
      .actions .btn { width: 100%; }
      .preview { height: 190px; }
      table {
        min-width: 680px;
      }
    }

    @media (max-width: 520px) {
      body { padding: 8px; }
      .panel-title { font-size: 16px; }
      .panel {
        padding: 10px;
        border-radius: 10px;
      }
      .spoiler-title {
        font-size: 17px;
      }
      .inline-group { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .field input,
      .field select,
      .field textarea,
      .btn { font-size: 16px; }
      th, td {
        padding: 8px;
        font-size: 13px;
      }
      .detail-main {
        padding: 5px;
        border-radius: 8px;
      }
      .detail {
        grid-template-columns: minmax(0, 1fr) 92px;
        gap: 4px;
      }
      .detail .card-grid {
        grid-template-columns: 1fr 1fr;
        gap: 3px;
      }
      .chip {
        padding: 4px;
        border-radius: 6px;
        min-height: 36px;
      }
      .chip .k {
        font-size: 9px;
        margin-bottom: 1px;
      }
      .chip .v {
        font-size: 12px;
      }
      .detail-section-title {
        font-size: 11px;
        margin: 3px 0 2px;
      }
      .assembly-badge {
        font-size: 10px;
        padding: 3px 7px;
        margin-bottom: 4px;
      }
      .detail-photo-btn {
        border-radius: 10px;
        padding: 3px;
      }
      .detail-photo {
        height: 92px;
        border-radius: 7px;
      }
      #detailTireName,
      #detailDiskName,
      #detailNotes {
        font-size: 13px;
      }
      #detailNotes {
        min-height: 52px;
      }
      .detail-notes-highlight {
        font-size: 13px;
      }
      .table-wrap {
        border-radius: 8px;
      }
      .table-wrap {
        display: none;
      }
      .mobile-list {
        display: grid;
      }
      .qty {
        min-width: 24px;
        padding: 2px 6px;
      }
      .thumb {
        width: 46px;
        height: 46px;
      }
      .inline-detail-cell {
        padding: 6px;
      }
      .mobile-inline-detail {
        margin-top: 5px;
        padding-top: 5px;
      }
      .detail-actions {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 4px;
      }
      .detail-actions .btn {
        width: auto;
        padding: 6px 4px;
        font-size: 11px;
        border-radius: 6px;
        white-space: nowrap;
      }
    }

    @media (min-width: 993px) {
      .bottom-nav { display: none; }
      .top-nav { display: none !important; }
      .nav-toggle { display: inline-flex !important; }
      .desktop-nav-spacer {
        display: block;
        height: 40px;
      }
    }

    @media (max-width: 992px) {
      .top-nav { display: flex; }
      .bottom-nav { display: flex; }
      .nav-toggle { display: none !important; }
      body {
        padding-bottom: 74px;
      }
      body.has-top-nav {
        margin-top: 0 !important;
        padding-top: 0 !important;
      }
      .app {
        margin-top: 0;
      }
      .top-nav-spacer {
        display: block;
        height: 56px;
      }
      .desktop-nav-spacer {
        display: none;
      }
    }

    @media (min-width: 993px) {
      .nav-toggle,
      .nav-toggle.d-none,
      .nav-toggle.d-none.d-lg-flex {
        position: fixed !important;
        top: 16px !important;
        left: 16px !important;
        z-index: 1301 !important;
        width: auto !important;
        height: auto !important;
        min-width: 0 !important;
        min-height: 42px !important;
        padding: 8px 12px !important;
        border: 1px solid rgba(80, 62, 44, 0.32) !important;
        border-radius: 10px !important;
        background: linear-gradient(145deg, #fffdf9, #efe2cf) !important;
        color: #1f2a37 !important;
        font-size: 14px !important;
        font-weight: 800 !important;
        line-height: 1.2 !important;
        box-shadow: 0 10px 24px rgba(76, 55, 31, 0.18), inset 0 1px 0 rgba(255, 255, 255, 0.9) !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        overflow: visible !important;
      }

      .nav-toggle::before,
      .nav-toggle::after {
        content: none !important;
        display: none !important;
        animation: none !important;
      }
    }
  </style>
</head>
<body class="has-top-nav">
  <?php renderUnifiedNavigation('tires', ['show_top' => true, 'show_bottom' => true, 'show_spacers' => true, 'toggle_label' => '☰ Меню', 'toggle_class' => 'btn nav-toggle d-none d-lg-flex']); ?>

  <div class="app">
    <section class="panel">
      <details class="spoiler" id="arrivalSpoiler">
        <summary id="arrivalTitle" class="spoiler-title">ПРИХОД</summary>
        <div class="spoiler-content">
      <form id="itemForm">
        <div class="section-block section-tire">
          <h3 class="section-heading">Данные резины</h3>
          <div class="inline-group">
            <div class="field"><label for="diameter">Диаметр</label><input id="diameter" inputmode="numeric" placeholder="Например 17" /></div>
            <div class="field"><label for="width">Ширина</label><input id="width" list="widthOptions" /></div>
            <div class="field"><label for="profile">Высота профиля</label><input id="profile" list="profileOptions" /></div>
            <div class="field"><label for="tireBrand">Бренд шины</label><input id="tireBrand" list="tireBrandOptions" /></div>
            <div class="field"><label for="season">Сезон</label><select id="season"><option>Лето</option><option>Шипы</option><option>Не шипы</option></select></div>
            <div class="field"><label for="qty">Кол-во</label><input id="qty" type="number" min="1" value="4" /></div>
            <div class="field">
              <label for="price">Состояние</label>
              <select id="price">
                <option value="">Не указано</option>
                <option>Новые</option>
                <option>Б/у</option>
              </select>
            </div>
            <div class="field"><label for="tireCost">Стоимость</label><input id="tireCost" type="number" min="0" step="1" placeholder="₽" /></div>
          </div>
        </div>

        <details class="section-block section-disk spoiler">
          <summary class="spoiler-title">Данные диска</summary>
          <div class="spoiler-content">
            <div class="inline-group">
              <div class="field"><label for="diskDiameter">Диаметр диска</label><select id="diskDiameter"></select></div>
              <div class="field"><label for="diskWidth">Ширина диска</label><input id="diskWidth" /></div>
              <div class="field"><label for="pcd">Разболтовка</label><input id="pcd" list="pcdOptions" placeholder="5x114.3" /></div>
              <div class="field"><label for="holes">Кол-во болтов</label><select id="holes"></select></div>
              <div class="field"><label for="dia">Диаметр ЦО</label><input id="dia" list="diaOptions" /></div>
              <div class="field"><label for="diskBrand">ET</label><input id="diskBrand" list="diskBrandOptions" /></div>
              <div class="field"><label for="diskMaker">Бренд диска</label><input id="diskMaker" list="diskMakerOptions" /></div>
              <div class="field"><label for="diskQty">Кол-во</label><input id="diskQty" type="number" min="1" /></div>
              <div class="field">
                <label for="warehouse">Состояние</label>
                <select id="warehouse">
                  <option value="">Не указано</option>
                  <option>Новые</option>
                  <option>Б/у</option>
                </select>
              </div>
              <div class="field"><label for="diskCost">Стоимость</label><input id="diskCost" type="number" min="0" step="1" placeholder="₽" /></div>
            </div>
          </div>
        </details>

        <details class="section-block spoiler">
          <summary class="spoiler-title">Дополнительно</summary>
          <div class="spoiler-content">
            <div class="accessory-buttons">
              <button class="btn btn-secondary accessory-add" type="button" data-accessory-type="Болты/Гайки">Болты/Гайки</button>
              <button class="btn btn-secondary accessory-add" type="button" data-accessory-type="Колпачки ЦО">Колпачки ЦО</button>
              <button class="btn btn-secondary accessory-add" type="button" data-accessory-type="Наклейки /Стикеры">Наклейки /Стикеры</button>
              <button class="btn btn-secondary accessory-add" type="button" data-accessory-type="Датчики давления">Датчики давления</button>
            </div>
            <div id="accessoryList" class="accessory-list"></div>
          </div>
        </details>

        <div class="grid-2">
          <div>
            <div class="field" style="margin-top: 8px;"><label for="notes">Примечание</label><textarea id="notes"></textarea></div>
          </div>
          <div class="photo-box">
            <img id="preview" class="preview" alt="Фото" />
            <div id="previewThumbs" class="thumbs"></div>
            <div class="photo-actions">
              <label class="btn btn-secondary" for="photoInput" style="text-align:center;">Добавить фото</label>
              <label class="btn btn-secondary" for="cameraInput" style="text-align:center;">Камера</label>
            </div>
            <input id="photoInput" type="file" accept="image/*" multiple hidden />
            <input id="cameraInput" type="file" accept="image/*" capture="environment" hidden />
          </div>
        </div>

        <datalist id="widthOptions"></datalist>
        <datalist id="profileOptions"></datalist>
        <datalist id="tireBrandOptions"></datalist>
        <datalist id="pcdOptions"></datalist>
        <datalist id="diaOptions"></datalist>
        <datalist id="diskBrandOptions"></datalist>
        <datalist id="diskMakerOptions"></datalist>

        <div class="actions">
          <button class="btn" type="submit">Сохранить товар</button>
          <button class="btn btn-secondary d-none" id="exitEdit" type="button">Выйти из редактирования</button>
          <button class="btn btn-secondary" id="clearForm" type="button">Очистить форму</button>
        </div>
        <div id="status" class="status"></div>
      </form>
        </div>
      </details>
    </section>

    <section class="panel">
      <div class="filters">
        <div class="list-switch">
          <button id="showActiveItems" class="btn" type="button">Рабочий список</button>
          <button id="showArchiveItems" class="btn btn-secondary" type="button">Архив шин</button>
        </div>
        <div class="filters-inline">
          <div class="field">
            <div class="filter-input-wrap">
              <input id="filterText" placeholder="Например: 19 Michelin 5x114 лето LS склад" />
              <button id="clearFilterX" class="filter-clear hidden" type="button" aria-label="Очистить фильтр">×</button>
            </div>
          </div>
        </div>
        <div id="filtersMeta" class="filters-meta"></div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Шины</th>
              <th>Кол-во (шины)</th>
            </tr>
          </thead>
          <tbody id="itemsBody"></tbody>
        </table>
      </div>
      <div id="mobileItems" class="mobile-list"></div>
    </section>

    <section class="panel" style="display:none;">
      <div id="detail" class="detail">
        <div class="detail-main">
          <div id="assemblyBadge" class="assembly-badge hidden">Колесо в сборе</div>
          <div id="detailTireTitle" class="detail-section-title">Параметры шины</div>
          <div class="card-grid" id="detailTireChips"></div>
          <div id="detailDiskTitle" class="detail-section-title">Параметры диска</div>
          <div class="card-grid" id="detailDiskChips"></div>
          <div id="detailTireNameRow" class="field" style="margin-top: 8px;"><label>Название шины</label><input id="detailTireName" readonly /></div>
          <div id="detailDiskNameRow" class="field" style="margin-top: 8px;"><label>Название диска</label><input id="detailDiskName" readonly /></div>
          <div id="detailAccessoriesTitle" class="detail-section-title">Дополнительно</div>
          <div class="card-grid" id="detailAccessoriesChips"></div>
          <div class="field" style="margin-top: 8px;"><label>Примечание</label><textarea id="detailNotes" readonly></textarea><div id="detailNotesHighlighted" class="detail-notes-highlight" style="display:none;"></div></div>
          <div class="actions detail-actions">
            <button id="editSelected" class="btn btn-secondary" type="button" data-full-label="Редактировать" data-short-label="Ред.">Редактировать</button>
            <button id="collapseDetail" class="btn btn-secondary" type="button" data-full-label="Свернуть" data-short-label="Сверн.">Свернуть</button>
            <button id="archiveSelected" class="btn btn-secondary" type="button" data-full-label="Переместить в архив" data-short-label="Архив">Переместить в архив</button>
            <button id="deleteSelected" class="btn btn-danger" type="button" data-full-label="Удалить" data-short-label="Удал.">Удалить</button>
          </div>
        </div>
        <div>
          <button id="detailPhotoBtn" class="detail-photo-btn hidden" type="button" title="Открыть фото">
            <img id="detailPhoto" class="detail-photo" alt="Фото товара" />
          </button>
          <div id="detailPhotoThumbs" class="detail-thumbs"></div>
        </div>
      </div>
      <div id="detailEmpty" style="color: var(--muted); font-size: 14px;">Выберите строку в таблице, чтобы открыть карточку товара.</div>
    </section>
  </div>
  <div id="detailHome" style="display:none;"></div>

  <div id="photoModal" class="photo-modal" aria-hidden="true">
    <button id="photoModalClose" class="photo-modal-close" type="button">Закрыть</button>
    <img id="photoModalImg" alt="Фото товара" />
  </div>

  <script>
    function syncTopNavSpacer() {
      const topNav = document.querySelector(".top-nav");
      const spacer = document.getElementById("topNavSpacer");
      if (!topNav || !spacer) return;
      if (window.innerWidth <= 992) {
        const h = Math.ceil(topNav.getBoundingClientRect().height) + 6;
        spacer.style.height = `${h}px`;
      } else {
        spacer.style.height = "0px";
      }
    }

    function syncDesktopNavSpacer() {
      const toggleBtn = document.querySelector(".nav-toggle");
      const spacer = document.getElementById("desktopNavSpacer");
      if (!toggleBtn || !spacer) return;
      if (window.innerWidth >= 993) {
        const rect = toggleBtn.getBoundingClientRect();
        const h = Math.ceil(rect.bottom) + 8;
        spacer.style.height = `${h}px`;
      } else {
        spacer.style.height = "0px";
      }
    }

    function syncActionButtonLabels() {
      const isMobile = window.innerWidth <= 520;
      document.querySelectorAll(".detail-actions .btn[data-full-label]").forEach((btn) => {
        const full = btn.getAttribute("data-full-label") || btn.textContent || "";
        const short = btn.getAttribute("data-short-label") || full;
        btn.textContent = isMobile ? short : full;
      });
    }

    function toggleNav() {
      const navMenu = document.getElementById("navMenu");
      if (navMenu) {
        navMenu.classList.toggle("open");
      }
    }

    document.addEventListener("click", function (event) {
      const navMenu = document.getElementById("navMenu");
      const navToggleBtn = document.querySelector(".nav-toggle");
      if (!navMenu || !navToggleBtn) return;
      if (navMenu.classList.contains("open") && !navMenu.contains(event.target) && !navToggleBtn.contains(event.target)) {
        navMenu.classList.remove("open");
      }
    });

    window.addEventListener("load", syncTopNavSpacer);
    window.addEventListener("load", syncDesktopNavSpacer);
    window.addEventListener("load", syncActionButtonLabels);
    window.addEventListener("resize", syncTopNavSpacer);
    window.addEventListener("resize", syncDesktopNavSpacer);
    window.addEventListener("resize", syncActionButtonLabels);
    window.addEventListener("orientationchange", syncTopNavSpacer);
    window.addEventListener("orientationchange", syncDesktopNavSpacer);
    window.addEventListener("orientationchange", syncActionButtonLabels);

    const API_URL = "tires.php";

    const refs = {
      form: document.getElementById("itemForm"),
      body: document.getElementById("itemsBody"),
      status: document.getElementById("status"),
      diameter: document.getElementById("diameter"),
      diskDiameter: document.getElementById("diskDiameter"),
      holes: document.getElementById("holes"),
      preview: document.getElementById("preview"),
      previewThumbs: document.getElementById("previewThumbs"),
      photoInput: document.getElementById("photoInput"),
      cameraInput: document.getElementById("cameraInput"),
      clearForm: document.getElementById("clearForm"),
      exitEdit: document.getElementById("exitEdit"),
      accessoryList: document.getElementById("accessoryList"),
      arrivalSpoiler: document.getElementById("arrivalSpoiler"),
      arrivalTitle: document.getElementById("arrivalTitle"),
      filterText: document.getElementById("filterText"),
      clearFilterX: document.getElementById("clearFilterX"),
      filtersMeta: document.getElementById("filtersMeta"),
      showActiveItems: document.getElementById("showActiveItems"),
      showArchiveItems: document.getElementById("showArchiveItems"),
      mobileItems: document.getElementById("mobileItems"),
      detailHome: document.getElementById("detailHome"),
      detail: document.getElementById("detail"),
      detailEmpty: document.getElementById("detailEmpty"),
      detailTireTitle: document.getElementById("detailTireTitle"),
      detailDiskTitle: document.getElementById("detailDiskTitle"),
      detailTireChips: document.getElementById("detailTireChips"),
      detailDiskChips: document.getElementById("detailDiskChips"),
      detailAccessoriesTitle: document.getElementById("detailAccessoriesTitle"),
      detailAccessoriesChips: document.getElementById("detailAccessoriesChips"),
      assemblyBadge: document.getElementById("assemblyBadge"),
      detailPhotoBtn: document.getElementById("detailPhotoBtn"),
      detailPhoto: document.getElementById("detailPhoto"),
      detailPhotoThumbs: document.getElementById("detailPhotoThumbs"),
      photoModal: document.getElementById("photoModal"),
      photoModalImg: document.getElementById("photoModalImg"),
      photoModalClose: document.getElementById("photoModalClose"),
      detailTireName: document.getElementById("detailTireName"),
      detailTireNameRow: document.getElementById("detailTireNameRow"),
      detailDiskName: document.getElementById("detailDiskName"),
      detailDiskNameRow: document.getElementById("detailDiskNameRow"),
      detailNotes: document.getElementById("detailNotes"),
      detailNotesHighlighted: document.getElementById("detailNotesHighlighted"),
      editSelected: document.getElementById("editSelected"),
      collapseDetail: document.getElementById("collapseDetail"),
      archiveSelected: document.getElementById("archiveSelected"),
      deleteSelected: document.getElementById("deleteSelected")
    };

    let items = [];
    let draftPhotos = [];
    let previewPhotoIndex = 0;
    let detailPhotos = [];
    let detailPhotoIndex = 0;
    let detailAccessoryOnly = false;
    let activeDetailQuery = "";
    let activeInlineRow = null;
    let activeMobileHost = null;
    let selectedId = null;
    let editingId = null;
    let isArchiveView = false;
    let accessoryDraft = [];
    const ACCESSORY_TYPES = ["Болты/Гайки", "Колпачки ЦО", "Наклейки /Стикеры", "Датчики давления"];
    const PHOTO_COMPRESS = {
      maxSide: 720,
      jpegQuality: 0.45,
      minSize: 250 * 1024
    };
    const PHOTO_COMPRESS_SKIP_TYPES = new Set(["image/gif", "image/svg+xml"]);

    setupDiameterSelects();
    updateFilterClearButton();
    setPreview([]);
    renderAccessoryList();
    initializePage();

    refs.form.addEventListener("submit", onSave);
    refs.clearForm.addEventListener("click", clearForm);
    refs.exitEdit.addEventListener("click", exitEditMode);
    document.querySelectorAll(".accessory-add").forEach((btn) => {
      btn.addEventListener("click", () => addAccessory(btn.dataset.accessoryType || ""));
    });
    refs.accessoryList.addEventListener("input", updateAccessoryDraftFromForm);
    refs.accessoryList.addEventListener("change", updateAccessoryDraftFromForm);
    refs.accessoryList.addEventListener("click", onAccessoryListClick);
    refs.accessoryList.addEventListener("change", onAccessoryPhotoChange);
    refs.photoInput.addEventListener("change", onPickPhoto);
    refs.cameraInput.addEventListener("change", onPickPhoto);
    refs.editSelected.addEventListener("click", editSelectedItem);
    refs.collapseDetail.addEventListener("click", closeDetail);
    refs.archiveSelected.addEventListener("click", toggleSelectedArchive);
    refs.deleteSelected.addEventListener("click", deleteSelectedItem);
    refs.showActiveItems.addEventListener("click", () => switchArchiveView(false));
    refs.showArchiveItems.addEventListener("click", () => switchArchiveView(true));
    refs.filterText.addEventListener("input", () => {
      updateFilterClearButton();
      renderTable();
    });
    refs.clearFilterX.addEventListener("click", clearFilter);
    refs.detailPhotoBtn.addEventListener("click", openPhotoModal);
    refs.detailPhoto.addEventListener("click", (e) => {
      e.stopPropagation();
      openPhotoModal(refs.detailPhoto.src);
    });
    refs.preview.addEventListener("click", (e) => {
      e.stopPropagation();
      openPhotoModal(draftPhotos[previewPhotoIndex] || refs.preview.src || "");
    });
    refs.detail.addEventListener("click", (e) => e.stopPropagation());
    refs.photoModalClose.addEventListener("click", closePhotoModal);
    refs.photoModal.addEventListener("click", (e) => {
      if (e.target === refs.photoModal) closePhotoModal();
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closePhotoModal();
    });

    async function initializePage() {
      await loadItemsFromServer();
      refreshAutocomplete();
      updateArchiveViewButtons();
      renderTable();
    }

    async function loadItemsFromServer() {
      try {
        const response = await fetch(`${API_URL}?action=list`, { credentials: "same-origin" });
        if (!response.ok) {
          setStatus("Ошибка загрузки данных", "error");
          return;
        }
        const payload = await response.json();
        items = Array.isArray(payload.items) ? payload.items : [];
      } catch {
        setStatus("Ошибка загрузки данных", "error");
      }
    }

    function setupDiameterSelects() {
      fillDiameterSelect(refs.diskDiameter, "Не указан");
      fillRangeSelect(refs.holes, "Не указано", 1, 5);
    }

    function fillDiameterSelect(select, placeholder) {
      select.innerHTML = "";
      const emptyOption = document.createElement("option");
      emptyOption.value = "";
      emptyOption.textContent = placeholder;
      select.appendChild(emptyOption);

      for (let d = 13; d <= 26; d += 1) {
        const option = document.createElement("option");
        option.value = String(d);
        option.textContent = String(d);
        select.appendChild(option);
      }
    }

    function fillRangeSelect(select, placeholder, from, to) {
      select.innerHTML = "";
      const emptyOption = document.createElement("option");
      emptyOption.value = "";
      emptyOption.textContent = placeholder;
      select.appendChild(emptyOption);

      for (let v = from; v <= to; v += 1) {
        const option = document.createElement("option");
        option.value = String(v);
        option.textContent = String(v);
        select.appendChild(option);
      }
    }

    function getField(id) {
      return document.getElementById(id).value.trim();
    }

    async function onSave(e) {
      e.preventDefault();
      const isEditing = Boolean(editingId);
      const item = {
        id: editingId || crypto.randomUUID(),
        diameter: getField("diameter"),
        width: getField("width"),
        profile: getField("profile"),
        tireBrand: getField("tireBrand"),
        season: getField("season"),
        qty: getField("qty"),
        price: getField("price"),
        tireCost: getField("tireCost"),
        diskDiameter: getField("diskDiameter"),
        diskWidth: getField("diskWidth"),
        holes: getField("holes"),
        pcd: getField("pcd"),
        dia: getField("dia"),
        diskBrand: getField("diskBrand"),
        diskMaker: getField("diskMaker"),
        diskQty: getField("diskQty"),
        warehouse: getField("warehouse"),
        diskCost: getField("diskCost"),
        notes: getField("notes"),
        accessories: getAccessoriesFromForm(),
        photos: draftPhotos.slice()
      };
      if (editingId) {
        const current = items.find((x) => x.id === editingId);
        if (current && isArchivedItem(current)) {
          item.archived = true;
          item.archivedAt = current.archivedAt || new Date().toISOString();
        }
      }
      const hasTireParams = hasValue(item.diameter);
      const hasDiskParams = [item.diskDiameter, item.diskWidth, item.holes, item.pcd, item.dia, item.diskBrand, item.diskMaker, item.diskQty, item.warehouse, item.diskCost]
        .some((v) => hasValue(v));
      // Если заполнен только блок дисков, не требуем шинное количество и используем diskQty как отображаемое.
      if (!hasTireParams && hasDiskParams && !hasValue(item.qty) && hasValue(item.diskQty)) {
        item.qty = item.diskQty;
      }
      if (!hasTireParams && !hasDiskParams && item.accessories.length) {
        item.qty = "";
      }

      if (editingId) {
        const current = items.find((x) => x.id === editingId);
        if (current && !item.photos.length) {
          item.photos = getItemPhotos(current);
        }
      }
      delete item.photo;

      try {
        const response = await fetch(`${API_URL}?action=save`, {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ item })
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) {
          throw new Error(payload.message || "Ошибка сохранения");
        }
        await loadItemsFromServer();
        refreshAutocomplete();
        renderTable();
        closeDetail();
        clearForm();
        setStatus(isEditing ? "Товар обновлен" : "Товар добавлен");
      } catch (error) {
        setStatus(error.message || "Ошибка сохранения", "error");
      }
    }

    function clearForm() {
      refs.form.reset();
      editingId = null;
      accessoryDraft = [];
      draftPhotos = [];
      previewPhotoIndex = 0;
      renderAccessoryList();
      setPreview([]);
      setArrivalTitle(false);
      refs.exitEdit.classList.add("d-none");
      refs.status.textContent = "";
    }

    function exitEditMode() {
      clearForm();
      setStatus("Редактирование отменено");
    }

    function setStatus(msg, type = "ok") {
      refs.status.textContent = msg;
      refs.status.style.color = type === "error" ? "var(--danger)" : "var(--ok)";
      setTimeout(() => {
        refs.status.textContent = "";
      }, 2000);
    }

    function switchArchiveView(showArchive) {
      isArchiveView = Boolean(showArchive);
      closeDetail();
      updateArchiveViewButtons();
      renderTable();
    }

    function updateArchiveViewButtons() {
      refs.showActiveItems.classList.toggle("btn-secondary", isArchiveView);
      refs.showArchiveItems.classList.toggle("btn-secondary", !isArchiveView);
    }

    function addAccessory(type) {
      const cleanType = ACCESSORY_TYPES.includes(type) ? type : ACCESSORY_TYPES[0];
      updateAccessoryDraftFromForm();
      accessoryDraft.push({
        type: cleanType,
        qty: "",
        note: "",
        photo: ""
      });
      renderAccessoryList();
    }

    function renderAccessoryList() {
      if (!accessoryDraft.length) {
        refs.accessoryList.innerHTML = '<div class="status" style="color: var(--muted);">Дополнительные позиции не добавлены.</div>';
        return;
      }

      refs.accessoryList.innerHTML = accessoryDraft.map((accessory, index) => `
        <div class="accessory-row" data-accessory-index="${index}">
          <div class="accessory-title">${escapeHtml(accessory.type || ACCESSORY_TYPES[0])}</div>
          <input type="hidden" value="${escapeHtml(accessory.type || ACCESSORY_TYPES[0])}" data-accessory-field="type" />
          <div class="field">
            <label>Кол-во</label>
            <input value="${escapeHtml(accessory.qty || "")}" data-accessory-field="qty" />
          </div>
          <div class="field">
            <label>Примечание</label>
            <input value="${escapeHtml(accessory.note || "")}" data-accessory-field="note" />
          </div>
          <div class="field">
            <label>Фото</label>
            <input type="file" accept="image/*" data-accessory-photo="${index}" />
            ${accessory.photo ? `<img class="accessory-photo-preview" src="${escapeHtml(accessory.photo)}" alt="Фото" data-accessory-preview="${index}" />` : ""}
          </div>
          <button class="btn btn-danger accessory-remove" type="button" data-remove-accessory="${index}">×</button>
        </div>
      `).join("");
    }

    function updateAccessoryDraftFromForm() {
      const rows = refs.accessoryList.querySelectorAll("[data-accessory-index]");
      accessoryDraft = Array.from(rows).map((row) => {
        const get = (field) => {
          const input = row.querySelector(`[data-accessory-field="${field}"]`);
          return input ? input.value.trim() : "";
        };
        return {
          type: get("type") || ACCESSORY_TYPES[0],
          qty: get("qty"),
          note: get("note"),
          photo: accessoryDraft[Number(row.dataset.accessoryIndex || "-1")]?.photo || ""
        };
      });
    }

    function getAccessoriesFromForm() {
      updateAccessoryDraftFromForm();
      return accessoryDraft
        .map((item) => ({
          type: ACCESSORY_TYPES.includes(item.type) ? item.type : ACCESSORY_TYPES[0],
          qty: item.qty || "",
          note: item.note || "",
          photo: item.photo || ""
        }))
        .filter((item) => hasValue(item.type));
    }

    function onAccessoryListClick(event) {
      const preview = event.target.closest("[data-accessory-preview]");
      if (preview) {
        const index = Number(preview.dataset.accessoryPreview || "-1");
        const photo = accessoryDraft[index]?.photo || preview.getAttribute("src") || "";
        openPhotoModal(photo);
        return;
      }

      const removeBtn = event.target.closest("[data-remove-accessory]");
      if (!removeBtn) return;
      const index = Number(removeBtn.dataset.removeAccessory || "-1");
      updateAccessoryDraftFromForm();
      accessoryDraft.splice(index, 1);
      renderAccessoryList();
    }

    async function onAccessoryPhotoChange(event) {
      const input = event.target.closest("[data-accessory-photo]");
      if (!input || !input.files || !input.files[0]) return;

      const index = Number(input.dataset.accessoryPhoto || "-1");
      if (!accessoryDraft[index]) return;

      updateAccessoryDraftFromForm();
      const compressed = await compressPhotoFile(input.files[0]);
      const photo = await readFileAsDataURL(compressed);
      if (photo) {
        accessoryDraft[index].photo = photo;
        renderAccessoryList();
      }
      input.value = "";
    }

    function getItemAccessories(item) {
      return Array.isArray(item.accessories)
        ? item.accessories.filter((accessory) => accessory && hasValue(accessory.type))
        : [];
    }

    function formatAccessory(accessory) {
      const parts = [accessory.type];
      if (hasValue(accessory.qty)) parts.push(accessory.qty);
      if (hasValue(accessory.note)) parts.push(accessory.note);
      return parts.join(" · ");
    }

    function formatAccessoryDetails(accessory) {
      const parts = [];
      if (hasValue(accessory.qty)) parts.push(accessory.qty);
      if (hasValue(accessory.note)) parts.push(accessory.note);
      if (hasValue(accessory.photo)) parts.push("Фото");
      return parts.join(" · ");
    }

    function replaceFileExtension(name, ext) {
      const safeName = String(name || "photo").trim() || "photo";
      const idx = safeName.lastIndexOf(".");
      const base = idx > 0 ? safeName.slice(0, idx) : safeName;
      return `${base}.${ext}`;
    }

    function shouldSkipCompression(file) {
      if (!file || !/^image\//.test(file.type)) {
        return true;
      }
      return PHOTO_COMPRESS_SKIP_TYPES.has(file.type);
    }

    function canvasToBlob(canvas, type, quality) {
      return new Promise((resolve) => {
        if (!canvas || typeof canvas.toBlob !== "function") {
          resolve(null);
          return;
        }
        canvas.toBlob((blob) => resolve(blob), type, quality);
      });
    }

    async function loadImageSource(file) {
      if (!file) return null;

      if (window.createImageBitmap) {
        try {
          const bitmap = await createImageBitmap(file, { imageOrientation: "from-image" });
          return {
            source: bitmap,
            width: bitmap.width,
            height: bitmap.height,
            cleanup() {
              if (bitmap && bitmap.close) bitmap.close();
            }
          };
        } catch {
          // fallback to Image
        }
      }

      return new Promise((resolve) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () =>
          resolve({
            source: img,
            width: img.naturalWidth || img.width,
            height: img.naturalHeight || img.height,
            cleanup() {
              URL.revokeObjectURL(url);
            }
          });
        img.onerror = () => {
          URL.revokeObjectURL(url);
          resolve(null);
        };
        img.src = url;
      });
    }

    async function compressPhotoFile(file) {
      if (shouldSkipCompression(file)) {
        return file;
      }

      const loaded = await loadImageSource(file);
      if (!loaded || !loaded.width || !loaded.height) {
        return file;
      }

      const largestSide = Math.max(loaded.width, loaded.height);
      const scale = largestSide > PHOTO_COMPRESS.maxSide ? PHOTO_COMPRESS.maxSide / largestSide : 1;
      const targetWidth = Math.max(1, Math.round(loaded.width * scale));
      const targetHeight = Math.max(1, Math.round(loaded.height * scale));

      if (scale === 1 && file.size <= PHOTO_COMPRESS.minSize) {
        loaded.cleanup();
        return file;
      }

      const canvas = document.createElement("canvas");
      canvas.width = targetWidth;
      canvas.height = targetHeight;
      const ctx = canvas.getContext("2d", { alpha: file.type === "image/png" });
      if (!ctx) {
        loaded.cleanup();
        return file;
      }

      ctx.imageSmoothingEnabled = true;
      ctx.imageSmoothingQuality = "high";
      ctx.drawImage(loaded.source, 0, 0, targetWidth, targetHeight);
      loaded.cleanup();

      const outputType = file.type === "image/png" ? "image/png" : "image/jpeg";
      const outputQuality = outputType === "image/jpeg" ? PHOTO_COMPRESS.jpegQuality : undefined;
      const blob = await canvasToBlob(canvas, outputType, outputQuality);
      if (!blob) {
        return file;
      }

      if (scale === 1 && blob.size >= file.size * 0.98) {
        return file;
      }

      const extension = outputType === "image/png" ? "png" : "jpg";
      const outputName = replaceFileExtension(file.name, extension);
      return new File([blob], outputName, { type: outputType, lastModified: Date.now() });
    }

    async function compressPhotoFiles(files) {
      const result = [];
      for (let i = 0; i < files.length; i += 1) {
        const file = files[i];
        try {
          const compressed = await compressPhotoFile(file);
          result.push(compressed);
        } catch {
          result.push(file);
        }
      }
      return result;
    }

    async function onPickPhoto(event) {
      const files = Array.from(event.target.files || []);
      if (!files.length) return;

      const beforeCount = draftPhotos.length;
      const compressedFiles = await compressPhotoFiles(files);
      const loaded = await Promise.all(compressedFiles.map(readFileAsDataURL));
      const newPhotos = loaded.filter(Boolean);
      if (!newPhotos.length) {
        event.target.value = "";
        return;
      }

      // Добавляем новые фото к уже выбранным, ничего не теряя.
      draftPhotos = draftPhotos.concat(newPhotos);

      // Если фото уже были, не переключаемся на новое автоматически:
      // пользователь продолжает видеть ранее выбранное фото.
      if (beforeCount === 0) {
        previewPhotoIndex = 0;
      } else if (previewPhotoIndex >= draftPhotos.length) {
        previewPhotoIndex = 0;
      }

      setPreview(draftPhotos);
      event.target.value = "";
    }

    function setPreview(photoList) {
      const photos = Array.isArray(photoList) ? photoList.filter(Boolean) : [];
      if (!photos.length) {
        refs.preview.src = "";
        refs.preview.style.display = "none";
        refs.previewThumbs.innerHTML = "";
        return;
      }
      if (previewPhotoIndex >= photos.length) previewPhotoIndex = 0;
      refs.preview.src = photos[previewPhotoIndex] || photos[0];
      refs.preview.style.display = "block";
      refs.previewThumbs.innerHTML = photos
        .map((src, idx) => `
          <button type="button" class="thumb-btn ${idx === previewPhotoIndex ? "active" : ""}" data-preview-index="${idx}">
            <img class="thumb" src="${src}" alt="Фото ${idx + 1}" />
          </button>
        `)
        .join("");
      refs.previewThumbs.querySelectorAll("[data-preview-index]").forEach((btn) => {
        btn.addEventListener("click", () => {
          previewPhotoIndex = Number(btn.dataset.previewIndex || "0");
          setPreview(photos);
        });
      });
    }

    function renderTable() {
      refs.body.innerHTML = "";
      refs.mobileItems.innerHTML = "";
      const query = refs.filterText.value.trim();
      if (!items.length) {
        const tr = document.createElement("tr");
        tr.innerHTML = '<td colspan="2" style="color:#5d6a7e">Список пуст. Добавьте первый товар.</td>';
        refs.body.appendChild(tr);
        refs.mobileItems.innerHTML = '<div class="mobile-card" style="color:#5d6a7e">Список пуст. Добавьте первый товар.</div>';
        refs.filtersMeta.textContent = "Записей: 0";
        closeDetail();
        return;
      }

      const filteredItems = getFilteredItems();
      const scopedTotal = items.filter((item) => isArchivedItem(item) === isArchiveView).length;
      refs.filtersMeta.textContent = `${isArchiveView ? "Архив" : "Рабочий список"}: ${filteredItems.length} из ${scopedTotal}`;

      if (!filteredItems.length) {
        const emptyText = refs.filterText.value.trim()
          ? "По вашему ключевому слову ничего не найдено."
          : (isArchiveView ? "В архиве шин пока нет записей." : "В рабочем списке нет записей.");
        const tr = document.createElement("tr");
        tr.innerHTML = `<td colspan="2" style="color:#5d6a7e">${emptyText}</td>`;
        refs.body.appendChild(tr);
        refs.mobileItems.innerHTML = `<div class="mobile-card" style="color:#5d6a7e">${emptyText}</div>`;
        closeDetail();
        return;
      }

      for (const item of filteredItems) {
        const tireLineHtml = formatTireLine(item, query);
        const listQty = getItemListQty(item);
        const tr = document.createElement("tr");
        tr.className = "row-item";
        if (isWheelAssembly(item)) tr.classList.add("is-assembly");
        tr.dataset.id = item.id;
        tr.innerHTML = `
          <td><div class="tire-line">${tireLineHtml}</div></td>
          <td><span class="qty">${highlightMatch(listQty, query)}</span></td>
        `;
        tr.addEventListener("click", () => {
          const isSameRow = selectedId === item.id;
          const isDetailOpen = refs.detail.classList.contains("visible") && activeInlineRow;
          if (isSameRow && isDetailOpen) {
            closeDetail();
            return;
          }
          openDetail(item.id);
          mountDetailUnderRow(tr);
        });
        refs.body.appendChild(tr);

        const card = document.createElement("div");
        card.className = `mobile-card ${isWheelAssembly(item) ? "is-assembly" : ""}`;
        card.innerHTML = `
          <div class="mobile-row">
            <div class="mobile-title tire-line">${tireLineHtml}</div>
            <div><span class="qty">${highlightMatch(listQty, query)}</span></div>
          </div>
        `;
        card.style.cursor = "pointer";
        card.addEventListener("click", () => {
          const isSameRow = selectedId === item.id;
          const isDetailOpen = refs.detail.classList.contains("visible") && activeMobileHost === card;
          if (isSameRow && isDetailOpen) {
            closeDetail();
            return;
          }
          openDetail(item.id);
          mountDetailInsideCard(card);
        });
        refs.mobileItems.appendChild(card);
      }

      revealFirstFilteredDetail(filteredItems, query);

      if (selectedId && !filteredItems.some((x) => x.id === selectedId)) {
        closeDetail();
      }
    }

    function getFilteredItems() {
      const text = normalize(refs.filterText.value);

      return items.filter((item) => {
        if (isArchivedItem(item) !== isArchiveView) return false;

        if (text) {
          const full = normalize([
            item.diameter, item.width, item.profile, item.tireBrand, item.season,
            item.pcd, item.diskBrand, item.diskMaker, item.warehouse, item.tireName, item.diskName,
            resolveTireCondition(item), item.qty, resolveTireCost(item), item.diskCost,
            item.diskDiameter, item.diskWidth, item.holes, item.dia, item.diskQty, item.notes,
            getItemAccessories(item).map(formatAccessory).join(" ")
          ].join(" "));
          if (!full.includes(text)) return false;
        }

        return true;
      });
    }

    function isArchivedItem(item) {
      return item && (item.archived === true || item.status === "archive" || item.status === "Архив");
    }

    function clearFilter() {
      refs.filterText.value = "";
      updateFilterClearButton();
      renderTable();
    }

    function updateFilterClearButton() {
      refs.clearFilterX.classList.toggle("hidden", !refs.filterText.value.trim());
    }

    function normalize(value) {
      return String(value || "").toLowerCase().trim();
    }

    function cssEscape(value) {
      if (window.CSS && typeof window.CSS.escape === "function") {
        return window.CSS.escape(value);
      }
      return value.replace(/["\\]/g, "\\$&");
    }

    function revealFirstFilteredDetail(filteredItems, query) {
      if (!query || !filteredItems.length) return;
      if (selectedId && filteredItems.some((item) => item.id === selectedId)) return;
      const item = filteredItems[0];
      const row = refs.body.querySelector(`[data-id="${cssEscape(String(item.id))}"]`);
      const mobileCard = Array.from(refs.mobileItems.children).find((card) => card.querySelector(".mobile-row"));
      openDetail(item.id);
      if (row && window.matchMedia("(min-width: 701px)").matches) {
        mountDetailUnderRow(row);
      } else if (mobileCard) {
        mountDetailInsideCard(mobileCard);
      }
    }

    function formatTireLine(item, query) {
      const hasTireParams = hasValue(item.diameter);
      if (!hasTireParams) {
        if (isAccessoryOnlyItem(item)) {
          return formatAccessoryLine(item, query);
        }

        const diskSegments = [
          { cls: "tire-radius", value: item.diskDiameter ? `Диск R${item.diskDiameter}` : "Диск" },
          { cls: "tire-width", value: item.diskWidth || "-" },
          { cls: "tire-profile", value: item.pcd || "-" },
          { cls: "tire-brand", value: item.diskMaker || "-" },
          { cls: "tire-season", value: item.warehouse || "-" },
          { cls: "tire-condition", value: item.diskBrand ? `ET ${item.diskBrand}` : "-" },
          { cls: "tire-cost", value: resolveDiskCost(item) ? `${resolveDiskCost(item)}₽` : "-" }
        ];
        return diskSegments
          .map((seg) => `<span class="tire-param ${seg.cls}">${highlightMatch(seg.value, query)}</span>`)
          .join("");
      }

      const tireCondition = resolveTireCondition(item);
      const tireCost = resolveTireCost(item);
      const segments = [
        { cls: "tire-radius", value: item.diameter ? `R${item.diameter}` : "R-" },
        { cls: "tire-width", value: item.width || "-" },
        { cls: "tire-profile", value: item.profile || "-" },
        { cls: "tire-brand", value: item.tireBrand || "-" },
        { cls: "tire-season", value: item.season || "-" },
        { cls: "tire-condition", value: tireCondition || "-" },
        { cls: "tire-cost", value: tireCost ? `${tireCost}₽` : "-" }
      ];

      return segments
        .map((seg) => `<span class="tire-param ${seg.cls}">${highlightMatch(seg.value, query)}</span>`)
        .join("");
    }

    function hasDiskListParams(item) {
      return [
        item.diskDiameter, item.diskWidth, item.holes, item.pcd, item.dia,
        item.diskBrand, item.diskMaker, item.diskQty, item.warehouse, item.diskCost, item.diskName
      ].some((value) => hasValue(value));
    }

    function isAccessoryOnlyItem(item) {
      return !hasValue(item.diameter) && !hasDiskListParams(item) && getItemAccessories(item).length > 0;
    }

    function formatAccessoryLine(item, query) {
      const accessories = getItemAccessories(item);
      const first = accessories[0] || {};
      const title = accessories.length > 1
        ? `${first.type} +${accessories.length - 1}`
        : first.type;
      const segments = [
        { cls: "tire-radius", value: title || "-" },
        { cls: "tire-width", value: first.qty || "-" },
        { cls: "tire-profile", value: first.note || "-" },
        { cls: "tire-brand", value: first.photo ? "Фото" : "-" }
      ];

      return segments
        .map((seg) => `<span class="tire-param ${seg.cls}">${highlightMatch(seg.value, query)}</span>`)
        .join("");
    }

    function getAccessoryDetailChips(accessories) {
      const chips = [];
      accessories.forEach((accessory, index) => {
        const suffix = accessories.length > 1 ? ` ${index + 1}` : "";
        chips.push({ title: `Группа${suffix}`, value: accessory.type || "-", photo: "", index });
        chips.push({ title: `Кол-во${suffix}`, value: accessory.qty || "-", photo: "", index });
        if (hasValue(accessory.note)) {
          chips.push({ title: `Примечание${suffix}`, value: accessory.note, photo: "", index });
        }
      });
      return chips;
    }

    function getItemListQty(item) {
      if (isAccessoryOnlyItem(item)) {
        const accessoryQty = getItemAccessories(item)[0]?.qty;
        if (hasValue(accessoryQty)) return accessoryQty;
      }
      return item.qty || "0";
    }

    function highlightMatch(value, query) {
      const text = String(value || "");
      const q = String(query || "").trim();
      if (!q) return escapeHtml(text);

      const tokens = [...new Set(q.toLowerCase().split(/\s+/).filter(Boolean))];
      if (!tokens.length) return escapeHtml(text);

      let result = "";
      let i = 0;
      const lower = text.toLowerCase();

      while (i < text.length) {
        let best = "";
        for (const t of tokens) {
          if (lower.startsWith(t, i) && t.length > best.length) best = t;
        }

        if (best) {
          const part = text.slice(i, i + best.length);
          result += `<mark class="hit">${escapeHtml(part)}</mark>`;
          i += best.length;
        } else {
          result += escapeHtml(text[i]);
          i += 1;
        }
      }
      return result;
    }

    function openDetail(id) {
      const item = items.find((x) => x.id === id);
      if (!item) return;

      selectedId = id;
      activeDetailQuery = refs.filterText.value.trim();
      const archived = isArchivedItem(item);
      refs.detail.classList.add("visible");
      refs.detailEmpty.style.display = "none";
      const accessoryOnly = isAccessoryOnlyItem(item);
      detailAccessoryOnly = accessoryOnly;
      refs.detail.classList.toggle("assembly", isWheelAssembly(item));
      refs.detail.classList.toggle("accessory", accessoryOnly);
      refs.assemblyBadge.classList.toggle("hidden", !isWheelAssembly(item));
      refs.archiveSelected.textContent = archived ? "Вернуть в работу" : "Переместить в архив";
      refs.archiveSelected.setAttribute("data-full-label", archived ? "Вернуть в работу" : "Переместить в архив");
      refs.archiveSelected.setAttribute("data-short-label", archived ? "Вернуть" : "Архив");
      syncActionButtonLabels();

      const tireCostValue = resolveTireCost(item);
      const diskCostValue = resolveDiskCost(item);
      const tireChips = [
        ["Диаметр", item.diameter],
        ["Ширина", item.width],
        ["Высота профиля", item.profile],
        ["Бренд шины", item.tireBrand],
        ["Сезон", item.season],
        ["Состояние", resolveTireCondition(item) || "-"],
        ["Стоимость шин", tireCostValue ? `${tireCostValue} ₽` : "-"],
        ["Кол-во", item.qty]
      ];

      const diskChips = [
        ["Диаметр диска", item.diskDiameter],
        ["Ширина диска", item.diskWidth],
        ["Разболтовка", item.pcd],
        ["Болты", item.holes],
        ["Диаметр ЦО", item.dia],
        ["ET", item.diskBrand],
        ["Бренд диска", item.diskMaker],
        ["Кол-во", item.diskQty],
        ["Состояние", item.warehouse],
        ["Стоимость дисков", diskCostValue ? `${diskCostValue} ₽` : ""]
      ];

      const tireVisibleChips = hasValue(item.diameter)
        ? tireChips.filter(([, v]) => hasValue(v))
        : [];
      const diskVisibleChips = diskChips.filter(([, v]) => hasValue(v));
      const hasDiskBlock = diskVisibleChips.length > 0 || hasValue(item.diskName);
      const accessories = getItemAccessories(item);
      const accessoryChips = accessoryOnly
        ? getAccessoryDetailChips(accessories)
        : accessories.map((accessory, index) => ({
            title: accessory.type,
            value: formatAccessoryDetails(accessory) || "-",
            photo: accessory.photo || "",
            index
          }));

      refs.detailTireTitle.style.display = tireVisibleChips.length ? "block" : "none";
      refs.detailTireChips.style.display = tireVisibleChips.length ? "grid" : "none";
      refs.detailDiskTitle.style.display = hasDiskBlock ? "block" : "none";
      refs.detailDiskChips.style.display = diskVisibleChips.length ? "grid" : "none";
      refs.detailAccessoriesTitle.textContent = accessoryOnly ? "Параметры аксессуара" : "Дополнительно";
      refs.detailAccessoriesTitle.style.display = accessoryChips.length ? "block" : "none";
      refs.detailAccessoriesChips.style.display = accessoryChips.length ? "grid" : "none";

      refs.detailTireChips.innerHTML = tireVisibleChips
        .map(([k, v]) => `<div class="chip"><div class="k">${escapeHtml(k)}</div><div class="v">${highlightMatch(v || "-", activeDetailQuery)}</div></div>`)
        .join("");

      refs.detailDiskChips.innerHTML = diskVisibleChips
        .map(([k, v]) => `<div class="chip"><div class="k">${escapeHtml(k)}</div><div class="v">${highlightMatch(v || "-", activeDetailQuery)}</div></div>`)
        .join("");

      refs.detailAccessoriesChips.innerHTML = accessoryChips
        .map((chip) => `
          <div class="chip">
            <div class="k">${escapeHtml(chip.title)}</div>
            <div class="v">${highlightMatch(chip.value || "-", activeDetailQuery)}</div>
            ${!accessoryOnly && chip.photo ? `<img class="accessory-photo-preview" src="${escapeHtml(chip.photo)}" alt="Фото" data-detail-accessory-photo="${chip.index}" />` : ""}
          </div>
        `)
        .join("");
      refs.detailAccessoriesChips.querySelectorAll("[data-detail-accessory-photo]").forEach((img) => {
        img.addEventListener("click", () => openPhotoModal(img.getAttribute("src") || ""));
      });

      refs.detailTireName.value = item.tireName || "";
      refs.detailDiskName.value = item.diskName || "";
      refs.detailTireNameRow.style.display = hasValue(item.diameter) && hasValue(item.tireName) ? "grid" : "none";
      refs.detailDiskNameRow.style.display = hasDiskBlock && hasValue(item.diskName) ? "grid" : "none";
      refs.detailNotes.value = item.notes || "";
      const notesMatch = activeDetailQuery && normalize(item.notes).includes(normalize(activeDetailQuery));
      refs.detailNotes.style.display = notesMatch ? "none" : "";
      refs.detailNotesHighlighted.style.display = notesMatch ? "block" : "none";
      refs.detailNotesHighlighted.innerHTML = notesMatch ? highlightMatch(item.notes || "-", activeDetailQuery) : "";
      detailPhotos = getDetailPhotos(item);
      detailPhotoIndex = 0;
      renderDetailPhotos();
    }

    function closeDetail() {
      selectedId = null;
      refs.detail.classList.remove("visible");
      refs.detail.classList.remove("assembly");
      refs.detail.classList.remove("accessory");
      refs.assemblyBadge.classList.add("hidden");
      refs.detailAccessoriesTitle.textContent = "Дополнительно";
      refs.detailAccessoriesTitle.style.display = "none";
      refs.detailAccessoriesChips.style.display = "none";
      refs.detailAccessoriesChips.innerHTML = "";
      refs.detailNotes.style.display = "";
      refs.detailNotesHighlighted.style.display = "none";
      refs.detailNotesHighlighted.innerHTML = "";
      refs.detailEmpty.style.display = "block";
      refs.detailPhotoThumbs.innerHTML = "";
      detailPhotos = [];
      detailPhotoIndex = 0;
      detailAccessoryOnly = false;
      resetInlineDetailPlacement();
      closePhotoModal();
    }

    function isWheelAssembly(item) {
      const hasTireParams = hasValue(item.diameter);
      const hasDiskParams = [item.diskDiameter, item.diskWidth, item.holes, item.pcd, item.dia, item.diskBrand, item.diskName]
        .some((v) => normalize(v) !== "");
      return hasTireParams && hasDiskParams;
    }

    function editSelectedItem() {
      const item = items.find((x) => x.id === selectedId);
      if (!item) return;

      editingId = item.id;
      const ids = [
        "diameter", "width", "profile", "tireBrand", "season", "qty", "price", "tireCost",
        "diskDiameter", "diskWidth", "holes", "pcd", "dia", "diskBrand", "diskMaker", "diskQty", "warehouse", "diskCost",
        "notes"
      ];

      for (const id of ids) {
        document.getElementById(id).value = item[id] || "";
      }
      document.getElementById("price").value = resolveTireCondition(item);
      document.getElementById("tireCost").value = resolveTireCost(item);

      draftPhotos = getItemPhotos(item);
      accessoryDraft = getItemAccessories(item).map((accessory) => ({ ...accessory }));
      renderAccessoryList();
      previewPhotoIndex = 0;
      setPreview(draftPhotos);
      refs.status.textContent = "Режим редактирования выбранного товара";
      refs.exitEdit.classList.remove("d-none");
      setArrivalTitle(true);
      refs.arrivalSpoiler.open = true;
      window.scrollTo({ top: 0, behavior: "smooth" });
    }

    async function toggleSelectedArchive() {
      if (!selectedId) return;
      const item = items.find((x) => x.id === selectedId);
      if (!item) return;

      const archived = isArchivedItem(item);
      const confirmText = archived ? "Вернуть выбранную запись в рабочий список?" : "Переместить выбранную запись в архив?";
      if (!window.confirm(confirmText)) return;

      const updatedItem = { ...item };
      if (archived) {
        updatedItem.archived = false;
        delete updatedItem.archivedAt;
        if (updatedItem.status === "archive" || updatedItem.status === "Архив") {
          delete updatedItem.status;
        }
      } else {
        updatedItem.archived = true;
        updatedItem.archivedAt = new Date().toISOString();
      }

      try {
        const response = await fetch(`${API_URL}?action=save`, {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ item: updatedItem })
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) {
          throw new Error(payload.message || "Ошибка сохранения");
        }
        await loadItemsFromServer();
        refreshAutocomplete();
        renderTable();
        closeDetail();
        setStatus(archived ? "Запись возвращена в работу" : "Запись перемещена в архив");
      } catch (error) {
        setStatus(error.message || "Ошибка сохранения", "error");
      }
    }

    async function deleteSelectedItem() {
      if (!selectedId) return;
      const shouldDelete = window.confirm("Вы уверены, что хотите удалить выбранную запись?");
      if (!shouldDelete) return;
      try {
        const response = await fetch(`${API_URL}?action=delete`, {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ id: selectedId })
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) {
          throw new Error(payload.message || "Ошибка удаления");
        }
        await loadItemsFromServer();
        refreshAutocomplete();
        renderTable();
        closeDetail();
        setStatus("Товар удален");
      } catch (error) {
        setStatus(error.message || "Ошибка удаления", "error");
      }
    }

    function openPhotoModal(src = "") {
      if (src && typeof src !== "string") {
        src = "";
      }
      src = src || detailPhotos[detailPhotoIndex] || refs.detailPhoto.src || "";
      if (!src) return;
      refs.photoModalImg.src = src;
      refs.photoModal.classList.add("visible");
      refs.photoModal.setAttribute("aria-hidden", "false");
    }

    function closePhotoModal() {
      refs.photoModal.classList.remove("visible");
      refs.photoModal.setAttribute("aria-hidden", "true");
      refs.photoModalImg.src = "";
    }

    function readFileAsDataURL(file) {
      return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result || ""));
        reader.onerror = () => resolve("");
        reader.readAsDataURL(file);
      });
    }

    function setArrivalTitle(isEditMode) {
      refs.arrivalTitle.textContent = isEditMode ? "РЕДАКТИРОВАТЬ" : "ПРИХОД";
    }

    function hasValue(value) {
      return String(value ?? "").trim() !== "";
    }

    function isConditionValue(value) {
      const v = String(value || "").trim();
      return v === "Новые" || v === "Б/у" || v === "Не указано";
    }

    function isNumericLike(value) {
      const v = String(value || "").trim().replace(",", ".");
      if (!v) return false;
      return Number.isFinite(Number(v));
    }

    function resolveTireCondition(item) {
      return isConditionValue(item.price) ? String(item.price || "").trim() : "";
    }

    function resolveTireCost(item) {
      if (hasValue(item.tireCost)) return String(item.tireCost).trim();
      if (hasValue(item.price) && !isConditionValue(item.price) && isNumericLike(item.price)) {
        return String(item.price).trim();
      }
      return "";
    }

    function resolveDiskCost(item) {
      if (hasValue(item.diskCost)) return String(item.diskCost).trim();
      return "";
    }

    function resetInlineDetailPlacement() {
      if (activeInlineRow && activeInlineRow.parentNode) {
        activeInlineRow.parentNode.removeChild(activeInlineRow);
      }
      activeInlineRow = null;

      if (activeMobileHost) {
        const inline = activeMobileHost.querySelector(".mobile-inline-detail");
        if (inline && inline.parentNode) inline.parentNode.removeChild(inline);
      }
      activeMobileHost = null;

      refs.detailHome.appendChild(refs.detail);
    }

    function mountDetailUnderRow(row) {
      resetInlineDetailPlacement();
      const tr = document.createElement("tr");
      tr.className = "inline-detail-row";
      const td = document.createElement("td");
      td.colSpan = 2;
      td.className = "inline-detail-cell";
      td.appendChild(refs.detail);
      tr.appendChild(td);
      row.insertAdjacentElement("afterend", tr);
      activeInlineRow = tr;
    }

    function mountDetailInsideCard(card) {
      resetInlineDetailPlacement();
      const host = document.createElement("div");
      host.className = "mobile-inline-detail";
      host.appendChild(refs.detail);
      card.appendChild(host);
      activeMobileHost = card;
    }

    function renderDetailPhotos() {
      const photos = detailPhotos.filter(Boolean);
      if (!photos.length) {
        refs.detailPhoto.src = "";
        refs.detailPhotoBtn.classList.add("hidden");
        refs.detailPhotoThumbs.innerHTML = "";
        return;
      }
      if (detailPhotoIndex >= photos.length) detailPhotoIndex = 0;
      refs.detailPhoto.src = photos[detailPhotoIndex];
      refs.detailPhotoBtn.classList.remove("hidden");
      if (detailAccessoryOnly) {
        refs.detailPhotoThumbs.innerHTML = "";
        return;
      }
      refs.detailPhotoThumbs.innerHTML = photos
        .map((src, idx) => `
          <button type="button" class="thumb-btn ${idx === detailPhotoIndex ? "active" : ""}" data-detail-index="${idx}">
            <img class="thumb" src="${src}" alt="Фото ${idx + 1}" />
          </button>
        `)
        .join("");
      refs.detailPhotoThumbs.querySelectorAll("[data-detail-index]").forEach((btn) => {
        btn.addEventListener("click", () => {
          detailPhotoIndex = Number(btn.dataset.detailIndex || "0");
          renderDetailPhotos();
        });
      });
    }

    function getItemPhotos(item) {
      if (Array.isArray(item.photos) && item.photos.length) {
        return item.photos.filter(Boolean);
      }
      if (item.photo) return [item.photo];
      return [];
    }

    function getDetailPhotos(item) {
      const photos = getItemPhotos(item);
      getItemAccessories(item).forEach((accessory) => {
        if (hasValue(accessory.photo)) photos.push(accessory.photo);
      });
      return [...new Set(photos.filter(Boolean))];
    }

    function refreshAutocomplete() {
      updateDataList("widthOptions", "width");
      updateDataList("profileOptions", "profile");
      updateDataList("tireBrandOptions", "tireBrand");
      updateDataList("pcdOptions", "pcd");
      updateDataList("diaOptions", "dia");
      updateDataList("diskBrandOptions", "diskBrand");
      updateDataList("diskMakerOptions", "diskMaker");
    }

    function updateDataList(listId, key) {
      const list = document.getElementById(listId);
      if (!list) return;

      const values = [...new Set(
        items
          .map((item) => String(item[key] || "").trim())
          .filter(Boolean)
      )].sort((a, b) => a.localeCompare(b, "ru"));

      list.innerHTML = values
        .map((value) => `<option value="${escapeHtml(value)}"></option>`)
        .join("");
    }

    function escapeHtml(value) {
      return String(value || "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
    }
  </script>
</body>
</html>


