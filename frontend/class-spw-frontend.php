<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SPW_Frontend {
	protected $sale_service;

	public function __construct( $sale_service ) {
		$this->sale_service = $sale_service;
	}

	public function hooks() {
		add_shortcode( 'sale_pulse_products', array( $this, 'render_products_shortcode' ) );
		add_shortcode( 'sale_pulse_timer', array( $this, 'render_timer_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	public function enqueue_frontend_assets() {
		wp_enqueue_style( 'spw-frontend', SPW_PLUGIN_URL . 'assets/css/spw-frontend.css', array(), SPW_VERSION );
		wp_enqueue_script( 'spw-frontend', SPW_PLUGIN_URL . 'assets/js/spw-frontend.js', array( 'jquery' ), SPW_VERSION, true );
		wp_localize_script( 'spw-frontend', 'spwData', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ) );
	}

	public function render_products_shortcode( $atts ) {
		$args = shortcode_atts( array( 'limit' => 8, 'orderby' => 'ending_soon', 'category' => '' ), $atts );
		$data = $this->sale_service->get_sale_products( $args );
		
		if ( empty( $data['products'] ) ) return '<p>' . esc_html__( 'محصول حراجی یافت نشد.', 'flash-sale-pulse-for-woocommerce' ) . '</p>';

		ob_start();
		include SPW_PLUGIN_PATH . 'frontend/templates/product-list.php';
		return ob_get_clean();
	}

	public function render_timer_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'product_id' => 0 ), $atts );
		$product_id = absint( $atts['product_id'] );
		if ( ! $product_id ) return '';

		$seconds = $this->sale_service->get_remaining_seconds( $product_id );
		if ( $seconds <= 0 ) return '<span class="spw-timer-expired">' . esc_html__( 'پایان یافته', 'flash-sale-pulse-for-woocommerce' ) . '</span>';

		return '<div class="spw-timer" data-seconds="' . esc_attr( $seconds ) . '"></div>';
	}
}
