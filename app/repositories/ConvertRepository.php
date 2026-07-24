<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class ConvertRepository
{
    private function sanitizePositiveInt(int $value): int
    {
        return max(1, min(200, $value));
    }

    public function getAvailableCurrencies(): array
    {
        return Database::connection()
            ->query("SELECT id, code, name, currency_type FROM currencies WHERE is_active = 1 ORDER BY code ASC")
            ->fetchAll() ?: [];
    }

    public function getUserRecentQuotes(int $userId, int $limit = 10): array
    {
        $safeLimit = $this->sanitizePositiveInt($limit);
        $stmt = Database::connection()->prepare(
            "SELECT cq.id, cf.code AS from_currency, ct.code AS to_currency,
                    cq.from_amount, cq.to_amount, cq.quoted_rate, cq.spread_percent,
                    cq.status, cq.expires_at, cq.created_at
             FROM convert_quotes cq
             INNER JOIN currencies cf ON cf.id = cq.from_currency_id
             INNER JOIN currencies ct ON ct.id = cq.to_currency_id
             WHERE cq.user_id = :user_id
             ORDER BY cq.id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $safeLimit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function getUserConvertTransactions(int $userId, int $limit = 20): array
    {
        $safeLimit = $this->sanitizePositiveInt($limit);
        $stmt = Database::connection()->prepare(
            "SELECT ct.id, cf.code AS from_currency, cto.code AS to_currency,
                    ct.from_amount, ct.to_amount, ct.executed_rate, ct.fee_amount,
                    ct.created_at
             FROM convert_transactions ct
             INNER JOIN convert_quotes cq ON cq.id = ct.convert_quote_id
             INNER JOIN currencies cf ON cf.id = cq.from_currency_id
             INNER JOIN currencies cto ON cto.id = cq.to_currency_id
             WHERE ct.user_id = :user_id
             ORDER BY ct.id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $safeLimit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function getUserConvertSummary(int $userId): array
    {
        $pdo = Database::connection();

        $totalStmt = $pdo->prepare('SELECT COUNT(*) FROM convert_transactions WHERE user_id = :uid');
        $totalStmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $totalStmt->execute();

        $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM convert_quotes WHERE user_id = :uid AND status = 'pending'");
        $pendingStmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $pendingStmt->execute();

        $totalFeesStmt = $pdo->prepare('SELECT COALESCE(SUM(fee_amount), 0) FROM convert_transactions WHERE user_id = :uid');
        $totalFeesStmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $totalFeesStmt->execute();

        $currencyPairsStmt = $pdo->query('SELECT COUNT(*) FROM currencies WHERE is_active = 1');

        return [
            'total_conversions' => (int)$totalStmt->fetchColumn(),
            'pending_quotes' => (int)$pendingStmt->fetchColumn(),
            'total_fees_paid' => (float)$totalFeesStmt->fetchColumn(),
            'available_currencies' => (int)$currencyPairsStmt->fetchColumn(),
        ];
    }
}
