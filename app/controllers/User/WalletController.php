<?php
declare(strict_types=1);
namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Libraries\Csrf;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\UserWalletService;
use Throwable;

final class WalletController extends BaseController
{
    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId  = (int)(Session::get('auth.user_id') ?? 0);
        $service = new UserWalletService();

        try {
            $wallets = $service->wallets($userId);
        } catch (Throwable) {
            $wallets = [];
        }

        $this->userView('user/wallet/index', [
            'title'       => 'Wallet Overview',
            'userSection' => 'wallet',
            'wallets'     => $wallets,
        ]);
    }

    public function deposit(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId  = (int)(Session::get('auth.user_id') ?? 0);
        $service = new UserWalletService();

        try {
            $currencies = $service->activeCurrencies();
            $deposits   = $service->deposits($userId);
        } catch (Throwable) {
            $currencies = [];
            $deposits   = [];
        }

        $this->userView('user/wallet/deposit', [
            'title'       => 'Deposit',
            'userSection' => 'deposit',
            'currencies'  => $currencies,
            'deposits'    => $deposits,
        ]);
    }

    public function submitDeposit(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);
        $file   = $_FILES['payment_proof'] ?? null;

        try {
            $id = (new UserWalletService())->submitDeposit($userId, $request->all(), $file ?: null);
            Response::json(['ok' => true, 'message' => 'Deposit request submitted successfully', 'id' => $id]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function withdraw(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId  = (int)(Session::get('auth.user_id') ?? 0);
        $service = new UserWalletService();

        try {
            $currencies  = $service->activeCurrencies();
            $withdrawals = $service->withdrawals($userId);
            $wallets     = $service->wallets($userId);
        } catch (Throwable) {
            $currencies  = [];
            $withdrawals = [];
            $wallets     = [];
        }

        $this->userView('user/wallet/withdraw', [
            'title'       => 'Withdraw',
            'userSection' => 'withdraw',
            'currencies'  => $currencies,
            'withdrawals' => $withdrawals,
            'wallets'     => $wallets,
        ]);
    }

    public function submitWithdrawal(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);

        try {
            $id = (new UserWalletService())->submitWithdrawal($userId, $request->all());
            Response::json(['ok' => true, 'message' => 'Withdrawal request submitted successfully', 'id' => $id]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function history(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId  = (int)(Session::get('auth.user_id') ?? 0);
        $service = new UserWalletService();

        try {
            $deposits    = $service->deposits($userId);
            $withdrawals = $service->withdrawals($userId);
        } catch (Throwable) {
            $deposits    = [];
            $withdrawals = [];
        }

        $this->userView('user/wallet/history', [
            'title'       => 'Transaction History',
            'userSection' => 'wallet-history',
            'deposits'    => $deposits,
            'withdrawals' => $withdrawals,
        ]);
    }
}
