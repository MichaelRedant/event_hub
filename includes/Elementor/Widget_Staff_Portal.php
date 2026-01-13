<?php
namespace EventHub\Elementor;

use Elementor\Widget_Base;
use EventHub\Registrations;
use EventHub\Settings;

defined('ABSPATH') || exit;

class Widget_Staff_Portal extends Widget_Base
{
    public function get_name(): string
    {
        return 'eventhub_staff_portal';
    }

    public function get_title(): string
    {
        return __('Medewerkersportaal', 'event-hub');
    }

    public function get_icon(): string
    {
        return 'eicon-lock-user';
    }

    public function get_categories(): array
    {
        return ['event-hub'];
    }

    public function get_script_depends(): array
    {
        return ['event-hub-staff-portal'];
    }

    public function get_style_depends(): array
    {
        return ['event-hub-staff-portal'];
    }

    protected function register_controls(): void
    {
        // Geen extra controls nodig: data komt vanuit PHP/REST.
    }

    protected function render(): void
    {
        $cpt = Settings::get_cpt_slug();
        $events = get_posts([
            'post_type' => $cpt,
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $registrations = new Registrations();
        $fields = $registrations->get_export_fields();

        if (!wp_script_is('event-hub-staff-portal', 'registered')) {
            wp_register_script(
                'event-hub-staff-portal',
                EVENT_HUB_URL . 'assets/js/staff-portal.js',
                [],
                EVENT_HUB_VERSION,
                true
            );
        }
        wp_enqueue_script('event-hub-staff-portal');

        if (!wp_style_is('event-hub-staff-portal', 'registered')) {
            wp_register_style(
                'event-hub-staff-portal',
                EVENT_HUB_URL . 'assets/css/staff-portal.css',
                [],
                EVENT_HUB_VERSION
            );
        }
        wp_enqueue_style('event-hub-staff-portal');

        $rest_url = rest_url('event-hub/v1/registrations');
        $views_url = rest_url('event-hub/v1/registrations/views');
        $nonce = wp_create_nonce('wp_rest');
        $events_data = array_map(static function ($post) {
            return [
                'id' => (int) $post->ID,
                'title' => $post->post_title,
            ];
        }, $events);
        $occurrence_map = [];
        foreach ($events as $event_post) {
            $occurrences = $registrations->get_occurrences((int) $event_post->ID);
            if (!$occurrences) {
                continue;
            }
            $items = [];
            foreach ($occurrences as $occ) {
                $occ_id = (int) ($occ['id'] ?? 0);
                if ($occ_id <= 0) {
                    continue;
                }
                $start = $occ['date_start'] ?? '';
                $label = $start ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($start)) : ('#' . $occ_id);
                $items[] = [
                    'id' => $occ_id,
                    'label' => $label,
                ];
            }
            if ($items) {
                $occurrence_map[(int) $event_post->ID] = $items;
            }
        }

        if (!$events_data) {
            echo '<div class="eh-staff-portal-note">' . esc_html__('Geen events gevonden om te tonen.', 'event-hub') . '</div>';
            return;
        }

        $data_attrs = sprintf(
            'data-rest="%s" data-views="%s" data-nonce="%s" data-events="%s" data-occurrences="%s" data-fields="%s"',
            esc_url($rest_url),
            esc_url($views_url),
            esc_attr($nonce),
            esc_attr(wp_json_encode($events_data)),
            esc_attr(wp_json_encode($occurrence_map)),
            esc_attr(wp_json_encode($fields))
        );
        ?>
        <div class="eh-staff-portal" <?php echo $data_attrs; ?>>
            <div class="eh-sp-controls">
                <label><?php esc_html_e('Kies event', 'event-hub'); ?>
                    <select class="eh-sp-event">
                        <option value=""><?php esc_html_e('Selecteer een event', 'event-hub'); ?></option>
                        <?php foreach ($events_data as $event): ?>
                            <option value="<?php echo esc_attr((string) $event['id']); ?>"><?php echo esc_html($event['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><?php esc_html_e('Datum', 'event-hub'); ?>
                    <select class="eh-sp-occurrence" disabled>
                        <option value=""><?php esc_html_e('Alle datums', 'event-hub'); ?></option>
                    </select>
                </label>
            </div>
            <div class="eh-sp-fields">
                <p><strong><?php esc_html_e('Velden voor export/overzicht', 'event-hub'); ?></strong></p>
                <div class="eh-sp-field-list"></div>
            </div>
            <div class="eh-sp-views">
                <div>
                    <label><?php esc_html_e('Opgeslagen view', 'event-hub'); ?>
                        <select class="eh-sp-view-select">
                            <option value=""><?php esc_html_e('Kies een view', 'event-hub'); ?></option>
                        </select>
                    </label>
                    <button type="button" class="eh-sp-view-apply button-secondary"><?php esc_html_e('Toepassen', 'event-hub'); ?></button>
                    <button type="button" class="eh-sp-view-delete button-secondary"><?php esc_html_e('Verwijder', 'event-hub'); ?></button>
                </div>
                <div class="eh-sp-view-save">
                    <input type="text" class="eh-sp-view-name" placeholder="<?php esc_attr_e('Naam voor nieuwe view', 'event-hub'); ?>" />
                    <button type="button" class="eh-sp-view-save-btn button"><?php esc_html_e('View opslaan', 'event-hub'); ?></button>
                </div>
            </div>
            <div class="eh-sp-actions">
                <button type="button" class="eh-sp-export-csv"><?php esc_html_e('Exporteer CSV', 'event-hub'); ?></button>
                <button type="button" class="eh-sp-export-html"><?php esc_html_e('Open in nieuw venster (HTML)', 'event-hub'); ?></button>
            </div>
            <div class="eh-sp-status" aria-live="polite"></div>
            <div class="eh-sp-table-wrap">
                <table class="eh-sp-table">
                    <thead></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
        <?php
    }
}
