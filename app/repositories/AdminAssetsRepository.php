<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class AdminAssetsRepository
{
    // -----------------------------------------------------------------------
    // Currencies / Assets
    // -----------------------------------------------------------------------

    public function listCurrencies(array $filters = []): array
    {
        $sql = "SELECT c.id, c.code, c.name, c.type, c.decimals, c.is_active,
                       c.min_withdrawal, c.max_withdrawal_daily,
                       c.withdrawal_fee_fixed, c.withdrawal_fee_percent,
                       c.network, c.contract_address, c.icon_url, c.created_at,
                       COUNT(DISTINCT w.id) AS wallet_count
                FROM currencies c
                LEFT JOIN wallets w ON w.currency_id = c.id
                WHERE 1=1";
        $params = [];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (c.code LIKE :search OR c.name LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $type = trim((string)($filters['type'] ?? ''));
        if ($type !== '') {
            $sql .= ' AND c.type = :type';
            $params['type'] = $type;
        }

        $isActive = $filters['is_active'] ?? '';
        if ($isActive !== '') {
            $sql .= ' AND c.is_active = :is_active';
            $params['is_active'] = (int)$isActive;
        }

        $sql .= ' GROUP BY c.id ORDER BY c.type ASC, c.code ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function findCurrencyById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM currencies WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createCurrency(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO currencies (code, name, type, decimals, is_active,
                min_withdrawal, max_withdrawal_daily,
                withdrawal_fee_fixed, withdrawal_fee_percent,
                network, contract_address, icon_url, created_at, updated_at)
             VALUES (:code, :name, :type, :decimals, :is_active,
                :min_withdrawal, :max_withdrawal_daily,
                :withdrawal_fee_fixed, :withdrawal_fee_percent,
                :network, :contract_address, :icon_url, NOW(), NOW())"
        );
        $stmt->execute($this->bindCurrencyData($data));
        return (int)$pdo->lastInsertId();
    }

    public function updateCurrency(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE currencies SET
                code = :code, name = :name, type = :type, decimals = :decimals,
                is_active = :is_active,
                min_withdrawal = :min_withdrawal, max_withdrawal_daily = :max_withdrawal_daily,
                withdrawal_fee_fixed = :withdrawal_fee_fixed, withdrawal_fee_percent = :withdrawal_fee_percent,
                network = :network, contract_address = :contract_address,
                icon_url = :icon_url, updated_at = NOW()
             WHERE id = :id"
        );
        $params = $this->bindCurrencyData($data);
        $params[':id'] = $id;
        $stmt->execute($params);
    }

    public function deleteCurrency(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM currencies WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function toggleCurrencyActive(int $id, bool $active): void
    {
        $stmt = Database::connection()->prepare('UPDATE currencies SET is_active = :a, updated_at = NOW() WHERE id = :id');
        $stmt->bindValue(':a', (int)$active, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function bindCurrencyData(array $data): array
    {
        return [
            ':code'                   => strtoupper(trim((string)($data['code'] ?? ''))),
            ':name'                   => trim((string)($data['name'] ?? '')),
            ':type'                   => trim((string)($data['type'] ?? 'crypto')),
            ':decimals'               => max(0, (int)($data['decimals'] ?? $data['precision'] ?? 8)),
            ':is_active'              => (int)(bool)($data['is_active'] ?? 1),
            ':min_withdrawal'         => (string)($data['min_withdrawal'] ?? '0'),
            ':max_withdrawal_daily'   => isset($data['max_withdrawal_daily']) && $data['max_withdrawal_daily'] !== '' ? (string)$data['max_withdrawal_daily'] : null,
            ':withdrawal_fee_fixed'   => (string)($data['withdrawal_fee_fixed'] ?? $data['withdrawal_fee_flat'] ?? '0'),
            ':withdrawal_fee_percent' => (string)($data['withdrawal_fee_percent'] ?? '0'),
            ':network'                => trim((string)($data['network'] ?? '')),
            ':contract_address'       => trim((string)($data['contract_address'] ?? '')),
            ':icon_url'               => trim((string)($data['icon_url'] ?? '')),
        ];
    }

    // -----------------------------------------------------------------------
    // Trading Pairs (extended)
    // -----------------------------------------------------------------------

    public function listTradingPairs(array $filters = []): array
    {
        $sql = "SELECT tp.id, tp.symbol, tp.base_currency_id, tp.quote_currency_id,
                       bc.code AS base_code, qc.code AS quote_code, tp.market_type,
                       tp.is_active, tp.trading_enabled, tp.is_visible,
                       tp.maker_fee_percent, tp.taker_fee_percent,
                       tp.min_order_size, tp.max_order_size, tp.min_notional,
                       tp.max_leverage, tp.price_precision, tp.quantity_precision,
                       tp.display_order, tp.created_at
                FROM trading_pairs tp
                LEFT JOIN currencies bc ON bc.id = tp.base_currency_id
                LEFT JOIN currencies qc ON qc.id = tp.quote_currency_id
                WHERE 1=1";
        $params = [];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (tp.symbol LIKE :search OR bc.code LIKE :search OR qc.code LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $marketType = trim((string)($filters['market_type'] ?? ''));
        if ($marketType !== '') {
            $sql .= ' AND tp.market_type = :market_type';
            $params['market_type'] = $marketType;
        }

        $isActive = $filters['is_active'] ?? '';
        if ($isActive !== '') {
            $sql .= ' AND tp.is_active = :is_active';
            $params['is_active'] = (int)$isActive;
        }

        $sql .= ' ORDER BY tp.display_order ASC, tp.symbol ASC LIMIT 200';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function findTradingPairById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT tp.*, bc.code AS base_code, qc.code AS quote_code
             FROM trading_pairs tp
             LEFT JOIN currencies bc ON bc.id = tp.base_currency_id
             LEFT JOIN currencies qc ON qc.id = tp.quote_currency_id
             WHERE tp.id = :id LIMIT 1"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createTradingPair(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO trading_pairs (symbol, base_currency_id, quote_currency_id, market_type,
                is_active, trading_enabled, is_visible, maker_fee_percent, taker_fee_percent,
                min_order_size, max_order_size, min_notional, max_leverage,
                price_precision, quantity_precision, display_order, created_at, updated_at)
             VALUES (:symbol, :base_currency_id, :quote_currency_id, :market_type,
                :is_active, :trading_enabled, :is_visible, :maker_fee_percent, :taker_fee_percent,
                :min_order_size, :max_order_size, :min_notional, :max_leverage,
                :price_precision, :quantity_precision, :display_order, NOW(), NOW())"
        );
        $stmt->execute($this->bindPairData($data));
        return (int)$pdo->lastInsertId();
    }

    public function updateTradingPair(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE trading_pairs SET
                symbol = :symbol, base_currency_id = :base_currency_id, quote_currency_id = :quote_currency_id,
                market_type = :market_type, is_active = :is_active, trading_enabled = :trading_enabled,
                is_visible = :is_visible, maker_fee_percent = :maker_fee_percent, taker_fee_percent = :taker_fee_percent,
                min_order_size = :min_order_size, max_order_size = :max_order_size, min_notional = :min_notional,
                max_leverage = :max_leverage, price_precision = :price_precision,
                quantity_precision = :quantity_precision, display_order = :display_order, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL"
        );
        $params = $this->bindPairData($data);
        $params[':id'] = $id;
        $stmt->execute($params);
    }

    public function deleteTradingPair(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM trading_pairs WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function bindPairData(array $data): array
    {
        return [
            ':symbol'              => strtoupper(trim((string)($data['symbol'] ?? ''))),
            ':base_currency_id'    => (int)($data['base_currency_id'] ?? 0),
            ':quote_currency_id'   => (int)($data['quote_currency_id'] ?? 0),
            ':market_type'         => trim((string)($data['market_type'] ?? 'spot')),
            ':is_active'           => (int)(bool)($data['is_active'] ?? 1),
            ':trading_enabled'     => (int)(bool)($data['trading_enabled'] ?? 1),
            ':is_visible'          => (int)(bool)($data['is_visible'] ?? 1),
            ':maker_fee_percent'   => (string)($data['maker_fee_percent'] ?? '0.1'),
            ':taker_fee_percent'   => (string)($data['taker_fee_percent'] ?? '0.1'),
            ':min_order_size'      => (string)($data['min_order_size'] ?? '0.001'),
            ':max_order_size'      => isset($data['max_order_size']) && $data['max_order_size'] !== '' ? (string)$data['max_order_size'] : null,
            ':min_notional'        => (string)($data['min_notional'] ?? '1'),
            ':max_leverage'        => max(1, (int)($data['max_leverage'] ?? 1)),
            ':price_precision'     => max(0, (int)($data['price_precision'] ?? 2)),
            ':quantity_precision'  => max(0, (int)($data['quantity_precision'] ?? 6)),
            ':display_order'       => max(0, (int)($data['display_order'] ?? 0)),
        ];
    }

    // -----------------------------------------------------------------------
    // Price Tickers
    // -----------------------------------------------------------------------

    public function listPriceTickers(int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT pt.pair_id, tp.symbol, pt.last_price, pt.bid_price, pt.ask_price,
                    pt.volume_24h, pt.price_change_24h, pt.price_change_pct_24h,
                    pt.high_24h, pt.low_24h, pt.updated_at
             FROM price_tickers pt
             INNER JOIN trading_pairs tp ON tp.id = pt.pair_id
             WHERE tp.deleted_at IS NULL
             ORDER BY pt.volume_24h DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
