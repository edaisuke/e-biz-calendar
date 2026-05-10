<?php

if (!defined('ABSPATH')) {
    exit;
}

class EBC_Admin
{
    private string $option_name = 'ebc_closed_days';
    private string $weekday_option_name = 'ebc_closed_weekdays';
    private string $open_days_option_name = 'ebc_open_days';
    private string $holiday_enabled_option_name = 'ebc_holiday_enabled';
    private string $holiday_closed_option_name = 'ebc_holiday_closed';
    private string $event_gutenberg_option_name = 'ebc_event_gutenberg_enabled';
    private string $color_option_name = 'ebc_colors';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_admin_menu()
    {
        add_menu_page(
            'e-Biz Calendar',
            'e-Biz Calendar',
            'manage_options',
            'e-biz-calendar',
            [$this, 'render_admin_page'],
            'dashicons-calendar-alt',
            58
        );

        add_submenu_page(
            'e-biz-calendar',
            '設定',
            '設定',
            'manage_options',
            'e-biz-calendar',
            [$this, 'render_admin_page']
        );

        add_submenu_page(
            'e-biz-calendar',
            'イベント',
            'イベント',
            'edit_posts',
            'edit.php?post_type=ebc_event'
        );
    }

    public function register_settings()
    {
        register_setting(
            'ebc_settings_group',
            $this->option_name,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_closed_days'],
                'default' => [],
            ]
        );

        register_setting(
            'ebc_settings_group',
            $this->weekday_option_name,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_closed_weekdays'],
                'default' => [],
            ]
        );

        register_setting(
            'ebc_settings_group',
            $this->open_days_option_name,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_closed_days'],
                'default' => [],
            ]
        );

        register_setting(
            'ebc_settings_group',
            $this->holiday_enabled_option_name,
            [
                'type' => 'boolean',
                'sanitize_callback' => [$this, 'sanitize_checkbox'],
                'default' => 1,
            ]
        );

        register_setting(
            'ebc_settings_group',
            $this->holiday_closed_option_name,
            [
                'type' => 'boolean',
                'sanitize_callback' => [$this, 'sanitize_checkbox'],
                'default' => 1,
            ]
        );

        register_setting(
            'ebc_settings_group',
            $this->event_gutenberg_option_name,
            [
                'type' => 'boolean',
                'sanitize_callback' => [$this, 'sanitize_checkbox'],
                'default' => 1,
            ]
        );

        register_setting(
            'ebc_settings_group',
            $this->color_option_name,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_colors'],
                'default' => [],
            ]
        );
    }

    public function sanitize_closed_days($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $sanitized = [];

        foreach ($value as $date) {
            $date = sanitize_text_field($date);

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $sanitized[] = $date;
            }
        }

        return array_values(array_unique($sanitized));
    }

    public function sanitize_closed_weekdays($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $sanitized = [];

        foreach ($value as $weekday) {
            $weekday = absint($weekday);

            if ($weekday >= 0 && $weekday <= 6) {
                $sanitized[] = $weekday;
            }
        }

        return array_values(array_unique($sanitized));
    }

    public function sanitize_checkbox($value): int
    {
        return !empty($value) ? 1 : 0;
    }

    public function sanitize_colors($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $keys = [
            'normal_bg',
            'normal_text',
            'holiday_bg',
            'holiday_text',
            'closed_bg',
            'closed_text',
            'open_bg',
            'open_text',
            'event_bg',
            'event_text',
        ];

        $colors = [];

        foreach ($keys as $key) {
            if (!empty($value[$key]))
            {
                $color = sanitize_hex_color($value[$key]);

                if ($color) {
                    $colors[$key] = $color;
                }
            }
        }

        return $colors;
    }

    public function render_admin_page()
    {
        $closed_days = get_option($this->option_name, []);
        $closed_weekdays = get_option($this->weekday_option_name, []);
        $open_days = get_option($this->open_days_option_name, []);
        $holiday_enabled = (int) get_option($this->holiday_enabled_option_name, 1);
        $holiday_closed = (int) get_option($this->holiday_closed_option_name, 1);
        $event_gutenberg_enabled = (int) get_option($this->event_gutenberg_option_name, 1);
        $colors = get_option($this->color_option_name, []);

        if (!is_array($closed_days)) {
            $closed_days = [];
        }

        if (!is_array($closed_weekdays)) {
            $closed_weekdays = [];
        }

        if (!is_array($open_days)) {
            $open_days = [];
        }

        if (!is_array($colors)) {
            $colors = [];
        }

        $year  = isset($_GET['ebc_year']) ? absint($_GET['ebc_year']) : (int) date('Y');
        $month = isset($_GET['ebc_month']) ? absint($_GET['ebc_month']) : (int) date('m');

        if ($month < 1 || $month > 12) {
            $month = (int) date('m');
        }

        $first_day = sprintf('%04d-%02d-01', $year, $month);
        $timestamp = strtotime($first_day);

        $days_in_month = (int) date('t', $timestamp);
        $start_weekday = (int) date('w', $timestamp);

        $prev_year = $year;
        $prev_month = $month - 1;

        if ($prev_month < 1) {
            $prev_month = 12;
            $prev_year--;
        }

        $next_year = $year;
        $next_month = $month + 1;

        if ($next_month > 12) {
            $next_month = 1;
            $next_year++;
        }

        $weekday_labels = [
            '日曜日',
            '月曜日',
            '火曜日',
            '水曜日',
            '木曜日',
            '金曜日',
            '土曜日'
        ];

        $default_colors = [
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
        $colors = array_merge($default_colors, $colors);

        ?>
        <div class="wrap">
            <h1>e-Biz Calendar 設定</h1>

            <form method="post" action="options.php">
                <?php settings_fields('ebc_settings_group'); ?>

                <h2>毎週定休日</h2>

                <p>毎週休業日にする曜日を選択してください。</p>

                <div class="ebc-weekday-settings">
                    <?php foreach ($weekday_labels as $weekday_value => $weekday_label) : ?>
                        <label>
                            <input type="checkbox"
                                name="<?php echo esc_attr($this->weekday_option_name); ?>[]"
                                value="<?php echo esc_attr((string) $weekday_value); ?>"
                                <?php checked(in_array($weekday_value, array_map('intval', $closed_weekdays), true)); ?>>
                            <?php echo esc_html($weekday_label); ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <h2>祝日設定</h2>

                <div class="ebc-holiday-settings">
                    <label>
                        <input type="checkbox"
                            name="<?php echo esc_attr($this->holiday_enabled_option_name); ?>"
                            value="1"
                            <?php checked($holiday_enabled, 1); ?>>
                        日本の祝日をカレンダーに表示する
                    </label>

                    <label>
                        <input type="checkbox"
                            name="<?php echo esc_attr($this->holiday_closed_option_name); ?>"
                            value="1"
                            <?php checked($holiday_closed, 1); ?>>
                        祝日を休業日として扱う
                    </label>
                </div>

                <?php submit_button('保存'); ?>

                <hr>

                <div id="ebc-hidden-inputs">
                    <?php foreach ($closed_days as $date) : ?>
                        <input type="hidden"
                            name="<?php echo esc_attr($this->option_name); ?>[]"
                            value="<?php echo esc_attr($date); ?>">
                    <?php endforeach; ?>
                </div>

                <div id="ebc-open-hidden-inputs">
                    <?php foreach ($open_days as $date) : ?>
                        <input type="hidden"
                            name="<?php echo esc_attr($this->open_days_option_name); ?>[]"
                            value="<?php echo esc_attr($date); ?>">
                    <?php endforeach; ?>
                </div>

                <div class="ebc-admin-nav">
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=e-biz-calendar&ebc_year=' . $prev_year . '&ebc_month=' . $prev_month)); ?>">
                        前月
                    </a>

                    <strong><?php echo esc_html($year . '年' . $month . '月'); ?></strong>

                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=e-biz-calendar&ebc_year=' . $next_year . '&ebc_month=' . $next_month)); ?>">
                        翌月
                    </a>
                </div>

                <table class="ebc-admin-calendar">
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
                                $holiday_enabled = (int) get_option($this->holiday_enabled_option_name, 1);
                                $holiday_closed  = (int) get_option($this->holiday_closed_option_name, 1);
                                $holiday_name = $holiday_enabled ? EBC_Holidays::get_holiday_name($current_date) : null;
                                $is_holiday = $holiday_name !== null;

                                $classes = ['ebc-admin-day'];

                                if ($weekday === 0) {
                                    $classes[] = 'sun';
                                }

                                if ($weekday === 6) {
                                    $classes[] = 'sat';
                                }

                                $is_weekly_closed = in_array($weekday, array_map('intval', $closed_weekdays), true);
                                $is_custom_closed = in_array($current_date, $closed_days, true);
                                $is_special_open  = in_array($current_date, $open_days, true);
                                $is_holiday_closed = $is_holiday && $holiday_closed === 1;

                                if ($is_special_open) {
                                    $classes[] = 'is-open';
                                } elseif ($is_holiday_closed || $is_weekly_closed || $is_custom_closed) {
                                    $classes[] = 'is-closed';
                                }

                                if ($is_holiday) {
                                    $classes[] = 'is-holiday';
                                }

                                if ($is_weekly_closed) {
                                    $classes[] = 'is-weekly-closed';
                                }

                                if ($is_custom_closed) {
                                    $classes[] = 'is-custom-closed';
                                }

                                echo '<td class="' . esc_attr(implode(' ', $classes)) . '" data-date="' . esc_attr($current_date) . '">';
                                echo '<span class="date-number">' . esc_html((string) $day) . '</span>';

                                if ($holiday_name) {
                                    echo '<span class="holiday-label">' . esc_html($holiday_name) . '</span>';
                                }

                                echo '<span class="closed-label">休</span>';
                                echo '<span class="open-label">営</span>';
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

                <p>日付をクリックすると休業日のON/OFFを切り替えできます。</p>

                <?php submit_button('保存'); ?>

                <hr>

                <h2>イベント編集設定</h2>

                <div class="ebc-event-editor-settings">
                    <label>
                        <input type="checkbox"
                            name="<?php echo esc_attr($this->event_gutenberg_option_name); ?>"
                            value="1"
                            <?php checked($event_gutenberg_enabled, 1); ?>>
                        イベント編集画面でブロックエディターを使用する
                    </label>
                </div>

                <hr>

                <h2>色設定</h2>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">通常の日</th>
                            <td>
                                <label>
                                    背景色
                                    <input type="color"
                                        name="<?php echo esc_attr($this->color_option_name); ?>[normal_bg]"
                                        value="<?php echo esc_attr($colors['normal_bg']); ?>">
                                </label>

                                <label style="margin-left:16px;">
                                    文字色
                                    <input type="color"
                                        name="<?php echo esc_attr($this->color_option_name); ?>[normal_text]"
                                        value="<?php echo esc_attr($colors['normal_text']); ?>">
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">日本の祝日</th>
                            <td>
                                <label>
                                    背景色
                                    <input type="color"
                                        name="<?php echo esc_attr($this->color_option_name); ?>[holiday_bg]"
                                        value="<?php echo esc_attr($colors['holiday_bg']); ?>">
                                </label>

                                <label style="margin-left:16px;">
                                    文字色
                                    <input type="color"
                                        name="<?php echo esc_attr($this->color_option_name); ?>[holiday_text]"
                                        value="<?php echo esc_attr($colors['holiday_text']); ?>">
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">休業日</th>
                            <td>
                                <label>
                                    背景色
                                    <input type="color"
                                        name="<?php echo esc_attr($this->color_option_name); ?>[closed_bg]"
                                        value="<?php echo esc_attr($colors['closed_bg']); ?>">
                                </label>

                                <label style="margin-left:16px;">
                                    文字色
                                    <input type="color"
                                        name="<?php echo esc_attr($this->color_option_name); ?>[closed_text]"
                                        value="<?php echo esc_attr($colors['closed_text']); ?>">
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">営業日</th>
                            <td>
                                <label>
                                    背景色
                                    <input type="color"
                                        name="<?php echo esc_attr($this->color_option_name); ?>[open_bg]"
                                        value="<?php echo esc_attr($colors['open_bg']); ?>">
                                </label>

                                <label style="margin-left:16px;">
                                    文字色
                                    <input type="color"
                                        name="<?php echo esc_attr($this->color_option_name); ?>[open_text]"
                                        value="<?php echo esc_attr($colors['open_text']); ?>">
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">イベント</th>
                            <td>
                                <label>
                                    背景色
                                    <input type="color"
                                        name="<?php echo esc_attr($this->color_option_name); ?>[event_bg]"
                                        value="<?php echo esc_attr($colors['event_bg']); ?>">
                                </label>

                                <label style="margin-left:16px;">
                                    文字色
                                    <input type="color"
                                        name="<?php echo esc_attr($this->color_option_name); ?>[event_text]"
                                        value="<?php echo esc_attr($colors['event_text']); ?>">
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>


            </form>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const closedOptionName = '<?php echo esc_js($this->option_name); ?>';
                const openOptionName = '<?php echo esc_js($this->open_days_option_name); ?>';

                const closedInputs = document.getElementById('ebc-hidden-inputs');
                const openInputs = document.getElementById('ebc-open-hidden-inputs');

                const days = document.querySelectorAll('.ebc-admin-day[data-date]');

                function getDates(container) {
                    return Array.from(
                        container.querySelectorAll('input[type="hidden"]')
                    ).map(input => input.value);
                }

                function renderInputs(container, optionName, dates) {
                    container.innerHTML = '';

                    dates
                        .filter((date, index, self) => self.indexOf(date) === index)
                        .sort()
                        .forEach(date => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = optionName + '[]';
                            input.value = date;
                            container.appendChild(input);
                        });
                }

                function updateClass(day, status) {
                    day.classList.remove('is-closed', 'is-open', 'is-custom-closed');

                    if (status === 'closed') {
                        day.classList.add('is-closed', 'is-custom-closed');
                    }

                    if (status === 'open') {
                        day.classList.add('is-open');
                    }
                }

                days.forEach(day => {
                    day.addEventListener('click', function () {
                        const date = this.dataset.date;
                        const isWeeklyClosed = this.classList.contains('is-weekly-closed');

                        let closedDates = getDates(closedInputs);
                        let openDates = getDates(openInputs);

                        const isCustomClosed = closedDates.includes(date);
                        const isSpecialOpen = openDates.includes(date);

                        closedDates = closedDates.filter(item => item !== date);
                        openDates = openDates.filter(item => item !== date);

                        if (isWeeklyClosed) {
                            if (!isSpecialOpen) {
                                openDates.push(date);
                                updateClass(this, 'open');
                            } else {
                                updateClass(this, 'weekly');
                                this.classList.add('is-closed');
                            }
                        } else {
                            if (!isCustomClosed) {
                                closedDates.push(date);
                                updateClass(this, 'closed');
                            } else {
                                updateClass(this, 'normal');
                            }
                        }

                        renderInputs(closedInputs, closedOptionName, closedDates);
                        renderInputs(openInputs, openOptionName, openDates);
                    });
                });
            });
        </script>
        <?php
    }
}
