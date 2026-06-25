<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPW_Activator {

	public static function activate() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			if ( function_exists( 'deactivate_plugins' ) && defined( 'SPW_PLUGIN_BASENAME' ) ) {
				deactivate_plugins( SPW_PLUGIN_BASENAME );
			}

			wp_die(
				esc_html__( 'Flash Sale Pulse for WooCommerce requires WooCommerce to be active.', 'flash-sale-pulse-for-woocommerce' ),
				esc_html__( 'Plugin activation failed', 'flash-sale-pulse-for-woocommerce' ),
				array(
					'back_link' => true,
				)
			);
		}

		$defaults = array(
			'timer_title'       => 'زمان باقی‌مانده تا پایان حراجی',
			'button_text'       => 'مشاهده محصول',
			'products_per_page' => 8,
			'default_orderby'   => 'ending_soon',
			'hide_oos'          => 'yes',
			'cleanup_batch'     => 25,
			'db_version'        => defined( 'SPW_DB_VERSION' ) ? SPW_DB_VERSION : '1.0.0',
		);

		if ( ! get_option( 'spw_settings' ) ) {
			add_option( 'spw_settings', $defaults );
		}

		if ( ! wp_next_scheduled( 'spw_cleanup_expired_sales_event' ) ) {
			wp_schedule_event( time() + 300, 'spw_five_minutes', 'spw_cleanup_expired_sales_event' );
		}
	}
}
