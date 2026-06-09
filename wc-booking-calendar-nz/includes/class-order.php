<?php
/**
 * WC Booking Calendar - Order class (legacy bridge).
 *
 * NOTE: All order-line-item persistence and booking creation hooks
 * live in includes/hooks.php (which uses the bookings CPT +
 * WC_Booking_Calendar_Availability_Manager::update_availability() to
 * write to the custom tables).
 *
 * This class is kept for backward compatibility and to expose a small
 * static helper. It deliberately does NOT register WooCommerce hooks of
 * its own — doing so would create duplicate bookings and try to insert
 * rows with mismatched column names.
 *
 * @package WC_Booking_Calendar_NZ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Booking_Calendar_Order
 */
class WC_Booking_Calendar_Order {

	/**
	 * Singleton.
	 *
	 * @var WC_Booking_Calendar_Order|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return WC_Booking_Calendar_Order
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Intentionally empty — see file header.
	}

	/**
	 * Get all booking rows for an order from the custom bookings table.
	 *
	 * @param int $order_id Order ID.
	 * @return array<int,object>
	 */
	public static function get_bookings_for_order( $order_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wc_booking_calendar_bookings';
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d ORDER BY booking_date ASC, booking_time ASC",
				(int) $order_id
			)
		);
	}
}
