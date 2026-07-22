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
use App\Controllers\Admin\DepositsController as AdminDepositsController;
use App\Controllers\Admin\WithdrawalsController as AdminWithdrawalsController;
use App\Controllers\Admin\TradingEngineController as AdminTradingEngineController;
use App\Controllers\Admin\MarketsController as AdminMarketsController;
use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\InstallerController;
use App\Controllers\User\ApiKeysController as UserApiKeysController;
use App\Controllers\User\ConvertController as UserConvertController;
use App\Controllers\User\DashboardController as UserDashboardController;
use App\Controllers\User\KycController as UserKycController;
use App\Controllers\User\NotificationsController as UserNotificationsController;
use App\Controllers\User\OrdersController as UserOrdersController;
use App\Controllers\User\PlatformController as UserPlatformController;
use App\Controllers\User\PositionsController as UserPositionsController;
use App\Controllers\User\ProfileController as UserProfileController;
use App\Controllers\User\ReferralController as UserReferralController;
use App\Controllers\User\SecurityController as UserSecurityController;
use App\Controllers\User\StakingController as UserStakingController;
use App\Controllers\User\TicketsController as UserTicketsController;
use App\Controllers\User\TradesController as UserTradesController;
use App\Controllers\User\WalletController as UserWalletController;
use App\Controllers\User\DepositController as UserDepositController;
use App\Controllers\User\WithdrawalController as UserWithdrawalController;
use App\Controllers\User\TradingController as UserTradingController;
use App\Controllers\User\MarketsController as UserMarketsController;
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
// ADMIN – Markets (Market Overview, Price Feed, Providers, Statistics)
// =====================================================================
$router->get('/admin/markets',                      [AdminMarketsController::class, 'index']);
$router->get('/admin/markets/pairs',                [AdminMarketsController::class, 'pairs']);
$router->get('/admin/markets/pair/detail',          [AdminMarketsController::class, 'pairDetail']);
$router->get('/admin/markets/statistics',           [AdminMarketsController::class, 'statistics']);
$router->get('/admin/markets/providers',            [AdminMarketsController::class, 'providers']);
$router->post('/admin/markets/providers/create',    [AdminMarketsController::class, 'createProvider']);
$router->post('/admin/markets/providers/update',    [AdminMarketsController::class, 'updateProvider']);
$router->post('/admin/markets/providers/toggle',    [AdminMarketsController::class, 'toggleProvider']);
$router->get('/admin/markets/feed',                 [AdminMarketsController::class, 'feed']);
$router->post('/admin/markets/feed/save',           [AdminMarketsController::class, 'saveFeed']);
$router->post('/admin/markets/feed/toggle',         [AdminMarketsController::class, 'toggleFeed']);
$router->get('/admin/markets/sync-logs',            [AdminMarketsController::class, 'syncLogs']);
$router->get('/admin/markets/mappings',             [AdminMarketsController::class, 'mappings']);
$router->post('/admin/markets/mappings/save',       [AdminMarketsController::class, 'saveMapping']);
$router->post('/admin/markets/mappings/delete',     [AdminMarketsController::class, 'deleteMapping']);

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
// Deposits
$router->get('/admin/wallets/deposits', [AdminWalletsController::class, 'deposits']);
$router->post('/admin/wallets/deposits/review', [AdminWalletsController::class, 'reviewDeposit']);
// Withdrawals (legacy – kept for backward compat)
$router->get('/admin/wallets/withdrawals', [AdminWalletsController::class, 'withdrawals']);
$router->post('/admin/wallets/withdrawals/review', [AdminWalletsController::class, 'reviewWithdrawal']);

// =====================================================================
// ADMIN – Dedicated Deposit Management
// =====================================================================
$router->get('/admin/deposits',                   [AdminDepositsController::class, 'index']);
$router->get('/admin/deposits/detail',            [AdminDepositsController::class, 'detail']);
$router->post('/admin/deposits/review',           [AdminDepositsController::class, 'review']);
$router->post('/admin/deposits/bulk-credit',      [AdminDepositsController::class, 'bulkCredit']);
$router->post('/admin/deposits/bulk-flag',        [AdminDepositsController::class, 'bulkFlag']);
$router->get('/admin/deposits/reports',           [AdminDepositsController::class, 'reports']);
$router->get('/admin/deposits/export',            [AdminDepositsController::class, 'export']);
$router->get('/admin/deposits/gateways',          [AdminDepositsController::class, 'gateways']);
$router->post('/admin/deposits/gateways/update',  [AdminDepositsController::class, 'updateGateway']);

// =====================================================================
// ADMIN – Dedicated Withdrawal Management
// =====================================================================
$router->get('/admin/withdrawals',                    [AdminWithdrawalsController::class, 'index']);
$router->get('/admin/withdrawals/detail',             [AdminWithdrawalsController::class, 'detail']);
$router->post('/admin/withdrawals/review',            [AdminWithdrawalsController::class, 'review']);
$router->post('/admin/withdrawals/bulk-approve',      [AdminWithdrawalsController::class, 'bulkApprove']);
$router->post('/admin/withdrawals/bulk-reject',       [AdminWithdrawalsController::class, 'bulkReject']);
$router->get('/admin/withdrawals/reports',            [AdminWithdrawalsController::class, 'reports']);
$router->get('/admin/withdrawals/export',             [AdminWithdrawalsController::class, 'export']);
$router->get('/admin/withdrawals/gateways',           [AdminWithdrawalsController::class, 'gateways']);
$router->post('/admin/withdrawals/gateways/update',   [AdminWithdrawalsController::class, 'updateGateway']);
// Manual adjustment
$router->get('/admin/wallets/adjustment', [AdminWalletsController::class, 'adjustment']);
$router->post('/admin/wallets/adjustment', [AdminWalletsController::class, 'doAdjustment']);
// Monitoring rules
$router->get('/admin/wallets/monitoring', [AdminWalletsController::class, 'monitoring']);
$router->post('/admin/wallets/monitoring/create', [AdminWalletsController::class, 'createRule']);
$router->post('/admin/wallets/monitoring/toggle', [AdminWalletsController::class, 'toggleRule']);
$router->post('/admin/wallets/monitoring/delete', [AdminWalletsController::class, 'deleteRule']);

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
// ADMIN – Trading Engine
// =====================================================================
$router->get('/admin/trading-engine',                        [AdminTradingEngineController::class, 'index']);
$router->get('/admin/trading-engine/risk',                   [AdminTradingEngineController::class, 'risk']);
$router->post('/admin/trading-engine/liquidate',             [AdminTradingEngineController::class, 'forceLiquidate']);
$router->get('/admin/trading-engine/fee-tiers',              [AdminTradingEngineController::class, 'feeTiers']);
$router->post('/admin/trading-engine/fee-tiers/create',      [AdminTradingEngineController::class, 'createFeeTier']);
$router->post('/admin/trading-engine/fee-tiers/update',      [AdminTradingEngineController::class, 'updateFeeTier']);
$router->get('/admin/trading-engine/positions',              [AdminTradingEngineController::class, 'positions']);

// =====================================================================
// USER – Trading Terminal (full trading engine)
// =====================================================================
$router->get('/trade',                        [UserTradingController::class, 'index']);
$router->post('/trading/order/spot',          [UserTradingController::class, 'placeSpotOrder']);
$router->post('/trading/order/futures',       [UserTradingController::class, 'placeFuturesOrder']);
$router->post('/trading/order/margin',        [UserTradingController::class, 'placeMarginOrder']);
$router->post('/trading/order/cancel',        [UserTradingController::class, 'cancelOrder']);
$router->post('/trading/position/close',      [UserTradingController::class, 'closePosition']);
$router->get('/trading/orderbook',            [UserTradingController::class, 'orderBook']);
$router->get('/trading/candles',              [UserTradingController::class, 'candles']);
$router->get('/trading/ticker',               [UserTradingController::class, 'ticker']);
$router->get('/trading/tickers',              [UserTradingController::class, 'tickers']);
$router->get('/trading/recent-trades',        [UserTradingController::class, 'recentTrades']);
$router->get('/trading/my-orders',            [UserTradingController::class, 'myOrders']);
$router->get('/trading/my-positions',         [UserTradingController::class, 'myPositions']);
$router->get('/dashboard/metrics', [UserDashboardController::class, 'metrics']);
$router->get('/trading', [UserPlatformController::class, 'index']);
$router->get('/trading/snapshot', [UserPlatformController::class, 'snapshot']);
$router->get('/staking', [UserStakingController::class, 'index']);
$router->get('/staking/snapshot', [UserStakingController::class, 'snapshot']);
$router->get('/convert', [UserConvertController::class, 'index']);
$router->get('/convert/snapshot', [UserConvertController::class, 'snapshot']);

// =====================================================================
// USER – Profile
// =====================================================================
$router->get('/user/profile',        [UserProfileController::class, 'index']);
$router->post('/user/profile/update',[UserProfileController::class, 'update']);
$router->post('/user/profile/avatar',[UserProfileController::class, 'uploadAvatar']);

// =====================================================================
// USER – Security
// =====================================================================
$router->get('/user/security',                   [UserSecurityController::class, 'index']);
$router->post('/user/security/password',         [UserSecurityController::class, 'changePassword']);
$router->get('/user/security/2fa/generate',      [UserSecurityController::class, 'generate2faSecret']);
$router->post('/user/security/2fa/enable',       [UserSecurityController::class, 'enable2fa']);
$router->post('/user/security/2fa/disable',      [UserSecurityController::class, 'disable2fa']);
$router->post('/user/security/sessions/revoke',  [UserSecurityController::class, 'revokeSession']);
$router->post('/user/security/settings',         [UserSecurityController::class, 'updateSecuritySettings']);

// =====================================================================
// USER – KYC
// =====================================================================
$router->get('/user/kyc',        [UserKycController::class, 'index']);
$router->post('/user/kyc/submit',[UserKycController::class, 'submit']);

// =====================================================================
// USER – Wallet
// =====================================================================
$router->get('/user/wallet',                   [UserWalletController::class, 'index']);
$router->get('/user/wallet/deposit',           [UserWalletController::class, 'deposit']);
$router->post('/user/wallet/deposit',          [UserWalletController::class, 'submitDeposit']);
$router->get('/user/wallet/withdraw',          [UserWalletController::class, 'withdraw']);
$router->post('/user/wallet/withdraw',         [UserWalletController::class, 'submitWithdrawal']);
$router->post('/user/wallet/withdraw/cancel',  [UserWalletController::class, 'cancelWithdrawal']);
$router->get('/user/wallet/history',           [UserWalletController::class, 'history']);
$router->get('/user/wallet/transfer',          [UserWalletController::class, 'transfer']);
$router->post('/user/wallet/transfer',         [UserWalletController::class, 'submitTransfer']);
$router->get('/user/wallet/ledger',            [UserWalletController::class, 'ledger']);
$router->get('/user/wallet/addresses',         [UserWalletController::class, 'addresses']);
$router->post('/user/wallet/addresses/add',    [UserWalletController::class, 'addAddress']);
$router->post('/user/wallet/addresses/revoke', [UserWalletController::class, 'revokeAddress']);
$router->get('/user/wallet/balance',           [UserWalletController::class, 'balance']);

// =====================================================================
// USER – Dedicated Deposit System
// =====================================================================
$router->get('/user/deposit',                [UserDepositController::class, 'index']);
$router->get('/user/deposit/fiat',           [UserDepositController::class, 'fiat']);
$router->post('/user/deposit/submit-crypto', [UserDepositController::class, 'submitCrypto']);
$router->post('/user/deposit/submit-fiat',   [UserDepositController::class, 'submitFiat']);
$router->post('/user/deposit/cancel',        [UserDepositController::class, 'cancel']);
$router->get('/user/deposit/report',         [UserDepositController::class, 'report']);
$router->get('/user/deposit/address-info',   [UserDepositController::class, 'addressInfo']);

// =====================================================================
// USER – Dedicated Withdrawal System
// =====================================================================
$router->get('/user/withdrawal',                 [UserWithdrawalController::class, 'index']);
$router->get('/user/withdrawal/fiat',            [UserWithdrawalController::class, 'fiat']);
$router->post('/user/withdrawal/submit-crypto',  [UserWithdrawalController::class, 'submitCrypto']);
$router->post('/user/withdrawal/submit-fiat',    [UserWithdrawalController::class, 'submitFiat']);
$router->post('/user/withdrawal/cancel',         [UserWithdrawalController::class, 'cancel']);
$router->get('/user/withdrawal/report',          [UserWithdrawalController::class, 'report']);
$router->get('/user/withdrawal/limit-info',      [UserWithdrawalController::class, 'limitInfo']);
$router->get('/user/withdrawal/fee-preview',     [UserWithdrawalController::class, 'feePreview']);

// =====================================================================
// USER – Orders
// =====================================================================
$router->get('/user/orders',         [UserOrdersController::class, 'index']);
$router->get('/user/orders/history', [UserOrdersController::class, 'history']);
$router->post('/user/orders/cancel', [UserOrdersController::class, 'cancel']);

// =====================================================================
// USER – Trades
// =====================================================================
$router->get('/user/trades', [UserTradesController::class, 'index']);

// =====================================================================
// USER – Positions
// =====================================================================
$router->get('/user/positions',       [UserPositionsController::class, 'index']);
$router->post('/user/positions/close',[UserPositionsController::class, 'close']);

// =====================================================================
// USER – Notifications
// =====================================================================
$router->get('/user/notifications',          [UserNotificationsController::class, 'index']);
$router->post('/user/notifications/read',    [UserNotificationsController::class, 'markRead']);
$router->post('/user/notifications/read-all',[UserNotificationsController::class, 'markAllRead']);
$router->post('/user/notifications/delete',  [UserNotificationsController::class, 'delete']);

// =====================================================================
// USER – Support Tickets
// =====================================================================
$router->get('/user/tickets',         [UserTicketsController::class, 'index']);
$router->get('/user/tickets/create',  [UserTicketsController::class, 'create']);
$router->post('/user/tickets',        [UserTicketsController::class, 'store']);
$router->get('/user/tickets/view',    [UserTicketsController::class, 'show']);
$router->post('/user/tickets/reply',  [UserTicketsController::class, 'reply']);

// =====================================================================
// USER – Referral
// =====================================================================
$router->get('/user/referral', [UserReferralController::class, 'index']);

// =====================================================================
// USER – API Keys
// =====================================================================
$router->get('/user/api-keys',          [UserApiKeysController::class, 'index']);
$router->post('/user/api-keys/create',  [UserApiKeysController::class, 'create']);
$router->post('/user/api-keys/revoke',  [UserApiKeysController::class, 'revoke']);

// =====================================================================
// USER – Markets & Watchlist
// =====================================================================
$router->get('/markets',                    [UserMarketsController::class, 'index']);
$router->get('/markets/watchlist',          [UserMarketsController::class, 'watchlist']);
$router->post('/markets/watchlist/toggle',  [UserMarketsController::class, 'toggleWatchlist']);
$router->get('/markets/detail',             [UserMarketsController::class, 'detail']);
$router->get('/markets/tickers',            [UserMarketsController::class, 'tickers']);
$router->get('/markets/ticker',             [UserMarketsController::class, 'ticker']);

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
