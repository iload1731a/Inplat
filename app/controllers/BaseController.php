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
}
