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
    }

    public function register_widgets(\Elementor\Widgets_Manager $widgets_manager): void
    {
        require_once EVENT_HUB_PATH . 'includes/Elementor/Widget_Session_List.php';
        require_once EVENT_HUB_PATH . 'includes/Elementor/Widget_Session_Detail.php';

        $widgets_manager->register(new Widget_Session_List());
        $widgets_manager->register(new Widget_Session_Detail($this->registrations));
    }
}
