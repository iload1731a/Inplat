<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\View;

abstract class BaseController
{
    protected function view(string $view, array $data = []): void
    {
        View::render($view, $data);
    }

    protected function userView(string $view, array $data = []): void
    {
        View::render($view, $data, 'layouts/user');
    }

    /**
     * Smart render: picks user layout for user/* views, main layout otherwise.
     */
    protected function render(string $view, array $data = []): void
    {
        $layout = str_starts_with($view, 'user/') ? 'layouts/user' : 'layouts/main';
        View::render($view, $data, $layout);
    }
}
