<?php
/**
 * Fallback single template for Event Hub sessions.
 *
 * @var WP_Post $post
 */

use EventHub\Registrations;

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
    $color = sanitize_hex_color((string) get_post_meta($session_id, '_eh_color', true)) ?: '#2271b1';
    $enable_module_meta = get_post_meta($session_id, '_eh_enable_module', true);
    $module_enabled = ($enable_module_meta === '') ? true : (bool) $enable_module_meta;
    $hero_image = get_the_post_thumbnail_url($session_id, 'full');
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
        ];
        $result = $registrations->create_registration($data);
        if (is_wp_error($result)) {
            $error = $result->get_error_message();
        } else {
            $created = $registrations->get_registration($result);
            if ($created && ($created['status'] ?? '') === 'waitlist') {
                $message = __('Bedankt! Je staat nu op de wachtlijst.', 'event-hub');
            } else {
                $message = __('Bedankt! We hebben je inschrijving ontvangen.', 'event-hub');
            }
            $state = $registrations->get_capacity_state($session_id);
            $status = get_post_meta($session_id, '_eh_status', true) ?: 'open';
            $waitlist_count = $state['waitlist'] ?? 0;
        }
    }
    ?>
    <main class="eh-single">
        <section class="eh-single__hero" style="--eh-accent: <?php echo esc_attr($color); ?>;">
            <?php if ($hero_image) : ?>
                <div class="eh-single__hero-media" style="background-image:url('<?php echo esc_url($hero_image); ?>');"></div>
            <?php endif; ?>
            <div class="eh-single__hero-content">
                <div class="eh-hero-top">
                    <?php if ($badge) : ?>
                        <span class="eh-badge-pill <?php echo esc_attr($badge['class']); ?>"><?php echo esc_html($badge['label']); ?></span>
                    <?php endif; ?>
                    <h1><?php the_title(); ?></h1>
                    <?php if ($date_start) : ?>
                        <p class="eh-single__hero-meta">
                            <?php
                            echo esc_html(date_i18n(get_option('date_format'), strtotime($date_start)));
                            echo ' | ';
                            echo esc_html(date_i18n(get_option('time_format'), strtotime($date_start)));
                            if ($date_end) {
                                echo ' - ' . esc_html(date_i18n(get_option('time_format'), strtotime($date_end)));
                            }
                            ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($location_label) : ?>
                        <p class="eh-single__hero-meta"><?php echo esc_html($location_label); ?></p>
                    <?php endif; ?>
                    <div class="eh-cta-bar">
                        <a class="eh-btn" href="<?php echo esc_url($cta_target); ?>" style="background: <?php echo esc_attr($color); ?>;">
                            <?php echo esc_html($cta_label); ?>
                        </a>
                        <?php if (!$module_enabled) : ?>
                            <span class="eh-single__hero-meta"><?php esc_html_e('Inschrijvingen verlopen extern.', 'event-hub'); ?></span>
                        <?php elseif ($state['is_full']) : ?>
                            <span class="eh-single__hero-meta"><?php esc_html_e('Volzet, wachtlijst mogelijk.', 'event-hub'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="eh-stats-grid">
                    <div class="eh-stat-chip">
                        <h4><?php esc_html_e('Beschikbaarheid', 'event-hub'); ?></h4>
                        <p><?php echo esc_html($availability_label); ?></p>
                    </div>
                    <div class="eh-stat-chip">
                        <h4><?php esc_html_e('Wachtlijst', 'event-hub'); ?></h4>
                        <p><?php echo esc_html($waitlist_label); ?></p>
                    </div>
                    <?php if ($location_label) : ?>
                        <div class="eh-stat-chip">
                            <h4><?php esc_html_e('Locatie', 'event-hub'); ?></h4>
                            <p><?php echo esc_html($location_label); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($price !== '') : ?>
                        <div class="eh-stat-chip">
                            <h4><?php esc_html_e('Prijs', 'event-hub'); ?></h4>
                            <p><?php echo esc_html((string) $price); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="eh-single__details" id="eh-details">
            <div class="eh-single__card">
                <h2><?php esc_html_e('Praktische info', 'event-hub'); ?></h2>
                <dl class="eh-def-list">
                    <?php if ($is_online && $online_link) : ?>
                        <div>
                            <dt><?php esc_html_e('Deelnamelink', 'event-hub'); ?></dt>
                            <dd><a href="<?php echo esc_url($online_link); ?>" target="_blank" rel="noopener"><?php echo esc_html($online_link); ?></a></dd>
                        </div>
                    <?php elseif ($address) : ?>
                        <div>
                            <dt><?php esc_html_e('Adres', 'event-hub'); ?></dt>
                            <dd><?php echo esc_html($address); ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($organizer) : ?>
                        <div>
                            <dt><?php esc_html_e('Organisator', 'event-hub'); ?></dt>
                            <dd><?php echo esc_html($organizer); ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($staff) : ?>
                        <div>
                            <dt><?php esc_html_e('Sprekers', 'event-hub'); ?></dt>
                            <dd><?php echo esc_html($staff); ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($price !== '') : ?>
                        <div>
                            <dt><?php esc_html_e('Prijs', 'event-hub'); ?></dt>
                            <dd><?php echo esc_html((string) $price); ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($ticket_note) : ?>
                        <div>
                            <dt><?php esc_html_e('Ticketinfo', 'event-hub'); ?></dt>
                            <dd><?php echo wp_kses_post(nl2br($ticket_note)); ?></dd>
                        </div>
                    <?php endif; ?>
                    <div>
                        <dt><?php esc_html_e('Beschikbaarheid', 'event-hub'); ?></dt>
                        <dd>
                            <?php
                            if ($state['capacity'] > 0) {
                                echo esc_html(sprintf(_n('%d plaats vrij', '%d plaatsen vrij', $state['available'], 'event-hub'), $state['available']));
                                echo ' / ';
                                echo esc_html(sprintf(_n('%d plaats totaal', '%d plaatsen totaal', $state['capacity'], 'event-hub'), $state['capacity']));
                            } else {
                                esc_html_e('Onbeperkt', 'event-hub');
                            }
                            if ($waitlist_count > 0) {
                                echo '<div class="eh-waitlist-note">';
                                echo esc_html(sprintf(_n('%d persoon op de wachtlijst', '%d personen op de wachtlijst', $waitlist_count, 'event-hub'), $waitlist_count));
                                echo '</div>';
                            }
                            ?>
                        </dd>
                    </div>
                    <?php if ($booking_open || $booking_close) : ?>
                        <div>
                            <dt><?php esc_html_e('Inschrijvingen', 'event-hub'); ?></dt>
                            <dd>
                                <?php
                                if ($booking_open) {
                                    printf('<div>%s</div>', esc_html(sprintf(__('Vanaf %s', 'event-hub'), date_i18n(get_option('date_format'), strtotime($booking_open)))));
                                }
                                if ($booking_close) {
                                    printf('<div>%s</div>', esc_html(sprintf(__('Tot %s', 'event-hub'), date_i18n(get_option('date_format'), strtotime($booking_close)))));
                                }
                                ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                </dl>
                <?php
                $colleagues_meta = get_post_meta($session_id, '_eh_colleagues', true);
                $colleagues_ids = [];
                $legacy_colleagues = [];
                if (is_array($colleagues_meta)) {
                    // Detect legacy structure (arrays with first_name keys).
                    if ($colleagues_meta && isset($colleagues_meta[0]) && is_array($colleagues_meta[0]) && array_key_exists('first_name', $colleagues_meta[0])) {
                        $legacy_colleagues = $colleagues_meta;
                    } else {
                        $colleagues_ids = array_map('intval', $colleagues_meta);
                    }
                }
                $global = \EventHub\Settings::get_general();
                $all_colleagues = isset($global['colleagues']) && is_array($global['colleagues']) ? $global['colleagues'] : [];
                $colleagues = [];
                if ($colleagues_ids) {
                    foreach ($colleagues_ids as $cid) {
                        if (isset($all_colleagues[$cid])) {
                            $colleagues[] = $all_colleagues[$cid];
                        }
                    }
                }
                // Fallback: legacy colleagues
                if (!$colleagues && $legacy_colleagues) {
                    $colleagues = $legacy_colleagues;
                }
                if (!empty($colleagues)) :
                    ?>
                    <div class="eh-team-grid">
                        <?php foreach ($colleagues as $colleague) : ?>
                            <div class="eh-team-card">
                                <?php
                                $photo_id = (int) ($colleague['photo_id'] ?? 0);
                                if ($photo_id) {
                                    echo wp_get_attachment_image($photo_id, [160, 160]);
                                } else {
                                    $initials = '';
                                    $fn = trim((string) ($colleague['first_name'] ?? ''));
                                    $ln = trim((string) ($colleague['last_name'] ?? ''));
                                    if ($fn !== '') { $initials .= mb_substr($fn, 0, 1); }
                                    if ($ln !== '') { $initials .= mb_substr($ln, 0, 1); }
                                    echo '<div class="eh-avatar-placeholder">' . esc_html($initials ?: '•') . '</div>';
                                }
                                ?>
                                <div class="eh-team-name"><?php echo esc_html(trim(($colleague['first_name'] ?? '') . ' ' . ($colleague['last_name'] ?? ''))); ?></div>
                                <?php if (!empty($colleague['role'])) : ?>
                                    <div class="eh-team-role"><?php echo esc_html($colleague['role']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="eh-single__content">
                <?php the_content(); ?>
            </div>
        </section>

        <?php if ($module_enabled) : ?>
        <section class="eh-single__form" id="eh-register-form">
            <div class="eh-single__card">
                <h2><?php esc_html_e('Inschrijven', 'event-hub'); ?></h2>

                <?php if ($message) : ?>
                    <div class="eh-alert success"><?php echo esc_html($message); ?></div>
                <?php endif; ?>
                <?php if ($error) : ?>
                    <div class="eh-alert error"><?php echo esc_html($error); ?></div>
                <?php endif; ?>

                <?php
                $booking_open_ts = $booking_open ? strtotime($booking_open) : false;
                $booking_close_ts = $booking_close ? strtotime($booking_close) : false;
                $event_start_ts = $date_start ? strtotime($date_start) : false;
                $now = current_time('timestamp');
                $before_window = $booking_open_ts && $now < $booking_open_ts;
                $after_window = false;
                $after_window_reason = '';
                if ($booking_close_ts && $now > $booking_close_ts) {
                    $after_window = true;
                    $after_window_reason = 'close_date';
                } elseif (!$booking_close_ts && $event_start_ts) {
                    $event_day_start = strtotime(date('Y-m-d 00:00:00', $event_start_ts));
                    if ($event_day_start && $now >= $event_day_start) {
                        $after_window = true;
                        $after_window_reason = 'event_day';
                    }
                }

                $is_active_status = in_array($status, ['open', 'full'], true);
                $waitlist_mode = $is_active_status && !$before_window && !$after_window && $state['is_full'];

                if (!$is_active_status && !$waitlist_mode || $before_window || ($after_window && !$waitlist_mode)) :
                    $notice = '';
                    if ($status === 'cancelled') {
                        $notice = __('Dit event werd geannuleerd.', 'event-hub');
                    } elseif ($status === 'closed') {
                        $notice = __('Inschrijvingen zijn gesloten.', 'event-hub');
                    } elseif ($state['is_full']) {
                        $notice = __('Dit event is volzet.', 'event-hub');
                    } elseif ($before_window) {
                        $notice = __('De inschrijvingen zijn nog niet geopend.', 'event-hub');
                    } elseif ($after_window_reason === 'event_day') {
                        $notice = __('De inschrijvingen sloten op de dag van het event.', 'event-hub');
                    } else {
                        $notice = __('De inschrijvingen zijn afgesloten.', 'event-hub');
                    }
                    echo '<div class="eh-alert notice">' . esc_html($notice) . '</div>';
                else :
                    if ($waitlist_mode) {
                        $waitlist_text = $waitlist_count > 0
                            ? sprintf(
                                __('Dit event is volzet. Er staan momenteel %s op de wachtlijst. Vul je gegevens in om aan te sluiten.', 'event-hub'),
                                sprintf(_n('%d persoon', '%d personen', $waitlist_count, 'event-hub'), $waitlist_count)
                            )
                            : __('Dit event is volzet. Vul je gegevens in om op de wachtlijst te komen.', 'event-hub');
                        echo '<div class="eh-alert notice">' . esc_html($waitlist_text) . '</div>';
                    }
                    ?>
                    <form method="post" class="eh-form-grid">
                        <?php wp_nonce_field('eh_register_' . $session_id, 'eh_register_nonce'); ?>
                        <input type="hidden" name="session_id" value="<?php echo esc_attr((string) $session_id); ?>">
                        <?php if ($waitlist_mode) : ?>
                            <input type="hidden" name="waitlist_opt_in" value="1">
                        <?php endif; ?>
                        <label>
                            <span><?php esc_html_e('Voornaam', 'event-hub'); ?> *</span>
                            <input type="text" name="first_name" required>
                        </label>
                        <label>
                            <span><?php esc_html_e('Familienaam', 'event-hub'); ?> *</span>
                            <input type="text" name="last_name" required>
                        </label>
                        <label>
                            <span><?php esc_html_e('E-mailadres', 'event-hub'); ?> *</span>
                            <input type="email" name="email" required>
                        </label>
                        <label>
                            <span><?php esc_html_e('Telefoon', 'event-hub'); ?></span>
                            <input type="text" name="phone">
                        </label>
                        <label>
                            <span><?php esc_html_e('Bedrijf', 'event-hub'); ?></span>
                            <input type="text" name="company">
                        </label>
                        <label>
                            <span><?php esc_html_e('BTW-nummer', 'event-hub'); ?></span>
                            <input type="text" name="vat">
                        </label>
                        <label>
                            <span><?php esc_html_e('Rol', 'event-hub'); ?></span>
                            <input type="text" name="role">
                        </label>
                        <label>
                            <span><?php esc_html_e('Aantal personen', 'event-hub'); ?></span>
                            <input type="number" name="people_count" min="1" max="<?php echo esc_attr((string) max(1, $state['capacity'] ?: 99)); ?>" value="1">
                        </label>
                        <label class="eh-form-checkbox">
                            <input type="checkbox" name="consent_marketing" value="1">
                            <span><?php esc_html_e('Ik wil marketingupdates ontvangen', 'event-hub'); ?></span>
                        </label>
                        <?php if (\EventHub\Security::captcha_enabled()) : ?>
                            <input type="hidden" name="eh_captcha_token" id="eh_captcha_token" value="">
                            <?php
                            $site_key = \EventHub\Security::site_key();
                            $provider = \EventHub\Security::provider();
                            if ($site_key) {
                                if ($provider === 'hcaptcha') {
                                    echo '<script src="https://js.hcaptcha.com/1/api.js?render=' . esc_attr($site_key) . '" async defer></script>';
                                    echo '<script>window.addEventListener("load",function(){if(window.hcaptcha){hcaptcha.ready(function(){hcaptcha.execute("' . esc_js($site_key) . '",{action:"eventhub_register"}).then(function(token){var el=document.getElementById("eh_captcha_token");if(el){el.value=token;}});});}});</script>';
                                } else {
                                    echo '<script src="https://www.google.com/recaptcha/api.js?render=' . esc_attr($site_key) . '"></script>';
                                    echo '<script>document.addEventListener("DOMContentLoaded",function(){if(window.grecaptcha){grecaptcha.ready(function(){grecaptcha.execute("' . esc_js($site_key) . '",{action:"eventhub_register"}).then(function(token){var el=document.getElementById("eh_captcha_token");if(el){el.value=token;}});});}});</script>';
                                }
                            }
                            ?>
                        <?php endif; ?>
                        <button type="submit" class="eh-btn" style="background: <?php echo esc_attr($color); ?>;">
                            <?php echo esc_html($waitlist_mode ? __('Op wachtlijst plaatsen', 'event-hub') : __('Inschrijven', 'event-hub')); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </section>
        <?php else : ?>
        <section class="eh-single__form" id="eh-register-form">
            <div class="eh-single__card">
                <h2><?php esc_html_e('Inschrijvingen', 'event-hub'); ?></h2>
                <div class="eh-alert notice"><?php esc_html_e('Inschrijvingen voor dit event verlopen extern.', 'event-hub'); ?></div>
            </div>
        </section>
        <?php endif; ?>
    </main>
    <?php
endwhile;

get_footer();
