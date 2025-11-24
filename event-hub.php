<?php
/**
 * Plugin Name: Event Hub
 * Description: Event Hub - beheer infosessies en opleidingen met inschrijvingen, e-mails en Elementor-widgets.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Michaël Redant
 * Text Domain: event-hub
 */

defined('ABSPATH') || exit;

// Basic constants
define('EVENT_HUB_VERSION', '1.0.0');
define('EVENT_HUB_FILE', __FILE__);
define('EVENT_HUB_PATH', plugin_dir_path(__FILE__));
define('EVENT_HUB_URL', plugin_dir_url(__FILE__));

// Simple PSR-4 style autoloader for the plugin namespace
spl_autoload_register(static function ($class) {
    $prefix = 'EventHub\\';
    $base_dir = EVENT_HUB_PATH . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Activation/Deactivation hooks
register_activation_hook(__FILE__, static function () {
    if (!class_exists('EventHub\\Activator')) {
        require_once EVENT_HUB_PATH . 'includes/Activator.php';
    }
    \EventHub\Activator::activate();
});

register_deactivation_hook(__FILE__, static function () {
    // Currently no recurring cron cleanups needed beyond defaults
});

// Bootstrap plugin after plugins_loaded
add_action('plugins_loaded', static function () {
    if (!class_exists('EventHub\\Plugin')) {
        return; // Autoloader should load it; bail if not found
    }
    $plugin = new \EventHub\Plugin();
    $plugin->init();
});
