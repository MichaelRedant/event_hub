<?php
/**
 * Fallback single template for Event Hub sessions (builder-aware).
 *
 * @var WP_Post $post
 */

use EventHub\Registrations;
use EventHub\Settings;

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();
    $session_id = get_the_ID();
    $registrations = new Registrations();
    $state = $registrations->get_capacity_state($session_id);
    $waitlist_count = $state['waitlist'] ?? 0;
    $status = get_post_meta($session_id, '_eh_status', true) ?: 'open';
    $date_start = get_post_meta($session_id, '_eh_date_start', true);
    $date_end = get_post_meta($session_id, '_eh_date_end', true);
    $location = get_post_meta($session_id, '_eh_location', true);
    $is_online = (bool) get_post_meta($session_id, '_eh_is_online', true);
    $online_link = get_post_meta($session_id, '_eh_online_link', true);
    $address = get_post_meta($session_id, '_eh_address', true);
    $organizer = get_post_meta($session_id, '_eh_organizer', true);
    $staff = get_post_meta($session_id, '_eh_staff', true);
    $price = get_post_meta($session_id, '_eh_price', true);
    $ticket_note = get_post_meta($session_id, '_eh_ticket_note', true);
    $booking_open = get_post_meta($session_id, '_eh_booking_open', true);
    $booking_close = get_post_meta($session_id, '_eh_booking_close', true);
    $hide_fields = array_map('sanitize_key', (array) get_post_meta($session_id, '_eh_form_hide_fields', true));
    $extra_fields = $registrations->get_extra_fields($session_id);
    $color = sanitize_hex_color((string) get_post_meta($session_id, '_eh_color', true)) ?: '#2271b1';
    $enable_module_meta = get_post_meta($session_id, '_eh_enable_module', true);
    $module_enabled = ($enable_module_meta === '') ? true : (bool) $enable_module_meta;
    $hero_image = get_the_post_thumbnail_url($session_id, 'full');
    $general = Settings::get_general();
    $layout = $general['single_layout'] ?? 'modern';
    $layout_class = $layout === 'compact' ? 'layout-compact' : 'layout-modern';
    $builder_sections = [];
    // Local builder override
    $use_local_builder = (int) get_post_meta($session_id, '_eh_use_local_builder', true);
    $builder_json = '';
    if ($use_local_builder) {
        $builder_json = (string) get_post_meta($session_id, '_eh_builder_sections', true);
    } else {
        $builder_json = (string) ($general['single_builder_sections'] ?? '');
    }
    if ($builder_json) {
        $decoded = json_decode($builder_json, true);
        if (is_array($decoded)) {
            $builder_sections = array_filter($decoded, static fn($row) => !empty($row['id']));
        }
    }

    $availability_label = $state['capacity'] > 0
        ? sprintf(__('%1$d / %2$d bezet', 'event-hub'), $state['booked'], $state['capacity'])
        : __('Onbeperkt', 'event-hub');
    $waitlist_label = $waitlist_count > 0
        ? sprintf(_n('%d persoon', '%d personen', $waitlist_count, 'event-hub'), $waitlist_count)
        : __('Geen wachtlijst', 'event-hub');
    $location_label = $is_online ? __('Online', 'event-hub') : ($location ?: '');
    $cta_label = $module_enabled
        ? ($state['is_full'] ? __('Op wachtlijst', 'event-hub') : __('Inschrijven', 'event-hub'))
        : __('Meer info', 'event-hub');
    $cta_target = $module_enabled ? '#eh-register-form' : '#eh-details';

    $badge = (new class {
        public function render(string $status, bool $is_full): array
        {
            if ($status === 'cancelled') {
                return ['label' => __('Geannuleerd', 'event-hub'), 'class' => 'status-cancelled'];
            }
            if ($status === 'closed') {
                return ['label' => __('Gesloten', 'event-hub'), 'class' => 'status-closed'];
            }
            if ($status === 'full' || $is_full) {
                return ['label' => __('Wachtlijst', 'event-hub'), 'class' => 'status-full'];
            }
            return ['label' => __('Beschikbaar', 'event-hub'), 'class' => 'status-open'];
        }
    })->render($status, $state['is_full']);

    $message = '';
    $error = '';
    if (
        isset($_POST['eh_register_nonce'])
        && wp_verify_nonce(sanitize_text_field((string) $_POST['eh_register_nonce']), 'eh_register_' . $session_id)
    ) {
        $extra_input = isset($_POST['extra']) && is_array($_POST['extra']) ? $_POST['extra'] : [];
        $data = [
            'session_id' => $session_id,
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'company' => sanitize_text_field($_POST['company'] ?? ''),
            'vat' => sanitize_text_field($_POST['vat'] ?? ''),
            'role' => sanitize_text_field($_POST['role'] ?? ''),
            'people_count' => isset($_POST['people_count']) ? (int) $_POST['people_count'] : 1,
            'consent_marketing' => isset($_POST['consent_marketing']) ? 1 : 0,
            'waitlist_opt_in' => isset($_POST['waitlist_opt_in']) ? 1 : 0,
            'extra' => $extra_input,
        ];
        $result = $registrations->create_registration($data);
        if (is_wp_error($result)) {
            $error = $result->get_error_message();
        } else {
            $created = $registrations->get_registration($result);
            $message = ($created && ($created['status'] ?? '') === 'waitlist')
                ? __('Bedankt! Je staat nu op de wachtlijst.', 'event-hub')
                : __('Bedankt! We hebben je inschrijving ontvangen.', 'event-hub');
            $state = $registrations->get_capacity_state($session_id);
            $status = get_post_meta($session_id, '_eh_status', true) ?: 'open';
            $waitlist_count = $state['waitlist'] ?? 0;
        }
    }

    // Custom HTML blocks (kept for compatibility with preview slots)
    $custom_before_hero = '';
    $custom_after_hero = '';
    $custom_before_form = '';
    $custom_after_form = '';
    $custom_after_details = '';
    if (!empty($general['single_custom_enabled'])) {
        $custom_before_hero = !empty($general['single_custom_html_before_hero']) ? do_shortcode(wp_kses_post((string) $general['single_custom_html_before_hero'])) : '';
        $custom_after_hero = !empty($general['single_custom_html_after_hero']) ? do_shortcode(wp_kses_post((string) $general['single_custom_html_after_hero'])) : '';
        $custom_before_form = !empty($general['single_custom_html_before_form']) ? do_shortcode(wp_kses_post((string) $general['single_custom_html_before_form'])) : '';
        $custom_after_form = !empty($general['single_custom_html_after_form']) ? do_shortcode(wp_kses_post((string) $general['single_custom_html_after_form'])) : '';
        $custom_after_details = !empty($general['single_custom_html_after_details']) ? do_shortcode(wp_kses_post((string) $general['single_custom_html_after_details'])) : '';
    }

    $render_section = function (string $id, array $style) use (
        $badge,
        $color,
        $hero_image,
        $date_start,
        $date_end,
        $location_label,
        $cta_target,
        $cta_label,
        $module_enabled,
        $state,
        $availability_label,
        $waitlist_label,
        $price,
        $is_online,
        $online_link,
        $address,
        $organizer,
        $staff,
        $booking_open,
        $booking_close,
        $custom_before_form,
        $custom_after_form,
        $custom_after_details,
        $extra_fields,
        $hide_fields,
        $session_id,
        $message,
        $error,
        $ticket_note
    ) {
        $accent = $style['accent'] ?? '';
        $bg = $style['bg'] ?? '';
        $heading = $style['heading'] ?? '';
        $wrap_style = '';
        $pad = isset($style['padding']) ? (int) $style['padding'] : 0;
        $font_size = isset($style['fontSize']) ? (int) $style['fontSize'] : 0;
        $pad_mobile = isset($style['paddingMobile']) ? (int) $style['paddingMobile'] : 0;
        $variant = isset($style['variant']) ? sanitize_key((string) $style['variant']) : 'default';
        $accent_value = $accent ?: $color;
        $base_classes = ['eh-single__card', 'eh-pad-mobile'];
        if ($variant && $variant !== 'default') {
            $base_classes[] = 'eh-variant-' . $variant;
        }
        if (!empty($style['gradient'])) {
            $wrap_style .= 'background:';
            if ($style['gradient'] === 'sunset') { $wrap_style .= 'linear-gradient(135deg,#f97316,#ef4444);'; }
            elseif ($style['gradient'] === 'mint') { $wrap_style .= 'linear-gradient(135deg,#10b981,#34d399);'; }
            elseif ($style['gradient'] === 'ocean') { $wrap_style .= 'linear-gradient(135deg,#0ea5e9,#6366f1);'; }
        } elseif ($bg) {
            $wrap_style .= 'background:' . esc_attr($bg) . ';';
        }
        if (!empty($style['bgImage'])) {
            $wrap_style .= 'background-image:url(' . esc_url($style['bgImage']) . ');background-size:cover;background-position:center;';
        }
        if ($accent_value) {
            $wrap_style .= '--eh-accent:' . esc_attr($accent_value) . ';';
        }
        if ($pad) {
            $wrap_style .= 'padding:' . esc_attr((string) $pad) . 'px;';
        }
        if ($pad_mobile) {
            $wrap_style .= '--eh-pad-mobile:' . esc_attr((string) $pad_mobile) . 'px;';
        }
        switch ($id) {
            case 'status':
                echo '<section class="' . esc_attr(implode(' ', array_map('sanitize_html_class', $base_classes))) . '" style="' . esc_attr($wrap_style) . '">';
                echo '<h2 style="' . ($font_size ? 'font-size:' . esc_attr((string) $font_size) . 'px;' : '') . '">' . esc_html($heading ?: __('Status', 'event-hub')) . '</h2>';
                if ($badge) {
                    echo '<span class="eh-badge-pill ' . esc_attr($badge['class']) . '">' . esc_html($badge['label']) . '</span>';
                }
                echo '<div class="eh-stats-grid">';
                echo '<div class="eh-stat-chip"><h4>' . esc_html__('Beschikbaarheid', 'event-hub') . '</h4><p>' . esc_html($availability_label) . '</p></div>';
                echo '<div class="eh-stat-chip"><h4>' . esc_html__('Wachtlijst', 'event-hub') . '</h4><p>' . esc_html($waitlist_label) . '</p></div>';
                if ($price !== '') {
                    echo '<div class="eh-stat-chip"><h4>' . esc_html__('Prijs', 'event-hub') . '</h4><p>' . esc_html((string) $price) . '</p></div>';
                }
                if ($location_label) {
                    echo '<div class="eh-stat-chip"><h4>' . esc_html__('Locatie', 'event-hub') . '</h4><p>' . esc_html($location_label) . '</p></div>';
                }
                echo '</div>';
                echo '</section>';
                break;
            case 'hero':
                $hero_classes = ['eh-single__hero', 'eh-pad-mobile'];
                if ($variant && $variant !== 'default') {
                    $hero_classes[] = 'eh-variant-' . $variant;
                }
                echo '<section class="' . esc_attr(implode(' ', array_map('sanitize_html_class', $hero_classes))) . '" style="' . esc_attr($wrap_style ?: ('--eh-accent:' . esc_attr($accent_value) . ';')) . '">';
                if ($hero_image) {
                    echo '<div class="eh-single__hero-media" style="background-image:url(' . esc_url($hero_image) . ');"></div>';
                }
                echo '<div class="eh-single__hero-content"><div class="eh-hero-top">';
                if ($badge) {
                    echo '<span class="eh-badge-pill ' . esc_attr($badge['class']) . '">' . esc_html($badge['label']) . '</span>';
                }
                echo '<h1>' . esc_html($heading ?: get_the_title()) . '</h1>';
                if ($date_start) {
                    echo '<p class="eh-single__hero-meta">';
                    echo esc_html(date_i18n(get_option('date_format'), strtotime($date_start))) . ' | ' . esc_html(date_i18n(get_option('time_format'), strtotime($date_start)));
                    if ($date_end) {
                        echo ' - ' . esc_html(date_i18n(get_option('time_format'), strtotime($date_end)));
                    }
                    echo '</p>';
                }
                if ($location_label) {
                    echo '<p class="eh-single__hero-meta">' . esc_html($location_label) . '</p>';
                }
                echo '<div class="eh-cta-bar"><a class="eh-btn" href="' . esc_url($cta_target) . '" style="background:' . esc_attr($accent_value) . ';">' . esc_html($style['cta'] ?? $cta_label) . '</a>';
                if (!$module_enabled) {
                    echo '<span class="eh-single__hero-meta">' . esc_html__('Inschrijvingen verlopen extern.', 'event-hub') . '</span>';
                } elseif ($state['is_full']) {
                    echo '<span class="eh-single__hero-meta">' . esc_html__('Volzet, wachtlijst mogelijk.', 'event-hub') . '</span>';
                }
                echo '</div></div></div></section>';
                break;
            case 'info':
                echo '<section class="' . esc_attr(implode(' ', array_map('sanitize_html_class', $base_classes))) . '" style="' . esc_attr($wrap_style) . '">';
                echo '<h2 style="' . ($font_size ? 'font-size:' . esc_attr((string) $font_size) . 'px;' : '') . '">' . esc_html($heading ?: __('Praktische info', 'event-hub')) . '</h2>';
                echo '<dl class="eh-def-list">';
                if ($is_online && $online_link) {
                    echo '<div><dt>' . esc_html__('Deelnamelink', 'event-hub') . '</dt><dd><a href="' . esc_url($online_link) . '" target="_blank" rel="noopener">' . esc_html($online_link) . '</a></dd></div>';
                } elseif ($address) {
                    echo '<div><dt>' . esc_html__('Adres', 'event-hub') . '</dt><dd>' . esc_html($address) . '</dd></div>';
                }
                if ($organizer) {
                    echo '<div><dt>' . esc_html__('Organisator', 'event-hub') . '</dt><dd>' . esc_html($organizer) . '</dd></div>';
                }
                if ($staff) {
                    echo '<div><dt>' . esc_html__('Sprekers', 'event-hub') . '</dt><dd>' . esc_html($staff) . '</dd></div>';
                }
                if ($price !== '') {
                    echo '<div><dt>' . esc_html__('Prijs', 'event-hub') . '</dt><dd>' . esc_html((string) $price) . '</dd></div>';
                }
                if ($booking_open) {
                    echo '<div><dt>' . esc_html__('Inschrijven vanaf', 'event-hub') . '</dt><dd>' . esc_html(date_i18n(get_option('date_format'), strtotime($booking_open))) . '</dd></div>';
                }
                if ($booking_close) {
                    echo '<div><dt>' . esc_html__('Inschrijven tot', 'event-hub') . '</dt><dd>' . esc_html(date_i18n(get_option('date_format'), strtotime($booking_close))) . '</dd></div>';
                }
                echo '</dl></section>';
                break;
            case 'content':
                echo '<section class="' . esc_attr(implode(' ', array_map('sanitize_html_class', $base_classes))) . '" style="' . esc_attr($wrap_style) . '">';
                echo '<h2 style="' . ($font_size ? 'font-size:' . esc_attr((string) $font_size) . 'px;' : '') . '">' . esc_html($heading ?: __('Over dit event', 'event-hub')) . '</h2>';
                echo '<div class="eh-single__content">';
                the_content();
                if (!empty($ticket_note)) {
                    echo '<p class="eh-single__note">' . esc_html($ticket_note) . '</p>';
                }
                echo '</div></section>';
                break;
            case 'form':
                echo '<section class="' . esc_attr(implode(' ', array_map('sanitize_html_class', $base_classes))) . '" id="eh-register-form" style="' . esc_attr($wrap_style) . '">';
                echo '<h2 style="' . ($font_size ? 'font-size:' . esc_attr((string) $font_size) . 'px;' : '') . '">' . esc_html($heading ?: __('Inschrijven', 'event-hub')) . '</h2>';
                if ($message) {
                    echo '<div class="eh-alert success">' . esc_html($message) . '</div>';
                }
                if ($error) {
                    echo '<div class="eh-alert error">' . esc_html($error) . '</div>';
                }
                if (!$module_enabled) {
                    echo '<p class="eh-alert notice">' . esc_html__('Inschrijvingen voor dit event verlopen extern.', 'event-hub') . '</p>';
                } else {
                    echo '<form method="post" class="eh-form-grid">';
                    wp_nonce_field('eh_register_' . $session_id, 'eh_register_nonce');
                    echo '<label>' . esc_html__('Voornaam', 'event-hub') . '<input type="text" name="first_name" required></label>';
                    echo '<label>' . esc_html__('Familienaam', 'event-hub') . '<input type="text" name="last_name" required></label>';
                    echo '<label>' . esc_html__('E-mail', 'event-hub') . '<input type="email" name="email" required></label>';
                    echo '<label>' . esc_html__('Telefoon', 'event-hub') . '<input type="text" name="phone"></label>';
                    echo '<label>' . esc_html__('Bedrijf', 'event-hub') . '<input type="text" name="company"></label>';
                    if (!in_array('people_count', $hide_fields, true)) {
                        echo '<label>' . esc_html__('Aantal personen', 'event-hub') . '<input type="number" name="people_count" min="1" value="1"></label>';
                    }
                    if (!empty($extra_fields)) {
                        foreach ($extra_fields as $field) {
                            $slug = isset($field['slug']) ? sanitize_key((string) $field['slug']) : '';
                            $label = isset($field['label']) ? sanitize_text_field((string) $field['label']) : '';
                            $required = !empty($field['required']);
                            if ($slug === '' || $label === '') {
                                continue;
                            }
                            echo '<label>' . esc_html($label) . '<input type="text" name="extra[' . esc_attr($slug) . ']" ' . ($required ? 'required' : '') . '></label>';
                        }
                    }
                    echo '<label class="eh-form-checkbox"><input type="checkbox" name="consent_marketing" value="1"><span>' . esc_html__('Ik wil relevante communicatie ontvangen.', 'event-hub') . '</span></label>';
                    if ($state['is_full']) {
                        echo '<label class="eh-form-checkbox"><input type="checkbox" name="waitlist_opt_in" value="1"><span>' . esc_html__('Zet me op de wachtlijst indien volzet.', 'event-hub') . '</span></label>';
                    }
                    echo '<button class="eh-btn" type="submit" style="background:' . esc_attr($accent ?: $color) . ';">' . esc_html($style['cta'] ?? $cta_label) . '</button>';
                    echo '</form>';
                }
                echo '</section>';
                break;
            case 'quote':
                echo '<section class="' . esc_attr(implode(' ', array_map('sanitize_html_class', array_merge($base_classes, ['eh-custom-block'])))) . '" style="' . esc_attr($wrap_style) . '">';
                echo '<h2 style="' . ($font_size ? 'font-size:' . esc_attr((string) $font_size) . 'px;' : '') . '">' . esc_html($heading ?: __('Quote', 'event-hub')) . '</h2>';
                echo '<blockquote class="eh-single__quote">' . esc_html__('Inspirerende quote', 'event-hub') . '</blockquote>';
                echo '</section>';
                break;
            case 'faq':
                echo '<section class="' . esc_attr(implode(' ', array_map('sanitize_html_class', array_merge($base_classes, ['eh-custom-block'])))) . '" style="' . esc_attr($wrap_style) . '">';
                echo '<h2 style="' . ($font_size ? 'font-size:' . esc_attr((string) $font_size) . 'px;' : '') . '">' . esc_html($heading ?: __('FAQ', 'event-hub')) . '</h2>';
                echo '<div class="eh-faq"><p><strong>' . esc_html__('Vraag 1', 'event-hub') . '</strong><br>' . esc_html__('Antwoord 1', 'event-hub') . '</p></div>';
                echo '</section>';
                break;
            case 'agenda':
                echo '<section class="' . esc_attr(implode(' ', array_map('sanitize_html_class', array_merge($base_classes, ['eh-custom-block'])))) . '" style="' . esc_attr($wrap_style) . '">';
                echo '<h2 style="' . ($font_size ? 'font-size:' . esc_attr((string) $font_size) . 'px;' : '') . '">' . esc_html($heading ?: __('Agenda', 'event-hub')) . '</h2>';
                echo '<ul class="eh-agenda"><li>' . esc_html__('10:00 - Intro', 'event-hub') . '</li><li>' . esc_html__('10:30 - Spreker', 'event-hub') . '</li></ul>';
                echo '</section>';
                break;
            case 'buttons':
                echo '<section class="' . esc_attr(implode(' ', array_map('sanitize_html_class', array_merge($base_classes, ['eh-custom-block'])))) . '" style="' . esc_attr($wrap_style) . '">';
                echo '<h2 style="' . ($font_size ? 'font-size:' . esc_attr((string) $font_size) . 'px;' : '') . '">' . esc_html($heading ?: __('CTA knoppen', 'event-hub')) . '</h2>';
                $cta = $heading ?: __('CTA knoppen', 'event-hub');
                echo '<div class="eh-button-stack">';
                echo '<a class="eh-btn" href="#" style="background:' . esc_attr($accent_value) . ';">' . esc_html($style['cta'] ?? __('Knop A', 'event-hub')) . '</a>';
                echo '<a class="eh-btn ghost" href="#" style="color:' . esc_attr($accent_value) . ';border:1px solid ' . esc_attr($accent_value) . ';">' . esc_html($style['cta'] ?? __('Knop B', 'event-hub')) . '</a>';
                echo '</div>';
                echo '</section>';
                break;
            case 'gallery':
                echo '<section class="' . esc_attr(implode(' ', array_map('sanitize_html_class', array_merge($base_classes, ['eh-custom-block'])))) . '" style="' . esc_attr($wrap_style) . '">';
                echo '<h2 style="' . ($font_size ? 'font-size:' . esc_attr((string) $font_size) . 'px;' : '') . '">' . esc_html($heading ?: __('Gallery', 'event-hub')) . '</h2>';
                echo '<div class="eh-gallery"><div></div><div></div><div></div></div>';
                echo '</section>';
                break;
            case 'card':
                echo '<section class="' . esc_attr(implode(' ', array_map('sanitize_html_class', array_merge($base_classes, ['eh-custom-block'])))) . '" style="' . esc_attr($wrap_style) . '">';
                echo '<h2 style="' . ($font_size ? 'font-size:' . esc_attr((string) $font_size) . 'px;' : '') . '">' . esc_html($heading ?: __('Vrij blok', 'event-hub')) . '</h2>';
                echo '<div class="eh-single__content">' . esc_html__('Voeg eigen content toe via custom HTML blokken.', 'event-hub') . '</div>';
                echo '</section>';
                break;
            case 'textblock':
                $body = isset($style['body']) ? (string) $style['body'] : '';
                echo '<section class="' . esc_attr(implode(' ', array_map('sanitize_html_class', array_merge($base_classes, ['eh-custom-block'])))) . '" style="' . esc_attr($wrap_style) . '">';
                echo '<h2 style="' . ($font_size ? 'font-size:' . esc_attr((string) $font_size) . 'px;' : '') . '">' . esc_html($heading ?: __('Tekstblok', 'event-hub')) . '</h2>';
                echo '<div class="eh-single__content">' . ($body ? wpautop(wp_kses_post($body)) : esc_html__('Vrije tekst', 'event-hub')) . '</div>';
                echo '</section>';
                break;
            case 'custom1':
                if (!empty($custom_after_details)) {
                    echo '<section class="' . esc_attr(implode(' ', array_map('sanitize_html_class', array_merge($base_classes, ['eh-custom-block'])))) . '" style="' . esc_attr($wrap_style) . '">' . $custom_after_details . '</section>';
                }
                break;
            case 'custom2':
                if (!empty($custom_before_form) || !empty($custom_after_form)) {
                    echo '<section class="' . esc_attr(implode(' ', array_map('sanitize_html_class', array_merge($base_classes, ['eh-custom-block'])))) . '" style="' . esc_attr($wrap_style) . '">' . $custom_before_form . $custom_after_form . '</section>';
                }
                break;
        }
    };
    ?>
<main class="eh-single <?php echo esc_attr($layout_class); ?>">
    <?php
    if (!empty($builder_sections)) {
        foreach ($builder_sections as $section) {
            $render_section((string) $section['id'], is_array($section) ? $section : []);
        }
    } else {
        // Fallback: hero + info + content + form + custom blocks
        do_action('event_hub_single_before_hero', $session_id);
        if ($custom_before_hero) {
            echo '<div class="eh-custom-block eh-custom-before-hero">' . $custom_before_hero . '</div>';
        }
        $render_section('hero', ['accent' => $color]);
        do_action('event_hub_single_after_hero', $session_id);
        if ($custom_after_hero) {
            echo '<div class="eh-custom-block eh-custom-after-hero">' . $custom_after_hero . '</div>';
        }
        $render_section('info', []);
        $render_section('content', []);
        if ($custom_after_details) {
            echo '<div class="eh-single__card eh-custom-block eh-custom-after-details">' . $custom_after_details . '</div>';
        }
        if ($custom_before_form) {
            echo '<div class="eh-single__card eh-custom-block eh-custom-before-form">' . $custom_before_form . '</div>';
        }
        $render_section('form', ['accent' => $color]);
        if ($custom_after_form) {
            echo '<div class="eh-single__card eh-custom-block eh-custom-after-form">' . $custom_after_form . '</div>';
        }
    }
    ?>
</main>
<?php
endwhile;
get_footer();
