<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPW_Sale_Service {

	public function hooks() {
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
		add_action( 'spw_cleanup_expired_sales_event', array( $this, 'cleanup_expired_sales' ) );
	}

	public function add_cron_schedules( $schedules ) {
		if ( ! isset( $schedules['spw_five_minutes'] ) ) {
			$schedules['spw_five_minutes'] = array(
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => 'Every five minutes - Flash Sale Pulse',
			);
		}

		return $schedules;
	}

	public function is_product_in_flash_sale( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return false;
		}

		if ( ! $product->exists() ) {
			return false;
		}

		if ( ! $product->is_purchasable() ) {
			return false;
		}

		if ( ! $product->is_in_stock() ) {
			return false;
		}

		if ( ! $product->is_on_sale() ) {
			return false;
		}

		$product_id = $product->get_id();
		$window     = SPW_Helpers::get_product_sale_window( $product_id );
		$now        = current_time( 'timestamp' );

		if ( ! SPW_Helpers::is_valid_window( $window['start'], $window['end'] ) ) {
			return false;
		}

		if ( $window['start'] > $now ) {
			return false;
		}

		if ( $window['end'] <= $now ) {
			return false;
		}

		return true;
	}

	public function get_remaining_seconds( $product_id ) {
		$product_id = absint( $product_id );

		if ( ! $product_id ) {
			return 0;
		}

		$window = SPW_Helpers::get_product_sale_window( $product_id );

		if ( empty( $window['end'] ) ) {
			return 0;
		}

		return max( 0, (int) $window['end'] - current_time( 'timestamp' ) );
	}

	public function get_sale_products( $args = array() ) {
		$settings = SPW_Helpers::get_settings();

		$defaults = array(
			'limit'    => isset( $settings['products_per_page'] ) ? absint( $settings['products_per_page'] ) : 8,
			'page'     => 1,
			'orderby'  => isset( $settings['default_orderby'] ) ? sanitize_key( $settings['default_orderby'] ) : 'ending_soon',
			'category' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$limit   = max( 1, absint( $args['limit'] ) );
		$page    = max( 1, absint( $args['page'] ) );
		$orderby = sanitize_key( $args['orderby'] );

		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'     => '_spw_sale_start',
				'value'   => current_time( 'timestamp' ),
				'compare' => '<=',
				'type'    => 'NUMERIC',
			),
			array(
				'key'     => '_spw_sale_end',
				'value'   => current_time( 'timestamp' ),
				'compare' => '>',
				'type'    => 'NUMERIC',
			),
			array(
				'key'     => '_sale_price',
				'value'   => '',
				'compare' => '!=',
			),
		);

		if ( isset( $settings['hide_oos'] ) && 'yes' === $settings['hide_oos'] ) {
			$meta_query[] = array(
				'key'     => '_stock_status',
				'value'   => 'instock',
				'compare' => '=',
			);
		}

		$query_args = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'paged'               => $page,
			'ignore_sticky_posts' => true,
			'meta_query'          => $meta_query,
		);

		if ( ! empty( $args['category'] ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => sanitize_title( $args['category'] ),
				),
			);
		}

		switch ( $orderby ) {
			case 'date':
				$query_args['orderby'] = 'date';
				$query_args['order']   = 'DESC';
				break;

			case 'title':
				$query_args['orderby'] = 'title';
				$query_args['order']   = 'ASC';
				break;

			case 'ending_soon':
			default:
				$query_args['meta_key'] = '_spw_sale_end';
				$query_args['orderby']  = 'meta_value_num';
				$query_args['order']    = 'ASC';
				break;
		}

		$query    = new WP_Query( $query_args );
		$products = array();

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$product = wc_get_product( $post->ID );

				if ( $product && $this->is_product_in_flash_sale( $product ) ) {
					$products[] = $product;
				}
			}
		}

		return array(
			'products'     => $products,
			'current_page' => $page,
			'max_pages'    => (int) $query->max_num_pages,
			'total'        => (int) $query->found_posts,
		);
	}

	public function cleanup_expired_sales() {
		$settings = SPW_Helpers::get_settings();
		$batch    = isset( $settings['cleanup_batch'] ) ? max( 1, absint( $settings['cleanup_batch'] ) ) : 25;

		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => $batch,
				'fields'         => 'ids',
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

		if ( empty( $query->posts ) ) {
			return;
		}

		foreach ( $query->posts as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$product->delete_meta_data( '_spw_sale_start' );
			$product->delete_meta_data( '_spw_sale_end' );

			if ( method_exists( $product, 'set_sale_price' ) ) {
				$product->set_sale_price( '' );
			}

			if ( method_exists( $product, 'set_date_on_sale_from' ) ) {
				$product->set_date_on_sale_from( null );
			}

			if ( method_exists( $product, 'set_date_on_sale_to' ) ) {
				$product->set_date_on_sale_to( null );
			}

			$product->save();
		}
	}
}
