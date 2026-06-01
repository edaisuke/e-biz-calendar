<?php

if ( ! defined ( 'ABSPATH' ) ) {
    exit;
}

class EBC_Elementor_Calendar_Widget extends \Elementor\Widget_Base
{
    public function get_name(): string
    {
        return 'e_biz_calendar';
    }

    public function get_title(): string
    {
        return 'e-BizCalendar';
    }

    public function get_icon(): string
    {
        return 'eicon-calendar';
    }

    public function get_categories(): array
    {
        return ['general'];
    }

    public function get_keywords(): array
    {
        return ['calendar', 'event', 'holiday', 'business', 'schedule'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section(
            'section_calendar',
            [
                'label' => 'カレンダー設定',
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'current_date',
            [
                'label' => '現在の年月を表示',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => 'はい',
                'label_off' => 'いいえ',
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'year',
            [
                'label' => '表示年',
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min'  => 2000,
                'max'  => 2200,
                'default' => (int) date('Y'),
                'condition' => [
                    'current_date' => '',
                ],
            ]
        );

        $this->add_control(
            'month',
            [
                'label' => '表示月',
                'type'  => \Elementor\Controls_Manager::SELECT,
                'default' => (int) date('n'),
                'options' => [
                    1 => '1月',
                    2 => '2月',
                    3 => '3月',
                    4 => '4月',
                    5 => '5月',
                    6 => '6月',
                    7 => '7月',
                    8 => '8月',
                    9 => '9月',
                    10 => '10月',
                    11 => '11月',
                    12 => '12月',
                ],
                'condition' => [
                    'current_date' => '',
                ],
            ]
        );

        $this->add_control(
            'show_prev',
            [
                'label' => '前月ボタンを表示',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => '表示',
                'label_off' => '非表示',
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_next',
            [
                'label' => '翌月ボタンを表示',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => '表示',
                'label_off' => '非表示',
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'holiday_enabled',
            [
                'label' => '祝日を表示',
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => '',
                'options' => [
                    '' => '全体設定に従う',
                    '1' => '表示する',
                    '0' => '表示しない',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        $current_date = isset($settings['current_date']) && $settings['current_date'] === 'yes';
        $year  = $current_date ? (int) date('Y') : (isset($settings['year']) ? absint($settings['year']) : (int) date('Y'));
        $month = $current_date ? (int) date('n') : (isset($settings['month']) ? absint($settings['month']) : (int) date('n'));

        $show_prev = $settings['show_prev'] === 'yes' ? '1' : '0';
        $show_next = $settings['show_next'] === 'yes' ? '1' : '0';
        $holiday_enabled = isset($settings['holiday_enabled'])
            ? (string) $settings['holiday_enabled'] : '';

        echo do_shortcode(sprintf(
            '[e_biz_calendar year="%d" month="%d" show_prev="%s" show_next="%s" holiday_enabled="%s"]',
            $year,
            $month,
            esc_attr($show_prev),
            esc_attr($show_next),
            esc_attr($holiday_enabled)
        ));
    }
}
