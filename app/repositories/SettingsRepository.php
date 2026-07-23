<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use PDO;

/**
 * Central repository for all platform settings:
 * general, company, branding, themes, localization, languages,
 * SMTP, SMS, API integrations, trading config, wallet config,
 * security config, backup, cache, and system information.
 */
final class SettingsRepository
{
    // -----------------------------------------------------------------------
    // System Settings (key-value store)
    // -----------------------------------------------------------------------

    /** Return all settings, optionally filtered by one or more categories. */
    public function getAll(array $categories = []): array
    {
        if ($categories === []) {
            $stmt = Database::connection()->query(
                'SELECT id, setting_key, setting_value, value_type, category, description, is_public, updated_at
                 FROM system_settings ORDER BY category ASC, setting_key ASC'
            );
            return $stmt->fetchAll() ?: [];
        }

        $placeholders = implode(',', array_fill(0, count($categories), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT id, setting_key, setting_value, value_type, category, description, is_public, updated_at
             FROM system_settings WHERE category IN ($placeholders) ORDER BY setting_key ASC"
        );
        $stmt->execute(array_values($categories));
        return $stmt->fetchAll() ?: [];
    }

    /** Return settings as associative map [key => value]. */
    public function getMap(array $categories = []): array
    {
        $rows = $this->getAll($categories);
        $map  = [];
        foreach ($rows as $row) {
            $map[(string)$row['setting_key']] = (string)($row['setting_value'] ?? '');
        }
        return $map;
    }

    /** Upsert a batch of key=>value pairs under a given category. */
    public function saveGroup(string $category, array $keyValues, int $adminId): void
    {
        $pdo = Database::connection();
        foreach ($keyValues as $key => $value) {
            $stmt = $pdo->prepare(
                "INSERT INTO system_settings (setting_key, setting_value, value_type, category, description, is_public, updated_by, updated_at)
                 VALUES (:key, :value, 'string', :cat, '', 0, :admin, NOW())
                 ON DUPLICATE KEY UPDATE setting_value = :value2, updated_by = :admin2, updated_at = NOW()"
            );
            $stmt->execute([
                ':key'    => (string)$key,
                ':value'  => $value !== null ? (string)$value : null,
                ':cat'    => $category,
                ':admin'  => $adminId,
                ':value2' => $value !== null ? (string)$value : null,
                ':admin2' => $adminId,
            ]);
        }
    }

    /** Single setting upsert. */
    public function upsert(string $key, ?string $value, string $type, string $category, int $adminId): void
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO system_settings (setting_key, setting_value, value_type, category, updated_by, updated_at)
             VALUES (:k, :v, :t, :cat, :a, NOW())
             ON DUPLICATE KEY UPDATE setting_value = :v2, value_type = :t2, updated_by = :a2, updated_at = NOW()"
        );
        $stmt->execute([
            ':k'   => $key,
            ':v'   => $value,
            ':t'   => $type,
            ':cat' => $category,
            ':a'   => $adminId,
            ':v2'  => $value,
            ':t2'  => $type,
            ':a2'  => $adminId,
        ]);
    }

    // -----------------------------------------------------------------------
    // Languages
    // -----------------------------------------------------------------------

    public function listLanguages(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, code, name, native_name, flag_code, is_rtl, is_active, is_default, sort_order, updated_at
             FROM languages ORDER BY sort_order ASC, name ASC'
        );
        return $stmt->fetchAll() ?: [];
    }

    public function findLanguageById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM languages WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    public function createLanguage(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO languages (code, name, native_name, flag_code, is_rtl, is_active, is_default, sort_order)
             VALUES (:code, :name, :native, :flag, :rtl, :active, 0, :sort)'
        );
        $stmt->execute([
            ':code'   => strtolower(trim((string)($data['code'] ?? ''))),
            ':name'   => trim((string)($data['name'] ?? '')),
            ':native' => trim((string)($data['native_name'] ?? '')),
            ':flag'   => trim((string)($data['flag_code'] ?? '')),
            ':rtl'    => (int)(bool)($data['is_rtl'] ?? 0),
            ':active' => (int)(bool)($data['is_active'] ?? 1),
            ':sort'   => (int)($data['sort_order'] ?? 99),
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function updateLanguage(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE languages SET code = :code, name = :name, native_name = :native, flag_code = :flag,
             is_rtl = :rtl, is_active = :active, sort_order = :sort, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            ':code'   => strtolower(trim((string)($data['code'] ?? ''))),
            ':name'   => trim((string)($data['name'] ?? '')),
            ':native' => trim((string)($data['native_name'] ?? '')),
            ':flag'   => trim((string)($data['flag_code'] ?? '')),
            ':rtl'    => (int)(bool)($data['is_rtl'] ?? 0),
            ':active' => (int)(bool)($data['is_active'] ?? 1),
            ':sort'   => (int)($data['sort_order'] ?? 99),
            ':id'     => $id,
        ]);
    }

    public function deleteLanguage(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM languages WHERE id = :id AND is_default = 0');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function setDefaultLanguage(int $id): void
    {
        $pdo = Database::connection();
        $pdo->exec('UPDATE languages SET is_default = 0');
        $stmt = $pdo->prepare('UPDATE languages SET is_default = 1, is_active = 1 WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // -----------------------------------------------------------------------
    // Themes
    // -----------------------------------------------------------------------

    public function listThemes(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, name, slug, color_scheme, primary_color, secondary_color, accent_color,
                    success_color, danger_color, bg_color, surface_color, font_family, font_size_base,
                    border_radius, logo_url, favicon_url, is_active, is_default, updated_at
             FROM app_themes ORDER BY is_default DESC, name ASC'
        );
        return $stmt->fetchAll() ?: [];
    }

    public function findThemeById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM app_themes WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    public function activeTheme(): ?array
    {
        $stmt = Database::connection()->query(
            'SELECT * FROM app_themes WHERE is_default = 1 LIMIT 1'
        );
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    public function saveTheme(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE app_themes SET name = :name, color_scheme = :cs, primary_color = :pc,
             secondary_color = :sc, accent_color = :ac, success_color = :suc, danger_color = :dc,
             bg_color = :bg, surface_color = :surf, font_family = :ff, font_size_base = :fsz,
             border_radius = :br, custom_css = :css, logo_url = :logo, favicon_url = :fav,
             is_active = :active, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            ':name'   => trim((string)($data['name'] ?? '')),
            ':cs'     => in_array((string)($data['color_scheme'] ?? 'dark'), ['dark','light','auto'], true) ? $data['color_scheme'] : 'dark',
            ':pc'     => trim((string)($data['primary_color'] ?? '#3b82f6')),
            ':sc'     => trim((string)($data['secondary_color'] ?? '#64748b')),
            ':ac'     => trim((string)($data['accent_color'] ?? '#f59e0b')),
            ':suc'    => trim((string)($data['success_color'] ?? '#10b981')),
            ':dc'     => trim((string)($data['danger_color'] ?? '#ef4444')),
            ':bg'     => trim((string)($data['bg_color'] ?? '#0f172a')),
            ':surf'   => trim((string)($data['surface_color'] ?? '#1e293b')),
            ':ff'     => trim((string)($data['font_family'] ?? 'Inter, system-ui, sans-serif')),
            ':fsz'    => trim((string)($data['font_size_base'] ?? '16px')),
            ':br'     => trim((string)($data['border_radius'] ?? '0.5rem')),
            ':css'    => ($data['custom_css'] ?? '') !== '' ? (string)$data['custom_css'] : null,
            ':logo'   => ($data['logo_url'] ?? '') !== '' ? trim((string)$data['logo_url']) : null,
            ':fav'    => ($data['favicon_url'] ?? '') !== '' ? trim((string)$data['favicon_url']) : null,
            ':active' => (int)(bool)($data['is_active'] ?? 1),
            ':id'     => $id,
        ]);
    }

    public function createTheme(array $data, int $adminId): int
    {
        $pdo = Database::connection();
        $slug = preg_replace('/[^a-z0-9\-]/', '-', strtolower(trim((string)($data['name'] ?? 'theme'))))
              . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        $stmt = $pdo->prepare(
            'INSERT INTO app_themes (name, slug, color_scheme, primary_color, secondary_color, accent_color,
             success_color, danger_color, bg_color, surface_color, font_family, font_size_base, border_radius,
             custom_css, logo_url, favicon_url, is_active, is_default, created_by)
             VALUES (:name, :slug, :cs, :pc, :sc, :ac, :suc, :dc, :bg, :surf, :ff, :fsz, :br, :css, :logo, :fav, 1, 0, :admin)'
        );
        $stmt->execute([
            ':name'  => trim((string)($data['name'] ?? 'New Theme')),
            ':slug'  => $slug,
            ':cs'    => in_array((string)($data['color_scheme'] ?? 'dark'), ['dark','light','auto'], true) ? $data['color_scheme'] : 'dark',
            ':pc'    => trim((string)($data['primary_color'] ?? '#3b82f6')),
            ':sc'    => trim((string)($data['secondary_color'] ?? '#64748b')),
            ':ac'    => trim((string)($data['accent_color'] ?? '#f59e0b')),
            ':suc'   => trim((string)($data['success_color'] ?? '#10b981')),
            ':dc'    => trim((string)($data['danger_color'] ?? '#ef4444')),
            ':bg'    => trim((string)($data['bg_color'] ?? '#0f172a')),
            ':surf'  => trim((string)($data['surface_color'] ?? '#1e293b')),
            ':ff'    => trim((string)($data['font_family'] ?? 'Inter, system-ui, sans-serif')),
            ':fsz'   => trim((string)($data['font_size_base'] ?? '16px')),
            ':br'    => trim((string)($data['border_radius'] ?? '0.5rem')),
            ':css'   => ($data['custom_css'] ?? '') !== '' ? (string)$data['custom_css'] : null,
            ':logo'  => ($data['logo_url'] ?? '') !== '' ? trim((string)$data['logo_url']) : null,
            ':fav'   => ($data['favicon_url'] ?? '') !== '' ? trim((string)$data['favicon_url']) : null,
            ':admin' => $adminId,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function activateTheme(int $id): void
    {
        $pdo = Database::connection();
        $pdo->exec('UPDATE app_themes SET is_default = 0');
        $stmt = $pdo->prepare('UPDATE app_themes SET is_default = 1, is_active = 1, updated_at = NOW() WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function deleteTheme(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM app_themes WHERE id = :id AND is_default = 0');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // -----------------------------------------------------------------------
    // SMTP Configurations
    // -----------------------------------------------------------------------

    public function listSmtpConfigs(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, name, host, port, encryption, username, from_email, from_name, reply_to,
                    max_per_minute, is_active, is_default, last_tested_at, last_test_ok, last_test_error, updated_at
             FROM smtp_configs ORDER BY is_default DESC, id ASC'
        );
        return $stmt->fetchAll() ?: [];
    }

    public function findSmtpById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM smtp_configs WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    public function saveSmtpConfig(array $data, int $adminId): int
    {
        $pdo   = Database::connection();
        $id    = (int)($data['id'] ?? 0);
        $pwd   = trim((string)($data['password'] ?? ''));
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE smtp_configs SET name = :name, host = :host, port = :port, encryption = :enc,
                 username = :user, from_email = :fe, from_name = :fn, reply_to = :rt,
                 max_per_minute = :mpm, is_active = :active, updated_at = NOW()
                 ' . ($pwd !== '' ? ', password = :pwd' : '') . '
                 WHERE id = :id'
            );
            $params = [
                ':name'   => trim((string)($data['name'] ?? '')),
                ':host'   => trim((string)($data['host'] ?? '')),
                ':port'   => max(1, min(65535, (int)($data['port'] ?? 587))),
                ':enc'    => in_array($data['encryption'] ?? '', ['none','ssl','tls','starttls'], true) ? $data['encryption'] : 'tls',
                ':user'   => trim((string)($data['username'] ?? '')),
                ':fe'     => trim((string)($data['from_email'] ?? '')),
                ':fn'     => trim((string)($data['from_name'] ?? '')),
                ':rt'     => trim((string)($data['reply_to'] ?? '')),
                ':mpm'    => max(1, (int)($data['max_per_minute'] ?? 60)),
                ':active' => (int)(bool)($data['is_active'] ?? 0),
                ':id'     => $id,
            ];
            if ($pwd !== '') {
                $params[':pwd'] = $pwd;
            }
            $stmt->execute($params);
            return $id;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO smtp_configs (name, host, port, encryption, username, password, from_email, from_name, reply_to, max_per_minute, is_active, is_default, created_by)
             VALUES (:name, :host, :port, :enc, :user, :pwd, :fe, :fn, :rt, :mpm, :active, 0, :admin)'
        );
        $stmt->execute([
            ':name'   => trim((string)($data['name'] ?? '')),
            ':host'   => trim((string)($data['host'] ?? '')),
            ':port'   => max(1, min(65535, (int)($data['port'] ?? 587))),
            ':enc'    => in_array($data['encryption'] ?? '', ['none','ssl','tls','starttls'], true) ? $data['encryption'] : 'tls',
            ':user'   => trim((string)($data['username'] ?? '')),
            ':pwd'    => $pwd !== '' ? $pwd : null,
            ':fe'     => trim((string)($data['from_email'] ?? '')),
            ':fn'     => trim((string)($data['from_name'] ?? '')),
            ':rt'     => trim((string)($data['reply_to'] ?? '')),
            ':mpm'    => max(1, (int)($data['max_per_minute'] ?? 60)),
            ':active' => (int)(bool)($data['is_active'] ?? 0),
            ':admin'  => $adminId,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function setDefaultSmtp(int $id): void
    {
        $pdo = Database::connection();
        $pdo->exec('UPDATE smtp_configs SET is_default = 0');
        $stmt = $pdo->prepare('UPDATE smtp_configs SET is_default = 1, is_active = 1 WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function deleteSmtp(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM smtp_configs WHERE id = :id AND is_default = 0');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function recordSmtpTestResult(int $id, bool $ok, string $error = ''): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE smtp_configs SET last_tested_at = NOW(), last_test_ok = :ok, last_test_error = :err WHERE id = :id'
        );
        $stmt->bindValue(':ok', (int)$ok, PDO::PARAM_INT);
        $stmt->bindValue(':err', $error !== '' ? $error : null);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // -----------------------------------------------------------------------
    // SMS Configurations
    // -----------------------------------------------------------------------

    public function listSmsConfigs(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, name, provider, account_sid, from_number, sender_id, is_active, is_default, max_per_minute, updated_at
             FROM sms_configs ORDER BY is_default DESC, id ASC'
        );
        return $stmt->fetchAll() ?: [];
    }

    public function findSmsById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM sms_configs WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    public function saveSmsConfig(array $data, int $adminId): int
    {
        $pdo      = Database::connection();
        $id       = (int)($data['id'] ?? 0);
        $providers = ['twilio', 'nexmo', 'aws_sns', 'msg91', 'custom'];
        $provider  = in_array((string)($data['provider'] ?? ''), $providers, true) ? $data['provider'] : 'twilio';
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE sms_configs SET name = :name, provider = :prov, account_sid = :sid,
                 from_number = :fn, sender_id = :snd, api_endpoint = :ep,
                 max_per_minute = :mpm, is_active = :active, updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                ':name'   => trim((string)($data['name'] ?? '')),
                ':prov'   => $provider,
                ':sid'    => trim((string)($data['account_sid'] ?? '')),
                ':fn'     => trim((string)($data['from_number'] ?? '')),
                ':snd'    => trim((string)($data['sender_id'] ?? '')),
                ':ep'     => trim((string)($data['api_endpoint'] ?? '')),
                ':mpm'    => max(1, (int)($data['max_per_minute'] ?? 30)),
                ':active' => (int)(bool)($data['is_active'] ?? 0),
                ':id'     => $id,
            ]);
            // Update tokens only if provided
            if (trim((string)($data['auth_token'] ?? '')) !== '') {
                $s2 = $pdo->prepare('UPDATE sms_configs SET auth_token = :t WHERE id = :id');
                $s2->execute([':t' => trim((string)$data['auth_token']), ':id' => $id]);
            }
            return $id;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO sms_configs (name, provider, account_sid, auth_token, from_number, sender_id, api_endpoint, max_per_minute, is_active, is_default, created_by)
             VALUES (:name, :prov, :sid, :token, :fn, :snd, :ep, :mpm, :active, 0, :admin)'
        );
        $stmt->execute([
            ':name'   => trim((string)($data['name'] ?? '')),
            ':prov'   => $provider,
            ':sid'    => trim((string)($data['account_sid'] ?? '')),
            ':token'  => trim((string)($data['auth_token'] ?? '')),
            ':fn'     => trim((string)($data['from_number'] ?? '')),
            ':snd'    => trim((string)($data['sender_id'] ?? '')),
            ':ep'     => trim((string)($data['api_endpoint'] ?? '')),
            ':mpm'    => max(1, (int)($data['max_per_minute'] ?? 30)),
            ':active' => (int)(bool)($data['is_active'] ?? 0),
            ':admin'  => $adminId,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function setDefaultSms(int $id): void
    {
        $pdo = Database::connection();
        $pdo->exec('UPDATE sms_configs SET is_default = 0');
        $stmt = $pdo->prepare('UPDATE sms_configs SET is_default = 1, is_active = 1 WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function deleteSms(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM sms_configs WHERE id = :id AND is_default = 0');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // -----------------------------------------------------------------------
    // API Integrations
    // -----------------------------------------------------------------------

    public function listApiIntegrations(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, name, provider, category, sandbox_mode, is_active, notes, updated_at
             FROM api_integrations ORDER BY category ASC, name ASC'
        );
        return $stmt->fetchAll() ?: [];
    }

    public function findApiIntegrationById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM api_integrations WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    public function saveApiIntegration(array $data, int $adminId): int
    {
        $pdo = Database::connection();
        $id  = (int)($data['id'] ?? 0);
        $cats = ['payment','kyc','analytics','trading','social','other'];
        $cat  = in_array((string)($data['category'] ?? ''), $cats, true) ? $data['category'] : 'other';
        $extra = ($data['extra_config'] ?? '') !== '' ? (string)$data['extra_config'] : null;
        // Validate JSON if provided
        if ($extra !== null && json_decode($extra) === null) {
            $extra = null;
        }
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE api_integrations SET name = :name, category = :cat, sandbox_mode = :sandbox,
                 is_active = :active, notes = :notes, extra_config = :extra, updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                ':name'    => trim((string)($data['name'] ?? '')),
                ':cat'     => $cat,
                ':sandbox' => (int)(bool)($data['sandbox_mode'] ?? 1),
                ':active'  => (int)(bool)($data['is_active'] ?? 0),
                ':notes'   => trim((string)($data['notes'] ?? '')),
                ':extra'   => $extra,
                ':id'      => $id,
            ]);
            // Update sensitive fields only if non-empty
            if (trim((string)($data['api_key'] ?? '')) !== '') {
                $s2 = $pdo->prepare('UPDATE api_integrations SET api_key = :k WHERE id = :id');
                $s2->execute([':k' => trim((string)$data['api_key']), ':id' => $id]);
            }
            if (trim((string)($data['api_secret'] ?? '')) !== '') {
                $s2 = $pdo->prepare('UPDATE api_integrations SET api_secret = :s WHERE id = :id');
                $s2->execute([':s' => trim((string)$data['api_secret']), ':id' => $id]);
            }
            if (trim((string)($data['webhook_secret'] ?? '')) !== '') {
                $s2 = $pdo->prepare('UPDATE api_integrations SET webhook_secret = :ws WHERE id = :id');
                $s2->execute([':ws' => trim((string)$data['webhook_secret']), ':id' => $id]);
            }
            return $id;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO api_integrations (name, provider, category, api_key, api_secret, webhook_secret, extra_config, sandbox_mode, is_active, notes, created_by)
             VALUES (:name, :prov, :cat, :key, :secret, :ws, :extra, :sandbox, :active, :notes, :admin)'
        );
        $stmt->execute([
            ':name'    => trim((string)($data['name'] ?? '')),
            ':prov'    => trim((string)($data['provider'] ?? '')),
            ':cat'     => $cat,
            ':key'     => trim((string)($data['api_key'] ?? '')),
            ':secret'  => trim((string)($data['api_secret'] ?? '')),
            ':ws'      => trim((string)($data['webhook_secret'] ?? '')),
            ':extra'   => $extra,
            ':sandbox' => (int)(bool)($data['sandbox_mode'] ?? 1),
            ':active'  => (int)(bool)($data['is_active'] ?? 0),
            ':notes'   => trim((string)($data['notes'] ?? '')),
            ':admin'   => $adminId,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function deleteApiIntegration(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM api_integrations WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // -----------------------------------------------------------------------
    // Backup Logs
    // -----------------------------------------------------------------------

    public function listBackupLogs(int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT bl.id, bl.backup_type, bl.trigger_type, bl.status, bl.file_path,
                    bl.file_size_bytes, bl.duration_seconds, bl.error_message, bl.notes,
                    bl.started_at, bl.completed_at,
                    COALESCE(au.full_name, au.username) AS created_by_name
             FROM backup_logs bl
             LEFT JOIN admin_users au ON au.id = bl.created_by
             ORDER BY bl.started_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function createBackupLog(string $type, string $trigger, int $adminId): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO backup_logs (backup_type, trigger_type, status, created_by, started_at)
             VALUES (:type, :trigger, 'running', :admin, NOW())"
        );
        $stmt->execute([':type' => $type, ':trigger' => $trigger, ':admin' => $adminId]);
        return (int)$pdo->lastInsertId();
    }

    public function completeBackupLog(int $id, bool $ok, string $filePath, int $bytes, int $duration, string $error = ''): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE backup_logs SET status = :status, file_path = :fp, file_size_bytes = :fsz,
             duration_seconds = :dur, error_message = :err, completed_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            ':status' => $ok ? 'completed' : 'failed',
            ':fp'     => $filePath,
            ':fsz'    => $bytes,
            ':dur'    => $duration,
            ':err'    => $error !== '' ? $error : null,
            ':id'     => $id,
        ]);
    }

    public function deleteBackupLog(int $id): void
    {
        $stmt = Database::connection()->prepare("UPDATE backup_logs SET status = 'deleted' WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // -----------------------------------------------------------------------
    // Cache Logs
    // -----------------------------------------------------------------------

    public function listCacheLogs(int $limit = 30): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT cl.id, cl.cache_type, cl.items_cleared, cl.notes, cl.flushed_at,
                    COALESCE(au.full_name, au.username) AS flushed_by_name
             FROM cache_logs cl
             LEFT JOIN admin_users au ON au.id = cl.flushed_by
             ORDER BY cl.flushed_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function logCacheFlush(string $cacheType, int $adminId, int $itemsCleared, string $notes = ''): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO cache_logs (cache_type, flushed_by, items_cleared, notes) VALUES (:type, :admin, :items, :notes)'
        );
        $stmt->execute([
            ':type'  => $cacheType,
            ':admin' => $adminId,
            ':items' => $itemsCleared,
            ':notes' => $notes !== '' ? $notes : null,
        ]);
    }

    // -----------------------------------------------------------------------
    // System Information
    // -----------------------------------------------------------------------

    public function systemStats(): array
    {
        $pdo = Database::connection();
        $stats = [];

        // User counts
        $row = $pdo->query("SELECT COUNT(*) AS total, SUM(CASE WHEN is_active=1 THEN 1 ELSE 0 END) AS active, SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today FROM users")->fetch();
        $stats['users_total']   = (int)($row['total'] ?? 0);
        $stats['users_active']  = (int)($row['active'] ?? 0);
        $stats['users_today']   = (int)($row['today'] ?? 0);

        // Order counts
        $row = $pdo->query("SELECT COUNT(*) AS total, SUM(CASE WHEN status='open' THEN 1 ELSE 0 END) AS open_orders FROM orders")->fetch();
        $stats['orders_total'] = (int)($row['total'] ?? 0);
        $stats['orders_open']  = (int)($row['open_orders'] ?? 0);

        // Trade volume 24h
        $row = $pdo->query("SELECT COALESCE(SUM(quote_quantity), 0) AS vol FROM trades WHERE created_at >= NOW() - INTERVAL 24 HOUR")->fetch();
        $stats['volume_24h'] = (float)($row['vol'] ?? 0);

        // Pending KYC
        $row = $pdo->query("SELECT COUNT(DISTINCT user_id) AS cnt FROM kyc_documents WHERE status = 'pending'")->fetch();
        $stats['kyc_pending'] = (int)($row['cnt'] ?? 0);

        // Open tickets
        $row = $pdo->query("SELECT COUNT(*) AS cnt FROM support_tickets WHERE status IN ('open','in_progress')")->fetch();
        $stats['tickets_open'] = (int)($row['cnt'] ?? 0);

        // Pending withdrawals
        $row = $pdo->query("SELECT COUNT(*) AS cnt FROM withdrawals WHERE status = 'pending'")->fetch();
        $stats['withdrawals_pending'] = (int)($row['cnt'] ?? 0);

        // DB size (information_schema) — use prepared statements to avoid injection
        $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        $stmt = $pdo->prepare(
            'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
             FROM information_schema.TABLES WHERE table_schema = ?'
        );
        $stmt->execute([$dbName]);
        $row = $stmt->fetch();
        $stats['db_size_mb'] = (float)($row['size_mb'] ?? 0);

        // Table count
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS cnt FROM information_schema.TABLES WHERE table_schema = ?'
        );
        $stmt->execute([$dbName]);
        $row = $stmt->fetch();
        $stats['db_tables'] = (int)($row['cnt'] ?? 0);

        return $stats;
    }

    public function phpExtensions(): array
    {
        return get_loaded_extensions();
    }

    public function getMySQLVersion(): string
    {
        $row = Database::connection()->query('SELECT VERSION() AS v')->fetch();
        return (string)($row['v'] ?? 'unknown');
    }

    public function getTableList(): array
    {
        $stmt = Database::connection()->query('SHOW TABLE STATUS');
        return $stmt->fetchAll() ?: [];
    }
}
