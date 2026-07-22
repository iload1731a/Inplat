<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\UserWalletRepository;

final class UserWalletService
{
    private const CRYPTO_PRECISION = 8;

    private readonly UserWalletRepository $repo;

    public function __construct(?UserWalletRepository $repo = null)
    {
        $this->repo = $repo ?? new UserWalletRepository();
    }

    public function wallets(int $userId): array
    {
        return $this->repo->wallets($userId);
    }

    public function activeCurrencies(): array
    {
        return $this->repo->activeCurrencies();
    }

    public function submitDeposit(int $userId, array $input, ?array $file = null): int
    {
        $currencyId = (int)($input['currency_id'] ?? 0);
        if ($currencyId <= 0) {
            throw new \InvalidArgumentException('Please select a currency');
        }
        $amount = (float)($input['amount'] ?? 0);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero');
        }

        $proofUrl = null;
        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
            if (!in_array($file['type'], $allowedMimes, true)) {
                throw new \InvalidArgumentException('Invalid proof file type');
            }
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new \InvalidArgumentException('Proof file must be smaller than 5MB');
            }
            $uploadDir = app_path('public/uploads/deposits/');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext      = pathinfo((string)$file['name'], PATHINFO_EXTENSION);
            $filename = 'dep_' . $userId . '_' . time() . '.' . strtolower($ext);
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                throw new \RuntimeException('Failed to save payment proof');
            }
            $proofUrl = '/uploads/deposits/' . $filename;
        }

        $wallet = $this->repo->walletByUserAndCurrency($userId, $currencyId);

        return $this->repo->createDeposit([
            'user_id'           => $userId,
            'wallet_id'         => $wallet ? (int)$wallet['id'] : null,
            'currency_id'       => $currencyId,
            'amount'            => number_format($amount, self::CRYPTO_PRECISION, '.', ''),
            'net_amount'        => number_format($amount, self::CRYPTO_PRECISION, '.', ''),
            'method'            => trim((string)($input['method'] ?? 'manual')),
            'reference'         => trim((string)($input['reference'] ?? '')) ?: null,
            'payment_proof_url' => $proofUrl,
        ]);
    }

    public function submitWithdrawal(int $userId, array $input): int
    {
        $currencyId = (int)($input['currency_id'] ?? 0);
        if ($currencyId <= 0) {
            throw new \InvalidArgumentException('Please select a currency');
        }
        $amount = (float)($input['amount'] ?? 0);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero');
        }
        $address = trim((string)($input['destination_address'] ?? ''));
        if ($address === '') {
            throw new \InvalidArgumentException('Destination address is required');
        }

        $wallet = $this->repo->walletByUserAndCurrency($userId, $currencyId);
        if ($wallet === null || (float)$wallet['available_balance'] < $amount) {
            throw new \InvalidArgumentException('Insufficient balance');
        }

        return $this->repo->createWithdrawal([
            'user_id'             => $userId,
            'wallet_id'           => (int)$wallet['id'],
            'currency_id'         => $currencyId,
            'amount'              => number_format($amount, self::CRYPTO_PRECISION, '.', ''),
            'net_amount'          => number_format($amount, self::CRYPTO_PRECISION, '.', ''),
            'destination_address' => $address,
            'destination_memo'    => trim((string)($input['destination_memo'] ?? '')) ?: null,
            'method'              => trim((string)($input['method'] ?? 'crypto')),
        ]);
    }

    public function deposits(int $userId): array
    {
        return $this->repo->deposits($userId);
    }

    public function withdrawals(int $userId): array
    {
        return $this->repo->withdrawals($userId);
    }
}
