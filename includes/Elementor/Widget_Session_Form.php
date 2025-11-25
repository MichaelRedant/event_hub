<?php
namespace EventHub\Elementor;

use Elementor\Controls_Manager;

defined('ABSPATH') || exit;

class Widget_Session_Form extends Widget_Session_Detail
{

    public function get_name(): string
    {
        return 'eventhub_session_form';
    }

    public function get_title(): string
    {
        return __('Inschrijvingsformulier', 'event-hub');
    }

    public function get_icon(): string
    {
        return 'eicon-form-horizontal';
    }

    public function get_categories(): array
    {
        return ['event-hub'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('section_content', [
            'label' => __('Eventbron', 'event-hub'),
        ]);

        $this->add_control('detect_current', [
            'label' => __('Gebruik huidig event', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->add_control('session_id', [
            'label' => __('Fallback / preview event', 'event-hub'),
            'type' => Controls_Manager::SELECT2,
            'options' => $this->get_session_options(),
            'multiple' => false,
            'label_block' => true,
        ]);

        $this->end_controls_section();

        $this->register_form_settings_controls();
        $this->register_alert_styles();
        $this->register_form_styles();
        $this->register_submit_styles();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        // Force form visibility in this widget context.
        $settings['show_details'] = 'no';
        $settings['show_form'] = 'yes';

        $session_id = $this->resolve_session_id($settings);
        if (!$session_id) {
            $this->render_no_session_notice();
            return;
        }

        if ($this->preview_message && $this->is_editor_mode()) {
            echo '<div class="eh-alert notice">' . esc_html($this->preview_message) . '</div>';
        }

        $accent = $this->get_accent_color($session_id);
        echo '<div class="eh-session-detail eh-session-form-only" style="--eh-accent:' . esc_attr($accent) . ';">';
        $this->render_registration_form($session_id, $settings);
        echo '</div>';
        $this->inline_styles();
    }
}
