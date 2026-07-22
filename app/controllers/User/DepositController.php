<?php
declare(strict_types=1);
namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Libraries\Csrf;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\DepositService;
use Throwable;

/**
 * User Deposit Controller
 * Routes: /user/deposit/*
 */
final class DepositController extends BaseController
{
    private function userId(): int
    {
        return (int)(Session::get('auth.user_id') ?? 0);
    }

    private function svc(): DepositService
    {
        return new DepositService();
    }

    // -------------------------------------------------------------------------
    // Deposit centre (crypto)
    // -------------------------------------------------------------------------

    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = $this->userId();

        $filters = [
            'status'      => trim((string)$request->input('status',      '')),
            'currency_id' => (int)$request->input('currency_id', 0) ?: null,
            'date_from'   => trim((string)$request->input('date_from',   '')),
            'date_to'     => trim((string)$request->input('date_to',     '')),
        ];
        $filters = array_filter($filters, static fn($v) => $v !== null && $v !== '');

        try {
            $data = $this->svc()->userDepositIndex($userId, $filters);
        } catch (Throwable) {
            $data = ['currencies' => [], 'deposits' => [], 'stats' => [], 'filters' => $filters];
        }

        $this->userView('user/deposit/index', [
            'title'       => 'Deposit',
            'userSection' => 'deposit',
            ...$data,
        ]);
    }

    // -------------------------------------------------------------------------
    // Fiat deposit form
    // -------------------------------------------------------------------------

    public function fiat(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = $this->userId();

        try {
            $data = $this->svc()->userDepositIndex($userId, ['type' => 'fiat']);
            // Keep only fiat currencies
            $data['currencies'] = array_filter(
                $data['currencies'],
                static fn(array $c) => ($c['type'] ?? '') === 'fiat'
            );
            $data['currencies'] = array_values($data['currencies']);
        } catch (Throwable) {
            $data = ['currencies' => [], 'deposits' => [], 'stats' => [], 'filters' => []];
        }

        $this->userView('user/deposit/fiat', [
            'title'       => 'Fiat Deposit',
            'userSection' => 'deposit',
            ...$data,
        ]);
    }

    // -------------------------------------------------------------------------
    // Submit crypto deposit
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
            Response::json(['ok' => true, 'message' => 'Deposit request submitted successfully.', 'id' => $id]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // -------------------------------------------------------------------------
    // Submit fiat deposit
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
            Response::json(['ok' => true, 'message' => 'Fiat deposit submitted. Our team will verify your payment shortly.', 'id' => $id]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // -------------------------------------------------------------------------
    // Cancel deposit
    // -------------------------------------------------------------------------

    public function cancel(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();

        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId    = $this->userId();
        $depositId = (int)$request->input('deposit_id', 0);

        try {
            $this->svc()->cancelDeposit($userId, $depositId);
            Response::json(['ok' => true, 'message' => 'Deposit cancelled.']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // -------------------------------------------------------------------------
    // Deposit report
    // -------------------------------------------------------------------------

    public function report(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = $this->userId();

        try {
            $data = $this->svc()->userDepositReport($userId);
        } catch (Throwable) {
            $data = ['deposits' => [], 'stats' => [], 'monthly' => []];
        }

        $this->userView('user/deposit/report', [
            'title'       => 'Deposit Report',
            'userSection' => 'deposit',
            ...$data,
        ]);
    }

    // -------------------------------------------------------------------------
    // AJAX: address info
    // -------------------------------------------------------------------------

    public function addressInfo(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId     = $this->userId();
        $currencyId = (int)$request->input('currency_id', 0);

        try {
            $data = $this->svc()->addressInfo($userId, $currencyId);
            Response::json(['ok' => true, ...$data]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
