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
    $occurrences = $registrations->get_occurrences($session_id);
    $selected_occurrence_id = isset($_GET['eh_occurrence']) ? (int) $_GET['eh_occurrence'] : 0;
    if (isset($_POST['occurrence_id'])) {
        $selected_occurrence_id = (int) $_POST['occurrence_id'];
    }
    $selected_occurrence = $selected_occurrence_id ? $registrations->get_occurrence($session_id, $selected_occurrence_id) : null;
    if (!$selected_occurrence && $occurrences) {
        $selected_occurrence = $registrations->get_default_occurrence($session_id);
    }
    $selected_occurrence_id = $selected_occurrence ? (int) ($selected_occurrence['id'] ?? 0) : 0;
    $state = $registrations->get_capacity_state($session_id, $selected_occurrence_id);
    $waitlist_count = $state['waitlist'] ?? 0;
    $status = get_post_meta($session_id, '_eh_status', true) ?: 'open';
    $date_start = $selected_occurrence ? ($selected_occurrence['date_start'] ?? '') : get_post_meta($session_id, '_eh_date_start', true);
    $date_end = $selected_occurrence ? ($selected_occurrence['date_end'] ?? '') : get_post_meta($session_id, '_eh_date_end', true);
    $location = get_post_meta($session_id, '_eh_location', true);
    $is_online = (bool) get_post_meta($session_id, '_eh_is_online', true);
    $online_link = get_post_meta($session_id, '_eh_online_link', true);
    $address = get_post_meta($session_id, '_eh_address', true);
    $organizer = get_post_meta($session_id, '_eh_organizer', true);
    $staff = get_post_meta($session_id, '_eh_staff', true);
    $price = get_post_meta($session_id, '_eh_price', true);
    $ticket_note = get_post_meta($session_id, '_eh_ticket_note', true);
    $booking_open = $selected_occurrence ? ($selected_occurrence['booking_open'] ?? '') : get_post_meta($session_id, '_eh_booking_open', true);
    $booking_close = $selected_occurrence ? ($selected_occurrence['booking_close'] ?? '') : get_post_meta($session_id, '_eh_booking_close', true);
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
    $custom_template = $general['single_custom_code'] ?? '';
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
    // Bepaal of inschrijvingen open zijn (na sluiting/event niet meer tonen).
    $now = current_time('timestamp');
    $start_ts = $date_start ? strtotime($date_start) : null;
    $close_ts = $booking_close ? strtotime($booking_close) : null;
    $open_ts = $booking_open ? strtotime($booking_open) : null;
    $event_day_cutoff = $start_ts ? strtotime(date('Y-m-d 00:00:00', $start_ts)) : null;
    $can_register = $module_enabled;
    $register_notice = '';
    if ($can_register) {
        if (in_array($status, ['cancelled', 'closed'], true)) {
            $can_register = false;
            $register_notice = __('Inschrijvingen zijn gesloten.', 'event-hub');
        } elseif ($open_ts && $now < $open_ts) {
            $can_register = false;
            $register_notice = sprintf(
                __('Inschrijven kan vanaf %s.', 'event-hub'),
                date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $open_ts)
            );
        } elseif (($close_ts && $now > $close_ts) || (!$close_ts && $event_day_cutoff && $now >= $event_day_cutoff)) {
            $can_register = false;
            $register_notice = __('Inschrijvingen zijn gesloten.', 'event-hub');
        }
    }
    $cta_target = '#eh-register-form';
    if (!$module_enabled) {
        $cta_label = __('Meer info', 'event-hub');
    } elseif (!$can_register) {
        $cta_label = __('Inschrijvingen gesloten', 'event-hub');
    } else {
        $cta_label = $state['is_full'] ? __('Op wachtlijst', 'event-hub') : __('Inschrijven', 'event-hub');
    }

    $badge = (new class {
        public function render(string $status, bool $is_full, ?string $start, ?string $end): array
        {
            $now = current_time('timestamp');
            $start_ts = $start ? strtotime($start) : null;
            $end_ts   = $end ? strtotime($end) : null;
            if ($status === 'cancelled') {
                return ['label' => __('Geannuleerd', 'event-hub'), 'class' => 'status-cancelled'];
            }
            if ($status === 'closed') {
                return ['label' => __('Gesloten', 'event-hub'), 'class' => 'status-closed'];
            }
            if ($status === 'full' || $is_full) {
                return ['label' => __('Wachtlijst', 'event-hub'), 'class' => 'status-full'];
            }
            if ($start_ts && $end_ts && $now > $end_ts) {
                return ['label' => __('Afgerond', 'event-hub'), 'class' => 'status-done'];
            }
            if ($start_ts && $now >= $start_ts && (!$end_ts || $now <= $end_ts)) {
                return ['label' => __('Bezig', 'event-hub'), 'class' => 'status-live'];
            }
            return ['label' => __('Beschikbaar', 'event-hub'), 'class' => 'status-open'];
        }
    })->render($status, $state['is_full'], $date_start, $date_end);

    $message = '';
    $error = '';
    if (
        isset($_POST['eh_register_nonce'])
        && wp_verify_nonce(sanitize_text_field((string) $_POST['eh_register_nonce']), 'eh_register_' . $session_id)
    ) {
        $extra_input = isset($_POST['extra']) && is_array($_POST['extra']) ? $_POST['extra'] : [];
        $occurrence_id = isset($_POST['occurrence_id']) ? (int) $_POST['occurrence_id'] : 0;
        $data = [
            'session_id' => $session_id,
            'occurrence_id' => $occurrence_id,
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
            $state = $registrations->get_capacity_state($session_id, $occurrence_id);
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

    // Als er een custom template is opgegeven, render die met placeholders.
    $render_custom_template = function () use (
        $custom_template,
        $badge,
        $hero_image,
        $color,
        $cta_label,
        $cta_target,
        $availability_label,
        $waitlist_label,
        $location_label,
        $organizer,
        $staff,
        $price,
        $booking_open,
        $booking_close,
        $date_start,
        $date_end
    ) {
        if (!$custom_template) {
            return;
        }
        $date_fmt = get_option('date_format');
        $time_fmt = get_option('time_format');
        $start_fmt = $date_start ? date_i18n($date_fmt . ' ' . $time_fmt, strtotime($date_start)) : '';
        $end_fmt = $date_end ? date_i18n($date_fmt . ' ' . $time_fmt, strtotime($date_end)) : '';
        $range = $start_fmt;
        if ($date_end) {
            $range .= ' - ' . date_i18n($time_fmt, strtotime($date_end));
        }
        $agenda_lines = get_post_meta(get_the_ID(), '_eh_agenda', true);
        $agenda_html = '';
        if ($agenda_lines) {
            $lines = preg_split('/\r\n|\r|\n/', (string) $agenda_lines);
            $clean = array_values(array_filter(array_map('trim', $lines)));
            if ($clean) {
                $agenda_html = '<ul class="eh-agenda">';
                foreach ($clean as $line) {
                    $agenda_html .= '<li>' . esc_html($line) . '</li>';
                }
                $agenda_html .= '</ul>';
            }
        }
        $hero_override = get_post_meta(get_the_ID(), '_eh_hero_image_override', true);
        if ($hero_override) {
            $hero_image = $hero_override;
        }
        $colleagues_html = '';
        $global_colleagues = Settings::get_general()['colleagues'] ?? [];
        $selected = (array) get_post_meta(get_the_ID(), '_eh_colleagues', true);
        if ($selected && $global_colleagues) {
            $colleagues_html .= '<div class="eh-colleagues">';
            foreach ($selected as $col_id) {
                if (!isset($global_colleagues[$col_id])) {
                    continue;
                }
                $c = $global_colleagues[$col_id];
                $name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
                $role = $c['role'] ?? '';
                $bio  = $c['bio'] ?? '';
                $photo_id = isset($c['photo_id']) ? (int) $c['photo_id'] : 0;
                $photo_url = $photo_id ? wp_get_attachment_image_url($photo_id, 'thumbnail') : '';
                $colleagues_html .= '<div class="eh-colleague">';
                if ($photo_url) {
                    $colleagues_html .= '<img class="eh-colleague__photo" src="' . esc_url($photo_url) . '" alt="' . esc_attr($name) . '">';
                }
                $colleagues_html .= '<div class="eh-colleague__info"><strong>' . esc_html($name) . '</strong>';
                if ($role) {
                    $colleagues_html .= '<div class="eh-colleague__role">' . esc_html($role) . '</div>';
                }
                if ($bio) {
                    $colleagues_html .= '<div class="eh-colleague__bio">' . wp_kses_post($bio) . '</div>';
                }
                $colleagues_html .= '</div></div>';
            }
            $colleagues_html .= '</div>';
        }
        $colleague_names = '';
        if ($colleagues_html) {
            $colleague_names = implode(', ', array_map(static function ($sel) use ($global_colleagues) {
                if (isset($global_colleagues[$sel])) {
                    return trim(($global_colleagues[$sel]['first_name'] ?? '') . ' ' . ($global_colleagues[$sel]['last_name'] ?? ''));
                }
                return '';
            }, $selected));
            $colleague_names = trim($colleague_names, ' ,');
        }
        $map = [
            // Dubbele accolades
            '{{title}}' => get_the_title(),
            '{{excerpt}}' => get_the_excerpt(),
            '{{date_start}}' => $start_fmt,
            '{{date_end}}' => $end_fmt,
            '{{date_range}}' => $range,
            '{{location}}' => $location_label,
            '{{status_label}}' => $badge['label'] ?? '',
            '{{status_class}}' => $badge['class'] ?? '',
            '{{badge_class}}' => $badge['class'] ?? '',
            '{{hero_image}}' => $hero_image ?: '',
            '{{cta_label}}' => $cta_label,
            '{{cta_link}}' => $cta_target,
            '{{availability}}' => $availability_label,
            '{{waitlist}}' => $waitlist_label,
            '{{organizer}}' => $organizer ?: '',
            '{{staff}}' => $staff ?: '',
            '{{price}}' => $price !== '' ? (string) $price : '',
            '{{booking_open}}' => $booking_open ? date_i18n($date_fmt, strtotime($booking_open)) : '',
            '{{booking_close}}' => $booking_close ? date_i18n($date_fmt, strtotime($booking_close)) : '',
            '{{color}}' => $color,
            '{{agenda}}' => $agenda_html,
            '{{colleagues}}' => $colleagues_html,
            '{{capacity}}' => isset($state['capacity']) ? (string) $state['capacity'] : '',
            '{{colleague_names}}' => $colleague_names,
            // Enkele accolades (email placeholders)
            '{event_title}'       => get_the_title(),
            '{event_excerpt}'     => wp_strip_all_tags(get_the_excerpt()),
            '{event_date}'        => $start_fmt,
            '{event_time}'        => $date_start ? date_i18n(get_option('time_format'), strtotime($date_start)) : '',
            '{event_end_time}'    => $date_end ? date_i18n(get_option('time_format'), strtotime($date_end)) : '',
            '{event_location}'    => $location_label,
            '{event_address}'     => $address ?: '',
            '{event_online_link}' => $online_link ?: '',
            '{event_language}'    => get_post_meta(get_the_ID(), '_eh_language', true) ?: '',
            '{event_target}'      => get_post_meta(get_the_ID(), '_eh_target_audience', true) ?: '',
            '{event_status}'      => $status,
            '{event_link}'        => get_permalink(get_the_ID()),
            '{organizer}'         => $organizer ?: '',
            '{ticket_note}'       => $ticket_note ?: '',
            '{price}'             => $price !== '' ? (string) $price : '',
            '{no_show_fee}'       => get_post_meta(get_the_ID(), '_eh_no_show_fee', true) !== '' ? (string) get_post_meta(get_the_ID(), '_eh_no_show_fee', true) : '',
            '{booking_open}'      => $booking_open ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking_open)) : '',
            '{booking_close}'     => $booking_close ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking_close)) : '',
            '{site_name}'         => get_bloginfo('name'),
            '{site_url}'          => home_url('/'),
            '{admin_email}'       => get_option('admin_email'),
            '{current_date}'      => date_i18n(get_option('date_format')),
            '{status_label}'      => $badge['label'] ?? '',
            '{status_class}'      => $badge['class'] ?? '',
            '{agenda}'            => $agenda_html,
            '{colleagues}'        => $colleagues_html,
            '{capacity}'          => isset($state['capacity']) ? (string) $state['capacity'] : '',
            '{colleague_names}'   => $colleague_names,
            // Basisregistratie placeholders leeg
            '{first_name}'        => '',
            '{last_name}'         => '',
            '{full_name}'         => '',
            '{email}'             => '',
            '{phone}'             => '',
            '{company}'           => '',
            '{vat}'               => '',
            '{role}'              => '',
            '{people_count}'      => '',
            '{registration_id}'   => '',
            '{session_id}'        => (string) get_the_ID(),
        ];
        // Custom placeholders uit settings (zelfde als e-mail)
        $custom = \EventHub\Settings::get_custom_placeholders();
        if ($custom) {
            foreach ($custom as $token => $value) {
                $map[$token] = wp_strip_all_tags($value);
            }
        }
        $html = strtr((string) $custom_template, $map);
        // Verwijder onbekende placeholders, maar laat CSS/HTML intact.
        $html = preg_replace('/\{\{[A-Za-z0-9_:\.-]+\}\}/', '', $html);
        $html = preg_replace('/\{[A-Za-z0-9_:\.-]+\}/', '', $html);
        echo do_shortcode($html);
    };

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
        $can_register,
        $register_notice,
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
                } elseif (!$can_register) {
                    echo '<span class="eh-single__hero-meta">' . esc_html($register_notice ?: __('Inschrijvingen zijn gesloten.', 'event-hub')) . '</span>';
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
                } elseif (!$can_register) {
                    echo '<p class="eh-alert notice">' . esc_html($register_notice ?: __('Inschrijvingen zijn gesloten.', 'event-hub')) . '</p>';
                } else {
                    echo '<form method="post" class="eh-form-grid">';
                    wp_nonce_field('eh_register_' . $session_id, 'eh_register_nonce');
                    if ($occurrences) {
                        echo '<label>' . esc_html__('Kies datum', 'event-hub');
                        echo '<select name="occurrence_id" required>';
                        echo '<option value="">' . esc_html__('Maak een keuze', 'event-hub') . '</option>';
                        foreach ($occurrences as $occ) {
                            $occ_id = (int) ($occ['id'] ?? 0);
                            if ($occ_id <= 0) {
                                continue;
                            }
                            $occ_state = $registrations->get_capacity_state($session_id, $occ_id);
                            $occ_start = $occ['date_start'] ?? '';
                            $occ_end = $occ['date_end'] ?? '';
                            $occ_date = $occ_start ? date_i18n(get_option('date_format'), strtotime($occ_start)) : '';
                            $occ_time_start = $occ_start ? date_i18n(get_option('time_format'), strtotime($occ_start)) : '';
                            $occ_time_end = $occ_end ? date_i18n(get_option('time_format'), strtotime($occ_end)) : '';
                            $occ_time_range = $occ_time_start && $occ_time_end ? $occ_time_start . ' - ' . $occ_time_end : $occ_time_start;
                            $occ_avail = $occ_state['capacity'] > 0
                                ? sprintf(_n('%d plaats beschikbaar', '%d plaatsen beschikbaar', $occ_state['available'], 'event-hub'), $occ_state['available'])
                                : __('Onbeperkt', 'event-hub');
                            $occ_waitlist = $occ_state['waitlist'] > 0
                                ? sprintf(_n('%d persoon op de wachtlijst', '%d personen op de wachtlijst', $occ_state['waitlist'], 'event-hub'), $occ_state['waitlist'])
                                : __('Geen wachtlijst', 'event-hub');
                            $label_parts = array_filter([$occ_date, $occ_time_range, $occ_avail]);
                            $label = implode(' | ', $label_parts);
                            $selected = selected($selected_occurrence_id, $occ_id, false);
                            echo '<option value="' . esc_attr((string) $occ_id) . '"' . $selected
                                . ' data-date-label="' . esc_attr($occ_date) . '"'
                                . ' data-time-range="' . esc_attr($occ_time_range) . '"'
                                . ' data-availability="' . esc_attr($occ_avail) . '"'
                                . ' data-waitlist="' . esc_attr($occ_waitlist) . '"'
                                . ' data-full="' . esc_attr($occ_state['is_full'] ? '1' : '0') . '"'
                                . '>' . esc_html($label ?: (string) $occ_id) . '</option>';
                        }
                        echo '</select></label>';
                    }
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
                    if ($occurrences || $state['is_full']) {
                        echo '<label class="eh-form-checkbox" data-eventhub-waitlist-optin><input type="checkbox" name="waitlist_opt_in" value="1"><span>' . esc_html__('Zet me op de wachtlijst indien volzet.', 'event-hub') . '</span></label>';
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
    if ($custom_template) {
        $render_custom_template();
        // Toon altijd het standaard inschrijfformulier erna.
        $render_section('form', ['accent' => $color]);
    } elseif (!empty($builder_sections)) {
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
