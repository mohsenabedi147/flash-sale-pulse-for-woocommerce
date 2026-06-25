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

	public function register_assets() {
		wp_register_style(
			'spw-frontend',
			SPW_PLUGIN_URL . 'assets/css/spw-frontend.css',
			array(),
			SPW_VERSION
		);

		wp_register_script(
			'spw-frontend',
			SPW_PLUGIN_URL . 'assets/js/spw-frontend.js',
			array(),
			SPW_VERSION,
			true
		);
	}

	protected function enqueue_assets() {
		wp_enqueue_style( 'spw-frontend' );
		wp_enqueue_script( 'spw-frontend' );

		if ( ! $this->localized ) {
			wp_localize_script(
				'spw-frontend',
				'spwFrontend',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'spw_load_more_nonce' ),
				)
			);

			$this->localized = true;
		}
	}

	public function render_single_product_timer() {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		if ( ! $this->sale_service->is_product_in_flash_sale( $product ) ) {
			return;
		}

		$this->enqueue_assets();

		echo wp_kses_post( $this->get_timer_markup( $product->get_id() ) );
	}

	public function render_timer_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'product_id' => 0,
			),
			$atts,
			'sale_pulse_timer'
		);

		$product_id = absint( $atts['product_id'] );

		if ( ! $product_id ) {
			return '';
		}

		$product = wc_get_product( $product_id );

		if ( ! $product || ! $this->sale_service->is_product_in_flash_sale( $product ) ) {
			return '';
		}

		$this->enqueue_assets();

		return $this->get_timer_markup( $product_id );
	}

	public function render_products_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'    => 8,
				'page'     => 1,
				'orderby'  => 'ending_soon',
				'category' => '',
			),
			$atts,
			'sale_pulse_products'
		);

		$this->enqueue_assets();

		$limit    = max( 1, absint( $atts['limit'] ) );
		$page     = max( 1, absint( $atts['page'] ) );
		$orderby  = sanitize_key( $atts['orderby'] );
		$category = sanitize_text_field( $atts['category'] );

		$results = $this->sale_service->get_sale_products(
			array(
				'limit'    => $limit,
				'page'     => $page,
				'orderby'  => $orderby,
				'category' => $category,
			)
		);

		ob_start();
		?>
		<div class="spw-products-wrap"
			dir="rtl"
			data-limit="<?php echo esc_attr( $limit ); ?>"
			data-orderby="<?php echo esc_attr( $orderby ); ?>"
			data-category="<?php echo esc_attr( $category ); ?>"
			data-page="<?php echo esc_attr( $results['current_page'] ); ?>"
			data-max-pages="<?php echo esc_attr( $results['max_pages'] ); ?>">

			<div class="spw-products-grid">
				<?php
				if ( ! empty( $results['products'] ) ) {
					foreach ( $results['products'] as $product ) {
						echo wp_kses_post( $this->get_product_card_markup( $product ) );
					}
				}
				?>
			</div>

			<?php if ( empty( $results['products'] ) ) : ?>
				<div class="spw-empty">در حال حاضر محصولی برای نمایش در حراجی وجود ندارد.</div>
			<?php endif; ?>

			<?php if ( $results['current_page'] < $results['max_pages'] ) : ?>
				<button type="button" class="spw-load-more">نمایش محصولات بیشتر</button>
			<?php endif; ?>
		</div>
		<?php

		return ob_get_clean();
	}

	public function ajax_load_more() {
		check_ajax_referer( 'spw_load_more_nonce', 'nonce' );

		$page     = isset( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 1;
		$limit    = isset( $_POST['limit'] ) ? absint( wp_unslash( $_POST['limit'] ) ) : 8;
		$orderby  = isset( $_POST['orderby'] ) ? sanitize_key( wp_unslash( $_POST['orderby'] ) ) : 'ending_soon';
		$category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';

		$results = $this->sale_service->get_sale_products(
			array(
				'limit'    => $limit,
				'page'     => $page,
				'orderby'  => $orderby,
				'category' => $category,
			)
		);

		$html = '';

		foreach ( $results['products'] as $product ) {
			$html .= $this->get_product_card_markup( $product );
		}

		wp_send_json_success(
			array(
				'html'      => $html,
				'page'      => $results['current_page'],
				'max_pages' => $results['max_pages'],
			)
		);
	}

	protected function get_timer_markup( $product_id ) {
		$settings  = SPW_Helpers::get_settings();
		$remaining = $this->sale_service->get_remaining_seconds( $product_id );

		if ( $remaining <= 0 ) {
			return '';
		}

		$parts  = SPW_Helpers::format_seconds( $remaining );
		$window = SPW_Helpers::get_product_sale_window( $product_id );

		ob_start();
		?>
		<div class="spw-timer-box" dir="rtl" data-end="<?php echo esc_attr( $window['end'] ); ?>">
			<div class="spw-timer-title"><?php echo esc_html( $settings['timer_title'] ); ?></div>

			<div class="spw-timer-items">
				<span class="spw-time-item">
					<strong class="spw-days"><?php echo esc_html( $parts['days'] ); ?></strong>
					<em>روز</em>
				</span>

				<span class="spw-time-item">
					<strong class="spw-hours"><?php echo esc_html( $parts['hours'] ); ?></strong>
					<em>ساعت</em>
				</span>

				<span class="spw-time-item">
					<strong class="spw-minutes"><?php echo esc_html( $parts['minutes'] ); ?></strong>
					<em>دقیقه</em>
				</span>

				<span class="spw-time-item">
					<strong class="spw-seconds"><?php echo esc_html( $parts['seconds'] ); ?></strong>
					<em>ثانیه</em>
				</span>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	protected function get_product_card_markup( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		$settings = SPW_Helpers::get_settings();

		ob_start();
		?>
		<article class="spw-product-card" dir="rtl">
			<a class="spw-product-thumb" href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>">
				<?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ); ?>
			</a>

			<div class="spw-product-content">
				<h3 class="spw-product-title">
					<a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>">
						<?php echo esc_html( $product->get_name() ); ?>
					</a>
				</h3>

				<div class="spw-product-price">
					<?php echo wp_kses_post( $product->get_price_html() ); ?>
				</div>

				<?php echo wp_kses_post( $this->get_timer_markup( $product->get_id() ) ); ?>

				<a class="spw-product-button" href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>">
					<?php echo esc_html( $settings['button_text'] ); ?>
				</a>
			</div>
		</article>
		<?php

		return ob_get_clean();
	}
}
