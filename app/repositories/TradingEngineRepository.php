<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use PDO;
use RuntimeException;

/**
 * Complete Trading Engine Repository.
 *
 * Handles: order placement, order book queries, simulated matching engine,
 * atomic trade execution, position management (spot / margin / futures),
 * fee calculation, candlestick / ticker updates and market-data reads.
 *
 * Every balance-affecting operation delegates to WalletBalanceRepository
 * to guarantee double-entry ledger integrity.
 */
final class TradingEngineRepository
{
    private readonly WalletBalanceRepository $balanceRepo;

    public function __construct()
    {
        $this->balanceRepo = new WalletBalanceRepository();
    }

    // =========================================================================
    // TRADING PAIRS & MARKET DATA
    // =========================================================================

    public function getTradingPairBySymbol(string $symbol): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT tp.id, tp.symbol, tp.market_type, tp.price_precision, tp.quantity_precision,
                    tp.min_order_size, tp.max_order_size, tp.min_notional,
                    tp.maker_fee_percent, tp.taker_fee_percent, tp.max_leverage,
                    tp.is_active, tp.trading_enabled,
                    bc.id AS base_currency_id, bc.code AS base_code, bc.name AS base_name,
                    bc.decimals AS base_decimals,
                    qc.id AS quote_currency_id, qc.code AS quote_code, qc.name AS quote_name,
                    qc.decimals AS quote_decimals,
                    COALESCE(pt.last_price, 0)          AS last_price,
                    COALESCE(pt.best_bid, 0)            AS best_bid,
                    COALESCE(pt.best_ask, 0)            AS best_ask,
                    COALESCE(pt.change_24h_percent, 0)  AS change_24h_percent,
                    COALESCE(pt.high_24h, 0)            AS high_24h,
                    COALESCE(pt.low_24h, 0)             AS low_24h,
                    COALESCE(pt.volume_24h, 0)          AS volume_24h
             FROM trading_pairs tp
             INNER JOIN currencies bc ON bc.id = tp.base_currency_id
             INNER JOIN currencies qc ON qc.id = tp.quote_currency_id
             LEFT  JOIN price_tickers pt ON pt.trading_pair_id = tp.id
             WHERE tp.symbol = :sym AND tp.is_active = 1
             LIMIT 1'
        );
        $stmt->bindValue(':sym', $symbol);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getTradingPairById(int $pairId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT tp.id, tp.symbol, tp.market_type, tp.price_precision, tp.quantity_precision,
                    tp.min_order_size, tp.max_order_size, tp.min_notional,
                    tp.maker_fee_percent, tp.taker_fee_percent, tp.max_leverage,
                    tp.is_active, tp.trading_enabled,
                    bc.id AS base_currency_id, bc.code AS base_code,
                    qc.id AS quote_currency_id, qc.code AS quote_code,
                    COALESCE(pt.last_price, 0) AS last_price
             FROM trading_pairs tp
             INNER JOIN currencies bc ON bc.id = tp.base_currency_id
             INNER JOIN currencies qc ON qc.id = tp.quote_currency_id
             LEFT  JOIN price_tickers pt ON pt.trading_pair_id = tp.id
             WHERE tp.id = :id AND tp.is_active = 1
             LIMIT 1'
        );
        $stmt->bindValue(':id', $pairId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** All visible active pairs with latest ticker snapshot. */
    public function getAllActivePairs(): array
    {
        $stmt = Database::connection()->query(
            'SELECT tp.id, tp.symbol, tp.market_type,
                    bc.code AS base_code, qc.code AS quote_code,
                    COALESCE(pt.last_price, 0)         AS last_price,
                    COALESCE(pt.best_bid, 0)           AS best_bid,
                    COALESCE(pt.best_ask, 0)           AS best_ask,
                    COALESCE(pt.change_24h_percent, 0) AS change_24h_percent,
                    COALESCE(pt.high_24h, 0)           AS high_24h,
                    COALESCE(pt.low_24h, 0)            AS low_24h,
                    COALESCE(pt.volume_24h, 0)         AS volume_24h
             FROM trading_pairs tp
             INNER JOIN currencies bc ON bc.id = tp.base_currency_id
             INNER JOIN currencies qc ON qc.id = tp.quote_currency_id
             LEFT  JOIN price_tickers pt ON pt.trading_pair_id = tp.id
             WHERE tp.is_active = 1 AND tp.is_visible = 1
             ORDER BY tp.display_order ASC, tp.id ASC'
        );
        return $stmt->fetchAll() ?: [];
    }

    public function getTicker(int $pairId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT pt.*, tp.symbol
             FROM price_tickers pt
             INNER JOIN trading_pairs tp ON tp.id = pt.trading_pair_id
             WHERE pt.trading_pair_id = :pid LIMIT 1'
        );
        $stmt->bindValue(':pid', $pairId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Return simulated order book from the open orders table.
     * Bids: buy orders sorted price DESC; asks: sell orders sorted price ASC.
     */
    public function getOrderBook(int $pairId, int $depth = 20): array
    {
        $pdo = Database::connection();

        $bids = $pdo->prepare(
            "SELECT price, SUM(remaining_quantity) AS quantity, COUNT(*) AS order_count
             FROM orders
             WHERE trading_pair_id = :pid AND side = 'buy'
               AND status IN ('open','partially_filled') AND price IS NOT NULL
             GROUP BY price
             ORDER BY price DESC
             LIMIT :depth"
        );
        $bids->bindValue(':pid',   $pairId, PDO::PARAM_INT);
        $bids->bindValue(':depth', max(1, $depth), PDO::PARAM_INT);
        $bids->execute();

        $asks = $pdo->prepare(
            "SELECT price, SUM(remaining_quantity) AS quantity, COUNT(*) AS order_count
             FROM orders
             WHERE trading_pair_id = :pid AND side = 'sell'
               AND status IN ('open','partially_filled') AND price IS NOT NULL
             GROUP BY price
             ORDER BY price ASC
             LIMIT :depth"
        );
        $asks->bindValue(':pid',   $pairId, PDO::PARAM_INT);
        $asks->bindValue(':depth', max(1, $depth), PDO::PARAM_INT);
        $asks->execute();

        return [
            'bids' => $bids->fetchAll() ?: [],
            'asks' => $asks->fetchAll() ?: [],
        ];
    }

    /** OHLCV candlestick data for charting. */
    public function getCandlesticks(int $pairId, string $interval, int $limit = 200): array
    {
        $allowed = ['1m', '5m', '15m', '30m', '1h', '4h', '1d', '1w', '1M'];
        if (!in_array($interval, $allowed, true)) {
            $interval = '1h';
        }

        $stmt = Database::connection()->prepare(
            'SELECT open_time, open_price, high_price, low_price, close_price,
                    volume, quote_volume, trade_count
             FROM candlesticks
             WHERE trading_pair_id = :pid AND interval_code = :iv
             ORDER BY open_time DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':pid', $pairId, PDO::PARAM_INT);
        $stmt->bindValue(':iv',  $interval);
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];
        // Return in ascending order for charting libraries
        return array_reverse($rows);
    }

    /** Recent public trades for a pair. */
    public function getRecentTrades(int $pairId, int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT t.price, t.quantity, t.quote_amount, t.maker_side, t.executed_at
             FROM trades t
             WHERE t.trading_pair_id = :pid
             ORDER BY t.id DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':pid', $pairId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // FEE ENGINE
    // =========================================================================

    /** Returns maker/taker fee rates for a user on a given pair. */
    public function getUserFeeRates(int $userId, float $pairMakerFee, float $pairTakerFee): array
    {
        $pdo = Database::connection();

        // Check for individual override first
        $feeOverrideStmt = $pdo->prepare(
            'SELECT ufo.custom_maker_fee_percent, ufo.custom_taker_fee_percent,
                    ufo.fee_tier_id,
                    ft.maker_fee_percent AS tier_maker, ft.taker_fee_percent AS tier_taker
             FROM user_fee_overrides ufo
             LEFT JOIN fee_tiers ft ON ft.id = ufo.fee_tier_id AND ft.is_active = 1
             WHERE ufo.user_id = :uid LIMIT 1'
        );
        $feeOverrideStmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $feeOverrideStmt->execute();
        $override = $feeOverrideStmt->fetch();

        if ($override !== false) {
            $makerFee = $override['custom_maker_fee_percent'] !== null
                ? (float)$override['custom_maker_fee_percent']
                : ($override['tier_maker'] !== null ? (float)$override['tier_maker'] : $pairMakerFee);
            $takerFee = $override['custom_taker_fee_percent'] !== null
                ? (float)$override['custom_taker_fee_percent']
                : ($override['tier_taker'] !== null ? (float)$override['tier_taker'] : $pairTakerFee);
            return ['maker' => $makerFee, 'taker' => $takerFee];
        }

        return ['maker' => $pairMakerFee, 'taker' => $pairTakerFee];
    }

    public function recordFeeRevenue(
        int    $tradeId,
        int    $currencyId,
        string $amount,
        string $source = 'trading_fee'
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO fee_revenue_ledger (trade_id, currency_id, amount, source, created_at)
             VALUES (:tid, :cid, :amt, :src, NOW())'
        );
        $stmt->bindValue(':tid', $tradeId,    PDO::PARAM_INT);
        $stmt->bindValue(':cid', $currencyId, PDO::PARAM_INT);
        $stmt->bindValue(':amt', $amount);
        $stmt->bindValue(':src', $source);
        $stmt->execute();
    }

    // =========================================================================
    // ORDER PLACEMENT
    // =========================================================================

    /**
     * Insert a new order record.  Locks required funds via WalletBalanceRepository.
     *
     * For SPOT BUY orders:  lock quote_currency (price * quantity for limit; market uses last_price estimate)
     * For SPOT SELL orders: lock base_currency (quantity)
     * For FUTURES orders:   lock margin (quantity * price / leverage) from margin wallet
     *
     * @param array $data {
     *   user_id, trading_pair_id, order_type (market|limit|stop_limit|stop_market),
     *   side (buy|sell), time_in_force (GTC|IOC|FOK), price, stop_price,
     *   quantity, leverage, is_reduce_only, client_order_id, source,
     *   market_type (spot|margin|futures)
     * }
     * @return int inserted order ID
     */
    public function placeOrder(array $data): int
    {
        $pdo = Database::connection();

        $userId       = (int)$data['user_id'];
        $pairId       = (int)$data['trading_pair_id'];
        $orderType    = (string)$data['order_type'];
        $side         = (string)$data['side'];
        $tif          = (string)($data['time_in_force'] ?? 'GTC');
        $price        = isset($data['price']) && $data['price'] !== null ? (string)$data['price'] : null;
        $stopPrice    = isset($data['stop_price']) && $data['stop_price'] !== null ? (string)$data['stop_price'] : null;
        $quantity     = (string)$data['quantity'];
        $leverage     = (string)($data['leverage'] ?? '1.00');
        $isReduceOnly = (int)($data['is_reduce_only'] ?? 0);
        $clientOid    = $data['client_order_id'] ?? null;
        $source       = (string)($data['source'] ?? 'web');
        $marketType   = (string)($data['market_type'] ?? 'spot');
        $orderUuid    = $this->generateUuid();

        // Fetch pair info for wallet IDs
        $pair = $this->getTradingPairById($pairId);
        if ($pair === null) {
            throw new RuntimeException('Trading pair not found or inactive.');
        }

        $baseCurrencyId  = (int)$pair['base_currency_id'];
        $quoteCurrencyId = (int)$pair['quote_currency_id'];
        $effectivePrice  = $price !== null ? $price : (string)$pair['last_price'];
        if (bccomp($effectivePrice, '0', 18) <= 0) {
            $effectivePrice = '1'; // fallback to avoid division-by-zero
        }

        // Determine lock amount and wallet
        $walletType = $marketType === 'futures' ? 'futures' : ($marketType === 'margin' ? 'margin' : 'spot');

        if ($marketType === 'futures') {
            // Margin required = notional / leverage
            $notional      = bcmul($effectivePrice, $quantity, 18);
            $marginRequired = bcdiv($notional, $leverage, 18);
            $lockWallet    = $this->balanceRepo->findOrCreate($userId, $quoteCurrencyId, $walletType);
            $lockAmount    = $marginRequired;
        } elseif ($side === 'buy') {
            // Lock quote (cost = price * qty)
            $lockAmount = bcmul($effectivePrice, $quantity, 18);
            $lockWallet = $this->balanceRepo->findOrCreate($userId, $quoteCurrencyId, $walletType);
        } else {
            // Lock base (quantity)
            $lockAmount = $quantity;
            $lockWallet = $this->balanceRepo->findOrCreate($userId, $baseCurrencyId, $walletType);
        }

        // Lock funds before inserting order (prevents placing unfunded orders)
        $this->balanceRepo->lock((int)$lockWallet['id'], $lockAmount);

        // Get the order type id from order_types table
        $typeIdStmt = $pdo->prepare('SELECT id FROM order_types WHERE name = :name LIMIT 1');
        $typeIdStmt->bindValue(':name', $orderType);
        $typeIdStmt->execute();
        $typeRow = $typeIdStmt->fetch();
        $orderTypeId = $typeRow !== false ? (int)$typeRow['id'] : 1;

        $stmt = $pdo->prepare(
            'INSERT INTO orders
               (order_uuid, user_id, trading_pair_id, order_type_id, side, time_in_force,
                price, stop_price, quantity, filled_quantity, remaining_quantity,
                average_fill_price, status, leverage, is_reduce_only, client_order_id,
                rejection_reason, source, created_at, updated_at)
             VALUES
               (:uuid, :uid, :pid, :otype, :side, :tif,
                :price, :stop, :qty, 0, :qty,
                NULL, \'open\', :lev, :reduce, :coid,
                NULL, :src, NOW(), NOW())'
        );
        $stmt->bindValue(':uuid',   $orderUuid);
        $stmt->bindValue(':uid',    $userId,       PDO::PARAM_INT);
        $stmt->bindValue(':pid',    $pairId,       PDO::PARAM_INT);
        $stmt->bindValue(':otype',  $orderTypeId,  PDO::PARAM_INT);
        $stmt->bindValue(':side',   $side);
        $stmt->bindValue(':tif',    $tif);
        $stmt->bindValue(':price',  $price);
        $stmt->bindValue(':stop',   $stopPrice);
        $stmt->bindValue(':qty',    $quantity);
        $stmt->bindValue(':lev',    $leverage);
        $stmt->bindValue(':reduce', $isReduceOnly, PDO::PARAM_INT);
        $stmt->bindValue(':coid',   $clientOid);
        $stmt->bindValue(':src',    $source);
        $stmt->execute();

        $orderId = (int)$pdo->lastInsertId();

        // Record order created event
        $this->insertOrderEvent($orderId, 'created', [
            'order_type' => $orderType,
            'side'       => $side,
            'quantity'   => $quantity,
            'price'      => $price,
        ]);

        return $orderId;
    }

    // =========================================================================
    // ORDER CANCELLATION
    // =========================================================================

    /**
     * Cancel an open order and unlock held funds.
     */
    public function cancelOrder(int $orderId, int $userId, string $reason = 'Cancelled by user'): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            "SELECT o.*, ot.name AS order_type_name,
                    tp.base_currency_id, tp.quote_currency_id, tp.market_type
             FROM orders o
             INNER JOIN order_types ot ON ot.id = o.order_type_id
             INNER JOIN trading_pairs tp ON tp.id = o.trading_pair_id
             WHERE o.id = :oid AND o.user_id = :uid
               AND o.status IN ('open','partially_filled')
             LIMIT 1 FOR UPDATE"
        );
        $stmt->bindValue(':oid', $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId,  PDO::PARAM_INT);
        $stmt->execute();
        $order = $stmt->fetch();
        if ($order === false) {
            throw new RuntimeException('Order not found or cannot be cancelled.');
        }

        // Unlock remaining locked funds
        $remainingQty = (string)$order['remaining_quantity'];
        $price        = $order['price'] !== null ? (string)$order['price'] : null;
        $leverage     = (string)($order['leverage'] ?? '1.00');
        $marketType   = (string)$order['market_type'];
        $walletType   = $marketType === 'futures' ? 'futures' : ($marketType === 'margin' ? 'margin' : 'spot');

        if ($marketType === 'futures') {
            $lastPrice = $price ?? '1';
            $unlockAmt = bcdiv(bcmul($lastPrice, $remainingQty, 18), $leverage, 18);
            $currencyId = (int)$order['quote_currency_id'];
        } elseif ($order['side'] === 'buy') {
            $unlockAmt  = bcmul($price ?? '1', $remainingQty, 18);
            $currencyId = (int)$order['quote_currency_id'];
        } else {
            $unlockAmt  = $remainingQty;
            $currencyId = (int)$order['base_currency_id'];
        }

        $wallet = $this->balanceRepo->findOrCreate($userId, $currencyId, $walletType);
        if (bccomp($unlockAmt, '0', 18) > 0) {
            $this->balanceRepo->unlock((int)$wallet['id'], $unlockAmt);
        }

        $upd = $pdo->prepare(
            "UPDATE orders
             SET status = 'cancelled', cancelled_at = NOW(), rejection_reason = :reason, updated_at = NOW()
             WHERE id = :oid"
        );
        $upd->bindValue(':reason', $reason);
        $upd->bindValue(':oid',    $orderId, PDO::PARAM_INT);
        $upd->execute();

        $this->insertOrderEvent($orderId, 'cancelled', ['reason' => $reason]);
    }

    // =========================================================================
    // MATCHING ENGINE
    // =========================================================================

    /**
     * Find the best matching counter-order for the given order.
     *
     * Rules:
     * - BUY limit order matches SELL orders at ask <= bid (price-time priority)
     * - SELL limit order matches BUY orders at bid >= ask (price-time priority)
     * - Market orders match at any available price
     */
    public function findMatchingOrders(int $orderId): array
    {
        $pdo = Database::connection();

        $orderStmt = $pdo->prepare(
            "SELECT o.id, o.side, o.price, o.remaining_quantity, o.trading_pair_id,
                    o.user_id, o.leverage, tp.market_type,
                    ot.name AS order_type_name
             FROM orders o
             INNER JOIN order_types ot ON ot.id = o.order_type_id
             INNER JOIN trading_pairs tp ON tp.id = o.trading_pair_id
             WHERE o.id = :oid AND o.status IN ('open','partially_filled')
             LIMIT 1"
        );
        $orderStmt->bindValue(':oid', $orderId, PDO::PARAM_INT);
        $orderStmt->execute();
        $order = $orderStmt->fetch();
        if ($order === false) {
            return [];
        }

        $isMarket  = in_array((string)$order['order_type_name'], ['market', 'stop_market'], true);
        $pairId    = (int)$order['trading_pair_id'];
        $side      = (string)$order['side'];
        $price     = $order['price'] !== null ? (string)$order['price'] : null;

        if ($side === 'buy') {
            // Find sell orders at ask <= our bid
            if ($isMarket) {
                $sql = "SELECT o.id, o.user_id, o.price, o.remaining_quantity, o.leverage, tp.market_type
                        FROM orders o
                        INNER JOIN trading_pairs tp ON tp.id = o.trading_pair_id
                        WHERE o.trading_pair_id = :pid AND o.side = 'sell'
                          AND o.status IN ('open','partially_filled')
                          AND o.id != :oid AND o.user_id != :uid
                        ORDER BY o.price ASC, o.id ASC LIMIT 50";
            } else {
                $sql = "SELECT o.id, o.user_id, o.price, o.remaining_quantity, o.leverage, tp.market_type
                        FROM orders o
                        INNER JOIN trading_pairs tp ON tp.id = o.trading_pair_id
                        WHERE o.trading_pair_id = :pid AND o.side = 'sell'
                          AND o.status IN ('open','partially_filled')
                          AND o.price <= :price
                          AND o.id != :oid AND o.user_id != :uid
                        ORDER BY o.price ASC, o.id ASC LIMIT 50";
            }
        } else {
            // Find buy orders at bid >= our ask
            if ($isMarket) {
                $sql = "SELECT o.id, o.user_id, o.price, o.remaining_quantity, o.leverage, tp.market_type
                        FROM orders o
                        INNER JOIN trading_pairs tp ON tp.id = o.trading_pair_id
                        WHERE o.trading_pair_id = :pid AND o.side = 'buy'
                          AND o.status IN ('open','partially_filled')
                          AND o.id != :oid AND o.user_id != :uid
                        ORDER BY o.price DESC, o.id ASC LIMIT 50";
            } else {
                $sql = "SELECT o.id, o.user_id, o.price, o.remaining_quantity, o.leverage, tp.market_type
                        FROM orders o
                        INNER JOIN trading_pairs tp ON tp.id = o.trading_pair_id
                        WHERE o.trading_pair_id = :pid AND o.side = 'buy'
                          AND o.status IN ('open','partially_filled')
                          AND o.price >= :price
                          AND o.id != :oid AND o.user_id != :uid
                        ORDER BY o.price DESC, o.id ASC LIMIT 50";
            }
        }

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':pid', $pairId, PDO::PARAM_INT);
        $stmt->bindValue(':oid', $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':uid', (int)$order['user_id'], PDO::PARAM_INT);
        if (!$isMarket && $price !== null) {
            $stmt->bindValue(':price', $price);
        }
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Execute an atomic trade between two matched orders.
     *
     * This is the core of the matching engine:
     * 1. Calculate fill quantity (min of remaining quantities)
     * 2. Settle wallets (debit locked, credit received)
     * 3. Deduct fees
     * 4. Record trade
     * 5. Update both orders (filled_quantity, remaining_quantity, average_fill_price, status)
     * 6. Record ledger entries and fee revenue
     *
     * @return int Trade ID (0 if nothing matched)
     */
    public function executeTrade(
        int    $buyOrderId,
        int    $sellOrderId,
        string $tradePrice,
        string $tradeQuantity,
        array  $pair
    ): int {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            // Lock both orders for update
            $lockStmt = $pdo->prepare(
                "SELECT o.id, o.user_id, o.side, o.price, o.quantity,
                        o.filled_quantity, o.remaining_quantity, o.average_fill_price,
                        o.leverage, tp.market_type,
                        tp.base_currency_id, tp.quote_currency_id,
                        tp.maker_fee_percent, tp.taker_fee_percent
                 FROM orders o
                 INNER JOIN trading_pairs tp ON tp.id = o.trading_pair_id
                 WHERE o.id IN (:buy, :sell) FOR UPDATE"
            );
            $lockStmt->bindValue(':buy',  $buyOrderId,  PDO::PARAM_INT);
            $lockStmt->bindValue(':sell', $sellOrderId, PDO::PARAM_INT);
            $lockStmt->execute();
            $locked = $lockStmt->fetchAll();

            $orders = [];
            foreach ($locked as $row) {
                $orders[(int)$row['id']] = $row;
            }

            if (!isset($orders[$buyOrderId], $orders[$sellOrderId])) {
                $pdo->rollBack();
                return 0;
            }

            $buyOrder  = $orders[$buyOrderId];
            $sellOrder = $orders[$sellOrderId];

            $pairMakerFee = (float)($pair['maker_fee_percent'] ?? $buyOrder['maker_fee_percent'] ?? 0.1);
            $pairTakerFee = (float)($pair['taker_fee_percent'] ?? $buyOrder['taker_fee_percent'] ?? 0.15);

            // The resting order is the maker; the incoming is the taker
            // Typically sell is the maker if sell price <= buy price
            $makerSide = 'sell';

            // Fee rates per user
            $buyerFeeRates  = $this->getUserFeeRates((int)$buyOrder['user_id'],  $pairMakerFee, $pairTakerFee);
            $sellerFeeRates = $this->getUserFeeRates((int)$sellOrder['user_id'], $pairMakerFee, $pairTakerFee);

            $buyerFeeRate  = $makerSide === 'buy'  ? $buyerFeeRates['maker']  : $buyerFeeRates['taker'];
            $sellerFeeRate = $makerSide === 'sell' ? $sellerFeeRates['maker'] : $sellerFeeRates['taker'];

            $quoteAmount = bcmul($tradePrice, $tradeQuantity, 18);
            $buyerFee    = bcmul($quoteAmount, bcdiv((string)$buyerFeeRate,  '100', 18), 18);
            $sellerFee   = bcmul($quoteAmount, bcdiv((string)$sellerFeeRate, '100', 18), 18);

            $baseCurrencyId  = (int)($pair['base_currency_id']  ?? $buyOrder['base_currency_id']);
            $quoteCurrencyId = (int)($pair['quote_currency_id'] ?? $buyOrder['quote_currency_id']);
            $buyerMarketType = (string)$buyOrder['market_type'];
            $sellerMarketType = (string)$sellOrder['market_type'];
            $buyerWalletType  = $buyerMarketType === 'futures' ? 'futures' : ($buyerMarketType === 'margin' ? 'margin' : 'spot');
            $sellerWalletType = $sellerMarketType === 'futures' ? 'futures' : ($sellerMarketType === 'margin' ? 'margin' : 'spot');

            // Insert trade record
            $tradeUuid = $this->generateUuid();
            $tradeStmt = $pdo->prepare(
                'INSERT INTO trades
                   (trade_uuid, trading_pair_id, buy_order_id, sell_order_id,
                    buyer_id, seller_id, price, quantity, quote_amount,
                    buyer_fee, seller_fee, maker_side, executed_at)
                 VALUES
                   (:uuid, :pid, :boid, :soid, :bid, :sid, :price, :qty, :qamt,
                    :bfee, :sfee, :mkside, NOW())'
            );
            $tradeStmt->bindValue(':uuid',   $tradeUuid);
            $tradeStmt->bindValue(':pid',    (int)$pair['id'],          PDO::PARAM_INT);
            $tradeStmt->bindValue(':boid',   $buyOrderId,               PDO::PARAM_INT);
            $tradeStmt->bindValue(':soid',   $sellOrderId,              PDO::PARAM_INT);
            $tradeStmt->bindValue(':bid',    (int)$buyOrder['user_id'], PDO::PARAM_INT);
            $tradeStmt->bindValue(':sid',    (int)$sellOrder['user_id'],PDO::PARAM_INT);
            $tradeStmt->bindValue(':price',  $tradePrice);
            $tradeStmt->bindValue(':qty',    $tradeQuantity);
            $tradeStmt->bindValue(':qamt',   $quoteAmount);
            $tradeStmt->bindValue(':bfee',   $buyerFee);
            $tradeStmt->bindValue(':sfee',   $sellerFee);
            $tradeStmt->bindValue(':mkside', $makerSide);
            $tradeStmt->execute();
            $tradeId = (int)$pdo->lastInsertId();

            // Settle buyer wallet: gets base, spent locked quote
            $buyerQuoteWallet = $this->balanceRepo->findOrCreate((int)$buyOrder['user_id'], $quoteCurrencyId, $buyerWalletType);
            $buyerBaseWallet  = $this->balanceRepo->findOrCreate((int)$buyOrder['user_id'], $baseCurrencyId,  $buyerWalletType);

            // Release locked quote from buyer (price * qty was locked at order placement)
            $lockedQuoteUsed = bcmul((string)($buyOrder['price'] ?? $tradePrice), $tradeQuantity, 18);
            if (bccomp($lockedQuoteUsed, '0', 18) > 0) {
                // Debit from locked portion (already moved to locked_balance by lock())
                // We need to reduce locked_balance and apply actual spend
                $this->releaseLockedAndDebit(
                    $pdo,
                    (int)$buyerQuoteWallet['id'],
                    $lockedQuoteUsed,
                    bcadd($quoteAmount, $buyerFee, 18),
                    $tradeId
                );
            }

            // Credit base to buyer (minus buyer fee in base equivalent)
            $buyerBaseReceived = $tradeQuantity; // fee is in quote for simplicity
            $this->creditFromLocked($pdo, (int)$buyerBaseWallet['id'], $buyerBaseReceived, $tradeId);

            // Settle seller wallet: gets quote, spent locked base
            $sellerBaseWallet  = $this->balanceRepo->findOrCreate((int)$sellOrder['user_id'], $baseCurrencyId,  $sellerWalletType);
            $sellerQuoteWallet = $this->balanceRepo->findOrCreate((int)$sellOrder['user_id'], $quoteCurrencyId, $sellerWalletType);

            // Release seller's locked base
            $this->releaseLockedAndDebit(
                $pdo,
                (int)$sellerBaseWallet['id'],
                $tradeQuantity,
                $tradeQuantity,
                $tradeId
            );

            // Credit quote to seller (minus fee)
            $sellerQuoteReceived = bcsub($quoteAmount, $sellerFee, 18);
            if (bccomp($sellerQuoteReceived, '0', 18) < 0) {
                $sellerQuoteReceived = '0';
            }
            $this->creditFromLocked($pdo, (int)$sellerQuoteWallet['id'], $sellerQuoteReceived, $tradeId);

            // Update buy order
            $this->updateOrderFill($pdo, $buyOrderId, $tradeQuantity, $tradePrice);

            // Update sell order
            $this->updateOrderFill($pdo, $sellOrderId, $tradeQuantity, $tradePrice);

            // Record fee revenue (fees collected in quote currency)
            $totalFee = bcadd($buyerFee, $sellerFee, 18);
            if (bccomp($totalFee, '0', 18) > 0) {
                $this->recordFeeRevenue($tradeId, $quoteCurrencyId, $totalFee, 'trading_fee');
            }

            // Update price ticker
            $this->updatePriceTicker((int)$pair['id'], $tradePrice, $tradeQuantity);

            // Update candlestick for 1m, 1h, 1d intervals
            foreach (['1m', '1h', '1d'] as $interval) {
                $this->upsertCandlestick((int)$pair['id'], $interval, $tradePrice, $tradeQuantity);
            }

            $pdo->commit();
            return $tradeId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // POSITION MANAGEMENT (FUTURES / MARGIN)
    // =========================================================================

    public function getOrCreateMarginAccount(int $userId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM margin_accounts WHERE user_id = :uid LIMIT 1');
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        if ($row !== false) {
            return $row;
        }

        $ins = $pdo->prepare(
            'INSERT INTO margin_accounts
               (user_id, margin_level, total_collateral_usd, total_borrowed_usd, is_restricted, created_at, updated_at)
             VALUES (:uid, 0, 0, 0, 0, NOW(), NOW())'
        );
        $ins->bindValue(':uid', $userId, PDO::PARAM_INT);
        $ins->execute();

        return [
            'id'                  => (int)$pdo->lastInsertId(),
            'user_id'             => $userId,
            'margin_level'        => '0',
            'total_collateral_usd'=> '0',
            'total_borrowed_usd'  => '0',
            'is_restricted'       => 0,
        ];
    }

    /**
     * Open a new position (futures or margin).
     */
    public function openPosition(array $data): int
    {
        $pdo = Database::connection();

        $userId        = (int)$data['user_id'];
        $pairId        = (int)$data['trading_pair_id'];
        $side          = (string)$data['position_side'];
        $entryPrice    = (string)$data['entry_price'];
        $quantity      = (string)$data['quantity'];
        $leverage      = (string)($data['leverage'] ?? '1.00');
        $marginUsed    = (string)($data['margin_used'] ?? '0');
        $liquidationPx = isset($data['liquidation_price']) ? (string)$data['liquidation_price'] : null;

        $stmt = $pdo->prepare(
            'INSERT INTO positions
               (user_id, trading_pair_id, position_side, entry_price, quantity, leverage,
                liquidation_price, margin_used, unrealized_pnl, realized_pnl,
                status, opened_at)
             VALUES
               (:uid, :pid, :side, :entry, :qty, :lev,
                :liq, :margin, 0, 0, \'open\', NOW())'
        );
        $stmt->bindValue(':uid',    $userId,        PDO::PARAM_INT);
        $stmt->bindValue(':pid',    $pairId,        PDO::PARAM_INT);
        $stmt->bindValue(':side',   $side);
        $stmt->bindValue(':entry',  $entryPrice);
        $stmt->bindValue(':qty',    $quantity);
        $stmt->bindValue(':lev',    $leverage);
        $stmt->bindValue(':liq',    $liquidationPx);
        $stmt->bindValue(':margin', $marginUsed);
        $stmt->execute();

        return (int)$pdo->lastInsertId();
    }

    public function updatePositionPnl(int $positionId, string $currentPrice): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'SELECT position_side, entry_price, quantity, leverage FROM positions WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $positionId, PDO::PARAM_INT);
        $stmt->execute();
        $pos = $stmt->fetch();
        if ($pos === false) {
            return;
        }

        $qty        = (string)$pos['quantity'];
        $entryPrice = (string)$pos['entry_price'];
        $side       = (string)$pos['position_side'];

        if ($side === 'long') {
            $priceDiff    = bcsub($currentPrice, $entryPrice, 18);
        } else {
            $priceDiff    = bcsub($entryPrice, $currentPrice, 18);
        }
        $unrealizedPnl = bcmul($priceDiff, $qty, 18);

        $upd = $pdo->prepare(
            'UPDATE positions SET unrealized_pnl = :upnl, updated_at = NOW() WHERE id = :id'
        );
        $upd->bindValue(':upnl', $unrealizedPnl);
        $upd->bindValue(':id',   $positionId, PDO::PARAM_INT);
        $upd->execute();
    }

    public function closePosition(int $positionId, int $userId, string $closePrice): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT p.*, tp.quote_currency_id FROM positions p
                 INNER JOIN trading_pairs tp ON tp.id = p.trading_pair_id
                 WHERE p.id = :pid AND p.user_id = :uid AND p.status = \'open\'
                 LIMIT 1 FOR UPDATE'
            );
            $stmt->bindValue(':pid', $positionId, PDO::PARAM_INT);
            $stmt->bindValue(':uid', $userId,     PDO::PARAM_INT);
            $stmt->execute();
            $pos = $stmt->fetch();

            if ($pos === false) {
                throw new RuntimeException('Position not found or already closed.');
            }

            $qty        = (string)$pos['quantity'];
            $entryPrice = (string)$pos['entry_price'];
            $side       = (string)$pos['position_side'];
            $leverage   = (string)$pos['leverage'];
            $marginUsed = (string)$pos['margin_used'];

            if ($side === 'long') {
                $priceDiff = bcsub($closePrice, $entryPrice, 18);
            } else {
                $priceDiff = bcsub($entryPrice, $closePrice, 18);
            }
            $realizedPnl = bcmul($priceDiff, $qty, 18);

            // Return margin + pnl to user's futures wallet
            $quoteCurrencyId = (int)$pos['quote_currency_id'];
            $futuresWallet   = $this->balanceRepo->findOrCreate($userId, $quoteCurrencyId, 'futures');
            $walletId        = (int)$futuresWallet['id'];

            $returnAmount = bcadd($marginUsed, $realizedPnl, 18);
            if (bccomp($returnAmount, '0', 18) > 0) {
                // Use internal write directly within this transaction
                $stmt2 = $pdo->prepare(
                    'UPDATE wallets SET available_balance = available_balance + :amt, updated_at = NOW()
                     WHERE id = :wid'
                );
                $stmt2->bindValue(':amt', $returnAmount);
                $stmt2->bindValue(':wid', $walletId, PDO::PARAM_INT);
                $stmt2->execute();
            }

            // Update realized_pnl on margin_accounts
            $this->addRealizedPnlToMarginAccount($pdo, $userId, $realizedPnl);

            // Close position
            $upd = $pdo->prepare(
                "UPDATE positions
                 SET status = 'closed', realized_pnl = :rpnl, unrealized_pnl = 0,
                     closed_at = NOW()
                 WHERE id = :pid"
            );
            $upd->bindValue(':rpnl', $realizedPnl);
            $upd->bindValue(':pid',  $positionId, PDO::PARAM_INT);
            $upd->execute();

            $pdo->commit();
            return ['realized_pnl' => $realizedPnl, 'margin_returned' => $marginUsed];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Force liquidate a position: loss is covered by insurance fund if insufficient.
     */
    public function liquidatePosition(int $positionId, string $liquidationPrice): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT p.*, tp.quote_currency_id
                 FROM positions p
                 INNER JOIN trading_pairs tp ON tp.id = p.trading_pair_id
                 WHERE p.id = :pid AND p.status = \'open\'
                 LIMIT 1 FOR UPDATE'
            );
            $stmt->bindValue(':pid', $positionId, PDO::PARAM_INT);
            $stmt->execute();
            $pos = $stmt->fetch();
            if ($pos === false) {
                $pdo->rollBack();
                return;
            }

            $qty        = (string)$pos['quantity'];
            $entryPrice = (string)$pos['entry_price'];
            $side       = (string)$pos['position_side'];
            $marginUsed = (string)$pos['margin_used'];

            if ($side === 'long') {
                $priceDiff = bcsub($liquidationPrice, $entryPrice, 18);
            } else {
                $priceDiff = bcsub($entryPrice, $liquidationPrice, 18);
            }
            $loss        = bcmul($priceDiff, $qty, 18); // negative for a loss
            $lossAbs     = bccomp($loss, '0', 18) < 0 ? bcmul($loss, '-1', 18) : '0';

            // Insurance fund covers any shortfall
            $insuranceCovered = '0';
            if (bccomp($lossAbs, $marginUsed, 18) > 0) {
                $shortfall        = bcsub($lossAbs, $marginUsed, 18);
                $insuranceCovered = $shortfall;
                // Deduct from insurance fund (best effort)
                $pdo->prepare(
                    'UPDATE insurance_fund SET balance = GREATEST(0, balance - :amt), updated_at = NOW() WHERE id = 1'
                )->execute([':amt' => $shortfall]);
            }

            // Record liquidation
            $liqStmt = $pdo->prepare(
                'INSERT INTO liquidations
                   (position_id, user_id, liquidation_price, quantity_liquidated,
                    loss_amount, insurance_fund_covered, created_at)
                 VALUES (:pid, :uid, :lpx, :qty, :loss, :ins, NOW())'
            );
            $liqStmt->bindValue(':pid',  $positionId,        PDO::PARAM_INT);
            $liqStmt->bindValue(':uid',  (int)$pos['user_id'], PDO::PARAM_INT);
            $liqStmt->bindValue(':lpx',  $liquidationPrice);
            $liqStmt->bindValue(':qty',  $qty);
            $liqStmt->bindValue(':loss', $lossAbs);
            $liqStmt->bindValue(':ins',  $insuranceCovered);
            $liqStmt->execute();

            // Mark position as liquidated
            $upd = $pdo->prepare(
                "UPDATE positions
                 SET status = 'liquidated', realized_pnl = :rpnl, unrealized_pnl = 0,
                     closed_at = NOW()
                 WHERE id = :pid"
            );
            $upd->bindValue(':rpnl', '-' . $lossAbs);
            $upd->bindValue(':pid',  $positionId, PDO::PARAM_INT);
            $upd->execute();

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Return positions where margin_level is critically low. */
    public function getPositionsNearLiquidation(float $marginThresholdPercent = 10.0): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT p.id, p.user_id, p.trading_pair_id, p.position_side, p.entry_price,
                    p.quantity, p.leverage, p.liquidation_price, p.margin_used,
                    p.unrealized_pnl, p.opened_at,
                    u.username, u.email, tp.symbol
             FROM positions p
             INNER JOIN users u ON u.id = p.user_id
             INNER JOIN trading_pairs tp ON tp.id = p.trading_pair_id
             LEFT  JOIN price_tickers pt ON pt.trading_pair_id = tp.id
             WHERE p.status = 'open'
               AND p.liquidation_price IS NOT NULL
               AND (
                 (p.position_side = 'long'  AND COALESCE(pt.last_price, p.entry_price) <= p.liquidation_price * 1.05)
                 OR
                 (p.position_side = 'short' AND COALESCE(pt.last_price, p.entry_price) >= p.liquidation_price * 0.95)
               )
             ORDER BY p.margin_used ASC
             LIMIT 100"
        );
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getAllOpenPositions(array $filters = []): array
    {
        $sql = "SELECT p.id, p.user_id, p.position_side, p.entry_price, p.quantity, p.leverage,
                       p.liquidation_price, p.margin_used, p.unrealized_pnl, p.realized_pnl,
                       p.status, p.opened_at,
                       u.username, u.email, tp.symbol,
                       COALESCE(pt.last_price, p.entry_price) AS current_price
                FROM positions p
                INNER JOIN users u  ON u.id  = p.user_id
                INNER JOIN trading_pairs tp ON tp.id = p.trading_pair_id
                LEFT  JOIN price_tickers pt ON pt.trading_pair_id = tp.id
                WHERE 1=1";

        $params = [];

        $status = (string)($filters['status'] ?? 'open');
        if ($status !== '') {
            $sql .= ' AND p.status = :status';
            $params['status'] = $status;
        }

        $symbol = trim((string)($filters['symbol'] ?? ''));
        if ($symbol !== '') {
            $sql .= ' AND tp.symbol LIKE :sym';
            $params['sym'] = '%' . $symbol . '%';
        }

        $sql .= ' ORDER BY p.id DESC LIMIT 500';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // FEE TIERS MANAGEMENT
    // =========================================================================

    public function getFeeTiers(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, tier_name, min_30d_volume, min_token_holding,
                    maker_fee_percent, taker_fee_percent,
                    withdrawal_fee_discount_percent, is_active, created_at
             FROM fee_tiers ORDER BY min_30d_volume ASC'
        );
        return $stmt->fetchAll() ?: [];
    }

    public function createFeeTier(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO fee_tiers
               (tier_name, min_30d_volume, min_token_holding,
                maker_fee_percent, taker_fee_percent,
                withdrawal_fee_discount_percent, is_active, created_at)
             VALUES (:name, :vol, :token, :maker, :taker, :wddisc, :active, NOW())'
        );
        $stmt->bindValue(':name',   (string)$data['tier_name']);
        $stmt->bindValue(':vol',    (string)$data['min_30d_volume']);
        $stmt->bindValue(':token',  (string)($data['min_token_holding'] ?? '0'));
        $stmt->bindValue(':maker',  (string)$data['maker_fee_percent']);
        $stmt->bindValue(':taker',  (string)$data['taker_fee_percent']);
        $stmt->bindValue(':wddisc', (string)($data['withdrawal_fee_discount_percent'] ?? '0'));
        $stmt->bindValue(':active', (int)($data['is_active'] ?? 1), PDO::PARAM_INT);
        $stmt->execute();
        return (int)Database::connection()->lastInsertId();
    }

    public function updateFeeTier(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE fee_tiers
             SET tier_name = :name, min_30d_volume = :vol, min_token_holding = :token,
                 maker_fee_percent = :maker, taker_fee_percent = :taker,
                 withdrawal_fee_discount_percent = :wddisc, is_active = :active
             WHERE id = :id'
        );
        $stmt->bindValue(':name',   (string)$data['tier_name']);
        $stmt->bindValue(':vol',    (string)$data['min_30d_volume']);
        $stmt->bindValue(':token',  (string)($data['min_token_holding'] ?? '0'));
        $stmt->bindValue(':maker',  (string)$data['maker_fee_percent']);
        $stmt->bindValue(':taker',  (string)$data['taker_fee_percent']);
        $stmt->bindValue(':wddisc', (string)($data['withdrawal_fee_discount_percent'] ?? '0'));
        $stmt->bindValue(':active', (int)($data['is_active'] ?? 1), PDO::PARAM_INT);
        $stmt->bindValue(':id',     $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // =========================================================================
    // TRADING ENGINE ADMIN STATS
    // =========================================================================

    public function getEngineStats(): array
    {
        $pdo = Database::connection();

        return [
            'open_orders'      => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('open','partially_filled')")->fetchColumn(),
            'orders_today'     => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
            'trades_today'     => (int)$pdo->query("SELECT COUNT(*) FROM trades WHERE DATE(executed_at) = CURDATE()")->fetchColumn(),
            'volume_today_usd' => (string)($pdo->query("SELECT COALESCE(SUM(quote_amount),0) FROM trades WHERE DATE(executed_at) = CURDATE()")->fetchColumn() ?: '0'),
            'open_positions'   => (int)$pdo->query("SELECT COUNT(*) FROM positions WHERE status = 'open'")->fetchColumn(),
            'liq_candidates'   => (int)$pdo->query("SELECT COUNT(*) FROM positions WHERE status = 'open' AND liquidation_price IS NOT NULL")->fetchColumn(),
            'fee_revenue_today'=> (string)($pdo->query("SELECT COALESCE(SUM(amount),0) FROM fee_revenue_ledger WHERE DATE(created_at) = CURDATE() AND source = 'trading_fee'")->fetchColumn() ?: '0'),
            'insurance_fund'   => (string)($pdo->query("SELECT COALESCE(balance,0) FROM insurance_fund WHERE id = 1")->fetchColumn() ?: '0'),
        ];
    }

    public function getDailyVolumeChart(int $days = 30): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DATE(executed_at) AS day,
                    SUM(quote_amount) AS volume,
                    COUNT(*) AS trade_count
             FROM trades
             WHERE executed_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY DATE(executed_at)
             ORDER BY day ASC'
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getTopTradedPairs(int $limit = 10): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT tp.symbol, SUM(t.quote_amount) AS volume_24h, COUNT(*) AS trades_24h
             FROM trades t
             INNER JOIN trading_pairs tp ON tp.id = t.trading_pair_id
             WHERE t.executed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY t.trading_pair_id, tp.symbol
             ORDER BY volume_24h DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // CANDLESTICK / TICKER UPDATES
    // =========================================================================

    public function upsertCandlestick(
        int    $pairId,
        string $interval,
        string $price,
        string $quantity
    ): void {
        $openTime = $this->candlestickOpenTime($interval);

        $stmt = Database::connection()->prepare(
            'INSERT INTO candlesticks
               (trading_pair_id, interval_code, open_time, open_price, high_price, low_price,
                close_price, volume, quote_volume, trade_count)
             VALUES
               (:pid, :iv, :ot, :price, :price2, :price3, :price4, :vol, :qvol, 1)
             ON DUPLICATE KEY UPDATE
               high_price   = GREATEST(high_price, :hp),
               low_price    = LEAST(low_price, :lp),
               close_price  = :cp,
               volume       = volume + :v,
               quote_volume = quote_volume + :qv,
               trade_count  = trade_count + 1'
        );
        $quoteVolume = bcmul($price, $quantity, 18);
        $stmt->bindValue(':pid',    $pairId,   PDO::PARAM_INT);
        $stmt->bindValue(':iv',     $interval);
        $stmt->bindValue(':ot',     $openTime);
        $stmt->bindValue(':price',  $price);
        $stmt->bindValue(':price2', $price);
        $stmt->bindValue(':price3', $price);
        $stmt->bindValue(':price4', $price);
        $stmt->bindValue(':vol',    $quantity);
        $stmt->bindValue(':qvol',   $quoteVolume);
        $stmt->bindValue(':hp',     $price);
        $stmt->bindValue(':lp',     $price);
        $stmt->bindValue(':cp',     $price);
        $stmt->bindValue(':v',      $quantity);
        $stmt->bindValue(':qv',     $quoteVolume);
        $stmt->execute();
    }

    public function updatePriceTicker(int $pairId, string $lastPrice, string $tradeQuantity): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO price_tickers (trading_pair_id, last_price, volume_24h, updated_at)
             VALUES (:pid, :price, :vol, NOW())
             ON DUPLICATE KEY UPDATE
               last_price  = :price2,
               volume_24h  = volume_24h + :vol2,
               updated_at  = NOW()'
        );
        $stmt->bindValue(':pid',    $pairId,    PDO::PARAM_INT);
        $stmt->bindValue(':price',  $lastPrice);
        $stmt->bindValue(':vol',    $tradeQuantity);
        $stmt->bindValue(':price2', $lastPrice);
        $stmt->bindValue(':vol2',   $tradeQuantity);
        $stmt->execute();
    }

    // =========================================================================
    // USER-FACING QUERIES
    // =========================================================================

    public function getUserOpenOrders(int $userId, ?int $pairId = null, int $limit = 100): array
    {
        $sql = "SELECT o.id, o.order_uuid, o.side, o.price, o.stop_price,
                       o.quantity, o.filled_quantity, o.remaining_quantity,
                       o.average_fill_price, o.status, o.time_in_force, o.leverage,
                       o.created_at,
                       tp.symbol AS pair_symbol, tp.market_type,
                       ot.name AS order_type
                FROM orders o
                INNER JOIN trading_pairs tp ON tp.id = o.trading_pair_id
                INNER JOIN order_types ot   ON ot.id = o.order_type_id
                WHERE o.user_id = :uid AND o.status IN ('open','partially_filled')";

        $params = ['uid' => $userId];
        if ($pairId !== null) {
            $sql .= ' AND o.trading_pair_id = :pid';
            $params['pid'] = $pairId;
        }
        $sql .= ' ORDER BY o.id DESC LIMIT :lim';
        $params['lim'] = max(1, $limit);

        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $k => $v) {
            if ($k === 'lim' || $k === 'uid' || $k === 'pid') {
                $stmt->bindValue(':' . $k, $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':' . $k, $v);
            }
        }
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getUserPositions(int $userId, string $status = 'open'): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.id, p.position_side AS side, p.entry_price, p.quantity, p.leverage,
                    p.liquidation_price, p.margin_used, p.unrealized_pnl, p.realized_pnl,
                    p.status, p.opened_at, p.closed_at,
                    tp.symbol AS pair_symbol, tp.market_type,
                    COALESCE(pt.last_price, p.entry_price) AS current_price
             FROM positions p
             INNER JOIN trading_pairs tp ON tp.id = p.trading_pair_id
             LEFT  JOIN price_tickers pt ON pt.trading_pair_id = tp.id
             WHERE p.user_id = :uid AND p.status = :status
             ORDER BY p.id DESC LIMIT 200'
        );
        $stmt->bindValue(':uid',    $userId, PDO::PARAM_INT);
        $stmt->bindValue(':status', $status);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function findOrderForUser(int $orderId, int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT o.*, tp.symbol AS pair_symbol, ot.name AS order_type_name
             FROM orders o
             INNER JOIN trading_pairs tp ON tp.id = o.trading_pair_id
             INNER JOIN order_types ot   ON ot.id = o.order_type_id
             WHERE o.id = :oid AND o.user_id = :uid LIMIT 1'
        );
        $stmt->bindValue(':oid', $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId,  PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function updateOrderFill(\PDO $pdo, int $orderId, string $fillQty, string $fillPrice): void
    {
        $stmt = $pdo->prepare(
            'SELECT filled_quantity, remaining_quantity, quantity, average_fill_price FROM orders WHERE id = :id FOR UPDATE'
        );
        $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
        $stmt->execute();
        $order = $stmt->fetch();
        if ($order === false) {
            return;
        }

        $newFilled    = bcadd((string)$order['filled_quantity'], $fillQty, 18);
        $newRemaining = bcsub((string)$order['quantity'], $newFilled, 18);
        if (bccomp($newRemaining, '0', 18) < 0) {
            $newRemaining = '0';
        }

        // Weighted average fill price
        $prevAvg    = (string)($order['average_fill_price'] ?? $fillPrice);
        $prevFilled = (string)$order['filled_quantity'];
        if (bccomp($newFilled, '0', 18) > 0) {
            $numerator = bcadd(
                bcmul($prevAvg, $prevFilled, 18),
                bcmul($fillPrice, $fillQty, 18),
                18
            );
            $avgPrice = bcdiv($numerator, $newFilled, 18);
        } else {
            $avgPrice = $fillPrice;
        }

        $status = bccomp($newRemaining, '0', 18) <= 0 ? 'filled' : 'partially_filled';
        $filledAt = $status === 'filled' ? ', updated_at = NOW()' : '';

        $upd = $pdo->prepare(
            "UPDATE orders
             SET filled_quantity = :fq, remaining_quantity = :rq,
                 average_fill_price = :avg, status = :status, updated_at = NOW()
             WHERE id = :id"
        );
        $upd->bindValue(':fq',     $newFilled);
        $upd->bindValue(':rq',     $newRemaining);
        $upd->bindValue(':avg',    $avgPrice);
        $upd->bindValue(':status', $status);
        $upd->bindValue(':id',     $orderId, PDO::PARAM_INT);
        $upd->execute();

        $event = $status === 'filled' ? 'filled' : 'partially_filled';
        $this->insertOrderEventInTransaction($pdo, $orderId, $event, ['fill_qty' => $fillQty, 'fill_price' => $fillPrice]);
    }

    /**
     * Release locked balance and debit the actual cost in one wallet update.
     * locked_balance -= lockAmt; available_balance -= (actualCost - lockAmt) if lockAmt > actualCost else 0.
     * In practice: reduce locked_balance by lockAmt, then debit actualCost from available.
     */
    private function releaseLockedAndDebit(\PDO $pdo, int $walletId, string $lockAmt, string $actualCost, int $tradeId): void
    {
        $stmt = $pdo->prepare(
            'SELECT available_balance, locked_balance FROM wallets WHERE id = :id FOR UPDATE'
        );
        $stmt->bindValue(':id', $walletId, PDO::PARAM_INT);
        $stmt->execute();
        $w = $stmt->fetch();
        if ($w === false) {
            return;
        }

        $locked    = (string)$w['locked_balance'];
        $available = (string)$w['available_balance'];

        // How much locked to release
        $releaseFromLocked = bccomp($lockAmt, $locked, 18) > 0 ? $locked : $lockAmt;
        $remainingCost     = bcsub($actualCost, $releaseFromLocked, 18);

        $newLocked    = bcsub($locked, $releaseFromLocked, 18);
        $newAvailable = $available;
        if (bccomp($remainingCost, '0', 18) > 0) {
            $newAvailable = bcsub($available, $remainingCost, 18);
            if (bccomp($newAvailable, '0', 18) < 0) {
                $newAvailable = '0';
            }
        }

        $upd = $pdo->prepare(
            'UPDATE wallets SET available_balance = :avail, locked_balance = :locked, updated_at = NOW() WHERE id = :id'
        );
        $upd->bindValue(':avail',  $newAvailable);
        $upd->bindValue(':locked', $newLocked);
        $upd->bindValue(':id',     $walletId, PDO::PARAM_INT);
        $upd->execute();

        // Write ledger debit
        $newTotal = bcadd($newAvailable, $newLocked, 18);
        $this->writeLedgerInTransaction($pdo, $walletId, 'trade', $tradeId, 'debit', $actualCost, $newTotal, 'Trade settlement debit');
    }

    private function creditFromLocked(\PDO $pdo, int $walletId, string $amount, int $tradeId): void
    {
        if (bccomp($amount, '0', 18) <= 0) {
            return;
        }
        $stmt = $pdo->prepare(
            'SELECT available_balance, locked_balance FROM wallets WHERE id = :id FOR UPDATE'
        );
        $stmt->bindValue(':id', $walletId, PDO::PARAM_INT);
        $stmt->execute();
        $w = $stmt->fetch();
        if ($w === false) {
            return;
        }

        $newAvailable = bcadd((string)$w['available_balance'], $amount, 18);
        $upd = $pdo->prepare(
            'UPDATE wallets SET available_balance = :avail, updated_at = NOW() WHERE id = :id'
        );
        $upd->bindValue(':avail', $newAvailable);
        $upd->bindValue(':id',    $walletId, PDO::PARAM_INT);
        $upd->execute();

        $newTotal = bcadd($newAvailable, (string)$w['locked_balance'], 18);
        $this->writeLedgerInTransaction($pdo, $walletId, 'trade', $tradeId, 'credit', $amount, $newTotal, 'Trade settlement credit');
    }

    private function writeLedgerInTransaction(
        \PDO   $pdo,
        int    $walletId,
        string $referenceType,
        int    $referenceId,
        string $direction,
        string $amount,
        string $balanceAfter,
        string $notes = ''
    ): void {
        $stmt = $pdo->prepare(
            'INSERT INTO ledger_entries
               (wallet_id, reference_type, reference_id, direction, amount, balance_after, notes, created_at)
             VALUES (:wid, :rtype, :rid, :dir, :amt, :bal, :notes, NOW())'
        );
        $stmt->bindValue(':wid',   $walletId,     PDO::PARAM_INT);
        $stmt->bindValue(':rtype', $referenceType);
        $stmt->bindValue(':rid',   $referenceId,  PDO::PARAM_INT);
        $stmt->bindValue(':dir',   $direction);
        $stmt->bindValue(':amt',   $amount);
        $stmt->bindValue(':bal',   $balanceAfter);
        $stmt->bindValue(':notes', $notes ?: null);
        $stmt->execute();
    }

    private function insertOrderEvent(int $orderId, string $eventType, array $details = []): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO order_events (order_id, event_type, details, created_at)
             VALUES (:oid, :ev, :det, NOW())'
        );
        $stmt->bindValue(':oid', $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':ev',  $eventType);
        $stmt->bindValue(':det', $details !== [] ? json_encode($details) : null);
        $stmt->execute();
    }

    private function insertOrderEventInTransaction(\PDO $pdo, int $orderId, string $eventType, array $details = []): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO order_events (order_id, event_type, details, created_at)
             VALUES (:oid, :ev, :det, NOW())'
        );
        $stmt->bindValue(':oid', $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':ev',  $eventType);
        $stmt->bindValue(':det', $details !== [] ? json_encode($details) : null);
        $stmt->execute();
    }

    private function addRealizedPnlToMarginAccount(\PDO $pdo, int $userId, string $pnl): void
    {
        // Best-effort: doesn't throw if margin_accounts row missing
        $pdo->prepare(
            'UPDATE margin_accounts SET total_collateral_usd = total_collateral_usd + :pnl, updated_at = NOW()
             WHERE user_id = :uid'
        )->execute([':pnl' => $pnl, ':uid' => $userId]);
    }

    private function generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function candlestickOpenTime(string $interval): string
    {
        $now = time();
        return match ($interval) {
            '1m'  => date('Y-m-d H:i:00', $now - ($now % 60)),
            '5m'  => date('Y-m-d H:i:00', $now - ($now % 300)),
            '15m' => date('Y-m-d H:i:00', $now - ($now % 900)),
            '30m' => date('Y-m-d H:i:00', $now - ($now % 1800)),
            '1h'  => date('Y-m-d H:00:00', $now - ($now % 3600)),
            '4h'  => date('Y-m-d H:00:00', $now - ($now % 14400)),
            '1d'  => date('Y-m-d 00:00:00', $now),
            '1w'  => date('Y-m-d 00:00:00', $now - (date('N', $now) - 1) * 86400),
            '1M'  => date('Y-m-01 00:00:00', $now),
            default => date('Y-m-d H:00:00', $now - ($now % 3600)),
        };
    }
}
