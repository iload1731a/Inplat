<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class UserProfileRepository
{
    public function findByUserId(int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.id, u.uuid, u.username, u.email, u.phone, u.first_name, u.last_name,
                    u.country_code, u.timezone, u.preferred_language, u.account_type,
                    u.status, u.kyc_status, u.kyc_level, u.referral_code, u.created_at,
                    p.date_of_birth, p.gender, p.address_line1, p.address_line2, p.city,
                    p.state_province, p.postal_code, p.country_code AS profile_country,
                    p.occupation, p.source_of_funds, p.annual_income_range, p.avatar_url,
                    p.company_name, p.tax_id, p.updated_at AS profile_updated_at
             FROM users u
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE u.id = :uid LIMIT 1'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function upsertProfile(int $userId, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO user_profiles
                (user_id, date_of_birth, gender, address_line1, address_line2, city,
                 state_province, postal_code, country_code, occupation, source_of_funds,
                 annual_income_range, company_name, tax_id, created_at, updated_at)
             VALUES
                (:uid, :dob, :gender, :addr1, :addr2, :city,
                 :state, :postal, :country, :occupation, :source_of_funds,
                 :income, :company, :tax_id, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                date_of_birth       = VALUES(date_of_birth),
                gender              = VALUES(gender),
                address_line1       = VALUES(address_line1),
                address_line2       = VALUES(address_line2),
                city                = VALUES(city),
                state_province      = VALUES(state_province),
                postal_code         = VALUES(postal_code),
                country_code        = VALUES(country_code),
                occupation          = VALUES(occupation),
                source_of_funds     = VALUES(source_of_funds),
                annual_income_range = VALUES(annual_income_range),
                company_name        = VALUES(company_name),
                tax_id              = VALUES(tax_id),
                updated_at          = NOW()'
        );
        $stmt->bindValue(':uid',            $userId,                       PDO::PARAM_INT);
        $stmt->bindValue(':dob',            $data['date_of_birth'] ?? null);
        $stmt->bindValue(':gender',         $data['gender']         ?? null);
        $stmt->bindValue(':addr1',          $data['address_line1']  ?? null);
        $stmt->bindValue(':addr2',          $data['address_line2']  ?? null);
        $stmt->bindValue(':city',           $data['city']           ?? null);
        $stmt->bindValue(':state',          $data['state_province'] ?? null);
        $stmt->bindValue(':postal',         $data['postal_code']    ?? null);
        $stmt->bindValue(':country',        $data['country_code']   ?? null);
        $stmt->bindValue(':occupation',     $data['occupation']     ?? null);
        $stmt->bindValue(':source_of_funds',$data['source_of_funds']?? null);
        $stmt->bindValue(':income',         $data['annual_income_range'] ?? null);
        $stmt->bindValue(':company',        $data['company_name']   ?? null);
        $stmt->bindValue(':tax_id',         $data['tax_id']         ?? null);
        $stmt->execute();
    }

    public function updateUserFields(int $userId, array $fields): void
    {
        $columnMap = [
            'first_name'         => 'first_name',
            'last_name'          => 'last_name',
            'phone'              => 'phone',
            'timezone'           => 'timezone',
            'preferred_language' => 'preferred_language',
        ];
        $sets = [];
        $params = [':uid' => $userId];
        foreach ($fields as $key => $val) {
            if (isset($columnMap[$key])) {
                $safeCol = $columnMap[$key];
                $placeholder = ':col_' . $safeCol;
                $sets[] = $safeCol . ' = ' . $placeholder;
                $params[$placeholder] = $val;
            }
        }
        if ($sets === []) {
            return;
        }
        $sql  = 'UPDATE users SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = :uid';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public function updateAvatar(int $userId, string $avatarUrl): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO user_profiles (user_id, avatar_url, created_at, updated_at)
             VALUES (:uid, :url, NOW(), NOW())
             ON DUPLICATE KEY UPDATE avatar_url = :url2, updated_at = NOW()'
        );
        $stmt->bindValue(':uid',  $userId,    PDO::PARAM_INT);
        $stmt->bindValue(':url',  $avatarUrl);
        $stmt->bindValue(':url2', $avatarUrl);
        $stmt->execute();
    }
}
