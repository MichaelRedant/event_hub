<?php
namespace EventHub\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use EventHub\Settings;
use WP_Query;

defined('ABSPATH') || exit;

class Widget_Session_List extends Widget_Base
{
    public function get_name(): string
    {
        return 'eventhub_session_list';
    }

    public function get_title(): string
    {
        return __('Eventlijst', 'event-hub');
    }

    public function get_icon(): string
    {
        return 'eicon-post-list';
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

        $this->add_control('posts_per_page', [
            'label' => __('Aantal events', 'event-hub'),
            'type' => Controls_Manager::NUMBER,
            'default' => 6,
            'min' => 1,
        ]);

        $this->add_control('language', [
            'label' => __('Taalfilter (bv. nl)', 'event-hub'),
            'type' => Controls_Manager::TEXT,
        ]);

        $terms = get_terms([
            'taxonomy' => Settings::get_tax_slug(),
            'hide_empty' => false,
        ]);
        $options = ['' => __('Alle types', 'event-hub')];
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $options[$term->slug] = $term->name;
            }
        }
        $this->add_control('session_type', [
            'label' => __('Eventtype', 'event-hub'),
            'type' => Controls_Manager::SELECT,
            'options' => $options,
            'default' => '',
        ]);

        $this->add_control('only_future', [
            'label' => __('Enkel toekomstige events', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'label_on' => __('Ja', 'event-hub'),
            'label_off' => __('Nee', 'event-hub'),
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->add_control('layout', [
            'label' => __('Lay-out', 'event-hub'),
            'type' => Controls_Manager::SELECT,
            'options' => [
                'list' => __('Lijst', 'event-hub'),
                'card' => __('Kaart', 'event-hub'),
            ],
            'default' => 'card',
        ]);

        $this->add_control('included_statuses', [
            'label' => __('Toon statussen', 'event-hub'),
            'type' => Controls_Manager::SELECT2,
            'multiple' => true,
            'options' => [
                'open' => __('Open', 'event-hub'),
                'full' => __('Volzet', 'event-hub'),
                'cancelled' => __('Geannuleerd', 'event-hub'),
                'closed' => __('Gesloten', 'event-hub'),
            ],
            'default' => ['open', 'full'],
        ]);

        $this->add_control('show_excerpt', [
            'label' => __('Toon samenvatting', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->add_control('show_location', [
            'label' => __('Toon locatie', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->add_control('show_price', [
            'label' => __('Toon prijs', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->add_control('show_ticket_note', [
            'label' => __('Toon ticketinfo', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->add_control('show_button', [
            'label' => __('Toon knop Meer info', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->add_control('show_search', [
            'label' => __('Toon zoekveld', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->add_control('show_availability', [
            'label' => __('Toon beschikbaarheidsbadge', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        $args = [
            'post_type' => Settings::get_cpt_slug(),
            'posts_per_page' => !empty($settings['posts_per_page']) ? (int) $settings['posts_per_page'] : 6,
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_key' => '_eh_date_start',
        ];

        $meta_query = [
            [
                'key' => '_eh_show_on_site',
                'value' => 1,
                'compare' => '=',
            ],
        ];

        if (!empty($settings['only_future']) && $settings['only_future'] === 'yes') {
            $meta_query[] = [
                'key' => '_eh_date_start',
                'value' => current_time('mysql'),
                'compare' => '>=',
                'type' => 'DATETIME',
            ];
        }

        if (!empty($settings['language'])) {
            $meta_query[] = [
                'key' => '_eh_language',
                'value' => sanitize_text_field((string) $settings['language']),
                'compare' => '=',
            ];
        }

        if ($meta_query) {
            $args['meta_query'] = $meta_query;
        }

        if (!empty($settings['included_statuses'])) {
            $statuses = array_map('sanitize_text_field', (array) $settings['included_statuses']);
            $statuses = array_filter($statuses);
            if ($statuses) {
                $args['meta_query'][] = [
                    'key' => '_eh_status',
                    'value' => $statuses,
                    'compare' => 'IN',
                ];
            }
        }

        if (!empty($settings['session_type'])) {
            $args['tax_query'] = [[
                'taxonomy' => Settings::get_tax_slug(),
                'field' => 'slug',
                'terms' => sanitize_text_field((string) $settings['session_type']),
            ]];
        }

        $search = isset($_GET['eh_q']) ? sanitize_text_field((string) $_GET['eh_q']) : '';
        if ($search !== '') {
            $args['s'] = $search;
        }

        $query = new WP_Query($args);

        if (!empty($settings['show_search']) && $settings['show_search'] === 'yes') {
            echo '<form method="get" class="eh-session-search">';
            echo '<input type="text" name="eh_q" placeholder="' . esc_attr__('Zoek een event...', 'event-hub') . '" value="' . esc_attr($search) . '" />';
            echo '<button type="submit">' . esc_html__('Filter', 'event-hub') . '</button>';
            echo '</form>';
        }

        $layout = !empty($settings['layout']) ? $settings['layout'] : 'card';
        echo '<div class="eh-session-list eh-layout-' . esc_attr($layout) . '">';
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $this->render_card(get_the_ID(), $settings);
            }
            wp_reset_postdata();
        } else {
            echo '<p>' . esc_html__('Geen komende events gevonden.', 'event-hub') . '</p>';
        }
        echo '</div>';

        $this->inline_styles();
    }

    private function render_card(int $post_id, array $settings): void
    {
        $title = get_the_title($post_id);
        $permalink = get_permalink($post_id);
        $excerpt = get_the_excerpt($post_id);
        $start = get_post_meta($post_id, '_eh_date_start', true);
        $location = get_post_meta($post_id, '_eh_location', true);
        $price = get_post_meta($post_id, '_eh_price', true);
        $ticket_note = get_post_meta($post_id, '_eh_ticket_note', true);
        $color = sanitize_hex_color((string) get_post_meta($post_id, '_eh_color', true)) ?: '#2271b1';
        $status = get_post_meta($post_id, '_eh_status', true) ?: 'open';

        [$capacity, $booked, $available, $is_full] = $this->get_capacity_state($post_id);
        $date_label = $start ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($start)) : '';

        $badge = $this->get_status_badge($status, $is_full);

        echo '<article class="eh-session-card">';
        if (!empty($settings['show_availability']) && $settings['show_availability'] === 'yes' && $badge) {
            echo '<span class="eh-badge ' . esc_attr($badge['class']) . '">' . esc_html($badge['label']) . '</span>';
        }
        echo '<h3><a href="' . esc_url($permalink) . '">' . esc_html($title) . '</a></h3>';
        if ($date_label) {
            echo '<div class="eh-meta">' . esc_html($date_label) . '</div>';
        }
        if (!empty($settings['show_location']) && $settings['show_location'] === 'yes' && $location) {
            echo '<div class="eh-meta">' . esc_html($location) . '</div>';
        }
        if (!empty($settings['show_price']) && $settings['show_price'] === 'yes' && $price !== '') {
            echo '<div class="eh-meta"><strong>' . esc_html__('Prijs', 'event-hub') . ':</strong> ' . esc_html($price) . '</div>';
        }
        if (!empty($settings['show_ticket_note']) && $settings['show_ticket_note'] === 'yes' && $ticket_note) {
            echo '<div class="eh-meta">' . wp_kses_post(nl2br($ticket_note)) . '</div>';
        }
        if (!empty($settings['show_availability']) && $settings['show_availability'] === 'yes' && $capacity > 0) {
            echo '<div class="eh-meta">' . esc_html(sprintf(_n('%d plaats beschikbaar', '%d plaatsen beschikbaar', $available, 'event-hub'), $available)) . '</div>';
        }
        if (!empty($settings['show_excerpt']) && $settings['show_excerpt'] === 'yes' && $excerpt) {
            echo '<p class="eh-excerpt">' . wp_kses_post($excerpt) . '</p>';
        }
        if (!empty($settings['show_button']) && $settings['show_button'] === 'yes') {
            echo '<a class="eh-btn" style="background:' . esc_attr($color) . ';" href="' . esc_url($permalink) . '">' . esc_html__('Meer informatie', 'event-hub') . '</a>';
        }
        echo '</article>';
    }

    /**
     * @return array{int,int,int,bool} [capacity, booked, available, is_full]
     */
    private function get_capacity_state(int $post_id): array
    {
        $capacity = (int) get_post_meta($post_id, '_eh_capacity', true);
        if ($capacity <= 0) {
            return [0, 0, 0, false];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'eh_session_registrations';
        $booked = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(people_count),0) FROM {$table} WHERE session_id = %d AND status IN ('registered','confirmed')",
                $post_id
            )
        );
        $available = max(0, $capacity - $booked);
        return [$capacity, $booked, $available, $available <= 0];
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
        return ['label' => __('Beschikbaar', 'event-hub'), 'class' => 'eh-badge-available'];
    }

    private function inline_styles(): void
    {
        echo '<style>
        .eh-session-search{margin-bottom:16px;display:flex;gap:8px}
        .eh-session-search input{flex:1;padding:8px;border:1px solid #ddd;border-radius:4px}
        .eh-session-search button{padding:8px 16px;border:0;background:#2271b1;color:#fff;border-radius:4px;cursor:pointer}
        .eh-session-list{display:grid;gap:16px}
        .eh-layout-card{grid-template-columns:repeat(auto-fill,minmax(260px,1fr))}
        .eh-layout-list{grid-template-columns:1fr}
        .eh-session-card{position:relative;border:1px solid #e0e0e0;border-radius:8px;padding:18px;box-shadow:0 2px 6px rgba(0,0,0,.03)}
        .eh-session-card h3{margin:0 0 8px;font-size:20px}
        .eh-session-card .eh-meta{color:#555;font-size:14px;margin-bottom:6px}
        .eh-session-card .eh-excerpt{margin:12px 0 16px}
        .eh-btn{display:inline-block;padding:8px 14px;border-radius:4px;color:#fff;text-decoration:none;font-weight:600}
        .eh-badge{position:absolute;top:14px;right:14px;padding:4px 10px;border-radius:4px;font-size:12px;color:#fff}
        .eh-badge-available{background:#2c9a3f}
        .eh-badge-full{background:#c62828}
        .eh-badge-closed{background:#666}
        .eh-badge-cancelled{background:#a0008f}
        </style>';
    }
}
