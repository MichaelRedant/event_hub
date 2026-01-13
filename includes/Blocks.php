<?php
namespace EventHub;

defined('ABSPATH') || exit;

class Blocks
{
    private CPT_Session $cpt;
    private Registrations $registrations;

    public function __construct(CPT_Session $cpt_session, Registrations $registrations)
    {
        $this->cpt = $cpt_session;
        $this->registrations = $registrations;
    }

    public function register_blocks(): void
    {
        $script_handle = 'event-hub-blocks';
        wp_register_script(
            $script_handle,
            EVENT_HUB_URL . 'assets/js/blocks.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render', 'wp-block-editor'],
            EVENT_HUB_VERSION,
            true
        );

        // Detail block
        register_block_type('event-hub/session-detail', [
            'api_version' => 2,
            'render_callback' => [$this, 'render_session_detail_block'],
            'editor_script' => $script_handle,
            'style' => 'event-hub-frontend-style',
            'attributes' => [
                'sessionId' => [
                    'type' => 'integer',
                    'default' => 0,
                ],
                'template' => [
                    'type' => 'string',
                    'default' => '',
                ],
            ],
        ]);

        // List block
        register_block_type('event-hub/session-list', [
            'api_version' => 2,
            'render_callback' => [$this, 'render_session_list_block'],
            'editor_script' => $script_handle,
            'style' => 'event-hub-frontend-style',
            'attributes' => [
                'count' => [
                    'type' => 'integer',
                    'default' => 6,
                ],
                'status' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'order' => [
                    'type' => 'string',
                    'default' => 'ASC',
                ],
                'showExcerpt' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'showDate' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
            ],
        ]);

        // Full calendar block (Octopus style)
        register_block_type('event-hub/calendar', [
            'api_version'    => 2,
            'render_callback'=> [$this, 'render_calendar_block'],
            'editor_script'  => $script_handle,
            'style'          => 'event-hub-frontend-style',
            'attributes'     => [
                'initialView' => [
                    'type'    => 'string',
                    'default' => 'dayGridMonth',
                ],
            ],
        ]);
    }

    public function render_session_detail_block(array $attributes): string
    {
        $atts = [
            'id' => isset($attributes['sessionId']) ? (int) $attributes['sessionId'] : 0,
            'template' => isset($attributes['template']) ? (string) $attributes['template'] : '',
        ];
        return $this->cpt->render_session_shortcode($atts);
    }

    public function render_session_list_block(array $attributes): string
    {
        $count = isset($attributes['count']) ? max(1, (int) $attributes['count']) : 6;
        $status = isset($attributes['status']) ? sanitize_text_field((string) $attributes['status']) : '';
        $order = isset($attributes['order']) && in_array(strtoupper((string) $attributes['order']), ['ASC', 'DESC'], true)
            ? strtoupper((string) $attributes['order'])
            : 'ASC';
        $show_excerpt = !empty($attributes['showExcerpt']);
        $show_date = !empty($attributes['showDate']);

        $query = new \WP_Query([
            'post_type' => Settings::get_cpt_slug(),
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'meta_key' => '_eh_date_start',
            'order' => $order,
            'post_status' => 'publish',
        ]);

        if (!$query->have_posts()) {
            return '<div class="eh-session-list-block no-results">' . esc_html__('Geen events gevonden.', 'event-hub') . '</div>';
        }

        $items = [];
        foreach ($query->posts as $post) {
            $session_id = (int) $post->ID;
            $occurrences = $this->registrations->get_occurrences($session_id);
            if ($occurrences) {
                foreach ($occurrences as $occ) {
                    $occ_id = (int) ($occ['id'] ?? 0);
                    if ($occ_id <= 0) {
                        continue;
                    }
                    $start = $occ['date_start'] ?? '';
                    if (!$start) {
                        continue;
                    }
                    $items[] = [
                        'session_id' => $session_id,
                        'occurrence_id' => $occ_id,
                        'start' => $start,
                        'start_ts' => strtotime($start),
                    ];
                }
                continue;
            }
            $start = get_post_meta($session_id, '_eh_date_start', true);
            if (!$start) {
                continue;
            }
            $items[] = [
                'session_id' => $session_id,
                'occurrence_id' => 0,
                'start' => $start,
                'start_ts' => strtotime($start),
            ];
        }

        if (!$items) {
            wp_reset_postdata();
            return '<div class="eh-session-list-block no-results">' . esc_html__('Geen events gevonden.', 'event-hub') . '</div>';
        }

        usort($items, static function ($a, $b) use ($order): int {
            $left = $a['start_ts'] ?? 0;
            $right = $b['start_ts'] ?? 0;
            if ($left === $right) {
                return 0;
            }
            if ($order === 'DESC') {
                return $left < $right ? 1 : -1;
            }
            return $left < $right ? -1 : 1;
        });

        $items = array_slice($items, 0, $count);

        ob_start();
        echo '<div class="eh-session-list-block">';
        foreach ($items as $item) {
            $session_id = (int) $item['session_id'];
            $occurrence_id = (int) $item['occurrence_id'];
            $start = $item['start'] ?? '';
            $time = $start ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($start)) : '';
            $excerpt = $show_excerpt ? get_the_excerpt($session_id) : '';
            $permalink = get_permalink($session_id);
            if ($occurrence_id > 0) {
                $permalink = add_query_arg('eh_occurrence', $occurrence_id, $permalink);
            }
            echo '<article class="eh-session-card">';
            echo '<h3 class="eh-session-title"><a href="' . esc_url($permalink) . '">' . esc_html(get_the_title($session_id)) . '</a></h3>';
            if ($show_date && $time) {
                echo '<div class="eh-session-meta"><span class="eh-session-date">' . esc_html($time) . '</span></div>';
            }
            if ($excerpt) {
                echo '<div class="eh-session-excerpt">' . esc_html($excerpt) . '</div>';
            }
            echo '<a class="eh-session-link" href="' . esc_url($permalink) . '">' . esc_html__('Bekijk event', 'event-hub') . '</a>';
            echo '</article>';
        }
        echo '</div>';
        wp_reset_postdata();
        return (string) ob_get_clean();
    }

    public function render_calendar_block(array $attributes): string
    {
        $view = isset($attributes['initialView']) ? sanitize_text_field((string) $attributes['initialView']) : 'dayGridMonth';
        $allowed_views = ['dayGridMonth', 'timeGridWeek', 'listWeek'];
        if (!in_array($view, $allowed_views, true)) {
            $view = 'dayGridMonth';
        }

        // Enqueue FullCalendar (CDN fallback) + our frontend calendar script.
        $fc_css = file_exists(EVENT_HUB_PATH . 'assets/vendor/fullcalendar/main.min.css')
            ? EVENT_HUB_URL . 'assets/vendor/fullcalendar/main.min.css'
            : 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.css';
        $fc_js = file_exists(EVENT_HUB_PATH . 'assets/vendor/fullcalendar/index.global.min.js')
            ? EVENT_HUB_URL . 'assets/vendor/fullcalendar/index.global.min.js'
            : 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js';

        wp_enqueue_style('event-hub-fullcalendar', $fc_css, [], EVENT_HUB_VERSION);
        wp_enqueue_script('event-hub-fullcalendar', $fc_js, [], EVENT_HUB_VERSION, true);
        wp_enqueue_script(
            'event-hub-frontend-calendar',
            EVENT_HUB_URL . 'assets/js/frontend-calendar.js',
            ['event-hub-fullcalendar'],
            EVENT_HUB_VERSION,
            true
        );

        wp_localize_script('event-hub-frontend-calendar', 'EventHubPublicCalendar', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'view'    => $view,
            'labels'  => [
                'error' => __('Er ging iets mis bij het laden van events.', 'event-hub'),
            ],
        ]);

        $id = 'eh-public-calendar-' . wp_generate_password(6, false, false);
        ob_start();
        ?>
        <div class="eh-public-calendar-wrap">
            <div id="<?php echo esc_attr($id); ?>" class="eh-public-calendar" data-initial-view="<?php echo esc_attr($view); ?>"></div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
