<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use PDO;
use RuntimeException;
use Throwable;

final class AdminManagementRepository
{
    public function findAdminById(int $adminId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT au.id, au.username, au.email, au.full_name, au.role_id, r.name AS role_name
             FROM admin_users au
             LEFT JOIN roles r ON r.id = au.role_id
             WHERE au.id = :id AND au.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->bindValue(':id', $adminId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function listActiveAdmins(): array
    {
        $stmt = Database::connection()->query(
            "SELECT au.id, COALESCE(NULLIF(au.full_name, ''), au.username) AS display_name, au.email, COALESCE(r.name, 'admin') AS role_name
             FROM admin_users au
             LEFT JOIN roles r ON r.id = au.role_id
             WHERE au.status = 'active' AND au.deleted_at IS NULL
             ORDER BY display_name ASC"
        );

        return $stmt->fetchAll() ?: [];
    }

    public function listUsers(array $filters = []): array
    {
        $sql = "SELECT u.id, u.uuid, u.username, u.email, u.status, u.kyc_status, u.kyc_level, u.account_type,
                       u.two_factor_enabled, u.country_code, u.preferred_language, u.last_login_at, u.created_at,
                       u.first_name, u.last_name,
                       COALESCE(SUM(w.available_balance), 0) AS total_available_balance,
                       COALESCE(SUM(w.locked_balance), 0) AS total_locked_balance
                FROM users u
                LEFT JOIN user_profiles up ON up.user_id = u.id
                LEFT JOIN wallets w ON w.user_id = u.id
                WHERE u.deleted_at IS NULL";
        $params = [];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (u.username LIKE :search OR u.email LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND u.status = :status';
            $params['status'] = $status;
        }

        $kycStatus = trim((string)($filters['kyc_status'] ?? ''));
        if ($kycStatus !== '') {
            $sql .= ' AND u.kyc_status = :kyc_status';
            $params['kyc_status'] = $kycStatus;
        }

        $sql .= ' GROUP BY u.id, u.uuid, u.username, u.email, u.status, u.kyc_status, u.kyc_level, u.account_type,
                         u.two_factor_enabled, u.country_code, u.preferred_language, u.last_login_at, u.created_at,
                         u.first_name, u.last_name
                  ORDER BY u.id DESC LIMIT 100';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public function getUserDetails(int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT u.*, up.date_of_birth, up.gender, up.address_line1, up.address_line2, up.city, up.state_province,
                    up.postal_code, up.country_code AS profile_country_code, up.occupation, up.source_of_funds,
                    up.annual_income_range, up.avatar_url, up.company_name, up.company_registration_no, up.tax_id,
                    COALESCE((SELECT COUNT(*) FROM kyc_documents kd WHERE kd.user_id = u.id), 0) AS kyc_documents_count,
                    COALESCE((SELECT COUNT(*) FROM support_tickets st WHERE st.user_id = u.id), 0) AS support_tickets_count,
                    COALESCE((SELECT COUNT(*) FROM notifications n WHERE n.user_id = u.id AND n.is_read = 0), 0) AS unread_notifications_count
             FROM users u
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE u.id = :id AND u.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function getUserWallets(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT w.id, c.code AS currency_code, c.name AS currency_name, w.wallet_type, w.available_balance,
                    w.locked_balance, w.total_deposited, w.total_withdrawn, w.is_frozen, w.freeze_reason
             FROM wallets w
             INNER JOIN currencies c ON c.id = w.currency_id
             WHERE w.user_id = :user_id
             ORDER BY c.code ASC, w.wallet_type ASC'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function getUserKycDocuments(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT kd.id, kd.document_type, kd.document_number, kd.file_url, kd.issue_country, kd.issue_date, kd.expiry_date,
                    kd.status, kd.review_notes, kd.reviewed_at, kd.created_at,
                    COALESCE(au.full_name, au.username) AS reviewed_by_name
             FROM kyc_documents kd
             LEFT JOIN admin_users au ON au.id = kd.reviewed_by
             WHERE kd.user_id = :user_id
             ORDER BY kd.id DESC'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function getUserLoginHistory(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, ip_address, user_agent, status, created_at
             FROM login_history
             WHERE user_id = :user_id
             ORDER BY id DESC
             LIMIT 15'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function getUserNotifications(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, type, title, message, channel, is_read, created_at
             FROM notifications
             WHERE user_id = :user_id
             ORDER BY id DESC
             LIMIT 15'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function updateUserProfile(int $userId, array $payload): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $before = $this->getUserDetails($userId);
            if ($before === null) {
                throw new RuntimeException('User not found.');
            }

            $userStmt = $pdo->prepare(
                'UPDATE users SET email = :email, phone = :phone, first_name = :first_name, last_name = :last_name,
                    country_code = :country_code, timezone = :timezone, preferred_language = :preferred_language,
                    account_type = :account_type, status = :status, kyc_status = :kyc_status, kyc_level = :kyc_level,
                    updated_at = NOW()
                 WHERE id = :id'
            );
            $userStmt->execute([
                'email' => $payload['email'],
                'phone' => $payload['phone'],
                'first_name' => $payload['first_name'],
                'last_name' => $payload['last_name'],
                'country_code' => $payload['country_code'],
                'timezone' => $payload['timezone'],
                'preferred_language' => $payload['preferred_language'],
                'account_type' => $payload['account_type'],
                'status' => $payload['status'],
                'kyc_status' => $payload['kyc_status'],
                'kyc_level' => $payload['kyc_level'],
                'id' => $userId,
            ]);

            $profileStmt = $pdo->prepare(
                'INSERT INTO user_profiles (user_id, date_of_birth, gender, address_line1, address_line2, city, state_province,
                    postal_code, country_code, occupation, source_of_funds, annual_income_range, company_name,
                    company_registration_no, tax_id, created_at, updated_at)
                 VALUES (:user_id, :date_of_birth, :gender, :address_line1, :address_line2, :city, :state_province,
                    :postal_code, :profile_country_code, :occupation, :source_of_funds, :annual_income_range, :company_name,
                    :company_registration_no, :tax_id, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    date_of_birth = VALUES(date_of_birth),
                    gender = VALUES(gender),
                    address_line1 = VALUES(address_line1),
                    address_line2 = VALUES(address_line2),
                    city = VALUES(city),
                    state_province = VALUES(state_province),
                    postal_code = VALUES(postal_code),
                    country_code = VALUES(country_code),
                    occupation = VALUES(occupation),
                    source_of_funds = VALUES(source_of_funds),
                    annual_income_range = VALUES(annual_income_range),
                    company_name = VALUES(company_name),
                    company_registration_no = VALUES(company_registration_no),
                    tax_id = VALUES(tax_id),
                    updated_at = NOW()'
            );
            $profileStmt->execute([
                'user_id' => $userId,
                'date_of_birth' => $payload['date_of_birth'] !== '' ? $payload['date_of_birth'] : null,
                'gender' => $payload['gender'] !== '' ? $payload['gender'] : null,
                'address_line1' => $payload['address_line1'],
                'address_line2' => $payload['address_line2'],
                'city' => $payload['city'],
                'state_province' => $payload['state_province'],
                'postal_code' => $payload['postal_code'],
                'profile_country_code' => $payload['profile_country_code'],
                'occupation' => $payload['occupation'],
                'source_of_funds' => $payload['source_of_funds'],
                'annual_income_range' => $payload['annual_income_range'],
                'company_name' => $payload['company_name'],
                'company_registration_no' => $payload['company_registration_no'],
                'tax_id' => $payload['tax_id'],
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function reviewKycDocument(int $documentId, int $adminId, string $status, string $notes): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT user_id FROM kyc_documents WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $documentId, PDO::PARAM_INT);
        $stmt->execute();
        $userId = (int)($stmt->fetchColumn() ?: 0);
        if ($userId <= 0) {
            throw new RuntimeException('KYC document not found.');
        }

        $update = $pdo->prepare(
            'UPDATE kyc_documents
             SET status = :status, review_notes = :review_notes, reviewed_by = :reviewed_by, reviewed_at = NOW(), updated_at = NOW()
             WHERE id = :id'
        );
        $update->execute([
            'status' => $status,
            'review_notes' => $notes !== '' ? $notes : null,
            'reviewed_by' => $adminId,
            'id' => $documentId,
        ]);

        return $userId;
    }

    public function refreshUserKycSummary(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
                COUNT(*) AS total_count
             FROM kyc_documents
             WHERE user_id = :user_id"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $summary = $stmt->fetch() ?: [];

        $pending = (int)($summary['pending_count'] ?? 0);
        $approved = (int)($summary['approved_count'] ?? 0);
        $rejected = (int)($summary['rejected_count'] ?? 0);
        $total = (int)($summary['total_count'] ?? 0);

        $kycStatus = 'unverified';
        $kycLevel = 0;
        if ($pending > 0) {
            $kycStatus = 'pending';
        } elseif ($total > 0 && $approved === $total) {
            $kycStatus = 'approved';
            $kycLevel = max(1, $approved);
        } elseif ($rejected > 0) {
            $kycStatus = 'rejected';
        }

        $update = Database::connection()->prepare('UPDATE users SET kyc_status = :kyc_status, kyc_level = :kyc_level, updated_at = NOW() WHERE id = :id');
        $update->execute([
            'kyc_status' => $kycStatus,
            'kyc_level' => $kycLevel,
            'id' => $userId,
        ]);

        return ['kyc_status' => $kycStatus, 'kyc_level' => $kycLevel];
    }

    public function adjustWalletBalance(int $walletId, int $adminId, string $direction, string $amount, string $reason): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT w.id, w.user_id, w.currency_id, w.wallet_type, w.available_balance, w.locked_balance,
                        c.code AS currency_code, u.username
                 FROM wallets w
                 INNER JOIN currencies c ON c.id = w.currency_id
                 INNER JOIN users u ON u.id = w.user_id
                 WHERE w.id = :id
                 FOR UPDATE'
            );
            $stmt->bindValue(':id', $walletId, PDO::PARAM_INT);
            $stmt->execute();
            $wallet = $stmt->fetch();
            if ($wallet === false) {
                throw new RuntimeException('Wallet not found.');
            }

            $delta = (float)$amount;
            $currentBalance = (float)$wallet['available_balance'];
            $newBalance = $direction === 'credit' ? $currentBalance + $delta : $currentBalance - $delta;
            if ($newBalance < -0.000000001) {
                throw new RuntimeException('Insufficient wallet balance for this adjustment.');
            }

            $update = $pdo->prepare('UPDATE wallets SET available_balance = :available_balance, updated_at = NOW() WHERE id = :id');
            $update->execute([
                'available_balance' => $newBalance,
                'id' => $walletId,
            ]);

            $ledger = $pdo->prepare(
                'INSERT INTO ledger_entries (wallet_id, reference_type, reference_id, direction, amount, balance_after, notes, created_by_admin, created_at)
                 VALUES (:wallet_id, :reference_type, NULL, :direction, :amount, :balance_after, :notes, :created_by_admin, NOW())'
            );
            $ledger->execute([
                'wallet_id' => $walletId,
                'reference_type' => 'adjustment',
                'direction' => $direction,
                'amount' => $delta,
                'balance_after' => $newBalance,
                'notes' => $reason,
                'created_by_admin' => $adminId,
            ]);

            $pdo->commit();

            return [
                'wallet_id' => (int)$wallet['id'],
                'user_id' => (int)$wallet['user_id'],
                'username' => (string)$wallet['username'],
                'currency_code' => (string)$wallet['currency_code'],
                'wallet_type' => (string)$wallet['wallet_type'],
                'previous_balance' => $currentBalance,
                'new_balance' => $newBalance,
                'amount' => $delta,
                'direction' => $direction,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function listDeposits(array $filters = []): array
    {
        $sql = "SELECT d.id, d.user_id, u.username, c.code AS currency_code, d.amount, d.tx_hash, d.status, d.confirmations,
                       d.flagged_reason, d.created_at, d.credited_at,
                       COALESCE(au.full_name, au.username) AS reviewed_by_name
                FROM deposits d
                INNER JOIN users u ON u.id = d.user_id
                INNER JOIN currencies c ON c.id = d.currency_id
                LEFT JOIN admin_users au ON au.id = d.reviewed_by
                WHERE 1 = 1";
        $params = [];

        $status = trim((string)($filters['deposit_status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND d.status = :deposit_status';
            $params['deposit_status'] = $status;
        }

        $search = trim((string)($filters['deposit_search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (u.username LIKE :deposit_search OR d.tx_hash LIKE :deposit_search)';
            $params['deposit_search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY d.id DESC LIMIT 50';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public function listWithdrawals(array $filters = []): array
    {
        $sql = "SELECT w.id, w.user_id, u.username, c.code AS currency_code, w.amount, w.fee, w.destination_address,
                       w.tx_hash, w.status, w.requires_manual_review, w.rejection_reason, w.requested_at, w.processed_at,
                       COALESCE(au.full_name, au.username) AS reviewed_by_name
                FROM withdrawals w
                INNER JOIN users u ON u.id = w.user_id
                INNER JOIN currencies c ON c.id = w.currency_id
                LEFT JOIN admin_users au ON au.id = w.reviewed_by
                WHERE 1 = 1";
        $params = [];

        $status = trim((string)($filters['withdrawal_status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND w.status = :withdrawal_status';
            $params['withdrawal_status'] = $status;
        }

        $search = trim((string)($filters['withdrawal_search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (u.username LIKE :withdrawal_search OR w.destination_address LIKE :withdrawal_search OR w.tx_hash LIKE :withdrawal_search)';
            $params['withdrawal_search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY w.id DESC LIMIT 50';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public function reviewDeposit(int $depositId, int $adminId, string $status, string $notes): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT d.*, u.username, c.code AS currency_code, w.available_balance, w.total_deposited
                 FROM deposits d
                 INNER JOIN users u ON u.id = d.user_id
                 INNER JOIN currencies c ON c.id = d.currency_id
                 INNER JOIN wallets w ON w.id = d.wallet_id
                 WHERE d.id = :id
                 FOR UPDATE'
            );
            $stmt->bindValue(':id', $depositId, PDO::PARAM_INT);
            $stmt->execute();
            $deposit = $stmt->fetch();
            if ($deposit === false) {
                throw new RuntimeException('Deposit request not found.');
            }

            $creditedAt = null;
            if ($status === 'credited' && (string)$deposit['status'] !== 'credited') {
                $newBalance = (float)$deposit['available_balance'] + (float)$deposit['amount'];
                $newTotalDeposited = (float)$deposit['total_deposited'] + (float)$deposit['amount'];

                $walletUpdate = $pdo->prepare('UPDATE wallets SET available_balance = :available_balance, total_deposited = :total_deposited, updated_at = NOW() WHERE id = :id');
                $walletUpdate->execute([
                    'available_balance' => $newBalance,
                    'total_deposited' => $newTotalDeposited,
                    'id' => (int)$deposit['wallet_id'],
                ]);

                $ledger = $pdo->prepare(
                    'INSERT INTO ledger_entries (wallet_id, reference_type, reference_id, direction, amount, balance_after, notes, created_by_admin, created_at)
                     VALUES (:wallet_id, :reference_type, :reference_id, :direction, :amount, :balance_after, :notes, :created_by_admin, NOW())'
                );
                $ledger->execute([
                    'wallet_id' => (int)$deposit['wallet_id'],
                    'reference_type' => 'deposit',
                    'reference_id' => $depositId,
                    'direction' => 'credit',
                    'amount' => (float)$deposit['amount'],
                    'balance_after' => $newBalance,
                    'notes' => 'Admin deposit credit',
                    'created_by_admin' => $adminId,
                ]);
                $creditedAt = date('Y-m-d H:i:s');
            }

            $update = $pdo->prepare(
                'UPDATE deposits
                 SET status = :status,
                     flagged_reason = :flagged_reason,
                     reviewed_by = :reviewed_by,
                     credited_at = :credited_at
                 WHERE id = :id'
            );
            $update->execute([
                'status' => $status,
                'flagged_reason' => $status === 'flagged' ? $notes : null,
                'reviewed_by' => $adminId,
                'credited_at' => $creditedAt,
                'id' => $depositId,
            ]);

            $pdo->commit();

            return [
                'deposit_id' => $depositId,
                'user_id' => (int)$deposit['user_id'],
                'username' => (string)$deposit['username'],
                'currency_code' => (string)$deposit['currency_code'],
                'amount' => (float)$deposit['amount'],
                'status' => $status,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function reviewWithdrawal(int $withdrawalId, int $adminId, string $status, string $reason): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT w.*, u.username, c.code AS currency_code, wa.available_balance, wa.total_withdrawn
                 FROM withdrawals w
                 INNER JOIN users u ON u.id = w.user_id
                 INNER JOIN currencies c ON c.id = w.currency_id
                 INNER JOIN wallets wa ON wa.id = w.wallet_id
                 WHERE w.id = :id
                 FOR UPDATE'
            );
            $stmt->bindValue(':id', $withdrawalId, PDO::PARAM_INT);
            $stmt->execute();
            $withdrawal = $stmt->fetch();
            if ($withdrawal === false) {
                throw new RuntimeException('Withdrawal request not found.');
            }

            $processedAt = null;
            if ($status === 'completed' && (string)$withdrawal['status'] !== 'completed') {
                $totalDebit = (float)$withdrawal['amount'] + (float)$withdrawal['fee'];
                $newBalance = (float)$withdrawal['available_balance'] - $totalDebit;
                if ($newBalance < -0.000000001) {
                    throw new RuntimeException('Wallet balance is too low to complete this withdrawal.');
                }

                $newTotalWithdrawn = (float)$withdrawal['total_withdrawn'] + (float)$withdrawal['amount'];
                $walletUpdate = $pdo->prepare('UPDATE wallets SET available_balance = :available_balance, total_withdrawn = :total_withdrawn, updated_at = NOW() WHERE id = :id');
                $walletUpdate->execute([
                    'available_balance' => $newBalance,
                    'total_withdrawn' => $newTotalWithdrawn,
                    'id' => (int)$withdrawal['wallet_id'],
                ]);

                $ledger = $pdo->prepare(
                    'INSERT INTO ledger_entries (wallet_id, reference_type, reference_id, direction, amount, balance_after, notes, created_by_admin, created_at)
                     VALUES (:wallet_id, :reference_type, :reference_id, :direction, :amount, :balance_after, :notes, :created_by_admin, NOW())'
                );
                $ledger->execute([
                    'wallet_id' => (int)$withdrawal['wallet_id'],
                    'reference_type' => 'withdrawal',
                    'reference_id' => $withdrawalId,
                    'direction' => 'debit',
                    'amount' => $totalDebit,
                    'balance_after' => $newBalance,
                    'notes' => 'Admin withdrawal completion',
                    'created_by_admin' => $adminId,
                ]);
                $processedAt = date('Y-m-d H:i:s');
            }

            $update = $pdo->prepare(
                'UPDATE withdrawals
                 SET status = :status,
                     rejection_reason = :rejection_reason,
                     reviewed_by = :reviewed_by,
                     processed_at = :processed_at
                 WHERE id = :id'
            );
            $update->execute([
                'status' => $status,
                'rejection_reason' => in_array($status, ['rejected', 'cancelled'], true) ? $reason : null,
                'reviewed_by' => $adminId,
                'processed_at' => $processedAt,
                'id' => $withdrawalId,
            ]);

            $pdo->commit();

            return [
                'withdrawal_id' => $withdrawalId,
                'user_id' => (int)$withdrawal['user_id'],
                'username' => (string)$withdrawal['username'],
                'currency_code' => (string)$withdrawal['currency_code'],
                'amount' => (float)$withdrawal['amount'],
                'fee' => (float)$withdrawal['fee'],
                'status' => $status,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function resolveNotificationRecipients(string $audience, int $userId, string $statusFilter, string $kycFilter): array
    {
        $sql = 'SELECT id, username, email FROM users WHERE deleted_at IS NULL';
        $params = [];

        if ($audience === 'single') {
            $sql .= ' AND id = :user_id';
            $params['user_id'] = $userId;
        } else {
            if ($statusFilter !== '') {
                $sql .= ' AND status = :status';
                $params['status'] = $statusFilter;
            }
            if ($kycFilter !== '') {
                $sql .= ' AND kyc_status = :kyc_status';
                $params['kyc_status'] = $kycFilter;
            }
        }

        $sql .= ' ORDER BY id DESC LIMIT 250';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public function createNotifications(array $recipients, string $type, string $title, string $message, string $channel): int
    {
        if ($recipients === []) {
            return 0;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO notifications (user_id, type, title, message, channel, is_read, created_at)
             VALUES (:user_id, :type, :title, :message, :channel, 0, NOW())'
        );

        foreach ($recipients as $recipient) {
            $stmt->execute([
                'user_id' => (int)$recipient['id'],
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'channel' => $channel,
            ]);
        }

        return count($recipients);
    }

    public function listRecentNotifications(): array
    {
        $stmt = Database::connection()->query(
            'SELECT n.id, n.user_id, u.username, n.type, n.title, n.channel, n.is_read, n.created_at
             FROM notifications n
             INNER JOIN users u ON u.id = n.user_id
             ORDER BY n.id DESC
             LIMIT 30'
        );

        return $stmt->fetchAll() ?: [];
    }

    public function listEmailTemplates(): array
    {
        $stmt = Database::connection()->query(
            'SELECT et.id, et.template_key, et.subject, et.body_html, et.is_active, et.updated_at,
                    COALESCE(au.full_name, au.username) AS updated_by_name
             FROM email_templates et
             LEFT JOIN admin_users au ON au.id = et.updated_by
             ORDER BY et.template_key ASC'
        );

        return $stmt->fetchAll() ?: [];
    }

    public function saveEmailTemplate(array $payload, int $adminId): int
    {
        $pdo = Database::connection();
        $templateId = (int)($payload['template_id'] ?? 0);

        if ($templateId > 0) {
            $stmt = $pdo->prepare(
                'UPDATE email_templates
                 SET subject = :subject, body_html = :body_html, is_active = :is_active, updated_by = :updated_by, updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                'subject' => $payload['subject'],
                'body_html' => $payload['body_html'],
                'is_active' => $payload['is_active'],
                'updated_by' => $adminId,
                'id' => $templateId,
            ]);

            return $templateId;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO email_templates (template_key, subject, body_html, is_active, updated_by, updated_at)
             VALUES (:template_key, :subject, :body_html, :is_active, :updated_by, NOW())'
        );
        $stmt->execute([
            'template_key' => $payload['template_key'],
            'subject' => $payload['subject'],
            'body_html' => $payload['body_html'],
            'is_active' => $payload['is_active'],
            'updated_by' => $adminId,
        ]);

        return (int)$pdo->lastInsertId();
    }

    public function listSupportTickets(array $filters = []): array
    {
        $sql = "SELECT st.id, st.ticket_number, st.user_id, u.username, st.subject, st.category, st.priority, st.status,
                       st.created_at, st.updated_at, st.closed_at,
                       COALESCE(au.full_name, au.username) AS assigned_to_name,
                       (SELECT COUNT(*) FROM ticket_messages tm WHERE tm.ticket_id = st.id) AS message_count
                FROM support_tickets st
                INNER JOIN users u ON u.id = st.user_id
                LEFT JOIN admin_users au ON au.id = st.assigned_to
                WHERE 1 = 1";
        $params = [];

        $status = trim((string)($filters['ticket_status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND st.status = :ticket_status';
            $params['ticket_status'] = $status;
        }

        $priority = trim((string)($filters['ticket_priority'] ?? ''));
        if ($priority !== '') {
            $sql .= ' AND st.priority = :ticket_priority';
            $params['ticket_priority'] = $priority;
        }

        $search = trim((string)($filters['ticket_search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (st.ticket_number LIKE :ticket_search OR st.subject LIKE :ticket_search OR u.username LIKE :ticket_search)';
            $params['ticket_search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY st.id DESC LIMIT 60';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public function getSupportTicket(int $ticketId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT st.*, u.username, u.email,
                    COALESCE(au.full_name, au.username) AS assigned_to_name
             FROM support_tickets st
             INNER JOIN users u ON u.id = st.user_id
             LEFT JOIN admin_users au ON au.id = st.assigned_to
             WHERE st.id = :id
             LIMIT 1"
        );
        $stmt->bindValue(':id', $ticketId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function getSupportMessages(int $ticketId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT tm.id, tm.sender_type, tm.sender_id, tm.message, tm.attachment_url, tm.created_at
             FROM ticket_messages tm
             WHERE tm.ticket_id = :ticket_id
             ORDER BY tm.id ASC'
        );
        $stmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function updateSupportTicket(int $ticketId, int $assignedTo, string $status): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE support_tickets
             SET assigned_to = :assigned_to,
                 status = :status,
                 closed_at = CASE WHEN :status IN (\'resolved\', \'closed\') THEN NOW() ELSE NULL END,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'assigned_to' => $assignedTo > 0 ? $assignedTo : null,
            'status' => $status,
            'id' => $ticketId,
        ]);
    }

    public function replySupportTicket(int $ticketId, int $adminId, string $message, string $status): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $insert = $pdo->prepare(
                'INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message, attachment_url, created_at)
                 VALUES (:ticket_id, :sender_type, :sender_id, :message, NULL, NOW())'
            );
            $insert->execute([
                'ticket_id' => $ticketId,
                'sender_type' => 'admin',
                'sender_id' => $adminId,
                'message' => $message,
            ]);

            $update = $pdo->prepare(
                'UPDATE support_tickets
                 SET assigned_to = :assigned_to,
                     status = :status,
                     closed_at = CASE WHEN :status IN (\'resolved\', \'closed\') THEN NOW() ELSE NULL END,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $update->execute([
                'assigned_to' => $adminId,
                'status' => $status,
                'id' => $ticketId,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function listSettingCategories(): array
    {
        $stmt = Database::connection()->query('SELECT DISTINCT category FROM system_settings ORDER BY category ASC');
        $rows = $stmt->fetchAll() ?: [];

        return array_map(static fn (array $row): string => (string)$row['category'], $rows);
    }

    public function listSystemSettings(string $category = ''): array
    {
        $sql = 'SELECT id, setting_key, setting_value, value_type, category, description, is_public, updated_at FROM system_settings';
        $params = [];
        if ($category !== '') {
            $sql .= ' WHERE category = :category';
            $params['category'] = $category;
        }
        $sql .= ' ORDER BY category ASC, setting_key ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public function updateSystemSetting(int $settingId, int $adminId, ?string $value): array
    {
        $pdo = Database::connection();
        $beforeStmt = $pdo->prepare('SELECT id, setting_key, setting_value, value_type, category, description, is_public FROM system_settings WHERE id = :id LIMIT 1');
        $beforeStmt->bindValue(':id', $settingId, PDO::PARAM_INT);
        $beforeStmt->execute();
        $before = $beforeStmt->fetch();
        if ($before === false) {
            throw new RuntimeException('Setting not found.');
        }

        $stmt = $pdo->prepare('UPDATE system_settings SET setting_value = :setting_value, updated_by = :updated_by, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'setting_value' => $value,
            'updated_by' => $adminId,
            'id' => $settingId,
        ]);

        return $before;
    }

    public function logAdminAction(int $adminId, string $action, string $entityType, string $entityId, ?array $oldValues, ?array $newValues, string $ipAddress): void
    {
        $oldJson = $this->encodeJson($oldValues);
        $newJson = $this->encodeJson($newValues);

        $activity = Database::connection()->prepare(
            'INSERT INTO admin_activity_logs (admin_id, action, entity_type, entity_id, old_values, new_values, ip_address, created_at)
             VALUES (:admin_id, :action, :entity_type, :entity_id, :old_values, :new_values, :ip_address, NOW())'
        );
        $activity->execute([
            'admin_id' => $adminId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldJson,
            'new_values' => $newJson,
            'ip_address' => $ipAddress,
        ]);

        $audit = Database::connection()->prepare(
            'INSERT INTO audit_logs (actor_type, actor_id, event, description, metadata, ip_address, created_at)
             VALUES (:actor_type, :actor_id, :event, :description, :metadata, :ip_address, NOW())'
        );
        $audit->execute([
            'actor_type' => 'admin',
            'actor_id' => $adminId,
            'event' => $action,
            'description' => $entityType . ' #' . $entityId,
            'metadata' => $newJson,
            'ip_address' => $ipAddress,
        ]);
    }

    private function encodeJson(?array $values): ?string
    {
        if ($values === null) {
            return null;
        }

        $json = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : null;
    }
}
