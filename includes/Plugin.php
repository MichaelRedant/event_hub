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
    /** @var Blocks */
    private $blocks;
    /** @var Migrations */
    private $migrations;
    /** @var Logger */
    private $logger;

    public function __construct()
    {
        $this->logger        = new Logger();
        $this->registrations = new Registrations();
        $this->emails        = new Emails($this->registrations, $this->logger);
        $this->cpt_session   = new CPT_Session($this->registrations, $this->emails);
        $this->settings      = new Settings();
        $this->admin_menus   = new Admin_Menus($this->registrations, $this->emails, $this->settings, $this->logger);
        $this->locale        = new Locale();
        $this->cpt_email     = new CPT_Email();
        $this->blocks        = new Blocks($this->cpt_session, $this->registrations);
        $this->migrations    = new Migrations();
        add_action('admin_notices', [$this->settings, 'maybe_notice_cpt_tax_issues']);
        add_action('admin_notices', [$this->settings, 'maybe_notice_linked_sync']);
    }

    public function init(): void
    {
        // Load i18n helpers (runtime Flemish translations)
        $this->locale->init();
        $this->migrations->run();

        // Register CPT + Tax + Meta Boxes
        add_action('init', [$this->cpt_session, 'register_post_type'], 20);
        add_action('init', [$this->cpt_session, 'register_taxonomies'], 20);
        add_action('init', [$this->cpt_session, 'register_shortcodes'], 20);
        add_action('add_meta_boxes', [$this->cpt_session, 'register_meta_boxes']);
        add_action('save_post', [$this->cpt_session, 'save_meta_boxes']);
        add_action('admin_notices', [$this->cpt_session, 'maybe_notice_missing_templates']);
        add_action('admin_notices', [$this->cpt_session, 'maybe_notice_bulk_email_result']);
        add_action('admin_notices', [$this->cpt_session, 'maybe_notice_cpt_fallback']);
        add_action('admin_footer-post.php', [$this->cpt_session, 'render_sticky_savebar']);
        add_action('admin_footer-post-new.php', [$this->cpt_session, 'render_sticky_savebar']);
        add_action('admin_notices', [$this, 'maybe_prompt_cpt_choice']);
        add_action('admin_notices', [$this, 'maybe_notice_cpt_saved']);
        add_filter('use_block_editor_for_post_type', [$this->cpt_session, 'disable_block_editor'], 10, 2);
        add_action('admin_post_event_hub_send_bulk_email', [$this->cpt_session, 'handle_bulk_email_action']);
        add_action('admin_post_event_hub_choose_cpt', [$this, 'handle_cpt_choice']);
        add_filter('post_row_actions', [$this->cpt_session, 'add_dashboard_row_action'], 10, 2);
        add_filter('redirect_post_location', [$this->cpt_session, 'keep_edit_redirect'], 100, 2);
        add_filter('wp_redirect', [$this->cpt_session, 'intercept_wp_redirect'], 100, 2);
        add_action('admin_print_footer_scripts', [$this->cpt_session, 'force_edit_referer_script']);
        add_filter('single_template', [$this->cpt_session, 'maybe_single_template']);
        add_action('rest_api_init', [$this->registrations, 'register_rest_routes']);
        add_action('init', [$this, 'register_shared_assets']);
        add_action('wp_enqueue_scripts', [$this, 'localize_frontend_assets']);
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'localize_frontend_assets']);
        add_action('template_redirect', [$this, 'maybe_handle_public_cancel']);
        add_action('init', [$this->blocks, 'register_blocks']);
        add_action('admin_init', [$this->cpt_session, 'register_admin_columns']);
        $this->maybe_enable_local_mailer();
        $this->register_logging_hooks();

        // Email templates CPT
        add_action('init', [$this->cpt_email, 'register_post_type']);
        add_action('add_meta_boxes', [$this->cpt_email, 'register_meta_boxes']);
        add_action('save_post', [$this->cpt_email, 'save_meta_boxes']);
        add_filter('redirect_post_location', [$this->cpt_email, 'keep_edit_redirect'], 90, 2);
        add_action('admin_post_eh_email_send_test', [$this->cpt_email, 'handle_send_test']);
        add_action('admin_notices', [$this->cpt_email, 'maybe_notice_test']);

        // Admin menus and pages
        add_action('admin_menu', [$this->admin_menus, 'register_menus']);
        add_action('admin_enqueue_scripts', [$this->admin_menus, 'enqueue_assets']);
        add_action('wp_ajax_event_hub_calendar_events', [$this->admin_menus, 'ajax_calendar_events']);
        add_action('wp_ajax_event_hub_public_calendar', [$this->admin_menus, 'ajax_public_calendar_events']);
        add_action('wp_ajax_nopriv_event_hub_public_calendar', [$this->admin_menus, 'ajax_public_calendar_events']);
        add_action('wp_ajax_event_hub_search_linked_events', [$this->cpt_session, 'ajax_search_linked_events']);
        add_action('admin_post_event_hub_delete_logs', [$this->admin_menus, 'handle_delete_logs']);
        add_action('admin_post_event_hub_sync_linked_events', [$this->settings, 'handle_linked_sync_action']);
        add_action('save_post', [$this->settings, 'maybe_sync_from_linked_event'], 20, 3);

        // Settings page
        add_action('admin_init', [$this->settings, 'register_settings']);

        // Fallback runner voor herinneringen/follow-ups als WP-Cron niet draait.
        add_action('init', [$this, 'maybe_run_due_email_events'], 1);
        add_action('init', [$this, 'maybe_handle_event_hub_cron_request'], 0);

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

    /**
     * Externe trigger (bv. uptime robot/cron ping) om due e-mails te versturen.
     * Gebruik: ?event_hub_cron=1&key=JOUW_KEY
     */
    public function maybe_handle_event_hub_cron_request(): void
    {
        if (!isset($_GET['event_hub_cron'])) {
            return;
        }
        $key = isset($_GET['key']) ? sanitize_text_field((string) $_GET['key']) : '';
        $valid_key = $this->get_cron_key();
        if ($key !== $valid_key) {
            status_header(403);
            exit('invalid key');
        }
        $this->maybe_run_due_email_events(true);
        exit('ok');
    }

    private function get_cron_key(): string
    {
        $key = get_option('event_hub_cron_key', '');
        if (!$key) {
            $key = wp_generate_password(16, false);
            update_option('event_hub_cron_key', $key);
        }
        return $key;
    }

    /**
     * Run due reminder/follow-up events wanneer WP-Cron niet draait.
     * Lichtgewicht: checkt enkel specifieke hooks en loopt max. 1x per 30s.
     */
    public function maybe_run_due_email_events(bool $force = false): void
    {
        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }
        // Voorkom stampede.
        if (!$force && get_transient('event_hub_cron_runner_lock')) {
            return;
        }
        set_transient('event_hub_cron_runner_lock', 1, 30);

        if (!function_exists('_get_cron_array')) {
            require_once ABSPATH . 'wp-includes/cron.php';
        }
        $crons = _get_cron_array();
        if (!$crons || !is_array($crons)) {
            return;
        }

        $now = time();
        $target_hooks = ['event_hub_send_reminder', 'event_hub_send_followup', 'event_hub_send_confirmation', 'event_hub_send_waitlist_created', 'event_hub_retry_email'];

        foreach ($crons as $timestamp => $jobs) {
            if ($timestamp > $now) {
                break;
            }
            foreach ($jobs as $hook => $instances) {
                if (!in_array($hook, $target_hooks, true)) {
                    continue;
                }
                foreach ($instances as $instance) {
                    $args = $instance['args'] ?? [];
                    wp_unschedule_event($timestamp, $hook, $args);
                    do_action($hook, ...$args);
                }
            }
        }

        // Extra fail-safe: forceer due reminders indien cron jobs ontbreken.
        if (isset($this->emails) && method_exists($this->emails, 'force_due_reminders')) {
            $this->emails->force_due_reminders();
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

    /**
     * Publieke annuleringshandler via ?eh_cancel=token.
     */
    public function maybe_handle_public_cancel(): void
    {
        if (!isset($_GET['eh_cancel'])) {
            return;
        }
        $token = sanitize_text_field((string) $_GET['eh_cancel']);
        $result = $this->registrations->cancel_by_token($token);
        $success = !is_wp_error($result);
        $title = $success ? __('Inschrijving geannuleerd', 'event-hub') : __('Annulatie mislukt', 'event-hub');
        $message = $success ? __('Je inschrijving werd geannuleerd. We hebben je plaats vrijgemaakt.', 'event-hub') : $result->get_error_message();
        $redirect = $success && $result ? get_permalink((int) $result['session_id']) : home_url('/');
        $redirect_label = $success ? __('Terug naar event', 'event-hub') : __('Ga naar de site', 'event-hub');

        status_header($success ? 200 : 400);
        nocache_headers();
        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html(get_bloginfo('name') . ' - ' . $title); ?></title>
            <style>
                body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f5f5f7;color:#111;padding:32px;margin:0;}
                .eh-cancel-wrap{max-width:520px;margin:40px auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;box-shadow:0 10px 30px rgba(0,0,0,0.05);}
                h1{margin-top:0;font-size:24px;}
                p{line-height:1.5;font-size:15px;}
                a.button{display:inline-block;margin-top:12px;padding:10px 16px;border-radius:8px;text-decoration:none;font-weight:600;}
                .button-primary{background:#111;color:#fff;}
                .button-secondary{background:#e5e7eb;color:#111;}
            </style>
        </head>
        <body>
            <div class="eh-cancel-wrap">
                <h1><?php echo esc_html($title); ?></h1>
                <p><?php echo esc_html($message); ?></p>
                <a class="button button-primary" href="<?php echo esc_url($redirect); ?>"><?php echo esc_html($redirect_label); ?></a>
                <a class="button button-secondary" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Ga naar de homepage', 'event-hub'); ?></a>
            </div>
        </body>
        </html>
        <?php
        exit;
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

    public function maybe_prompt_cpt_choice(): void
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }
        if (!get_transient('event_hub_show_cpt_prompt')) {
            return;
        }

        $post_types = get_post_types(['show_ui' => true], 'objects');
        $disallow = ['attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation'];
        $choices = [];
        foreach ($post_types as $slug => $obj) {
            if (in_array($slug, $disallow, true)) {
                continue;
            }
            $label = isset($obj->labels->singular_name) ? $obj->labels->singular_name : $slug;
            $choices[$slug] = $label;
        }
        $current = Settings::get_cpt_slug();
        ?>
        <div class="notice notice-info" style="padding:12px 16px;">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('event_hub_choose_cpt'); ?>
                <input type="hidden" name="action" value="event_hub_choose_cpt" />
                <p><strong><?php esc_html_e('Kies het evenementen-CPT voor Event Hub.', 'event-hub'); ?></strong></p>
                <p><?php esc_html_e('We vonden de volgende post types. Kies er één om te gebruiken, of kies de standaard Event Hub CPT.', 'event-hub'); ?></p>
                <?php if ($choices): ?>
                    <p>
                        <label for="eh_cpt_slug"><?php esc_html_e('Bestaande CPT', 'event-hub'); ?></label>
                        <select name="eh_cpt_slug" id="eh_cpt_slug">
                            <?php foreach ($choices as $slug => $label): ?>
                                <option value="<?php echo esc_attr($slug); ?>" <?php selected($slug, $current); ?>><?php echo esc_html($label . ' (' . $slug . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p class="submit">
                        <button type="submit" name="eh_choice" value="external" class="button button-primary"><?php esc_html_e('Gebruik geselecteerde CPT', 'event-hub'); ?></button>
                        <button type="submit" name="eh_choice" value="internal" class="button"><?php esc_html_e('Gebruik Event Hub CPT', 'event-hub'); ?></button>
                    </p>
                <?php else: ?>
                    <p><?php esc_html_e('Geen bestaande CPT gevonden. We schakelen de standaard Event Hub CPT in.', 'event-hub'); ?></p>
                    <p class="submit">
                        <button type="submit" name="eh_choice" value="internal" class="button button-primary"><?php esc_html_e('Gebruik Event Hub CPT', 'event-hub'); ?></button>
                    </p>
                <?php endif; ?>
            </form>
        </div>
        <?php
    }

    public function handle_cpt_choice(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Je hebt geen toegang tot deze actie.', 'event-hub'));
        }
        check_admin_referer('event_hub_choose_cpt');

        $choice = isset($_POST['eh_choice']) ? sanitize_text_field((string) $_POST['eh_choice']) : 'external';
        $selected_slug = isset($_POST['eh_cpt_slug']) ? sanitize_key((string) $_POST['eh_cpt_slug']) : '';

        $general = Settings::get_general();
        if ($choice === 'external' && $selected_slug !== '') {
            $general['use_external_cpt'] = 1;
            $general['cpt_slug'] = $selected_slug;
        } else {
            $general['use_external_cpt'] = 0;
            $general['cpt_slug'] = 'eh_session';
        }
        update_option(Settings::OPTION_GENERAL, $general);
        delete_transient('event_hub_show_cpt_prompt');

        $redirect = add_query_arg(
            [
                'page' => 'event-hub-general',
                'eh_cpt_saved' => 1,
            ],
            admin_url('admin.php')
        );
        wp_safe_redirect($redirect);
        exit;
    }

    public function maybe_notice_cpt_saved(): void
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }
        if (!isset($_GET['eh_cpt_saved'])) {
            return;
        }
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('CPT-voorkeur opgeslagen. Pas eventueel de taxonomie aan in de algemene instellingen.', 'event-hub'); ?></p>
        </div>
        <?php
    }

    private function register_logging_hooks(): void
    {
        if (!$this->logger) {
            return;
        }
        add_action('event_hub_registration_created', function (int $id) {
            $this->logger->log('registration', 'Inschrijving aangemaakt', ['id' => $id]);
        });
        add_action('event_hub_waitlist_created', function (int $id) {
            $this->logger->log('registration', 'Wachtlijst-registratie aangemaakt', ['id' => $id]);
        });
        add_action('event_hub_registration_deleted', function (int $id, array $reg) {
            $this->logger->log('registration', 'Inschrijving verwijderd', ['id' => $id, 'session_id' => $reg['session_id'] ?? '']);
        }, 10, 2);
        add_action('event_hub_registration_cancelled', function (int $id) {
            $this->logger->log('registration', 'Inschrijving geannuleerd via link', ['id' => $id]);
        });
    }
}
