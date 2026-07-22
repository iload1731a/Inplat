<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\WalletTransferRepository;
use App\Repositories\WalletBalanceRepository;
use App\Repositories\UserWalletRepository;
use App\Libraries\Database;
use InvalidArgumentException;
use RuntimeException;
use PDO;

final class WalletTransferService
{
    public function __construct(
        private readonly WalletTransferRepository $transferRepo = new WalletTransferRepository(),
        private readonly WalletBalanceRepository  $balanceRepo  = new WalletBalanceRepository(),
        private readonly UserWalletRepository     $walletRepo   = new UserWalletRepository(),
    ) {}

    // -------------------------------------------------------------------------
    // User-to-user transfer
    // -------------------------------------------------------------------------

    public function transferToUser(int $fromUserId, array $input): int
    {
        $recipientEmail = trim((string)($input['recipient_email'] ?? ''));
        $currencyId     = (int)($input['currency_id']     ?? 0);
        $amount         = trim((string)($input['amount']  ?? '0'));
        $note           = trim((string)($input['note']    ?? ''));

        if ($recipientEmail === '') {
            throw new InvalidArgumentException('Recipient email is required');
        }
        if ($currencyId <= 0) {
            throw new InvalidArgumentException('Please select a currency');
        }
        if (!is_numeric($amount) || bccomp($amount, '0', 18) <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero');
        }

        // Resolve recipient
        $recipient = $this->findUserByEmail($recipientEmail);
        if ($recipient === null) {
            throw new InvalidArgumentException('Recipient not found');
        }
        if ((int)$recipient['id'] === $fromUserId) {
            throw new InvalidArgumentException('You cannot transfer to yourself');
        }

        // Get or verify source wallet
        $fromWallet = $this->walletRepo->walletByUserAndCurrency($fromUserId, $currencyId, 'spot');
        if ($fromWallet === null || (float)$fromWallet['available_balance'] < (float)$amount) {
            throw new InvalidArgumentException('Insufficient balance');
        }
        if ((int)$fromWallet['is_frozen'] === 1) {
            throw new RuntimeException('Your wallet is frozen');
        }

        // Ensure destination wallet exists
        $toWallet = $this->balanceRepo->findOrCreate((int)$recipient['id'], $currencyId, 'spot');

        return $this->transferRepo->transfer(
            (int)$fromWallet['id'],
            (int)$toWallet['id'],
            $fromUserId,
            (int)$recipient['id'],
            $currencyId,
            $amount,
            $note
        );
    }

    // -------------------------------------------------------------------------
    // Cross-wallet-type transfer (same user: spot ↔ futures ↔ margin)
    // -------------------------------------------------------------------------

    public function transferBetweenWalletTypes(int $userId, array $input): int
    {
        $fromType   = trim((string)($input['from_wallet_type'] ?? 'spot'));
        $toType     = trim((string)($input['to_wallet_type']   ?? 'futures'));
        $currencyId = (int)($input['currency_id']  ?? 0);
        $amount     = trim((string)($input['amount'] ?? '0'));

        $allowed = ['spot', 'margin', 'futures', 'funding'];
        if (!in_array($fromType, $allowed, true) || !in_array($toType, $allowed, true)) {
            throw new InvalidArgumentException('Invalid wallet type');
        }
        if ($fromType === $toType) {
            throw new InvalidArgumentException('Source and destination wallet types must be different');
        }
        if ($currencyId <= 0) {
            throw new InvalidArgumentException('Please select a currency');
        }
        if (!is_numeric($amount) || bccomp($amount, '0', 18) <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero');
        }

        $fromWallet = $this->walletRepo->walletByUserAndCurrency($userId, $currencyId, $fromType);
        if ($fromWallet === null || bccomp((string)$fromWallet['available_balance'], $amount, 18) < 0) {
            throw new InvalidArgumentException('Insufficient balance in source wallet');
        }
        if ((int)$fromWallet['is_frozen'] === 1) {
            throw new RuntimeException('Source wallet is frozen');
        }

        $toWallet = $this->balanceRepo->findOrCreate($userId, $currencyId, $toType);

        return $this->transferRepo->transfer(
            (int)$fromWallet['id'],
            (int)$toWallet['id'],
            $userId,
            $userId,
            $currencyId,
            $amount,
            "Internal transfer: {$fromType} → {$toType}"
        );
    }

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    public function transferHistory(int $userId): array
    {
        return $this->transferRepo->transfersByUser($userId);
    }

    public function allTransfers(array $filters = []): array
    {
        return $this->transferRepo->allTransfers($filters);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function findUserByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, username, email FROM users WHERE email = :email AND is_active = 1 LIMIT 1'
        );
        $stmt->bindValue(':email', $email);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
