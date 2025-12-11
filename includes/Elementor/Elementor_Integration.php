<?php
namespace EventHub\Elementor;

use EventHub\Registrations;

defined('ABSPATH') || exit;

class Elementor_Integration
{
    private Registrations $registrations;

    public function __construct(Registrations $registrations)
    {
        $this->registrations = $registrations;
    }

    public function init(): void
    {
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
        add_action('elementor/elements/categories_registered', [$this, 'register_category']);
    }

    public function register_widgets(\Elementor\Widgets_Manager $widgets_manager): void
    {
        require_once EVENT_HUB_PATH . 'includes/Elementor/Widget_Session_List.php';
        require_once EVENT_HUB_PATH . 'includes/Elementor/Widget_Session_Upcoming.php';
        require_once EVENT_HUB_PATH . 'includes/Elementor/Widget_Session_Detail.php';
        require_once EVENT_HUB_PATH . 'includes/Elementor/Widget_Session_Form.php';
        require_once EVENT_HUB_PATH . 'includes/Elementor/Widget_Session_Field.php';
        require_once EVENT_HUB_PATH . 'includes/Elementor/Widget_Calendar.php';
        require_once EVENT_HUB_PATH . 'includes/Elementor/Widget_Session_Slider.php';
        require_once EVENT_HUB_PATH . 'includes/Elementor/Widget_Colleagues.php';
        require_once EVENT_HUB_PATH . 'includes/Elementor/Widget_Session_Agenda.php';
        require_once EVENT_HUB_PATH . 'includes/Elementor/Widget_Session_Status.php';
        require_once EVENT_HUB_PATH . 'includes/Elementor/Widget_Staff_Portal.php';

        Widget_Session_Detail::set_registrations_service($this->registrations);
        Widget_Session_Form::set_registrations_service($this->registrations);
        Widget_Session_Field::set_registrations_service($this->registrations);

        $widgets_manager->register(new Widget_Session_List());
        $widgets_manager->register(new Widget_Session_Upcoming());
        $widgets_manager->register(new Widget_Session_Detail());
        $widgets_manager->register(new Widget_Session_Form());
        $widgets_manager->register(new Widget_Session_Field());
        $widgets_manager->register(new Widget_Calendar());
        $widgets_manager->register(new Widget_Session_Slider());
        $widgets_manager->register(new Widget_Colleagues());
        $widgets_manager->register(new Widget_Session_Agenda());
        $widgets_manager->register(new Widget_Session_Status());
        $widgets_manager->register(new Widget_Staff_Portal());
    }

    public function register_category(\Elementor\Elements_Manager $elements_manager): void
    {
        $elements_manager->add_category('event-hub', [
            'title' => __('Event Hub', 'event-hub'),
            'icon' => 'fa fa-calendar',
        ]);
    }
}
