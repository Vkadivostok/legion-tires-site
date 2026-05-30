<?php

/**
 * Скрипт для применения миграций базы данных
 *
 * Использование: php migrate.php
 */

require_once __DIR__ . '/../db.php';
// Таблица для отслеживания примененных миграций
$createMigrationsTable = "
CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(255) UNIQUE NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_migration_name (migration_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";
if (!$conn->query($createMigrationsTable)) {
    die("Ошибка создания таблицы migrations: " . $conn->error . "\n");
}

// Директория с миграциями
$migrationsDir = __DIR__;
$migrationFiles = glob($migrationsDir . '/*_*.sql');
sort($migrationFiles);
// Получаем список примененных миграций
$appliedMigrations = [];
$result = $conn->query("SELECT migration_name FROM migrations");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $appliedMigrations[] = $row['migration_name'];
    }
}

echo "Проверка миграций...\n\n";
$appliedCount = 0;
foreach ($migrationFiles as $migrationFile) {
    $migrationName = basename($migrationFile);
// Пропускаем файл migrate.php и другие не-SQL файлы
    if (pathinfo($migrationFile, PATHINFO_EXTENSION) !== 'sql') {
        continue;
    }

    // Проверяем, применена ли уже миграция
    if (in_array($migrationName, $appliedMigrations)) {
        echo "✓ Миграция $migrationName уже применена, пропускаем\n";
        continue;
    }

    echo "Применение миграции: $migrationName\n";
// Читаем содержимое миграции
    $sql = file_get_contents($migrationFile);
    if ($sql === false) {
        echo "✗ Ошибка чтения файла миграции\n";
        continue;
    }

    // Разбиваем на отдельные запросы (по точке с запятой)
    // ВНИМАНИЕ: Это простая реализация, для сложных миграций может потребоваться более умный парсер
    $queries = array_filter(array_map('trim', explode(';', $sql)), function ($query) {

            return !empty($query) && !preg_match('/^\s*--/', $query);
    });
    $conn->begin_transaction();
    try {
        foreach ($queries as $query) {
            if (empty(trim($query))) {
                continue;
            }

            // Пропускаем комментарии
            if (preg_match('/^\s*--/', $query)) {
                continue;
            }

            if (!$conn->query($query)) {
                throw new Exception("Ошибка выполнения запроса: " . $conn->error . "\nЗапрос: " . substr($query, 0, 100));
            }
        }

        // Записываем факт применения миграции
        $stmt = $conn->prepare("INSERT INTO migrations (migration_name) VALUES (?)");
        $stmt->bind_param("s", $migrationName);
        if (!$stmt->execute()) {
            throw new Exception("Ошибка записи миграции в БД: " . $stmt->error);
        }

        $stmt->close();
        $conn->commit();
        echo "✓ Миграция $migrationName успешно применена\n\n";
        $appliedCount++;
    } catch (Exception $e) {
        $conn->rollback();
        echo "✗ Ошибка применения миграции $migrationName: " . $e->getMessage() . "\n";
        echo "Откат изменений выполнен\n\n";
    }
}

if ($appliedCount === 0) {
    echo "Новых миграций не найдено.\n";
} else {
    echo "Всего применено миграций: $appliedCount\n";
}

$conn->close();
