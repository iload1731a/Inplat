<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\RequestContext;
use App\Repositories\AdminAssetsRepository;
use App\Repositories\AdminManagementRepository;
use InvalidArgumentException;

final class AdminAssetsService
{
    public function __construct(
        private readonly AdminAssetsRepository     $assetsRepo = new AdminAssetsRepository(),
        private readonly AdminManagementRepository $mgmtRepo   = new AdminManagementRepository(),
    ) {}

    // -----------------------------------------------------------------------
    // Assets Index
    // -----------------------------------------------------------------------

    public function assetsIndex(array $filters): array
    {
        return [
            'currencies'    => $this->assetsRepo->listCurrencies($filters),
            'priceTickers'  => $this->assetsRepo->listPriceTickers(30),
            'filters'       => $filters,
        ];
    }

    // -----------------------------------------------------------------------
    // Trading Pairs Index
    // -----------------------------------------------------------------------

    public function tradingPairsIndex(array $filters): array
    {
        return [
            'pairs'      => $this->assetsRepo->listTradingPairs($filters),
            'currencies' => $this->assetsRepo->listCurrencies([]),
            'filters'    => $filters,
        ];
    }

    // -----------------------------------------------------------------------
    // Currency CRUD
    // -----------------------------------------------------------------------

    public function createCurrency(int $adminId, array $payload): int
    {
        $code = strtoupper(trim((string)($payload['code'] ?? '')));
        $name = trim((string)($payload['name'] ?? ''));

        if ($code === '' || $name === '') {
            throw new InvalidArgumentException('Currency code and name are required.');
        }

        if (!preg_match('/^[A-Z0-9]{1,20}$/', $code)) {
            throw new InvalidArgumentException('Currency code must be 1-20 uppercase alphanumeric characters.');
        }

        $allowedTypes = ['crypto', 'fiat', 'token', 'stablecoin'];
        if (!in_array(trim((string)($payload['type'] ?? '')), $allowedTypes, true)) {
            throw new InvalidArgumentException('Invalid currency type.');
        }

        $currencyId = $this->assetsRepo->createCurrency($payload);
        $this->mgmtRepo->logAdminAction($adminId, 'create_currency', 'currencies', (string)$currencyId, null, ['code' => $code], RequestContext::ipAddress());
        return $currencyId;
    }

    public function updateCurrency(int $adminId, int $currencyId, array $payload): void
    {
        $currency = $this->assetsRepo->findCurrencyById($currencyId);
        if ($currency === null) {
            throw new InvalidArgumentException('Currency not found.');
        }

        $this->assetsRepo->updateCurrency($currencyId, $payload);
        $this->mgmtRepo->logAdminAction($adminId, 'update_currency', 'currencies', (string)$currencyId, null, ['code' => $payload['code'] ?? ''], RequestContext::ipAddress());
    }

    public function deleteCurrency(int $adminId, int $currencyId): void
    {
        $currency = $this->assetsRepo->findCurrencyById($currencyId);
        if ($currency === null) {
            throw new InvalidArgumentException('Currency not found.');
        }

        $this->assetsRepo->deleteCurrency($currencyId);
        $this->mgmtRepo->logAdminAction($adminId, 'delete_currency', 'currencies', (string)$currencyId, null, ['code' => $currency['code']], RequestContext::ipAddress());
    }

    public function toggleCurrency(int $adminId, int $currencyId, bool $active): void
    {
        $currency = $this->assetsRepo->findCurrencyById($currencyId);
        if ($currency === null) {
            throw new InvalidArgumentException('Currency not found.');
        }

        $this->assetsRepo->toggleCurrencyActive($currencyId, $active);
        $this->mgmtRepo->logAdminAction($adminId, $active ? 'enable_currency' : 'disable_currency', 'currencies', (string)$currencyId, null, ['code' => $currency['code']], RequestContext::ipAddress());
    }

    // -----------------------------------------------------------------------
    // Trading Pair CRUD
    // -----------------------------------------------------------------------

    public function createTradingPair(int $adminId, array $payload): int
    {
        $symbol = strtoupper(trim((string)($payload['symbol'] ?? '')));
        if ($symbol === '') {
            throw new InvalidArgumentException('Trading pair symbol is required.');
        }

        $baseId  = (int)($payload['base_currency_id'] ?? 0);
        $quoteId = (int)($payload['quote_currency_id'] ?? 0);

        if ($baseId <= 0 || $quoteId <= 0) {
            throw new InvalidArgumentException('Base and quote currencies are required.');
        }

        if ($baseId === $quoteId) {
            throw new InvalidArgumentException('Base and quote currencies must differ.');
        }

        $pairId = $this->assetsRepo->createTradingPair($payload);
        $this->mgmtRepo->logAdminAction($adminId, 'create_trading_pair', 'trading_pairs', (string)$pairId, null, ['symbol' => $symbol], RequestContext::ipAddress());
        return $pairId;
    }

    public function updateTradingPair(int $adminId, int $pairId, array $payload): void
    {
        $pair = $this->assetsRepo->findTradingPairById($pairId);
        if ($pair === null) {
            throw new InvalidArgumentException('Trading pair not found.');
        }

        $this->assetsRepo->updateTradingPair($pairId, $payload);
        $this->mgmtRepo->logAdminAction($adminId, 'update_trading_pair', 'trading_pairs', (string)$pairId, null, ['symbol' => $payload['symbol'] ?? ''], RequestContext::ipAddress());
    }

    public function deleteTradingPair(int $adminId, int $pairId): void
    {
        $pair = $this->assetsRepo->findTradingPairById($pairId);
        if ($pair === null) {
            throw new InvalidArgumentException('Trading pair not found.');
        }

        $this->assetsRepo->deleteTradingPair($pairId);
        $this->mgmtRepo->logAdminAction($adminId, 'delete_trading_pair', 'trading_pairs', (string)$pairId, null, ['symbol' => $pair['symbol']], RequestContext::ipAddress());
    }
}
