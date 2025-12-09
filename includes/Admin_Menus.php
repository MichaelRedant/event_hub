<?php

namespace EventHub;



defined('ABSPATH') || exit;



class Admin_Menus

{

    private Registrations $registrations;

    private Emails $emails;

    private Settings $settings;

    private ?Logger $logger;



    public function __construct(Registrations $registrations, Emails $emails, Settings $settings, ?Logger $logger = null)

    {

        $this->registrations = $registrations;

        $this->emails        = $emails;

        $this->settings      = $settings;

        $this->logger        = $logger;

    }



    public function register_menus(): void

    {

        add_menu_page(

            __('Event Hub', 'event-hub'),

            __('Event Hub', 'event-hub'),

            'edit_posts',

            'event-hub',

            [$this, 'render_info_page'],

            'dashicons-calendar-alt',

            25

        );



        add_submenu_page(

            'event-hub',

            __('Evenementen', 'event-hub'),

            __('Evenementen', 'event-hub'),

            'edit_posts',

            'edit.php?post_type=' . Settings::get_cpt_slug()

        );



        add_submenu_page(

            'event-hub',

            __('E-mailsjablonen', 'event-hub'),

            __('E-mailsjablonen', 'event-hub'),

            'edit_posts',

            'edit.php?post_type=' . CPT_Email::CPT

        );



        add_submenu_page(

            'event-hub',

            __('Inschrijvingen', 'event-hub'),

            __('Inschrijvingen', 'event-hub'),

            'edit_posts',

            'event-hub-registrations',

            [$this, 'render_registrations_page']

        );



        add_submenu_page(

            'event-hub',

            __('Eventkalender', 'event-hub'),

            __('Eventkalender', 'event-hub'),

            'edit_posts',

            'event-hub-calendar',

            [$this, 'render_calendar_page']

        );



        add_submenu_page(

            'event-hub',

            __('E-mailinstellingen', 'event-hub'),

            __('E-mailinstellingen', 'event-hub'),

            'edit_posts',

            'event-hub-settings',

            [$this->settings, 'render_page']

        );



        add_submenu_page(

            'event-hub',

            __('Algemene instellingen', 'event-hub'),

            __('Algemene instellingen', 'event-hub'),

            'manage_options',

            'event-hub-general',

            [$this->settings, 'render_general_page']

        );



        add_submenu_page(

            'event-hub',

            __('Eventdashboard', 'event-hub'),

            __('Eventdashboard', 'event-hub'),

            'edit_posts',

            'event-hub-event',

            [$this, 'render_event_dashboard']

        );

        add_submenu_page(

            'event-hub',

            __('Statistieken', 'event-hub'),

            __('Statistieken', 'event-hub'),

            'edit_posts',

            'event-hub-stats',

            [$this, 'render_stats_page']

        );

        add_submenu_page(

            'event-hub',

            __('Logs', 'event-hub'),

            __('Logs', 'event-hub'),

            'manage_options',

            'event-hub-logs',

            [$this, 'render_logs_page']

        );

        add_action('admin_head', static function () {

            remove_submenu_page('event-hub', 'event-hub-event');

        });

        add_action('admin_init', [$this, 'maybe_process_event_actions']);

    }



    public function render_info_page(): void

    {

        if (!current_user_can('edit_posts')) {

            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));

        }

        $cpt_slug = Settings::get_cpt_slug();

        echo '<div class="wrap eh-admin">';
        echo '<h1>' . esc_html__('Event Hub', 'event-hub') . '</h1>';
        echo '<p style="max-width:720px;color:#52616b;">' . esc_html__('Kies een startpunt om meteen te werken. Alle belangrijke onderdelen van de plugin in één overzicht.', 'event-hub') . '</p>';

        $cards = [
            [
                'title' => __('Evenementen', 'event-hub'),
                'text'  => __('Maak nieuwe events, bewerk bestaande en beheer sessies.', 'event-hub'),
                'link'  => admin_url('edit.php?post_type=' . $cpt_slug),
                'cta'   => __('Ga naar evenementen', 'event-hub'),
            ],
            [
                'title' => __('Nieuwe inschrijving', 'event-hub'),
                'text'  => __('Voeg handmatig een deelnemer toe of open de lijst.', 'event-hub'),
                'link'  => add_query_arg(['page' => 'event-hub-registrations', 'action' => 'new'], admin_url('admin.php')),
                'cta'   => __('Open inschrijvingen', 'event-hub'),
            ],
            [
                'title' => __('Kalender', 'event-hub'),
                'text'  => __('Bekijk events in kalenderweergave en voeg snel toe.', 'event-hub'),
                'link'  => admin_url('admin.php?page=event-hub-calendar'),
                'cta'   => __('Open kalender', 'event-hub'),
            ],
            [
                'title' => __('E-mails & sjablonen', 'event-hub'),
                'text'  => __('Beheer automatische mails en algemene mailinstellingen.', 'event-hub'),
                'link'  => admin_url('edit.php?post_type=' . CPT_Email::CPT),
                'cta'   => __('Naar e-mails', 'event-hub'),
            ],
            [
                'title' => __('Algemene instellingen', 'event-hub'),
                'text'  => __('Kies CPT, formulieren, beveiliging en front-end opties.', 'event-hub'),
                'link'  => admin_url('admin.php?page=event-hub-general'),
                'cta'   => __('Open instellingen', 'event-hub'),
            ],
            [
                'title' => __('Statistieken', 'event-hub'),
                'text'  => __('Bekijk registraties, wachtlijst en top-events.', 'event-hub'),
                'link'  => admin_url('admin.php?page=event-hub-stats'),
                'cta'   => __('Bekijk stats', 'event-hub'),
            ],
            [
                'title' => __('Logs', 'event-hub'),
                'text'  => __('Controleer e-mail logs en systeemmeldingen.', 'event-hub'),
                'link'  => admin_url('admin.php?page=event-hub-logs'),
                'cta'   => __('Open logs', 'event-hub'),
            ],
            [
                'title' => __('Nieuw event', 'event-hub'),
                'text'  => __('Start direct een nieuw event in de moderne editor.', 'event-hub'),
                'link'  => admin_url('post-new.php?post_type=' . $cpt_slug),
                'cta'   => __('Nieuw event', 'event-hub'),
            ],
        ];

        echo '<div class="eh-grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-top:18px;">';
        foreach ($cards as $card) {
            echo '<div class="eh-card stat" style="display:flex;flex-direction:column;gap:10px;">';
            echo '<h3 style="margin:0;font-size:17px;">' . esc_html($card['title']) . '</h3>';
            echo '<p style="margin:0;color:#64748b;line-height:1.5;">' . esc_html($card['text']) . '</p>';
            echo '<a class="button button-primary" href="' . esc_url($card['link']) . '">' . esc_html($card['cta']) . '</a>';
            echo '</div>';
        }
        echo '</div>';

        echo '</div>';

    }


    public function enqueue_assets(string $hook): void
    {

        $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';

        $plugin_pages = [

            'event-hub',

            'event-hub-registrations',

            'event-hub-settings',

            'event-hub-general',

            'event-hub-calendar',

            'event-hub-event',

            'event-hub-stats',

            'event-hub-logs',

        ];



        if (in_array($page, $plugin_pages, true)) {

            wp_enqueue_style(

                'event-hub-admin',

                EVENT_HUB_URL . 'assets/css/admin.css',

                [],

                EVENT_HUB_VERSION

            );

            wp_enqueue_script(

                'event-hub-admin-ui',

                EVENT_HUB_URL . 'assets/js/admin-ui.js',

                [],

                EVENT_HUB_VERSION,

                true

            );

            $toast = null;

            if (!empty($_GET['eh_notice'])) {

                $notice = sanitize_text_field((string) $_GET['eh_notice']);

                $map = [

                    'reg_created' => __('Inschrijving toegevoegd.', 'event-hub'),

                    'reg_deleted' => __('Inschrijving verwijderd.', 'event-hub'),

                    'bulk_deleted' => __('Geselecteerde inschrijvingen verwijderd.', 'event-hub'),

                    'bulk_sent' => __('Bulkmail verzonden.', 'event-hub'),

                    'reg_updated' => __('Inschrijving bijgewerkt.', 'event-hub'),

                ];

                if (isset($map[$notice])) {

                    $toast = $map[$notice];

                }

            }

            wp_localize_script('event-hub-admin-ui', 'EventHubAdminUI', [

                'toast' => $toast,

            ]);

        }

        // Load styling/scripts on post edit screens for the Event Hub CPT as well.
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && in_array($screen->base, ['post', 'post-new'], true) && $screen->post_type === Settings::get_cpt_slug()) {
            wp_enqueue_style(
                'event-hub-admin',
                EVENT_HUB_URL . 'assets/css/admin.css',
                [],
                EVENT_HUB_VERSION
            );
            wp_enqueue_script(
                'event-hub-admin-ui',
                EVENT_HUB_URL . 'assets/js/admin-ui.js',
                [],
                EVENT_HUB_VERSION,
                true
            );
        }



        if ($page === 'event-hub-calendar') {

            $fc_css = EVENT_HUB_URL . 'assets/vendor/fullcalendar/main.min.css';

            $fc_js  = EVENT_HUB_URL . 'assets/vendor/fullcalendar/index.global.min.js';

            $fc_css_local = file_exists(EVENT_HUB_PATH . 'assets/vendor/fullcalendar/main.min.css');

            $fc_js_local  = file_exists(EVENT_HUB_PATH . 'assets/vendor/fullcalendar/index.global.min.js');

            $fc_css_ver = $fc_css_local ? EVENT_HUB_VERSION : '5.11.5';

            $fc_js_ver  = $fc_js_local ? EVENT_HUB_VERSION : '6.1.11';

            if (!$fc_css_local) {

                $fc_css = 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.css';

            }

            if (!$fc_js_local) {

                $fc_js = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js';

            }

            $fc_css = apply_filters('event_hub_fullcalendar_css', $fc_css);

            $fc_js  = apply_filters('event_hub_fullcalendar_js', $fc_js);



            wp_enqueue_style(

                'event-hub-fullcalendar',

                $fc_css,

                [],

                $fc_css_ver

            );

            wp_enqueue_script(

                'event-hub-fullcalendar',

                $fc_js,

                [],

                $fc_js_ver,

                true

            );

            wp_enqueue_script(

                'event-hub-admin-calendar',

                EVENT_HUB_URL . 'assets/js/admin-calendar.js',

                ['event-hub-fullcalendar'],

                EVENT_HUB_VERSION,

                true

            );

            wp_localize_script('event-hub-admin-calendar', 'eventHubCalendar', [

                'ajaxUrl' => admin_url('admin-ajax.php'),

                'nonce'   => wp_create_nonce('event_hub_calendar'),

                'labels'  => [

                    'loading' => __('Events laden...', 'event-hub'),

                    'error'   => __('Events konden niet worden opgehaald.', 'event-hub'),

                    'create'  => __('Nieuw event maken', 'event-hub'),

                ],

                'newEventUrl' => admin_url('post-new.php?post_type=' . Settings::get_cpt_slug()),

            ]);

        }



        if (in_array($page, ['event-hub-event','event-hub-stats'], true)) {

            wp_enqueue_style(

                'event-hub-dashboard',

                EVENT_HUB_URL . 'assets/css/admin-dashboard.css',

                ['event-hub-admin'],

                EVENT_HUB_VERSION

            );

        }



        if ($hook === 'edit.php') {

            $screen = get_current_screen();

            if ($screen && $screen->post_type === Settings::get_cpt_slug()) {

                wp_enqueue_style(

                    'event-hub-events-list',

                    EVENT_HUB_URL . 'assets/css/admin-events.css',

                    ['event-hub-admin'],

                    EVENT_HUB_VERSION

                );

            }

        }

    }



    public function render_stats_page(): void

    {

        if (!current_user_can('edit_posts')) {

            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));

        }



        $range_start = isset($_GET['eh_stats_start']) ? sanitize_text_field((string) $_GET['eh_stats_start']) : '';

        $range_end   = isset($_GET['eh_stats_end']) ? sanitize_text_field((string) $_GET['eh_stats_end']) : '';



        $start_ts = $range_start ? strtotime($range_start . ' 00:00:00') : strtotime('-30 days');

        $end_ts   = $range_end ? strtotime($range_end . ' 23:59:59') : time();



        if (!$start_ts || !$end_ts || $start_ts > $end_ts) {

            $start_ts = strtotime('-30 days');

            $end_ts = time();

        }



        $stats = $this->collect_stats($start_ts, $end_ts);



        echo '<div class="wrap eh-admin">';

        echo '<h1>' . esc_html__('Event Hub - Statistieken', 'event-hub') . '</h1>';

        echo '<form method="get" class="eh-filter-bar" style="margin-top:16px;margin-bottom:12px;">';

        echo '<input type="hidden" name="page" value="event-hub-stats">';

        echo '<label>' . esc_html__('Start', 'event-hub') . '<br><input type="date" name="eh_stats_start" value="' . esc_attr(date('Y-m-d', $start_ts)) . '"></label>';

        echo '<label>' . esc_html__('Einde', 'event-hub') . '<br><input type="date" name="eh_stats_end" value="' . esc_attr(date('Y-m-d', $end_ts)) . '"></label>';

        submit_button(__('Filter', 'event-hub'), 'primary', '', false);

        echo '</form>';



        echo '<div class="eh-stat-grid">';

        $cards = [

            ['label' => __('Nieuwe inschrijvingen', 'event-hub'), 'value' => (string) $stats['registrations']],

            ['label' => __('Bevestigd', 'event-hub'), 'value' => (string) $stats['confirmed']],

            ['label' => __('Wachtlijst', 'event-hub'), 'value' => (string) $stats['waitlist']],

            ['label' => __('Events in periode', 'event-hub'), 'value' => (string) $stats['events']],

            ['label' => __('Verzonden mails (template)', 'event-hub'), 'value' => (string) $stats['template_mails']],

        ];

        foreach ($cards as $card) {
            echo '<div class="eh-stat-card"><h3>' . esc_html($card['label']) . '</h3><div class="value">' . esc_html($card['value']) . '</div></div>';
        }

        echo '</div>';



        echo '<div class="eh-panel">';

        echo '<div class="eh-panel__head"><h2>' . esc_html__('Top events (registraties)', 'event-hub') . '</h2></div>';

        if (!$stats['top_events']) {
            echo '<p>' . esc_html__('Geen data in deze periode.', 'event-hub') . '</p>';
        } else {
            echo '<div class="eh-stat-grid">';
            foreach ($stats['top_events'] as $row) {
                $edit = get_edit_post_link($row['id']);
                echo '<div class="eh-stat-card">';
                echo '<h3><a href="' . esc_url($edit) . '">' . esc_html($row['title']) . '</a></h3>';
                echo '<div class="value">' . esc_html((string) $row['regs']) . '</div>';
                echo '<div class="eh-row-sub">' . esc_html(sprintf(__('%d wachtlijst', 'event-hub'), (int) $row['waitlist'])) . '</div>';
                echo '</div>';
            }
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    public function render_calendar_page(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));
        }

        echo '<div class="wrap eh-admin">';
        echo '<h1>' . esc_html__('Eventkalender', 'event-hub') . '</h1>';
        echo '<p>' . esc_html__('Bekijk al je events in een oogopslag. Klik op een event om het in een nieuw tabblad te openen.', 'event-hub') . '</p>';
        $statuses = [
            'open' => __('Open', 'event-hub'),
            'full' => __('Volzet', 'event-hub'),
            'closed' => __('Gesloten', 'event-hub'),
            'cancelled' => __('Geannuleerd', 'event-hub'),
        ];
        echo '<div class="eh-sticky-filters" style="position:relative;box-shadow:none;margin:0 0 14px 0;">';
        echo '<div class="eh-chip active" data-status="">' . esc_html__('Alle statussen', 'event-hub') . '</div>';
        foreach ($statuses as $key => $label) {
            echo '<div class="eh-chip" data-status="' . esc_attr($key) . '">' . esc_html($label) . '</div>';
        }
        echo '</div>';
        echo '<div id="eh-admin-calendar" style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:12px;"></div>';
        echo '<p class="description">' . esc_html__('Klik op een lege dag om een nieuw event te maken, of klik op een bestaande kaart om het event te openen.', 'event-hub') . '</p>';
        echo '</div>';
    }

private function collect_stats(int $start_ts, int $end_ts): array

    {

        global $wpdb;

        $registrations_table = $wpdb->prefix . Registrations::TABLE;



        $start = gmdate('Y-m-d H:i:s', $start_ts);

        $end   = gmdate('Y-m-d H:i:s', $end_ts);



        $regs = (int) $wpdb->get_var($wpdb->prepare(

            "SELECT COUNT(*) FROM {$registrations_table} WHERE created_at BETWEEN %s AND %s",

            $start,

            $end

        ));

        $confirmed = (int) $wpdb->get_var($wpdb->prepare(

            "SELECT COUNT(*) FROM {$registrations_table} WHERE created_at BETWEEN %s AND %s AND status = 'confirmed'",

            $start,

            $end

        ));

        $waitlist = (int) $wpdb->get_var($wpdb->prepare(

            "SELECT COUNT(*) FROM {$registrations_table} WHERE created_at BETWEEN %s AND %s AND status = 'waitlist'",

            $start,

            $end

        ));



        $events = (int) $wpdb->get_var($wpdb->prepare(

            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND post_date BETWEEN %s AND %s",

            Settings::get_cpt_slug(),

            $start,

            $end

        ));



        // Template mails sent (logged via event_hub_email_sent)

        $template_mails = (int) $wpdb->get_var($wpdb->prepare(

            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_eh_email_log_time' AND meta_value BETWEEN %s AND %s",

            $start,

            $end

        ));



        // Top events by registrations in range

        $top_rows = $wpdb->get_results($wpdb->prepare(

            "SELECT session_id, 

                SUM(CASE WHEN status = 'waitlist' THEN 1 ELSE 0 END) AS waitlist,

                COUNT(*) AS regs

             FROM {$registrations_table}

             WHERE created_at BETWEEN %s AND %s

             GROUP BY session_id

             ORDER BY regs DESC

             LIMIT 5",

            $start,

            $end

        ), ARRAY_A) ?: [];



        $top_events = [];

        foreach ($top_rows as $row) {

            $title = get_the_title((int) $row['session_id']);

            $top_events[] = [

                'id' => (int) $row['session_id'],

                'title' => $title ?: __('Onbekend event', 'event-hub'),

                'regs' => (int) $row['regs'],

                'waitlist' => (int) $row['waitlist'],

            ];

        }



        return [

            'registrations' => $regs,

            'confirmed' => $confirmed,

            'waitlist' => $waitlist,

            'events' => $events,

            'template_mails' => $template_mails,

            'top_events' => $top_events,

        ];

    }



    /**

     * Build a map of extra field slugs to labels across the given rows.

     *

     * @param array<int,array> $rows

     * @return array<string,string>

     */

    private function collect_extra_field_map(array $rows): array

    {

        $map = [];

        foreach ($rows as $row) {

            $sid = isset($row['session_id']) ? (int) $row['session_id'] : 0;

            if (!$sid) { continue; }

            $fields = get_post_meta($sid, '_eh_extra_fields', true);

            if (!is_array($fields)) { continue; }

            foreach ($fields as $field) {

                if (empty($field['slug'])) { continue; }

                $slug = sanitize_key((string) $field['slug']);

                if ($slug === '') { continue; }

                if (!isset($map[$slug])) {

                    $map[$slug] = isset($field['label']) ? sanitize_text_field((string) $field['label']) : $slug;

                }

            }

        }

        return $map;

    }



    public function render_logs_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));
        }
        $logs = $this->logger ? $this->logger->all() : [];
        $type_filter = isset($_GET['log_type']) ? sanitize_text_field((string) $_GET['log_type']) : '';
        $start_filter = isset($_GET['log_start']) ? sanitize_text_field((string) $_GET['log_start']) : '';
        $end_filter = isset($_GET['log_end']) ? sanitize_text_field((string) $_GET['log_end']) : '';

        $filtered = array_filter($logs, static function ($log) use ($type_filter, $start_filter, $end_filter) {
            if ($type_filter && (!isset($log['type']) || $log['type'] !== $type_filter)) {
                return false;
            }
            $ts = isset($log['ts']) ? (int) $log['ts'] : 0;
            if ($start_filter) {
                $start_ts = strtotime($start_filter . ' 00:00:00');
                if ($start_ts && $ts < $start_ts) {
                    return false;
                }
            }
            if ($end_filter) {
                $end_ts = strtotime($end_filter . ' 23:59:59');
                if ($end_ts && $ts > $end_ts) {
                    return false;
                }
            }
            return true;
        });

        $types = ['' => __('Alle types', 'event-hub'), 'email' => 'email', 'registration' => 'registration', 'log' => 'log'];

        echo '<div class="wrap eh-admin">';
        echo '<h1>' . esc_html__('Event Hub - Logs', 'event-hub') . '</h1>';
        echo '<p class="description">' . esc_html__('Basislog van e-mail- en registratie-events (laatste 200).', 'event-hub') . '</p>';

        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" class="eh-filter-bar">';
        echo '<input type="hidden" name="page" value="event-hub-logs">';
        echo '<select name="log_type">';
        foreach ($types as $val => $label) {
            $lbl = $label ?: __('Alle types', 'event-hub');
            echo '<option value="' . esc_attr($val) . '"' . selected($type_filter, $val, false) . '>' . esc_html($lbl) . '</option>';
        }
        echo '</select>';
        echo '<input type="date" name="log_start" value="' . esc_attr($start_filter) . '">';
        echo '<input type="date" name="log_end" value="' . esc_attr($end_filter) . '">';
        submit_button(__('Filter', 'event-hub'), 'primary', '', false);
        if ($type_filter || $start_filter || $end_filter) {
            echo '<a class="button" href="' . esc_url(remove_query_arg(['log_type','log_start','log_end'])) . '">' . esc_html__('Reset', 'event-hub') . '</a>';
        }
        echo '</form>';

        if (!$filtered) {
            echo '<div class="eh-card"><p>' . esc_html__('Geen logs beschikbaar.', 'event-hub') . '</p></div>';
        } else {
            echo '<div class="eh-timeline">';
            foreach ($filtered as $log) {
                $ts = isset($log['ts']) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), (int) $log['ts']) : '';
                $type = $log['type'] ?? '';
                $msg = $log['message'] ?? '';
                $ctx = $log['context'] ?? [];
                echo '<div class="eh-row-card">';
                echo '<div class="eh-row-main" style="grid-template-columns:1fr;">';
                echo '<div class="eh-row-title">' . esc_html($msg) . '</div>';
                echo '<div class="eh-row-sub">' . esc_html($ts) . '</div>';
                echo '<div class="eh-cal-badges">';
                echo '<span class="eh-pill gray">' . esc_html($type ?: 'log') . '</span>';
                echo '</div>';
                if (is_array($ctx) && $ctx) {
                    echo '<div class="eh-row-sub">';
                    foreach ($ctx as $k => $v) {
                        echo '<span class="eh-chip" style="padding:4px 8px;border-radius:8px;background:var(--eh-surface-subtle);border:1px solid var(--eh-border);">' . esc_html($k) . ': ' . esc_html((string) $v) . '</span> ';
                    }
                    echo '</div>';
                }
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
        }
        echo '</div>';
    }



    public function render_registrations_page(): void

    {

        if (!current_user_can('edit_posts')) {

            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));

        }



        if (

            isset($_POST['eh_bulk_action'], $_POST['eh_reg_ids'], $_POST['eh_bulk_nonce'])

            && $_POST['eh_bulk_action'] === 'delete'

            && wp_verify_nonce(sanitize_text_field((string) $_POST['eh_bulk_nonce']), 'eh_reg_bulk')

        ) {

            $this->handle_bulk_delete_registrations((array) $_POST['eh_reg_ids']);

            return;

        }



        if (!empty($_GET['eh_notice'])) {

            $notice = sanitize_text_field((string) $_GET['eh_notice']);

            $class = 'notice-success';

            $message = '';

            if ($notice === 'reg_created') {

                $message = __('Nieuwe inschrijving toegevoegd.', 'event-hub');

            }

            if ($notice === 'bulk_deleted') {

                $message = __('Geselecteerde inschrijvingen verwijderd.', 'event-hub');

            }

            if ($message) {

                echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';

            }

        }



        if (isset($_GET['download']) && $_GET['download'] === 'csv') {

            $session_id = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;

            $status = isset($_GET['status']) ? sanitize_text_field((string) $_GET['status']) : '';

            $name = isset($_GET['name']) ? sanitize_text_field((string) $_GET['name']) : '';

            $this->export_registrations_csv($session_id, $status, $name);

            return;

        }

        if (isset($_GET['download']) && $_GET['download'] === 'html') {

            $session_id = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;

            $status = isset($_GET['status']) ? sanitize_text_field((string) $_GET['status']) : '';

            $name = isset($_GET['name']) ? sanitize_text_field((string) $_GET['name']) : '';

            $this->render_printable_registrations($session_id, $status, $name);

            return;

        }



        $action = isset($_GET['action']) ? sanitize_text_field((string) $_GET['action']) : 'list';

        if ($action === 'edit') {

            $this->render_edit_registration();

            return;

        }

        if ($action === 'new') {

            $this->render_new_registration();

            return;

        }

        if ($action === 'delete') {

            $this->handle_delete_registration();

        }

        $this->render_list_registrations();

    }



    private function render_list_registrations(): void

    {

        $session_id = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;

        $status     = isset($_GET['status']) ? sanitize_text_field((string) $_GET['status']) : '';

        $name       = isset($_GET['name']) ? sanitize_text_field((string) $_GET['name']) : '';



        $sessions = get_posts([

            'post_type'   => Settings::get_cpt_slug(),

            'numberposts' => -1,

            'orderby'     => 'title',

            'order'       => 'ASC',

        ]);



        echo '<div class="wrap eh-admin">';

        $new_url = add_query_arg(

            [

                'page' => 'event-hub-registrations',

                'action' => 'new',

                'session_id' => $session_id ?: '',

            ],

            admin_url('admin.php')

        );

        echo '<h1 style="display:flex;justify-content:space-between;align-items:center;gap:12px;">' . esc_html__('Inschrijvingen', 'event-hub');

        echo '<a class="button button-primary" href="' . esc_url($new_url) . '">' . esc_html__('Nieuwe inschrijving', 'event-hub') . '</a>';

        echo '</h1>';

        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" class="eh-filter-bar">';

        echo '<input type="hidden" name="page" value="event-hub-registrations" />';

        echo '<input type="hidden" name="download_nonce" value="' . esc_attr(wp_create_nonce('eh_reg_export')) . '" />';

        echo '<select name="session_id">';

        echo '<option value="0">' . esc_html__('Alle evenementen', 'event-hub') . '</option>';

        foreach ($sessions as $session) {

            echo '<option value="' . esc_attr((string) $session->ID) . '"' . selected($session_id, $session->ID, false) . '>';

            echo esc_html($session->post_title);

            echo '</option>';

        }

        echo '</select>';

        echo '<select name="status">';

        echo '<option value="">' . esc_html__('Alle statussen', 'event-hub') . '</option>';

        $statuses = $this->get_status_labels();

        foreach ($statuses as $key => $label) {

            echo '<option value="' . esc_attr($key) . '"' . selected($status, $key, false) . '>' . esc_html($label) . '</option>';

        }

        echo '</select>';

        echo '<input type="text" name="name" placeholder="' . esc_attr__('Zoek op naam', 'event-hub') . '" value="' . esc_attr($name) . '" />';

        submit_button(__('Filteren', 'event-hub'), 'primary', '', false);

        echo '<button type="submit" name="download" value="csv" class="button button-secondary">' . esc_html__('Exporteer CSV', 'event-hub') . '</button>';

        echo '<button type="submit" name="download" value="html" class="button">' . esc_html__('Printbare lijst (HTML)', 'event-hub') . '</button>';

        echo '</form>';



        $registrations = $this->fetch_registrations($session_id, $status, $name);



        echo '<div class="eh-table-wrap">';

        echo '<form method="post" onsubmit="return confirm(\'' . esc_js(__('Geselecteerde inschrijvingen verwijderen?', 'event-hub')) . '\');">';

        wp_nonce_field('eh_reg_bulk', 'eh_bulk_nonce');

        echo '<input type="hidden" name="page" value="event-hub-registrations" />';

        echo '<input type="hidden" name="eh_bulk_action" value="delete" />';



        if (!$registrations) {

            echo '<div class="eh-row-card"><div class="eh-row-main"><div class="eh-row-title">' . esc_html__('Geen inschrijvingen gevonden.', 'event-hub') . '</div></div></div>';

        } else {

            echo '<div class="eh-sticky-filters">';

            echo '<label class="eh-chip"><input type="checkbox" id="eh-reg-check-all" style="margin-right:6px;" />' . esc_html__('Selecteer alles', 'event-hub') . '</label>';

            echo '<div class="eh-chip active">' . esc_html(sprintf(_n('%d inschrijving', '%d inschrijvingen', count($registrations), 'event-hub'), count($registrations))) . '</div>';

            echo '<div style="margin-left:auto;display:flex;gap:8px;align-items:center;">';

            echo '<button type="submit" class="button button-secondary" name="eh_bulk_delete_btn" value="1">' . esc_html__('Verwijder geselecteerden', 'event-hub') . '</button>';

            echo '</div>';

            echo '</div>';



            foreach ($registrations as $row) {

                $session_id = (int) $row['session_id'];

                $session    = get_post($session_id);

                $edit_url   = add_query_arg(

                    [

                        'page'   => 'event-hub-registrations',

                        'action' => 'edit',

                        'id'     => (int) $row['id'],

                    ],

                    admin_url('admin.php')

                );

                $delete_url = wp_nonce_url(

                    add_query_arg(

                        [

                            'page'   => 'event-hub-registrations',

                            'action' => 'delete',

                            'id'     => (int) $row['id'],

                        ],

                        admin_url('admin.php')

                    ),

                    'eh_delete_registration_' . (int) $row['id']

                );

                $status_key = $row['status'] ?? '';

                $badge_label = $statuses[$status_key] ?? $status_key;

                $status_class = 'gray';

                if (in_array($status_key, ['confirmed','registered'], true)) { $status_class = 'green'; }

                if ($status_key === 'waitlist') { $status_class = 'orange'; }

                if ($status_key === 'cancelled') { $status_class = 'red'; }



                echo '<div class="eh-row-card">';

                echo '<div class="eh-row-main">';

                echo '<div><label><input type="checkbox" class="eh-reg-check" name="eh_reg_ids[]" value="' . esc_attr((string) $row['id']) . '" /> <span class="eh-row-title">#' . esc_html((string) $row['id']) . '</span></label></div>';

                echo '<div>';

                if ($session) {

                    echo '<div class="eh-row-title">' . esc_html($session->post_title) . '</div>';

                } else {

                    echo '<div class="eh-row-title">' . esc_html__('Onbekend event', 'event-hub') . '</div>';

                }

                echo '<div class="eh-row-sub">' . esc_html(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) . '</div>';

                echo '</div>';

                echo '<div>';

                echo '<div class="eh-row-sub"><a href="mailto:' . esc_attr($row['email']) . '">' . esc_html($row['email']) . '</a></div>';

                echo '<div class="eh-row-sub">' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($row['created_at']))) . '</div>';

                echo '</div>';

                echo '<div>';

                echo '<span class="eh-pill ' . esc_attr($status_class) . '">' . esc_html($badge_label) . '</span>';

                echo '<div class="eh-row-sub">' . esc_html(sprintf(_n('%d persoon', '%d personen', (int) $row['people_count'], 'event-hub'), (int) $row['people_count'])) . '</div>';

                echo '</div>';

                echo '</div>';

                echo '<div class="eh-row-actions">';

                echo '<a class="button button-small" href="' . esc_url($edit_url) . '">' . esc_html__('Bewerken', 'event-hub') . '</a>';

                echo '<a class="button button-small" href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Deze inschrijving verwijderen?', 'event-hub')) . '\');">' . esc_html__('Verwijderen', 'event-hub') . '</a>';

                echo '</div>';

                echo '</div>';

            }

        }



        echo '</form>';

        echo '<script>document.addEventListener("DOMContentLoaded",function(){var all=document.getElementById("eh-reg-check-all");if(!all){return;}all.addEventListener("change",function(){document.querySelectorAll(".eh-reg-check").forEach(function(cb){cb.checked=all.checked;});});});</script>';

        echo '</div>';

        echo '</div>';

    }



    private function fetch_registrations(int $session_id, string $status, string $name = ''): array

    {

        if ($session_id > 0) {

            $registrations = $this->registrations->get_registrations_by_session($session_id);

        } else {

            global $wpdb;

            $table         = $wpdb->prefix . Registrations::TABLE;

            $registrations = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 100", ARRAY_A) ?: [];

        }



        $registrations = array_values(array_filter(

            $registrations,

            static function ($row) use ($status, $name): bool {

                if ($status !== '' && (!isset($row['status']) || $row['status'] !== $status)) {

                    return false;

                }

                if ($name !== '') {

                    $full = strtolower(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));

                    return str_contains($full, strtolower($name));

                }

                return true;

            }

        ));



        return $registrations;

    }



    private function render_edit_registration(): void

    {

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if (!$id) {

            echo '<div class="notice notice-error"><p>' . esc_html__('Ongeldige inschrijving.', 'event-hub') . '</p></div>';

            return;

        }



        $registration = $this->registrations->get_registration($id);

        if (!$registration) {

            echo '<div class="notice notice-error"><p>' . esc_html__('Inschrijving niet gevonden.', 'event-hub') . '</p></div>';

            return;

        }

        $statuses = $this->get_status_labels();



        if (

            isset($_POST['eh_manual_email_nonce'])

            && wp_verify_nonce(sanitize_text_field((string) $_POST['eh_manual_email_nonce']), 'eh_manual_email_' . $id)

        ) {

            if (!current_user_can('edit_posts')) {

                wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));

            }

            $template_id = isset($_POST['eh_template_id']) ? (int) $_POST['eh_template_id'] : 0;

            if ($template_id) {

                $result = $this->emails->send_template($id, $template_id, 'manual');

                if (is_wp_error($result)) {

                    echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';

                } else {

                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('E-mail verstuurd.', 'event-hub') . '</p></div>';

                }

            } else {

                echo '<div class="notice notice-error"><p>' . esc_html__('Selecteer een sjabloon.', 'event-hub') . '</p></div>';

            }

        }



        if (

            isset($_POST['eh_edit_registration_nonce'])

            && wp_verify_nonce(sanitize_text_field((string) $_POST['eh_edit_registration_nonce']), 'eh_edit_registration_' . $id)

        ) {

            if (!current_user_can('edit_posts')) {

                wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));

            }



            $update = [

                'first_name'        => sanitize_text_field($_POST['first_name'] ?? $registration['first_name']),

                'last_name'         => sanitize_text_field($_POST['last_name'] ?? $registration['last_name']),

                'email'             => sanitize_email($_POST['email'] ?? $registration['email']),

                'phone'             => sanitize_text_field($_POST['phone'] ?? $registration['phone']),

                'company'           => sanitize_text_field($_POST['company'] ?? $registration['company']),

                'vat'               => sanitize_text_field($_POST['vat'] ?? $registration['vat']),

                'role'              => sanitize_text_field($_POST['role'] ?? $registration['role']),

                'people_count'      => max(1, (int) ($_POST['people_count'] ?? $registration['people_count'])),

                'status'            => sanitize_text_field($_POST['status'] ?? $registration['status']),

                'consent_marketing' => isset($_POST['consent_marketing']) ? 1 : 0,

            ];



            if ($this->registrations->update_registration($id, $update)) {

                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Inschrijving bijgewerkt.', 'event-hub') . '</p></div>';

                $registration = $this->registrations->get_registration($id);

            } else {

                echo '<div class="notice notice-error"><p>' . esc_html__('Opslaan mislukt. Probeer opnieuw.', 'event-hub') . '</p></div>';

            }

        }



        echo '<div class="wrap eh-admin">';

        echo '<h1>' . esc_html__('Inschrijving bewerken', 'event-hub') . '</h1>';

        echo '<form method="post">';

        wp_nonce_field('eh_edit_registration_' . $id, 'eh_edit_registration_nonce');

        echo '<div class="eh-form-card">';

        echo '<div class="eh-form-two-col">';



        $fields = [

            'first_name' => __('Voornaam', 'event-hub'),

            'last_name'  => __('Familienaam', 'event-hub'),

            'email'      => __('E-mail', 'event-hub'),

            'phone'      => __('Telefoon', 'event-hub'),

            'company'    => __('Bedrijf', 'event-hub'),

            'vat'        => __('BTW-nummer', 'event-hub'),

            'role'       => __('Rol', 'event-hub'),

        ];



        foreach ($fields as $key => $label) {

            echo '<div class="field"><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label>';

            echo '<input type="text" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" value="' . esc_attr((string) ($registration[$key] ?? '')) . '"></div>';

        }



        echo '<div class="field"><label for="people_count">' . esc_html__('Aantal personen', 'event-hub') . '</label>';

        echo '<input type="number" min="1" name="people_count" id="people_count" value="' . esc_attr((string) ($registration['people_count'] ?? 1)) . '"></div>';



        echo '<div class="field"><label for="status">' . esc_html__('Status', 'event-hub') . '</label>';

        echo '<select name="status" id="status">';

        foreach ($statuses as $value => $label) {

            echo '<option value="' . esc_attr($value) . '"' . selected($registration['status'], $value, false) . '>' . esc_html($label) . '</option>';

        }

        echo '</select></div>';



        echo '<div class="field" style="grid-column:1 / -1;"><label><input type="checkbox" name="consent_marketing" value="1" ' . checked((int) ($registration['consent_marketing'] ?? 0), 1, false) . '> ';

        echo esc_html__('Contacteer mij voor updates', 'event-hub') . '</label></div>';



        echo '</div>'; // grid

        echo '</div>'; // card

        submit_button(__('Opslaan', 'event-hub'));

        echo '</form>';



        $templates = get_posts([

            'post_type' => CPT_Email::CPT,

            'numberposts' => -1,

            'orderby' => 'title',

            'order' => 'ASC',

        ]);

        if ($templates) {

            echo '<hr />';

            echo '<h2>' . esc_html__('Handmatig e-mailen', 'event-hub') . '</h2>';

            echo '<form method="post" style="max-width:480px;">';

            wp_nonce_field('eh_manual_email_' . $id, 'eh_manual_email_nonce');

            echo '<p><label for="eh_template_id">' . esc_html__('Kies sjabloon', 'event-hub') . '</label><br />';

            echo '<select name="eh_template_id" id="eh_template_id" class="regular-text">';

            echo '<option value="">' . esc_html__('Selecteer een sjabloon', 'event-hub') . '</option>';

            foreach ($templates as $tpl) {

                echo '<option value="' . esc_attr((string) $tpl->ID) . '">' . esc_html($tpl->post_title) . '</option>';

            }

            echo '</select></p>';

            submit_button(__('Verzend naar deelnemer', 'event-hub'), 'secondary');

            echo '</form>';

        }

        echo '</div>';

    }



    private function render_new_registration(): void

    {

        $sessions = get_posts([

            'post_type'   => Settings::get_cpt_slug(),

            'numberposts' => -1,

            'orderby'     => 'title',

            'order'       => 'ASC',

        ]);



        if (!$sessions) {

        echo '<div class="wrap eh-admin"><div class="notice notice-warning"><p>' . esc_html__('Maak eerst een event aan om inschrijvingen toe te voegen.', 'event-hub') . '</p></div></div>';

            return;

        }



        $preselect = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;

        $statuses = $this->get_status_labels();

        $error = '';



        if (

            isset($_POST['eh_add_registration_nonce'])

            && wp_verify_nonce(sanitize_text_field((string) $_POST['eh_add_registration_nonce']), 'eh_add_registration')

        ) {

            $payload = [

                'session_id' => isset($_POST['session_id']) ? (int) $_POST['session_id'] : 0,

                'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),

                'last_name'  => sanitize_text_field($_POST['last_name'] ?? ''),

                'email'      => sanitize_email($_POST['email'] ?? ''),

                'phone'      => sanitize_text_field($_POST['phone'] ?? ''),

                'company'    => sanitize_text_field($_POST['company'] ?? ''),

                'vat'        => sanitize_text_field($_POST['vat'] ?? ''),

                'role'       => sanitize_text_field($_POST['role'] ?? ''),

                'people_count' => isset($_POST['people_count']) ? (int) $_POST['people_count'] : 1,

                'status'     => sanitize_text_field($_POST['status'] ?? 'confirmed'),

                'consent_marketing' => isset($_POST['consent_marketing']) ? 1 : 0,

            ];



            $result = $this->registrations->create_registration($payload, true);

            if (is_wp_error($result)) {

                $error = $result->get_error_message();

            } else {

                if (!headers_sent()) {

                    $redirect = add_query_arg(

                        [

                            'page' => 'event-hub-registrations',

                            'session_id' => $payload['session_id'],

                            'eh_notice' => 'reg_created',

                        ],

                        admin_url('admin.php')

                    );

                    wp_safe_redirect($redirect);

                    exit;

                }

                $_GET['eh_notice'] = 'reg_created';

            }

        }



        echo '<div class="wrap eh-admin">';

        echo '<h1>' . esc_html__('Nieuwe inschrijving', 'event-hub') . '</h1>';

        if ($error) {

            echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';

        } else {

            echo '<div class="notice notice-info"><p>' . esc_html__('Deze inschrijving telt meteen mee, ook als het event volzet is.', 'event-hub') . '</p></div>';

        }

        echo '<form method="post">';

        wp_nonce_field('eh_add_registration', 'eh_add_registration_nonce');

        echo '<div class="eh-form-card">';

        echo '<div class="eh-form-two-col">';



        $current_session = isset($_POST['session_id']) ? (int) $_POST['session_id'] : $preselect;



        echo '<div class="field">';

        echo '<label for="session_id">' . esc_html__('Evenement', 'event-hub') . '</label>';

        echo '<select id="session_id" name="session_id" required>';

        echo '<option value="">' . esc_html__('Selecteer een evenement', 'event-hub') . '</option>';

        foreach ($sessions as $session) {

            $selected = selected((int) $session->ID, $current_session, false);

            echo '<option value="' . esc_attr((string) $session->ID) . '"' . $selected . '>' . esc_html($session->post_title) . '</option>';

        }

        echo '</select>';

        echo '</div>';



        $this->render_new_registration_input('first_name', __('Voornaam', 'event-hub'), true);

        $this->render_new_registration_input('last_name', __('Familienaam', 'event-hub'), true);

        $this->render_new_registration_input('email', __('E-mail', 'event-hub'), true, 'email');

        $this->render_new_registration_input('phone', __('Telefoon', 'event-hub'));

        $this->render_new_registration_input('company', __('Bedrijf', 'event-hub'));

        $this->render_new_registration_input('vat', __('BTW-nummer', 'event-hub'));

        $this->render_new_registration_input('role', __('Rol', 'event-hub'));



        echo '<div class="field">';

        echo '<label for="people_count">' . esc_html__('Aantal personen', 'event-hub') . '</label>';

        echo '<input type="number" id="people_count" name="people_count" min="1" value="' . esc_attr((string) ($_POST['people_count'] ?? 1)) . '" />';

        echo '</div>';



        echo '<div class="field">';

        echo '<label for="status">' . esc_html__('Status', 'event-hub') . '</label>';

        echo '<select id="status" name="status">';

        foreach ($statuses as $key => $label) {

            echo '<option value="' . esc_attr($key) . '"' . selected(sanitize_text_field($_POST['status'] ?? 'confirmed'), $key, false) . '>' . esc_html($label) . '</option>';

        }

        echo '</select>';

        echo '</div>';



        echo '<div class="field full checkbox"><label><input type="checkbox" name="consent_marketing" value="1"' . checked(isset($_POST['consent_marketing']), true, false) . ' /> ' . esc_html__('Marketingtoestemming gegeven', 'event-hub') . '</label></div>';



        echo '</div>'; // grid

        echo '</div>'; // card

        submit_button(__('Inschrijving opslaan', 'event-hub'));

        echo '</form>';

        echo '</div>';

    }



    private function render_new_registration_input(string $name, string $label, bool $required = false, string $type = 'text'): void

    {

        $value = isset($_POST[$name]) ? sanitize_text_field((string) $_POST[$name]) : '';

        echo '<div class="field">';

        echo '<label for="' . esc_attr($name) . '">' . esc_html($label . ($required ? ' *' : '')) . '</label>';

        echo '<input type="' . esc_attr($type) . '" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . ($required ? ' required' : '') . ' />';

        echo '</div>';

    }



    private function handle_delete_registration(): void

    {

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if (!$id) {

            return;

        }



        check_admin_referer('eh_delete_registration_' . $id);



        if (!current_user_can('delete_posts')) {

            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));

        }



        $deleted = $this->registrations->delete_registration($id);

        $redirect = add_query_arg(['page' => 'event-hub-registrations', 'eh_notice' => $deleted ? 'reg_deleted' : 'reg_delete_failed'], admin_url('admin.php'));

        if (!headers_sent()) {

            wp_safe_redirect($redirect);

            exit;

        }

        echo '<div class="notice ' . ($deleted ? 'notice-success' : 'notice-error') . '"><p>' . esc_html($deleted ? __('Inschrijving verwijderd.', 'event-hub') : __('Verwijderen mislukt.', 'event-hub')) . '</p></div>';

    }



    private function handle_bulk_delete_registrations(array $ids): void

    {

        if (!current_user_can('delete_posts')) {

            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));

        }

        $ids = array_map('intval', $ids);

        $ids = array_filter($ids, static function ($id) {

            return $id > 0;

        });

        foreach ($ids as $id) {

            $this->registrations->delete_registration($id);

        }

        $redirect = add_query_arg(['page' => 'event-hub-registrations', 'eh_notice' => 'bulk_deleted'], admin_url('admin.php'));

        if (!headers_sent()) {

            wp_safe_redirect($redirect);

            exit;

        }

        echo '<div class="notice notice-success"><p>' . esc_html__('Geselecteerde inschrijvingen verwijderd.', 'event-hub') . '</p></div>';

    }



    private function get_status_labels(): array

    {

        return [

            'registered' => __('Geregistreerd', 'event-hub'),

            'confirmed'  => __('Bevestigd', 'event-hub'),

            'cancelled'  => __('Geannuleerd', 'event-hub'),

            'attended'   => __('Aanwezig', 'event-hub'),

            'no_show'    => __('Niet opgedaagd', 'event-hub'),

            'waitlist'   => __('Wachtlijst', 'event-hub'),

        ];

    }



    public function ajax_calendar_events(): void

    {

        check_ajax_referer('event_hub_calendar');

        if (!current_user_can('edit_posts')) {

            wp_send_json_error(['message' => __('Geen toegang.', 'event-hub')], 403);

        }



        $start = isset($_GET['start']) ? strtotime(sanitize_text_field((string) $_GET['start'])) : false;

        $end   = isset($_GET['end']) ? strtotime(sanitize_text_field((string) $_GET['end'])) : false;



        $args = [

            'post_type'      => Settings::get_cpt_slug(),

            'post_status'    => 'publish',

            'posts_per_page' => -1,

        ];



        if ($start && $end) {

            $args['meta_query'] = [[

                'key'     => '_eh_date_start',

                'value'   => [gmdate('Y-m-d H:i:s', $start), gmdate('Y-m-d H:i:s', $end)],

                'compare' => 'BETWEEN',

                'type'    => 'DATETIME',

            ]];

        }



        $posts = get_posts($args);

        $events = [];

        foreach ($posts as $post) {

            $date_start = get_post_meta($post->ID, '_eh_date_start', true);

            if (!$date_start) {

                continue;

            }

            $date_end = get_post_meta($post->ID, '_eh_date_end', true);

            $color = sanitize_hex_color((string) get_post_meta($post->ID, '_eh_color', true)) ?: '#2271b1';

            $status = get_post_meta($post->ID, '_eh_status', true) ?: 'open';

            $capacity = (int) get_post_meta($post->ID, '_eh_capacity', true);
            $booked = $this->registrations->count_booked((int) $post->ID);
            $available = ($capacity > 0) ? max(0, $capacity - $booked) : null;
            $occupancy = ($capacity > 0) ? min(100, (int) round(($booked / max(1, $capacity)) * 100)) : null;
            $location = get_post_meta($post->ID, '_eh_location', true);
            $is_online = (bool) get_post_meta($post->ID, '_eh_is_online', true);
            $term_list = wp_get_post_terms($post->ID, Settings::get_tax_slug(), ['fields' => 'names']);

            $events[] = [

                'id' => $post->ID,

                'title' => $post->post_title,

                'start' => date('c', strtotime($date_start)),

                'end' => $date_end ? date('c', strtotime($date_end)) : null,

                'url' => add_query_arg(

                    [

                        'page' => 'event-hub-event',

                        'event_id' => $post->ID,

                    ],

                    admin_url('admin.php')

                ),

                'backgroundColor' => $color,

                'borderColor' => $color,

                'classNames' => ['eh-status-' . sanitize_html_class($status)],

                'extendedProps' => [

                    'status' => $status,

                    'location' => $location,
                    'is_online' => $is_online,
                    'capacity' => $capacity,
                    'booked' => $booked,
                    'available' => $available,
                    'occupancy' => $occupancy,
                    'terms' => $term_list,

                ],

            ];

        }



        wp_send_json_success($events);

    }

    /**
     * Publieke variant voor frontend kalenderblokken (geen login nodig).
     */
    public function ajax_public_calendar_events(): void
    {
        $start = isset($_GET['start']) ? strtotime(sanitize_text_field((string) $_GET['start'])) : false;
        $end   = isset($_GET['end']) ? strtotime(sanitize_text_field((string) $_GET['end'])) : false;

        // Beperk query window om abuse te voorkomen.
        $range_days = 120; // 4 maanden window
        $now = time();
        if (!$start) { $start = strtotime('-1 month', $now); }
        if (!$end) { $end = strtotime('+' . $range_days . ' days', $now); }
        if (($end - $start) > ($range_days * DAY_IN_SECONDS)) {
            $end = $start + ($range_days * DAY_IN_SECONDS);
        }

        $args = [
            'post_type'      => Settings::get_cpt_slug(),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [[
                'key'     => '_eh_date_start',
                'value'   => [gmdate('Y-m-d H:i:s', $start), gmdate('Y-m-d H:i:s', $end)],
                'compare' => 'BETWEEN',
                'type'    => 'DATETIME',
            ]],
        ];

        $posts = get_posts($args);
        $events = [];
        foreach ($posts as $post) {
            $date_start = get_post_meta($post->ID, '_eh_date_start', true);
            if (!$date_start) {
                continue;
            }
            $date_end = get_post_meta($post->ID, '_eh_date_end', true);
            $color = sanitize_hex_color((string) get_post_meta($post->ID, '_eh_color', true)) ?: '#2271b1';
            $events[] = [
                'id'    => $post->ID,
                'title' => $post->post_title,
                'start' => date('c', strtotime($date_start)),
                'end'   => $date_end ? date('c', strtotime($date_end)) : null,
                'url'   => get_permalink($post->ID),
                'backgroundColor' => $color,
                'borderColor'     => $color,
            ];
        }

        wp_send_json_success($events);
    }



    private function export_registrations_csv(int $session_id, string $status, string $name): void

    {

        if (!isset($_GET['download_nonce']) || !wp_verify_nonce(sanitize_text_field((string) $_GET['download_nonce']), 'eh_reg_export')) {

            wp_die(__('Ongeldige exportaanvraag.', 'event-hub'));

        }

        if (!current_user_can('edit_posts')) {

            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));

        }



        $rows = $this->fetch_registrations($session_id, $status, $name);
        $colleagues_label = $this->get_event_colleagues_label($session_id);
        $extra_map = $this->collect_extra_field_map($rows);

        nocache_headers();

        header('Content-Type: text/csv; charset=utf-8');

        header('Content-Disposition: attachment; filename="event-hub-registraties-' . gmdate('Ymd-His') . '.csv"');



        $out = fopen('php://output', 'w');

        fputcsv($out, [
            'ID',
            'Event',
            'Voornaam',
            'Familienaam',
            'E-mail',
            'Status',
            'Personen',
            'Telefoon',
            'Bedrijf',
            'BTW',
            'Rol',
            'Marketing',
            'Collega\'s',
            'Aangemaakt',
            ...array_values($extra_map),
        ]);



        foreach ($rows as $row) {

            $title = get_the_title((int) $row['session_id']);

            $extra_vals = [];

            $extra = [];

            if (!empty($row['extra_data'])) {

                $decoded = json_decode((string) $row['extra_data'], true);

                if (is_array($decoded)) {

                    $extra = $decoded;

                }

            }

            foreach (array_keys($extra_map) as $slug) {

                $extra_vals[] = isset($extra[$slug]) ? (is_scalar($extra[$slug]) ? $extra[$slug] : wp_json_encode($extra[$slug])) : '';

            }

            $colleagues_label = $this->get_event_colleagues_label((int) $row['session_id']);

            fputcsv($out, [

                $row['id'],

                $title,

                $row['first_name'],

                $row['last_name'],

                $row['email'],

                $row['status'],

                $row['people_count'],

                $row['phone'],

                $row['company'],

                $row['vat'],

                $row['role'],

                $row['consent_marketing'] ? 'ja' : 'nee',

                $colleagues_label,

                $row['created_at'],

                ...$extra_vals,

            ]);

        }

        fclose($out);

        exit;

    }



    private function render_printable_registrations(int $session_id, string $status, string $name): void

    {

        if (!isset($_GET['download_nonce']) || !wp_verify_nonce(sanitize_text_field((string) $_GET['download_nonce']), 'eh_reg_export')) {

            wp_die(__('Ongeldige exportaanvraag.', 'event-hub'));

        }

        if (!current_user_can('edit_posts')) {

            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));

        }

        $rows = $this->fetch_registrations($session_id, $status, $name);

        $extra_map = $this->collect_extra_field_map($rows);

        nocache_headers();

        echo '<!doctype html><html><head><meta charset="utf-8"><title>Event Hub registraties</title>';

        echo '<style>

            body{font-family:Arial, sans-serif; margin:20px; color:#111;}

            h1{margin-top:0;}

            table{border-collapse:collapse; width:100%; font-size:13px;}

            th,td{border:1px solid #ccc; padding:6px 8px; text-align:left;}

            th{background:#f5f5f5;}

            .meta{margin-bottom:12px; font-size:12px; color:#555;}

        </style></head><body>';

        echo '<h1>Event Hub registraties</h1>';

        echo '<div class="meta">';

        if ($session_id) {

            echo esc_html__('Event:', 'event-hub') . ' ' . esc_html(get_the_title($session_id)) . '<br>';

        } else {

            echo esc_html__('Event:', 'event-hub') . ' ' . esc_html__('Alle evenementen', 'event-hub') . '<br>';

        }

        echo esc_html__('Statusfilter:', 'event-hub') . ' ' . ($status ? esc_html($status) : esc_html__('Alle', 'event-hub')) . '<br>';

        echo esc_html__('Exportdatum:', 'event-hub') . ' ' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'))) . '</div>';



        echo '<table><thead><tr>';

        $headers = [

            __('ID', 'event-hub'),

            __('Event', 'event-hub'),

            __('Voornaam', 'event-hub'),

            __('Familienaam', 'event-hub'),

            __('E-mail', 'event-hub'),

            __('Status', 'event-hub'),

            __('Personen', 'event-hub'),

            __('Telefoon', 'event-hub'),

            __('Bedrijf', 'event-hub'),

            __('BTW', 'event-hub'),

            __('Rol', 'event-hub'),

            __('Marketing', 'event-hub'),

            __('Collega\'s', 'event-hub'),

            __('Aangemaakt', 'event-hub'),

        ];

        foreach ($extra_map as $label) {

            $headers[] = $label;

        }

        foreach ($headers as $h) {

            echo '<th>' . esc_html($h) . '</th>';

        }

        echo '</tr></thead><tbody>';

        if (!$rows) {

            echo '<tr><td colspan="' . count($headers) . '">' . esc_html__('Geen inschrijvingen gevonden.', 'event-hub') . '</td></tr>';

        } else {

            foreach ($rows as $row) {

                $title = get_the_title((int) $row['session_id']);

                $extra_vals = [];

                $extra = [];

                if (!empty($row['extra_data'])) {

                    $decoded = json_decode((string) $row['extra_data'], true);

                    if (is_array($decoded)) {

                        $extra = $decoded;

                    }

                }

                foreach (array_keys($extra_map) as $slug) {

                    $extra_vals[] = isset($extra[$slug]) ? (is_scalar($extra[$slug]) ? $extra[$slug] : wp_json_encode($extra[$slug])) : '';

                }

                $colleagues_label = $this->get_event_colleagues_label((int) $row['session_id']);

                echo '<tr>';

                echo '<td>' . esc_html($row['id']) . '</td>';

                echo '<td>' . esc_html($title) . '</td>';

                echo '<td>' . esc_html($row['first_name']) . '</td>';

                echo '<td>' . esc_html($row['last_name']) . '</td>';

                echo '<td>' . esc_html($row['email']) . '</td>';

                echo '<td>' . esc_html($row['status']) . '</td>';

                echo '<td>' . esc_html($row['people_count']) . '</td>';

                echo '<td>' . esc_html($row['phone']) . '</td>';

                echo '<td>' . esc_html($row['company']) . '</td>';

                echo '<td>' . esc_html($row['vat']) . '</td>';

                echo '<td>' . esc_html($row['role']) . '</td>';

                echo '<td>' . ($row['consent_marketing'] ? esc_html__('ja', 'event-hub') : esc_html__('nee', 'event-hub')) . '</td>';

                echo '<td>' . esc_html($colleagues_label) . '</td>';

                echo '<td>' . esc_html($row['created_at']) . '</td>';

                foreach ($extra_vals as $val) {

                    echo '<td>' . esc_html($val) . '</td>';

                }

                echo '</tr>';

            }

        }

        echo '</tbody></table>';

        echo '<script>window.print();</script>';

        echo '</body></html>';

        exit;

    }



    public function render_event_dashboard(): void

    {

        if (!current_user_can('edit_posts')) {

            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));

        }

        $event_id = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;

        if (!$event_id) {

            echo '<div class="wrap eh-admin"><div class="notice notice-error"><p>' . esc_html__('Event niet gevonden.', 'event-hub') . '</p></div></div>';

            return;

        }

        if (isset($_GET['download'], $_GET['nonce']) && $_GET['download'] === 'csv') {

            $this->export_event_dashboard_csv($event_id, sanitize_text_field((string) $_GET['nonce']));

            return;

        }



        $event = get_post($event_id);

        if (!$event || $event->post_type !== Settings::get_cpt_slug()) {

            echo '<div class="wrap eh-admin"><div class="notice notice-error"><p>' . esc_html__('Event niet gevonden.', 'event-hub') . '</p></div></div>';

            return;

        }



        $registrations = $this->registrations->get_registrations_by_session($event_id);
        $colleagues_label = $this->get_event_colleagues_label($event_id);
        $colleagues_label = $this->get_event_colleagues_label($event_id);

        $search = isset($_GET['eh_search']) ? sanitize_text_field((string) $_GET['eh_search']) : '';

        if ($search !== '') {

            $needle = strtolower($search);

            $registrations = array_values(array_filter($registrations, static function ($row) use ($needle): bool {

                $name = strtolower(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));

                $email = strtolower($row['email'] ?? '');

                return str_contains($name, $needle) || str_contains($email, $needle);

            }));

        }

        $waitlist_regs = array_values(array_filter($registrations, static fn($row) => ($row['status'] ?? '') === 'waitlist'));

        $active_regs = array_values(array_filter($registrations, static fn($row) => ($row['status'] ?? '') !== 'waitlist'));

        $state = $this->registrations->get_capacity_state($event_id);

        $status = get_post_meta($event_id, '_eh_status', true) ?: 'open';

        $date_start = get_post_meta($event_id, '_eh_date_start', true);

        $date_end = get_post_meta($event_id, '_eh_date_end', true);

        $location = get_post_meta($event_id, '_eh_location', true);

        $is_online = (bool) get_post_meta($event_id, '_eh_is_online', true);

        $booking_open = get_post_meta($event_id, '_eh_booking_open', true);

        $booking_close = get_post_meta($event_id, '_eh_booking_close', true);

        $templates = get_posts([

            'post_type' => CPT_Email::CPT,

            'numberposts' => -1,

            'orderby' => 'title',

            'order' => 'ASC',

        ]);



        $edit_link = add_query_arg(

            [

                'post'   => $event_id,

                'action' => 'edit',

            ],

            admin_url('post.php')

        );



        echo '<div class="wrap eh-admin eh-event-dashboard">';

        if (!empty($_GET['eh_notice'])) {

            $notice = sanitize_text_field((string) $_GET['eh_notice']);

            $class = 'notice-success';

            $message = '';

            if ($notice === 'bulk_sent') {

                $message = __('Bulk e-mail verzonden.', 'event-hub');

            } elseif ($notice === 'individual_sent') {

                $message = __('E-mail naar deelnemer verzonden.', 'event-hub');

            } elseif ($notice === 'bulk_error') {

                $class = 'notice-error';

                $message = __('Bulk e-mail verzenden mislukt.', 'event-hub');

            } elseif ($notice === 'individual_error') {

                $class = 'notice-error';

                $message = __('E-mail versturen mislukt.', 'event-hub');

            } elseif ($notice === 'reg_updated') {

                $message = __('Registratie bijgewerkt.', 'event-hub');

            } elseif ($notice === 'reg_deleted') {

                $message = __('Registratie verwijderd.', 'event-hub');

            }

            if ($message) {

                echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';

            }

        }



        echo '<div class="eh-dashboard-header">';

        echo '<div><h1>' . esc_html__('Eventdashboard', 'event-hub') . '</h1>';

        echo '<h2>' . esc_html(get_the_title($event_id)) . '</h2></div>';

        echo '<div class="eh-dashboard-actions">';

        echo '<a class="button button-primary" href="' . esc_url($edit_link) . '">' . esc_html__('Bewerk evenement', 'event-hub') . '</a>';

        echo '<a class="button" href="' . esc_url(get_permalink($event_id)) . '" target="_blank" rel="noopener">' . esc_html__('Bekijk op site', 'event-hub') . '</a>';

        echo '<a class="button" href="' . esc_url(add_query_arg(['page' => 'event-hub-registrations', 'session_id' => $event_id], admin_url('admin.php'))) . '">' . esc_html__('Inschrijvingenlijst', 'event-hub') . '</a>';

        echo '<a class="button" href="' . esc_url(add_query_arg(['page' => 'event-hub-registrations', 'action' => 'new', 'session_id' => $event_id], admin_url('admin.php'))) . '">' . esc_html__('Nieuwe inschrijving', 'event-hub') . '</a>';

        echo '<a class="button" href="' . esc_url(add_query_arg(['page' => 'event-hub-event', 'event_id' => $event_id, 'download' => 'csv', 'nonce' => wp_create_nonce('eh_event_csv_' . $event_id)], admin_url('admin.php'))) . '">' . esc_html__('Download CSV', 'event-hub') . '</a>';

        if ($templates) {

            echo '<form method="post" class="eh-bulk-mail-form">';

            wp_nonce_field('eh_event_bulk_mail_' . $event_id, 'eh_event_bulk_mail_nonce');

            echo '<input type="hidden" name="event_id" value="' . esc_attr((string) $event_id) . '">';

            echo '<select name="bulk_template_id">';

            echo '<option value="">' . esc_html__('Bulk e-mail sjabloon', 'event-hub') . '</option>';

            foreach ($templates as $tpl) {

                echo '<option value="' . esc_attr((string) $tpl->ID) . '">' . esc_html($tpl->post_title) . '</option>';

            }

            echo '</select>';

            echo '<button class="button">' . esc_html__('Verstuur', 'event-hub') . '</button>';

            echo '</form>';

        }

        echo '</div>';

        echo '</div>'; // header



        echo '<div class="eh-grid stats">';

        $cards = [

            ['label' => __('Status', 'event-hub'), 'value' => ucfirst($status)],

            ['label' => __('Startmoment', 'event-hub'), 'value' => $date_start ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($date_start)) : '—'],

            ['label' => __('Einde', 'event-hub'), 'value' => $date_end ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($date_end)) : '—'],

            ['label' => __('Locatie', 'event-hub'), 'value' => $is_online ? __('Online', 'event-hub') : ($location ?: '—')],

            ['label' => __('Inschrijvingen', 'event-hub'), 'value' => (string) $state['booked']],

            ['label' => __('Beschikbaar', 'event-hub'), 'value' => $state['capacity'] > 0 ? sprintf('%d / %d', $state['available'], $state['capacity']) : __('Onbeperkt', 'event-hub')],

            ['label' => __('Boekingsstart', 'event-hub'), 'value' => $booking_open ? date_i18n(get_option('date_format'), strtotime($booking_open)) : '—'],

            ['label' => __('Boekingseinde', 'event-hub'), 'value' => $booking_close ? date_i18n(get_option('date_format'), strtotime($booking_close)) : '—'],

            ['label' => __('Wachtlijst', 'event-hub'), 'value' => (string) count($waitlist_regs)],

        ];

        foreach ($cards as $card) {

            echo '<div class="eh-card stat"><h3>' . esc_html($card['label']) . '</h3><p>' . esc_html($card['value']) . '</p></div>';

        }

        echo '</div>';



        // Search/filter bar for participants and waitlist

        $search_query = $search ?: '';

        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" class="eh-filter-bar" style="margin-top:12px">';

        echo '<input type="hidden" name="page" value="event-hub-event">';

        echo '<input type="hidden" name="event_id" value="' . esc_attr((string) $event_id) . '">';

        echo '<input type="search" name="eh_search" placeholder="' . esc_attr__('Zoek op naam of e-mail', 'event-hub') . '" value="' . esc_attr($search_query) . '" style="min-width:240px">';

        submit_button(__('Zoeken', 'event-hub'), 'secondary', '', false);

        if ($search_query !== '') {

            echo '<a class="button" href="' . esc_url(remove_query_arg('eh_search')) . '">' . esc_html__('Reset', 'event-hub') . '</a>';

        }

        echo '</form>';



        $statuses = $this->get_status_labels();



        echo '<div class="eh-panel">';

        echo '<div class="eh-panel__head"><h2>' . esc_html__('Deelnemers', 'event-hub') . '</h2></div>';

        if (!$active_regs) {

            echo '<p>' . esc_html__('Nog geen inschrijvingen.', 'event-hub') . '</p>';

        } else {

            echo '<table class="widefat fixed striped eh-table eh-table-card">';

            echo '<thead><tr>';

            $cols = [

                __('Naam', 'event-hub'),

                __('E-mail', 'event-hub'),

                __('Telefoon', 'event-hub'),

                __('Bedrijf', 'event-hub'),

                __('Personen', 'event-hub'),

                __('Status', 'event-hub'),

                __('Ingeschreven op', 'event-hub'),

                __('Acties', 'event-hub'),

            ];

            foreach ($cols as $col) {

                echo '<th>' . esc_html($col) . '</th>';

            }

            echo '</tr></thead><tbody>';

            foreach ($active_regs as $row) {

                $reg_id = (int) ($row['id'] ?? 0);

                $current_status = $row['status'] ?? '';

                echo '<tr>';

                echo '<td>' . esc_html(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) . '</td>';

                echo '<td><a href="mailto:' . esc_attr($row['email']) . '">' . esc_html($row['email']) . '</a></td>';

                echo '<td>' . esc_html($row['phone'] ?? '') . '</td>';

                echo '<td>' . esc_html($row['company'] ?? '') . '</td>';

                echo '<td>' . esc_html((string) ($row['people_count'] ?? 1)) . '</td>';

                echo '<td>' . esc_html(ucfirst($row['status'] ?? '')) . '</td>';

                echo '<td>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($row['created_at']))) . '</td>';

                echo '<td>';

                echo '<form method="post" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">';

                wp_nonce_field('eh_event_dashboard_action_' . $event_id, 'eh_event_dashboard_nonce');

                echo '<input type="hidden" name="event_id" value="' . esc_attr((string) $event_id) . '">';

                echo '<input type="hidden" name="registration_id" value="' . esc_attr((string) $reg_id) . '">';

                echo '<input type="hidden" name="eh_dashboard_action" value="update_status">';

                echo '<select name="new_status">';

                foreach ($statuses as $key => $label) {

                    echo '<option value="' . esc_attr($key) . '"' . selected($current_status, $key, false) . '>' . esc_html($label) . '</option>';

                }

                echo '</select>';

                echo '<button class="button button-small" type="submit">' . esc_html__('Opslaan', 'event-hub') . '</button>';

                echo '</form>';

                if ($templates) {

                    echo '<form method="post" class="eh-individual-mail" style="margin-top:6px;">';

                    wp_nonce_field('eh_event_individual_mail_' . $event_id, 'eh_event_individual_mail_nonce');

                    echo '<input type="hidden" name="event_id" value="' . esc_attr((string) $event_id) . '">';

                    echo '<input type="hidden" name="registration_id" value="' . esc_attr((string) $reg_id) . '">';

                    echo '<select name="individual_template_id">';

                    echo '<option value="">' . esc_html__('Kies sjabloon', 'event-hub') . '</option>';

                    foreach ($templates as $tpl) {

                        echo '<option value="' . esc_attr((string) $tpl->ID) . '">' . esc_html($tpl->post_title) . '</option>';

                    }

                    echo '</select>';

                    echo '<button class="button button-small">' . esc_html__('Verstuur', 'event-hub') . '</button>';

                    echo '</form>';

                } else {

                    esc_html_e('Geen sjablonen beschikbaar.', 'event-hub');

                }

                echo '<form method="post" style="margin-top:6px;" onsubmit="return confirm(\'' . esc_js(__('Deze inschrijving verwijderen?', 'event-hub')) . '\');">';

                wp_nonce_field('eh_event_dashboard_action_' . $event_id, 'eh_event_dashboard_nonce');

                echo '<input type="hidden" name="event_id" value="' . esc_attr((string) $event_id) . '">';

                echo '<input type="hidden" name="registration_id" value="' . esc_attr((string) $reg_id) . '">';

                echo '<input type="hidden" name="eh_dashboard_action" value="delete_registration">';

                echo '<button class="button button-small" type="submit">' . esc_html__('Verwijderen', 'event-hub') . '</button>';

                echo '</form>';

                echo '</td>';

                echo '</tr>';

            }

            echo '</tbody></table>';

        }

        echo '</div>';



        echo '<div class="eh-panel">';

        echo '<div class="eh-panel__head"><h2>' . esc_html__('Wachtlijst', 'event-hub') . '</h2></div>';

        if (!$waitlist_regs) {

            echo '<p>' . esc_html__('Er staan momenteel geen mensen op de wachtlijst.', 'event-hub') . '</p>';

        } else {

            echo '<table class="widefat fixed striped eh-table eh-table-card">';

            echo '<thead><tr>';

            $wait_cols = [

                __('Naam', 'event-hub'),

                __('E-mail', 'event-hub'),

                __('Telefoon', 'event-hub'),

                __('Personen', 'event-hub'),

                __('Sinds', 'event-hub'),

                __('Acties', 'event-hub'),

            ];

            foreach ($wait_cols as $col) {

                echo '<th>' . esc_html($col) . '</th>';

            }

            echo '</tr></thead><tbody>';

            foreach ($waitlist_regs as $row) {

                $reg_id = (int) ($row['id'] ?? 0);

                echo '<tr>';

                echo '<td>' . esc_html(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) . '</td>';

                echo '<td><a href="mailto:' . esc_attr($row['email']) . '">' . esc_html($row['email']) . '</a></td>';

                echo '<td>' . esc_html($row['phone'] ?? '') . '</td>';

                echo '<td>' . esc_html((string) ($row['people_count'] ?? 1)) . '</td>';

                echo '<td>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($row['created_at']))) . '</td>';

                echo '<td>';

                echo '<form method="post" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">';

                wp_nonce_field('eh_event_dashboard_action_' . $event_id, 'eh_event_dashboard_nonce');

                echo '<input type="hidden" name="event_id" value="' . esc_attr((string) $event_id) . '">';

                echo '<input type="hidden" name="registration_id" value="' . esc_attr((string) $reg_id) . '">';

                echo '<input type="hidden" name="eh_dashboard_action" value="promote_waitlist">';

                echo '<select name="new_status">';

                $promotion_statuses = [

                    'registered' => __('Geregistreerd', 'event-hub'),

                    'confirmed'  => __('Bevestigd', 'event-hub'),

                ];

                foreach ($promotion_statuses as $key => $label) {

                    echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';

                }

                echo '</select>';

                echo '<button class="button button-small" type="submit">' . esc_html__('Promoveren', 'event-hub') . '</button>';

                echo '</form>';

                echo '<form method="post" style="margin-top:6px;" onsubmit="return confirm(\'' . esc_js(__('Deze inschrijving verwijderen?', 'event-hub')) . '\');">';

                wp_nonce_field('eh_event_dashboard_action_' . $event_id, 'eh_event_dashboard_nonce');

                echo '<input type="hidden" name="event_id" value="' . esc_attr((string) $event_id) . '">';

                echo '<input type="hidden" name="registration_id" value="' . esc_attr((string) $reg_id) . '">';

                echo '<input type="hidden" name="eh_dashboard_action" value="delete_registration">';

                echo '<button class="button button-small" type="submit">' . esc_html__('Verwijderen', 'event-hub') . '</button>';

                echo '</form>';

                echo '</td>';

                echo '</tr>';

            }

            echo '</tbody></table>';

        }

        echo '</div>';



        echo '</div>';

    }



    public function maybe_process_event_actions(): void

    {

        if (!current_user_can('edit_posts')) {

            return;

        }

        if (empty($_GET['page']) || $_GET['page'] !== 'event-hub-event') {

            return;

        }

        if (empty($_POST['event_id'])) {

            return;

        }



        $event_id = (int) $_POST['event_id'];

        $redirect = add_query_arg(['page' => 'event-hub-event', 'event_id' => $event_id], admin_url('admin.php'));



        if (isset($_POST['bulk_template_id']) && !empty($_POST['eh_event_bulk_mail_nonce']) && wp_verify_nonce(sanitize_text_field((string) $_POST['eh_event_bulk_mail_nonce']), 'eh_event_bulk_mail_' . $event_id)) {

            $template_id = (int) $_POST['bulk_template_id'];

            if ($template_id) {

                $registrations = $this->registrations->get_registrations_by_session($event_id);

                foreach ($registrations as $row) {

                    $this->emails->send_template((int) $row['id'], $template_id, 'dashboard_bulk');

                }

                wp_safe_redirect(add_query_arg('eh_notice', 'bulk_sent', $redirect));

                exit;

            }

            wp_safe_redirect(add_query_arg('eh_notice', 'bulk_error', $redirect));

            exit;

        }



        if (isset($_POST['individual_template_id'], $_POST['registration_id']) && !empty($_POST['eh_event_individual_mail_nonce']) && wp_verify_nonce(sanitize_text_field((string) $_POST['eh_event_individual_mail_nonce']), 'eh_event_individual_mail_' . $event_id)) {

            $template_id = (int) $_POST['individual_template_id'];

            $registration_id = (int) $_POST['registration_id'];

            if ($template_id && $registration_id) {

                $result = $this->emails->send_template($registration_id, $template_id, 'dashboard_individual');

                $notice = is_wp_error($result) ? 'individual_error' : 'individual_sent';

                wp_safe_redirect(add_query_arg('eh_notice', $notice, $redirect));

                exit;

            }

            wp_safe_redirect(add_query_arg('eh_notice', 'individual_error', $redirect));

            exit;

        }



        // Dashboard inline actions (update status / delete / promote)

        if (

            isset($_POST['eh_dashboard_action'], $_POST['registration_id'])

            && !empty($_POST['eh_event_dashboard_nonce'])

            && wp_verify_nonce(sanitize_text_field((string) $_POST['eh_event_dashboard_nonce']), 'eh_event_dashboard_action_' . $event_id)

        ) {

            $action = sanitize_text_field((string) $_POST['eh_dashboard_action']);

            $reg_id = (int) $_POST['registration_id'];

            $allowed_statuses = array_keys(Registrations::get_status_labels());



            if ($action === 'update_status' && isset($_POST['new_status'])) {

                $new_status = sanitize_text_field((string) $_POST['new_status']);

                if (in_array($new_status, $allowed_statuses, true)) {

                    $this->registrations->update_registration($reg_id, ['status' => $new_status]);

                    wp_safe_redirect(add_query_arg('eh_notice', 'reg_updated', $redirect));

                    exit;

                }

            }



            if ($action === 'promote_waitlist' && isset($_POST['new_status'])) {

                $new_status = sanitize_text_field((string) $_POST['new_status']);

                if (in_array($new_status, ['registered', 'confirmed'], true)) {

                    $this->registrations->update_registration($reg_id, ['status' => $new_status]);

                    wp_safe_redirect(add_query_arg('eh_notice', 'reg_updated', $redirect));

                    exit;

                }

            }



            if ($action === 'delete_registration') {

                $this->registrations->delete_registration($reg_id);

                wp_safe_redirect(add_query_arg('eh_notice', 'reg_deleted', $redirect));

                exit;

            }

        }

    }



    private function export_event_dashboard_csv(int $event_id, string $nonce): void

    {

        if (!wp_verify_nonce($nonce, 'eh_event_csv_' . $event_id)) {

            wp_die(__('Ongeldige exportaanvraag.', 'event-hub'));

        }

        if (!current_user_can('edit_posts')) {

            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));

        }

        $registrations = $this->registrations->get_registrations_by_session($event_id);

        nocache_headers();

        header('Content-Type: text/csv; charset=utf-8');

        header('Content-Disposition: attachment; filename="event-hub-event-' . $event_id . '-' . gmdate('Ymd-His') . '.csv"');

        $out = fopen('php://output', 'w');

        fputcsv($out, [
            __('Voornaam', 'event-hub'),
            __('Familienaam', 'event-hub'),
            __('E-mail', 'event-hub'),
            __('Telefoon', 'event-hub'),
            __('Bedrijf', 'event-hub'),
            __('Aantal personen', 'event-hub'),
            __('Status', 'event-hub'),
            __('Collega\'s', 'event-hub'),
            __('Aangemaakt', 'event-hub'),
        ]);

        foreach ($registrations as $row) {

            fputcsv($out, [

                $row['first_name'],

                $row['last_name'],

                $row['email'],

                $row['phone'],

                $row['company'],

                $row['people_count'],
                $row['status'],
                $colleagues_label,
                $row['created_at'],

            ]);

        }

        fclose($out);

        exit;

    }

    /**
     * Bouw een leesbare lijst van collega-namen die aan het event zijn gekoppeld.
     */
    private function get_event_colleagues_label(int $event_id): string
    {
        $general = Settings::get_general();
        $global = isset($general['colleagues']) && is_array($general['colleagues']) ? $general['colleagues'] : [];
        if (!$global) {
            return '';
        }
        $selected = (array) get_post_meta($event_id, '_eh_colleagues', true);
        if (!$selected) {
            return '';
        }
        $names = [];
        foreach ($selected as $cid) {
            if (!isset($global[$cid])) {
                continue;
            }
            $c = $global[$cid];
            $name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }
        return implode(', ', $names);
    }

}


