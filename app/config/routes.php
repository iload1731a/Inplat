<?php

declare(strict_types=1);

use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\Admin\PlatformController as AdminPlatformController;
use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\InstallerController;
use App\Controllers\User\DashboardController as UserDashboardController;
use App\Controllers\User\PlatformController as UserPlatformController;
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
$router->post('/forgot-password', [AuthController::class, 'forgotPassword']);
$router->get('/reset-password', [AuthController::class, 'resetPasswordForm']);
$router->post('/reset-password', [AuthController::class, 'resetPassword']);
$router->get('/email/verify/notice', [AuthController::class, 'verifyNotice']);
$router->get('/email/verify', [AuthController::class, 'verifyEmail']);
$router->get('/two-factor-challenge', [AuthController::class, 'twoFactorChallengeForm']);
$router->post('/two-factor-challenge', [AuthController::class, 'verifyTwoFactorChallenge']);
$router->get('/security/sessions', [AuthController::class, 'sessions']);
$router->post('/security/sessions/revoke', [AuthController::class, 'revokeSession']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/admin/dashboard', [AdminDashboardController::class, 'index']);
$router->get('/admin/dashboard/metrics', [AdminDashboardController::class, 'metrics']);
$router->get('/admin/platform', [AdminPlatformController::class, 'index']);
$router->get('/admin/platform/snapshot', [AdminPlatformController::class, 'snapshot']);

$router->get('/dashboard', [UserDashboardController::class, 'index']);
$router->get('/dashboard/metrics', [UserDashboardController::class, 'metrics']);
$router->get('/trading', [UserPlatformController::class, 'index']);
$router->get('/trading/snapshot', [UserPlatformController::class, 'snapshot']);

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
