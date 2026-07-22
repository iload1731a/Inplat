<?php

declare(strict_types=1);

namespace App\Libraries;

final class View
{
    public static function render(string $view, array $data = [], string $layout = 'layouts/main'): void
    {
        $viewFile = app_path('app/views/' . $view . '.php');
        $layoutFile = app_path('app/views/' . $layout . '.php');

        if (!is_file($viewFile)) {
            throw new \RuntimeException('View not found: ' . $view);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        require $layoutFile;
    }
}
