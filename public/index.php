<?php

declare(strict_types=1);

define('INPLAT_START', microtime(true));

require dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set((string)config('app.timezone', 'UTC'));

\App\Libraries\Session::start();

(new \App\Services\AuthService())->hydrateFromRememberCookie(
    (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
    substr(trim((string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')), 0, 500)
);

$installed = is_file((string)config('app.installed_lock'));
$request = new \App\Libraries\Request();
$requestPath = $request->path();
\App\Libraries\LicenseGuard::assertValidForRequest($requestPath);

if (!$installed && !str_starts_with($requestPath, '/install')) {
    header('Location: /install/step1');
    exit;
}

$router = new \App\Libraries\Router();
require app_path('app/config/routes.php');
$router->dispatch($request);
