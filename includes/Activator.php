<?php
namespace EventHub;

defined('ABSPATH') || exit;

class Activator
{
    public static function activate(): void
    {
        self::create_tables();
        // Option defaults for settings
        if (!get_option('event_hub_email_settings')) {
            update_option('event_hub_email_settings', self::default_email_settings());
        }
        update_option('event_hub_db_version', \EventHub\Migrations::DB_VERSION);
        // Trigger first-run CPT chooser notice
        set_transient('event_hub_show_cpt_prompt', 1, DAY_IN_SECONDS);
        self::seed_default_templates();
    }

    public static function create_tables(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'eh_session_registrations';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id bigint(20) unsigned NOT NULL,
            first_name varchar(190) NOT NULL,
            last_name varchar(190) NOT NULL,
            email varchar(190) NOT NULL,
            phone varchar(190) NULL,
            company varchar(190) NULL,
            vat varchar(64) NULL,
            role varchar(190) NULL,
            people_count int(11) NOT NULL DEFAULT 1,
            status varchar(32) NOT NULL DEFAULT 'registered',
            consent_marketing tinyint(1) NOT NULL DEFAULT 0,
            cancel_token varchar(64) NULL,
            extra_data longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY status (status),
            UNIQUE KEY uniq_session_email (session_id, email),
            UNIQUE KEY uniq_cancel_token (cancel_token)
        ) $charset_collate;";

        dbDelta($sql);
    }

    private static function default_email_settings(): array
    {
        return [
            'from_name' => get_bloginfo('name'),
            'from_email' => get_option('admin_email'),
            // Legacy days key blijft staan voor backwards compat, maar we sturen op uren.
            'reminder_offset_hours' => 24,
            'reminder_offset_days' => 3,
            'followup_offset_hours' => 24,
            'cancel_cutoff_hours' => 24,
            'confirmation_timing_mode' => 'immediate',
            'confirmation_timing_hours' => 24,
            'waitlist_timing_mode' => 'immediate',
            'waitlist_timing_hours' => 24,
            'custom_placeholders_raw' => '',
            'custom_placeholders' => [],
        ];
    }

    private static function seed_default_templates(): void
    {
        $existing = get_posts([
            'post_type' => CPT_Email::CPT,
            'posts_per_page' => 1,
            'post_status' => 'any',
            'fields' => 'ids',
        ]);
        if ($existing) {
            return;
        }

        $templates = [
            [
                'post_title' => __('Bevestiging standaard', 'event-hub'),
                'subject'    => __('Je inschrijving voor {event_title}', 'event-hub'),
                'body'       => __("Dag {first_name},\n\nBedankt voor je inschrijving voor {event_title} op {event_date} om {event_time}.\nLocatie: {event_location}\nOnline link: {event_online_link}\nAantal personen: {people_count}\n\nKan je toch niet komen? Annuleer hier: {cancel_link}\n\nTot snel!\n{site_name}", 'event-hub'),
            ],
            [
                'post_title' => __('Herinnering standaard', 'event-hub'),
                'subject'    => __('Herinnering: {event_title}', 'event-hub'),
                'body'       => __("Dag {first_name},\n\nBinnenkort vindt {event_title} plaats op {event_date} om {event_time}.\nLocatie: {event_location}\nOnline link: {event_online_link}\n\nKan je niet aanwezig zijn? Annuleer je deelname via: {cancel_link}\n\nTot dan!\n{site_name}", 'event-hub'),
            ],
            [
                'post_title' => __('Bedankt standaard', 'event-hub'),
                'subject'    => __('Bedankt voor je aanwezigheid bij {event_title}', 'event-hub'),
                'body'       => __("Dag {first_name},\n\nBedankt om aanwezig te zijn op {event_title}. Laat ons zeker weten wat je ervan vond.\n\nVriendelijke groeten,\n{site_name}", 'event-hub'),
            ],
        ];

        foreach ($templates as $tpl) {
            $post_id = wp_insert_post([
                'post_type'   => CPT_Email::CPT,
                'post_status' => 'publish',
                'post_title'  => $tpl['post_title'],
            ]);
            if ($post_id && !is_wp_error($post_id)) {
                update_post_meta($post_id, '_eh_email_subject', $tpl['subject']);
                update_post_meta($post_id, '_eh_email_body', $tpl['body']);
            }
        }
    }
}
