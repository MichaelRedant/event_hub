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
        $this->registrations = new Registrations();
        $this->emails        = new Emails($this->registrations);
        $this->cpt_session   = new CPT_Session($this->registrations, $this->emails);
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
        add_action('admin_notices', [$this->cpt_session, 'maybe_notice_bulk_email_result']);
        add_filter('use_block_editor_for_post_type', [$this->cpt_session, 'disable_block_editor'], 10, 2);
        add_action('admin_post_event_hub_send_bulk_email', [$this->cpt_session, 'handle_bulk_email_action']);
        add_filter('post_row_actions', [$this->cpt_session, 'add_dashboard_row_action'], 10, 2);
        add_filter('redirect_post_location', [$this->cpt_session, 'keep_edit_redirect'], 100, 2);
        add_filter('wp_redirect', [$this->cpt_session, 'intercept_wp_redirect'], 100, 2);
        add_action('admin_print_footer_scripts', [$this->cpt_session, 'force_edit_referer_script']);
        add_filter('single_template', [$this->cpt_session, 'maybe_single_template']);
        add_action('rest_api_init', [$this->registrations, 'register_rest_routes']);
        add_action('init', [$this, 'register_shared_assets']);
        add_action('wp_enqueue_scripts', [$this, 'localize_frontend_assets']);
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'localize_frontend_assets']);
        add_action('admin_init', [$this->cpt_session, 'register_admin_columns']);
        $this->maybe_enable_local_mailer();

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

    public function register_shared_assets(): void
    {
        wp_register_style(
            'event-hub-frontend-style',
            EVENT_HUB_URL . 'assets/css/frontend.css',
            [],
            EVENT_HUB_VERSION
        );

        wp_register_script(
            'event-hub-frontend',
            EVENT_HUB_URL . 'assets/js/frontend-form.js',
            [],
            EVENT_HUB_VERSION,
            true
        );
    }

    public function localize_frontend_assets(): void
    {
        if (!wp_script_is('event-hub-frontend', 'registered')) {
            $this->register_shared_assets();
        }
        wp_localize_script('event-hub-frontend', 'EventHubForms', [
            'endpoint' => rest_url('event-hub/v1/register'),
            'nonce' => wp_create_nonce('wp_rest'),
            'messages' => [
                'success' => __('Bedankt! We hebben je inschrijving ontvangen.', 'event-hub'),
                'error' => __('Verzenden mislukt. Controleer je invoer en probeer opnieuw.', 'event-hub'),
            ],
        ]);
    }

    private function maybe_enable_local_mailer(): void
    {
        if (!function_exists('wp_get_environment_type')) {
            return;
        }
        if (wp_get_environment_type() !== 'local') {
            return;
        }
        add_action('phpmailer_init', [$this, 'configure_local_mailer']);
    }

    public function configure_local_mailer(\PHPMailer\PHPMailer\PHPMailer $phpmailer): void
    {
        $settings = [
            'host' => defined('EVENT_HUB_LOCAL_SMTP_HOST') ? EVENT_HUB_LOCAL_SMTP_HOST : '127.0.0.1',
            'port' => defined('EVENT_HUB_LOCAL_SMTP_PORT') ? (int) EVENT_HUB_LOCAL_SMTP_PORT : 1025,
            'secure' => defined('EVENT_HUB_LOCAL_SMTP_SECURE') ? EVENT_HUB_LOCAL_SMTP_SECURE : '',
            'auth' => defined('EVENT_HUB_LOCAL_SMTP_AUTH') ? (bool) EVENT_HUB_LOCAL_SMTP_AUTH : false,
            'username' => defined('EVENT_HUB_LOCAL_SMTP_USER') ? EVENT_HUB_LOCAL_SMTP_USER : '',
            'password' => defined('EVENT_HUB_LOCAL_SMTP_PASS') ? EVENT_HUB_LOCAL_SMTP_PASS : '',
        ];
        if (!$settings['auth'] && $settings['username'] !== '') {
            $settings['auth'] = true;
        }
        $settings = apply_filters('event_hub_local_mailer_settings', $settings);

        $phpmailer->isSMTP();
        $phpmailer->Host = $settings['host'];
        $phpmailer->Port = (int) $settings['port'];
        $phpmailer->SMTPSecure = $settings['secure'];
        $phpmailer->SMTPAutoTLS = false;
        $phpmailer->SMTPAuth = !empty($settings['auth']);

        if ($phpmailer->SMTPAuth) {
            $phpmailer->Username = $settings['username'];
            $phpmailer->Password = $settings['password'];
        }
    }
}

