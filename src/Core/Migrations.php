<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use wpdb;

final class Migrations
{
    private const OPTION_KEY = 'fp_resv_db_version';
    private const DB_VERSION = '2025.10.09';

    public static function run(): void
    {
        global $wpdb;

        $installedVersion = get_option(self::OPTION_KEY);
        if ($installedVersion === self::DB_VERSION) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();

        foreach (self::getTableDefinitions($wpdb, $charsetCollate) as $sql) {
            dbDelta($sql);
        }

        update_option(self::OPTION_KEY, self::DB_VERSION);
    }

    /**
     * @return array<int, string>
     */
    private static function getTableDefinitions(wpdb $wpdb, string $charsetCollate): array
    {
        $prefix = $wpdb->prefix;
        $tables = [];

        $reservationsTable = $prefix . 'fp_reservations';
        $tables[]          = <<<SQL
CREATE TABLE {$reservationsTable} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    status VARCHAR(20) NOT NULL,
    date DATE NOT NULL,
    time TIME NOT NULL,
    party SMALLINT UNSIGNED NOT NULL,
    room_id BIGINT UNSIGNED DEFAULT NULL,
    table_id BIGINT UNSIGNED DEFAULT NULL,
    customer_id BIGINT UNSIGNED DEFAULT NULL,
    notes TEXT,
    allergies TEXT,
    value DECIMAL(10,2) DEFAULT NULL,
    currency VARCHAR(3) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    visited_at DATETIME DEFAULT NULL,
    utm_source VARCHAR(100) DEFAULT NULL,
    utm_medium VARCHAR(100) DEFAULT NULL,
    utm_campaign VARCHAR(150) DEFAULT NULL,
    lang VARCHAR(10) DEFAULT NULL,
    location_id VARCHAR(64) DEFAULT NULL,
    calendar_event_id VARCHAR(191) DEFAULT NULL,
    calendar_synced_at DATETIME DEFAULT NULL,
    calendar_sync_status VARCHAR(20) DEFAULT NULL,
    calendar_last_error TEXT DEFAULT NULL,
    request_id VARCHAR(100) DEFAULT NULL,
    PRIMARY KEY  (id),
    KEY status (status),
    KEY date (date),
    KEY customer_id (customer_id),
    KEY room_table (room_id, table_id),
    KEY lang (lang),
    KEY calendar_event_id (calendar_event_id),
    KEY request_id (request_id)
) {$charsetCollate};
SQL;

        $customersTable = $prefix . 'fp_customers';
        $tables[]       = <<<SQL
CREATE TABLE {$customersTable} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(191) NOT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    lang VARCHAR(10) DEFAULT NULL,
    marketing_consent TINYINT(1) NOT NULL DEFAULT 0,
    profiling_consent TINYINT(1) NOT NULL DEFAULT 0,
    consent_ts DATETIME DEFAULT NULL,
    consent_version VARCHAR(20) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    UNIQUE KEY email (email)
) {$charsetCollate};
SQL;

        $roomsTable = $prefix . 'fp_rooms';
        $tables[]   = <<<SQL
CREATE TABLE {$roomsTable} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT NULL,
    capacity SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    order_index SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (id)
) {$charsetCollate};
SQL;

        $tablesTable = $prefix . 'fp_tables';
        $tables[]    = <<<SQL
CREATE TABLE {$tablesTable} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    room_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL,
    seats_min SMALLINT UNSIGNED DEFAULT NULL,
    seats_std SMALLINT UNSIGNED DEFAULT NULL,
    seats_max SMALLINT UNSIGNED DEFAULT NULL,
    attributes_json LONGTEXT DEFAULT NULL,
    join_group VARCHAR(60) DEFAULT NULL,
    pos_x DECIMAL(8,2) DEFAULT NULL,
    pos_y DECIMAL(8,2) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'available',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY room_id (room_id),
    KEY join_group (join_group),
    UNIQUE KEY room_code (room_id, code)
) {$charsetCollate};
SQL;

        $closuresTable = $prefix . 'fp_closures';
        $tables[]      = <<<SQL
CREATE TABLE {$closuresTable} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    scope VARCHAR(30) NOT NULL,
    room_id BIGINT UNSIGNED DEFAULT NULL,
    table_id BIGINT UNSIGNED DEFAULT NULL,
    type VARCHAR(30) NOT NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    recurrence_json LONGTEXT DEFAULT NULL,
    capacity_override_json LONGTEXT DEFAULT NULL,
    note TEXT,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY scope (scope),
    KEY period (start_at, end_at)
) {$charsetCollate};
SQL;

        $eventsTable = $prefix . 'fp_events';
        $tables[]    = <<<SQL
CREATE TABLE {$eventsTable} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(191) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    capacity INT UNSIGNED DEFAULT NULL,
    price DECIMAL(10,2) DEFAULT NULL,
    currency VARCHAR(3) DEFAULT NULL,
    settings_json LONGTEXT DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    lang VARCHAR(10) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    UNIQUE KEY slug (slug),
    KEY status (status),
    KEY language (lang)
) {$charsetCollate};
SQL;

        $ticketsTable = $prefix . 'fp_tickets';
        $tables[]     = <<<SQL
CREATE TABLE {$ticketsTable} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    reservation_id BIGINT UNSIGNED DEFAULT NULL,
    customer_id BIGINT UNSIGNED DEFAULT NULL,
    category VARCHAR(100) DEFAULT NULL,
    price DECIMAL(10,2) DEFAULT NULL,
    currency VARCHAR(3) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    qr_code_text TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY event_id (event_id),
    KEY reservation_id (reservation_id),
    KEY customer_id (customer_id)
) {$charsetCollate};
SQL;

        $paymentsTable = $prefix . 'fp_payments';
        $tables[]      = <<<SQL
CREATE TABLE {$paymentsTable} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(50) NOT NULL,
    type VARCHAR(30) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL,
    status VARCHAR(20) NOT NULL,
    external_id VARCHAR(191) DEFAULT NULL,
    meta_json LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY reservation_id (reservation_id),
    KEY status (status)
) {$charsetCollate};
SQL;

        $surveysTable = $prefix . 'fp_surveys';
        $tables[]     = <<<SQL
CREATE TABLE {$surveysTable} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(191) NOT NULL,
    lang VARCHAR(10) DEFAULT NULL,
    stars_food TINYINT UNSIGNED DEFAULT NULL,
    stars_service TINYINT UNSIGNED DEFAULT NULL,
    stars_atmosphere TINYINT UNSIGNED DEFAULT NULL,
    nps TINYINT UNSIGNED DEFAULT NULL,
    comment TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    review_link_shown TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY  (id),
    KEY reservation_id (reservation_id),
    KEY email (email)
) {$charsetCollate};
SQL;

        $postVisitTable = $prefix . 'fp_postvisit_jobs';
        $tables[]       = <<<SQL
CREATE TABLE {$postVisitTable} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id BIGINT UNSIGNED NOT NULL,
    run_at DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    channel VARCHAR(40) NOT NULL,
    last_error TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY run_at (run_at),
    KEY status (status)
) {$charsetCollate};
SQL;

        $mailLogTable = $prefix . 'fp_mail_log';
        $tables[]     = <<<SQL
CREATE TABLE {$mailLogTable} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id BIGINT UNSIGNED DEFAULT NULL,
    to_emails TEXT NOT NULL,
    subject VARCHAR(191) NOT NULL,
    first_line VARCHAR(191) DEFAULT NULL,
    status VARCHAR(20) NOT NULL,
    error TEXT,
    content_type VARCHAR(50) NOT NULL DEFAULT 'text/html',
    body LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY reservation_id (reservation_id),
    KEY status (status)
) {$charsetCollate};
SQL;

        $brevoLogTable = $prefix . 'fp_brevo_log';
        $tables[]      = <<<SQL
CREATE TABLE {$brevoLogTable} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id BIGINT UNSIGNED DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    payload_snippet TEXT,
    status VARCHAR(20) NOT NULL,
    error TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY reservation_id (reservation_id),
    KEY status (status)
) {$charsetCollate};
SQL;

        $auditLogTable = $prefix . 'fp_audit_log';
        $tables[]      = <<<SQL
CREATE TABLE {$auditLogTable} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_id BIGINT UNSIGNED DEFAULT NULL,
    actor_role VARCHAR(60) DEFAULT NULL,
    action VARCHAR(60) NOT NULL,
    entity VARCHAR(60) NOT NULL,
    entity_id BIGINT UNSIGNED DEFAULT NULL,
    before_json LONGTEXT DEFAULT NULL,
    after_json LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip VARCHAR(45) DEFAULT NULL,
    PRIMARY KEY  (id),
    KEY actor (actor_id),
    KEY entity (entity, entity_id)
) {$charsetCollate};
SQL;

        return $tables;
    }
}
