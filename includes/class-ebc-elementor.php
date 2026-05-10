<?php

if ( ! defined ( 'ABSPATH' ) ) {
    exit;
}

class EBC_Elementor
{
    public function __construct()
    {
        add_action( 'elementor/widgets/widgets_registered', [ $this, 'register_widgets' ] );
    }

    public function register_widgets($widgets_manager): void
    {
        if ( ! did_action( 'elementor/loaded' ) ) {
            return;
        }

        require_once EBC_PLUGIN_DIR . 'includes/widgets/class-ebc-elementor-calendar-widget.php';

        $widgets_manager->register( new EBC_Elementor_Calendar_Widget() );
    }
}
