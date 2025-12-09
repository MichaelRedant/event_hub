<?php
namespace EventHub\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use EventHub\Settings;

defined('ABSPATH') || exit;

class Widget_Session_Agenda extends Widget_Base
{
    public function get_name(): string
    {
        return 'eventhub_session_agenda';
    }

    public function get_title(): string
    {
        return __('Event agenda/tijdlijn', 'event-hub');
    }

    public function get_icon(): string
    {
        return 'eicon-time-line';
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

        $this->add_control('detect_current', [
            'label' => __('Gebruik huidig event', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->add_control('session_id', [
            'label' => __('Fallback / kies event', 'event-hub'),
            'type' => Controls_Manager::SELECT2,
            'options' => $this->get_session_options(),
            'multiple' => false,
            'label_block' => true,
        ]);

        $this->add_control('heading', [
            'label' => __('Titel', 'event-hub'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Programma', 'event-hub'),
            'label_block' => true,
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_style', [
            'label' => __('Stijl', 'event-hub'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'title_typo',
            'selector' => '{{WRAPPER}} .eh-agenda__title',
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'item_typo',
            'selector' => '{{WRAPPER}} .eh-agenda__item',
        ]);
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'item_border',
            'selector' => '{{WRAPPER}} .eh-agenda__item',
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'item_shadow',
            'selector' => '{{WRAPPER}} .eh-agenda__item',
        ]);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $session_id = $this->resolve_session_id($settings);
        if (!$session_id) {
            echo '<div class="eh-alert notice">' . esc_html__('Geen event gevonden voor agenda.', 'event-hub') . '</div>';
            return;
        }
        $heading = !empty($settings['heading']) ? $settings['heading'] : __('Programma', 'event-hub');

        $agenda_raw = get_post_meta($session_id, '_eh_agenda', true);
        $items = $this->parse_agenda($agenda_raw);
        if (!$items) {
            echo '<div class="eh-alert notice">' . esc_html__('Geen agenda-items ingevoerd.', 'event-hub') . '</div>';
            return;
        }

        echo '<div class="eh-agenda">';
        if ($heading) {
            echo '<h3 class="eh-agenda__title">' . esc_html($heading) . '</h3>';
        }
        echo '<div class="eh-agenda__list">';
        foreach ($items as $item) {
            $time = $item['time'] ?? '';
            $text = $item['text'] ?? '';
            echo '<div class="eh-agenda__item">';
            if ($time) {
                echo '<div class="eh-agenda__time">' . esc_html($time) . '</div>';
            }
            echo '<div class="eh-agenda__text">' . esc_html($text) . '</div>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
        $this->inline_styles();
    }

    private function resolve_session_id(array $settings): int
    {
        $cpt = Settings::get_cpt_slug();
        $use_detect = !empty($settings['detect_current']) && $settings['detect_current'] === 'yes';
        if ($use_detect) {
            $candidate = get_queried_object_id() ?: get_the_ID();
            if ($candidate && get_post_type($candidate) === $cpt) {
                return (int) $candidate;
            }
        }
        if (!empty($settings['session_id'])) {
            $manual = (int) $settings['session_id'];
            if ($manual && get_post_type($manual) === $cpt) {
                return $manual;
            }
        }
        $posts = get_posts([
            'post_type' => $cpt,
            'numberposts' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        return $posts ? (int) $posts[0]->ID : 0;
    }

    /**
     * Parse agenda textarea: each line "HH:MM - tekst" or just "tekst".
     *
     * @return array<int,array{time:string,text:string}>
     */
    private function parse_agenda($raw): array
    {
        $raw = (string) $raw;
        if ($raw === '') {
            return [];
        }
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $time = '';
            $text = $line;
            if (preg_match('/^([0-2]?\d:[0-5]\d)\s*[-–—]\s*(.+)$/', $line, $m)) {
                $time = $m[1];
                $text = $m[2];
            }
            $out[] = ['time' => $time, 'text' => $text];
        }
        return $out;
    }

    private function get_session_options(): array
    {
        $posts = get_posts([
            'post_type' => Settings::get_cpt_slug(),
            'numberposts' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        $opts = [];
        foreach ($posts as $post) {
            $opts[$post->ID] = $post->post_title;
        }
        return $opts;
    }

    private function inline_styles(): void
    {
        ?>
        <style>
        .eh-agenda{padding:16px;border-radius:12px;background:#fff;border:1px solid #e5e7eb;box-shadow:0 10px 30px rgba(15,23,42,.05)}
        .eh-agenda__title{margin:0 0 12px;font-size:20px}
        .eh-agenda__list{display:flex;flex-direction:column;gap:10px}
        .eh-agenda__item{display:grid;grid-template-columns:120px 1fr;gap:12px;padding:12px;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0;align-items:center}
        .eh-agenda__time{font-weight:700;color:#0f172a;font-family:monospace}
        .eh-agenda__text{color:#334155;font-size:15px}
        @media(max-width:600px){.eh-agenda__item{grid-template-columns:1fr}.eh-agenda__time{font-family:inherit;color:#0f172a}}
        </style>
        <?php
    }
}
