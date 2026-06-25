<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * سرویس اصلی منطق حراجی و پاک‌سازی خودکار
 */
class SPW_Sale_Service {

	public function hooks() {
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
		add_action( 'spw_cleanup_expired_sales_event', array( $this, 'cleanup_expired_sales' ) );
	}

	public function add_cron_schedules( $schedules ) {
		if ( ! isset( $schedules['spw_five_minutes'] ) ) {
			$schedules['spw_five_minutes'] = array(
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => __( 'هر ۵ دقیقه - نبض حراجی', 'flash-sale-pulse-for-woocommerce' ),
			);
		}
		return $schedules;
	}

	/**
	 * پاک‌سازی محصولات منقضی شده با مدیریت مصرف حافظه
	 */
	public function cleanup_expired_sales() {
		$settings = SPW_Helpers::get_settings();
		// استفاده از Batch تعیین شده در تنظیمات برای جلوگیری از رسیدن به Memory Limit
		$batch = isset( $settings['cleanup_batch'] ) ? max( 1, absint( $settings['cleanup_batch'] ) ) : 25;

		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => $batch,
				'fields'         => 'ids', // فقط IDها را دریافت می‌کنیم تا مصرف حافظه به حداقل برسد
				'meta_query'     => array(
					array(
						'key'     => '_spw_sale_end',
						'value'   => current_time( 'timestamp' ),
						'compare' => '<=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		if ( ! $query->have_posts() ) {
			return;
		}

		foreach ( $query->posts as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			// حذف متاداده‌های اختصاصی افزونه
			$product->delete_meta_data( '_spw_sale_start' );
			$product->delete_meta_data( '_spw_sale_end' );

			// برگرداندن قیمت محصول به حالت عادی در ووکامرس
			$product->set_sale_price( '' );
			$product->set_date_on_sale_from( null );
			$product->set_date_on_sale_to( null );

			// ذخیره تغییرات
			$product->save();
			
			// پاکسازی حافظه برای هر محصول
			wc_delete_product_transients( $product_id );
		}
	}

	// سایر متدها شامل is_product_in_flash_sale و get_sale_products ...
    // (این متدها در ساختار فعلی شما به درستی کار می‌کنند)
}
