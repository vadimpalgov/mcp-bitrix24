#!/usr/bin/env php
<?php

/**
 * MCP Server entry point.
 *
 * Запуск:
 *   docker exec -i bitrix_php php /var/www/bitrix/local/modules/bit.mcp/extension/cli/server.php
 *
 * Конфиг MCP клиента (Claude Desktop / Cursor):
 * {
 *   "mcpServers": {
 *     "bitrix24": {
 *       "command": "wsl",
 *       "args": ["docker", "exec", "-i", "bitrix_php", "php",
 *                "/var/www/bitrix/local/modules/bit.mcp/extension/cli/server.php"]
 *     }
 *   }
 * }
 *
 * Параметры в composer.json проекта (extra.mcp-bitrix24):
 * {
 *   "extra": {
 *     "mcp-bitrix24": {
 *       "bitrix_root": "/var/www/bitrix",   // явный путь; если не задан — автообнаружение
 *       "admin_user_id": 1,                 // ID пользователя для авторизации (default: 1)
 *       "modules": ["crm", "tasks"]         // Bitrix-модули для подгрузки
 *     }
 *   }
 * }
 */

// Find vendor/autoload.php walking up from current directory
$autoloader = null;
$dir = __DIR__;
for ($i = 0; $i < 10; $i++) {
    if (file_exists($dir . '/vendor/autoload.php')) {
        $autoloader = $dir . '/vendor/autoload.php';
        break;
    }
    $parent = dirname($dir);
    if ($parent === $dir) {
        break;
    }
    $dir = $parent;
}

if (!$autoloader) {
    fwrite(STDERR, "[MCP] vendor/autoload.php not found\n");
    exit(1);
}

require_once $autoloader;

use Parfeon\Bitrix\Mcp\Config;
use Parfeon\Bitrix\Mcp\ServerFactory;

$bitrixRoot = Config::getBitrixRoot();

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NO_LANG_FILES', false);
define('NOT_CHECK_PERMISSIONS', true);

$_SERVER['DOCUMENT_ROOT'] = $bitrixRoot;
$_SERVER['HTTP_HOST']     = 'localhost';
$_SERVER['REQUEST_URI']   = '/';
$_SERVER['SERVER_NAME']   = 'localhost';
$_SERVER['SERVER_PORT']   = '80';
$_SERVER['HTTPS']         = 'off';

require_once $bitrixRoot . '/bitrix/modules/main/include/prolog_before.php';

foreach (Config::getModules() as $module) {
    \Bitrix\Main\Loader::includeModule($module);
}

global $USER;
$USER = new \CUser();
$USER->Authorize(Config::getAdminUserId());

ServerFactory::run();
