<?php

if (!defined('ABSPATH')) {
    exit;
}

class EBC_Shortcode
{
    public function __construct()
    {
        add_shortcode('e_biz_calendar', [$this, 'render_calendar']);

        add_action('wp_ajax_ebc_get_calendar', [$this, 'ajax_get_calendar']);
        add_action('wp_ajax_nopriv_ebc_get_calendar', [$this, 'ajax_get_calendar']);

        add_action('wp_ajax_ebc_get_event', [$this, 'ajax_get_event']);
        add_action('wp_ajax_nopriv_ebc_get_event', [$this, 'ajax_get_event']);
    }

    public function ajax_get_calendar()
    {
        check_ajax_referer('ebc_calendar_nonce', 'nonce');

        $year = isset($_POST['year']) ? absint($_POST['year']) : (int) date('Y');
        $month = isset($_POST['month']) ? absint($_POST['month']) : (int) date('m');
        $holiday_enabled_override = null;

        if ($year < 1970 || $year > 2100) {
            wp_send_json_error([
                'message' => 'Invalid year.',
            ]);
        }

        if ($month < 1 || $month > 12) {
            wp_send_json_error([
                'message' => 'Invalid month.',
            ]);
        }

        if (isset($_POST['holiday_enabled'])) {
            if ($_POST['holiday_enabled'] === '1') {
                $holiday_enabled_override = true;
            } elseif ($_POST['holiday_enabled'] === '0') {
                $holiday_enabled_override = false;
            }
        }

        wp_send_json_success([
            'html'  => $this->build_calendar_html($year, $month, $holiday_enabled_override),
            'year'  => $year,
            'month' => $month,
            'title' => $year . '年' . $month . '月',
        ]);
    }

    private function get_events_by_month(int $year, int $month): array
    {
        $month_start = sprintf('%04d-%02d-01', $year, $month);
        $month_end   = date('Y-m-t', strtotime($month_start));

        $query = new WP_Query([
            'post_type' => 'ebc_event',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_ebc_event_dates',
                    'compare' => 'EXISTS',
                ],
                [
                    'relation' => 'AND',
                    [
                        'key' => '_ebc_event_start_date',
                        'value' => $month_end,
                        'compare' => '<=',
                        'type' => 'DATE',
                    ],
                    [
                        'key' => '_ebc_event_end_date',
                        'value' => $month_start,
                        'compare' => '>=',
                        'type' => 'DATE',
                    ],
                ],
            ],
        ]);

        $events_by_date = [];

        foreach ($query->posts as $post) {
            $specific_dates = get_post_meta($post->ID, '_ebc_event_dates', true);

            if (is_array($specific_dates) && !empty($specific_dates)) {

                foreach ($specific_dates as $date) {

                    if ($date < $month_start || $date > $month_end) {
                        continue;
                    }

                    $events_by_date[$date][] = [
                        'id' => $post->ID,
                        'title' => get_the_title($post),
                        'excerpt' => get_the_excerpt($post),
                        'start_date' => $date,
                        'end_date' => $date,
                        'is_start' => true,
                        'is_end' => true,
                        'is_specific_date' => true,
                    ];
                }

                continue;
            }

            $start_date = get_post_meta($post->ID, '_ebc_event_start_date', true);
            $end_date   = get_post_meta($post->ID, '_ebc_event_end_date', true);

            if (!$start_date) {
                continue;
            }

            if (!$end_date) {
                $end_date = $start_date;
            }

            $display_start = max(strtotime($start_date), strtotime($month_start));
            $display_end   = min(strtotime($end_date), strtotime($month_end));

            for (
                $time = $display_start;
                $time <= $display_end;
                $time = strtotime('+1 day', $time)
            ) {
                $date = date('Y-m-d', $time);

                $events_by_date[$date][] = [
                    'id' => $post->ID,
                    'title' => get_the_title($post),
                    'excerpt' => get_the_excerpt($post),
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'is_start' => $date === $start_date,
                    'is_end' => $date === $end_date,
                    'is_specific_date' => false,
                ];
            }
        }

        wp_reset_postdata();

        return $events_by_date;
    }

    public function render_calendar($atts): string
    {
        $atts = shortcode_atts([
            'year'  => date('Y'),
            'month' => date('m'),
            'show_prev' => '1',
            'show_next' => '1',
            'holiday_enabled' => '',
        ], $atts);

        $year  = absint($atts['year']);
        $month = absint($atts['month']);
        $show_prev = $atts['show_prev'] === '1';
        $show_next = $atts['show_next'] === '1';

        if ($year < 1970) {
            $year = (int) date('Y');
        }

        if ($month < 1 || $month > 12) {
            $month = (int) date('m');
        }

        $holiday_enabled_override = null;
        if ($atts['holiday_enabled'] === '1') {
            $holiday_enabled_override = true;
        } elseif ($atts['holiday_enabled'] === '0') {
            $holiday_enabled_override = false;
        }

        $colors = $this->get_calendar_colors();

        $style = sprintf(
            '--ebc-normal-bg:%s;--ebc-normal-text:%s;--ebc-holiday-bg:%s;--ebc-holiday-text:%s;--ebc-closed-bg:%s;--ebc-closed-text:%s;--ebc-open-bg:%s;--ebc-open-text:%s;--ebc-event-bg:%s;--ebc-event-text:%s;',
            esc_attr($colors['normal_bg']),
            esc_attr($colors['normal_text']),
            esc_attr($colors['holiday_bg']),
            esc_attr($colors['holiday_text']),
            esc_attr($colors['closed_bg']),
            esc_attr($colors['closed_text']),
            esc_attr($colors['open_bg']),
            esc_attr($colors['open_text']),
            esc_attr($colors['event_bg']),
            esc_attr($colors['event_text'])
        );

        ob_start();
        ?>
        <div class="ebc-calendar-wrap"
             style="<?php echo esc_attr($style); ?>"
             data-year="<?php echo esc_attr($year); ?>"
             data-month="<?php echo esc_attr($month); ?>"
             data-holiday-enabled="<?php echo esc_attr($atts['holiday_enabled']); ?>">

            <div class="ebc-calendar-nav">
                <div class="ebc-calendar-nav-prev">
                <?php if ($show_prev) : ?>
                    <button type="button" class="ebc-calendar-prev">前月</button>
                <?php else : ?>
                    <span class="ebc-calendar-nav-spacer"></span>
                <?php endif; ?>
                </div>

                <div class="ebc-calendar-title">
                    <span class="ebc-calendar-title-year"><?php echo esc_html($year); ?>年</span>
                    <span class="ebc-calendar-title-month"><?php echo esc_html($month); ?>月</span>
                </div>

                <div class="ebc-calendar-nav-next">
                <?php if ($show_next) : ?>
                    <button type="button" class="ebc-calendar-next">翌月</button>
                <?php else : ?>
                    <span class="ebc-calendar-nav-spacer"></span>
                <?php endif; ?>
                </div>
            </div>

            <div class="ebc-calendar-loading" style="display:none;">
                読み込み中...
            </div>

            <div class="ebc-calendar-content">
                <?php echo $this->build_calendar_html($year, $month, $holiday_enabled_override); ?>
            </div>

            <div class="ebc-event-modal" aria-hidden="true">
                <div class="ebc-event-modal-backdrop"></div>

                <div class="ebc-event-modal-panel" role="dialog" aria-modal="true">
                    <button type="button" class="ebc-event-modal-close" aria-label="閉じる">
                        ×
                    </button>

                    <div class="ebc-event-modal-body">
                        <h3 class="ebc-event-modal-title"></h3>

                        <div class="ebc-event-modal-dates"></div>

                        <div class="ebc-event-modal-content"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    private function build_calendar_html(int $year, int $month, ?bool $holiday_enabled_override = null): string
    {
        $closed_days = get_option('ebc_closed_days', []);

        if (!is_array($closed_days)) {
            $closed_days = [];
        }

        $closed_weekdays = get_option('ebc_closed_weekdays', []);

        if (!is_array($closed_weekdays)) {
            $closed_weekdays = [];
        }

        $open_days = get_option('ebc_open_days', []);

        if (!is_array($open_days)) {
            $open_days = [];
        }

        $closed_weekdays = array_map('intval', $closed_weekdays);

        $first_day = sprintf('%04d-%02d-01', $year, $month);
        $timestamp = strtotime($first_day);

        $days_in_month = (int) date('t', $timestamp);
        $start_weekday = (int) date('w', $timestamp);

        $holiday_enabled = $holiday_enabled_override !== null
            ? $holiday_enabled_override ? 1 : 0
            : (int) get_option('ebc_holiday_enabled', 1);
        $holiday_closed  = (int) get_option('ebc_holiday_closed', 1);

        $events_by_date = $this->get_events_by_month($year, $month);

        // 変数の上書き
        $year  = absint($year);
        $month = absint($month);

        ob_start();
        ?>
        <div class="ebc-calendar" data-title="<?php echo esc_attr($year . '年' . $month . '月'); ?>">
            <table class="ebc-calendar-table">
                <thead>
                    <tr>
                        <th class="sun">日</th>
                        <th>月</th>
                        <th>火</th>
                        <th>水</th>
                        <th>木</th>
                        <th>金</th>
                        <th class="sat">土</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php
                        for ($i = 0; $i < $start_weekday; $i++) {
                            echo '<td class="empty"></td>';
                        }

                        for ($day = 1; $day <= $days_in_month; $day++) {
                            $current_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                            $weekday = (int) date('w', strtotime($current_date));

                            $holiday_name = $holiday_enabled ? EBC_Holidays::get_holiday_name($current_date) : null;
                            $is_holiday = $holiday_name !== null;

                            $classes = ['day'];

                            if ($weekday === 0) {
                                $classes[] = 'sun';
                            }

                            if ($weekday === 6) {
                                $classes[] = 'sat';
                            }

                            $is_weekly_closed = in_array($weekday, $closed_weekdays, true);
                            $is_custom_closed = in_array($current_date, $closed_days, true);
                            $is_special_open  = in_array($current_date, $open_days, true);
                            $is_holiday_closed = $is_holiday && $holiday_closed === 1;

                            if ($is_special_open) {
                                $classes[] = 'open';
                            } elseif ($is_holiday_closed || $is_weekly_closed || $is_custom_closed) {
                                $classes[] = 'closed';
                            }

                            if ($is_holiday) {
                                $classes[] = 'holiday';
                            }

                            if ($is_weekly_closed) {
                                $classes[] = 'weekly-closed';
                            }

                            if ($is_custom_closed) {
                                $classes[] = 'custom-closed';
                            }

                            echo '<td class="' . esc_attr(implode(' ', $classes)) . '">';
                            echo '<span class="date-number">' . esc_html((string) $day) . '</span>';

                            if ($holiday_name) {
                                echo '<span class="holiday-name">' . esc_html($holiday_name) . '</span>';
                            }

                            if ($is_special_open) {
                                echo '<span class="status open-status">営</span>';
                            } elseif ($is_holiday_closed || $is_weekly_closed || $is_custom_closed) {
                                echo '<span class="status">休</span>';
                            }

                            // イベント表示
                            if (!empty($events_by_date[$current_date])) {
                                echo '<div class="ebc-events">';

                                foreach ($events_by_date[$current_date] as $event) {
                                    $event_classes = ['ebc-event'];

                                    if (!empty($event['is_start'])) {
                                        $event_classes[] = 'is-start';
                                    }

                                    if (!empty($event['is_end'])) {
                                        $event_classes[] = 'is-end';
                                    }

                                    echo '<a class="' . esc_attr(implode(' ', $event_classes)) . '"
                                             href="#"
                                             data-event-id="' . esc_attr($event['id']) . '">';
                                    // echo '<a class="' . esc_attr(implode(' ', $event_classes)) . '" href="' . esc_url($event['url']) . '">';

                                    // echo '<span class="ebc-event-title">' . esc_html($event['title']) . '</span>';

                                    if (!empty($event['is_specific_date'])) {
                                        // echo '<span class="ebc-event-period">単日開催</span>';
                                        echo '<span class="ebc-event-period">イ</span>';
                                    } elseif ($event['start_date'] !== $event['end_date']) {
                                        echo '<span class="ebc-event-period">';
                                        echo 'イ';
                                        // echo esc_html(date('n/j', strtotime($event['start_date'])) . '〜' . date('n/j', strtotime($event['end_date'])));
                                        echo '</span>';
                                    }

                                    // if (!empty($event['excerpt'])) {
                                    //     echo '<span class="ebc-event-description">' . esc_html($event['excerpt']) . '</span>';
                                    // }

                                    echo '</a>';
                                }

                                echo '</div>';
                            }

                            echo '</td>';

                            if (($start_weekday + $day) % 7 === 0 && $day !== $days_in_month) {
                                echo '</tr><tr>';
                            }
                        }

                        $last_weekday = (int) date('w', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $days_in_month)));

                        for ($i = $last_weekday; $i < 6; $i++) {
                            echo '<td class="empty"></td>';
                        }
                        ?>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php

        return ob_get_clean();
    }

    private function get_calendar_colors(): array
    {
        $colors = get_option('ebc_colors', []);

        if (!is_array($colors)) {
            $colors = [];
        }

        $defaults = [
            'normal_bg'     => '#ffffff',
            'normal_text'   => '#334155',
            'holiday_bg'    => '#ffffff',
            'holiday_text'  => '#d63638',
            'closed_bg'     => '#d63638',
            'closed_text'   => '#ffffff',
            'open_bg'       => '#2271b1',
            'open_text'     => '#ffffff',
            'event_bg'      => '#0000ff',
            'event_text'    => '#ffffff',
        ];

        return array_merge($defaults, $colors);
    }


    public function ajax_get_event()
    {
        check_ajax_referer('ebc_calendar_nonce', 'nonce');

        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;

        if (!$event_id) {
            wp_send_json_error([
                'message' => 'Invalid event ID.',
            ]);
        }

        $post = get_post($event_id);

        if (!$post || $post->post_type !== 'ebc_event' || $post->post_status !== 'publish') {
            wp_send_json_error([
                'message' => 'Event not found.',
            ]);
        }

        $start_date  = get_post_meta($event_id, '_ebc_event_start_date', true);
        $end_date    = get_post_meta($event_id, '_ebc_event_end_date', true);
        $event_dates = get_post_meta($event_id, '_ebc_event_dates', true);

        if (!is_array($event_dates)) {
            $event_dates = [];
        }

        $content = apply_filters('the_content', $post->post_content);

        wp_send_json_success([
            'id'            => $event_id,
            'title'         => get_the_title($post),
            'content'       => $content,
            'url'           => get_permalink($post),
            'start_date'    => $start_date,
            'end_date'      => $end_date,
            'event_dates'   => $event_dates,
        ]);
    }
}
