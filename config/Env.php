<?php

declare(strict_types=1);

namespace Config;

use Dotenv\Dotenv;
use RuntimeException;
use Throwable;

final class Env
{
    private static bool $loaded = false;

    public static function load(string $basePath): void
    {
        if (self::$loaded) {
            return;
        }

        $basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $envFile = $basePath . DIRECTORY_SEPARATOR . '.env';

        if (!is_file($envFile) || !class_exists(Dotenv::class)) {
            self::$loaded = true;
            return;
        }

        try {
            Dotenv::createImmutable($basePath)->safeLoad();
            self::$loaded = true;
        } catch (Throwable $e) {
            throw new RuntimeException('Errore caricamento configurazione ambiente', 0, $e);
        }
    }

    public static function getString(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        $trimmed = trim((string) $value);
        return $trimmed === '' ? $default : $trimmed;
    }

    public static function getInt(string $key, int $default): int
    {
        $value = self::getString($key);
        if ($value === null || filter_var($value, FILTER_VALIDATE_INT) === false) {
            return $default;
        }

        return (int) $value;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::getString($key);
        if ($value === null) {
            return $default;
        }

        $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $boolValue ?? $default;
    }
}
