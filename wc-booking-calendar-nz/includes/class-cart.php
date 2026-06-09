<?php
/**
 * WC Booking Calendar - Cart class (legacy bridge).
 *
 * NOTE: The canonical cart hooks live in includes/hooks.php
 * (registered via wc_booking_calendar_register_hooks()).
 *
 * This class is kept as a thin bridge so any third-party code that calls
 * WC_Booking_Calendar_Cart::get_instance() still works. It intentionally
 * does NOT register WooCommerce hooks of its own — doing so would
 * double-fire the cart filters and overwrite the data set by hooks.php.
 *
 * @package WC_Booking_Calendar_NZ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Booking_Calendar_Cart
 */
class WC_Booking_Calendar_Cart {

	/**
	 * Singleton.
	 *
	 * @var WC_Booking_Calendar_Cart|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return WC_Booking_Calendar_Cart
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
}
