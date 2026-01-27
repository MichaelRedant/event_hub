<?php
namespace EventHub;

defined('ABSPATH') || exit;

class Settings
{
    public const OPTION = 'event_hub_email_settings';
    public const OPTION_GENERAL = 'event_hub_general_settings';
    private static bool $sync_lock = false;

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
            ['confirmation_timing_mode', __('Bevestiging timing', 'event-hub'), 'timing'],
            ['reminder_offset_hours', __('Herinnering (uren voor start)', 'event-hub'), 'number'],
            ['followup_offset_hours', __('Nadien (uren na einde)', 'event-hub'), 'number'],
            ['waitlist_timing_mode', __('Wachtlijst timing', 'event-hub'), 'timing'],
            ['cancel_cutoff_hours', __('Annuleren via link tot (uren voor start)', 'event-hub'), 'number'],
            ['custom_placeholders_raw', __('Eigen placeholders', 'event-hub'), 'textarea'],
        ];

        foreach ($fields as [$key, $label, $type]) {
            add_settings_field($key, $label, function () use ($key, $type) {
                $opts = get_option(self::OPTION, []);
                $val = $opts[$key] ?? '';
                $name = self::OPTION . "[{$key}]";
                if ($type === 'textarea') {
                    echo '<textarea class="large-text code" rows="6" name="' . esc_attr($name) . '">' . esc_textarea((string) $val) . '</textarea>';
                    echo '<p class="description">' . esc_html__('Eén placeholder per lijn, formaat: mijn_token=Mijn waarde. Gebruik accolades { } om de tokennaam te markeren.', 'event-hub') . '</p>';
                } elseif ($type === 'number') {
                    echo '<input type="number" min="0" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '" />';
                } elseif ($type === 'timing') {
                    $hours_key = str_replace('_mode', '_hours', $key);
                    $hours_val = $opts[$hours_key] ?? 24;
                    $options = [
                        'immediate' => __('Onmiddellijk', 'event-hub'),
                        'before_start' => __('X uren voor start', 'event-hub'),
                        'after_end' => __('X uren na einde/start', 'event-hub'),
                    ];
                    echo '<select name="' . esc_attr($name) . '">';
                    foreach ($options as $opt_val => $opt_label) {
                        echo '<option value="' . esc_attr($opt_val) . '"' . selected($val ?: 'immediate', $opt_val, false) . '>' . esc_html($opt_label) . '</option>';
                    }
                    echo '</select>';
                    echo '<div style="margin-top:6px;">';
                    echo '<label style="display:block;margin-bottom:4px;font-weight:600;">' . esc_html__('Aantal uren', 'event-hub') . '</label>';
                    echo '<input type="number" min="0" name="' . esc_attr(self::OPTION . "[{$hours_key}]") . '" value="' . esc_attr((string) $hours_val) . '" />';
                    echo '</div>';
                    echo '<p class="description">' . esc_html__('Kies wanneer de mail vertrekt. Bij “voor start” of “na einde” gebruiken we het aantal uren hiernaast. Leeg laten? We nemen de standaard (24u).', 'event-hub') . '</p>';
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
                'linked_sync_enabled' => 0,
                'linked_sync_mode' => 'manual',
                'linked_sync_strategy' => 'fill_empty',
                'linked_sync_source_cpt' => '',
                'linked_sync_map' => [],
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
            echo '<div style="display:flex;gap:20px;align-items:flex-start;position:relative;padding-right:680px;">';
            echo '<div style="flex:1 1 100%;">';
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
            echo '<div class="eh-general-preview" style="position:fixed; top:96px; right:10px; width:620px; max-width:70vw; z-index:10;">';
            echo '<div style="border:1px solid #e5e7eb;border-radius:12px;background:#f8fafc;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,.12);">';
            echo '<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-bottom:1px solid #e5e7eb;background:#fff;">';
            echo '<strong style="font-size:13px;color:#0f172a;">Live preview</strong>';
            echo '<button type="button" id="eh-preview-toggle" style="border:1px solid #e5e7eb;background:#f8fafc;color:#0f172a;padding:4px 8px;border-radius:8px;font-size:12px;cursor:pointer;">' . esc_html__('Inklappen', 'event-hub') . '</button>';
            echo '</div>';
            echo '<div id="eh-code-preview" style="min-height:120px;max-height:280px;overflow:auto;padding:14px;background:#fff;"></div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            $lbl_collapse = esc_js(__('Inklappen', 'event-hub'));
            $lbl_expand   = esc_js(__('Uitklappen', 'event-hub'));
            echo '<script>(function(){const field=document.getElementById("eh-custom-code");const preview=document.getElementById("eh-code-preview");const toggle=document.getElementById("eh-preview-toggle");let collapsed=false;function execScripts(root){root.querySelectorAll("script").forEach(function(old){const neu=document.createElement("script"); if(old.src){neu.src=old.src;} else {neu.textContent=old.textContent;} old.replaceWith(neu);});}function render(){if(!field||!preview) return; preview.innerHTML=field.value||""; execScripts(preview);} if(field){field.addEventListener("input", render);} render(); if(toggle && preview){toggle.addEventListener("click", function(){collapsed=!collapsed; preview.style.display = collapsed ? "none" : "block"; toggle.textContent = collapsed ? "' . $lbl_expand . '" : "' . $lbl_collapse . '";});}})();</script>';
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

        add_settings_section('eh_cpt_sync', __('CPT sync', 'event-hub'), function () {
            echo '<p>' . esc_html__('Koppel een extern events-CPT aan Event Hub en synchroniseer velden om dubbel invullen te vermijden.', 'event-hub') . '</p>';
        }, self::OPTION_GENERAL);

        add_settings_field('linked_sync_enabled', __('Sync inschakelen', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $name = self::OPTION_GENERAL . '[linked_sync_enabled]';
            $checked = !empty($opts['linked_sync_enabled']) ? 'checked' : '';
            echo '<label><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . $checked . ' /> ' . esc_html__('Activeer synchronisatie van gekoppelde events.', 'event-hub') . '</label>';
        }, self::OPTION_GENERAL, 'eh_cpt_sync');

        add_settings_field('linked_sync_source_cpt', __('Bron CPT-slug', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $val = $opts['linked_sync_source_cpt'] ?? '';
            $name = self::OPTION_GENERAL . '[linked_sync_source_cpt]';
            $post_types = get_post_types(['show_ui' => true], 'objects');
            $datalist_id = 'eh_sync_cpt_list';
            echo '<input type="text" class="regular-text" list="' . esc_attr($datalist_id) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '" placeholder="bv. evenementen" />';
            echo '<datalist id="' . esc_attr($datalist_id) . '">';
            foreach ($post_types as $pt) {
                $label = isset($pt->labels->singular_name) ? $pt->labels->singular_name : $pt->name;
                echo '<option value="' . esc_attr($pt->name) . '">' . esc_html($label) . '</option>';
            }
            echo '</datalist>';
            echo '<p class="description">' . esc_html__('Kies het externe CPT dat je wilt synchroniseren.', 'event-hub') . '</p>';
        }, self::OPTION_GENERAL, 'eh_cpt_sync');

        add_settings_field('linked_sync_mode', __('Sync moment', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $val = $opts['linked_sync_mode'] ?? 'manual';
            $name = self::OPTION_GENERAL . '[linked_sync_mode]';
            $options = [
                'manual' => __('Alleen handmatig', 'event-hub'),
                'on_save' => __('Automatisch bij opslaan van bron CPT', 'event-hub'),
            ];
            echo '<select name="' . esc_attr($name) . '">';
            foreach ($options as $key => $label) {
                echo '<option value="' . esc_attr($key) . '"' . selected($val, $key, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
        }, self::OPTION_GENERAL, 'eh_cpt_sync');

        add_settings_field('linked_sync_strategy', __('Conflictstrategie', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $val = $opts['linked_sync_strategy'] ?? 'fill_empty';
            $name = self::OPTION_GENERAL . '[linked_sync_strategy]';
            $options = [
                'fill_empty' => __('Vul alleen lege velden in Event Hub', 'event-hub'),
                'overwrite' => __('Overschrijf Event Hub velden met brondata', 'event-hub'),
            ];
            echo '<select name="' . esc_attr($name) . '">';
            foreach ($options as $key => $label) {
                echo '<option value="' . esc_attr($key) . '"' . selected($val, $key, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
        }, self::OPTION_GENERAL, 'eh_cpt_sync');

        add_settings_field('linked_sync_map', __('Veldkoppeling', 'event-hub'), [$this, 'render_sync_map_field'], self::OPTION_GENERAL, 'eh_cpt_sync');
        add_settings_field('linked_sync_actions', __('Handmatige import', 'event-hub'), [$this, 'render_sync_actions_field'], self::OPTION_GENERAL, 'eh_cpt_sync');

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
        echo '<div class="wrap eh-admin">';
        echo '<h1>' . esc_html__('Event Hub - Algemeen', 'event-hub') . '</h1>';
        echo '<p style="max-width:720px;color:#475569;margin:6px 0 16px;">' . esc_html__('Kies welk CPT en welke taxonomie Event Hub gebruikt. Gebruik je een bestaande CPT? Dan laten we die ongemoeid bij deïnstallatie.', 'event-hub') . '</p>';
        echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px 20px;max-width:980px;">';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin-bottom:16px;">';
        echo '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px;">';
        echo '<h3 style="margin-top:0;">' . esc_html__('Snel overzicht', 'event-hub') . '</h3>';
        echo '<ul style="margin:0; padding-left:18px; color:#475569; line-height:1.5;">';
        echo '<li>' . esc_html__('Externe CPT? Vink aan en kies de slug.', 'event-hub') . '</li>';
        echo '<li>' . esc_html__('Labels en icoon bepalen hoe het menu eruitziet.', 'event-hub') . '</li>';
        echo '<li>' . esc_html__('Taxonomie: koppel bestaande of laat Event Hub er een maken.', 'event-hub') . '</li>';
        echo '</ul>';
        echo '</div>';
        echo '<div style="background:#ecfeff;border:1px solid #bae6fd;border-radius:12px;padding:14px;">';
        echo '<h3 style="margin-top:0;">' . esc_html__('Veiligheid bij verwijderen', 'event-hub') . '</h3>';
        echo '<p style="margin:0;color:#0f172a;">' . esc_html__('We verwijderen enkel de Event Hub CPT bij deïnstallatie als je geen externe CPT gebruikt. Externe CPT-data blijft altijd staan.', 'event-hub') . '</p>';
        echo '</div>';
        echo '</div>'; // grid
        echo '<form method="post" action="options.php" style="margin-top:0;">';
        settings_fields(self::OPTION_GENERAL);
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px;">';
        echo '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;">';
        echo '<h3 style="margin-top:0;">' . esc_html__('CPT & Menu', 'event-hub') . '</h3>';
        do_settings_sections(self::OPTION_GENERAL);
        echo '</div>';
        echo '</div>';
        submit_button(__('Wijzigingen opslaan', 'event-hub'));
        echo '</form>';
        echo '</div>';
    }

    public function render_sync_map_field(): void
    {
        $opts = get_option(self::OPTION_GENERAL, []);
        $map = isset($opts['linked_sync_map']) && is_array($opts['linked_sync_map']) ? $opts['linked_sync_map'] : [];
        $defs = $this->get_sync_field_definitions();
        $base = self::OPTION_GENERAL . '[linked_sync_map]';
        $post_field_hint = esc_html__('Postvelden: title, content, excerpt, date, modified, slug, status.', 'event-hub');
        echo '<div class="notice notice-info" style="margin:12px 0;">';
        echo '<p style="margin:0;"><strong>' . esc_html__('Hoe werkt veldkoppeling?', 'event-hub') . '</strong></p>';
        echo '<ul style="margin:6px 0 0 18px; color:#334155;">';
        echo '<li>' . esc_html__('Kies bij elke Event Hub-kolom of je data haalt uit een standaard postveld (title, content, ... ) of uit een meta key.', 'event-hub') . '</li>';
        echo '<li>' . esc_html__('Postvelden synchroniseren rechtstreeks met het WordPress-bericht; meta keys gebruik je voor eigen velden of ACF.', 'event-hub') . '</li>';
        echo '<li>' . esc_html__('Leeg laten betekent: niet overschrijven in de sync.', 'event-hub') . '</li>';
        echo '<li>' . esc_html__('Tip: zet de conflictaanpak hierboven op "Vul alleen lege velden" als je Event Hub inhoud wilt behouden.', 'event-hub') . '</li>';
        echo '</ul>';
        echo '</div>';
        echo '<table class="widefat striped" style="max-width:980px;">';
        echo '<thead><tr><th>' . esc_html__('Event Hub veld', 'event-hub') . '</th><th>' . esc_html__('Bron type', 'event-hub') . '</th><th>' . esc_html__('Bron veld/meta key', 'event-hub') . '</th></tr></thead>';
        echo '<tbody>';
        foreach ($defs as $field_key => $def) {
            $row = isset($map[$field_key]) && is_array($map[$field_key]) ? $map[$field_key] : [];
            $source = $row['source'] ?? '';
            $key = $row['key'] ?? '';
            $name = $base . '[' . $field_key . ']';
            echo '<tr>';
            echo '<td><strong>' . esc_html($def['label']) . '</strong><br><span class="description">' . esc_html($field_key) . '</span></td>';
            echo '<td>';
            echo '<select name="' . esc_attr($name . '[source]') . '">';
            echo '<option value="">' . esc_html__('Niet koppelen', 'event-hub') . '</option>';
            echo '<option value="post"' . selected($source, 'post', false) . '>' . esc_html__('Postveld', 'event-hub') . '</option>';
            echo '<option value="meta"' . selected($source, 'meta', false) . '>' . esc_html__('Meta key', 'event-hub') . '</option>';
            echo '</select>';
            echo '</td>';
            echo '<td>';
            echo '<input type="text" class="regular-text" name="' . esc_attr($name . '[key]') . '" value="' . esc_attr((string) $key) . '" placeholder="' . esc_attr($def['placeholder']) . '" />';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '<p class="description">' . $post_field_hint . '</p>';
    }

    public function render_sync_actions_field(): void
    {
        $opts = get_option(self::OPTION_GENERAL, []);
        $source_cpt = $opts['linked_sync_source_cpt'] ?? '';
        $btn_label = esc_html__('Importeer & synchroniseer nu', 'event-hub');
        $sync_url = wp_nonce_url(
            add_query_arg(['action' => 'event_hub_sync_linked_events', 'sync_action' => 'import'], admin_url('admin-post.php')),
            'event_hub_linked_sync',
            'event_hub_linked_sync_nonce'
        );
        if ($source_cpt) {
            echo '<a class="button button-secondary" href="' . esc_url($sync_url) . '">' . $btn_label . '</a>';
        } else {
            echo '<button type="button" class="button button-secondary" disabled>' . $btn_label . '</button>';
        }
        echo '<p class="description">' . esc_html__('Maakt Event Hub events aan voor alle items in de bron-CPT en synchroniseert velden volgens de mapping. Bestaande koppelingen worden hergebruikt.', 'event-hub') . '</p>';
    }

    /**
     * @return array<string,array{label:string,target:string,type:string,placeholder:string,meta_key?:string,post_field?:string}>
     */
    private function get_sync_field_definitions(): array
    {
        return [
            'post_title' => [
                'label' => __('Titel', 'event-hub'),
                'target' => 'post',
                'post_field' => 'post_title',
                'type' => 'text',
                'placeholder' => 'title',
            ],
            'post_content' => [
                'label' => __('Inhoud', 'event-hub'),
                'target' => 'post',
                'post_field' => 'post_content',
                'type' => 'html',
                'placeholder' => 'content',
            ],
            'post_excerpt' => [
                'label' => __('Samenvatting', 'event-hub'),
                'target' => 'post',
                'post_field' => 'post_excerpt',
                'type' => 'text',
                'placeholder' => 'excerpt',
            ],
            '_eh_date_start' => [
                'label' => __('Startdatum', 'event-hub'),
                'target' => 'meta',
                'meta_key' => '_eh_date_start',
                'type' => 'datetime',
                'placeholder' => 'start_date',
            ],
            '_eh_date_end' => [
                'label' => __('Einddatum', 'event-hub'),
                'target' => 'meta',
                'meta_key' => '_eh_date_end',
                'type' => 'datetime',
                'placeholder' => 'end_date',
            ],
            '_eh_booking_open' => [
                'label' => __('Boekingen openen', 'event-hub'),
                'target' => 'meta',
                'meta_key' => '_eh_booking_open',
                'type' => 'datetime',
                'placeholder' => 'booking_open',
            ],
            '_eh_booking_close' => [
                'label' => __('Boekingen sluiten', 'event-hub'),
                'target' => 'meta',
                'meta_key' => '_eh_booking_close',
                'type' => 'datetime',
                'placeholder' => 'booking_close',
            ],
            '_eh_location' => [
                'label' => __('Locatie', 'event-hub'),
                'target' => 'meta',
                'meta_key' => '_eh_location',
                'type' => 'text',
                'placeholder' => 'location',
            ],
            '_eh_address' => [
                'label' => __('Adres', 'event-hub'),
                'target' => 'meta',
                'meta_key' => '_eh_address',
                'type' => 'text',
                'placeholder' => 'address',
            ],
            '_eh_is_online' => [
                'label' => __('Online event', 'event-hub'),
                'target' => 'meta',
                'meta_key' => '_eh_is_online',
                'type' => 'bool',
                'placeholder' => 'is_online',
            ],
            '_eh_online_link' => [
                'label' => __('Onlinelink', 'event-hub'),
                'target' => 'meta',
                'meta_key' => '_eh_online_link',
                'type' => 'url',
                'placeholder' => 'online_link',
            ],
            '_eh_capacity' => [
                'label' => __('Capaciteit', 'event-hub'),
                'target' => 'meta',
                'meta_key' => '_eh_capacity',
                'type' => 'int',
                'placeholder' => 'capacity',
            ],
            '_eh_price' => [
                'label' => __('Prijs', 'event-hub'),
                'target' => 'meta',
                'meta_key' => '_eh_price',
                'type' => 'float',
                'placeholder' => 'price',
            ],
            '_eh_no_show_fee' => [
                'label' => __('No-show fee', 'event-hub'),
                'target' => 'meta',
                'meta_key' => '_eh_no_show_fee',
                'type' => 'float',
                'placeholder' => 'no_show_fee',
            ],
            '_eh_ticket_note' => [
                'label' => __('Ticket info', 'event-hub'),
                'target' => 'meta',
                'meta_key' => '_eh_ticket_note',
                'type' => 'html',
                'placeholder' => 'ticket_note',
            ],
            '_eh_color' => [
                'label' => __('Accentkleur', 'event-hub'),
                'target' => 'meta',
                'meta_key' => '_eh_color',
                'type' => 'color',
                'placeholder' => 'color',
            ],
            '_eh_agenda' => [
                'label' => __('Agenda', 'event-hub'),
                'target' => 'meta',
                'meta_key' => '_eh_agenda',
                'type' => 'html',
                'placeholder' => 'agenda',
            ],
            '_eh_language' => [
                'label' => __('Taal', 'event-hub'),
                'target' => 'meta',
                'meta_key' => '_eh_language',
                'type' => 'text',
                'placeholder' => 'language',
            ],
            '_eh_target_audience' => [
                'label' => __('Doelgroep', 'event-hub'),
                'target' => 'meta',
                'meta_key' => '_eh_target_audience',
                'type' => 'text',
                'placeholder' => 'audience',
            ],
            '_eh_organizer' => [
                'label' => __('Organisator', 'event-hub'),
                'target' => 'meta',
                'meta_key' => '_eh_organizer',
                'type' => 'text',
                'placeholder' => 'organizer',
            ],
            '_eh_staff' => [
                'label' => __('Sprekers/team', 'event-hub'),
                'target' => 'meta',
                'meta_key' => '_eh_staff',
                'type' => 'text',
                'placeholder' => 'staff',
            ],
            '_eh_show_on_site' => [
                'label' => __('Toon op site', 'event-hub'),
                'target' => 'meta',
                'meta_key' => '_eh_show_on_site',
                'type' => 'bool',
                'placeholder' => 'show_on_site',
            ],
            '_eh_status' => [
                'label' => __('Status', 'event-hub'),
                'target' => 'meta',
                'meta_key' => '_eh_status',
                'type' => 'text',
                'placeholder' => 'status',
            ],
            '_eh_hero_image_override' => [
                'label' => __('Hero afbeelding (URL)', 'event-hub'),
                'target' => 'meta',
                'meta_key' => '_eh_hero_image_override',
                'type' => 'url',
                'placeholder' => 'hero_image',
            ],
        ];
    }

    private function sanitize_sync_map($input): array
    {
        if (!is_array($input)) {
            return [];
        }
        $defs = $this->get_sync_field_definitions();
        $allowed_post_fields = ['title', 'content', 'excerpt', 'date', 'modified', 'slug', 'status'];
        $out = [];
        foreach ($defs as $field_key => $def) {
            if (empty($input[$field_key]) || !is_array($input[$field_key])) {
                continue;
            }
            $source = sanitize_key((string) ($input[$field_key]['source'] ?? ''));
            $key = sanitize_text_field((string) ($input[$field_key]['key'] ?? ''));
            if ($source === '' || $key === '') {
                continue;
            }
            if ($source === 'post') {
                $key = sanitize_key($key);
                if (!in_array($key, $allowed_post_fields, true)) {
                    continue;
                }
            } elseif ($source === 'meta') {
                $key = sanitize_key($key);
            } else {
                continue;
            }
            $out[$field_key] = [
                'source' => $source,
                'key' => $key,
            ];
        }
        return $out;
    }

    /**
     * @return array{enabled:bool,mode:string,strategy:string,source_cpt:string,map:array}
     */
    private function get_sync_settings(): array
    {
        $opts = self::get_general();
        $map = isset($opts['linked_sync_map']) && is_array($opts['linked_sync_map']) ? $opts['linked_sync_map'] : [];
        return [
            'enabled' => !empty($opts['linked_sync_enabled']),
            'mode' => $opts['linked_sync_mode'] ?? 'manual',
            'strategy' => $opts['linked_sync_strategy'] ?? 'fill_empty',
            'source_cpt' => sanitize_key((string) ($opts['linked_sync_source_cpt'] ?? '')),
            'map' => $map,
        ];
    }

    public function maybe_sync_from_linked_event(int $post_id, \WP_Post $post, bool $update): void
    {
        if (self::$sync_lock) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if (self::use_external_cpt()) {
            return;
        }
        $settings = $this->get_sync_settings();
        if (!$settings['enabled'] || $settings['mode'] !== 'on_save') {
            return;
        }
        if ($settings['source_cpt'] === '' || $post->post_type !== $settings['source_cpt']) {
            return;
        }
        if (empty($settings['map'])) {
            return;
        }

        $session_id = (int) get_post_meta($post_id, '_eh_linked_session_id', true);
        if ($session_id <= 0) {
            $session_id = $this->find_session_by_linked_event($post_id, $settings['source_cpt']);
        }
        if ($session_id <= 0) {
            return;
        }

        $changes = $this->sync_linked_post_to_session($post_id, $session_id, $settings);
        if ($changes > 0) {
            $logger = new Logger();
            $logger->log('linked_sync', 'Event sync uitgevoerd op save.', [
                'source_id' => $post_id,
                'session_id' => $session_id,
                'changes' => $changes,
            ]);
        }
    }

    public function handle_linked_sync_action(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));
        }
        $nonce = $_POST['event_hub_linked_sync_nonce'] ?? $_GET['event_hub_linked_sync_nonce'] ?? '';
        if (empty($nonce) || !wp_verify_nonce(sanitize_text_field((string) $nonce), 'event_hub_linked_sync')) {
            wp_die(__('Ongeldige aanvraag.', 'event-hub'));
        }
        if (self::use_external_cpt()) {
            set_transient('event_hub_sync_notice', [
                'type' => 'error',
                'message' => __('Sync is niet nodig wanneer je Event Hub op een externe CPT laat draaien.', 'event-hub'),
            ], 60);
            wp_safe_redirect(add_query_arg(['page' => 'event-hub-general'], admin_url('admin.php')));
            exit;
        }

        $settings = $this->get_sync_settings();
        if ($settings['source_cpt'] === '' || !post_type_exists($settings['source_cpt'])) {
            set_transient('event_hub_sync_notice', [
                'type' => 'error',
                'message' => __('Bron CPT is niet ingesteld of bestaat niet.', 'event-hub'),
            ], 60);
            wp_safe_redirect(add_query_arg(['page' => 'event-hub-general'], admin_url('admin.php')));
            exit;
        }
        if (empty($settings['map'])) {
            set_transient('event_hub_sync_notice', [
                'type' => 'error',
                'message' => __('Stel eerst veldkoppelingen in voordat je importeert.', 'event-hub'),
            ], 60);
            wp_safe_redirect(add_query_arg(['page' => 'event-hub-general'], admin_url('admin.php')));
            exit;
        }

        $result = $this->run_linked_import($settings);
        set_transient('event_hub_sync_notice', $result, 60);
        wp_safe_redirect(add_query_arg(['page' => 'event-hub-general'], admin_url('admin.php')));
        exit;
    }

    /**
     * @return array<string,mixed>
     */
    private function run_linked_import(array $settings): array
    {
        $stats = [
            'type' => 'success',
            'message' => __('Sync afgerond.', 'event-hub'),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $source_cpt = $settings['source_cpt'];
        $post_ids = get_posts([
            'post_type' => $source_cpt,
            'post_status' => ['publish', 'future'],
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        if (!$post_ids) {
            $stats['message'] = __('Geen events gevonden in het bron CPT.', 'event-hub');
            return $stats;
        }

        foreach ($post_ids as $source_id) {
            $source_id = (int) $source_id;
            $source_post = get_post($source_id);
            if (!$source_post) {
                $stats['errors']++;
                continue;
            }

            $session_id = (int) get_post_meta($source_id, '_eh_linked_session_id', true);
            if ($session_id <= 0) {
                $session_id = $this->find_session_by_linked_event($source_id, $source_cpt);
            }

            $created = false;
            if ($session_id <= 0) {
                $session_id = wp_insert_post([
                    'post_type' => self::get_cpt_slug(),
                    'post_status' => $source_post->post_status === 'future' ? 'future' : 'publish',
                    'post_title' => $source_post->post_title,
                    'post_content' => $source_post->post_content,
                    'post_excerpt' => $source_post->post_excerpt,
                ], true);
                if (is_wp_error($session_id)) {
                    $stats['errors']++;
                    continue;
                }
                $created = true;
                $stats['created']++;
                update_post_meta((int) $session_id, '_eh_linked_event_id', $source_id);
                update_post_meta((int) $session_id, '_eh_linked_event_cpt', $source_cpt);
                update_post_meta($source_id, '_eh_linked_session_id', (int) $session_id);
            }

            $changes = $this->sync_linked_post_to_session($source_id, (int) $session_id, $settings);
            if ($changes > 0) {
                $stats['updated']++;
            } elseif (!$created) {
                $stats['skipped']++;
            }
        }

        $logger = new Logger();
        $logger->log('linked_sync', 'Handmatige import uitgevoerd.', [
            'source_cpt' => $source_cpt,
            'created' => $stats['created'],
            'updated' => $stats['updated'],
            'skipped' => $stats['skipped'],
            'errors' => $stats['errors'],
        ]);

        return $stats;
    }

    private function sync_linked_post_to_session(int $source_post_id, int $session_id, array $settings): int
    {
        $defs = $this->get_sync_field_definitions();
        $map = $settings['map'] ?? [];
        if (!$defs || !$map) {
            return 0;
        }

        $source_post = get_post($source_post_id);
        $session_post = get_post($session_id);
        if (!$source_post || !$session_post) {
            return 0;
        }

        $overwrite = ($settings['strategy'] ?? 'fill_empty') === 'overwrite';
        $post_updates = [];
        $changes = 0;

        foreach ($defs as $field_key => $def) {
            if (empty($map[$field_key]) || !is_array($map[$field_key])) {
                continue;
            }
            $source_type = $map[$field_key]['source'] ?? '';
            $source_key = $map[$field_key]['key'] ?? '';
            if ($source_type === '' || $source_key === '') {
                continue;
            }

            $source_value = $this->resolve_source_value($source_post_id, $source_post, $source_type, $source_key);
            if ($source_value === null || $source_value === '') {
                continue;
            }

            $normalized = $this->normalize_sync_value($source_value, $def['type']);
            if ($normalized === null) {
                continue;
            }
            if ($normalized === '' && !in_array($def['type'], ['bool', 'int', 'float'], true)) {
                continue;
            }

            if ($def['target'] === 'post') {
                $post_field = $def['post_field'] ?? '';
                if ($post_field === '') {
                    continue;
                }
                $current = $session_post->$post_field ?? '';
                if (!$overwrite && !$this->is_empty_target($current, $def['type'])) {
                    continue;
                }
                if ($this->values_equal($current, $normalized, $def['type'])) {
                    continue;
                }
                $post_updates[$post_field] = $normalized;
                $changes++;
                continue;
            }

            $meta_key = $def['meta_key'] ?? '';
            if ($meta_key === '') {
                continue;
            }
            $current = get_post_meta($session_id, $meta_key, true);
            if (!$overwrite && !$this->is_empty_target($current, $def['type'])) {
                continue;
            }
            if ($this->values_equal($current, $normalized, $def['type'])) {
                continue;
            }
            update_post_meta($session_id, $meta_key, $normalized);
            $changes++;
        }

        if ($post_updates) {
            $post_updates['ID'] = $session_id;
            self::$sync_lock = true;
            wp_update_post($post_updates);
            self::$sync_lock = false;
        }

        update_post_meta($session_id, '_eh_linked_event_id', $source_post_id);
        if (!empty($settings['source_cpt'])) {
            update_post_meta($session_id, '_eh_linked_event_cpt', $settings['source_cpt']);
        }
        update_post_meta($source_post_id, '_eh_linked_session_id', $session_id);

        return $changes;
    }

    private function find_session_by_linked_event(int $source_post_id, string $source_cpt): int
    {
        $args = [
            'post_type' => self::get_cpt_slug(),
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_eh_linked_event_id',
                    'value' => $source_post_id,
                    'compare' => '=',
                ],
            ],
        ];
        if ($source_cpt !== '') {
            $args['meta_query'][] = [
                'key' => '_eh_linked_event_cpt',
                'value' => $source_cpt,
                'compare' => '=',
            ];
        }
        $query = new \WP_Query($args);
        if ($query->have_posts()) {
            return (int) $query->posts[0];
        }

        if ($source_cpt !== '') {
            $fallback = new \WP_Query([
                'post_type' => self::get_cpt_slug(),
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => '_eh_linked_event_id',
                        'value' => $source_post_id,
                        'compare' => '=',
                    ],
                ],
            ]);
            if ($fallback->have_posts()) {
                return (int) $fallback->posts[0];
            }
        }

        return 0;
    }

    private function resolve_source_value(int $source_post_id, \WP_Post $source_post, string $source_type, string $key)
    {
        if ($source_type === 'meta') {
            return get_post_meta($source_post_id, $key, true);
        }
        if ($source_type === 'post') {
            switch ($key) {
                case 'title':
                    return $source_post->post_title;
                case 'content':
                    return $source_post->post_content;
                case 'excerpt':
                    return $source_post->post_excerpt;
                case 'date':
                    return $source_post->post_date;
                case 'modified':
                    return $source_post->post_modified;
                case 'slug':
                    return $source_post->post_name;
                case 'status':
                    return $source_post->post_status;
                default:
                    return null;
            }
        }
        return null;
    }

    private function normalize_sync_value($value, string $type)
    {
        if (is_array($value)) {
            $value = wp_json_encode($value);
        }
        if (is_object($value)) {
            $value = wp_json_encode($value);
        }
        switch ($type) {
            case 'datetime':
                return $this->normalize_datetime($value);
            case 'int':
                if ($value === '' || $value === null) {
                    return null;
                }
                return (int) $value;
            case 'float':
                if ($value === '' || $value === null) {
                    return null;
                }
                return (float) $value;
            case 'bool':
                if (is_string($value)) {
                    $lower = strtolower(trim($value));
                    if (in_array($lower, ['0', 'false', 'nee', 'no', 'off', ''], true)) {
                        return 0;
                    }
                    return 1;
                }
                return $value ? 1 : 0;
            case 'url':
                return esc_url_raw((string) $value);
            case 'color':
                return sanitize_hex_color((string) $value) ?: '';
            case 'html':
                return wp_kses_post((string) $value);
            case 'text':
            default:
                return sanitize_text_field((string) $value);
        }
    }

    private function normalize_datetime($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_numeric($value)) {
            $ts = (int) $value;
        } else {
            $ts = strtotime((string) $value);
        }
        if (!$ts) {
            return '';
        }
        return gmdate('Y-m-d H:i:00', $ts);
    }

    private function is_empty_target($value, string $type): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value)) {
            return trim($value) === '';
        }
        if (is_array($value)) {
            return empty($value);
        }
        if (is_numeric($value) || is_bool($value)) {
            return false;
        }
        return empty($value);
    }

    private function values_equal($current, $next, string $type): bool
    {
        switch ($type) {
            case 'int':
            case 'bool':
                return (int) $current === (int) $next;
            case 'float':
                return (float) $current === (float) $next;
            default:
                return (string) $current === (string) $next;
        }
    }

    public function maybe_notice_linked_sync(): void
    {
        if (!is_admin()) {
            return;
        }
        if (!isset($_GET['page']) || $_GET['page'] !== 'event-hub-general') {
            return;
        }
        $notice = get_transient('event_hub_sync_notice');
        if (!$notice || !is_array($notice)) {
            return;
        }
        delete_transient('event_hub_sync_notice');
        $type = ($notice['type'] ?? '') === 'error' ? 'notice-error' : 'notice-success';
        $message = $notice['message'] ?? '';
        $counts = [];
        foreach (['created' => __('Aangemaakt', 'event-hub'), 'updated' => __('Bijgewerkt', 'event-hub'), 'skipped' => __('Overgeslagen', 'event-hub'), 'errors' => __('Fouten', 'event-hub')] as $key => $label) {
            if (isset($notice[$key])) {
                $counts[] = $label . ': ' . (int) $notice[$key];
            }
        }
        if ($counts) {
            $message .= ' ' . implode(' | ', $counts);
        }
        echo '<div class="notice ' . esc_attr($type) . '"><p>' . esc_html(trim($message)) . '</p></div>';
    }

    public function sanitize_settings($input): array
    {
        $out = [];
        $out['from_name'] = isset($input['from_name']) ? sanitize_text_field((string) $input['from_name']) : '';
        $out['from_email'] = isset($input['from_email']) ? sanitize_email((string) $input['from_email']) : '';
        $transport = $input['mail_transport'] ?? 'php';
        $out['mail_transport'] = in_array($transport, ['php', 'smtp_plugin'], true) ? $transport : 'php';
        $allowed_timing = ['immediate','before_start','after_end'];
        $conf_mode = $input['confirmation_timing_mode'] ?? 'immediate';
        $out['confirmation_timing_mode'] = in_array($conf_mode, $allowed_timing, true) ? $conf_mode : 'immediate';
        if (isset($input['confirmation_timing_hours']) && $input['confirmation_timing_hours'] !== '') {
            $out['confirmation_timing_hours'] = max(0, (int) $input['confirmation_timing_hours']);
        } else {
            $out['confirmation_timing_hours'] = 24;
        }
        // Reminder in uren, met fallback vanaf legacy dagenveld.
        if (isset($input['reminder_offset_hours']) && $input['reminder_offset_hours'] !== '') {
            $out['reminder_offset_hours'] = max(0, (int) $input['reminder_offset_hours']);
        } elseif (isset($input['reminder_offset_days']) && $input['reminder_offset_days'] !== '') {
            $out['reminder_offset_hours'] = max(0, (int) $input['reminder_offset_days']) * 24;
        } else {
            $out['reminder_offset_hours'] = 24;
        }
        $out['cancel_cutoff_hours'] = isset($input['cancel_cutoff_hours']) ? max(0, (int) $input['cancel_cutoff_hours']) : 24;
        $out['followup_offset_hours'] = isset($input['followup_offset_hours']) ? max(0, (int) $input['followup_offset_hours']) : 24;
        $wait_mode = $input['waitlist_timing_mode'] ?? 'immediate';
        $out['waitlist_timing_mode'] = in_array($wait_mode, $allowed_timing, true) ? $wait_mode : 'immediate';
        if (isset($input['waitlist_timing_hours']) && $input['waitlist_timing_hours'] !== '') {
            $out['waitlist_timing_hours'] = max(0, (int) $input['waitlist_timing_hours']);
        } else {
            $out['waitlist_timing_hours'] = 24;
        }
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
        $requested_cpt = isset($input['cpt_slug']) ? sanitize_key((string) $input['cpt_slug']) : 'eh_session';
        if ($out['use_external_cpt']) {
            $resolved = self::resolve_post_type_slug($requested_cpt);
            $out['cpt_slug'] = $resolved ?: 'eh_session';
        } else {
            $out['cpt_slug'] = $requested_cpt ?: 'eh_session';
        }
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
        $out['linked_sync_enabled'] = !empty($input['linked_sync_enabled']) ? 1 : 0;
        $mode = $input['linked_sync_mode'] ?? 'manual';
        $out['linked_sync_mode'] = in_array($mode, ['manual', 'on_save'], true) ? $mode : 'manual';
        $strategy = $input['linked_sync_strategy'] ?? 'fill_empty';
        $out['linked_sync_strategy'] = in_array($strategy, ['fill_empty', 'overwrite'], true) ? $strategy : 'fill_empty';
        $source_cpt = isset($input['linked_sync_source_cpt']) ? sanitize_key((string) $input['linked_sync_source_cpt']) : '';
        $out['linked_sync_source_cpt'] = $source_cpt !== '' ? self::resolve_post_type_slug($source_cpt) : '';
        $out['linked_sync_map'] = $this->sanitize_sync_map($input['linked_sync_map'] ?? []);
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
            'linked_sync_enabled' => 0,
            'linked_sync_mode' => 'manual',
            'linked_sync_strategy' => 'fill_empty',
            'linked_sync_source_cpt' => '',
            'linked_sync_map' => [],
        ];
        $opts = get_option(self::OPTION_GENERAL, []);
        return wp_parse_args($opts, $defaults);
    }

    public static function get_cpt_slug(): string
    {
        $g = self::get_general();
        return sanitize_key((string) ($g['cpt_slug'] ?? 'eh_session'));
    }

    public static function resolve_post_type_slug(string $slug): string
    {
        $slug = sanitize_key($slug);
        if ($slug === '') {
            return '';
        }
        if (post_type_exists($slug)) {
            return $slug;
        }
        $post_types = get_post_types([], 'objects');
        foreach ($post_types as $pt) {
            if (!empty($pt->rewrite['slug']) && $pt->rewrite['slug'] === $slug) {
                return $pt->name;
            }
            if (is_string($pt->has_archive) && $pt->has_archive === $slug) {
                return $pt->name;
            }
        }
        return $slug;
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
