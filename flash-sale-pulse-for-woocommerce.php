<?php
/**
 * Plugin Name: Flash Sale Pulse for WooCommerce
 * Plugin URI: https://seoparsi.ir/
 * Description: افزونه نبض حراجی برای ووکامرس با پشتیبانی از HPOS، تاریخ شمسی، تایمر حراجی و شورت‌کد محصولات حراجی.
 * Version: 1.0.0
 * Author: SeoParsi Plugins
 * Author URI: https://seoparsi.ir/
 * Text Domain: flash-sale-pulse-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SPW_VERSION', '1.0.0' );
define( 'SPW_DB_VERSION', '1.0.0' );
define( 'SPW_PLUGIN_FILE', __FILE__ );
define( 'SPW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SPW_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'SPW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * اعلام سازگاری با HPOS و Blocks ووکامرس.
 */
add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SPW_PLUGIN_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', SPW_PLUGIN_FILE, true );
		}
	}
);

/**
 * لود امن فایل‌ها.
 *
 * هدف:
 * اگر فایلی وجود نداشت، به‌جای Fatal error و خطای 500،
 * افزونه در پنل مدیریت خطای قابل‌فهم نمایش دهد.
 */
function spw_require_file( $relative_path ) {
	$file = SPW_PLUGIN_PATH . ltrim( $relative_path, '/' );

	if ( file_exists( $file ) ) {
		require_once $file;
		return true;
	}

	add_action(
		'admin_notices',
		function() use ( $relative_path ) {
			if ( current_user_can( 'manage_options' ) ) {
				echo '<div class="notice notice-error"><p>';
				echo esc_html(
					sprintf(
						'Flash Sale Pulse: فایل ضروری افزونه پیدا نشد: %s',
						$relative_path
					)
				);
				echo '</p></div>';
			}
		}
	);

	return false;
}

/**
 * فایل‌های ضروری پایه.
 */
spw_require_file( 'includes/class-spw-activator.php' );
spw_require_file( 'includes/class-spw-deactivator.php' );
spw_require_file( 'includes/class-spw-helpers.php' );
spw_require_file( 'includes/class-spw-sale-service.php' );
spw_require_file( 'admin/class-spw-admin.php' );
spw_require_file( 'frontend/class-spw-frontend.php' );
spw_require_file( 'includes/class-spw-plugin.php' );

register_activation_hook( __FILE__, array( 'SPW_Activator', 'activate' ) );

if ( class_exists( 'SPW_Deactivator' ) ) {
	register_deactivation_hook( __FILE__, array( 'SPW_Deactivator', 'deactivate' ) );
}

/**
 * نمایش خطای مدیریتی اگر ووکامرس فعال نباشد.
 */
function spw_missing_woocommerce_notice() {
	if ( current_user_can( 'activate_plugins' ) ) {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Flash Sale Pulse for WooCommerce requires WooCommerce to be installed and active.', 'flash-sale-pulse-for-woocommerce' );
		echo '</p></div>';
	}
}

/**
 * اجرای افزونه.
 */
function spw_run_plugin() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'spw_missing_woocommerce_notice' );
		return;
	}

	$required_classes = array(
		'SPW_Helpers',
		'SPW_Sale_Service',
		'SPW_Admin',
		'SPW_Frontend',
		'SPW_Plugin',
	);

	foreach ( $required_classes as $class_name ) {
		if ( ! class_exists( $class_name ) ) {
			add_action(
				'admin_notices',
				function() use ( $class_name ) {
					if ( current_user_can( 'manage_options' ) ) {
						echo '<div class="notice notice-error"><p>';
						echo esc_html(
							sprintf(
								'Flash Sale Pulse: کلاس ضروری افزونه پیدا نشد: %s',
								$class_name
							)
						);
						echo '</p></div>';
					}
				}
			);

			return;
		}
	}

	$plugin = new SPW_Plugin();
	$plugin->run();
}
add_action( 'plugins_loaded', 'spw_run_plugin', 20 );
