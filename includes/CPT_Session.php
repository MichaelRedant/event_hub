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
        // If using external CPT, do not register our own
        if (\EventHub\Settings::use_external_cpt()) {
            return;
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

    }

    public function register_admin_columns(): void
    {
        $cpt = $this->get_cpt();
        add_filter("manage_edit-{$cpt}_columns", [$this, 'add_admin_columns']);
        add_action("manage_{$cpt}_posts_custom_column", [$this, 'render_admin_column'], 10, 2);
        add_action('admin_head', [$this, 'admin_columns_styles']);
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
        if (defined('ELEMENTOR_PRO_VERSION')) {
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
        $price = get_post_meta($post->ID, '_eh_price', true);
        $no_show_fee = get_post_meta($post->ID, '_eh_no_show_fee', true);
        $show_on_site_meta = get_post_meta($post->ID, '_eh_show_on_site', true);
        $show_on_site = ($show_on_site_meta === '') ? true : (bool) $show_on_site_meta;
        $color = get_post_meta($post->ID, '_eh_color', true) ?: '#2271b1';
        $ticket_note = get_post_meta($post->ID, '_eh_ticket_note', true);

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
                        </div>
                    </div>
                </div>
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
                            <div class="field">
                                <label for="_eh_staff"><?php echo esc_html__('Medewerkers (komma-gescheiden)', 'event-hub'); ?></label>
                                <input type="text" id="_eh_staff" name="_eh_staff" value="<?php echo esc_attr($staff); ?>" placeholder="<?php echo esc_attr__('vb. Sarah, Tim, extern spreker…', 'event-hub'); ?>">
                            </div>
                        </div>
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
                    echo '<div class="eh-form-two-col">';
                    $render_select = function (string $name, array $selected, string $label, string $helper) use ($emails) {
                        echo '<div class="field full">';
                        echo '<label><strong>' . esc_html($label) . '</strong></label>';
                        echo '<select name="' . esc_attr($name) . '[]" multiple class="eh-template-select">';
                        foreach ($emails as $e) {
                            $sel = in_array((string) $e->ID, array_map('strval', $selected), true) ? 'selected' : '';
                            echo '<option value="' . esc_attr((string) $e->ID) . '" ' . $sel . '>' . esc_html($e->post_title) . '</option>';
                        }
                        echo '</select>';
                        echo '<p class="description">' . esc_html($helper) . '</p>';
                        $render_select(
                        '_eh_email_waitlist_templates',
                        $sel_waitlist,
                        __('Wachtlijst promotie', 'event-hub'),
                        __('Verwittigt deelnemers wanneer ze van de wachtlijst komen.', 'event-hub')
                    );
                    echo '</div>';
                    };

                    $render_select(
                        '_eh_email_confirm_templates',
                        $sel_confirm,
                        __('Bevestiging (na inschrijving)', 'event-hub'),
                        __('Wordt onmiddellijk verstuurd.', 'event-hub')
                    );
                    $render_select(
                        '_eh_email_reminder_templates',
                        $sel_remind,
                        __('Herinnering (voor de start)', 'event-hub'),
                        __('Gepland volgens de timing in het sjabloon.', 'event-hub')
                    );
                    $render_select(
                        '_eh_email_followup_templates',
                        $sel_follow,
                        __('Nadien (aftermovie, survey, …)', 'event-hub'),
                        __('Versturen we nadat de sessie voorbij is.', 'event-hub')
                    );
                    echo '</div>';
                }
                ?>
            </div>
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
            $label = $capacity > 0 ? sprintf('%d / %d', $booked, $capacity) : (string) $booked;
            $percentage = $capacity > 0 ? min(100, max(0, round(($booked / max(1, $capacity)) * 100))) : 0;
            echo '<div class="eh-progress"><span class="eh-progress-bar" style="width:' . esc_attr((string) $percentage) . '%;"></span></div>';
            echo '<span class="eh-progress-label">' . esc_html($label) . '</span>';
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
        .eh-badge-pill{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.03em}
        .eh-badge-pill.status-open{background:#e3f4e8;color:#256029}
        .eh-badge-pill.status-full{background:#fdecea;color:#ab1f24}
        .eh-badge-pill.status-closed{background:#f0f0f0;color:#444}
        .eh-badge-pill.status-cancelled{background:#fce7f1;color:#8e2453}
        .eh-badge-pill.status-online{background:#e3f2fd;color:#0d47a1}
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
        // E-mail sjablonen per fase
        $confirm = isset($_POST['_eh_email_confirm_templates']) ? array_map('intval', (array) $_POST['_eh_email_confirm_templates']) : [];
        $remind  = isset($_POST['_eh_email_reminder_templates']) ? array_map('intval', (array) $_POST['_eh_email_reminder_templates']) : [];
        $follow  = isset($_POST['_eh_email_followup_templates']) ? array_map('intval', (array) $_POST['_eh_email_followup_templates']) : [];
        $waitlist = isset($_POST['_eh_email_waitlist_templates']) ? array_map('intval', (array) $_POST['_eh_email_waitlist_templates']) : [];
        update_post_meta($post_id, '_eh_email_confirm_templates', $confirm);
        update_post_meta($post_id, '_eh_email_reminder_templates', $remind);
        update_post_meta($post_id, '_eh_email_followup_templates', $follow);
        update_post_meta($post_id, '_eh_email_waitlist_templates', $waitlist);

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

}
