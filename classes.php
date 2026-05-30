<?php

/**
 * Константы приложения
 * Хранит все конфигурационные значения, заменяя магические числа и хардкод
 */
class AppConstants
{
    // ==================== ИЗОБРАЖЕНИЯ ====================
    const IMAGE_MAX_WIDTH = 720;
    const IMAGE_QUALITY = 45;
    const IMAGE_MAX_SIZE = 15728640; // 15MB в байтах
    const MAX_PHOTOS_PER_ORDER = 10;
    const TELEGRAM_ENABLED = false;
    const TELEGRAM_NOTIFY_MAX_PHOTOS = 3;
    const TELEGRAM_CONNECT_TIMEOUT = 3;
    const TELEGRAM_MESSAGE_TIMEOUT = 5;
    const TELEGRAM_MEDIA_TIMEOUT = 8;
    const UPLOAD_DIR = 'Uploads/';
    // Разрешенные типы изображений
    const ALLOWED_IMAGE_TYPES = [
        IMAGETYPE_JPEG,
        IMAGETYPE_PNG,
        IMAGETYPE_GIF
    ];
    const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    // ==================== ЛОКАЦИИ ====================
    const REMOTE_LOCATIONS = [];
    const ALL_LOCATIONS = [];

    /**
     * Проверяет, является ли локация удаленной
     *
     * @param string $location Локация для проверки
     * @return bool
     */
    public static function isRemoteLocation($location)
    {
        return in_array($location, self::REMOTE_LOCATIONS);
    }

    // ==================== СТАТУСЫ ЗАКАЗОВ ====================
    const ORDER_STATUS_NEW = 'new';
    const ORDER_STATUS_IN_PROGRESS = 'in_progress';
    const ORDER_STATUS_COMPLETED = 'completed';
    const ORDER_STATUS_ARCHIVE = 'archive';

    /**
     * Получить все возможные статусы заказов
     *
     * @return array
     */
    public static function getOrderStatuses()
    {
        return [
            self::ORDER_STATUS_NEW,
            self::ORDER_STATUS_IN_PROGRESS,
            self::ORDER_STATUS_COMPLETED,
            self::ORDER_STATUS_ARCHIVE
        ];
    }

    // ==================== ПРАВИЛА РАСЧЕТА ЗАРПЛАТЫ ====================
    /**
     * Правила расчета зарплаты для различных услуг
     * Ключ - название услуги
     * Значение - либо фиксированная сумма за единицу (int), либо процент от стоимости (float)
     */
    const SALARY_RULES = [
        'Покрасить диск' => 250,
        'Правка' => 0.25,
        'Шиномонтаж' => 0.25,
        'Песочка диск' => 250,
        'Сварка' => 0.25,
        'Проточка тормозных дисков' => 0.25,
        'ЧПУ Алмазная проточка' => 250,
        'Покраска суппортов' => 250,
        'Покраска ступиц' => 250,
        'Покраска насадок глушителя' => 250,
        'Покраска поводков' => 250,
        'продажа болтов на диски' => 0.25,
        'Аргоновая наварка' => 0.25,
        'Димет Алюминиевое напыление' => 250,
    ];

    // ==================== ПАГИНАЦИЯ ====================
    const ITEMS_PER_PAGE = 20;
    const ITEMS_PER_PAGE_MOBILE = 10;

    // ==================== СТАТУСЫ ЗАРПЛАТЫ ====================
    const SALARY_STATUS_WAITING_PAYMENT = 'waiting_payment';
    const SALARY_STATUS_PAID = 'paid';

    // ==================== СТАТУСЫ СКЛАДА ====================
    const STORAGE_STATUS_STORED = 'На хранении';
    const STORAGE_STATUS_ISSUED = 'Выдано';

    // ==================== ВАЛИДАЦИЯ ====================
    const PHONE_REGEX = '/^\+?[0-9]{10,15}$/';
    const ORDER_ID_REGEX = '/^#?(\d+)$/';

    // ==================== РАЗМЕРЫ ДИСКОВ ====================
    /**
     * Получить список доступных размеров дисков (R13-R25)
     *
     * @return array
     */
    public static function getDiskSizes()
    {
        $sizes = [];
        for ($i = 13; $i <= 25; $i++) {
            $sizes[] = "R{$i}";
        }
        return $sizes;
    }

    // ==================== СЛУЖЕБНЫЕ МЕТОДЫ ====================
    /**
     * Получить правило расчета зарплаты для услуги
     *
     * @param string $serviceName Название услуги
     * @return int|float|null Правило расчета или null если не найдено
     */
    public static function getSalaryRule($serviceName)
    {
        return self::SALARY_RULES[$serviceName] ?? null;
    }

    /**
     * Проверяет, является ли правило фиксированной суммой
     *
     * @param mixed $rule Правило расчета
     * @return bool
     */
    public static function isFixedAmountRule($rule)
    {
        return is_int($rule);
    }
}

/**
 * Менеджер транзакций базы данных
 * Упрощает работу с транзакциями и обеспечивает их целостность
 */
class TransactionManager
{
    private $conn;

    /**
     * Конструктор
     *
     * @param mysqli $conn Соединение с базой данных
     */
    public function __construct($conn)
    {
        if (!$conn instanceof mysqli) {
            throw new InvalidArgumentException('Передан неверный тип соединения с БД');
        }
        $this->conn = $conn;
    }

    /**
     * Выполнение операций в транзакции
     * Автоматически выполняет commit при успехе или rollback при ошибке
     *
     * @param callable $callback Функция-колбэк с операциями БД
     * @return mixed Результат выполнения колбэка
     * @throws Exception При ошибке выполнения
     */
    public function transaction(callable $callback)
    {
        // Начинаем транзакцию
        if (!$this->conn->begin_transaction()) {
            throw new Exception('Не удалось начать транзакцию: ' . $this->conn->error);
        }

        try {
            // Выполняем операции
            $result = $callback($this->conn);
            // Если все успешно - коммитим
            if (!$this->conn->commit()) {
                throw new Exception('Не удалось зафиксировать транзакцию: ' . $this->conn->error);
            }

            return $result;
        } catch (Exception $e) {
            // При ошибке - откатываем
            $this->conn->rollback();
            // Логируем ошибку
            error_log("Transaction failed: " . $e->getMessage());
            error_log("Rollback executed");
            // Пробрасываем исключение дальше
            throw $e;
        }
    }

    /**
     * Проверка, находится ли соединение в транзакции
     *
     * @return bool
     */
    public function inTransaction()
    {
        // В MySQL нет прямого способа проверить, но можно попробовать
        // через SQL команду
        $result = $this->conn->query("SELECT @@autocommit");
        if ($result) {
            $row = $result->fetch_row();
            // autocommit = 0 означает транзакцию
            return $row[0] == 0;
        }
        return false;
    }

    /**
     * Получить соединение с БД
     *
     * @return mysqli
     */
    public function getConnection()
    {
        return $this->conn;
    }
}

class Paginator
{
    private $totalItems;
    private $itemsPerPage;
    private $currentPage;

    public function __construct($totalItems, $itemsPerPage = 20, $currentPage = 1)
    {
        $this->totalItems = $totalItems;
        $this->itemsPerPage = $itemsPerPage;
        $this->currentPage = max(1, (int)$currentPage);
    }

    public function getOffset()
    {
        return ($this->currentPage - 1) * $this->itemsPerPage;
    }

    public function getLimit()
    {
        return $this->itemsPerPage;
    }

    public function getTotalPages()
    {
        return ceil($this->totalItems / $this->itemsPerPage);
    }

    public function renderLinks($baseUrl)
    {
        $totalPages = $this->getTotalPages();
        if ($totalPages <= 1) {
            return '';
        }

        $output = '<nav><ul class="pagination">';

        // Prev
        if ($this->currentPage > 1) {
            $output .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . ($this->currentPage - 1) . '">«</a></li>';
        } else {
            $output .= '<li class="page-item disabled"><span class="page-link">«</span></li>';
        }

        // Numbers
        for ($i = 1; $i <= $totalPages; $i++) {
            if ($i == $this->currentPage) {
                $output .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
            } else {
                $output .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . $i . '">' . $i . '</a></li>';
            }
        }

        // Next
        if ($this->currentPage < $totalPages) {
            $output .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . ($this->currentPage + 1) . '">»</a></li>';
        } else {
            $output .= '<li class="page-item disabled"><span class="page-link">»</span></li>';
        }

        $output .= '</ul></nav>';
        return $output;
    }
}

class CacheService
{
    private $cacheDir = __DIR__ . '/cache';

    public function __construct()
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }

    private function getCacheFile($key)
    {
        return $this->cacheDir . '/' . md5($key) . '.cache';
    }

    public function get($key)
    {
        $file = $this->getCacheFile($key);
        if (!file_exists($file)) {
            return null;
        }

        $content = file_get_contents($file);
        $data = unserialize($content);

        if (time() > $data['expires']) {
            unlink($file);
            return null;
        }

        return $data['value'];
    }

    public function set($key, $value, $ttl = 3600)
    {
        $file = $this->getCacheFile($key);
        $data = [
            'expires' => time() + $ttl,
            'value'   => $value,
        ];
        file_put_contents($file, serialize($data));
    }

    public function remember($key, $ttl, callable $callback)
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
        // Кеширование отключено, чтобы изменения на сервере отображались немедленно.
        // Функция теперь всегда выполняет запрос к базе данных, игнорируя кеш.
        return $callback();
    }

    public function clear($key)
    {
        $file = $this->getCacheFile($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public function clearAll()
    {
        $files = glob($this->cacheDir . '/*.cache');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}

/**
 * Централизованный обработчик ошибок и исключений.
 * Логирует ошибки и отправляет уведомления при критических сбоях.
 */
class ErrorHandler
{
    private $logFile;
    private $telegramService;

    /**
     * Конструктор.
     * @param string $logFile Путь к файлу логов.
     */
    public function __construct($logFile = __DIR__ . '/debug.log')
    {
        $this->logFile = $logFile;
        // Инициализируем TelegramService, если он доступен
        if (class_exists('TelegramService')) {
            $this->telegramService = new TelegramService();
        }
    }

    /**
     * Регистрирует обработчики ошибок и исключений.
     */
    public static function register()
    {
        $handler = new self();
        set_error_handler([$handler, 'handleError']);
        set_exception_handler([$handler, 'handleException']);
        register_shutdown_function([$handler, 'handleShutdown']);
    }

    /**
     * Обрабатывает ошибки PHP.
     */
    public function handleError($severity, $message, $file, $line)
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $context = ['file' => $file, 'line' => $line];
        $this->log($message, 'error', $context);

        // Не прерываем стандартный обработчик PHP
        return false;
    }

    /**
     * Обрабатывает неперехваченные исключения.
     */
    public function handleException(Throwable $e)
    {
        $context = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
        $this->log($e->getMessage(), 'critical', $context);
    }

    /**
     * Обрабатывает фатальные ошибки при завершении работы скрипта.
     */
    public function handleShutdown()
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            $context = ['file' => $error['file'], 'line' => $error['line']];
            $this->log($error['message'], 'fatal', $context);
        }
    }

    /**
     * Логирует сообщение.
     * @param string $message Сообщение об ошибке.
     * @param string $level Уровень ошибки (info, warning, error, critical, fatal).
     * @param array $context Дополнительный контекст.
     */
    public function log($message, $level = 'error', $context = [])
    {
        try {
            $logEntry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'level' => strtoupper($level),
                'message' => $message,
                'context' => $context,
                'user' => $_SESSION['username'] ?? 'guest',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
                'url' => $_SERVER['REQUEST_URI'] ?? 'UNKNOWN'
            ];

            $logString = json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
            file_put_contents($this->logFile, $logString, FILE_APPEND);

            // Отправка уведомления в Telegram при критических ошибках
            if (in_array($level, ['critical', 'fatal']) && $this->telegramService) {
                $tgMessage = "<b>🚨 " . strtoupper($level) . " Error</b>\n\n" .
                             "<b>Message:</b> " . htmlspecialchars($message) . "\n" .
                             "<b>File:</b> " . htmlspecialchars($context['file'] ?? 'N/A') . "\n" .
                             "<b>Line:</b> " . ($context['line'] ?? 'N/A');
                $this->telegramService->sendMessage($tgMessage);
            }
        } catch (Exception $e) {
            // Если даже логирование не удалось, пишем в системный лог
            error_log("Failed to write to custom log file: " . $e->getMessage());
        }
    }
}

/**
 * Сервис для работы с изображениями
 * Инкапсулирует всю логику загрузки, валидации и сжатия изображений
 */
class ImageService
{
    private $uploadDir;
    private $maxWidth;
    private $quality;
    private $maxFileSize;
    private $allowedTypes;
    private $allowedExtensions;

    /**
     * Конструктор
     *
     * @param string $uploadDir Директория для загрузки (относительный путь)
     * @param int $maxWidth Максимальная ширина после сжатия
     * @param int $quality Качество JPEG (0-100)
     * @param int $maxFileSize Максимальный размер файла в байтах
     */
    public function __construct($uploadDir = null, $maxWidth = null, $quality = null, $maxFileSize = null)
    {
        $this->uploadDir = $uploadDir ?? AppConstants::UPLOAD_DIR;
        $this->maxWidth = $maxWidth ?? AppConstants::IMAGE_MAX_WIDTH;
        $this->quality = $quality ?? AppConstants::IMAGE_QUALITY;
        $this->maxFileSize = $maxFileSize ?? AppConstants::IMAGE_MAX_SIZE;
        $this->allowedTypes = AppConstants::ALLOWED_IMAGE_TYPES;
        $this->allowedExtensions = AppConstants::ALLOWED_IMAGE_EXTENSIONS;
        if (defined('IMAGETYPE_WEBP')) {
            $this->allowedTypes[] = IMAGETYPE_WEBP;
        }
        if (!in_array('webp', $this->allowedExtensions, true)) {
            $this->allowedExtensions[] = 'webp';
        }
        // Создаем директорию, если её нет
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }

    /**
     * Валидация изображения
     *
     * @param array $file Массив $_FILES['field_name']
     * @return bool
     * @throws Exception При невалидном изображении
     */
    public function validateImage($file)
    {
        // Проверка наличия файла
        if (empty($file) || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('Файл не был загружен');
        }

        // Проверка размера
        if ($file['size'] > $this->maxFileSize) {
            throw new Exception('Размер файла превышает допустимый (' . ($this->maxFileSize / 1024 / 1024) . 'MB)');
        }

        // Проверка типа через getimagesize (более надежно, чем mime_content_type)
        $imageInfo = @getimagesize($file['tmp_name']);
        if (!$imageInfo) {
            throw new Exception('Файл не является изображением');
        }

        // Проверка типа изображения
        if (!in_array($imageInfo[2], $this->allowedTypes)) {
            throw new Exception('Неподдерживаемый тип изображения. Разрешены: JPG, PNG, GIF');
        }

        // Проверка расширения
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            throw new Exception('Недопустимое расширение файла');
        }

        return true;
    }

    /**
     * Сжатие изображения
     *
     * @param string $source Путь к исходному файлу
     * @param string $destination Путь для сохранения
     * @param int|null $maxWidth Максимальная ширина (null = использовать из конфига)
     * @param int|null $quality Качество (null = использовать из конфига)
     * @return bool
     * @throws Exception При ошибке обработки
     */
    public function compressImage($source, $destination, $maxWidth = null, $quality = null)
    {
        $maxWidth = $maxWidth ?? $this->maxWidth;
        $quality = $quality ?? $this->quality;
        $imageInfo = @getimagesize($source);
        if (!$imageInfo) {
            throw new Exception('Не удалось определить размер изображения');
        }

        list($width, $height, $type) = $imageInfo;
        // Рассчитываем новые размеры
        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = round(($height / $width) * $newWidth);
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }

        // Создаем новое изображение
        $image = imagecreatetruecolor($newWidth, $newHeight);
        if (!$image) {
            throw new Exception('Не удалось создать изображение');
        }

        // Загружаем исходное изображение
        $sourceImage = null;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $sourceImage = @imagecreatefromjpeg($source);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = @imagecreatefrompng($source);
                imagealphablending($image, false);
                imagesavealpha($image, true);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = @imagecreatefromgif($source);
                break;
            default:
                throw new Exception('Неподдерживаемый тип изображения');
        }

        if (!$sourceImage) {
            imagedestroy($image);
            throw new Exception('Не удалось загрузить исходное изображение');
        }

        // Масштабируем
        if (!imagecopyresampled($image, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height)) {
            imagedestroy($image);
            imagedestroy($sourceImage);
            throw new Exception('Не удалось масштабировать изображение');
        }

        // Сохраняем
        $success = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $success = @imagejpeg($image, $destination, $quality);
                break;
            case IMAGETYPE_PNG:
                $pngQuality = round(9 * (1 - $quality / 100));
                $success = @imagepng($image, $destination, $pngQuality);
                break;
            case IMAGETYPE_GIF:
                $success = @imagegif($image, $destination);
                break;
        }

        // Освобождаем память
        imagedestroy($image);
        imagedestroy($sourceImage);
        if (!$success) {
            throw new Exception('Не удалось сохранить изображение');
        }

        return true;
    }

    /**
     * Генерация уникального имени файла
     *
     * @param string $originalName Исходное имя файла
     * @param string $prefix Префикс для имени
     * @return string
     */
    public function generateUniqueFilename($originalName, $prefix = '')
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $baseName = uniqid($prefix . '_');
        return $baseName . '.' . $extension;
    }

    /**
     * Загрузка и обработка одного изображения
     *
     * @param array $file Массив $_FILES
     * @param string $prefix Префикс для имени файла
     * @return string Путь к сохраненному файлу
     * @throws Exception
     */
    public function uploadAndCompress($file, $prefix = '')
    {
        // Валидация
        $this->validateImage($file);
        // Генерация имени
        $filename = $this->generateUniqueFilename($file['name'], $prefix);
        $destination = rtrim($this->uploadDir, '/') . '/' . $filename;
        // Сжатие и сохранение
        $this->compressImage($file['tmp_name'], $destination);
        return $destination;
    }

    /**
     * Нормализует структуру $_FILES в плоский массив файлов.
     * Поддерживает случаи с несколькими input с одинаковым именем.
     *
     * @param array $files Массив $_FILES['field_name']
     * @return array
     */
    private function normalizeFilesInput($files)
    {
        if (!is_array($files) || !array_key_exists('name', $files)) {
            return [];
        }

        $normalized = [];
        $walk = function ($name, $type, $tmpName, $error, $size) use (&$walk, &$normalized) {
            if (is_array($name)) {
                foreach ($name as $index => $nestedName) {
                    $walk(
                        $nestedName,
                        is_array($type) ? ($type[$index] ?? '') : $type,
                        is_array($tmpName) ? ($tmpName[$index] ?? '') : $tmpName,
                        is_array($error) ? ($error[$index] ?? UPLOAD_ERR_NO_FILE) : $error,
                        is_array($size) ? ($size[$index] ?? 0) : $size
                    );
                }
                return;
            }

            $normalized[] = [
                'name' => (string)$name,
                'type' => (string)$type,
                'tmp_name' => (string)$tmpName,
                'error' => (int)$error,
                'size' => (int)$size
            ];
        };

        $walk(
            $files['name'],
            $files['type'] ?? '',
            $files['tmp_name'] ?? '',
            $files['error'] ?? UPLOAD_ERR_NO_FILE,
            $files['size'] ?? 0
        );

        return $normalized;
    }

    /**
     * Загрузка множественных изображений
     *
     * @param array $files Массив $_FILES['field_name']
     * @param int $maxCount Максимальное количество файлов
     * @param string $prefix Префикс для имен файлов
     * @return array Массив путей к сохраненным файлам
     */
    public function uploadMultiple($files, $maxCount = null, $prefix = '')
    {
        $maxCount = $maxCount ?? AppConstants::MAX_PHOTOS_PER_ORDER;
        $uploaded = [];
        $errors = [];
        $normalizedFiles = $this->normalizeFilesInput($files);
        if (empty($normalizedFiles)) {
            return ['uploaded' => [], 'errors' => []];
        }

        $count = min($maxCount, count($normalizedFiles));

        for ($i = 0; $i < $count; $i++) {
            $file = $normalizedFiles[$i];

            // Пропускаем пустые файлы
            if ($file['error'] === UPLOAD_ERR_NO_FILE || empty($file['size']) || $file['size'] == 0) {
                continue;
            }

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = [
                    'file' => $file['name'],
                    'error' => 'Ошибка загрузки файла (код: ' . $file['error'] . ')'
                ];
                continue;
            }
            try {
                $path = $this->uploadAndCompress($file, $prefix);
                $uploaded[] = $path;
            } catch (Exception $e) {
                $errors[] = [
                    'file' => $file['name'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'uploaded' => $uploaded,
            'errors' => $errors
        ];
    }

    /**
     * Безопасное удаление изображения
     *
     * @param string $path Путь к файлу
     * @return bool
     */
    public function deleteImage($path)
    {
        if (!empty($path) && file_exists($path)) {
            // Проверяем, что файл находится в разрешенной директории
            $realPath = realpath($path);
            $uploadDirReal = realpath($this->uploadDir);
            if ($realPath && $uploadDirReal && strpos($realPath, $uploadDirReal) === 0) {
                return @unlink($path);
            }
        }
        return false;
    }

    /**
     * Удаление множественных изображений
     *
     * @param array $paths Массив путей
     * @return int Количество удаленных файлов
     */
    public function deleteMultiple($paths)
    {
        $deleted = 0;
        foreach ($paths as $path) {
            if ($this->deleteImage($path)) {
                $deleted++;
            }
        }
        return $deleted;
    }
}

/**
 * Сервис для отправки уведомлений в Telegram
 * Инкапсулирует всю логику работы с Telegram Bot API
 */
class TelegramService
{
    private $botToken;
    private $chatId;
    private $baseUrl;

    /**
     * Конструктор
     *
     * @param string $botToken Токен бота
     * @param string $chatId ID чата для отправки
     */
    public function __construct($botToken = null, $chatId = null)
    {
        $this->botToken = $botToken ?? TELEGRAM_BOT_TOKEN;
        $this->chatId = $chatId ?? TELEGRAM_CHAT_ID;
        $this->baseUrl = "https://api.telegram.org/bot" . $this->botToken;
    }

    /**
     * Отправка текстового сообщения
     *
     * @param string $message Текст сообщения (поддерживает HTML)
     * @return bool
     */
    public function sendMessage($message)
    {
        if (!AppConstants::TELEGRAM_ENABLED) {
            return true;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . "/sendMessage");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'chat_id' => $this->chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, AppConstants::TELEGRAM_CONNECT_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, AppConstants::TELEGRAM_MESSAGE_TIMEOUT);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($response === false) {
            error_log("Ошибка отправки сообщения в Telegram: " . curl_error($ch));
            curl_close($ch);
            return false;
        }

        curl_close($ch);
        if ($httpCode !== 200) {
            error_log("Telegram API вернул код $httpCode: $response");
            return false;
        }

        return true;
    }

    /**
     * Отправка медиа-группы (до 10 фото)
     *
     * @param array $photos Массив путей к файлам изображений
     * @return bool
     */
    public function sendMediaGroup($photos)
    {
        if (!AppConstants::TELEGRAM_ENABLED) {
            return true;
        }
        if (empty($photos)) {
            return true;
        }

        $photos = array_slice(array_values($photos), 0, AppConstants::TELEGRAM_NOTIFY_MAX_PHOTOS);

        // Telegram ограничивает до 10 фото в одном запросе
        $photoChunks = array_chunk($photos, 10);
        $allSuccess = true;
        foreach ($photoChunks as $chunk) {
            $media = [];
            $postFields = ['chat_id' => $this->chatId];
            $photoIndex = 0;
            // Фильтруем только существующие файлы
            $validPhotos = array_filter($chunk, 'file_exists');
            if (empty($validPhotos)) {
                continue;
            }

            foreach ($validPhotos as $photo) {
                $media[] = [
                    'type' => 'photo',
                    'media' => 'attach://photo' . $photoIndex
                ];
                $postFields['photo' . $photoIndex] = new CURLFile($photo);
                $photoIndex++;
            }

            if (empty($media)) {
                continue;
            }

            $postFields['media'] = json_encode($media);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . "/sendMediaGroup");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, AppConstants::TELEGRAM_CONNECT_TIMEOUT);
            curl_setopt($ch, CURLOPT_TIMEOUT, AppConstants::TELEGRAM_MEDIA_TIMEOUT);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($response === false) {
                error_log("Ошибка отправки фото в Telegram: " . curl_error($ch));
                $allSuccess = false;
            } elseif ($httpCode !== 200) {
                error_log("Telegram API вернул код $httpCode: $response");
                $allSuccess = false;
            }

            curl_close($ch);
        }

        return $allSuccess;
    }

    /**
     * Отправка сообщения с фотографиями
     * Сначала отправляет фото, затем текст
     *
     * @param string $message Текст сообщения
     * @param array $photos Массив путей к фото
     * @return bool
     */
    public function sendMessageWithPhotos($message, $photos = [])
    {
        if (!AppConstants::TELEGRAM_ENABLED) {
            return true;
        }
        $photosSuccess = true;
        // Сначала отправляем фото
        if (!empty($photos)) {
            $photosSuccess = $this->sendMediaGroup($photos);
        }

        // Затем отправляем текстовое сообщение
        $messageSuccess = $this->sendMessage($message);
        return $photosSuccess && $messageSuccess;
    }

    /**
     * Форматирование сообщения о новом заказе
     *
     * @param array $order Данные заказа
     * @return string
     */
    public function formatOrderMessage($order)
    {
        $orderId = $order['id'] ?? '?';
        $clientName = htmlspecialchars($order['client_name'] ?? 'не указан');
        $licensePlate = htmlspecialchars($order['license_plate'] ?? 'не указан');
        $phone = htmlspecialchars($order['phone'] ?? 'не указан');
        $color = htmlspecialchars($order['color'] ?? 'не указан');
        $location = htmlspecialchars($order['location'] ?? 'не указана');
        $notes = htmlspecialchars($order['notes'] ?? 'нет');
        $price = !empty($order['price']) ? number_format($order['price'], 2) . ' руб.' : 'не указана';
        $queueDate = !empty($order['queue_date']) && $order['queue_date'] !== '0000-00-00'
            ? date('d.m.Y', strtotime($order['queue_date']))
            : 'не назначена';
        $createdAt = !empty($order['created_at'])
            ? date('d.m.Y H:i', strtotime($order['created_at']))
            : date('d.m.Y H:i');
        $message = "<b>Новый заказ #$orderId</b>\n" .
                   "Клиент: $clientName\n" .
                   "Госномер: $licensePlate\n" .
                   "Телефон: $phone\n" .
                   "Цвет: $color\n" .
                   "Локация: $location\n" .
                   "Стоимость: $price\n" .
                   "Примечание: $notes\n" .
                   "Очередь: $queueDate\n" .
                   "Статус: В работе\n" .
                   "Создан: $createdAt";
        return $message;
    }

    /**
     * Форматирование сообщения об обновлении заказа
     *
     * @param array $order Данные заказа
     * @return string
     */
    public function formatOrderUpdateMessage($order)
    {
        $orderId = $order['id'] ?? '?';
        $clientName = htmlspecialchars($order['client_name'] ?? 'не указан');
        $licensePlate = htmlspecialchars($order['license_plate'] ?? 'не указан');
        $phone = htmlspecialchars($order['phone'] ?? 'не указан');
        $color = htmlspecialchars($order['color'] ?? 'не указан');
        $location = htmlspecialchars($order['location'] ?? 'не указана');
        $notes = htmlspecialchars($order['notes'] ?? 'нет');
        $price = !empty($order['price']) ? number_format($order['price'], 2) . ' руб.' : 'не указана';
        $queueDate = !empty($order['queue_date']) && $order['queue_date'] !== '0000-00-00'
            ? date('d.m.Y', strtotime($order['queue_date']))
            : 'не назначена';
        $message = "<b>Заказ #$orderId обновлён</b>\n" .
                   "Клиент: $clientName\n" .
                   "Госномер: $licensePlate\n" .
                   "Телефон: $phone\n" .
                   "Цвет: $color\n" .
                   "Локация: $location\n" .
                   "Стоимость: $price\n" .
                   "Примечание: $notes\n" .
                   "Очередь: $queueDate\n" .
                   "Обновлено: " . date('d.m.Y H:i');
        return $message;
    }
}

/**
 * Сервис для работы с заказами
 * Инкапсулирует всю бизнес-логику работы с заказами
 */
class OrderService
{
    private $conn;
    private $transactionManager;
    private $imageService;
    private $telegramService;

    /**
     * Конструктор
     *
     * @param mysqli $conn Соединение с базой данных
     */
    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->transactionManager = new TransactionManager($conn);
        $this->imageService = new ImageService();
        $this->telegramService = new TelegramService();
    }

    /**
     * Обработка загрузки фотографий
     *
     * @param array $files Массив $_FILES
     * @param string $fieldName Имя поля с файлами
     * @param string $prefix Префикс для имен файлов
     * @return array Массив с uploaded (успешные) и errors (ошибки)
     */
    public function handlePhotoUpload($files, $fieldName = 'photos', $prefix = '', $maxCount = null)
    {
        if (empty($files[$fieldName]) || !array_key_exists('name', $files[$fieldName])) {
            return ['uploaded' => [], 'errors' => []];
        }

        $result = $this->imageService->uploadMultiple($files[$fieldName], $maxCount ?? AppConstants::MAX_PHOTOS_PER_ORDER, $prefix);
        // Логируем ошибки
        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                error_log("Ошибка загрузки изображения: " . $error['file'] . " - " . $error['error']);
            }
        }

        return $result;
    }

    /**
     * Обработка существующих фотографий при обновлении
     *
     * @param int $orderId ID заказа
     * @param array $existingPhotos Массив путей к существующим фото
     * @return array Массив путей к фото, которые нужно сохранить
     */
    public function handleExistingPhotos($orderId, $existingPhotos, $deleteUnselected = true)
    {
        $order = $this->findById($orderId);
        if (!$order) {
            return $deleteUnselected ? [] : ['keep' => [], 'to_delete' => []];
        }

        $old_photos = !empty($order['photos']) ? explode(',', $order['photos']) : [];
        $photos_to_keep = [];
        $photos_to_delete = [];
        $existing_normalized = [];
        foreach ($existingPhotos as $existing_photo) {
            $resolved = resolvePhotoPath($existing_photo);
            if ($resolved !== '') {
                $existing_normalized[] = $resolved;
            }
        }
        foreach ($old_photos as $old_photo) {
            $resolved_old = resolvePhotoPath($old_photo);
            $old_exists = $resolved_old !== '' && file_exists($resolved_old);
            $in_existing = in_array($old_photo, $existingPhotos, true) || in_array($resolved_old, $existing_normalized, true);
            if ($in_existing && $old_exists) {
                $photos_to_keep[] = $resolved_old;
            } elseif ($old_exists) {
                if ($deleteUnselected) {
                    $this->imageService->deleteImage($resolved_old);
                } else {
                    $photos_to_delete[] = $resolved_old;
                }
            }
        }

        if ($deleteUnselected) {
            return $photos_to_keep;
        }

        return [
            'keep' => $photos_to_keep,
            'to_delete' => $photos_to_delete
        ];
    }

    /**
     * Создание заказа
     *
     * @param array $data Данные заказа
     * @param array $files Загруженные файлы
     * @param string|null $queueDate Дата очереди
     * @return int|false ID созданного заказа или false при ошибке
     */
    public function create($data, $files, $queueDate = null)
    {
        // Подготовка данных
        $client_name = htmlspecialchars(trim($data['client_name']));
        $license_plate = htmlspecialchars(trim($data['license_plate'] ?? ''));
        $phone = htmlspecialchars(trim($data['phone'] ?? ''));
        $color = htmlspecialchars(trim($data['color'] ?? ''));
        $location = htmlspecialchars(trim($data['location'] ?? ''));
        $notes = htmlspecialchars(trim($data['notes'] ?? ''));
        $status = AppConstants::ORDER_STATUS_IN_PROGRESS;
        $price = 0;
        $manual_price = str_replace(',', '.', trim((string)($data['price'] ?? '')));
        if ($manual_price !== '' && is_numeric($manual_price)) {
            $price = max(0, (float)$manual_price);
        }
        // Обработка фотографий
        $photosResult = $this->handlePhotoUpload($files, 'photos');
        $photos = $photosResult['uploaded'];
        $photos_str = implode(',', $photos);
        // Нормализация даты очереди
        $queueDate = ($queueDate === null || empty($queueDate)) ? null : $queueDate;

        try {
            $order_id = $this->transactionManager->transaction(function ($conn) use (
                $client_name,
                $license_plate,
                $phone,
                $color,
                $location,
                $price,
                $notes,
                $status,
                $photos_str,
                $queueDate
            ) {
                $stmt = $conn->prepare("INSERT INTO orders (client_name, license_plate, phone, color, location, price, notes, status, photos, queue_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt === false) {
                    throw new Exception("Ошибка подготовки запроса: " . $conn->error);
                }

                $stmt->bind_param("sssssdssss", $client_name, $license_plate, $phone, $color, $location, $price, $notes, $status, $photos_str, $queueDate);

                if (!$stmt->execute()) {
                    $error = $stmt->error;
                    $stmt->close();
                    throw new Exception("Ошибка выполнения запроса: " . $error);
                }

                $order_id = $conn->insert_id;
                $stmt->close();

                return $order_id;
            });

            // Отправка уведомления после успешного создания (вне транзакции)
            if ($order_id) {
                $order_data = [
                    'id' => $order_id,
                    'client_name' => $client_name,
                    'license_plate' => $license_plate,
                    'phone' => $phone,
                    'color' => $color,
                    'location' => $location,
                    'price' => $price,
                    'notes' => $notes,
                    'queue_date' => $queueDate,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $message = $this->telegramService->formatOrderMessage($order_data);
                $this->telegramService->sendMessageWithPhotos($message, $photos);
            }

            return $order_id;
        } catch (Exception $e) {
            error_log("Ошибка создания заказа: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Получение заказа по ID
     *
     * @param int $orderId ID заказа
     * @return array|null Данные заказа или null
     */
    public function findById($orderId)
    {
        $stmt = $this->conn->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();
        $stmt->close();

        return $order ?: null;
    }

    /**
     * Получение заказов по статусу
     *
     * @param string $status Статус заказа
     * @param string $sort Тип сортировки (default, queue, location, service)
     * @param string $sortValue Значение для фильтрации
     * @return array Массив заказов
     */
    public function findByStatus($status, $sort = 'default', $sortValue = '', $limit = 20, $offset = 0)
    {
        $query = "SELECT * FROM orders WHERE status = ?";
        $limit = max(1, (int)$limit);
        $offset = max(0, (int)$offset);

        if ($sort === 'queue') {
            if ($sortValue) {
                $query .= " AND queue_date = ? ORDER BY queue_date ASC LIMIT ? OFFSET ?";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("ssii", $status, $sortValue, $limit, $offset);
            } else {
                $query .= " ORDER BY queue_date ASC LIMIT ? OFFSET ?";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("sii", $status, $limit, $offset);
            }
        } elseif ($sort === 'location') {
            if ($sortValue) {
                $query .= " AND location = ?";
            }
            $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $stmt = $this->conn->prepare($query);
            if ($sortValue) {
                $stmt->bind_param("ssii", $status, $sortValue, $limit, $offset);
            } else {
                $stmt->bind_param("sii", $status, $limit, $offset);
            }
        } else {
            $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("sii", $status, $limit, $offset);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        $stmt->close();

        return $orders;
    }

    /**
     * Обновление статуса заказа
     *
     * @param int $orderId ID заказа
     * @param string $newStatus Новый статус
     * @return bool
     */
    public function updateStatus($orderId, $newStatus)
    {
        $stmt = $this->conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $orderId);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Поиск заказов
     *
     * @param string $query Поисковый запрос
     * @return array Массив найденных заказов
     */
    public function search($query)
    {
        $query = trim((string)$query);
        if ($query === '') {
            return [];
        }

        $is_id_only_search = strpos($query, '#') === 0;
        if ($is_id_only_search) {
            $id_query = trim(ltrim($query, '#'));
            if ($id_query === '') {
                return [];
            }
            $id_search_term = "%$id_query%";
            $stmt = $this->conn->prepare("SELECT * FROM orders WHERE CAST(id AS CHAR) LIKE ? ORDER BY created_at DESC");
            $stmt->bind_param("s", $id_search_term);
            $stmt->execute();
            $result = $stmt->get_result();
            $orders = [];
            while ($row = $result->fetch_assoc()) {
                $orders[] = $row;
            }
            $stmt->close();
            return $orders;
        }

        // Обычный поиск по всем ключевым полям карточки заказа
        $search_term = "%$query%";
        $id_query = $query;
        $id_search_term = "%$id_query%";
        $stmt = $this->conn->prepare(
            "SELECT * FROM orders
             WHERE CAST(id AS CHAR) LIKE ?
                OR client_name LIKE ?
                OR license_plate LIKE ?
                OR phone LIKE ?
                OR color LIKE ?
                OR location LIKE ?
                OR notes LIKE ?
                OR CAST(price AS CHAR) LIKE ?
                OR status LIKE ?
                OR CAST(queue_date AS CHAR) LIKE ?
                OR DATE_FORMAT(queue_date, '%d.%m.%Y') LIKE ?
                OR CAST(created_at AS CHAR) LIKE ?
                OR DATE_FORMAT(created_at, '%d.%m.%Y %H:%i') LIKE ?
                OR DATE_FORMAT(created_at, '%d.%m.%Y') LIKE ?
                OR photos LIKE ?
             ORDER BY created_at DESC"
        );
        $stmt->bind_param(
            "sssssssssssssss",
            $id_search_term,
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
        );

        $stmt->execute();
        $result = $stmt->get_result();
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        $stmt->close();

        return $orders;
    }

    /**
     * Обновление заказа
     *
     * @param int $orderId ID заказа
     * @param array $data Данные для обновления
     * @param array $files Загруженные файлы
     * @return bool
     */
    public function update($orderId, $data, $files)
    {
        // Получаем старый заказ
        $old_order = $this->findById($orderId);
        if (!$old_order) {
            return false;
        }

        // Подготовка данных
        $client_name = htmlspecialchars(trim($data['client_name']));
        $license_plate = htmlspecialchars(trim($data['license_plate'] ?? ''));
        $phone = htmlspecialchars(trim($data['phone'] ?? ''));
        $color = htmlspecialchars(trim($data['color'] ?? ''));
        $location = htmlspecialchars(trim($data['location'] ?? ''));
        $notes = htmlspecialchars(trim($data['notes'] ?? ''));
        $queue_date = $data['queue_date'] ?? $old_order['queue_date'];

        $old_location = $old_order['location'] ?? '';
        $new_location = $location;

        $price = 0;
        $manual_price = str_replace(',', '.', trim((string)($data['price'] ?? '')));
        if ($manual_price !== '' && is_numeric($manual_price)) {
            $price = max(0, (float)$manual_price);
        }

        // Обработка существующих фото
        $existing_photos = $data['existing_photos'] ?? [];
        $existing_photos_result = $this->handleExistingPhotos($orderId, $existing_photos, false);
        $photos_to_keep = $existing_photos_result['keep'];
        $photos_to_delete = $existing_photos_result['to_delete'];

        // Обработка новых фото
        $available_slots = max(0, AppConstants::MAX_PHOTOS_PER_ORDER - count($photos_to_keep));
        $new_photos_result = $this->handlePhotoUpload($files, 'new_photos', 'new', $available_slots);
        $new_photos = $new_photos_result['uploaded'];

        $all_photos = array_merge($photos_to_keep, $new_photos);
        $photos_str = implode(',', $all_photos);

        // Используем транзакцию для обновления
        try {
            $result = $this->transactionManager->transaction(function ($conn) use (
                $orderId,
                $client_name,
                $license_plate,
                $phone,
                $color,
                $location,
                $price,
                $notes,
                $queue_date,
                $photos_str
            ) {
                $stmt = $conn->prepare("UPDATE orders SET client_name = ?, license_plate = ?, phone = ?, services = '', color = ?, location = ?, price = ?, notes = ?, queue_date = ?, photos = ? WHERE id = ?");
                if ($stmt === false) {
                    throw new Exception("Ошибка подготовки запроса: " . $conn->error);
                }

                $stmt->bind_param("sssssdsssi", $client_name, $license_plate, $phone, $color, $location, $price, $notes, $queue_date, $photos_str, $orderId);
                $result = $stmt->execute();
                $stmt->close();

                if (!$result) {
                    throw new Exception("Ошибка обновления заказа: " . $conn->error);
                }

                return true;
            });

            // Удаляем старые фото только после успешного обновления в БД.
            $this->imageService->deleteMultiple($photos_to_delete);

            // Отправка уведомления после успешного обновления (вне транзакции)
            $order_data = [
                'id' => $orderId,
                'client_name' => $client_name,
                'license_plate' => $license_plate,
                'phone' => $phone,
                'color' => $color,
                'location' => $location,
                'price' => $price,
                'notes' => $notes,
                'queue_date' => $queue_date
            ];
            $message = $this->telegramService->formatOrderUpdateMessage($order_data);
            $this->telegramService->sendMessageWithPhotos($message, $new_photos);

            return $result;
        } catch (Exception $e) {
            // Если обновление не удалось, удаляем только вновь загруженные файлы.
            $this->imageService->deleteMultiple($new_photos ?? []);
            error_log("Ошибка обновления заказа #$orderId: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Удаление заказа
     *
     * @param int $orderId ID заказа
     * @return bool
     */
    public function delete($orderId)
    {
        $order = $this->findById($orderId);
        if (!$order) {
            return false;
        }

        try {
            $result = $this->transactionManager->transaction(function ($conn) use ($orderId) {
                // Удаляем записи о зарплате, связанные с заказом
                $comments_pattern = "%в заказе #$orderId%";
                $stmt = $conn->prepare("DELETE FROM salary_records WHERE comments LIKE ?");
                $stmt->bind_param("s", $comments_pattern);
                if (!$stmt->execute()) {
                    $error = $stmt->error;
                    $stmt->close();
                    throw new Exception("Ошибка удаления записей о зарплате: " . $error);
                }
                $stmt->close();

                // Удаляем заказ
                $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
                $stmt->bind_param("i", $orderId);
                if (!$stmt->execute()) {
                    $error = $stmt->error;
                    $stmt->close();
                    throw new Exception("Ошибка удаления заказа: " . $error);
                }
                $stmt->close();

                return true;
            });

            // Удаляем фото после успешного удаления из БД (вне транзакции)
            $photos = !empty($order['photos']) ? explode(',', $order['photos']) : [];
            $this->imageService->deleteMultiple($photos);

            return $result;
        } catch (Exception $e) {
            error_log("Ошибка удаления заказа #$orderId: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Сервис для работы с зарплатой
 * Инкапсулирует логику расчета и управления зарплатой сотрудников
 */
class SalaryService
{
    private $conn;
    private $telegramService;

    /**
     * Конструктор
     *
     * @param mysqli $conn Соединение с базой данных
     */
    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->telegramService = new TelegramService();
    }

    /**
     * Расчет суммы за выполненную услугу
     *
     * @param string $serviceName Название услуги
     * @param float $servicePrice Цена услуги
     * @param int $quantity Количество
     * @return float Сумма зарплаты
     */
    public function calculateAmount($serviceName, $servicePrice, $quantity)
    {
        $rule = AppConstants::getSalaryRule($serviceName);
        if ($rule === null) {
            // Если правило не найдено
            return 0;
        }

        if (AppConstants::isFixedAmountRule($rule)) {
            // Фиксированная сумма за единицу
            return $rule * $quantity;
        }

        // Процент от общей стоимости услуги
        return ($servicePrice * $quantity) * $rule;
    }

    /**
     * Получение записей о зарплате
     *
     * @param string|null $status Фильтр по статусу
     * @param string|null $executor Фильтр по исполнителю
     * @return array
     */
    public function getRecords($status = null, $executor = null)
    {
        $username = $_SESSION['username'] ?? null;
        $is_admin = ($_SESSION['user_role'] ?? '') === 'admin';
        // Если не админ и не указан исполнитель, используем текущего пользователя
        if (!$is_admin && $executor === null) {
            $executor = $username;
        }

        if ($status) {
            if ($executor) {
                $stmt = $this->conn->prepare("SELECT * FROM salary_records WHERE status = ? AND executor = ? ORDER BY created_at DESC");
                $stmt->bind_param("ss", $status, $executor);
            } else {
                $stmt = $this->conn->prepare("SELECT * FROM salary_records WHERE status = ? ORDER BY created_at DESC");
                $stmt->bind_param("s", $status);
            }
        } else {
            if ($executor) {
                $stmt = $this->conn->prepare("SELECT * FROM salary_records WHERE executor = ? ORDER BY created_at DESC");
                $stmt->bind_param("s", $executor);
            } else {
                $stmt = $this->conn->prepare("SELECT * FROM salary_records ORDER BY created_at DESC");
            }
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $records = [];
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
        $stmt->close();
        return $records;
    }

    /**
     * Получение записи о зарплате по ID
     *
     * @param int $recordId ID записи
     * @return array|null
     */
    public function getRecordById($recordId)
    {
        $stmt = $this->conn->prepare("SELECT * FROM salary_records WHERE id = ?");
        $stmt->bind_param("i", $recordId);
        $stmt->execute();
        $result = $stmt->get_result();
        $record = $result->fetch_assoc();
        $stmt->close();
        return $record ?: null;
    }

    /**
     * Обновление статуса записи о зарплате
     *
     * @param int $recordId ID записи
     * @param string $newStatus Новый статус
     * @return bool
     */
    public function updateStatus($recordId, $newStatus)
    {
        $stmt = $this->conn->prepare("UPDATE salary_records SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $recordId);
        $result = $stmt->execute();
        if ($result) {
            $record = $this->getRecordById($recordId);
            if ($record) {
                $message = "<b>Статус записи З/П #$recordId обновлён</b>\n" .
                           "Исполнитель: " . $record['executor'] . "\n" .
                           "Дата выполнения: " . date('d.m.Y', strtotime($record['execution_date'])) . "\n" .
                           "Стоимость: " . number_format($record['amount'], 2) . " руб.\n" .
                           "Новый статус: " . ($newStatus === AppConstants::SALARY_STATUS_WAITING_PAYMENT ? 'Жду оплаты' : 'Выплачено') . "\n" .
                           "Обновлено: " . date('d.m.Y H:i');
                $this->telegramService->sendMessage($message);
            }
        }

        $stmt->close();
        return $result;
    }
}

/**
 * Базовый класс валидатора
 */
abstract class Validator
{
    protected $errors = [];

    /**
     * Выполняет валидацию данных.
     * @param array $data Данные для валидации.
     * @return bool true, если данные валидны, иначе false.
     */
    abstract public function validate(array $data): bool;

    /**
     * Возвращает массив ошибок валидации.
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Добавляет ошибку в список.
     * @param string $field Поле, в котором произошла ошибка.
     * @param string $message Сообщение об ошибке.
     */
    protected function addError(string $field, string $message)
    {
        $this->errors[$field][] = $message;
    }
}

/**
 * Валидатор для данных заказа.
 */
class OrderValidator extends Validator
{
    public function validate(array $data): bool
    {
        $this->errors = [];

        // Валидация имени клиента
        if (empty($data['client_name'])) {
            $this->addError('client_name', 'Имя клиента является обязательным полем.');
        }

        // Валидация телефона
        if (!empty($data['phone']) && !preg_match('/^(\+7|8)?[0-9]{10}$/', preg_replace('/[^0-9+]/', '', $data['phone']))) {
            $this->addError('phone', 'Некорректный формат номера телефона.');
        }

        // Валидация даты очереди
        if (!empty($data['queue_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['queue_date'])) {
            $this->addError('queue_date', 'Некорректный формат даты очереди.');
        }

        // Валидация общей стоимости заказа
        if (isset($data['price']) && trim((string)$data['price']) !== '') {
            $manual_price = str_replace(',', '.', trim((string)$data['price']));
            if (!is_numeric($manual_price) || (float)$manual_price < 0) {
                $this->addError('price', 'Стоимость заказа должна быть неотрицательным числом.');
            }
        }

        return empty($this->errors);
    }
}



