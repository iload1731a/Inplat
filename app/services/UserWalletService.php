<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\UserWalletRepository;
use App\Repositories\WalletBalanceRepository;
use App\Libraries\RequestContext;
use InvalidArgumentException;

final class UserWalletService
{
    private readonly UserWalletRepository   $repo;
    private readonly WalletBalanceRepository $balanceRepo;

    public function __construct(
        ?UserWalletRepository    $repo        = null,
        ?WalletBalanceRepository $balanceRepo = null,
    ) {
        $this->repo        = $repo        ?? new UserWalletRepository();
        $this->balanceRepo = $balanceRepo ?? new WalletBalanceRepository();
    }

    // -------------------------------------------------------------------------
    // Wallets
    // -------------------------------------------------------------------------

    public function wallets(int $userId): array
    {
        return $this->repo->wallets($userId);
    }

    public function walletsByType(int $userId, string $walletType): array
    {
        return $this->repo->walletsByType($userId, $walletType);
    }

    public function activeCurrencies(): array
    {
        return $this->repo->activeCurrencies();
    }

    // -------------------------------------------------------------------------
    // Deposits – schema-aligned (no fee/net_amount/method columns)
    // -------------------------------------------------------------------------

    public function submitDeposit(int $userId, array $input, ?array $file = null): int
    {
        $currencyId = (int)($input['currency_id'] ?? 0);
        if ($currencyId <= 0) {
            throw new InvalidArgumentException('Please select a currency');
        }
        $amount = trim((string)($input['amount'] ?? '0'));
        if (!is_numeric($amount) || bccomp($amount, '0', 18) <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero');
        }

        $txHash     = trim((string)($input['tx_hash']      ?? '')) ?: null;
        $fromAddr   = trim((string)($input['from_address'] ?? '')) ?: null;

        // Ensure wallet exists
        $wallet = $this->balanceRepo->findOrCreate($userId, $currencyId, 'spot');

        return $this->repo->createDeposit([
            'user_id'      => $userId,
            'wallet_id'    => (int)$wallet['id'],
            'currency_id'  => $currencyId,
            'amount'       => number_format((float)$amount, 18, '.', ''),
            'tx_hash'      => $txHash,
            'from_address' => $fromAddr,
        ]);
    }

    public function deposits(int $userId): array
    {
        return $this->repo->deposits($userId);
    }

    // -------------------------------------------------------------------------
    // Withdrawals – schema-aligned
    // -------------------------------------------------------------------------

    public function submitWithdrawal(int $userId, array $input): int
    {
        $currencyId = (int)($input['currency_id'] ?? 0);
        if ($currencyId <= 0) {
            throw new InvalidArgumentException('Please select a currency');
        }
        $amount = trim((string)($input['amount'] ?? '0'));
        if (!is_numeric($amount) || bccomp($amount, '0', 18) <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero');
        }
        $address = trim((string)($input['destination_address'] ?? ''));
        if ($address === '') {
            throw new InvalidArgumentException('Destination address is required');
        }

        $wallet = $this->repo->walletByUserAndCurrency($userId, $currencyId, 'spot');
        if ($wallet === null) {
            throw new InvalidArgumentException('Wallet not found for this currency');
        }
        if ((int)$wallet['is_frozen'] === 1) {
            throw new \RuntimeException('Your wallet is frozen. Please contact support.');
        }
        if (bccomp((string)$wallet['available_balance'], $amount, 18) < 0) {
            throw new InvalidArgumentException('Insufficient balance');
        }

        return $this->repo->createWithdrawal([
            'user_id'             => $userId,
            'wallet_id'           => (int)$wallet['id'],
            'currency_id'         => $currencyId,
            'amount'              => number_format((float)$amount, 18, '.', ''),
            'fee'                 => '0',
            'destination_address' => $address,
            'destination_tag'     => trim((string)($input['destination_tag'] ?? '')) ?: null,
        ]);
    }

    public function withdrawals(int $userId): array
    {
        return $this->repo->withdrawals($userId);
    }

    public function cancelWithdrawal(int $userId, int $withdrawalId): void
    {
        $withdrawal = $this->repo->findWithdrawalById($withdrawalId, $userId);
        if ($withdrawal === null) {
            throw new InvalidArgumentException('Withdrawal not found');
        }
        if ($withdrawal['status'] !== 'pending') {
            throw new InvalidArgumentException('Only pending withdrawals can be cancelled');
        }
        $this->repo->cancelWithdrawal($withdrawalId, $userId);
    }

    // -------------------------------------------------------------------------
    // Ledger
    // -------------------------------------------------------------------------

    public function ledger(int $userId, int $walletId, int $page = 1): array
    {
        // Verify wallet belongs to user
        $wallet = $this->balanceRepo->findById($walletId);
        if ($wallet === null || (int)$wallet['user_id'] !== $userId) {
            throw new InvalidArgumentException('Wallet not found');
        }
        $perPage = 50;
        $offset  = ($page - 1) * $perPage;
        $total   = $this->balanceRepo->countLedger($walletId);
        $items   = $this->balanceRepo->getLedger($walletId, $perPage, $offset);

        return [
            'wallet'    => $wallet,
            'items'     => $items,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    // -------------------------------------------------------------------------
    // Wallet deposit addresses
    // -------------------------------------------------------------------------

    public function depositAddresses(int $userId, int $walletId): array
    {
        $wallet = $this->balanceRepo->findById($walletId);
        if ($wallet === null || (int)$wallet['user_id'] !== $userId) {
            throw new InvalidArgumentException('Wallet not found');
        }
        return [
            'wallet'    => $wallet,
            'addresses' => $this->repo->walletAddresses($walletId),
        ];
    }

    // -------------------------------------------------------------------------
    // Withdrawal whitelist addresses
    // -------------------------------------------------------------------------

    public function whitelistAddresses(int $userId): array
    {
        return $this->repo->whitelistAddresses($userId);
    }

    public function addWhitelistAddress(int $userId, array $input): int
    {
        $currencyId = (int)($input['currency_id'] ?? 0);
        if ($currencyId <= 0) {
            throw new InvalidArgumentException('Please select a currency');
        }
        $address = trim((string)($input['address'] ?? ''));
        if ($address === '') {
            throw new InvalidArgumentException('Address is required');
        }
        if (strlen($address) < 10 || strlen($address) > 191) {
            throw new InvalidArgumentException('Invalid address format');
        }

        return $this->repo->addWhitelistAddress($userId, [
            'currency_id' => $currencyId,
            'address'     => $address,
            'tag_or_memo' => trim((string)($input['tag_or_memo'] ?? '')) ?: null,
            'label'       => trim((string)($input['label']       ?? '')) ?: null,
            'ip'          => RequestContext::ipAddress(),
        ]);
    }

    public function revokeWhitelistAddress(int $userId, int $addressId): void
    {
        if (!$this->repo->revokeWhitelistAddress($addressId, $userId)) {
            throw new InvalidArgumentException('Address not found');
        }
    }
}
