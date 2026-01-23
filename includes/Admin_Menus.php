<?php

namespace EventHub;

use WP_REST_Request;
use WP_REST_Response;

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

    public function register_rest_routes(): void
    {
        register_rest_route('event-hub/v1', '/admin/calendar', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'rest_admin_calendar'],
            'permission_callback' => static function (): bool {
                return current_user_can('edit_posts');
            },
            'args'                => [
                'start' => ['type' => 'string', 'required' => false],
                'end'   => ['type' => 'string', 'required' => false],
            ],
        ]);

        register_rest_route('event-hub/v1', '/calendar', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'rest_public_calendar'],
            'permission_callback' => '__return_true',
            'args'                => [
                'start' => ['type' => 'string', 'required' => false],
                'end'   => ['type' => 'string', 'required' => false],
            ],
        ]);
    }

    public function rest_admin_calendar(WP_REST_Request $request): WP_REST_Response
    {
        $start = $request->get_param('start');
        $end   = $request->get_param('end');
        $start_ts = $start ? strtotime((string) $start) : null;
        $end_ts   = $end ? strtotime((string) $end) : null;
        $events = $this->build_calendar_events($start_ts, $end_ts, true);
        return rest_ensure_response($events);
    }

    public function rest_public_calendar(WP_REST_Request $request): WP_REST_Response
    {
        $range_days = 120;
        $now = time();
        $start = $request->get_param('start');
        $end   = $request->get_param('end');
        $start_ts = $start ? strtotime((string) $start) : strtotime('-1 month', $now);
        $end_ts   = $end ? strtotime((string) $end) : strtotime('+' . $range_days . ' days', $now);
        if (($end_ts - $start_ts) > ($range_days * DAY_IN_SECONDS)) {
            $end_ts = $start_ts + ($range_days * DAY_IN_SECONDS);
        }
        $events = $this->build_calendar_events($start_ts, $end_ts, false);
        return rest_ensure_response($events);
    }

    /**
     * Bouw kalender events payload op basis van start/einde filter.
     *
     * @param int|null $start_ts
     * @param int|null $end_ts
     * @param bool     $admin_payload Voeg extra props toe (status/capacity/links).
     * @return array<int,array<string,mixed>>
     */
    private function build_calendar_events(?int $start_ts, ?int $end_ts, bool $admin_payload = false): array
    {
        $args = [
            'post_type'      => Settings::get_cpt_slug(),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ];

        $posts = get_posts($args);
        $events = [];
        foreach ($posts as $post) {
            $color = sanitize_hex_color((string) get_post_meta($post->ID, '_eh_color', true)) ?: '#2271b1';
            $status_meta = get_post_meta($post->ID, '_eh_status', true) ?: 'open';
            $location = get_post_meta($post->ID, '_eh_location', true);
            $is_online = (bool) get_post_meta($post->ID, '_eh_is_online', true);
            $term_list = wp_get_post_terms($post->ID, Settings::get_tax_slug(), ['fields' => 'names']);

            $occurrences = $this->registrations->get_occurrences($post->ID);
            if ($occurrences) {
                foreach ($occurrences as $occ) {
                    $occ_id = (int) ($occ['id'] ?? 0);
                    if ($occ_id <= 0) {
                        continue;
                    }
                    $date_start = $occ['date_start'] ?? '';
                    if (!$date_start) {
                        continue;
                    }
                    $occ_start_ts = strtotime($date_start);
                    if ($occ_start_ts && $start_ts && $occ_start_ts < $start_ts) {
                        continue;
                    }
                    if ($occ_start_ts && $end_ts && $occ_start_ts > $end_ts) {
                        continue;
                    }
                    $date_end = $occ['date_end'] ?? '';

                    $state = $this->registrations->get_capacity_state((int) $post->ID, $occ_id);
                    $status = $status_meta;
                    if ($admin_payload && !in_array($status, ['cancelled', 'closed'], true) && $state['is_full']) {
                        $status = 'full';
                    }
                    $capacity = $state['capacity'];
                    $booked = $state['booked'];
                    $available = ($capacity > 0) ? max(0, $capacity - $booked) : null;
                    $occupancy = ($capacity > 0) ? min(100, (int) round(($booked / max(1, $capacity)) * 100)) : null;

                    $event = [
                        'id' => $post->ID . '-' . $occ_id,
                        'title' => $post->post_title,
                        'start' => date('c', $occ_start_ts),
                        'end' => $date_end ? date('c', strtotime($date_end)) : null,
                        'url' => $admin_payload
                            ? add_query_arg(
                                [
                                    'page' => 'event-hub-event',
                                    'event_id' => $post->ID,
                                    'occurrence_id' => $occ_id,
                                ],
                                admin_url('admin.php')
                            )
                            : add_query_arg('eh_occurrence', $occ_id, get_permalink($post->ID)),
                        'backgroundColor' => $color,
                        'borderColor' => $color,
                    ];

                    if ($admin_payload) {
                        $event['classNames'] = ['eh-status-' . sanitize_html_class($status)];
                        $event['extendedProps'] = [
                            'status' => $status,
                            'event_id' => (int) $post->ID,
                            'occurrence_id' => $occ_id,
                            'location' => $location,
                            'is_online' => $is_online,
                            'capacity' => $capacity,
                            'booked' => $booked,
                            'available' => $available,
                            'occupancy' => $occupancy,
                            'terms' => $term_list,
                        ];
                    }

                    $events[] = $event;
                }
                continue;
            }

            $date_start = get_post_meta($post->ID, '_eh_date_start', true);
            if (!$date_start) {
                continue;
            }
            $post_start_ts = strtotime($date_start);
            if ($post_start_ts && $start_ts && $post_start_ts < $start_ts) {
                continue;
            }
            if ($post_start_ts && $end_ts && $post_start_ts > $end_ts) {
                continue;
            }
            $date_end = get_post_meta($post->ID, '_eh_date_end', true);

            $state = $this->registrations->get_capacity_state((int) $post->ID);
            $status = $status_meta;
            if ($admin_payload && !in_array($status, ['cancelled', 'closed'], true) && $state['is_full']) {
                $status = 'full';
            }
            $capacity = $state['capacity'];
            $booked = $state['booked'];
            $available = ($capacity > 0) ? max(0, $capacity - $booked) : null;
            $occupancy = ($capacity > 0) ? min(100, (int) round(($booked / max(1, $capacity)) * 100)) : null;

            $event = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'start' => date('c', $post_start_ts),
                'end' => $date_end ? date('c', strtotime($date_end)) : null,
                'url' => $admin_payload
                    ? add_query_arg(
                        [
                            'page' => 'event-hub-event',
                            'event_id' => $post->ID,
                        ],
                        admin_url('admin.php')
                    )
                    : get_permalink($post->ID),
                'backgroundColor' => $color,
                'borderColor' => $color,
            ];

            if ($admin_payload) {
                $event['classNames'] = ['eh-status-' . sanitize_html_class($status)];
                $event['extendedProps'] = [
                    'status' => $status,
                    'event_id' => (int) $post->ID,
                    'occurrence_id' => 0,
                    'location' => $location,
                    'is_online' => $is_online,
                    'capacity' => $capacity,
                    'booked' => $booked,
                    'available' => $available,
                    'occupancy' => $occupancy,
                    'terms' => $term_list,
                ];
            }

            $events[] = $event;
        }

        return $events;
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

        $admin_email = get_option('admin_email');
        echo '<div class="eh-card" style="margin-top:22px;max-width:720px;">';
        echo '<h2 style="margin-top:0;">' . esc_html__('Over de maker', 'event-hub') . '</h2>';
        echo '<p style="color:#52616b;margin:0 0 10px;">' . esc_html__('Event Hub is ontwikkeld en onderhouden door Michael Redant.', 'event-hub') . '</p>';
        echo '<p style="color:#52616b;margin:0;">' . esc_html__('Vragen of feedback? Stuur gerust een bericht.', 'event-hub') . '</p>';
        if ($admin_email) {
            echo '<p style="margin:8px 0 0;"><strong>E-mail:</strong> <a href="mailto:' . esc_attr($admin_email) . '">' . esc_html($admin_email) . '</a></p>';
        }
        $cron_key = get_option('event_hub_cron_key', '');
        if (!$cron_key) {
            $cron_key = wp_generate_password(16, false);
            update_option('event_hub_cron_key', $cron_key);
        }
        if ($cron_key) {
            $cron_url = add_query_arg(['event_hub_cron' => 1, 'key' => $cron_key], home_url('/'));
            echo '<div style="margin-top:12px;padding:10px;border:1px solid #e5e7eb;border-radius:8px;background:#f8fafc;">';
            echo '<strong>' . esc_html__('Cron-ping URL (extern aanroepen voor reminders):', 'event-hub') . '</strong><br>';
            echo '<code style="word-break:break-all;">' . esc_html($cron_url) . '</code>';
            echo '<p style="margin:6px 0 0;color:#52616b;font-size:12px;">' . esc_html__('Gebruik deze URL in een uptime/cron service (bv. elke minuut) om automatische e-mails exact op tijd te versturen, ook zonder WP-Cron.', 'event-hub') . '</p>';
            echo '</div>';
        }
        echo '</div>';

        echo '</div>';

    }


    public function enqueue_assets(string $hook): void
    {
        $ajax_disabled = defined('EVENT_HUB_DISABLE_AJAX') && EVENT_HUB_DISABLE_AJAX;

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

        $toast = null;

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
                'ajaxEnabled' => !$ajax_disabled,
                'restNonce' => wp_create_nonce('wp_rest'),
                'searchRestUrl' => rest_url('event-hub/v1/admin/search-linked'),

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
            wp_localize_script('event-hub-admin-ui', 'EventHubAdminUI', [
                'toast' => $toast,
                'ajaxEnabled' => !$ajax_disabled,
                'restNonce' => wp_create_nonce('wp_rest'),
                'searchRestUrl' => rest_url('event-hub/v1/admin/search-linked'),
            ]);
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

                'restUrl' => rest_url('event-hub/v1/admin/calendar'),

                'nonce'   => wp_create_nonce('wp_rest'),

                'labels'  => [

                    'loading' => __('Events laden...', 'event-hub'),

                    'error'   => __('Events konden niet worden opgehaald.', 'event-hub'),

                    'create'  => __('Nieuw event maken', 'event-hub'),
                    'export'  => __('Exporteer', 'event-hub'),
                    'export_title' => __('Selecteer events in deze maand', 'event-hub'),
                    'export_none' => __('Geen events in deze maand.', 'event-hub'),
                    'export_help' => __('Selecteer één of meerdere events/occurrences en kies een formaat.', 'event-hub'),
                    'format_csv' => __('CSV', 'event-hub'),
                    'format_xlsx' => __('XLSX', 'event-hub'),
                    'format_json' => __('JSON', 'event-hub'),

                ],

                'newEventUrl' => admin_url('post-new.php?post_type=' . Settings::get_cpt_slug()),
                'dashboardBase' => admin_url('admin.php?page=event-hub-event'),
                'registrationsBase' => admin_url('admin.php?page=event-hub-registrations'),
                'newRegistrationBase' => admin_url('admin.php?page=event-hub-registrations&action=new'),
                'exportNonce' => wp_create_nonce('eh_reg_export'),
                'exportBase' => admin_url('admin.php'),

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
            ['label' => __('No-show %', 'event-hub'), 'value' => $stats['no_show_rate']],
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

        echo '<div class="eh-panel">';
        echo '<div class="eh-panel__head"><h2>' . esc_html__('Per maand', 'event-hub') . '</h2></div>';
        if (!$stats['by_month']) {
            echo '<p>' . esc_html__('Geen data in deze periode.', 'event-hub') . '</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr>';
            echo '<th>' . esc_html__('Maand', 'event-hub') . '</th>';
            echo '<th>' . esc_html__('Inschrijvingen', 'event-hub') . '</th>';
            echo '<th>' . esc_html__('Bevestigd', 'event-hub') . '</th>';
            echo '<th>' . esc_html__('No-show', 'event-hub') . '</th>';
            echo '<th>' . esc_html__('% No-show', 'event-hub') . '</th>';
            echo '</tr></thead><tbody>';
            foreach ($stats['by_month'] as $row) {
                $rate = ($row['attended'] + $row['no_show']) > 0
                    ? round(($row['no_show'] / ($row['attended'] + $row['no_show'])) * 100, 1) . '%'
                    : '–';
                echo '<tr>';
                echo '<td>' . esc_html($row['label']) . '</td>';
                echo '<td>' . esc_html((string) $row['total']) . '</td>';
                echo '<td>' . esc_html((string) $row['confirmed']) . '</td>';
                echo '<td>' . esc_html((string) $row['no_show']) . '</td>';
                echo '<td>' . esc_html($rate) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
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



        $start_sql = esc_sql($start);
        $end_sql   = esc_sql($end);

        $regs = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$registrations_table} WHERE created_at BETWEEN '{$start_sql}' AND '{$end_sql}'"
        );

        $confirmed = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$registrations_table} WHERE created_at BETWEEN '{$start_sql}' AND '{$end_sql}' AND status = 'confirmed'"
        );

        $waitlist = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$registrations_table} WHERE created_at BETWEEN '{$start_sql}' AND '{$end_sql}' AND status = 'waitlist'"
        );



        $cpt_slug = sanitize_key(Settings::get_cpt_slug());
        $events = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND post_date BETWEEN %s AND %s",
            $cpt_slug,
            $start,
            $end
        ));

        // Template mails sent (logged via event_hub_email_sent)
        $template_mails = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_eh_email_log_time' AND meta_value BETWEEN '{$start_sql}' AND '{$end_sql}'"
        );

        $no_show = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$registrations_table} WHERE created_at BETWEEN '{$start_sql}' AND '{$end_sql}' AND status = 'no_show'"
        );
        $attended = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$registrations_table} WHERE created_at BETWEEN '{$start_sql}' AND '{$end_sql}' AND status = 'attended'"
        );

        // Top events by registrations in range
        $top_rows = $wpdb->get_results(
            "SELECT session_id, 
                SUM(CASE WHEN status = 'waitlist' THEN 1 ELSE 0 END) AS waitlist,
                COUNT(*) AS regs
             FROM {$registrations_table}
             WHERE created_at BETWEEN '{$start_sql}' AND '{$end_sql}'
             GROUP BY session_id
             ORDER BY regs DESC
             LIMIT 5",
            ARRAY_A
        ) ?: [];

        $by_month_rows = $wpdb->get_results(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym,
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed,
                SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) AS no_show,
                SUM(CASE WHEN status = 'attended' THEN 1 ELSE 0 END) AS attended
             FROM {$registrations_table}
             WHERE created_at BETWEEN '{$start_sql}' AND '{$end_sql}'
             GROUP BY ym
             ORDER BY ym DESC
             LIMIT 6",
            ARRAY_A
        ) ?: [];



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



        $by_month = [];
        foreach ($by_month_rows as $row) {
            $raw_label = $row['ym'] ?? '';
            $label_fmt = $raw_label !== '' ? date_i18n('F Y', strtotime($raw_label . '-01')) : '';
            $by_month[] = [
                'label' => $label_fmt ?: $raw_label,
                'total' => (int) ($row['total'] ?? 0),
                'confirmed' => (int) ($row['confirmed'] ?? 0),
                'no_show' => (int) ($row['no_show'] ?? 0),
                'attended' => (int) ($row['attended'] ?? 0),
            ];
        }

        $no_show_rate = ($attended + $no_show) > 0 ? round(($no_show / ($attended + $no_show)) * 100, 1) . '%' : '–';

        return [
            'registrations' => $regs,
            'confirmed' => $confirmed,
            'waitlist' => $waitlist,
            'events' => $events,
            'template_mails' => $template_mails,
            'top_events' => $top_events,
            'by_month' => $by_month,
            'no_show_rate' => $no_show_rate,
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
        if (isset($_GET['eh_logs_deleted'])) {
            $msg = $_GET['eh_logs_deleted'] === 'all' ? __('Alle logs verwijderd.', 'event-hub') : __('Geselecteerde logs verwijderd.', 'event-hub');
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg) . '</p></div>';
        }
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
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="event_hub_delete_logs">';
            wp_nonce_field('event_hub_delete_logs', 'event_hub_delete_logs_nonce');
            echo '<div style="margin-bottom:10px; display:flex; gap:8px; align-items:center;">';
            echo '<button type="submit" name="delete_selected" class="button button-secondary" onclick="return confirm(\'' . esc_js(__('Verwijder geselecteerde logs?', 'event-hub')) . '\');">' . esc_html__('Verwijder geselecteerde', 'event-hub') . '</button>';
            echo '<button type="submit" name="delete_all" class="button button-secondary" onclick="return confirm(\'' . esc_js(__('Alle logs verwijderen?', 'event-hub')) . '\');">' . esc_html__('Verwijder alle', 'event-hub') . '</button>';
            echo '</div>';
            echo '<div class="eh-timeline" style="gap:12px;">';
            foreach ($filtered as $key => $log) {
                $ts = isset($log['ts']) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), (int) $log['ts']) : '';
                $type = $log['type'] ?? '';
                $msg = $log['message'] ?? '';
                $ctx = $log['context'] ?? [];
                echo '<div class="eh-row-card" style="padding:14px 16px;">';
                echo '<div class="eh-row-main" style="grid-template-columns:24px 1fr 120px;align-items:start; gap:12px;">';
                echo '<div style="padding-top:4px;"><input type="checkbox" name="log_keys[]" value="' . esc_attr((string) $key) . '" /></div>';
                echo '<div>';
                echo '<div class="eh-row-title" style="margin-bottom:4px;">' . esc_html($msg) . '</div>';
                echo '<div class="eh-row-sub" style="margin-bottom:6px;">' . esc_html($ts) . '</div>';
                if (is_array($ctx) && $ctx) {
                    echo '<div class="eh-row-sub" style="display:flex;flex-wrap:wrap;gap:6px;">';
                    foreach ($ctx as $k => $v) {
                        echo '<span class="eh-chip" style="padding:4px 8px;border-radius:8px;background:var(--eh-surface-subtle);border:1px solid var(--eh-border);">' . esc_html($k) . ': ' . esc_html((string) $v) . '</span>';
                    }
                    echo '</div>';
                }
                echo '</div>';
                echo '<div class="eh-cal-badges" style="justify-content:flex-end;">';
                echo '<span class="eh-pill gray">' . esc_html($type ?: 'log') . '</span>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
            echo '</form>';
        }
        echo '</div>';
    }

    public function handle_delete_logs(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Je hebt geen toegang.', 'event-hub'));
        }
        if (!isset($_POST['event_hub_delete_logs_nonce']) || !wp_verify_nonce(sanitize_text_field((string) $_POST['event_hub_delete_logs_nonce']), 'event_hub_delete_logs')) {
            wp_die(__('Ongeldige aanvraag.', 'event-hub'));
        }
        $redirect = add_query_arg('page', 'event-hub-logs', admin_url('admin.php'));
        if (isset($_POST['delete_all'])) {
            if ($this->logger) {
                $this->logger->clear();
            }
            wp_safe_redirect(add_query_arg('eh_logs_deleted', 'all', $redirect));
            exit;
        }
        $keys = isset($_POST['log_keys']) && is_array($_POST['log_keys']) ? array_map('sanitize_text_field', (array) $_POST['log_keys']) : [];
        if ($keys && $this->logger) {
            $this->logger->delete($keys);
            wp_safe_redirect(add_query_arg('eh_logs_deleted', 'some', $redirect));
            exit;
        }
        wp_safe_redirect($redirect);
        exit;
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
            $occurrence_id = isset($_GET['occurrence_id']) ? (int) $_GET['occurrence_id'] : 0;

            $status = isset($_GET['status']) ? sanitize_text_field((string) $_GET['status']) : '';

            $name = isset($_GET['name']) ? sanitize_text_field((string) $_GET['name']) : '';

            $this->export_registrations_csv($session_id, $status, $name, $occurrence_id);

            return;

        }

        if (isset($_GET['download']) && $_GET['download'] === 'xlsx') {

            $session_id = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;
            $occurrence_id = isset($_GET['occurrence_id']) ? (int) $_GET['occurrence_id'] : 0;

            $status = isset($_GET['status']) ? sanitize_text_field((string) $_GET['status']) : '';

            $name = isset($_GET['name']) ? sanitize_text_field((string) $_GET['name']) : '';

            $this->export_registrations_xlsx($session_id, $status, $name, $occurrence_id);

            return;

        }

        if (isset($_GET['download']) && $_GET['download'] === 'json') {

            $session_id = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;
            $occurrence_id = isset($_GET['occurrence_id']) ? (int) $_GET['occurrence_id'] : 0;

            $status = isset($_GET['status']) ? sanitize_text_field((string) $_GET['status']) : '';

            $name = isset($_GET['name']) ? sanitize_text_field((string) $_GET['name']) : '';

            $this->export_registrations_json($session_id, $status, $name, $occurrence_id);

            return;

        }

        if (isset($_GET['download']) && $_GET['download'] === 'html') {

            $session_id = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;
            $occurrence_id = isset($_GET['occurrence_id']) ? (int) $_GET['occurrence_id'] : 0;

            $status = isset($_GET['status']) ? sanitize_text_field((string) $_GET['status']) : '';

            $name = isset($_GET['name']) ? sanitize_text_field((string) $_GET['name']) : '';

            $this->render_printable_registrations($session_id, $status, $name, $occurrence_id);

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

        $occurrence_id = isset($_GET['occurrence_id']) ? (int) $_GET['occurrence_id'] : 0;

        $status     = isset($_GET['status']) ? sanitize_text_field((string) $_GET['status']) : '';

        $name       = isset($_GET['name']) ? sanitize_text_field((string) $_GET['name']) : '';



        $sessions = get_posts([

            'post_type'   => Settings::get_cpt_slug(),

            'numberposts' => -1,

            'orderby'     => 'title',

            'order'       => 'ASC',

        ]);

        $occurrences = $session_id > 0 ? $this->registrations->get_occurrences($session_id) : [];



        echo '<div class="wrap eh-admin">';

        $new_url = add_query_arg(

            [

                'page' => 'event-hub-registrations',

                'action' => 'new',

                'session_id' => $session_id ?: '',

                'occurrence_id' => $occurrence_id ?: '',

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

        if ($occurrences) {
            echo '<select name="occurrence_id">';
            echo '<option value="0">' . esc_html__('Alle datums', 'event-hub') . '</option>';
            foreach ($occurrences as $occ) {
                $occ_id = (int) ($occ['id'] ?? 0);
                if ($occ_id <= 0) {
                    continue;
                }
                $start = $occ['date_start'] ?? '';
                $label = $start ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($start)) : ('#' . $occ_id);
                echo '<option value="' . esc_attr((string) $occ_id) . '"' . selected($occurrence_id, $occ_id, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
        }

        echo '<select name="status">';

        echo '<option value="">' . esc_html__('Alle statussen', 'event-hub') . '</option>';

        $statuses = $this->get_status_labels();

        $occurrence_map = [];
        foreach ($sessions as $session) {
            $occurrences = $this->registrations->get_occurrences($session->ID);
            if (!$occurrences) {
                continue;
            }
            $items = [];
            foreach ($occurrences as $occ) {
                $occ_id = (int) ($occ['id'] ?? 0);
                if ($occ_id <= 0) {
                    continue;
                }
                $start = $occ['date_start'] ?? '';
                $label = $start ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($start)) : ('#' . $occ_id);
                $items[] = [
                    'id' => $occ_id,
                    'label' => $label,
                ];
            }
            if ($items) {
                $occurrence_map[$session->ID] = $items;
            }
        }

        foreach ($statuses as $key => $label) {

            echo '<option value="' . esc_attr($key) . '"' . selected($status, $key, false) . '>' . esc_html($label) . '</option>';

        }

        echo '</select>';

        echo '<input type="text" name="name" placeholder="' . esc_attr__('Zoek op naam', 'event-hub') . '" value="' . esc_attr($name) . '" />';

        submit_button(__('Filteren', 'event-hub'), 'primary', '', false);

        echo '<select name="download" style="margin-left:8px;">';
        echo '<option value="csv">' . esc_html__('Exporteren (CSV)', 'event-hub') . '</option>';
        echo '<option value="xlsx">' . esc_html__('Exporteren (XLSX)', 'event-hub') . '</option>';
        echo '<option value="json">' . esc_html__('Exporteren (JSON)', 'event-hub') . '</option>';
        echo '<option value="html">' . esc_html__('Printbare lijst (HTML)', 'event-hub') . '</option>';
        echo '</select>';
        echo '<button type="submit" class="button button-secondary">' . esc_html__('Download', 'event-hub') . '</button>';

        echo '</form>';



        $registrations = $this->fetch_registrations($session_id, $status, $name, $occurrence_id);



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
                $occurrence_label = $this->format_occurrence_label($session_id, (int) ($row['occurrence_id'] ?? 0));

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
                if ($occurrence_label) {
                    echo '<div class="eh-row-sub">' . esc_html($occurrence_label) . '</div>';
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



    private function fetch_registrations(int $session_id, string $status, string $name = '', int $occurrence_id = 0): array

    {

        if ($session_id > 0) {

            $registrations = $this->registrations->get_registrations_by_session($session_id, $occurrence_id);

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
        $preselect_occurrence = isset($_GET['occurrence_id']) ? (int) $_GET['occurrence_id'] : 0;

        $statuses = $this->get_status_labels();

        $error = '';



        if (

            isset($_POST['eh_add_registration_nonce'])

            && wp_verify_nonce(sanitize_text_field((string) $_POST['eh_add_registration_nonce']), 'eh_add_registration')

        ) {

            $payload = [

                'session_id' => isset($_POST['session_id']) ? (int) $_POST['session_id'] : 0,

                'occurrence_id' => isset($_POST['occurrence_id']) ? (int) $_POST['occurrence_id'] : 0,

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

                            'occurrence_id' => $payload['occurrence_id'] ?: '',

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
        $current_occurrence = isset($_POST['occurrence_id']) ? (int) $_POST['occurrence_id'] : $preselect_occurrence;



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

        echo '<div class="field" id="eh-occurrence-field" style="display:none;">';
        echo '<label for="occurrence_id">' . esc_html__('Datum', 'event-hub') . '</label>';
        echo '<select id="occurrence_id" name="occurrence_id"></select>';
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

        $occurrence_json = wp_json_encode($occurrence_map);
        echo '<script>document.addEventListener("DOMContentLoaded",function(){var sessionSelect=document.getElementById("session_id");var field=document.getElementById("eh-occurrence-field");var occurrenceSelect=document.getElementById("occurrence_id");if(!sessionSelect||!field||!occurrenceSelect){return;}var map=' . $occurrence_json . ';var current=' . (int) $current_occurrence . ';function render(){var sessionId=parseInt(sessionSelect.value||"0",10);var items=map[sessionId]||[];occurrenceSelect.innerHTML="";if(!items.length){field.style.display="none";occurrenceSelect.required=false;return;}field.style.display="";occurrenceSelect.required=true;var placeholder=document.createElement("option");placeholder.value="";placeholder.textContent="' . esc_js(__('Selecteer datum', 'event-hub')) . '";occurrenceSelect.appendChild(placeholder);items.forEach(function(item){var opt=document.createElement("option");opt.value=String(item.id);opt.textContent=item.label;if(current&&item.id===current){opt.selected=true;}occurrenceSelect.appendChild(opt);});if(!occurrenceSelect.value&&items.length===1){occurrenceSelect.value=String(items[0].id);} }sessionSelect.addEventListener("change",function(){current=0;render();});render();});</script>';

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

        $posts = get_posts($args);
        $events = [];
        foreach ($posts as $post) {
            $color = sanitize_hex_color((string) get_post_meta($post->ID, '_eh_color', true)) ?: '#2271b1';
            $status_meta = get_post_meta($post->ID, '_eh_status', true) ?: 'open';
            $location = get_post_meta($post->ID, '_eh_location', true);
            $is_online = (bool) get_post_meta($post->ID, '_eh_is_online', true);
            $term_list = wp_get_post_terms($post->ID, Settings::get_tax_slug(), ['fields' => 'names']);

            $occurrences = $this->registrations->get_occurrences($post->ID);
            if ($occurrences) {
                foreach ($occurrences as $occ) {
                    $occ_id = (int) ($occ['id'] ?? 0);
                    if ($occ_id <= 0) {
                        continue;
                    }
                    $date_start = $occ['date_start'] ?? '';
                    if (!$date_start) {
                        continue;
                    }
                    $start_ts = strtotime($date_start);
                    if ($start_ts && $start && $end && ($start_ts < $start || $start_ts > $end)) {
                        continue;
                    }
                    $date_end = $occ['date_end'] ?? '';
                    $state = $this->registrations->get_capacity_state((int) $post->ID, $occ_id);
                    $status = $status_meta;
                    if (!in_array($status, ['cancelled', 'closed'], true) && $state['is_full']) {
                        $status = 'full';
                    }
                    $capacity = $state['capacity'];
                    $booked = $state['booked'];
                    $available = ($capacity > 0) ? max(0, $capacity - $booked) : null;
                    $occupancy = ($capacity > 0) ? min(100, (int) round(($booked / max(1, $capacity)) * 100)) : null;

                    $events[] = [
                        'id' => $post->ID . '-' . $occ_id,
                        'title' => $post->post_title,
                        'start' => date('c', $start_ts),
                        'end' => $date_end ? date('c', strtotime($date_end)) : null,
                        'url' => add_query_arg(
                            [
                                'page' => 'event-hub-event',
                                'event_id' => $post->ID,
                                'occurrence_id' => $occ_id,
                            ],
                            admin_url('admin.php')
                        ),
                        'backgroundColor' => $color,
                        'borderColor' => $color,
                        'classNames' => ['eh-status-' . sanitize_html_class($status)],
                        'extendedProps' => [
                            'status' => $status,
                            'event_id' => (int) $post->ID,
                            'occurrence_id' => $occ_id,
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
                continue;
            }

            $date_start = get_post_meta($post->ID, '_eh_date_start', true);
            if (!$date_start) {
                continue;
            }
            $start_ts = strtotime($date_start);
            if ($start_ts && $start && $end && ($start_ts < $start || $start_ts > $end)) {
                continue;
            }
            $date_end = get_post_meta($post->ID, '_eh_date_end', true);
            $state = $this->registrations->get_capacity_state((int) $post->ID);
            $status = $status_meta;
            if (!in_array($status, ['cancelled', 'closed'], true) && $state['is_full']) {
                $status = 'full';
            }
            $capacity = $state['capacity'];
            $booked = $state['booked'];
            $available = ($capacity > 0) ? max(0, $capacity - $booked) : null;
            $occupancy = ($capacity > 0) ? min(100, (int) round(($booked / max(1, $capacity)) * 100)) : null;

            $events[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'start' => date('c', $start_ts),
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
                    'event_id' => (int) $post->ID,
                    'occurrence_id' => 0,
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
        ];

        $posts = get_posts($args);
        $events = [];
        foreach ($posts as $post) {
            $color = sanitize_hex_color((string) get_post_meta($post->ID, '_eh_color', true)) ?: '#2271b1';

            $occurrences = $this->registrations->get_occurrences($post->ID);
            if ($occurrences) {
                foreach ($occurrences as $occ) {
                    $occ_id = (int) ($occ['id'] ?? 0);
                    if ($occ_id <= 0) {
                        continue;
                    }
                    $date_start = $occ['date_start'] ?? '';
                    if (!$date_start) {
                        continue;
                    }
                    $start_ts = strtotime($date_start);
                    if ($start_ts && $start && $end && ($start_ts < $start || $start_ts > $end)) {
                        continue;
                    }
                    $date_end = $occ['date_end'] ?? '';
                    $events[] = [
                        'id'    => $post->ID . '-' . $occ_id,
                        'title' => $post->post_title,
                        'start' => date('c', $start_ts),
                        'end'   => $date_end ? date('c', strtotime($date_end)) : null,
                        'url'   => add_query_arg('eh_occurrence', $occ_id, get_permalink($post->ID)),
                        'backgroundColor' => $color,
                        'borderColor'     => $color,
                    ];
                }
                continue;
            }

            $date_start = get_post_meta($post->ID, '_eh_date_start', true);
            if (!$date_start) {
                continue;
            }
            $start_ts = strtotime($date_start);
            if ($start_ts && $start && $end && ($start_ts < $start || $start_ts > $end)) {
                continue;
            }
            $date_end = get_post_meta($post->ID, '_eh_date_end', true);
            $events[] = [
                'id'    => $post->ID,
                'title' => $post->post_title,
                'start' => date('c', $start_ts),
                'end'   => $date_end ? date('c', strtotime($date_end)) : null,
                'url'   => get_permalink($post->ID),
                'backgroundColor' => $color,
                'borderColor'     => $color,
            ];
        }

        wp_send_json_success($events);
    }




    private function export_registrations_csv(int $session_id, string $status, string $name, int $occurrence_id = 0): void

    {

        if (!isset($_GET['download_nonce']) || !wp_verify_nonce(sanitize_text_field((string) $_GET['download_nonce']), 'eh_reg_export')) {

            wp_die(__('Ongeldige exportaanvraag.', 'event-hub'));

        }

        if (!current_user_can('edit_posts')) {

            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));

        }



        $rows = $this->fetch_registrations($session_id, $status, $name, $occurrence_id);
        $colleagues_label = $this->get_event_colleagues_label($session_id);
        $extra_map = $this->collect_extra_field_map($rows);

        nocache_headers();

        header('Content-Type: text/csv; charset=utf-8');

        header('Content-Disposition: attachment; filename="event-hub-registraties-' . gmdate('Ymd-His') . '.csv"');



        $out = fopen('php://output', 'w');

        fputcsv($out, [
            'ID',
            'Event',
            'Datum',
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

            $occurrence_label = $this->format_occurrence_label((int) $row['session_id'], (int) ($row['occurrence_id'] ?? 0));
            fputcsv($out, [

                $row['id'],

                $title,

                $occurrence_label,

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

    private function export_registrations_json(int $session_id, string $status, string $name, int $occurrence_id = 0): void
    {
        if (!isset($_GET['download_nonce']) || !wp_verify_nonce(sanitize_text_field((string) $_GET['download_nonce']), 'eh_reg_export')) {
            wp_die(__('Ongeldige exportaanvraag.', 'event-hub'));
        }
        if (!current_user_can('edit_posts')) {
            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));
        }

        $rows = $this->fetch_registrations($session_id, $status, $name, $occurrence_id);
        $colleagues_label = $this->get_event_colleagues_label($session_id);
        $extra_map = $this->collect_extra_field_map($rows);

        $payload = [];
        foreach ($rows as $row) {
            $extra = [];
            if (!empty($row['extra_data'])) {
                $decoded = json_decode((string) $row['extra_data'], true);
                if (is_array($decoded)) {
                    $extra = $decoded;
                }
            }
            $entry = [
                'id' => $row['id'],
                'event_id' => $row['session_id'],
                'event' => get_the_title((int) $row['session_id']),
                'occurrence' => $this->format_occurrence_label((int) $row['session_id'], (int) ($row['occurrence_id'] ?? 0)),
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'status' => $row['status'],
                'people_count' => $row['people_count'],
                'phone' => $row['phone'],
                'company' => $row['company'],
                'vat' => $row['vat'],
                'role' => $row['role'],
                'marketing' => (bool) $row['consent_marketing'],
                'colleagues' => $colleagues_label,
                'created_at' => $row['created_at'],
                'extra' => [],
            ];
            foreach (array_keys($extra_map) as $slug) {
                $entry['extra'][$slug] = $extra[$slug] ?? null;
            }
            $payload[] = $entry;
        }

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="event-hub-registraties-' . gmdate('Ymd-His') . '.json"');
        echo wp_json_encode($payload);
        exit;
    }

    private function export_registrations_xlsx(int $session_id, string $status, string $name, int $occurrence_id = 0): void
    {
        if (!isset($_GET['download_nonce']) || !wp_verify_nonce(sanitize_text_field((string) $_GET['download_nonce']), 'eh_reg_export')) {
            wp_die(__('Ongeldige exportaanvraag.', 'event-hub'));
        }
        if (!current_user_can('edit_posts')) {
            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));
        }

        $rows = $this->fetch_registrations($session_id, $status, $name, $occurrence_id);
        $colleagues_label = $this->get_event_colleagues_label($session_id);
        $extra_map = $this->collect_extra_field_map($rows);

        $headers = [
            'ID',
            'Event',
            'Datum',
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
        ];

        $data = [];
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
            $occurrence_label = $this->format_occurrence_label((int) $row['session_id'], (int) ($row['occurrence_id'] ?? 0));
            $data[] = [
                $row['id'],
                $title,
                $occurrence_label,
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
            ];
        }

        $outRows = array_merge([$headers], $data);
        if (empty($outRows)) {
            wp_die(__('Geen data om te exporteren.', 'event-hub'));
        }
        $this->output_xlsx($outRows, 'event-hub-registraties-' . gmdate('Ymd-His') . '.xlsx');
    }

    /**
     * Lightweight XLSX exporter (inline strings). Falls back to CSV if ZipArchive is missing.
     *
     * @param array<int,array<int|string>> $rows
     */
    private function output_xlsx(array $rows, string $filename): void
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->output_csv_fallback($rows, $filename . '.csv');
            return;
        }

        $sheetRows = [];
        $rowIndex = 1;
        foreach ($rows as $row) {
            $cells = [];
            $colIndex = 0;
            foreach ($row as $cell) {
                $colIndex++;
                $col = $this->xlsx_col_name($colIndex);
                $cells[] = '<c r="' . $col . $rowIndex . '" t="inlineStr"><is><t>' . esc_xml((string) $cell) . '</t></is></c>';
            }
            $sheetRows[] = '<row r="' . $rowIndex . '">' . implode('', $cells) . '</row>';
            $rowIndex++;
        }

        $sheetXml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . implode('', $sheetRows)
            . '</sheetData></worksheet>';

        $workbookXml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Registraties" sheetId="1" r:id="rId1"/></sheets></workbook>';

        $relsXml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $wbRelsXml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';

        $stylesXml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font/></fonts><fills count="1"><fill/></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="1"><xf xfId="0"/></cellXfs></styleSheet>';

        $zip = new \ZipArchive();
        $tmp = wp_tempnam($filename);
        if (!$tmp || $zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            $this->output_csv_fallback($rows, $filename . '.csv');
            return;
        }
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $relsXml);
        $zip->addFromString('xl/workbook.xml', $workbookXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRelsXml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->addFromString('xl/styles.xml', $stylesXml);
        $zip->close();

        nocache_headers();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    /**
     * Simple fallback to CSV if XLSX cannot be gebouwd.
     *
     * @param array<int,array<int|string>> $rows
     */
    private function output_csv_fallback(array $rows, string $filename): void
    {
        nocache_headers();
        @set_time_limit(0);
        wp_raise_memory_limit('admin');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        $out = fopen('php://output', 'w');
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    private function xlsx_col_name(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = (int) floor($index / 26);
        }
        return $name;
    }



    private function render_printable_registrations(int $session_id, string $status, string $name, int $occurrence_id = 0): void

    {

        if (!isset($_GET['download_nonce']) || !wp_verify_nonce(sanitize_text_field((string) $_GET['download_nonce']), 'eh_reg_export')) {

            wp_die(__('Ongeldige exportaanvraag.', 'event-hub'));

        }

        if (!current_user_can('edit_posts')) {

            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));

        }

        $rows = $this->fetch_registrations($session_id, $status, $name, $occurrence_id);

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
            __('Datum', 'event-hub'),

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
                $occurrence_label = $this->format_occurrence_label((int) $row['session_id'], (int) ($row['occurrence_id'] ?? 0));

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

                echo '<td>' . esc_html($occurrence_label) . '</td>';

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
        $occurrence_id = isset($_GET['occurrence_id']) ? (int) $_GET['occurrence_id'] : 0;

        if (!$event_id) {

            echo '<div class="wrap eh-admin"><div class="notice notice-error"><p>' . esc_html__('Event niet gevonden.', 'event-hub') . '</p></div></div>';

            return;

        }

        if (isset($_GET['download'], $_GET['nonce']) && $_GET['download'] === 'csv') {
            $this->export_event_dashboard_csv($event_id, sanitize_text_field((string) $_GET['nonce']), $occurrence_id);
            return;
        }
        if (isset($_GET['download'], $_GET['nonce']) && $_GET['download'] === 'xlsx') {
            $this->export_event_dashboard_xlsx($event_id, sanitize_text_field((string) $_GET['nonce']), $occurrence_id);
            return;
        }
        if (isset($_GET['download'], $_GET['nonce']) && $_GET['download'] === 'json') {
            $this->export_event_dashboard_json($event_id, sanitize_text_field((string) $_GET['nonce']), $occurrence_id);
            return;
        }



        $event = get_post($event_id);

        if (!$event || $event->post_type !== Settings::get_cpt_slug()) {

            echo '<div class="wrap eh-admin"><div class="notice notice-error"><p>' . esc_html__('Event niet gevonden.', 'event-hub') . '</p></div></div>';

            return;

        }

        $occurrences = $this->registrations->get_occurrences($event_id);
        $selected_occurrence = $occurrence_id > 0 ? $this->registrations->get_occurrence($event_id, $occurrence_id) : null;

        $registrations = $this->registrations->get_registrations_by_session($event_id, $occurrence_id);
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

        $state = $this->registrations->get_capacity_state($event_id, $occurrence_id);
        $status_counts = [];
        foreach ($registrations as $row) {
            $st = $row['status'] ?? '';
            if ($st === '') {
                continue;
            }
            if (!isset($status_counts[$st])) {
                $status_counts[$st] = 0;
            }
            $status_counts[$st]++;
        }

        $occurrence_stats = [];
        $booked_total = 0;
        $waitlist_total = 0;
        if ($occurrences) {
            foreach ($occurrences as $occ) {
                $occ_id = (int) ($occ['id'] ?? 0);
                if ($occ_id <= 0) {
                    continue;
                }
                $state_occ = $this->registrations->get_capacity_state($event_id, $occ_id);
                $booked_total += $state_occ['booked'];
                $waitlist_total += $state_occ['waitlist'];
                $label = $occ['date_start'] ?? '';
                $label = $label ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($label)) : ('#' . $occ_id);
                $occurrence_stats[] = [
                    'id' => $occ_id,
                    'label' => $label,
                    'booked' => $state_occ['booked'],
                    'capacity' => $state_occ['capacity'],
                    'waitlist' => $state_occ['waitlist'],
                    'is_full' => !empty($state_occ['is_full']),
                ];
            }
        } else {
            $booked_total = $state['booked'];
            $waitlist_total = $state['waitlist'];
        }

        $status = get_post_meta($event_id, '_eh_status', true) ?: 'open';

        $date_start = $selected_occurrence ? ($selected_occurrence['date_start'] ?? '') : get_post_meta($event_id, '_eh_date_start', true);

        $date_end = $selected_occurrence ? ($selected_occurrence['date_end'] ?? '') : get_post_meta($event_id, '_eh_date_end', true);

        $location = get_post_meta($event_id, '_eh_location', true);

        $is_online = (bool) get_post_meta($event_id, '_eh_is_online', true);

        $booking_open = $selected_occurrence ? ($selected_occurrence['booking_open'] ?? '') : get_post_meta($event_id, '_eh_booking_open', true);

        $booking_close = $selected_occurrence ? ($selected_occurrence['booking_close'] ?? '') : get_post_meta($event_id, '_eh_booking_close', true);

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

        echo '<h2>' . esc_html(get_the_title($event_id)) . '</h2>';
        echo '<div class="eh-dashboard-meta">';
        echo '<span class="eh-badge-pill status-' . esc_attr($status) . '">' . esc_html(ucfirst($status)) . '</span>';
        echo '<span class="eh-badge-pill status-open">' . esc_html(sprintf(_n('%d inschrijving', '%d inschrijvingen', $booked_total, 'event-hub'), $booked_total)) . '</span>';
        if ($waitlist_total > 0) {
            echo '<span class="eh-badge-pill status-waitlist">' . esc_html(sprintf(_n('%d wachtlijst', '%d wachtlijst', $waitlist_total, 'event-hub'), $waitlist_total)) . '</span>';
        }
        echo '</div>';
        echo '</div>';

        echo '<div class="eh-dashboard-actions">';

        echo '<a class="button button-primary" href="' . esc_url($edit_link) . '">' . esc_html__('Bewerk evenement', 'event-hub') . '</a>';

        echo '<a class="button" href="' . esc_url(get_permalink($event_id)) . '" target="_blank" rel="noopener">' . esc_html__('Bekijk op site', 'event-hub') . '</a>';

        echo '<a class="button" href="' . esc_url(add_query_arg(['page' => 'event-hub-registrations', 'session_id' => $event_id, 'occurrence_id' => $occurrence_id ?: ''], admin_url('admin.php'))) . '">' . esc_html__('Inschrijvingenlijst', 'event-hub') . '</a>';

        echo '<a class="button" href="' . esc_url(add_query_arg(['page' => 'event-hub-registrations', 'action' => 'new', 'session_id' => $event_id, 'occurrence_id' => $occurrence_id ?: ''], admin_url('admin.php'))) . '">' . esc_html__('Nieuwe inschrijving', 'event-hub') . '</a>';

        $export_args = ['page' => 'event-hub-event', 'event_id' => $event_id, 'occurrence_id' => $occurrence_id ?: '', 'nonce' => wp_create_nonce('eh_event_csv_' . $event_id)];
        echo '<a class="button" href="' . esc_url(add_query_arg(array_merge($export_args, ['download' => 'csv']), admin_url('admin.php'))) . '">' . esc_html__('Download CSV', 'event-hub') . '</a>';
        echo '<a class="button" href="' . esc_url(add_query_arg(array_merge($export_args, ['download' => 'xlsx']), admin_url('admin.php'))) . '">' . esc_html__('Download XLSX', 'event-hub') . '</a>';
        echo '<a class="button" href="' . esc_url(add_query_arg(array_merge($export_args, ['download' => 'json']), admin_url('admin.php'))) . '">' . esc_html__('Download JSON', 'event-hub') . '</a>';

        if ($templates) {

            echo '<form method="post" class="eh-bulk-mail-form">';

            wp_nonce_field('eh_event_bulk_mail_' . $event_id, 'eh_event_bulk_mail_nonce');

            echo '<input type="hidden" name="event_id" value="' . esc_attr((string) $event_id) . '">';
            echo '<input type="hidden" name="occurrence_id" value="' . esc_attr((string) $occurrence_id) . '">';

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

        if ($occurrence_stats) {
            echo '<div class="eh-occurrence-chips" style="margin:12px 0;display:flex;gap:8px;flex-wrap:wrap;">';
            $all_url = add_query_arg(['event_id' => $event_id, 'page' => 'event-hub-event', 'occurrence_id' => ''], admin_url('admin.php'));
            echo '<a class="eh-chip ' . ($occurrence_id === 0 ? 'active' : '') . '" href="' . esc_url($all_url) . '">' . esc_html__('Alle datums', 'event-hub') . '</a>';
            foreach ($occurrence_stats as $occ_stat) {
                $is_active = $occurrence_id === (int) $occ_stat['id'];
                $chip_url = add_query_arg(['event_id' => $event_id, 'page' => 'event-hub-event', 'occurrence_id' => (int) $occ_stat['id']], admin_url('admin.php'));
                $label = $occ_stat['label'];
                $badge = $occ_stat['capacity'] > 0 ? sprintf('%d/%d', $occ_stat['booked'], $occ_stat['capacity']) : (string) $occ_stat['booked'];
                if ($occ_stat['waitlist'] > 0) {
                    $badge .= ' +' . $occ_stat['waitlist'];
                }
                $class = 'eh-chip' . ($is_active ? ' active' : '');
                if (!empty($occ_stat['is_full'])) {
                    $class .= ' eh-chip-full';
                }
                echo '<a class="' . esc_attr($class) . '" href="' . esc_url($chip_url) . '"><span class="eh-chip-title">' . esc_html($label) . '</span><span class="count">' . esc_html($badge) . '</span></a>';
            }
            echo '</div>';
        }



        echo '<div class="eh-grid stats">';

        $cards = [

            ['label' => __('Status', 'event-hub'), 'value' => ucfirst($status)],

            ['label' => __('Startmoment', 'event-hub'), 'value' => $date_start ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($date_start)) : '—'],

            ['label' => __('Einde', 'event-hub'), 'value' => $date_end ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($date_end)) : '—'],

            ['label' => __('Locatie', 'event-hub'), 'value' => $is_online ? __('Online', 'event-hub') : ($location ?: '—')],

            ['label' => __('Inschrijvingen', 'event-hub'), 'value' => (string) $booked_total],

            ['label' => __('Beschikbaar', 'event-hub'), 'value' => $state['capacity'] > 0 ? sprintf('%d / %d', $state['available'], $state['capacity']) : __('Onbeperkt', 'event-hub')],

            ['label' => __('Boekingsstart', 'event-hub'), 'value' => $booking_open ? date_i18n(get_option('date_format'), strtotime($booking_open)) : '—'],

            ['label' => __('Boekingseinde', 'event-hub'), 'value' => $booking_close ? date_i18n(get_option('date_format'), strtotime($booking_close)) : '—'],

            ['label' => __('Wachtlijst', 'event-hub'), 'value' => (string) $waitlist_total],

        ];

        foreach ($cards as $card) {

            echo '<div class="eh-card stat"><h3>' . esc_html($card['label']) . '</h3><p>' . esc_html($card['value']) . '</p></div>';

        }

        echo '</div>';



        // Search/filter bar for participants and waitlist

        if ($status_counts) {
            echo '<div class="eh-status-summary" style="margin:8px 0 4px;display:flex;gap:8px;flex-wrap:wrap;">';
            foreach ($status_counts as $st => $count) {
                echo '<span class="eh-badge-pill status-' . esc_attr($st) . '">' . esc_html(ucfirst($st)) . ': ' . esc_html((string) $count) . '</span>';
            }
            echo '</div>';
        }

        $search_query = $search ?: '';

        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" class="eh-filter-bar" style="margin-top:12px">';

        echo '<input type="hidden" name="page" value="event-hub-event">';

        echo '<input type="hidden" name="event_id" value="' . esc_attr((string) $event_id) . '">';

        if ($occurrences) {
            echo '<select name="occurrence_id">';
            echo '<option value="0">' . esc_html__('Alle datums', 'event-hub') . '</option>';
            foreach ($occurrences as $occ) {
                $occ_id = (int) ($occ['id'] ?? 0);
                if ($occ_id <= 0) {
                    continue;
                }
                $start = $occ['date_start'] ?? '';
                $label = $start ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($start)) : ('#' . $occ_id);
                echo '<option value="' . esc_attr((string) $occ_id) . '"' . selected($occurrence_id, $occ_id, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
        }

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

                __('Datum', 'event-hub'),

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

                echo '<td>' . esc_html($this->format_occurrence_label($event_id, (int) ($row['occurrence_id'] ?? 0))) . '</td>';

                echo '<td>' . esc_html($row['phone'] ?? '') . '</td>';

                echo '<td>' . esc_html($row['company'] ?? '') . '</td>';

                echo '<td>' . esc_html((string) ($row['people_count'] ?? 1)) . '</td>';

                echo '<td>' . esc_html(ucfirst($row['status'] ?? '')) . '</td>';

                echo '<td>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($row['created_at']))) . '</td>';

                echo '<td>';

                echo '<form method="post" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">';

                wp_nonce_field('eh_event_dashboard_action_' . $event_id, 'eh_event_dashboard_nonce');

                echo '<input type="hidden" name="event_id" value="' . esc_attr((string) $event_id) . '">';


                echo '<input type="hidden" name="occurrence_id" value="' . esc_attr((string) $occurrence_id) . '">';


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


                    echo '<input type="hidden" name="occurrence_id" value="' . esc_attr((string) $occurrence_id) . '">';


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


                echo '<input type="hidden" name="occurrence_id" value="' . esc_attr((string) $occurrence_id) . '">';


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

                __('Datum', 'event-hub'),

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

                echo '<td>' . esc_html($this->format_occurrence_label($event_id, (int) ($row['occurrence_id'] ?? 0))) . '</td>';

                echo '<td>' . esc_html($row['phone'] ?? '') . '</td>';

                echo '<td>' . esc_html((string) ($row['people_count'] ?? 1)) . '</td>';

                echo '<td>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($row['created_at']))) . '</td>';

                echo '<td>';

                echo '<form method="post" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">';

                wp_nonce_field('eh_event_dashboard_action_' . $event_id, 'eh_event_dashboard_nonce');

                echo '<input type="hidden" name="event_id" value="' . esc_attr((string) $event_id) . '">';


                echo '<input type="hidden" name="occurrence_id" value="' . esc_attr((string) $occurrence_id) . '">';


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


                echo '<input type="hidden" name="occurrence_id" value="' . esc_attr((string) $occurrence_id) . '">';


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

        $occurrence_id = isset($_POST['occurrence_id']) ? (int) $_POST['occurrence_id'] : 0;



        $redirect_args = ['page' => 'event-hub-event', 'event_id' => $event_id];

        if ($occurrence_id > 0) {

            $redirect_args['occurrence_id'] = $occurrence_id;

        }

        $redirect = add_query_arg($redirect_args, admin_url('admin.php'));




        if (isset($_POST['bulk_template_id']) && !empty($_POST['eh_event_bulk_mail_nonce']) && wp_verify_nonce(sanitize_text_field((string) $_POST['eh_event_bulk_mail_nonce']), 'eh_event_bulk_mail_' . $event_id)) {

            $template_id = (int) $_POST['bulk_template_id'];

            if ($template_id) {

                $registrations = $this->registrations->get_registrations_by_session($event_id, $occurrence_id);

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



    private function export_event_dashboard_csv(int $event_id, string $nonce, int $occurrence_id = 0): void

    {

        if (!wp_verify_nonce($nonce, 'eh_event_csv_' . $event_id)) {

            wp_die(__('Ongeldige exportaanvraag.', 'event-hub'));

        }

        if (!current_user_can('edit_posts')) {

            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));

        }

        $registrations = $this->registrations->get_registrations_by_session($event_id, $occurrence_id);

        $colleagues_label = $this->get_event_colleagues_label($event_id);

        nocache_headers();

        header('Content-Type: text/csv; charset=utf-8');

        header('Content-Disposition: attachment; filename="event-hub-event-' . $event_id . '-' . gmdate('Ymd-His') . '.csv"');

        $out = fopen('php://output', 'w');

        fputcsv($out, [
            __('Voornaam', 'event-hub'),
            __('Familienaam', 'event-hub'),
            __('E-mail', 'event-hub'),
            __('Datum', 'event-hub'),
            __('Telefoon', 'event-hub'),
            __('Bedrijf', 'event-hub'),
            __('Aantal personen', 'event-hub'),
            __('Status', 'event-hub'),
            __('Collega\'s', 'event-hub'),
            __('Aangemaakt', 'event-hub'),
        ]);

        foreach ($registrations as $row) {

            $occurrence_label = $this->format_occurrence_label($event_id, (int) ($row['occurrence_id'] ?? 0));
            fputcsv($out, [

                $row['first_name'],

                $row['last_name'],

                $row['email'],

                $occurrence_label,

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

    private function export_event_dashboard_json(int $event_id, string $nonce, int $occurrence_id = 0): void
    {
        if (!wp_verify_nonce($nonce, 'eh_event_csv_' . $event_id)) {
            wp_die(__('Ongeldige exportaanvraag.', 'event-hub'));
        }
        if (!current_user_can('edit_posts')) {
            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));
        }

        $registrations = $this->registrations->get_registrations_by_session($event_id, $occurrence_id);
        $colleagues_label = $this->get_event_colleagues_label($event_id);
        $extra_map = $this->collect_extra_field_map($registrations);

        $payload = [];
        foreach ($registrations as $row) {
            $extra = [];
            if (!empty($row['extra_data'])) {
                $decoded = json_decode((string) $row['extra_data'], true);
                if (is_array($decoded)) {
                    $extra = $decoded;
                }
            }
            $entry = [
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'date' => $this->format_occurrence_label($event_id, (int) ($row['occurrence_id'] ?? 0)),
                'phone' => $row['phone'],
                'company' => $row['company'],
                'people_count' => $row['people_count'],
                'status' => $row['status'],
                'colleagues' => $colleagues_label,
                'created_at' => $row['created_at'],
                'extra' => [],
            ];
            foreach (array_keys($extra_map) as $slug) {
                $entry['extra'][$slug] = $extra[$slug] ?? null;
            }
            $payload[] = $entry;
        }

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="event-hub-event-' . $event_id . '-' . gmdate('Ymd-His') . '.json"');
        echo wp_json_encode($payload);
        exit;
    }

    private function export_event_dashboard_xlsx(int $event_id, string $nonce, int $occurrence_id = 0): void
    {
        if (!wp_verify_nonce($nonce, 'eh_event_csv_' . $event_id)) {
            wp_die(__('Ongeldige exportaanvraag.', 'event-hub'));
        }
        if (!current_user_can('edit_posts')) {
            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));
        }

        $registrations = $this->registrations->get_registrations_by_session($event_id, $occurrence_id);
        $colleagues_label = $this->get_event_colleagues_label($event_id);
        $extra_map = $this->collect_extra_field_map($registrations);

        $headers = [
            __('Voornaam', 'event-hub'),
            __('Familienaam', 'event-hub'),
            __('E-mail', 'event-hub'),
            __('Datum', 'event-hub'),
            __('Telefoon', 'event-hub'),
            __('Bedrijf', 'event-hub'),
            __('Aantal personen', 'event-hub'),
            __('Status', 'event-hub'),
            __('Collega\'s', 'event-hub'),
            __('Aangemaakt', 'event-hub'),
            ...array_values($extra_map),
        ];

        $data = [];
        foreach ($registrations as $row) {
            $occurrence_label = $this->format_occurrence_label($event_id, (int) ($row['occurrence_id'] ?? 0));
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
            $data[] = [
                $row['first_name'],
                $row['last_name'],
                $row['email'],
                $occurrence_label,
                $row['phone'],
                $row['company'],
                $row['people_count'],
                $row['status'],
                $colleagues_label,
                $row['created_at'],
                ...$extra_vals,
            ];
        }

        $outRows = array_merge([$headers], $data);
        if (empty($outRows)) {
            wp_die(__('Geen data om te exporteren.', 'event-hub'));
        }
        $this->output_xlsx($outRows, 'event-hub-event-' . $event_id . '-' . gmdate('Ymd-His') . '.xlsx');
    }

    private function format_occurrence_label(int $session_id, int $occurrence_id): string
    {
        if ($occurrence_id <= 0) {
            $date_start = get_post_meta($session_id, '_eh_date_start', true);
            if (!$date_start) {
                return '';
            }
            return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($date_start));
        }
        $occurrence = $this->registrations->get_occurrence($session_id, $occurrence_id);
        if (!$occurrence || empty($occurrence['date_start'])) {
            return '';
        }
        $start = $occurrence['date_start'];
        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($start));
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

    private function ajax_disabled(): bool
    {
        return defined('EVENT_HUB_DISABLE_AJAX') && EVENT_HUB_DISABLE_AJAX;
    }

}


