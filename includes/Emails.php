<?php
namespace EventHub;

defined('ABSPATH') || exit;

class Emails
{
    /**
     * Beschikbare placeholders en beschrijvingen.
     * @var array<string,string>
     */
    private const PLACEHOLDERS = [
        '{first_name}'        => 'Voornaam van de deelnemer',
        '{last_name}'         => 'Familienaam van de deelnemer',
        '{full_name}'         => 'Volledige naam (voornaam + familienaam)',
        '{email}'             => 'E-mailadres van de deelnemer',
        '{phone}'             => 'Telefoonnummer van de deelnemer',
        '{company}'           => 'Bedrijf',
        '{vat}'               => 'BTW-nummer',
        '{role}'              => 'Rol of functie',
        '{people_count}'      => 'Aantal gereserveerde plaatsen',
        '{registration_id}'   => 'ID van de inschrijving',
        '{session_id}'        => 'ID van het event',

        '{event_title}'       => 'Titel van het event',
        '{event_excerpt}'     => 'Samenvatting van het event',
        '{event_date}'        => 'Datum (siteformaat)',
        '{event_time}'        => 'Startuur',
        '{event_end_time}'    => 'Einduur',
        '{event_location}'    => 'Locatie',
        '{event_address}'     => 'Adres',
        '{event_online_link}' => 'Online link',
        '{event_language}'    => 'Taal',
        '{event_target}'      => 'Doelgroep',
        '{event_status}'      => 'Status',
        '{event_link}'        => 'Link naar detailpagina',
        '{organizer}'         => 'Organisator',
        '{ticket_note}'       => 'Ticket- of tariefinfo',
        '{price}'             => 'Ticketprijs',
        '{no_show_fee}'       => 'No-showkost',
        '{booking_open}'      => 'Datum waarop inschrijvingen openen',
        '{booking_close}'     => 'Datum waarop inschrijvingen sluiten',
        '{agenda}'            => 'Agenda (per lijn)',
        '{colleagues}'        => 'Collega blok (HTML)',
        '{colleague_names}'   => 'Lijst met collega-namen',
        '{cancel_link}'       => 'Publieke link voor de deelnemer om te annuleren',
        '{cancel_link_html}'  => 'Klikbare HTML-link voor annulatie',

        '{site_name}'         => 'Naam van de site',
        '{site_url}'          => 'URL van de site',
        '{admin_email}'       => 'E-mailadres van de beheerder',
        '{current_date}'      => 'Huidige datum',
    ];

    private Registrations $registrations;
    private ?Logger $logger = null;

    public function __construct(Registrations $registrations, ?Logger $logger = null)
    {
        $this->registrations = $registrations;
        $this->logger = $logger;
    }

    public function init(): void
    {
        add_action('event_hub_registration_created', [$this, 'handle_registration_created'], 10, 1);
        add_action('event_hub_send_reminder', [$this, 'send_reminder'], 10, 1);
        add_action('event_hub_send_followup', [$this, 'send_followup'], 10, 1);
        add_action('event_hub_waitlist_promoted', [$this, 'send_waitlist_promotion'], 10, 1);
        $this->maybe_force_php_transport();
    }

    public function handle_registration_created(int $registration_id): void
    {
        $reg = $this->registrations->get_registration($registration_id);
        if (!$reg || ($reg['status'] ?? '') === 'waitlist') { return; }
        $session_id = (int) $reg['session_id'];
        $start = get_post_meta($session_id, '_eh_date_start', true);
        $end   = get_post_meta($session_id, '_eh_date_end', true);
        $opts  = get_option(Settings::OPTION, []);

        // Send confirmation immediately
        $this->send_confirmation($registration_id);

        // Schedule reminder X hours before start (fallback to days for legacy).
        if ($start) {
            $ts = strtotime($start);
            $reminder_hours = $this->get_reminder_offset_hours($session_id, $opts);
            if ($reminder_hours >= 0 && $ts > time()) {
                $reminder_ts = $ts - ($reminder_hours * HOUR_IN_SECONDS);
                // If the ideal time is already voorbij (bv. late inschrijving), stuur meteen.
                if ($reminder_ts <= time()) {
                    $reminder_ts = time() + MINUTE_IN_SECONDS;
                }
                wp_schedule_single_event($reminder_ts, 'event_hub_send_reminder', [$registration_id]);
                $this->trigger_wp_cron_async();
            }
        }

        // Schedule follow-up Y hours after end or start if no end
        $base = $end ?: $start;
        if ($base) {
            $ts = strtotime($base);
            $event_hours = get_post_meta($session_id, '_eh_followup_offset_hours', true);
            $hours = ($event_hours === '' || $event_hours === null) ? (int) ($opts['followup_offset_hours'] ?? 24) : (int) $event_hours;
            $follow_ts = $ts + ($hours * HOUR_IN_SECONDS);
            if ($follow_ts > time()) {
                wp_schedule_single_event($follow_ts, 'event_hub_send_followup', [$registration_id]);
                $this->trigger_wp_cron_async();
            }
        }
    }

    public function send_confirmation(int $registration_id): void
    {
        $reg = $this->registrations->get_registration($registration_id);
        if (!$reg) { return; }
        $session_id = (int) $reg['session_id'];
        $custom_subj = get_post_meta($session_id, '_eh_email_custom_confirmation_subject', true);
        $custom_body = get_post_meta($session_id, '_eh_email_custom_confirmation_body', true);
        if ($custom_subj !== '' && $custom_body !== '') {
            $this->send_mail_with_placeholders($reg, (string) $custom_subj, (string) $custom_body, 'confirmation_custom');
            return;
        }
        $tpl_ids = (array) get_post_meta($session_id, '_eh_email_confirm_templates', true);
        foreach ($tpl_ids as $tpl_id) {
            $subject = (string) get_post_meta((int)$tpl_id, '_eh_email_subject', true);
            $body    = (string) get_post_meta((int)$tpl_id, '_eh_email_body', true);
            $this->send_mail_with_placeholders($reg, $subject, $body, 'confirmation');
        }
    }

    public function send_reminder(int $registration_id): void
    {
        $reg = $this->registrations->get_registration($registration_id);
        if (!$reg) { return; }

        // Only for active events and registered/confirmed
        $session_status = get_post_meta((int) $reg['session_id'], '_eh_status', true) ?: 'open';
        if ($session_status !== 'open') { return; }
        if (!in_array($reg['status'], ['registered','confirmed'], true)) { return; }

        $session_id = (int) $reg['session_id'];
        $custom_subj = get_post_meta($session_id, '_eh_email_custom_reminder_subject', true);
        $custom_body = get_post_meta($session_id, '_eh_email_custom_reminder_body', true);
        if ($custom_subj !== '' && $custom_body !== '') {
            $this->send_mail_with_placeholders($reg, (string) $custom_subj, (string) $custom_body, 'reminder_custom');
            return;
        }
        $tpl_ids = (array) get_post_meta($session_id, '_eh_email_reminder_templates', true);
        foreach (array_filter($tpl_ids) as $tpl_id) {
            $subject = (string) get_post_meta((int)$tpl_id, '_eh_email_subject', true);
            $body    = (string) get_post_meta((int)$tpl_id, '_eh_email_body', true);
            $this->send_mail_with_placeholders($reg, $subject, $body, 'reminder');
        }
    }

    public function send_followup(int $registration_id): void
    {
        $reg = $this->registrations->get_registration($registration_id);
        if (!$reg) { return; }
        $session_id = (int) $reg['session_id'];
        $custom_subj = get_post_meta($session_id, '_eh_email_custom_followup_subject', true);
        $custom_body = get_post_meta($session_id, '_eh_email_custom_followup_body', true);
        if ($custom_subj !== '' && $custom_body !== '') {
            $this->send_mail_with_placeholders($reg, (string) $custom_subj, (string) $custom_body, 'followup_custom');
            return;
        }
        $tpl_ids = (array) get_post_meta($session_id, '_eh_email_followup_templates', true);
        foreach (array_filter($tpl_ids) as $tpl_id) {
            $subject = (string) get_post_meta((int)$tpl_id, '_eh_email_subject', true);
            $body    = (string) get_post_meta((int)$tpl_id, '_eh_email_body', true);
            $this->send_mail_with_placeholders($reg, $subject, $body, 'followup');
        }
    }

    public function send_waitlist_promotion(int $registration_id): void
    {
        $reg = $this->registrations->get_registration($registration_id);
        if (!$reg) { return; }
        $session_id = (int) $reg['session_id'];
        $custom_subj = get_post_meta($session_id, '_eh_email_custom_waitlist_promotion_subject', true);
        $custom_body = get_post_meta($session_id, '_eh_email_custom_waitlist_promotion_body', true);
        if ($custom_subj !== '' && $custom_body !== '') {
            $this->send_mail_with_placeholders($reg, (string) $custom_subj, (string) $custom_body, 'waitlist_promotion_custom');
            return;
        }
        $tpl_ids = (array) get_post_meta($session_id, '_eh_email_waitlist_templates', true);
        if (!$tpl_ids) {
            return;
        }
        foreach (array_filter($tpl_ids) as $tpl_id) {
            $subject = (string) get_post_meta((int) $tpl_id, '_eh_email_subject', true);
            $body    = (string) get_post_meta((int) $tpl_id, '_eh_email_body', true);
            $this->send_mail_with_placeholders($reg, $subject, $body, 'waitlist_promotion');
        }
    }

    public function send_waitlist_created(int $registration_id): void
    {
        $reg = $this->registrations->get_registration($registration_id);
        if (!$reg) { return; }
        $session_id = (int) $reg['session_id'];
        $custom_subj = get_post_meta($session_id, '_eh_email_custom_waitlist_subject', true);
        $custom_body = get_post_meta($session_id, '_eh_email_custom_waitlist_body', true);
        if ($custom_subj !== '' && $custom_body !== '') {
            $this->send_mail_with_placeholders($reg, (string) $custom_subj, (string) $custom_body, 'waitlist_custom');
            return;
        }
        $tpl_ids = (array) get_post_meta($session_id, '_eh_email_waitlist_templates', true);
        foreach (array_filter($tpl_ids) as $tpl_id) {
            $subject = (string) get_post_meta((int)$tpl_id, '_eh_email_subject', true);
            $body    = (string) get_post_meta((int)$tpl_id, '_eh_email_body', true);
            $this->send_mail_with_placeholders($reg, $subject, $body, 'waitlist');
        }
    }

    public function send_mail_with_placeholders(array $reg, string $subject, string $body, string $type): bool
    {
        $session_id = (int) $reg['session_id'];
        $post = get_post($session_id);
        if (!$post) { return false; }

        $replacements = $this->build_placeholder_map($reg, $post);

        $subject_f = strtr($subject, $replacements);
        $body_f    = strtr($body, $replacements);

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $opts = get_option(Settings::OPTION, []);
        $from_name  = $opts['from_name'] ?? get_bloginfo('name');
        $from_email = $opts['from_email'] ?? get_option('admin_email');

        // Ensure alignment of sender headers for better deliverability.
        $headers[] = 'Reply-To: ' . $from_name . ' <' . $from_email . '>';

        $filter_from = static function () use ($from_email) { return $from_email; };
        $filter_name = static function () use ($from_name)  { return $from_name; };
        add_filter('wp_mail_from', $filter_from);
        add_filter('wp_mail_from_name', $filter_name);
        $sent = wp_mail($reg['email'], $subject_f, $body_f, $headers);
        remove_filter('wp_mail_from', $filter_from);
        remove_filter('wp_mail_from_name', $filter_name);

        if ($sent) {
            do_action('event_hub_email_sent', $type, (int) $reg['id']);
        }
        if ($this->logger) {
            $this->logger->log('email', $sent ? 'E-mail verzonden' : 'E-mail verzenden mislukt', [
                'registration_id' => $reg['id'] ?? '',
                'session_id' => $session_id,
                'type' => $type,
                'to' => $reg['email'],
                'subject' => $subject_f,
                'result' => $sent ? 'sent' : 'failed',
            ]);
        }

        return (bool) $sent;
    }

    /**
     * Verstuur een sjabloon handmatig naar één inschrijving.
     *
     * @return bool|\WP_Error
     */
    public function send_template(int $registration_id, int $template_id, string $context = 'manual')
    {
        $reg = $this->registrations->get_registration($registration_id);
        if (!$reg) {
            return new \WP_Error('registration_missing', __('Inschrijving niet gevonden.', 'event-hub'));
        }
        $template = get_post($template_id);
        if (!$template || $template->post_type !== CPT_Email::CPT) {
            return new \WP_Error('template_missing', __('E-mailsjabloon niet gevonden.', 'event-hub'));
        }
        $subject = (string) get_post_meta($template_id, '_eh_email_subject', true);
        $body    = (string) get_post_meta($template_id, '_eh_email_body', true);
        if ($subject === '' || $body === '') {
            return new \WP_Error('template_empty', __('Dit sjabloon heeft geen onderwerp of inhoud.', 'event-hub'));
        }

        $type = $context === 'manual' ? 'manual_template_' . $template_id : 'custom_template';
        $sent = $this->send_mail_with_placeholders($reg, $subject, $body, $type);
        if (!$sent) {
            return new \WP_Error('mail_failed', __('E-mail verzenden is mislukt.', 'event-hub'));
        }
        return true;
    }

    /**
     * Publieke lijst met placeholders + beschrijving (voor UI).
     */
    public static function get_placeholder_reference(): array
    {
        $map = self::PLACEHOLDERS;
        $custom = Settings::get_custom_placeholders();
        if ($custom) {
            foreach ($custom as $token => $value) {
                $map[$token] = sprintf(__('Aangepast: %s', 'event-hub'), wp_strip_all_tags($value));
            }
        }
        return $map;
    }

    /**
     * Bouwt een map voor strtr() op basis van event + registratie.
     *
     * @param array    $reg
     * @param \WP_Post $post
     * @return array<string,string>
     */
    private function build_placeholder_map(array $reg, \WP_Post $post): array
    {
        $session_id = (int) $reg['session_id'];
        $meta = static function (string $key) use ($session_id) {
            return get_post_meta($session_id, $key, true);
        };

        $start = $meta('_eh_date_start');
        $end   = $meta('_eh_date_end');
        $date_str = $start ? date_i18n(get_option('date_format'), strtotime($start)) : '';
        $time_str = $start ? date_i18n(get_option('time_format'), strtotime($start)) : '';
        $end_time = $end ? date_i18n(get_option('time_format'), strtotime($end)) : '';
        $colleagues = $this->format_colleagues($session_id);
        $cancel_link = '';
        $cancel_link_html = '';
        if (!empty($reg['cancel_token'])) {
            $cancel_link = add_query_arg('eh_cancel', rawurlencode((string) $reg['cancel_token']), home_url('/'));
            $cancel_link_html = sprintf(
                '<a href="%s">%s</a>',
                esc_url($cancel_link),
                esc_html__('Annuleer je inschrijving', 'event-hub')
            );
        }

        $map = [
            '{first_name}'        => $reg['first_name'] ?? '',
            '{last_name}'         => $reg['last_name'] ?? '',
            '{full_name}'         => trim(($reg['first_name'] ?? '') . ' ' . ($reg['last_name'] ?? '')),
            '{email}'             => $reg['email'] ?? '',
            '{phone}'             => $reg['phone'] ?? '',
            '{company}'           => $reg['company'] ?? '',
            '{vat}'               => $reg['vat'] ?? '',
            '{role}'              => $reg['role'] ?? '',
            '{people_count}'      => isset($reg['people_count']) ? (string) $reg['people_count'] : '1',
            '{registration_id}'   => isset($reg['id']) ? (string) $reg['id'] : '',
            '{session_id}'        => (string) $session_id,

            '{event_title}'       => $post->post_title,
            '{event_excerpt}'     => wp_strip_all_tags($post->post_excerpt ?: wp_trim_words($post->post_content, 30)),
            '{event_date}'        => $date_str,
            '{event_time}'        => $time_str,
            '{event_end_time}'    => $end_time,
            '{event_location}'    => $meta('_eh_location') ?: '',
            '{event_address}'     => $meta('_eh_address') ?: '',
            '{event_online_link}' => $meta('_eh_online_link') ?: '',
            '{event_language}'    => $meta('_eh_language') ?: '',
            '{event_target}'      => $meta('_eh_target_audience') ?: '',
            '{event_status}'      => $meta('_eh_status') ?: 'open',
            '{event_link}'        => get_permalink($session_id),
            '{cancel_link}'       => $cancel_link ? esc_url($cancel_link) : '',
            '{cancel_link_html}'  => $cancel_link_html,
            '{organizer}'         => $meta('_eh_organizer') ?: '',
            '{ticket_note}'       => $meta('_eh_ticket_note') ?: '',
            '{price}'             => $meta('_eh_price') !== '' ? (string) $meta('_eh_price') : '',
            '{no_show_fee}'       => $meta('_eh_no_show_fee') !== '' ? (string) $meta('_eh_no_show_fee') : '',
            '{booking_open}'      => $meta('_eh_booking_open') ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($meta('_eh_booking_open'))) : '',
            '{booking_close}'     => $meta('_eh_booking_close') ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($meta('_eh_booking_close'))) : '',
            '{agenda}'            => $this->format_agenda($meta('_eh_agenda')),
            '{colleagues}'        => $colleagues['html'],
            '{colleague_names}'   => $colleagues['names'],

            '{site_name}'         => get_bloginfo('name'),
            '{site_url}'          => home_url('/'),
            '{admin_email}'       => get_option('admin_email'),
            '{current_date}'      => date_i18n(get_option('date_format')),
        ];

        $custom = Settings::get_custom_placeholders();
        if ($custom) {
            foreach ($custom as $token => $value) {
                $map[$token] = wp_strip_all_tags($value);
            }
        }

        return $map;
    }

    /**
     * Berekent reminder-offset in uren met legacy fallback (dagen).
     */
    private function get_reminder_offset_hours(int $session_id, array $opts): int
    {
        $meta_hours = get_post_meta($session_id, '_eh_reminder_offset_hours', true);
        $meta_days  = get_post_meta($session_id, '_eh_reminder_offset_days', true); // legacy
        if ($meta_hours !== '' && $meta_hours !== null) {
            return max(0, (int) $meta_hours);
        }
        if ($meta_days !== '' && $meta_days !== null) {
            return max(0, (int) $meta_days * 24);
        }

        $global_hours = $opts['reminder_offset_hours'] ?? null;
        if ($global_hours !== null && $global_hours !== '') {
            return max(0, (int) $global_hours);
        }

        $global_days = $opts['reminder_offset_days'] ?? 3; // legacy option
        return max(0, (int) $global_days * 24);
    }

    /**
     * Fail-safe: stuur herinneringen die vervallen zijn maar (nog) niet verzonden wegens cron issues.
     * Gebruikt transients om dubbele verzending te vermijden.
     */
    public function force_due_reminders(): int
    {
        global $wpdb;
        $sent = 0;
        $now = current_time('timestamp');
        $table = $this->registrations->get_table();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, session_id, status FROM {$table} WHERE status IN ('registered','confirmed') AND session_id > 0"
            ),
            ARRAY_A
        );
        if (!$rows) {
            return 0;
        }
        $opts = get_option(Settings::OPTION, []);
        foreach ($rows as $row) {
            $session_id = (int) $row['session_id'];
            $start = get_post_meta($session_id, '_eh_date_start', true);
            if (!$start) {
                continue;
            }
            $start_ts = strtotime($start);
            if (!$start_ts) {
                continue;
            }
            $offset_hours = $this->get_reminder_offset_hours($session_id, $opts);
            $due_ts = $start_ts - ($offset_hours * HOUR_IN_SECONDS);
            if ($due_ts <= $now && $start_ts >= $now - 3600) { // niet te ver in het verleden
                $key = 'event_hub_reminder_sent_' . $row['id'];
                if (get_transient($key)) {
                    continue;
                }
                $this->send_reminder((int) $row['id']);
                set_transient($key, 1, DAY_IN_SECONDS * 2);
                $sent++;
            }
        }
        return $sent;
    }

    /**
     * Probeer wp-cron te triggeren zonder blokkeren (loopback).
     */
    private function trigger_wp_cron_async(): void
    {
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            return;
        }
        $url = site_url('wp-cron.php');
        wp_remote_post($url, [
            'timeout' => 0.01,
            'blocking' => false,
            'sslverify' => false,
            'body' => [
                'doing_wp_cron' => microtime(true),
            ],
        ]);
    }

    /**
     * Formatteer agenda meta naar HTML lijst (zonder scripts).
     */
    private function format_agenda($raw): string
    {
        if (!$raw) {
            return '';
        }
        $lines = preg_split('/\r\n|\r|\n/', (string) $raw);
        $clean = array_values(array_filter(array_map('trim', $lines)));
        if (!$clean) {
            return '';
        }
        $html = '<ul>';
        foreach ($clean as $line) {
            $html .= '<li>' . esc_html($line) . '</li>';
        }
        $html .= '</ul>';
        return $html;
    }

    /**
     * Formatteer gekoppelde collega’s als HTML en namenlijst voor e-mail.
     *
     * @return array{html:string,names:string}
     */
    private function format_colleagues(int $session_id): array
    {
        $out = ['html' => '', 'names' => ''];
        $general = Settings::get_general();
        $global = isset($general['colleagues']) && is_array($general['colleagues']) ? $general['colleagues'] : [];
        if (!$global) {
            return $out;
        }
        $selected = (array) get_post_meta($session_id, '_eh_colleagues', true);
        if (!$selected) {
            return $out;
        }

        $cards = [];
        $names = [];
        foreach ($selected as $cid) {
            if (!isset($global[$cid])) {
                continue;
            }
            $c = $global[$cid];
            $name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
            $role = $c['role'] ?? '';
            $email = $c['email'] ?? '';
            $phone = $c['phone'] ?? '';
            $linkedin = $c['linkedin'] ?? '';
            $bio = $c['bio'] ?? '';
            $photo_id = (int) ($c['photo_id'] ?? 0);
            $photo = $photo_id ? wp_get_attachment_image_url($photo_id, 'medium') : '';

            if ($name !== '') {
                $names[] = $name;
            }

            $card  = '<div class="eh-email-colleague" style="border:1px solid #e5e7eb;border-radius:12px;padding:10px;margin-bottom:8px;display:flex;gap:10px;align-items:flex-start;">';
            if ($photo) {
                $card .= '<div style="flex:0 0 56px;width:56px;height:56px;border-radius:12px;overflow:hidden;background:#f2f4f7;"><img src="' . esc_url($photo) . '" alt="' . esc_attr($name) . '" style="width:100%;height:100%;object-fit:cover;display:block;"></div>';
            }
            $card .= '<div style="flex:1;min-width:0;">';
            if ($name !== '') {
                $card .= '<div style="font-weight:700;color:#0f172a;">' . esc_html($name) . '</div>';
            }
            if ($role !== '') {
                $card .= '<div style="color:#475569;font-size:13px;">' . esc_html($role) . '</div>';
            }
            if ($bio !== '') {
                $card .= '<div style="color:#475569;font-size:13px;margin-top:4px;">' . wp_kses_post($bio) . '</div>';
            }
            if ($email || $phone || $linkedin) {
                $card .= '<div style="color:#0f172a;font-size:13px;margin-top:6px;line-height:1.5;">';
                if ($email) {
                    $card .= '<div><a href="mailto:' . esc_attr($email) . '" style="color:#0ea5e9;text-decoration:none;">' . esc_html($email) . '</a></div>';
                }
                if ($phone) {
                    $card .= '<div>' . esc_html($phone) . '</div>';
                }
                if ($linkedin) {
                    $card .= '<div><a href="' . esc_url($linkedin) . '" style="color:#0ea5e9;text-decoration:none;">' . esc_html($linkedin) . '</a></div>';
                }
                $card .= '</div>';
            }
            $card .= '</div></div>';
            $cards[] = $card;
        }

        if ($cards) {
            $out['html'] = implode('', $cards);
        }
        if ($names) {
            $out['names'] = implode(', ', $names);
        }
        return $out;
    }

    /**
     * Force PHP mail transport when selected, bypassing SMTP overrides.
     */
    private function maybe_force_php_transport(): void
    {
        $opts = Settings::get_email_settings();
        if (($opts['mail_transport'] ?? 'php') !== 'php') {
            return;
        }
        add_action('phpmailer_init', static function ($phpmailer) {
            if (is_object($phpmailer) && method_exists($phpmailer, 'isMail')) {
                $phpmailer->isMail();
            }
        }, 1);
    }
}


