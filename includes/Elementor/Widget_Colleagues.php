<?php
namespace EventHub\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use EventHub\Settings;

defined('ABSPATH') || exit;

class Widget_Colleagues extends Widget_Base
{
    public function get_name(): string
    {
        return 'eventhub_colleagues';
    }

    public function get_title(): string
    {
        return __('Event Hub Collega’s', 'event-hub');
    }

    public function get_icon(): string
    {
        return 'eicon-person';
    }

    public function get_categories(): array
    {
        return ['event-hub'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('section_content', [
            'label' => __('Bron', 'event-hub'),
        ]);

        $this->add_control('selected', [
            'label' => __('Selecteer collega’s', 'event-hub'),
            'type' => Controls_Manager::SELECT2,
            'multiple' => true,
            'options' => $this->get_colleague_options(),
            'label_block' => true,
            'description' => __('Laat leeg om allemaal te tonen.', 'event-hub'),
        ]);

        $this->add_control('show_email', [
            'label' => __('Toon e-mail', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'no',
        ]);

        $this->add_control('show_phone', [
            'label' => __('Toon telefoon', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'no',
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_layout', [
            'label' => __('Layout', 'event-hub'),
        ]);
        $this->add_control('layout', [
            'label' => __('Weergave', 'event-hub'),
            'type' => Controls_Manager::CHOOSE,
            'options' => [
                'grid' => [
                    'title' => __('Grid', 'event-hub'),
                    'icon' => 'eicon-gallery-grid',
                ],
                'cards' => [
                    'title' => __('Kaarten', 'event-hub'),
                    'icon' => 'eicon-post',
                ],
            ],
            'default' => 'grid',
            'toggle' => false,
        ]);
        $this->add_responsive_control('columns', [
            'label' => __('Kolommen', 'event-hub'),
            'type' => Controls_Manager::NUMBER,
            'default' => 3,
            'min' => 1,
            'max' => 4,
        ]);
        $this->end_controls_section();

        $this->start_controls_section('section_style', [
            'label' => __('Stijl', 'event-hub'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'name_typo',
            'selector' => '{{WRAPPER}} .eh-coll__name',
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'role_typo',
            'selector' => '{{WRAPPER}} .eh-coll__role',
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'bio_typo',
            'selector' => '{{WRAPPER}} .eh-coll__bio',
        ]);
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'card_border',
            'selector' => '{{WRAPPER}} .eh-coll',
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'card_shadow',
            'selector' => '{{WRAPPER}} .eh-coll',
        ]);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $selected_ids = isset($settings['selected']) && is_array($settings['selected']) ? array_map('intval', $settings['selected']) : [];
        $cols = isset($settings['columns']) ? max(1, (int) $settings['columns']) : 3;
        $layout = $settings['layout'] ?? 'grid';
        $show_email = !empty($settings['show_email']) && $settings['show_email'] === 'yes';
        $show_phone = !empty($settings['show_phone']) && $settings['show_phone'] === 'yes';

        $general = Settings::get_general();
        $colleagues = isset($general['colleagues']) && is_array($general['colleagues']) ? $general['colleagues'] : [];
        if ($selected_ids) {
            $colleagues = array_filter($colleagues, static function ($item, $idx) use ($selected_ids) {
                return in_array((int) $idx, $selected_ids, true);
            }, ARRAY_FILTER_USE_BOTH);
        }
        if (!$colleagues) {
            echo '<div class="eh-alert notice">' . esc_html__('Geen collega’s gevonden.', 'event-hub') . '</div>';
            return;
        }

        $wrapper_classes = ['eh-coll-wrap', 'eh-coll-' . $layout];
        $style_attr = 'style="--eh-coll-cols:' . esc_attr((string) $cols) . ';"';
        echo '<div class="' . esc_attr(implode(' ', $wrapper_classes)) . '" ' . $style_attr . '>';
        foreach ($colleagues as $col) {
            $name = trim(($col['first_name'] ?? '') . ' ' . ($col['last_name'] ?? ''));
            $role = $col['role'] ?? '';
            $bio = $col['bio'] ?? '';
            $photo_id = isset($col['photo_id']) ? (int) $col['photo_id'] : 0;
            $photo = $photo_id ? wp_get_attachment_image_url($photo_id, 'medium') : '';
            $email = $col['email'] ?? '';
            $phone = $col['phone'] ?? '';
            $linkedin = $col['linkedin'] ?? '';
            echo '<article class="eh-coll">';
            if ($photo) {
                echo '<div class="eh-coll__media" style="background-image:url(' . esc_url($photo) . ');"></div>';
            } else {
                echo '<div class="eh-coll__media placeholder"></div>';
            }
            echo '<div class="eh-coll__body">';
            echo '<h3 class="eh-coll__name">' . esc_html($name ?: __('Onbekend', 'event-hub')) . '</h3>';
            if ($role) {
                echo '<div class="eh-coll__role">' . esc_html($role) . '</div>';
            }
            if ($bio) {
                echo '<div class="eh-coll__bio">' . wp_kses_post(wpautop($bio)) . '</div>';
            }
            echo '<div class="eh-coll__links">';
            if ($show_email && $email) {
                echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
            }
            if ($show_phone && $phone) {
                echo '<a href="tel:' . esc_attr($phone) . '">' . esc_html($phone) . '</a>';
            }
            if ($linkedin) {
                echo '<a href="' . esc_url($linkedin) . '" target="_blank" rel="noopener">LinkedIn</a>';
            }
            echo '</div>';
            echo '</div>';
            echo '</article>';
        }
        echo '</div>';
        $this->inline_styles();
    }

    private function get_colleague_options(): array
    {
        $general = Settings::get_general();
        $colleagues = isset($general['colleagues']) && is_array($general['colleagues']) ? $general['colleagues'] : [];
        $opts = [];
        foreach ($colleagues as $idx => $col) {
            $name = trim(($col['first_name'] ?? '') . ' ' . ($col['last_name'] ?? ''));
            $opts[(string) $idx] = $name ?: sprintf(__('Collega %d', 'event-hub'), $idx + 1);
        }
        return $opts;
    }

    private function inline_styles(): void
    {
        ?>
        <style>
        .eh-coll-wrap{display:grid;gap:20px}
        .eh-coll-grid{grid-template-columns:repeat(var(--eh-coll-cols,3),minmax(0,1fr))}
        @media(max-width:960px){.eh-coll-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:640px){.eh-coll-grid{grid-template-columns:1fr}}
        .eh-coll{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 6px 18px rgba(15,23,42,.05)}
        .eh-coll__media{width:100%;padding-top:60%;background-size:cover;background-position:center}
        .eh-coll__media.placeholder{background:#f1f5f9}
        .eh-coll__body{padding:14px;display:flex;flex-direction:column;gap:8px}
        .eh-coll__name{margin:0;font-size:18px}
        .eh-coll__role{font-size:14px;color:#475569}
        .eh-coll__bio{font-size:14px;color:#475569}
        .eh-coll__links{display:flex;gap:10px;flex-wrap:wrap;font-size:13px}
        .eh-coll__links a{color:#0ea5e9;text-decoration:none}
        </style>
        <?php
    }
}
