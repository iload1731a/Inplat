<?php
declare(strict_types=1);
namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Libraries\Csrf;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\WithdrawalService;
use Throwable;

/**
 * User Withdrawal Controller
 * Handles crypto withdrawals, fiat/bank withdrawals, cancel, history, reports,
 * and the AJAX daily-limit info endpoint.
 *
 * Routes: /user/withdrawal/*
 */
final class WithdrawalController extends BaseController
{
    private function userId(): int
    {
        return (int)(Session::get('auth.user_id') ?? 0);
    }

    private function svc(): WithdrawalService
    {
        return new WithdrawalService();
    }

    // -------------------------------------------------------------------------
    // Withdrawal centre (crypto + fiat + history)
    // -------------------------------------------------------------------------

    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = $this->userId();

        $filters = [
            'status'    => trim((string)$request->input('status',    '')),
            'type'      => trim((string)$request->input('type',      '')),
            'date_from' => trim((string)$request->input('date_from', '')),
            'date_to'   => trim((string)$request->input('date_to',   '')),
        ];

        try {
            $data = $this->svc()->userWithdrawalCenter($userId, $filters);
        } catch (Throwable $e) {
            $data = [
                'currencies'     => [],
                'withdrawals'    => [],
                'stats'          => [],
                'monthly_totals' => [],
                'filters'        => $filters,
            ];
        }

        $this->userView('user/withdrawal/index', [
            'title'       => 'Withdraw Funds',
            'userSection' => 'withdraw',
            ...$data,
        ]);
    }

    // -------------------------------------------------------------------------
    // Fiat withdrawal page
    // -------------------------------------------------------------------------

    public function fiat(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = $this->userId();

        try {
            $data = $this->svc()->userWithdrawalCenter($userId);
            // Filter to fiat currencies only
            $data['currencies'] = array_filter(
                $data['currencies'],
                static fn($c) => ($c['type'] ?? '') === 'fiat'
            );
            $data['currencies'] = array_values($data['currencies']);
        } catch (Throwable $e) {
            $data = ['currencies' => [], 'withdrawals' => [], 'stats' => [], 'monthly_totals' => [], 'filters' => []];
        }

        $this->userView('user/withdrawal/fiat', [
            'title'       => 'Bank / Fiat Withdrawal',
            'userSection' => 'withdraw',
            ...$data,
        ]);
    }

    // -------------------------------------------------------------------------
    // Submit crypto withdrawal (AJAX)
    // -------------------------------------------------------------------------

    public function submitCrypto(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = $this->userId();

        try {
            $id = $this->svc()->submitCrypto($userId, $request->all());
            Response::json([
                'ok'      => true,
                'message' => 'Withdrawal request submitted successfully',
                'id'      => $id,
            ]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // -------------------------------------------------------------------------
    // Submit fiat withdrawal (AJAX)
    // -------------------------------------------------------------------------

    public function submitFiat(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = $this->userId();

        try {
            $id = $this->svc()->submitFiat($userId, $request->all());
            Response::json([
                'ok'      => true,
                'message' => 'Fiat withdrawal request submitted. Our team will process it within 1–3 business days.',
                'id'      => $id,
            ]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // -------------------------------------------------------------------------
    // Cancel withdrawal (AJAX)
    // -------------------------------------------------------------------------

    public function cancel(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId       = $this->userId();
        $withdrawalId = (int)$request->input('withdrawal_id', 0);

        try {
            $this->svc()->cancelWithdrawal($userId, $withdrawalId);
            Response::json(['ok' => true, 'message' => 'Withdrawal cancelled and amount refunded to your wallet']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // -------------------------------------------------------------------------
    // Reports page
    // -------------------------------------------------------------------------

    public function report(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = $this->userId();

        try {
            $center        = (new WithdrawalService())->userWithdrawalCenter($userId);
            $stats         = $center['stats'];
            $monthlyTotals = $center['monthly_totals'];
        } catch (Throwable) {
            $stats         = [];
            $monthlyTotals = [];
        }

        $this->userView('user/withdrawal/report', [
            'title'          => 'Withdrawal Report',
            'userSection'    => 'withdraw',
            'stats'          => $stats,
            'monthly_totals' => $monthlyTotals,
        ]);
    }

    // -------------------------------------------------------------------------
    // AJAX: daily limit info for a currency
    // -------------------------------------------------------------------------

    public function limitInfo(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId     = $this->userId();
        $currencyId = (int)$request->input('currency_id', 0);

        try {
            $info = $this->svc()->userDailyLimitInfo($userId, $currencyId);
            Response::json(['ok' => true, 'data' => $info]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // -------------------------------------------------------------------------
    // AJAX: calculate fee preview
    // -------------------------------------------------------------------------

    public function feePreview(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $currencyId = (int)$request->input('currency_id', 0);
        $amount     = trim((string)$request->input('amount', '0'));

        if ($currencyId <= 0 || !is_numeric($amount) || (float)$amount <= 0) {
            Response::json(['ok' => false, 'message' => 'Invalid input'], 422);
        }

        try {
            $repo = new \App\Repositories\WithdrawalRepository();
            $currency = $repo->currencyById($currencyId);
            if ($currency === null) {
                Response::json(['ok' => false, 'message' => 'Currency not found'], 422);
            }
            $fees = $this->svc()->calculateFee($currency, $amount);
            Response::json(['ok' => true, 'data' => $fees]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
