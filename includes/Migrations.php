<?php
namespace EventHub;

defined('ABSPATH') || exit;

/**
 * Simple schema/version manager for Event Hub.
 * Keeps DB in sync on upgrades without breaking existing installs.
 */
class Migrations
{
    public const DB_VERSION = '1.3.0';
    private const OPTION = 'event_hub_db_version';

    public function run(): void
    {
        $current = get_option(self::OPTION, '0');
        if (version_compare($current, self::DB_VERSION, '>=')) {
            return; // Up to date
        }

        // Current schema: ensure table exists/updated via dbDelta
        Activator::create_tables();

        update_option(self::OPTION, self::DB_VERSION);
    }
}
