<?php
namespace EventHub;

defined('ABSPATH') || exit;

class CPT_Session
{
    public const CPT = 'eh_session';
    public const TAX_TYPE = 'eh_session_type';

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

        ?>
        <div class="eh-admin eh-session-meta">
            <div class="eh-section">
                <h2><?php echo esc_html__('Planning', 'event-hub'); ?></h2>
                <div class="eh-form-two-col">
                    <div class="field">
                        <label for="_eh_date_start"><?php echo esc_html__('Startdatum en -uur', 'event-hub'); ?></label>
                        <input type="datetime-local" id="_eh_date_start" name="_eh_date_start" value="<?php echo $ds_val; ?>" required>
                    </div>
                    <div class="field">
                        <label for="_eh_date_end"><?php echo esc_html__('Einddatum en -uur', 'event-hub'); ?></label>
                        <input type="datetime-local" id="_eh_date_end" name="_eh_date_end" value="<?php echo $de_val; ?>">
                    </div>
                    <div class="field">
                        <label for="_eh_booking_open"><?php echo esc_html__('Inschrijvingen openen', 'event-hub'); ?></label>
                        <input type="datetime-local" id="_eh_booking_open" name="_eh_booking_open" value="<?php echo $bo_val; ?>">
                    </div>
                    <div class="field">
                        <label for="_eh_booking_close"><?php echo esc_html__('Inschrijvingen sluiten', 'event-hub'); ?></label>
                        <input type="datetime-local" id="_eh_booking_close" name="_eh_booking_close" value="<?php echo $bc_val; ?>">
                    </div>
                </div>
            </div>
            <p>
                <label for="_eh_date_end"><strong><?php echo esc_html__('Einddatum en -uur', 'event-hub'); ?></strong></label><br/>
                <input type="datetime-local" id="_eh_date_end" name="_eh_date_end" value="<?php echo $de_val; ?>" />
            </p>
            <p>
                <label for="_eh_booking_open"><strong><?php echo esc_html__('Inschrijvingen openen', 'event-hub'); ?></strong></label><br/>
                <input type="datetime-local" id="_eh_booking_open" name="_eh_booking_open" value="<?php echo $bo_val; ?>" />
            </p>
            <p>
                <label for="_eh_booking_close"><strong><?php echo esc_html__('Inschrijvingen sluiten', 'event-hub'); ?></strong></label><br/>
                <input type="datetime-local" id="_eh_booking_close" name="_eh_booking_close" value="<?php echo $bc_val; ?>" />
            </p>
            <p>
                <label for="_eh_location"><strong><?php echo esc_html__('Locatie', 'event-hub'); ?></strong></label><br/>
                <input type="text" id="_eh_location" name="_eh_location" value="<?php echo esc_attr($location); ?>" class="regular-text" />
            </p>
            <p>
                <label><input type="checkbox" name="_eh_is_online" value="1" <?php checked($is_online); ?> /> <?php echo esc_html__('Online sessie', 'event-hub'); ?></label>
            </p>
            <p>
                <label><input type="checkbox" name="_eh_show_on_site" value="1" <?php checked($show_on_site); ?> /> <?php echo esc_html__('Toon in eventlijsten', 'event-hub'); ?></label>
            </p>
            <p class="full">
                <label for="_eh_online_link"><strong><?php echo esc_html__('Onlinelink (URL)', 'event-hub'); ?></strong></label><br/>
                <input type="url" id="_eh_online_link" name="_eh_online_link" value="<?php echo esc_attr($online_link); ?>" class="regular-text" />
            </p>
            <p>
                <label for="_eh_capacity"><strong><?php echo esc_html__('Capaciteit', 'event-hub'); ?></strong></label><br/>
                <input type="number" id="_eh_capacity" name="_eh_capacity" value="<?php echo esc_attr($capacity); ?>" min="0" />
            </p>
            <p>
                <label for="_eh_language"><strong><?php echo esc_html__('Taal', 'event-hub'); ?></strong></label><br/>
                <input type="text" id="_eh_language" name="_eh_language" value="<?php echo esc_attr($language); ?>" placeholder="nl, fr, en" />
            </p>
            <p class="full">
                <label for="_eh_target_audience"><strong><?php echo esc_html__('Doelgroep', 'event-hub'); ?></strong></label><br/>
                <input type="text" id="_eh_target_audience" name="_eh_target_audience" value="<?php echo esc_attr($audience); ?>" class="regular-text" />
            </p>
            <p class="full">
                <label for="_eh_address"><strong><?php echo esc_html__('Adres', 'event-hub'); ?></strong></label><br/>
                <input type="text" id="_eh_address" name="_eh_address" value="<?php echo esc_attr($address); ?>" class="regular-text" />
            </p>
            <p>
                <label for="_eh_organizer"><strong><?php echo esc_html__('Organisator', 'event-hub'); ?></strong></label><br/>
                <input type="text" id="_eh_organizer" name="_eh_organizer" value="<?php echo esc_attr($organizer); ?>" class="regular-text" />
            </p>
            <p>
                <label for="_eh_staff"><strong><?php echo esc_html__('Medewerkers (komma-gescheiden)', 'event-hub'); ?></strong></label><br/>
                <input type="text" id="_eh_staff" name="_eh_staff" value="<?php echo esc_attr($staff); ?>" class="regular-text" />
            </p>
            <p>
                <label for="_eh_price"><strong><?php echo esc_html__('Prijs (optioneel)', 'event-hub'); ?></strong></label><br/>
                <input type="number" step="0.01" id="_eh_price" name="_eh_price" value="<?php echo esc_attr($price); ?>" />
            </p>
            <p>
                <label for="_eh_ticket_note"><strong><?php echo esc_html__('Ticket- of tariefinfo', 'event-hub'); ?></strong></label><br/>
                <textarea id="_eh_ticket_note" name="_eh_ticket_note" class="large-text" rows="3" placeholder="<?php echo esc_attr__('Beschrijving van tickettypes of kortingen', 'event-hub'); ?>"><?php echo esc_textarea($ticket_note); ?></textarea>
            </p>
            <p>
                <label for="_eh_no_show_fee"><strong><?php echo esc_html__('No-showkost (optioneel)', 'event-hub'); ?></strong></label><br/>
                <input type="number" step="0.01" id="_eh_no_show_fee" name="_eh_no_show_fee" value="<?php echo esc_attr($no_show_fee); ?>" />
            </p>
            <p>
                <label for="_eh_color"><strong><?php echo esc_html__('Accentkleur', 'event-hub'); ?></strong></label><br/>
                <input type="color" id="_eh_color" name="_eh_color" value="<?php echo esc_attr($color); ?>" />
            </p>
            <p>
                <label for="_eh_status"><strong><?php echo esc_html__('Status', 'event-hub'); ?></strong></label><br/>
                <select id="_eh_status" name="_eh_status">
                    <?php
                    $status_labels = [
                        'open' => __('Open', 'event-hub'),
                        'full' => __('Volzet', 'event-hub'),
                        'cancelled' => __('Geannuleerd', 'event-hub'),
                        'closed' => __('Gesloten', 'event-hub'),
                    ];
                    foreach ($status_labels as $val => $label): ?>
                        <option value="<?php echo esc_attr($val); ?>" <?php selected($status, $val); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <div class="eh-section">
                <h2><?php echo esc_html__('Automatische e-mails', 'event-hub'); ?></h2>
                <p><?php echo esc_html__('Kies welke sjablonen gebruikt worden. Zonder selectie wordt er niets verzonden.', 'event-hub'); ?></p>
                <div class="eh-form-two-col">
                    <?php
                    $emails = get_posts([
                        'post_type' => 'eh_email',
                        'numberposts' => -1,
                        'orderby' => 'title',
                        'order' => 'ASC',
                    ]);
                    $sel_confirm = (array) get_post_meta($post->ID, '_eh_email_confirm_templates', true);
                    $sel_remind  = (array) get_post_meta($post->ID, '_eh_email_reminder_templates', true);
                    $sel_follow  = (array) get_post_meta($post->ID, '_eh_email_followup_templates', true);

                    $render_select = function (string $name, array $selected, string $label) use ($emails) {
                        echo '<div class="field full">';
                        echo '<label><strong>' . esc_html($label) . '</strong></label>';
                        echo '<select name="' . esc_attr($name) . '[]" multiple class="eh-template-select">';
                        foreach ($emails as $e) {
                            $sel = in_array((string) $e->ID, array_map('strval', $selected), true) ? 'selected' : '';
                            echo '<option value="' . esc_attr((string) $e->ID) . '" ' . $sel . '>' . esc_html($e->post_title) . '</option>';
                        }
                        echo '</select>';
                        echo '</div>';
                    };

                    $render_select('_eh_email_confirm_templates', $sel_confirm, __('Bevestiging (na inschrijving)', 'event-hub'));
                    $render_select('_eh_email_reminder_templates', $sel_remind, __('Herinnering (voor start)', 'event-hub'));
                    $render_select('_eh_email_followup_templates', $sel_follow, __('Nadien (na afloop)', 'event-hub'));
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function save_meta_boxes(int $post_id): void
    {
        if (!isset($_POST['octo_session_meta_nonce']) || !wp_verify_nonce(sanitize_text_field((string) $_POST['octo_session_meta_nonce']), 'octo_session_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        if (get_post_type($post_id) !== $this->get_cpt()) {
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
        update_post_meta($post_id, '_eh_email_confirm_templates', $confirm);
        update_post_meta($post_id, '_eh_email_reminder_templates', $remind);
        update_post_meta($post_id, '_eh_email_followup_templates', $follow);
    }
}
