-- Миграция 001: Добавление индексов для улучшения производительности
-- Дата создания: 2024

-- Индексы для таблицы orders
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
CREATE INDEX IF NOT EXISTS idx_orders_queue_date ON orders(queue_date);
CREATE INDEX IF NOT EXISTS idx_orders_location ON orders(location);
CREATE INDEX IF NOT EXISTS idx_orders_created_at ON orders(created_at);

-- Индексы для таблицы salary_records
CREATE INDEX IF NOT EXISTS idx_salary_executor ON salary_records(executor);
CREATE INDEX IF NOT EXISTS idx_salary_status ON salary_records(status);
CREATE INDEX IF NOT EXISTS idx_salary_execution_date ON salary_records(execution_date);

-- Индексы для таблицы storage_orders
CREATE INDEX IF NOT EXISTS idx_storage_status ON storage_orders(status);
CREATE INDEX IF NOT EXISTS idx_storage_location ON storage_orders(storage_location);
CREATE INDEX IF NOT EXISTS idx_storage_inventory ON storage_orders(inventory_number);

-- Индексы для таблицы users
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);

-- Примечание: IF NOT EXISTS может не работать в старых версиях MySQL
-- В таком случае нужно вручную проверять существование индексов

