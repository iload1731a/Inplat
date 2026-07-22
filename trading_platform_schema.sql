-- ============================================================================
-- PROFESSIONAL TRADING PLATFORM - FULL DATABASE SCHEMA (MySQL 8.0+)
-- ============================================================================
-- Notes:
--  - InnoDB engine used everywhere for FK + transaction support.
--  - utf8mb4 charset for full unicode support.
--  - Soft-delete pattern (deleted_at) used on most admin-editable tables.
--  - Every "lookup"/configuration table is editable by admins via the
--    admin_users / roles / permissions system defined in Section 2.
--  - Monetary values use DECIMAL, never FLOAT, to avoid rounding errors.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS trading_platform
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE trading_platform;

-- ============================================================================
-- SECTION 1: USERS, AUTH, KYC
-- ============================================================================

CREATE TABLE users (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid                    CHAR(36) NOT NULL UNIQUE,
    username                VARCHAR(50) NOT NULL UNIQUE,
    email                   VARCHAR(191) NOT NULL UNIQUE,
    email_verified_at       DATETIME NULL,
    phone                   VARCHAR(20) NULL,
    phone_verified_at       DATETIME NULL,
    password_hash           VARCHAR(255) NOT NULL,
    password_algo           VARCHAR(30) NOT NULL DEFAULT 'argon2id',
    first_name              VARCHAR(100) NULL,
    last_name               VARCHAR(100) NULL,
    country_code            CHAR(2) NULL,
    timezone                VARCHAR(64) DEFAULT 'UTC',
    preferred_language      VARCHAR(10) DEFAULT 'en',
    account_type            ENUM('individual','corporate') NOT NULL DEFAULT 'individual',
    status                  ENUM('active','suspended','banned','pending','closed') NOT NULL DEFAULT 'pending',
    kyc_status              ENUM('unverified','pending','approved','rejected') NOT NULL DEFAULT 'unverified',
    kyc_level               TINYINT UNSIGNED NOT NULL DEFAULT 0,
    two_factor_enabled      TINYINT(1) NOT NULL DEFAULT 0,
    two_factor_secret       VARCHAR(255) NULL,
    referral_code           VARCHAR(20) UNIQUE NULL,
    referred_by             BIGINT UNSIGNED NULL,
    risk_score              DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    is_pep                  TINYINT(1) NOT NULL DEFAULT 0,  -- politically exposed person
    last_login_at           DATETIME NULL,
    last_login_ip           VARCHAR(45) NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at              DATETIME NULL,
    FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_users_status (status),
    INDEX idx_users_kyc_status (kyc_status)
) ENGINE=InnoDB;

CREATE TABLE user_profiles (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL UNIQUE,
    date_of_birth           DATE NULL,
    gender                  ENUM('male','female','other','undisclosed') NULL,
    address_line1           VARCHAR(191) NULL,
    address_line2           VARCHAR(191) NULL,
    city                    VARCHAR(100) NULL,
    state_province          VARCHAR(100) NULL,
    postal_code             VARCHAR(20) NULL,
    country_code            CHAR(2) NULL,
    occupation              VARCHAR(100) NULL,
    source_of_funds         VARCHAR(191) NULL,
    annual_income_range     VARCHAR(50) NULL,
    avatar_url              VARCHAR(500) NULL,
    company_name            VARCHAR(191) NULL,
    company_registration_no VARCHAR(100) NULL,
    tax_id                  VARCHAR(100) NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE kyc_documents (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    document_type           ENUM('passport','national_id','drivers_license','proof_of_address','selfie','corporate_doc','other') NOT NULL,
    document_number         VARCHAR(100) NULL,
    file_url                VARCHAR(500) NOT NULL,
    issue_country           CHAR(2) NULL,
    issue_date               DATE NULL,
    expiry_date              DATE NULL,
    status                  ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reviewed_by              BIGINT UNSIGNED NULL,   -- admin_users.id
    review_notes             VARCHAR(500) NULL,
    reviewed_at               DATETIME NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_kyc_status (status)
) ENGINE=InnoDB;

CREATE TABLE user_sessions (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    session_token           VARCHAR(255) NOT NULL UNIQUE,
    ip_address              VARCHAR(45) NOT NULL,
    user_agent              VARCHAR(500) NULL,
    device_fingerprint      VARCHAR(255) NULL,
    country_code            CHAR(2) NULL,
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    expires_at              DATETIME NOT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sessions_user (user_id, is_active)
) ENGINE=InnoDB;

CREATE TABLE login_history (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    ip_address              VARCHAR(45) NOT NULL,
    user_agent              VARCHAR(500) NULL,
    status                  ENUM('success','failed_password','failed_2fa','blocked') NOT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_login_history_user (user_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE password_resets (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    token_hash              VARCHAR(255) NOT NULL,
    expires_at              DATETIME NOT NULL,
    used_at                 DATETIME NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE api_keys (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    label                   VARCHAR(100) NULL,
    api_key                 VARCHAR(64) NOT NULL UNIQUE,
    api_secret_hash         VARCHAR(255) NOT NULL,
    permissions             SET('read','trade','withdraw') NOT NULL DEFAULT 'read',
    ip_whitelist            TEXT NULL,               -- comma separated CIDRs
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    last_used_at            DATETIME NULL,
    expires_at              DATETIME NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at              DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- SECTION 2: ADMIN, ROLES & PERMISSIONS (so admins can modify everything)
-- ============================================================================

CREATE TABLE roles (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                    VARCHAR(50) NOT NULL UNIQUE,      -- e.g. super_admin, compliance, support, market_maker_admin
    description             VARCHAR(255) NULL,
    is_system_role          TINYINT(1) NOT NULL DEFAULT 0,     -- protects core roles from deletion
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE permissions (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key`                   VARCHAR(100) NOT NULL UNIQUE,      -- e.g. 'users.edit', 'orders.cancel_any', 'settings.manage'
    module                  VARCHAR(50) NOT NULL,              -- e.g. 'users','trading','wallets','settings'
    description             VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
    role_id                 INT UNSIGNED NOT NULL,
    permission_id           INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE admin_users (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username                VARCHAR(50) NOT NULL UNIQUE,
    email                   VARCHAR(191) NOT NULL UNIQUE,
    password_hash           VARCHAR(255) NOT NULL,
    full_name               VARCHAR(150) NULL,
    role_id                 INT UNSIGNED NOT NULL,
    two_factor_enabled      TINYINT(1) NOT NULL DEFAULT 1,
    two_factor_secret       VARCHAR(255) NULL,
    status                  ENUM('active','suspended') NOT NULL DEFAULT 'active',
    last_login_at           DATETIME NULL,
    last_login_ip           VARCHAR(45) NULL,
    created_by              BIGINT UNSIGNED NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at              DATETIME NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Generic audit log capturing every admin action across every table
CREATE TABLE admin_activity_logs (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id                BIGINT UNSIGNED NOT NULL,
    action                  VARCHAR(100) NOT NULL,            -- e.g. 'update', 'delete', 'approve_kyc'
    entity_type             VARCHAR(100) NOT NULL,            -- table/entity name affected
    entity_id               VARCHAR(64) NULL,
    old_values              JSON NULL,
    new_values              JSON NULL,
    ip_address              VARCHAR(45) NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id),
    INDEX idx_admin_logs_entity (entity_type, entity_id),
    INDEX idx_admin_logs_admin (admin_id, created_at)
) ENGINE=InnoDB;

-- System-wide audit trail (covers user + system events, not just admin)
CREATE TABLE audit_logs (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_type              ENUM('user','admin','system') NOT NULL,
    actor_id                BIGINT UNSIGNED NULL,
    event                   VARCHAR(150) NOT NULL,
    description             VARCHAR(500) NULL,
    metadata                JSON NULL,
    ip_address              VARCHAR(45) NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_actor (actor_type, actor_id),
    INDEX idx_audit_event (event, created_at)
) ENGINE=InnoDB;

-- ============================================================================
-- SECTION 3: CURRENCIES, WALLETS & LEDGER
-- ============================================================================

CREATE TABLE currencies (
    id                      SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code                    VARCHAR(10) NOT NULL UNIQUE,       -- BTC, ETH, USD, USDT
    name                    VARCHAR(100) NOT NULL,
    type                    ENUM('fiat','crypto') NOT NULL,
    decimals                TINYINT UNSIGNED NOT NULL DEFAULT 8,
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    is_withdrawal_enabled   TINYINT(1) NOT NULL DEFAULT 1,
    is_deposit_enabled      TINYINT(1) NOT NULL DEFAULT 1,
    min_withdrawal          DECIMAL(36,18) NOT NULL DEFAULT 0,
    max_withdrawal_daily    DECIMAL(36,18) NULL,
    withdrawal_fee_fixed    DECIMAL(36,18) NOT NULL DEFAULT 0,
    withdrawal_fee_percent  DECIMAL(6,4) NOT NULL DEFAULT 0,
    network                 VARCHAR(50) NULL,                  -- e.g. ERC20, TRC20, BEP20 (for crypto)
    contract_address        VARCHAR(191) NULL,
    confirmations_required  INT UNSIGNED NULL,
    icon_url                VARCHAR(500) NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE wallets (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    currency_id             SMALLINT UNSIGNED NOT NULL,
    wallet_type             ENUM('spot','margin','futures','funding') NOT NULL DEFAULT 'spot',
    available_balance       DECIMAL(36,18) NOT NULL DEFAULT 0,
    locked_balance          DECIMAL(36,18) NOT NULL DEFAULT 0,   -- funds held in open orders
    total_deposited         DECIMAL(36,18) NOT NULL DEFAULT 0,
    total_withdrawn         DECIMAL(36,18) NOT NULL DEFAULT 0,
    is_frozen               TINYINT(1) NOT NULL DEFAULT 0,
    freeze_reason           VARCHAR(255) NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_currency_type (user_id, currency_id, wallet_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB;

CREATE TABLE wallet_addresses (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wallet_id               BIGINT UNSIGNED NOT NULL,
    address                 VARCHAR(191) NOT NULL,
    tag_or_memo             VARCHAR(100) NULL,
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE,
    INDEX idx_wallet_addr (address)
) ENGINE=InnoDB;

-- Immutable double-entry style ledger for every balance-affecting event
CREATE TABLE ledger_entries (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wallet_id               BIGINT UNSIGNED NOT NULL,
    reference_type          ENUM('deposit','withdrawal','trade','fee','transfer','adjustment','refund') NOT NULL,
    reference_id             BIGINT UNSIGNED NULL,
    direction               ENUM('credit','debit') NOT NULL,
    amount                  DECIMAL(36,18) NOT NULL,
    balance_after           DECIMAL(36,18) NOT NULL,
    notes                   VARCHAR(255) NULL,
    created_by_admin        BIGINT UNSIGNED NULL,    -- filled if this was a manual admin adjustment
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wallet_id) REFERENCES wallets(id),
    FOREIGN KEY (created_by_admin) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_ledger_wallet (wallet_id, created_at),
    INDEX idx_ledger_reference (reference_type, reference_id)
) ENGINE=InnoDB;

CREATE TABLE deposits (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    wallet_id               BIGINT UNSIGNED NOT NULL,
    currency_id             SMALLINT UNSIGNED NOT NULL,
    amount                  DECIMAL(36,18) NOT NULL,
    tx_hash                 VARCHAR(191) NULL,
    from_address            VARCHAR(191) NULL,
    to_address               VARCHAR(191) NULL,
    confirmations           INT UNSIGNED NOT NULL DEFAULT 0,
    status                  ENUM('pending','confirmed','credited','failed','flagged') NOT NULL DEFAULT 'pending',
    flagged_reason          VARCHAR(255) NULL,
    reviewed_by             BIGINT UNSIGNED NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    credited_at             DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (wallet_id) REFERENCES wallets(id),
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (reviewed_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_deposits_status (status),
    INDEX idx_deposits_tx (tx_hash)
) ENGINE=InnoDB;

CREATE TABLE withdrawals (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    wallet_id               BIGINT UNSIGNED NOT NULL,
    currency_id             SMALLINT UNSIGNED NOT NULL,
    amount                  DECIMAL(36,18) NOT NULL,
    fee                     DECIMAL(36,18) NOT NULL DEFAULT 0,
    destination_address     VARCHAR(191) NULL,
    destination_tag         VARCHAR(100) NULL,
    tx_hash                 VARCHAR(191) NULL,
    status                  ENUM('pending','approved','processing','completed','rejected','cancelled') NOT NULL DEFAULT 'pending',
    requires_manual_review  TINYINT(1) NOT NULL DEFAULT 0,
    reviewed_by              BIGINT UNSIGNED NULL,
    rejection_reason        VARCHAR(255) NULL,
    requested_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at             DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (wallet_id) REFERENCES wallets(id),
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (reviewed_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_withdrawals_status (status)
) ENGINE=InnoDB;

CREATE TABLE internal_transfers (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_user_id            BIGINT UNSIGNED NOT NULL,
    to_user_id              BIGINT UNSIGNED NOT NULL,
    currency_id             SMALLINT UNSIGNED NOT NULL,
    amount                  DECIMAL(36,18) NOT NULL,
    note                    VARCHAR(255) NULL,
    status                  ENUM('completed','reversed') NOT NULL DEFAULT 'completed',
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_user_id) REFERENCES users(id),
    FOREIGN KEY (to_user_id) REFERENCES users(id),
    FOREIGN KEY (currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB;

-- ============================================================================
-- SECTION 4: MARKETS & TRADING PAIRS
-- ============================================================================

CREATE TABLE trading_pairs (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    symbol                  VARCHAR(20) NOT NULL UNIQUE,       -- e.g. BTC/USDT
    base_currency_id        SMALLINT UNSIGNED NOT NULL,
    quote_currency_id       SMALLINT UNSIGNED NOT NULL,
    market_type             ENUM('spot','margin','futures') NOT NULL DEFAULT 'spot',
    price_precision         TINYINT UNSIGNED NOT NULL DEFAULT 2,
    quantity_precision      TINYINT UNSIGNED NOT NULL DEFAULT 6,
    min_order_size          DECIMAL(36,18) NOT NULL DEFAULT 0,
    max_order_size          DECIMAL(36,18) NULL,
    min_notional            DECIMAL(36,18) NOT NULL DEFAULT 0,
    maker_fee_percent       DECIMAL(6,4) NOT NULL DEFAULT 0.10,
    taker_fee_percent       DECIMAL(6,4) NOT NULL DEFAULT 0.15,
    max_leverage            DECIMAL(6,2) NOT NULL DEFAULT 1.00,
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    trading_enabled         TINYINT(1) NOT NULL DEFAULT 1,
    is_visible              TINYINT(1) NOT NULL DEFAULT 1,
    display_order           INT NOT NULL DEFAULT 0,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (base_currency_id) REFERENCES currencies(id),
    FOREIGN KEY (quote_currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB;

CREATE TABLE order_types (
    id                      TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                    VARCHAR(30) NOT NULL UNIQUE   -- market, limit, stop_limit, stop_market, trailing_stop, oco
) ENGINE=InnoDB;

CREATE TABLE orders (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_uuid              CHAR(36) NOT NULL UNIQUE,
    user_id                 BIGINT UNSIGNED NOT NULL,
    trading_pair_id         INT UNSIGNED NOT NULL,
    order_type_id           TINYINT UNSIGNED NOT NULL,
    side                    ENUM('buy','sell') NOT NULL,
    time_in_force           ENUM('GTC','IOC','FOK','GTD') NOT NULL DEFAULT 'GTC',
    price                   DECIMAL(36,18) NULL,             -- null for market orders
    stop_price              DECIMAL(36,18) NULL,
    quantity                DECIMAL(36,18) NOT NULL,
    filled_quantity         DECIMAL(36,18) NOT NULL DEFAULT 0,
    remaining_quantity      DECIMAL(36,18) NOT NULL,
    average_fill_price      DECIMAL(36,18) NULL,
    status                  ENUM('open','partially_filled','filled','cancelled','rejected','expired') NOT NULL DEFAULT 'open',
    leverage                DECIMAL(6,2) NOT NULL DEFAULT 1.00,
    is_reduce_only          TINYINT(1) NOT NULL DEFAULT 0,
    client_order_id         VARCHAR(100) NULL,
    rejection_reason        VARCHAR(255) NULL,
    source                  ENUM('web','mobile','api') NOT NULL DEFAULT 'web',
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    cancelled_at            DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (trading_pair_id) REFERENCES trading_pairs(id),
    FOREIGN KEY (order_type_id) REFERENCES order_types(id),
    INDEX idx_orders_user (user_id, status),
    INDEX idx_orders_pair_status (trading_pair_id, status),
    INDEX idx_orders_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE trades (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trade_uuid              CHAR(36) NOT NULL UNIQUE,
    trading_pair_id         INT UNSIGNED NOT NULL,
    buy_order_id            BIGINT UNSIGNED NOT NULL,
    sell_order_id           BIGINT UNSIGNED NOT NULL,
    buyer_id                BIGINT UNSIGNED NOT NULL,
    seller_id               BIGINT UNSIGNED NOT NULL,
    price                   DECIMAL(36,18) NOT NULL,
    quantity                DECIMAL(36,18) NOT NULL,
    quote_amount            DECIMAL(36,18) NOT NULL,          -- price * quantity
    buyer_fee               DECIMAL(36,18) NOT NULL DEFAULT 0,
    seller_fee              DECIMAL(36,18) NOT NULL DEFAULT 0,
    maker_side              ENUM('buy','sell') NOT NULL,
    executed_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trading_pair_id) REFERENCES trading_pairs(id),
    FOREIGN KEY (buy_order_id) REFERENCES orders(id),
    FOREIGN KEY (sell_order_id) REFERENCES orders(id),
    FOREIGN KEY (buyer_id) REFERENCES users(id),
    FOREIGN KEY (seller_id) REFERENCES users(id),
    INDEX idx_trades_pair_time (trading_pair_id, executed_at),
    INDEX idx_trades_buyer (buyer_id),
    INDEX idx_trades_seller (seller_id)
) ENGINE=InnoDB;

CREATE TABLE order_events (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id                BIGINT UNSIGNED NOT NULL,
    event_type              ENUM('created','partially_filled','filled','cancelled','rejected','modified') NOT NULL,
    details                 JSON NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- OHLCV candlestick data per interval, used to draw charts
CREATE TABLE candlesticks (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trading_pair_id         INT UNSIGNED NOT NULL,
    interval_code           ENUM('1m','5m','15m','30m','1h','4h','1d','1w','1M') NOT NULL,
    open_time               DATETIME NOT NULL,
    open_price              DECIMAL(36,18) NOT NULL,
    high_price              DECIMAL(36,18) NOT NULL,
    low_price               DECIMAL(36,18) NOT NULL,
    close_price             DECIMAL(36,18) NOT NULL,
    volume                  DECIMAL(36,18) NOT NULL DEFAULT 0,
    quote_volume            DECIMAL(36,18) NOT NULL DEFAULT 0,
    trade_count             INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_candle (trading_pair_id, interval_code, open_time),
    FOREIGN KEY (trading_pair_id) REFERENCES trading_pairs(id)
) ENGINE=InnoDB;

CREATE TABLE price_tickers (
    trading_pair_id         INT UNSIGNED PRIMARY KEY,
    last_price              DECIMAL(36,18) NOT NULL,
    best_bid                DECIMAL(36,18) NULL,
    best_ask                DECIMAL(36,18) NULL,
    change_24h_percent      DECIMAL(8,4) NOT NULL DEFAULT 0,
    high_24h                DECIMAL(36,18) NULL,
    low_24h                 DECIMAL(36,18) NULL,
    volume_24h              DECIMAL(36,18) NOT NULL DEFAULT 0,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (trading_pair_id) REFERENCES trading_pairs(id)
) ENGINE=InnoDB;

CREATE TABLE watchlists (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    trading_pair_id         INT UNSIGNED NOT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_watchlist (user_id, trading_pair_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (trading_pair_id) REFERENCES trading_pairs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- SECTION 5: MARGIN / FUTURES / POSITIONS
-- ============================================================================

CREATE TABLE margin_accounts (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL UNIQUE,
    margin_level            DECIMAL(10,4) NOT NULL DEFAULT 0,
    total_collateral_usd    DECIMAL(36,18) NOT NULL DEFAULT 0,
    total_borrowed_usd      DECIMAL(36,18) NOT NULL DEFAULT 0,
    is_restricted           TINYINT(1) NOT NULL DEFAULT 0,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE margin_loans (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    currency_id             SMALLINT UNSIGNED NOT NULL,
    principal               DECIMAL(36,18) NOT NULL,
    interest_rate_daily     DECIMAL(10,6) NOT NULL,
    interest_accrued        DECIMAL(36,18) NOT NULL DEFAULT 0,
    status                  ENUM('active','repaid','liquidated') NOT NULL DEFAULT 'active',
    borrowed_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    repaid_at               DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB;

CREATE TABLE positions (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    trading_pair_id         INT UNSIGNED NOT NULL,
    position_side           ENUM('long','short') NOT NULL,
    entry_price             DECIMAL(36,18) NOT NULL,
    quantity                DECIMAL(36,18) NOT NULL,
    leverage                DECIMAL(6,2) NOT NULL DEFAULT 1.00,
    liquidation_price       DECIMAL(36,18) NULL,
    margin_used              DECIMAL(36,18) NOT NULL DEFAULT 0,
    unrealized_pnl          DECIMAL(36,18) NOT NULL DEFAULT 0,
    realized_pnl            DECIMAL(36,18) NOT NULL DEFAULT 0,
    status                  ENUM('open','closed','liquidated') NOT NULL DEFAULT 'open',
    opened_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at               DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (trading_pair_id) REFERENCES trading_pairs(id),
    INDEX idx_positions_user_status (user_id, status)
) ENGINE=InnoDB;

CREATE TABLE liquidations (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    position_id             BIGINT UNSIGNED NOT NULL,
    user_id                 BIGINT UNSIGNED NOT NULL,
    liquidation_price       DECIMAL(36,18) NOT NULL,
    quantity_liquidated     DECIMAL(36,18) NOT NULL,
    loss_amount             DECIMAL(36,18) NOT NULL,
    insurance_fund_covered  DECIMAL(36,18) NOT NULL DEFAULT 0,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (position_id) REFERENCES positions(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE insurance_fund (
    id                      TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    currency_id             SMALLINT UNSIGNED NOT NULL,
    balance                 DECIMAL(36,18) NOT NULL DEFAULT 0,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB;

-- ============================================================================
-- SECTION 6: FEES & VIP TIERS
-- ============================================================================

CREATE TABLE fee_tiers (
    id                      SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tier_name               VARCHAR(50) NOT NULL,             -- e.g. 'VIP 1'
    min_30d_volume          DECIMAL(20,2) NOT NULL DEFAULT 0,
    min_token_holding       DECIMAL(20,2) NOT NULL DEFAULT 0,
    maker_fee_percent       DECIMAL(6,4) NOT NULL,
    taker_fee_percent       DECIMAL(6,4) NOT NULL,
    withdrawal_fee_discount_percent DECIMAL(6,4) NOT NULL DEFAULT 0,
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE user_fee_overrides (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL UNIQUE,
    fee_tier_id             SMALLINT UNSIGNED NULL,
    custom_maker_fee_percent DECIMAL(6,4) NULL,
    custom_taker_fee_percent DECIMAL(6,4) NULL,
    reason                  VARCHAR(255) NULL,
    set_by_admin            BIGINT UNSIGNED NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_tier_id) REFERENCES fee_tiers(id),
    FOREIGN KEY (set_by_admin) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE fee_revenue_ledger (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trade_id                BIGINT UNSIGNED NULL,
    withdrawal_id           BIGINT UNSIGNED NULL,
    currency_id             SMALLINT UNSIGNED NOT NULL,
    amount                  DECIMAL(36,18) NOT NULL,
    source                  ENUM('trading_fee','withdrawal_fee','margin_interest','other') NOT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trade_id) REFERENCES trades(id) ON DELETE SET NULL,
    FOREIGN KEY (withdrawal_id) REFERENCES withdrawals(id) ON DELETE SET NULL,
    FOREIGN KEY (currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB;

-- ============================================================================
-- SECTION 7: COMPLIANCE / RISK / AML
-- ============================================================================

CREATE TABLE risk_flags (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    flag_type               ENUM('unusual_volume','multiple_accounts','sanctioned_country','chargeback','velocity_check','manual') NOT NULL,
    severity                ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    description             VARCHAR(500) NULL,
    status                  ENUM('open','investigating','resolved','false_positive') NOT NULL DEFAULT 'open',
    assigned_to             BIGINT UNSIGNED NULL,
    resolved_at             DATETIME NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE ip_whitelist (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NULL,        -- null = platform-wide (admin office IPs etc)
    ip_address              VARCHAR(45) NOT NULL,
    label                   VARCHAR(100) NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ip_blacklist (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address              VARCHAR(45) NOT NULL UNIQUE,
    reason                  VARCHAR(255) NULL,
    blocked_by              BIGINT UNSIGNED NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blocked_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE sanctioned_countries (
    id                      SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    country_code            CHAR(2) NOT NULL UNIQUE,
    reason                  VARCHAR(255) NULL,
    added_at                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================================
-- SECTION 8: NOTIFICATIONS, CMS & COMMUNICATION
-- ============================================================================

CREATE TABLE notifications (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    type                    VARCHAR(50) NOT NULL,             -- order_filled, deposit_credited, security_alert, etc.
    title                   VARCHAR(191) NOT NULL,
    message                 TEXT NOT NULL,
    channel                 ENUM('in_app','email','sms','push') NOT NULL DEFAULT 'in_app',
    is_read                 TINYINT(1) NOT NULL DEFAULT 0,
    read_at                 DATETIME NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notifications_user (user_id, is_read)
) ENGINE=InnoDB;

CREATE TABLE email_templates (
    id                      SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_key            VARCHAR(100) NOT NULL UNIQUE,     -- e.g. 'welcome_email', 'withdrawal_confirmation'
    subject                 VARCHAR(255) NOT NULL,
    body_html               MEDIUMTEXT NOT NULL,
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    updated_by               BIGINT UNSIGNED NULL,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE announcements (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title                   VARCHAR(255) NOT NULL,
    body                    MEDIUMTEXT NOT NULL,
    category                ENUM('maintenance','new_listing','delisting','promotion','security','general') NOT NULL DEFAULT 'general',
    is_pinned               TINYINT(1) NOT NULL DEFAULT 0,
    is_published            TINYINT(1) NOT NULL DEFAULT 0,
    published_at            DATETIME NULL,
    created_by               BIGINT UNSIGNED NOT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admin_users(id)
) ENGINE=InnoDB;

CREATE TABLE banners (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title                   VARCHAR(191) NULL,
    image_url               VARCHAR(500) NOT NULL,
    link_url                VARCHAR(500) NULL,
    placement               ENUM('homepage','trading_page','mobile_home') NOT NULL DEFAULT 'homepage',
    display_order           INT NOT NULL DEFAULT 0,
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    starts_at               DATETIME NULL,
    ends_at                 DATETIME NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================================
-- SECTION 9: SUPPORT / TICKETING
-- ============================================================================

CREATE TABLE support_tickets (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_number           VARCHAR(20) NOT NULL UNIQUE,
    user_id                 BIGINT UNSIGNED NOT NULL,
    subject                 VARCHAR(255) NOT NULL,
    category                ENUM('account','deposit','withdrawal','trading','kyc','technical','other') NOT NULL DEFAULT 'other',
    priority                ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    status                  ENUM('open','in_progress','waiting_on_user','resolved','closed') NOT NULL DEFAULT 'open',
    assigned_to             BIGINT UNSIGNED NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at               DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE ticket_messages (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id               BIGINT UNSIGNED NOT NULL,
    sender_type             ENUM('user','admin') NOT NULL,
    sender_id               BIGINT UNSIGNED NOT NULL,
    message                 TEXT NOT NULL,
    attachment_url          VARCHAR(500) NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- SECTION 10: REFERRALS & AFFILIATES
-- ============================================================================

CREATE TABLE referrals (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    referrer_id             BIGINT UNSIGNED NOT NULL,
    referee_id              BIGINT UNSIGNED NOT NULL UNIQUE,
    commission_percent      DECIMAL(6,4) NOT NULL DEFAULT 20.0000,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referrer_id) REFERENCES users(id),
    FOREIGN KEY (referee_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE affiliate_commissions (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    referral_id             BIGINT UNSIGNED NOT NULL,
    trade_id                BIGINT UNSIGNED NULL,
    currency_id             SMALLINT UNSIGNED NOT NULL,
    amount                  DECIMAL(36,18) NOT NULL,
    status                  ENUM('pending','paid') NOT NULL DEFAULT 'pending',
    paid_at                 DATETIME NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referral_id) REFERENCES referrals(id) ON DELETE CASCADE,
    FOREIGN KEY (trade_id) REFERENCES trades(id) ON DELETE SET NULL,
    FOREIGN KEY (currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB;

-- ============================================================================
-- SECTION 11: SYSTEM SETTINGS (Global admin-editable configuration)
-- ============================================================================

CREATE TABLE system_settings (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key             VARCHAR(100) NOT NULL UNIQUE,     -- e.g. 'maintenance_mode', 'max_login_attempts'
    setting_value           TEXT NULL,
    value_type              ENUM('string','number','boolean','json') NOT NULL DEFAULT 'string',
    category                VARCHAR(50) NOT NULL DEFAULT 'general',
    description             VARCHAR(255) NULL,
    is_public               TINYINT(1) NOT NULL DEFAULT 0,    -- visible to frontend or admin-only
    updated_by               BIGINT UNSIGNED NULL,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE webhooks (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event                   VARCHAR(100) NOT NULL,            -- e.g. 'trade.executed', 'withdrawal.completed'
    target_url              VARCHAR(500) NOT NULL,
    secret                  VARCHAR(255) NOT NULL,
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE webhook_logs (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    webhook_id              INT UNSIGNED NOT NULL,
    payload                 JSON NULL,
    response_status         INT NULL,
    response_body           TEXT NULL,
    attempted_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE maintenance_windows (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title                   VARCHAR(191) NOT NULL,
    affected_services       VARCHAR(255) NULL,                -- e.g. 'trading,withdrawals'
    starts_at               DATETIME NOT NULL,
    ends_at                 DATETIME NOT NULL,
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    created_by               BIGINT UNSIGNED NOT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admin_users(id)
) ENGINE=InnoDB;

-- ============================================================================
-- SECTION 12: THIRD-PARTY PAIR IMPORT & REAL-TIME PRICE DATA INTEGRATION
-- ============================================================================
-- Lets admins register external data providers (CoinMarketCap, CoinGecko,
-- Binance, Kraken, CryptoCompare, etc.), import/discover trading pairs from
-- them, map internal currencies/pairs to each provider's identifiers, choose
-- which provider feeds real-time prices for each pair, and audit every
-- sync/tick for latency and reliability monitoring.
-- ============================================================================

-- Registry of connectable external data providers. Admin can add/edit/disable
-- any provider here without touching code.
CREATE TABLE price_data_providers (
    id                      SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                    VARCHAR(100) NOT NULL,             -- e.g. 'CoinMarketCap', 'CoinGecko', 'Binance'
    provider_code           VARCHAR(30) NOT NULL UNIQUE,        -- e.g. 'coinmarketcap', 'coingecko', 'binance'
    provider_type           ENUM('rest_market_data','websocket_stream','both') NOT NULL DEFAULT 'rest_market_data',
    base_url                VARCHAR(255) NULL,                  -- e.g. https://pro-api.coinmarketcap.com
    websocket_url           VARCHAR(255) NULL,                  -- e.g. wss://stream.binance.com:9443/ws
    auth_type               ENUM('none','api_key_header','api_key_query','hmac_signature','oauth2') NOT NULL DEFAULT 'api_key_header',
    api_key_encrypted       VARCHAR(500) NULL,                  -- store encrypted at rest, never plaintext
    api_secret_encrypted    VARCHAR(500) NULL,
    auth_header_name        VARCHAR(100) NULL DEFAULT 'X-CMC_PRO_API_KEY',
    rate_limit_per_minute   INT UNSIGNED NULL,
    priority                SMALLINT UNSIGNED NOT NULL DEFAULT 100, -- lower = tried first as primary source
    supports_pair_import    TINYINT(1) NOT NULL DEFAULT 1,
    supports_realtime_price TINYINT(1) NOT NULL DEFAULT 1,
    default_sync_interval_seconds INT UNSIGNED NOT NULL DEFAULT 60,
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    last_health_check_at    DATETIME NULL,
    last_health_status      ENUM('healthy','degraded','down','unknown') NOT NULL DEFAULT 'unknown',
    notes                   VARCHAR(500) NULL,
    created_by              BIGINT UNSIGNED NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Maps our internal currencies to each provider's own identifiers, since
-- CoinMarketCap, CoinGecko, and exchanges all use different IDs/symbols.
CREATE TABLE provider_asset_mappings (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id             SMALLINT UNSIGNED NOT NULL,
    currency_id             SMALLINT UNSIGNED NOT NULL,
    external_id             VARCHAR(100) NULL,                  -- e.g. CMC numeric id '1' for BTC
    external_symbol         VARCHAR(30) NULL,                   -- e.g. 'BTC'
    external_slug           VARCHAR(100) NULL,                  -- e.g. CoinGecko slug 'bitcoin'
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_provider_currency (provider_id, currency_id),
    FOREIGN KEY (provider_id) REFERENCES price_data_providers(id) ON DELETE CASCADE,
    FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Admin-triggered (or scheduled) jobs that pull a list of markets/pairs from
-- a provider so new pairs can be discovered and reviewed before going live.
CREATE TABLE pair_import_jobs (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id             SMALLINT UNSIGNED NOT NULL,
    triggered_by            BIGINT UNSIGNED NULL,                -- admin_users.id; null if run by scheduler
    trigger_type            ENUM('manual','scheduled') NOT NULL DEFAULT 'manual',
    status                  ENUM('running','completed','failed','partial') NOT NULL DEFAULT 'running',
    pairs_found             INT UNSIGNED NOT NULL DEFAULT 0,
    pairs_created           INT UNSIGNED NOT NULL DEFAULT 0,
    pairs_updated           INT UNSIGNED NOT NULL DEFAULT 0,
    pairs_skipped           INT UNSIGNED NOT NULL DEFAULT 0,
    error_message           VARCHAR(500) NULL,
    started_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at            DATETIME NULL,
    FOREIGN KEY (provider_id) REFERENCES price_data_providers(id),
    FOREIGN KEY (triggered_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_import_jobs_provider (provider_id, started_at)
) ENGINE=InnoDB;

-- Staging area for pairs/assets pulled from a provider. Nothing here goes
-- live in `trading_pairs` until an admin reviews and approves it.
CREATE TABLE imported_pairs_staging (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    import_job_id           BIGINT UNSIGNED NOT NULL,
    provider_id             SMALLINT UNSIGNED NOT NULL,
    external_base_symbol    VARCHAR(30) NOT NULL,
    external_quote_symbol   VARCHAR(30) NOT NULL,
    external_pair_id        VARCHAR(100) NULL,
    suggested_symbol        VARCHAR(20) NOT NULL,                -- e.g. 'BTC/USDT'
    market_cap_rank         INT UNSIGNED NULL,
    volume_24h_usd          DECIMAL(30,2) NULL,
    last_price_usd          DECIMAL(36,18) NULL,
    raw_payload             JSON NULL,                           -- full raw response for reference/debugging
    status                  ENUM('pending','approved','rejected','already_exists') NOT NULL DEFAULT 'pending',
    matched_trading_pair_id INT UNSIGNED NULL,                   -- filled once approved and created/linked
    reviewed_by             BIGINT UNSIGNED NULL,
    review_notes            VARCHAR(255) NULL,
    reviewed_at              DATETIME NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (import_job_id) REFERENCES pair_import_jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES price_data_providers(id),
    FOREIGN KEY (matched_trading_pair_id) REFERENCES trading_pairs(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_staging_status (status)
) ENGINE=InnoDB;

-- Per-pair configuration of which provider drives its live price, with a
-- fallback chain, so admin can control the real-time price source per pair.
CREATE TABLE price_feed_subscriptions (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trading_pair_id         INT UNSIGNED NOT NULL UNIQUE,
    primary_provider_id     SMALLINT UNSIGNED NOT NULL,
    fallback_provider_id    SMALLINT UNSIGNED NULL,
    feed_mode               ENUM('websocket','polling') NOT NULL DEFAULT 'websocket',
    poll_interval_seconds   INT UNSIGNED NULL,                   -- used only when feed_mode = 'polling'
    max_allowed_staleness_seconds INT UNSIGNED NOT NULL DEFAULT 30, -- alert/failover if no update within this window
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (trading_pair_id) REFERENCES trading_pairs(id) ON DELETE CASCADE,
    FOREIGN KEY (primary_provider_id) REFERENCES price_data_providers(id),
    FOREIGN KEY (fallback_provider_id) REFERENCES price_data_providers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- High-frequency raw tick log from real-time feeds. Typically short-retention
-- / partitioned by day in production; `price_tickers` (Section 4) holds the
-- current snapshot derived from this stream.
CREATE TABLE external_price_ticks (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trading_pair_id         INT UNSIGNED NOT NULL,
    provider_id             SMALLINT UNSIGNED NOT NULL,
    price                   DECIMAL(36,18) NOT NULL,
    volume_24h              DECIMAL(36,18) NULL,
    bid_price               DECIMAL(36,18) NULL,
    ask_price               DECIMAL(36,18) NULL,
    latency_ms              INT UNSIGNED NULL,                   -- time between provider timestamp and our ingestion
    received_at             DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    FOREIGN KEY (trading_pair_id) REFERENCES trading_pairs(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES price_data_providers(id),
    INDEX idx_ticks_pair_time (trading_pair_id, received_at)
) ENGINE=InnoDB;

-- Sync/connection health log: every scheduled REST pull or WebSocket
-- reconnect event, for monitoring and alerting on provider reliability.
CREATE TABLE price_sync_logs (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id             SMALLINT UNSIGNED NOT NULL,
    trading_pair_id         INT UNSIGNED NULL,                   -- null = provider-wide event (e.g. connect/disconnect)
    event_type              ENUM('sync_success','sync_failed','rate_limited','ws_connected','ws_disconnected','ws_error') NOT NULL,
    http_status             INT NULL,
    message                 VARCHAR(500) NULL,
    response_time_ms        INT UNSIGNED NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES price_data_providers(id),
    FOREIGN KEY (trading_pair_id) REFERENCES trading_pairs(id) ON DELETE SET NULL,
    INDEX idx_sync_logs_provider (provider_id, created_at)
) ENGINE=InnoDB;

-- ============================================================================
-- SECTION 13: ADVANCED SECURITY & ACCOUNT PROTECTION
-- ============================================================================

-- Per-user security preferences, separate from `users` to keep hot login-path
-- columns lean.
CREATE TABLE account_security_settings (
    user_id                     BIGINT UNSIGNED PRIMARY KEY,
    anti_phishing_code          VARCHAR(50) NULL,          -- shown in every official email so user can spot fakes
    withdrawal_whitelist_enabled TINYINT(1) NOT NULL DEFAULT 0,
    withdrawal_lock_until       DATETIME NULL,             -- temporary self-imposed withdrawal freeze
    login_email_notifications  TINYINT(1) NOT NULL DEFAULT 1,
    new_device_confirmation_required TINYINT(1) NOT NULL DEFAULT 1,
    updated_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Whitelisted withdrawal destinations. New addresses typically enter with a
-- cooldown (e.g. 24-48h) before they can be used, blocking attacker-added
-- addresses from being used immediately after an account takeover.
CREATE TABLE withdrawal_whitelist_addresses (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    currency_id             SMALLINT UNSIGNED NOT NULL,
    address                 VARCHAR(191) NOT NULL,
    tag_or_memo             VARCHAR(100) NULL,
    label                   VARCHAR(100) NULL,
    status                  ENUM('pending_cooldown','active','revoked') NOT NULL DEFAULT 'pending_cooldown',
    cooldown_ends_at        DATETIME NULL,
    added_via_ip            VARCHAR(45) NULL,
    revoked_at              DATETIME NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    INDEX idx_whitelist_user_status (user_id, status)
) ENGINE=InnoDB;

CREATE TABLE trusted_devices (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    device_fingerprint      VARCHAR(255) NOT NULL,
    device_name             VARCHAR(150) NULL,
    platform                VARCHAR(50) NULL,             -- e.g. 'iOS', 'Android', 'Web-Chrome'
    is_trusted              TINYINT(1) NOT NULL DEFAULT 0,
    first_seen_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    trusted_at              DATETIME NULL,
    revoked_at              DATETIME NULL,
    UNIQUE KEY uq_user_device (user_id, device_fingerprint),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- SECTION 14: RISK CONTROLS & TRADING INTEGRITY
-- ============================================================================

-- Admin-configured automatic circuit breaker rules per pair (e.g. halt if
-- price moves >10% in 5 minutes).
CREATE TABLE circuit_breakers (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trading_pair_id         INT UNSIGNED NOT NULL,
    trigger_type            ENUM('price_move_percent','volume_spike') NOT NULL DEFAULT 'price_move_percent',
    threshold_percent       DECIMAL(6,2) NOT NULL,
    evaluation_window_seconds INT UNSIGNED NOT NULL DEFAULT 300,
    halt_duration_seconds   INT UNSIGNED NOT NULL DEFAULT 900,
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    created_by              BIGINT UNSIGNED NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trading_pair_id) REFERENCES trading_pairs(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Actual halt events, whether triggered automatically by a circuit breaker
-- or manually by an admin.
CREATE TABLE trading_halts (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trading_pair_id         INT UNSIGNED NOT NULL,
    circuit_breaker_id      INT UNSIGNED NULL,             -- null if manually triggered
    reason                  VARCHAR(255) NOT NULL,
    triggered_by            ENUM('system','admin') NOT NULL DEFAULT 'system',
    admin_id                BIGINT UNSIGNED NULL,
    status                  ENUM('active','resolved') NOT NULL DEFAULT 'active',
    started_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at                DATETIME NULL,
    FOREIGN KEY (trading_pair_id) REFERENCES trading_pairs(id),
    FOREIGN KEY (circuit_breaker_id) REFERENCES circuit_breakers(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_halts_pair_status (trading_pair_id, status)
) ENGINE=InnoDB;

-- Position/order size and volume limits, settable globally, per fee tier,
-- or per individual user (most specific match wins).
CREATE TABLE user_trading_limits (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scope                   ENUM('global','fee_tier','user') NOT NULL DEFAULT 'user',
    fee_tier_id             SMALLINT UNSIGNED NULL,
    user_id                 BIGINT UNSIGNED NULL,
    trading_pair_id         INT UNSIGNED NULL,            -- null = applies to all pairs
    max_order_size          DECIMAL(36,18) NULL,
    max_position_size       DECIMAL(36,18) NULL,
    max_daily_volume        DECIMAL(36,18) NULL,
    max_open_orders         INT UNSIGNED NULL,
    max_leverage            DECIMAL(6,2) NULL,
    set_by_admin            BIGINT UNSIGNED NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (fee_tier_id) REFERENCES fee_tiers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (trading_pair_id) REFERENCES trading_pairs(id) ON DELETE CASCADE,
    FOREIGN KEY (set_by_admin) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_limits_user (user_id)
) ENGINE=InnoDB;

-- Periodic full order-book snapshots for reconstruction, matching-engine
-- audits, and dispute resolution. Store compactly as JSON arrays of
-- [price, quantity] levels; prune/archive aggressively in production.
CREATE TABLE order_book_snapshots (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trading_pair_id         INT UNSIGNED NOT NULL,
    snapshot_time           DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    bids                    JSON NOT NULL,
    asks                    JSON NOT NULL,
    best_bid                DECIMAL(36,18) NULL,
    best_ask                DECIMAL(36,18) NULL,
    FOREIGN KEY (trading_pair_id) REFERENCES trading_pairs(id) ON DELETE CASCADE,
    INDEX idx_book_snapshots_pair_time (trading_pair_id, snapshot_time)
) ENGINE=InnoDB;

-- Log of self-trade prevention actions (same user's own orders matching).
CREATE TABLE self_trade_prevention_logs (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    trading_pair_id         INT UNSIGNED NOT NULL,
    incoming_order_id       BIGINT UNSIGNED NOT NULL,
    resting_order_id        BIGINT UNSIGNED NOT NULL,
    action_taken            ENUM('cancel_incoming','cancel_resting','cancel_both','allowed') NOT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (trading_pair_id) REFERENCES trading_pairs(id),
    FOREIGN KEY (incoming_order_id) REFERENCES orders(id),
    FOREIGN KEY (resting_order_id) REFERENCES orders(id)
) ENGINE=InnoDB;

-- ============================================================================
-- SECTION 15: LEGAL & COMPLIANCE CASE MANAGEMENT
-- ============================================================================

-- Versioned legal documents. Admin publishes a new version; users are
-- required to re-accept before continuing to trade if `requires_reacceptance`.
CREATE TABLE legal_documents (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_type           ENUM('terms_of_service','privacy_policy','risk_disclosure','aml_policy','cookie_policy') NOT NULL,
    version                 VARCHAR(20) NOT NULL,
    title                   VARCHAR(255) NOT NULL,
    content_url             VARCHAR(500) NULL,
    body                    MEDIUMTEXT NULL,
    requires_reacceptance   TINYINT(1) NOT NULL DEFAULT 1,
    is_current              TINYINT(1) NOT NULL DEFAULT 0,
    published_by            BIGINT UNSIGNED NULL,
    published_at            DATETIME NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_doc_version (document_type, version),
    FOREIGN KEY (published_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Proof of user consent per document version, with IP for audit purposes.
CREATE TABLE user_document_consents (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    legal_document_id       INT UNSIGNED NOT NULL,
    accepted_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address              VARCHAR(45) NULL,
    UNIQUE KEY uq_user_doc (user_id, legal_document_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (legal_document_id) REFERENCES legal_documents(id)
) ENGINE=InnoDB;

-- Rule-based transaction monitoring. Rules are evaluated by the application
-- layer; matches auto-create rows in `risk_flags` (Section 7) or open a
-- SAR case directly.
CREATE TABLE transaction_monitoring_rules (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rule_name               VARCHAR(150) NOT NULL,
    rule_type               ENUM('deposit_velocity','withdrawal_velocity','volume_threshold','structuring_pattern','new_account_high_value','geo_risk','custom') NOT NULL,
    conditions              JSON NOT NULL,             -- e.g. {"amount_usd_gt": 10000, "window_hours": 24}
    action                  ENUM('flag_only','freeze_account','open_sar_case','notify_compliance') NOT NULL DEFAULT 'flag_only',
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    created_by              BIGINT UNSIGNED NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Formal Suspicious Activity Report case management, distinct from the
-- lighter-weight `risk_flags` table used for day-to-day triage.
CREATE TABLE sar_cases (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_number             VARCHAR(30) NOT NULL UNIQUE,
    user_id                 BIGINT UNSIGNED NOT NULL,
    related_risk_flag_id    BIGINT UNSIGNED NULL,
    opened_by               BIGINT UNSIGNED NOT NULL,
    assigned_to             BIGINT UNSIGNED NULL,
    summary                 TEXT NOT NULL,
    status                  ENUM('open','investigating','filed_with_authority','closed_no_action','closed_filed') NOT NULL DEFAULT 'open',
    filed_with_authority    TINYINT(1) NOT NULL DEFAULT 0,
    filed_reference_number  VARCHAR(100) NULL,
    filed_at                DATETIME NULL,
    closed_at               DATETIME NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (related_risk_flag_id) REFERENCES risk_flags(id) ON DELETE SET NULL,
    FOREIGN KEY (opened_by) REFERENCES admin_users(id),
    FOREIGN KEY (assigned_to) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_sar_status (status)
) ENGINE=InnoDB;

CREATE TABLE sar_case_notes (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sar_case_id             BIGINT UNSIGNED NOT NULL,
    admin_id                BIGINT UNSIGNED NOT NULL,
    note                    TEXT NOT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sar_case_id) REFERENCES sar_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id)
) ENGINE=InnoDB;

-- ============================================================================
-- SECTION 16: EXTRA PRODUCTS (STAKING, CONVERT/SWAP, OTC DESK)
-- ============================================================================

CREATE TABLE staking_pools (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    currency_id             SMALLINT UNSIGNED NOT NULL,
    name                    VARCHAR(150) NOT NULL,
    apy_percent             DECIMAL(6,3) NOT NULL,
    lock_period_days        INT UNSIGNED NOT NULL DEFAULT 0,   -- 0 = flexible/no lock
    min_stake_amount        DECIMAL(36,18) NOT NULL DEFAULT 0,
    max_pool_capacity       DECIMAL(36,18) NULL,
    total_staked            DECIMAL(36,18) NOT NULL DEFAULT 0,
    early_unstake_fee_percent DECIMAL(6,4) NOT NULL DEFAULT 0,
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB;

CREATE TABLE user_stakes (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    staking_pool_id         INT UNSIGNED NOT NULL,
    amount                  DECIMAL(36,18) NOT NULL,
    status                  ENUM('active','unstaking','completed','cancelled') NOT NULL DEFAULT 'active',
    rewards_earned          DECIMAL(36,18) NOT NULL DEFAULT 0,
    started_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    unlock_at               DATETIME NULL,
    ended_at                DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (staking_pool_id) REFERENCES staking_pools(id),
    INDEX idx_stakes_user_status (user_id, status)
) ENGINE=InnoDB;

CREATE TABLE staking_reward_payouts (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_stake_id           BIGINT UNSIGNED NOT NULL,
    amount                  DECIMAL(36,18) NOT NULL,
    paid_at                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_stake_id) REFERENCES user_stakes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Fixed-spread instant conversion (no order book matching involved).
CREATE TABLE convert_quotes (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    from_currency_id        SMALLINT UNSIGNED NOT NULL,
    to_currency_id          SMALLINT UNSIGNED NOT NULL,
    from_amount             DECIMAL(36,18) NOT NULL,
    to_amount               DECIMAL(36,18) NOT NULL,
    quoted_rate              DECIMAL(36,18) NOT NULL,
    spread_percent           DECIMAL(6,4) NOT NULL,
    status                  ENUM('pending','executed','expired','cancelled') NOT NULL DEFAULT 'pending',
    expires_at               DATETIME NOT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (from_currency_id) REFERENCES currencies(id),
    FOREIGN KEY (to_currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB;

CREATE TABLE convert_transactions (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    convert_quote_id        BIGINT UNSIGNED NOT NULL UNIQUE,
    user_id                 BIGINT UNSIGNED NOT NULL,
    executed_rate           DECIMAL(36,18) NOT NULL,
    from_amount             DECIMAL(36,18) NOT NULL,
    to_amount               DECIMAL(36,18) NOT NULL,
    fee_amount              DECIMAL(36,18) NOT NULL DEFAULT 0,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (convert_quote_id) REFERENCES convert_quotes(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- OTC (over-the-counter) desk for large block trades handled manually by staff.
CREATE TABLE otc_desk_requests (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    base_currency_id        SMALLINT UNSIGNED NOT NULL,
    quote_currency_id       SMALLINT UNSIGNED NOT NULL,
    side                    ENUM('buy','sell') NOT NULL,
    requested_amount        DECIMAL(36,18) NOT NULL,
    quoted_price             DECIMAL(36,18) NULL,
    status                  ENUM('pending','quoted','accepted','rejected','completed','expired') NOT NULL DEFAULT 'pending',
    assigned_to             BIGINT UNSIGNED NULL,
    notes                   VARCHAR(500) NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at            DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (base_currency_id) REFERENCES currencies(id),
    FOREIGN KEY (quote_currency_id) REFERENCES currencies(id),
    FOREIGN KEY (assigned_to) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================================
-- SECTION 17: GROWTH & ENGAGEMENT
-- ============================================================================

CREATE TABLE loyalty_tiers (
    id                      SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tier_name               VARCHAR(50) NOT NULL,
    min_points              INT UNSIGNED NOT NULL DEFAULT 0,
    benefits                JSON NULL,             -- e.g. {"fee_discount_percent": 5, "priority_support": true}
    is_active               TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE loyalty_points_ledger (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    points                  INT NOT NULL,           -- positive = earn, negative = redeem
    source                  ENUM('trade','referral','promotion','signup_bonus','manual_adjustment','redemption') NOT NULL,
    reference_id            VARCHAR(64) NULL,
    balance_after           INT NOT NULL,
    notes                   VARCHAR(255) NULL,
    created_by_admin        BIGINT UNSIGNED NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_admin) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_loyalty_user (user_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE trading_competitions (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title                   VARCHAR(191) NOT NULL,
    description             TEXT NULL,
    trading_pair_id         INT UNSIGNED NULL,      -- null = applies across all pairs
    ranking_metric          ENUM('volume','pnl','trade_count') NOT NULL DEFAULT 'volume',
    prize_pool_currency_id  SMALLINT UNSIGNED NOT NULL,
    prize_pool_amount       DECIMAL(36,18) NOT NULL,
    starts_at               DATETIME NOT NULL,
    ends_at                 DATETIME NOT NULL,
    status                  ENUM('upcoming','active','completed','cancelled') NOT NULL DEFAULT 'upcoming',
    created_by              BIGINT UNSIGNED NOT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trading_pair_id) REFERENCES trading_pairs(id) ON DELETE SET NULL,
    FOREIGN KEY (prize_pool_currency_id) REFERENCES currencies(id),
    FOREIGN KEY (created_by) REFERENCES admin_users(id)
) ENGINE=InnoDB;

CREATE TABLE competition_participants (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    competition_id          INT UNSIGNED NOT NULL,
    user_id                 BIGINT UNSIGNED NOT NULL,
    current_score           DECIMAL(36,18) NOT NULL DEFAULT 0,
    rank                    INT UNSIGNED NULL,
    prize_awarded           DECIMAL(36,18) NULL,
    joined_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_competition_user (competition_id, user_id),
    FOREIGN KEY (competition_id) REFERENCES trading_competitions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE push_notification_devices (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    device_token            VARCHAR(255) NOT NULL,
    platform                ENUM('ios','android','web') NOT NULL,
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    last_used_at            DATETIME NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_device_token (device_token),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- SECTION 18: PLATFORM OPERATIONS (FEATURE FLAGS, SUB-ACCOUNTS, API QUOTAS)
-- ============================================================================

CREATE TABLE feature_flags (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    flag_key                VARCHAR(100) NOT NULL UNIQUE,
    description             VARCHAR(255) NULL,
    is_enabled_globally     TINYINT(1) NOT NULL DEFAULT 0,
    rollout_percent         TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- gradual rollout, 0-100
    updated_by              BIGINT UNSIGNED NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE feature_flag_overrides (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    feature_flag_id         INT UNSIGNED NOT NULL,
    user_id                 BIGINT UNSIGNED NOT NULL,
    is_enabled              TINYINT(1) NOT NULL,
    UNIQUE KEY uq_flag_user (feature_flag_id, user_id),
    FOREIGN KEY (feature_flag_id) REFERENCES feature_flags(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Institutional sub-accounts: a master account can own/control multiple
-- linked trading sub-accounts, each still a full row in `users`.
CREATE TABLE sub_accounts (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    master_user_id          BIGINT UNSIGNED NOT NULL,
    sub_user_id             BIGINT UNSIGNED NOT NULL UNIQUE,
    label                   VARCHAR(100) NULL,
    permissions             JSON NULL,           -- e.g. {"can_withdraw": false, "can_trade": true}
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (master_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sub_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE api_rate_limit_policies (
    id                      SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    policy_name             VARCHAR(100) NOT NULL UNIQUE,
    requests_per_minute     INT UNSIGNED NOT NULL,
    requests_per_day        INT UNSIGNED NULL,
    applies_to              ENUM('default','fee_tier','specific_key') NOT NULL DEFAULT 'default',
    fee_tier_id             SMALLINT UNSIGNED NULL,
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (fee_tier_id) REFERENCES fee_tiers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE api_key_rate_limit_overrides (
    api_key_id              BIGINT UNSIGNED PRIMARY KEY,
    rate_limit_policy_id    SMALLINT UNSIGNED NOT NULL,
    FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE CASCADE,
    FOREIGN KEY (rate_limit_policy_id) REFERENCES api_rate_limit_policies(id)
) ENGINE=InnoDB;

CREATE TABLE api_usage_logs (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    api_key_id              BIGINT UNSIGNED NULL,
    user_id                 BIGINT UNSIGNED NULL,
    endpoint                VARCHAR(150) NOT NULL,
    ip_address              VARCHAR(45) NULL,
    status_code             INT NULL,
    response_time_ms        INT UNSIGNED NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_api_usage_key_time (api_key_id, created_at)
) ENGINE=InnoDB;

-- ============================================================================
-- SEED DATA: core roles, permissions and default settings
-- ============================================================================

INSERT INTO roles (name, description, is_system_role) VALUES
('super_admin', 'Full unrestricted access to all modules', 1),
('compliance_officer', 'KYC/AML review and risk management', 1),
('finance_admin', 'Deposits, withdrawals, ledger adjustments', 1),
('support_agent', 'Support tickets and user assistance', 1),
('market_operator', 'Manage trading pairs, fees, and listings', 1);

INSERT INTO permissions (`key`, module, description) VALUES
('users.view', 'users', 'View user accounts'),
('users.edit', 'users', 'Edit user account details'),
('users.suspend', 'users', 'Suspend or ban users'),
('kyc.review', 'compliance', 'Approve or reject KYC submissions'),
('wallets.adjust', 'wallets', 'Manually adjust wallet balances'),
('withdrawals.approve', 'wallets', 'Approve pending withdrawals'),
('trading_pairs.manage', 'trading', 'Create/edit/disable trading pairs'),
('fees.manage', 'trading', 'Edit fee tiers and overrides'),
('orders.cancel_any', 'trading', 'Cancel any user order'),
('settings.manage', 'system', 'Edit system-wide settings'),
('admins.manage', 'system', 'Create/edit other admin accounts and roles'),
('support.manage', 'support', 'Manage support tickets'),
('announcements.manage', 'cms', 'Publish announcements and banners'),
('price_providers.manage', 'trading', 'Configure third-party data providers and API keys'),
('pair_imports.manage', 'trading', 'Trigger pair imports and approve/reject staged pairs');

INSERT INTO system_settings (setting_key, setting_value, value_type, category, description, is_public) VALUES
('maintenance_mode', 'false', 'boolean', 'general', 'Global maintenance mode toggle', 1),
('max_login_attempts', '5', 'number', 'security', 'Max failed login attempts before lockout', 0),
('default_withdrawal_review_threshold_usd', '10000', 'number', 'compliance', 'Withdrawals above this amount require manual review', 0),
('registration_enabled', 'true', 'boolean', 'general', 'Allow new user signups', 1),
('platform_name', 'Trading Platform', 'string', 'general', 'Displayed platform name', 1);

INSERT INTO price_data_providers
    (name, provider_code, provider_type, base_url, websocket_url, auth_type, auth_header_name, rate_limit_per_minute, priority, supports_pair_import, supports_realtime_price, default_sync_interval_seconds, is_active, notes)
VALUES
    ('CoinMarketCap', 'coinmarketcap', 'rest_market_data', 'https://pro-api.coinmarketcap.com', NULL, 'api_key_header', 'X-CMC_PRO_API_KEY', 30, 20, 1, 1, 60, 1,
     'Best for market-cap ranked pair/asset discovery. Requires paid plan for higher rate limits.'),
    ('CoinGecko', 'coingecko', 'rest_market_data', 'https://api.coingecko.com/api/v3', NULL, 'none', NULL, 50, 30, 1, 1, 60, 1,
     'Free tier available, broad coverage, good fallback for pair metadata and pricing.'),
    ('Binance', 'binance', 'both', 'https://api.binance.com', 'wss://stream.binance.com:9443/ws', 'api_key_header', 'X-MBX-APIKEY', 1200, 10, 0, 1, 1, 1,
     'Preferred for real-time sub-second price ticks via WebSocket; not used for pair discovery.');

-- ============================================================================
-- SECTION 13: CHARTS & MARKET ANALYTICS
-- ============================================================================

-- Per-user chart layout and indicator preferences
CREATE TABLE user_chart_preferences (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    trading_pair_id         INT UNSIGNED NULL,
    interval_code           ENUM('1m','5m','15m','30m','1h','4h','1d','1w','1M') NOT NULL DEFAULT '1h',
    chart_type              ENUM('candlestick','line','bar','area','heikin_ashi') NOT NULL DEFAULT 'candlestick',
    indicators              JSON NULL COMMENT 'Active indicator configs: [{type,params,color}]',
    drawings                JSON NULL COMMENT 'Saved drawing overlays as serialised objects',
    layout                  JSON NULL COMMENT 'UI layout preferences (show_volume, theme, etc.)',
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_pair_pref (user_id, trading_pair_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (trading_pair_id) REFERENCES trading_pairs(id) ON DELETE SET NULL
) ENGINE=InnoDB COMMENT='Per-user chart preferences scoped to a trading pair (NULL = global default)';

-- Saved chart templates/layouts users can name and recall
CREATE TABLE chart_templates (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    name                    VARCHAR(80) NOT NULL,
    description             VARCHAR(255) NULL,
    chart_type              ENUM('candlestick','line','bar','area','heikin_ashi') NOT NULL DEFAULT 'candlestick',
    indicators              JSON NOT NULL,
    layout                  JSON NULL,
    is_public               TINYINT(1) NOT NULL DEFAULT 0,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- END OF SCHEMA
-- ============================================================================

-- ============================================================================
-- SECTION 19: TRADING SIGNALS, PRICE ALERTS AND AUTOMATION
-- ============================================================================

-- Signal providers (admin-managed sources: internal analysts, bots, 3rd-party)
CREATE TABLE signal_providers (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                    VARCHAR(100) NOT NULL,
    slug                    VARCHAR(100) NOT NULL UNIQUE,
    description             TEXT NULL,
    provider_type           ENUM('internal','bot','third_party') NOT NULL DEFAULT 'internal',
    logo_url                VARCHAR(255) NULL,
    website_url             VARCHAR(255) NULL,
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    is_public               TINYINT(1) NOT NULL DEFAULT 1,   -- visible to users
    subscription_price      DECIMAL(18,8) NOT NULL DEFAULT 0.00000000, -- 0 = free
    subscription_currency   VARCHAR(20) NULL,
    win_rate                DECIMAL(5,2) NULL,               -- % cached from signal_performance
    total_signals           INT UNSIGNED NOT NULL DEFAULT 0,
    created_by              BIGINT UNSIGNED NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB COMMENT='Sources that publish trading signals';

-- Trading signals published by providers or admins
CREATE TABLE trading_signals (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id             INT UNSIGNED NOT NULL,
    trading_pair_id         INT UNSIGNED NULL,
    pair_symbol             VARCHAR(30) NULL,                -- denormalised snapshot
    signal_type             ENUM('buy','sell','hold','close_long','close_short','watch') NOT NULL,
    market_type             ENUM('spot','futures','margin') NOT NULL DEFAULT 'spot',
    timeframe               ENUM('1m','5m','15m','30m','1h','4h','1d','1w') NOT NULL DEFAULT '1h',
    entry_price             DECIMAL(36,18) NULL,
    entry_price_high        DECIMAL(36,18) NULL,             -- entry zone high
    entry_price_low         DECIMAL(36,18) NULL,             -- entry zone low
    take_profit_1           DECIMAL(36,18) NULL,
    take_profit_2           DECIMAL(36,18) NULL,
    take_profit_3           DECIMAL(36,18) NULL,
    stop_loss               DECIMAL(36,18) NULL,
    leverage                TINYINT UNSIGNED NULL,           -- for futures signals
    risk_reward_ratio       DECIMAL(8,4) NULL,
    confidence_score        TINYINT UNSIGNED NULL,           -- 0-100
    analysis_text           TEXT NULL,
    chart_url               VARCHAR(255) NULL,
    tags                    JSON NULL,                       -- ["breakout","ema_cross","rsi_oversold"]
    status                  ENUM('active','hit_tp','hit_sl','cancelled','expired') NOT NULL DEFAULT 'active',
    hit_at                  DATETIME NULL,
    profit_pct              DECIMAL(10,4) NULL,              -- actual result when closed
    views_count             INT UNSIGNED NOT NULL DEFAULT 0,
    likes_count             INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at              DATETIME NULL,
    published_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_signal_provider  (provider_id),
    INDEX idx_signal_pair      (trading_pair_id),
    INDEX idx_signal_status    (status),
    INDEX idx_signal_published (published_at),
    FOREIGN KEY (provider_id) REFERENCES signal_providers(id) ON DELETE CASCADE,
    FOREIGN KEY (trading_pair_id) REFERENCES trading_pairs(id) ON DELETE SET NULL
) ENGINE=InnoDB COMMENT='Trading signals with entry/TP/SL targets';

-- User subscriptions to signal providers
CREATE TABLE signal_subscriptions (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    provider_id             INT UNSIGNED NOT NULL,
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    notify_email            TINYINT(1) NOT NULL DEFAULT 1,
    notify_platform         TINYINT(1) NOT NULL DEFAULT 1,
    subscribed_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at              DATETIME NULL,
    UNIQUE KEY uq_user_provider (user_id, provider_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES signal_providers(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='User subscriptions to signal providers';

-- User reactions/bookmarks on signals
CREATE TABLE signal_interactions (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    signal_id               BIGINT UNSIGNED NOT NULL,
    interaction_type        ENUM('like','bookmark','view') NOT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_signal_type (user_id, signal_id, interaction_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (signal_id) REFERENCES trading_signals(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Signal provider performance snapshots (updated by cron/background job)
CREATE TABLE signal_performance (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id             INT UNSIGNED NOT NULL,
    period                  ENUM('7d','30d','90d','all') NOT NULL DEFAULT '30d',
    total_signals           INT UNSIGNED NOT NULL DEFAULT 0,
    active_signals          INT UNSIGNED NOT NULL DEFAULT 0,
    hit_tp_count            INT UNSIGNED NOT NULL DEFAULT 0,
    hit_sl_count            INT UNSIGNED NOT NULL DEFAULT 0,
    cancelled_count         INT UNSIGNED NOT NULL DEFAULT 0,
    win_rate                DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    avg_profit_pct          DECIMAL(10,4) NULL,
    avg_loss_pct            DECIMAL(10,4) NULL,
    avg_rr_ratio            DECIMAL(8,4) NULL,
    best_signal_id          BIGINT UNSIGNED NULL,
    worst_signal_id         BIGINT UNSIGNED NULL,
    total_return_pct        DECIMAL(10,4) NULL,
    calculated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_provider_period (provider_id, period),
    FOREIGN KEY (provider_id) REFERENCES signal_providers(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='Cached performance metrics per provider per period';

-- User-defined price alerts
CREATE TABLE price_alerts (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    trading_pair_id         INT UNSIGNED NOT NULL,
    pair_symbol             VARCHAR(30) NOT NULL,
    alert_type              ENUM('price_above','price_below','percent_change_up','percent_change_down',
                                  'volume_spike','rsi_overbought','rsi_oversold',
                                  'ema_cross_up','ema_cross_down','new_high','new_low') NOT NULL,
    threshold_value         DECIMAL(36,18) NOT NULL,
    timeframe               ENUM('1m','5m','15m','1h','4h','1d') NOT NULL DEFAULT '1h',
    note                    VARCHAR(255) NULL,
    notify_email            TINYINT(1) NOT NULL DEFAULT 1,
    notify_platform         TINYINT(1) NOT NULL DEFAULT 1,
    is_recurring            TINYINT(1) NOT NULL DEFAULT 0,   -- 0 = one-shot, 1 = keeps firing
    status                  ENUM('active','triggered','paused','deleted') NOT NULL DEFAULT 'active',
    last_triggered_at       DATETIME NULL,
    trigger_count           INT UNSIGNED NOT NULL DEFAULT 0,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_alert_user    (user_id),
    INDEX idx_alert_pair    (trading_pair_id),
    INDEX idx_alert_status  (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (trading_pair_id) REFERENCES trading_pairs(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='User-defined price and indicator alerts';

-- Alert trigger history (one row per fire event)
CREATE TABLE alert_history (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alert_id                BIGINT UNSIGNED NOT NULL,
    user_id                 BIGINT UNSIGNED NOT NULL,
    trading_pair_id         INT UNSIGNED NOT NULL,
    alert_type              VARCHAR(50) NOT NULL,
    threshold_value         DECIMAL(36,18) NOT NULL,
    triggered_value         DECIMAL(36,18) NOT NULL,         -- actual price/indicator at trigger
    notification_sent       TINYINT(1) NOT NULL DEFAULT 0,
    triggered_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (alert_id) REFERENCES price_alerts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='Historical log of all alert trigger events';

-- User-defined automation rules (if condition THEN action)
CREATE TABLE automation_rules (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    name                    VARCHAR(100) NOT NULL,
    description             VARCHAR(255) NULL,
    trigger_type            ENUM('price_above','price_below','percent_change_up','percent_change_down',
                                  'rsi_overbought','rsi_oversold','ema_cross_up','ema_cross_down',
                                  'signal_received','order_filled','position_pnl_pct') NOT NULL,
    trigger_pair_id         INT UNSIGNED NULL,
    trigger_value           DECIMAL(36,18) NOT NULL,
    trigger_timeframe       ENUM('1m','5m','15m','1h','4h','1d') NOT NULL DEFAULT '1h',
    action_type             ENUM('place_market_order','place_limit_order','close_position',
                                  'cancel_open_orders','send_notification','webhook_call') NOT NULL,
    action_pair_id          INT UNSIGNED NULL,
    action_side             ENUM('buy','sell') NULL,
    action_quantity         DECIMAL(36,18) NULL,
    action_quantity_type    ENUM('fixed','pct_balance') NOT NULL DEFAULT 'fixed',
    action_price            DECIMAL(36,18) NULL,             -- for limit orders
    action_params           JSON NULL,                       -- extra params (webhook URL, message, etc.)
    cooldown_minutes        SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    max_executions          SMALLINT UNSIGNED NULL,          -- NULL = unlimited
    execution_count         INT UNSIGNED NOT NULL DEFAULT 0,
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    last_executed_at        DATETIME NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_automation_user   (user_id),
    INDEX idx_automation_active (is_active),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (trigger_pair_id) REFERENCES trading_pairs(id) ON DELETE SET NULL,
    FOREIGN KEY (action_pair_id) REFERENCES trading_pairs(id) ON DELETE SET NULL
) ENGINE=InnoDB COMMENT='User-defined if-this-then-that automation rules';

-- Execution log for automation rules
CREATE TABLE automation_rule_logs (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rule_id                 BIGINT UNSIGNED NOT NULL,
    user_id                 BIGINT UNSIGNED NOT NULL,
    trigger_type            VARCHAR(50) NOT NULL,
    trigger_value           DECIMAL(36,18) NOT NULL,
    action_type             VARCHAR(50) NOT NULL,
    action_result           ENUM('success','failed','skipped') NOT NULL DEFAULT 'success',
    result_detail           TEXT NULL,
    executed_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rule_id) REFERENCES automation_rules(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='Execution log for automation rule firings';

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- KYC VERIFICATION AND DOCUMENT MANAGEMENT SYSTEM
-- =============================================================================

-- KYC audit trail – every status change, submission, and admin action
CREATE TABLE kyc_audit_log (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    document_id             BIGINT UNSIGNED NULL,
    actor_type              ENUM('user','admin','system') NOT NULL DEFAULT 'user',
    actor_id                BIGINT UNSIGNED NULL,
    action                  VARCHAR(80) NOT NULL,
    old_status              VARCHAR(30) NULL,
    new_status              VARCHAR(30) NULL,
    notes                   VARCHAR(500) NULL,
    ip_address              VARCHAR(45) NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_kyc_audit_user (user_id),
    INDEX idx_kyc_audit_doc  (document_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='Complete audit trail for all KYC events';

-- Configurable document requirements per KYC level
CREATE TABLE kyc_requirements (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kyc_level               TINYINT UNSIGNED NOT NULL,
    document_type           VARCHAR(50) NOT NULL,
    is_required             TINYINT(1) NOT NULL DEFAULT 1,
    is_enabled              TINYINT(1) NOT NULL DEFAULT 1,
    display_name            VARCHAR(100) NOT NULL,
    description             VARCHAR(255) NULL,
    sort_order              TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_kyc_req_level (kyc_level, is_enabled)
) ENGINE=InnoDB COMMENT='Configurable KYC document requirements per level';

-- Risk scoring per user for compliance purposes
CREATE TABLE kyc_risk_assessments (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL UNIQUE,
    risk_score              TINYINT UNSIGNED NOT NULL DEFAULT 0,
    risk_level              ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
    risk_factors            JSON NULL,
    last_assessed_at        DATETIME NULL,
    assessed_by             BIGINT UNSIGNED NULL,
    notes                   VARCHAR(500) NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='KYC AML risk assessment scores per user';

-- Seed default KYC requirements
INSERT INTO kyc_requirements (kyc_level, document_type, display_name, description, sort_order) VALUES
(1, 'selfie',           'Selfie with ID',       'Clear photo of your face holding your government ID',  1),
(2, 'passport',         'Passport',             'Valid passport (any country)',                          1),
(2, 'national_id',      'National ID',          'Government-issued national identity card',             2),
(2, 'drivers_license',  "Driver's License",     'Valid driver''s license with photo',                   3),
(3, 'proof_of_address', 'Proof of Address',     'Utility bill or bank statement (< 3 months old)',      1),
(3, 'corporate_doc',    'Corporate Document',   'For business accounts: registration certificate',      2);
