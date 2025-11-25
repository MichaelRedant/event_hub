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

        '{site_name}'         => 'Naam van de site',
        '{site_url}'          => 'URL van de site',
        '{admin_email}'       => 'E-mailadres van de beheerder',
        '{current_date}'      => 'Huidige datum',
    ];

    private Registrations $registrations;

    public function __construct(Registrations $registrations)
    {
        $this->registrations = $registrations;
    }

    public function init(): void
    {
        add_action('event_hub_registration_created', [$this, 'handle_registration_created'], 10, 1);
        add_action('event_hub_send_reminder', [$this, 'send_reminder'], 10, 1);
        add_action('event_hub_send_followup', [$this, 'send_followup'], 10, 1);
        add_action('event_hub_waitlist_promoted', [$this, 'send_waitlist_promotion'], 10, 1);
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

        // Schedule reminder X days before start
        if ($start) {
            $ts = strtotime($start);
            $days = isset($opts['reminder_offset_days']) ? (int) $opts['reminder_offset_days'] : 3;
            $reminder_ts = $ts - ($days * DAY_IN_SECONDS);
            if ($reminder_ts > time()) {
                wp_schedule_single_event($reminder_ts, 'event_hub_send_reminder', [$registration_id]);
            }
        }

        // Schedule follow-up Y hours after end or start if no end
        $base = $end ?: $start;
        if ($base) {
            $ts = strtotime($base);
            $hours = isset($opts['followup_offset_hours']) ? (int) $opts['followup_offset_hours'] : 24;
            $follow_ts = $ts + ($hours * HOUR_IN_SECONDS);
            if ($follow_ts > time()) {
                wp_schedule_single_event($follow_ts, 'event_hub_send_followup', [$registration_id]);
            }
        }
    }

    public function send_confirmation(int $registration_id): void
    {
        $reg = $this->registrations->get_registration($registration_id);
        if (!$reg) { return; }
        $session_id = (int) $reg['session_id'];
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

    public function send_mail_with_placeholders(array $reg, string $subject, string $body, string $type): bool
    {
        $session_id = (int) $reg['session_id'];
        $post = get_post($session_id);
        if (!$post) { return false; }

        $replacements = $this->build_placeholder_map($reg, $post);

        $subject_f = strtr($subject, $replacements);
        $body_f    = strtr($body, $replacements);

        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        $opts = get_option(Settings::OPTION, []);
        $from_name  = $opts['from_name'] ?? get_bloginfo('name');
        $from_email = $opts['from_email'] ?? get_option('admin_email');

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
            '{organizer}'         => $meta('_eh_organizer') ?: '',
            '{ticket_note}'       => $meta('_eh_ticket_note') ?: '',
            '{price}'             => $meta('_eh_price') !== '' ? (string) $meta('_eh_price') : '',
            '{no_show_fee}'       => $meta('_eh_no_show_fee') !== '' ? (string) $meta('_eh_no_show_fee') : '',
            '{booking_open}'      => $meta('_eh_booking_open') ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($meta('_eh_booking_open'))) : '',
            '{booking_close}'     => $meta('_eh_booking_close') ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($meta('_eh_booking_close'))) : '',

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
}


