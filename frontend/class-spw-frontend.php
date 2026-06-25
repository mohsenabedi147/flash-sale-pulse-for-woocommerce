<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SPW_Frontend {
    protected $sale_service;

    public function __construct( $sale_service ) {
        $this->sale_service = $sale_service;
    }

    public function hooks() {
        add_shortcode( 'sale_pulse_products', array( $this, 'render_products_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets() {
        wp_enqueue_style( 'spw-frontend-style', SPW_PLUGIN_URL . 'assets/css/spw-frontend.css', array(), SPW_VERSION );
        wp_enqueue_script( 'spw-frontend-script', SPW_PLUGIN_URL . 'assets/js/spw-frontend.js', array( 'jquery' ), SPW_VERSION, true );
        
        // ارسال متغیرهای لازم به جاوااسکریپت برای تایمر
        wp_localize_script( 'spw-frontend-script', 'spw_vars', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'now'      => current_time( 'timestamp' )
        ) );
    }

    public function render_products_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'limit'   => 8,
            'orderby' => 'ending_soon',
        ), $atts, 'sale_pulse_products' );

        // منطق دریافت محصولات از Sale Service
        $product_ids = $this->sale_service->get_active_sale_ids( $atts['limit'], $atts['orderby'] );

        if ( empty( $product_ids ) ) {
            return '<p>' . __( 'در حال حاضر هیچ حراجی فعالی وجود ندارد.', 'flash-sale-pulse-for-woocommerce' ) . '</p>';
        }

        ob_start();
        echo '<div class="spw-products-grid" dir="rtl">';
        foreach ( $product_ids as $id ) {
            $product = wc_get_product( $id );
            include SPW_PLUGIN_PATH . 'frontend/partials/product-card.php';
        }
        echo '</div>';
        return ob_get_clean();
    }
}
