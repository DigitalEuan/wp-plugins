/**
 * WC Booking Calendar - Cross-cutting WooCommerce hooks.
 *
 * Procedural — separate from the OO subsystems. Invoked from the main
 * plugin via wc_booking_calendar_register_hooks().
 *
 * @package WC_Booking_Calendar_NZ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register procedural hooks. Idempotent — safe to call twice.
 *
 * @return void
 */
function wc_booking_calendar_register_hooks() {
	static $registered = false;
	if ( $registered ) {
		return;
	}
	$registered = true;

	// Cart validation.
	add_filter( 'woocommerce_add_to_cart_validation', 'wc_booking_calendar_validate_add_to_cart', 10, 6 );
	add_filter( 'woocommerce_add_cart_item_data', 'wc_booking_calendar_add_cart_item_data', 10, 3 );

	// Booking creation from completed checkout.
	add_action( 'woocommerce_checkout_order_processed', 'wc_booking_calendar_process_checkout_order', 10, 3 );
	add_action( 'woocommerce_store_api_checkout_order_processed', 'wc_booking_calendar_process_checkout_order_blocks', 10, 1 );

	// Order email integration.
	add_action( 'woocommerce_email_order_meta', 'wc_booking_calendar_email_order_meta', 10, 3 );
}

/**
 * Validate booking data when adding to cart.
 *
 * @param bool   $passed     Current state.
 * @param int    $product_id Product ID.
 * @param int    $quantity   Quantity.
 * @param int    $variation_id Variation ID (unused).
 * @param array  $variations Variations (unused).
 * @param array  $cart_item_data Existing data (unused).
 * @return bool
 */
function wc_booking_calendar_validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = array(), $cart_item_data = array() ) {
	unset( $quantity, $variation_id, $variations, $cart_item_data );

	if ( ! WC_Booking_Calendar_Product::is_booking_product( $product_id ) ) {
		return $passed;
	}

	$nonce = isset( $_POST['wc_booking_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_booking_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'wc_booking_calendar_add_to_cart' ) ) {
		wc_add_notice( __( 'Security check failed. Please refresh the page and try again.', 'wc-booking-calendar-nz' ), 'error' );
		return false;
	}

	$date = isset( $_POST['booking_date'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_date'] ) ) : '';
	$time = isset( $_POST['booking_time'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_time'] ) ) : '';

	if ( '' === $date || '' === $time ) {
		wc_add_notice( __( 'Please pick a date and time slot.', 'wc-booking-calendar-nz' ), 'error' );
		return false;
	}

	$resource_id  = isset( $_POST['booking_resource_id'] ) ? (int) $_POST['booking_resource_id'] : 0;
	$mode         = isset( $_POST['booking_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_mode'] ) ) : '';
	$person_types = wc_booking_calendar_sanitize_person_types( $_POST['person_types'] ?? array() );
	$total_people = array_sum( $person_types );
	if ( $total_people <= 0 ) {
		wc_add_notice( __( 'Please add at least one person to the booking.', 'wc-booking-calendar-nz' ), 'error' );
		return false;
	}

	$availability = WC_Booking_Calendar_Availability_Manager::get_instance();
	$check        = $availability->check_availability_with_person_types( $product_id, $date, $time, $person_types, $resource_id, $mode );
	if ( is_wp_error( $check ) ) {
		wc_add_notice( $check->get_error_message(), 'error' );
		return false;
	}

	return $passed;
}

/**
 * Attach booking data to the cart item.
 *
 * @param array $cart_item_data Existing data.
 * @param int   $product_id     Product ID.
 * @param int   $variation_id   Variation ID.
 * @return array
 */
function wc_booking_calendar_add_cart_item_data( $cart_item_data, $product_id, $variation_id = 0 ) {
	unset( $variation_id );

	if ( ! WC_Booking_Calendar_Product::is_booking_product( $product_id ) ) {
		return $cart_item_data;
	}

	$nonce = isset( $_POST['wc_booking_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_booking_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'wc_booking_calendar_add_to_cart' ) ) {
		return $cart_item_data;
	}

	$person_types = wc_booking_calendar_sanitize_person_types( $_POST['person_types'] ?? array() );

	$cart_item_data['booking_data'] = array(
		'product_id'        => $product_id,
		'booking_date'      => isset( $_POST['booking_date'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_date'] ) ) : '',
		'booking_time'      => isset( $_POST['booking_time'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_time'] ) ) : '',
		'booking_mode'      => isset( $_POST['booking_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_mode'] ) ) : '',
		'resource_id'       => isset( $_POST['booking_resource_id'] ) ? (int) $_POST['booking_resource_id'] : 0,
		'person_types'      => $person_types,
		'limited_mobility'  => isset( $_POST['booking_limited_mobility'] ) ? 'yes' : 'no',
		'special_requests'  => isset( $_POST['booking_special_requests'] ) ? sanitize_textarea_field( wp_unslash( $_POST['booking_special_requests'] ) ) : '',
		'unique_key'        => md5( microtime() . wp_rand() ),
	);

	$cart_item_data['booking_data']['booking_price'] = WC_Booking_Calendar_Product::calculate_price( $product_id, $person_types );

	return $cart_item_data;
}

/**
 * Sanitize person_types submission.
 *
 * @param mixed $raw Input.
 * @return array<int,int>
 */
function wc_booking_calendar_sanitize_person_types( $raw ) {
	if ( is_string( $raw ) ) {
		$raw = json_decode( wp_unslash( $raw ), true );
	}
	if ( ! is_array( $raw ) ) {
		return array();
	}
	$clean = array();
	foreach ( $raw as $type_id => $count ) {
		$tid = (int) $type_id;
		$c   = max( 0, (int) $count );
		if ( $tid > 0 && $c > 0 ) {
			$clean[ $tid ] = $c;
		}
	}
	return $clean;
}

/**
 * Called after a classic checkout order is processed.
 *
 * @param int      $order_id  Order ID.
 * @param array    $posted    Posted data (unused).
 * @param WC_Order $order     Order.
 * @return void
 */
function wc_booking_calendar_process_checkout_order( $order_id, $posted, $order ) {
	unset( $posted );
	if ( ! $order ) {
		$order = wc_get_order( $order_id );
	}
	if ( ! $order ) {
		return;
	}

	foreach ( $order->get_items() as $item ) {
		$product = method_exists( $item, 'get_product' ) ? $item->get_product() : null;
		if ( ! $product || ! WC_Booking_Calendar_Product::is_booking_product( $product ) ) {
			continue;
		}
		$booking_id = WC_Booking_Calendar_Booking_CPT::create_booking_from_order( $item, $order );
		if ( $booking_id ) {
			$availability = WC_Booking_Calendar_Availability_Manager::get_instance();
			$availability->update_availability(
				$booking_id,
				array(
					'product_id'   => $product->get_id(),
					'booking_date' => (string) $item->get_meta( '_booking_date' ),
					'booking_time' => (string) $item->get_meta( '_booking_time' ),
					'booking_mode' => (string) $item->get_meta( '_booking_mode' ),
					'resource_id'  => (int) $item->get_meta( '_booking_resource_id' ),
					'person_count' => (int) get_post_meta( $booking_id, '_booking_person_count', true ),
				)
			);
		}
	}
}

/**
 * Block checkout adapter.
 *
 * @param WC_Order $order Order.
 * @return void
 */
function wc_booking_calendar_process_checkout_order_blocks( $order ) {
	if ( $order ) {
		wc_booking_calendar_process_checkout_order( $order->get_id(), array(), $order );
	}
}

/**
 * Append booking summary to order emails.
 *
 * @param WC_Order $order        Order.
 * @param bool     $sent_to_admin Sent to admin flag.
 * @param bool     $plain_text   Plain text.
 * @return void
 */
function wc_booking_calendar_email_order_meta( $order, $sent_to_admin = false, $plain_text = false ) {
	unset( $sent_to_admin );

	$lines = array();
	foreach ( $order->get_items() as $item ) {
		$date = $item->get_meta( '_booking_date' );
		$time = $item->get_meta( '_booking_time' );
		if ( ! $date || ! $time ) {
			continue;
		}
		$lines[] = sprintf( '%s — %s %s', $item->get_name(), $date, $time );
	}
	if ( empty( $lines ) ) {
		return;
	}
	if ( $plain_text ) {
		echo "\n" . esc_html__( 'Bookings', 'wc-booking-calendar-nz' ) . "\n";
		foreach ( $lines as $line ) {
			echo esc_html( $line ) . "\n";
		}
	} else {
		echo '<h2>' . esc_html__( 'Bookings', 'wc-booking-calendar-nz' ) . '</h2><ul>';
		foreach ( $lines as $line ) {
			echo '<li>' . esc_html( $line ) . '</li>';
		}
		echo '</ul>';
	}
}