<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SignalsRepository;
use Throwable;

/**
 * Signals Service
 *
 * Business logic for:
 *  - Trading signal feed & detail
 *  - Signal provider management (admin)
 *  - User subscriptions
 *  - Signal performance calculation
 *  - Price alert CRUD & trigger processing
 *  - Automation rule CRUD & execution
 *  - Admin dashboard aggregation
 */
final class SignalsService
{
    private SignalsRepository $repo;

    public function __construct()
    {
        $this->repo = new SignalsRepository();
    }

    // =========================================================================
    // USER – SIGNAL DASHBOARD
    // =========================================================================

    public function getUserDashboard(int $userId): array
    {
        try {
            $providers    = $this->repo->allProviders(activeOnly: true);
            $subscriptions = $this->repo->userSubscriptions($userId);
            $subscribedIds = array_column($subscriptions, 'provider_id');

            // Feed from subscribed providers (or all if none subscribed)
            $feedFilters = [];
            $feed        = $this->repo->getSignalFeed($feedFilters, 20, 0);
            $signalIds   = array_column($feed, 'id');
            $interactions = $this->repo->getUserInteractions($userId, $signalIds);

            $alerts     = $this->repo->userAlerts($userId, 'active');
            $rules      = $this->repo->userRules($userId);
            $bookmarks  = $this->repo->getBookmarkedSignals($userId, 5);

            return [
                'providers'       => $providers,
                'subscriptions'   => $subscriptions,
                'subscribed_ids'  => $subscribedIds,
                'feed'            => $feed,
                'interactions'    => $interactions,
                'alerts'          => $alerts,
                'rules'           => $rules,
                'bookmarks'       => $bookmarks,
                'active_alert_count' => count($alerts),
                'active_rule_count'  => count(array_filter($rules, fn($r) => $r['is_active'])),
            ];
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // =========================================================================
    // SIGNAL FEED (USER)
    // =========================================================================

    public function getSignalFeed(array $filters, int $page = 1, int $perPage = 20): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $items  = $this->repo->getSignalFeed($filters, $perPage, $offset);
        $total  = $this->repo->countSignalFeed($filters);

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
        ];
    }

    public function getSignalDetail(int $signalId, int $userId): array|false
    {
        $signal = $this->repo->signalById($signalId);
        if (!$signal) {
            return false;
        }

        $this->repo->incrementSignalViews($signalId);
        $this->repo->upsertInteraction($userId, $signalId, 'view');

        $interactions = $this->repo->getUserInteractions($userId, [$signalId]);
        $perf         = $this->repo->getPerformance((int)$signal['provider_id'], '30d');

        return [
            'signal'       => $signal,
            'interactions' => $interactions[$signalId] ?? [],
            'performance'  => $perf ?: [],
        ];
    }

    // =========================================================================
    // SIGNAL INTERACTIONS
    // =========================================================================

    public function toggleLike(int $userId, int $signalId): array
    {
        $existing = $this->repo->getUserInteractions($userId, [$signalId]);
        if (!empty($existing[$signalId]['like'])) {
            $this->repo->removeInteraction($userId, $signalId, 'like');
            $liked = false;
        } else {
            $this->repo->upsertInteraction($userId, $signalId, 'like');
            $liked = true;
        }
        $signal = $this->repo->signalById($signalId);
        return ['liked' => $liked, 'likes_count' => (int)($signal['likes_count'] ?? 0)];
    }

    public function toggleBookmark(int $userId, int $signalId): array
    {
        $existing = $this->repo->getUserInteractions($userId, [$signalId]);
        if (!empty($existing[$signalId]['bookmark'])) {
            $this->repo->removeInteraction($userId, $signalId, 'bookmark');
            $bookmarked = false;
        } else {
            $this->repo->upsertInteraction($userId, $signalId, 'bookmark');
            $bookmarked = true;
        }
        return ['bookmarked' => $bookmarked];
    }

    // =========================================================================
    // SUBSCRIPTIONS
    // =========================================================================

    public function subscribe(int $userId, int $providerId, array $opts = []): array
    {
        try {
            $provider = $this->repo->providerById($providerId);
            if (!$provider || !$provider['is_active']) {
                return ['ok' => false, 'message' => 'Provider not found or inactive'];
            }
            $this->repo->subscribe($userId, $providerId, $opts);
            return ['ok' => true, 'message' => 'Subscribed to ' . $provider['name']];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function unsubscribe(int $userId, int $providerId): array
    {
        try {
            $this->repo->unsubscribe($userId, $providerId);
            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateSubscriptionPrefs(int $userId, int $providerId, array $prefs): array
    {
        try {
            $this->repo->updateSubscriptionPrefs($userId, $providerId, $prefs);
            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    // =========================================================================
    // PRICE ALERTS (USER)
    // =========================================================================

    public function getAlertsPage(int $userId): array
    {
        try {
            $alerts      = $this->repo->userAlerts($userId);
            $history     = $this->repo->alertHistory($userId, 50);
            $pairs       = $this->repo->getActivePairs();
            $alertTypes  = $this->getAlertTypeLabels();

            $stats = [
                'total'     => count($alerts),
                'active'    => count(array_filter($alerts, fn($a) => $a['status'] === 'active')),
                'triggered' => count(array_filter($alerts, fn($a) => $a['status'] === 'triggered')),
                'paused'    => count(array_filter($alerts, fn($a) => $a['status'] === 'paused')),
            ];

            return compact('alerts', 'history', 'pairs', 'alertTypes', 'stats');
        } catch (Throwable $e) {
            return ['error' => $e->getMessage(), 'alerts' => [], 'history' => [], 'pairs' => []];
        }
    }

    public function createAlert(int $userId, array $input): array
    {
        try {
            if (empty($input['trading_pair_id']) || empty($input['alert_type']) || !isset($input['threshold_value'])) {
                return ['ok' => false, 'message' => 'Pair, alert type and threshold are required'];
            }
            $pairs  = $this->repo->getActivePairs();
            $pairMap = array_column($pairs, 'symbol', 'id');
            $pairId  = (int)$input['trading_pair_id'];
            if (!isset($pairMap[$pairId])) {
                return ['ok' => false, 'message' => 'Invalid trading pair'];
            }

            $id = $this->repo->createAlert([
                'user_id'          => $userId,
                'trading_pair_id'  => $pairId,
                'pair_symbol'      => $pairMap[$pairId],
                'alert_type'       => $input['alert_type'],
                'threshold_value'  => (float)$input['threshold_value'],
                'timeframe'        => $input['timeframe'] ?? '1h',
                'note'             => trim($input['note'] ?? ''),
                'notify_email'     => !empty($input['notify_email']) ? 1 : 0,
                'notify_platform'  => !empty($input['notify_platform']) ? 1 : 0,
                'is_recurring'     => !empty($input['is_recurring']) ? 1 : 0,
            ]);
            return ['ok' => true, 'id' => $id, 'message' => 'Alert created'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateAlert(int $userId, int $alertId, array $input): array
    {
        try {
            $alert = $this->repo->alertById($alertId, $userId);
            if (!$alert) {
                return ['ok' => false, 'message' => 'Alert not found'];
            }
            $this->repo->updateAlert($alertId, $userId, [
                'alert_type'      => $input['alert_type'] ?? $alert['alert_type'],
                'threshold_value' => (float)($input['threshold_value'] ?? $alert['threshold_value']),
                'timeframe'       => $input['timeframe'] ?? $alert['timeframe'],
                'note'            => $input['note'] ?? $alert['note'],
                'notify_email'    => !empty($input['notify_email']) ? 1 : 0,
                'notify_platform' => !empty($input['notify_platform']) ? 1 : 0,
                'is_recurring'    => !empty($input['is_recurring']) ? 1 : 0,
            ]);
            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteAlert(int $userId, int $alertId): array
    {
        try {
            $this->repo->deleteAlert($alertId, $userId);
            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function pauseAlert(int $userId, int $alertId): array
    {
        try {
            $this->repo->pauseAlert($alertId, $userId);
            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function resumeAlert(int $userId, int $alertId): array
    {
        try {
            $this->repo->resumeAlert($alertId, $userId);
            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Process an alert trigger event (called by cron/price feed processor).
     * Marks alert triggered, logs history, sends notification.
     */
    public function processAlertTrigger(int $alertId, float $triggeredValue): void
    {
        try {
            $alert = $this->repo->alertById($alertId, 0);
            if (!$alert || $alert['status'] !== 'active') {
                return;
            }

            $this->repo->triggerAlert($alertId, $triggeredValue);
            $this->repo->logAlertHistory($alertId, (int)$alert['user_id'], (int)$alert['trading_pair_id'], [
                'alert_type'       => $alert['alert_type'],
                'threshold_value'  => $alert['threshold_value'],
                'triggered_value'  => $triggeredValue,
                'notification_sent'=> 1,
            ]);

            if ($alert['notify_platform']) {
                $typeLabel = $this->getAlertTypeLabels()[$alert['alert_type']] ?? $alert['alert_type'];
                $this->repo->insertNotification(
                    (int)$alert['user_id'],
                    'price_alert',
                    "Price Alert: {$alert['pair_symbol']}",
                    "{$typeLabel} alert triggered at " . number_format($triggeredValue, 8),
                    '/user/signals/alerts'
                );
            }
        } catch (Throwable) {
            // Silently swallow cron errors
        }
    }

    // =========================================================================
    // AUTOMATION RULES (USER)
    // =========================================================================

    public function getAutomationPage(int $userId): array
    {
        try {
            $rules   = $this->repo->userRules($userId);
            $pairs   = $this->repo->getActivePairs();
            $triggerTypes = $this->getTriggerTypeLabels();
            $actionTypes  = $this->getActionTypeLabels();

            $stats = [
                'total'       => count($rules),
                'active'      => count(array_filter($rules, fn($r) => $r['is_active'])),
                'executions'  => array_sum(array_column($rules, 'execution_count')),
            ];

            // Recent logs across all user rules
            $recentLogs = [];
            foreach (array_slice($rules, 0, 5) as $rule) {
                $logs = $this->repo->ruleLogs((int)$rule['id'], $userId, 5);
                foreach ($logs as $log) {
                    $log['rule_name'] = $rule['name'];
                    $recentLogs[]     = $log;
                }
            }
            usort($recentLogs, fn($a, $b) => strtotime($b['executed_at']) - strtotime($a['executed_at']));
            $recentLogs = array_slice($recentLogs, 0, 15);

            return compact('rules', 'pairs', 'triggerTypes', 'actionTypes', 'stats', 'recentLogs');
        } catch (Throwable $e) {
            return ['error' => $e->getMessage(), 'rules' => [], 'pairs' => []];
        }
    }

    public function createRule(int $userId, array $input): array
    {
        try {
            if (empty($input['name']) || empty($input['trigger_type']) || empty($input['action_type'])) {
                return ['ok' => false, 'message' => 'Name, trigger type and action type are required'];
            }
            if (!isset($input['trigger_value'])) {
                return ['ok' => false, 'message' => 'Trigger value is required'];
            }

            $id = $this->repo->createRule(array_merge($input, ['user_id' => $userId]));
            return ['ok' => true, 'id' => $id, 'message' => 'Automation rule created'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateRule(int $userId, int $ruleId, array $input): array
    {
        try {
            $rule = $this->repo->ruleById($ruleId, $userId);
            if (!$rule) {
                return ['ok' => false, 'message' => 'Rule not found'];
            }
            $this->repo->updateRule($ruleId, $userId, $input);
            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteRule(int $userId, int $ruleId): array
    {
        try {
            $this->repo->deleteRule($ruleId, $userId);
            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function toggleRule(int $userId, int $ruleId): array
    {
        try {
            $this->repo->toggleRule($ruleId, $userId);
            $rule = $this->repo->ruleById($ruleId, $userId);
            return ['ok' => true, 'is_active' => (bool)($rule['is_active'] ?? false)];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function getRuleLogs(int $userId, int $ruleId): array
    {
        try {
            $rule = $this->repo->ruleById($ruleId, $userId);
            if (!$rule) {
                return ['ok' => false, 'message' => 'Rule not found'];
            }
            $logs = $this->repo->ruleLogs($ruleId, $userId, 100);
            return ['ok' => true, 'logs' => $logs, 'rule' => $rule];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'logs' => []];
        }
    }

    // =========================================================================
    // PERFORMANCE (USER)
    // =========================================================================

    public function getUserPerformancePage(int $userId): array
    {
        try {
            $subscriptions = $this->repo->userSubscriptions($userId);
            $performance   = [];

            foreach ($subscriptions as $sub) {
                $pid = (int)$sub['provider_id'];
                foreach (['7d', '30d', '90d', 'all'] as $period) {
                    $performance[$pid][$period] = $this->repo->getPerformance($pid, $period) ?: [];
                }
            }

            $bookmarks = $this->repo->getBookmarkedSignals($userId, 20);

            return [
                'subscriptions' => $subscriptions,
                'performance'   => $performance,
                'bookmarks'     => $bookmarks,
            ];
        } catch (Throwable $e) {
            return ['error' => $e->getMessage(), 'subscriptions' => [], 'performance' => []];
        }
    }

    // =========================================================================
    // ADMIN – SIGNAL MANAGEMENT
    // =========================================================================

    public function getAdminDashboard(): array
    {
        try {
            $kpis          = $this->repo->adminKpis();
            $daily         = $this->repo->dailySignalVolume(30);
            $topProviders  = $this->repo->topProvidersByPerformance(10);
            $recentActivity = $this->repo->recentSignalActivity(20);
            $alertStats    = $this->repo->adminAlertStats();

            return compact('kpis', 'daily', 'topProviders', 'recentActivity', 'alertStats');
        } catch (Throwable $e) {
            return ['error' => $e->getMessage(), 'kpis' => [], 'daily' => []];
        }
    }

    public function getAdminSignalsList(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        try {
            $offset  = max(0, ($page - 1) * $perPage);
            $signals = $this->repo->getAdminSignals($filters, $perPage, $offset);
            $pairs   = $this->repo->getActivePairs();
            return ['signals' => $signals, 'pairs' => $pairs, 'filters' => $filters];
        } catch (Throwable $e) {
            return ['error' => $e->getMessage(), 'signals' => [], 'pairs' => []];
        }
    }

    public function adminCreateSignal(array $input): array
    {
        try {
            if (empty($input['provider_id']) || empty($input['signal_type'])) {
                return ['ok' => false, 'message' => 'Provider and signal type are required'];
            }
            $id = $this->repo->createSignal($input);

            // Notify subscribers
            $provider = $this->repo->providerById((int)$input['provider_id']);
            if ($provider) {
                $this->notifySubscribers((int)$input['provider_id'], (int)$id, $input, $provider['name']);
            }

            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function adminUpdateSignalStatus(int $id, string $status, ?float $profitPct = null): array
    {
        try {
            $this->repo->updateSignalStatus($id, $status, $profitPct);
            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function adminDeleteSignal(int $id): array
    {
        try {
            $this->repo->deleteSignal($id);
            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    // =========================================================================
    // ADMIN – PROVIDERS
    // =========================================================================

    public function getAdminProvidersPage(): array
    {
        try {
            $providers = $this->repo->allProviders();
            $perf      = [];
            foreach ($providers as $p) {
                $pid = (int)$p['id'];
                $perf[$pid] = [
                    '30d' => $this->repo->getPerformance($pid, '30d') ?: [],
                    'all' => $this->repo->getPerformance($pid, 'all') ?: [],
                ];
            }
            return ['providers' => $providers, 'performance' => $perf];
        } catch (Throwable $e) {
            return ['error' => $e->getMessage(), 'providers' => [], 'performance' => []];
        }
    }

    public function adminCreateProvider(array $input, int $adminId): array
    {
        try {
            if (empty($input['name'])) {
                return ['ok' => false, 'message' => 'Provider name is required'];
            }
            $input['slug']       = $this->generateSlug($input['name']);
            $input['created_by'] = $adminId;
            $id = $this->repo->createProvider($input);
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function adminUpdateProvider(int $id, array $input): array
    {
        try {
            $provider = $this->repo->providerById($id);
            if (!$provider) {
                return ['ok' => false, 'message' => 'Provider not found'];
            }
            $input['slug'] = $this->generateSlug($input['name'] ?? $provider['name']);
            $this->repo->updateProvider($id, $input);
            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function adminToggleProvider(int $id): array
    {
        try {
            $this->repo->toggleProvider($id);
            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /** Recalculate and save performance for all active providers. */
    public function recalculateAllPerformance(): void
    {
        try {
            $providers = $this->repo->allProviders();
            foreach ($providers as $provider) {
                $pid = (int)$provider['id'];
                foreach (['7d', '30d', '90d', 'all'] as $period) {
                    $data = $this->repo->recalculatePerformance($pid, $period);
                    $this->repo->upsertPerformance($pid, $period, $data);
                }
                // Update cached win_rate on provider row
                $perf30 = $this->repo->getPerformance($pid, '30d');
                if ($perf30) {
                    $this->repo->updateProvider($pid, array_merge((array)$provider, [
                        'win_rate' => $perf30['win_rate'],
                    ]));
                }
            }
        } catch (Throwable) {
            // Silently swallow cron errors
        }
    }

    // =========================================================================
    // ADMIN – ALERTS OVERVIEW
    // =========================================================================

    public function getAdminAlertsPage(): array
    {
        try {
            $stats  = $this->repo->adminAlertStats();
            $active = $this->repo->adminActiveAlerts(100);
            return ['stats' => $stats, 'active_alerts' => $active];
        } catch (Throwable $e) {
            return ['error' => $e->getMessage(), 'stats' => [], 'active_alerts' => []];
        }
    }

    // =========================================================================
    // ADMIN – PERFORMANCE REPORTS
    // =========================================================================

    public function getAdminPerformancePage(): array
    {
        try {
            $providers = $this->repo->allProviders();
            $daily     = $this->repo->dailySignalVolume(90);
            $top       = $this->repo->topProvidersByPerformance(20);

            $allPerf = [];
            foreach ($providers as $p) {
                $pid = (int)$p['id'];
                $allPerf[$pid] = [
                    '30d' => $this->repo->getPerformance($pid, '30d') ?: [],
                    '90d' => $this->repo->getPerformance($pid, '90d') ?: [],
                    'all' => $this->repo->getPerformance($pid, 'all') ?: [],
                ];
            }

            return [
                'providers'   => $providers,
                'performance' => $allPerf,
                'daily'       => $daily,
                'top'         => $top,
            ];
        } catch (Throwable $e) {
            return ['error' => $e->getMessage(), 'providers' => [], 'daily' => []];
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function notifySubscribers(int $providerId, int $signalId, array $signal, string $providerName): void
    {
        try {
            $subs = $this->repo->userSubscriptions(0);  // Need all subscribers — handled via DB query
            // Direct DB fan-out not practical here; cron picks this up via active subscriptions
            // For immediate push, insert one notification for each subscriber
            $pdo  = \App\Libraries\Database::connection();
            $rows = $pdo->prepare(
                'SELECT user_id FROM signal_subscriptions
                 WHERE provider_id = :pid AND is_active = 1 AND notify_platform = 1'
            );
            $rows->bindValue(':pid', $providerId, \PDO::PARAM_INT);
            $rows->execute();
            $users = $rows->fetchAll(\PDO::FETCH_COLUMN) ?: [];

            foreach ($users as $uid) {
                $this->repo->insertNotification(
                    (int)$uid,
                    'trading_signal',
                    "New Signal: {$providerName} — " . strtoupper($signal['signal_type'] ?? ''),
                    ($signal['pair_symbol'] ?? 'N/A') . ' ' . strtoupper($signal['signal_type'] ?? '') . ' signal published',
                    "/user/signals/detail?id={$signalId}"
                );
            }
        } catch (Throwable) {
            // Non-fatal
        }
    }

    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
        return substr($slug, 0, 100);
    }

    public function getAlertTypeLabels(): array
    {
        return [
            'price_above'         => 'Price Above',
            'price_below'         => 'Price Below',
            'percent_change_up'   => '% Change Up',
            'percent_change_down' => '% Change Down',
            'volume_spike'        => 'Volume Spike',
            'rsi_overbought'      => 'RSI Overbought',
            'rsi_oversold'        => 'RSI Oversold',
            'ema_cross_up'        => 'EMA Cross Up',
            'ema_cross_down'      => 'EMA Cross Down',
            'new_high'            => 'New High',
            'new_low'             => 'New Low',
        ];
    }

    public function getTriggerTypeLabels(): array
    {
        return [
            'price_above'          => 'Price rises above value',
            'price_below'          => 'Price drops below value',
            'percent_change_up'    => 'Price rises by % in timeframe',
            'percent_change_down'  => 'Price drops by % in timeframe',
            'rsi_overbought'       => 'RSI exceeds threshold',
            'rsi_oversold'         => 'RSI drops below threshold',
            'ema_cross_up'         => 'Price crosses EMA upward',
            'ema_cross_down'       => 'Price crosses EMA downward',
            'signal_received'      => 'New trading signal received',
            'order_filled'         => 'Order gets filled',
            'position_pnl_pct'     => 'Position PnL reaches %',
        ];
    }

    public function getActionTypeLabels(): array
    {
        return [
            'place_market_order'   => 'Place market order',
            'place_limit_order'    => 'Place limit order',
            'close_position'       => 'Close open position',
            'cancel_open_orders'   => 'Cancel all open orders',
            'send_notification'    => 'Send notification',
            'webhook_call'         => 'Call webhook URL',
        ];
    }
}
