<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * مدیریت پنل و فیلدهای محصول افزونه.
 */
class SPW_Admin {

	protected $sale_service;

	public function __construct( $sale_service ) {
		$this->sale_service = $sale_service;
	}

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 99 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'woocommerce_product_options_pricing', array( $this, 'add_product_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_fields' ) );
	}

	public function enqueue_admin_assets( $hook ) {
		$screen = get_current_screen();

		if ( 'woocommerce_page_spw-settings' !== $hook && ( ! $screen || 'product' !== $screen->post_type ) ) {
			return;
		}

		wp_enqueue_style(
			'spw-admin',
			SPW_PLUGIN_URL . 'assets/css/spw-admin.css',
			array(),
			SPW_VERSION
		);

		wp_enqueue_script(
			'spw-admin',
			SPW_PLUGIN_URL . 'assets/js/spw-admin.js',
			array( 'jquery' ),
			SPW_VERSION,
			true
		);
	}

	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'نبض حراجی', 'flash-sale-pulse-for-woocommerce' ),
			__( 'نبض حراجی', 'flash-sale-pulse-for-woocommerce' ),
			'manage_woocommerce',
			'spw-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting( 'spw_settings_group', 'spw_settings', array( $this, 'sanitize_settings' ) );
	}

	public function sanitize_settings( $input ) {
		$output = SPW_Helpers::get_settings();

		$output['timer_title']       = isset( $input['timer_title'] ) ? sanitize_text_field( wp_unslash( $input['timer_title'] ) ) : $output['timer_title'];
		$output['button_text']       = isset( $input['button_text'] ) ? sanitize_text_field( wp_unslash( $input['button_text'] ) ) : $output['button_text'];
		
		// اضافه کردن سقف منطقی برای جلوگیری از فشار به سرور
		$output['products_per_page'] = isset( $input['products_per_page'] ) ? min( 100, max( 1, absint( $input['products_per_page'] ) ) ) : $output['products_per_page'];
		$output['default_orderby']   = isset( $input['default_orderby'] ) ? sanitize_key( $input['default_orderby'] ) : $output['default_orderby'];
		$output['hide_oos']          = 'yes';
		
		// اضافه کردن سقف منطقی برای batch size
		$output['cleanup_batch']     = isset( $input['cleanup_batch'] ) ? min( 500, max( 1, absint( $input['cleanup_batch'] ) ) ) : $output['cleanup_batch'];
		$output['db_version']        = SPW_DB_VERSION;

		if ( ! in_array( $output['default_orderby'], array( 'ending_soon', 'date', 'title' ), true ) ) {
			$output['default_orderby'] = 'ending_soon';
		}

		return $output;
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$settings = SPW_Helpers::get_settings();
		?>
		<div class="wrap spw-admin-wrap" dir="rtl">
			<h1><?php esc_html_e( 'نبض حراجی', 'flash-sale-pulse-for-woocommerce' ); ?></h1>

			<div class="spw-admin-card">
				<h2><?php esc_html_e( 'تنظیمات افزونه نبض حراجی برای ووکامرس', 'flash-sale-pulse-for-woocommerce' ); ?></h2>
				<p><?php esc_html_e( 'در نسخه ۱.۰، افزونه فقط محصولاتی را نمایش می‌دهد که قیمت فروش ویژه ووکامرس، تاریخ شروع، تاریخ پایان معتبر و موجودی فعال داشته باشند.', 'flash-sale-pulse-for-woocommerce' ); ?></p>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'spw_settings_group' ); ?>

				<div class="spw-tabs">
					<a href="#spw-tab-general" class="spw-tab-link is-active"><?php esc_html_e( 'عمومی', 'flash-sale-pulse-for-woocommerce' ); ?></a>
					<a href="#spw-tab-texts" class="spw-tab-link"><?php esc_html_e( 'متن‌ها', 'flash-sale-pulse-for-woocommerce' ); ?></a>
					<a href="#spw-tab-shortcodes" class="spw-tab-link"><?php esc_html_e( 'شورت‌کدها', 'flash-sale-pulse-for-woocommerce' ); ?></a>
					<a href="#spw-tab-advanced" class="spw-tab-link"><?php esc_html_e( 'پیشرفته', 'flash-sale-pulse-for-woocommerce' ); ?></a>
				</div>

				<div id="spw-tab-general" class="spw-tab-content is-active">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="spw_products_per_page"><?php esc_html_e( 'تعداد محصول در هر بار نمایش', 'flash-sale-pulse-for-woocommerce' ); ?></label></th>
							<td><input name="spw_settings[products_per_page]" id="spw_products_per_page" type="number" min="1" max="100" class="small-text" value="<?php echo esc_attr( $settings['products_per_page'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="spw_default_orderby"><?php esc_html_e( 'ترتیب پیش‌فرض', 'flash-sale-pulse-for-woocommerce' ); ?></label></th>
							<td>
								<select name="spw_settings[default_orderby]" id="spw_default_orderby">
									<option value="ending_soon" <?php selected( $settings['default_orderby'], 'ending_soon' ); ?>><?php esc_html_e( 'نزدیک‌ترین زمان پایان', 'flash-sale-pulse-for-woocommerce' ); ?></option>
									<option value="date" <?php selected( $settings['default_orderby'], 'date' ); ?>><?php esc_html_e( 'جدیدترین محصولات', 'flash-sale-pulse-for-woocommerce' ); ?></option>
									<option value="title" <?php selected( $settings['default_orderby'], 'title' ); ?>><?php esc_html_e( 'عنوان محصول', 'flash-sale-pulse-for-woocommerce' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'برای افزایش حس فوریت، حالت «نزدیک‌ترین زمان پایان» پیشنهاد می‌شود.', 'flash-sale-pulse-for-woocommerce' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div id="spw-tab-texts" class="spw-tab-content">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="spw_timer_title"><?php esc_html_e( 'عنوان تایمر', 'flash-sale-pulse-for-woocommerce' ); ?></label></th>
							<td><input name="spw_settings[timer_title]" id="spw_timer_title" type="text" class="regular-text" value="<?php echo esc_attr( $settings['timer_title'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="spw_button_text"><?php esc_html_e( 'متن دکمه محصول', 'flash-sale-pulse-for-woocommerce' ); ?></label></th>
							<td><input name="spw_settings[button_text]" id="spw_button_text" type="text" class="regular-text" value="<?php echo esc_attr( $settings['button_text'] ); ?>"></td>
						</tr>
					</table>
				</div>

				<div id="spw-tab-shortcodes" class="spw-tab-content">
					<div class="spw-admin-card">
						<h2><?php esc_html_e( 'شورت‌کدهای نسخه ۱.۰', 'flash-sale-pulse-for-woocommerce' ); ?></h2>
						<p><code>[sale_pulse_products limit="8" orderby="ending_soon"]</code></p>
						<p><code>[sale_pulse_timer product_id="123"]</code></p>
						<p><code>[sale_pulse_block limit="8" category="mobile"]</code></p>
					</div>
				</div>

				<div id="spw-tab-advanced" class="spw-tab-content">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="spw_cleanup_batch"><?php esc_html_e( 'تعداد پردازش پاک‌سازی در هر نوبت', 'flash-sale-pulse-for-woocommerce' ); ?></label></th>
							<td>
								<input name="spw_settings[cleanup_batch]" id="spw_cleanup_batch" type="number" min="1" max="500" class="small-text" value="<?php echo esc_attr( $settings['cleanup_batch'] ); ?>">
								<p class="description"><?php esc_html_e( 'برای فروشگاه‌های بزرگ عدد پایین‌تر فشار کمتری به سرور وارد می‌کند.', 'flash-sale-pulse-for-woocommerce' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button( __( 'ذخیره تنظیمات', 'flash-sale-pulse-for-woocommerce' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function add_product_fields() {
		global $post;

		$product_id = $post ? $post->ID : 0;
		$window     = SPW_Helpers::get_product_sale_window( $product_id );
		$start      = SPW_Helpers::timestamp_to_jalali_datetime( $window['start'] );
		$end        = SPW_Helpers::timestamp_to_jalali_datetime( $window['end'] );

		echo '<div class="options_group spw-product-fields" dir="rtl">';
		echo '<p class="form-field"><strong>' . esc_html__( 'نبض حراجی', 'flash-sale-pulse-for-woocommerce' ) . '</strong></p>';

		// اضافه کردن nonce برای امنیت فرم
		wp_nonce_field( 'spw_save_product_meta', 'spw_product_meta_nonce' );

		woocommerce_wp_text_input(
			array(
				'id'          => '_spw_sale_start_jalali',
				'label'       => __( 'تاریخ شروع حراجی', 'flash-sale-pulse-for-woocommerce' ),
				'placeholder' => '1403/01/01 09:00',
				'value'       => $start,
				'desc_tip'    => true,
				'description' => __( 'تاریخ را به فرمت شمسی وارد کنید. نمونه: 1403/01/01 09:00', 'flash-sale-pulse-for-woocommerce' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_spw_sale_end_jalali',
				'label'       => __( 'تاریخ پایان حراجی', 'flash-sale-pulse-for-woocommerce' ),
				'placeholder' => '1403/01/05 23:59',
				'value'       => $end,
				'desc_tip'    => true,
				'description' => __( 'پس از پایان این زمان، قیمت حراجی و اطلاعات نبض حراجی توسط کران پاک می‌شود.', 'flash-sale-pulse-for-woocommerce' ),
			)
		);

		echo '<p class="form-field spw-help-text">' . esc_html__( 'برای نمایش محصول در نبض حراجی، علاوه بر این تاریخ‌ها باید قیمت فروش ویژه ووکامرس نیز تنظیم شده باشد و محصول موجود باشد.', 'flash-sale-pulse-for-woocommerce' ) . '</p>';
		echo '</div>';
	}

	public function save_product_fields( $product ) {
		// بررسی nonce برای امنیت
		if ( ! isset( $_POST['spw_product_meta_nonce'] ) || ! wp_verify_nonce( $_POST['spw_product_meta_nonce'], 'spw_save_product_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $product->get_id() ) ) {
			return;
		}

		$start_raw = isset( $_POST['_spw_sale_start_jalali'] ) ? sanitize_text_field( wp_unslash( $_POST['_spw_sale_start_jalali'] ) ) : '';
		$end_raw   = isset( $_POST['_spw_sale_end_jalali'] ) ? sanitize_text_field( wp_unslash( $_POST['_spw_sale_end_jalali'] ) ) : '';

		$start = SPW_Helpers::jalali_datetime_to_timestamp( $start_raw );
		$end   = SPW_Helpers::timestamp_to_jalali_datetime_to_timestamp( $end_raw ); // توجه: این باید با هلپر شما همخوانی داشته باشد

		if ( $start > 0 ) {
			$product->update_meta_data( '_spw_sale_start', $start );
		} else {
			$product->delete_meta_data( '_spw_sale_start' );
		}

		if ( $end > 0 ) {
			$product->update_meta_data( '_spw_sale_end', $end );
		} else {
			$product->delete_meta_data( '_spw_sale_end' );
		}
	}
}
