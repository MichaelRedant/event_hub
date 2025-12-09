<?php
namespace EventHub\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

defined('ABSPATH') || exit;

class Widget_Calendar extends Widget_Base
{
    public function get_name(): string
    {
        return 'event_hub_calendar';
    }

    public function get_title(): string
    {
        return __('Event Hub - Kalender', 'event-hub');
    }

    public function get_icon(): string
    {
        return 'eicon-calendar';
    }

    public function get_categories(): array
    {
        return ['event-hub'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('section_content', [
            'label' => __('Kalender', 'event-hub'),
        ]);

        $this->add_control('initial_view', [
            'label'   => __('Startweergave', 'event-hub'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'dayGridMonth',
            'options' => [
                'dayGridMonth' => __('Maand', 'event-hub'),
                'timeGridWeek' => __('Week (tijd)', 'event-hub'),
                'listWeek'     => __('Weeklijst', 'event-hub'),
            ],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $view = isset($settings['initial_view']) ? $settings['initial_view'] : 'dayGridMonth';
        $allowed = ['dayGridMonth', 'timeGridWeek', 'listWeek'];
        if (!in_array($view, $allowed, true)) {
            $view = 'dayGridMonth';
        }

        $fc_css = file_exists(EVENT_HUB_PATH . 'assets/vendor/fullcalendar/main.min.css')
            ? EVENT_HUB_URL . 'assets/vendor/fullcalendar/main.min.css'
            : 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.css';
        $fc_js = file_exists(EVENT_HUB_PATH . 'assets/vendor/fullcalendar/index.global.min.js')
            ? EVENT_HUB_URL . 'assets/vendor/fullcalendar/index.global.min.js'
            : 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js';

        wp_enqueue_style('event-hub-fullcalendar', $fc_css, [], EVENT_HUB_VERSION);
        wp_enqueue_script('event-hub-fullcalendar', $fc_js, [], EVENT_HUB_VERSION, true);
        wp_enqueue_script(
            'event-hub-frontend-calendar',
            EVENT_HUB_URL . 'assets/js/frontend-calendar.js',
            ['event-hub-fullcalendar'],
            EVENT_HUB_VERSION,
            true
        );
        wp_localize_script('event-hub-frontend-calendar', 'EventHubPublicCalendar', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'view'    => $view,
            'labels'  => [
                'error' => __('Er ging iets mis bij het laden van events.', 'event-hub'),
            ],
        ]);

        $id = 'eh-public-calendar-' . wp_generate_password(6, false, false);
        echo '<div class="eh-public-calendar-wrap"><div id="' . esc_attr($id) . '" class="eh-public-calendar" data-initial-view="' . esc_attr($view) . '"></div></div>';
    }
}
