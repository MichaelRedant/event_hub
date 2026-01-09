<?php
namespace EventHub\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use EventHub\Registrations;
use EventHub\Settings;

defined('ABSPATH') || exit;

class Widget_Session_Status extends Widget_Base
{
    protected Registrations $registrations;

    public function __construct($data = [], $args = null)
    {
        parent::__construct($data, $args);
        $this->registrations = new Registrations();
    }

    public function get_name(): string
    {
        return 'eventhub_session_status';
    }

    public function get_title(): string
    {
        return __('Event status strip', 'event-hub');
    }

    public function get_icon(): string
    {
        return 'eicon-info-box';
    }

    public function get_categories(): array
    {
        return ['event-hub'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('section_content', [
            'label' => __('Bron', 'event-hub'),
        ]);

        $this->add_control('detect_current', [
            'label' => __('Gebruik huidig event', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->add_control('session_id', [
            'label' => __('Fallback / kies event', 'event-hub'),
            'type' => Controls_Manager::SELECT2,
            'options' => $this->get_session_options(),
            'multiple' => false,
            'label_block' => true,
        ]);

        $this->add_control('cta_label', [
            'label' => __('CTA-label', 'event-hub'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Inschrijven', 'event-hub'),
        ]);

        $this->add_control('cta_url_override', [
            'label' => __('Eigen CTA-link (optioneel)', 'event-hub'),
            'type' => Controls_Manager::URL,
            'placeholder' => 'https://',
            'label_block' => true,
        ]);

        $this->add_control('show_waitlist_cta', [
            'label' => __('Wachtlijst CTA tonen bij volzet', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_style', [
            'label' => __('Stijl', 'event-hub'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'title_typo',
            'selector' => '{{WRAPPER}} .eh-status__title',
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'meta_typo',
            'selector' => '{{WRAPPER}} .eh-status__meta',
        ]);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $session_id = $this->resolve_session_id($settings);
        if (!$session_id) {
            echo '<div class="eh-alert notice">' . esc_html__('Geen event gevonden.', 'event-hub') . '</div>';
            return;
        }

        $status = get_post_meta($session_id, '_eh_status', true) ?: 'open';
        $state = $this->registrations->get_capacity_state($session_id);
        $date_start = get_post_meta($session_id, '_eh_date_start', true);
        $date_label = $date_start ? date_i18n(get_option('date_format'), strtotime($date_start)) : '';
        $time_label = $date_start ? date_i18n(get_option('time_format'), strtotime($date_start)) : '';
        $availability = $this->availability_label($state);
        $badge = $this->get_badge($status, $state['is_full']);
        $cta_label = !empty($settings['cta_label']) ? $settings['cta_label'] : __('Inschrijven', 'event-hub');
        $cta_url = !empty($settings['cta_url_override']['url']) ? $settings['cta_url_override']['url'] : get_permalink($session_id);
        $show_waitlist_cta = !empty($settings['show_waitlist_cta']) && $settings['show_waitlist_cta'] === 'yes';

        $cta_disabled = in_array($status, ['cancelled', 'closed'], true);
        if ($state['is_full'] && !$show_waitlist_cta) {
            $cta_disabled = true;
        }
        $cta_text = $cta_label;
        if ($state['is_full'] && $show_waitlist_cta) {
            $cta_text = __('Op wachtlijst', 'event-hub');
        }
        if ($cta_disabled) {
            $cta_text = __('Inschrijvingen gesloten', 'event-hub');
        }

        echo '<div class="eh-status-bar">';
        echo '<div class="eh-status__info">';
        if ($badge) {
            echo '<span class="eh-badge ' . esc_attr($badge['class']) . '">' . esc_html($badge['label']) . '</span>';
        }
        echo '<div class="eh-status__title">' . esc_html(get_the_title($session_id)) . '</div>';
        echo '<div class="eh-status__meta">';
        if ($date_label) {
            echo '<span>' . esc_html($date_label . ($time_label ? ' • ' . $time_label : '')) . '</span>';
        }
        if ($availability) {
            echo '<span>' . esc_html($availability) . '</span>';
        }
        echo '</div>';
        echo '</div>';

        echo '<div class="eh-status__cta">';
        if ($cta_disabled) {
            echo '<span class="eh-status__pill">' . esc_html($cta_text) . '</span>';
        } else {
            $target = !empty($settings['cta_url_override']['is_external']) ? ' target="_blank" rel="noopener"' : '';
            echo '<a class="eh-btn" href="' . esc_url($cta_url) . '"' . $target . '>' . esc_html($cta_text) . '</a>';
        }
        echo '</div>';
        echo '</div>';
        $this->inline_styles();
    }

    private function availability_label(array $state): string
    {
        if (empty($state['capacity'])) {
            return '';
        }
        return sprintf(_n('%d plaats beschikbaar', '%d plaatsen beschikbaar', $state['available'], 'event-hub'), $state['available']);
    }

    private function get_badge(string $status, bool $is_full): ?array
    {
        if ($status === 'cancelled') {
            return ['label' => __('Geannuleerd', 'event-hub'), 'class' => 'eh-badge-cancelled'];
        }
        if ($status === 'closed') {
            return ['label' => __('Gesloten', 'event-hub'), 'class' => 'eh-badge-closed'];
        }
        if ($status === 'full' || $is_full) {
            return ['label' => __('Wachtlijst', 'event-hub'), 'class' => 'eh-badge-full'];
        }
        return ['label' => __('Beschikbaar', 'event-hub'), 'class' => 'eh-badge-open'];
    }

    private function resolve_session_id(array $settings): int
    {
        $cpt = Settings::get_cpt_slug();
        $use_detect = !empty($settings['detect_current']) && $settings['detect_current'] === 'yes';
        if ($use_detect) {
            $candidate = get_queried_object_id() ?: get_the_ID();
            if ($candidate) {
                $candidate_type = get_post_type($candidate);
                if ($candidate_type === $cpt) {
                    return (int) $candidate;
                }
                $linked = $this->find_linked_session_id((int) $candidate, (string) $candidate_type);
                if ($linked) {
                    return $linked;
                }
            }
        }
        if (!empty($settings['session_id'])) {
            $manual = (int) $settings['session_id'];
            if ($manual && get_post_type($manual) === $cpt) {
                return $manual;
            }
        }
        $posts = get_posts([
            'post_type' => $cpt,
            'numberposts' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        return $posts ? (int) $posts[0]->ID : 0;
    }

    private function find_linked_session_id(int $external_id, string $external_type): int
    {
        if (!$external_id || $external_type === '') {
            return 0;
        }
        $linked_session_id = (int) get_post_meta($external_id, '_eh_linked_session_id', true);
        if ($linked_session_id) {
            $type = get_post_type($linked_session_id);
            if (!$type) {
                global $wpdb;
                $type = $wpdb->get_var($wpdb->prepare("SELECT post_type FROM {$wpdb->posts} WHERE ID = %d", $linked_session_id));
            }
            if ($type === Settings::get_cpt_slug()) {
                return $linked_session_id;
            }
        }
        $args = [
            'post_type' => Settings::get_cpt_slug(),
            'post_status' => 'any',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => '_eh_linked_event_cpt',
                    'value' => $external_type,
                ],
                [
                    'key' => '_eh_linked_event_id',
                    'value' => (string) $external_id,
                    'compare' => '=',
                ],
            ],
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
        ];
        $query = new \WP_Query($args);
        if (!empty($query->posts)) {
            return (int) $query->posts[0]->ID;
        }
        $args_id_only = $args;
        $args_id_only['meta_query'] = [
            [
                'key' => '_eh_linked_event_id',
                'value' => (string) $external_id,
                'compare' => '=',
            ],
        ];
        $query = new \WP_Query($args_id_only);
        if (!empty($query->posts)) {
            return (int) $query->posts[0]->ID;
        }
        $args['suppress_filters'] = true;
        $query = new \WP_Query($args);
        if (!empty($query->posts)) {
            return (int) $query->posts[0]->ID;
        }
        $args_id_only['suppress_filters'] = true;
        $query = new \WP_Query($args_id_only);
        if (!empty($query->posts)) {
            return (int) $query->posts[0]->ID;
        }
        global $wpdb;
        $sql = "
            SELECT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} m1 ON p.ID = m1.post_id AND m1.meta_key = %s AND m1.meta_value = %s
            LEFT JOIN {$wpdb->postmeta} m2 ON p.ID = m2.post_id AND m2.meta_key = %s
            WHERE p.post_type = %s
              AND p.post_status IN ('publish','future','draft','pending','private')
              AND (m2.meta_value = %s OR m2.meta_value = '' OR m2.meta_value IS NULL)
            LIMIT 1
        ";
        $row = $wpdb->get_var($wpdb->prepare(
            $sql,
            '_eh_linked_event_id',
            (string) $external_id,
            '_eh_linked_event_cpt',
            Settings::get_cpt_slug(),
            $external_type
        ));
        if ($row) {
            return (int) $row;
        }
        $row = $wpdb->get_var($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id AND m.meta_key = %s AND m.meta_value = %s WHERE p.post_type = %s AND p.post_status IN ('publish','future','draft','pending','private') LIMIT 1",
            '_eh_linked_event_id',
            (string) $external_id,
            Settings::get_cpt_slug()
        ));
        if ($row) {
            return (int) $row;
        }
        $slug = '';
        $external_post = get_post($external_id);
        if ($external_post && !empty($external_post->post_name)) {
            $slug = (string) $external_post->post_name;
        }
        if ($slug === '') {
            $slug = (string) $wpdb->get_var($wpdb->prepare("SELECT post_name FROM {$wpdb->posts} WHERE ID = %d", $external_id));
        }
        if ($slug !== '') {
            $row = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s LIMIT 1",
                Settings::get_cpt_slug(),
                $slug
            ));
            if ($row) {
                return (int) $row;
            }
        }
        return 0;
    }

    private function get_session_options(): array
    {
        $posts = get_posts([
            'post_type' => Settings::get_cpt_slug(),
            'numberposts' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        $opts = [];
        foreach ($posts as $post) {
            $opts[$post->ID] = $post->post_title;
        }
        return $opts;
    }

    private function inline_styles(): void
    {
        ?>
        <style>
        .eh-status-bar{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 18px;border:1px solid #e5e7eb;border-radius:12px;background:#fff;box-shadow:0 8px 20px rgba(15,23,42,.05)}
        @media(max-width:700px){.eh-status-bar{flex-direction:column;align-items:flex-start}}
        .eh-status__info{display:flex;flex-direction:column;gap:6px}
        .eh-status__title{font-size:18px;font-weight:700;color:#0f172a}
        .eh-status__meta{display:flex;flex-wrap:wrap;gap:10px;color:#475569;font-size:14px}
        .eh-status__cta{display:flex;align-items:center;gap:10px}
        .eh-btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 16px;background:#0ea5e9;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;transition:transform .2s ease,opacity .2s ease}
        .eh-btn:hover{opacity:.92;transform:translateY(-1px)}
        .eh-status__pill{display:inline-flex;align-items:center;padding:10px 14px;border-radius:8px;background:#f1f5f9;color:#475569;font-weight:600}
        .eh-badge{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;color:#fff;width:max-content}
        .eh-badge-open{background:#22c55e}
        .eh-badge-full{background:#c026d3}
        .eh-badge-closed{background:#64748b}
        .eh-badge-cancelled{background:#ef4444}
        </style>
        <?php
    }
}
