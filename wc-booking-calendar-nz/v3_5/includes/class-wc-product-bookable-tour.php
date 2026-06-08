/**
 * WC Booking Calendar - Bookable Tour Product Class.
 *
 * Loaded on woocommerce_loaded after WC_Product_Simple exists.
 *
 * @package WC_Booking_Calendar_NZ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Product_Simple' ) ) {
	return;
}

/**
 * Bookable tour product type.
 */
class WC_Product_Bookable_Tour extends WC_Product_Simple {

	/**
	 * Get internal type.
	 *
	 * @return string
	 */
	public function get_type() {
		return WC_Booking_Calendar_Product::PRODUCT_TYPE;
	}

	/**
	 * Always purchasable.
	 *
	 * @return bool
	 */
	public function is_purchasable() {
		return true;
	}

	/**
	 * Bookings are virtual (no shipping).
	 *
	 * @return bool
	 */
	public function is_virtual() {
		return true;
	}

	/**
	 * No stock management.
	 *
	 * @return bool
	 */
	public function managing_stock() {
		return false;
	}
}