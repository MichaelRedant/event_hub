<?php
namespace EventHub;

defined('ABSPATH') || exit;

class Admin_Menus
{
    private Registrations $registrations;
    private Emails $emails;
    private Settings $settings;

    public function __construct(Registrations $registrations, Emails $emails, Settings $settings)
    {
        $this->registrations = $registrations;
        $this->emails        = $emails;
        $this->settings      = $settings;
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
            'manage_options',
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
    }

    public function render_info_page(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));
        }

        echo '<div class="wrap eh-admin">';
        echo '<h1>' . esc_html__('Event Hub - Info', 'event-hub') . '</h1>';
        echo '<div class="eh-grid">';
        echo '<div class="eh-card">';
        echo '<h2>' . esc_html__('Plugingegevens', 'event-hub') . '</h2>';
        echo '<table class="eh-meta-table">';
        echo '<tr><th>' . esc_html__('Naam', 'event-hub') . '</th><td>Event Hub</td></tr>';
        echo '<tr><th>' . esc_html__('Versie', 'event-hub') . '</th><td>' . esc_html(EVENT_HUB_VERSION) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Maker', 'event-hub') . '</th><td>Micha&euml;l Redant</td></tr>';
        echo '</table>';
        echo '<div style="margin-top:16px;display:flex;gap:12px;flex-wrap:wrap">';
        echo '<a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=event-hub-settings')) . '">' . esc_html__('E-mailinstellingen', 'event-hub') . '</a>';
        echo '<a class="button" href="' . esc_url(admin_url('edit.php?post_type=' . Settings::get_cpt_slug())) . '">' . esc_html__('Beheer evenementen', 'event-hub') . '</a>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=event-hub-calendar')) . '">' . esc_html__('Kalender', 'event-hub') . '</a>';
        echo '</div>';
        echo '</div>';
        echo '<div class="eh-card">';
        echo '<h2>' . esc_html__('Snelkoppelingen', 'event-hub') . '</h2>';
        echo '<ul style="margin:0;padding-left:18px;line-height:1.8">';
        echo '<li><a href="' . esc_url(admin_url('admin.php?page=event-hub-registrations')) . '">' . esc_html__('Inschrijvingen beheren', 'event-hub') . '</a></li>';
        echo '<li><a href="' . esc_url(admin_url('edit.php?post_type=' . CPT_Email::CPT)) . '">' . esc_html__('E-mailsjablonen beheren', 'event-hub') . '</a></li>';
        echo '<li><a href="' . esc_url(admin_url('admin.php?page=event-hub-general')) . '">' . esc_html__('Algemene instellingen', 'event-hub') . '</a></li>';
        echo '</ul>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    public function render_calendar_page(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Eventkalender', 'event-hub') . '</h1>';
        echo '<p>' . esc_html__('Bekijk al je events in één oogopslag. Klik op een event om het in een nieuw tabblad te openen.', 'event-hub') . '</p>';
        echo '<div id="eh-admin-calendar" style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:12px;"></div>';
        echo '<p class="description">' . esc_html__('Klik op een lege dag om een nieuw event te maken, of klik op een bestaande kaart om het event te openen.', 'event-hub') . '</p>';
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
        ];

        if (in_array($page, $plugin_pages, true)) {
            wp_enqueue_style(
                'event-hub-admin',
                EVENT_HUB_URL . 'assets/css/admin.css',
                [],
                EVENT_HUB_VERSION
            );
        }

        if ($page !== 'event-hub-calendar') {
            return;
        }

        wp_enqueue_style(
            'event-hub-fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css',
            [],
            '6.1.11'
        );
        wp_enqueue_script(
            'event-hub-fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js',
            [],
            '6.1.11',
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

    public function render_registrations_page(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));
        }

        if (isset($_GET['download']) && $_GET['download'] === 'csv') {
            $session_id = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;
            $status = isset($_GET['status']) ? sanitize_text_field((string) $_GET['status']) : '';
            $this->export_registrations_csv($session_id, $status);
            return;
        }

        $action = isset($_GET['action']) ? sanitize_text_field((string) $_GET['action']) : 'list';
        if ($action === 'edit') {
            $this->render_edit_registration();
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

        $sessions = get_posts([
            'post_type'   => Settings::get_cpt_slug(),
            'numberposts' => -1,
            'orderby'     => 'title',
            'order'       => 'ASC',
        ]);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Inschrijvingen', 'event-hub') . '</h1>';
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
        submit_button(__('Filteren', 'event-hub'), 'primary', '', false);
        echo '<button type="submit" name="download" value="csv" class="button button-secondary">' . esc_html__('Exporteer CSV', 'event-hub') . '</button>';
        echo '</form>';

        $registrations = $this->fetch_registrations($session_id, $status);

        echo '<div class="eh-table-wrap">';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        $columns = [
            __('ID', 'event-hub'),
            __('Evenement', 'event-hub'),
            __('Naam', 'event-hub'),
            __('E-mail', 'event-hub'),
            __('Personen', 'event-hub'),
            __('Status', 'event-hub'),
            __('Aangemaakt', 'event-hub'),
            __('Acties', 'event-hub'),
        ];
        foreach ($columns as $column) {
            echo '<th>' . esc_html($column) . '</th>';
        }
        echo '</tr></thead><tbody>';

        if (!$registrations) {
            echo '<tr><td colspan="8">' . esc_html__('Geen inschrijvingen gevonden.', 'event-hub') . '</td></tr>';
        } else {
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

                echo '<tr>';
                echo '<td>' . esc_html((string) $row['id']) . '</td>';
                echo '<td>';
                if ($session) {
                    echo '<a href="' . esc_url(get_edit_post_link($session_id)) . '">' . esc_html($session->post_title) . '</a>';
                } else {
                    echo esc_html__('Onbekend event', 'event-hub');
                }
                echo '</td>';
                echo '<td>' . esc_html(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) . '</td>';
                echo '<td><a href="mailto:' . esc_attr($row['email']) . '">' . esc_html($row['email']) . '</a></td>';
                echo '<td>' . esc_html((string) ($row['people_count'] ?? 1)) . '</td>';
                $status_key = $row['status'] ?? '';
                $badge_label = $statuses[$status_key] ?? $status_key;
                echo '<td><span class="eh-badge-pill status-' . esc_attr($status_key) . '">' . esc_html($badge_label) . '</span></td>';
                echo '<td>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($row['created_at']))) . '</td>';
                echo '<td>';
                echo '<a class="button button-small" href="' . esc_url($edit_url) . '">' . esc_html__('Bewerken', 'event-hub') . '</a> ';
                echo '<a class="button button-small" href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Deze inschrijving verwijderen?', 'event-hub')) . '\');">' . esc_html__('Verwijderen', 'event-hub') . '</a>';
                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '</div>';
        echo '</div>';
    }

    private function fetch_registrations(int $session_id, string $status): array
    {
        if ($session_id > 0) {
            $registrations = $this->registrations->get_registrations_by_session($session_id);
        } else {
            global $wpdb;
            $table         = $wpdb->prefix . Registrations::TABLE;
            $registrations = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 100", ARRAY_A) ?: [];
        }

        if ($status !== '') {
            $registrations = array_values(array_filter(
                $registrations,
                static fn ($row) => isset($row['status']) && $row['status'] === $status
            ));
        }

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

        echo '<div class="wrap">';
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

        if ($this->registrations->delete_registration($id)) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Inschrijving verwijderd.', 'event-hub') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Verwijderen mislukt.', 'event-hub') . '</p></div>';
        }
    }

    private function get_status_labels(): array
    {
        return [
            'registered' => __('Geregistreerd', 'event-hub'),
            'confirmed'  => __('Bevestigd', 'event-hub'),
            'cancelled'  => __('Geannuleerd', 'event-hub'),
            'attended'   => __('Aanwezig', 'event-hub'),
            'no_show'    => __('Niet opgedaagd', 'event-hub'),
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
            $events[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'start' => date('c', strtotime($date_start)),
                'end' => $date_end ? date('c', strtotime($date_end)) : null,
                'url' => get_edit_post_link($post->ID, 'raw'),
                'backgroundColor' => $color,
                'borderColor' => $color,
                'classNames' => ['eh-status-' . sanitize_html_class($status)],
                'extendedProps' => [
                    'status' => $status,
                    'location' => get_post_meta($post->ID, '_eh_location', true),
                ],
            ];
        }

        wp_send_json_success($events);
    }

    private function export_registrations_csv(int $session_id, string $status): void
    {
        if (!isset($_GET['download_nonce']) || !wp_verify_nonce(sanitize_text_field((string) $_GET['download_nonce']), 'eh_reg_export')) {
            wp_die(__('Ongeldige exportaanvraag.', 'event-hub'));
        }
        if (!current_user_can('edit_posts')) {
            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));
        }

        $rows = $this->fetch_registrations($session_id, $status);
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
            'Aangemaakt',
        ]);

        foreach ($rows as $row) {
            $title = get_the_title((int) $row['session_id']);
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
                $row['created_at'],
            ]);
        }
        fclose($out);
        exit;
    }
}
