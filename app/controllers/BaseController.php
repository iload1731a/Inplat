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

    protected function adminView(string $view, array $data = []): void
    {
        View::render($view, $data, 'layouts/admin');
    }

    /**
     * Smart render: picks user layout for user/* views, admin layout for admin/* views,
     * main layout otherwise.
     */
    protected function render(string $view, array $data = []): void
    {
        if (str_starts_with($view, 'user/')) {
            $layout = 'layouts/user';
        } elseif (str_starts_with($view, 'admin/')) {
            $layout = 'layouts/admin';
        } else {
            $layout = 'layouts/main';
        }
        View::render($view, $data, $layout);
    }
}
