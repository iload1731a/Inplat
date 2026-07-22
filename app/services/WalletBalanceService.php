<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\WalletBalanceRepository;
use App\Repositories\UserWalletRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * Balance Engine Service – handles atomic credit/debit operations
 * with full double-entry ledger support.
 */
final class WalletBalanceService
{
    public function __construct(
        private readonly WalletBalanceRepository $balanceRepo = new WalletBalanceRepository(),
        private readonly UserWalletRepository    $walletRepo  = new UserWalletRepository(),
    ) {}

    /**
     * Credit a user's wallet balance after a confirmed deposit.
     */
    public function creditDeposit(int $walletId, string $amount, int $depositId): void
    {
        $this->validateAmount($amount);
        $this->balanceRepo->credit(
            $walletId,
            $amount,
            'deposit',
            $depositId,
            "Deposit #{$depositId} credited"
        );
    }

    /**
     * Debit a user's wallet balance when a withdrawal is approved.
     */
    public function debitWithdrawal(int $walletId, string $amount, int $withdrawalId): void
    {
        $this->validateAmount($amount);
        $this->balanceRepo->debit(
            $walletId,
            $amount,
            'withdrawal',
            $withdrawalId,
            "Withdrawal #{$withdrawalId} debited"
        );
    }

    /**
     * Manual admin adjustment (credit).
     */
    public function adminCredit(int $walletId, string $amount, string $notes, int $adminId): void
    {
        if (trim($notes) === '') {
            throw new InvalidArgumentException('Adjustment notes are required');
        }
        $this->validateAmount($amount);
        $this->balanceRepo->credit(
            $walletId,
            $amount,
            'adjustment',
            0,
            $notes,
            $adminId
        );
    }

    /**
     * Manual admin adjustment (debit).
     */
    public function adminDebit(int $walletId, string $amount, string $notes, int $adminId): void
    {
        if (trim($notes) === '') {
            throw new InvalidArgumentException('Adjustment notes are required');
        }
        $this->validateAmount($amount);
        $this->balanceRepo->debit(
            $walletId,
            $amount,
            'adjustment',
            0,
            $notes,
            $adminId
        );
    }

    /**
     * Lock funds in a wallet (for open orders).
     */
    public function lockFunds(int $walletId, string $amount): void
    {
        $this->validateAmount($amount);
        $this->balanceRepo->lock($walletId, $amount);
    }

    /**
     * Release locked funds back to available.
     */
    public function unlockFunds(int $walletId, string $amount): void
    {
        $this->validateAmount($amount);
        $this->balanceRepo->unlock($walletId, $amount);
    }

    /**
     * Get or create a wallet for a user/currency/type combination.
     */
    public function ensureWallet(int $userId, int $currencyId, string $walletType = 'spot'): array
    {
        return $this->balanceRepo->findOrCreate($userId, $currencyId, $walletType);
    }

    /**
     * Get wallet by ID.
     */
    public function getWallet(int $walletId): ?array
    {
        return $this->balanceRepo->findById($walletId);
    }

    /**
     * Get paginated ledger for a wallet.
     */
    public function getLedger(int $walletId, int $page = 1, int $perPage = 50): array
    {
        $offset = ($page - 1) * $perPage;
        $total  = $this->balanceRepo->countLedger($walletId);
        $items  = $this->balanceRepo->getLedger($walletId, $perPage, $offset);

        return [
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $perPage,
            'last_page'  => max(1, (int)ceil($total / $perPage)),
        ];
    }

    private function validateAmount(string $amount): void
    {
        if (!is_numeric($amount) || bccomp($amount, '0', 18) <= 0) {
            throw new InvalidArgumentException('Amount must be a positive number');
        }
    }
}
