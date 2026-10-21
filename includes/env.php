<?php
/**
 * Environment loader.
 *
 * Reads the project `.env` into $_ENV once per request and exposes a small
 * accessor. Kept intentionally simple: no third-party parser, no framework.
 *
 * WHY: secrets (sync keys, service keys) must come from the environment rather
 * than from tracked source files. See docs/runbooks/rb-0001 for context.
 *
 * @package InventoryManagement\Env
 */

declare(strict_types=1);

/**
 * Load `.env` from the project root into $_ENV.
 *
 * Idempotent and safe to call repeatedly. Existing $_ENV values win, so real
 * process environment variables always override the file (12-factor order).
 *
 * @return void
 */
function loadEnv(): void
{
    static $loaded = false;

    if ($loaded) {
        return;
    }
    $loaded = true;

    $envPath = __DIR__ . '/../.env';
    if (!is_readable($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Strip optional surrounding quotes.
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        if ($key !== '' && !array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
    }
}

/**
 * Read an environment value.
 *
 * @param string $key     Variable name, e.g. 'INVENTORY_SYNC_SECRET'
 * @param string $default Returned when the variable is missing or empty
 * @return string
 */
function envValue(string $key, string $default = ''): string
{
    loadEnv();

    $value = $_ENV[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return (string) $value;
}