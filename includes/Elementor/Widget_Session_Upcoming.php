<?php
namespace EventHub\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use EventHub\Registrations;
use EventHub\Settings;
use WP_Query;

defined('ABSPATH') || exit;

class Widget_Session_Upcoming extends Widget_Base
{
    protected Registrations $registrations;

    public function __construct($data = [], $args = null)
    {
        parent::__construct($data, $args);
        $this->registrations = new Registrations();
    }

    public function get_name(): string
    {
        return 'eventhub_session_upcoming';
    }

    public function get_title(): string
    {
        return __('Upcoming events (grid)', 'event-hub');
    }

    public function get_icon(): string
    {
        return 'eicon-posts-grid';
    }

    public function get_categories(): array
    {
        return ['event-hub'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('section_query', [
            'label' => __('Query', 'event-hub'),
        ]);

        $this->add_control('posts_per_page', [
            'label' => __('Aantal events', 'event-hub'),
            'type' => Controls_Manager::NUMBER,
            'default' => 6,
            'min' => 1,
            'max' => 50,
        ]);

        $this->add_control('filter_linked_current', [
            'label' => __('Filter op gekoppeld event (huidige post)', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
            'description' => __('Op externe single (bv. JetEngine) tonen we enkel gekoppelde events.', 'event-hub'),
        ]);

        $this->add_control('only_future', [
            'label' => __('Enkel toekomstige events', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        // Tax filter
        $terms = get_terms([
            'taxonomy' => Settings::get_tax_slug(),
            'hide_empty' => false,
        ]);
        $tax_options = ['' => __('Alle types', 'event-hub')];
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $tax_options[$term->slug] = $term->name;
            }
        }
        $this->add_control('session_type', [
            'label' => __('Eventtype', 'event-hub'),
            'type' => Controls_Manager::SELECT,
            'options' => $tax_options,
            'default' => '',
        ]);

        $this->add_control('location_filter', [
            'label' => __('Locatie bevat', 'event-hub'),
            'type' => Controls_Manager::TEXT,
            'placeholder' => __('bv. Gent, online', 'event-hub'),
        ]);

        $colleague_options = [];
        $colleagues = Settings::get_general()['colleagues'] ?? [];
        foreach ($colleagues as $idx => $col) {
            $name = trim(($col['first_name'] ?? '') . ' ' . ($col['last_name'] ?? ''));
            $colleague_options[(string) $idx] = $name ?: sprintf(__('Collega %d', 'event-hub'), $idx + 1);
        }
        $this->add_control('team_filter', [
            'label' => __('Teamlid geselecteerd', 'event-hub'),
            'type' => Controls_Manager::SELECT2,
            'options' => $colleague_options,
            'multiple' => true,
            'label_block' => true,
            'description' => __('Filter events waar deze collega’s gekoppeld zijn.', 'event-hub'),
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_layout', [
            'label' => __('Layout', 'event-hub'),
        ]);
        $this->add_control('layout', [
            'label' => __('Weergave', 'event-hub'),
            'type' => Controls_Manager::CHOOSE,
            'options' => [
                'grid' => [
                    'title' => __('Grid', 'event-hub'),
                    'icon' => 'eicon-posts-grid',
                ],
                'list' => [
                    'title' => __('Lijst', 'event-hub'),
                    'icon' => 'eicon-menu-bar',
                ],
            ],
            'default' => 'grid',
            'toggle' => false,
        ]);
        $this->add_responsive_control('columns', [
            'label' => __('Kolommen', 'event-hub'),
            'type' => Controls_Manager::NUMBER,
            'min' => 1,
            'max' => 4,
            'default' => 3,
            'condition' => ['layout' => 'grid'],
        ]);
        $this->add_control('show_excerpt', [
            'label' => __('Toon korte beschrijving', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);
        $this->add_control('cta_label', [
            'label' => __('CTA-label', 'event-hub'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Inschrijven', 'event-hub'),
        ]);
        $this->end_controls_section();

        $this->start_controls_section('section_style', [
            'label' => __('Kaarten', 'event-hub'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'title_typo',
            'selector' => '{{WRAPPER}} .eh-upcoming-card__title',
        ]);
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'card_border',
            'selector' => '{{WRAPPER}} .eh-upcoming-card',
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'card_shadow',
            'selector' => '{{WRAPPER}} .eh-upcoming-card',
        ]);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $cpt = Settings::get_cpt_slug();
        $per_page = isset($settings['posts_per_page']) ? (int) $settings['posts_per_page'] : 6;
        $only_future = !empty($settings['only_future']) && $settings['only_future'] === 'yes';

        $meta_query = [];
        $now = current_time('mysql');
        if ($only_future) {
            $meta_query[] = [
                'key' => '_eh_date_start',
                'value' => $now,
                'compare' => '>=',
                'type' => 'DATETIME',
            ];
        }
        if (!empty($settings['location_filter'])) {
            $meta_query[] = [
                'key' => '_eh_location',
                'value' => sanitize_text_field((string) $settings['location_filter']),
                'compare' => 'LIKE',
            ];
        }
        if (!empty($settings['team_filter']) && is_array($settings['team_filter'])) {
            foreach ($settings['team_filter'] as $col_id) {
                $meta_query[] = [
                    'key' => '_eh_colleagues',
                    'value' => '"' . intval($col_id) . '"',
                    'compare' => 'LIKE',
                ];
            }
        }
        $linked_meta = $this->get_linked_meta_query($settings);
        if ($linked_meta) {
            $meta_query = array_merge($meta_query, $linked_meta);
        }
        if (count($meta_query) > 1) {
            $meta_query['relation'] = 'AND';
        }

        $tax_query = [];
        if (!empty($settings['session_type'])) {
            $tax_query[] = [
                'taxonomy' => Settings::get_tax_slug(),
                'field' => 'slug',
                'terms' => $settings['session_type'],
            ];
        }

        $query = new WP_Query([
            'post_type' => $cpt,
            'posts_per_page' => $per_page,
            'meta_key' => '_eh_date_start',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_type' => 'DATETIME',
            'meta_query' => $meta_query,
            'tax_query' => $tax_query,
            'post_status' => 'publish',
        ]);

        if (!$query->have_posts()) {
            echo '<div class="eh-alert notice">' . esc_html__('Geen events gevonden.', 'event-hub') . '</div>';
            return;
        }

        $layout = $settings['layout'] ?? 'grid';
        $columns = isset($settings['columns']) ? max(1, (int) $settings['columns']) : 3;
        $show_excerpt = !empty($settings['show_excerpt']) && $settings['show_excerpt'] === 'yes';
        $cta_label = !empty($settings['cta_label']) ? $settings['cta_label'] : __('Inschrijven', 'event-hub');

        $wrapper_classes = ['eh-upcoming', 'eh-upcoming--' . $layout];
        $style_attr = '';
        if ($layout === 'grid') {
            $style_attr = 'style="--eh-upcoming-cols:' . esc_attr((string) $columns) . ';"';
        }

        echo '<div class="' . esc_attr(implode(' ', $wrapper_classes)) . '" ' . $style_attr . '>';

        while ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();
            $badge = $this->get_status_badge($id);
            $link = get_permalink($id);
            $date_start = get_post_meta($id, '_eh_date_start', true);
            $date_label = $date_start ? date_i18n(get_option('date_format'), strtotime($date_start)) : '';
            $time_label = $date_start ? date_i18n(get_option('time_format'), strtotime($date_start)) : '';
            $location = get_post_meta($id, '_eh_location', true);
            $thumb = get_the_post_thumbnail_url($id, 'large');
            $available_label = $this->get_available_label($id);

            echo '<article class="eh-upcoming-card">';
            if ($thumb) {
                echo '<a class="eh-upcoming-card__media" href="' . esc_url($link) . '"><span style="background-image:url(' . esc_url($thumb) . ');"></span></a>';
            }
            echo '<div class="eh-upcoming-card__body">';
            if ($badge) {
                echo '<span class="eh-badge ' . esc_attr($badge['class']) . '">' . esc_html($badge['label']) . '</span>';
            }
            echo '<h3 class="eh-upcoming-card__title"><a href="' . esc_url($link) . '">' . esc_html(get_the_title()) . '</a></h3>';
            echo '<div class="eh-upcoming-card__meta">';
            if ($date_label) {
                echo '<span>' . esc_html($date_label . ($time_label ? ' • ' . $time_label : '')) . '</span>';
            }
            if ($location) {
                echo '<span>' . esc_html($location) . '</span>';
            }
            if ($available_label) {
                echo '<span class="eh-upcoming-card__availability">' . esc_html($available_label) . '</span>';
            }
            echo '</div>';
            if ($show_excerpt) {
                echo '<p class="eh-upcoming-card__excerpt">' . esc_html(wp_trim_words(get_the_excerpt(), 22)) . '</p>';
            }
            echo '<div class="eh-upcoming-card__actions">';
            echo '<a class="eh-btn" href="' . esc_url($link) . '">' . esc_html($cta_label) . '</a>';
            echo '</div>';
            echo '</div>';
            echo '</article>';
        }

        echo '</div>';
        wp_reset_postdata();
        $this->inline_styles();
    }

    private function get_linked_meta_query(array $settings): array
    {
        if (empty($settings['filter_linked_current']) || $settings['filter_linked_current'] !== 'yes') {
            return [];
        }
        $cpt = Settings::get_cpt_slug();
        $candidate = get_queried_object_id();
        if (!$candidate) {
            $candidate = get_the_ID();
        }
        if (!$candidate) {
            return [];
        }
        $candidate_type = get_post_type($candidate);
        if (!$candidate_type || $candidate_type === $cpt) {
            return [];
        }
        return [
            [
                'key' => '_eh_linked_event_cpt',
                'value' => $candidate_type,
            ],
            [
                'key' => '_eh_linked_event_id',
                'value' => (string) $candidate,
                'compare' => '=',
            ],
        ];
    }

    private function get_status_badge(int $post_id): ?array
    {
        $status = get_post_meta($post_id, '_eh_status', true) ?: 'open';
        $state = $this->registrations->get_capacity_state($post_id);
        if ($status === 'cancelled') {
            return ['label' => __('Geannuleerd', 'event-hub'), 'class' => 'eh-badge-cancelled'];
        }
        if ($status === 'closed') {
            return ['label' => __('Gesloten', 'event-hub'), 'class' => 'eh-badge-closed'];
        }
        if ($status === 'full' || $state['is_full']) {
            return ['label' => __('Wachtlijst', 'event-hub'), 'class' => 'eh-badge-full'];
        }
        return ['label' => __('Beschikbaar', 'event-hub'), 'class' => 'eh-badge-open'];
    }

    private function get_available_label(int $post_id): string
    {
        $state = $this->registrations->get_capacity_state($post_id);
        if (empty($state['capacity'])) {
            return '';
        }
        return sprintf(_n('%d plaats beschikbaar', '%d plaatsen beschikbaar', $state['available'], 'event-hub'), $state['available']);
    }

    protected function inline_styles(): void
    {
        ?>
        <style>
        .eh-upcoming{display:grid;gap:20px}
        .eh-upcoming--grid{grid-template-columns:repeat(var(--eh-upcoming-cols,3),minmax(0,1fr))}
        @media (max-width:960px){.eh-upcoming--grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media (max-width:640px){.eh-upcoming--grid{grid-template-columns:1fr}}
        .eh-upcoming--list{grid-template-columns:1fr}
        .eh-upcoming-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 10px 30px rgba(15,23,42,.05)}
        .eh-upcoming-card__media{display:block;position:relative;padding-top:56%;overflow:hidden}
        .eh-upcoming-card__media span{position:absolute;inset:0;background-size:cover;background-position:center;transition:transform .3s ease}
        .eh-upcoming-card__media:hover span{transform:scale(1.03)}
        .eh-upcoming-card__body{padding:16px;display:flex;flex-direction:column;gap:10px}
        .eh-upcoming-card__title{margin:0;font-size:18px;line-height:1.3}
        .eh-upcoming-card__title a{text-decoration:none;color:#0f172a}
        .eh-upcoming-card__meta{display:flex;flex-wrap:wrap;gap:8px;font-size:14px;color:#475569}
        .eh-upcoming-card__excerpt{margin:0;font-size:14px;color:#475569}
        .eh-upcoming-card__actions{margin-top:auto}
        .eh-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 16px;background:var(--eh-accent,#2271b1);color:#fff;border-radius:8px;text-decoration:none;font-weight:600;transition:transform .2s ease,opacity .2s ease}
        .eh-btn:hover{opacity:.92;transform:translateY(-1px)}
        .eh-badge{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;color:#fff;background:#0ea5e9;width:max-content}
        .eh-badge-open{background:#16a34a}
        .eh-badge-full{background:#c026d3}
        .eh-badge-closed{background:#64748b}
        .eh-badge-cancelled{background:#ef4444}
        .eh-upcoming-card__availability{font-weight:600;color:#0f172a}
        </style>
        <?php
    }
}
