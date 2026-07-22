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
use App\Services\WalletTransferService;
use App\Services\WalletBalanceService;
use Throwable;

final class WalletController extends BaseController
{
    private function userId(): int
    {
        return (int)(Session::get('auth.user_id') ?? 0);
    }

    // -------------------------------------------------------------------------
    // Overview
    // -------------------------------------------------------------------------

    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId  = $this->userId();
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

    // -------------------------------------------------------------------------
    // Deposit
    // -------------------------------------------------------------------------

    public function deposit(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId  = $this->userId();
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

        $userId = $this->userId();

        try {
            $id = (new UserWalletService())->submitDeposit($userId, $request->all());
            Response::json(['ok' => true, 'message' => 'Deposit request submitted successfully', 'id' => $id]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // -------------------------------------------------------------------------
    // Withdrawal
    // -------------------------------------------------------------------------

    public function withdraw(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId  = $this->userId();
        $service = new UserWalletService();

        try {
            $currencies  = $service->activeCurrencies();
            $withdrawals = $service->withdrawals($userId);
            $wallets     = $service->wallets($userId);
            $whitelist   = $service->whitelistAddresses($userId);
        } catch (Throwable) {
            $currencies  = [];
            $withdrawals = [];
            $wallets     = [];
            $whitelist   = [];
        }

        $this->userView('user/wallet/withdraw', [
            'title'       => 'Withdraw',
            'userSection' => 'withdraw',
            'currencies'  => $currencies,
            'withdrawals' => $withdrawals,
            'wallets'     => $wallets,
            'whitelist'   => $whitelist,
        ]);
    }

    public function submitWithdrawal(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = $this->userId();

        try {
            $id = (new UserWalletService())->submitWithdrawal($userId, $request->all());
            Response::json(['ok' => true, 'message' => 'Withdrawal request submitted successfully', 'id' => $id]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function cancelWithdrawal(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId       = $this->userId();
        $withdrawalId = (int)$request->input('withdrawal_id', 0);

        try {
            (new UserWalletService())->cancelWithdrawal($userId, $withdrawalId);
            Response::json(['ok' => true, 'message' => 'Withdrawal cancelled']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // -------------------------------------------------------------------------
    // Transaction History
    // -------------------------------------------------------------------------

    public function history(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId  = $this->userId();
        $service = new UserWalletService();

        try {
            $deposits    = $service->deposits($userId);
            $withdrawals = $service->withdrawals($userId);
            $transfers   = (new WalletTransferService())->transferHistory($userId);
        } catch (Throwable) {
            $deposits    = [];
            $withdrawals = [];
            $transfers   = [];
        }

        $this->userView('user/wallet/history', [
            'title'       => 'Transaction History',
            'userSection' => 'wallet-history',
            'deposits'    => $deposits,
            'withdrawals' => $withdrawals,
            'transfers'   => $transfers,
        ]);
    }

    // -------------------------------------------------------------------------
    // Internal Transfer
    // -------------------------------------------------------------------------

    public function transfer(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId  = $this->userId();
        $service = new UserWalletService();

        try {
            $currencies = $service->activeCurrencies();
            $wallets    = $service->wallets($userId);
            $transfers  = (new WalletTransferService())->transferHistory($userId);
        } catch (Throwable) {
            $currencies = [];
            $wallets    = [];
            $transfers  = [];
        }

        $this->userView('user/wallet/transfer', [
            'title'       => 'Transfer',
            'userSection' => 'transfer',
            'currencies'  => $currencies,
            'wallets'     => $wallets,
            'transfers'   => $transfers,
        ]);
    }

    public function submitTransfer(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId      = $this->userId();
        $type        = (string)$request->input('transfer_type', 'user');

        try {
            $svc = new WalletTransferService();
            if ($type === 'internal') {
                $id = $svc->transferBetweenWalletTypes($userId, $request->all());
            } else {
                $id = $svc->transferToUser($userId, $request->all());
            }
            Response::json(['ok' => true, 'message' => 'Transfer completed successfully', 'id' => $id]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // -------------------------------------------------------------------------
    // Ledger
    // -------------------------------------------------------------------------

    public function ledger(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId   = $this->userId();
        $walletId = (int)$request->input('wallet_id', 0);
        $page     = max(1, (int)$request->input('page', 1));

        try {
            $data = (new UserWalletService())->ledger($userId, $walletId, $page);
        } catch (Throwable $e) {
            Session::put('flash.error', $e->getMessage());
            Response::redirect('/user/wallet');
        }

        $this->userView('user/wallet/ledger', [
            'title'       => 'Wallet Ledger',
            'userSection' => 'wallet',
            ...$data,
        ]);
    }

    // -------------------------------------------------------------------------
    // Whitelist Addresses
    // -------------------------------------------------------------------------

    public function addresses(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId  = $this->userId();
        $service = new UserWalletService();

        try {
            $addresses  = $service->whitelistAddresses($userId);
            $currencies = $service->activeCurrencies();
        } catch (Throwable) {
            $addresses  = [];
            $currencies = [];
        }

        $this->userView('user/wallet/addresses', [
            'title'       => 'Withdrawal Addresses',
            'userSection' => 'wallet-addresses',
            'addresses'   => $addresses,
            'currencies'  => $currencies,
        ]);
    }

    public function addAddress(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = $this->userId();

        try {
            $id = (new UserWalletService())->addWhitelistAddress($userId, $request->all());
            Response::json(['ok' => true, 'message' => 'Address added. It will be active in 24 hours.', 'id' => $id]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function revokeAddress(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId    = $this->userId();
        $addressId = (int)$request->input('address_id', 0);

        try {
            (new UserWalletService())->revokeWhitelistAddress($userId, $addressId);
            Response::json(['ok' => true, 'message' => 'Address revoked']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // -------------------------------------------------------------------------
    // Balance API (AJAX)
    // -------------------------------------------------------------------------

    public function balance(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId     = $this->userId();
        $currencyId = (int)$request->input('currency_id', 0);

        try {
            $service = new UserWalletService();
            $wallet  = $currencyId > 0
                ? $service->wallets($userId)
                : $service->wallets($userId);
            Response::json(['ok' => true, 'wallets' => $wallet]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}

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
