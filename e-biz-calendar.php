<?php
/**
 * Plugin Name: e-BizCalendar
 * Description: A simple calendar plugin for WordPress.
 * Version: 1.1.1
 * Author: イー・フロンティア・システムズ
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'EBC_PLUGIN_VERSION', '1.1.1' );
define( 'EBC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EBC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once EBC_PLUGIN_DIR . 'includes/class-ebc-holidays.php';
require_once EBC_PLUGIN_DIR . 'includes/class-ebc-admin.php';
require_once EBC_PLUGIN_DIR . 'includes/class-ebc-event-post-type.php';
require_once EBC_PLUGIN_DIR . 'includes/class-ebc-shortcode.php';
require_once EBC_PLUGIN_DIR . 'includes/class-ebc-elementor.php';

final class E_BizCalendar
{
    public function __construct()
    {
        add_action( 'plugins_loaded', [$this, 'init'] );
        add_action( 'wp_enqueue_scripts', [$this, 'enqueue_assets'] );
        add_action( 'admin_enqueue_scripts', [$this, 'enqueue_admin_assets'] );
    }

    public function init()
    {
        new EBC_Event_Post_Type();
        new EBC_Admin();
        new EBC_Shortcode();
        new EBC_Elementor();
    }

    public function enqueue_assets()
    {
        wp_enqueue_style(
            'ebc-calendar',
            EBC_PLUGIN_URL . 'assets/css/ebc-calendar.css',
            [],
            EBC_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'ebc-calendar',
            EBC_PLUGIN_URL . 'assets/js/ebc-calendar.js',
            ['jquery'],
            EBC_PLUGIN_VERSION,
            true
        );

        wp_localize_script(
            'ebc-calendar',
            'EBC_Ajax',
            [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'ebc_calendar_nonce' ),
            ]
        );
    }

    public function enqueue_admin_assets()
    {
        wp_enqueue_style(
            'ebc-calendar-admin',
            EBC_PLUGIN_URL . 'assets/css/ebc-calendar.css',
            [],
            EBC_PLUGIN_VERSION
        );
    }
}

new E_BizCalendar();
