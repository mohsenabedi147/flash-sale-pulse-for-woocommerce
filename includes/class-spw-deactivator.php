<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPW_Deactivator {

	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'spw_cleanup_expired_sales_event' );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'spw_cleanup_expired_sales_event' );
		}
	}
}
