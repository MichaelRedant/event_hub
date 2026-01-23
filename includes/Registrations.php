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

        // Simple honeypot: reject if hidden field is filled.
        if (isset($_POST['_eh_hp']) && trim((string) $_POST['_eh_hp']) !== '') {
            return new \WP_Error('spam_detected', __('Ongeldige inzending.', 'event-hub'));
        }

        $defaults = [
            'session_id' => 0,
            'occurrence_id' => 0,
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
            'extra' => [],
        ];
        $data = wp_parse_args($data, $defaults);

        $waitlist_opt_in = !empty($data['waitlist_opt_in']);

        $data = [
            'session_id' => (int) $data['session_id'],
            'occurrence_id' => (int) $data['occurrence_id'],
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
            'extra_data' => null,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'cancel_token' => $this->generate_unique_cancel_token(),
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

        $occurrences = $this->get_occurrences((int) $data['session_id']);
        $occurrence = null;
        if ($occurrences) {
            if ((int) $data['occurrence_id'] <= 0) {
                return new \WP_Error('occurrence_required', __('Kies een datum voor dit event.', 'event-hub'));
            }
            $occurrence = $this->get_occurrence((int) $data['session_id'], (int) $data['occurrence_id']);
            if (!$occurrence) {
                return new \WP_Error('occurrence_invalid', __('De gekozen datum is ongeldig.', 'event-hub'));
            }
        } else {
            $data['occurrence_id'] = 0;
        }

        $session_status = get_post_meta((int) $data['session_id'], '_eh_status', true) ?: 'open';
        if (!$is_admin && !in_array($session_status, ['open', 'full'], true)) {
            return new \WP_Error('event_closed', __('Dit event accepteert momenteel geen inschrijvingen.', 'event-hub'));
        }
        $enable_module_meta = get_post_meta((int) $data['session_id'], '_eh_enable_module', true);
        $module_enabled = ($enable_module_meta === '') ? true : (bool) $enable_module_meta;
        if (!$is_admin && !$module_enabled) {
            return new \WP_Error('module_disabled', __('Dit event accepteert geen inschrijvingen.', 'event-hub'));
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
            $open = $occurrence ? ($occurrence['booking_open'] ?? '') : get_post_meta((int) $data['session_id'], '_eh_booking_open', true);
            $close = $occurrence ? ($occurrence['booking_close'] ?? '') : get_post_meta((int) $data['session_id'], '_eh_booking_close', true);
            $event_start = $occurrence ? ($occurrence['date_start'] ?? '') : get_post_meta((int) $data['session_id'], '_eh_date_start', true);
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
            if (!$this->has_capacity((int) $data['session_id'], (int) $data['people_count'], (int) $data['occurrence_id'])) {
                if ($waitlist_opt_in) {
                    $data['status'] = 'waitlist';
                } else {
                    return new \WP_Error('capacity_full', __('Dit event zit vol.', 'event-hub'));
                }
            }
        }

        // Duplicate check by email + session
        if (!$is_admin && $this->exists_by_email((int) $data['session_id'], (string) $data['email'], (int) $data['occurrence_id'])) {
            return new \WP_Error('duplicate', __('Je bent al ingeschreven voor dit event.', 'event-hub'));
        }

        // Extra fields: validate against event config
        $extra_fields = $this->get_extra_fields((int) $data['session_id']);
        $extra_payload = $this->sanitize_extra_payload($extra_fields, $data['extra']);
        if ($extra_payload instanceof \WP_Error) {
            return $extra_payload;
        }
        if (!empty($extra_payload)) {
            $data['extra_data'] = wp_json_encode($extra_payload);
        }
        unset($data['extra']);

        $inserted = $wpdb->insert(
            $this->table,
            $data,
            [
                '%d','%d','%s','%s','%s','%s','%s','%s','%s','%d','%s','%d','%s','%s','%s','%s'
            ]
        );

        if ($inserted === false) {
            return new \WP_Error('db_error', __('Databasefout bij het opslaan van de inschrijving.', 'event-hub'));
        }

        $id = (int) $wpdb->insert_id;
        $this->sync_session_status((int) $data['session_id'], (int) $data['occurrence_id']);
        if (($data['status'] ?? '') !== 'waitlist') {
            do_action('event_hub_registration_created', $id);
        } else {
            do_action('event_hub_waitlist_created', $id);
        }
        return $id;
    }

    /**
     * Genereer een unieke annulatietoken per inschrijving.
     */
    private function generate_unique_cancel_token(): string
    {
        global $wpdb;
        do {
            $token = wp_generate_password(32, false, false);
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table} WHERE cancel_token = %s LIMIT 1", $token));
        } while (!empty($exists));
        return $token;
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

    public function get_table(): string
    {
        return $this->table;
    }

    private function get_cancel_cutoff_hours(int $session_id): int
    {
        $meta_hours = get_post_meta($session_id, '_eh_cancel_cutoff_hours', true);
        if ($meta_hours !== '' && $meta_hours !== null) {
            return max(0, (int) $meta_hours);
        }
        $opts = get_option(Settings::OPTION, []);
        $global = $opts['cancel_cutoff_hours'] ?? 24;
        return max(0, (int) $global);
    }

    /**
     * @param int $session_id
     * @return array<int, array>
     */
    public function get_registrations_by_session(int $session_id, int $occurrence_id = 0): array
    {
        global $wpdb;
        if ($occurrence_id > 0) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE session_id = %d AND occurrence_id = %d ORDER BY created_at DESC",
                $session_id,
                $occurrence_id
            );
        } else {
            $sql = $wpdb->prepare("SELECT * FROM {$this->table} WHERE session_id = %d ORDER BY created_at DESC", $session_id);
        }
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
     * @param string $token
     * @return array|null
     */
    public function get_registration_by_token(string $token): ?array
    {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT * FROM {$this->table} WHERE cancel_token = %s", $token);
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
        $previous_status = $existing['status'] ?? '';

        global $wpdb;
        $fields = [];
        $formats = [];
        $map = [
            'occurrence_id' => '%d',
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
                if ($key === 'occurrence_id') {
                    $val = max(0, (int) $val);
                } elseif ($key === 'email') {
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
            $occurrence_id = isset($fields['occurrence_id'])
                ? (int) $fields['occurrence_id']
                : (int) ($existing['occurrence_id'] ?? 0);
            $this->sync_session_status((int) $existing['session_id'], $occurrence_id);
            $next_status = isset($fields['status']) ? (string) $fields['status'] : $previous_status;
            if ($previous_status !== 'cancelled' && $next_status === 'cancelled') {
                do_action('event_hub_registration_cancelled', $id);
            }
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
            $this->sync_session_status((int) $existing['session_id'], (int) ($existing['occurrence_id'] ?? 0));
            do_action('event_hub_registration_deleted', $id, $existing);
        }
        return $res !== false;
    }

    /**
     * Annuleer een inschrijving via token (publieke link).
     *
     * @return array|\WP_Error
     */
    public function cancel_by_token(string $token)
    {
        $token = sanitize_text_field($token);
        if ($token === '') {
            return new \WP_Error('invalid_token', __('Annulatielink is ongeldig.', 'event-hub'));
        }
        $reg = $this->get_registration_by_token($token);
        if (!$reg) {
            return new \WP_Error('not_found', __('Inschrijving niet gevonden of al geannuleerd.', 'event-hub'));
        }
        if (($reg['status'] ?? '') === 'cancelled') {
            return new \WP_Error('already_cancelled', __('Je inschrijving was al geannuleerd.', 'event-hub'));
        }
        $allowed = ['registered', 'confirmed', 'waitlist'];
        if (!in_array($reg['status'], $allowed, true)) {
            return new \WP_Error('invalid_status', __('Deze inschrijving kan niet meer geannuleerd worden.', 'event-hub'));
        }
        // Check cancel cutoff (uren voor start)
        $cutoff_hours = $this->get_cancel_cutoff_hours((int) $reg['session_id']);
        $start = '';
        $occurrence_id = (int) ($reg['occurrence_id'] ?? 0);
        if ($occurrence_id > 0) {
            $occurrence = $this->get_occurrence((int) $reg['session_id'], $occurrence_id);
            if ($occurrence) {
                $start = $occurrence['date_start'] ?? '';
            }
        }
        if ($start === '') {
            $start = get_post_meta((int) $reg['session_id'], '_eh_date_start', true);
        }
        if ($cutoff_hours > 0 && $start) {
            $start_ts = strtotime($start);
            if ($start_ts && current_time('timestamp') > ($start_ts - ($cutoff_hours * HOUR_IN_SECONDS))) {
                return new \WP_Error('cancel_window_closed', sprintf(
                    __('Annuleren via de link kan tot %d uur voor de start.', 'event-hub'),
                    $cutoff_hours
                ));
            }
        }

        global $wpdb;
        $updated = $wpdb->update(
            $this->table,
            [
                'status' => 'cancelled',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => (int) $reg['id']],
            ['%s', '%s'],
            ['%d']
        );
        if ($updated === false) {
            return new \WP_Error('db_error', __('Annulatie is mislukt. Probeer later opnieuw.', 'event-hub'));
        }

        $this->sync_session_status((int) $reg['session_id'], $occurrence_id);
        do_action('event_hub_registration_cancelled', (int) $reg['id']);

        return $this->get_registration((int) $reg['id']);
    }

    /**
     * Count booked seats for capacity checks.
     */
    public function count_booked(int $session_id, int $occurrence_id = 0): int
    {
        global $wpdb;
        $statuses = ['registered','confirmed'];
        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        if ($occurrence_id > 0) {
            $sql = $wpdb->prepare(
                "SELECT COALESCE(SUM(people_count),0) FROM {$this->table} WHERE session_id = %d AND occurrence_id = %d AND status IN ($placeholders)",
                array_merge([$session_id, $occurrence_id], $statuses)
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT COALESCE(SUM(people_count),0) FROM {$this->table} WHERE session_id = %d AND status IN ($placeholders)",
                array_merge([$session_id], $statuses)
            );
        }
        return (int) $wpdb->get_var($sql);
    }

    /**
     * Count people currently on the waitlist.
     */
    public function count_waitlist(int $session_id, int $occurrence_id = 0): int
    {
        global $wpdb;
        if ($occurrence_id > 0) {
            $sql = $wpdb->prepare(
                "SELECT COALESCE(SUM(people_count),0) FROM {$this->table} WHERE session_id = %d AND occurrence_id = %d AND status = %s",
                $session_id,
                $occurrence_id,
                'waitlist'
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT COALESCE(SUM(people_count),0) FROM {$this->table} WHERE session_id = %d AND status = %s",
                $session_id,
                'waitlist'
            );
        }
        return (int) $wpdb->get_var($sql);
    }

    /**
     * Check if new registration can be accepted given capacity.
     */
    public function can_register(int $session_id, int $people = 1, int $occurrence_id = 0): bool
    {
        // Booking window checks & status
        $status = get_post_meta($session_id, '_eh_status', true) ?: 'open';
        if ($status !== 'open') {
            return false;
        }
        $now = current_time('timestamp');
        $occurrence = $occurrence_id > 0 ? $this->get_occurrence($session_id, $occurrence_id) : null;
        $open = $occurrence ? ($occurrence['booking_open'] ?? '') : get_post_meta($session_id, '_eh_booking_open', true);
        $close = $occurrence ? ($occurrence['booking_close'] ?? '') : get_post_meta($session_id, '_eh_booking_close', true);
        $event_start = $occurrence ? ($occurrence['date_start'] ?? '') : get_post_meta($session_id, '_eh_date_start', true);
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
        return $this->has_capacity($session_id, $people, $occurrence_id);
    }

    /**
     * Check if a registration exists for email + session.
     */
    public function exists_by_email(int $session_id, string $email, int $occurrence_id = 0): bool
    {
        global $wpdb;
        if ($occurrence_id > 0) {
            $sql = $wpdb->prepare(
                "SELECT id FROM {$this->table} WHERE session_id = %d AND occurrence_id = %d AND email = %s LIMIT 1",
                $session_id,
                $occurrence_id,
                $email
            );
        } else {
            $sql = $wpdb->prepare("SELECT id FROM {$this->table} WHERE session_id = %d AND email = %s LIMIT 1", $session_id, $email);
        }
        $id = $wpdb->get_var($sql);
        return !empty($id);
    }

    private function has_capacity(int $session_id, int $people = 1, int $occurrence_id = 0): bool
    {
        $capacity = $this->get_capacity_limit($session_id, $occurrence_id);
        if ($capacity <= 0) {
            return true;
        }
        $booked = $this->count_booked($session_id, $occurrence_id);
        return ($booked + max(1, $people)) <= $capacity;
    }

    /**
     * Bepaal capaciteit/boekingen voor een sessie.
     *
     * @return array{capacity:int, booked:int, available:int, is_full:bool, waitlist:int}
     */
    public function get_capacity_state(int $session_id, int $occurrence_id = 0): array
    {
        $occurrence = null;
        if ($occurrence_id > 0) {
            $occurrence = $this->get_occurrence($session_id, $occurrence_id);
        } else {
            $occurrence = $this->get_default_occurrence($session_id);
            if ($occurrence) {
                $occurrence_id = (int) $occurrence['id'];
            }
        }

        $capacity = $this->get_capacity_limit($session_id, $occurrence_id);
        $booked = $this->count_booked($session_id, $occurrence_id);
        $waitlist = $this->count_waitlist($session_id, $occurrence_id);
        if ($capacity <= 0) {
            return [
                'capacity' => 0,
                'booked' => $booked,
                'available' => 0,
                'is_full' => false,
                'waitlist' => $waitlist,
                'occurrence_id' => $occurrence_id,
            ];
        }
        $available = max(0, $capacity - $booked);
        return [
            'capacity' => $capacity,
            'booked' => $booked,
            'available' => $available,
            'is_full' => $available <= 0,
            'waitlist' => $waitlist,
            'occurrence_id' => $occurrence_id,
        ];
    }

    public function sync_session_status(int $session_id, int $occurrence_id = 0): void
    {
        $occurrences = $this->get_occurrences($session_id);
        if ($occurrences) {
            if ($occurrence_id > 0) {
                $this->promote_waitlist($session_id, $occurrence_id);
            } else {
                foreach ($occurrences as $occ) {
                    $this->promote_waitlist($session_id, (int) $occ['id']);
                }
            }
            return;
        }

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

    private function promote_waitlist(int $session_id, int $occurrence_id = 0): void
    {
        $capacity = $this->get_capacity_limit($session_id, $occurrence_id);
        if ($capacity <= 0) {
            return;
        }

        global $wpdb;
        while ($this->has_capacity($session_id, 1, $occurrence_id)) {
            $next = $wpdb->get_row(
                $occurrence_id > 0
                    ? $wpdb->prepare(
                        "SELECT * FROM {$this->table} WHERE session_id = %d AND occurrence_id = %d AND status = %s ORDER BY created_at ASC LIMIT 1",
                        $session_id,
                        $occurrence_id,
                        'waitlist'
                    )
                    : $wpdb->prepare(
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
            if (!$this->has_capacity($session_id, $people, $occurrence_id)) {
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
        register_rest_route('event-hub/v1', '/session', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'handle_rest_session'],
            'permission_callback' => '__return_true',
            'args' => [
                'session_id' => [
                    'required' => true,
                    'type' => 'integer',
                ],
                'occurrence_id' => [
                    'required' => false,
                    'type' => 'integer',
                ],
            ],
        ]);
        register_rest_route('event-hub/v1', '/registrations', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'handle_rest_registrations'],
            'permission_callback' => [$this, 'rest_can_manage_registrations'],
            'args' => [
                'session_id' => [
                    'required' => true,
                    'type' => 'integer',
                ],
                'occurrence_id' => [
                    'required' => false,
                    'type' => 'integer',
                ],
                'fields' => [
                    'required' => false,
                    'type' => 'array',
                ],
            ],
        ]);
        register_rest_route('event-hub/v1', '/registrations/views', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'handle_rest_list_views'],
            'permission_callback' => [$this, 'rest_can_manage_registrations'],
        ]);
        register_rest_route('event-hub/v1', '/registrations/views', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle_rest_save_view'],
            'permission_callback' => [$this, 'rest_can_manage_registrations'],
            'args' => [
                'name' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'fields' => [
                    'required' => true,
                    'type' => 'array',
                ],
            ],
        ]);
        register_rest_route('event-hub/v1', '/registrations/views', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [$this, 'handle_rest_delete_view'],
            'permission_callback' => [$this, 'rest_can_manage_registrations'],
            'args' => [
                'name' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);
        register_rest_route('event-hub/v1', '/cancel', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'handle_rest_cancel'],
            'permission_callback' => '__return_true',
            'args' => [
                'token' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);
    }

    public function handle_rest_register(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        if (!$params) {
            $params = $request->get_body_params();
        }

        // Honeypot: if filled, treat as spam
        if (!empty($params['_eh_hp'])) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Verzenden mislukt. Probeer opnieuw.', 'event-hub'),
            ], 400);
        }

        // Basic rate limiting
        $rate_error = $this->enforce_rate_limit();
        if ($rate_error instanceof \WP_Error) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $rate_error->get_error_message(),
            ], 429);
        }

        $extra = [];
        foreach ($params as $key => $value) {
            if (strpos((string) $key, 'extra[') === 0 && substr($key, -1) === ']') {
                $slug = trim(substr((string) $key, 6, -1));
                if ($slug !== '') {
                    $extra[$slug] = $value;
                }
            }
        }

        $data = [
            'session_id' => isset($params['session_id']) ? (int) $params['session_id'] : 0,
            'occurrence_id' => isset($params['occurrence_id']) ? (int) $params['occurrence_id'] : 0,
            'first_name' => sanitize_text_field($params['first_name'] ?? ''),
            'last_name' => sanitize_text_field($params['last_name'] ?? ''),
            'email' => sanitize_email($params['email'] ?? ''),
            'phone' => isset($params['phone']) ? sanitize_text_field((string) $params['phone']) : null,
            'company' => isset($params['company']) ? sanitize_text_field((string) $params['company']) : null,
            'vat' => isset($params['vat']) ? sanitize_text_field((string) $params['vat']) : null,
            'role' => isset($params['role']) ? sanitize_text_field((string) $params['role']) : null,
            'people_count' => isset($params['people_count']) ? (int) $params['people_count'] : 1,
            'consent_marketing' => !empty($params['consent_marketing']) ? 1 : 0,
            'extra' => $extra,
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

        $state = $this->get_capacity_state($data['session_id'], (int) $data['occurrence_id']);
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
                'occurrence_id' => (int) $data['occurrence_id'],
                'status' => $status,
                'status_label' => $status_badge['label'],
                'status_class' => $status_badge['class'],
                'state' => $state,
                'available_label' => $available_label,
                'button_disabled' => in_array($status, ['cancelled', 'closed'], true),
            ],
        ]);
    }

    public function handle_rest_session(WP_REST_Request $request): WP_REST_Response
    {
        $session_id = (int) $request->get_param('session_id');
        if ($session_id <= 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Ongeldig event.', 'event-hub'),
            ], 400);
        }

        $post = get_post($session_id);
        if (!$post || $post->post_type !== Settings::get_cpt_slug()) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Event niet gevonden.', 'event-hub'),
            ], 404);
        }

        $occurrences = $this->get_occurrences($session_id);
        $requested_occurrence = (int) $request->get_param('occurrence_id');
        $selected_occurrence = $requested_occurrence > 0 ? $this->get_occurrence($session_id, $requested_occurrence) : null;
        if (!$selected_occurrence && $occurrences) {
            $selected_occurrence = $this->get_default_occurrence($session_id);
        }
        $selected_occurrence_id = $selected_occurrence ? (int) ($selected_occurrence['id'] ?? 0) : 0;

        $state = $this->get_capacity_state($session_id, $selected_occurrence_id);
        $status = get_post_meta($session_id, '_eh_status', true) ?: 'open';
        $badge = $this->get_status_badge_data($status, $state['is_full']);
        $color = sanitize_hex_color((string) get_post_meta($session_id, '_eh_color', true)) ?: '#2271b1';
        $hero_image = get_the_post_thumbnail_url($session_id, 'full') ?: '';
        $date_start = $selected_occurrence ? ($selected_occurrence['date_start'] ?? '') : get_post_meta($session_id, '_eh_date_start', true);
        $date_end = $selected_occurrence ? ($selected_occurrence['date_end'] ?? '') : get_post_meta($session_id, '_eh_date_end', true);
        $location = get_post_meta($session_id, '_eh_location', true);
        $is_online = (bool) get_post_meta($session_id, '_eh_is_online', true);
        $online_link = get_post_meta($session_id, '_eh_online_link', true);
        $address = get_post_meta($session_id, '_eh_address', true);
        $organizer = get_post_meta($session_id, '_eh_organizer', true);
        $staff = get_post_meta($session_id, '_eh_staff', true);
        $price = get_post_meta($session_id, '_eh_price', true);
        $ticket_note = get_post_meta($session_id, '_eh_ticket_note', true);
        $booking_open = $selected_occurrence ? ($selected_occurrence['booking_open'] ?? '') : get_post_meta($session_id, '_eh_booking_open', true);
        $booking_close = $selected_occurrence ? ($selected_occurrence['booking_close'] ?? '') : get_post_meta($session_id, '_eh_booking_close', true);
        $enable_module_meta = get_post_meta($session_id, '_eh_enable_module', true);
        $module_enabled = ($enable_module_meta === '') ? true : (bool) $enable_module_meta;
        $hide_fields = array_map('sanitize_key', (array) get_post_meta($session_id, '_eh_form_hide_fields', true));
        $extra_fields = $this->get_extra_fields($session_id);

        $date_label = $date_start ? date_i18n(get_option('date_format'), strtotime($date_start)) : '';
        $time_start = $date_start ? date_i18n(get_option('time_format'), strtotime($date_start)) : '';
        $time_end = $date_end ? date_i18n(get_option('time_format'), strtotime($date_end)) : '';
        $time_range = $time_start && $time_end ? $time_start . ' - ' . $time_end : $time_start;
        $location_label = $is_online ? __('Online', 'event-hub') : ($location ?: '');

        $availability_label = $state['capacity'] > 0
            ? sprintf(_n('%d plaats beschikbaar', '%d plaatsen beschikbaar', $state['available'], 'event-hub'), $state['available'])
            : __('Onbeperkt', 'event-hub');
        $waitlist_label = $state['waitlist'] > 0
            ? sprintf(_n('%d persoon op de wachtlijst', '%d personen op de wachtlijst', $state['waitlist'], 'event-hub'), $state['waitlist'])
            : __('Geen wachtlijst', 'event-hub');

        $occurrence_payloads = [];
        if ($occurrences) {
            foreach ($occurrences as $occ) {
                $occurrence_payloads[] = $this->build_occurrence_payload($session_id, $occ);
            }
        }

        $can_register = $module_enabled;
        $register_notice = '';
        $now = current_time('timestamp');
        $open_ts = $booking_open ? strtotime($booking_open) : null;
        $close_ts = $booking_close ? strtotime($booking_close) : null;
        $event_start_ts = $date_start ? strtotime($date_start) : null;

        if ($can_register) {
            if (in_array($status, ['cancelled', 'closed'], true)) {
                $can_register = false;
                $register_notice = __('Inschrijvingen zijn gesloten.', 'event-hub');
            } elseif ($open_ts && $now < $open_ts) {
                $can_register = false;
                $register_notice = sprintf(__('Inschrijven kan vanaf %s.', 'event-hub'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $open_ts));
            } elseif (($close_ts && $now > $close_ts) || (!$close_ts && $event_start_ts && $now >= strtotime(date('Y-m-d 00:00:00', $event_start_ts)))) {
                $can_register = false;
                $register_notice = __('Inschrijvingen zijn gesloten.', 'event-hub');
            }
        }

        $captcha = [
            'enabled' => \EventHub\Security::captcha_enabled(),
            'provider' => \EventHub\Security::provider(),
            'site_key' => \EventHub\Security::site_key(),
        ];

        return new WP_REST_Response([
            'success' => true,
            'session' => [
                'id' => $session_id,
                'occurrence_id' => $selected_occurrence_id,
                'occurrences' => $occurrence_payloads,
                'title' => get_the_title($session_id),
                'excerpt' => get_the_excerpt($session_id),
                'content' => apply_filters('the_content', $post->post_content),
                'permalink' => get_permalink($session_id),
                'badge' => $badge,
                'status' => $status,
                'color' => $color,
                'hero_image' => $hero_image,
                'date_label' => $date_label,
                'time_range' => $time_range,
                'location_label' => $location_label,
                'address' => $address ?: '',
                'online_link' => $online_link ?: '',
                'organizer' => $organizer ?: '',
                'staff' => $staff ?: '',
                'price' => $price,
                'ticket_note' => $ticket_note ?: '',
                'availability_label' => $availability_label,
                'waitlist_label' => $waitlist_label,
                'state' => $state,
                'can_register' => $can_register,
                'module_enabled' => $module_enabled,
                'register_notice' => $register_notice,
                'waitlist_mode' => $can_register && $state['is_full'],
                'hide_fields' => $hide_fields,
                'extra_fields' => $extra_fields,
                'booking_open' => $booking_open ?: '',
                'booking_close' => $booking_close ?: '',
                'captcha' => $captcha,
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

    /**
     * @return array<int,array{id:int,date_start:string,date_end:string,capacity:int,booking_open:string,booking_close:string}>
     */
    public function get_occurrences(int $session_id): array
    {
        $raw = get_post_meta($session_id, '_eh_occurrences', true);
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $occ) {
            if (!is_array($occ)) {
                continue;
            }
            $id = isset($occ['id']) ? (int) $occ['id'] : 0;
            if ($id <= 0) {
                continue;
            }
            $start = isset($occ['date_start']) ? sanitize_text_field((string) $occ['date_start']) : '';
            $end = isset($occ['date_end']) ? sanitize_text_field((string) $occ['date_end']) : '';
            $capacity = isset($occ['capacity']) ? (int) $occ['capacity'] : 0;
            $booking_open = isset($occ['booking_open']) ? sanitize_text_field((string) $occ['booking_open']) : '';
            $booking_close = isset($occ['booking_close']) ? sanitize_text_field((string) $occ['booking_close']) : '';
            $location_name = isset($occ['location_name']) ? sanitize_text_field((string) $occ['location_name']) : '';
            $location_address = isset($occ['location_address']) ? sanitize_text_field((string) $occ['location_address']) : '';
            $out[] = [
                'id' => $id,
                'date_start' => $start,
                'date_end' => $end,
                'capacity' => $capacity,
                'booking_open' => $booking_open,
                'booking_close' => $booking_close,
                'location_name' => $location_name,
                'location_address' => $location_address,
            ];
        }
        usort($out, static function (array $a, array $b): int {
            $a_ts = $a['date_start'] ? strtotime($a['date_start']) : 0;
            $b_ts = $b['date_start'] ? strtotime($b['date_start']) : 0;
            return $a_ts <=> $b_ts;
        });
        return $out;
    }

    public function get_occurrence(int $session_id, int $occurrence_id): ?array
    {
        if ($occurrence_id <= 0) {
            return null;
        }
        foreach ($this->get_occurrences($session_id) as $occ) {
            if ((int) $occ['id'] === $occurrence_id) {
                return $occ;
            }
        }
        return null;
    }

    public function get_default_occurrence(int $session_id): ?array
    {
        $occurrences = $this->get_occurrences($session_id);
        if (!$occurrences) {
            return null;
        }
        $now = current_time('timestamp');
        foreach ($occurrences as $occ) {
            $start_ts = $occ['date_start'] ? strtotime($occ['date_start']) : 0;
            if ($start_ts && $start_ts >= $now) {
                return $occ;
            }
        }
        return $occurrences[0] ?? null;
    }

    /**
     * @return array<string,mixed>
     */
    private function build_occurrence_payload(int $session_id, array $occurrence): array
    {
        $date_start = $occurrence['date_start'] ?? '';
        $date_end = $occurrence['date_end'] ?? '';
        $date_label = $date_start ? date_i18n(get_option('date_format'), strtotime($date_start)) : '';
        $time_start = $date_start ? date_i18n(get_option('time_format'), strtotime($date_start)) : '';
        $time_end = $date_end ? date_i18n(get_option('time_format'), strtotime($date_end)) : '';
        $time_range = $time_start && $time_end ? $time_start . ' - ' . $time_end : $time_start;
        $location_name = $occurrence['location_name'] ?? '';
        $location_address = $occurrence['location_address'] ?? '';
        $state = $this->get_capacity_state($session_id, (int) ($occurrence['id'] ?? 0));
        $availability_label = $state['capacity'] > 0
            ? sprintf(_n('%d plaats beschikbaar', '%d plaatsen beschikbaar', $state['available'], 'event-hub'), $state['available'])
            : __('Onbeperkt', 'event-hub');
        $waitlist_label = $state['waitlist'] > 0
            ? sprintf(_n('%d persoon op de wachtlijst', '%d personen op de wachtlijst', $state['waitlist'], 'event-hub'), $state['waitlist'])
            : __('Geen wachtlijst', 'event-hub');

        return [
            'id' => (int) ($occurrence['id'] ?? 0),
            'date_start' => $date_start,
            'date_end' => $date_end,
            'date_label' => $date_label,
            'time_range' => $time_range,
            'booking_open' => $occurrence['booking_open'] ?? '',
            'booking_close' => $occurrence['booking_close'] ?? '',
            'location_name' => $location_name,
            'location_address' => $location_address,
            'state' => $state,
            'availability_label' => $availability_label,
            'waitlist_label' => $waitlist_label,
        ];
    }

    private function format_occurrence_label(int $session_id, int $occurrence_id): string
    {
        if ($occurrence_id <= 0) {
            $date_start = get_post_meta($session_id, '_eh_date_start', true);
            if (!$date_start) {
                return '';
            }
            $date = date_i18n(get_option('date_format'), strtotime($date_start));
            $time = date_i18n(get_option('time_format'), strtotime($date_start));
            return trim($date . ' ' . $time);
        }
        $occurrence = $this->get_occurrence($session_id, $occurrence_id);
        if (!$occurrence) {
            return '';
        }
        $date_start = $occurrence['date_start'] ?? '';
        if (!$date_start) {
            return '';
        }
        $date = date_i18n(get_option('date_format'), strtotime($date_start));
        $time = date_i18n(get_option('time_format'), strtotime($date_start));
        return trim($date . ' ' . $time);
    }

    /**
     * @return array<int,array{label:string,slug:string,type:string,required:bool,options:array<string>,builtin:bool}>
     */
    /**
     * Get extra field definitions for a session.
     *
     * @return array<int,array{label:string,slug:string,type:string,required:bool,options:array<string>,builtin:bool}>
     */
    public function get_extra_fields(int $session_id): array
    {
        $defs = get_post_meta($session_id, '_eh_extra_fields', true);
        $out = [];
        if (!is_array($defs)) {
            return $out;
        }
        foreach ($defs as $def) {
            $slug = isset($def['slug']) ? sanitize_key((string) $def['slug']) : '';
            if ($slug === '') {
                continue;
            }
            $type = isset($def['type']) ? sanitize_key((string) $def['type']) : 'text';
            $allowed = ['text','textarea','select'];
            if (!in_array($type, $allowed, true)) {
                $type = 'text';
            }
            $options = [];
            if ($type === 'select' && !empty($def['options']) && is_array($def['options'])) {
                foreach ($def['options'] as $opt) {
                    $opt = trim((string) $opt);
                    if ($opt !== '') {
                        $options[] = $opt;
                    }
                }
            }
            $out[] = [
                'label' => isset($def['label']) ? sanitize_text_field((string) $def['label']) : $slug,
                'slug' => $slug,
                'type' => $type,
                'required' => !empty($def['required']),
                'options' => $options,
                'builtin' => false,
            ];
        }
        return $out;
    }

    private function get_capacity_limit(int $session_id, int $occurrence_id = 0): int
    {
        if ($occurrence_id > 0) {
            $occurrence = $this->get_occurrence($session_id, $occurrence_id);
            if ($occurrence && isset($occurrence['capacity'])) {
                return (int) $occurrence['capacity'];
            }
        }
        return (int) get_post_meta($session_id, '_eh_capacity', true);
    }

    /**
     * Validate/sanitize extra payload.
     *
     * @param array $fields
     * @param array $provided
     * @return array|\WP_Error
     */
    private function sanitize_extra_payload(array $fields, $provided)
    {
        $provided = is_array($provided) ? $provided : [];
        $clean = [];
        foreach ($fields as $field) {
            $slug = $field['slug'];
            $val = $provided[$slug] ?? '';
            if ($field['required'] && $val === '') {
                return new \WP_Error('extra_required', sprintf(__('Veld "%s" is verplicht.', 'event-hub'), $field['label']));
            }
            if ($val === '') {
                continue;
            }
            if ($field['type'] === 'select') {
                $options = $field['options'] ?? [];
                if ($options && !in_array($val, $options, true)) {
                    return new \WP_Error('extra_invalid', sprintf(__('Ongeldige keuze voor "%s".', 'event-hub'), $field['label']));
                }
                $clean[$slug] = sanitize_text_field((string) $val);
            } elseif ($field['type'] === 'textarea') {
                $clean[$slug] = wp_kses_post((string) $val);
            } else {
                $clean[$slug] = sanitize_text_field((string) $val);
            }
        }
        return $clean;
    }

    public function handle_rest_registrations(WP_REST_Request $request): WP_REST_Response
    {
        $session_id = (int) $request->get_param('session_id');
        $occurrence_id = (int) $request->get_param('occurrence_id');
        if ($session_id <= 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Event ID ontbreekt.', 'event-hub'),
            ], 400);
        }

        $available = $this->get_export_fields();
        $fields = $request->get_param('fields');
        $fields = is_array($fields) ? array_map('sanitize_key', $fields) : [];
        $fields = $fields ? array_values(array_intersect(array_keys($available), $fields)) : array_keys($available);

        $rows = $this->get_registrations_by_session($session_id, $occurrence_id);
        $registrations = [];
        foreach ($rows as $row) {
            $row_occurrence_id = (int) ($row['occurrence_id'] ?? 0);
            $occurrence_label = $this->format_occurrence_label((int) $row['session_id'], $row_occurrence_id);
            $decoded_extra = [];
            if (!empty($row['extra_data'])) {
                $decoded = json_decode((string) $row['extra_data'], true);
                if (is_array($decoded)) {
                    $decoded_extra = $decoded;
                }
            }
            $flat_extra = $this->format_extra_data($decoded_extra);
            $record = [
                'id' => (int) $row['id'],
                'occurrence_id' => $row_occurrence_id,
                'occurrence_label' => $occurrence_label,
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'company' => $row['company'],
                'vat' => $row['vat'],
                'role' => $row['role'],
                'people_count' => (int) $row['people_count'],
                'status' => $row['status'],
                'consent_marketing' => (int) $row['consent_marketing'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'extra_data' => $flat_extra,
            ];
            $registrations[] = array_intersect_key($record, array_flip($fields));
        }

        return new WP_REST_Response([
            'success' => true,
            'fields' => $fields,
            'available_fields' => $available,
            'registrations' => $registrations,
        ]);
    }

    public function handle_rest_list_views(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => true,
            'views' => $this->get_user_views(),
        ]);
    }

    public function handle_rest_save_view(WP_REST_Request $request): WP_REST_Response
    {
        $name = sanitize_text_field((string) $request->get_param('name'));
        $fields = $request->get_param('fields');
        $available = $this->get_export_fields();
        if ($name === '' || !is_array($fields)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Naam of velden ontbreken.', 'event-hub'),
            ], 400);
        }
        $clean_fields = array_values(array_intersect(array_keys($available), array_map('sanitize_key', $fields)));
        if (!$clean_fields) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Geen geldige velden geselecteerd.', 'event-hub'),
            ], 400);
        }
        $views = $this->get_user_views();
        $views[$name] = $clean_fields;
        $this->store_user_views($views);
        return new WP_REST_Response([
            'success' => true,
            'views' => $views,
        ]);
    }

    public function handle_rest_delete_view(WP_REST_Request $request): WP_REST_Response
    {
        $name = sanitize_text_field((string) $request->get_param('name'));
        $views = $this->get_user_views();
        if ($name === '' || !isset($views[$name])) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('View niet gevonden.', 'event-hub'),
            ], 404);
        }
        unset($views[$name]);
        $this->store_user_views($views);
        return new WP_REST_Response([
            'success' => true,
            'views' => $views,
        ]);
    }

    public function handle_rest_cancel(WP_REST_Request $request): WP_REST_Response
    {
        $token = sanitize_text_field((string) $request->get_param('token'));
        $result = $this->cancel_by_token($token);
        if (is_wp_error($result)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
            ], 400);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Je inschrijving werd geannuleerd.', 'event-hub'),
            'registration_id' => (int) $result['id'],
            'session_id' => (int) $result['session_id'],
        ], 200);
    }

    public function rest_can_manage_registrations(): bool
    {
        return current_user_can('edit_posts');
    }

    /**
     * @return array<string,array>
     */
    private function get_user_views(): array
    {
        if (!is_user_logged_in()) {
            return [];
        }
        $views = get_user_meta(get_current_user_id(), 'event_hub_sp_views', true);
        return is_array($views) ? $views : [];
    }

    private function store_user_views(array $views): void
    {
        if (!is_user_logged_in()) {
            return;
        }
        update_user_meta(get_current_user_id(), 'event_hub_sp_views', $views);
    }

    /**
     * IP-based rate limiting for the REST registration endpoint.
     * Allows 10 requests per 10 minutes per IP; admins are exempt.
     *
     * @return true|\WP_Error
     */
    private function enforce_rate_limit()
    {
        if (is_user_logged_in() && current_user_can('manage_options')) {
            return true;
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ip = sanitize_text_field($ip);
        if ($ip === '') {
            return true;
        }
        $limit = apply_filters('event_hub_rate_limit_count', 10);
        $window = apply_filters('event_hub_rate_limit_window', 10 * MINUTE_IN_SECONDS);
        $key = 'event_hub_rate_' . md5($ip);
        $record = get_transient($key);
        $now = time();
        if (!is_array($record) || !isset($record['count'], $record['start']) || ($now - (int) $record['start']) > $window) {
            $record = ['count' => 1, 'start' => $now];
            set_transient($key, $record, $window);
            return true;
        }
        if ((int) $record['count'] >= $limit) {
            return new \WP_Error('rate_limited', __('Te veel pogingen. Probeer het later opnieuw.', 'event-hub'));
        }
        $record['count'] = (int) $record['count'] + 1;
        set_transient($key, $record, $window);
        return true;
    }

    /**
     * Lijst van velden voor export/overzicht.
     *
     * @return array<string,string>
     */
    public function get_export_fields(): array
    {
        return [
            'occurrence_label' => __('Datum', 'event-hub'),
            'occurrence_id' => __('Occurrence ID', 'event-hub'),
            'first_name' => __('Voornaam', 'event-hub'),
            'last_name' => __('Familienaam', 'event-hub'),
            'email' => __('E-mail', 'event-hub'),
            'phone' => __('Telefoon', 'event-hub'),
            'company' => __('Bedrijf', 'event-hub'),
            'vat' => __('BTW', 'event-hub'),
            'role' => __('Rol', 'event-hub'),
            'people_count' => __('Aantal', 'event-hub'),
            'status' => __('Status', 'event-hub'),
            'created_at' => __('Aangemaakt op', 'event-hub'),
            'extra_data' => __('Extra velden', 'event-hub'),
        ];
    }

    private function format_extra_data(array $data): string
    {
        if (!$data) {
            return '';
        }
        $lines = [];
        foreach ($data as $key => $val) {
            $label = is_string($key) ? $key : (string) $key;
            if (is_array($val)) {
                $val = implode(', ', $val);
            }
            $lines[] = $label . ': ' . wp_strip_all_tags((string) $val);
        }
        return implode(' | ', $lines);
    }
}
