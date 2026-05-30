-- Миграция 004: перевод service_costs с utf8 (3 байта) на utf8mb4 (полный Unicode).
-- utf8 в MySQL не поддерживает символы за пределами BMP (эмодзи и часть редких знаков),
-- поэтому везде в проекте используется utf8mb4. Эта таблица была создана раньше
-- стандартизации и осталась с устаревшей кодировкой.

ALTER TABLE `service_costs`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `service_costs`
    DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
