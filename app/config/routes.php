<?php

declare(strict_types=1);

use App\Controllers\Admin\DashboardController;
use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\InstallerController;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Router;

/** @var Router $router */
$router->get('/', [HomeController::class, 'index']);

$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'registerForm']);
$router->post('/register', [AuthController::class, 'register']);
$router->get('/forgot-password', [AuthController::class, 'forgotPasswordForm']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/admin/dashboard', [DashboardController::class, 'index']);

$router->get('/install/step1', [InstallerController::class, 'step1']);
$router->get('/install/step2', [InstallerController::class, 'step2']);
$router->post('/install/database', [InstallerController::class, 'saveDatabase']);
$router->get('/install/step3', [InstallerController::class, 'step3']);
$router->post('/install/import', [InstallerController::class, 'importSchema']);
$router->get('/install/step4', [InstallerController::class, 'step4']);
$router->post('/install/admin', [InstallerController::class, 'createAdmin']);
$router->get('/install/step5', [InstallerController::class, 'step5']);

$router->get('/health', static function (Request $request): void {
    Response::json(['ok' => true, 'app' => config('app.name')]);
});
