<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_GET['order_id']) || !isset($_GET['section'])) {
    header("Location: index.php");
    exit;
}

$order_id = $_GET['order_id'];
$section = $_GET['section'];
track_user_activity('edit_order');
$order = getOrderById($order_id);

if (!$order) {
    header("Location: index.php?section=$section");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    $data = [
        'client_name' => $_POST['client_name'],
        'license_plate' => $_POST['license_plate'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'color' => $_POST['color'] ?? '',
        'price' => $_POST['price'] ?? '',
        'notes' => $_POST['notes'] ?? '',
        'queue_date' => $_POST['queue_date'] ?? $order['queue_date'],
        'existing_photos' => $_POST['existing_photos'] ?? [],
        'location' => $_POST['location'] ?? ''
    ];

    if (updateOrder($order_id, $data, $_FILES)) {
        log_change("Отредактировал заказ #{$order_id}");
        $section_safe = urlencode($section);
        $order_safe = urlencode((string)$order_id);
        header("Location: index.php?section={$section_safe}&open_details={$order_safe}");
        exit;
    } else {
        $error = "Ошибка при сохранении изменений.";
    }
}

$photos = !empty($order['photos']) ? explode(',', $order['photos']) : [];
$photos = array_values(array_filter(array_map('resolvePhotoPath', $photos), function ($photo) {
    return $photo !== '' && file_exists($photo);
}));
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Редактировать заказ</title>
    <link rel="icon" type="image/jpeg" href="Logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f8;
            background-image: url('https://www.diskzakaz.ru/1/fn.png');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: cover;
            color: #1f2a37;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 14px;
            padding: 10px;
            margin-top: 50px;
        }
        .container {
            padding: 10px;
            max-width: 100%;
        }
        .metallic-card {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.96), rgba(245, 247, 250, 0.96));
            border: 1px solid rgba(15, 23, 42, 0.18);
            border-radius: 12px;
            margin-bottom: 10px;
            padding: 8px;
            box-shadow: 0 6px 22px rgba(15, 23, 42, 0.16), inset 0 1px 1px rgba(255, 255, 255, 0.2);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            backdrop-filter: blur(10px);
        }
        .metallic-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.5);
        }
        .form-control {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px;
            font-size: 12px;
            color: #1f2a37;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08);
        }
        .form-control:focus {
            border-color: #00b4d8;
            box-shadow: 0 0 0 3px rgba(0, 180, 216, 0.25);
            background: #ffffff;
            color: #1f2a37;
        }
        .form-control::placeholder {
            color: #6b7280;
        }
        .btn {
            padding: 8px 12px;
            font-size: 12px;
            border-radius: 8px;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.5);
        }
        .btn-primary {
            background: linear-gradient(145deg, #00b4d8, #0077b6);
            color: #ffffff;
        }
        .btn-primary:hover {
            background: linear-gradient(145deg, #0077b6, #005f8c);
        }
        .btn-secondary {
            background: linear-gradient(145deg, #6c757d, #495057);
            color: #ffffff;
        }
        .btn-secondary:hover {
            background: linear-gradient(145deg, #495057, #343a40);
        }
        .btn-warning {
            background: linear-gradient(145deg, #ffc107, #e0a800);
            color: #212529;
        }
        .btn-warning:hover {
            background: linear-gradient(145deg, #e0a800, #c69500);
        }
        .btn-danger {
            background: linear-gradient(145deg, #dc3545, #c82333);
            color: #ffffff;
        }
        .btn-danger:hover {
            background: linear-gradient(145deg, #c82333, #bd2130);
        }
        .alert {
            background: rgba(231, 76, 60, 0.8);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            backdrop-filter: blur(5px);
        }
        .photo-preview {
            height: 80px;
            width: 80px;
            object-fit: cover;
            border-radius: 8px;
            margin: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .photo-preview:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        .photos-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 10px 0;
        }
        .photo-item {
            position: relative;
        }
        .photo-checkbox {
            position: absolute;
            top: 5px;
            left: 5px;
            z-index: 2;
        }
        .form-label {
            color: #1f2a37;
            font-weight: 600;
        }
        h1 {
            color: #ffffff;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
            font-weight: 700;
            margin-bottom: 20px;
            text-align: center;
        }
        @media (max-width: 768px) {
            .container { padding: 10px; }
            .btn { padding: 8px 15px; font-size: 14px; }
            .form-control { padding: 8px 12px; font-size: 14px; }
            .photo-preview { height: 80px; width: 80px; }
            h1 { font-size: 24px; }
        }
        @media (max-width: 576px) {
            .photo-preview { height: 60px; width: 60px; }
            .btn { padding: 6px 12px; font-size: 12px; }
            .form-control { padding: 6px 10px; font-size: 12px; }
            h1 { font-size: 20px; }
        }
    </style>
    <link rel="stylesheet" href="views-global.css">
</head>
<body>
    <div class="container">
        <h1>Редактировать заказ #<?php echo $order_id; ?></h1>
        
        <?php if (isset($error)) : ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="metallic-card">
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="client_name" class="form-label">ФИО клиента *</label>
                        <input type="text" class="form-control" id="client_name" name="client_name" value="<?php echo htmlspecialchars($order['client_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="license_plate" class="form-label">Госномер</label>
                            <input type="text" class="form-control" id="license_plate" name="license_plate" value="<?php echo htmlspecialchars($order['license_plate'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Телефон</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($order['phone'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="color" class="form-label">Цвет</label>
                            <input type="text" class="form-control" id="color" name="color" value="<?php echo htmlspecialchars($order['color'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="price" class="form-label">Стоимость</label>
                            <input type="number" class="form-control" id="price" name="price" step="0.01" value="<?php echo htmlspecialchars($order['price'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="location" class="form-label">Локация</label>
                        <select class="form-control" id="location" name="location">
                            <option value="Юзовская" <?php echo (($order['location'] ?? '') === 'Юзовская' ? 'selected' : ''); ?>>Юзовская</option>
                            <option value="Каширская" <?php echo (($order['location'] ?? '') === 'Каширская' ? 'selected' : ''); ?>>Каширская</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="queue_date" class="form-label">Дата очереди</label>
                        <div class="input-group">
                            <input type="date" class="form-control" id="queue_date" name="queue_date" value="<?php echo $order['queue_date'] ? date('Y-m-d', strtotime($order['queue_date'])) : ''; ?>">
                            <button type="button" class="btn btn-primary" onclick="openCalendar()"><i class="fas fa-calendar-alt"></i> Изменить</button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Примечание</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($order['notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Текущие фотографии</label>
                        <div class="photos-container">
                            <?php foreach ($photos as $photo) : ?>
                                <?php if (file_exists($photo)) : ?>
                                    <div class="photo-item">
                                        <input type="checkbox" class="photo-checkbox" name="existing_photos[]" value="<?php echo htmlspecialchars($photo); ?>" checked>
                                        <img src="<?php echo htmlspecialchars($photo) . '?v=' . (file_exists($photo) ? filemtime($photo) : 0); ?>" class="photo-preview" data-photos='<?php echo htmlspecialchars(json_encode(array_map(function($p){ return $p . '?v=' . (file_exists($p) ? filemtime($p) : 0); }, $photos)), ENT_QUOTES, 'UTF-8'); ?>' onclick="viewPhoto(this, <?php echo array_search($photo, $photos); ?>)">
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted">Снимите галочку, чтобы удалить фото</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Добавить новые фотографии</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="editOrderCameraBtn">С камеры</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="editOrderGalleryBtn">Из галереи</button>
                        </div>
                        <input type="file" class="form-control mt-2" id="editOrderCameraInput" name="new_photos[]" multiple accept="image/*" capture="environment" hidden>
                        <input type="file" class="form-control mt-2" id="editOrderGalleryInput" name="new_photos[]" multiple accept="image/*" hidden>
                    </div>
                    
                    <div class="d-flex gap-3">
                        <button type="submit" name="update_order" class="btn btn-warning"><i class="fas fa-save"></i> Сохранить</button>
                        <a href="index.php?section=<?php echo $section; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Назад</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="background: rgba(255, 255, 255, 0.96); color: #1f2a37; border: 1px solid rgba(15, 23, 42, 0.12);">
                <div class="modal-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                    <h5 id="photoModalLabel" class="modal-title" style="color: #1f2a37;">Просмотр фото</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center" style="padding: 0;">
                    <div id="photoCarousel" class="carousel slide" data-bs-ride="carousel">
                        <div class="carousel-inner">
                            <!-- Слайды будут добавлены здесь через JavaScript -->
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#photoCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#photoCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
    <script>
        let photoModalInstance;
        document.addEventListener('DOMContentLoaded', function() {
            var cameraBtn = document.getElementById('editOrderCameraBtn');
            var galleryBtn = document.getElementById('editOrderGalleryBtn');
            var cameraInput = document.getElementById('editOrderCameraInput');
            var galleryInput = document.getElementById('editOrderGalleryInput');
            if (cameraBtn && cameraInput) {
                cameraBtn.addEventListener('click', function() {
                    if (typeof cameraInput.showPicker === 'function') {
                        cameraInput.showPicker();
                    } else {
                        cameraInput.click();
                    }
                });
            }
            if (galleryBtn && galleryInput) {
                galleryBtn.addEventListener('click', function() {
                    if (typeof galleryInput.showPicker === 'function') {
                        galleryInput.showPicker();
                    } else {
                        galleryInput.click();
                    }
                });
            }
        });
        
        function openCalendar() {
            const input = document.getElementById('queue_date');
            if (!input) return;

            if (window.flatpickr) {
                if (!input._flatpickr) {
                    flatpickr(input, {
                        dateFormat: "Y-m-d",
                        locale: "ru",
                        allowInput: true,
                        onChange: function(selectedDates, dateStr) {
                            input.value = dateStr;
                        }
                    });
                }
                input._flatpickr.open();
                return;
            }

            if (typeof input.showPicker === 'function') {
                input.showPicker();
            } else {
                input.focus();
            }
        }

        function viewPhoto(element, index) {
            const photosJson = element.getAttribute('data-photos');
            const photos = JSON.parse(photosJson);
            const carouselInner = document.querySelector('#photoCarousel .carousel-inner');
            carouselInner.innerHTML = ''; // Очищаем карусель

            photos.forEach((photo, i) => {
                const item = document.createElement('div');
                item.className = 'carousel-item' + (i === index ? ' active' : '');
                
                const img = document.createElement('img');
                img.src = photo;
                img.className = 'd-block w-100';
                img.style.maxHeight = '80vh';
                img.style.objectFit = 'contain';
                
                item.appendChild(img);
                carouselInner.appendChild(item);
            });

            if (!photoModalInstance) {
                photoModalInstance = new bootstrap.Modal(document.getElementById('photoModal'));
            }
            
            // Убедимся, что карусель инициализирована и переключим на нужный слайд
            const carousel = new bootstrap.Carousel(document.getElementById('photoCarousel'));
            carousel.to(index);

            photoModalInstance.show();
        }

    </script>
</body>
</html>



