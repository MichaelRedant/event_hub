<?php
namespace EventHub;

defined('ABSPATH') || exit;

class Plugin
{
    /** @var CPT_Session */
    private $cpt_session;
    /** @var Registrations */
    private $registrations;
    /** @var Admin_Menus */
    private $admin_menus;
    /** @var Emails */
    private $emails;
    /** @var Settings */
    private $settings;
    /** @var Locale */
    private $locale;
    /** @var CPT_Email */
    private $cpt_email;

    public function __construct()
    {
        $this->cpt_session   = new CPT_Session();
        $this->registrations = new Registrations();
        $this->emails        = new Emails($this->registrations);
        $this->settings      = new Settings();
        $this->admin_menus   = new Admin_Menus($this->registrations, $this->emails, $this->settings);
        $this->locale        = new Locale();
        $this->cpt_email     = new CPT_Email();
    }

    public function init(): void
    {
        // Load i18n helpers (runtime Flemish translations)
        $this->locale->init();

        // Register CPT + Tax + Meta Boxes
        add_action('init', [$this->cpt_session, 'register_post_type']);
        add_action('init', [$this->cpt_session, 'register_taxonomies']);
        add_action('add_meta_boxes', [$this->cpt_session, 'register_meta_boxes']);
        add_action('save_post', [$this->cpt_session, 'save_meta_boxes']);
        add_action('admin_notices', [$this->cpt_session, 'maybe_notice_missing_templates']);
        add_filter('use_block_editor_for_post_type', [$this->cpt_session, 'disable_block_editor'], 10, 2);

        // Email templates CPT
        add_action('init', [$this->cpt_email, 'register_post_type']);
        add_action('add_meta_boxes', [$this->cpt_email, 'register_meta_boxes']);
        add_action('save_post', [$this->cpt_email, 'save_meta_boxes']);

        // Admin menus and pages
        add_action('admin_menu', [$this->admin_menus, 'register_menus']);
        add_action('admin_enqueue_scripts', [$this->admin_menus, 'enqueue_assets']);
        add_action('wp_ajax_event_hub_calendar_events', [$this->admin_menus, 'ajax_calendar_events']);

        // Settings page
        add_action('admin_init', [$this->settings, 'register_settings']);

        // Emails: hooks and cron actions
        $this->emails->init();

        // Elementor integration
        if (did_action('elementor/loaded')) {
            $this->maybe_bootstrap_elementor();
        } else {
            add_action('elementor/loaded', [$this, 'maybe_bootstrap_elementor']);
        }
    }

    public function maybe_bootstrap_elementor(): void
    {
        if (did_action('elementor/loaded')) {
            // Lazy-load Elementor integration class
            if (!class_exists('EventHub\\Elementor\\Elementor_Integration')) {
                $path = EVENT_HUB_PATH . 'includes/Elementor/Elementor_Integration.php';
                if (file_exists($path)) {
                    require_once $path;
                }
            }
            if (class_exists('EventHub\\Elementor\\Elementor_Integration')) {
                $integration = new \EventHub\Elementor\Elementor_Integration($this->registrations);
                $integration->init();
            }
        }
    }
}

