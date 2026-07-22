<?php

declare(strict_types=1);

use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\Admin\ManagementController as AdminManagementController;
use App\Controllers\Admin\PlatformController as AdminPlatformController;
use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\InstallerController;
use App\Controllers\User\ConvertController as UserConvertController;
use App\Controllers\User\DashboardController as UserDashboardController;
use App\Controllers\User\PlatformController as UserPlatformController;
use App\Controllers\User\StakingController as UserStakingController;
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
$router->get('/admin/users', [AdminManagementController::class, 'users']);
$router->get('/admin/user', [AdminManagementController::class, 'user']);
$router->post('/admin/users/update', [AdminManagementController::class, 'updateUser']);
$router->post('/admin/users/kyc', [AdminManagementController::class, 'reviewKyc']);
$router->post('/admin/users/balance', [AdminManagementController::class, 'adjustBalance']);
$router->get('/admin/finance', [AdminManagementController::class, 'finance']);
$router->post('/admin/finance/deposit', [AdminManagementController::class, 'reviewDeposit']);
$router->post('/admin/finance/withdrawal', [AdminManagementController::class, 'reviewWithdrawal']);
$router->get('/admin/communications', [AdminManagementController::class, 'communications']);
$router->post('/admin/communications/notify', [AdminManagementController::class, 'sendNotification']);
$router->post('/admin/communications/template', [AdminManagementController::class, 'saveEmailTemplate']);
$router->get('/admin/support', [AdminManagementController::class, 'support']);
$router->post('/admin/support/update', [AdminManagementController::class, 'updateSupportTicket']);
$router->post('/admin/support/reply', [AdminManagementController::class, 'replySupportTicket']);
$router->get('/admin/settings', [AdminManagementController::class, 'settings']);
$router->post('/admin/settings/update', [AdminManagementController::class, 'updateSetting']);

$router->get('/admin/trading', [AdminManagementController::class, 'trading']);
$router->post('/admin/trading/pair/update', [AdminManagementController::class, 'updateTradingPair']);
$router->post('/admin/trading/halt', [AdminManagementController::class, 'haltTrading']);
$router->post('/admin/trading/halt/resolve', [AdminManagementController::class, 'resolveHalt']);

$router->get('/admin/risk', [AdminManagementController::class, 'risk']);
$router->post('/admin/risk/flag/update', [AdminManagementController::class, 'updateRiskFlag']);
$router->post('/admin/risk/ip/block', [AdminManagementController::class, 'blockIP']);
$router->post('/admin/risk/ip/unblock', [AdminManagementController::class, 'unblockIP']);

$router->get('/dashboard', [UserDashboardController::class, 'index']);
$router->get('/dashboard/metrics', [UserDashboardController::class, 'metrics']);
$router->get('/trading', [UserPlatformController::class, 'index']);
$router->get('/trading/snapshot', [UserPlatformController::class, 'snapshot']);
$router->get('/staking', [UserStakingController::class, 'index']);
$router->get('/staking/snapshot', [UserStakingController::class, 'snapshot']);
$router->get('/convert', [UserConvertController::class, 'index']);
$router->get('/convert/snapshot', [UserConvertController::class, 'snapshot']);

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
