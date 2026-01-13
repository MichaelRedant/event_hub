<?php
namespace EventHub;

defined('ABSPATH') || exit;

/**
 * Simple schema/version manager for Event Hub.
 * Keeps DB in sync on upgrades without breaking existing installs.
 */
class Migrations
{
    public const DB_VERSION = '1.4.1';
    private const OPTION = 'event_hub_db_version';

    public function run(): void
    {
        $current = get_option(self::OPTION, '0');
        if (version_compare($current, self::DB_VERSION, '>=')) {
            return; // Up to date
        }

        // Current schema: ensure table exists/updated via dbDelta
        Activator::create_tables();
        $this->maybe_update_registration_indexes();
        Activator::seed_default_templates();

        update_option(self::OPTION, self::DB_VERSION);
    }

    private function maybe_update_registration_indexes(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . Registrations::TABLE;
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$table}", ARRAY_A);
        if (!$indexes) {
            return;
        }
        $by_name = [];
        foreach ($indexes as $index) {
            $name = $index['Key_name'] ?? '';
            if ($name !== '') {
                $by_name[$name][] = $index;
            }
        }

        if (isset($by_name['uniq_session_email'])) {
            $wpdb->query("ALTER TABLE {$table} DROP INDEX uniq_session_email");
        }
        if (!isset($by_name['uniq_session_occurrence_email'])) {
            $wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY uniq_session_occurrence_email (session_id, occurrence_id, email)");
        }
    }
}
