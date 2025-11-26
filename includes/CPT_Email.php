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
            'show_in_menu' => false,
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
    }
}
