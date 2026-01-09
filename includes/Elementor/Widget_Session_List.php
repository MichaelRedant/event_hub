<?php
namespace EventHub\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
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
        return ['event-hub'];
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

        $this->add_control('filter_linked_current', [
            'label' => __('Filter op gekoppeld event (huidige post)', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
            'description' => __('Op externe single (bv. JetEngine) tonen we enkel gekoppelde events.', 'event-hub'),
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

        $this->add_control('respect_show_on_site', [
            'label' => __('Filter op "toon op site"', 'event-hub'),
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

        $this->add_control('button_text', [
            'label' => __('Knoplabel', 'event-hub'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Meer informatie', 'event-hub'),
            'label_block' => true,
            'condition' => ['show_button' => 'yes'],
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

        $this->add_control('empty_state_text', [
            'label' => __('Tekst geen resultaten', 'event-hub'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Geen komende events gevonden.', 'event-hub'),
            'label_block' => true,
        ]);

        $this->end_controls_section();

        $this->register_layout_styles();
        $this->register_card_styles();
        $this->register_typography_styles();
        $this->register_button_styles();
        $this->register_badge_styles();
        $this->register_search_styles();
    }

    private function register_layout_styles(): void
    {
        $this->start_controls_section('section_layout_style', [
            'label' => __('Lay-out', 'event-hub'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('card_columns', [
            'label' => __('Kolommen (kaartlay-out)', 'event-hub'),
            'type' => Controls_Manager::SLIDER,
            'range' => [
                'px' => [
                    'min' => 1,
                    'max' => 4,
                    'step' => 1,
                ],
            ],
            'selectors' => [
                '{{WRAPPER}} .eh-session-list.eh-layout-card' => 'grid-template-columns: repeat({{SIZE}}, minmax(0,1fr));',
            ],
            'condition' => [
                'layout' => 'card',
            ],
        ]);

        $this->add_responsive_control('card_gap', [
            'label' => __('Afstand tussen items', 'event-hub'),
            'type' => Controls_Manager::SLIDER,
            'range' => [
                'px' => [
                    'min' => 0,
                    'max' => 80,
                ],
            ],
            'selectors' => [
                '{{WRAPPER}} .eh-session-list' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('card_alignment', [
            'label' => __('Tekstuitlijning', 'event-hub'),
            'type' => Controls_Manager::CHOOSE,
            'options' => [
                'left' => [
                    'title' => __('Links', 'event-hub'),
                    'icon' => 'eicon-text-align-left',
                ],
                'center' => [
                    'title' => __('Centreren', 'event-hub'),
                    'icon' => 'eicon-text-align-center',
                ],
                'right' => [
                    'title' => __('Rechts', 'event-hub'),
                    'icon' => 'eicon-text-align-right',
                ],
            ],
            'selectors' => [
                '{{WRAPPER}} .eh-session-card' => 'text-align: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    private function register_card_styles(): void
    {
        $this->start_controls_section('section_card_style', [
            'label' => __('Kaarten', 'event-hub'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        if (class_exists(Group_Control_Background::class)) {
            $this->add_group_control(Group_Control_Background::get_type(), [
                'name' => 'card_background',
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .eh-session-card',
            ]);
        }

        if (class_exists(Group_Control_Border::class)) {
            $this->add_group_control(Group_Control_Border::get_type(), [
                'name' => 'card_border',
                'selector' => '{{WRAPPER}} .eh-session-card',
            ]);
        }

        $this->add_responsive_control('card_border_radius', [
            'label' => __('Hoekradius', 'event-hub'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range' => [
                'px' => [
                    'min' => 0,
                    'max' => 60,
                ],
                '%' => [
                    'min' => 0,
                    'max' => 50,
                ],
            ],
            'selectors' => [
                '{{WRAPPER}} .eh-session-card' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('card_padding', [
            'label' => __('Padding', 'event-hub'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors' => [
                '{{WRAPPER}} .eh-session-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        if (class_exists(Group_Control_Box_Shadow::class)) {
            $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
                'name' => 'card_box_shadow',
                'selector' => '{{WRAPPER}} .eh-session-card',
            ]);
        }

        $this->end_controls_section();
    }

    private function register_typography_styles(): void
    {
        $this->start_controls_section('section_title_style', [
            'label' => __('Titels', 'event-hub'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        if (class_exists(Group_Control_Typography::class)) {
            $this->add_group_control(Group_Control_Typography::get_type(), [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .eh-session-card h3, {{WRAPPER}} .eh-session-card h3 a',
            ]);
        }

        $this->add_control('title_color', [
            'label' => __('Tekstkleur', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-card h3, {{WRAPPER}} .eh-session-card h3 a' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_meta_style', [
            'label' => __('Meta & tekst', 'event-hub'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        if (class_exists(Group_Control_Typography::class)) {
            $this->add_group_control(Group_Control_Typography::get_type(), [
                'name' => 'meta_typography',
                'selector' => '{{WRAPPER}} .eh-session-card .eh-meta, {{WRAPPER}} .eh-session-card .eh-excerpt',
            ]);
        }

        $this->add_control('meta_color', [
            'label' => __('Metakleur', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-card .eh-meta' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('excerpt_color', [
            'label' => __('Samenvattingskleur', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-card .eh-excerpt' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    private function register_button_styles(): void
    {
        $this->start_controls_section('section_button_style', [
            'label' => __('Knop', 'event-hub'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => [
                'show_button' => 'yes',
            ],
        ]);

        if (class_exists(Group_Control_Typography::class)) {
            $this->add_group_control(Group_Control_Typography::get_type(), [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .eh-session-card .eh-btn',
            ]);
        }

        $this->start_controls_tabs('tabs_button_colors');

        $this->start_controls_tab('tab_button_normal', [
            'label' => __('Normaal', 'event-hub'),
        ]);

        $this->add_control('button_text_color', [
            'label' => __('Tekst', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-card .eh-btn' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('button_background_color', [
            'label' => __('Achtergrond', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-card .eh-btn' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();

        $this->start_controls_tab('tab_button_hover', [
            'label' => __('Hover', 'event-hub'),
        ]);

        $this->add_control('button_text_color_hover', [
            'label' => __('Tekst', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-card .eh-btn:hover' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('button_background_color_hover', [
            'label' => __('Achtergrond', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-card .eh-btn:hover' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_responsive_control('button_border_radius', [
            'label' => __('Hoekradius', 'event-hub'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'selectors' => [
                '{{WRAPPER}} .eh-session-card .eh-btn' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('button_padding', [
            'label' => __('Padding', 'event-hub'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors' => [
                '{{WRAPPER}} .eh-session-card .eh-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    private function register_badge_styles(): void
    {
        $this->start_controls_section('section_badge_style', [
            'label' => __('Badges', 'event-hub'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        if (class_exists(Group_Control_Typography::class)) {
            $this->add_group_control(Group_Control_Typography::get_type(), [
                'name' => 'badge_typography',
                'selector' => '{{WRAPPER}} .eh-session-card .eh-badge',
            ]);
        }

        $this->add_control('badge_corner', [
            'label' => __('Hoekradius', 'event-hub'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'selectors' => [
                '{{WRAPPER}} .eh-session-card .eh-badge' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('badge_padding', [
            'label' => __('Padding', 'event-hub'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors' => [
                '{{WRAPPER}} .eh-session-card .eh-badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('badge_available_color', [
            'label' => __('Kleur beschikbaar', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-card .eh-badge-available' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('badge_full_color', [
            'label' => __('Kleur volzet', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-card .eh-badge-full' => 'background-color: {{VALUE}};',
                '{{WRAPPER}} .eh-session-card .eh-waitlist' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('badge_closed_color', [
            'label' => __('Kleur gesloten', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-card .eh-badge-closed' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('badge_cancelled_color', [
            'label' => __('Kleur geannuleerd', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-card .eh-badge-cancelled' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    private function register_search_styles(): void
    {
        $this->start_controls_section('section_search_style', [
            'label' => __('Zoek/filter', 'event-hub'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => [
                'show_search' => 'yes',
            ],
        ]);

        if (class_exists(Group_Control_Typography::class)) {
            $this->add_group_control(Group_Control_Typography::get_type(), [
                'name' => 'search_typography',
                'selector' => '{{WRAPPER}} .eh-session-search input, {{WRAPPER}} .eh-session-search button',
            ]);
        }

        $this->add_control('search_input_color', [
            'label' => __('Tekstkleur veld', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-search input' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('search_input_background', [
            'label' => __('Achtergrond veld', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-search input' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('search_input_border_color', [
            'label' => __('Rand veld', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-search input' => 'border-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('search_button_background', [
            'label' => __('Knop achtergrond', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-search button' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('search_button_color', [
            'label' => __('Knop tekst', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-search button' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        wp_enqueue_style('event-hub-frontend-style');
        wp_enqueue_script('event-hub-frontend');

        $args = [
            'post_type' => Settings::get_cpt_slug(),
            'posts_per_page' => !empty($settings['posts_per_page']) ? (int) $settings['posts_per_page'] : 6,
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_key' => '_eh_date_start',
        ];

        $meta_query = [];
        if (!empty($settings['respect_show_on_site']) && $settings['respect_show_on_site'] === 'yes') {
            $meta_query[] = [
                'key' => '_eh_show_on_site',
                'value' => 1,
                'compare' => '=',
            ];
        }

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

        $linked_meta = $this->get_linked_meta_query($settings);
        if ($linked_meta) {
            $meta_query = array_merge($meta_query, $linked_meta);
        }

        if ($meta_query) {
            $args['meta_query'] = $meta_query;
        }

        if (!empty($settings['included_statuses'])) {
            $statuses = array_map('sanitize_text_field', (array) $settings['included_statuses']);
            $statuses = array_filter($statuses);
            if ($statuses) {
                if (empty($args['meta_query'])) {
                    $args['meta_query'] = [];
                }
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
            $empty = !empty($settings['empty_state_text']) ? $settings['empty_state_text'] : __('Geen komende events gevonden.', 'event-hub');
            echo '<p class="eh-empty-state">' . esc_html($empty) . '</p>';
        }
        echo '</div>';

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

        [$capacity, $booked, $available, $is_full, $waitlist] = $this->get_capacity_state($post_id);
        $date_label = $start ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($start)) : '';

        $badge = $this->get_status_badge($status, $is_full);
        $colleagues = $this->get_colleagues_for_session($post_id);

        echo '<article class="eh-session-card" data-eventhub-session="' . esc_attr((string) $post_id) . '">';
        if (!empty($settings['show_availability']) && $settings['show_availability'] === 'yes' && $badge) {
            echo '<span class="eh-badge ' . esc_attr($badge['class']) . '" data-eventhub-status>' . esc_html($badge['label']) . '</span>';
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
        if ($colleagues) {
            $names = array_map('esc_html', array_map('trim', $colleagues));
            echo '<div class="eh-meta eh-colleagues-meta">' . esc_html__('Aanwezig:', 'event-hub') . ' ' . implode(', ', $names) . '</div>';
        }
        if (!empty($settings['show_availability']) && $settings['show_availability'] === 'yes' && $capacity > 0) {
            $availability_label = sprintf(_n('%d plaats beschikbaar', '%d plaatsen beschikbaar', $available, 'event-hub'), $available);
            echo '<div class="eh-meta eh-availability" data-eventhub-availability>' . esc_html($availability_label) . '</div>';
            if ($is_full && $waitlist > 0) {
                $wait_label = sprintf(_n('%d persoon op de wachtlijst', '%d personen op de wachtlijst', $waitlist, 'event-hub'), $waitlist);
                echo '<div class="eh-meta eh-waitlist" data-eventhub-waitlist>' . esc_html($wait_label) . '</div>';
            }
        }
        if (!empty($settings['show_excerpt']) && $settings['show_excerpt'] === 'yes' && $excerpt) {
            echo '<p class="eh-excerpt">' . wp_kses_post($excerpt) . '</p>';
        }
        if (!empty($settings['show_button']) && $settings['show_button'] === 'yes') {
            $button_label = !empty($settings['button_text']) ? $settings['button_text'] : __('Meer informatie', 'event-hub');
            echo '<a class="eh-btn" data-eventhub-button data-eventhub-open="' . esc_attr((string) $post_id) . '" style="background:' . esc_attr($color) . ';" href="' . esc_url($permalink) . '">' . esc_html($button_label) . '</a>';
        }
        echo '</article>';
    }

    /**
     * @return array{int,int,int,bool,int} [capacity, booked, available, is_full, waitlist]
     */
    private function get_capacity_state(int $post_id): array
    {
        $capacity = (int) get_post_meta($post_id, '_eh_capacity', true);
        if ($capacity <= 0) {
            return [0, 0, 0, false, 0];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'eh_session_registrations';
        $booked = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(people_count),0) FROM {$table} WHERE session_id = %d AND status IN ('registered','confirmed')",
                $post_id
            )
        );
        $waitlist = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(people_count),0) FROM {$table} WHERE session_id = %d AND status = %s",
                $post_id,
                'waitlist'
            )
        );
        $available = max(0, $capacity - $booked);
        return [$capacity, $booked, $available, $available <= 0, $waitlist];
    }

    /**
     * Fetch selected colleagues (names) for a session.
     *
     * @return array<int,string>
     */
    private function get_colleagues_for_session(int $post_id): array
    {
        $ids = get_post_meta($post_id, '_eh_colleagues', true);
        if (!is_array($ids)) {
            return [];
        }
        $ids = array_map('intval', $ids);
        if (!$ids) {
            return [];
        }
        $global = \EventHub\Settings::get_general();
        $all = isset($global['colleagues']) && is_array($global['colleagues']) ? $global['colleagues'] : [];
        $names = [];
        foreach ($ids as $cid) {
            if (isset($all[$cid])) {
                $names[] = trim(($all[$cid]['first_name'] ?? '') . ' ' . ($all[$cid]['last_name'] ?? ''));
            }
        }
        return array_filter($names);
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
        .eh-session-search{margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap}
        .eh-session-search input{flex:1 1 220px;padding:10px 14px;border:1px solid #ddd;border-radius:4px;background:#fff}
        .eh-session-search button{padding:10px 18px;border:0;background:#2271b1;color:#fff;border-radius:4px;cursor:pointer;font-weight:600;transition:background-color .2s ease}
        .eh-session-search button:hover{background:#1c5b8c}
        .eh-session-list{display:grid;gap:16px}
        .eh-layout-card{grid-template-columns:repeat(auto-fill,minmax(260px,1fr))}
        .eh-layout-list{grid-template-columns:1fr}
        .eh-session-card{position:relative;border:1px solid #e0e0e0;border-radius:8px;padding:18px;box-shadow:0 2px 6px rgba(0,0,0,.03);background:#fff;display:flex;flex-direction:column;gap:8px}
        .eh-session-card h3{margin:0;font-size:20px}
        .eh-session-card .eh-meta{color:#555;font-size:14px;margin-bottom:6px}
        .eh-session-card .eh-excerpt{margin:12px 0 16px}
        .eh-session-card .eh-btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 16px;border-radius:4px;color:#fff;text-decoration:none;font-weight:600;transition:transform .2s ease,opacity .2s ease}
        .eh-session-card .eh-waitlist{color:#a35b07;font-weight:700}
        .eh-session-card .eh-btn:hover{opacity:.9;transform:translateY(-1px)}
        .eh-session-card .eh-btn.is-disabled{opacity:.5;pointer-events:none}
        .eh-badge{position:absolute;top:14px;right:14px;padding:4px 10px;border-radius:4px;font-size:12px;color:#fff}
        .eh-badge-available{background:#2c9a3f}
        .eh-badge-full{background:#c62828}
        .eh-badge-waitlist{background:#8a6df1}
        .eh-badge-closed{background:#666}
        .eh-badge-cancelled{background:#a0008f}
        .eh-empty-state{text-align:center;padding:24px;color:#6d6d6d;background:#fafafa;border-radius:6px;border:1px dashed #e1e1e1}
        </style>';
    }
}
