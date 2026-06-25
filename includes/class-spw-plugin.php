<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPW_Plugin {

	protected $admin;
	protected $frontend;
	protected $sale_service;

	public function __construct() {
		$this->sale_service = new SPW_Sale_Service();
		$this->admin        = new SPW_Admin( $this->sale_service );
		$this->frontend     = new SPW_Frontend( $this->sale_service );
	}

	public function run() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		if ( method_exists( $this->sale_service, 'hooks' ) ) {
			$this->sale_service->hooks();
		}

		if ( method_exists( $this->admin, 'hooks' ) && is_admin() ) {
			$this->admin->hooks();
		}

		if ( method_exists( $this->frontend, 'hooks' ) ) {
			$this->frontend->hooks();
		}
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'flash-sale-pulse-for-woocommerce',
			false,
			dirname( SPW_PLUGIN_BASENAME ) . '/languages'
		);
	}
}
