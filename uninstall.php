<?php
/**
 * Uninstall cleanup for Event Hub.
 * - Verwijder eigen CPT content (events + e-mails) enkel als Event Hub de CPT zelf registreerde.
 * - Gebruik externe CPT? Laat die posts ongemoeid.
 * - Opruimen van opties, logs en DB-tabel voor registraties.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Basisopties ophalen zonder afhankelijk te zijn van autoloader.
$email_opts   = get_option('event_hub_email_settings', []);
$general_opts = get_option('event_hub_general_settings', []);

$use_external_cpt = !empty($general_opts['use_external_cpt']);
$cpt_slug         = $general_opts['cpt_slug'] ?? 'eh_session';
$email_cpt        = 'eh_email';

// DB tabel voor registraties
global $wpdb;
$registrations_table = $wpdb->prefix . 'eh_session_registrations';

// Helper: delete posts for a CPT.
$delete_cpt_posts = static function (string $post_type): void {
    $posts = get_posts([
        'post_type'      => $post_type,
        'post_status'    => 'any',
        'numberposts'    => -1,
        'fields'         => 'ids',
        'suppress_filters' => true,
    ]);
    if ($posts) {
        foreach ($posts as $post_id) {
            wp_delete_post($post_id, true);
        }
    }
};

// Alleen het door Event Hub geregistreerde CPT opruimen wanneer het geen externe CPT is.
if (!$use_external_cpt && $cpt_slug) {
    $delete_cpt_posts($cpt_slug);
}

// E-mail sjablonen (altijd plugin-CPT)
$delete_cpt_posts($email_cpt);

// Drop registratie tabel indien aanwezig.
if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $registrations_table)) === $registrations_table) {
    $wpdb->query("DROP TABLE IF EXISTS {$registrations_table}");
}

// Opties en transients opruimen.
delete_option('event_hub_email_settings');
delete_option('event_hub_general_settings');
delete_option('event_hub_db_version');
delete_option('event_hub_logs');
delete_transient('event_hub_show_cpt_prompt');

// Cron key verwijderen indien aangemaakt.
delete_option('event_hub_cron_key');
