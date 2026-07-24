<?php

declare(strict_types=1);

namespace App\Libraries;

final class Env
{
    public static function load(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            $separator = strpos($line, '=');
            if ($separator === false) {
                continue;
            }

            $name = trim(substr($line, 0, $separator));
            $value = trim(substr($line, $separator + 1));

            if ($name === '' || preg_match('/^[a-zA-Z0-9_]+$/', $name) !== 1) {
                continue;
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            if (array_key_exists($name, $_ENV) || array_key_exists($name, $_SERVER)) {
                continue;
            }

            if (getenv($name, true) !== false) {
                continue;
            }

            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv($name . '=' . $value);
        }
    }
}
