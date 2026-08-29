<?php

namespace Parfeon\Bitrix\Mcp;

class Config
{
    private static array $extra = [];
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        // Find project root by vendor/autoload.php, then read its composer.json.
        // This avoids stopping at the package's own composer.json inside vendor/.
        $dir = __DIR__;
        for ($i = 0; $i < 12; $i++) {
            if (file_exists($dir . '/vendor/autoload.php')) {
                $file = $dir . '/composer.json';
                if (file_exists($file)) {
                    $json = json_decode(file_get_contents($file), true) ?? [];
                    self::$extra = $json['extra']['mcp-bitrix24'] ?? [];
                }
                return;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }
    }

    public static function getBitrixRoot(): string
    {
        self::load();

        if (!empty(self::$extra['bitrix_root'])) {
            return rtrim(self::$extra['bitrix_root'], '/\\');
        }

        return self::discoverBitrixRoot();
    }

    public static function getAdminUserId(): int
    {
        self::load();
        return (int)(self::$extra['admin_user_id'] ?? 1);
    }

    public static function getModules(): array
    {
        self::load();
        return self::$extra['modules'] ?? ['crm', 'tasks', 'socialnetwork'];
    }

    private static function discoverBitrixRoot(): string
    {
        $marker = 'bitrix/modules/main/include/prolog_before.php';

        $dir = __DIR__;
        for ($i = 0; $i < 15; $i++) {
            if (file_exists($dir . '/' . $marker)) {
                return $dir;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        throw new \RuntimeException(
            'Bitrix root not found. Set extra.mcp-bitrix24.bitrix_root in composer.json'
        );
    }
}
