<?php
namespace EventHub;

defined('ABSPATH') || exit;

class CPT_Session
{
    public const CPT = 'eh_session';
    public const TAX_TYPE = 'eh_session_type';

    private Registrations $registrations;
    private Emails $emails;

    public function __construct(?Registrations $registrations = null, ?Emails $emails = null)
    {
        $this->registrations = $registrations ?? new Registrations();
        $this->emails        = $emails ?? new Emails($this->registrations);
    }

    private function get_cpt(): string
    {
        return \EventHub\Settings::get_cpt_slug();
    }
    private function get_tax(): string
    {
        return \EventHub\Settings::get_tax_slug();
    }

    public function register_post_type(): void
    {
        // If using external CPT, ensure it exists; otherwise fallback to built-in to avoid invalid post type errors.
        if (\EventHub\Settings::use_external_cpt()) {
            $slug = $this->get_cpt();
            if (post_type_exists($slug)) {
                return;
            }
            \EventHub\Settings::fallback_to_builtin_cpt();
        }

        $labels = [
            'name' => __('Evenementen', 'event-hub'),
            'singular_name' => __('Evenement', 'event-hub'),
            'add_new' => __('Nieuw toevoegen', 'event-hub'),
            'add_new_item' => __('Nieuw evenement toevoegen', 'event-hub'),
            'edit_item' => __('Evenement bewerken', 'event-hub'),
            'new_item' => __('Nieuw evenement', 'event-hub'),
            'view_item' => __('Evenement bekijken', 'event-hub'),
            'search_items' => __('Evenementen zoeken', 'event-hub'),
            'not_found' => __('Geen evenementen gevonden', 'event-hub'),
            'not_found_in_trash' => __('Geen evenementen in prullenbak', 'event-hub'),
            'menu_name' => __('Evenementen', 'event-hub'),
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            'show_in_rest' => false,
            'menu_icon' => 'dashicons-calendar-alt',
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail'],
            'rewrite' => ['slug' => 'events'],
            'taxonomies' => ['post_tag'],
        ];
        register_post_type($this->get_cpt(), $args);
    }

    public function register_taxonomies(): void
    {
        $labels = [
            'name' => __('Eventtypes', 'event-hub'),
            'singular_name' => __('Eventtype', 'event-hub'),
        ];
        register_taxonomy(
            $this->get_tax(),
            [$this->get_cpt()],
            [
                'labels' => $labels,
                'public' => true,
                'hierarchical' => false,
                'show_in_rest' => true,
            ]
        );
    }

    public function register_meta_boxes(): void
    {
        add_meta_box(
            'octo_session_details',
            __('Evenementdetails', 'event-hub'),
            [$this, 'render_meta_box'],
            $this->get_cpt(),
            'normal',
            'default'
        );
        add_meta_box(
            'eh_extra_fields',
            __('Extra formulier-velden', 'event-hub'),
            [$this, 'render_extra_fields_meta_box'],
            $this->get_cpt(),
            'normal',
            'default'
        );

    }

    public function render_extra_fields_meta_box(\WP_Post $post): void
    {
        $saved = get_post_meta($post->ID, '_eh_extra_fields', true);
        $fields = is_array($saved) ? $saved : [];
        $hide_defaults = (array) get_post_meta($post->ID, '_eh_form_hide_fields', true);
        wp_nonce_field('eh_extra_fields_meta', 'eh_extra_fields_nonce');
        echo '<style>
            .eh-extra-box{border:1px solid #e5e7eb;border-radius:10px;background:#fff;padding:12px;margin-top:8px}
            #eh-extra-fields-table th,#eh-extra-fields-table td{vertical-align:top}
            #eh-extra-fields-table textarea{min-width:220px}
            .eh-extra-box h4{margin:14px 0 6px;font-size:14px}
        </style>';
        echo '<p>' . esc_html__('Voeg optionele velden toe die enkel voor dit event getoond worden.', 'event-hub') . '</p>';
        echo '<div class="eh-extra-box">';
        echo '<table class="widefat striped" id="eh-extra-fields-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Label', 'event-hub') . '</th>';
        echo '<th>' . esc_html__('Slug', 'event-hub') . '</th>';
        echo '<th>' . esc_html__('Type', 'event-hub') . '</th>';
        echo '<th>' . esc_html__('Opties (per lijn bij select)', 'event-hub') . '</th>';
        echo '<th>' . esc_html__('Verplicht', 'event-hub') . '</th>';
        echo '<th></th>';
        echo '</tr></thead><tbody>';
        if (!$fields) {
            $fields[] = ['label' => '', 'slug' => '', 'type' => 'text', 'required' => 0, 'options' => []];
        }
        foreach ($fields as $index => $field) {
            $this->render_extra_field_row($index, $field);
        }
        echo '</tbody></table>';
        echo '<p><button type="button" class="button" id="eh-extra-add-row">' . esc_html__('Veld toevoegen', 'event-hub') . '</button></p>';
        ?>
        <script>
        (function(){
            const table = document.getElementById('eh-extra-fields-table');
            const addBtn = document.getElementById('eh-extra-add-row');
            if (!table || !addBtn) { return; }
            addBtn.addEventListener('click', function(){
                const tbody = table.querySelector('tbody');
                const index = tbody.querySelectorAll('tr').length;
                const tpl = document.getElementById('eh-extra-row-template').innerHTML.replace(/__i__/g, index);
                const wrap = document.createElement('tbody');
                wrap.innerHTML = tpl;
                tbody.appendChild(wrap.firstElementChild);
            });
            table.addEventListener('click', function(e){
                if (e.target.classList.contains('eh-extra-remove')) {
                    e.preventDefault();
                    const row = e.target.closest('tr');
                    if (row) { row.remove(); }
                }
            });
        })();
        </script>
        <template id="eh-extra-row-template">
            <?php $this->render_extra_field_row('__i__', ['label'=>'','slug'=>'','type'=>'text','required'=>0,'options'=>[]], true); ?>
        </template>
        <h4>' . esc_html__('Standaard velden verbergen', 'event-hub') . '</h4>
        <p><label><input type="checkbox" name="eh_hide_fields[]" value="people_count"' . checked(in_array('people_count', $hide_defaults, true), true, false) . '> ' . esc_html__('Verberg "Aantal personen"', 'event-hub') . '</label></p>
        <p><label><input type="checkbox" name="eh_hide_fields[]" value="phone"' . checked(in_array('phone', $hide_defaults, true), true, false) . '> ' . esc_html__('Verberg "Telefoon"', 'event-hub') . '</label></p>
        <p><label><input type="checkbox" name="eh_hide_fields[]" value="company"' . checked(in_array('company', $hide_defaults, true), true, false) . '> ' . esc_html__('Verberg "Bedrijf"', 'event-hub') . '</label></p>
        <p><label><input type="checkbox" name="eh_hide_fields[]" value="vat"' . checked(in_array('vat', $hide_defaults, true), true, false) . '> ' . esc_html__('Verberg "BTW-nummer"', 'event-hub') . '</label></p>
        <p><label><input type="checkbox" name="eh_hide_fields[]" value="role"' . checked(in_array('role', $hide_defaults, true), true, false) . '> ' . esc_html__('Verberg "Rol"', 'event-hub') . '</label></p>
        <p><label><input type="checkbox" name="eh_hide_fields[]" value="marketing"' . checked(in_array('marketing', $hide_defaults, true), true, false) . '> ' . esc_html__('Verberg marketing-opt-in', 'event-hub') . '</label></p>
        </div>
        <?php
    }

    private function render_extra_field_row($index, array $field, bool $rawOutput = false): void
    {
        $label = $field['label'] ?? '';
        $slug = $field['slug'] ?? '';
        $type = $field['type'] ?? 'text';
        $required = !empty($field['required']);
        $options = '';
        if (!empty($field['options']) && is_array($field['options'])) {
            $options = implode("\n", $field['options']);
        }
        ob_start();
        echo '<tr>';
        echo '<td><input type="text" name="eh_extra_fields[label][]" value="' . esc_attr((string) $label) . '" class="regular-text" /></td>';
        echo '<td><input type="text" name="eh_extra_fields[slug][]" value="' . esc_attr((string) $slug) . '" class="regular-text" placeholder="broodje" /></td>';
        echo '<td><select name="eh_extra_fields[type][]">';
        $types = ['text' => __('Tekst', 'event-hub'), 'textarea' => __('Tekstvak', 'event-hub'), 'select' => __('Keuzelijst', 'event-hub')];
        foreach ($types as $val => $label_t) {
            echo '<option value="' . esc_attr($val) . '"' . selected($type, $val, false) . '>' . esc_html($label_t) . '</option>';
        }
        echo '</select></td>';
        echo '<td><textarea name="eh_extra_fields[options][]" rows="2" class="large-text code" placeholder="' . esc_attr__("broodje kaas\nbroodje hesp", 'event-hub') . '">' . esc_textarea($options) . '</textarea></td>';
        echo '<td style="text-align:center"><input type="checkbox" name="eh_extra_fields[required][' . esc_attr((string) $index) . ']" value="1"' . checked($required, true, false) . ' /></td>';
        echo '<td><button type="button" class="button-link-delete eh-extra-remove">' . esc_html__('Verwijder', 'event-hub') . '</button></td>';
        echo '</tr>';
        $html = ob_get_clean();
        echo $rawOutput ? $html : $html; // if rawOutput true, used inside template
    }

    public function register_admin_columns(): void
    {
        $cpt = $this->get_cpt();
        add_filter("manage_edit-{$cpt}_columns", [$this, 'add_admin_columns']);
        add_action("manage_{$cpt}_posts_custom_column", [$this, 'render_admin_column'], 10, 2);
        add_action('admin_head', [$this, 'admin_columns_styles']);
    }

    public function register_shortcodes(): void
    {
        add_shortcode('event_hub_session', [$this, 'render_session_shortcode']);
        add_shortcode('event_hub_list', [$this, 'render_list_shortcode']);
    }

    public function render_session_shortcode($atts = []): string
    {
        $atts = shortcode_atts([
            'id' => 0,
            'template' => '',
        ], $atts, 'event_hub_session');

        $post_id = (int) $atts['id'];
        if (!$post_id && isset($GLOBALS['post']) && $GLOBALS['post'] instanceof \WP_Post) {
            $post_id = (int) $GLOBALS['post']->ID;
        }
        if (!$post_id) {
            return esc_html__('Geen event opgegeven voor de shortcode.', 'event-hub');
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== $this->get_cpt()) {
            return esc_html__('Event niet gevonden of ongeldig post type.', 'event-hub');
        }

        $template_attr = sanitize_file_name((string) $atts['template']);
        $fallback = EVENT_HUB_PATH . 'templates/single-event-hub.php';
        $template = $fallback;
        if ($template_attr) {
            $located = locate_template($template_attr);
            if ($located) {
                $template = $located;
            }
        } else {
            $located = locate_template('single-' . $this->get_cpt() . '.php');
            if ($located) {
                $template = $located;
            }
        }
        $template = apply_filters('event_hub_session_shortcode_template', $template, $post, $atts);
        if (!file_exists($template)) {
            return esc_html__('Geen template gevonden voor dit event.', 'event-hub');
        }

        setup_postdata($post);
        ob_start();
        include $template;
        wp_reset_postdata();
        return (string) ob_get_clean();
    }

    public function render_list_shortcode($atts = []): string
    {
        $atts = shortcode_atts([
            'count' => 6,
            'order' => 'ASC',
            'status' => '',
            'show_excerpt' => '1',
            'show_date' => '1',
        ], $atts, 'event_hub_list');

        $count = max(1, (int) $atts['count']);
        $order = strtoupper((string) $atts['order']) === 'DESC' ? 'DESC' : 'ASC';
        $show_excerpt = $atts['show_excerpt'] === '1' || $atts['show_excerpt'] === 1 || $atts['show_excerpt'] === true;
        $show_date = $atts['show_date'] === '1' || $atts['show_date'] === 1 || $atts['show_date'] === true;
        $status = sanitize_text_field((string) $atts['status']);

        $meta_query = [];
        if ($status !== '') {
            $meta_query[] = [
                'key' => '_eh_status',
                'value' => $status,
                'compare' => '=',
            ];
        }

        $query = new \WP_Query([
            'post_type' => $this->get_cpt(),
            'posts_per_page' => $count,
            'orderby' => 'meta_value',
            'meta_key' => '_eh_date_start',
            'order' => $order,
            'post_status' => 'publish',
            'meta_query' => $meta_query,
        ]);

        if (!$query->have_posts()) {
            return '<div class="eh-session-list-block no-results">' . esc_html__('Geen events gevonden.', 'event-hub') . '</div>';
        }

        ob_start();
        echo '<div class="eh-session-list-block">';
        while ($query->have_posts()) {
            $query->the_post();
            $session_id = get_the_ID();
            $start = get_post_meta($session_id, '_eh_date_start', true);
            $time = $start ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($start)) : '';
            $excerpt = $show_excerpt ? get_the_excerpt() : '';
            echo '<article class="eh-session-card">';
            echo '<h3 class="eh-session-title"><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></h3>';
            if ($show_date && $time) {
                echo '<div class="eh-session-meta"><span class="eh-session-date">' . esc_html($time) . '</span></div>';
            }
            if ($excerpt) {
                echo '<div class="eh-session-excerpt">' . esc_html($excerpt) . '</div>';
            }
            echo '<a class="eh-session-link" href="' . esc_url(get_permalink()) . '">' . esc_html__('Bekijk event', 'event-hub') . '</a>';
            echo '</article>';
        }
        echo '</div>';
        wp_reset_postdata();
        return (string) ob_get_clean();
    }

    public function add_dashboard_row_action(array $actions, \WP_Post $post): array
    {
        if ($post->post_type !== $this->get_cpt()) {
            return $actions;
        }

        $actions['event_hub_dashboard'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url(add_query_arg(
                [
                    'page' => 'event-hub-event',
                    'event_id' => $post->ID,
                ],
                admin_url('admin.php')
            )),
            esc_html__('Bekijk dashboard', 'event-hub')
        );

        return $actions;
    }

    public function keep_edit_redirect(string $location, int $post_id): string
    {
        if (get_post_type($post_id) !== $this->get_cpt()) {
            return $location;
        }

        $forced = add_query_arg(
            [
                'post'   => $post_id,
                'action' => 'edit',
                'message'=> isset($_GET['message']) ? (int) $_GET['message'] : (isset($_POST['post_status']) && $_POST['post_status'] === 'draft' ? 6 : 1),
            ],
            admin_url('post.php')
        );

        $this->log_redirect($location, $forced, $post_id);

        return $forced;
    }

    public function force_edit_referer_script(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->base !== 'post' || $screen->post_type !== $this->get_cpt()) {
            return;
        }
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var form = document.getElementById('post');
            if (!form) { return; }
            var ref = form.querySelector('input[name="_wp_http_referer"]');
            if (ref) {
                var url = window.location.href;
                var relative = url.replace(window.location.origin, '');
                ref.value = relative;
                if (window.console && console.debug) {
                    console.debug('[EventHub] referer reset to', relative);
                }
            }
        });
        </script>
        <?php
    }

    private function log_redirect(string $original, string $forced, int $post_id): void
    {
        $this->debug_log('redirect_post_location', [
            'post_id'  => $post_id,
            'original' => $original,
            'forced'   => $forced,
            'referer'  => $_POST['_wp_http_referer'] ?? '',
            'request'  => [
                'action' => $_POST['action'] ?? '',
                'post_status' => $_POST['post_status'] ?? '',
                'post_type' => $_POST['post_type'] ?? '',
            ],
        ]);
    }

    public function maybe_single_template(string $template): string
    {
        if (!is_singular($this->get_cpt())) {
            return $template;
        }
        $general = Settings::get_general();
        $custom_enabled = !empty($general['single_custom_enabled']);
        $use_builtin = true;
        if (is_singular() && get_the_ID()) {
            $use_builtin_meta = get_post_meta(get_the_ID(), '_eh_use_builtin_page', true);
            $use_builtin = ($use_builtin_meta === '') ? true : (bool) $use_builtin_meta;
        }
        if (!$use_builtin && defined('ELEMENTOR_PRO_VERSION')) {
            return $template;
        }
        if (locate_template('single-' . $this->get_cpt() . '.php')) {
            return $template;
        }
        $fallback = EVENT_HUB_PATH . 'templates/single-event-hub.php';
        if (file_exists($fallback)) {
            add_action('wp_enqueue_scripts', [$this, 'enqueue_single_assets']);
            return $fallback;
        }
        return $template;
    }

    public function enqueue_single_assets(): void
    {
        wp_enqueue_style('event-hub-frontend-style');
        wp_enqueue_script('event-hub-frontend');
    }

    public function disable_block_editor(bool $use_block, string $post_type): bool
    {
        if ($post_type === $this->get_cpt()) {
            return false;
        }
        return $use_block;
    }

    public function maybe_notice_missing_templates(): void
    {
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'post' || $screen->post_type !== $this->get_cpt()) {
            return;
        }

        $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
        if (!$post_id) {
            return;
        }

        $confirm = (array) get_post_meta($post_id, '_eh_email_confirm_templates', true);
        $remind  = (array) get_post_meta($post_id, '_eh_email_reminder_templates', true);
        $follow  = (array) get_post_meta($post_id, '_eh_email_followup_templates', true);

        $missing = [];
        if (!$confirm) { $missing[] = __('Bevestiging', 'event-hub'); }
        if (!$remind)  { $missing[] = __('Herinnering', 'event-hub'); }
        if (!$follow)  { $missing[] = __('Nadien', 'event-hub'); }

        if ($missing) {
            echo '<div class="notice notice-warning"><p>' .
                esc_html(sprintf(__('Dit event heeft geen e-mailsjablonen voor: %s. Voeg er minstens één toe om automatische mails te versturen.', 'event-hub'), implode(', ', $missing))) .
                '</p></div>';
        }
    }

    public function render_meta_box(\WP_Post $post): void
    {
        // Needed for media modal (colleagues photos).
        if (function_exists('wp_enqueue_media')) {
            wp_enqueue_media();
        }
        wp_nonce_field('octo_session_meta', 'octo_session_meta_nonce');

        $date_start = get_post_meta($post->ID, '_eh_date_start', true);
        $date_end = get_post_meta($post->ID, '_eh_date_end', true);
        $location = get_post_meta($post->ID, '_eh_location', true);
        $is_online = (bool) get_post_meta($post->ID, '_eh_is_online', true);
        $online_link = get_post_meta($post->ID, '_eh_online_link', true);
        $capacity = get_post_meta($post->ID, '_eh_capacity', true);
        $language = get_post_meta($post->ID, '_eh_language', true);
        $audience = get_post_meta($post->ID, '_eh_target_audience', true);
        $status = get_post_meta($post->ID, '_eh_status', true) ?: 'open';
        $booking_open = get_post_meta($post->ID, '_eh_booking_open', true);
        $booking_close = get_post_meta($post->ID, '_eh_booking_close', true);
        $address = get_post_meta($post->ID, '_eh_address', true);
        $organizer = get_post_meta($post->ID, '_eh_organizer', true);
        $staff = get_post_meta($post->ID, '_eh_staff', true);
        $general = Settings::get_general();
        $colleagues_global = isset($general['colleagues']) && is_array($general['colleagues']) ? $general['colleagues'] : [];
        $colleagues = (array) get_post_meta($post->ID, '_eh_colleagues', true);
        $use_builtin_page_meta = get_post_meta($post->ID, '_eh_use_builtin_page', true);
        $use_builtin_page = ($use_builtin_page_meta === '') ? 1 : (int) $use_builtin_page_meta;
        $use_local_builder = (int) get_post_meta($post->ID, '_eh_use_local_builder', true);
        $builder_local = get_post_meta($post->ID, '_eh_builder_sections', true);
        $builder_local = $builder_local !== '' ? $builder_local : ($general['single_builder_sections'] ?? '[]');
        $price = get_post_meta($post->ID, '_eh_price', true);
        $no_show_fee = get_post_meta($post->ID, '_eh_no_show_fee', true);
        $show_on_site_meta = get_post_meta($post->ID, '_eh_show_on_site', true);
        $show_on_site = ($show_on_site_meta === '') ? true : (bool) $show_on_site_meta;
        $color = get_post_meta($post->ID, '_eh_color', true) ?: '#2271b1';
        $ticket_note = get_post_meta($post->ID, '_eh_ticket_note', true);
        $enable_module_meta = get_post_meta($post->ID, '_eh_enable_module', true);
        $enable_module = ($enable_module_meta === '') ? 1 : (int) $enable_module_meta;

        if (!$date_start && isset($_GET['eh_start'])) {
            $maybe = sanitize_text_field((string) $_GET['eh_start']);
            $ts = strtotime($maybe);
            if ($ts) {
                $date_start = gmdate('Y-m-d H:i:00', $ts);
                if (!$date_end) {
                    $date_end = gmdate('Y-m-d H:i:00', $ts + HOUR_IN_SECONDS);
                }
            }
        }

        $ds_val = $date_start ? esc_attr(date('Y-m-d\TH:i', strtotime($date_start))) : '';
        $de_val = $date_end ? esc_attr(date('Y-m-d\TH:i', strtotime($date_end))) : '';
        $bo_val = $booking_open ? esc_attr(date('Y-m-d\TH:i', strtotime($booking_open))) : '';
        $bc_val = $booking_close ? esc_attr(date('Y-m-d\TH:i', strtotime($booking_close))) : '';

        $emails = get_posts([
            'post_type'  => CPT_Email::CPT,
            'numberposts'=> -1,
            'orderby'    => 'title',
            'order'      => 'ASC',
        ]);
        $sel_confirm = (array) get_post_meta($post->ID, '_eh_email_confirm_templates', true);
        $sel_remind  = (array) get_post_meta($post->ID, '_eh_email_reminder_templates', true);
        $sel_follow  = (array) get_post_meta($post->ID, '_eh_email_followup_templates', true);
        $sel_waitlist = (array) get_post_meta($post->ID, '_eh_email_waitlist_templates', true);
        $custom_email_fields = [
            'confirmation' => __('Bevestiging inschrijving', 'event-hub'),
            'reminder' => __('Herinnering (voor start)', 'event-hub'),
            'followup' => __('Nadien (bedanking)', 'event-hub'),
            'waitlist' => __('Wachtlijst bevestiging', 'event-hub'),
            'waitlist_promotion' => __('Wachtlijst promotie', 'event-hub'),
        ];
        ?>
        <div class="eh-admin eh-session-meta">
            <div class="eh-onboarding-card">
                <h2><?php echo esc_html__('Je event klaarzetten', 'event-hub'); ?></h2>
                <p><?php echo esc_html__('Volg de drie stappen hieronder. Elke kaart bevat korte uitleg zodat nieuwe collega’s meteen meekunnen.', 'event-hub'); ?></p>
                <ol>
                    <li><?php echo esc_html__('Plan het moment en bepaal wanneer registraties open/gesloten zijn.', 'event-hub'); ?></li>
                    <li><?php echo esc_html__('Beschrijf de ervaring: locatie, online link, praktische details.', 'event-hub'); ?></li>
                    <li><?php echo esc_html__('Koppel mails zodat deelnemers automatisch op de hoogte blijven.', 'event-hub'); ?></li>
                </ol>
                <p class="eh-tip"><?php echo esc_html__('Tip: sla tussendoor op. Zie je het event niet op de website? Controleer dan of “Toon in eventlijsten” aangevinkt is.', 'event-hub'); ?></p>
            </div>

            <div class="eh-section">
                <div class="eh-section-header">
                    <span class="step-label"><?php echo esc_html__('Stap 1', 'event-hub'); ?></span>
                    <div>
                        <h2><?php echo esc_html__('Planning & inschrijvingen', 'event-hub'); ?></h2>
                        <p><?php echo esc_html__('Deze tijden sturen de kalender, reminders en capaciteit. Laat start & einde leeg? Dan verschijnt het event zonder tijd.', 'event-hub'); ?></p>
                    </div>
                </div>
                <div class="eh-field-grid">
                    <div class="eh-field-card">
                        <div class="eh-field-card__head">
                            <h3><?php echo esc_html__('Moment', 'event-hub'); ?></h3>
                            <p><?php echo esc_html__('Bepaalt hoe het event in kalender en ICS verschijnt.', 'event-hub'); ?></p>
                        </div>
                        <div class="eh-form-two-col">
                            <div class="field">
                                <label for="_eh_date_start"><?php echo esc_html__('Startdatum en -uur', 'event-hub'); ?></label>
                                <input type="datetime-local" id="_eh_date_start" name="_eh_date_start" value="<?php echo $ds_val; ?>" required>
                                <p class="description"><?php echo esc_html__('We raden aan om hier altijd een uur in te vullen.', 'event-hub'); ?></p>
                            </div>
                            <div class="field">
                                <label for="_eh_date_end"><?php echo esc_html__('Einddatum en -uur', 'event-hub'); ?></label>
                                <input type="datetime-local" id="_eh_date_end" name="_eh_date_end" value="<?php echo $de_val; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="eh-field-card">
                        <div class="eh-field-card__head">
                            <h3><?php echo esc_html__('Registratievenster', 'event-hub'); ?></h3>
                            <p><?php echo esc_html__('Open en sluit inschrijvingen automatisch.', 'event-hub'); ?></p>
                        </div>
                        <div class="eh-form-two-col">
                            <div class="field">
                                <label for="_eh_booking_open"><?php echo esc_html__('Registraties openen', 'event-hub'); ?></label>
                                <input type="datetime-local" id="_eh_booking_open" name="_eh_booking_open" value="<?php echo $bo_val; ?>">
                                <p class="description"><?php echo esc_html__('Laat leeg om meteen open te zetten.', 'event-hub'); ?></p>
                            </div>
                            <div class="field">
                                <label for="_eh_booking_close"><?php echo esc_html__('Registraties sluiten', 'event-hub'); ?></label>
                                <input type="datetime-local" id="_eh_booking_close" name="_eh_booking_close" value="<?php echo $bc_val; ?>">
                                <p class="description"><?php echo esc_html__('Nuttig voor wachtlijsten of cateringdeadlines.', 'event-hub'); ?></p>
                            </div>
                            <div class="field">
                                <label for="_eh_status"><?php echo esc_html__('Status', 'event-hub'); ?></label>
                                <select id="_eh_status" name="_eh_status">
                                    <?php
                                    $status_labels = [
                                        'open'      => __('Open', 'event-hub'),
                                        'full'      => __('Volzet', 'event-hub'),
                                        'cancelled' => __('Geannuleerd', 'event-hub'),
                                        'closed'    => __('Gesloten', 'event-hub'),
                                    ];
                                    foreach ($status_labels as $val => $label) {
                                        echo '<option value="' . esc_attr($val) . '" ' . selected($status, $val, false) . '>' . esc_html($label) . '</option>';
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php echo esc_html__('Wordt getoond in filters en badges.', 'event-hub'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="eh-field-card">
                        <div class="eh-field-card__head">
                            <h3><?php echo esc_html__('Beschikbaarheid', 'event-hub'); ?></h3>
                            <p><?php echo esc_html__('Laat bezoekers weten of er nog plaatsen zijn.', 'event-hub'); ?></p>
                        </div>
                        <div class="eh-form-two-col">
                            <div class="field">
                                <label for="_eh_capacity"><?php echo esc_html__('Capaciteit', 'event-hub'); ?></label>
                                <input type="number" id="_eh_capacity" name="_eh_capacity" value="<?php echo esc_attr($capacity); ?>" min="0" placeholder="0 = onbeperkt">
                                <p class="description"><?php echo esc_html__('Gebruik 0 wanneer er geen beperking is.', 'event-hub'); ?></p>
                            </div>
                            <div class="field full toggle">
                                <label>
                                    <input type="checkbox" name="_eh_show_on_site" value="1" <?php checked($show_on_site); ?>>
                                    <?php echo esc_html__('Toon in eventlijsten & widgets', 'event-hub'); ?>
                                </label>
                                <p class="description"><?php echo esc_html__('Schakel uit om intern te testen zonder publiek.', 'event-hub'); ?></p>
                            </div>
                            <div class="field full toggle">
                                <label>
                                    <input type="checkbox" name="_eh_use_builtin_page" value="1" <?php checked($use_builtin_page); ?>>
                                    <?php echo esc_html__('Gebruik Event Hub pagina (inschrijving)', 'event-hub'); ?>
                                </label>
                                <p class="description"><?php echo esc_html__('Schakel uit als je voor dit event een externe/Elementor pagina wil gebruiken.', 'event-hub'); ?></p>
                            </div>
                            <div class="field full toggle">
                                <label>
                                    <input type="checkbox" name="_eh_enable_module" value="1" <?php checked($enable_module); ?>>
                                    <?php echo esc_html__('Event Hub inschrijvingen activeren', 'event-hub'); ?>
                                </label>
                                <p class="description"><?php echo esc_html__('Schakel uit als dit event extern beheerd wordt en geen inschrijvingen via Event Hub nodig heeft.', 'event-hub'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="eh-field-card">
                        <div class="eh-field-card__head">
                            <h3><?php echo esc_html__('Layout builder (event)', 'event-hub'); ?></h3>
                            <p><?php echo esc_html__('Overschrijf de globale layout voor dit event.', 'event-hub'); ?></p>
                        </div>
                        <div class="eh-form-two-col">
                            <div class="field full toggle">
                                <label>
                                    <input type="checkbox" name="_eh_use_local_builder" value="1" <?php checked($use_local_builder); ?>>
                                    <?php echo esc_html__('Gebruik lokale builder', 'event-hub'); ?>
                                </label>
                                <p class="description"><?php echo esc_html__('Aan = deze builder JSON gebruiken in plaats van de globale.', 'event-hub'); ?></p>
                            </div>
                            <div class="field" style="grid-column:1 / -1;" id="eh-builder-local-wrapper">
                                <label><?php echo esc_html__('Secties & stijl (lokaal)', 'event-hub'); ?></label>
                                <input type="hidden" id="eh-builder-local" name="_eh_builder_sections" value="<?php echo esc_attr((string) $builder_local); ?>">
                                <div id="eh-builder-local-list" style="border:1px solid #e5e7eb;border-radius:10px;padding:8px;background:#fff;box-shadow:0 6px 16px rgba(15,23,42,.05);"></div>
                                <div style="display:flex;gap:8px;align-items:center;margin-top:8px;">
                                    <select id="eh-builder-local-add" style="min-width:160px;">
                                        <option value="status"><?php echo esc_html__('Status', 'event-hub'); ?></option>
                                        <option value="hero"><?php echo esc_html__('Hero', 'event-hub'); ?></option>
                                        <option value="info"><?php echo esc_html__('Praktische info', 'event-hub'); ?></option>
                                        <option value="content"><?php echo esc_html__('Content', 'event-hub'); ?></option>
                                        <option value="form"><?php echo esc_html__('Formulier', 'event-hub'); ?></option>
                                        <option value="quote"><?php echo esc_html__('Quote', 'event-hub'); ?></option>
                                        <option value="faq"><?php echo esc_html__('FAQ', 'event-hub'); ?></option>
                                        <option value="agenda"><?php echo esc_html__('Agenda', 'event-hub'); ?></option>
                                        <option value="buttons"><?php echo esc_html__('CTA knoppen', 'event-hub'); ?></option>
                                        <option value="gallery"><?php echo esc_html__('Gallery', 'event-hub'); ?></option>
                                    </select>
                                    <button type="button" class="button" id="eh-builder-local-add-btn"><?php echo esc_html__('Sectie toevoegen', 'event-hub'); ?></button>
                                </div>
                                <p class="description"><?php echo esc_html__('Sleep voor volgorde, pas accent/bg/heading/font/padding per sectie aan. Wordt alleen gebruikt als lokale builder is ingeschakeld.', 'event-hub'); ?></p>
                            </div>
                    </div>
                </div>
            </div>

            <script>
                (function(){
                    const wrap=document.getElementById('eh-builder-local-wrapper');
                    const onToggle=document.querySelector('input[name="_eh_use_local_builder"]');
                    const listEl=document.getElementById('eh-builder-local-list');
                    const inputEl=document.getElementById('eh-builder-local');
                    const addSel=document.getElementById('eh-builder-local-add');
                    const addBtn=document.getElementById('eh-builder-local-add-btn');
                    if(!wrap||!listEl||!inputEl) return;
                    const defaults={
                        status:'<?php echo esc_js(__('Status', 'event-hub')); ?>',
                        hero:'<?php echo esc_js(__('Hero', 'event-hub')); ?>',
                        info:'<?php echo esc_js(__('Praktische info', 'event-hub')); ?>',
                        content:'<?php echo esc_js(__('Content', 'event-hub')); ?>',
                        form:'<?php echo esc_js(__('Formulier', 'event-hub')); ?>',
                        quote:'<?php echo esc_js(__('Quote', 'event-hub')); ?>',
                        faq:'<?php echo esc_js(__('FAQ', 'event-hub')); ?>',
                        agenda:'<?php echo esc_js(__('Agenda', 'event-hub')); ?>',
                        buttons:'<?php echo esc_js(__('CTA knoppen', 'event-hub')); ?>',
                        gallery:'<?php echo esc_js(__('Gallery', 'event-hub')); ?>'
                    };
                    function rowTpl(sec){
                        const accent=sec.accent||'#0f172a';
                        const bg=sec.bg||'#ffffff';
                        const heading=sec.heading||'';
                        const fontSize=sec.fontSize||16;
                        const padding=sec.padding||16;
                        const paddingMobile=sec.paddingMobile||14;
                        const gradient=sec.gradient||'';
                        const bgImage=sec.bgImage||'';
                        const variant=sec.variant||'default';
                        return `<div class="eh-builder-row" draggable="true" data-id="${sec.id}" style="display:grid;grid-template-columns:16px 1fr;gap:10px;align-items:center;padding:8px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:6px;background:#f8fafc;"><span style="cursor:grab;font-size:16px;line-height:1;">&#8942;&#8942;</span><div><div style="display:flex;justify-content:space-between;gap:8px;align-items:center;"><strong>${sec.title||defaults[sec.id]||sec.id}</strong><span style="font-size:11px;color:#475569;">${sec.id}</span></div><div style="display:flex;gap:8px;margin-top:6px;flex-wrap:wrap;"><label style="font-size:12px;color:#475569;"><?php echo esc_js(__('Accent', 'event-hub')); ?> <input data-field="accent" type="color" value="${accent}" style="width:70px;"></label><label style="font-size:12px;color:#475569;"><?php echo esc_js(__('Achtergrond', 'event-hub')); ?> <input data-field="bg" type="color" value="${bg}" style="width:70px;"></label><label style="font-size:12px;color:#475569;"><?php echo esc_js(__('Gradient', 'event-hub')); ?> <select data-field="gradient" style="min-width:120px;"><option value=""><?php echo esc_js(__('Geen', 'event-hub')); ?></option><option value="sunset" ${gradient==='sunset'?'selected':''}>Sunset</option><option value="mint" ${gradient==='mint'?'selected':''}>Mint</option><option value="ocean" ${gradient==='ocean'?'selected':''}>Ocean</option></select></label><label style="font-size:12px;color:#475569;display:flex;gap:4px;align-items:center;"><?php echo esc_js(__('Bg image', 'event-hub')); ?> <input data-field="bgImage" type="text" value="${bgImage}" style="width:150px;" placeholder="https://..."><button type="button" class="button button-small eh-builder-media" data-target="bgImage"><?php echo esc_js(__('Kies', 'event-hub')); ?></button></label><label style="font-size:12px;color:#475569;"><?php echo esc_js(__('Heading', 'event-hub')); ?> <input data-field="heading" type="text" value="${heading}" style="width:140px;" placeholder="<?php echo esc_js(__('Titel override', 'event-hub')); ?>"></label><label style="font-size:12px;color:#475569;"><?php echo esc_js(__('CTA label', 'event-hub')); ?> <input data-field="cta" type="text" value="${sec.cta||''}" style="width:120px;" placeholder="<?php echo esc_js(__('Bijv. Inschrijven', 'event-hub')); ?>"></label><label style="font-size:12px;color:#475569;"><?php echo esc_js(__('Font size', 'event-hub')); ?> <input data-field="fontSize" type="number" min="12" max="32" value="${fontSize}" style="width:70px;"></label><label style="font-size:12px;color:#475569;"><?php echo esc_js(__('Padding (desktop)', 'event-hub')); ?> <input data-field="padding" type="number" min="8" max="64" value="${padding}" style="width:70px;"></label><label style="font-size:12px;color:#475569;"><?php echo esc_js(__('Padding mobiel', 'event-hub')); ?> <input data-field="paddingMobile" type="number" min="6" max="48" value="${paddingMobile}" style="width:70px;"></label><label style="font-size:12px;color:#475569;"><?php echo esc_js(__('Variant', 'event-hub')); ?> <select data-field="variant" style="min-width:120px;"><option value="default" ${variant==='default'?'selected':''}>Default</option><option value="card" ${variant==='card'?'selected':''}>Card</option><option value="soft" ${variant==='soft'?'selected':''}>Soft</option></select></label></div></div></div>`;
                    }
                    let dragEl=null;
                    function read(){
                        try { return JSON.parse(inputEl.value)||[]; } catch(e){ return []; }
                    }
                    function render(){
                        listEl.innerHTML='';
                        read().forEach(sec=>{
                            const w=document.createElement('div');
                            w.innerHTML=rowTpl(sec);
                            const row=w.firstChild;
                            if(row){ row.dataset.body = sec.body || ''; listEl.appendChild(row); }
                        });
                        attach();
                    }
                    function attach(){
                        listEl.querySelectorAll('.eh-builder-row').forEach(row=>{
                            row.addEventListener('dragstart',e=>{dragEl=row; e.dataTransfer.effectAllowed='move';});
                            row.addEventListener('dragover',e=>{
                                e.preventDefault();
                                const over=e.target.closest('.eh-builder-row');
                                if(!over||over===row||over===dragEl) return;
                                const rect=over.getBoundingClientRect();
                                const before=(e.clientY-rect.top)/rect.height < 0.5;
                                listEl.insertBefore(dragEl, before?over:over.nextSibling);
                                sync();
                            });
                        });
                        listEl.querySelectorAll('input,select').forEach(inp=>{inp.addEventListener('input', sync);});
                        listEl.querySelectorAll('.eh-builder-media').forEach(btn=>{
                            btn.addEventListener('click',function(e){
                                e.preventDefault();
                                if(typeof wp === 'undefined' || !wp.media) return;
                                const input = btn.previousElementSibling;
                                const frame = wp.media({title:'<?php echo esc_js(__('Selecteer achtergrond', 'event-hub')); ?>',button:{text:'<?php echo esc_js(__('Gebruiken', 'event-hub')); ?>'},multiple:false});
                                frame.on('select', function(){
                                    const att = frame.state().get('selection').first().toJSON();
                                    if(input){ input.value = att.url || ''; sync(); }
                                });
                                frame.open();
                            });
                        });
                    }
                    function sync(){
                        const rows=[...listEl.querySelectorAll('.eh-builder-row')];
                        const data=rows.map(r=>({
                            id:r.dataset.id||'',
                            title:r.querySelector('strong')?.textContent||r.dataset.id,
                            accent:r.querySelector('[data-field="accent"]')?.value||'',
                            bg:r.querySelector('[data-field="bg"]')?.value||'',
                            gradient:r.querySelector('[data-field="gradient"]')?.value||'',
                            bgImage:r.querySelector('[data-field="bgImage"]')?.value||'',
                            heading:r.querySelector('[data-field="heading"]')?.value||'',
                            cta:r.querySelector('[data-field="cta"]')?.value||'',
                            fontSize:parseInt(r.querySelector('[data-field="fontSize"]')?.value||'16',10),
                            padding:parseInt(r.querySelector('[data-field="padding"]')?.value||'16',10),
                            paddingMobile:parseInt(r.querySelector('[data-field="paddingMobile"]')?.value||'14',10),
                            variant:r.querySelector('[data-field="variant"]')?.value||'default',
                            body:r.dataset.body||''
                        }));
                        inputEl.value = JSON.stringify(data);
                    }
                    function addSection(type){
                        const data=read();
                        data.push({id:type,title:defaults[type]||type,accent:'#0f172a',bg:'#ffffff',gradient:'',bgImage:'',heading:'',cta:'',fontSize:16,padding:16,paddingMobile:14,variant:'default',body:''});
                        inputEl.value=JSON.stringify(data);
                        render();
                        sync();
                    }
                    if(addBtn){ addBtn.addEventListener('click', ()=>addSection(addSel.value)); }
                    function toggle(){
                        wrap.style.display = onToggle && onToggle.checked ? '' : 'none';
                    }
                    if(onToggle){ onToggle.addEventListener('change', toggle); }
                    toggle();
                    render();
                })();
            </script>
        </div>

        <div class="eh-section">
                <div class="eh-section-header">
                    <span class="step-label"><?php echo esc_html__('Stap 2', 'event-hub'); ?></span>
                    <div>
                        <h2><?php echo esc_html__('Locatie & beleving', 'event-hub'); ?></h2>
                        <p><?php echo esc_html__('Vertel waar het event plaatsvindt of hoe deelnemers kunnen inloggen.', 'event-hub'); ?></p>
                    </div>
                </div>
                <div class="eh-field-grid">
                    <div class="eh-field-card">
                        <div class="eh-field-card__head">
                            <h3><?php echo esc_html__('Fysieke locatie', 'event-hub'); ?></h3>
                            <p><?php echo esc_html__('Vul in voor on-site events. Laat leeg voor volledig online.', 'event-hub'); ?></p>
                        </div>
                        <div class="eh-form-two-col">
                            <div class="field">
                                <label for="_eh_location"><?php echo esc_html__('Locatie / zaalnaam', 'event-hub'); ?></label>
                                <input type="text" id="_eh_location" name="_eh_location" value="<?php echo esc_attr($location); ?>" placeholder="<?php echo esc_attr__('vb. Campus Gent - Auditorium A', 'event-hub'); ?>">
                            </div>
                            <div class="field">
                                <label for="_eh_address"><?php echo esc_html__('Adres', 'event-hub'); ?></label>
                                <input type="text" id="_eh_address" name="_eh_address" value="<?php echo esc_attr($address); ?>" class="regular-text">
                            </div>
                        </div>
                    </div>
                    <div class="eh-field-card">
                        <div class="eh-field-card__head">
                            <h3><?php echo esc_html__('Online of hybride', 'event-hub'); ?></h3>
                            <p><?php echo esc_html__('Voeg een meetinglink toe en toon automatisch een online badge.', 'event-hub'); ?></p>
                        </div>
                        <div class="eh-form-two-col">
                            <div class="field toggle full">
                                <label>
                                    <input type="checkbox" name="_eh_is_online" value="1" <?php checked($is_online); ?>>
                                    <?php echo esc_html__('Dit is een online sessie', 'event-hub'); ?>
                                </label>
                                <p class="description"><?php echo esc_html__('Verberg de fysieke locatie wanneer enkel online.', 'event-hub'); ?></p>
                            </div>
                            <div class="field">
                                <label for="_eh_online_link"><?php echo esc_html__('Onlinelink (URL)', 'event-hub'); ?></label>
                                <input type="url" id="_eh_online_link" name="_eh_online_link" value="<?php echo esc_attr($online_link); ?>" placeholder="https://">
                                <p class="description"><?php echo esc_html__('Wordt enkel getoond wanneer online is ingeschakeld.', 'event-hub'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="eh-field-card">
                        <div class="eh-field-card__head">
                            <h3><?php echo esc_html__('Stijl & kleur', 'event-hub'); ?></h3>
                            <p><?php echo esc_html__('Accentkleur voor kalender, kaarten en badges.', 'event-hub'); ?></p>
                        </div>
                        <div class="eh-form-two-col">
                            <div class="field">
                                <label for="_eh_color"><?php echo esc_html__('Accentkleur', 'event-hub'); ?></label>
                                <input type="color" id="_eh_color" name="_eh_color" value="<?php echo esc_attr($color); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="eh-section">
                <div class="eh-section-header">
                    <span class="step-label"><?php echo esc_html__('Stap 3', 'event-hub'); ?></span>
                    <div>
                        <h2><?php echo esc_html__('Praktische info & team', 'event-hub'); ?></h2>
                        <p><?php echo esc_html__('Deze blok helpt deelnemers beslissen of het event bij hen past.', 'event-hub'); ?></p>
                    </div>
                </div>
                <div class="eh-field-grid">
                    <div class="eh-field-card">
                        <div class="eh-field-card__head">
                            <h3><?php echo esc_html__('Taal & doelgroep', 'event-hub'); ?></h3>
                            <p><?php echo esc_html__('Geef bezoekers context over niveau en taalgebruik.', 'event-hub'); ?></p>
                        </div>
                        <div class="eh-form-two-col">
                            <div class="field">
                                <label for="_eh_language"><?php echo esc_html__('Taal', 'event-hub'); ?></label>
                                <input type="text" id="_eh_language" name="_eh_language" value="<?php echo esc_attr($language); ?>" placeholder="nl, fr, en">
                            </div>
                            <div class="field">
                                <label for="_eh_target_audience"><?php echo esc_html__('Doelgroep', 'event-hub'); ?></label>
                                <input type="text" id="_eh_target_audience" name="_eh_target_audience" value="<?php echo esc_attr($audience); ?>" placeholder="<?php echo esc_attr__('vb. Starters, HR managers, alumni…', 'event-hub'); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="eh-field-card">
                        <div class="eh-field-card__head">
                            <h3><?php echo esc_html__('Prijs & ticketing', 'event-hub'); ?></h3>
                            <p><?php echo esc_html__('Toon meteen wat deelnemers mogen verwachten.', 'event-hub'); ?></p>
                        </div>
                        <div class="eh-form-two-col">
                            <div class="field">
                                <label for="_eh_price"><?php echo esc_html__('Prijs (optioneel)', 'event-hub'); ?></label>
                                <input type="number" step="0.01" id="_eh_price" name="_eh_price" value="<?php echo esc_attr($price); ?>" placeholder="0.00">
                            </div>
                            <div class="field">
                                <label for="_eh_no_show_fee"><?php echo esc_html__('No-show kost', 'event-hub'); ?></label>
                                <input type="number" step="0.01" id="_eh_no_show_fee" name="_eh_no_show_fee" value="<?php echo esc_attr($no_show_fee); ?>">
                            </div>
                            <div class="field full">
                                <label for="_eh_ticket_note"><?php echo esc_html__('Ticket- of tariefinfo', 'event-hub'); ?></label>
                                <textarea id="_eh_ticket_note" name="_eh_ticket_note" class="large-text" rows="3" placeholder="<?php echo esc_attr__('Beschrijf pakketten, kortingen of praktische info.', 'event-hub'); ?>"><?php echo esc_textarea($ticket_note); ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="eh-field-card">
                        <div class="eh-field-card__head">
                            <h3><?php echo esc_html__('Team', 'event-hub'); ?></h3>
                            <p><?php echo esc_html__('Zo weten collega’s wie aanspreekpunt is.', 'event-hub'); ?></p>
                        </div>
                        <div class="eh-form-two-col">
                            <div class="field">
                                <label for="_eh_organizer"><?php echo esc_html__('Organisator', 'event-hub'); ?></label>
                                <input type="text" id="_eh_organizer" name="_eh_organizer" value="<?php echo esc_attr($organizer); ?>" placeholder="<?php echo esc_attr__('vb. Team Marketing', 'event-hub'); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="eh-field-card">
                        <div class="eh-field-card__head">
                            <h3><?php echo esc_html__('Collega’s op dit event', 'event-hub'); ?></h3>
                            <p><?php echo esc_html__('Selecteer teamleden uit de centrale lijst.', 'event-hub'); ?></p>
                        </div>
                        <?php if (!$colleagues_global) : ?>
                            <p><?php echo esc_html__('Nog geen collega’s aangemaakt. Voeg ze toe via Event Hub > Algemene instellingen.', 'event-hub'); ?></p>
                        <?php else : ?>
                            <div class="eh-colleagues-list eh-colleagues-select">
                                <?php foreach ($colleagues_global as $idx => $c) : 
                                    $cid = (int) $idx;
                                    $selected = in_array($cid, array_map('intval', $colleagues), true);
                                    $name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
                                    $role = $c['role'] ?? '';
                                    $photo = (int) ($c['photo_id'] ?? 0);
                                    ?>
                                    <label class="eh-colleague-option">
                                        <input type="checkbox" name="eh_colleagues_selected[]" value="<?php echo esc_attr((string) $cid); ?>" <?php checked($selected); ?>>
                                        <?php if ($photo) : ?>
                                            <span class="eh-colleague-photo-preview"><?php echo wp_get_attachment_image($photo, [40, 40]); ?></span>
                                        <?php endif; ?>
                                        <span>
                                            <strong><?php echo esc_html($name ?: __('Naamloos', 'event-hub')); ?></strong>
                                            <?php if ($role) : ?>
                                                <div class="description" style="margin:0;"><?php echo esc_html($role); ?></div>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="eh-section">
                <div class="eh-section-header">
                    <span class="step-label"><?php echo esc_html__('Bonus', 'event-hub'); ?></span>
                    <div>
                        <h2><?php echo esc_html__('Automatische e-mails', 'event-hub'); ?></h2>
                        <p><?php echo esc_html__('Kies welke sjablonen we gebruiken. Zonder selectie vertrekt er niets automatisch.', 'event-hub'); ?></p>
                    </div>
                </div>
                <?php
                if (!$emails) {
                    $link = esc_url(admin_url('post-new.php?post_type=' . CPT_Email::CPT));
                    echo '<p>' . esc_html__('Je hebt nog geen e-mailsjablonen. Maak er eerst eentje aan.', 'event-hub') . '</p>';
                    echo '<p><a class="button button-secondary" href="' . $link . '">' . esc_html__('Nieuw e-mailsjabloon', 'event-hub') . '</a></p>';
                } else {
                    $email_cards = [
                        [
                            'key' => 'waitlist',
                            'label' => __('Wachtlijst bevestiging', 'event-hub'),
                            'desc' => __('Bevestigt inschrijving op de wachtlijst.', 'event-hub'),
                            'select_name' => '_eh_email_waitlist_templates',
                            'selected' => $sel_waitlist,
                            'timing' => null,
                            'badge' => __('Wachtlijst', 'event-hub'),
                        ],
                        [
                            'key' => 'waitlist_promotion',
                            'label' => __('Wachtlijst promotie', 'event-hub'),
                            'desc' => __('Verwittigt deelnemers wanneer ze van de wachtlijst komen.', 'event-hub'),
                            'select_name' => '_eh_email_waitlist_templates',
                            'selected' => $sel_waitlist,
                            'timing' => null,
                            'badge' => __('Wachtlijst', 'event-hub'),
                        ],
                        [
                            'key' => 'confirmation',
                            'label' => __('Bevestiging (na inschrijving)', 'event-hub'),
                            'desc' => __('Wordt onmiddellijk verstuurd.', 'event-hub'),
                            'select_name' => '_eh_email_confirm_templates',
                            'selected' => $sel_confirm,
                            'timing' => null,
                            'badge' => __('Voor inschrijving', 'event-hub'),
                        ],
                        [
                            'key' => 'reminder',
                            'label' => __('Herinnering (voor de start)', 'event-hub'),
                            'desc' => __('Plan je reminder voor dit event.', 'event-hub'),
                            'select_name' => '_eh_email_reminder_templates',
                            'selected' => $sel_remind,
                            'timing' => [
                                'name' => '_eh_reminder_offset_days',
                                'label' => __('Dagen voor start', 'event-hub'),
                                'value' => get_post_meta($post->ID, '_eh_reminder_offset_days', true),
                                'placeholder' => '3',
                            ],
                            'badge' => __('Voor start', 'event-hub'),
                        ],
                        [
                            'key' => 'followup',
                            'label' => __('Nadien (aftermovie, survey, ...)', 'event-hub'),
                            'desc' => __('Verstuur na afloop van dit event.', 'event-hub'),
                            'select_name' => '_eh_email_followup_templates',
                            'selected' => $sel_follow,
                            'timing' => [
                                'name' => '_eh_followup_offset_hours',
                                'label' => __('Uren na einde', 'event-hub'),
                                'value' => get_post_meta($post->ID, '_eh_followup_offset_hours', true),
                                'placeholder' => '24',
                            ],
                            'badge' => __('Na afloop', 'event-hub'),
                        ],
                    ];

                    echo '<div class="eh-email-grid">';
                    foreach ($email_cards as $card) {
                        $custom_subj = get_post_meta($post->ID, '_eh_email_custom_' . $card['key'] . '_subject', true);
                        $custom_body = get_post_meta($post->ID, '_eh_email_custom_' . $card['key'] . '_body', true);
                        echo '<div class="eh-email-card">';
                        echo '<h4>' . esc_html($card['label']);
                        if (!empty($card['badge'])) {
                            echo '<span class="eh-badge-pill status-open" style="margin-left:auto;">' . esc_html($card['badge']) . '</span>';
                        }
                        echo '</h4>';
                        echo '<p class="description" style="margin:4px 0 8px;">' . esc_html($card['desc']) . '</p>';
                        echo '<div class="eh-email-body">';
                        echo '<div class="eh-email-row">';
                        echo '<label style="font-weight:600;">' . esc_html__('Sjabloon', 'event-hub') . '</label>';
                        echo '<select name="' . esc_attr($card['select_name']) . '[]" multiple class="eh-template-select" style="min-height:90px;">';
                        foreach ($emails as $e) {
                            $sel = in_array((string) $e->ID, array_map('strval', $card['selected']), true) ? 'selected' : '';
                            echo '<option value="' . esc_attr((string) $e->ID) . '" ' . $sel . '>' . esc_html($e->post_title) . '</option>';
                        }
                        echo '</select>';
                        echo '</div>';
                        if (!empty($card['timing'])) {
                            echo '<div class="eh-email-row">';
                            echo '<label style="font-weight:600;">' . esc_html($card['timing']['label']) . '</label>';
                            echo '<input type="number" name="' . esc_attr($card['timing']['name']) . '" placeholder="' . esc_attr($card['timing']['placeholder']) . '" value="' . esc_attr((string) $card['timing']['value']) . '" min="0" />';
                            echo '<span class="description">' . esc_html__('Laat leeg om de algemene instelling te gebruiken.', 'event-hub') . '</span>';
                            echo '</div>';
                        }
                        echo '<details>';
                        echo '<summary>' . esc_html__('Eigen mail voor dit event (optioneel)', 'event-hub') . '</summary>';
                        echo '<div class="eh-email-row" style="flex-direction:column;align-items:flex-start;">';
                        echo '<label style="font-weight:600;">' . esc_html__('Onderwerp', 'event-hub') . '</label>';
                        echo '<input type="text" class="regular-text" name="_eh_email_custom_' . esc_attr($card['key']) . '_subject" value="' . esc_attr((string) $custom_subj) . '" placeholder="' . esc_attr__('Onderwerp', 'event-hub') . '" />';
                        echo '<label style="font-weight:600;margin-top:8px;">' . esc_html__('Inhoud (HTML toegestaan)', 'event-hub') . '</label>';
                        echo '<textarea class="large-text code" rows="4" name="_eh_email_custom_' . esc_attr($card['key']) . '_body" placeholder="' . esc_attr__('HTML of tekst', 'event-hub') . '">' . esc_textarea((string) $custom_body) . '</textarea>';
                        echo '</div>';
                        echo '</details>';
                        echo '</div>'; // body
                        echo '</div>'; // card
                    }
                    echo '</div>'; // grid
                }
                ?>
            </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            try {
                var wrapper = document.querySelector('.eh-colleagues-list');
                var addBtn = document.querySelector('.eh-colleague-add');
                var template = document.querySelector('#eh-colleague-template');
                if (!wrapper || !addBtn || !template || typeof wp === 'undefined' || !wp.media) { return; }

                function bindPhoto(btn) {
                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        var row = btn.closest('.eh-colleague-row');
                        var input = row.querySelector('.eh-colleague-photo-id');
                        var preview = row.querySelector('.eh-colleague-photo-preview');
                        var frame = wp.media({
                            title: '<?php echo esc_js(__('Selecteer foto', 'event-hub')); ?>',
                            button: { text: '<?php echo esc_js(__('Gebruiken', 'event-hub')); ?>' },
                            multiple: false
                        });
                        frame.on('select', function () {
                            var attachment = frame.state().get('selection').first().toJSON();
                            input.value = attachment.id;
                            if (preview) {
                                var url = (attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url);
                                preview.innerHTML = '<img src="' + url + '" alt="" style="width:60px;height:60px;border-radius:8px;object-fit:cover;">';
                            }
                        });
                        frame.open();
                    });
                }

                function bindRow(row) {
                    var removeBtn = row.querySelector('.eh-colleague-remove');
                    if (removeBtn) {
                        removeBtn.addEventListener('click', function (e) {
                            e.preventDefault();
                            row.remove();
                        });
                    }
                    var photoBtn = row.querySelector('.eh-colleague-photo-btn');
                    if (photoBtn) {
                        bindPhoto(photoBtn);
                    }
                }

                wrapper.querySelectorAll('.eh-colleague-row').forEach(bindRow);

                addBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var idx = wrapper.querySelectorAll('.eh-colleague-row').length;
                    var html = template.innerHTML.replace(/__index__/g, idx);
                    var temp = document.createElement('div');
                    temp.innerHTML = html.trim();
                    var row = temp.firstElementChild;
                    wrapper.appendChild(row);
                    bindRow(row);
                });
            } catch (err) {
                if (window.console) { console.warn('[EventHub] Colleagues init failed', err); }
            }
        });
        </script>
        <?php
    }

    public function render_sticky_savebar(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->base !== 'post' || $screen->post_type !== $this->get_cpt()) {
            return;
        }
        ?>
        <div class="eh-sticky-savebar">
            <div class="eh-savebar-inner">
                <div class="eh-savebar-left">
                    <strong><?php echo esc_html__('Event opslaan', 'event-hub'); ?></strong>
                    <span><?php echo esc_html__('Wijzigingen worden toegepast na opslaan.', 'event-hub'); ?></span>
                </div>
                <div class="eh-savebar-actions">
                    <button type="button" class="button" id="eh-preview-btn"><?php echo esc_html__('Voorbeeld', 'event-hub'); ?></button>
                    <button type="button" class="button button-primary" id="eh-save-btn"><?php echo esc_html__('Opslaan', 'event-hub'); ?></button>
                </div>
            </div>
        </div>
        <script>
        (function(){
            const saveBtn = document.getElementById('eh-save-btn');
            const previewBtn = document.getElementById('eh-preview-btn');
            const publish = document.getElementById('publish');
            const preview = document.getElementById('post-preview');
            if (saveBtn && publish) {
                saveBtn.addEventListener('click', function(e){
                    e.preventDefault();
                    publish.click();
                });
            }
            if (previewBtn && preview) {
                previewBtn.addEventListener('click', function(e){
                    e.preventDefault();
                    preview.click();
                });
            }
        })();
        </script>
        <?php
    }

    public function maybe_notice_cpt_fallback(): void
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }
        if (!get_transient('event_hub_cpt_fallback')) {
            return;
        }
        delete_transient('event_hub_cpt_fallback');
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php esc_html_e('Het gekozen externe CPT werd niet gevonden. We zijn teruggevallen op de standaard Event Hub CPT (eh_session). Pas dit eventueel aan in de algemene instellingen.', 'event-hub'); ?></p>
        </div>
        <?php
    }

    public function render_email_broadcast_box(\WP_Post $post): void
    {
        $templates = get_posts([
            'post_type'   => CPT_Email::CPT,
            'numberposts' => -1,
            'orderby'     => 'title',
            'order'       => 'ASC',
        ]);

        $registrations = $this->registrations->get_registrations_by_session((int) $post->ID);
        $status_labels = Registrations::get_status_labels();
        $counts = [];
        foreach ($status_labels as $key => $label) {
            $counts[$key] = 0;
        }
        foreach ($registrations as $reg) {
            $status = $reg['status'] ?? '';
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }
        $total = count($registrations);
        $default_statuses = ['registered', 'confirmed'];

        echo '<div class="eh-email-broadcast-box">';

        if (!$templates) {
            $link = esc_url(admin_url('post-new.php?post_type=' . CPT_Email::CPT));
            echo '<p>' . esc_html__('Je hebt nog geen e-mailsjablonen ingesteld.', 'event-hub') . '</p>';
            echo '<p><a class="button" href="' . $link . '">' . esc_html__('Maak nieuw sjabloon', 'event-hub') . '</a></p>';
            echo '</div>';
            return;
        }

        if (!$registrations) {
            echo '<p>' . esc_html__('Nog geen inschrijvingen voor dit event.', 'event-hub') . '</p>';
            echo '<p>' . esc_html__('Zodra deelnemers zich registreren, kan je van hieruit bulk e-mails sturen.', 'event-hub') . '</p>';
            echo '</div>';
            return;
        }

        echo '<p><strong>' . esc_html(sprintf(_n('%d inschrijving', '%d inschrijvingen', $total, 'event-hub'), $total)) . '</strong></p>';
        echo '<ul style="margin:0 0 12px 16px;padding:0;list-style:disc;">';
        foreach ($status_labels as $status => $label) {
            echo '<li>' . esc_html($label) . ': ' . esc_html((string) $counts[$status]) . '</li>';
        }
        echo '</ul>';

        $action = esc_url(admin_url('admin-post.php'));
        echo '<form method="post" action="' . $action . '">';
        echo '<input type="hidden" name="action" value="event_hub_send_bulk_email">';
        echo '<input type="hidden" name="session_id" value="' . esc_attr((string) $post->ID) . '">';
        wp_nonce_field('event_hub_bulk_email_' . $post->ID);

        echo '<p>';
        echo '<label for="eventhub-bulk-template"><strong>' . esc_html__('Kies sjabloon', 'event-hub') . '</strong></label><br />';
        echo '<select class="widefat" name="template_id" id="eventhub-bulk-template" required>';
        echo '<option value="">' . esc_html__('Selecteer…', 'event-hub') . '</option>';
        foreach ($templates as $template) {
            echo '<option value="' . esc_attr((string) $template->ID) . '">' . esc_html($template->post_title) . '</option>';
        }
        echo '</select>';
        echo '</p>';

        echo '<fieldset>';
        echo '<legend><strong>' . esc_html__('Ontvangers (status)', 'event-hub') . '</strong></legend>';
        foreach ($status_labels as $status => $label) {
            $checked = in_array($status, $default_statuses, true) ? ' checked' : '';
            echo '<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="registration_statuses[]" value="' . esc_attr($status) . '"' . $checked . '> ' . esc_html($label) . '</label>';
        }
        echo '</fieldset>';

        echo '<p class="description">' . esc_html__('De geselecteerde mail wordt onmiddellijk verstuurd naar elke deelnemer die aan de gekozen statussen voldoet.', 'event-hub') . '</p>';
        submit_button(__('Verzend naar deelnemers', 'event-hub'), 'secondary', 'submit', false);
        echo '</form>';
        echo '</div>';
    }

    public function handle_bulk_email_action(): void
    {
        $session_id = isset($_POST['session_id']) ? (int) $_POST['session_id'] : 0;
        if (!$session_id) {
            wp_safe_redirect(admin_url());
            exit;
        }

        check_admin_referer('event_hub_bulk_email_' . $session_id);

        if (!current_user_can('edit_post', $session_id)) {
            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));
        }

        $template_id = isset($_POST['template_id']) ? (int) $_POST['template_id'] : 0;
        $requested_statuses = isset($_POST['registration_statuses']) ? array_map('sanitize_text_field', (array) $_POST['registration_statuses']) : [];
        $allowed_statuses = array_keys(Registrations::get_status_labels());
        $default_statuses = array_values(array_intersect($allowed_statuses, ['registered', 'confirmed']));
        $statuses = array_values(array_intersect($allowed_statuses, $requested_statuses));
        if (!$statuses) {
            $statuses = $default_statuses ?: $allowed_statuses;
        }

        $redirect = add_query_arg(
            [
                'post' => $session_id,
                'action' => 'edit',
            ],
            admin_url('post.php')
        );

        if (!$template_id) {
            $redirect = add_query_arg(['eh_bulk_email' => 'error', 'reason' => 'no_template'], $redirect);
            wp_safe_redirect($redirect);
            exit;
        }

        $registrations = $this->registrations->get_registrations_by_session($session_id);
        if (!$registrations) {
            $redirect = add_query_arg(['eh_bulk_email' => 'error', 'reason' => 'no_registrations'], $redirect);
            wp_safe_redirect($redirect);
            exit;
        }

        $sent = 0;
        $failed = 0;
        $targets = 0;
        $last_error = '';
        foreach ($registrations as $registration) {
            if (!in_array($registration['status'], $statuses, true)) {
                continue;
            }
            $targets++;
            $result = $this->emails->send_template((int) $registration['id'], $template_id, 'bulk_event');
            if (is_wp_error($result)) {
                $failed++;
                if (!$last_error) {
                    $last_error = $result->get_error_message();
                }
            } else {
                $sent++;
            }
        }

        if ($targets === 0) {
            $redirect = add_query_arg(['eh_bulk_email' => 'error', 'reason' => 'no_matches'], $redirect);
        } else {
            $status = $failed > 0 ? 'partial' : 'success';
            $args = [
                'eh_bulk_email' => $status,
                'sent' => $sent,
            ];
            if ($failed) {
                $args['failed'] = $failed;
            }
            if ($last_error) {
                $args['message'] = $last_error;
            }
            $redirect = add_query_arg($args, $redirect);
        }

        wp_safe_redirect($redirect);
        exit;
    }

    public function maybe_notice_bulk_email_result(): void
    {
        if (empty($_GET['eh_bulk_email'])) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'post' || $screen->post_type !== $this->get_cpt()) {
            return;
        }

        $status = sanitize_text_field((string) $_GET['eh_bulk_email']);
        $class = 'notice-success';
        $message = '';

        if ($status === 'success') {
            $sent = isset($_GET['sent']) ? (int) $_GET['sent'] : 0;
            $message = sprintf(_n('%d e-mail verzonden.', '%d e-mails verzonden.', $sent, 'event-hub'), $sent);
        } elseif ($status === 'partial') {
            $class = 'notice-warning';
            $sent = isset($_GET['sent']) ? (int) $_GET['sent'] : 0;
            $failed = isset($_GET['failed']) ? (int) $_GET['failed'] : 0;
            $message = sprintf(__('Verzending klaar: %1$d verstuurd, %2$d mislukt.', 'event-hub'), $sent, $failed);
            if (!empty($_GET['message'])) {
                $message .= ' ' . sanitize_text_field(wp_unslash((string) $_GET['message']));
            }
        } else {
            $class = 'notice-error';
            $reason = isset($_GET['reason']) ? sanitize_text_field((string) $_GET['reason']) : '';
            switch ($reason) {
                case 'no_template':
                    $message = __('Selecteer een e-mailsjabloon vooraleer te verzenden.', 'event-hub');
                    break;
                case 'no_registrations':
                    $message = __('Er zijn nog geen inschrijvingen voor dit event.', 'event-hub');
                    break;
                case 'no_matches':
                    $message = __('Geen deelnemers voldoen aan de gekozen statussen.', 'event-hub');
                    break;
                default:
                    $message = __('Bulk e-mail verzenden is mislukt.', 'event-hub');
                    break;
            }
        }

        if ($message) {
            echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }

    public function add_admin_columns(array $columns): array
    {
        $new = [];
        if (isset($columns['cb'])) {
            $new['cb'] = $columns['cb'];
            unset($columns['cb']);
        }
        $new['title'] = __('Evenement', 'event-hub');
        $new['eh_dates'] = __('Planning', 'event-hub');
        $new['eh_location'] = __('Locatie', 'event-hub');
        $new['eh_capacity'] = __('Capaciteit', 'event-hub');
        $new['eh_occupancy'] = __('Bezetting', 'event-hub');
        $new['eh_status'] = __('Status', 'event-hub');

        $date_label = $columns['date'] ?? '';
        unset($columns['title'], $columns['date']);

        foreach ($columns as $key => $label) {
            $new[$key] = $label;
        }

        if ($date_label) {
            $new['date'] = $date_label;
        }

        return $new;
    }

    public function render_admin_column(string $column, int $post_id): void
    {
        if (!in_array($column, ['eh_dates', 'eh_location', 'eh_capacity', 'eh_occupancy', 'eh_status'], true)) {
            return;
        }
        $state = $this->registrations->get_capacity_state($post_id);

        if ($column === 'eh_dates') {
            $start = get_post_meta($post_id, '_eh_date_start', true);
            $end = get_post_meta($post_id, '_eh_date_end', true);
            if ($start) {
                $start_label = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($start));
                $end_label = $end ? date_i18n(get_option('time_format'), strtotime($end)) : '';
                echo esc_html($start_label);
                if ($end_label) {
                    echo '<br><span class="description">' . esc_html(sprintf(__('Tot %s', 'event-hub'), $end_label)) . '</span>';
                }
            } else {
                echo '—';
            }
            return;
        }

        if ($column === 'eh_location') {
            $is_online = (bool) get_post_meta($post_id, '_eh_is_online', true);
            $location = get_post_meta($post_id, '_eh_location', true);
            if ($is_online) {
                echo '<span class="eh-badge-pill status-online">' . esc_html__('Online', 'event-hub') . '</span>';
            } elseif ($location) {
                echo esc_html($location);
            } else {
                echo '—';
            }
            return;
        }

        if ($column === 'eh_capacity') {
            $capacity = $state['capacity'] > 0 ? $state['capacity'] : __('Onbeperkt', 'event-hub');
            echo '<strong>' . esc_html((string) $capacity) . '</strong>';
            return;
        }

        if ($column === 'eh_occupancy') {
            $booked = $state['booked'];
            $capacity = $state['capacity'];
            $waitlist = $state['waitlist'] ?? 0;
            $label = $capacity > 0 ? sprintf('%d / %d', $booked, $capacity) : (string) $booked;
            $percentage = $capacity > 0 ? min(100, max(0, round(($booked / max(1, $capacity)) * 100))) : 0;
            echo '<div class="eh-progress"><span class="eh-progress-bar" style="width:' . esc_attr((string) $percentage) . '%;"></span></div>';
            echo '<span class="eh-progress-label">' . esc_html($label) . '</span>';
            if ($waitlist > 0) {
                $waitlist_label = sprintf(
                    _n('%d persoon', '%d personen', $waitlist, 'event-hub'),
                    $waitlist
                );
                echo '<div class="eh-waitlist-row">';
                echo '<span class="eh-badge-pill status-waitlist">' . esc_html__('Wachtlijst', 'event-hub') . '</span>';
                echo '<span class="eh-progress-label">' . esc_html($waitlist_label) . '</span>';
                echo '</div>';
            }
            return;
        }

        if ($column === 'eh_status') {
            $status = get_post_meta($post_id, '_eh_status', true) ?: 'open';
            $labels = [
                'open' => __('Open', 'event-hub'),
                'full' => __('Volzet', 'event-hub'),
                'closed' => __('Gesloten', 'event-hub'),
                'cancelled' => __('Geannuleerd', 'event-hub'),
            ];
            $class = 'status-' . sanitize_html_class($status);
            echo '<span class="eh-badge-pill ' . esc_attr($class) . '">' . esc_html($labels[$status] ?? ucfirst($status)) . '</span>';
            return;
        }
    }

    public function admin_columns_styles(): void
    {
        if (!function_exists('get_current_screen')) {
            require_once ABSPATH . 'wp-admin/includes/screen.php';
        }
        if (!function_exists('get_current_screen')) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== $this->get_cpt()) {
            return;
        }
        echo '<style>
        .column-eh_dates{width:190px}
        .column-eh_location{width:140px}
        .column-eh_capacity,.column-eh_occupancy,.column-eh_status{width:130px}
        .eh-progress{background:#eef1f6;border-radius:999px;height:6px;margin-bottom:4px;overflow:hidden}
        .eh-progress-bar{display:block;height:100%;background:#2271b1;border-radius:999px}
        .eh-progress-label{font-size:12px;color:#4b5563;font-weight:600}
        .eh-waitlist-row{display:flex;align-items:center;gap:6px;margin-top:6px}
        .eh-badge-pill{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.03em}
        .eh-badge-pill.status-open{background:#e3f4e8;color:#256029}
        .eh-badge-pill.status-full{background:#fdecea;color:#ab1f24}
        .eh-badge-pill.status-closed{background:#f0f0f0;color:#444}
        .eh-badge-pill.status-cancelled{background:#fce7f1;color:#8e2453}
        .eh-badge-pill.status-online{background:#e3f2fd;color:#0d47a1}
        .eh-badge-pill.status-waitlist{background:#fff4e6;color:#b45309}
        </style>';
    }

    public function save_meta_boxes(int $post_id): void
    {
        $this->debug_log('save_meta_boxes_attempt', [
            'post_id'    => $post_id,
            'post_type'  => get_post_type($post_id),
            'autosave'   => defined('DOING_AUTOSAVE') && DOING_AUTOSAVE,
            'can_edit'   => current_user_can('edit_post', $post_id),
            'nonce'      => isset($_POST['octo_session_meta_nonce']),
            'action'     => $_POST['action'] ?? '',
        ]);

        if (!isset($_POST['octo_session_meta_nonce']) || !wp_verify_nonce(sanitize_text_field((string) $_POST['octo_session_meta_nonce']), 'octo_session_meta')) {
            $this->debug_log('save_meta_boxes_skip', ['post_id' => $post_id, 'reason' => 'nonce']);
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            $this->debug_log('save_meta_boxes_skip', ['post_id' => $post_id, 'reason' => 'autosave']);
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            $this->debug_log('save_meta_boxes_skip', ['post_id' => $post_id, 'reason' => 'cap']);
            return;
        }
        if (get_post_type($post_id) !== $this->get_cpt()) {
            $this->debug_log('save_meta_boxes_skip', ['post_id' => $post_id, 'reason' => 'post_type', 'actual' => get_post_type($post_id), 'expected' => $this->get_cpt()]);
            return;
        }

        $date_start = isset($_POST['_eh_date_start']) ? sanitize_text_field((string) $_POST['_eh_date_start']) : '';
        $date_end   = isset($_POST['_eh_date_end']) ? sanitize_text_field((string) $_POST['_eh_date_end']) : '';
        $booking_open = isset($_POST['_eh_booking_open']) ? sanitize_text_field((string) $_POST['_eh_booking_open']) : '';
        $booking_close= isset($_POST['_eh_booking_close']) ? sanitize_text_field((string) $_POST['_eh_booking_close']) : '';
        $location   = isset($_POST['_eh_location']) ? sanitize_text_field((string) $_POST['_eh_location']) : '';
        $is_online  = isset($_POST['_eh_is_online']) ? 1 : 0;
        $show_on_site = isset($_POST['_eh_show_on_site']) ? 1 : 0;
        $online_link = isset($_POST['_eh_online_link']) ? esc_url_raw((string) $_POST['_eh_online_link']) : '';
        $capacity   = isset($_POST['_eh_capacity']) ? intval($_POST['_eh_capacity']) : '';
        $language   = isset($_POST['_eh_language']) ? sanitize_text_field((string) $_POST['_eh_language']) : '';
        $audience   = isset($_POST['_eh_target_audience']) ? sanitize_text_field((string) $_POST['_eh_target_audience']) : '';
        $status     = isset($_POST['_eh_status']) ? sanitize_text_field((string) $_POST['_eh_status']) : 'open';
        $address    = isset($_POST['_eh_address']) ? sanitize_text_field((string) $_POST['_eh_address']) : '';
        $organizer  = isset($_POST['_eh_organizer']) ? sanitize_text_field((string) $_POST['_eh_organizer']) : '';
        $staff      = isset($_POST['_eh_staff']) ? sanitize_text_field((string) $_POST['_eh_staff']) : '';
        $price      = isset($_POST['_eh_price']) ? floatval((string) $_POST['_eh_price']) : '';
        $no_show_fee= isset($_POST['_eh_no_show_fee']) ? floatval((string) $_POST['_eh_no_show_fee']) : '';
        $ticket_note = isset($_POST['_eh_ticket_note']) ? wp_kses_post((string) $_POST['_eh_ticket_note']) : '';
        $color      = isset($_POST['_eh_color']) ? sanitize_hex_color((string) $_POST['_eh_color']) : '#2271b1';
        $use_local_builder = isset($_POST['_eh_use_local_builder']) ? 1 : 0;
        $builder_local = isset($_POST['_eh_builder_sections']) ? (string) $_POST['_eh_builder_sections'] : '';
        $allowed_sections = ['status','hero','info','content','form','custom1','custom2','quote','faq','agenda','buttons','gallery','card','textblock'];
        $clean_builder = '';
        if ($builder_local !== '') {
            $decoded = json_decode($builder_local, true);
            $san = [];
            if (is_array($decoded)) {
                foreach ($decoded as $row) {
                    $id = isset($row['id']) ? sanitize_key((string) $row['id']) : '';
                    if ($id === '' || !in_array($id, $allowed_sections, true)) {
                        continue;
                    }
                    $san[] = [
                        'id' => $id,
                        'title' => isset($row['title']) ? sanitize_text_field((string) $row['title']) : $id,
                        'accent' => isset($row['accent']) ? sanitize_text_field((string) $row['accent']) : '',
                        'bg' => isset($row['bg']) ? sanitize_text_field((string) $row['bg']) : '',
                        'heading' => isset($row['heading']) ? sanitize_text_field((string) $row['heading']) : '',
                        'cta' => isset($row['cta']) ? sanitize_text_field((string) $row['cta']) : '',
                        'gradient' => isset($row['gradient']) ? sanitize_text_field((string) $row['gradient']) : '',
                        'bgImage' => isset($row['bgImage']) ? esc_url_raw((string) $row['bgImage']) : '',
                        'fontSize' => isset($row['fontSize']) ? (int) $row['fontSize'] : 16,
                        'padding' => isset($row['padding']) ? (int) $row['padding'] : 16,
                        'paddingMobile' => isset($row['paddingMobile']) ? (int) $row['paddingMobile'] : (isset($row['padding']) ? (int) $row['padding'] : 14),
                        'variant' => isset($row['variant']) ? sanitize_text_field((string) $row['variant']) : 'default',
                        'body' => isset($row['body']) ? wp_kses_post((string) $row['body']) : '',
                    ];
                }
            }
            if ($san) {
                $clean_builder = wp_json_encode($san);
            }
        }
        $general = Settings::get_general();
        $colleagues_global = isset($general['colleagues']) && is_array($general['colleagues']) ? $general['colleagues'] : [];
        $colleagues_selected_raw = isset($_POST['eh_colleagues_selected']) ? (array) $_POST['eh_colleagues_selected'] : [];
        $colleagues = array_values(array_map('intval', $colleagues_selected_raw));
        $use_builtin_page_meta = isset($_POST['_eh_use_builtin_page']) ? (int) $_POST['_eh_use_builtin_page'] : 0;
        // Fallback: map legacy structure to IDs if available and none selected.
        if (!$colleagues) {
            $legacy_existing = get_post_meta($post_id, '_eh_colleagues', true);
            if (is_array($legacy_existing) && isset($legacy_existing[0]) && is_array($legacy_existing[0]) && array_key_exists('first_name', $legacy_existing[0])) {
                $colleagues = $this->map_legacy_colleagues_to_ids($legacy_existing, $colleagues_global);
            }
        }

        // Convert HTML5 datetime-local (Y-m-d\TH:i) to MySQL datetime
        $ds_store = $date_start ? gmdate('Y-m-d H:i:00', strtotime($date_start)) : '';
        $de_store = $date_end ? gmdate('Y-m-d H:i:00', strtotime($date_end)) : '';
        $bo_store = $booking_open ? gmdate('Y-m-d H:i:00', strtotime($booking_open)) : '';
        $bc_store = $booking_close ? gmdate('Y-m-d H:i:00', strtotime($booking_close)) : '';

        update_post_meta($post_id, '_eh_date_start', $ds_store);
        update_post_meta($post_id, '_eh_date_end', $de_store);
        update_post_meta($post_id, '_eh_location', $location);
        update_post_meta($post_id, '_eh_is_online', $is_online);
        update_post_meta($post_id, '_eh_show_on_site', $show_on_site);
        update_post_meta($post_id, '_eh_online_link', $online_link);
        update_post_meta($post_id, '_eh_capacity', $capacity);
        update_post_meta($post_id, '_eh_enable_module', $enable_module_meta === '' ? 1 : (int) !empty($_POST['_eh_enable_module']));
        update_post_meta($post_id, '_eh_language', $language);
        update_post_meta($post_id, '_eh_target_audience', $audience);
        update_post_meta($post_id, '_eh_status', $status);
        update_post_meta($post_id, '_eh_booking_open', $bo_store);
        update_post_meta($post_id, '_eh_booking_close', $bc_store);
        update_post_meta($post_id, '_eh_address', $address);
        update_post_meta($post_id, '_eh_organizer', $organizer);
        update_post_meta($post_id, '_eh_staff', $staff);
        update_post_meta($post_id, '_eh_price', $price);
        update_post_meta($post_id, '_eh_no_show_fee', $no_show_fee);
        update_post_meta($post_id, '_eh_ticket_note', $ticket_note);
        update_post_meta($post_id, '_eh_color', $color ?: '#2271b1');
        update_post_meta($post_id, '_eh_use_builtin_page', $use_builtin_page_meta ? 1 : 0);
        update_post_meta($post_id, '_eh_use_local_builder', $use_local_builder ? 1 : 0);
        update_post_meta($post_id, '_eh_builder_sections', $clean_builder !== '' ? $clean_builder : '');
        update_post_meta($post_id, '_eh_colleagues', $colleagues);
        // E-mail sjablonen per fase
        $confirm = isset($_POST['_eh_email_confirm_templates']) ? array_map('intval', (array) $_POST['_eh_email_confirm_templates']) : [];
        $remind  = isset($_POST['_eh_email_reminder_templates']) ? array_map('intval', (array) $_POST['_eh_email_reminder_templates']) : [];
        $follow  = isset($_POST['_eh_email_followup_templates']) ? array_map('intval', (array) $_POST['_eh_email_followup_templates']) : [];
        $waitlist = isset($_POST['_eh_email_waitlist_templates']) ? array_map('intval', (array) $_POST['_eh_email_waitlist_templates']) : [];
        update_post_meta($post_id, '_eh_email_confirm_templates', $confirm);
        update_post_meta($post_id, '_eh_email_reminder_templates', $remind);
        update_post_meta($post_id, '_eh_email_followup_templates', $follow);
        update_post_meta($post_id, '_eh_email_waitlist_templates', $waitlist);
        $reminder_offset = isset($_POST['_eh_reminder_offset_days']) && $_POST['_eh_reminder_offset_days'] !== '' ? max(0, (int) $_POST['_eh_reminder_offset_days']) : '';
        $followup_offset = isset($_POST['_eh_followup_offset_hours']) && $_POST['_eh_followup_offset_hours'] !== '' ? max(0, (int) $_POST['_eh_followup_offset_hours']) : '';
        update_post_meta($post_id, '_eh_reminder_offset_days', $reminder_offset);
        update_post_meta($post_id, '_eh_followup_offset_hours', $followup_offset);
        $custom_email_fields = ['confirmation','reminder','followup','waitlist','waitlist_promotion'];
        foreach ($custom_email_fields as $key) {
            $subj = isset($_POST['_eh_email_custom_' . $key . '_subject']) ? wp_kses_post((string) $_POST['_eh_email_custom_' . $key . '_subject']) : '';
            $body = isset($_POST['_eh_email_custom_' . $key . '_body']) ? wp_kses_post((string) $_POST['_eh_email_custom_' . $key . '_body']) : '';
            update_post_meta($post_id, '_eh_email_custom_' . $key . '_subject', $subj);
            update_post_meta($post_id, '_eh_email_custom_' . $key . '_body', $body);
        }
        // Extra velden bewaren
        $extra_fields = [];
        if (isset($_POST['eh_extra_fields']) && is_array($_POST['eh_extra_fields'])) {
            $ef = $_POST['eh_extra_fields'];
            $labels = $ef['label'] ?? [];
            $slugs  = $ef['slug'] ?? [];
            $types  = $ef['type'] ?? [];
            $options_raw = $ef['options'] ?? [];
            $required_raw = $ef['required'] ?? [];
            foreach ($labels as $i => $label) {
                $slug_raw = isset($slugs[$i]) ? (string) $slugs[$i] : '';
                $slug = sanitize_key($slug_raw);
                if ($slug === '' && $label !== '') {
                    $slug = sanitize_key(sanitize_title($label));
                }
                if ($slug === '') {
                    continue;
                }
                $type = isset($types[$i]) ? sanitize_key((string) $types[$i]) : 'text';
                $allowed = ['text','textarea','select'];
                if (!in_array($type, $allowed, true)) {
                    $type = 'text';
                }
                $opts = [];
                if ($type === 'select' && isset($options_raw[$i])) {
                    $lines = preg_split('/\r\n|\r|\n/', (string) $options_raw[$i]);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if ($line !== '') {
                            $opts[] = $line;
                        }
                    }
                }
                $extra_fields[] = [
                    'label' => sanitize_text_field((string) $label) ?: $slug,
                    'slug' => $slug,
                    'type' => $type,
                    'required' => isset($required_raw[$i]),
                    'options' => $opts,
                ];
            }
        }
        update_post_meta($post_id, '_eh_extra_fields', $extra_fields);
        $hide_fields = isset($_POST['eh_hide_fields']) && is_array($_POST['eh_hide_fields'])
            ? array_values(array_filter(array_map('sanitize_key', (array) $_POST['eh_hide_fields'])))
            : [];
        update_post_meta($post_id, '_eh_form_hide_fields', $hide_fields);

        // Recalculate capacity status and promote waitlist if needed after admin changes.
        $this->registrations->sync_session_status($post_id);

        $this->debug_log('save_meta_boxes_done', [
            'post_id'    => $post_id,
            'date_start' => $ds_store,
            'date_end'   => $de_store,
            'capacity'   => $capacity,
            'status'     => $status,
        ]);
    }
    public function intercept_wp_redirect(string $location, int $status): string
    {
        $post_id = isset($_POST['post_ID']) ? (int) $_POST['post_ID'] : 0;
        if (!$post_id) {
            return $location;
        }
        if (get_post_type($post_id) !== $this->get_cpt()) {
            return $location;
        }

        $forced = add_query_arg(
            [
                'post'   => $post_id,
                'action' => 'edit',
                'message'=> isset($_GET['message']) ? (int) $_GET['message'] : 1,
            ],
            admin_url('post.php')
        );

        $this->debug_log('wp_redirect', [
            'status'   => $status,
            'original' => $location,
            'forced'   => $forced,
        ]);

        return $forced;
    }

    private function debug_log(string $tag, array $payload): void
    {
        if (!defined('EVENT_HUB_DEBUG') && (!defined('WP_DEBUG') || !WP_DEBUG)) {
            return;
        }
        $path = trailingslashit(WP_CONTENT_DIR) . 'event-hub-debug.log';
        $line = '[' . gmdate('c') . "] {$tag} " . wp_json_encode($payload) . PHP_EOL;
        file_put_contents($path, $line, FILE_APPEND);
    }

    /**
     * Map legacy colleague entries (with names) to global colleague IDs.
     *
     * @param array $legacy Array of arrays with first_name/last_name keys.
     * @param array $global List of global colleagues.
     * @return array<int>
     */
    private function map_legacy_colleagues_to_ids(array $legacy, array $global): array
    {
        if (!$legacy || !$global) {
            return [];
        }
        $name_to_id = [];
        foreach ($global as $id => $c) {
            $name = strtolower(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')));
            if ($name !== '') {
                $name_to_id[$name] = (int) $id;
            }
        }
        $ids = [];
        foreach ($legacy as $row) {
            $name = strtolower(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
            if ($name && isset($name_to_id[$name])) {
                $ids[] = $name_to_id[$name];
            }
        }
        return array_values(array_unique($ids));
    }
}








