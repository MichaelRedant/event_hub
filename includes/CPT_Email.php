<?php
namespace EventHub;

defined('ABSPATH') || exit;

class CPT_Email
{
    public const CPT = 'eh_email';

    public function register_post_type(): void
    {
        $labels = [
            'name' => __('E-mailsjablonen', 'event-hub'),
            'singular_name' => __('E-mailsjabloon', 'event-hub'),
            'add_new' => __('Nieuw toevoegen', 'event-hub'),
            'add_new_item' => __('Nieuw e-mailsjabloon', 'event-hub'),
            'edit_item' => __('Sjabloon bewerken', 'event-hub'),
            'new_item' => __('Nieuw sjabloon', 'event-hub'),
            'view_item' => __('Sjabloon bekijken', 'event-hub'),
            'search_items' => __('Sjablonen zoeken', 'event-hub'),
            'not_found' => __('Geen sjablonen gevonden', 'event-hub'),
            'menu_name' => __('E-mailsjablonen', 'event-hub'),
        ];

        register_post_type(self::CPT, [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'event-hub',
            'supports' => ['title'],
        ]);
    }

    public function register_meta_boxes(): void
    {
        add_meta_box(
            'eh_email_details',
            __('Details', 'event-hub'),
            [$this, 'render_meta_box'],
            self::CPT,
            'normal',
            'default'
        );
    }

    public function render_meta_box(\WP_Post $post): void
    {
        wp_nonce_field('eh_email_meta', 'eh_email_meta_nonce');
        $subject = get_post_meta($post->ID, '_eh_email_subject', true);
        $body = get_post_meta($post->ID, '_eh_email_body', true);
        $type = get_post_meta($post->ID, '_eh_email_type', true);
        $types = [
            '' => __('Vrij', 'event-hub'),
            'confirmation' => __('Bevestiging', 'event-hub'),
            'reminder' => __('Herinnering', 'event-hub'),
            'followup' => __('Nadien', 'event-hub'),
        ];
        echo '<p><label><strong>' . esc_html__('Type', 'event-hub') . '</strong></label><br/>';
        echo '<select name="_eh_email_type">';
        foreach ($types as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($type, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></p>';

        echo '<p><label><strong>' . esc_html__('Onderwerp', 'event-hub') . '</strong></label><br/>';
        echo '<input type="text" class="regular-text" name="_eh_email_subject" value="' . esc_attr((string) $subject) . '" /></p>';

        echo '<p><label><strong>' . esc_html__('Inhoud', 'event-hub') . '</strong></label><br/>';
        wp_editor(
            (string) $body,
            'eh_email_body_editor',
            [
                'textarea_name' => '_eh_email_body',
                'textarea_rows' => 12,
                'media_buttons' => false,
                'teeny'         => false,
                'tinymce'       => true,
                'quicktags'     => true,
            ]
        );

        $placeholders = \EventHub\Emails::get_placeholder_reference();
        echo '<details class="eh-placeholder-panel"><summary>' . esc_html__('Beschikbare placeholders', 'event-hub') . '</summary><ul>';
        foreach ($placeholders as $token => $desc) {
            echo '<li><code>' . esc_html($token) . '</code> - ' . esc_html($desc) . '</li>';
        }
        echo '</ul></details>';

        $current_user = wp_get_current_user();
        $default_test = $current_user && $current_user->user_email ? $current_user->user_email : get_option('admin_email');
        echo '<hr />';
        echo '<h4>' . esc_html__('Test', 'event-hub') . '</h4>';
        echo '<p>' . esc_html__('Verzend een testmail met voorbeelddata.', 'event-hub') . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="eh_email_send_test" />';
        echo '<input type="hidden" name="post_id" value="' . esc_attr((string) $post->ID) . '" />';
        wp_nonce_field('eh_email_send_test', 'eh_email_send_test_nonce');
        echo '<label><span style="display:inline-block;margin-bottom:4px;font-weight:600;">' . esc_html__('Verzend naar', 'event-hub') . '</span><br />';
        echo '<input type="email" name="eh_test_recipient" value="' . esc_attr((string) $default_test) . '" class="regular-text" required /></label>';
        echo '<p><button type="submit" class="button">' . esc_html__('Verzend test', 'event-hub') . '</button></p>';
        echo '</form>';
    }

    public function save_meta_boxes(int $post_id): void
    {
        if (!isset($_POST['eh_email_meta_nonce']) || !wp_verify_nonce(sanitize_text_field((string) $_POST['eh_email_meta_nonce']), 'eh_email_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return; }
        if (!current_user_can('edit_post', $post_id)) { return; }
        if (get_post_type($post_id) !== self::CPT) { return; }

        $subject = isset($_POST['_eh_email_subject']) ? sanitize_text_field((string) $_POST['_eh_email_subject']) : '';
        $body = isset($_POST['_eh_email_body']) ? wp_kses_post((string) $_POST['_eh_email_body']) : '';
        $type = isset($_POST['_eh_email_type']) ? sanitize_text_field((string) $_POST['_eh_email_type']) : '';

        update_post_meta($post_id, '_eh_email_subject', $subject);
        update_post_meta($post_id, '_eh_email_body', $body);
        update_post_meta($post_id, '_eh_email_type', $type);

        $status = get_post_status($post_id);
        if (in_array($status, ['draft', 'auto-draft'], true) && current_user_can('publish_post', $post_id)) {
            remove_action('save_post', [$this, 'save_meta_boxes']);
            wp_update_post([
                'ID' => $post_id,
                'post_status' => 'publish',
            ]);
            add_action('save_post', [$this, 'save_meta_boxes']);
        }
    }

    public function keep_edit_redirect(string $location, int $post_id): string
    {
        if (get_post_type($post_id) !== self::CPT) {
            return $location;
        }

        return add_query_arg(
            [
                'post'   => $post_id,
                'action' => 'edit',
                'message'=> isset($_GET['message']) ? (int) $_GET['message'] : 1,
            ],
            admin_url('post.php')
        );
    }

    public function handle_send_test(): void
    {
        if (!isset($_POST['eh_email_send_test_nonce']) || !wp_verify_nonce(sanitize_text_field((string) $_POST['eh_email_send_test_nonce']), 'eh_email_send_test')) {
            wp_die(__('Ongeldige aanvraag.', 'event-hub'));
        }
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        if (!$post_id || get_post_type($post_id) !== self::CPT) {
            wp_die(__('Ongeldig sjabloon.', 'event-hub'));
        }
        if (!current_user_can('edit_post', $post_id)) {
            wp_die(__('Je hebt geen rechten om deze test te sturen.', 'event-hub'));
        }
        $recipient = isset($_POST['eh_test_recipient']) ? sanitize_email((string) $_POST['eh_test_recipient']) : '';
        if (!$recipient) {
            $fail_url = get_edit_post_link($post_id, 'raw') ?: admin_url('post.php?post=' . $post_id . '&action=edit');
            wp_safe_redirect(add_query_arg(['eh_test_result' => 'fail'], $fail_url));
            exit;
        }
        $subject = (string) get_post_meta($post_id, '_eh_email_subject', true);
        $body = (string) get_post_meta($post_id, '_eh_email_body', true);
        $sample = [
            '{first_name}' => 'Test',
            '{last_name}' => 'Gebruiker',
            '{full_name}' => 'Test Gebruiker',
            '{email}' => $recipient,
            '{event_title}' => __('Voorbeeld event', 'event-hub'),
            '{event_date}' => date_i18n(get_option('date_format')),
            '{event_time}' => date_i18n(get_option('time_format'), strtotime('+1 hour')),
            '{event_location}' => 'Octopus HQ',
            '{event_online_link}' => home_url('/'),
            '{cancel_link}' => home_url('/?eh_cancel=example'),
            '{cancel_link_html}' => '<a href="' . esc_url(home_url('/?eh_cancel=example')) . '">' . esc_html__('Annuleer je inschrijving', 'event-hub') . '</a>',
            '{site_name}' => get_bloginfo('name'),
            '{site_url}' => home_url('/'),
            '{current_date}' => date_i18n(get_option('date_format')),
        ];
        $subject_f = strtr($subject ?: __('Voorbeeldmail', 'event-hub'), $sample);
        $body_f = strtr($body ?: __('Dit is een test van Event Hub.', 'event-hub'), $sample);
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $filter_content_type = static function () { return 'text/html; charset=UTF-8'; };
        add_filter('wp_mail_content_type', $filter_content_type, 999);
        wp_mail($recipient, $subject_f, $body_f, $headers);
        remove_filter('wp_mail_content_type', $filter_content_type, 999);
        $ok_url = get_edit_post_link($post_id, 'raw') ?: admin_url('post.php?post=' . $post_id . '&action=edit');
        wp_safe_redirect(add_query_arg(['eh_test_result' => 'ok'], $ok_url));
        exit;
    }

    public function maybe_notice_test(): void
    {
        if (!is_admin()) {
            return;
        }
        if (!isset($_GET['eh_test_result'], $_GET['post'])) {
            return;
        }
        $post_id = (int) $_GET['post'];
        if (get_post_type($post_id) !== self::CPT) {
            return;
        }
        $result = sanitize_text_field((string) $_GET['eh_test_result']);
        if ($result === 'ok') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Testmail verzonden.', 'event-hub') . '</p></div>';
        } elseif ($result === 'fail') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Testmail versturen mislukt.', 'event-hub') . '</p></div>';
        }
    }
}
