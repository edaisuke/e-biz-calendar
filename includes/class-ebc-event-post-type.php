<?php

if (!defined('ABSPATH')) {
    exit;
}

class EBC_Event_Post_Type
{
    public function __construct()
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_ebc_event', [$this, 'save_meta']);

        add_filter('use_block_editor_for_post_type', [$this, 'use_block_editor'], 10, 2);
    }

    public function register_post_type(): void
    {
        register_post_type('ebc_event', [
            'labels' => [
                'name' => 'イベント',
                'singular_name' => 'イベント',
                'add_new_item' => 'イベントを追加',
                'edit_item' => 'イベントを編集',
            ],
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'menu_icon' => 'dashicons-calendar-alt',
            'has_archive' => true,
            'rewrite' => [
                'slug' => 'events',
            ],
            'supports' => [
                'title',
                'editor',
                'thumbnail',
                'excerpt',
            ],
            'show_in_rest' => true,
        ]);
    }

    public function add_meta_boxes(): void
    {
        add_meta_box(
            'ebc_event_meta',
            'イベント情報',
            [$this, 'render_meta_box'],
            'ebc_event',
            'normal',
            'high'
        );
    }


    public function use_block_editor(bool $use_block_editor, string $post_type): bool
    {
        if ($post_type !== 'ebc_event') {
            return $use_block_editor;
        }

        return (int) get_option('ebc_event_gutenberg_enabled', 1) === 1;
    }

    public function render_meta_box($post): void
    {
        wp_nonce_field('ebc_save_event_meta', 'ebc_event_meta_nonce');

        $start_date  = get_post_meta($post->ID, '_ebc_event_start_date', true);
        $end_date    = get_post_meta($post->ID, '_ebc_event_end_date', true);
        $event_dates = get_post_meta($post->ID, '_ebc_event_dates', true);

        if (is_array($event_dates)) {
            $event_dates = implode("\n", $event_dates);
        }
        ?>

        <style>
            .ebc-event-meta-group {
                margin-bottom: 24px;
            }

            .ebc-event-meta-group label {
                display: block;
                margin-bottom: 6px;
                font-weight: 700;
            }

            .ebc-event-meta-group small {
                display: block;
                margin-top: 6px;
                color: #666;
            }

            .ebc-event-meta-group input[type="date"] {
                width: 220px;
            }

            .ebc-event-meta-group textarea {
                width: 100%;
                max-width: 420px;
            }
        </style>

        <div class="ebc-event-meta-group">
            <label for="ebc_event_start_date">
                開始日
            </label>

            <input type="date"
                   id="ebc_event_start_date"
                   name="ebc_event_start_date"
                   value="<?php echo esc_attr($start_date); ?>">
        </div>

        <div class="ebc-event-meta-group">
            <label for="ebc_event_end_date">
                終了日
            </label>

            <input type="date"
                   id="ebc_event_end_date"
                   name="ebc_event_end_date"
                   value="<?php echo esc_attr($end_date); ?>">

            <small>
                連続開催の場合に使用します。
            </small>
        </div>

        <div class="ebc-event-meta-group">
            <label for="ebc_event_dates">
                飛び飛び開催日
            </label>

            <textarea id="ebc_event_dates"
                      name="ebc_event_dates"
                      rows="6"
                      placeholder="2026-05-10&#10;2026-05-17&#10;2026-05-24"><?php echo esc_textarea($event_dates); ?></textarea>

            <small>
                1行に1日ずつ入力してください。<br>
                ここに日付がある場合は、開始日〜終了日よりこちらを優先します。
            </small>
        </div>

        <?php
    }

    public function save_meta(int $post_id): void
    {
        if (
            !isset($_POST['ebc_event_meta_nonce']) ||
            !wp_verify_nonce($_POST['ebc_event_meta_nonce'], 'ebc_save_event_meta')
        ) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $start_date = isset($_POST['ebc_event_start_date'])
            ? sanitize_text_field($_POST['ebc_event_start_date'])
            : '';

        $end_date = isset($_POST['ebc_event_end_date'])
            ? sanitize_text_field($_POST['ebc_event_end_date'])
            : '';

        $raw_event_dates = isset($_POST['ebc_event_dates'])
            ? sanitize_textarea_field($_POST['ebc_event_dates'])
            : '';

        $event_dates = [];

        if ($raw_event_dates !== '') {
            $lines = preg_split('/\r\n|\r|\n/', $raw_event_dates);

            foreach ($lines as $line) {
                $date = trim($line);

                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    $event_dates[] = $date;
                }
            }

            $event_dates = array_values(array_unique($event_dates));
            sort($event_dates);
        }

        if (!empty($event_dates)) {
            update_post_meta($post_id, '_ebc_event_dates', $event_dates);

            if (
                !empty($start_date) &&
                preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)
            ) {
                update_post_meta($post_id, '_ebc_event_start_date', $start_date);
            }

            if (
                !empty($end_date) &&
                preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)
            ) {
                update_post_meta($post_id, '_ebc_event_end_date', $end_date);
            }

            return;
        }

        delete_post_meta($post_id, '_ebc_event_dates');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
            delete_post_meta($post_id, '_ebc_event_start_date');
            delete_post_meta($post_id, '_ebc_event_end_date');
            return;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
            $end_date = $start_date;
        }

        if (strtotime($end_date) < strtotime($start_date)) {
            $end_date = $start_date;
        }

        update_post_meta($post_id, '_ebc_event_start_date', $start_date);
        update_post_meta($post_id, '_ebc_event_end_date', $end_date);
    }
}
