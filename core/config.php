<?php

$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) {
    require_once $localConfig;
}

function app_config_value(string $name, string $default = ''): string
{
    $value = getenv($name);
    return $value === false ? $default : $value;
}

if (!defined('DB_HOST')) {
    define('DB_HOST', app_config_value('DB_HOST', 'localhost'));
}
if (!defined('DB_USER')) {
    define('DB_USER', app_config_value('DB_USER'));
}
if (!defined('DB_PASS')) {
    define('DB_PASS', app_config_value('DB_PASS'));
}
if (!defined('DB_NAME')) {
    define('DB_NAME', app_config_value('DB_NAME'));
}
if (!defined('PASSWORD_REMINDER_EMAIL')) {
    define('PASSWORD_REMINDER_EMAIL', app_config_value('PASSWORD_REMINDER_EMAIL'));
}

if (!defined('TELEGRAM_BOT_TOKEN')) {
    define('TELEGRAM_BOT_TOKEN', app_config_value('TELEGRAM_BOT_TOKEN'));
}
if (!defined('TELEGRAM_CHAT_ID')) {
    define('TELEGRAM_CHAT_ID', app_config_value('TELEGRAM_CHAT_ID'));
}
