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
use App\Controllers\Admin\TradesController as AdminTradesController;
use App\Controllers\Admin\PositionsController as AdminPositionsController;
use App\Controllers\Admin\PlatformController as AdminPlatformController;
use App\Controllers\Admin\RolesController as AdminRolesController;
use App\Controllers\Admin\SystemController as AdminSystemController;
use App\Controllers\Admin\WalletsController as AdminWalletsController;
use App\Controllers\Admin\DepositsController as AdminDepositsController;
use App\Controllers\Admin\WithdrawalsController as AdminWithdrawalsController;
use App\Controllers\Admin\TradingEngineController as AdminTradingEngineController;
use App\Controllers\Admin\MarketsController as AdminMarketsController;
use App\Controllers\Admin\AdminMarketController;
use App\Controllers\Admin\AdminExchangeController;
use App\Controllers\Admin\MarketSyncController;
use App\Controllers\Admin\ChartsController as AdminChartsController;
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
use App\Controllers\User\PortfolioController as UserPortfolioController;
use App\Controllers\User\WalletController as UserWalletController;
use App\Controllers\User\DepositController as UserDepositController;
use App\Controllers\User\WithdrawalController as UserWithdrawalController;
use App\Controllers\User\TradingController as UserTradingController;
use App\Controllers\User\MarketsController as UserMarketsController;
use App\Controllers\User\ChartsController as UserChartsController;
use App\Controllers\Admin\NotificationsController as AdminNotificationsController;
use App\Controllers\Admin\AffiliateController as AdminAffiliateController;
use App\Controllers\Admin\CmsController as AdminCmsController;
use App\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Controllers\PublicCmsController;
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
$router->post('/admin/assets/pairs/import', [AdminAssetsController::class, 'importPairs']);

// =====================================================================
// ADMIN – Markets (Market Overview, Price Feed, Providers, Statistics)
// =====================================================================
$router->get('/admin/markets',                      [AdminMarketsController::class, 'index']);
$router->get('/admin/markets/data-sync',            [AdminMarketController::class, 'index']);
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
$router->get('/admin/markets/exchange/binance',     [AdminExchangeController::class, 'binanceInfo']);
$router->post('/admin/markets/sync/exchange-info',  [MarketSyncController::class, 'syncExchangeInfo']);
$router->post('/admin/markets/sync/tickers',        [MarketSyncController::class, 'syncTickers']);
$router->post('/admin/markets/sync/candles',        [MarketSyncController::class, 'syncCandles']);

// =====================================================================
// ADMIN – Charts & Market Analytics
// =====================================================================
$router->get('/admin/charts',               [AdminChartsController::class, 'index']);
$router->get('/admin/charts/data',          [AdminChartsController::class, 'data']);
$router->get('/admin/charts/volatility',    [AdminChartsController::class, 'volatility']);
$router->get('/admin/charts/correlation',   [AdminChartsController::class, 'correlation']);
$router->get('/admin/charts/heatmap',       [AdminChartsController::class, 'heatmap']);
$router->get('/admin/charts/export',        [AdminChartsController::class, 'export']);

// =====================================================================
// ADMIN – Orders Management
// =====================================================================
$router->get('/admin/orders', [AdminOrdersController::class, 'index']);
$router->get('/admin/order', [AdminOrdersController::class, 'detail']);
$router->post('/admin/orders/cancel', [AdminOrdersController::class, 'cancelOrder']);
$router->post('/admin/orders/bulk-cancel', [AdminOrdersController::class, 'bulkCancel']);
$router->get('/admin/orders/reports', [AdminOrdersController::class, 'reports']);
$router->get('/admin/orders/export',  [AdminOrdersController::class, 'export']);

// =====================================================================
// ADMIN – Wallets Management
// =====================================================================
$router->get('/admin/wallets', [AdminWalletsController::class, 'index']);
$router->get('/admin/wallet/ledger', [AdminWalletsController::class, 'ledger']);
$router->post('/admin/wallets/lookup', [AdminWalletsController::class, 'lookup']);
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
$router->get('/admin/kyc',                          [AdminKycController::class, 'index']);
$router->post('/admin/kyc/review',                  [AdminKycController::class, 'review']);
$router->get('/admin/kyc/detail',                   [AdminKycController::class, 'detail']);
$router->get('/admin/kyc/user',                     [AdminKycController::class, 'userProfile']);
$router->post('/admin/kyc/bulk-approve',            [AdminKycController::class, 'bulkApprove']);
$router->post('/admin/kyc/bulk-reject',             [AdminKycController::class, 'bulkReject']);
$router->post('/admin/kyc/re-request',              [AdminKycController::class, 'reRequest']);
$router->post('/admin/kyc/risk',                    [AdminKycController::class, 'risk']);
$router->get('/admin/kyc/compliance',               [AdminKycController::class, 'compliance']);
$router->get('/admin/kyc/export',                   [AdminKycController::class, 'export']);
$router->get('/admin/kyc/requirements',             [AdminKycController::class, 'requirements']);
$router->post('/admin/kyc/requirements/update',     [AdminKycController::class, 'requirementUpdate']);
$router->get('/admin/kyc/audit',                    [AdminKycController::class, 'auditLog']);

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
// ADMIN – Support Tickets (dedicated controller)
// =====================================================================
$router->get('/admin/tickets',                         [\App\Controllers\Admin\TicketsController::class, 'index']);
$router->get('/admin/tickets/list',                    [\App\Controllers\Admin\TicketsController::class, 'list']);
$router->get('/admin/tickets/detail',                  [\App\Controllers\Admin\TicketsController::class, 'detail']);
$router->post('/admin/tickets/update',                 [\App\Controllers\Admin\TicketsController::class, 'update']);
$router->post('/admin/tickets/reply',                  [\App\Controllers\Admin\TicketsController::class, 'reply']);
$router->post('/admin/tickets/note',                   [\App\Controllers\Admin\TicketsController::class, 'note']);
$router->post('/admin/tickets/bulk',                   [\App\Controllers\Admin\TicketsController::class, 'bulk']);
$router->get('/admin/tickets/categories',              [\App\Controllers\Admin\TicketsController::class, 'categories']);
$router->post('/admin/tickets/categories/create',      [\App\Controllers\Admin\TicketsController::class, 'createCategory']);
$router->post('/admin/tickets/categories/update',      [\App\Controllers\Admin\TicketsController::class, 'updateCategory']);
$router->post('/admin/tickets/categories/delete',      [\App\Controllers\Admin\TicketsController::class, 'deleteCategory']);
$router->get('/admin/tickets/analytics',               [\App\Controllers\Admin\TicketsController::class, 'analytics']);
$router->get('/admin/tickets/export',                  [\App\Controllers\Admin\TicketsController::class, 'export']);
$router->post('/admin/tickets/tags/create',            [\App\Controllers\Admin\TicketsController::class, 'createTag']);
$router->post('/admin/tickets/tags/update',            [\App\Controllers\Admin\TicketsController::class, 'updateTags']);
// Legacy redirect (keep old /admin/support route working)
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
// ADMIN – Settings Hub (new comprehensive settings module)
// =====================================================================
$router->get( '/admin/settings/hub',                     [AdminSettingsController::class, 'hub']);
// General
$router->get( '/admin/settings/general',                 [AdminSettingsController::class, 'general']);
$router->post('/admin/settings/general',                 [AdminSettingsController::class, 'saveGeneral']);
// Company
$router->get( '/admin/settings/company',                 [AdminSettingsController::class, 'company']);
$router->post('/admin/settings/company',                 [AdminSettingsController::class, 'saveCompany']);
// Branding
$router->get( '/admin/settings/branding',                [AdminSettingsController::class, 'branding']);
$router->post('/admin/settings/branding',                [AdminSettingsController::class, 'saveBranding']);
// Theme Management
$router->get( '/admin/settings/theme',                   [AdminSettingsController::class, 'theme']);
$router->post('/admin/settings/theme/save',              [AdminSettingsController::class, 'saveTheme']);
$router->post('/admin/settings/theme/activate',          [AdminSettingsController::class, 'activateTheme']);
$router->post('/admin/settings/theme/delete',            [AdminSettingsController::class, 'deleteTheme']);
// Localization
$router->get( '/admin/settings/localization',            [AdminSettingsController::class, 'localization']);
$router->post('/admin/settings/localization',            [AdminSettingsController::class, 'saveLocalization']);
// Languages
$router->get( '/admin/settings/languages',               [AdminSettingsController::class, 'languages']);
$router->post('/admin/settings/languages/create',        [AdminSettingsController::class, 'createLanguage']);
$router->post('/admin/settings/languages/update',        [AdminSettingsController::class, 'updateLanguage']);
$router->post('/admin/settings/languages/delete',        [AdminSettingsController::class, 'deleteLanguage']);
$router->post('/admin/settings/languages/default',       [AdminSettingsController::class, 'setDefaultLanguage']);
// SMTP
$router->get( '/admin/settings/smtp',                    [AdminSettingsController::class, 'smtp']);
$router->post('/admin/settings/smtp/save',               [AdminSettingsController::class, 'saveSmtp']);
$router->post('/admin/settings/smtp/default',            [AdminSettingsController::class, 'setDefaultSmtp']);
$router->post('/admin/settings/smtp/delete',             [AdminSettingsController::class, 'deleteSmtp']);
$router->post('/admin/settings/smtp/test',               [AdminSettingsController::class, 'testSmtp']);
// SMS
$router->get( '/admin/settings/sms',                     [AdminSettingsController::class, 'sms']);
$router->post('/admin/settings/sms/save',                [AdminSettingsController::class, 'saveSms']);
$router->post('/admin/settings/sms/default',             [AdminSettingsController::class, 'setDefaultSms']);
$router->post('/admin/settings/sms/delete',              [AdminSettingsController::class, 'deleteSms']);
// API Integrations
$router->get( '/admin/settings/api',                     [AdminSettingsController::class, 'apiIntegrations']);
$router->post('/admin/settings/api/save',                [AdminSettingsController::class, 'saveApiIntegration']);
$router->post('/admin/settings/api/delete',              [AdminSettingsController::class, 'deleteApiIntegration']);
// Trading Configuration
$router->get( '/admin/settings/trading',                 [AdminSettingsController::class, 'tradingConfig']);
$router->post('/admin/settings/trading',                 [AdminSettingsController::class, 'saveTradingConfig']);
// Wallet Configuration
$router->get( '/admin/settings/wallet',                  [AdminSettingsController::class, 'walletConfig']);
$router->post('/admin/settings/wallet',                  [AdminSettingsController::class, 'saveWalletConfig']);
// Security Configuration
$router->get( '/admin/settings/security',                [AdminSettingsController::class, 'securityConfig']);
$router->post('/admin/settings/security',                [AdminSettingsController::class, 'saveSecurityConfig']);
// Maintenance Mode
$router->get( '/admin/settings/maintenance',             [AdminSettingsController::class, 'maintenance']);
$router->post('/admin/settings/maintenance',             [AdminSettingsController::class, 'saveMaintenance']);
// Backup Management
$router->get( '/admin/settings/backup',                  [AdminSettingsController::class, 'backup']);
$router->post('/admin/settings/backup/settings',         [AdminSettingsController::class, 'saveBackupSettings']);
$router->post('/admin/settings/backup/run',              [AdminSettingsController::class, 'runBackup']);
$router->post('/admin/settings/backup/delete-log',       [AdminSettingsController::class, 'deleteBackupLog']);
// Cache Management
$router->get( '/admin/settings/cache',                   [AdminSettingsController::class, 'cache']);
$router->post('/admin/settings/cache/config',            [AdminSettingsController::class, 'saveCacheConfig']);
$router->post('/admin/settings/cache/flush',             [AdminSettingsController::class, 'flushCache']);
// System Information
$router->get( '/admin/settings/system-info',             [AdminSettingsController::class, 'systemInfo']);

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
// ADMIN – Trades Management
// =====================================================================
$router->get('/admin/trades',         [AdminTradesController::class, 'index']);
$router->get('/admin/trades/reports', [AdminTradesController::class, 'reports']);
$router->get('/admin/trades/export',  [AdminTradesController::class, 'export']);

// =====================================================================
// ADMIN – Positions Management
// =====================================================================
$router->get('/admin/positions',              [AdminPositionsController::class, 'index']);
$router->get('/admin/positions/risk',         [AdminPositionsController::class, 'risk']);
$router->post('/admin/positions/force-close', [AdminPositionsController::class, 'forceClose']);
$router->get('/admin/positions/export',       [AdminPositionsController::class, 'export']);

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
$router->get('/dashboard', [UserDashboardController::class, 'index']);
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
$router->get('/user/kyc',             [UserKycController::class, 'index']);
$router->post('/user/kyc/submit',     [UserKycController::class, 'submit']);
$router->post('/user/kyc/delete',     [UserKycController::class, 'delete']);
$router->get('/user/kyc/document',    [UserKycController::class, 'document']);
$router->get('/user/kyc/status',      [UserKycController::class, 'status']);

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
$router->get('/user/orders',              [UserOrdersController::class, 'index']);
$router->get('/user/orders/history',     [UserOrdersController::class, 'history']);
$router->get('/user/orders/detail',      [UserOrdersController::class, 'detail']);
$router->get('/user/orders/export',      [UserOrdersController::class, 'export']);
$router->post('/user/orders/cancel',     [UserOrdersController::class, 'cancel']);

// =====================================================================
// USER – Trades
// =====================================================================
$router->get('/user/trades',        [UserTradesController::class, 'index']);
$router->get('/user/trades/export', [UserTradesController::class, 'export']);

// =====================================================================
// USER – Positions
// =====================================================================
$router->get('/user/positions',            [UserPositionsController::class, 'index']);
$router->get('/user/positions/history',    [UserPositionsController::class, 'history']);
$router->get('/user/positions/analytics',  [UserPositionsController::class, 'analytics']);
$router->post('/user/positions/close',     [UserPositionsController::class, 'close']);
$router->post('/user/positions/add-margin',[UserPositionsController::class, 'addMargin']);

// =====================================================================
// USER – Notifications
// =====================================================================
$router->get('/user/notifications',                     [UserNotificationsController::class, 'index']);
$router->get('/user/notifications/preferences',         [UserNotificationsController::class, 'preferences']);
$router->post('/user/notifications/preferences',        [UserNotificationsController::class, 'savePreferences']);
$router->get('/user/notifications/history',             [UserNotificationsController::class, 'history']);
$router->post('/user/notifications/read',               [UserNotificationsController::class, 'markRead']);
$router->post('/user/notifications/read-all',           [UserNotificationsController::class, 'markAllRead']);
$router->post('/user/notifications/read-type',          [UserNotificationsController::class, 'markTypeRead']);
$router->post('/user/notifications/delete',             [UserNotificationsController::class, 'delete']);
$router->post('/user/notifications/delete-read',        [UserNotificationsController::class, 'deleteAllRead']);
$router->get('/user/notifications/poll',                [UserNotificationsController::class, 'poll']);
$router->post('/user/notifications/announcement-read',  [UserNotificationsController::class, 'announcementRead']);
$router->post('/user/notifications/push-register',      [UserNotificationsController::class, 'pushRegister']);
$router->post('/user/notifications/push-deregister',    [UserNotificationsController::class, 'pushDeregister']);

// =====================================================================
// USER – Support Tickets
// =====================================================================
$router->get('/user/tickets',         [UserTicketsController::class, 'index']);
$router->get('/user/tickets/create',  [UserTicketsController::class, 'create']);
$router->post('/user/tickets',        [UserTicketsController::class, 'store']);
$router->get('/user/tickets/view',    [UserTicketsController::class, 'show']);
$router->post('/user/tickets/reply',  [UserTicketsController::class, 'reply']);
$router->post('/user/tickets/close',  [UserTicketsController::class, 'close']);
$router->post('/user/tickets/rate',   [UserTicketsController::class, 'rate']);

// =====================================================================
// USER – Portfolio Analytics
// =====================================================================
$router->get('/user/portfolio',            [UserPortfolioController::class, 'index']);
$router->get('/user/portfolio/analytics',  [UserPortfolioController::class, 'analytics']);

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
// USER – Advanced Charts & Market Analytics
// =====================================================================
$router->get('/charts',                        [UserChartsController::class, 'index']);
$router->get('/charts/data',                   [UserChartsController::class, 'data']);
$router->get('/charts/volume-profile',         [UserChartsController::class, 'volumeProfile']);
$router->get('/charts/compare',                [UserChartsController::class, 'compare']);
$router->get('/charts/compare/data',           [UserChartsController::class, 'compareData']);
$router->post('/charts/preferences',           [UserChartsController::class, 'savePreferences']);
$router->get('/charts/templates',              [UserChartsController::class, 'listTemplates']);
$router->post('/charts/templates',             [UserChartsController::class, 'saveTemplate']);
$router->post('/charts/templates/delete',      [UserChartsController::class, 'deleteTemplate']);

// =====================================================================
// USER – Signals, Alerts & Automation
// =====================================================================
$router->get('/user/signals',                        [\App\Controllers\User\SignalsController::class, 'index']);
$router->get('/user/signals/feed',                   [\App\Controllers\User\SignalsController::class, 'feed']);
$router->get('/user/signals/detail',                 [\App\Controllers\User\SignalsController::class, 'detail']);
$router->post('/user/signals/like',                  [\App\Controllers\User\SignalsController::class, 'like']);
$router->post('/user/signals/bookmark',              [\App\Controllers\User\SignalsController::class, 'bookmark']);
$router->get('/user/signals/bookmarks',              [\App\Controllers\User\SignalsController::class, 'bookmarks']);
$router->get('/user/signals/subscribe',              [\App\Controllers\User\SignalsController::class, 'subscriptions']);
$router->post('/user/signals/subscribe',             [\App\Controllers\User\SignalsController::class, 'subscribe']);
$router->post('/user/signals/unsubscribe',           [\App\Controllers\User\SignalsController::class, 'unsubscribe']);
$router->post('/user/signals/subscription-prefs',    [\App\Controllers\User\SignalsController::class, 'subscriptionPrefs']);
$router->get('/user/signals/alerts',                 [\App\Controllers\User\SignalsController::class, 'alerts']);
$router->post('/user/signals/alerts/create',         [\App\Controllers\User\SignalsController::class, 'createAlert']);
$router->post('/user/signals/alerts/update',         [\App\Controllers\User\SignalsController::class, 'updateAlert']);
$router->post('/user/signals/alerts/delete',         [\App\Controllers\User\SignalsController::class, 'deleteAlert']);
$router->post('/user/signals/alerts/pause',          [\App\Controllers\User\SignalsController::class, 'pauseAlert']);
$router->post('/user/signals/alerts/resume',         [\App\Controllers\User\SignalsController::class, 'resumeAlert']);
$router->get('/user/signals/automation',             [\App\Controllers\User\SignalsController::class, 'automation']);
$router->post('/user/signals/automation/create',     [\App\Controllers\User\SignalsController::class, 'createRule']);
$router->post('/user/signals/automation/update',     [\App\Controllers\User\SignalsController::class, 'updateRule']);
$router->post('/user/signals/automation/delete',     [\App\Controllers\User\SignalsController::class, 'deleteRule']);
$router->post('/user/signals/automation/toggle',     [\App\Controllers\User\SignalsController::class, 'toggleRule']);
$router->get('/user/signals/automation/logs',        [\App\Controllers\User\SignalsController::class, 'ruleLogs']);
$router->get('/user/signals/performance',            [\App\Controllers\User\SignalsController::class, 'performance']);

// =====================================================================
// ADMIN – Signals, Alerts & Automation
// =====================================================================
$router->get('/admin/signals',                       [\App\Controllers\Admin\SignalsController::class, 'index']);
$router->get('/admin/signals/list',                  [\App\Controllers\Admin\SignalsController::class, 'list']);
$router->post('/admin/signals/create',               [\App\Controllers\Admin\SignalsController::class, 'create']);
$router->post('/admin/signals/status',               [\App\Controllers\Admin\SignalsController::class, 'updateStatus']);
$router->post('/admin/signals/delete',               [\App\Controllers\Admin\SignalsController::class, 'deleteSignal']);
$router->get('/admin/signals/providers',             [\App\Controllers\Admin\SignalsController::class, 'providers']);
$router->post('/admin/signals/providers/create',     [\App\Controllers\Admin\SignalsController::class, 'createProvider']);
$router->post('/admin/signals/providers/update',     [\App\Controllers\Admin\SignalsController::class, 'updateProvider']);
$router->post('/admin/signals/providers/toggle',     [\App\Controllers\Admin\SignalsController::class, 'toggleProvider']);
$router->post('/admin/signals/providers/recalc',     [\App\Controllers\Admin\SignalsController::class, 'recalcPerformance']);
$router->get('/admin/signals/alerts',                [\App\Controllers\Admin\SignalsController::class, 'alerts']);
$router->get('/admin/signals/performance',           [\App\Controllers\Admin\SignalsController::class, 'performance']);

// =====================================================================
// ADMIN – Notification Center
// =====================================================================
$router->get('/admin/notifications',                              [AdminNotificationsController::class, 'index']);
$router->get('/admin/notifications/history',                      [AdminNotificationsController::class, 'history']);
$router->get('/admin/notifications/export',                       [AdminNotificationsController::class, 'export']);
$router->get('/admin/notifications/broadcast',                    [AdminNotificationsController::class, 'broadcast']);
$router->post('/admin/notifications/broadcast',                   [AdminNotificationsController::class, 'broadcast']);
$router->get('/admin/notifications/announcements',                [AdminNotificationsController::class, 'announcements']);
$router->post('/admin/notifications/announcements/create',        [AdminNotificationsController::class, 'createAnnouncement']);
$router->post('/admin/notifications/announcements/update',        [AdminNotificationsController::class, 'updateAnnouncement']);
$router->post('/admin/notifications/announcements/delete',        [AdminNotificationsController::class, 'deleteAnnouncement']);
$router->post('/admin/notifications/announcements/toggle',        [AdminNotificationsController::class, 'toggleAnnouncement']);
$router->get('/admin/notifications/templates',                    [AdminNotificationsController::class, 'templates']);
$router->post('/admin/notifications/templates/save',              [AdminNotificationsController::class, 'saveTemplate']);
$router->post('/admin/notifications/templates/delete',            [AdminNotificationsController::class, 'deleteTemplate']);

// =====================================================================
// =====================================================================
// USER – Referral Program (expanded)
// =====================================================================
$router->get('/user/referral',              [UserReferralController::class, 'index']);
$router->get('/user/referral/referrals',    [UserReferralController::class, 'referrals']);
$router->get('/user/referral/network',      [UserReferralController::class, 'network']);
$router->get('/user/referral/commissions',  [UserReferralController::class, 'commissions']);
$router->get('/user/referral/rewards',      [UserReferralController::class, 'rewards']);
$router->get('/user/referral/withdraw',     [UserReferralController::class, 'withdraw']);
$router->post('/user/referral/withdraw',    [UserReferralController::class, 'requestWithdraw']);

// =====================================================================
// ADMIN – Affiliate Program & Commission Engine
// =====================================================================
$router->get('/admin/affiliate',                         [AdminAffiliateController::class, 'index']);
$router->get('/admin/affiliate/affiliates',              [AdminAffiliateController::class, 'affiliates']);
$router->get('/admin/affiliate/commissions',             [AdminAffiliateController::class, 'commissions']);
$router->post('/admin/affiliate/commissions/mark-paid',  [AdminAffiliateController::class, 'markPaid']);
$router->get('/admin/affiliate/commissions/export',      [AdminAffiliateController::class, 'exportCommissions']);
$router->get('/admin/affiliate/payouts',                 [AdminAffiliateController::class, 'payouts']);
$router->post('/admin/affiliate/payouts/process',        [AdminAffiliateController::class, 'processPayout']);
$router->post('/admin/affiliate/payouts/bulk',           [AdminAffiliateController::class, 'bulkPayouts']);
$router->get('/admin/affiliate/payouts/export',          [AdminAffiliateController::class, 'exportPayouts']);
$router->get('/admin/affiliate/tiers',                   [AdminAffiliateController::class, 'tiers']);
$router->post('/admin/affiliate/tiers',                  [AdminAffiliateController::class, 'saveTiers']);
$router->get('/admin/affiliate/settings',                [AdminAffiliateController::class, 'settings']);
$router->post('/admin/affiliate/settings',               [AdminAffiliateController::class, 'saveSettings']);
$router->get('/admin/affiliate/reports',                 [AdminAffiliateController::class, 'reports']);

// =====================================================================
// ADMIN – CMS, Website Builder, Blog and SEO
// =====================================================================
$router->get('/admin/cms',                              [AdminCmsController::class, 'index']);
$router->get('/admin/cms/media',                        [AdminCmsController::class, 'media']);
$router->post('/admin/cms/media/upload',                [AdminCmsController::class, 'mediaUpload']);
$router->post('/admin/cms/media/update',                [AdminCmsController::class, 'mediaUpdate']);
$router->post('/admin/cms/media/delete',                [AdminCmsController::class, 'mediaDelete']);
$router->get('/admin/cms/pages',                        [AdminCmsController::class, 'pages']);
$router->get('/admin/cms/pages/create',                 [AdminCmsController::class, 'pageCreate']);
$router->post('/admin/cms/pages/create',                [AdminCmsController::class, 'pageCreate']);
$router->get('/admin/cms/pages/edit',                   [AdminCmsController::class, 'pageEdit']);
$router->post('/admin/cms/pages/update',                [AdminCmsController::class, 'pageUpdate']);
$router->post('/admin/cms/pages/delete',                [AdminCmsController::class, 'pageDelete']);
$router->get('/admin/cms/homepage',                     [AdminCmsController::class, 'homepage']);
$router->post('/admin/cms/homepage/section/save',       [AdminCmsController::class, 'saveHomepageSection']);
$router->post('/admin/cms/homepage/reorder',            [AdminCmsController::class, 'reorderHomepage']);
$router->get('/admin/cms/blog',                         [AdminCmsController::class, 'blog']);
$router->get('/admin/cms/blog/create',                  [AdminCmsController::class, 'postCreate']);
$router->post('/admin/cms/blog/create',                 [AdminCmsController::class, 'postCreate']);
$router->get('/admin/cms/blog/edit',                    [AdminCmsController::class, 'postEdit']);
$router->post('/admin/cms/blog/update',                 [AdminCmsController::class, 'postUpdate']);
$router->post('/admin/cms/blog/delete',                 [AdminCmsController::class, 'postDelete']);
$router->get('/admin/cms/blog/categories',              [AdminCmsController::class, 'blogCategories']);
$router->post('/admin/cms/blog/categories/save',        [AdminCmsController::class, 'saveBlogCategory']);
$router->post('/admin/cms/blog/categories/delete',      [AdminCmsController::class, 'deleteBlogCategory']);
$router->get('/admin/cms/faq',                          [AdminCmsController::class, 'faq']);
$router->post('/admin/cms/faq/category/save',           [AdminCmsController::class, 'saveFaqCategory']);
$router->post('/admin/cms/faq/category/delete',         [AdminCmsController::class, 'deleteFaqCategory']);
$router->post('/admin/cms/faq/save',                    [AdminCmsController::class, 'saveFaq']);
$router->post('/admin/cms/faq/delete',                  [AdminCmsController::class, 'deleteFaq']);
$router->get('/admin/cms/testimonials',                 [AdminCmsController::class, 'testimonials']);
$router->post('/admin/cms/testimonials/save',           [AdminCmsController::class, 'saveTestimonial']);
$router->post('/admin/cms/testimonials/delete',         [AdminCmsController::class, 'deleteTestimonial']);
$router->get('/admin/cms/features',                     [AdminCmsController::class, 'features']);
$router->post('/admin/cms/features/save',               [AdminCmsController::class, 'saveFeature']);
$router->post('/admin/cms/features/delete',             [AdminCmsController::class, 'deleteFeature']);
$router->get('/admin/cms/pricing',                      [AdminCmsController::class, 'pricing']);
$router->post('/admin/cms/pricing/save',                [AdminCmsController::class, 'savePricing']);
$router->post('/admin/cms/pricing/delete',              [AdminCmsController::class, 'deletePricing']);
$router->get('/admin/cms/contact',                      [AdminCmsController::class, 'contact']);
$router->get('/admin/cms/contact/view',                 [AdminCmsController::class, 'contactView']);
$router->post('/admin/cms/contact/reply',               [AdminCmsController::class, 'contactReply']);
$router->post('/admin/cms/contact/spam',                [AdminCmsController::class, 'contactSpam']);
$router->post('/admin/cms/contact/delete',              [AdminCmsController::class, 'contactDelete']);
$router->get('/admin/cms/seo',                          [AdminCmsController::class, 'seo']);
$router->post('/admin/cms/seo/save',                    [AdminCmsController::class, 'saveSeo']);
$router->get('/admin/cms/sitemap',                      [AdminCmsController::class, 'sitemap']);
$router->get('/admin/cms/sitemap/download',             [AdminCmsController::class, 'sitemapDownload']);

// =====================================================================
// PUBLIC – CMS, Blog, FAQ, Contact, Sitemap
// =====================================================================
$router->get('/blog',                   [PublicCmsController::class, 'blog']);
$router->get('/blog/post',              [PublicCmsController::class, 'blogPost']);
$router->get('/news',                   [PublicCmsController::class, 'news']);
$router->get('/news/post',              [PublicCmsController::class, 'blogPost']);
$router->get('/faq',                    [PublicCmsController::class, 'faq']);
$router->post('/faq/vote',              [PublicCmsController::class, 'faqVote']);
$router->get('/contact',                [PublicCmsController::class, 'contact']);
$router->post('/contact',               [PublicCmsController::class, 'contact']);
$router->get('/page',                   [PublicCmsController::class, 'page']);
$router->get('/sitemap.xml',            [PublicCmsController::class, 'sitemap']);

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
