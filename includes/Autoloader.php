<?php
declare(strict_types=1);

namespace WpMcpUltimate;

final class Autoloader {
    private static bool $registered = false;

    public static function register(): bool {
        if (self::$registered) {
            return true;
        }

        spl_autoload_register([self::class, 'autoload']);
        self::$registered = true;
        return true;
    }

    private static function autoload(string $class): void {
        $prefix = 'WpMcpUltimate\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file = WP_MCP_ULTIMATE_DIR . 'includes/' . str_replace('\\', '/', $relative) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }
}
