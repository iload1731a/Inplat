<?php

declare(strict_types=1);

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        $base = dirname(__DIR__, 2);
        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        static $config = [];

        [$file, $item] = array_pad(explode('.', $key, 2), 2, null);

        if (!isset($config[$file])) {
            $path = app_path('app/config/' . $file . '.php');
            $config[$file] = is_file($path) ? require $path : [];
        }

        if ($item === null) {
            return $config[$file] ?? $default;
        }

        return $config[$file][$item] ?? $default;
    }
}

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('password_algo')) {
    function password_algo(): string|int|null
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }
}
