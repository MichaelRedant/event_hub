<?php
namespace EventHub;

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
     * @param array $data
     * @return int|\WP_Error Inserted ID or WP_Error
     */
    public function create_registration(array $data)
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
        ];
        $data = wp_parse_args($data, $defaults);

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

        if (empty($data['session_id']) || empty($data['first_name']) || empty($data['last_name']) || empty($data['email'])) {
            return new \WP_Error('invalid_data', __('Verplichte velden ontbreken.', 'event-hub'));
        }
        if (!is_email($data['email'])) {
            return new \WP_Error('invalid_email', __('E-mailadres is ongeldig.', 'event-hub'));
        }

        $session_status = get_post_meta((int) $data['session_id'], '_eh_status', true) ?: 'open';
        if ($session_status !== 'open') {
            return new \WP_Error('event_closed', __('Dit event accepteert momenteel geen inschrijvingen.', 'event-hub'));
        }

        // CAPTCHA check
        if (\EventHub\Security::captcha_enabled()) {
            $token = isset($_POST['eh_captcha_token']) ? sanitize_text_field((string) $_POST['eh_captcha_token']) : '';
            if (!\EventHub\Security::verify_token($token, 'event_hub_register')) {
                return new \WP_Error('captcha_failed', __('CAPTCHA validatie mislukt. Probeer opnieuw.', 'event-hub'));
            }
        }

        // Booking window + capacity checks
        $now = current_time('timestamp');
        $open = get_post_meta((int)$data['session_id'], '_eh_booking_open', true);
        $close = get_post_meta((int)$data['session_id'], '_eh_booking_close', true);
        if ($open && $now < strtotime($open)) {
            return new \WP_Error('booking_not_open', __('Inschrijvingen zijn nog niet geopend voor dit event.', 'event-hub'));
        }
        if ($close && $now > strtotime($close)) {
            return new \WP_Error('booking_closed', __('Inschrijvingen zijn gesloten voor dit event.', 'event-hub'));
        }
        if (!$this->can_register($data['session_id'], $data['people_count'])) {
            return new \WP_Error('capacity_full', __('Dit event zit vol.', 'event-hub'));
        }

        // Duplicate check by email + session
        if ($this->exists_by_email((int) $data['session_id'], (string) $data['email'])) {
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
        do_action('event_hub_registration_created', $id);
        return $id;
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
        if ($open && $now < strtotime($open)) {
            return false;
        }
        if ($close && $now > strtotime($close)) {
            return false;
        }
        $capacity = (int) get_post_meta($session_id, '_eh_capacity', true);
        if ($capacity <= 0) {
            return true; // unlimited or not set
        }
        $booked = $this->count_booked($session_id);
        return ($booked + max(1, $people)) <= $capacity;
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
        } elseif (!$state['is_full'] && $status === 'full') {
            update_post_meta($session_id, '_eh_status', 'open');
        }
    }
}
