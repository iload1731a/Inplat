<?php

declare(strict_types=1);

use App\Controllers\Admin\AdminBaseController;
use App\Controllers\Admin\AssetsController as AdminAssetsController;
use App\Controllers\Admin\ContentController as AdminContentController;
use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\Admin\KycController as AdminKycController;
use App\Controllers\Admin\LogsController as AdminLogsController;
use App\Controllers\Admin\ManagementController as AdminManagementController;
use App\Controllers\Admin\OrdersController as AdminOrdersController;
use App\Controllers\Admin\PlatformController as AdminPlatformController;
use App\Controllers\Admin\RolesController as AdminRolesController;
use App\Controllers\Admin\SystemController as AdminSystemController;
use App\Controllers\Admin\WalletsController as AdminWalletsController;
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

// =====================================================================
// ADMIN – Dashboard & Platform
// =====================================================================
$router->get('/admin/dashboard', [AdminDashboardController::class, 'index']);
$router->get('/admin/dashboard/metrics', [AdminDashboardController::class, 'metrics']);
$router->get('/admin/platform', [AdminPlatformController::class, 'index']);
$router->get('/admin/platform/snapshot', [AdminPlatformController::class, 'snapshot']);

// =====================================================================
// ADMIN – User Management
// =====================================================================
$router->get('/admin/users', [AdminManagementController::class, 'users']);
$router->get('/admin/user', [AdminManagementController::class, 'user']);
$router->post('/admin/users/update', [AdminManagementController::class, 'updateUser']);
$router->post('/admin/users/kyc', [AdminManagementController::class, 'reviewKyc']);
$router->post('/admin/users/balance', [AdminManagementController::class, 'adjustBalance']);
$router->post('/admin/users/ban', [AdminManagementController::class, 'banUser']);
$router->post('/admin/users/unban', [AdminManagementController::class, 'unbanUser']);
$router->post('/admin/users/reset-2fa', [AdminManagementController::class, 'resetUserTwoFactor']);
$router->post('/admin/users/revoke-sessions', [AdminManagementController::class, 'revokeUserSessions']);

// =====================================================================
// ADMIN – Roles & Permissions
// =====================================================================
$router->get('/admin/roles', [AdminRolesController::class, 'index']);
$router->post('/admin/roles/create', [AdminRolesController::class, 'createRole']);
$router->post('/admin/roles/update', [AdminRolesController::class, 'updateRole']);
$router->post('/admin/roles/delete', [AdminRolesController::class, 'deleteRole']);
$router->post('/admin/roles/permissions/sync', [AdminRolesController::class, 'syncPermissions']);
$router->get('/admin/roles/permissions', [AdminRolesController::class, 'getRolePermissions']);
$router->post('/admin/permissions/create', [AdminRolesController::class, 'createPermission']);
$router->post('/admin/permissions/update', [AdminRolesController::class, 'updatePermission']);
$router->post('/admin/permissions/delete', [AdminRolesController::class, 'deletePermission']);
$router->post('/admin/admin-users/create', [AdminRolesController::class, 'createAdminUser']);
$router->post('/admin/admin-users/update', [AdminRolesController::class, 'updateAdminUser']);
$router->post('/admin/admin-users/delete', [AdminRolesController::class, 'deleteAdminUser']);

// =====================================================================
// ADMIN – Markets & Assets
// =====================================================================
$router->get('/admin/assets', [AdminAssetsController::class, 'index']);
$router->get('/admin/assets/pairs', [AdminAssetsController::class, 'pairs']);
$router->post('/admin/assets/currency/create', [AdminAssetsController::class, 'createCurrency']);
$router->post('/admin/assets/currency/update', [AdminAssetsController::class, 'updateCurrency']);
$router->post('/admin/assets/currency/delete', [AdminAssetsController::class, 'deleteCurrency']);
$router->post('/admin/assets/currency/toggle', [AdminAssetsController::class, 'toggleCurrency']);
$router->post('/admin/assets/pair/create', [AdminAssetsController::class, 'createPair']);
$router->post('/admin/assets/pair/update', [AdminAssetsController::class, 'updatePair']);
$router->post('/admin/assets/pair/delete', [AdminAssetsController::class, 'deletePair']);

// =====================================================================
// ADMIN – Orders Management
// =====================================================================
$router->get('/admin/orders', [AdminOrdersController::class, 'index']);
$router->get('/admin/order', [AdminOrdersController::class, 'detail']);
$router->post('/admin/orders/cancel', [AdminOrdersController::class, 'cancelOrder']);
$router->post('/admin/orders/bulk-cancel', [AdminOrdersController::class, 'bulkCancel']);

// =====================================================================
// ADMIN – Wallets Management
// =====================================================================
$router->get('/admin/wallets', [AdminWalletsController::class, 'index']);
$router->get('/admin/wallet/ledger', [AdminWalletsController::class, 'ledger']);
$router->post('/admin/wallets/freeze', [AdminWalletsController::class, 'freeze']);
$router->post('/admin/wallets/unfreeze', [AdminWalletsController::class, 'unfreeze']);

// =====================================================================
// ADMIN – KYC Verification
// =====================================================================
$router->get('/admin/kyc', [AdminKycController::class, 'index']);
$router->post('/admin/kyc/review', [AdminKycController::class, 'review']);

// =====================================================================
// ADMIN – Finance (Deposits & Withdrawals)
// =====================================================================
$router->get('/admin/finance', [AdminManagementController::class, 'finance']);
$router->post('/admin/finance/deposit', [AdminManagementController::class, 'reviewDeposit']);
$router->post('/admin/finance/withdrawal', [AdminManagementController::class, 'reviewWithdrawal']);

// =====================================================================
// ADMIN – Trading Management
// =====================================================================
$router->get('/admin/trading', [AdminManagementController::class, 'trading']);
$router->post('/admin/trading/pair/update', [AdminManagementController::class, 'updateTradingPair']);
$router->post('/admin/trading/halt', [AdminManagementController::class, 'haltTrading']);
$router->post('/admin/trading/halt/resolve', [AdminManagementController::class, 'resolveHalt']);

// =====================================================================
// ADMIN – Risk & Compliance
// =====================================================================
$router->get('/admin/risk', [AdminManagementController::class, 'risk']);
$router->post('/admin/risk/flag/update', [AdminManagementController::class, 'updateRiskFlag']);
$router->post('/admin/risk/ip/block', [AdminManagementController::class, 'blockIP']);
$router->post('/admin/risk/ip/unblock', [AdminManagementController::class, 'unblockIP']);

// =====================================================================
// ADMIN – Communications
// =====================================================================
$router->get('/admin/communications', [AdminManagementController::class, 'communications']);
$router->post('/admin/communications/notify', [AdminManagementController::class, 'sendNotification']);
$router->post('/admin/communications/template', [AdminManagementController::class, 'saveEmailTemplate']);

// =====================================================================
// ADMIN – Support Tickets
// =====================================================================
$router->get('/admin/support', [AdminManagementController::class, 'support']);
$router->post('/admin/support/update', [AdminManagementController::class, 'updateSupportTicket']);
$router->post('/admin/support/reply', [AdminManagementController::class, 'replySupportTicket']);

// =====================================================================
// ADMIN – Content Management (CMS)
// =====================================================================
$router->get('/admin/content', [AdminContentController::class, 'index']);
$router->post('/admin/content/announcement/create', [AdminContentController::class, 'createAnnouncement']);
$router->post('/admin/content/announcement/update', [AdminContentController::class, 'updateAnnouncement']);
$router->post('/admin/content/announcement/delete', [AdminContentController::class, 'deleteAnnouncement']);
$router->post('/admin/content/banner/create', [AdminContentController::class, 'createBanner']);
$router->post('/admin/content/banner/update', [AdminContentController::class, 'updateBanner']);
$router->post('/admin/content/banner/delete', [AdminContentController::class, 'deleteBanner']);
$router->post('/admin/content/email-template/save', [AdminContentController::class, 'saveEmailTemplate']);
$router->post('/admin/content/email-template/delete', [AdminContentController::class, 'deleteEmailTemplate']);
$router->post('/admin/content/document/save', [AdminContentController::class, 'saveLegalDocument']);
$router->post('/admin/content/document/delete', [AdminContentController::class, 'deleteLegalDocument']);

// =====================================================================
// ADMIN – Activity & Audit Logs
// =====================================================================
$router->get('/admin/logs', [AdminLogsController::class, 'index']);

// =====================================================================
// ADMIN – System Management
// =====================================================================
$router->get('/admin/system', [AdminSystemController::class, 'index']);
$router->post('/admin/system/flags/create', [AdminSystemController::class, 'createFlag']);
$router->post('/admin/system/flags/update', [AdminSystemController::class, 'updateFlag']);
$router->post('/admin/system/flags/delete', [AdminSystemController::class, 'deleteFlag']);
$router->post('/admin/system/maintenance/create', [AdminSystemController::class, 'createMaintenance']);
$router->post('/admin/system/maintenance/status', [AdminSystemController::class, 'updateMaintenanceStatus']);
$router->post('/admin/system/maintenance/delete', [AdminSystemController::class, 'deleteMaintenance']);
$router->post('/admin/system/settings/update', [AdminSystemController::class, 'updateSetting']);
$router->post('/admin/system/settings/create', [AdminSystemController::class, 'createSetting']);
$router->post('/admin/system/providers/toggle', [AdminSystemController::class, 'toggleProvider']);
$router->post('/admin/system/webhooks/create', [AdminSystemController::class, 'createWebhook']);
$router->post('/admin/system/webhooks/delete', [AdminSystemController::class, 'deleteWebhook']);

// =====================================================================
// ADMIN – Settings (legacy route kept for compatibility)
// =====================================================================
$router->get('/admin/settings', [AdminManagementController::class, 'settings']);
$router->post('/admin/settings/update', [AdminManagementController::class, 'updateSetting']);

// =====================================================================
// USER – Dashboard & Trading
// =====================================================================
$router->get('/dashboard', [UserDashboardController::class, 'index']);
$router->get('/dashboard/metrics', [UserDashboardController::class, 'metrics']);
$router->get('/trading', [UserPlatformController::class, 'index']);
$router->get('/trading/snapshot', [UserPlatformController::class, 'snapshot']);
$router->get('/staking', [UserStakingController::class, 'index']);
$router->get('/staking/snapshot', [UserStakingController::class, 'snapshot']);
$router->get('/convert', [UserConvertController::class, 'index']);
$router->get('/convert/snapshot', [UserConvertController::class, 'snapshot']);

// =====================================================================
// INSTALLER
// =====================================================================
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
