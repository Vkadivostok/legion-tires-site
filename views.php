<?php

// Функции для рендеринга header и footer
function renderHeader($section = '')
{
    global $menu_items;
    
    $current_page_script = basename($_SERVER['PHP_SELF']);
    if ($current_page_script !== 'index.php') {
        $section = str_replace('.php', '', $current_page_script);
    } else {
        $section = $_GET['section'] ?? 'new_order';
    }
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Система управления заказами</title>
    <link rel="icon" type="image/jpeg" href="Logo.png">
    <link rel="apple-touch-icon" href="Logo.png">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#000000"/>
    <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="vendor/fontawesome/css/all.min.css">
    <link href="vendor/flatpickr/flatpickr.min.css" rel="stylesheet">
    <!-- General Styles -->
    <style>
        body {
            background-color: #f4f6f8;
            background-image: url('./1/fn.png');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: cover;
            color: #1f2a37;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 16px;
            padding: 10px;
            margin-top: 50px;
            margin-bottom: 80px; /* Space for bottom nav */
        }
        .container {
            padding: 10px;
            max-width: 100%;
            width: 100%;
        }
        .metallic-card {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.96), rgba(245, 247, 250, 0.96));
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            margin-bottom: 15px;
            padding: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4), inset 0 1px 1px rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
        }
        .btn {
            padding: 12px 16px;
            font-size: 16px;
            border-radius: 8px;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            min-height: 48px;
            transition: all 0.2s ease;
        }
        .btn-primary {
            background: linear-gradient(145deg, #00b4d8, #0077b6);
            color: #ffffff;
        }
        .bottom-nav {
            display: flex;
            align-items: center;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 44px;
            background: linear-gradient(145deg, #ffffff, #eef2f5);
            z-index: 1000;
            padding: 3px 0;
            box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.4);
            overflow-x: auto;
            overflow-y: hidden;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .bottom-nav {
            scroll-behavior: smooth;
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
            padding: 4px 8px;
            border-radius: 8px;
            min-width: 70px;
            min-height: 34px;
            flex: 0 0 auto;
        }
        .bottom-nav .nav-icon {
            font-size: 14px;
            line-height: 1;
            margin-bottom: 2px;
        }
        .bottom-nav .nav-item.active {
            background: linear-gradient(145deg, #00b4d8, #0077b6);
            color: #ffffff;
        }
        body#new_order .metallic-card {
            border: 2px solid #b8c3d1;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2), inset 0 0 0 1px rgba(255,255,255,0.7);
        }
        body#new_order .form-control,
        body#new_order .form-select {
            background-color: #f8fafc !important;
            border: 2px solid #a9b6c8;
            color: #9b2cff !important;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.9);
            color-scheme: light;
        }
        body#new_order .form-control::placeholder {
            color: #c06bff;
        }
        body#new_order .form-control:focus,
        body#new_order .form-select:focus {
            background-color: #ffffff !important;
            color: #9b2cff !important;
            border-color: #9b2cff;
            box-shadow: 0 0 0 3px rgba(155, 44, 255, 0.25);
        }
        body#new_order .btn {
            border: 2px solid rgba(17, 24, 39, 0.25);
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.18);
        }
        body#new_order .input-group,
        body#new_order .row,
        body#new_order .collapse-content {
            border: 1px solid rgba(15, 23, 42, 0.12);
            border-radius: 6px;
        }
        body#new_order .collapse-content {
            border-color: rgba(15, 23, 42, 0.18);
        }
        body#new_order .photos-container {
            border: 1px dashed rgba(15, 23, 42, 0.25);
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.6);
        }
        
         .active-glow {
            box-shadow: 0 0 15px 5px rgba(0, 180, 216, 0.7);
            transition: box-shadow 0.3s ease-in-out;
        }
        @media (min-width: 993px) {
            .bottom-nav {
                display: none;
            }
        }
    </style>
    <link rel="stylesheet" href="views-global.css">
</head>
<body id="<?= htmlspecialchars($section) ?>" class="has-top-nav">
    <main class="container" id="mainContent">
    <?php
}

function renderFooter()
{
    global $menu_items;
    ?>
</main>

<div class="bottom-nav d-lg-none">
    <?php
    $request_uri = $_SERVER['REQUEST_URI'];

    foreach ($menu_items as $key => $item) {
        if (isset($item['admin_only']) && $item['admin_only'] && (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin')) {
            continue;
        }
        
        // Check if the item's URL is present in the request URI
        $is_active = (strpos($request_uri, $item['url']) !== false);

        // A special case for the default page when no section is specified
        if (empty($_GET['section']) && basename($request_uri) === 'index.php' && $key === 'new_order') {
            $is_active = true;
        }
        
        $item_class = 'nav-item ' . ($is_active ? 'active' : '');
        if (isset($item['class'])) {
            $item_class .= ' ' . $item['class'];
        }
        echo '<a href="' . htmlspecialchars($item['url']) . '" class="' . $item_class . '">';
        if (!empty($item['icon'])) {
            echo '<i class="fas ' . htmlspecialchars($item['icon']) . ' nav-icon"></i>';
        }
        echo '<span>' . htmlspecialchars($item['label']) . '</span>';
        echo '</a>';
    }
    ?>
    <a href="?logout" class="nav-item">
        <span>Выйти</span>
    </a>
</div>

<script src="vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="vendor/flatpickr/flatpickr.min.js"></script>
<script>
    function logOrderCardOpen(orderId) {
        if (!orderId) return;
        fetch('log_navigation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: `Открытие карточки заказа #${orderId}`
            })
        }).catch(function () {});
    }
    function toggleDetails(element, orderId) {
        // The original function tried to find 'details-' + orderId but order details use 'orderDetails' + orderId
        // Using Bootstrap's collapse functionality instead
        var orderDetails = document.getElementById('orderDetails' + orderId);
        if (orderDetails) {
            var isOpening = !orderDetails.classList.contains('show');
            var bsCollapse = new bootstrap.Collapse(orderDetails, {
                toggle: true
            });
            if (isOpening) {
                logOrderCardOpen(orderId);
            }
        }
    }
    // Function to toggle the navigation menu
    function toggleNav() {
        const navMenu = document.getElementById('navMenu');
        if (navMenu) {
            navMenu.classList.toggle('open');
        }
    }

    // Close the navigation menu when clicking outside of it
    document.addEventListener('click', function(event) {
        const navMenu = document.getElementById('navMenu');
        const navToggle = document.querySelector('.nav-toggle');
        
        if (navMenu && navToggle) {
            const isClickInsideNav = navMenu.contains(event.target);
            const isClickOnToggle = navToggle.contains(event.target);
            
            if (!isClickInsideNav && !isClickOnToggle && navMenu.classList.contains('open')) {
                navMenu.classList.remove('open');
            }
        }
    });

    // Also close the navigation menu when clicking on a menu item
    document.addEventListener('click', function(event) {
        const navMenu = document.getElementById('navMenu');
        if (navMenu && event.target.closest('.nav-menu a')) {
            navMenu.classList.remove('open');
        }
    });

    function centerActiveNavItem() {
        const navContainer = document.querySelector('.bottom-nav');
        if (!navContainer) return;

        const activeItem = navContainer.querySelector('.nav-item.active');
        if (!activeItem) return;

        const containerRect = navContainer.getBoundingClientRect();
        const itemRect = activeItem.getBoundingClientRect();

        // Calculate the ideal scroll position to center the item
        const scrollLeft = activeItem.offsetLeft - (containerRect.width / 2) + (itemRect.width / 2);

        navContainer.scrollTo({
            left: scrollLeft,
            behavior: 'auto'
        });
        
        // Optional: Add a class for a glowing effect and remove from others
        const allNavItems = navContainer.querySelectorAll('.nav-item');
        allNavItems.forEach(item => {
            item.classList.remove('active-glow');
        });
        activeItem.classList.add('active-glow');
    }

    // Run on page load and after DOM content is loaded
    document.addEventListener('DOMContentLoaded', centerActiveNavItem);
    window.addEventListener('load', centerActiveNavItem); // Fallback for images or other resources

    // Fast mobile navigation for top/bottom bars.
    // Side menu stays unchanged.
    document.addEventListener('click', function(event) {
        if (window.innerWidth > 992) return;
        const link = event.target.closest('.top-nav a, .bottom-nav a');
        if (!link) return;
        if (link.target && link.target !== '_self') return;
        if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const href = link.getAttribute('href');
        if (!href) return;
        event.preventDefault();
        window.location.assign(href);
    }, true);
    
    
    // Function to edit order - redirects to edit_order.php with order ID and section
    function editOrder(orderId, section) {
        window.location.href = 'edit_order.php?order_id=' + orderId + '&section=' + section;
    }
    
    // Function to update button text based on input content
    function updateButtonText(orderId) {
        var noteTextarea = document.querySelector('#noteForm' + orderId + ' textarea[name="note"]');
        var photosInput = document.querySelector('#noteForm' + orderId + ' input[name="additional_photos[]"]');
        var submitButton = document.getElementById('submitButton' + orderId);
        var archiveButton = document.getElementById('archiveButton' + orderId);
        
        if (noteTextarea && submitButton) {
            var hasContent = noteTextarea.value.trim() !== '';
            var hasPhotos = photosInput && photosInput.files.length > 0;
            
            if (hasContent || hasPhotos) {
                submitButton.textContent = 'Добавить примечание и фото';
            } else {
                submitButton.textContent = 'Добавить примечание';
            }
            
            // Enable/disable button based on content
            submitButton.disabled = !(hasContent || hasPhotos);
        }
        
        if (archiveButton) {
            // Archive button should always be enabled if it exists
            archiveButton.disabled = false;
        }
    }

    const PHOTO_COMPRESS = {
        maxSide: 720,
        jpegQuality: 0.45,
        minSize: 250 * 1024
    };
    const PHOTO_COMPRESS_SKIP_TYPES = new Set(['image/gif', 'image/svg+xml']);
    const MAX_ORDER_PHOTOS = 10;

    function getNewOrderUploadUi() {
        return {
            box: document.getElementById('newOrderUploadStatus'),
            text: document.getElementById('newOrderUploadText'),
            bar: document.getElementById('newOrderUploadBar')
        };
    }

    function updateNewOrderUploadUi(visible, text, percent, isError) {
        var ui = getNewOrderUploadUi();
        if (!ui.box || !ui.text || !ui.bar) {
            return;
        }
        ui.box.style.display = visible ? 'block' : 'none';
        ui.text.textContent = text || '';
        var value = Math.max(0, Math.min(100, Number(percent) || 0));
        ui.bar.style.width = value + '%';
        ui.bar.setAttribute('aria-valuenow', String(value));
        ui.bar.classList.toggle('bg-danger', !!isError);
        ui.bar.classList.toggle('progress-bar-animated', !isError && value < 100);
    }

    function replaceFileExtension(name, ext) {
        var safeName = String(name || 'photo').trim() || 'photo';
        var idx = safeName.lastIndexOf('.');
        var base = idx > 0 ? safeName.slice(0, idx) : safeName;
        return base + '.' + ext;
    }

    function shouldSkipCompression(file) {
        if (!file || !/^image\//.test(file.type)) {
            return true;
        }
        return PHOTO_COMPRESS_SKIP_TYPES.has(file.type);
    }

    function canvasToBlob(canvas, type, quality) {
        return new Promise(function(resolve) {
            if (!canvas || typeof canvas.toBlob !== 'function') {
                resolve(null);
                return;
            }
            canvas.toBlob(function(blob) { resolve(blob); }, type, quality);
        });
    }

    async function loadImageSource(file) {
        if (!file) {
            return null;
        }
        if (window.createImageBitmap) {
            try {
                var bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
                return {
                    source: bitmap,
                    width: bitmap.width,
                    height: bitmap.height,
                    cleanup: function() { if (bitmap && bitmap.close) bitmap.close(); }
                };
            } catch (error) {
                // fallback to Image
            }
        }
        return new Promise(function(resolve) {
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function() {
                resolve({
                    source: img,
                    width: img.naturalWidth || img.width,
                    height: img.naturalHeight || img.height,
                    cleanup: function() { URL.revokeObjectURL(url); }
                });
            };
            img.onerror = function() {
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
        var loaded = await loadImageSource(file);
        if (!loaded || !loaded.width || !loaded.height) {
            return file;
        }

        var maxSide = PHOTO_COMPRESS.maxSide;
        var largestSide = Math.max(loaded.width, loaded.height);
        var scale = largestSide > maxSide ? maxSide / largestSide : 1;
        var targetWidth = Math.max(1, Math.round(loaded.width * scale));
        var targetHeight = Math.max(1, Math.round(loaded.height * scale));

        if (scale === 1 && file.size <= PHOTO_COMPRESS.minSize) {
            loaded.cleanup();
            return file;
        }

        var canvas = document.createElement('canvas');
        canvas.width = targetWidth;
        canvas.height = targetHeight;
        var ctx = canvas.getContext('2d', { alpha: file.type === 'image/png' });
        if (!ctx) {
            loaded.cleanup();
            return file;
        }
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(loaded.source, 0, 0, targetWidth, targetHeight);
        loaded.cleanup();

        var outputType = file.type === 'image/png' ? 'image/png' : 'image/jpeg';
        var outputQuality = outputType === 'image/jpeg' ? PHOTO_COMPRESS.jpegQuality : undefined;
        var blob = await canvasToBlob(canvas, outputType, outputQuality);
        if (!blob) {
            return file;
        }

        if (scale === 1 && blob.size >= file.size * 0.98) {
            return file;
        }

        var extension = outputType === 'image/png' ? 'png' : 'jpg';
        var outputName = replaceFileExtension(file.name, extension);
        return new File([blob], outputName, { type: outputType, lastModified: Date.now() });
    }

    async function compressPhotoFiles(files, progressCallback) {
        var output = [];
        var total = files.length;
        for (var i = 0; i < total; i++) {
            var file = files[i];
            try {
                output.push(await compressPhotoFile(file));
            } catch (error) {
                output.push(file);
            }
            if (typeof progressCallback === 'function') {
                progressCallback(i + 1, total);
            }
        }
        return output;
    }

    var photoInputState = new WeakMap();

    function getPhotoInputState(input) {
        var state = photoInputState.get(input);
        if (!state) {
            state = {
                files: [],
                chain: Promise.resolve()
            };
            photoInputState.set(input, state);
        }
        return state;
    }

    async function enqueueInputFiles(input, selectedFiles) {
        if (!input || !Array.isArray(selectedFiles) || selectedFiles.length === 0) {
            return;
        }
        if (typeof DataTransfer === 'undefined') {
            return;
        }

        var state = getPhotoInputState(input);
        state.chain = state.chain.then(async function() {
            var isNewOrderInput = input.id === 'newOrderCameraInput' || input.id === 'newOrderGalleryInput';
            if (isNewOrderInput) {
                updateNewOrderUploadUi(true, 'Подготовка фото 0/' + selectedFiles.length, 5, false);
            }
            var compressed = await compressPhotoFiles(selectedFiles, function(done, total) {
                if (!isNewOrderInput) {
                    return;
                }
                var percent = Math.round((done / total) * 55);
                updateNewOrderUploadUi(true, 'Подготовка фото ' + done + '/' + total, percent, false);
            });
            var nextFiles = state.files.concat(compressed);
            if (isNewOrderInput && nextFiles.length > MAX_ORDER_PHOTOS) {
                nextFiles = nextFiles.slice(0, MAX_ORDER_PHOTOS);
                alert('Можно загрузить максимум ' + MAX_ORDER_PHOTOS + ' фото в заказ.');
            }
            state.files = nextFiles;

            var dt = new DataTransfer();
            state.files.forEach(function(file) { dt.items.add(file); });
            input.files = dt.files;
            if (isNewOrderInput) {
                updateNewOrderUploadUi(true, 'Фото подготовлены: ' + state.files.length + ' шт.', 60, false);
            }
        });

        return state.chain;
    }

    function clearQueuedInputFiles(input) {
        if (!input) return;
        var state = getPhotoInputState(input);
        state.files = [];
        input.value = '';
        if (input.id === 'newOrderCameraInput' || input.id === 'newOrderGalleryInput') {
            updateNewOrderUploadUi(false, '', 0, false);
        }
    }

    function waitForQueuedInput(input) {
        var state = photoInputState.get(input);
        if (!state || !state.chain) {
            return Promise.resolve();
        }
        return state.chain;
    }

    function setupPhotoCompression() {
        var newOrderInputs = [
            document.getElementById('newOrderCameraInput'),
            document.getElementById('newOrderGalleryInput'),
            document.getElementById('photos')
        ].filter(Boolean);
        newOrderInputs.forEach(function(input) {
            input.addEventListener('change', function() {
                var selectedFiles = Array.from(input.files || []);
                enqueueInputFiles(input, selectedFiles);
            });
        });

        var newOrderForm = document.getElementById('newOrderForm');
        if (newOrderForm) {
            var uploadHintTimer = null;

            function setSubmitButtonsDisabled(disabled) {
                var buttons = newOrderForm.querySelectorAll('button[type="submit"], button[id^="newOrder"]');
                buttons.forEach(function(btn) {
                    btn.disabled = !!disabled;
                });
            }

            function unlockNewOrderForm() {
                newOrderForm.dataset.compressionSubmitting = '0';
                setSubmitButtonsDisabled(false);
            }

            function submitNewOrderWithProgress() {
                var formData = new FormData(newOrderForm);
                var xhr = new XMLHttpRequest();
                var submitUrl = newOrderForm.getAttribute('action') || window.location.href;
                xhr.open('POST', submitUrl, true);
                xhr.timeout = 180000;

                xhr.upload.onprogress = function(event) {
                    if (!event.lengthComputable) {
                        updateNewOrderUploadUi(true, 'Загрузка фото на сервер...', 80, false);
                        return;
                    }
                    var percent = 60 + Math.round((event.loaded / event.total) * 35);
                    updateNewOrderUploadUi(true, 'Загрузка фото на сервер...', percent, false);
                };

                xhr.onloadstart = function() {
                    updateNewOrderUploadUi(true, 'Загрузка фото на сервер...', 65, false);
                    clearTimeout(uploadHintTimer);
                    uploadHintTimer = setTimeout(function() {
                        updateNewOrderUploadUi(true, 'Медленная сеть: загрузка идет дольше обычного...', 75, false);
                    }, 15000);
                };

                xhr.onload = function() {
                    clearTimeout(uploadHintTimer);
                    if (xhr.status >= 200 && xhr.status < 400) {
                        updateNewOrderUploadUi(true, 'Готово, открываю заказ...', 100, false);
                        newOrderForm.dispatchEvent(new CustomEvent('order-app:clear-draft'));
                        window.location.href = xhr.responseURL || '?section=in_progress';
                        return;
                    }
                    unlockNewOrderForm();
                    updateNewOrderUploadUi(true, 'Ошибка сервера. Попробуйте еще раз.', 100, true);
                };

                xhr.onerror = function() {
                    clearTimeout(uploadHintTimer);
                    unlockNewOrderForm();
                    updateNewOrderUploadUi(true, 'Проблема с интернетом или сервером. Проверьте связь и повторите.', 100, true);
                };

                xhr.ontimeout = function() {
                    clearTimeout(uploadHintTimer);
                    unlockNewOrderForm();
                    updateNewOrderUploadUi(true, 'Сервер долго отвечает. Проверьте интернет и повторите.', 100, true);
                };

                xhr.send(formData);
            }

            newOrderForm.addEventListener('reset', function() {
                newOrderInputs.forEach(clearQueuedInputFiles);
            });
            newOrderForm.addEventListener('submit', function(event) {
                if (newOrderForm.dataset.compressionSubmitting === '1') {
                    return;
                }
                var waits = newOrderInputs.map(waitForQueuedInput);
                if (!waits.length) {
                    return;
                }
                event.preventDefault();
                if (!navigator.onLine) {
                    updateNewOrderUploadUi(true, 'Нет подключения к интернету. Подключитесь и повторите.', 100, true);
                    return;
                }
                setSubmitButtonsDisabled(true);
                updateNewOrderUploadUi(true, 'Подготовка фото...', 10, false);
                Promise.all(waits).then(function() {
                    newOrderForm.dataset.compressionSubmitting = '1';
                    submitNewOrderWithProgress();
                }).catch(function() {
                    unlockNewOrderForm();
                    updateNewOrderUploadUi(true, 'Ошибка при подготовке фото. Повторите попытку.', 100, true);
                });
            });
        }

        var noteInputs = document.querySelectorAll('form[id^=\"noteForm\"] input[name=\"additional_photos[]\"]');
        noteInputs.forEach(function(input) {
            input.addEventListener('change', function() {
                var selectedFiles = Array.from(input.files || []);
                enqueueInputFiles(input, selectedFiles).then(function() {
                    var form = input.closest('form');
                    if (form && form.id && form.id.indexOf('noteForm') === 0) {
                        var orderId = form.id.replace('noteForm', '');
                        if (orderId) {
                            updateButtonText(orderId);
                        }
                    }
                });
            });

            var noteForm = input.closest('form');
            if (noteForm && !noteForm.dataset.compressSubmitBound) {
                noteForm.dataset.compressSubmitBound = '1';
                noteForm.addEventListener('submit', function(event) {
                    if (noteForm.dataset.compressionSubmitting === '1') {
                        return;
                    }
                    var formInputs = Array.from(noteForm.querySelectorAll('input[type=\"file\"]'));
                    var waits = formInputs.map(waitForQueuedInput);
                    if (!waits.length) {
                        return;
                    }
                    event.preventDefault();
                    Promise.all(waits).then(function() {
                        noteForm.dataset.compressionSubmitting = '1';
                        if (typeof noteForm.requestSubmit === 'function') {
                            noteForm.requestSubmit();
                            return;
                        }
                        noteForm.dispatchEvent(new CustomEvent('order-app:clear-draft'));
                        noteForm.submit();
                    });
                });
            }
        });
    }
    
    // Function to get URL parameter by name
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }
    
    // Function to scroll to and expand order card after page load if open_details parameter is present
    document.addEventListener('DOMContentLoaded', function() {
        // Check if open_details parameter exists in URL
        var openDetailsId = getUrlParameter('open_details');
        if (openDetailsId) {
            // Find the order card element by ID
            var orderCard = document.getElementById('order' + openDetailsId);
            if (orderCard) {
                // Wait a bit for Bootstrap to initialize, then expand and scroll
                setTimeout(function() {
                    // Expand the order details using Bootstrap's collapse method
                    var orderDetails = document.getElementById('orderDetails' + openDetailsId);
                    if (orderDetails && !orderDetails.classList.contains('show')) {
                        var bsCollapse = new bootstrap.Collapse(orderDetails, {
                            toggle: true
                        });
                    }
                    
                    // Scroll the order card to the center of the screen
                    var rect = orderCard.getBoundingClientRect();
                    var centerY = window.innerHeight / 2;
                    var elementY = rect.top + window.scrollY;
                    var scrollToY = elementY - centerY + (rect.height / 2);
                    
                    window.scrollTo({
                        top: scrollToY,
                        behavior: 'smooth'
                    });
                }, 100); // Small delay to ensure Bootstrap components are initialized
            }
        }
    });

    document.addEventListener('DOMContentLoaded', setupPhotoCompression);
    
    
</script>
</body>
</html>
    <?php
}

function renderLoginPage($login_error = null, $login_success = null)
{
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Вход в систему</title>
        <link rel="icon" type="image/jpeg" href="Logo.png">
        <link rel="apple-touch-icon" href="Logo.png">
        <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
        <link rel="manifest" href="manifest.json">
        <meta name="theme-color" content="#000000"/>
        <style>
            body.login-page {
                background:
                    radial-gradient(850px 560px at 8% 10%, rgba(0, 180, 216, 0.24), transparent 58%),
                    radial-gradient(780px 540px at 94% 8%, rgba(0, 82, 204, 0.2), transparent 55%),
                    linear-gradient(rgba(240, 248, 255, 0.92), rgba(224, 237, 248, 0.92)),
                    url('Logo.png') center center / cover no-repeat;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
                padding: 20px;
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            }
            .login-page .login-container {
                position: relative;
                overflow: hidden;
                max-width: 430px;
                width: 100%;
                padding: 30px;
                background: linear-gradient(145deg, rgba(255, 255, 255, 0.96), rgba(244, 248, 252, 0.96));
                border-radius: 16px;
                box-shadow: 0 18px 45px rgba(13, 47, 79, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.7);
                border: 1px solid rgba(13, 78, 127, 0.22);
                backdrop-filter: blur(10px);
            }
            .login-page .login-container::before {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 6px;
                background: linear-gradient(90deg, #00b4d8, #0077b6, #00b4d8);
                opacity: 0.9;
            }
            .login-page .orders-label {
                margin-bottom: 10px;
                text-align: center;
                font-size: clamp(34px, 8vw, 52px);
                font-weight: 900;
                letter-spacing: 7px;
                color: #00558a;
                text-transform: uppercase;
                line-height: 1;
                text-shadow: 0 10px 26px rgba(0, 89, 145, 0.18);
            }
            .login-page .login-title {
                color: #144369;
                font-size: 1.05rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 1.2px;
                margin-bottom: 20px !important;
            }
            .login-page .form-control {
                margin-bottom: 15px;
                font-size: 16px;
                padding: 12px;
                border: 1px solid rgba(13, 78, 127, 0.25);
                border-radius: 8px;
                background: rgba(255, 255, 255, 0.98);
                color: #1f2a37;
                box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08);
            }
            .login-page .form-control:focus {
                border-color: #00b4d8;
                box-shadow: 0 0 0 3px rgba(0, 180, 216, 0.25);
                background: rgba(255, 255, 255, 0.98);
                color: #1f2a37;
            }
            .login-page .btn {
                width: 100%;
                font-size: 16px;
                padding: 12px;
                background: linear-gradient(145deg, #00b4d8, #0077b6);
                border: 1px solid rgba(0, 119, 182, 0.6);
                color: #ffffff;
                border-radius: 8px;
                box-shadow: 0 4px 15px rgba(0, 180, 216, 0.3);
            }
            .login-page .btn:hover {
                box-shadow: 0 6px 20px rgba(0, 180, 216, 0.5);
                background: linear-gradient(145deg, #0077b6, #005f8c);
            }
            .login-page .alert {
                margin-bottom: 15px;
                font-size: 16px;
                background: rgba(231, 76, 60, 0.8);
                color: #ffffff;
                border: 1px solid rgba(231, 76, 60, 0.45);
                border-radius: 8px;
                backdrop-filter: blur(5px);
            }
            .login-page .alert-success {
                background: rgba(39, 174, 96, 0.85);
                border-color: rgba(39, 174, 96, 0.5);
            }
            .login-page h2 {
                color: #0b2f51;
                text-shadow: 0 1px 2px rgba(255, 255, 255, 0.7);
                letter-spacing: 0.6px;
            }
            .login-page .form-label {
                color: #1c2f45;
                font-weight: 600;
            }
            .login-page .login-divider {
                margin: 22px 0 18px;
                border-top: 1px solid rgba(255, 255, 255, 0.12);
                padding-top: 18px;
            }
            .login-page .login-helper-text {
                color: #cfd8dc;
                font-size: 14px;
                margin-bottom: 12px;
            }
            .login-page .btn-secondary-action {
                background: linear-gradient(145deg, #495057, #343a40);
                box-shadow: 0 4px 15px rgba(73, 80, 87, 0.28);
            }
            .login-page .btn-secondary-action:hover {
                background: linear-gradient(145deg, #3d444a, #2b3035);
                box-shadow: 0 6px 20px rgba(73, 80, 87, 0.38);
            }
            @media (max-width: 576px) {
                .login-page .login-container {
                    padding: 15px;
                    margin: 10px;
                }
                .login-page .form-control,
                .login-page .btn {
                    font-size: 14px;
                    padding: 10px;
                }
                .login-page .login-title {
                    font-size: 0.95rem;
                }
                .login-page .orders-label {
                    letter-spacing: 5px;
                }
            }
        </style>
        <link rel="stylesheet" href="views-global.css">
    </head>
    <body class="has-top-nav login-page">
        <div class="login-container" id="loginContainer">
            <div class="orders-label">ЗАКАЗЫ</div>
            <h2 class="text-center mb-4 login-title">Вход в систему</h2>
            <?php if ($login_error) {
                echo "<div class='alert alert-danger'>" . htmlspecialchars($login_error) . "</div>";
            } ?>
            <?php if ($login_success) {
                echo "<div class='alert alert-success'>" . htmlspecialchars($login_success) . "</div>";
            } ?>
            <form method="post">
                <div class="mb-3">
                    <label for="username" class="form-label">Логин</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Пароль</label>
                    <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" name="login" class="btn">Войти</button>
            </form>
            
        </div>
    </body>
    </html>
    <?php
}

function renderMainPage($conn, $section, $queue_date, $form_data)
{
    if (!$conn instanceof mysqli) {
        die("Ошибка подключения к базе данных");
    }
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <title>Система управления заказами</title>
        <link rel="icon" type="image/jpeg" href="Logo.png">
        <link rel="apple-touch-icon" href="Logo.png">
        <link rel="manifest" href="manifest.json">
        <meta name="theme-color" content="#000000"/>
        <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="vendor/fontawesome/css/all.min.css">
        <link href="vendor/flatpickr/flatpickr.min.css" rel="stylesheet">
        <style>
            body {
                background-color: #f4f6f8;
                background-image: url('./1/fn.png');
                background-repeat: no-repeat;
                background-position: center center;
                background-size: cover;
                color: #1f2a37;
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
                font-size: 16px;
                padding: 10px;
                margin-top: 50px;
                margin-bottom: 80px; /* Добавляем отступ снизу для нижней навигации */
            }
        .top-left-buttons {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 1250;
            display: flex;
            gap: 10px;
            align-items: center;
        }
            .dropdown-menu {
                background: linear-gradient(145deg, rgba(255, 255, 255, 0.96), rgba(245, 247, 250, 0.96));
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 8px;
                backdrop-filter: blur(10px);
            }
            .dropdown-item {
                color: #1f2a37;
                padding: 10px 15px;
            }
            .dropdown-item:hover {
                background: rgba(0, 180, 216, 0.3);
                color: #ffffff;
            }
            .container {
                padding: 10px;
                max-width: 100%;
                width: 100%;
            }
            .dropdown-menu {
                background: linear-gradient(145deg, rgba(255, 255, 255, 0.96), rgba(245, 247, 250, 0.96));
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 8px;
                backdrop-filter: blur(10px);
            }
            .dropdown-item {
                color: #1f2a37;
                padding: 10px 15px;
            }
            .dropdown-item:hover {
                background: rgba(0, 180, 216, 0.3);
                color: #ffffff;
            }
            .metallic-card {
                background: linear-gradient(145deg, rgba(255, 255, 255, 0.96), rgba(245, 247, 250, 0.96));
                border: 1px solid rgba(15, 23, 42, 0.16);
                border-radius: 12px;
                margin-bottom: 15px;
                padding: 12px;
                box-shadow: 0 6px 22px rgba(15, 23, 42, 0.16), inset 0 1px 1px rgba(255, 255, 255, 0.2);
                backdrop-filter: blur(10px);
                position: relative;
            }
            .form-control {
                background: #2c3e50; /* Темный фон */
                border: 1px solid rgba(255, 255, 255, 0.2);
                border-radius: 8px;
                padding: 12px;
                font-size: 16px;
                color: #ffffff; /* Белый цвет текста */
                width: 100%;
                box-sizing: border-box;
            }
            .form-control:focus {
                border-color: gold; /* Золотой цвет границы при фокусе */
                box-shadow: 0 0 8px rgba(255, 215, 0, 0.5); /* Золотое свечение */
                background: #2c3e50; /* Темный фон при фокусе */
                color: #ffffff; /* Белый цвет текста при фокусе */
            }
            body#search .search-query-input {
                background: #ffffff !important;
                color: #111111 !important;
                border: 1px solid #cbd5e1;
            }
            body#search .search-query-input:focus {
                background: #ffffff !important;
                color: #111111 !important;
                border-color: #00b4d8;
                box-shadow: 0 0 0 3px rgba(0, 180, 216, 0.22);
            }
            body#search .search-query-input::placeholder {
                color: #6b7280;
            }
            form[id^="noteForm"] textarea.form-control {
                background: #ffffff;
                color: #1f2a37;
                border: 1px solid #cbd5e1;
            }
            form[id^="noteForm"] textarea.form-control:focus {
                background: #ffffff;
                color: #1f2a37;
                border-color: #00b4d8;
                box-shadow: 0 0 0 3px rgba(0, 180, 216, 0.25);
            }
            form[id^="noteForm"] textarea.form-control::placeholder {
                color: #6b7280;
            }
            .btn {
                padding: 12px 16px;
                font-size: 16px;
                border-radius: 8px;
                border: none;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
                display: inline-flex;
                align-items: center;
                justify-content: center;
                text-align: center;
                min-height: 48px;
                transition: all 0.2s ease;
            }
            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(0, 0, 0, 0.5);
            }
            .btn:active {
                transform: translateY(0);
            }
            .btn-primary {
                background: linear-gradient(145deg, #00b4d8, #0077b6);
                color: #ffffff;
            }
            .btn-primary:hover {
                background: linear-gradient(145deg, #0077b6, #005f8c);
            }
            .btn-success {
                background: linear-gradient(145deg, #2ecc71, #27ae60);
                color: #ffffff;
            }
            .btn-secondary {
                background: linear-gradient(145deg, #e2e8f0, #cbd5e1);
                color: #ffffff;
            }
            .btn-warning {
                background: linear-gradient(145deg, #f1c40f, #e67e22);
                color: #ffffff;
            }
            .btn-danger {
                background: linear-gradient(145deg, #e74c3c, #c0392b);
                color: #ffffff;
            }
            .btn-info {
                background: linear-gradient(145deg, #00b4d8, #0077b6);
                color: #ffffff;
            }
            .btn-info:hover {
                background: linear-gradient(145deg, #0077b6, #005f8c);
            }
            .btn-sm {
                padding: 10px 14px;
                font-size: 14px;
                min-height: 40px;
            }
            .btn-block {
                display: block;
                width: 100%;
            }
            .photo-preview {
                height: 200px;
                width: auto;
                max-width: 200px;
                object-fit: contain;
                border: 1px solid rgba(255, 255, 255, 0.2);
                border-radius: 8px;
                cursor: pointer;
                margin: 2px;
                flex-shrink: 0;
            }
            .photo-preview.photo-error {
                opacity: 0.5;
                filter: grayscale(1);
            }
            .photos-container {
                display: flex;
                flex-direction: row;
                overflow-x: auto;
                padding: 5px 0;
                white-space: nowrap;
                width: 100%;
                gap: 2px;
                align-items: flex-start;
            }
            .action-buttons {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                padding: 10px 0;
                justify-content: center;
                width: 100%;
                margin-top: 10px;
            }
            .action-buttons .btn {
                flex: 1;
                min-width: 120px;
                margin: 2px;
            }
            .collapse-content {
                background: linear-gradient(145deg, rgba(255, 255, 255, 0.96), rgba(245, 247, 250, 0.96));
                padding: 12px;
                border-radius: 8px;
                font-size: 14px;
                border: 1px solid #cbd5e1;
                backdrop-filter: blur(5px);
                color: #1f2a37;
            }
            .collapse-content .info-row {
                margin: 5px 0;
                padding: 5px 0;
                border-bottom: 1px solid rgba(15, 23, 42, 0.12);
                color: #1f2a37;
            }
            .collapse-content .info-row:last-child {
                border-bottom: none;
            }
            .order-expense-category {
                display: inline-block;
                font-weight: 900;
                text-transform: uppercase;
                letter-spacing: 0.7px;
                -webkit-text-stroke: 0.9px rgba(20, 20, 20, 0.85);
                paint-order: stroke fill;
                text-shadow:
                    1px 0 0 rgba(20, 20, 20, 0.65),
                    -1px 0 0 rgba(20, 20, 20, 0.65),
                    0 1px 0 rgba(20, 20, 20, 0.65),
                    0 -1px 0 rgba(20, 20, 20, 0.65);
            }
            .order-expense-link {
                text-decoration: none;
            }
            .order-expense-link:hover .order-expense-category,
            .order-expense-link:focus-visible .order-expense-category {
                text-decoration: underline;
            }
            .order-expense-category-expense {
                color: #ff4d6d;
            }
            .order-expense-category-income {
                color: #2ddf74;
            }
            .order-expense-category-dimet {
                color: #52c2ff;
            }
            .order-expense-category-paint_1_layer {
                color: #ffbf3a;
            }
            .order-expense-category-geometry_fix {
                color: #cb8cff;
            }
            .order-expense-category-welding {
                color: #ff6a3d;
            }
            .client-info {
                display: flex;
                flex-direction: column;
                gap: 2px;
                padding: 2px 0;
            }
            .info-divider {
                border: 0;
                height: 1px;
                background: rgba(15, 23, 42, 0.06);
                margin: 5px 0;
            }
            .phone-link {
                color: #00b4d8;
                text-decoration: none;
                font-weight: 500;
            }
            .phone-link:hover {
                text-decoration: underline;
                color: #48cae4;
            }
            h5 {
                font-size: 18px;
                margin-bottom: 12px;
                color: #ffffff;
                text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
            }
            .button-group {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin-top: 10px;
            }
            .modal-content {
                background: linear-gradient(145deg, rgba(255, 255, 255, 0.96), rgba(245, 247, 250, 0.96));
                color: #1f2a37;
                border: 1px solid rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(10px);
            }
            .modal-header, .modal-footer {
                border-color: rgba(255, 255, 255, 0.1);
            }
            .nav-menu {
                position: fixed;
                top: 0;
                left: 0;
                width: 280px;
                max-width: 85vw;
                height: 100%;
                background: linear-gradient(145deg, #ffffff, #eef2f5);
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
                z-index: 1000;
                display: flex;
                flex-direction: column;
                padding: 20px;
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
                overflow-y: auto;
            }
            .nav-menu.open {
                transform: translateX(0);
            }
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
            .nav-menu a:hover, .nav-menu button:hover {
                background: rgba(15, 23, 42, 0.06);
                color: #ffffff;
            }
            .nav-menu a.active {
                background: linear-gradient(145deg, #00b4d8, #0077b6);
                color: #ffffff;
                box-shadow: 0 2px 10px rgba(0, 180, 216, 0.3);
            }
            .nav-toggle {
                z-index: 1100;
                background: linear-gradient(145deg, #00b4d8, #0077b6);
                border: none;
                border-radius: 8px;
                padding: 10px 14px;
                box-shadow: 0 4px 15px rgba(0, 180, 216, 0.3);
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 48px;
            }
            .nav-toggle:hover {
                box-shadow: 0 6px 20px rgba(0, 180, 216, 0.5);
            }
            .top-nav {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                height: 44px;
                background: linear-gradient(145deg, #ffffff, #eef2f5);
                z-index: 1200;
                padding: 6px 8px;
                overflow-x: auto;
                white-space: nowrap;
                gap: 6px;
                border-bottom: 1px solid #cbd5e1;
            }
            .top-nav a {
                display: inline-block;
                color: #1f2a37;
                text-decoration: none;
                font-size: 12px;
                padding: 6px 8px;
                border-radius: 6px;
            }
            .top-nav a.active {
                color: #00b4d8;
                font-weight: 600;
                background: rgba(15, 23, 42, 0.06);
            }
            /* Показываем кнопку меню на ПК */
            @media (min-width: 993px) {
                .nav-toggle {
                    display: flex !important;
                }
            }
            select.compact option {
                padding: 5px 10px;
                line-height: 1.4;
            }
            .loading-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                z-index: 2000;
                justify-content: center;
                align-items: center;
            }
            .loader {
                border: 8px solid #cbd5e1;
                border-top: 8px solid #00b4d8;
                border-radius: 50%;
                width: 50px;
                height: 50px;
                animation: spin 1s linear infinite;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            .collapsing {
                transition: none !important;
            }
            .collapse:not(.show) {
                display: none;
            }
            /* Улучшенная адаптация для мобильных устройств */
            @media (max-width: 992px) {
                .top-left-buttons {
                    position: absolute;
                    top: 56px;
                    left: 5px;
                    gap: 5px;
                }
                .top-nav {
                    display: flex;
                }
                .nav-toggle {
                    padding: 8px 12px;
                    min-height: 40px;
                }
                body {
                    font-size: 16px;
                    padding: 8px;
                    margin-top: 108px;
                }
                .container {
                    padding: 8px;
                }
                .metallic-card {
                    padding: 10px;
                    margin-bottom: 12px;
                }
                .form-control {
                    font-size: 16px;
                    padding: 12px;
                }
                .btn {
                    padding: 12px 16px;
                    font-size: 16px;
                    min-height: 48px;
                }
                .btn-sm {
                    padding: 10px 14px;
                    font-size: 14px;
                }
                .photo-preview {
                    height: 120px;
                    max-width: 120px;
                }
                h5 {
                    font-size: 18px;
                }
                .nav-menu {
                    display: none;
                    width: 250px;
                }
            }
            @media (max-width: 768px) {
                .top-left-buttons {
                    position: absolute;
                    top: 56px;
                    left: 5px;
                    gap: 5px;
                }
                body {
                    font-size: 16px;
                    padding: 6px;
                    margin-top: 60px;
                }
                .container {
                    padding: 6px;
                }
                .metallic-card {
                    padding: 8px;
                    margin-bottom: 10px;
                }
                .form-control {
                    font-size: 16px;
                    padding: 10px;
                }
                .btn {
                    padding: 10px 14px;
                    font-size: 16px;
                    min-height: 46px;
                }
                .btn-sm {
                    padding: 8px 12px;
                    font-size: 14px;
                }
                .photo-preview {
                    height: 110px;
                    max-width: 110px;
                }
                .action-buttons .btn {
                    min-width: auto;
                    flex: 1 1 45%;
                }
                h5 {
                    font-size: 17px;
                }
                .nav-menu {
                    display: none;
                    width: 230px;
                }
                .nav-toggle {
                    padding: 8px 12px;
                    min-height: 44px;
                }
                .photos-container {
                    gap: 6px;
                }
                .button-group {
                    flex-direction: column;
                    gap: 8px;
                }
                .button-group .btn {
                    width: 100%;
                }
            }
            @media (max-width: 992px) {
                body {
                    font-size: 16px;
                    padding: 8px;
                    margin-top: 60px;
                    margin-bottom: 90px; /* Добавляем отступ снизу для нижней навигации */
                }
                .container {
                    padding: 8px;
                }
                .metallic-card {
                    padding: 10px;
                    margin-bottom: 12px;
                }
                .form-control {
                    font-size: 16px;
                    padding: 12px;
                }
                .btn {
                    padding: 12px 16px;
                    font-size: 16px;
                    min-height: 48px;
                }
                .btn-sm {
                    padding: 10px 14px;
                    font-size: 14px;
                }
                .photo-preview {
                    height: 120px;
                    max-width: 120px;
                }
                h5 {
                    font-size: 18px;
                }
                .nav-menu {
                    width: 250px;
                }
                /* Добавляем стили для мобильной навигации внизу */
                .bottom-nav {
                    display: flex;
                    align-items: center;
                    position: fixed;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    height: 44px;
                    background: linear-gradient(145deg, #ffffff, #eef2f5);
                    z-index: 1000;
                    padding: 3px 0;
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
                    font-size: 11px;
                    padding: 4px 8px;
                    border-radius: 8px;
                    min-width: 70px;
                    min-height: 34px;
                    flex: 0 0 auto;
                }
                .bottom-nav .nav-icon {
                    font-size: 14px;
                    line-height: 1;
                    margin-bottom: 2px;
                }
                .bottom-nav .nav-item.active {
                    background: linear-gradient(145deg, #00b4d8, #0077b6);
                    color: #ffffff;
                }
                .active-glow {
                    box-shadow: 0 0 15px 5px rgba(0, 180, 216, 0.7);
                    transition: box-shadow 0.3s ease-in-out;
                }
                /* Скрываем боковое меню на мобильных устройствах в пользу нижней навигации */
                .nav-menu {
                    display: none;
                }
                .nav-toggle {
                    display: none !important;
                }
            }
            @media (max-width: 768px) {
                .top-left-buttons {
                     position: absolute;
                     top: 56px;
                     left: 5px;
                     gap: 5px;
                     display: flex; /* Ensure top-left-buttons are flex on mobile */
                }
                body {
                    font-size: 16px;
                    padding: 6px;
                    margin-top: 108px;
                    margin-bottom: 90px;
                }
                .container {
                    padding: 6px;
                }
                .metallic-card {
                    padding: 8px;
                    margin-bottom: 10px;
                }
                .form-control {
                    font-size: 16px;
                    padding: 10px;
                }
                .btn {
                    padding: 10px 14px;
                    font-size: 16px;
                    min-height: 46px;
                }
                .btn-sm {
                    padding: 8px 12px;
                    font-size: 14px;
                }
                .photo-preview {
                    height: 100px;
                    max-width: 100px;
                }
                .action-buttons .btn {
                    min-width: auto;
                    flex: 1 1 45%;
                }
                h5 {
                    font-size: 17px;
                }
                .nav-menu {
                    display: none;
                    width: 230px;
                }
                .nav-toggle {
                    padding: 8px 12px;
                    min-height: 44px;
                    display: none !important;
                }
                .photos-container {
                    gap: 6px;
                }
                .button-group {
                    flex-direction: column;
                    gap: 8px;
                }
                .button-group .btn {
                    width: 100%;
                }
            }
            @media (max-width: 576px) {
                .top-left-buttons {
                     top: 56px;
                     left: 5px;
                     gap: 5px;
                     display: flex; /* Ensure top-left-buttons are flex on mobile */
                }
                body {
                    font-size: 16px;
                    padding: 5px;
                    margin-top: 60px;
                    margin-bottom: 90px;
                }
                .container {
                    padding: 5px;
                }
                .metallic-card {
                    padding: 8px;
                    margin-bottom: 10px;
                }
                .form-control {
                    font-size: 16px;
                    padding: 10px;
                }
                .btn {
                    padding: 12px;
                    font-size: 16px;
                    min-height: 48px;
                }
                .btn-sm {
                    padding: 10px;
                    font-size: 15px;
                }
                .photo-preview {
                    height: 90px;
                    max-width: 90px;
                }
                .action-buttons .btn {
                    flex: 1 1 100%;
                    margin: 2px 0;
                }
                h5 {
                    font-size: 17px;
                }
                .nav-menu {
                    display: none;
                    width: 100%;
                    left: -10%;
                }
                .nav-toggle {
                    padding: 10px 12px;
                    min-height: 48px;
                    display: none !important;
                }
                .photos-container {
                    gap: 5px;
                }
                .button-group {
                    flex-direction: column;
                    gap: 10px;
                }
                .button-group .btn {
                    width: 100%;
                }
            }
            <?php if (in_array($section, ['in_progress', 'completed', 'archive'], true)) : ?>
            @media (max-width: 992px) {
                body {
                    padding-left: 0 !important;
                    padding-right: 0 !important;
                }
                .container {
                    padding-left: 0 !important;
                    padding-right: 0 !important;
                }
                .metallic-card {
                    width: 100%;
                    margin-left: 0;
                    margin-right: 0;
                    margin-bottom: 6px;
                    padding: 6px;
                    border-radius: 6px;
                }
                .metallic-card .card-body {
                    padding: 6px;
                }
            }
            <?php endif; ?>
            .form-label {
                color: #1f2a37;
                font-weight: 500;
                margin-bottom: 6px;
                display: block;
            }
            .text-muted {
                color: #64748b !important;
            }
            .table {
                color: #1f2a37;
                background: linear-gradient(145deg, rgba(255, 255, 255, 0.96), rgba(245, 247, 250, 0.96));
                border: 1px solid rgba(255, 255, 255, 0.1);
                font-size: 14px;
            }
            .table-bordered th,
            .table-bordered td {
                border: 1px solid rgba(255, 255, 255, 0.1);
                padding: 8px;
            }
            .table thead th {
                background: linear-gradient(145deg, #ffffff, #eef2f5);
                color: #1f2a37;
                font-weight: 600;
            }
            
            /* Modern Settings Page Styles */
            .settings-container {
                display: flex;
                flex-direction: column;
                gap: 12px;
                width: 100%;
            }
            
            .settings-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
                border-bottom: 1px solid rgba(15, 23, 42, 0.12);
            }
            .settings-actions {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            
            .settings-title {
                font-size: 22px;
                color: #1f2a37;
                margin: 0;
                font-weight: 600;
            }
            
            .btn-gradient {
                background: linear-gradient(145deg, #00b4d8, #0077b6);
                color: #ffffff;
                border: none;
                border-radius: 8px;
                padding: 10px 16px;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(0, 180, 216, 0.3);
            }
            
            .btn-gradient:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(0, 180, 216, 0.5);
            }
            
            .settings-section {
                background: #ffffff;
                border: 1px solid #b8c3d1;
                border-radius: 8px;
                padding: 12px;
                box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
            }
            
            .section-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
                padding-bottom: 8px;
                border-bottom: 1px solid #d7dee8;
            }
            
            .section-title {
                font-size: 18px;
                color: #1f2a37;
                margin: 0;
                font-weight: 600;
            }
            
            .user-count {
                background: rgba(15, 23, 42, 0.06);
                color: #1f2a37;
                padding: 4px 10px;
                border-radius: 20px;
                font-size: 14px;
            }
            
            .user-form {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            
            .form-group {
                display: flex;
                flex-direction: column;
                gap: 6px;
            }
            
            .form-label {
                color: #1f2a37;
                font-weight: 500;
                margin-bottom: 6px;
                display: block;
            }
            
            .form-input, .form-select {
                background: #ffffff;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
                padding: 9px 10px;
                font-size: 14px;
                color: #1f2a37;
                width: 100%;
                box-sizing: border-box;
                min-height: 38px;
            }
            
            .form-input:focus, .form-select:focus {
                border-color: #00b4d8;
                box-shadow: 0 0 8px rgba(0, 180, 216, 0.3);
                background: #ffffff;
                color: #1f2a37;
            }
            
            .form-select {
                appearance: none;
                background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
                background-repeat: no-repeat;
                background-position: right 1rem center;
                background-size: 1em;
                padding-right: 40px;
            }
            
            .btn-full {
                width: 100%;
            }
            
            .users-table-container {
                width: fit-content;
                max-width: 100%;
                overflow-x: auto;
                border-radius: 4px;
                border: 1px solid #7f8da1;
                background: #ffffff;
                box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
            }
            .users-mobile-list {
                display: none;
            }
            .user-mobile-card {
                background: #ffffff;
                border: 1px solid #94a3b8;
                border-radius: 6px;
                padding: 0;
                margin-bottom: 8px;
                overflow: hidden;
            }
            .user-mobile-row {
                display: flex;
                justify-content: space-between;
                gap: 10px;
                margin: 0;
                padding: 8px 10px;
                font-size: 13px;
                border-bottom: 1px solid #cbd5e1;
            }
            .user-mobile-row:last-child {
                margin-bottom: 0;
            }
            .user-mobile-label {
                color: #64748b;
                min-width: 70px;
                flex-shrink: 0;
                font-weight: 700;
            }
            .user-mobile-value {
                color: #1f2a37;
                text-align: right;
                word-break: break-word;
            }
            .user-mobile-actions {
                display: flex;
                gap: 6px;
                padding: 8px;
                background: #f8fafc;
            }
            .user-mobile-actions .btn-edit,
            .user-mobile-actions .btn-delete,
            .user-mobile-actions .btn-block {
                flex: 1;
                justify-content: center;
                min-height: 42px;
            }
            
            .users-table {
                width: auto;
                min-width: 900px;
                max-width: 100%;
                border-collapse: collapse;
                color: #1f2a37;
                font-size: 12px;
                table-layout: fixed;
                background: #ffffff;
            }
            
            .users-table th,
            .users-table td {
                padding: 6px 8px;
                text-align: left;
                border: 1px solid #9aa8bb;
                vertical-align: middle;
                line-height: 1.25;
            }
            
            .users-table th {
                background: #eef3f8 !important;
                color: #162033 !important;
                font-weight: 800;
                position: sticky;
                top: 0;
                box-shadow: inset 0 -1px 0 #7f8da1;
                text-shadow: none !important;
                letter-spacing: 0;
            }
            
            .users-table-col-id {
                width: 64px;
            }

            .users-table-col-login {
                width: 12ch;
            }

            .users-table-col-role,
            .users-table-col-status {
                width: 132px;
            }

            .users-table-col-actions {
                width: 430px;
            }

            .users-table th:nth-child(3),
            .users-table td:nth-child(3),
            .users-table th:nth-child(4),
            .users-table td:nth-child(4) {
                text-align: center;
            }
            
            .user-id {
                color: #334155;
                font-size: 12px;
                font-weight: 800;
            }
            
            .user-login {
                font-weight: 500;
                overflow: hidden;
            }
            
            .user-role {
                text-align: center;
            }
            
            .user-password {
                font-family: monospace;
                font-size: 14px;
                letter-spacing: 1px;
            }
            
            .badge {
                padding: 2px 7px;
                border-radius: 4px;
                font-size: 11px;
                font-weight: 600;
                display: inline-block;
                white-space: nowrap;
                border: 1px solid currentColor;
            }
            
            .badge-admin {
                background: rgba(231, 76, 60, 0.3);
                color: #e74c3c;
            }
            
            .badge-user {
                background: rgba(46, 204, 113, 0.3);
                color: #2ecc71;
            }

            .badge-blocked {
                background: rgba(239, 68, 68, 0.16);
                color: #b91c1c;
            }

            .badge-active {
                background: rgba(34, 197, 94, 0.16);
                color: #166534;
            }
            
            .user-actions {
                white-space: normal;
                text-align: left;
            }

            .settings-action-group {
                display: flex;
                gap: 10px;
                align-items: center;
                justify-content: center;
                flex-wrap: nowrap;
            }

            .user-actions form {
                display: block !important;
                margin: 0;
                flex: 0 0 auto;
            }
            
            .btn-edit, .btn-delete, .btn-block {
                background: transparent;
                border: 1px solid transparent;
                border-radius: 4px;
                padding: 0 10px;
                cursor: pointer;
                transition: all 0.2s ease;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 32px;
                width: 128px;
                min-width: 128px;
                max-width: 128px;
                font-size: 12px;
                font-weight: 600;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.65);
            }
            
            .btn-edit {
                background: #fff8db;
                border-color: #d6a900;
                color: #7a5b00;
            }

            .btn-block {
                background: #eef2f7;
                border-color: #94a3b8;
                color: #1e293b;
            }

            .btn-block.is-blocked {
                background: #dcfce7;
                border-color: #16a34a;
                color: #166534;
            }
            
            .btn-delete {
                background: #fee2e2;
                border-color: #ef4444;
                color: #991b1b;
            }
            
            .btn-edit:hover {
                background: #ffef9f;
            }
            
            .btn-delete:hover {
                background: #fecaca;
            }

            .btn-block:hover {
                background: #e2e8f0;
            }

            .btn-block.is-blocked:hover {
                background: #bbf7d0;
            }

            .btn-edit:disabled,
            .btn-delete:disabled,
            .btn-block:disabled {
                opacity: 0.62;
                cursor: not-allowed;
                transform: none !important;
            }
            
            .action-icon {
                font-size: 16px;
            }
            
            .actions-header {
                text-align: center;
            }

            .settings-summary {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 8px;
                margin-bottom: 8px;
            }

            .settings-summary-card {
                background: #f8fafc;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
                padding: 9px 10px;
                box-shadow: none;
            }

            .settings-summary-card strong {
                display: block;
                font-size: 20px;
                color: #0f172a;
                line-height: 1.1;
                margin-bottom: 2px;
            }

            .settings-summary-card span {
                color: #64748b;
                font-size: 13px;
            }

            .settings-note {
                margin-bottom: 10px;
                padding: 8px 10px;
                border-radius: 6px;
                background: rgba(15, 23, 42, 0.04);
                color: #475569;
                font-size: 13px;
                border: 1px solid rgba(148, 163, 184, 0.18);
            }

            .settings-form-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 8px;
                align-items: end;
            }

            .settings-toolbar {
                display: grid;
                grid-template-columns: minmax(220px, 1.5fr) repeat(2, minmax(170px, 0.8fr));
                gap: 8px;
                margin-bottom: 8px;
                align-items: end;
            }

            .settings-toolbar .form-group {
                margin: 0;
            }

            .settings-toolbar-label {
                display: block;
                margin-bottom: 6px;
                color: #475569;
                font-size: 13px;
                font-weight: 600;
            }

            .settings-muted {
                color: #64748b;
                font-size: 13px;
                margin-bottom: 6px;
            }

            .users-table tbody tr.is-hidden,
            .user-mobile-card.is-hidden {
                display: none !important;
            }

            .users-table tbody tr {
                transition: background-color 0.18s ease;
            }

            .users-table tbody tr:nth-child(even) {
                background: #f8fafc;
            }

            .users-table tbody tr:hover {
                background: #eef6ff;
            }

            .user-login-main {
                display: block;
                font-weight: 700;
                color: #0f172a;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .user-login-meta {
                display: block;
                margin-top: 1px;
                color: #64748b;
                font-size: 11px;
            }

            /* Mobile Responsive Styles for Settings */
            @media (max-width: 768px) {
                .settings-container {
                    gap: 10px;
                }
                .settings-header {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 15px;
                    padding: 8px 0;
                }
                
                .settings-title {
                    font-size: 20px;
                }
                
                .section-header {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 8px;
                    margin-bottom: 12px;
                }
                
                .user-count {
                    align-self: flex-start;
                }
                .btn-gradient {
                    width: 100%;
                }
                .settings-section {
                    padding: 8px;
                    border-radius: 6px;
                }
                .settings-toolbar {
                    grid-template-columns: 1fr;
                    gap: 10px;
                }
                .form-input, .form-select {
                    min-height: 38px;
                    padding: 9px 10px;
                }
                
                .users-table-container {
                    display: none;
                }
                .users-mobile-list {
                    display: block;
                }
                .user-mobile-card {
                    border-radius: 6px;
                    padding: 8px;
                    margin-bottom: 7px;
                }
                .user-mobile-actions {
                    display: grid;
                    grid-template-columns: 1fr;
                    gap: 6px;
                }
                .user-mobile-actions form {
                    width: 100%;
                }
                .btn-edit,
                .btn-delete,
                .btn-block {
                    width: 100%;
                }
            }
            
            @media (max-width: 576px) {
                .settings-title {
                    font-size: 19px;
                }
                
                .form-input, .form-select {
                    padding: 12px;
                    font-size: 16px;
                    min-height: 44px;
                }
                
                .settings-section {
                    padding: 8px;
                }
            }
            /* New order: force light inputs */
            body#new_order .form-control,
            body#new_order .form-select {
                background-color: #f8fafc !important;
                color: #1f2a37 !important;
                border: 2px solid #a9b6c8 !important;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
                color-scheme: light;
            }
            body#new_order .form-control::placeholder {
                color: #64748b;
            }
            body#new_order .form-control:focus,
            body#new_order .form-select:focus {
                background-color: #ffffff !important;
                color: #1f2a37 !important;
                border-color: #4b5563 !important;
                box-shadow: 0 0 0 3px rgba(75, 85, 99, 0.25);
            }
        </style>
        <link rel="stylesheet" href="views-global.css">
    </head>
    <body id="<?= htmlspecialchars($section) ?>" class="<?= $section === 'warehouse' ? 'warehouse-side-nav-only' : 'has-top-nav' ?>">
        <?php
        $navOptions = [
            'show_top' => $section !== 'warehouse',
            'show_bottom' => $section !== 'warehouse',
            'show_spacers' => false,
            'toggle_label' => '☰',
            'toggle_class' => 'btn nav-toggle',
        ];
        renderUnifiedNavigation($section, $navOptions);
        ?>
        <main class="container" id="mainContent">
            <script>
    let photoModalInstance;

    function getTouchDistance(touches) {
        const dx = touches[0].clientX - touches[1].clientX;
        const dy = touches[0].clientY - touches[1].clientY;
        return Math.hypot(dx, dy);
    }

    function applyImageTransform(img) {
        const scale = parseFloat(img.dataset.scale || '1');
        const panX = parseFloat(img.dataset.panX || '0');
        const panY = parseFloat(img.dataset.panY || '0');
        img.style.transform = `translate(${panX}px, ${panY}px) scale(${scale})`;
    }

    function resetImageZoom(img) {
        if (!img) return;
        img.dataset.scale = '1';
        img.dataset.panX = '0';
        img.dataset.panY = '0';
        applyImageTransform(img);
    }

    function retryPhotoLoad(img) {
        if (!img) return;
        const retries = Number(img.dataset.retry || '0');
        if (retries >= 1) {
            img.classList.add('photo-error');
            return;
        }
        img.dataset.retry = String(retries + 1);
        const baseSrc = img.dataset.src || img.currentSrc || img.getAttribute('src') || '';
        if (!baseSrc) return;
        try {
            const url = new URL(baseSrc, window.location.origin);
            url.searchParams.set('retry', String(Date.now()));
            img.src = url.toString();
        } catch (e) {
            const sep = baseSrc.includes('?') ? '&' : '?';
            img.src = baseSrc + sep + 'retry=' + Date.now();
        }
    }

    function bindOrderPhotoHandlers() {
        document.querySelectorAll('img.photo-preview').forEach((img) => {
            if (img.dataset.bound === '1') return;
            img.dataset.bound = '1';
            img.addEventListener('error', function () {
                retryPhotoLoad(img);
            });
        });
    }

    function viewPhoto(element, index) {
        // Remove existing modal to avoid conflicts
        const existingModal = document.getElementById('photoModal');
        if (existingModal) {
            existingModal.remove();
        }

        const photosJson = element.getAttribute('data-photos');
        const photos = JSON.parse(photosJson);

        const modalDiv = document.createElement('div');
        modalDiv.id = 'photoModal';
        modalDiv.className = 'modal fade';
        modalDiv.tabIndex = '-1';
        modalDiv.setAttribute('aria-labelledby', 'photoModalLabel');
        modalDiv.innerHTML = `
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content" style="background: rgba(255, 255, 255, 0.96); color: #1f2a37; border: 1px solid rgba(15, 23, 42, 0.12);">
                    <div class="modal-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                        <h5 id="photoModalLabel" class="modal-title" style="color: #1f2a37;">Просмотр фото</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center" style="padding: 0;">
                        <div id="photoCarousel" class="carousel slide">
                            <div class="carousel-inner">
                                <!-- Slides will be added here -->
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
        `;
        document.body.appendChild(modalDiv);

        const carouselInner = modalDiv.querySelector('.carousel-inner');
        const carouselEl = modalDiv.querySelector('#photoCarousel');
        photos.forEach((photo, i) => {
            const item = document.createElement('div');
            item.className = 'carousel-item' + (i === index ? ' active' : '');
            
            const img = document.createElement('img');
            img.dataset.src = photo;
            img.dataset.retry = '0';
            img.src = photo;
            img.className = 'd-block w-100';
            img.style.maxHeight = '80vh';
            img.style.objectFit = 'contain';
            img.style.transformOrigin = 'center center';
            img.style.touchAction = 'none';
            img.dataset.scale = '1';
            img.dataset.panX = '0';
            img.dataset.panY = '0';
            img.addEventListener('error', function () {
                retryPhotoLoad(img);
            });
            let pinchStartDistance = 0;
            let panStartX = 0;
            let panStartY = 0;
            let isPanning = false;

            img.addEventListener('touchstart', function (e) {
                if (e.touches.length === 2) {
                    pinchStartDistance = getTouchDistance(e.touches);
                    isPanning = false;
                    e.stopPropagation();
                } else if (e.touches.length === 1 && parseFloat(img.dataset.scale || '1') > 1) {
                    isPanning = true;
                    panStartX = e.touches[0].clientX - parseFloat(img.dataset.panX || '0');
                    panStartY = e.touches[0].clientY - parseFloat(img.dataset.panY || '0');
                    e.stopPropagation();
                }
            }, { passive: true });

            img.addEventListener('touchmove', function (e) {
                if (e.touches.length === 2 && pinchStartDistance > 0) {
                    const currentDistance = getTouchDistance(e.touches);
                    const delta = currentDistance / pinchStartDistance;
                    const currentScale = parseFloat(img.dataset.scale || '1');
                    const pinchScale = Math.min(4, Math.max(1, currentScale * delta));
                    img.dataset.scale = String(pinchScale);
                    pinchStartDistance = currentDistance;
                    applyImageTransform(img);
                    e.preventDefault();
                    e.stopPropagation();
                } else if (e.touches.length === 1 && isPanning && parseFloat(img.dataset.scale || '1') > 1) {
                    img.dataset.panX = String(e.touches[0].clientX - panStartX);
                    img.dataset.panY = String(e.touches[0].clientY - panStartY);
                    applyImageTransform(img);
                    e.preventDefault();
                    e.stopPropagation();
                }
            }, { passive: false });

            img.addEventListener('touchend', function (e) {
                if (e.touches.length < 2) {
                    pinchStartDistance = 0;
                }
                if (e.touches.length === 0) {
                    isPanning = false;
                }
            }, { passive: true });
            
            item.appendChild(img);
            carouselInner.appendChild(item);
        });

        carouselEl.addEventListener('slide.bs.carousel', function (e) {
            const activeImg = carouselEl.querySelector('.carousel-item.active img');
            if (activeImg && parseFloat(activeImg.dataset.scale || '1') > 1) {
                e.preventDefault();
                return;
            }
            carouselEl.querySelectorAll('img').forEach(resetImageZoom);
        });

        const photoModalInstance = new bootstrap.Modal(modalDiv);
        photoModalInstance.show();

        // Properly handle modal disposal
        modalDiv.addEventListener('hidden.bs.modal', function () {
            carouselEl.querySelectorAll('img').forEach(resetImageZoom);
            photoModalInstance.dispose();
            modalDiv.remove();
        });
    }
    document.addEventListener('DOMContentLoaded', bindOrderPhotoHandlers);
            </script>
            <?php
            switch ($section) {
                case 'new_order':
                    renderNewOrderForm($queue_date, $form_data);
                    break;
                case 'in_progress':
                    $sort = $_GET['sort'] ?? 'default';
                    $sort_value = $_GET['sort_value'] ?? '';
                    $totalOrders = countOrdersByStatus('in_progress', $sort, $sort_value);
                    $orders = getOrdersByStatus('in_progress', $sort, $sort_value, max(1, $totalOrders), 0);
                    displayOrders($orders, 'Заказы в работе', $section, $sort, $sort_value, null);
                    break;
                case 'completed':
                    $sort = $_GET['sort'] ?? 'default';
                    $sort_value = $_GET['sort_value'] ?? '';
                    $totalOrders = countOrdersByStatus('completed', $sort, $sort_value);
                    $orders = getOrdersByStatus('completed', $sort, $sort_value, max(1, $totalOrders), 0);
                    displayOrders($orders, 'Готовые заказы', $section, $sort, $sort_value, null);
                    break;
                case 'archive':
                    $sort = $_GET['sort'] ?? 'default';
                    $sort_value = $_GET['sort_value'] ?? '';
                    $totalOrders = countOrdersByStatus('archive', $sort, $sort_value);
                    $orders = getOrdersByStatus('archive', $sort, $sort_value, max(1, $totalOrders), 0);
                    displayOrders($orders, 'Архив заказов', $section, $sort, $sort_value, null);
                    break;
                case 'warehouse':
                    renderWarehousePage();
                    break;
                case 'zakaz':
                    renderZakazPage();
                    break;
                case 'storage':
                    header("Location: storage.php");
                    exit;
                case 'tires':
                    header("Location: tires.php");
                    exit;
                case 'search':
                    renderSearchForm($conn);
                    break;
                case 'settings':
                    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
                        renderSettingsPage($conn);
                    } else {
                        echo "<div class='alert alert-danger'>Доступ запрещён</div>";
                    }
                    break;
                case 'reports':
                    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
                        header("Location: reports.php");
                        exit;
                    } else {
                        echo "<div class='alert alert-danger'>Доступ запрещён</div>";
                    }
                    break;
                case 'zp':
                    header("Location: zp.php");
                    exit;
                case 'expenses':
                    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
                        renderExpensesPage($conn);
                    } else {
                        echo "<div class='alert alert-danger'>Доступ запрещён</div>";
                    }
            }
            ?>
        </main>


        <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editUserModalLabel">Редактировать пользователя</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post">
                            <input type="hidden" name="user_id" id="edit_user_id">
                            <div class="mb-3">
                                <label for="edit_username" class="form-label">Логин</label>
                                <input type="text" class="form-control" id="edit_username" name="edit_username" required autocomplete="username">
                            </div>
                            <div class="mb-3">
                                <label for="edit_password" class="form-label">Пароль</label>
                                <input type="password" class="form-control" id="edit_password" name="edit_password" autocomplete="new-password">
                            </div>
                            <div class="mb-3">
                                <label for="edit_role" class="form-label">Роль</label>
                                <select class="form-control" id="edit_role" name="edit_role">
                                    <option value="user">Пользователь</option>
                                    <option value="admin">Админ</option>
                                </select>
                            </div>
                            <button type="submit" name="edit_user" class="btn btn-primary">Сохранить</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editRashodyUserModal" tabindex="-1" aria-labelledby="editRashodyUserModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editRashodyUserModalLabel">Редактировать пользователя расходов</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post">
                            <input type="hidden" name="rashody_user_id" id="edit_rashody_user_id">
                            <div class="mb-3">
                                <label for="edit_rashody_username" class="form-label">Логин</label>
                                <input type="text" class="form-control" id="edit_rashody_username" name="edit_rashody_username" required autocomplete="username">
                            </div>
                            <div class="mb-3">
                                <label for="edit_rashody_password" class="form-label">Новый пароль</label>
                                <input type="password" class="form-control" id="edit_rashody_password" name="edit_rashody_password" autocomplete="new-password" placeholder="Оставьте пустым, чтобы не менять">
                            </div>
                            <button type="submit" name="edit_rashody_user" class="btn btn-primary">Сохранить</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="loading-overlay" id="loadingOverlay">
            <div class="loader"></div>
        </div>

        <script src="vendor/bootstrap/bootstrap.bundle.min.js"></script>
        <script src="vendor/flatpickr/flatpickr.min.js"></script>
        <script>
            function toggleNav() {
                const navMenu = document.getElementById('navMenu');
                const navToggleBtn = document.querySelector('.nav-toggle'); // Получаем кнопку меню
                navMenu.classList.toggle('open');
                if (window.innerWidth > 992) { // Только для ПК версии
                    if (navMenu.classList.contains('open')) {
                        navToggleBtn.style.display = 'none'; // Скрываем кнопку, если меню открыто
                    } else {
                        navToggleBtn.style.display = 'flex'; // Показываем кнопку, если меню закрыто
                    }
                }
            }


            function editUser(id, username, password, role) {
                document.getElementById('edit_user_id').value = id;
                document.getElementById('edit_username').value = username;
                document.getElementById('edit_password').value = password;
                document.getElementById('edit_role').value = role;
                const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
                modal.show();
            }

            function editRashodyUser(id, username) {
                document.getElementById('edit_rashody_user_id').value = id;
                document.getElementById('edit_rashody_username').value = username;
                document.getElementById('edit_rashody_password').value = '';
                const modal = new bootstrap.Modal(document.getElementById('editRashodyUserModal'));
                modal.show();
            }

            function initSettingsUserFilters() {
                const searchInput = document.getElementById('settingsUserSearch');
                const roleFilter = document.getElementById('settingsRoleFilter');
                const statusFilter = document.getElementById('settingsStatusFilter');
                const info = document.getElementById('settingsUserFilterInfo');
                if (!searchInput || !roleFilter || !statusFilter || !info) {
                    return;
                }

                const rows = Array.from(document.querySelectorAll('[data-user-row]'));
                const cards = Array.from(document.querySelectorAll('[data-user-card]'));
                const items = rows.concat(cards);

                const applyFilters = function () {
                    const query = String(searchInput.value || '').trim().toLowerCase();
                    const role = String(roleFilter.value || '');
                    const status = String(statusFilter.value || '');
                    let visibleRows = 0;
                    let visibleCards = 0;

                    rows.forEach(function (item) {
                        const haystack = String(item.getAttribute('data-user-search') || '').toLowerCase();
                        const itemRole = String(item.getAttribute('data-user-role') || '');
                        const itemStatus = String(item.getAttribute('data-user-status') || '');
                        const matchesQuery = !query || haystack.indexOf(query) !== -1;
                        const matchesRole = !role || itemRole === role;
                        const matchesStatus = !status || itemStatus === status;
                        const visible = matchesQuery && matchesRole && matchesStatus;
                        item.classList.toggle('is-hidden', !visible);
                        if (visible) {
                            visibleRows += 1;
                        }
                    });

                    cards.forEach(function (item) {
                        const haystack = String(item.getAttribute('data-user-search') || '').toLowerCase();
                        const itemRole = String(item.getAttribute('data-user-role') || '');
                        const itemStatus = String(item.getAttribute('data-user-status') || '');
                        const matchesQuery = !query || haystack.indexOf(query) !== -1;
                        const matchesRole = !role || itemRole === role;
                        const matchesStatus = !status || itemStatus === status;
                        const visible = matchesQuery && matchesRole && matchesStatus;
                        item.classList.toggle('is-hidden', !visible);
                        if (visible) {
                            visibleCards += 1;
                        }
                    });

                    if (!query && !role && !status) {
                        info.textContent = 'Показаны все пользователи.';
                        return;
                    }

                    const total = rows.length || cards.length || 0;
                    const visibleCount = rows.length ? visibleRows : visibleCards;
                    info.textContent = 'Найдено: ' + visibleCount + ' из ' + total + '.';
                };

                searchInput.addEventListener('input', applyFilters);
                roleFilter.addEventListener('change', applyFilters);
                statusFilter.addEventListener('change', applyFilters);
                applyFilters();
            }

            document.addEventListener('DOMContentLoaded', initSettingsUserFilters);

            function editOrder(orderId, section) {
                window.location.href = `edit_order.php?order_id=${orderId}&section=${section}`;
            }

            function updateButtonText(orderId) {
                const textarea = document.querySelector('#noteForm' + orderId + ' textarea[name="note"]');
                const fileInput = document.querySelector('#noteForm' + orderId + ' input[type="file"]');
                const submitButton = document.getElementById('submitButton' + orderId);
                const archiveButton = document.getElementById('archiveButton' + orderId);

                let hasContent = textarea.value.trim() !== '' || (fileInput.files && fileInput.files.length > 0);
                if (submitButton) {
                    submitButton.textContent = hasContent ? 'Сохранить данные' : 'Добавить примечание';
                    submitButton.disabled = !hasContent;
                }
                if (archiveButton) {
                    archiveButton.textContent = hasContent ? 'Добавить и в архив' : 'Переместить в архив';
                }
            }

            function logOrderCardOpen(orderId) {
                if (!orderId) return;
                fetch('log_navigation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: `Открытие карточки заказа #${orderId}`
                    })
                }).catch(() => {});
            }

            function toggleDetails(button, orderId) {
                const collapse = document.getElementById('orderDetails' + orderId);
                if (collapse.classList.contains('show')) {
                    button.textContent = 'Открыть подробности';
                } else {
                    button.textContent = 'Закрыть подробности';
                    logOrderCardOpen(orderId);
                }
            }

            function openCalendar() {
                const input = document.getElementById('queue_date');
                if (!input) return;

                if (window.flatpickr) {
                    if (!input._flatpickr) {
                        flatpickr(input, {
                            dateFormat: "Y-m-d",
                            locale: "ru",
                            minDate: input.getAttribute('data-min-date') || null,
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

            function viewCalendar() {
                window.open('calendar.php', '_blank', 'width=800,height=600');
            }


            function toggleSortValue(select) {
                const sortValueSelect = document.getElementById('sortValue');
                const section = '<?php echo htmlspecialchars($section); ?>';
                if (select.value === 'default') {
                    sortValueSelect.style.display = 'none';
                    applySort(section);
                } else {
                    sortValueSelect.style.display = 'block';
                    // Загружаем актуальные значения для выбранного типа сортировки
                    loadSortValues(select.value, section, false); // Не сохраняем текущее значение при смене типа
                }
            }

            function loadSortValues(sortType, section, preserveValue = true) {
                const sortValueSelect = document.getElementById('sortValue');
                const currentValue = preserveValue ? sortValueSelect.value : ''; // Сохраняем текущее значение только если нужно
                sortValueSelect.innerHTML = '<option value="">Загрузка...</option>';
                
                console.log('Загружаем значения для:', sortType, 'секция:', section, 'сохраняем значение:', preserveValue);
                
                // Отправляем AJAX запрос для получения актуальных значений
                fetch(`?section=${section}&action=get_sort_values&sort_type=${sortType}`)
                    .then(response => {
                        console.log('Ответ получен:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Данные получены:', data);
                        sortValueSelect.innerHTML = '<option value="">Все</option>';
                        
                        if (data.error) {
                            console.error('Ошибка сервера:', data.error);
                            return;
                        }
                        
                        if (data.values && data.values.length > 0) {
                            data.values.forEach(value => {
                                const option = document.createElement('option');
                                option.value = value.value;
                                option.textContent = value.label;
                                if (preserveValue && value.value === currentValue) {
                                    option.selected = true; // Восстанавливаем выбранное значение
                                }
                                sortValueSelect.appendChild(option);
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Ошибка загрузки значений:', error);
                        sortValueSelect.innerHTML = '<option value="">Все</option>';
                    });
            }

            function applySort(section) {
                const sortType = document.getElementById('sortType').value;
                const sortValue = document.getElementById('sortValue').value;
                let url = `?section=${section}`;
                if (sortType !== 'default') {
                    url += `&sort=${sortType}`;
                }
                if (sortValue) {
                    url += `&sort_value=${sortValue}`;
                }
                window.location.href = url;
            }

            function showLoading() {
                document.getElementById('loadingOverlay').style.display = 'flex';
            }

            window.addEventListener('load', function() {
                const urlParams = new URLSearchParams(window.location.search);
                const openDetails = urlParams.get('open_details');
                if (openDetails) {
                    const collapse = document.getElementById('orderDetails' + openDetails);
                    if (collapse) {
                        const card = document.getElementById('order' + openDetails);
                        if (card) {
                            collapse.classList.add('show');
                            const toggleBtn = document.getElementById('toggle' + openDetails);
                            if (toggleBtn) {
                                toggleBtn.textContent = 'Закрыть подробности';
                            }
                            logOrderCardOpen(openDetails);
                            // Прокрутка к элементу
                            setTimeout(() => {
                                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }, 100); // Небольшая задержка для корректной работы
                        }
                    }
                }
                
                // Автоматически загружаем значения сортировки, если выбран тип сортировки
                const sortTypeSelect = document.getElementById('sortType');
                const sortValueSelect = document.getElementById('sortValue');
                if (sortTypeSelect && sortValueSelect && sortTypeSelect.value !== 'default' && sortValueSelect.style.display !== 'none') {
                    const section = '<?php echo htmlspecialchars($section); ?>';
                    const sortValue = urlParams.get('sort_value') || '';
                    
                    // Устанавливаем выбранное значение из URL
                    if (sortValue) {
                        sortValueSelect.value = sortValue;
                    }
                    
                    loadSortValues(sortTypeSelect.value, section, true); // Сохраняем выбранное значение при загрузке страницы
                }

                // Initialize button states on page load
                const orderForms = document.querySelectorAll('form[id^="noteForm"]');
                orderForms.forEach(form => {
                    const orderId = form.id.replace('noteForm', '');
                    updateButtonText(orderId);
                });
            });

            // Закрытие меню при клике на пустую область
            document.addEventListener('click', function(event) {
                const navMenu = document.getElementById('navMenu');
                const navToggleBtn = document.querySelector('.nav-toggle'); // Получаем кнопку меню
                const mainContent = document.getElementById('mainContent');
                if (navMenu.classList.contains('open') &&
                    !navMenu.contains(event.target) &&
                    !navToggleBtn.contains(event.target) &&
                    mainContent.contains(event.target)) {
                    navMenu.classList.remove('open');
                    if (window.innerWidth > 992) { // Только для ПК версии
                        navToggleBtn.style.display = 'flex'; // Показываем кнопку при закрытии
                    }
                }
            });

            (function setupReliableNavClose() {
                function setNavOpen(open) {
                    const navMenu = document.getElementById('navMenu');
                    const navToggleBtn = document.querySelector('.nav-toggle');
                    if (!navMenu) return;
                    navMenu.classList.toggle('open', Boolean(open));
                    document.body.classList.toggle('nav-menu-open', Boolean(open));
                    if (navToggleBtn && window.innerWidth > 992) {
                        navToggleBtn.style.display = open ? 'none' : 'flex';
                    }
                }

                window.toggleNav = function() {
                    const navMenu = document.getElementById('navMenu');
                    setNavOpen(!(navMenu && navMenu.classList.contains('open')));
                };

                document.addEventListener('click', function(event) {
                    const navMenu = document.getElementById('navMenu');
                    const navToggleBtn = document.querySelector('.nav-toggle');
                    if (!navMenu || !navMenu.classList.contains('open')) return;
                    if (navMenu.contains(event.target) || (navToggleBtn && navToggleBtn.contains(event.target))) return;
                    setNavOpen(false);
                }, true);

                document.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape') {
                        setNavOpen(false);
                    }
                });
            })();

            // Log navigation
            document.querySelectorAll('.nav-menu a, .bottom-nav a').forEach(link => {
                link.addEventListener('click', function(e) {
                    const section = this.getAttribute('data-section');
                    if (section) {
                        fetch('log_navigation.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({ action: `Переход в раздел: ${section}` })
                        });
                    }
                });
            });

            (function () {
                const DRAFT_PREFIX = 'order_app_draft_v1:';
                const DIRTY_MARK = '1';
                let dirtyForms = 0;

                function getCurrentUser() {
                    return <?php echo json_encode((string)($_SESSION['username'] ?? 'guest'), JSON_UNESCAPED_UNICODE); ?>;
                }

                function getCurrentSection() {
                    return <?php echo json_encode((string)$section, JSON_UNESCAPED_UNICODE); ?>;
                }

                function getFormKey(form) {
                    if (!form || !form.id) return '';
                    return DRAFT_PREFIX + getCurrentUser() + ':' + getCurrentSection() + ':' + form.id;
                }

                function isPersistableField(el) {
                    if (!el || !el.name || el.disabled) return false;
                    if (el.type === 'file' || el.type === 'password' || el.type === 'hidden') return false;
                    return true;
                }

                function collectFormState(form) {
                    const state = {};
                    form.querySelectorAll('input, textarea, select').forEach((el) => {
                        if (!isPersistableField(el)) return;
                        if (el.type === 'checkbox' || el.type === 'radio') {
                            state[el.name] = state[el.name] || [];
                            if (el.checked) {
                                state[el.name].push(el.value);
                            }
                            return;
                        }
                        if (el.tagName === 'SELECT' && el.multiple) {
                            state[el.name] = Array.from(el.selectedOptions).map((opt) => opt.value);
                            return;
                        }
                        state[el.name] = el.value;
                    });
                    return state;
                }

                function restoreFormState(form, state) {
                    if (!form || !state) return;
                    form.querySelectorAll('input, textarea, select').forEach((el) => {
                        if (!isPersistableField(el)) return;
                        if (!(el.name in state)) return;
                        const saved = state[el.name];

                        if (el.type === 'checkbox' || el.type === 'radio') {
                            const selected = Array.isArray(saved) ? saved : [saved];
                            el.checked = selected.includes(el.value);
                            return;
                        }
                        if (el.tagName === 'SELECT' && el.multiple) {
                            const selected = Array.isArray(saved) ? saved : [saved];
                            Array.from(el.options).forEach((opt) => {
                                opt.selected = selected.includes(opt.value);
                            });
                            return;
                        }
                        el.value = typeof saved === 'string' ? saved : '';
                    });
                }

                function markDirty(form, isDirty) {
                    if (!form) return;
                    const wasDirty = form.dataset.draftDirty === DIRTY_MARK;
                    if (wasDirty === isDirty) return;
                    form.dataset.draftDirty = isDirty ? DIRTY_MARK : '';
                    dirtyForms += isDirty ? 1 : -1;
                    if (dirtyForms < 0) dirtyForms = 0;
                }

                function saveDraft(form) {
                    const key = getFormKey(form);
                    if (!key) return;
                    try {
                        const payload = {
                            savedAt: Date.now(),
                            state: collectFormState(form)
                        };
                        localStorage.setItem(key, JSON.stringify(payload));
                        markDirty(form, true);
                    } catch (e) {
                        // ignore storage errors
                    }
                }

                function clearDraft(form) {
                    const key = getFormKey(form);
                    if (!key) return;
                    try {
                        localStorage.removeItem(key);
                    } catch (e) {
                        // ignore storage errors
                    }
                    markDirty(form, false);
                }

                function restoreDraft(form) {
                    const key = getFormKey(form);
                    if (!key) return;
                    try {
                        const raw = localStorage.getItem(key);
                        if (!raw) return;
                        const payload = JSON.parse(raw);
                        if (!payload || typeof payload !== 'object' || !payload.state) return;
                        restoreFormState(form, payload.state);
                    } catch (e) {
                        // ignore malformed drafts
                    }
                }

                function bindDraftForForm(form) {
                    if (!form || !form.id || form.dataset.draftBound === '1') return;
                    form.dataset.draftBound = '1';

                    restoreDraft(form);
                    form.dispatchEvent(new Event('input', { bubbles: true }));
                    if (typeof bindOrderPhotoHandlers === 'function') {
                        bindOrderPhotoHandlers();
                    }

                    let timer = null;
                    const scheduleSave = function () {
                        clearTimeout(timer);
                        timer = setTimeout(() => saveDraft(form), 800);
                    };

                    form.addEventListener('input', function (e) {
                        if (!isPersistableField(e.target)) return;
                        scheduleSave();
                    });

                    form.addEventListener('change', function (e) {
                        if (!isPersistableField(e.target)) return;
                        scheduleSave();
                    });

                    form.addEventListener('submit', function () {
                        clearDraft(form);
                    });

                    form.addEventListener('order-app:clear-draft', function () {
                        clearDraft(form);
                    });
                }

                function initDraftAutosave() {
                    document.querySelectorAll('form#newOrderForm, form[id^="noteForm"]').forEach(bindDraftForForm);
                }

                window.addEventListener('beforeunload', function (event) {
                    if (dirtyForms <= 0) return;
                    event.preventDefault();
                    event.returnValue = '';
                });

                document.addEventListener('DOMContentLoaded', initDraftAutosave);
            })();
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var cameraBtn = document.getElementById('newOrderCameraBtn');
                var galleryBtn = document.getElementById('newOrderGalleryBtn');
                var cameraInput = document.getElementById('newOrderCameraInput');
                var galleryInput = document.getElementById('newOrderGalleryInput');
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
    <?php
}

function buildVersionedPhotoUrl($photo)
{
    $photo = trim((string)$photo);
    if ($photo === '' || !file_exists($photo)) {
        return $photo;
    }
    $version = @filemtime($photo);
    if (!$version) {
        return $photo;
    }
    $sep = strpos($photo, '?') === false ? '?' : '&';
    return $photo . $sep . 'v=' . $version;
}

function renderWarehousePage()
{
    ?>
    <style>
        body#warehouse {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            padding-bottom: 0 !important;
        }
        body#warehouse #mainContent {
            max-width: 100%;
            padding: 0;
            min-height: 100dvh;
            height: 100dvh;
            overflow: hidden;
        }
        .warehouse-shell {
            height: 100%;
            min-height: 0;
            width: 100%;
            overflow: hidden;
            background: #f7f8fb;
        }
        .warehouse-shell iframe {
            display: block;
            width: 100%;
            height: 100%;
            border: 0;
            background: #f7f8fb;
        }
        @media (max-width: 900px) {
            body#warehouse {
                padding-left: 0;
                padding-right: 0;
                overflow: hidden;
            }
            body#warehouse #mainContent {
                height: 100dvh;
                min-height: 100dvh;
            }
            .warehouse-shell {
                height: 100%;
                min-height: 0;
                border-radius: 0;
            }
        }
    </style>
    <section class="warehouse-shell" aria-label="Склад">
        <iframe src="warehouse_embed.php" title="Склад: диски и шины" loading="eager"></iframe>
    </section>
    <?php
}

function renderZakazPage()
{
    ?>
    <style>
        body#zakaz #mainContent {
            max-width: 100%;
            padding: 0;
        }
        .zakaz-shell {
            height: calc(100dvh - 92px);
            min-height: 760px;
            width: 100%;
            overflow: hidden;
            border-radius: 12px;
            box-shadow: 0 6px 22px rgba(15, 23, 42, 0.16);
            background: #f3eadc;
        }
        .zakaz-shell iframe {
            display: block;
            width: 100%;
            height: 100%;
            border: 0;
            background: #f3eadc;
        }
        @media (max-width: 900px) {
            body#zakaz {
                padding-left: 0;
                padding-right: 0;
            }
            .zakaz-shell {
                height: calc(100dvh - 96px);
                min-height: 700px;
                border-radius: 0;
            }
        }
    </style>
    <section class="zakaz-shell" aria-label="Оформление заказ-наряда">
        <iframe src="shinomontazh?v=202605101735" title="Оформление заказ-наряда" allow="web-share"></iframe>
    </section>
    <div class="metallic-card mt-3">
        <p class="mb-2">Если форма не отобразилась выше, откройте заказ-наряд напрямую.</p>
        <a class="btn btn-primary btn-sm" href="shinomontazh?v=202605101735" target="_blank" rel="noopener">Открыть заказ-наряд</a>
    </div>
    <?php
}

function displayOrders($orders, $title, $section, $sort = 'default', $sort_value = '', $paginator = null, $show_sort_controls = true)
{
    $sort_controls_classes = $show_sort_controls ? 'd-flex gap-2 align-items-center' : 'd-none';
    if ($show_sort_controls && in_array($section, ['in_progress', 'completed'], true)) {
        $sort_controls_classes .= ' d-none d-md-flex';
    }

    echo '
    <div class="card metallic-card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h5 class="card-title">' . $title . '</h5>
                <div class="' . $sort_controls_classes . '">
                    <select class="form-control" id="sortType" onchange="toggleSortValue(this)">
                        <option value="default" ' . ($sort === 'default' ? 'selected' : '') . '>По умолчанию</option>
                        <option value="queue" ' . ($sort === 'queue' ? 'selected' : '') . '>По дате очереди</option>
                        <option value="location" ' . ($sort === 'location' ? 'selected' : '') . '>По локации</option>
                    </select>
                    <select class="form-control" id="sortValue" style="display: ' . ($sort === 'default' ? 'none' : 'block') . ';" onchange="applySort(\'' . htmlspecialchars($section) . '\')">
                        <option value="">Все</option>';

    // Не заполняем список значений здесь - они будут загружены через AJAX
    // Это позволяет всегда показывать полный список доступных значений

    echo '
                    </select>
                    <button class="btn btn-info btn-sm" onclick="applySort(\'' . htmlspecialchars($section) . '\')">Применить</button>
                </div>
            </div>';

    if ($section === 'in_progress') {
        echo '
            <div class="mt-3 in-progress-search-wrap">
                <input type="text" class="form-control search-query-input" id="inProgressLiveFilter" placeholder="Поиск" autocomplete="off">
            </div>
            <div id="inProgressGlobalResults" class="mt-3" style="display:none;"></div>
            <style>
                .in-progress-search-wrap {
                    max-width: 760px;
                }
                #inProgressLiveFilter {
                    background: linear-gradient(180deg, #ffffff 0%, #f3fbff 100%) !important;
                    color: #111111 !important;
                    caret-color: #111111;
                    border: 2px solid #00b4d8 !important;
                    border-radius: 14px;
                    min-height: 52px;
                    padding: 12px 16px;
                    font-size: 17px;
                    font-weight: 600;
                    letter-spacing: 0.15px;
                    box-shadow: 0 8px 20px rgba(0, 77, 112, 0.18), inset 0 1px 0 rgba(255, 255, 255, 0.9);
                    transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
                }
                #inProgressLiveFilter::placeholder {
                    color: #1f4c63;
                    font-weight: 700;
                    font-size: 16px;
                    letter-spacing: 0.25px;
                    opacity: 1;
                }
                #inProgressLiveFilter:hover {
                    border-color: #009bb9 !important;
                    box-shadow: 0 10px 24px rgba(0, 77, 112, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.95);
                }
                #inProgressLiveFilter:focus {
                    background: #ffffff !important;
                    color: #111111 !important;
                    border-color: #007f9b !important;
                    box-shadow: 0 0 0 4px rgba(0, 180, 216, 0.22), 0 12px 28px rgba(0, 77, 112, 0.24) !important;
                    outline: none;
                }
                .search-hit {
                    background: #fde68a;
                    color: inherit;
                    padding: 0 2px;
                    border-radius: 3px;
                }
            </style>';
    }

    if (empty($orders)) {
        echo '<p class="text-muted">Нет заказов</p>';
    } else {
        $order_ids = array_map(function ($order) {
            return $order['id'] ?? '';
        }, $orders);
        $rashody_map = [];
        if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
            $rashody_map = getRashodyExpensesByOrders($order_ids);
        }

        foreach ($orders as $order) {
            $photos_raw = !empty($order['photos']) ? explode(',', $order['photos']) : [];
            $photos = [];
            foreach ($photos_raw as $photo) {
                $resolved = resolvePhotoPath($photo);
                if ($resolved !== '' && file_exists($resolved)) {
                    $photos[] = $resolved;
                }
            }
            $search_index = implode(' ', [
                $order['id'] ?? '',
                $order['client_name'] ?? '',
                $order['license_plate'] ?? '',
                $order['phone'] ?? '',
                $order['color'] ?? '',
                $order['location'] ?? '',
                $order['price'] ?? '',
                $order['queue_date'] ?? '',
                $order['created_at'] ?? '',
                $order['status'] ?? '',
                $order['notes'] ?? ''
            ]);
            $search_index_attr = htmlspecialchars($search_index, ENT_QUOTES, 'UTF-8');

            $created_label = '';
            if (!empty($order['created_at'])) {
                $created_ts = strtotime($order['created_at']);
                if ($created_ts) {
                    $months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
                    $weekdays = ['понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота', 'воскресенье'];
                    $day = (int)date('j', $created_ts);
                    $month = $months[(int)date('n', $created_ts) - 1] ?? '';
                    $weekday = $weekdays[(int)date('N', $created_ts) - 1] ?? '';
                    if ($month !== '' && $weekday !== '') {
                        $created_label = $day . ' ' . $month . ' ' . $weekday;
                    }
                }
            }
            $order_price_value = (float)($order['price'] ?? 0);
            $order_price_label = $order_price_value > 0 ? number_format($order_price_value, 2) . ' руб.' : 'не указана';
            $in_progress_local_class = $section === 'in_progress' ? ' in-progress-local-order' : '';

            echo '
            <div class="card mb-2 metallic-card order-live-filter-target' . $in_progress_local_class . '" data-search-index="' . $search_index_attr . '" data-order-id="' . htmlspecialchars((string)($order['id'] ?? ''), ENT_QUOTES, 'UTF-8') . '" id="order' . htmlspecialchars($order['id']) . '">
                <div class="card-body">
                    <div class="order-header">
                        <div class="client-info">
                            <strong class="text-dark" style="color: #1f2a37 !important;">#' . htmlspecialchars($order['id']) . ' ' . htmlspecialchars($order['client_name']) . '</strong>
                            <hr class="info-divider">
                            <small class="text-muted">';
            if ($order['phone']) {
                $phone_href = preg_replace('/[^0-9+]/', '', (string)$order['phone']);
                echo '<a href="tel:' . htmlspecialchars($phone_href !== '' ? $phone_href : (string)$order['phone']) . '" class="phone-link" style="color: #00b4d8 !important;">' . htmlspecialchars($order['phone']) . '</a>';
            } else {
                echo '<span style="color: #6b7280 !important;">Нет телефона</span>';
            }
            if ($created_label !== '') {
                echo '<span style="color: #6b7280 !important;"> • ' . htmlspecialchars($created_label) . '</span>';
            }
            echo '</small>
                            <div class="text-muted" style="font-size: 12px; margin-top: 4px;">Стоимость: ' . htmlspecialchars($order_price_label) . '</div>
                        </div>
                    </div>';
            if (!empty($photos)) {
                $photos_for_modal = array_map('buildVersionedPhotoUrl', $photos);
                $photos_json = htmlspecialchars(json_encode($photos_for_modal), ENT_QUOTES, 'UTF-8');
                echo '<div class="photos-container">';
                foreach ($photos as $index => $photo) {
                    if (file_exists($photo)) {
                        $photo_src = htmlspecialchars(buildVersionedPhotoUrl($photo), ENT_QUOTES, 'UTF-8');
                        echo '<img src="' . $photo_src . '" class="photo-preview" data-src="' . $photo_src . '" data-retry="0" loading="lazy" decoding="async" fetchpriority="low" data-photos=\'' . $photos_json . '\' onclick="viewPhoto(this, ' . $index . ')" tabindex="0" onkeydown="if(event.key === \'Enter\') viewPhoto(this, ' . $index . ')">';
                    }
                }
                echo '</div>';
            } else {
                echo '<div style="height: 30px;"></div>';
            }
            echo '
                    <div class="action-buttons">
                        <button class="btn btn-info btn-sm details-toggle" data-bs-toggle="collapse" data-bs-target="#orderDetails' . htmlspecialchars($order['id']) . '" id="toggle' . htmlspecialchars($order['id']) . '" onclick="toggleDetails(this, ' . htmlspecialchars($order['id']) . ')">Открыть подробности</button>';
            echo '</div>
                    <div class="collapse mt-2" id="orderDetails' . htmlspecialchars($order['id']) . '">
                        <div class="collapse-content">
                            <p class="info-row"><strong>Госномер:</strong> ' . htmlspecialchars($order['license_plate'] ?? 'не указан') . '</p>';
            if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
                echo '<p class="info-row"><strong>Чистая прибыль компании:</strong> ' . number_format((float)($order['price'] ?? 0), 2) . ' руб.</p>';
            }
            echo '
                            <p class="info-row"><strong>Цвет:</strong> ' . htmlspecialchars($order['color'] ?? 'не указан') . '</p>
                            <p class="info-row"><strong>Локация:</strong> ' . htmlspecialchars($order['location'] ?? 'не указана') . '</p>';
            $order_price = (float)($order['price'] ?? 0);
            echo '<p class="info-row"><strong>Стоимость:</strong> ' . ($order_price > 0 ? number_format($order_price, 2) . ' руб.' : 'не указана') . '</p>';
            if (!empty($order['queue_date']) && $order['queue_date'] !== '0000-00-00') {
                echo '<p class="info-row"><strong>Очередь:</strong> ' . date('d.m.Y', strtotime($order['queue_date'])) . '</p>';
            }
            echo '
                            <p class="info-row"><strong>Создан:</strong> ' . date('d.m.Y H:i', strtotime($order['created_at'])) . '</p>';
            if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
                $linked_expenses = $rashody_map[(string)$order['id']] ?? [];
                if (!empty($linked_expenses)) {
                    $expense_category_labels = [
                        'expense' => 'Расходы',
                        'income' => 'Доходы',
                        'dimet' => 'Димет',
                        'paint_1_layer' => 'Покраска 1 слой',
                        'geometry_fix' => 'Правка геометрии',
                        'welding' => 'Сварка'
                    ];
                    $expense_category_classes = [
                        'expense' => 'order-expense-category-expense',
                        'income' => 'order-expense-category-income',
                        'dimet' => 'order-expense-category-dimet',
                        'paint_1_layer' => 'order-expense-category-paint_1_layer',
                        'geometry_fix' => 'order-expense-category-geometry_fix',
                        'welding' => 'order-expense-category-welding'
                    ];
                    echo '<p class="info-row"><strong>Движение денежных средств:</strong></p><ul>';
                    foreach ($linked_expenses as $expense) {
                        $expense_id = trim((string)($expense['id'] ?? ''));
                        $type_key = ($expense['type'] ?? '') === 'income' ? 'income' : 'expense';
                        $category_key = trim((string)($expense['category'] ?? ''));
                        if ($category_key === '') {
                            $category_key = $type_key;
                        }
                        $category_label = $expense_category_labels[$category_key] ?? ($type_key === 'income' ? 'Доходы' : 'Расходы');
                        $category_class = $expense_category_classes[$category_key] ?? ($type_key === 'income'
                            ? $expense_category_classes['income']
                            : $expense_category_classes['expense']);
                        $amount = number_format((float)($expense['amount'] ?? 0), 2);
                        $date = !empty($expense['date']) ? date('d.m.Y', strtotime($expense['date'])) : '';
                        $note = trim((string)($expense['note'] ?? ''));
                        $owner = trim((string)($expense['owner'] ?? ''));
                        $category_label_upper = function_exists('mb_strtoupper')
                            ? mb_strtoupper($category_label, 'UTF-8')
                            : strtoupper($category_label);
                        $category_badge = '<span class="order-expense-category ' . htmlspecialchars($category_class) . '">'
                            . htmlspecialchars($category_label_upper)
                            . '</span>';
                        if ($expense_id !== '') {
                            $expense_href = 'Расходы.html?expense_id=' . rawurlencode($expense_id) . '&order_id=' . rawurlencode((string)$order['id']);
                            $category_badge = '<a class="order-expense-link" href="' . htmlspecialchars($expense_href) . '" title="Открыть запись расходов">'
                                . $category_badge
                                . '</a>';
                        }
                        $details = $date !== '' ? htmlspecialchars($date) . ' — ' : '';
                        $details .= $category_badge . ' '
                            . htmlspecialchars($amount . ' руб.');
                        if ($note !== '') {
                            $details .= ' — ' . htmlspecialchars($note);
                        }
                        if ($owner !== '') {
                            $details .= ' (' . htmlspecialchars($owner) . ')';
                        }
                        echo '<li>' . $details . '</li>';
                    }
                    echo '</ul>';
                }
            }
            echo '
                            <p class="info-row"><strong>Примечания:</strong>
                                <p>' . nl2br(htmlspecialchars($order['notes'] ?? 'нет')) . '</p>
                            </div>
                            <form method="post" enctype="multipart/form-data" id="noteForm' . htmlspecialchars($order['id']) . '">
                                <input type="hidden" name="order_id" value="' . htmlspecialchars($order['id']) . '">
                                <input type="hidden" name="section" value="' . htmlspecialchars($section) . '">
                                <input type="hidden" name="open_details" value="' . htmlspecialchars($order['id']) . '">
                                <div class="mb-2">';
            echo '
                                    <textarea class="form-control" name="note" rows="2" placeholder="Добавить примечание" oninput="updateButtonText(' . htmlspecialchars($order['id']) . ')"></textarea>
                                </div>
                                <div class="mb-2">
                                    <input type="file" class="form-control" name="additional_photos[]" multiple accept="image/*" onchange="updateButtonText(' . htmlspecialchars($order['id']) . ')">
                                </div>
                                <div class="button-group">';
            if ($order['status'] === 'completed') {
                echo '
                                    <button type="submit" name="add_note" class="btn btn-primary btn-sm" id="submitButton' . htmlspecialchars($order['id']) . '">Добавить примечание</button>
                                    <button type="submit" name="archive_order" class="btn btn-secondary btn-sm" id="archiveButton' . htmlspecialchars($order['id']) . '">Переместить в архив</button>';
            } else {
                echo '
                                    <button type="submit" name="add_note" class="btn btn-primary btn-sm" id="submitButton' . htmlspecialchars($order['id']) . '">Добавить примечание</button>';
            }
            if ($order['status'] === 'completed' || $order['status'] === 'archive') {
                echo '
                                    <a href="?section=' . htmlspecialchars($section) . '&reopen=' . htmlspecialchars($order['id']) . '" class="btn btn-success btn-sm" onclick="return confirm(\'Вы уверены, что хотите вернуть заказ в работу?\');">Вернуть в работу</a>';
            }
            if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
                echo '
                                    <button type="button" class="btn btn-warning btn-sm" onclick="editOrder(' . htmlspecialchars($order['id']) . ', \'' . htmlspecialchars($section) . '\')">Редактировать</button>';
            }
            if ($order['status'] === 'in_progress') {
                echo '
                                    <a href="?section=in_progress&complete=' . htmlspecialchars($order['id']) . '" class="btn btn-success btn-sm" onclick="return confirm(\'Вы уверены, что хотите переместить в Готово?\');">Заказ выполнен</a>';
            }
            if ($order['status'] === 'archive' && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
                echo '
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="order_id" value="' . htmlspecialchars($order['id']) . '">
                                        <button type="submit" name="delete_order" class="btn btn-danger btn-sm" onclick="return confirm(\'Вы уверены, что хотите удалить заказ из архива?\');">Удалить</button>
                                    </form>';
            }
            echo '
                                </div>
                                ' . (isset($GLOBALS['archive_error']) ? '<div class="alert alert-danger mt-2">' . htmlspecialchars($GLOBALS['archive_error']) . '</div>' : '') . '
                            </form>
                        </div>
                    </div>
                </div>
            </div>';
        }
    }
    echo '
        </div>
    </div>';

    if ($section === 'in_progress') {
        echo '
    <script>
    (function () {
        const input = document.getElementById("inProgressLiveFilter");
        if (!input || input.dataset.bound === "1") {
            return;
        }
        input.dataset.bound = "1";

        const localCards = Array.from(document.querySelectorAll(".in-progress-local-order"));
        const globalResults = document.getElementById("inProgressGlobalResults");
        const localPaginator = document.getElementById("inProgressLocalPaginator");
        let emptyState = document.getElementById("inProgressFilterEmpty");
        if (!emptyState) {
            emptyState = document.createElement("p");
            emptyState.id = "inProgressFilterEmpty";
            emptyState.className = "text-muted mt-2";
            emptyState.textContent = "По вашему запросу ничего не найдено";
            emptyState.style.display = "none";
            input.parentElement.insertAdjacentElement("afterend", emptyState);
        }

        let debounceTimer = null;
        let requestCounter = 0;
        let activeController = null;

        function setLocalVisibility(visible) {
            localCards.forEach(function (card) {
                card.style.display = visible ? "" : "none";
            });
            if (localPaginator) {
                localPaginator.style.display = visible ? "" : "none";
            }
        }

        function showLocalOrders() {
            setLocalVisibility(true);
            if (globalResults) {
                globalResults.style.display = "none";
                globalResults.innerHTML = "";
            }
            emptyState.style.display = "none";
        }

        function loadGlobalResults(rawQuery) {
            const query = (rawQuery || "").trim();
            if (query === "") {
                showLocalOrders();
                return;
            }

            const currentRequest = ++requestCounter;
            if (activeController) {
                activeController.abort();
            }
            activeController = new AbortController();
            const requestSignal = activeController.signal;
            setLocalVisibility(false);
            emptyState.style.display = "none";
            if (globalResults) {
                globalResults.style.display = "block";
                globalResults.innerHTML = "<p class=\"text-muted\">Поиск...</p>";
            }

            const endpoint = "index.php?action=search_orders&section=search&statuses=in_progress,completed,archive&query=" + encodeURIComponent(query);
            fetch(endpoint, { credentials: "same-origin", signal: requestSignal, cache: "no-store" })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error("HTTP " + response.status);
                    }
                    return response.text();
                })
                .then(function (html) {
                    if (currentRequest !== requestCounter) {
                        return;
                    }
                    if (globalResults) {
                        globalResults.innerHTML = html;
                        highlightInProgressResults(globalResults, query);
                        revealInProgressHighlightedDetails(globalResults);
                    }
                })
                .catch(function () {
                    if (currentRequest !== requestCounter) {
                        return;
                    }
                    if (requestSignal.aborted) {
                        return;
                    }
                    if (globalResults) {
                        globalResults.innerHTML = "<div class=\"alert alert-danger\">Ошибка загрузки результатов поиска</div>";
                    }
                });
        }

        input.addEventListener("input", function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                loadGlobalResults(input.value);
            }, 250);
        });

        function normalizeSearchText(value) {
            return String(value || "").toLocaleLowerCase("ru-RU").trim();
        }

        function getSearchTokens(query) {
            return Array.from(new Set(normalizeSearchText(query).split(/\s+/).filter(Boolean)));
        }

        function highlightInProgressResults(root, query) {
            const tokens = getSearchTokens(query);
            if (!tokens.length || !root) return;
            const blockedTags = new Set(["SCRIPT", "STYLE", "INPUT", "TEXTAREA", "SELECT", "BUTTON"]);
            const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
                acceptNode: function (node) {
                    const parent = node.parentElement;
                    if (!parent || blockedTags.has(parent.tagName) || parent.closest("mark")) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    return tokens.some(function (token) {
                        return normalizeSearchText(node.nodeValue).includes(token);
                    }) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
                }
            });
            const nodes = [];
            while (walker.nextNode()) nodes.push(walker.currentNode);
            nodes.forEach(function (node) {
                const text = node.nodeValue || "";
                const lower = text.toLocaleLowerCase("ru-RU");
                const fragment = document.createDocumentFragment();
                let index = 0;
                while (index < text.length) {
                    let bestToken = "";
                    let bestIndex = -1;
                    tokens.forEach(function (token) {
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
                    const mark = document.createElement("mark");
                    mark.className = "search-hit";
                    mark.textContent = text.slice(bestIndex, bestIndex + bestToken.length);
                    fragment.appendChild(mark);
                    index = bestIndex + bestToken.length;
                }
                node.parentNode.replaceChild(fragment, node);
            });
        }

        function revealInProgressHighlightedDetails(root) {
            if (!root || typeof bootstrap === "undefined") return;
            root.querySelectorAll(".collapse").forEach(function (collapse) {
                if (!collapse.querySelector(".search-hit")) return;
                bootstrap.Collapse.getOrCreateInstance(collapse, { toggle: false }).show();
                const id = collapse.id || "";
                const button = id ? root.querySelector("[data-bs-target=\"#" + id + "\"]") : null;
                if (button) {
                    button.textContent = "Закрыть подробности";
                }
            });
        }
    })();
    </script>';
    }

    if ($paginator) {
        $baseUrl = "?section=$section&sort=$sort&sort_value=$sort_value";
        $paginator_html = $paginator->renderLinks($baseUrl);
        if ($section === 'in_progress') {
            echo '<div id="inProgressLocalPaginator">' . $paginator_html . '</div>';
        } else {
            echo $paginator_html;
        }
    }
}

function renderNewOrderForm($queue_date, $form_data)
{
    $form_errors = $_SESSION['form_errors'] ?? [];
    $form_values = $_SESSION['form_data'] ?? $form_data ?? [];
    unset($_SESSION['form_errors'], $_SESSION['form_data']);

    $error_class = function ($field) use ($form_errors) {
        return isset($form_errors[$field]) ? 'is-invalid' : '';
    };

    $error_message = function ($field) use ($form_errors) {
        if (isset($form_errors[$field])) {
            return '<div class="invalid-feedback">' . htmlspecialchars(implode(', ', $form_errors[$field])) . '</div>';
        }
        return '';
    };

    echo '
    <div class="new-order-workspace">
    <div class="card metallic-card new-order-panel">
        <div class="card-body">
            <div class="new-order-hero">
                <div>
                    <h1 class="new-order-title">Новый заказ</h1>
                </div>
            </div>
            <form method="post" enctype="multipart/form-data" id="newOrderForm" onsubmit="showLoading()">
                <section class="new-order-section new-order-client-section">
                    <div class="new-order-section-head">
                        <span class="new-order-step">01</span>
                        <div>
                            <h2>Клиент</h2>
                            <p>Основные данные для карточки заказа</p>
                        </div>
                    </div>
                <div class="mb-2 new-order-field new-order-field-wide">
                    <label for="client_name" class="form-label">ФИО клиента *</label>
                    <input type="text" class="form-control ' . $error_class('client_name') . '" id="client_name" name="client_name" value="' . htmlspecialchars($form_values['client_name'] ?? '') . '" required>
                    ' . $error_message('client_name') . '
                </div>
                <div class="row new-order-grid new-order-client-grid">
                    <div class="col-md-6 mb-2 new-order-plate-field">
                        <label for="license_plate" class="form-label">Госномер</label>
                        <input type="text" class="form-control" id="license_plate" name="license_plate" value="' . htmlspecialchars($form_values['license_plate'] ?? '') . '">
                    </div>
                    <div class="col-md-6 mb-2 new-order-phone-field">
                        <label for="phone" class="form-label">Телефон</label>
                        <input type="tel" class="form-control ' . $error_class('phone') . '" id="phone" name="phone" value="' . htmlspecialchars($form_values['phone'] ?? '') . '">
                        ' . $error_message('phone') . '
                    </div>
                </div>
                </section>
                <section class="new-order-section new-order-details-section">
                    <div class="new-order-section-head">
                        <span class="new-order-step">02</span>
                        <div>
                            <h2>Детали</h2>
                            <p>Цвет, сумма, очередь и примечания мастеру</p>
                        </div>
                    </div>
                <div class="row new-order-grid">
                    <div class="col-md-6 mb-2">
                        <label for="color" class="form-label">Цвет</label>
                        <input type="text" class="form-control" id="color" name="color" value="' . htmlspecialchars($form_values['color'] ?? '') . '">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label for="price" class="form-label">Стоимость</label>
                        <input type="number" class="form-control ' . $error_class('price') . '" id="price" name="price" value="' . htmlspecialchars($form_values['price'] ?? '') . '" step="0.01" min="0" placeholder="Общая стоимость заказа">
                        ' . $error_message('price') . '
                    </div>
                </div>
                <input type="hidden" id="location" name="location" value="' . htmlspecialchars($form_values['location'] ?? 'Юзовская') . '">
                <div class="mb-2 new-order-field">
                    <label for="queue_date" class="form-label">Дата очереди</label>
                    <div class="input-group new-order-date-group">
                        <input type="date" class="form-control" id="queue_date" name="queue_date" data-min-date="' . date('Y-m-d') . '" min="' . date('Y-m-d') . '" value="' . ($queue_date ? date('Y-m-d', strtotime($queue_date)) : ($form_values['queue_date'] ?? '')) . '">
                        <button type="button" class="btn btn-primary" onclick="openCalendar()">Назначить</button>
                    </div>
                </div>
                <div class="mb-2 new-order-field">
                    <label for="notes" class="form-label">Примечание</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3">' . htmlspecialchars($form_values['notes'] ?? '') . '</textarea>
                </div>
                </section>
                <section class="new-order-section new-order-photo-section">
                    <div class="new-order-section-head">
                        <span class="new-order-step">03</span>
                        <div>
                            <h2>Фотографии</h2>
                        </div>
                    </div>
                <div class="mb-2">
                    <label class="form-label">Фотографии</label>
                    <div class="d-flex gap-2 flex-wrap new-order-photo-actions">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="newOrderCameraBtn">С камеры</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="newOrderGalleryBtn">Из галереи</button>
                    </div>
                    <small class="text-muted d-block mt-1">Можно загрузить до 10 фото</small>
                    <input type="file" class="form-control mt-2" id="newOrderCameraInput" name="photos[]" multiple accept="image/*" capture="environment" hidden>
                    <input type="file" class="form-control mt-2" id="newOrderGalleryInput" name="photos[]" multiple accept="image/*" hidden>
                    <div id="newOrderUploadStatus" class="mt-2" style="display:none;">
                        <div id="newOrderUploadText" class="small text-muted mb-1">Подготовка фото...</div>
                        <div class="progress" style="height:8px;">
                            <div id="newOrderUploadBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
                        </div>
                    </div>
                </div>
                </section>
                <div class="d-flex gap-2 new-order-actions">
                    <button type="submit" name="create_order" class="btn btn-primary">Создать заказ</button>
                    <button type="button" class="btn btn-info" onclick="viewCalendar()">Открыть календарь</button>
                </div>
            </form>
        </div>
    </div>
    </div>';
}

function renderSearchForm($conn)
{
    if (!$conn instanceof mysqli) {
        die("Ошибка подключения к базе данных");
    }
    $search_query = $_GET['query'] ?? '';
    echo '
    <div class="card metallic-card">
        <div class="card-body">
            <h5 class="card-title">Поиск заказов</h5>
            <form method="get" class="mb-3" id="searchOrdersForm" action="index.php">
                <input type="hidden" name="section" value="search">
                <div class="input-group">
                    <input type="text" class="form-control search-query-input" id="searchQueryInput" name="query" value="' . htmlspecialchars($search_query) . '" placeholder="Поиск по всем данным карточки заказа" autocomplete="off">
                    <button type="submit" class="btn btn-primary">Найти</button>
                </div>
            </form>
            <div id="searchResults">';
    if (trim($search_query) !== '') {
        $orders = searchOrders($search_query);
        $orders = array_values(array_filter($orders, function ($order) {
            return in_array((string)($order['status'] ?? ''), ['in_progress', 'completed', 'archive'], true);
        }));
        displayOrders($orders, 'Результаты поиска', 'search');
    } else {
        echo '<p class="text-muted">Введите текст для поиска</p>';
    }
    echo '
            </div>
        </div>
    </div>
    <script>
    (function () {
        const form = document.getElementById("searchOrdersForm");
        const input = document.getElementById("searchQueryInput");
        const results = document.getElementById("searchResults");

        if (!form || !input || !results || form.dataset.liveSearchInit === "1") {
            return;
        }
        form.dataset.liveSearchInit = "1";

        let debounceTimer = null;
        let requestCounter = 0;
        let activeController = null;

        function updateUrl(query) {
            const url = new URL(window.location.href);
            url.searchParams.set("section", "search");
            if (query) {
                url.searchParams.set("query", query);
            } else {
                url.searchParams.delete("query");
            }
            window.history.replaceState(null, "", url.toString());
        }

        function renderHint() {
            results.innerHTML = "<p class=\"text-muted\">Введите текст для поиска</p>";
        }

        function renderLoading() {
            results.innerHTML = "<p class=\"text-muted\">Поиск...</p>";
        }

        function renderError() {
            results.innerHTML = "<div class=\"alert alert-danger\">Ошибка загрузки результатов поиска</div>";
        }

        function loadResults(rawQuery) {
            const query = rawQuery.trim();
            updateUrl(query);

            if (!query) {
                renderHint();
                return;
            }

            const currentRequest = ++requestCounter;
            if (activeController) {
                activeController.abort();
            }
            activeController = new AbortController();
            const requestSignal = activeController.signal;
            renderLoading();

            const endpoint = "index.php?action=search_orders&section=search&statuses=in_progress,completed,archive&query=" + encodeURIComponent(query);
            fetch(endpoint, { credentials: "same-origin", signal: requestSignal, cache: "no-store" })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error("HTTP " + response.status);
                    }
                    return response.text();
                })
                .then(function (html) {
                    if (currentRequest !== requestCounter) {
                        return;
                    }
                    results.innerHTML = html;
                })
                .catch(function () {
                    if (currentRequest !== requestCounter) {
                        return;
                    }
                    if (requestSignal.aborted) {
                        return;
                    }
                    renderError();
                });
        }

        form.addEventListener("submit", function (event) {
            event.preventDefault();
            clearTimeout(debounceTimer);
            loadResults(input.value);
        });

        input.addEventListener("input", function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                loadResults(input.value);
            }, 250);
        });
    })();
    </script>';
}

function renderSettingsPage($conn)
{
    if (!$conn instanceof mysqli) {
        die("Ошибка подключения к базе данных");
    }
    $users = array_values(array_filter(getUsers(), static function (array $user): bool {
        if (function_exists('isProtectedDefaultAdminUsername')) {
            return !isProtectedDefaultAdminUsername((string)($user['username'] ?? ''));
        }
        return mb_strtolower(trim((string)($user['username'] ?? '')), 'UTF-8') !== 'admin';
    }));
    $totalUsers = count($users);
    $blockedUsers = count(array_filter($users, static function (array $user): bool {
        return (int)($user['is_blocked'] ?? 0) === 1;
    }));
    $adminUsers = count(array_filter($users, static function (array $user): bool {
        return (string)($user['role'] ?? '') === 'admin';
    }));
    $currentUserId = (int)($_SESSION['user_id'] ?? 0);
    $build_user_edit_args = static function (array $user): string {
        $args = [
            (int)($user['id'] ?? 0),
            (string)($user['username'] ?? ''),
            (string)($user['password'] ?? ''),
            (string)($user['role'] ?? 'user')
        ];
        return htmlspecialchars(json_encode($args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
    };
    echo '
    <div class="settings-container">
        <div class="settings-header">
            <h5 class="settings-title">Настройки</h5>
            <div class="settings-actions">
                <a href="reports.php" class="btn btn-gradient">📊 Отчеты</a>
                <a href="logs.php" class="btn btn-gradient">Логи</a>
            </div>
        </div>';
    if (!empty($GLOBALS['settings_success_message'])) {
        echo '<div class="alert alert-success">' . htmlspecialchars((string)$GLOBALS['settings_success_message']) . '</div>';
    }
    if (!empty($GLOBALS['settings_error_message'])) {
        echo '<div class="alert alert-danger">' . htmlspecialchars((string)$GLOBALS['settings_error_message']) . '</div>';
    }
    echo '
        <div class="settings-summary">
            <div class="settings-summary-card">
                <strong>' . $totalUsers . '</strong>
                <span>Всего учетных записей</span>
            </div>
            <div class="settings-summary-card">
                <strong>' . $adminUsers . '</strong>
                <span>Администраторов</span>
            </div>
            <div class="settings-summary-card">
                <strong>' . $blockedUsers . '</strong>
                <span>Заблокировано</span>
            </div>
        </div>
        
        <div class="settings-section">
            <div class="section-header">
                <h6 class="section-title">Добавить пользователя</h6>
            </div>
            <div class="settings-note">Эти учетные записи общие для всех разделов системы, включая страницу расходов.</div>
            <form method="post" class="user-form">
                <div class="settings-form-grid">
                    <div class="form-group">
                        <label for="new_username" class="form-label">Логин</label>
                        <input type="text" class="form-input" id="new_username" name="new_username" required autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label for="new_password" class="form-label">Пароль</label>
                        <input type="password" class="form-input" id="new_password" name="new_password" required autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label for="new_role" class="form-label">Роль</label>
                        <select class="form-select" id="new_role" name="new_role">
                            <option value="user">Пользователь</option>
                            <option value="admin">Админ</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="add_user" class="btn btn-gradient btn-full">Добавить пользователя</button>
            </form>
        </div>
        
        <div class="settings-section">
            <div class="section-header">
                <h6 class="section-title">Пользователи системы</h6>
                <span class="user-count">' . $totalUsers . ' ' . getDeclension($totalUsers, 'пользователь', 'пользователя', 'пользователей') . '</span>
            </div>
            <div class="settings-note">Доступ к расходам управляется этими же учетными записями. Отдельный блок пользователей расходов удалён как дубликат.</div>
            <div class="settings-toolbar">
                <div class="form-group">
                    <label for="settingsUserSearch" class="settings-toolbar-label">Поиск пользователя</label>
                    <input type="search" class="form-input" id="settingsUserSearch" placeholder="Логин или ID">
                </div>
                <div class="form-group">
                    <label for="settingsRoleFilter" class="settings-toolbar-label">Роль</label>
                    <select id="settingsRoleFilter" class="form-select">
                        <option value="">Все роли</option>
                        <option value="admin">Администраторы</option>
                        <option value="user">Пользователи</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="settingsStatusFilter" class="settings-toolbar-label">Статус</label>
                    <select id="settingsStatusFilter" class="form-select">
                        <option value="">Все статусы</option>
                        <option value="active">Активные</option>
                        <option value="blocked">Заблокированные</option>
                    </select>
                </div>
            </div>
            <div id="settingsUserFilterInfo" class="settings-muted">Показаны все пользователи.</div>
            
            <div class="users-table-container">
                <table class="users-table">
                    <colgroup>
                        <col class="users-table-col-id">
                        <col class="users-table-col-login">
                        <col class="users-table-col-role">
                        <col class="users-table-col-status">
                        <col class="users-table-col-actions">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Логин</th>
                            <th>Роль</th>
                            <th>Статус</th>
                            <th class="actions-header">Действия</th>
                        </tr>
                    </thead>
                    <tbody>';
    foreach ($users as $user) {
        $role_badge = $user['role'] === 'admin' ? 'badge-admin' : 'badge-user';
        $status_badge = (int)($user['is_blocked'] ?? 0) === 1 ? 'badge-blocked' : 'badge-active';
        $status_label = (int)($user['is_blocked'] ?? 0) === 1 ? 'Заблокирован' : 'Активен';
        $is_blocked = (int)($user['is_blocked'] ?? 0) === 1;
        $is_self = $currentUserId > 0 && $currentUserId === (int)$user['id'];
        $edit_args = $build_user_edit_args($user);
        echo '
                    <tr data-user-row data-user-search="' . htmlspecialchars(mb_strtolower((string)$user['username'], 'UTF-8')) . ' #' . htmlspecialchars((string)$user['id']) . '" data-user-role="' . htmlspecialchars((string)$user['role']) . '" data-user-status="' . ($is_blocked ? 'blocked' : 'active') . '">
                        <td class="user-id">#' . htmlspecialchars($user['id']) . '</td>
                        <td class="user-login"><span class="user-login-main">' . htmlspecialchars($user['username']) . '</span><span class="user-login-meta">' . ($is_blocked ? 'Вход закрыт' : 'Доступ открыт') . '</span></td>
                        <td class="user-role"><span class="badge ' . $role_badge . '">' . ($user['role'] === 'admin' ? 'Админ' : 'Пользователь') . '</span></td>
                        <td class="user-role"><span class="badge ' . $status_badge . '">' . $status_label . '</span></td>
                        <td class="user-actions"><div class="settings-action-group">
                            <button class="btn btn-edit" type="button" onclick="editUser(...JSON.parse(this.dataset.user))" data-user="' . $edit_args . '">Изменить</button>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="user_id" value="' . htmlspecialchars($user['id']) . '">
                                <input type="hidden" name="blocked_state" value="' . ($is_blocked ? '0' : '1') . '">
                                <button type="submit" name="toggle_user_block" class="btn btn-block ' . ($is_blocked ? 'is-blocked' : '') . '"' . ($is_self ? ' disabled title="Нельзя заблокировать свой текущий аккаунт"' : '') . '>' . ($is_blocked ? 'Разблокировать' : 'Заблокировать') . '</button>
                            </form>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="user_id" value="' . htmlspecialchars($user['id']) . '">
                                <button type="submit" name="delete_user" class="btn btn-delete" onclick="return confirm(\'Вы уверены, что хотите удалить пользователя?\');"' . ($is_self ? ' disabled title="Нельзя удалить свой текущий аккаунт"' : '') . '>Удалить</button>
                            </form>
                        </div></td>
                    </tr>';
    }
    echo '
                    </tbody>
                </table>
            </div>
            <div class="users-mobile-list">';
    foreach ($users as $user) {
        $role_badge = $user['role'] === 'admin' ? 'badge-admin' : 'badge-user';
        $status_badge = (int)($user['is_blocked'] ?? 0) === 1 ? 'badge-blocked' : 'badge-active';
        $status_label = (int)($user['is_blocked'] ?? 0) === 1 ? 'Заблокирован' : 'Активен';
        $is_blocked = (int)($user['is_blocked'] ?? 0) === 1;
        $is_self = $currentUserId > 0 && $currentUserId === (int)$user['id'];
        $edit_args = $build_user_edit_args($user);
        echo '
                <div class="user-mobile-card" data-user-card data-user-search="' . htmlspecialchars(mb_strtolower((string)$user['username'], 'UTF-8')) . ' #' . htmlspecialchars((string)$user['id']) . '" data-user-role="' . htmlspecialchars((string)$user['role']) . '" data-user-status="' . ($is_blocked ? 'blocked' : 'active') . '">
                    <div class="user-mobile-row">
                        <span class="user-mobile-label">ID</span>
                        <span class="user-mobile-value">#' . htmlspecialchars($user['id']) . '</span>
                    </div>
                    <div class="user-mobile-row">
                        <span class="user-mobile-label">Логин</span>
                        <span class="user-mobile-value">' . htmlspecialchars($user['username']) . '</span>
                    </div>
                    <div class="user-mobile-row">
                        <span class="user-mobile-label">Роль</span>
                        <span class="user-mobile-value"><span class="badge ' . $role_badge . '">' . ($user['role'] === 'admin' ? 'Админ' : 'Пользователь') . '</span></span>
                    </div>
                    <div class="user-mobile-row">
                        <span class="user-mobile-label">Статус</span>
                        <span class="user-mobile-value"><span class="badge ' . $status_badge . '">' . $status_label . '</span></span>
                    </div>
                    <div class="user-mobile-actions">
                        <button class="btn btn-edit" type="button" onclick="editUser(...JSON.parse(this.dataset.user))" data-user="' . $edit_args . '">Изменить</button>
                        <form method="post" style="flex: 1;">
                            <input type="hidden" name="user_id" value="' . htmlspecialchars($user['id']) . '">
                            <input type="hidden" name="blocked_state" value="' . ($is_blocked ? '0' : '1') . '">
                            <button type="submit" name="toggle_user_block" class="btn btn-block w-100 ' . ($is_blocked ? 'is-blocked' : '') . '"' . ($is_self ? ' disabled title="Нельзя заблокировать свой текущий аккаунт"' : '') . '>' . ($is_blocked ? 'Разблокировать' : 'Заблокировать') . '</button>
                        </form>
                        <form method="post" style="flex: 1;">
                            <input type="hidden" name="user_id" value="' . htmlspecialchars($user['id']) . '">
                            <button type="submit" name="delete_user" class="btn btn-delete w-100" onclick="return confirm(\'Вы уверены, что хотите удалить пользователя?\');"' . ($is_self ? ' disabled title="Нельзя удалить свой текущий аккаунт"' : '') . '>Удалить</button>
                        </form>
                    </div>
                </div>';
    }
    echo '
            </div>
        </div>
    </div>';
}

function getDeclension($number, $one, $two, $five)
{
    $number = abs($number);
    $number = $number % 100;
    if ($number >= 5 && $number <= 20) {
        return $five;
    }
    $number = $number % 10;
    if ($number == 1) {
        return $one;
    }
    if ($number >= 2 && $number <= 4) {
        return $two;
    }
    return $five;
}

if (!function_exists('getExpenseTypes')) {
    function getExpenseTypes($conn)
    {
        $query = "SELECT DISTINCT type FROM expenses ORDER BY type";
        $result = $conn->query($query);
        $types = [];
        while ($row = $result->fetch_assoc()) {
            $types[] = $row['type'];
        }

        // Если в базе нет типов расходов, возвращаем часто используемые типы по умолчанию
        if (empty($types)) {
            $default_types = [
                'Аренда',
                'Зарплата',
                'Коммунальные услуги',
                'Оборудование',
                'Материалы',
                'Транспорт',
                'Реклама',
                'Прочее'
            ];
            return $default_types;
        }

        return $types;
    }
}

function renderExpensesPage($conn)
{
    // Обработка добавления нового расхода
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
        $expense_type = trim($_POST['expense_type']);
        $amount = floatval($_POST['amount']);
        $date = $_POST['date'];
        $location = $_POST['location'] ?? '';
        $notes = trim($_POST['notes']);

        if (!empty($expense_type) && $amount > 0 && !empty($date)) {
            $stmt = $conn->prepare("INSERT INTO expenses (type, amount, date, location, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sdsss", $expense_type, $amount, $date, $location, $notes);
            $result = $stmt->execute();
            $stmt->close();

            if ($result) {
                $success_message = "Расход успешно добавлен";
            } else {
                $error_message = "Ошибка при добавлении расхода: " . $conn->error;
            }
        } else {
            $error_message = "Заполните все обязательные поля правильно";
        }
    }

    // Обработка удаления расхода
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_expense'])) {
        $expense_id = intval($_POST['expense_id']);

        if ($expense_id > 0) {
            $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");
            $stmt->bind_param("i", $expense_id);
            $result = $stmt->execute();
            $stmt->close();

            if ($result) {
                $success_message = "Расход успешно удален";
            } else {
                $error_message = "Ошибка при удалении расхода: " . $conn->error;
            }
        } else {
            $error_message = "Неверный ID расхода";
        }
    }

    // Обработка фильтрации по дате и типу
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    $expense_type_filter = $_GET['expense_type'] ?? '';
    $location_filter = $_GET['location'] ?? '';

    // Получение всех типов расходов для выпадающего списка
    $expense_types = getExpenseTypes($conn);
    $locations = [];
    $locations_result = $conn->query("SELECT DISTINCT location FROM expenses WHERE location IS NOT NULL AND location != '' ORDER BY location ASC");
    if ($locations_result) {
        while ($row = $locations_result->fetch_assoc()) {
            $locations[] = $row['location'];
        }
    }

    // Получение всех расходов для отображения с фильтрацией
    $where_conditions = [];
    $params = [];
    $types = "";

    if (!empty($start_date)) {
        $where_conditions[] = "date >= ?";
        $params[] = $start_date;
        $types .= "s";
    }

    if (!empty($end_date)) {
        $where_conditions[] = "date <= ?";
        $params[] = $end_date;
        $types .= "s";
    }

    if (!empty($expense_type_filter)) {
        $where_conditions[] = "type = ?";
        $params[] = $expense_type_filter;
        $types .= "s";
    }

    if (!empty($location_filter)) {
        $where_conditions[] = "location = ?";
        $params[] = $location_filter;
        $types .= "s";
    }

    // Build the query
    $query = "SELECT * FROM expenses";
    if (!empty($where_conditions)) {
        $query .= " WHERE " . implode(" AND ", $where_conditions);
    }
    $query .= " ORDER BY date DESC, created_at DESC";

    // Prepare and execute the statement
    if (!empty($params)) {
        $expenses_result = $conn->prepare($query);
        $expenses_result->bind_param($types, ...$params);
        $expenses_result->execute();
        $expenses = $expenses_result->get_result();
        $expenses_array = [];
        while ($row = $expenses->fetch_assoc()) {
            $expenses_array[] = $row;
        }
        $expenses = $expenses_array;
    } else {
        $expenses_result = $conn->query($query);
        $expenses = [];
        while ($row = $expenses_result->fetch_assoc()) {
            $expenses[] = $row;
        }
    }


    // Функция для получения общего количества и суммы расходов
    if (!function_exists('getTotalExpenses')) {
        function getTotalExpenses($conn, $start_date = '', $end_date = '', $expense_type = '', $location = '')
        {
            $where_conditions = [];
            $params = [];
            $types = "";

            if (!empty($start_date)) {
                $where_conditions[] = "date >= ?";
                $params[] = $start_date;
                $types .= "s";
            }

            if (!empty($end_date)) {
                $where_conditions[] = "date <= ?";
                $params[] = $end_date;
                $types .= "s";
            }

            if (!empty($expense_type)) {
                $where_conditions[] = "type = ?";
                $params[] = $expense_type;
                $types .= "s";
            }

            if (!empty($location)) {
                $where_conditions[] = "location = ?";
                $params[] = $location;
                $types .= "s";
            }

            $query = "SELECT COUNT(*) as count, SUM(amount) as total FROM expenses";
            if (!empty($where_conditions)) {
                $query .= " WHERE " . implode(" AND ", $where_conditions);
            }

            if (!empty($params)) {
                $result = $conn->prepare($query);
                $result->bind_param($types, ...$params);
                $result->execute();
                return $result->get_result()->fetch_assoc();
            } else {
                $result = $conn->query($query);
                return $result->fetch_assoc();
            }
        }
    }

    $total_expenses = getTotalExpenses($conn, $start_date, $end_date, $expense_type_filter, $location_filter);

    ?>
    <style>
        .expenses-page .table-container {
            width: 100%;
            overflow-x: auto;
        }
        .expenses-mobile-list {
            display: none;
        }
        .expense-mobile-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 10px;
            cursor: pointer;
        }
        .expense-mobile-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }
        .expense-mobile-title {
            color: #ffffff;
            font-weight: 600;
            word-break: break-word;
        }
        .expense-mobile-amount {
            color: #48cae4;
            font-weight: 700;
            white-space: nowrap;
        }
        .expense-mobile-chevron {
            color: #b0b0b0;
            font-size: 12px;
            margin-left: 6px;
            transition: transform 0.2s ease;
        }
        .expense-mobile-card.is-open .expense-mobile-chevron {
            transform: rotate(180deg);
        }
        .expense-mobile-details {
            display: none;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }
        .expense-mobile-card.is-open .expense-mobile-details {
            display: block;
        }
        .expense-mobile-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 6px;
            font-size: 14px;
        }
        .expense-mobile-row:last-child {
            margin-bottom: 0;
        }
        .expense-mobile-label {
            color: #b0b0b0;
            min-width: 90px;
            flex-shrink: 0;
        }
        .expense-mobile-value {
            color: #ffffff;
            text-align: right;
            word-break: break-word;
        }
        .expense-notes {
            white-space: pre-wrap;
            word-break: break-word;
        }
        .expense-mobile-total {
            font-weight: 700;
            color: #48cae4;
        }
        @media (max-width: 992px) {
            .expenses-page {
                padding-left: 0 !important;
                padding-right: 0 !important;
            }
            .expenses-page h1 {
                font-size: 22px;
                margin-bottom: 12px;
            }
            .expenses-page .metallic-card {
                margin-bottom: 8px;
                padding: 8px;
                border-radius: 8px;
            }
            .expenses-page .card-body {
                padding: 8px;
            }
            .expenses-page .row.g-3,
            .expenses-page .row.mb-3 {
                --bs-gutter-x: 0.5rem;
                --bs-gutter-y: 0.5rem;
            }
            .expenses-page .btn {
                min-height: 44px;
            }
            .expenses-page .expense-filter-actions {
                display: grid !important;
                grid-template-columns: 1fr;
                gap: 8px;
            }
            .expenses-page .expense-filter-actions .btn,
            .expenses-page .expense-filter-actions a.btn {
                width: 100%;
                margin: 0 !important;
            }
            .expenses-page .table-container {
                display: none;
            }
            .expenses-mobile-list {
                display: block;
            }
            .expenses-page .form-control {
                font-size: 16px;
            }
        }
    </style>
    <div class="container expenses-page">
        <h1 class="text-center">Управление расходами</h1>
        <div class="d-flex justify-content-end mb-2">
            <a href="index.php?section=new_order" class="btn btn-secondary btn-sm">Главная</a>
        </div>
        
        <?php if (isset($success_message)) : ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)) : ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <!-- Фильтр по дате и типу -->
        <div class="card metallic-card mb-4">
            <div class="card-body">
                <button class="btn btn-secondary w-100 d-flex justify-content-between align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#expensesFilterCollapse" aria-expanded="false" aria-controls="expensesFilterCollapse">
                    <span>Фильтр</span>
                    <span>▼</span>
                </button>
                <div class="collapse mt-3" id="expensesFilterCollapse">
                    <form method="get" class="row g-3 expense-filter-form">
                        <input type="hidden" name="section" value="expenses">
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">Начальная дата</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $_GET['start_date'] ?? ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="end_date" class="form-label">Конечная дата</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $_GET['end_date'] ?? ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="expense_type_filter" class="form-label">Статья расхода</label>
                            <select class="form-control" id="expense_type_filter" name="expense_type">
                                <option value="">Все статьи</option>
                                <?php foreach ($expense_types as $type) : ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo (isset($_GET['expense_type']) && $_GET['expense_type'] == $type) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="location_filter" class="form-label">Локация</label>
                            <select class="form-control" id="location_filter" name="location">
                                <option value="">Все локации</option>
                                <?php foreach ($locations as $location) : ?>
                                    <option value="<?php echo htmlspecialchars($location); ?>" <?php echo $location_filter === $location ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($location); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 d-flex justify-content-end expense-filter-actions">
                            <button type="submit" class="btn btn-primary">Применить фильтры</button>
                            <a href="?section=expenses" class="btn btn-secondary ms-2">Сбросить</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Форма добавления расхода -->
        <div class="card metallic-card mb-4">
            <div class="card-body">
                <button class="btn btn-secondary w-100 d-flex justify-content-between align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#expensesAddCollapse" aria-expanded="false" aria-controls="expensesAddCollapse">
                    <span>Добавить новый расход</span>
                    <span>▼</span>
                </button>
                <div class="collapse mt-3" id="expensesAddCollapse">
                    <form method="post">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="expense_type" class="form-label">Статья расхода *</label>
                                <div class="autocomplete">
                                    <input type="text" class="form-control" id="expense_type" name="expense_type" placeholder="Введите статью расхода" required>
                                    <div id="expense_type_list" class="autocomplete-items"></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="amount" class="form-label">Сумма *</label>
                                <input type="number" class="form-control" id="amount" name="amount" step="0.01" placeholder="Введите сумму" min="0" required>
                            </div>
                            <div class="col-md-3">
                                <label for="date" class="form-label">Дата *</label>
                                <input type="date" class="form-control" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-2">
                                <label for="location" class="form-label">Локация</label>
                                <input type="text" class="form-control" id="location" name="location" list="expenseLocationOptions" placeholder="Введите локацию">
                                <datalist id="expenseLocationOptions">
                                    <?php foreach ($locations as $location) : ?>
                                        <option value="<?php echo htmlspecialchars($location); ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="notes" class="form-label">Примечание</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Дополнительная информация" data-autogrow="true"></textarea>
                            </div>
                        </div>
                        <button type="submit" name="add_expense" class="btn btn-primary w-100">Добавить расход</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Таблица расходов -->
                <div class="card metallic-card">
                    <div class="card-body">
                        <h5>Список расходов</h5>
                        <?php if (empty($expenses)) : ?>
                            <p>Нет записей о расходах</p>
                        <?php else : ?>
                            <div class="table-container">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Статья</th>
                                            <th>Сумма</th>
                                            <th>Дата</th>
                                            <th>Локация</th>
                                            <th>Примечание</th>
                                            <th>Создано</th>
                                            <th>Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($expenses as $expense) : ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($expense['type']); ?></td>
                                                <td><?php echo number_format($expense['amount'], 2); ?> ₽</td>
                                                <td><?php echo date('d.m.Y', strtotime($expense['date'])); ?></td>
                                                <td><?php echo htmlspecialchars($expense['location'] ?? ''); ?></td>
                                                <td><div class="expense-notes"><?php echo htmlspecialchars($expense['notes'] ?? ''); ?></div></td>
                                                <td><?php echo date('d.m.Y H:i', strtotime($expense['created_at'])); ?></td>
                                                <td>
                                                    <form method="post" style="display:inline;" onsubmit="return confirm('Вы уверены, что хотите удалить этот расход?');">
                                                        <input type="hidden" name="expense_id" value="<?php echo $expense['id']; ?>">
                                                        <button type="submit" name="delete_expense" class="btn btn-danger btn-sm">Удалить</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="total-row">
                                            <td colspan="1">Итого</td>
                                            <td><?php echo number_format($total_expenses['total'], 2); ?> ₽</td>
                                            <td colspan="5"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="expenses-mobile-list">
                                <?php foreach ($expenses as $expense) : ?>
                                    <div class="expense-mobile-card" onclick="toggleExpenseCard(this)">
                                        <div class="expense-mobile-card-header">
                                            <span class="expense-mobile-title"><?php echo htmlspecialchars($expense['type']); ?></span>
                                            <span class="expense-mobile-amount"><?php echo number_format($expense['amount'], 2); ?> ₽ <span class="expense-mobile-chevron">▼</span></span>
                                        </div>
                                        <div class="expense-mobile-details">
                                            <div class="expense-mobile-row">
                                                <span class="expense-mobile-label">Дата</span>
                                                <span class="expense-mobile-value"><?php echo date('d.m.Y', strtotime($expense['date'])); ?></span>
                                            </div>
                                            <div class="expense-mobile-row">
                                                <span class="expense-mobile-label">Локация</span>
                                                <span class="expense-mobile-value"><?php echo htmlspecialchars($expense['location'] ?? ''); ?></span>
                                            </div>
                                            <div class="expense-mobile-row">
                                                <span class="expense-mobile-label">Примечание</span>
                                                <span class="expense-mobile-value expense-notes"><?php echo htmlspecialchars($expense['notes'] ?? ''); ?></span>
                                            </div>
                                            <div class="expense-mobile-row">
                                                <span class="expense-mobile-label">Создано</span>
                                                <span class="expense-mobile-value"><?php echo date('d.m.Y H:i', strtotime($expense['created_at'])); ?></span>
                                            </div>
                                            <form method="post" onsubmit="return confirm('Вы уверены, что хотите удалить этот расход?');" onclick="event.stopPropagation();">
                                                <input type="hidden" name="expense_id" value="<?php echo $expense['id']; ?>">
                                                <button type="submit" name="delete_expense" class="btn btn-danger btn-sm w-100">Удалить</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="expense-mobile-card">
                                    <div class="expense-mobile-row">
                                        <span class="expense-mobile-label">Итого</span>
                                        <span class="expense-mobile-value expense-mobile-total"><?php echo number_format($total_expenses['total'], 2); ?> ₽</span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
    </div>
    <script>
        function toggleExpenseCard(cardEl) {
            if (!cardEl) return;
            cardEl.classList.toggle('is-open');
        }
    </script>
    <script>
        // Autocomplete functionality for expense types
        document.addEventListener('DOMContentLoaded', function() {
            function autoGrowTextarea(textarea) {
                if (!textarea) return;
                textarea.style.height = 'auto';
                textarea.style.overflow = 'hidden';
                textarea.style.resize = 'none';
                textarea.style.height = textarea.scrollHeight + 'px';
            }

            function initAutoGrow() {
                const textareas = document.querySelectorAll('textarea[data-autogrow="true"]');
                textareas.forEach(textarea => {
                    autoGrowTextarea(textarea);
                    textarea.addEventListener('input', function() {
                        autoGrowTextarea(textarea);
                    });
                });
            }

            initAutoGrow();

            const expenseTypeInput = document.getElementById('expense_type');
            const expenseTypeList = document.getElementById('expense_type_list');
            let expenseTypes = <?php echo json_encode($expense_types); ?>;
            let currentIndex = -1;
            let isFocused = false;
            
            // Initialize autocomplete
            function initAutocomplete() {
                if (!expenseTypeInput || !expenseTypeList) return;
                
                // Clear previous event listeners
                expenseTypeInput.removeEventListener('input', handleInput);
                expenseTypeInput.removeEventListener('keydown', handleKeydown);
                expenseTypeInput.removeEventListener('focus', handleFocus);
                expenseTypeInput.removeEventListener('blur', handleBlur);
                
                // Add event listeners
                expenseTypeInput.addEventListener('input', handleInput);
                expenseTypeInput.addEventListener('keydown', handleKeydown);
                expenseTypeInput.addEventListener('focus', handleFocus);
                expenseTypeInput.addEventListener('blur', handleBlur);
            }
            
            function handleInput(e) {
                const value = e.target.value.toLowerCase();
                currentIndex = -1;
                
                if (value.length === 0) {
                    hideSuggestions();
                    return;
                }
                
                // Filter expense types
                const filteredTypes = expenseTypes.filter(type =>
                    type.toLowerCase().includes(value)
                );
                
                if (filteredTypes.length > 0) {
                    showSuggestions(filteredTypes);
                } else {
                    hideSuggestions();
                }
            }
            
            function handleKeydown(e) {
                const items = expenseTypeList.getElementsByTagName('div');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    currentIndex = Math.min(currentIndex + 1, items.length - 1);
                    updateActiveItem(items);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    currentIndex = Math.max(currentIndex - 1, -1);
                    updateActiveItem(items);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (currentIndex >= 0 && items[currentIndex]) {
                        selectItem(items[currentIndex].textContent);
                    }
                } else if (e.key === 'Escape') {
                    hideSuggestions();
                }
            }
            
            function handleFocus() {
                isFocused = true;
                // Show suggestions when focused (optional)
            }
            
            function handleBlur() {
                isFocused = false;
                // Hide suggestions with delay to allow click events to register
                setTimeout(() => {
                    if (!isFocused) {
                        hideSuggestions();
                    }
                }, 150);
            }
            
            function updateActiveItem(items) {
                // Remove active class from all items
                for (let i = 0; i < items.length; i++) {
                    items[i].classList.remove('autocomplete-active');
                }
                
                // Add active class to current item
                if (currentIndex >= 0 && items[currentIndex]) {
                    items[currentIndex].classList.add('autocomplete-active');
                    items[currentIndex].scrollIntoView({ block: 'nearest' });
                }
            }
            
            function showSuggestions(types) {
                expenseTypeList.innerHTML = '';
                
                types.forEach(type => {
                    const div = document.createElement('div');
                    div.textContent = type;
                    div.addEventListener('click', function() {
                        selectItem(type);
                    });
                    expenseTypeList.appendChild(div);
                });
                
                expenseTypeList.style.display = 'block';
            }
            
            function hideSuggestions() {
                expenseTypeList.style.display = 'none';
            }
            
            function selectItem(value) {
                expenseTypeInput.value = value;
                hideSuggestions();
                expenseTypeInput.focus();
            }
            
            // Initialize when page loads
            initAutocomplete();
            
            // Handle click outside to close suggestions
            document.addEventListener('click', function(e) {
                if (e.target !== expenseTypeInput && !expenseTypeInput.contains(e.target) &&
                    e.target !== expenseTypeList && !expenseTypeList.contains(e.target)) {
                    hideSuggestions();
                }
            });
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
    <?php
}


