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
                    echo '<p class="description">' . esc_html__('Één placeholder per lijn, formaat: mijn_token=Mijn waarde. Gebruik accolades { } om de tokennaam te markeren.', 'event-hub') . '</p>';
                } elseif ($type === 'number') {
                    echo '<input type="number" min="0" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '" />';
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
                'enable_recaptcha' => 0,
                'recaptcha_provider' => 'google_recaptcha_v3',
                'recaptcha_site_key' => '',
                'recaptcha_secret_key' => '',
                'recaptcha_score' => 0.5,
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

        // Extra optiespagina onder Instellingen (voor beheerders)
        add_options_page(
            __('Event Hub e-mails', 'event-hub'),
            __('Event Hub e-mails', 'event-hub'),
            'manage_options',
            self::OPTION,
            [$this, 'render_page']
        );
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
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
        $out['enable_recaptcha'] = !empty($input['enable_recaptcha']) ? 1 : 0;
        $allowed = ['google_recaptcha_v3','hcaptcha'];
        $out['recaptcha_provider'] = in_array($input['recaptcha_provider'] ?? '', $allowed, true) ? $input['recaptcha_provider'] : 'google_recaptcha_v3';
        $out['recaptcha_site_key'] = isset($input['recaptcha_site_key']) ? sanitize_text_field((string) $input['recaptcha_site_key']) : '';
        $out['recaptcha_secret_key'] = isset($input['recaptcha_secret_key']) ? sanitize_text_field((string) $input['recaptcha_secret_key']) : '';
        $score = isset($input['recaptcha_score']) ? (float) $input['recaptcha_score'] : 0.5;
        $out['recaptcha_score'] = ($score >= 0 && $score <= 1) ? $score : 0.5;
        return $out;
    }

    public static function get_general(): array
    {
        $defaults = [
            'use_external_cpt' => 0,
            'cpt_slug' => 'eh_session',
            'tax_slug' => 'eh_session_type',
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
