<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPW_Frontend {

	protected $sale_service;
	protected $localized = false;

	public function __construct( $sale_service ) {
		$this->sale_service = $sale_service;
	}

	public function hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_shortcode( 'sale_pulse_products', array( $this, 'render_products_shortcode' ) );
		add_shortcode( 'sale_pulse_block', array( $this, 'render_products_shortcode' ) );
		add_shortcode( 'sale_pulse_timer', array( $this, 'render_timer_shortcode' ) );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_single_product_timer' ), 25 );
		add_action( 'wp_ajax_spw_load_more', array( $this, 'ajax_load_more' ) );
		add_action( 'wp_ajax_nopriv_spw_load_more', array( $this, 'ajax_load_more' ) );
	}

	// Helper برای محاسبه درصد و مبلغ تخفیف
	protected function get_discount_data( $product ) {
		$reg_price = (float) $product->get_regular_price();
		$sale_price = (float) $product->get_price();
		if ( $reg_price > $sale_price && $sale_price > 0 ) {
			$saving = $reg_price - $sale_price;
			$percent = round( ($saving / $reg_price) * 100 );
			return array( 'saving' => $saving, 'percent' => $percent );
		}
		return false;
	}

	// Markup اختصاصی برای Badge روی تصویر
	protected function get_discount_badge_markup( $product ) {
		$data = $this->get_discount_data( $product );
		if ( ! $data ) return '';
		return '<span class="spw-discount-badge">' . esc_html( $data['percent'] ) . '% تخفیف</span>';
	}

	public function register_assets() {
		wp_register_style( 'spw-frontend', SPW_PLUGIN_URL . 'assets/css/spw-frontend.css', array(), SPW_VERSION );
		wp_register_script( 'spw-frontend', SPW_PLUGIN_URL . 'assets/js/spw-frontend.js', array(), SPW_VERSION, true );
	}

	protected function enqueue_assets() {
		wp_enqueue_style( 'spw-frontend' );
		wp_enqueue_script( 'spw-frontend' );
		if ( ! $this->localized ) {
			wp_localize_script( 'spw-frontend', 'spwFrontend', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'spw_load_more_nonce' ) ) );
			$this->localized = true;
		}
	}

	// باکس مخصوص صفحه‌ی تک محصول
	public function render_single_product_timer() {
		global $product;
		if ( ! $product instanceof WC_Product || ! $this->sale_service->is_product_in_flash_sale( $product ) ) return;
		$this->enqueue_assets();
		echo wp_kses_post( $this->get_single_product_timer_markup( $product ) );
	}

	protected function get_single_product_timer_markup( $product ) {
		$remaining = $this->sale_service->get_remaining_seconds( $product->get_id() );
		if ( $remaining <= 0 ) return '';
		
		$parts  = SPW_Helpers::format_seconds( $remaining );
		$window = SPW_Helpers::get_product_sale_window( $product->get_id() );
		$data   = $this->get_discount_data( $product );

		ob_start();
		?>
		<div class="spw-single-timer-box" dir="rtl" data-end="<?php echo esc_attr( $window['end'] ); ?>">
			<?php if ( $data ) : ?>
				<div class="spw-promo-badge">
					(<?php echo wp_strip_all_tags( wc_price( $data['saving'] ) ); ?> سود) <?php echo esc_html( $data['percent'] ); ?>% تخفیف
				</div>
			<?php endif; ?>
			<div class="spw-timer-items">
				<span class="spw-time-item"><strong class="spw-days"><?php echo esc_html( $parts['days'] ); ?></strong><em>روز</em></span>
				<span class="spw-time-item"><strong class="spw-hours"><?php echo esc_html( $parts['hours'] ); ?></strong><em>ساعت</em></span>
				<span class="spw-time-item"><strong class="spw-minutes"><?php echo esc_html( $parts['minutes'] ); ?></strong><em>دقیقه</em></span>
				<span class="spw-time-item"><strong class="spw-seconds"><?php echo esc_html( $parts['seconds'] ); ?></strong><em>ثانیه</em></span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	protected function get_timer_markup( $product_id ) {
		$settings  = SPW_Helpers::get_settings();
		$remaining = $this->sale_service->get_remaining_seconds( $product_id );
		if ( $remaining <= 0 ) return '';
		$parts  = SPW_Helpers::format_seconds( $remaining );
		$window = SPW_Helpers::get_product_sale_window( $product_id );

		ob_start();
		?>
		<div class="spw-timer-box" dir="rtl" data-end="<?php echo esc_attr( $window['end'] ); ?>">
			<div class="spw-timer-title"><?php echo esc_html( $settings['timer_title'] ); ?></div>
			<div class="spw-timer-items">
				<span class="spw-time-item"><strong class="spw-days"><?php echo esc_html( $parts['days'] ); ?></strong><em>روز</em></span>
				<span class="spw-time-item"><strong class="spw-hours"><?php echo esc_html( $parts['hours'] ); ?></strong><em>ساعت</em></span>
				<span class="spw-time-item"><strong class="spw-minutes"><?php echo esc_html( $parts['minutes'] ); ?></strong><em>دقیقه</em></span>
				<span class="spw-time-item"><strong class="spw-seconds"><?php echo esc_html( $parts['seconds'] ); ?></strong><em>ثانیه</em></span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	protected function get_product_card_markup( $product ) {
		if ( ! $product instanceof WC_Product ) return '';
		$settings = SPW_Helpers::get_settings();

		ob_start();
		?>
		<article class="spw-product-card" dir="rtl">
			<a class="spw-product-thumb" href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>">
				<?php echo wp_kses_post( $this->get_discount_badge_markup( $product ) ); ?>
				<?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ); ?>
			</a>
			<div class="spw-product-content">
				<h3 class="spw-product-title">
					<a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
				</h3>
				<div class="spw-product-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
				<?php echo wp_kses_post( $this->get_timer_markup( $product->get_id() ) ); ?>
				<a class="spw-product-button" href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>"><?php echo esc_html( $settings['button_text'] ); ?></a>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}
    
    // متدهای دیگر کلاس (render_timer_shortcode, render_products_shortcode و ajax_load_more) ثابت باقی می‌مانند
}
