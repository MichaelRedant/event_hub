<?php
namespace EventHub;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use wpdb;

defined('ABSPATH') || exit;

class Registrations
{
    public const TABLE = 'eh_session_registrations';

    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . self::TABLE;
    }

    /**
     * Create a registration record.
     *
     * @param array $data
     * @param bool  $is_admin Allow admins to bypass capacity/time/captcha restrictions.
     * @return int|\WP_Error
     */
    public function create_registration(array $data, bool $is_admin = false)
    {
        global $wpdb;

        $defaults = [
            'session_id' => 0,
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => null,
            'company' => null,
            'vat' => null,
            'role' => null,
            'people_count' => 1,
            'status' => 'registered',
            'consent_marketing' => 0,
            'waitlist_opt_in' => 0,
        ];
        $data = wp_parse_args($data, $defaults);

        $waitlist_opt_in = !empty($data['waitlist_opt_in']);

        $data = [
            'session_id' => (int) $data['session_id'],
            'first_name' => sanitize_text_field((string) $data['first_name']),
            'last_name'  => sanitize_text_field((string) $data['last_name']),
            'email'      => sanitize_email((string) $data['email']),
            'phone'      => $data['phone'] !== null ? sanitize_text_field((string) $data['phone']) : null,
            'company'    => $data['company'] !== null ? sanitize_text_field((string) $data['company']) : null,
            'vat'        => $data['vat'] !== null ? sanitize_text_field((string) $data['vat']) : null,
            'role'       => $data['role'] !== null ? sanitize_text_field((string) $data['role']) : null,
            'people_count' => max(1, (int) $data['people_count']),
            'status' => sanitize_text_field((string) $data['status']),
            'consent_marketing' => (int) !empty($data['consent_marketing']),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];
        if (!isset(self::get_status_labels()[$data['status']])) {
            $data['status'] = $is_admin ? 'confirmed' : 'registered';
        }

        if (empty($data['session_id']) || empty($data['first_name']) || empty($data['last_name']) || empty($data['email'])) {
            return new \WP_Error('invalid_data', __('Verplichte velden ontbreken.', 'event-hub'));
        }
        if (!is_email($data['email'])) {
            return new \WP_Error('invalid_email', __('E-mailadres is ongeldig.', 'event-hub'));
        }

        $session_status = get_post_meta((int) $data['session_id'], '_eh_status', true) ?: 'open';
        if (!$is_admin && !in_array($session_status, ['open', 'full'], true)) {
            return new \WP_Error('event_closed', __('Dit event accepteert momenteel geen inschrijvingen.', 'event-hub'));
        }

        // CAPTCHA check
        if (!$is_admin && \EventHub\Security::captcha_enabled()) {
            $token = isset($_POST['eh_captcha_token']) ? sanitize_text_field((string) $_POST['eh_captcha_token']) : '';
            if (!\EventHub\Security::verify_token($token, 'event_hub_register')) {
                return new \WP_Error('captcha_failed', __('CAPTCHA validatie mislukt. Probeer opnieuw.', 'event-hub'));
            }
        }

        // Booking window + capacity checks
        if (!$is_admin) {
            $now = current_time('timestamp');
            $open = get_post_meta((int) $data['session_id'], '_eh_booking_open', true);
            $close = get_post_meta((int) $data['session_id'], '_eh_booking_close', true);
            $event_start = get_post_meta((int) $data['session_id'], '_eh_date_start', true);
            if ($open && $now < strtotime($open)) {
                return new \WP_Error('booking_not_open', __('Inschrijvingen zijn nog niet geopend voor dit event.', 'event-hub'));
            }
            if ($close && $now > strtotime($close)) {
                return new \WP_Error('booking_closed', __('Inschrijvingen zijn gesloten voor dit event.', 'event-hub'));
            }
            if (!$close && $event_start) {
                $event_start_ts = strtotime($event_start);
                if ($event_start_ts) {
                    $event_day_cutoff = strtotime(date('Y-m-d 00:00:00', $event_start_ts));
                    if ($event_day_cutoff && $now >= $event_day_cutoff) {
                        return new \WP_Error('booking_closed', __('Inschrijvingen zijn gesloten voor dit event.', 'event-hub'));
                    }
                }
            }
            if (!$this->has_capacity((int) $data['session_id'], (int) $data['people_count'])) {
                if ($waitlist_opt_in) {
                    $data['status'] = 'waitlist';
                } else {
                    return new \WP_Error('capacity_full', __('Dit event zit vol.', 'event-hub'));
                }
            }
        }

        // Duplicate check by email + session
        if (!$is_admin && $this->exists_by_email((int) $data['session_id'], (string) $data['email'])) {
            return new \WP_Error('duplicate', __('Je bent al ingeschreven voor dit event.', 'event-hub'));
        }

        $inserted = $wpdb->insert(
            $this->table,
            $data,
            [
                '%d','%s','%s','%s','%s','%s','%s','%s','%d','%s','%d','%s','%s'
            ]
        );

        if ($inserted === false) {
            return new \WP_Error('db_error', __('Databasefout bij het opslaan van de inschrijving.', 'event-hub'));
        }

        $id = (int) $wpdb->insert_id;
        $this->sync_session_status((int) $data['session_id']);
        if (($data['status'] ?? '') !== 'waitlist') {
            do_action('event_hub_registration_created', $id);
        }
        return $id;
    }

    /**
     * @return array<string,string>
     */
    public static function get_status_labels(): array
    {
        return [
            'registered' => __('Geregistreerd', 'event-hub'),
            'confirmed'  => __('Bevestigd', 'event-hub'),
            'cancelled'  => __('Geannuleerd', 'event-hub'),
            'attended'   => __('Aanwezig', 'event-hub'),
            'no_show'    => __('No-show', 'event-hub'),
            'waitlist'   => __('Wachtlijst', 'event-hub'),
        ];
    }

    /**
     * @param int $session_id
     * @return array<int, array>
     */
    public function get_registrations_by_session(int $session_id): array
    {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT * FROM {$this->table} WHERE session_id = %d ORDER BY created_at DESC", $session_id);
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    /**
     * @param int $id
     * @return array|null
     */
    public function get_registration(int $id): ?array
    {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id);
        $row = $wpdb->get_row($sql, ARRAY_A);
        return $row ?: null;
    }

    /**
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update_registration(int $id, array $data): bool
    {
        $existing = $this->get_registration($id);
        if (!$existing) {
            return false;
        }

        global $wpdb;
        $fields = [];
        $formats = [];
        $map = [
            'first_name' => '%s',
            'last_name' => '%s',
            'email' => '%s',
            'phone' => '%s',
            'company' => '%s',
            'vat' => '%s',
            'role' => '%s',
            'people_count' => '%d',
            'status' => '%s',
            'consent_marketing' => '%d',
        ];
        foreach ($map as $key => $format) {
            if (array_key_exists($key, $data)) {
                $val = $data[$key];
                if ($key === 'email') {
                    $val = sanitize_email((string) $val);
                } elseif (in_array($key, ['first_name','last_name','phone','company','vat','role','status'], true)) {
                    $val = sanitize_text_field((string) $val);
                } elseif ($key === 'people_count') {
                    $val = max(1, (int) $val);
                } elseif ($key === 'consent_marketing') {
                    $val = (int) !empty($val);
                }
                $fields[$key] = $val;
                $formats[] = $format;
            }
        }
        if (!$fields) {
            return false;
        }
        $fields['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        $res = $wpdb->update($this->table, $fields, ['id' => $id], $formats, ['%d']);
        if ($res !== false) {
            $this->sync_session_status((int) $existing['session_id']);
        }
        return $res !== false;
    }

    public function delete_registration(int $id): bool
    {
        $existing = $this->get_registration($id);
        if (!$existing) {
            return false;
        }

        global $wpdb;
        $res = $wpdb->delete($this->table, ['id' => $id], ['%d']);
        if ($res !== false) {
            $this->sync_session_status((int) $existing['session_id']);
        }
        return $res !== false;
    }

    /**
     * Count booked seats for capacity checks.
     */
    public function count_booked(int $session_id): int
    {
        global $wpdb;
        $statuses = ['registered','confirmed'];
        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $sql = $wpdb->prepare(
            "SELECT COALESCE(SUM(people_count),0) FROM {$this->table} WHERE session_id = %d AND status IN ($placeholders)",
            array_merge([$session_id], $statuses)
        );
        return (int) $wpdb->get_var($sql);
    }

    /**
     * Check if new registration can be accepted given capacity.
     */
    public function can_register(int $session_id, int $people = 1): bool
    {
        // Booking window checks & status
        $status = get_post_meta($session_id, '_eh_status', true) ?: 'open';
        if ($status !== 'open') {
            return false;
        }
        $now = current_time('timestamp');
        $open = get_post_meta($session_id, '_eh_booking_open', true);
        $close = get_post_meta($session_id, '_eh_booking_close', true);
        $event_start = get_post_meta($session_id, '_eh_date_start', true);
        if ($open && $now < strtotime($open)) {
            return false;
        }
        if ($close && $now > strtotime($close)) {
            return false;
        }
        if (!$close && $event_start) {
            $event_start_ts = strtotime($event_start);
            if ($event_start_ts) {
                $event_day_cutoff = strtotime(date('Y-m-d 00:00:00', $event_start_ts));
                if ($event_day_cutoff && $now >= $event_day_cutoff) {
                    return false;
                }
            }
        }
        return $this->has_capacity($session_id, $people);
    }

    /**
     * Check if a registration exists for email + session.
     */
    public function exists_by_email(int $session_id, string $email): bool
    {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT id FROM {$this->table} WHERE session_id = %d AND email = %s LIMIT 1", $session_id, $email);
        $id = $wpdb->get_var($sql);
        return !empty($id);
    }

    private function has_capacity(int $session_id, int $people = 1): bool
    {
        $capacity = (int) get_post_meta($session_id, '_eh_capacity', true);
        if ($capacity <= 0) {
            return true;
        }
        $booked = $this->count_booked($session_id);
        return ($booked + max(1, $people)) <= $capacity;
    }

    /**
     * Bepaal capaciteit/boekingen voor een sessie.
     *
     * @return array{capacity:int, booked:int, available:int, is_full:bool}
     */
    public function get_capacity_state(int $session_id): array
    {
        $capacity = (int) get_post_meta($session_id, '_eh_capacity', true);
        $booked = $this->count_booked($session_id);
        if ($capacity <= 0) {
            return [
                'capacity' => 0,
                'booked' => $booked,
                'available' => 0,
                'is_full' => false,
            ];
        }
        $available = max(0, $capacity - $booked);
        return [
            'capacity' => $capacity,
            'booked' => $booked,
            'available' => $available,
            'is_full' => $available <= 0,
        ];
    }

    private function sync_session_status(int $session_id): void
    {
        $capacity = (int) get_post_meta($session_id, '_eh_capacity', true);
        $status = get_post_meta($session_id, '_eh_status', true) ?: 'open';
        if (in_array($status, ['cancelled', 'closed'], true)) {
            return;
        }

        if ($capacity <= 0) {
            if ($status === 'full') {
                update_post_meta($session_id, '_eh_status', 'open');
            }
            return;
        }

        $state = $this->get_capacity_state($session_id);
        if ($state['is_full'] && $status !== 'full') {
            update_post_meta($session_id, '_eh_status', 'full');
            $status = 'full';
        } elseif (!$state['is_full'] && $status === 'full') {
            update_post_meta($session_id, '_eh_status', 'open');
            $status = 'open';
        }

        if ($status === 'open') {
            $this->promote_waitlist($session_id);
        }
    }

    private function promote_waitlist(int $session_id): void
    {
        $capacity = (int) get_post_meta($session_id, '_eh_capacity', true);
        if ($capacity <= 0) {
            return;
        }

        global $wpdb;
        while ($this->has_capacity($session_id, 1)) {
            $next = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$this->table} WHERE session_id = %d AND status = %s ORDER BY created_at ASC LIMIT 1",
                    $session_id,
                    'waitlist'
                ),
                ARRAY_A
            );
            if (!$next) {
                break;
            }
            $people = max(1, (int) ($next['people_count'] ?? 1));
            if (!$this->has_capacity($session_id, $people)) {
                break;
            }

            $updated = $wpdb->update(
                $this->table,
                [
                    'status' => 'registered',
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => (int) $next['id']],
                ['%s', '%s'],
                ['%d']
            );

            if ($updated === false) {
                break;
            }

            $reg_id = (int) $next['id'];
            do_action('event_hub_waitlist_promoted', $reg_id);
            do_action('event_hub_registration_created', $reg_id);
        }
    }

    public function register_rest_routes(): void
    {
        register_rest_route('event-hub/v1', '/register', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle_rest_register'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle_rest_register(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        if (!$params) {
            $params = $request->get_body_params();
        }

        $data = [
            'session_id' => isset($params['session_id']) ? (int) $params['session_id'] : 0,
            'first_name' => sanitize_text_field($params['first_name'] ?? ''),
            'last_name' => sanitize_text_field($params['last_name'] ?? ''),
            'email' => sanitize_email($params['email'] ?? ''),
            'phone' => isset($params['phone']) ? sanitize_text_field((string) $params['phone']) : null,
            'company' => isset($params['company']) ? sanitize_text_field((string) $params['company']) : null,
            'vat' => isset($params['vat']) ? sanitize_text_field((string) $params['vat']) : null,
            'role' => isset($params['role']) ? sanitize_text_field((string) $params['role']) : null,
            'people_count' => isset($params['people_count']) ? (int) $params['people_count'] : 1,
            'consent_marketing' => !empty($params['consent_marketing']) ? 1 : 0,
        ];

        if (isset($params['captcha_token'])) {
            $_POST['eh_captcha_token'] = sanitize_text_field((string) $params['captcha_token']);
        } else {
            unset($_POST['eh_captcha_token']);
        }

        $result = $this->create_registration($data);
        if (is_wp_error($result)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
            ], 400);
        }

        $registration = $this->get_registration($result);
        $is_waitlist = $registration && ($registration['status'] ?? '') === 'waitlist';

        $state = $this->get_capacity_state($data['session_id']);
        $status = get_post_meta($data['session_id'], '_eh_status', true) ?: 'open';
        $status_badge = $this->get_status_badge_data($status, $state['is_full']);
        $available_label = '';
        if ($state['capacity'] > 0) {
            $available_label = sprintf(_n('%d plaats beschikbaar', '%d plaatsen beschikbaar', $state['available'], 'event-hub'), $state['available']);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => $is_waitlist ? __('Bedankt! Je staat nu op de wachtlijst.', 'event-hub') : __('Bedankt! We hebben je inschrijving ontvangen.', 'event-hub'),
            'registration_id' => $result,
            'waitlist' => $is_waitlist,
            'session' => [
                'id' => (int) $data['session_id'],
                'status' => $status,
                'status_label' => $status_badge['label'],
                'status_class' => $status_badge['class'],
                'state' => $state,
                'available_label' => $available_label,
                'button_disabled' => in_array($status, ['cancelled', 'closed'], true),
            ],
        ]);
    }

    private function get_status_badge_data(string $status, bool $is_full): array
    {
        if ($status === 'cancelled') {
            return ['label' => __('Geannuleerd', 'event-hub'), 'class' => 'eh-badge-cancelled'];
        }
        if ($status === 'closed') {
            return ['label' => __('Gesloten', 'event-hub'), 'class' => 'eh-badge-closed'];
        }
        if ($status === 'full' || $is_full) {
            return ['label' => __('Wachtlijst', 'event-hub'), 'class' => 'eh-badge-waitlist'];
        }
        return ['label' => __('Beschikbaar', 'event-hub'), 'class' => 'eh-badge-available'];
    }
}
