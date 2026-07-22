<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class PlatformRepository
{
    public function adminOperationsSnapshot(): array
    {
        $pdo = Database::connection();

        return [
            'user_status' => $pdo->query("SELECT status, COUNT(*) AS total FROM users GROUP BY status ORDER BY total DESC")->fetchAll() ?: [],
            'pending_kyc' => $pdo->query("SELECT kd.id, u.username, kd.document_type, kd.status, kd.created_at FROM kyc_documents kd INNER JOIN users u ON u.id = kd.user_id WHERE kd.status = 'pending' ORDER BY kd.id DESC LIMIT 10")->fetchAll() ?: [],
            'latest_tickets' => $pdo->query("SELECT ticket_number, subject, priority, status, created_at FROM support_tickets ORDER BY id DESC LIMIT 10")->fetchAll() ?: [],
            'latest_deposits' => $pdo->query("SELECT id, amount, status, created_at FROM deposits ORDER BY id DESC LIMIT 10")->fetchAll() ?: [],
            'latest_withdrawals' => $pdo->query("SELECT id, amount, status, requested_at FROM withdrawals ORDER BY id DESC LIMIT 10")->fetchAll() ?: [],
            'settings' => $pdo->query('SELECT setting_key, setting_value FROM system_settings ORDER BY setting_key ASC LIMIT 20')->fetchAll() ?: [],
            'module_status' => $this->adminModuleStatus($pdo),
            'settings_status' => $this->settingsCoverageStatus($pdo),
        ];
    }

    public function userTradingSnapshot(int $userId): array
    {
        $pdo = Database::connection();

        $pairs = $pdo->query('SELECT tp.symbol, tp.market_type, COALESCE(pt.last_price, 0) AS last_price, COALESCE(pt.change_24h_percent, 0) AS change_24h_percent, COALESCE(pt.volume_24h, 0) AS volume_24h FROM trading_pairs tp LEFT JOIN price_tickers pt ON pt.trading_pair_id = tp.id WHERE tp.is_visible = 1 ORDER BY tp.display_order ASC, tp.id ASC LIMIT 20')->fetchAll() ?: [];

        $ordersStmt = $pdo->prepare("SELECT o.id, tp.symbol, o.side, o.price, o.quantity, o.status, o.created_at FROM orders o INNER JOIN trading_pairs tp ON tp.id = o.trading_pair_id WHERE o.user_id = :user_id ORDER BY o.id DESC LIMIT 10");
        $ordersStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $ordersStmt->execute();

        $positionsStmt = $pdo->prepare("SELECT p.id, tp.symbol, p.position_side, p.leverage, p.quantity, p.unrealized_pnl, p.status FROM positions p INNER JOIN trading_pairs tp ON tp.id = p.trading_pair_id WHERE p.user_id = :user_id ORDER BY p.id DESC LIMIT 10");
        $positionsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $positionsStmt->execute();

        $walletStmt = $pdo->prepare('SELECT c.code, w.wallet_type, w.available_balance, w.locked_balance FROM wallets w INNER JOIN currencies c ON c.id = w.currency_id WHERE w.user_id = :user_id ORDER BY w.id DESC LIMIT 10');
        $walletStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $walletStmt->execute();

        $notificationsStmt = $pdo->prepare('SELECT type, title, channel, is_read, created_at FROM notifications WHERE user_id = :user_id ORDER BY id DESC LIMIT 10');
        $notificationsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $notificationsStmt->execute();

        $ticketsStmt = $pdo->prepare('SELECT ticket_number, subject, priority, status, created_at FROM support_tickets WHERE user_id = :user_id ORDER BY id DESC LIMIT 10');
        $ticketsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $ticketsStmt->execute();

        $apiKeysStmt = $pdo->prepare('SELECT label, permissions, is_active, created_at, last_used_at FROM api_keys WHERE user_id = :user_id ORDER BY id DESC LIMIT 10');
        $apiKeysStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $apiKeysStmt->execute();

        return [
            'pairs' => $pairs,
            'openOrders' => $ordersStmt->fetchAll() ?: [],
            'positions' => $positionsStmt->fetchAll() ?: [],
            'wallets' => $walletStmt->fetchAll() ?: [],
            'notifications' => $notificationsStmt->fetchAll() ?: [],
            'tickets' => $ticketsStmt->fetchAll() ?: [],
            'apiKeys' => $apiKeysStmt->fetchAll() ?: [],
        ];
    }

    private function adminModuleStatus(PDO $pdo): array
    {
        $counts = [
            'users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'roles' => (int)$pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn(),
            'permissions' => (int)$pdo->query('SELECT COUNT(*) FROM permissions')->fetchColumn(),
            'orders' => (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
            'markets' => (int)$pdo->query('SELECT COUNT(*) FROM trading_pairs')->fetchColumn(),
            'assets' => (int)$pdo->query('SELECT COUNT(*) FROM currencies')->fetchColumn(),
            'pairs' => (int)$pdo->query('SELECT COUNT(*) FROM trading_pairs')->fetchColumn(),
            'deposits' => (int)$pdo->query('SELECT COUNT(*) FROM deposits')->fetchColumn(),
            'withdrawals' => (int)$pdo->query('SELECT COUNT(*) FROM withdrawals')->fetchColumn(),
            'wallets' => (int)$pdo->query('SELECT COUNT(*) FROM wallets')->fetchColumn(),
            'kyc' => (int)$pdo->query('SELECT COUNT(*) FROM kyc_documents')->fetchColumn(),
            'tickets' => (int)$pdo->query('SELECT COUNT(*) FROM support_tickets')->fetchColumn(),
            'pages' => (int)$pdo->query('SELECT COUNT(*) FROM legal_documents')->fetchColumn(),
            'news' => (int)$pdo->query('SELECT COUNT(*) FROM announcements')->fetchColumn(),
            'languages' => (int)$pdo->query('SELECT COUNT(*) FROM system_settings WHERE category = \'localization\'')->fetchColumn(),
            'logs' => (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn(),
            'email_templates' => (int)$pdo->query('SELECT COUNT(*) FROM email_templates')->fetchColumn(),
            'sms_templates' => (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE channel = 'sms'")->fetchColumn(),
            'notification_templates' => (int)$pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn(),
            'cron_jobs' => (int)$pdo->query('SELECT COUNT(*) FROM pair_import_jobs')->fetchColumn(),
            'api_settings' => (int)$pdo->query('SELECT COUNT(*) FROM price_data_providers')->fetchColumn(),
        ];

        return [
            ['module' => 'User Management', 'total' => $counts['users']],
            ['module' => 'Role Management', 'total' => $counts['roles']],
            ['module' => 'Permission Management', 'total' => $counts['permissions']],
            ['module' => 'Trading Management', 'total' => $counts['orders']],
            ['module' => 'Markets', 'total' => $counts['markets']],
            ['module' => 'Assets', 'total' => $counts['assets']],
            ['module' => 'Trading Pairs', 'total' => $counts['pairs']],
            ['module' => 'Orders', 'total' => $counts['orders']],
            ['module' => 'Deposits', 'total' => $counts['deposits']],
            ['module' => 'Withdrawals', 'total' => $counts['withdrawals']],
            ['module' => 'Wallets', 'total' => $counts['wallets']],
            ['module' => 'KYC', 'total' => $counts['kyc']],
            ['module' => 'Support Tickets', 'total' => $counts['tickets']],
            ['module' => 'CMS Pages', 'total' => $counts['pages']],
            ['module' => 'FAQ', 'total' => 0],
            ['module' => 'News', 'total' => $counts['news']],
            ['module' => 'Announcements', 'total' => $counts['news']],
            ['module' => 'Languages', 'total' => $counts['languages']],
            ['module' => 'Settings', 'total' => (int)$pdo->query('SELECT COUNT(*) FROM system_settings')->fetchColumn()],
            ['module' => 'Logs', 'total' => $counts['logs']],
            ['module' => 'Email Templates', 'total' => $counts['email_templates']],
            ['module' => 'SMS Templates', 'total' => $counts['sms_templates']],
            ['module' => 'Notification Templates', 'total' => $counts['notification_templates']],
            ['module' => 'Maintenance Mode', 'total' => (int)$pdo->query("SELECT COUNT(*) FROM system_settings WHERE setting_key = 'maintenance_mode'")->fetchColumn()],
            ['module' => 'Cron Jobs', 'total' => $counts['cron_jobs']],
            ['module' => 'API Settings', 'total' => $counts['api_settings']],
        ];
    }

    private function settingsCoverageStatus(PDO $pdo): array
    {
        $rows = $pdo->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll() ?: [];
        $indexed = [];
        foreach ($rows as $row) {
            $key = strtolower((string)($row['setting_key'] ?? ''));
            if ($key !== '') {
                $indexed[$key] = (string)($row['setting_value'] ?? '');
            }
        }

        $mapping = [
            'General' => ['platform_name', 'registration_enabled'],
            'Company' => ['company_name'],
            'Branding' => ['brand_primary_color'],
            'Logo' => ['logo_url'],
            'Dark Logo' => ['logo_dark_url'],
            'Favicon' => ['favicon_url'],
            'SMTP' => ['smtp_host'],
            'SMS' => ['sms_provider'],
            'API' => ['api_enabled'],
            'Trading APIs' => ['trading_api_provider'],
            'Cron' => ['cron_enabled'],
            'Maintenance' => ['maintenance_mode'],
            'Localization' => ['default_locale'],
            'Timezone' => ['timezone'],
            'Currency' => ['base_currency'],
            'Language' => ['default_language'],
        ];

        $status = [];
        foreach ($mapping as $label => $candidates) {
            $configured = false;
            $value = '';
            foreach ($candidates as $candidate) {
                if (array_key_exists($candidate, $indexed) && trim((string)$indexed[$candidate]) !== '') {
                    $configured = true;
                    $value = (string)$indexed[$candidate];
                    break;
                }
            }

            $status[] = [
                'setting' => $label,
                'state' => $configured ? 'Configured' : 'Pending',
                'value' => $configured ? $value : '-',
            ];
        }

        return $status;
    }
}
