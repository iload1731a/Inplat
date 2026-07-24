<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Libraries\Csrf;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\SignalsService;
use Throwable;

/**
 * User Signals Controller
 *
 * Routes:
 *   GET  /user/signals                  — Signal dashboard (feed + quick stats)
 *   GET  /user/signals/feed             — Paginated signal feed (AJAX-friendly)
 *   GET  /user/signals/detail           — Signal detail page
 *   POST /user/signals/like             — Toggle like on a signal (AJAX)
 *   POST /user/signals/bookmark         — Toggle bookmark (AJAX)
 *   GET  /user/signals/bookmarks        — Saved/bookmarked signals
 *   GET  /user/signals/subscribe        — Available providers + subscription management
 *   POST /user/signals/subscribe        — Subscribe to a provider
 *   POST /user/signals/unsubscribe      — Unsubscribe from a provider
 *   POST /user/signals/subscription-prefs — Update notification prefs
 *   GET  /user/signals/alerts           — Price alerts dashboard
 *   POST /user/signals/alerts/create    — Create new price alert
 *   POST /user/signals/alerts/update    — Update existing alert
 *   POST /user/signals/alerts/delete    — Delete alert
 *   POST /user/signals/alerts/pause     — Pause alert
 *   POST /user/signals/alerts/resume    — Resume alert
 *   GET  /user/signals/automation       — Automation rules dashboard
 *   POST /user/signals/automation/create  — Create automation rule
 *   POST /user/signals/automation/update  — Update automation rule
 *   POST /user/signals/automation/delete  — Delete automation rule
 *   POST /user/signals/automation/toggle  — Toggle rule active/inactive
 *   GET  /user/signals/automation/logs  — Rule execution logs (AJAX)
 *   GET  /user/signals/performance      — Signal performance analytics
 */
final class SignalsController extends BaseController
{
    private SignalsService $service;

    public function __construct()
    {
        $this->service = new SignalsService();
    }

    private function userId(): int
    {
        return (int)(Session::get('auth.user_id') ?? 0);
    }

    private function requireCsrf(Request $request): void
    {
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuth();
        $userId = $this->userId();

        try {
            $data = $this->service->getUserDashboard($userId);
        } catch (Throwable $e) {
            $data = ['error' => $e->getMessage()];
        }

        $this->render('user/signals/index', array_merge($data, [
            'title'       => 'Trading Signals',
            'userSection' => 'signals',
            'breadcrumb'  => [['label' => 'Signals']],
        ]));
    }

    // =========================================================================
    // SIGNAL FEED
    // =========================================================================

    public function feed(Request $request): void
    {
        AuthMiddleware::ensureAuth();
        $userId = $this->userId();

        $filters = [
            'provider_id' => (int)($request->input('provider_id') ?? 0) ?: null,
            'pair_id'     => (int)($request->input('pair_id') ?? 0) ?: null,
            'signal_type' => $request->input('signal_type') ?? '',
            'market_type' => $request->input('market_type') ?? '',
            'status'      => $request->input('status') ?? '',
            'timeframe'   => $request->input('timeframe') ?? '',
        ];
        $page  = max(1, (int)($request->input('page') ?? 1));
        $result = $this->service->getSignalFeed(array_filter($filters), $page);

        if ($request->input('format') === 'json') {
            Response::json($result);
            return;
        }

        $this->render('user/signals/feed', array_merge($result, [
            'title'       => 'Signal Feed',
            'userSection' => 'signals',
            'breadcrumb'  => [
                ['label' => 'Signals', 'url' => '/user/signals'],
                ['label' => 'Feed'],
            ],
            'filters'     => $filters,
            'providers'   => $this->service->getAdminProvidersPage()['providers'] ?? [],
        ]));
    }

    // =========================================================================
    // SIGNAL DETAIL
    // =========================================================================

    public function detail(Request $request): void
    {
        AuthMiddleware::ensureAuth();
        $userId   = $this->userId();
        $signalId = (int)($request->input('id') ?? 0);

        $result = $this->service->getSignalDetail($signalId, $userId);
        if (!$result) {
            Response::redirect('/user/signals');
            return;
        }

        $this->render('user/signals/detail', array_merge($result, [
            'title'       => 'Signal Detail',
            'userSection' => 'signals',
            'breadcrumb'  => [
                ['label' => 'Signals', 'url' => '/user/signals'],
                ['label' => 'Detail'],
            ],
        ]));
    }

    // =========================================================================
    // INTERACTIONS (AJAX)
    // =========================================================================

    public function like(Request $request): void
    {
        AuthMiddleware::ensureAuth();
        $this->requireCsrf($request);
        $userId   = $this->userId();
        $signalId = (int)($request->input('signal_id') ?? 0);
        Response::json($this->service->toggleLike($userId, $signalId));
    }

    public function bookmark(Request $request): void
    {
        AuthMiddleware::ensureAuth();
        $this->requireCsrf($request);
        $userId   = $this->userId();
        $signalId = (int)($request->input('signal_id') ?? 0);
        Response::json($this->service->toggleBookmark($userId, $signalId));
    }

    public function bookmarks(Request $request): void
    {
        AuthMiddleware::ensureAuth();
        $userId    = $this->userId();
        $bookmarks = $this->service->getUserPerformancePage($userId)['bookmarks'] ?? [];

        $this->render('user/signals/bookmarks', [
            'title'       => 'Saved Signals',
            'userSection' => 'signals',
            'breadcrumb'  => [
                ['label' => 'Signals', 'url' => '/user/signals'],
                ['label' => 'Bookmarks'],
            ],
            'bookmarks'   => $bookmarks,
        ]);
    }

    // =========================================================================
    // SUBSCRIPTIONS
    // =========================================================================

    public function subscriptions(Request $request): void
    {
        AuthMiddleware::ensureAuth();
        $userId = $this->userId();

        try {
            $providerPage   = $this->service->getAdminProvidersPage();
            $mySubscriptions = $this->service->getUserDashboard($userId)['subscriptions'] ?? [];
            $subscribedIds   = array_column($mySubscriptions, 'provider_id');
        } catch (Throwable $e) {
            $providerPage   = ['providers' => [], 'performance' => []];
            $mySubscriptions = [];
            $subscribedIds   = [];
        }

        $this->render('user/signals/subscribe', [
            'title'           => 'Signal Providers',
            'userSection'     => 'signals',
            'breadcrumb'      => [
                ['label' => 'Signals', 'url' => '/user/signals'],
                ['label' => 'Providers'],
            ],
            'providers'       => $providerPage['providers'] ?? [],
            'performance'     => $providerPage['performance'] ?? [],
            'my_subscriptions'=> $mySubscriptions,
            'subscribed_ids'  => $subscribedIds,
        ]);
    }

    public function subscribe(Request $request): void
    {
        AuthMiddleware::ensureAuth();
        $this->requireCsrf($request);
        $userId     = $this->userId();
        $providerId = (int)($request->input('provider_id') ?? 0);
        $opts       = [
            'notify_email'    => !empty($request->input('notify_email')) ? 1 : 0,
            'notify_platform' => !empty($request->input('notify_platform')) ? 1 : 1,
        ];
        Response::json($this->service->subscribe($userId, $providerId, $opts));
    }

    public function unsubscribe(Request $request): void
    {
        AuthMiddleware::ensureAuth();
        $this->requireCsrf($request);
        $userId     = $this->userId();
        $providerId = (int)($request->input('provider_id') ?? 0);
        Response::json($this->service->unsubscribe($userId, $providerId));
    }

    public function subscriptionPrefs(Request $request): void
    {
        AuthMiddleware::ensureAuth();
        $this->requireCsrf($request);
        $userId     = $this->userId();
        $providerId = (int)($request->input('provider_id') ?? 0);
        $prefs      = [
            'notify_email'    => !empty($request->input('notify_email')) ? 1 : 0,
            'notify_platform' => !empty($request->input('notify_platform')) ? 1 : 0,
        ];
        Response::json($this->service->updateSubscriptionPrefs($userId, $providerId, $prefs));
    }

    // =========================================================================
    // PRICE ALERTS
    // =========================================================================

    public function alerts(Request $request): void
    {
        AuthMiddleware::ensureAuth();
        $userId = $this->userId();
        $data   = $this->service->getAlertsPage($userId);

        $this->render('user/signals/alerts', array_merge($data, [
            'title'       => 'Price Alerts',
            'userSection' => 'signals',
            'breadcrumb'  => [
                ['label' => 'Signals', 'url' => '/user/signals'],
                ['label' => 'Price Alerts'],
            ],
        ]));
    }

    public function createAlert(Request $request): void
    {
        AuthMiddleware::ensureAuth();
        $this->requireCsrf($request);
        $userId = $this->userId();
        $input  = [
            'trading_pair_id' => $request->input('trading_pair_id'),
            'alert_type'      => $request->input('alert_type'),
            'threshold_value' => $request->input('threshold_value'),
            'timeframe'       => $request->input('timeframe') ?? '1h',
            'note'            => $request->input('note') ?? '',
            'notify_email'    => $request->input('notify_email'),
            'notify_platform' => $request->input('notify_platform'),
            'is_recurring'    => $request->input('is_recurring'),
        ];
        Response::json($this->service->createAlert($userId, $input));
    }

    public function updateAlert(Request $request): void
    {
        AuthMiddleware::ensureAuth();
        $this->requireCsrf($request);
        $userId  = $this->userId();
        $alertId = (int)($request->input('alert_id') ?? 0);
        Response::json($this->service->updateAlert($userId, $alertId, $request->all()));
    }

    public function deleteAlert(Request $request): void
    {
        AuthMiddleware::ensureAuth();
        $this->requireCsrf($request);
        $userId  = $this->userId();
        $alertId = (int)($request->input('alert_id') ?? 0);
        Response::json($this->service->deleteAlert($userId, $alertId));
    }

    public function pauseAlert(Request $request): void
    {
        AuthMiddleware::ensureAuth();
        $this->requireCsrf($request);
        $userId  = $this->userId();
        $alertId = (int)($request->input('alert_id') ?? 0);
        Response::json($this->service->pauseAlert($userId, $alertId));
    }

    public function resumeAlert(Request $request): void
    {
        AuthMiddleware::ensureAuth();
        $this->requireCsrf($request);
        $userId  = $this->userId();
        $alertId = (int)($request->input('alert_id') ?? 0);
        Response::json($this->service->resumeAlert($userId, $alertId));
    }

    // =========================================================================
    // AUTOMATION RULES
    // =========================================================================

    public function automation(Request $request): void
    {
        AuthMiddleware::ensureAuth();
        $userId = $this->userId();
        $data   = $this->service->getAutomationPage($userId);

        $this->render('user/signals/automation', array_merge($data, [
            'title'       => 'Automation Rules',
            'userSection' => 'signals',
            'breadcrumb'  => [
                ['label' => 'Signals', 'url' => '/user/signals'],
                ['label' => 'Automation'],
            ],
        ]));
    }

    public function createRule(Request $request): void
    {
        AuthMiddleware::ensureAuth();
        $this->requireCsrf($request);
        $userId = $this->userId();

        $actionParams = [];
        if ($request->input('webhook_url')) {
            $actionParams['webhook_url'] = $request->input('webhook_url');
        }
        if ($request->input('notification_message')) {
            $actionParams['message'] = $request->input('notification_message');
        }

        $input = [
            'name'               => trim((string)($request->input('name') ?? '')),
            'description'        => trim((string)($request->input('description') ?? '')),
            'trigger_type'       => $request->input('trigger_type'),
            'trigger_pair_id'    => (int)($request->input('trigger_pair_id') ?? 0) ?: null,
            'trigger_value'      => $request->input('trigger_value'),
            'trigger_timeframe'  => $request->input('trigger_timeframe') ?? '1h',
            'action_type'        => $request->input('action_type'),
            'action_pair_id'     => (int)($request->input('action_pair_id') ?? 0) ?: null,
            'action_side'        => $request->input('action_side') ?? null,
            'action_quantity'    => $request->input('action_quantity') ?? null,
            'action_quantity_type'=> $request->input('action_quantity_type') ?? 'fixed',
            'action_price'       => $request->input('action_price') ?? null,
            'action_params'      => !empty($actionParams) ? $actionParams : null,
            'cooldown_minutes'   => (int)($request->input('cooldown_minutes') ?? 60),
            'max_executions'     => (int)($request->input('max_executions') ?? 0) ?: null,
        ];
        Response::json($this->service->createRule($userId, $input));
    }

    public function updateRule(Request $request): void
    {
        AuthMiddleware::ensureAuth();
        $this->requireCsrf($request);
        $userId = $this->userId();
        $ruleId = (int)($request->input('rule_id') ?? 0);
        Response::json($this->service->updateRule($userId, $ruleId, $request->all()));
    }

    public function deleteRule(Request $request): void
    {
        AuthMiddleware::ensureAuth();
        $this->requireCsrf($request);
        $userId = $this->userId();
        $ruleId = (int)($request->input('rule_id') ?? 0);
        Response::json($this->service->deleteRule($userId, $ruleId));
    }

    public function toggleRule(Request $request): void
    {
        AuthMiddleware::ensureAuth();
        $this->requireCsrf($request);
        $userId = $this->userId();
        $ruleId = (int)($request->input('rule_id') ?? 0);
        Response::json($this->service->toggleRule($userId, $ruleId));
    }

    public function ruleLogs(Request $request): void
    {
        AuthMiddleware::ensureAuth();
        $userId = $this->userId();
        $ruleId = (int)($request->input('rule_id') ?? 0);
        Response::json($this->service->getRuleLogs($userId, $ruleId));
    }

    // =========================================================================
    // PERFORMANCE PAGE
    // =========================================================================

    public function performance(Request $request): void
    {
        AuthMiddleware::ensureAuth();
        $userId = $this->userId();
        $data   = $this->service->getUserPerformancePage($userId);

        $this->render('user/signals/performance', array_merge($data, [
            'title'       => 'Signal Performance',
            'userSection' => 'signals',
            'breadcrumb'  => [
                ['label' => 'Signals', 'url' => '/user/signals'],
                ['label' => 'Performance'],
            ],
        ]));
    }
}
