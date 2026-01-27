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
        $this->start_controls_section('section_layout', [
            'label' => __('Weergave', 'event-hub'),
        ]);

        $this->add_control('layout_mode', [
            'label' => __('Layout', 'event-hub'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'table',
            'options' => [
                'table' => __('Tabel', 'event-hub'),
                'cards' => __('Cards', 'event-hub'),
            ],
        ]);

        $this->add_control('accent_color', [
            'label' => __('Accentkleur', 'event-hub'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'default' => '#0f6289',
        ]);

        $this->add_responsive_control('padding', [
            'label' => __('Padding', 'event-hub'),
            'type' => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 48]],
            'default' => ['size' => 16, 'unit' => 'px'],
        ]);

        $this->add_responsive_control('radius', [
            'label' => __('Hoekradius', 'event-hub'),
            'type' => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 32]],
            'default' => ['size' => 12, 'unit' => 'px'],
        ]);

        $this->add_control('gap', [
            'label' => __('Afstand tussen elementen', 'event-hub'),
            'type' => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 32]],
            'default' => ['size' => 10, 'unit' => 'px'],
        ]);

        $this->add_responsive_control('max_width', [
            'label' => __('Maximale breedte', 'event-hub'),
            'type' => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['%', 'px'],
            'range' => [
                '%' => ['min' => 10, 'max' => 100],
                'px' => ['min' => 600, 'max' => 2000],
            ],
            'default' => ['size' => 100, 'unit' => '%'],
        ]);

        $this->add_responsive_control('stack_gap', [
            'label' => __('Verticale afstand blokken', 'event-hub'),
            'type' => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 48]],
            'default' => ['size' => 12, 'unit' => 'px'],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_order', [
            'label' => __('Volgorde onderdelen', 'event-hub'),
        ]);

        $order_options = [
            1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6',
        ];

        $this->add_control('order_controls', [
            'label' => __('Event & datum selectie', 'event-hub'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $order_options,
            'default' => 1,
        ]);
        $this->add_control('order_quickbar', [
            'label' => __('Zoek / filters / stats', 'event-hub'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $order_options,
            'default' => 2,
        ]);
        $this->add_control('order_fields', [
            'label' => __('Kolomkeuze', 'event-hub'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $order_options,
            'default' => 3,
        ]);
        $this->add_control('order_views', [
            'label' => __('Views (opslaan/laden)', 'event-hub'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $order_options,
            'default' => 4,
        ]);
        $this->add_control('order_actions', [
            'label' => __('Acties (exports)', 'event-hub'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $order_options,
            'default' => 5,
        ]);
        $this->add_control('order_table', [
            'label' => __('Resultaat tabel/cards', 'event-hub'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $order_options,
            'default' => 6,
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_style', [
            'label' => __('Stijl', 'event-hub'),
            'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('bg_color', [
            'label' => __('Achtergrond', 'event-hub'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'default' => '#f8fbff',
            'selectors' => [
                '{{WRAPPER}} .eh-staff-portal' => 'background: {{VALUE}};',
            ],
        ]);

        $this->add_control('text_color', [
            'label' => __('Tekstkleur', 'event-hub'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .eh-staff-portal' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('surface_color', [
            'label' => __('Panel achtergrond', 'event-hub'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'default' => '#ffffff',
            'selectors' => [
                '{{WRAPPER}} .eh-staff-portal' => '--eh-sp-surface: {{VALUE}};',
            ],
        ]);

        $this->add_control('border_color', [
            'label' => __('Randkleur', 'event-hub'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'default' => '#dfe6f0',
            'selectors' => [
                '{{WRAPPER}} .eh-staff-portal' => '--eh-sp-border: {{VALUE}};',
            ],
        ]);

        $this->add_control('chip_active', [
            'label' => __('Actieve status chip', 'event-hub'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'default' => '#0f6289',
            'selectors' => [
                '{{WRAPPER}} .eh-staff-portal' => '--eh-sp-chip-active: {{VALUE}};',
            ],
        ]);

        $this->add_control('chip_inactive', [
            'label' => __('Inactieve status chip', 'event-hub'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'default' => '#f8fafc',
            'selectors' => [
                '{{WRAPPER}} .eh-staff-portal' => '--eh-sp-chip-inactive: {{VALUE}};',
            ],
        ]);

        $this->add_control('table_header_bg', [
            'label' => __('Tabel header', 'event-hub'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'default' => '#f2f5fa',
            'selectors' => [
                '{{WRAPPER}} .eh-staff-portal' => '--eh-sp-th: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
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

        $settings = $this->get_settings_for_display();
        $layout_mode = !empty($settings['layout_mode']) ? $settings['layout_mode'] : 'table';
        $accent = !empty($settings['accent_color']) ? $settings['accent_color'] : '#0f6289';
        $pad = isset($settings['padding']['size']) ? (float) $settings['padding']['size'] . ($settings['padding']['unit'] ?? 'px') : '16px';
        $radius = isset($settings['radius']['size']) ? (float) $settings['radius']['size'] . ($settings['radius']['unit'] ?? 'px') : '12px';
        $gap = isset($settings['gap']['size']) ? (float) $settings['gap']['size'] . ($settings['gap']['unit'] ?? 'px') : '10px';
        $max_width = isset($settings['max_width']['size']) ? (float) $settings['max_width']['size'] . ($settings['max_width']['unit'] ?? '%') : '100%';
        $stack_gap = isset($settings['stack_gap']['size']) ? (float) $settings['stack_gap']['size'] . ($settings['stack_gap']['unit'] ?? 'px') : '12px';
        $order_controls = !empty($settings['order_controls']) ? (int) $settings['order_controls'] : 1;
        $order_quickbar = !empty($settings['order_quickbar']) ? (int) $settings['order_quickbar'] : 2;
        $order_fields = !empty($settings['order_fields']) ? (int) $settings['order_fields'] : 3;
        $order_views = !empty($settings['order_views']) ? (int) $settings['order_views'] : 4;
        $order_actions = !empty($settings['order_actions']) ? (int) $settings['order_actions'] : 5;
        $order_table = !empty($settings['order_table']) ? (int) $settings['order_table'] : 6;
        $bg = !empty($settings['bg_color']) ? $settings['bg_color'] : '';
        $text = !empty($settings['text_color']) ? $settings['text_color'] : '';
        $surface = !empty($settings['surface_color']) ? $settings['surface_color'] : '';
        $border = !empty($settings['border_color']) ? $settings['border_color'] : '';
        $chip_active = !empty($settings['chip_active']) ? $settings['chip_active'] : '';
        $chip_inactive = !empty($settings['chip_inactive']) ? $settings['chip_inactive'] : '';
        $th_bg = !empty($settings['table_header_bg']) ? $settings['table_header_bg'] : '';
        $style_vars = sprintf(
            '--eh-sp-accent:%s;--eh-sp-pad:%s;--eh-sp-radius:%s;--eh-sp-gap:%s;--eh-sp-max:%s;--eh-sp-stack-gap:%s;--eh-sp-order-controls:%d;--eh-sp-order-quickbar:%d;--eh-sp-order-fields:%d;--eh-sp-order-views:%d;--eh-sp-order-actions:%d;--eh-sp-order-table:%d;%s%s%s%s%s%s%s',
            esc_attr($accent),
            esc_attr($pad),
            esc_attr($radius),
            esc_attr($gap),
            esc_attr($max_width),
            esc_attr($stack_gap),
            $order_controls,
            $order_quickbar,
            $order_fields,
            $order_views,
            $order_actions,
            $order_table,
            $bg ? '--eh-sp-bg:' . esc_attr($bg) . ';' : '',
            $text ? '--eh-sp-text:' . esc_attr($text) . ';' : '',
            $surface ? '--eh-sp-surface:' . esc_attr($surface) . ';' : '',
            $border ? '--eh-sp-border:' . esc_attr($border) . ';' : '',
            $chip_active ? '--eh-sp-chip-active:' . esc_attr($chip_active) . ';' : '',
            $chip_inactive ? '--eh-sp-chip-inactive:' . esc_attr($chip_inactive) . ';' : '',
            $th_bg ? '--eh-sp-th:' . esc_attr($th_bg) . ';' : ''
        );
        ?>
        <div class="eh-staff-portal" data-layout="<?php echo esc_attr($layout_mode); ?>" style="<?php echo esc_attr($style_vars); ?>" <?php echo $data_attrs; ?>>
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
            <div class="eh-sp-quickbar">
                <div class="eh-sp-search">
                    <input type="text" class="eh-sp-search-input" placeholder="<?php esc_attr_e('Zoek op naam, e-mail of andere velden…', 'event-hub'); ?>" />
                    <div class="eh-sp-result-count" aria-live="polite"></div>
                </div>
                <div class="eh-sp-status-filters" role="group" aria-label="<?php esc_attr_e('Filter op status', 'event-hub'); ?>">
                    <button type="button" class="is-active" data-status=""><?php esc_html_e('Alle statussen', 'event-hub'); ?></button>
                    <button type="button" data-status="registered"><?php esc_html_e('Ingeschreven', 'event-hub'); ?></button>
                    <button type="button" data-status="confirmed"><?php esc_html_e('Bevestigd', 'event-hub'); ?></button>
                    <button type="button" data-status="waitlist"><?php esc_html_e('Wachtlijst', 'event-hub'); ?></button>
                    <button type="button" data-status="cancelled"><?php esc_html_e('Geannuleerd', 'event-hub'); ?></button>
                </div>
                <div class="eh-sp-stats" aria-live="polite">
                    <div class="eh-sp-stat">
                        <span class="eh-sp-stat-label"><?php esc_html_e('Totaal', 'event-hub'); ?></span>
                        <span class="eh-sp-stat-value" data-stat="total">0</span>
                    </div>
                    <div class="eh-sp-stat">
                        <span class="eh-sp-stat-label"><?php esc_html_e('Wachtlijst', 'event-hub'); ?></span>
                        <span class="eh-sp-stat-value" data-stat="waitlist">0</span>
                    </div>
                    <div class="eh-sp-stat">
                        <span class="eh-sp-stat-label"><?php esc_html_e('Geannuleerd', 'event-hub'); ?></span>
                        <span class="eh-sp-stat-value" data-stat="cancelled">0</span>
                    </div>
                </div>
            </div>
            <div class="eh-sp-fields">
                <p><strong><?php esc_html_e('Velden voor export/overzicht', 'event-hub'); ?></strong></p>
                <div class="eh-sp-field-list"></div>
                <div class="eh-sp-field-actions">
                    <input type="text" class="eh-sp-custom-name" placeholder="<?php esc_attr_e('Naam nieuwe (lege) kolom', 'event-hub'); ?>" />
                    <button type="button" class="eh-sp-add-custom button-secondary"><?php esc_html_e('Kolom toevoegen', 'event-hub'); ?></button>
                    <span class="eh-sp-hint"><?php esc_html_e('Versleep of gebruik pijlen om volgorde te wijzigen.', 'event-hub'); ?></span>
                </div>
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
