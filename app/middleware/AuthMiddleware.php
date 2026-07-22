<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Libraries\Response;
use App\Libraries\Session;

final class AuthMiddleware
{
    public static function ensureAuthenticated(): void
    {
        if (Session::get('auth.user_id') === null) {
            Response::redirect('/login');
        }
    }

    public static function ensureAdmin(): void
    {
        self::ensureAuthenticated();

        if ((bool)(Session::get('auth.is_admin') ?? false) !== true) {
            Response::redirect('/dashboard');
        }
    }
}
