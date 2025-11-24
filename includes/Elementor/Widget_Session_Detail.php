<?php
namespace EventHub\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use EventHub\Registrations;
use EventHub\Settings;

defined('ABSPATH') || exit;

class Widget_Session_Detail extends Widget_Base
{
    private Registrations $registrations;

    public function __construct(Registrations $registrations, array $data = [], array $args = null)
    {
        parent::__construct($data, $args);
        $this->registrations = $registrations;
    }

    public function get_name(): string
    {
        return 'eventhub_session_detail';
    }

    public function get_title(): string
    {
        return __('Eventdetail + Inschrijving', 'event-hub');
    }

    public function get_icon(): string
    {
        return 'eicon-post';
    }

    public function get_categories(): array
    {
        return ['general'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('section_content', [
            'label' => __('Inhoud', 'event-hub'),
        ]);

        $this->add_control('detect_current', [
            'label' => __('Gebruik huidig event', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $options = [];
        $posts = get_posts([
            'post_type' => Settings::get_cpt_slug(),
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        foreach ($posts as $post) {
            $options[$post->ID] = $post->post_title;
        }

        $this->add_control('session_id', [
            'label' => __('Selecteer event', 'event-hub'),
            'type' => Controls_Manager::SELECT2,
            'options' => $options,
            'multiple' => false,
            'label_block' => true,
            'condition' => ['detect_current!' => 'yes'],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $session_id = $this->resolve_session_id($settings);
        if (!$session_id) {
            echo '<p>' . esc_html__('Geen event geselecteerd.', 'event-hub') . '</p>';
            return;
        }

        $this->render_details($session_id);
        $this->render_registration_form($session_id);
        $this->inline_styles();
    }

    private function resolve_session_id(array $settings): int
    {
        $post_id = 0;
        $cpt = Settings::get_cpt_slug();
        if (!empty($settings['detect_current']) && $settings['detect_current'] === 'yes') {
            $post_id = get_the_ID();
        } elseif (!empty($settings['session_id'])) {
            $post_id = (int) $settings['session_id'];
        }

        if (!$post_id || get_post_type($post_id) !== $cpt) {
            return 0;
        }
        return $post_id;
    }

    private function render_details(int $session_id): void
    {
        $title = get_the_title($session_id);
        $content = apply_filters('the_content', get_post_field('post_content', $session_id));
        $start = get_post_meta($session_id, '_eh_date_start', true);
        $end = get_post_meta($session_id, '_eh_date_end', true);
        $location = get_post_meta($session_id, '_eh_location', true);
        $is_online = (bool) get_post_meta($session_id, '_eh_is_online', true);
        $online_link = get_post_meta($session_id, '_eh_online_link', true);
        $address = get_post_meta($session_id, '_eh_address', true);
        $organizer = get_post_meta($session_id, '_eh_organizer', true);
        $staff = get_post_meta($session_id, '_eh_staff', true);
        $price = get_post_meta($session_id, '_eh_price', true);
        $ticket_note = get_post_meta($session_id, '_eh_ticket_note', true);
        $no_show_fee = get_post_meta($session_id, '_eh_no_show_fee', true);
        $status = get_post_meta($session_id, '_eh_status', true) ?: 'open';
        $color = sanitize_hex_color((string) get_post_meta($session_id, '_eh_color', true)) ?: '#2271b1';

        $state = $this->registrations->get_capacity_state($session_id);
        $capacity = $state['capacity'];
        $available = $state['available'];
        $is_full = $state['is_full'];

        $date_label = $start ? date_i18n(get_option('date_format'), strtotime($start)) : '';
        $time_start = $start ? date_i18n(get_option('time_format'), strtotime($start)) : '';
        $time_end = $end ? date_i18n(get_option('time_format'), strtotime($end)) : '';

        echo '<div class="eh-session-detail">';
        echo '<h2>' . esc_html($title) . '</h2>';
        if ($date_label) {
            $time_range = $time_end ? $time_start . ' - ' . $time_end : $time_start;
            echo '<div class="eh-meta">' . esc_html($date_label . ' • ' . $time_range) . '</div>';
        }
        if ($is_online && $online_link) {
            echo '<div class="eh-meta"><a href="' . esc_url($online_link) . '" target="_blank" rel="noopener">' . esc_html__('Deelnamelink', 'event-hub') . '</a></div>';
        } elseif ($location) {
            echo '<div class="eh-meta">' . esc_html($location) . '</div>';
        }
        if ($address) {
            echo '<div class="eh-meta">' . esc_html($address) . '</div>';
        }
        if ($organizer) {
            echo '<div class="eh-meta">' . esc_html(sprintf(__('Organisator: %s', 'event-hub'), $organizer)) . '</div>';
        }
        if ($staff) {
            echo '<div class="eh-meta">' . esc_html(sprintf(__('Sprekers/medewerkers: %s', 'event-hub'), $staff)) . '</div>';
        }
        $pricing_lines = [];
        if ($price !== '') {
            $pricing_lines[] = sprintf(__('Prijs: %s', 'event-hub'), $price);
        }
        if ($no_show_fee !== '') {
            $pricing_lines[] = sprintf(__('No-showkost: %s', 'event-hub'), $no_show_fee);
        }
        if ($pricing_lines) {
            $escaped = array_map('esc_html', $pricing_lines);
            echo '<div class="eh-meta">' . implode('<br>', $escaped) . '</div>';
        }
        if ($ticket_note) {
            echo '<div class="eh-meta">' . wp_kses_post(nl2br($ticket_note)) . '</div>';
        }
        if ($capacity > 0) {
            echo '<div class="eh-meta"><strong>' . esc_html__('Beschikbaarheden', 'event-hub') . ':</strong> ' . esc_html(sprintf(_n('%d plaats', '%d plaatsen', $available, 'event-hub'), $available)) . '</div>';
        }

        $badge = $this->get_status_badge($status, $is_full);
        if ($badge) {
            echo '<span class="eh-badge ' . esc_attr($badge['class']) . '">' . esc_html($badge['label']) . '</span>';
        }

        echo '<div class="eh-content">' . $content . '</div>';
        echo '<hr style="border:0;border-top:1px solid #f0f0f0;margin:24px 0" />';
        echo '<h3 style="color:' . esc_attr($color) . ';">' . esc_html__('Inschrijven', 'event-hub') . '</h3>';
        echo '</div>';
    }

    private function render_registration_form(int $session_id): void
    {
        $status = get_post_meta($session_id, '_eh_status', true) ?: 'open';
        $state = $this->registrations->get_capacity_state($session_id);
        $capacity = $state['capacity'];
        $is_full = $state['is_full'];
        $booking_open = get_post_meta($session_id, '_eh_booking_open', true);
        $booking_close = get_post_meta($session_id, '_eh_booking_close', true);
        $now = current_time('timestamp');

        $before_window = $booking_open && $now < strtotime($booking_open);
        $after_window = $booking_close && $now > strtotime($booking_close);

        if ($status !== 'open' || $is_full || $before_window || $after_window) {
            if ($status === 'cancelled') {
                echo '<div class="eh-alert error">' . esc_html__('Dit event werd geannuleerd.', 'event-hub') . '</div>';
            } elseif ($status === 'closed') {
                echo '<div class="eh-alert error">' . esc_html__('Inschrijvingen zijn gesloten.', 'event-hub') . '</div>';
            } elseif ($is_full) {
                echo '<div class="eh-alert notice">' . esc_html__('Dit event is volzet.', 'event-hub') . '</div>';
            } elseif ($before_window) {
                echo '<div class="eh-alert notice">' . esc_html__('De inschrijvingen zijn nog niet geopend.', 'event-hub') . '</div>';
            } else {
                echo '<div class="eh-alert notice">' . esc_html__('De inschrijvingen zijn afgesloten.', 'event-hub') . '</div>';
            }
            return;
        }

        $message = '';
        $error = '';
        if (
            isset($_POST['eh_register_nonce'])
            && wp_verify_nonce(sanitize_text_field((string) $_POST['eh_register_nonce']), 'eh_register_' . $session_id)
        ) {
            $data = [
                'session_id' => $session_id,
                'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
                'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
                'email' => sanitize_text_field($_POST['email'] ?? ''),
                'phone' => sanitize_text_field($_POST['phone'] ?? ''),
                'company' => sanitize_text_field($_POST['company'] ?? ''),
                'vat' => sanitize_text_field($_POST['vat'] ?? ''),
                'role' => sanitize_text_field($_POST['role'] ?? ''),
                'people_count' => isset($_POST['people_count']) ? (int) $_POST['people_count'] : 1,
                'consent_marketing' => isset($_POST['consent_marketing']) ? 1 : 0,
            ];
            $result = $this->registrations->create_registration($data);
            if (is_wp_error($result)) {
                $error = $result->get_error_message();
            } else {
                $message = __('Bedankt! We hebben je inschrijving ontvangen.', 'event-hub');
            }
        }

        if ($message) {
            echo '<div class="eh-alert success">' . esc_html($message) . '</div>';
            return;
        }
        if ($error) {
            echo '<div class="eh-alert error">' . esc_html($error) . '</div>';
        }

        echo '<form method="post" class="eh-form">';
        wp_nonce_field('eh_register_' . $session_id, 'eh_register_nonce');
        echo '<div class="eh-grid">';
        echo $this->input('first_name', __('Voornaam', 'event-hub'), true);
        echo $this->input('last_name', __('Familienaam', 'event-hub'), true);
        echo $this->input('email', __('E-mailadres', 'event-hub'), true, 'email');
        echo $this->input('phone', __('Telefoon', 'event-hub'));
        echo $this->input('company', __('Bedrijf', 'event-hub'));
        echo $this->input('vat', __('BTW-nummer', 'event-hub'));
        echo $this->input('role', __('Rol', 'event-hub'));
        echo $this->number('people_count', __('Aantal personen', 'event-hub'), $capacity > 0 ? $capacity : 99);
        echo '<div class="field full"><label><input type="checkbox" name="consent_marketing" value="1" /> ' . esc_html__('Ik wil marketingupdates ontvangen', 'event-hub') . '</label></div>';
        echo '</div>';

        $this->render_captcha_fields();

        echo '<button type="submit" class="eh-btn">' . esc_html__('Inschrijven', 'event-hub') . '</button>';
        echo '</form>';
    }

    private function input(string $name, string $label, bool $required = false, string $type = 'text'): string
    {
        $value = isset($_POST[$name]) ? sanitize_text_field((string) $_POST[$name]) : '';
        $req = $required ? ' required' : '';
        $asterisk = $required ? ' *' : '';
        return '<div class="field"><label for="' . esc_attr($name) . '">' . esc_html($label . $asterisk) . '</label><input type="' . esc_attr($type) . '" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . $req . ' /></div>';
    }

    private function number(string $name, string $label, int $max): string
    {
        $value = isset($_POST[$name]) ? (int) $_POST[$name] : 1;
        $value = max(1, $value);
        return '<div class="field"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label><input type="number" min="1" max="' . esc_attr((string) max(1, $max)) . '" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" /></div>';
    }

    private function render_captcha_fields(): void
    {
        if (!\EventHub\Security::captcha_enabled()) {
            return;
        }
        $site_key = \EventHub\Security::site_key();
        if (!$site_key) {
            return;
        }
        $provider = \EventHub\Security::provider();
        echo '<input type="hidden" name="eh_captcha_token" id="eh_captcha_token" value="">';
        if ($provider === 'hcaptcha') {
            echo '<script src="https://js.hcaptcha.com/1/api.js?render=' . esc_attr($site_key) . '" async defer></script>';
            echo '<script>window.addEventListener("load",function(){if(window.hcaptcha){hcaptcha.ready(function(){hcaptcha.execute("' . esc_js($site_key) . '",{action:"eventhub_register"}).then(function(token){var el=document.getElementById("eh_captcha_token");if(el){el.value=token;}});});}});</script>';
        } else {
            echo '<script src="https://www.google.com/recaptcha/api.js?render=' . esc_attr($site_key) . '"></script>';
            echo '<script>document.addEventListener("DOMContentLoaded",function(){if(window.grecaptcha){grecaptcha.ready(function(){grecaptcha.execute("' . esc_js($site_key) . '",{action:"eventhub_register"}).then(function(token){var el=document.getElementById("eh_captcha_token");if(el){el.value=token;}});});}});</script>';
        }
    }

    private function get_status_badge(string $status, bool $is_full): ?array
    {
        if ($status === 'cancelled') {
            return ['label' => __('Geannuleerd', 'event-hub'), 'class' => 'eh-badge-cancelled'];
        }
        if ($status === 'closed') {
            return ['label' => __('Gesloten', 'event-hub'), 'class' => 'eh-badge-closed'];
        }
        if ($status === 'full' || $is_full) {
            return ['label' => __('Volzet', 'event-hub'), 'class' => 'eh-badge-full'];
        }
        return null;
    }

    private function inline_styles(): void
    {
        echo '<style>
        .eh-session-detail .eh-meta{color:#555;margin-bottom:6px}
        .eh-session-detail .eh-badge{display:inline-block;margin:10px 0;padding:4px 10px;border-radius:4px;color:#fff;font-size:13px}
        .eh-badge-full{background:#c62828}
        .eh-badge-cancelled{background:#8e24aa}
        .eh-badge-closed{background:#555}
        .eh-content{margin:16px 0}
        .eh-alert{padding:12px 16px;border-radius:4px;margin-bottom:16px}
        .eh-alert.success{background:#e7f7ec;border:1px solid #b0e2bf}
        .eh-alert.error{background:#fdecea;border:1px solid #f5c0bc}
        .eh-alert.notice{background:#fff4e5;border:1px solid #f2cb99}
        .eh-form .eh-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:12px}
        .eh-form .field label{display:block;font-weight:600;margin-bottom:4px}
        .eh-form input[type=text],.eh-form input[type=email],.eh-form input[type=number]{width:100%;padding:8px;border:1px solid #dcdcdc;border-radius:4px}
        .eh-form .field.full{grid-column:1/-1}
        .eh-btn{background:#2271b1;color:#fff;border:0;padding:10px 20px;border-radius:4px;cursor:pointer;font-size:16px}
        </style>';
    }
}
