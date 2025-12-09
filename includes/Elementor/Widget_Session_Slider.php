<?php
namespace EventHub\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use EventHub\Settings;
use WP_Query;

defined('ABSPATH') || exit;

class Widget_Session_Slider extends Widget_Base
{
    public function get_name(): string
    {
        return 'eventhub_session_slider';
    }

    public function get_title(): string
    {
        return __('Event hero slider', 'event-hub');
    }

    public function get_icon(): string
    {
        return 'eicon-slides';
    }

    public function get_categories(): array
    {
        return ['event-hub'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('section_query', [
            'label' => __('Query', 'event-hub'),
        ]);

        $this->add_control('posts_per_page', [
            'label' => __('Aantal slides', 'event-hub'),
            'type' => Controls_Manager::NUMBER,
            'default' => 5,
            'min' => 1,
            'max' => 10,
        ]);

        $this->add_control('only_future', [
            'label' => __('Enkel toekomstige events', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $terms = get_terms([
            'taxonomy' => Settings::get_tax_slug(),
            'hide_empty' => false,
        ]);
        $tax_options = ['' => __('Alle types', 'event-hub')];
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $tax_options[$term->slug] = $term->name;
            }
        }
        $this->add_control('session_type', [
            'label' => __('Eventtype', 'event-hub'),
            'type' => Controls_Manager::SELECT,
            'options' => $tax_options,
            'default' => '',
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_content', [
            'label' => __('Inhoud', 'event-hub'),
        ]);
        $this->add_control('cta_label', [
            'label' => __('CTA-label', 'event-hub'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Inschrijven', 'event-hub'),
        ]);
        $this->add_control('show_badge', [
            'label' => __('Status-badge tonen', 'event-hub'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);
        $this->end_controls_section();

        $this->start_controls_section('section_style', [
            'label' => __('Stijl', 'event-hub'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'title_typo',
            'selector' => '{{WRAPPER}} .eh-slider__title',
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'meta_typo',
            'selector' => '{{WRAPPER}} .eh-slider__meta',
        ]);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $cpt = Settings::get_cpt_slug();
        $per_page = isset($settings['posts_per_page']) ? (int) $settings['posts_per_page'] : 5;
        $only_future = !empty($settings['only_future']) && $settings['only_future'] === 'yes';

        $meta_query = [];
        $now = current_time('mysql');
        if ($only_future) {
            $meta_query[] = [
                'key' => '_eh_date_start',
                'value' => $now,
                'compare' => '>=',
                'type' => 'DATETIME',
            ];
        }
        $tax_query = [];
        if (!empty($settings['session_type'])) {
            $tax_query[] = [
                'taxonomy' => Settings::get_tax_slug(),
                'field' => 'slug',
                'terms' => $settings['session_type'],
            ];
        }

        $query = new WP_Query([
            'post_type' => $cpt,
            'posts_per_page' => $per_page,
            'meta_key' => '_eh_date_start',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_type' => 'DATETIME',
            'meta_query' => $meta_query,
            'tax_query' => $tax_query,
            'post_status' => 'publish',
        ]);

        if (!$query->have_posts()) {
            echo '<div class="eh-alert notice">' . esc_html__('Geen events gevonden.', 'event-hub') . '</div>';
            return;
        }

        $cta_label = !empty($settings['cta_label']) ? $settings['cta_label'] : __('Inschrijven', 'event-hub');
        $show_badge = !empty($settings['show_badge']) && $settings['show_badge'] === 'yes';

        echo '<div class="eh-slider" data-eh-slider="1">';
        echo '<div class="eh-slider__track">';

        while ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();
            $date_start = get_post_meta($id, '_eh_date_start', true);
            $date_end = get_post_meta($id, '_eh_date_end', true);
            $date_label = $date_start ? date_i18n(get_option('date_format'), strtotime($date_start)) : '';
            $time_label = $date_start ? date_i18n(get_option('time_format'), strtotime($date_start)) : '';
            $location = get_post_meta($id, '_eh_location', true);
            $thumb = get_the_post_thumbnail_url($id, 'large');
            $badge = $show_badge ? $this->get_status_badge($id) : null;
            $link = get_permalink($id);

            echo '<article class="eh-slide">';
            if ($thumb) {
                echo '<div class="eh-slide__media" style="background-image:url(' . esc_url($thumb) . ');"></div>';
            }
            echo '<div class="eh-slide__content">';
            if ($badge) {
                echo '<span class="eh-badge ' . esc_attr($badge['class']) . '">' . esc_html($badge['label']) . '</span>';
            }
            echo '<h3 class="eh-slider__title"><a href="' . esc_url($link) . '">' . esc_html(get_the_title()) . '</a></h3>';
            echo '<div class="eh-slider__meta">';
            if ($date_label) {
                echo '<span>' . esc_html($date_label . ($time_label ? ' • ' . $time_label : '')) . '</span>';
            }
            if ($location) {
                echo '<span>' . esc_html($location) . '</span>';
            }
            echo '</div>';
            echo '<p class="eh-slider__excerpt">' . esc_html(wp_trim_words(get_the_excerpt(), 24)) . '</p>';
            echo '<div class="eh-slide__actions">';
            echo '<a class="eh-btn" href="' . esc_url($link) . '">' . esc_html($cta_label) . '</a>';
            echo '</div>';
            echo '</div>';
            echo '</article>';
        }

        echo '</div>'; // track
        echo '<div class="eh-slider__nav">';
        echo '<button class="eh-slider__prev" type="button" aria-label="' . esc_attr__('Vorige', 'event-hub') . '">&larr;</button>';
        echo '<button class="eh-slider__next" type="button" aria-label="' . esc_attr__('Volgende', 'event-hub') . '">&rarr;</button>';
        echo '</div>';
        echo '</div>';
        wp_reset_postdata();
        $this->inline_assets();
    }

    private function get_status_badge(int $post_id): ?array
    {
        $status = get_post_meta($post_id, '_eh_status', true) ?: 'open';
        if ($status === 'cancelled') {
            return ['label' => __('Geannuleerd', 'event-hub'), 'class' => 'eh-badge-cancelled'];
        }
        if ($status === 'closed') {
            return ['label' => __('Gesloten', 'event-hub'), 'class' => 'eh-badge-closed'];
        }
        if ($status === 'full') {
            return ['label' => __('Wachtlijst', 'event-hub'), 'class' => 'eh-badge-full'];
        }
        return ['label' => __('Beschikbaar', 'event-hub'), 'class' => 'eh-badge-open'];
    }

    private function inline_assets(): void
    {
        ?>
        <style>
        .eh-slider{position:relative;overflow:hidden;border-radius:16px;background:#0b1626;color:#f8fafc;padding:10px}
        .eh-slider__track{display:flex;transition:transform .5s ease}
        .eh-slide{min-width:100%;display:grid;grid-template-columns:1.2fr 1fr;gap:20px;align-items:stretch}
        @media (max-width:960px){.eh-slide{grid-template-columns:1fr;min-width:100%}}
        .eh-slide__media{border-radius:12px;min-height:320px;background-size:cover;background-position:center}
        .eh-slide__content{display:flex;flex-direction:column;gap:12px;padding:16px}
        .eh-slider__title{margin:0;font-size:26px;line-height:1.3}
        .eh-slider__title a{color:#fff;text-decoration:none}
        .eh-slider__meta{display:flex;flex-wrap:wrap;gap:10px;font-size:14px;color:#cbd5e1}
        .eh-slider__excerpt{margin:0;color:#e2e8f0;font-size:15px}
        .eh-slide__actions{margin-top:auto}
        .eh-btn{display:inline-flex;align-items:center;justify-content:center;padding:11px 18px;border-radius:10px;background:#f97316;color:#fff;text-decoration:none;font-weight:700;transition:transform .2s ease,opacity .2s ease}
        .eh-btn:hover{opacity:.92;transform:translateY(-1px)}
        .eh-badge{display:inline-flex;align-items:center;padding:5px 11px;border-radius:999px;font-size:12px;font-weight:700;color:#fff;width:max-content}
        .eh-badge-open{background:#22c55e}
        .eh-badge-full{background:#c026d3}
        .eh-badge-closed{background:#64748b}
        .eh-badge-cancelled{background:#ef4444}
        .eh-slider__nav{position:absolute;inset-block:50% auto;right:16px;display:flex;gap:8px;transform:translateY(-50%)}
        .eh-slider__nav button{background:#111827;color:#fff;border:1px solid #334155;width:42px;height:42px;border-radius:10px;cursor:pointer;font-size:16px;font-weight:700;transition:opacity .2s ease,transform .2s ease}
        .eh-slider__nav button:hover{opacity:.9;transform:translateY(-2px)}
        </style>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            document.querySelectorAll('[data-eh-slider="1"]').forEach(function(slider){
                const track = slider.querySelector('.eh-slider__track');
                const slides = slider.querySelectorAll('.eh-slide');
                const prev = slider.querySelector('.eh-slider__prev');
                const next = slider.querySelector('.eh-slider__next');
                if (!track || !slides.length) return;
                let index = 0;
                function update(){
                    track.style.transform = 'translateX(' + (-index * 100) + '%)';
                }
                if (prev) prev.addEventListener('click', function(){ index = (index - 1 + slides.length) % slides.length; update(); });
                if (next) next.addEventListener('click', function(){ index = (index + 1) % slides.length; update(); });
                // Auto-advance
                let timer = setInterval(function(){ index = (index + 1) % slides.length; update(); }, 5000);
                slider.addEventListener('mouseenter', function(){ clearInterval(timer); });
                slider.addEventListener('mouseleave', function(){ timer = setInterval(function(){ index = (index + 1) % slides.length; update(); }, 5000); });
            });
        });
        </script>
        <?php
    }
}
