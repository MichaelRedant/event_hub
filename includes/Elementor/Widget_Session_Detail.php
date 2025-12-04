<?php
namespace EventHub\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use EventHub\Registrations;
use EventHub\Settings;

defined('ABSPATH') || exit;

class Widget_Session_Detail extends Widget_Base
{
    protected static ?Registrations $registrations_service = null;
    protected Registrations $registrations;
    protected ?string $preview_message = null;

    public static function set_registrations_service(Registrations $registrations): void
    {
        self::$registrations_service = $registrations;
    }

    public function __construct(array $data = [], array $args = null)
    {
        parent::__construct($data, $args);
        $this->registrations = self::$registrations_service ?: new Registrations();
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
        return ['event-hub'];
    }

    protected function register_controls(): void
    {
        $this->register_session_controls();
        $this->register_form_settings_controls();
        $this->register_wrapper_styles();
        $this->register_header_styles();
        $this->register_meta_styles();
        $this->register_alert_styles();
        $this->register_form_styles();
        $this->register_submit_styles();
    }

    protected function register_session_controls(): void
    {
        $this->start_controls_section('section_content', [
            'label' => __('Eventbron & onderdelen', 'event-hub'),
        ]);

        $this->add_control('detect_current', [
            'label' => __('Gebruik huidig event', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
            'description' => __('Handig voor single event templates. Voor andere pagina’s kies je hieronder zelf een event ter preview.', 'event-hub'),
        ]);

        $this->add_control('session_id', [
            'label' => __('Fallback / preview event', 'event-hub'),
            'type' => Controls_Manager::SELECT2,
            'options' => $this->get_session_options(),
            'multiple' => false,
            'label_block' => true,
            'description' => __('Wordt gebruikt wanneer Elementor geen huidig event kan detecteren (bv. op een gewone pagina).', 'event-hub'),
        ]);

        $this->add_control('show_details', [
            'label' => __('Toon eventdetails', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->add_control('show_form', [
            'label' => __('Toon inschrijfformulier', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->end_controls_section();
    }

    protected function register_form_settings_controls(): void
    {
        $this->start_controls_section('section_form_content', [
            'label' => __('Formulier', 'event-hub'),
        ]);

        $this->add_control('form_heading', [
            'label' => __('Formuliertitel', 'event-hub'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Inschrijven', 'event-hub'),
            'label_block' => true,
        ]);

        $this->add_control('submit_label', [
            'label' => __('Knoplabel', 'event-hub'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Inschrijven', 'event-hub'),
            'label_block' => true,
        ]);

        $this->add_control('success_message', [
            'label' => __('Succesbericht', 'event-hub'),
            'type' => Controls_Manager::TEXTAREA,
            'rows' => 3,
            'default' => __('Bedankt! We hebben je inschrijving ontvangen.', 'event-hub'),
        ]);

        $this->add_control('show_marketing_optin', [
            'label' => __('Marketing opt-in veld', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->end_controls_section();
    }

    protected function get_session_options(): array
    {
        static $options = null;
        if ($options !== null) {
            return $options;
        }
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
        return $options;
    }

    protected function register_wrapper_styles(): void
    {
        $this->start_controls_section('section_wrapper_style', [
            'label' => __('Container', 'event-hub'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        if (class_exists(Group_Control_Background::class)) {
            $this->add_group_control(Group_Control_Background::get_type(), [
                'name' => 'detail_background',
                'selector' => '{{WRAPPER}} .eh-session-detail',
                'types' => ['classic', 'gradient'],
            ]);
        }

        if (class_exists(Group_Control_Border::class)) {
            $this->add_group_control(Group_Control_Border::get_type(), [
                'name' => 'detail_border',
                'selector' => '{{WRAPPER}} .eh-session-detail',
            ]);
        }

        $this->add_responsive_control('detail_border_radius', [
            'label' => __('Hoekradius', 'event-hub'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range' => [
                'px' => ['min' => 0, 'max' => 80],
                '%' => ['min' => 0, 'max' => 50],
            ],
            'selectors' => [
                '{{WRAPPER}} .eh-session-detail' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('detail_padding', [
            'label' => __('Padding', 'event-hub'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors' => [
                '{{WRAPPER}} .eh-session-detail' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        if (class_exists(Group_Control_Box_Shadow::class)) {
            $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
                'name' => 'detail_box_shadow',
                'selector' => '{{WRAPPER}} .eh-session-detail',
            ]);
        }

        $this->end_controls_section();
    }

    protected function register_header_styles(): void
    {
        $this->start_controls_section('section_header_style', [
            'label' => __('Titels', 'event-hub'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        if (class_exists(Group_Control_Typography::class)) {
            $this->add_group_control(Group_Control_Typography::get_type(), [
                'name' => 'header_typography',
                'selector' => '{{WRAPPER}} .eh-session-detail__title',
            ]);
        }

        $this->add_control('header_color', [
            'label' => __('Kleur', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-detail__title' => 'color: {{VALUE}};',
            ],
        ]);

        if (class_exists(Group_Control_Typography::class)) {
            $this->add_group_control(Group_Control_Typography::get_type(), [
                'name' => 'form_header_typography',
                'selector' => '{{WRAPPER}} .eh-session-form__title',
            ]);
        }

        $this->add_control('form_header_color', [
            'label' => __('Formuliertitel kleur', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-form__title' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    protected function register_meta_styles(): void
    {
        $this->start_controls_section('section_meta_style', [
            'label' => __('Meta & inhoud', 'event-hub'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        if (class_exists(Group_Control_Typography::class)) {
            $this->add_group_control(Group_Control_Typography::get_type(), [
                'name' => 'meta_typography',
                'selector' => '{{WRAPPER}} .eh-session-detail__meta .eh-meta',
            ]);
        }

        $this->add_control('meta_color', [
            'label' => __('Metakleur', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-detail__meta .eh-meta' => 'color: {{VALUE}};',
            ],
        ]);

        if (class_exists(Group_Control_Typography::class)) {
            $this->add_group_control(Group_Control_Typography::get_type(), [
                'name' => 'content_typography',
                'selector' => '{{WRAPPER}} .eh-session-detail__content',
            ]);
        }

        $this->add_control('content_color', [
            'label' => __('Inhoudskleur', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-detail__content' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    protected function register_alert_styles(): void
    {
        $this->start_controls_section('section_alert_style', [
            'label' => __('Alerts', 'event-hub'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        if (class_exists(Group_Control_Typography::class)) {
            $this->add_group_control(Group_Control_Typography::get_type(), [
                'name' => 'alert_typography',
                'selector' => '{{WRAPPER}} .eh-alert',
            ]);
        }

        $this->add_control('alert_success_background', [
            'label' => __('Succes achtergrond', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-alert.success' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('alert_success_color', [
            'label' => __('Succes tekst', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-alert.success' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('alert_error_background', [
            'label' => __('Fout achtergrond', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-alert.error' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('alert_error_color', [
            'label' => __('Fout tekst', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-alert.error' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('alert_notice_background', [
            'label' => __('Info achtergrond', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-alert.notice' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('alert_notice_color', [
            'label' => __('Info tekst', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-alert.notice' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    protected function register_form_styles(): void
    {
        $this->start_controls_section('section_form_style', [
            'label' => __('Formuliervelden', 'event-hub'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('form_columns_gap', [
            'label' => __('Kolomafstand', 'event-hub'),
            'type' => Controls_Manager::SLIDER,
            'range' => [
                'px' => ['min' => 0, 'max' => 40],
            ],
            'selectors' => [
                '{{WRAPPER}} .eh-form .eh-grid' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        if (class_exists(Group_Control_Typography::class)) {
            $this->add_group_control(Group_Control_Typography::get_type(), [
                'name' => 'field_typography',
                'selector' => '{{WRAPPER}} .eh-form input',
            ]);
        }

        $this->add_control('field_text_color', [
            'label' => __('Tekst kleur', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-form input' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('field_background_color', [
            'label' => __('Achtergrond', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-form input' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('field_border_color', [
            'label' => __('Randkleur', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-form input' => 'border-color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('field_border_radius', [
            'label' => __('Hoekradius', 'event-hub'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'selectors' => [
                '{{WRAPPER}} .eh-form input' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    protected function register_submit_styles(): void
    {
        $this->start_controls_section('section_submit_style', [
            'label' => __('Verzendknop', 'event-hub'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        if (class_exists(Group_Control_Typography::class)) {
            $this->add_group_control(Group_Control_Typography::get_type(), [
                'name' => 'submit_typography',
                'selector' => '{{WRAPPER}} .eh-session-form .eh-btn',
            ]);
        }

        $this->start_controls_tabs('tabs_submit_state');

        $this->start_controls_tab('tab_submit_normal', [
            'label' => __('Normaal', 'event-hub'),
        ]);

        $this->add_control('submit_text_color', [
            'label' => __('Tekst', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-form .eh-btn' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('submit_background_color', [
            'label' => __('Achtergrond', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-form .eh-btn' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();

        $this->start_controls_tab('tab_submit_hover', [
            'label' => __('Hover', 'event-hub'),
        ]);

        $this->add_control('submit_text_color_hover', [
            'label' => __('Tekst', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-form .eh-btn:hover' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('submit_background_color_hover', [
            'label' => __('Achtergrond', 'event-hub'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-session-form .eh-btn:hover' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_responsive_control('submit_border_radius', [
            'label' => __('Hoekradius', 'event-hub'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'selectors' => [
                '{{WRAPPER}} .eh-session-form .eh-btn' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('submit_padding', [
            'label' => __('Padding', 'event-hub'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors' => [
                '{{WRAPPER}} .eh-session-form .eh-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $show_details = !empty($settings['show_details']) && $settings['show_details'] === 'yes';
        $show_form = !empty($settings['show_form']) && $settings['show_form'] === 'yes';

        if (!$show_details && !$show_form) {
            echo '<div class="eh-alert notice">' . esc_html__('Schakel minstens één onderdeel in (details of formulier).', 'event-hub') . '</div>';
            return;
        }

        $session_id = $this->resolve_session_id($settings);
        if (!$session_id) {
            $this->render_no_session_notice();
            return;
        }

        $accent = $this->get_accent_color($session_id);

        if ($this->preview_message && $this->is_editor_mode()) {
            echo '<div class="eh-alert notice">' . esc_html($this->preview_message) . '</div>';
        }

        echo '<div class="eh-session-detail" style="--eh-accent:' . esc_attr($accent) . ';">';
        if ($show_details) {
            $this->render_details($session_id);
        }
        if ($show_form && $module_enabled) {
            $this->render_registration_form($session_id, $settings);
        } elseif ($show_form && !$module_enabled) {
            echo '<div class="eh-alert notice">' . esc_html__('Inschrijvingen voor dit event verlopen extern.', 'event-hub') . '</div>';
        }
        echo '</div>';
        $this->inline_styles();
    }

    protected function resolve_session_id(array $settings): int
    {
        $this->preview_message = null;
        $cpt = Settings::get_cpt_slug();
        $use_detect = !empty($settings['detect_current']) && $settings['detect_current'] === 'yes';

        if ($use_detect) {
            $candidate = get_queried_object_id();
            if (!$candidate) {
                $candidate = get_the_ID();
            }
            if ($candidate && get_post_type($candidate) === $cpt) {
                return (int) $candidate;
            }
        }

        if (!empty($settings['session_id'])) {
            $manual = (int) $settings['session_id'];
            if ($manual && get_post_type($manual) === $cpt) {
                if ($use_detect && $this->is_editor_mode()) {
                    $this->preview_message = __('Preview gebruikt het gekozen fallback-event.', 'event-hub');
                }
                return $manual;
            }
        }

        if ($this->is_editor_mode()) {
            $fallback = $this->get_first_session_id();
            if ($fallback) {
                $this->preview_message = __('Preview toont het meest recente event.', 'event-hub');
                return $fallback;
            }
        }

        return 0;
    }

    protected function get_accent_color(int $session_id): string
    {
        $color = sanitize_hex_color((string) get_post_meta($session_id, '_eh_color', true));
        return $color ?: '#2271b1';
    }


    protected function render_details(int $session_id): void
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
        $colleagues_meta = get_post_meta($session_id, '_eh_colleagues', true);
        $colleagues_ids = is_array($colleagues_meta) ? array_map('intval', $colleagues_meta) : [];
        $colleague_names = [];
        if ($colleagues_ids) {
            $global = Settings::get_general();
            $all = isset($global['colleagues']) && is_array($global['colleagues']) ? $global['colleagues'] : [];
            foreach ($colleagues_ids as $cid) {
                if (isset($all[$cid])) {
                    $colleague_names[] = trim(($all[$cid]['first_name'] ?? '') . ' ' . ($all[$cid]['last_name'] ?? ''));
                }
            }
        }
        $price = get_post_meta($session_id, '_eh_price', true);
        $ticket_note = get_post_meta($session_id, '_eh_ticket_note', true);
        $no_show_fee = get_post_meta($session_id, '_eh_no_show_fee', true);
        $status = get_post_meta($session_id, '_eh_status', true) ?: 'open';
        $enable_module_meta = get_post_meta($session_id, '_eh_enable_module', true);
        $module_enabled = ($enable_module_meta === '') ? true : (bool) $enable_module_meta;

        $state = $this->registrations->get_capacity_state($session_id);
        $capacity = $state['capacity'];
        $available = $state['available'];
        $is_full = $state['is_full'];

        $date_label = $start ? date_i18n(get_option('date_format'), strtotime($start)) : '';
        $time_start = $start ? date_i18n(get_option('time_format'), strtotime($start)) : '';
        $time_end = $end ? date_i18n(get_option('time_format'), strtotime($end)) : '';

        $badge = $this->get_status_badge($status, $is_full);

        echo '<div class="eh-session-detail__header">';
        echo '<h2 class="eh-session-detail__title">' . esc_html($title) . '</h2>';
        if ($badge) {
            echo '<span class="eh-badge ' . esc_attr($badge['class']) . '">' . esc_html($badge['label']) . '</span>';
        }
        echo '</div>';

        $meta_output = [];
        if ($date_label) {
            $time_range = $time_end ? $time_start . ' - ' . $time_end : $time_start;
            $meta_output[] = esc_html($time_range ? $date_label . ' | ' . $time_range : $date_label);
        }
        if ($is_online && $online_link) {
            $meta_output[] = '<a href="' . esc_url($online_link) . '" target="_blank" rel="noopener">' . esc_html__('Deelnamelink', 'event-hub') . '</a>';
        } elseif ($location) {
            $meta_output[] = esc_html($location);
        }
        if ($address) {
            $meta_output[] = esc_html($address);
        }
        if ($organizer) {
            $meta_output[] = esc_html(sprintf(__('Organisator: %s', 'event-hub'), $organizer));
        }
        if ($staff) {
            $meta_output[] = esc_html(sprintf(__('Sprekers/medewerkers: %s', 'event-hub'), $staff));
        }
        if ($colleague_names) {
            $meta_output[] = esc_html(sprintf(__('Aanwezig: %s', 'event-hub'), implode(', ', $colleague_names)));
        }
        if ($colleague_names) {
            $meta_output[] = esc_html(sprintf(__('Aanwezig: %s', 'event-hub'), implode(', ', $colleague_names)));
        }
        $pricing_lines = [];
        if ($price !== '') {
            $pricing_lines[] = sprintf(__('Prijs: %s', 'event-hub'), $price);
        }
        if ($no_show_fee !== '') {
            $pricing_lines[] = sprintf(__('No-showkost: %s', 'event-hub'), $no_show_fee);
        }
        if ($pricing_lines) {
            $meta_output[] = esc_html(implode(' | ', $pricing_lines));
        }
        if ($ticket_note) {
            $meta_output[] = wp_kses_post(nl2br($ticket_note));
        }
        if ($capacity > 0) {
            $meta_output[] = '<strong>' . esc_html__('Beschikbaarheden', 'event-hub') . ':</strong> ' . esc_html(sprintf(_n('%d plaats', '%d plaatsen', $available, 'event-hub'), $available));
        }

        if ($meta_output) {
            echo '<div class="eh-session-detail__meta">';
            foreach ($meta_output as $line) {
                echo '<div class="eh-meta">' . $line . '</div>';
            }
            echo '</div>';
        }

        if ($content) {
            echo '<div class="eh-session-detail__content">' . $content . '</div>';
        }
    }


    protected function render_registration_form(int $session_id, array $settings): void
    {
        $status = get_post_meta($session_id, '_eh_status', true) ?: 'open';
        $state = $this->registrations->get_capacity_state($session_id);
        $capacity = $state['capacity'];
        $is_full = $state['is_full'];
        $booking_open = get_post_meta($session_id, '_eh_booking_open', true);
        $booking_close = get_post_meta($session_id, '_eh_booking_close', true);
        $event_start = get_post_meta($session_id, '_eh_date_start', true);
        $now = current_time('timestamp');

        $before_window = $booking_open && $now < strtotime($booking_open);
        $after_window = false;
        $after_window_reason = '';
        if ($booking_close && $now > strtotime($booking_close)) {
            $after_window = true;
            $after_window_reason = 'close_date';
        } elseif (!$booking_close && $event_start) {
            $event_start_ts = strtotime($event_start);
            if ($event_start_ts) {
                $event_day_cutoff = strtotime(date('Y-m-d 00:00:00', $event_start_ts));
                if ($event_day_cutoff && $now >= $event_day_cutoff) {
                    $after_window = true;
                    $after_window_reason = 'event_day';
                }
            }
        }

        $form_heading = !empty($settings['form_heading']) ? $settings['form_heading'] : __('Inschrijven', 'event-hub');
        $button_label = !empty($settings['submit_label']) ? $settings['submit_label'] : __('Inschrijven', 'event-hub');
        $success_message_text = !empty($settings['success_message']) ? $settings['success_message'] : __('Bedankt! We hebben je inschrijving ontvangen.', 'event-hub');
        $show_marketing = !empty($settings['show_marketing_optin']) && $settings['show_marketing_optin'] === 'yes';
        $hide_fields = $this->get_form_hide_fields($session_id);

        wp_enqueue_script('event-hub-frontend');

        echo '<div class="eh-session-form" data-event-hub-form="1">';
        if (!empty($form_heading)) {
            echo '<h3 class="eh-session-form__title">' . esc_html($form_heading) . '</h3>';
        }

        $is_active_status = in_array($status, ['open', 'full'], true);
        $waitlist_mode = $is_active_status && !$before_window && !$after_window && $is_full;

        if ((!$is_active_status && !$waitlist_mode) || $before_window || ($after_window && !$waitlist_mode)) {
            if ($status === 'cancelled') {
                $alert = __('Dit event werd geannuleerd.', 'event-hub');
                $class = 'error';
            } elseif ($status === 'closed') {
                $alert = __('Inschrijvingen zijn gesloten.', 'event-hub');
                $class = 'error';
            } elseif ($is_full) {
                $alert = __('Dit event is volzet.', 'event-hub');
                $class = 'notice';
            } elseif ($before_window) {
                $alert = __('De inschrijvingen zijn nog niet geopend.', 'event-hub');
                $class = 'notice';
            } elseif ($after_window_reason === 'event_day') {
                $alert = __('De inschrijvingen sloten op de dag van het event.', 'event-hub');
                $class = 'notice';
            } else {
                $alert = __('De inschrijvingen zijn afgesloten.', 'event-hub');
                $class = 'notice';
            }
            echo '<div class="eh-alert ' . esc_attr($class) . '">' . esc_html($alert) . '</div>';
            echo '</div>';
            return;
        }
        if ($waitlist_mode) {
            echo '<div class="eh-alert notice">' . esc_html__('Dit event is volzet. Vul je gegevens in om op de wachtlijst te komen.', 'event-hub') . '</div>';
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
                'waitlist_opt_in' => $waitlist_mode ? 1 : 0,
                'extra' => isset($_POST['extra']) && is_array($_POST['extra']) ? $_POST['extra'] : [],
            ];
            $result = $this->registrations->create_registration($data);
            if (is_wp_error($result)) {
                $error = $result->get_error_message();
            } else {
                $created = $this->registrations->get_registration($result);
                if ($created && ($created['status'] ?? '') === 'waitlist') {
                    $message = __('Bedankt! Je staat nu op de wachtlijst.', 'event-hub');
                } else {
                    $message = $success_message_text;
                }
            }
        }

        if ($message) {
            echo '<div class="eh-alert success">' . wp_kses_post($message) . '</div>';
            echo '</div>';
            return;
        }
        if ($error) {
            echo '<div class="eh-alert error">' . esc_html($error) . '</div>';
        }

        echo '<form method="post" class="eh-form" data-ehevent="' . esc_attr((string) $session_id) . '">';
        wp_nonce_field('eh_register_' . $session_id, 'eh_register_nonce');
        echo '<input type="hidden" name="session_id" value="' . esc_attr((string) $session_id) . '" />';
        if ($waitlist_mode) {
            echo '<input type="hidden" name="waitlist_opt_in" value="1" />';
        }
        echo '<div class="eh-grid">';
        echo $this->input('first_name', __('Voornaam', 'event-hub'), true);
        echo $this->input('last_name', __('Familienaam', 'event-hub'), true);
        echo $this->input('email', __('E-mailadres', 'event-hub'), true, 'email');
        if (!in_array('phone', $hide_fields, true)) {
            echo $this->input('phone', __('Telefoon', 'event-hub'));
        }
        if (!in_array('company', $hide_fields, true)) {
            echo $this->input('company', __('Bedrijf', 'event-hub'));
        }
        if (!in_array('vat', $hide_fields, true)) {
            echo $this->input('vat', __('BTW-nummer', 'event-hub'));
        }
        if (!in_array('role', $hide_fields, true)) {
            echo $this->input('role', __('Rol', 'event-hub'));
        }
        if (!in_array('people_count', $hide_fields, true)) {
            echo $this->number('people_count', __('Aantal personen', 'event-hub'), $capacity > 0 ? $capacity : 99);
        }
        $extra_fields = $this->registrations->get_extra_fields($session_id);
        foreach ($extra_fields as $field) {
            $slug = $field['slug'];
            $label = $field['label'];
            $required_attr = $field['required'] ? 'required' : '';
            $name = 'extra[' . $slug . ']';
            $id = 'extra_' . $slug;
            echo '<div class="field">';
            echo '<label for="' . esc_attr($id) . '">' . esc_html($label . ($field['required'] ? ' *' : '')) . '</label>';
            if ($field['type'] === 'textarea') {
                $val = isset($_POST['extra'][$slug]) ? wp_kses_post((string) $_POST['extra'][$slug]) : '';
                echo '<textarea name="' . esc_attr($name) . '" id="' . esc_attr($id) . '" rows="3" ' . $required_attr . '>' . esc_textarea($val) . '</textarea>';
            } elseif ($field['type'] === 'select') {
                $val = isset($_POST['extra'][$slug]) ? sanitize_text_field((string) $_POST['extra'][$slug]) : '';
                echo '<select name="' . esc_attr($name) . '" id="' . esc_attr($id) . '" ' . $required_attr . '>';
                echo '<option value="">' . esc_html__('Maak een keuze', 'event-hub') . '</option>';
                foreach ($field['options'] as $opt) {
                    echo '<option value="' . esc_attr($opt) . '"' . selected($val, $opt, false) . '>' . esc_html($opt) . '</option>';
                }
                echo '</select>';
            } else {
                $val = isset($_POST['extra'][$slug]) ? sanitize_text_field((string) $_POST['extra'][$slug]) : '';
                echo '<input type="text" name="' . esc_attr($name) . '" id="' . esc_attr($id) . '" value="' . esc_attr($val) . '" ' . $required_attr . ' />';
            }
            echo '</div>';
        }
        if ($show_marketing && !in_array('marketing', $hide_fields, true)) {
            $checked = isset($_POST['consent_marketing']) ? ' checked' : '';
            echo '<div class="field full checkbox"><label><input type="checkbox" name="consent_marketing" value="1"' . $checked . ' /> ' . esc_html__('Ik wil marketingupdates ontvangen', 'event-hub') . '</label></div>';
        }
        echo '</div>';

        $this->render_captcha_fields();

        $submit_label = $waitlist_mode ? __('Op wachtlijst plaatsen', 'event-hub') : $button_label;
        echo '<button type="submit" class="eh-btn">' . esc_html($submit_label) . '</button>';
        echo '</form>';
        echo '</div>';
    }

    protected function render_no_session_notice(): void
    {
        $message = __('Geen event geselecteerd. Kies een event of activeer "Gebruik huidig event".', 'event-hub');
        if ($this->is_editor_mode()) {
            $message .= ' ' . __('Tip: kies onderaan een fallback event om de widget in de builder te previewen.', 'event-hub');
        }
        echo '<div class="eh-alert notice">' . esc_html($message) . '</div>';
    }

    protected function is_editor_mode(): bool
    {
        if (class_exists('\Elementor\Plugin')) {
            return (bool) \Elementor\Plugin::$instance->editor->is_edit_mode();
        }
        return false;
    }

    protected function get_first_session_id(): int
    {
        $args = [
            'post_type' => Settings::get_cpt_slug(),
            'numberposts' => 1,
            'meta_key' => '_eh_date_start',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_type' => 'DATETIME',
        ];
        $posts = get_posts($args);
        if (!$posts) {
            $posts = get_posts([
                'post_type' => Settings::get_cpt_slug(),
                'numberposts' => 1,
                'orderby' => 'date',
                'order' => 'DESC',
            ]);
        }
        if (!$posts) {
            return 0;
        }
        return (int) $posts[0]->ID;
    }

    protected function input(string $name, string $label, bool $required = false, string $type = 'text'): string
    {
        $value = isset($_POST[$name]) ? sanitize_text_field((string) $_POST[$name]) : '';
        $req = $required ? ' required' : '';
        $asterisk = $required ? ' *' : '';
        return '<div class="field"><label for="' . esc_attr($name) . '">' . esc_html($label . $asterisk) . '</label><input type="' . esc_attr($type) . '" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . $req . ' /></div>';
    }

    protected function number(string $name, string $label, int $max): string
    {
        $value = isset($_POST[$name]) ? (int) $_POST[$name] : 1;
        $value = max(1, $value);
        return '<div class="field"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label><input type="number" min="1" max="' . esc_attr((string) max(1, $max)) . '" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" /></div>';
    }

    /**
     * Fields that should be hidden for this session.
     *
     * @return array<int,string>
     */
    protected function get_form_hide_fields(int $session_id): array
    {
        $meta = get_post_meta($session_id, '_eh_form_hide_fields', true);
        return is_array($meta) ? array_map('sanitize_key', $meta) : [];
    }

    protected function render_captcha_fields(): void
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

    protected function get_status_badge(string $status, bool $is_full): ?array
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

    protected function inline_styles(): void
    {
        echo '<style>
        .eh-session-detail{border:1px solid #e6e6e6;border-radius:12px;padding:28px;background:#fff;box-shadow:0 10px 30px rgba(20,20,20,.04)}
        .eh-session-detail__header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px}
        .eh-session-detail__title{margin:0;font-size:28px;line-height:1.2}
        .eh-session-detail__meta{display:flex;flex-direction:column;gap:6px;margin-bottom:24px}
        .eh-session-detail__meta .eh-meta{color:#555;font-size:15px}
        .eh-session-detail__meta .eh-meta a{color:var(--eh-accent,#2271b1);text-decoration:none}
        .eh-session-detail__content{margin-bottom:24px}
        .eh-session-detail .eh-badge{display:inline-flex;align-items:center;padding:6px 14px;border-radius:999px;font-size:12px;color:#fff;background:var(--eh-accent,#2271b1);font-weight:600}
        .eh-badge-full{background:#c62828}
        .eh-badge-cancelled{background:#8e24aa}
        .eh-badge-closed{background:#555}
        .eh-session-form{border-top:1px solid #f0f0f0;padding-top:24px;margin-top:12px}
        .eh-session-form__title{margin:0 0 18px;font-size:22px;color:var(--eh-accent,#2271b1)}
        .eh-alert{padding:14px 18px;border-radius:8px;margin-bottom:16px;border:1px solid transparent;font-size:15px}
        .eh-alert.success{background:#e7f7ec;border-color:#b0e2bf}
        .eh-alert.error{background:#fdecea;border-color:#f5c0bc}
        .eh-alert.notice{background:#fff4e5;border-color:#f2cb99}
        .eh-form .eh-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:12px}
        .eh-form .field label{display:block;font-weight:600;margin-bottom:4px}
        .eh-form input[type=text],.eh-form input[type=email],.eh-form input[type=number]{width:100%;padding:11px;border:1px solid #dcdcdc;border-radius:6px;background:#fff;font-size:15px}
        .eh-form .field.full{grid-column:1/-1}
        .eh-form .field.checkbox label{font-weight:500;gap:10px;display:flex;align-items:flex-start}
        .eh-form .field.checkbox input{width:auto;margin-top:3px}
        .eh-session-form .eh-btn{background:var(--eh-accent,#2271b1);color:#fff;border:0;padding:12px 26px;border-radius:6px;cursor:pointer;font-size:16px;font-weight:600;transition:opacity .2s ease,transform .2s ease}
        .eh-session-form .eh-btn:hover{opacity:.9;transform:translateY(-1px)}
        .eh-session-form .eh-form-feedback{margin-bottom:12px}
        .eh-session-form.eh-form--loading .eh-btn{opacity:.5;pointer-events:none}
        </style>';
    }
}
