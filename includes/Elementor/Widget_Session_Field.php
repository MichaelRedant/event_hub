<?php
namespace EventHub\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

defined('ABSPATH') || exit;

class Widget_Session_Field extends Widget_Session_Detail
{
    public function get_name(): string
    {
        return 'eventhub_session_field';
    }

    public function get_title(): string
    {
        return __('Event veld', 'event-hub');
    }

    public function get_icon(): string
    {
        return 'eicon-editor-bold';
    }

    public function get_categories(): array
    {
        return ['event-hub'];
    }

    protected function register_controls(): void
    {
        $this->register_session_controls();

        $this->start_controls_section('section_field', [
            'label' => __('Veld', 'event-hub'),
        ]);

        $this->add_control('field_key', [
            'label' => __('Kies veld', 'event-hub'),
            'type' => Controls_Manager::SELECT,
            'default' => 'title',
            'options' => [
                'title' => __('Titel', 'event-hub'),
                'excerpt' => __('Samenvatting', 'event-hub'),
                'content' => __('Content', 'event-hub'),
                'date' => __('Datum', 'event-hub'),
                'time_range' => __('Tijd', 'event-hub'),
                'location' => __('Locatie', 'event-hub'),
                'address' => __('Adres', 'event-hub'),
                'online_link' => __('Online link', 'event-hub'),
                'status' => __('Status', 'event-hub'),
                'organizer' => __('Organisator', 'event-hub'),
                'staff' => __('Sprekers/medewerkers', 'event-hub'),
                'price' => __('Prijs', 'event-hub'),
                'ticket_note' => __('Ticketinfo', 'event-hub'),
                'capacity' => __('Capaciteit', 'event-hub'),
                'available' => __('Beschikbare plaatsen', 'event-hub'),
                'booking_window' => __('Boekingsperiode', 'event-hub'),
                'custom_meta' => __('Aangepaste meta', 'event-hub'),
            ],
        ]);

        $this->add_control('custom_meta_key', [
            'label' => __('Meta key', 'event-hub'),
            'type' => Controls_Manager::TEXT,
            'conditions' => [
                'terms' => [[
                    'name' => 'field_key',
                    'operator' => '==',
                    'value' => 'custom_meta',
                ]],
            ],
        ]);

        $this->add_control('prefix_text', [
            'label' => __('Prefix', 'event-hub'),
            'type' => Controls_Manager::TEXT,
        ]);

        $this->add_control('suffix_text', [
            'label' => __('Suffix', 'event-hub'),
            'type' => Controls_Manager::TEXT,
        ]);

        $this->add_control('fallback_text', [
            'label' => __('Fallback', 'event-hub'),
            'type' => Controls_Manager::TEXT,
            'description' => __('Wordt getoond als het veld leeg is.', 'event-hub'),
        ]);

        $this->add_control('html_tag', [
            'label' => __('HTML-tag', 'event-hub'),
            'type' => Controls_Manager::SELECT,
            'default' => 'div',
            'options' => [
                'div' => 'div',
                'span' => 'span',
                'p' => 'p',
                'h2' => 'h2',
                'h3' => 'h3',
                'h4' => 'h4',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_style', [
            'label' => __('Stijl', 'event-hub'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'typography',
            'selector' => '{{WRAPPER}} .eh-session-field',
        ]);

        $this->add_control('text_color', [
            'label' => __('Tekstkleur', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-field' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('spacing', [
            'label' => __('Marges', 'event-hub'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors' => [
                '{{WRAPPER}} .eh-session-field' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('status_style_heading', [
            'label' => __('Statusbadge', 'event-hub'),
            'type' => Controls_Manager::HEADING,
            'condition' => ['field_key' => 'status'],
        ]);

        $this->add_control('status_badge_text_color', [
            'label' => __('Badge tekstkleur', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'condition' => ['field_key' => 'status'],
            'selectors' => [
                '{{WRAPPER}} .eh-session-field-badge' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('status_badge_background', [
            'label' => __('Badge achtergrond', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'condition' => ['field_key' => 'status'],
            'selectors' => [
                '{{WRAPPER}} .eh-session-field-badge' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $session_id = $this->resolve_session_id($settings);
        if (!$session_id) {
            $this->render_no_session_notice();
            return;
        }

        $value = $this->get_field_value($session_id, $settings);
        if ($value === '' || $value === null) {
            if (!empty($settings['fallback_text'])) {
                $value = $settings['fallback_text'];
            } else {
                return;
            }
        }

        $prefix = !empty($settings['prefix_text']) ? $settings['prefix_text'] : '';
        $suffix = !empty($settings['suffix_text']) ? $settings['suffix_text'] : '';
        $tag = in_array($settings['html_tag'] ?? 'div', ['div', 'span', 'p', 'h2', 'h3', 'h4'], true) ? $settings['html_tag'] : 'div';
        $field = $settings['field_key'] ?? 'title';

        echo '<' . esc_attr($tag) . ' class="eh-session-field eh-field-' . esc_attr($field) . '">';
        echo wp_kses_post($prefix . $value . $suffix);
        echo '</' . esc_attr($tag) . '>';
    }

    private function get_field_value(int $session_id, array $settings)
    {
        $field = $settings['field_key'] ?? 'title';
        $post = get_post($session_id);
        if (!$post) {
            return '';
        }
        switch ($field) {
            case 'title':
                return esc_html(get_the_title($session_id));
            case 'excerpt':
                return wp_kses_post($post->post_excerpt ?: wp_trim_words($post->post_content, 40));
            case 'content':
                return apply_filters('the_content', $post->post_content);
            case 'date':
                $start = get_post_meta($session_id, '_eh_date_start', true);
                return $start ? esc_html(date_i18n(get_option('date_format'), strtotime($start))) : '';
            case 'time_range':
                $start = get_post_meta($session_id, '_eh_date_start', true);
                $end = get_post_meta($session_id, '_eh_date_end', true);
                if (!$start) {
                    return '';
                }
                $from = date_i18n(get_option('time_format'), strtotime($start));
                $to = $end ? date_i18n(get_option('time_format'), strtotime($end)) : '';
                return esc_html($to ? $from . ' - ' . $to : $from);
            case 'location':
                $is_online = (bool) get_post_meta($session_id, '_eh_is_online', true);
                if ($is_online) {
                    return '<span class="eh-session-field-badge status-online">' . esc_html__('Online', 'event-hub') . '</span>';
                }
                return esc_html(get_post_meta($session_id, '_eh_location', true));
            case 'address':
                return esc_html(get_post_meta($session_id, '_eh_address', true));
            case 'online_link':
                $link = get_post_meta($session_id, '_eh_online_link', true);
                if ($link) {
                    return '<a href="' . esc_url($link) . '" target="_blank" rel="noopener">' . esc_html($link) . '</a>';
                }
                return '';
            case 'status':
                $status = get_post_meta($session_id, '_eh_status', true) ?: 'open';
                $state = $this->registrations->get_capacity_state($session_id);
                $badge = $this->get_status_badge($status, $state['is_full']);
                if ($badge) {
                    return '<span class="eh-session-field-badge ' . esc_attr($badge['class']) . '">' . esc_html($badge['label']) . '</span>';
                }
                return '';
            case 'organizer':
                return esc_html(get_post_meta($session_id, '_eh_organizer', true));
            case 'staff':
                return esc_html(get_post_meta($session_id, '_eh_staff', true));
            case 'price':
                $price = get_post_meta($session_id, '_eh_price', true);
                return $price !== '' ? esc_html((string) $price) : '';
            case 'ticket_note':
                $note = get_post_meta($session_id, '_eh_ticket_note', true);
                return $note ? wp_kses_post(nl2br($note)) : '';
            case 'capacity':
                $state = $this->registrations->get_capacity_state($session_id);
                return $state['capacity'] > 0 ? esc_html((string) $state['capacity']) : esc_html__('Onbeperkt', 'event-hub');
            case 'available':
                $state = $this->registrations->get_capacity_state($session_id);
                if ($state['capacity'] <= 0) {
                    return esc_html__('Onbeperkt', 'event-hub');
                }
                return esc_html(sprintf(_n('%d plaats beschikbaar', '%d plaatsen beschikbaar', $state['available'], 'event-hub'), $state['available']));
            case 'booking_window':
                $open = get_post_meta($session_id, '_eh_booking_open', true);
                $close = get_post_meta($session_id, '_eh_booking_close', true);
                $parts = [];
                if ($open) {
                    $parts[] = sprintf(__('Vanaf %s', 'event-hub'), date_i18n(get_option('date_format'), strtotime($open)));
                }
                if ($close) {
                    $parts[] = sprintf(__('Tot %s', 'event-hub'), date_i18n(get_option('date_format'), strtotime($close)));
                }
                return esc_html(implode(' · ', $parts));
            case 'custom_meta':
                $key = $settings['custom_meta_key'] ?? '';
                if (!$key) {
                    return '';
                }
                $value = get_post_meta($session_id, $key, true);
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                return esc_html((string) $value);
        }
        return '';
    }
}
