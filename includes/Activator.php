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
            occurrence_id bigint(20) unsigned NOT NULL DEFAULT 0,
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
            KEY occurrence_id (occurrence_id),
            KEY status (status),
            UNIQUE KEY uniq_session_occurrence_email (session_id, occurrence_id, email),
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

    public static function seed_default_templates(): void
    {
        $templates = self::get_default_email_templates();
        if (!$templates) {
            return;
        }

        foreach ($templates as $tpl) {
            $key = isset($tpl['key']) ? (string) $tpl['key'] : '';
            $title = isset($tpl['post_title']) ? (string) $tpl['post_title'] : '';
            $body = isset($tpl['body']) ? (string) $tpl['body'] : '';
            if ($title === '') {
                continue;
            }
            if ($body === '') {
                continue;
            }
            if (self::default_template_exists($key, $title)) {
                continue;
            }
            $post_id = wp_insert_post([
                'post_type'   => CPT_Email::CPT,
                'post_status' => 'publish',
                'post_title'  => $title,
            ]);
            if ($post_id && !is_wp_error($post_id)) {
                update_post_meta($post_id, '_eh_email_subject', (string) ($tpl['subject'] ?? ''));
                update_post_meta($post_id, '_eh_email_body', $body);
                if (!empty($tpl['type'])) {
                    update_post_meta($post_id, '_eh_email_type', (string) $tpl['type']);
                }
                if ($key !== '') {
                    update_post_meta($post_id, '_eh_email_system_key', $key);
                }
            }
        }
    }

    private static function default_template_exists(string $key, string $title): bool
    {
        if ($key !== '') {
            $existing = get_posts([
                'post_type' => CPT_Email::CPT,
                'posts_per_page' => 1,
                'post_status' => 'any',
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => '_eh_email_system_key',
                        'value' => $key,
                        'compare' => '=',
                    ],
                ],
            ]);
            if ($existing) {
                return true;
            }
        }

        $query = new \WP_Query([
            'post_type' => CPT_Email::CPT,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'title' => $title,
            'fields' => 'ids',
            'suppress_filters' => true,
        ]);
        return $query->have_posts();
    }

    private static function load_template_body(string $filename): string
    {
        $path = trailingslashit(EVENT_HUB_PATH) . 'templates/emails/' . $filename;
        if (!file_exists($path)) {
            return '';
        }
        $contents = file_get_contents($path);
        return $contents !== false ? $contents : '';
    }

    /**
     * @return array<int,array{key:string,post_title:string,subject:string,body:string,type?:string}>
     */
    private static function get_default_email_templates(): array
    {
        return [
            [
                'key' => 'waitlist_default',
                'post_title' => __('Wachtlijst bevestiging (standaard)', 'event-hub'),
                'subject'    => __('Bevestiging wachtlijst - {event_title}', 'event-hub'),
                'body'       => self::load_template_body('waitlist.html'),
            ],
            [
                'key' => 'waitlist_promotion_default',
                'post_title' => __('Wachtlijst promotie (standaard)', 'event-hub'),
                'subject'    => __('Je bent ingeschreven - {event_title}', 'event-hub'),
                'body'       => self::load_template_body('waitlist-promotion.html'),
            ],
            [
                'key' => 'confirmation_default',
                'post_title' => __('Bevestiging inschrijving (standaard)', 'event-hub'),
                'subject'    => __('Je bent ingeschreven - {event_title}', 'event-hub'),
                'body'       => self::load_template_body('confirmation.html'),
                'type'       => 'confirmation',
            ],
            [
                'key' => 'reminder_default',
                'post_title' => __('Herinnering (standaard)', 'event-hub'),
                'subject'    => __('Herinnering - {event_title}', 'event-hub'),
                'body'       => self::load_template_body('reminder.html'),
                'type'       => 'reminder',
            ],
            [
                'key' => 'followup_default',
                'post_title' => __('Nadien (standaard)', 'event-hub'),
                'subject'    => __('Bedankt - {event_title}', 'event-hub'),
                'body'       => self::load_template_body('followup.html'),
                'type'       => 'followup',
            ],
            [
                'key' => 'event_cancelled_default',
                'post_title' => __('Event geannuleerd (standaard)', 'event-hub'),
                'subject'    => __('Event geannuleerd - {event_title}', 'event-hub'),
                'body'       => self::load_template_body('event-cancelled.html'),
            ],
            [
                'key' => 'registration_cancelled_default',
                'post_title' => __('Inschrijving geannuleerd (standaard)', 'event-hub'),
                'subject'    => __('Inschrijving geannuleerd - {event_title}', 'event-hub'),
                'body'       => self::load_template_body('registration-cancelled.html'),
            ],
        ];
    }
}
