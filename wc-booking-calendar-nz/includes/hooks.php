<?php
/**
 * WC Booking Calendar - Cross-cutting WooCommerce hooks.
 *
 * @package WC_Booking_Calendar_NZ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register procedural hooks. Idempotent.
 *
 * @return void
 */
function wc_booking_calendar_register_hooks() {
	static $registered = false;
	if ( $registered ) {
		return;
	}
	$registered = true;

	add_filter( 'woocommerce_add_to_cart_validation', 'wc_booking_calendar_validate_add_to_cart', 10, 6 );
	add_filter( 'woocommerce_add_cart_item_data', 'wc_booking_calendar_add_cart_item_data', 10, 3 );
	add_filter( 'woocommerce_get_item_data', 'wc_booking_calendar_get_cart_item_display_data', 10, 2 );

	add_action( 'woocommerce_checkout_create_order_line_item', 'wc_booking_calendar_add_order_item_meta', 10, 4 );
	add_action( 'woocommerce_before_calculate_totals', 'wc_booking_calendar_apply_booking_price', 10, 1 );
	add_action( 'woocommerce_check_cart_items', 'wc_booking_calendar_validate_cart_items' );
	add_action( 'woocommerce_checkout_order_processed', 'wc_booking_calendar_process_checkout_order', 10, 3 );
	add_action( 'woocommerce_store_api_checkout_order_processed', 'wc_booking_calendar_process_checkout_order_blocks', 10, 1 );
	add_action( 'woocommerce_email_order_meta', 'wc_booking_calendar_email_order_meta', 10, 3 );
	add_action( 'woocommerce_review_order_before_payment', 'wc_booking_calendar_deposit_notice' );

	add_action( 'wp_ajax_wc_booking_calendar_get_bookings', 'wc_booking_calendar_get_bookings_ajax' );
	add_action( 'wp_ajax_nopriv_wc_booking_calendar_get_bookings', 'wc_booking_calendar_get_bookings_ajax' );

	add_action( 'woocommerce_order_status_cancelled', 'wc_booking_calendar_release_availability_on_cancel', 10, 1 );
	add_action( 'woocommerce_order_status_refunded', 'wc_booking_calendar_release_availability_on_cancel', 10, 1 );
}

/**
 * Get configured deposit percentage.
 *
 * @return int
 */
function wc_booking_calendar_get_deposit_percentage() {
	$percent = (int) get_option( 'wc_booking_calendar_deposit_percentage', 50 );
	return max( 0, min( 100, $percent ) );
}

/**
 * Get configured add-ons, falling back to legacy Morning Tea.
 *
 * @return array<int,array<string,mixed>>
 */
function wc_booking_calendar_get_configured_addons() {
	$addons = (array) get_option( 'wc_booking_calendar_addons', array() );
	if ( empty( $addons ) ) {
		$addons = array(
			array(
				'id'         => 1,
				'key'        => 'morning-tea',
				'label'      => __( 'Morning Tea', 'wc-booking-calendar-nz' ),
				'price'      => (float) get_option( 'wc_booking_calendar_morning_tea_price', 10 ),
				'per_person' => 1,
				'enabled'    => 1,
			),
		);
	}

	$out = array();
	foreach ( $addons as $index => $addon ) {
		if ( ! is_array( $addon ) ) {
			continue;
		}
		$label = isset( $addon['label'] ) ? sanitize_text_field( $addon['label'] ) : '';
		if ( '' === $label ) {
			continue;
		}
		$key = sanitize_title( $addon['key'] ?? $label );
		if ( '' === $key ) {
			$key = 'addon-' . ( $index + 1 );
		}
		$out[] = array(
			'id'         => isset( $addon['id'] ) ? (int) $addon['id'] : ( $index + 1 ),
			'key'        => $key,
			'label'      => $label,
			'price'      => isset( $addon['price'] ) ? (float) $addon['price'] : 0.0,
			'per_person' => ! empty( $addon['per_person'] ) ? 1 : 0,
			'enabled'    => ! array_key_exists( 'enabled', $addon ) || ! empty( $addon['enabled'] ) ? 1 : 0,
		);
	}

	return $out;
}

/**
 * Sanitize selected add-ons from request/cart payload.
 *
 * @param mixed $raw Raw input.
 * @return array<int,string>
 */
function wc_booking_calendar_sanitize_selected_addons( $raw ) {
	if ( is_string( $raw ) ) {
		$decoded = json_decode( wp_unslash( $raw ), true );
		if ( is_array( $decoded ) ) {
			$raw = $decoded;
		} elseif ( false !== strpos( $raw, ',' ) ) {
			$raw = explode( ',', $raw );
		} else {
			$raw = array( $raw );
		}
	}
	if ( ! is_array( $raw ) ) {
		return array();
	}

	$valid = array();
	foreach ( wc_booking_calendar_get_configured_addons() as $addon ) {
		$valid[] = $addon['key'];
		$valid[] = (string) $addon['id'];
	}

	$clean = array();
	foreach ( $raw as $value ) {
		$key = sanitize_title( (string) $value );
		if ( '' === $key ) {
			continue;
		}
		if ( in_array( $key, $valid, true ) || in_array( (string) $value, $valid, true ) ) {
			$clean[] = $key;
		}
	}

	return array_values( array_unique( $clean ) );
}

/**
 * Resolve selected add-on keys to full definitions.
 *
 * @param array<int,string> $selected_keys Selected keys.
 * @return array<int,array<string,mixed>>
 */
function wc_booking_calendar_resolve_selected_addons( array $selected_keys ) {
	$selected_lookup = array_map( 'sanitize_title', $selected_keys );
	$resolved        = array();
	foreach ( wc_booking_calendar_get_configured_addons() as $addon ) {
		if ( empty( $addon['enabled'] ) ) {
			continue;
		}
		if ( in_array( $addon['key'], $selected_lookup, true ) || in_array( sanitize_title( (string) $addon['id'] ), $selected_lookup, true ) ) {
			$resolved[] = $addon;
		}
	}
	return $resolved;
}

/**
 * Does the selected mode allow add-ons?
 *
 * @param string $mode Mode key.
 * @return bool
 */
function wc_booking_calendar_mode_supports_addons( $mode ) {
	$availability = WC_Booking_Calendar_Availability_Manager::get_instance();
	$mode_config  = $availability->get_mode_config( (string) $mode );
	if ( ! $mode_config ) {
		$mode_config = $availability->get_default_mode();
	}
	return ! empty( $mode_config['show_addons'] );
}

/**
 * Determine whether the selected mode should be treated as Guided Tour.
 *
 * @param string $mode Mode key or label.
 * @return bool
 */
function wc_booking_calendar_is_guided_mode( $mode ) {
	$mode = sanitize_title( (string) $mode );
	if ( in_array( $mode, array( 'guided', 'guided-tour' ), true ) ) {
		return true;
	}

	$availability = WC_Booking_Calendar_Availability_Manager::get_instance();
	$mode_config  = $availability->get_mode_config( (string) $mode );
	if ( ! $mode_config ) {
		return false;
	}

	$mode_key = sanitize_title( (string) ( $mode_config['key'] ?? $mode_config['name'] ?? '' ) );
	if ( in_array( $mode_key, array( 'guided', 'guided-tour' ), true ) ) {
		return true;
	}

	return false !== strpos( strtolower( (string) ( $mode_config['name'] ?? '' ) ), 'guided' );
}

/**
 * Count how many selected people have an effective price greater than zero.
 *
 * @param int   $product_id   Product ID.
 * @param array $person_types Person counts.
 * @return int
 */
function wc_booking_calendar_get_paying_people_count( $product_id, array $person_types ) {
	$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
	if ( ! $product ) {
		return 0;
	}

	$all_types  = (array) get_option( 'wc_booking_calendar_person_types', array() );
	$base_price = (float) $product->get_meta( '_booking_base_price' );
	if ( $base_price <= 0 ) {
		$base_price = (float) $product->get_price();
	}

	$paying_people_count = 0;
	foreach ( $person_types as $type_id => $count ) {
		$count = max( 0, (int) $count );
		if ( $count <= 0 ) {
			continue;
		}

		$adjustment = 0.0;
		foreach ( $all_types as $pt ) {
			if ( (int) ( $pt['id'] ?? 0 ) === (int) $type_id ) {
				$adjustment = (float) ( $pt['price'] ?? 0 );
				break;
			}
		}

		if ( ( $base_price + $adjustment ) > 0 ) {
			$paying_people_count += $count;
		}
	}

	return $paying_people_count;
}

/**
 * Calculate full booking total before any deposit reduction.
 *
 * @param int    $product_id     Product ID.
 * @param array  $person_types   Person counts.
 * @param string $booking_date   Booking date.
 * @param string $mode           Mode key.
 * @param array  $selected_addons Selected add-ons.
 * @return float
 */
function wc_booking_calendar_calculate_booking_total( $product_id, array $person_types, $booking_date = '', $mode = '', array $selected_addons = array() ) {
	$total_people = array_sum( array_map( 'intval', $person_types ) );
	$total        = WC_Booking_Calendar_Product::calculate_price( $product_id, $person_types );

	if ( $total_people > 0 && wc_booking_calendar_mode_supports_addons( $mode ) ) {
		foreach ( wc_booking_calendar_resolve_selected_addons( $selected_addons ) as $addon ) {
			$addon_total = (float) $addon['price'];
			if ( ! empty( $addon['per_person'] ) ) {
				$addon_total *= $total_people;
			}
			$total += $addon_total;
		}
	}

	$availability = WC_Booking_Calendar_Availability_Manager::get_instance();
	if ( $booking_date && $availability->validate_date( $booking_date ) ) {
		$rules        = $availability->get_general_rules();
		$day          = strtolower( gmdate( 'l', strtotime( $booking_date ) ) );
		$peak_days_lc = array_map( 'strtolower', (array) $rules['peak_days'] );
		if ( in_array( $day, $peak_days_lc, true ) ) {
			$total = round( $total * (float) $rules['peak_multiplier'], 2 );
		}
	}

	return round( max( 0, $total ), 2 );
}

/**
 * Calculate amount due today.
 *
 * @param float  $full_total      Full total.
 * @param string $payment_option  full|deposit.
 * @return float
 */
function wc_booking_calendar_calculate_due_today( $full_total, $payment_option ) {
	$full_total = round( max( 0, (float) $full_total ), 2 );
	if ( $full_total <= 0 ) {
		return 0.0;
	}

	$due_today = $full_total;
	if ( 'deposit' === $payment_option ) {
		$percent = wc_booking_calendar_get_deposit_percentage();
		if ( $percent > 0 && $percent < 100 ) {
			$due_today = round( $full_total * ( $percent / 100 ), 2 );
		}
	}

	if ( $due_today > 0 && $due_today < 0.01 ) {
		$due_today = 0.01;
	}

	return round( min( $full_total, $due_today ), 2 );
}

/**
 * Detect legacy Morning Tea selection.
 *
 * @param array<int,string> $selected_addons Add-ons.
 * @return string yes|no
 */
function wc_booking_calendar_has_morning_tea( array $selected_addons ) {
	foreach ( wc_booking_calendar_resolve_selected_addons( $selected_addons ) as $addon ) {
		$label = strtolower( (string) $addon['label'] );
		if ( 'morning-tea' === $addon['key'] || false !== strpos( $label, 'morning tea' ) ) {
			return 'yes';
		}
	}
	return 'no';
}

/**
 * Validate the guided-tour minimum requirement consistently.
 *
 * Guided tours require at least 10 selected people and a booking total above
 * zero after pricing rules/add-ons are applied.
 *
 * @param int    $product_id      Product ID.
 * @param string $mode            Selected booking mode.
 * @param array  $person_types    Person counts.
 * @param string $booking_date    Booking date.
 * @param array  $selected_addons Selected add-ons.
 * @return true|WP_Error
 */
function wc_booking_calendar_validate_guided_booking_requirements( $product_id, $mode, array $person_types, $booking_date = '', array $selected_addons = array() ) {
	if ( ! wc_booking_calendar_is_guided_mode( $mode ) ) {
		return true;
	}

	$paying_people = wc_booking_calendar_get_paying_people_count( $product_id, $person_types );
	if ( $paying_people < 10 ) {
		return new WP_Error( 'guided_minimum_paying_people', __( 'Guided tours require at least 10 people with a price greater than $0.00.', 'wc-booking-calendar-nz' ) );
	}

	$full_price = wc_booking_calendar_calculate_booking_total( $product_id, $person_types, $booking_date, $mode, $selected_addons );
	if ( $full_price <= 0 ) {
		return new WP_Error( 'guided_minimum_paying_people', __( 'Guided tours require at least 10 people with a price greater than $0.00.', 'wc-booking-calendar-nz' ) );
	}

	return true;
}

/**
 * Validate booking data when adding to cart.
 *
 * @param bool  $passed Current state.
 * @param int   $product_id Product ID.
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

	$selected_addons = wc_booking_calendar_sanitize_selected_addons( $_POST['booking_addons'] ?? array() );
	$guided_requirement = wc_booking_calendar_validate_guided_booking_requirements( $product_id, $mode, $person_types, $date, $selected_addons );
	if ( is_wp_error( $guided_requirement ) ) {
		wc_add_notice( $guided_requirement->get_error_message(), 'error' );
		return false;
	}

	$availability = WC_Booking_Calendar_Availability_Manager::get_instance();
	$check        = $availability->check_availability_with_person_types( $product_id, $date, $time, $person_types, $resource_id, $mode );
	if ( is_wp_error( $check ) ) {
		wc_add_notice( $check->get_error_message(), 'error' );
		return false;
	}

	$payment_option  = isset( $_POST['booking_payment_option'] ) && 'full' === sanitize_text_field( wp_unslash( $_POST['booking_payment_option'] ) ) ? 'full' : 'deposit';
	$full_price      = wc_booking_calendar_calculate_booking_total( $product_id, $person_types, $date, $mode, $selected_addons );
	$amount_due      = wc_booking_calendar_calculate_due_today( $full_price, $payment_option );

	if ( $full_price <= 0 || $amount_due <= 0 ) {
		wc_add_notice( __( 'This booking currently calculates to $0.00, so checkout cannot continue. Please set a valid booking price, deposit, or add-on amount for this product.', 'wc-booking-calendar-nz' ), 'error' );
		return false;
	}

	return $passed;
}

/**
 * Attach booking data to the cart item.
 *
 * @param array $cart_item_data Existing data.
 * @param int   $product_id Product ID.
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

	$person_types    = wc_booking_calendar_sanitize_person_types( $_POST['person_types'] ?? array() );
	$selected_addons = wc_booking_calendar_sanitize_selected_addons( $_POST['booking_addons'] ?? array() );
	$payment_option  = isset( $_POST['booking_payment_option'] ) && 'full' === sanitize_text_field( wp_unslash( $_POST['booking_payment_option'] ) ) ? 'full' : 'deposit';
	$booking_mode    = isset( $_POST['booking_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_mode'] ) ) : '';
	$booking_date    = isset( $_POST['booking_date'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_date'] ) ) : '';
	$full_price      = wc_booking_calendar_calculate_booking_total( $product_id, $person_types, $booking_date, $booking_mode, $selected_addons );
	$amount_due      = wc_booking_calendar_calculate_due_today( $full_price, $payment_option );

	if ( 0 === wc_booking_calendar_get_deposit_percentage() || 100 === wc_booking_calendar_get_deposit_percentage() ) {
		$payment_option = 'full';
		$amount_due     = $full_price;
	}

	$cart_item_data['booking_data'] = array(
		'product_id'        => $product_id,
		'booking_date'      => $booking_date,
		'booking_time'      => isset( $_POST['booking_time'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_time'] ) ) : '',
		'booking_mode'      => $booking_mode,
		'resource_id'       => isset( $_POST['booking_resource_id'] ) ? (int) $_POST['booking_resource_id'] : 0,
		'person_types'      => $person_types,
		'limited_mobility'  => isset( $_POST['booking_limited_mobility'] ) ? 'yes' : 'no',
		'special_requests'  => isset( $_POST['booking_special_requests'] ) ? sanitize_textarea_field( wp_unslash( $_POST['booking_special_requests'] ) ) : '',
		'booking_addons'    => $selected_addons,
		'payment_option'    => $payment_option,
		'booking_full_price'=> $full_price,
		'amount_due_today'  => $amount_due,
		'morning_tea'       => wc_booking_calendar_has_morning_tea( $selected_addons ),
		'unique_key'        => md5( microtime() . wp_rand() ),
	);

	return $cart_item_data;
}

/**
 * Save booking data to order line item meta.
 */
function wc_booking_calendar_add_order_item_meta( $item, $cart_item_key, $values, $order ) {
	unset( $cart_item_key, $order );
	if ( empty( $values['booking_data'] ) ) {
		return;
	}

	$data = $values['booking_data'];
	$item->add_meta_data( '_booking_date', $data['booking_date'] ?? '' );
	$item->add_meta_data( '_booking_time', $data['booking_time'] ?? '' );
	$item->add_meta_data( '_booking_mode', $data['booking_mode'] ?? '' );
	$item->add_meta_data( '_booking_resource_id', (int) ( $data['resource_id'] ?? 0 ) );
	$item->add_meta_data( '_booking_limited_mobility', $data['limited_mobility'] ?? 'no' );
	$item->add_meta_data( '_booking_special_requests', $data['special_requests'] ?? '' );
	$item->add_meta_data( '_booking_morning_tea', $data['morning_tea'] ?? 'no' );
	$item->add_meta_data( '_booking_addons', wp_json_encode( (array) ( $data['booking_addons'] ?? array() ) ) );
	$item->add_meta_data( '_booking_payment_option', $data['payment_option'] ?? 'full' );
	$item->add_meta_data( '_booking_person_types', wp_json_encode( $data['person_types'] ?? array() ) );
	$item->add_meta_data( '_booking_person_count', array_sum( (array) ( $data['person_types'] ?? array() ) ) );
	$item->add_meta_data( '_booking_full_amount', (float) ( $data['booking_full_price'] ?? 0 ) );
	$item->add_meta_data( '_booking_amount_due_today', (float) ( $data['amount_due_today'] ?? 0 ) );
	$item->add_meta_data( '_booking_total_price', (float) ( $data['booking_full_price'] ?? 0 ) );
}

/**
 * Show booking data in cart/checkout.
 *
 * @param array $item_data Item data.
 * @param array $cart_item Cart item.
 * @return array
 */
function wc_booking_calendar_get_cart_item_display_data( $item_data, $cart_item ) {
	if ( empty( $cart_item['booking_data'] ) ) {
		return $item_data;
	}

	$data = $cart_item['booking_data'];
	if ( ! empty( $data['booking_date'] ) ) {
		$item_data[] = array( 'name' => __( 'Date', 'wc-booking-calendar-nz' ), 'value' => $data['booking_date'] );
	}
	if ( ! empty( $data['booking_time'] ) ) {
		$item_data[] = array( 'name' => __( 'Time', 'wc-booking-calendar-nz' ), 'value' => $data['booking_time'] );
	}
	if ( ! empty( $data['booking_mode'] ) ) {
		$item_data[] = array( 'name' => __( 'Mode', 'wc-booking-calendar-nz' ), 'value' => $data['booking_mode'] );
	}

	$addon_labels = array();
	foreach ( wc_booking_calendar_resolve_selected_addons( (array) ( $data['booking_addons'] ?? array() ) ) as $addon ) {
		$label = $addon['label'];
		if ( function_exists( 'wc_price' ) ) {
			$label .= ' (' . wp_strip_all_tags( wc_price( (float) $addon['price'] ) ) . ( ! empty( $addon['per_person'] ) ? ' ' . __( 'per person', 'wc-booking-calendar-nz' ) : '' ) . ')';
		}
		$addon_labels[] = $label;
	}
	if ( ! empty( $addon_labels ) ) {
		$item_data[] = array( 'name' => __( 'Add-ons', 'wc-booking-calendar-nz' ), 'value' => implode( ', ', $addon_labels ) );
	}

	if ( ! empty( $data['booking_full_price'] ) && ! empty( $data['amount_due_today'] ) && (float) $data['amount_due_today'] < (float) $data['booking_full_price'] ) {
		$item_data[] = array( 'name' => __( 'Payment Today', 'wc-booking-calendar-nz' ), 'value' => __( 'Deposit', 'wc-booking-calendar-nz' ) );
		if ( function_exists( 'wc_price' ) ) {
			$item_data[] = array( 'name' => __( 'Booking Total', 'wc-booking-calendar-nz' ), 'value' => wp_strip_all_tags( wc_price( (float) $data['booking_full_price'] ) ) );
		}
	}

	return $item_data;
}

/**
 * Update cart item price based on booking selections.
 */
function wc_booking_calendar_apply_booking_price( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}

	foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
		if ( empty( $cart_item['booking_data'] ) || empty( $cart_item['data'] ) || ! is_object( $cart_item['data'] ) ) {
			continue;
		}
		$data      = $cart_item['booking_data'];
		$full      = wc_booking_calendar_calculate_booking_total(
			$cart_item['product_id'],
			isset( $data['person_types'] ) ? (array) $data['person_types'] : array(),
			(string) ( $data['booking_date'] ?? '' ),
			(string) ( $data['booking_mode'] ?? '' ),
			(array) ( $data['booking_addons'] ?? array() )
		);
		$due_today = wc_booking_calendar_calculate_due_today( $full, (string) ( $data['payment_option'] ?? 'full' ) );
		$cart->cart_contents[ $cart_item_key ]['booking_data']['booking_full_price'] = $full;
		$cart->cart_contents[ $cart_item_key ]['booking_data']['amount_due_today']   = $due_today;
		if ( $full > 0 && $due_today > 0 ) {
			$cart_item['data']->set_price( max( 0.01, (float) $due_today ) );
		}
	}
}

/**
 * Final validation during checkout to ensure availability hasn't changed.
 */
function wc_booking_calendar_validate_cart_items() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}
	$availability = WC_Booking_Calendar_Availability_Manager::get_instance();

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		if ( empty( $cart_item['booking_data'] ) ) {
			continue;
		}
		$data = $cart_item['booking_data'];
		$result = $availability->check_availability(
			(int) $cart_item['product_id'],
			(string) ( $data['booking_date'] ?? '' ),
			(string) ( $data['booking_time'] ?? '' ),
			(int) ( $data['resource_id'] ?? 0 ),
			(string) ( $data['booking_mode'] ?? '' ),
			array_sum( (array) ( $data['person_types'] ?? array() ) )
		);

		if ( is_wp_error( $result ) ) {
			wc_add_notice(
				sprintf(
					__( 'Sorry, the booking for %1$s on %2$s is no longer available: %3$s', 'wc-booking-calendar-nz' ),
					$cart_item['data']->get_name(),
					$data['booking_date'] ?? '',
					$result->get_error_message()
				),
				'error'
			);
		}
	}
}

/**
 * Display deposit notice at checkout when relevant.
 */
function wc_booking_calendar_deposit_notice() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}

	$deposit_used = false;
	foreach ( WC()->cart->get_cart() as $cart_item ) {
		if ( empty( $cart_item['booking_data'] ) ) {
			continue;
		}
		$full = (float) ( $cart_item['booking_data']['booking_full_price'] ?? 0 );
		$due  = (float) ( $cart_item['booking_data']['amount_due_today'] ?? $full );
		if ( $full > 0 && $due > 0 && $due < $full ) {
			$deposit_used = true;
			break;
		}
	}

	if ( ! $deposit_used ) {
		return;
	}

	echo '<p class="booking-deposit-notice" style="font-size:0.9em;color:#666;">' . esc_html__( 'A deposit is being charged today. The booking total remains recorded in full on the order and booking record.', 'wc-booking-calendar-nz' ) . '</p>';
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
		if ( $item->get_meta( '_booking_id' ) ) {
			continue;
		}
		$booking_id = WC_Booking_Calendar_Booking_CPT::create_booking_from_order( $item, $order );
		if ( $booking_id ) {
			$availability = WC_Booking_Calendar_Availability_Manager::get_instance();
			$availability->update_availability(
				$booking_id,
				array(
					'order_id'      => $order->get_id(),
					'order_item_id' => $item->get_id(),
					'product_id'    => $product->get_id(),
					'booking_date'  => (string) $item->get_meta( '_booking_date' ),
					'booking_time'  => (string) $item->get_meta( '_booking_time' ),
					'booking_mode'  => (string) $item->get_meta( '_booking_mode' ),
					'resource_id'   => (int) $item->get_meta( '_booking_resource_id' ),
					'person_count'  => (int) get_post_meta( $booking_id, '_booking_person_count', true ),
					'person_types'  => WC_Booking_Calendar_Booking_CPT::decode_person_types( $item->get_meta( '_booking_person_types' ) ),
					'total_price'   => (float) $item->get_meta( '_booking_full_amount' ),
					'status'        => 'pending',
				)
			);
		}
	}
}

/**
 * Block checkout adapter.
 */
function wc_booking_calendar_process_checkout_order_blocks( $order ) {
	if ( $order ) {
		wc_booking_calendar_process_checkout_order( $order->get_id(), array(), $order );
	}
}

/**
 * Append booking summary to order emails.
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

/**
 * Display Limited Mobility / Special Requests in order emails.
 *
 * @param array $formatted_meta Formatted meta data.
 * @return array
 */
function wc_booking_calendar_display_meta_in_emails( $formatted_meta ) {
	foreach ( $formatted_meta as $key => $meta ) {
		if ( '_booking_limited_mobility' === $meta->key ) {
			$formatted_meta[ $key ]->display_key = __( 'Limited Mobility / Special Requests', 'wc-booking-calendar-nz' );
		}
	}
	return $formatted_meta;
}
add_filter( 'woocommerce_order_item_get_formatted_meta_data', 'wc_booking_calendar_display_meta_in_emails', 10, 1 );

/**
 * Get bookings for a date range (AJAX endpoint).
 *
 * @return void
 */
function wc_booking_calendar_get_bookings_ajax() {
	$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'wc_booking_calendar' ) && ! wp_verify_nonce( $nonce, 'wc_booking_calendar_admin' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wc-booking-calendar-nz' ) ), 403 );
	}

	$start = isset( $_REQUEST['start'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['start'] ) ) : gmdate( 'Y-m-01' );
	$end   = isset( $_REQUEST['end'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['end'] ) ) : gmdate( 'Y-m-t' );

	global $wpdb;
	$table    = $wpdb->prefix . 'wc_booking_calendar_bookings';
	$bookings = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, product_id, booking_date, booking_time, booking_mode, resource_id, person_count, status
			   FROM {$table}
			  WHERE booking_date BETWEEN %s AND %s",
			$start,
			$end
		)
	);

	$events = array();
	foreach ( $bookings as $booking ) {
		$title    = ( 'guided' === $booking->booking_mode ? __( 'Guided', 'wc-booking-calendar-nz' ) : __( 'Booking', 'wc-booking-calendar-nz' ) ) . ' — ' . get_the_title( (int) $booking->product_id );
		$events[] = array(
			'id'    => (int) $booking->id,
			'title' => $title,
			'start' => $booking->booking_date . ( $booking->booking_time ? 'T' . substr( $booking->booking_time, 0, 5 ) : '' ),
			'color' => ( 'guided' === $booking->booking_mode ? '#f0563a' : '#3a89f0' ),
		);
	}

	wp_send_json( $events );
}

add_filter( 'woocommerce_email_order_meta_fields', 'wc_booking_calendar_add_email_instructions', 10, 3 );

/**
 * Add custom instructions to customer emails based on booking mode.
 *
 * @param array    $fields Existing fields.
 * @param bool     $sent_to_admin Sent to admin.
 * @param WC_Order $order Order.
 * @return array
 */
function wc_booking_calendar_add_email_instructions( $fields, $sent_to_admin, $order ) {
	if ( $sent_to_admin ) {
		return $fields;
	}

	foreach ( $order->get_items() as $item_id => $item ) {
		$mode = (string) $item->get_meta( '_booking_mode' );
		if ( ! $mode ) {
			continue;
		}

		$instructions = 'guided' === $mode
			? __( 'Guided Tour Instructions: Please arrive 15 minutes early and bring suitable walking shoes.', 'wc-booking-calendar-nz' )
			: __( 'Booking Instructions: Please arrive at your scheduled time and follow any venue instructions included with your booking.', 'wc-booking-calendar-nz' );

		$fields[ 'booking_instructions_' . $item_id ] = array(
			'label' => __( 'Booking Details', 'wc-booking-calendar-nz' ),
			'value' => $instructions,
		);
	}

	return $fields;
}

/**
 * Release availability when an order is cancelled or refunded.
 *
 * @param int $order_id Order ID.
 * @return void
 */
function wc_booking_calendar_release_availability_on_cancel( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}
	$availability_manager = WC_Booking_Calendar_Availability_Manager::get_instance();

	foreach ( $order->get_items() as $item ) {
		$date = $item->get_meta( '_booking_date' );
		if ( ! $date ) {
			continue;
		}

		$booking_post_id = (int) $item->get_meta( '_booking_id' );
		if ( $booking_post_id ) {
			$availability_manager->release_availability( $booking_post_id );
			$post = get_post( $booking_post_id );
			if ( $post && in_array( $post->post_status, array( 'pending', 'confirmed' ), true ) ) {
				wp_update_post( array( 'ID' => $booking_post_id, 'post_status' => 'cancelled' ) );
			}
		} else {
			$time = (string) $item->get_meta( '_booking_time' );
			$availability_manager->release_availability( (int) $item->get_product_id(), (string) $date, $time, 0 );
		}
	}
}
