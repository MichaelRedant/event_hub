<?php
namespace EventHub;

defined('ABSPATH') || exit;

class Settings
{
    public const OPTION = 'event_hub_email_settings';
    public const OPTION_GENERAL = 'event_hub_general_settings';

    public function register_settings(): void
    {
        // E-mail instellingen
        register_setting(self::OPTION, self::OPTION, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'default' => [],
        ]);

        add_settings_section('octo_emails_main', __('E-mailinstellingen', 'event-hub'), function () {
            echo '<p>' . esc_html__('Stel de e-mailafzender en sjablonen in voor inschrijvingen en herinneringen.', 'event-hub') . '</p>';
        }, self::OPTION);

        $fields = [
            ['from_name', __('Afzendernaam', 'event-hub'), 'text'],
            ['from_email', __('Afzender e-mail', 'event-hub'), 'email'],
            ['mail_transport', __('Mailtransport', 'event-hub'), 'select'],
            ['reminder_offset_days', __('Herinnering (dagen voor start)', 'event-hub'), 'number'],
            ['followup_offset_hours', __('Nadien (uren na einde)', 'event-hub'), 'number'],
            ['custom_placeholders_raw', __('Eigen placeholders', 'event-hub'), 'textarea'],
        ];

        foreach ($fields as [$key, $label, $type]) {
            add_settings_field($key, $label, function () use ($key, $type) {
                $opts = get_option(self::OPTION, []);
                $val = $opts[$key] ?? '';
                $name = self::OPTION . "[{$key}]";
                if ($type === 'textarea') {
                    echo '<textarea class="large-text code" rows="6" name="' . esc_attr($name) . '">' . esc_textarea((string) $val) . '</textarea>';
                    echo '<p class="description">' . esc_html__('ÃƒÂ¢Ã¢â‚¬ÂÃ…â€œÃƒÆ’Ã‚Â«ÃƒÂ¢Ã¢â‚¬ÂÃ…â€œÃƒâ€šÃ‚Â®n placeholder per lijn, formaat: mijn_token=Mijn waarde. Gebruik accolades { } om de tokennaam te markeren.', 'event-hub') . '</p>';
                } elseif ($type === 'number') {
                    echo '<input type="number" min="0" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '" />';
                } elseif ($type === 'select' && $key === 'mail_transport') {
                    $options = [
                        'php' => __('PHP mail (standaard)', 'event-hub'),
                        'smtp_plugin' => __('SMTP via mailgateway plugin', 'event-hub'),
                    ];
                    echo '<select name="' . esc_attr($name) . '">';
                    foreach ($options as $opt_val => $opt_label) {
                        echo '<option value="' . esc_attr($opt_val) . '"' . selected($val, $opt_val, false) . '>' . esc_html($opt_label) . '</option>';
                    }
                    echo '</select>';
                    echo '<p class="description">' . esc_html__('Kies PHP mail (hosting) of laat een SMTP/mailgateway plugin het transport overnemen.', 'event-hub') . '</p>';
                } else {
                    echo '<input type="' . esc_attr($type) . '" class="regular-text" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '" />';
                }
            }, self::OPTION, 'octo_emails_main');
        }

        // Algemene instellingen
        register_setting(self::OPTION_GENERAL, self::OPTION_GENERAL, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_general_settings'],
            'default' => [
                'use_external_cpt' => 0,
                'cpt_slug' => 'eh_session',
                'tax_slug' => 'eh_session_type',
                'single_layout' => 'modern',
                'single_custom_enabled' => 0,
                'single_custom_css' => '',
                'single_custom_js' => '',
                'single_custom_html_before_hero' => '',
                'single_custom_html_after_hero' => '',
                'single_custom_html_before_form' => '',
                'single_custom_html_after_form' => '',
                'single_custom_html_after_details' => '',
                'single_custom_code' => '',
                'single_builder_sections' => '[]',
                'enable_recaptcha' => 0,
                'recaptcha_provider' => 'google_recaptcha_v3',
                'recaptcha_site_key' => '',
                'recaptcha_secret_key' => '',
                'recaptcha_score' => 0.5,
                'cpt_menu_label' => 'Evenementen',
                'cpt_singular_label' => 'Evenement',
                'cpt_menu_icon' => 'dashicons-calendar-alt',
                'cpt_menu_position' => 20,
            ],
        ]);

        add_settings_section('eh_general_main', __('Algemeen', 'event-hub'), function () {
            echo '<p>' . esc_html__('Bepaal welke CPT en taxonomie Event Hub gebruikt voor evenementen.', 'event-hub') . '</p>';
        }, self::OPTION_GENERAL);

        add_settings_field('use_external_cpt', __('Bestaand CPT gebruiken', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $name = self::OPTION_GENERAL . '[use_external_cpt]';
            $checked = !empty($opts['use_external_cpt']) ? 'checked' : '';
            echo '<label><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . $checked . ' /> ' . esc_html__('Gebruik een bestaande evenementen-CPT (JetEngine, CPT UI, Pods, ...). Event Hub registreert dan geen eigen CPT.', 'event-hub') . '</label>';
            echo '<p class="description">' . esc_html__('Handig wanneer je op verschillende sites met andere slugs werkt en alles uniform wilt houden.', 'event-hub') . '</p>';
        }, self::OPTION_GENERAL, 'eh_general_main');

        add_settings_field('cpt_slug', __('Event CPT-slug', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $val = $opts['cpt_slug'] ?? 'eh_session';
            $name = self::OPTION_GENERAL . '[cpt_slug]';
            $post_types = get_post_types(['show_ui' => true], 'objects');
            $datalist_id = 'eh_cpt_list';
            echo '<input type="text" class="regular-text" list="' . esc_attr($datalist_id) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '" placeholder="bv. events, trainingen, webinars" />';
            echo '<datalist id="' . esc_attr($datalist_id) . '">';
            foreach ($post_types as $pt) {
                $label = isset($pt->labels->singular_name) ? $pt->labels->singular_name : $pt->name;
                echo '<option value="' . esc_attr($pt->name) . '">' . esc_html($label) . '</option>';
            }
            echo '</datalist>';
            echo '<p class="description">' . esc_html__('Begin te typen om beschikbare post types te zoeken. Kies het CPT waarin je events staan.', 'event-hub') . '</p>';
        }, self::OPTION_GENERAL, 'eh_general_main');

        add_settings_field('cpt_menu_label', __('CPT menu-label', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $val = $opts['cpt_menu_label'] ?? 'Evenementen';
            $name = self::OPTION_GENERAL . '[cpt_menu_label]';
            echo '<input type="text" class="regular-text" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '" placeholder="bv. Evenementen" />';
            echo '<p class="description">' . esc_html__('Tekst in het admin-menu.', 'event-hub') . '</p>';
        }, self::OPTION_GENERAL, 'eh_general_main');

        add_settings_field('cpt_singular_label', __('CPT enkelvoud label', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $val = $opts['cpt_singular_label'] ?? 'Evenement';
            $name = self::OPTION_GENERAL . '[cpt_singular_label]';
            echo '<input type="text" class="regular-text" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '" placeholder="bv. Evenement" />';
            echo '<p class="description">' . esc_html__('Wordt gebruikt in metaboxen en titels.', 'event-hub') . '</p>';
        }, self::OPTION_GENERAL, 'eh_general_main');

        add_settings_field('cpt_menu_icon', __('CPT icoon (dashicon)', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $val = $opts['cpt_menu_icon'] ?? 'dashicons-calendar-alt';
            $name = self::OPTION_GENERAL . '[cpt_menu_icon]';
            $options = [
                'dashicons-calendar-alt' => __('Kalender', 'event-hub'),
                'dashicons-megaphone' => __('Megaphone', 'event-hub'),
                'dashicons-tickets' => __('Tickets', 'event-hub'),
                'dashicons-groups' => __('Groepen', 'event-hub'),
                'dashicons-welcome-learn-more' => __('Leren', 'event-hub'),
                'dashicons-clipboard' => __('Clipboard', 'event-hub'),
            ];
            echo '<select name="' . esc_attr($name) . '">';
            foreach ($options as $opt_val => $opt_label) {
                echo '<option value="' . esc_attr($opt_val) . '"' . selected($val, $opt_val, false) . '>' . esc_html($opt_label) . '</option>';
            }
            echo '</select>';
            echo '<p class="description">' . esc_html__('Kies een Dashicon voor het menu.', 'event-hub') . '</p>';
        }, self::OPTION_GENERAL, 'eh_general_main');

        add_settings_field('cpt_menu_position', __('CPT menu-positie', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $val = isset($opts['cpt_menu_position']) ? (int) $opts['cpt_menu_position'] : 20;
            $name = self::OPTION_GENERAL . '[cpt_menu_position]';
            echo '<input type="number" min="2" max="99" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '" />';
            echo '<p class="description">' . esc_html__('Lagere cijfers komen hoger in het admin-menu. Voorbeeld: 5 = boven Berichten, 20 = standaard.', 'event-hub') . '</p>';
        }, self::OPTION_GENERAL, 'eh_general_main');

        add_settings_field('tax_slug', __('Eventtype-taxonomie-slug', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $val = $opts['tax_slug'] ?? 'eh_session_type';
            $name = self::OPTION_GENERAL . '[tax_slug]';
            $current_cpt = $opts['cpt_slug'] ?? 'eh_session';
            $tax_objects = function_exists('get_object_taxonomies') ? get_object_taxonomies($current_cpt, 'objects') : [];
            if (empty($tax_objects)) {
                $tax_objects = get_taxonomies(['show_ui' => true], 'objects');
            }
            $datalist_id = 'eh_tax_list';
            echo '<input type="text" class="regular-text" list="' . esc_attr($datalist_id) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '" placeholder="bv. event_type, session_type" />';
            echo '<datalist id="' . esc_attr($datalist_id) . '">';
            foreach ($tax_objects as $tx) {
                $label = isset($tx->labels->singular_name) ? $tx->labels->singular_name : $tx->name;
                echo '<option value="' . esc_attr($tx->name) . '">' . esc_html($label) . '</option>';
            }
            echo '</datalist>';
            echo '<p class="description">' . esc_html__('Begin te typen om beschikbare taxonomieen te zoeken. Bestaat het niet, dan maakt Event Hub het aan en koppelt het aan het CPT.', 'event-hub') . '</p>';
        }, self::OPTION_GENERAL, 'eh_general_main');

        add_settings_field('single_layout', __('Single event layout', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $val = $opts['single_layout'] ?? 'modern';
            $name = self::OPTION_GENERAL . '[single_layout]';
            $choices = [
                'modern' => __('Modern (hero + sticky formulier)', 'event-hub'),
                'compact' => __('Compact (stapel, lichtgewicht)', 'event-hub'),
            ];
            echo '<select name="' . esc_attr($name) . '">';
            foreach ($choices as $key => $label) {
                echo '<option value="' . esc_attr($key) . '"' . selected($val, $key, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
            echo '<p class="description">' . esc_html__('Kies de standaard layout voor de single event pagina. Thema-overrides blijven mogelijk.', 'event-hub') . '</p>';
        }, self::OPTION_GENERAL, 'eh_general_main');

        // Eén blok voor HTML/CSS/JS met live preview (zwevend rechts) + placeholders lijst.
        add_settings_field('single_custom_code', __('Eigen template (HTML/CSS/JS)', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $code = $opts['single_custom_code'] ?? '';
            $name = self::OPTION_GENERAL . '[single_custom_code]';
            $placeholders = \EventHub\Emails::get_placeholder_reference();
            echo '<div style="display:flex;gap:20px;align-items:flex-start;">';
            echo '<div style="flex:1 1 55%;">';
            echo '<textarea class="large-text code" rows="18" name="' . esc_attr($name) . '" id="eh-custom-code" placeholder="<!-- HTML -->&#10;<style>/* CSS */</style>&#10;<script>// JS</script>">' . esc_textarea((string) $code) . '</textarea>';
            echo '<p class="description">' . esc_html__('Gebruik placeholders voor dynamische data. Dubbele accolades ({{title}}) of dezelfde tokens als in e-mailtemplates (bijv. {event_title}, {event_date}, {event_location}, {cta_link}). Inline <style>/<script> is toegestaan.', 'event-hub') . '</p>';
            echo '<details style="margin-top:10px;"><summary style="cursor:pointer;font-weight:600;">' . esc_html__('Beschikbare placeholders', 'event-hub') . '</summary>';
            echo '<ul style="margin:10px 0 0 16px;columns:2;gap:12px;font-size:12px;">';
            foreach ($placeholders as $token => $desc) {
                echo '<li><code>' . esc_html($token) . '</code> — ' . esc_html($desc) . '</li>';
            }
            // Extra dubbele accolades varianten
            $extra = [
                '{{title}}' => __('Event titel', 'event-hub'),
                '{{excerpt}}' => __('Korte beschrijving', 'event-hub'),
                '{{date_range}}' => __('Datum + tijd bereik', 'event-hub'),
                '{{date_start}}' => __('Start (datum+tijd)', 'event-hub'),
                '{{date_end}}' => __('Einde (datum+tijd)', 'event-hub'),
                '{{location}}' => __('Locatie/online', 'event-hub'),
                '{{status_label}}' => __('Status label (badge)', 'event-hub'),
                '{{status_class}}' => __('Status CSS class', 'event-hub'),
                '{{hero_image}}' => __('Hero-afbeelding URL', 'event-hub'),
                '{{cta_label}}' => __('CTA tekst', 'event-hub'),
                '{{cta_link}}' => __('CTA link', 'event-hub'),
                '{{availability}}' => __('Capaciteit label', 'event-hub'),
                '{{waitlist}}' => __('Wachtlijst label', 'event-hub'),
                '{{color}}' => __('Accentkleur', 'event-hub'),
            ];
            foreach ($extra as $token => $desc) {
                echo '<li><code>' . esc_html($token) . '</code> — ' . esc_html($desc) . '</li>';
            }
            echo '</ul></details>';
            echo '</div>';
            echo '<div style="flex:1 1 45%; position:sticky; top:80px;">';
            echo '<div style="border:1px solid #e5e7eb;border-radius:12px;background:#f8fafc;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,.08);">';
            echo '<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-bottom:1px solid #e5e7eb;background:#fff;"><strong style="font-size:13px;color:#0f172a;">Live preview</strong><span style="font-size:12px;color:#64748b;">2026 view</span></div>';
            echo '<div id="eh-code-preview" style="min-height:240px;padding:14px;background:#fff;"></div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '<script>(function(){const field=document.getElementById("eh-custom-code");const preview=document.getElementById("eh-code-preview");function execScripts(root){root.querySelectorAll("script").forEach(function(old){const neu=document.createElement("script"); if(old.src){neu.src=old.src;} else {neu.textContent=old.textContent;} old.replaceWith(neu);});}function render(){if(!field||!preview) return; preview.innerHTML=field.value||""; execScripts(preview);} if(field){field.addEventListener("input", render);} render();})();</script>';
        }, self::OPTION_GENERAL, 'eh_general_main');

        add_settings_field('enable_recaptcha', __('reCAPTCHA beveiligen', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $name = self::OPTION_GENERAL . '[enable_recaptcha]';
            $checked = !empty($opts['enable_recaptcha']) ? 'checked' : '';
            echo '<label><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . $checked . ' /> ' . esc_html__('Activeer spamfilter op alle formulieren.', 'event-hub') . '</label>';
        }, self::OPTION_GENERAL, 'eh_general_main');

        add_settings_field('recaptcha_provider', __('reCAPTCHA aanbieder', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $val = $opts['recaptcha_provider'] ?? 'google_recaptcha_v3';
            $name = self::OPTION_GENERAL . '[recaptcha_provider]';
            $providers = [
                'google_recaptcha_v3' => 'Google reCAPTCHA v3',
                'hcaptcha' => 'hCaptcha',
            ];
            echo '<select name="' . esc_attr($name) . '">';
            foreach ($providers as $key => $label) {
                echo '<option value="' . esc_attr($key) . '"' . selected($val, $key, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
        }, self::OPTION_GENERAL, 'eh_general_main');

        add_settings_field('recaptcha_site_key', __('Publieke sleutel', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $val = $opts['recaptcha_site_key'] ?? '';
            $name = self::OPTION_GENERAL . '[recaptcha_site_key]';
            echo '<input type="text" class="regular-text" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '" />';
        }, self::OPTION_GENERAL, 'eh_general_main');

        add_settings_field('recaptcha_secret_key', __('Geheime sleutel', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $val = $opts['recaptcha_secret_key'] ?? '';
            $name = self::OPTION_GENERAL . '[recaptcha_secret_key]';
            echo '<input type="text" class="regular-text" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '" />';
        }, self::OPTION_GENERAL, 'eh_general_main');

        add_settings_field('recaptcha_score', __('Scoredrempel', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $val = isset($opts['recaptcha_score']) ? (float) $opts['recaptcha_score'] : 0.5;
            $name = self::OPTION_GENERAL . '[recaptcha_score]';
            echo '<input type="number" step="0.1" min="0" max="1" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '" />';
            echo '<p class="description">' . esc_html__('Hoe hoger, hoe strenger de spamfilter (standaard 0.5).', 'event-hub') . '</p>';
        }, self::OPTION_GENERAL, 'eh_general_main');

        add_settings_field('colleagues', __('Collega\'s', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $colleagues = isset($opts['colleagues']) && is_array($opts['colleagues']) ? $opts['colleagues'] : [];
            // Bepaal per collega in hoeveel events ze geselecteerd zijn
            $usage = [];
            $events = get_posts([
                'post_type' => Settings::get_cpt_slug(),
                'numberposts' => -1,
                'fields' => 'ids',
            ]);
            foreach ($events as $eid) {
                $selected = get_post_meta((int) $eid, '_eh_colleagues', true);
                $ids = is_array($selected) ? array_map('intval', $selected) : [];
                foreach ($ids as $cid) {
                    $usage[$cid] = ($usage[$cid] ?? 0) + 1;
                }
            }
            if (function_exists('wp_enqueue_media')) {
                wp_enqueue_media();
            }
            echo '<div class="eh-colleagues-list" data-template="#eh-colleague-settings-template">';
            $index = 0;
            foreach ($colleagues as $colleague) {
                $first = $colleague['first_name'] ?? '';
                $last  = $colleague['last_name'] ?? '';
                $role  = $colleague['role'] ?? '';
                $photo = (int) ($colleague['photo_id'] ?? 0);
                $email = $colleague['email'] ?? '';
                $phone = $colleague['phone'] ?? '';
                $linkedin = $colleague['linkedin'] ?? '';
                $bio = $colleague['bio'] ?? '';
                $count = $usage[$index] ?? 0;
                echo '<div class="eh-colleague-row" data-index="' . esc_attr((string) $index) . '">';
                echo '<div><label>' . esc_html__('Voornaam', 'event-hub') . '</label><input type="text" name="' . esc_attr(self::OPTION_GENERAL) . '[colleagues][' . esc_attr((string) $index) . '][first_name]" value="' . esc_attr($first) . '"></div>';
                echo '<div><label>' . esc_html__('Familienaam', 'event-hub') . '</label><input type="text" name="' . esc_attr(self::OPTION_GENERAL) . '[colleagues][' . esc_attr((string) $index) . '][last_name]" value="' . esc_attr($last) . '"></div>';
                echo '<div><label>' . esc_html__('Functie', 'event-hub') . '</label><input type="text" name="' . esc_attr(self::OPTION_GENERAL) . '[colleagues][' . esc_attr((string) $index) . '][role]" value="' . esc_attr($role) . '"></div>';
                echo '<div><label>' . esc_html__('E-mail', 'event-hub') . '</label><input type="email" name="' . esc_attr(self::OPTION_GENERAL) . '[colleagues][' . esc_attr((string) $index) . '][email]" value="' . esc_attr($email) . '"></div>';
                echo '<div><label>' . esc_html__('Tel', 'event-hub') . '</label><input type="text" name="' . esc_attr(self::OPTION_GENERAL) . '[colleagues][' . esc_attr((string) $index) . '][phone]" value="' . esc_attr($phone) . '"></div>';
                echo '<div><label>' . esc_html__('LinkedIn/URL', 'event-hub') . '</label><input type="text" name="' . esc_attr(self::OPTION_GENERAL) . '[colleagues][' . esc_attr((string) $index) . '][linkedin]" value="' . esc_attr($linkedin) . '"></div>';
                echo '<div style="grid-column:1/-1;"><label>' . esc_html__('Bio', 'event-hub') . '</label><textarea name="' . esc_attr(self::OPTION_GENERAL) . '[colleagues][' . esc_attr((string) $index) . '][bio]" rows="2" class="large-text">' . esc_textarea((string) $bio) . '</textarea></div>';
                echo '<div class="eh-colleague-photo"><label>' . esc_html__('Foto', 'event-hub') . '</label>';
                echo '<input type="hidden" class="eh-colleague-photo-id" name="' . esc_attr(self::OPTION_GENERAL) . '[colleagues][' . esc_attr((string) $index) . '][photo_id]" value="' . esc_attr((string) $photo) . '" />';
                echo '<div class="eh-colleague-photo-preview">';
                if ($photo) {
                    echo wp_get_attachment_image($photo, [60, 60]);
                }
                echo '</div>';
                echo '<button type="button" class="button eh-colleague-photo-btn">' . esc_html__('Kies foto', 'event-hub') . '</button>';
                echo '</div>';
                echo '<div class="eh-colleague-usage">' . sprintf(esc_html__('%d events', 'event-hub'), (int) $count) . '</div>';
                echo '<button type="button" class="button-link-delete eh-colleague-remove">' . esc_html__('Verwijder', 'event-hub') . '</button>';
                echo '</div>';
                $index++;
            }
            echo '</div>';
            echo '<button type="button" class="button eh-colleague-add">' . esc_html__('Collega toevoegen', 'event-hub') . '</button>';
            ?>
            <template id="eh-colleague-settings-template">
                <div class="eh-colleague-row" data-index="__index__">
                    <div>
                        <label><?php echo esc_html__('Voornaam', 'event-hub'); ?></label>
                        <input type="text" name="<?php echo esc_attr(self::OPTION_GENERAL); ?>[colleagues][__index__][first_name]" value="">
                    </div>
                    <div>
                        <label><?php echo esc_html__('Familienaam', 'event-hub'); ?></label>
                        <input type="text" name="<?php echo esc_attr(self::OPTION_GENERAL); ?>[colleagues][__index__][last_name]" value="">
                    </div>
                    <div>
                        <label><?php echo esc_html__('Functie', 'event-hub'); ?></label>
                        <input type="text" name="<?php echo esc_attr(self::OPTION_GENERAL); ?>[colleagues][__index__][role]" value="">
                    </div>
                    <div>
                        <label><?php echo esc_html__('E-mail', 'event-hub'); ?></label>
                        <input type="email" name="<?php echo esc_attr(self::OPTION_GENERAL); ?>[colleagues][__index__][email]" value="">
                    </div>
                    <div>
                        <label><?php echo esc_html__('Tel', 'event-hub'); ?></label>
                        <input type="text" name="<?php echo esc_attr(self::OPTION_GENERAL); ?>[colleagues][__index__][phone]" value="">
                    </div>
                    <div>
                        <label><?php echo esc_html__('LinkedIn/URL', 'event-hub'); ?></label>
                        <input type="text" name="<?php echo esc_attr(self::OPTION_GENERAL); ?>[colleagues][__index__][linkedin]" value="">
                    </div>
                    <div style="grid-column:1/-1;">
                        <label><?php echo esc_html__('Bio', 'event-hub'); ?></label>
                        <textarea name="<?php echo esc_attr(self::OPTION_GENERAL); ?>[colleagues][__index__][bio]" rows="2" class="large-text"></textarea>
                    </div>
                    <div class="eh-colleague-photo">
                        <label><?php echo esc_html__('Foto', 'event-hub'); ?></label>
                        <input type="hidden" class="eh-colleague-photo-id" name="<?php echo esc_attr(self::OPTION_GENERAL); ?>[colleagues][__index__][photo_id]" value="">
                        <div class="eh-colleague-photo-preview"></div>
                        <button type="button" class="button eh-colleague-photo-btn"><?php echo esc_html__('Kies foto', 'event-hub'); ?></button>
                    </div>
                    <button type="button" class="button-link-delete eh-colleague-remove"><?php echo esc_html__('Verwijder', 'event-hub'); ?></button>
                </div>
            </template>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                try {
                    var wrap = document.querySelector('.eh-colleagues-list');
                    var addBtn = document.querySelector('.eh-colleague-add');
                    var tpl = document.querySelector('#eh-colleague-settings-template');
                    if (!wrap || !addBtn || !tpl || typeof wp === 'undefined' || !wp.media) { return; }
                    function bindPhoto(btn) {
                        btn.addEventListener('click', function (e) {
                            e.preventDefault();
                            var row = btn.closest('.eh-colleague-row');
                            var input = row.querySelector('.eh-colleague-photo-id');
                            var preview = row.querySelector('.eh-colleague-photo-preview');
                            var frame = wp.media({ title: '<?php echo esc_js(__('Selecteer foto', 'event-hub')); ?>', button: { text: '<?php echo esc_js(__('Gebruiken', 'event-hub')); ?>' }, multiple: false });
                            frame.on('select', function () {
                                var attachment = frame.state().get('selection').first().toJSON();
                                input.value = attachment.id;
                                var url = (attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url);
                                preview.innerHTML = '<img src="' + url + '" alt="" style="width:60px;height:60px;border-radius:8px;object-fit:cover;">';
                            });
                            frame.open();
                        });
                    }
                    function bindRow(row) {
                        var remove = row.querySelector('.eh-colleague-remove');
                        if (remove) {
                            remove.addEventListener('click', function (e) {
                                e.preventDefault();
                                row.remove();
                            });
                        }
                        var photoBtn = row.querySelector('.eh-colleague-photo-btn');
                        if (photoBtn) {
                            bindPhoto(photoBtn);
                        }
                    }
                    wrap.querySelectorAll('.eh-colleague-row').forEach(bindRow);
                    addBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        var idx = wrap.querySelectorAll('.eh-colleague-row').length;
                        var html = tpl.innerHTML.replace(/__index__/g, idx);
                        var temp = document.createElement('div');
                        temp.innerHTML = html.trim();
                        var row = temp.firstElementChild;
                        wrap.appendChild(row);
                        bindRow(row);
                    });
                } catch (err) {
                    if (window.console) { console.warn('[EventHub] Settings colleagues init failed', err); }
                }
            });
            </script>
            <?php
        }, self::OPTION_GENERAL, 'eh_general_main');

        // Geen aparte WP-instellingenpagina; pagina's zitten onder Event Hub menu (Admin_Menus).
    }

    public function render_page(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(__('Je hebt geen rechten om deze pagina te openen.', 'event-hub'));
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Event Hub e-mails', 'event-hub') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields(self::OPTION);
        do_settings_sections(self::OPTION);
        submit_button(__('Wijzigingen opslaan', 'event-hub'));
        echo '</form>';
        echo '</div>';
    }

    public function render_general_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Event Hub - Algemeen', 'event-hub') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields(self::OPTION_GENERAL);
        do_settings_sections(self::OPTION_GENERAL);
        submit_button(__('Wijzigingen opslaan', 'event-hub'));
        echo '</form>';
        echo '</div>';
    }

    public function sanitize_settings($input): array
    {
        $out = [];
        $out['from_name'] = isset($input['from_name']) ? sanitize_text_field((string) $input['from_name']) : '';
        $out['from_email'] = isset($input['from_email']) ? sanitize_email((string) $input['from_email']) : '';
        $transport = $input['mail_transport'] ?? 'php';
        $out['mail_transport'] = in_array($transport, ['php', 'smtp_plugin'], true) ? $transport : 'php';
        $out['reminder_offset_days'] = isset($input['reminder_offset_days']) ? max(0, (int) $input['reminder_offset_days']) : 3;
        $out['followup_offset_hours'] = isset($input['followup_offset_hours']) ? max(0, (int) $input['followup_offset_hours']) : 24;
        if (isset($input['custom_placeholders_raw'])) {
            $raw = (string) $input['custom_placeholders_raw'];
            $out['custom_placeholders_raw'] = wp_kses_post($raw);
            $out['custom_placeholders'] = $this->parse_custom_placeholders($raw);
        }
        return $out;
    }

    public function sanitize_general_settings($input): array
    {
        $out = [];
        $out['use_external_cpt'] = !empty($input['use_external_cpt']) ? 1 : 0;
        $out['cpt_slug'] = isset($input['cpt_slug']) ? sanitize_key((string) $input['cpt_slug']) : 'eh_session';
        $out['tax_slug'] = isset($input['tax_slug']) ? sanitize_key((string) $input['tax_slug']) : 'eh_session_type';
        $out['cpt_menu_label'] = isset($input['cpt_menu_label']) ? sanitize_text_field((string) $input['cpt_menu_label']) : 'Evenementen';
        $out['cpt_singular_label'] = isset($input['cpt_singular_label']) ? sanitize_text_field((string) $input['cpt_singular_label']) : 'Evenement';
        $icon = isset($input['cpt_menu_icon']) ? sanitize_text_field((string) $input['cpt_menu_icon']) : 'dashicons-calendar-alt';
        $out['cpt_menu_icon'] = (strpos($icon, 'dashicons-') === 0) ? $icon : 'dashicons-calendar-alt';
        $pos = isset($input['cpt_menu_position']) ? (int) $input['cpt_menu_position'] : 20;
        $out['cpt_menu_position'] = ($pos >= 2 && $pos <= 99) ? $pos : 20;
        $layout = $input['single_layout'] ?? 'modern';
        $out['single_layout'] = in_array($layout, ['modern', 'compact'], true) ? $layout : 'modern';
        $out['single_custom_enabled'] = 0;
        $out['single_custom_css'] = '';
        $out['single_custom_js'] = '';
        $out['single_custom_html_before_hero'] = '';
        $out['single_custom_html_after_hero'] = '';
        $out['single_custom_html_before_form'] = '';
        $out['single_custom_html_after_form'] = '';
        $out['single_custom_html_after_details'] = '';
        // Builder niet meer gebruikt, veld behouden voor compat.
        $out['single_builder_sections'] = isset($input['single_builder_sections']) ? (string) $input['single_builder_sections'] : '[]';
        // Volledige custom code (HTML/CSS/JS)
        $out['single_custom_code'] = isset($input['single_custom_code']) ? (string) $input['single_custom_code'] : '';
        $out['enable_recaptcha'] = !empty($input['enable_recaptcha']) ? 1 : 0;
        $allowed = ['google_recaptcha_v3','hcaptcha'];
        $out['recaptcha_provider'] = in_array($input['recaptcha_provider'] ?? '', $allowed, true) ? $input['recaptcha_provider'] : 'google_recaptcha_v3';
        $out['recaptcha_site_key'] = isset($input['recaptcha_site_key']) ? sanitize_text_field((string) $input['recaptcha_site_key']) : '';
        $out['recaptcha_secret_key'] = isset($input['recaptcha_secret_key']) ? sanitize_text_field((string) $input['recaptcha_secret_key']) : '';
        $score = isset($input['recaptcha_score']) ? (float) $input['recaptcha_score'] : 0.5;
        $out['recaptcha_score'] = ($score >= 0 && $score <= 1) ? $score : 0.5;
        $out['colleagues'] = [];
        if (isset($input['colleagues']) && is_array($input['colleagues'])) {
            foreach ($input['colleagues'] as $row) {
                $first = isset($row['first_name']) ? sanitize_text_field((string) $row['first_name']) : '';
                $last  = isset($row['last_name']) ? sanitize_text_field((string) $row['last_name']) : '';
                $role  = isset($row['role']) ? sanitize_text_field((string) $row['role']) : '';
                $email = isset($row['email']) ? sanitize_email((string) $row['email']) : '';
                $phone = isset($row['phone']) ? sanitize_text_field((string) $row['phone']) : '';
                $linkedin = isset($row['linkedin']) ? esc_url_raw((string) $row['linkedin']) : '';
                $bio = isset($row['bio']) ? wp_kses_post((string) $row['bio']) : '';
                $photo = isset($row['photo_id']) ? (int) $row['photo_id'] : 0;
                if ($first || $last || $role || $photo || $email || $phone || $linkedin || $bio) {
                    $out['colleagues'][] = [
                        'first_name' => $first,
                        'last_name'  => $last,
                        'role'       => $role,
                        'email'      => $email,
                        'phone'      => $phone,
                        'linkedin'   => $linkedin,
                        'bio'        => $bio,
                        'photo_id'   => $photo,
                    ];
                }
            }
        }
        return $out;
    }

    public static function get_general(): array
    {
        $defaults = [
            'use_external_cpt' => 0,
            'cpt_slug' => 'eh_session',
            'tax_slug' => 'eh_session_type',
            'cpt_menu_label' => 'Evenementen',
            'cpt_singular_label' => 'Evenement',
            'cpt_menu_icon' => 'dashicons-calendar-alt',
            'cpt_menu_position' => 20,
            'single_layout' => 'modern',
            'single_custom_enabled' => 0,
            'single_custom_css' => '',
            'single_custom_js' => '',
            'single_custom_html_before_hero' => '',
            'single_custom_html_after_hero' => '',
            'single_custom_html_before_form' => '',
            'single_custom_html_after_form' => '',
            'single_custom_html_after_details' => '',
            'single_custom_code' => '',
            'single_builder_sections' => '[]',
            'enable_recaptcha' => 0,
            'recaptcha_provider' => 'google_recaptcha_v3',
            'recaptcha_site_key' => '',
            'recaptcha_secret_key' => '',
            'recaptcha_score' => 0.5,
        ];
        $opts = get_option(self::OPTION_GENERAL, []);
        return wp_parse_args($opts, $defaults);
    }

    public static function get_cpt_slug(): string
    {
        $g = self::get_general();
        return sanitize_key((string) ($g['cpt_slug'] ?? 'eh_session'));
    }

    public static function get_tax_slug(): string
    {
        $g = self::get_general();
        return sanitize_key((string) ($g['tax_slug'] ?? 'eh_session_type'));
    }

    public static function use_external_cpt(): bool
    {
        $g = self::get_general();
        return !empty($g['use_external_cpt']);
    }

    /**
     * Force back to the built-in CPT/tax when external slugs are missing.
     * Prevents fatal errors when a referenced CPT (e.g. JetEngine) is disabled.
     */
    public static function fallback_to_builtin_cpt(): void
    {
        $general = self::get_general();
        $general['use_external_cpt'] = 0;
        $general['cpt_slug'] = 'eh_session';
        $general['tax_slug'] = 'eh_session_type';
        update_option(self::OPTION_GENERAL, $general);
        set_transient('event_hub_cpt_fallback', 1, HOUR_IN_SECONDS);
    }

    public static function get_email_settings(): array
    {
        return get_option(self::OPTION, []);
    }

    public static function get_custom_placeholders(): array
    {
        $opts = self::get_email_settings();
        return isset($opts['custom_placeholders']) && is_array($opts['custom_placeholders'])
            ? $opts['custom_placeholders']
            : [];
    }

    /**
     * Admin notice wanneer een externe CPT/tax niet bestaat.
     */
    public function maybe_notice_cpt_tax_issues(): void
    {
        if (!is_admin()) {
            return;
        }
        $g = self::get_general();
        if (empty($g['use_external_cpt'])) {
            return;
        }
        $cpt = sanitize_key((string) ($g['cpt_slug'] ?? ''));
        $tax = sanitize_key((string) ($g['tax_slug'] ?? ''));
        $missing = [];
        if ($cpt && !post_type_exists($cpt)) {
            $missing[] = sprintf(__('CPT "%s" bestaat niet.', 'event-hub'), $cpt);
        }
        if ($tax && !taxonomy_exists($tax)) {
            $missing[] = sprintf(__('Taxonomie "%s" bestaat niet.', 'event-hub'), $tax);
        }
        if ($missing) {
            echo '<div class="notice notice-error"><p><strong>' . esc_html__('Event Hub configuratie', 'event-hub') . '</strong><br>' . esc_html(implode(' ', $missing)) . ' ' . esc_html__('Pas de slugs aan of schakel externe CPT uit.', 'event-hub') . '</p></div>';
        }
    }

    private function parse_custom_placeholders(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $map = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            [$token, $value] = array_map('trim', explode('=', $line, 2));
            if ($token === '') {
                continue;
            }
            $token = trim($token);
            $token = trim($token, '{}');
            if ($token === '') {
                continue;
            }
            $token = '{' . $token . '}';
            $map[$token] = wp_kses_post($value);
        }
        return $map;
    }
}
