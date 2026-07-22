<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\RequestContext;
use App\Repositories\AdminWalletsRepository;
use App\Repositories\AdminManagementRepository;
use InvalidArgumentException;

final class AdminWalletsService
{
    public function __construct(
        private readonly AdminWalletsRepository    $walletsRepo = new AdminWalletsRepository(),
        private readonly AdminManagementRepository $mgmtRepo    = new AdminManagementRepository(),
    ) {}

    public function walletsIndex(array $filters): array
    {
        return [
            'wallets'     => $this->walletsRepo->listWallets($filters),
            'walletStats' => $this->walletsRepo->getWalletStats(),
            'topWallets'  => $this->walletsRepo->getTopWallets(),
            'filters'     => $filters,
        ];
    }

    public function walletLedger(int $walletId): array
    {
        $wallet = $this->walletsRepo->findWalletById($walletId);
        if ($wallet === null) {
            throw new InvalidArgumentException('Wallet not found.');
        }

        return [
            'wallet'  => $wallet,
            'ledger'  => $this->walletsRepo->getLedger($walletId, 100),
        ];
    }

    public function freezeWallet(int $adminId, int $walletId, string $reason): void
    {
        $wallet = $this->walletsRepo->findWalletById($walletId);
        if ($wallet === null) {
            throw new InvalidArgumentException('Wallet not found.');
        }

        if ($reason === '') {
            throw new InvalidArgumentException('Freeze reason is required.');
        }

        $this->walletsRepo->freezeWallet($walletId, $reason);
        $this->mgmtRepo->logAdminAction($adminId, 'freeze_wallet', 'wallets', (string)$walletId, null, ['reason' => $reason, 'user' => $wallet['username']], RequestContext::ipAddress());
    }

    public function unfreezeWallet(int $adminId, int $walletId): void
    {
        $wallet = $this->walletsRepo->findWalletById($walletId);
        if ($wallet === null) {
            throw new InvalidArgumentException('Wallet not found.');
        }

        $this->walletsRepo->unfreezeWallet($walletId);
        $this->mgmtRepo->logAdminAction($adminId, 'unfreeze_wallet', 'wallets', (string)$walletId, null, ['user' => $wallet['username']], RequestContext::ipAddress());
    }
}
