<?php
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

	// Save booking data to order line item meta.
	add_action( 'woocommerce_checkout_create_order_line_item', 'wc_booking_calendar_add_order_item_meta', 10, 4 );

	// Apply booking price and deposit fee.
	add_action( 'woocommerce_before_calculate_totals', 'wc_booking_calendar_apply_booking_price', 10, 1 );
	add_action( 'woocommerce_cart_calculate_fees', 'wc_booking_calendar_add_deposit_fee', 10, 1 );

	// Final cart validation before checkout.
	add_action( 'woocommerce_check_cart_items', 'wc_booking_calendar_validate_cart_items' );

	// Booking creation from completed checkout.
	add_action( 'woocommerce_checkout_order_processed', 'wc_booking_calendar_process_checkout_order', 10, 3 );
	add_action( 'woocommerce_store_api_checkout_order_processed', 'wc_booking_calendar_process_checkout_order_blocks', 10, 1 );

	// Order email integration.
	add_action( 'woocommerce_email_order_meta', 'wc_booking_calendar_email_order_meta', 10, 3 );

	// Deposit notice at checkout.
	add_action( 'woocommerce_review_order_before_payment', 'wc_booking_calendar_deposit_notice' );

	// AJAX endpoint for getting bookings
	add_action( 'wp_ajax_wc_booking_calendar_get_bookings', 'wc_booking_calendar_get_bookings_ajax' );
	add_action( 'wp_ajax_nopriv_wc_booking_calendar_get_bookings', 'wc_booking_calendar_get_bookings_ajax' );

	// Release availability on order cancellation/refund
	add_action( 'woocommerce_order_status_cancelled', 'wc_booking_calendar_release_availability_on_cancel', 10, 1 );
	add_action( 'woocommerce_order_status_refunded', 'wc_booking_calendar_release_availability_on_cancel', 10, 1 );
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
		'morning_tea'       => isset( $_POST['booking_morning_tea'] ) ? 'yes' : 'no',
		'unique_key'        => md5( microtime() . wp_rand() ),
	);

	$cart_item_data['booking_data']['booking_price'] = WC_Booking_Calendar_Product::calculate_price( $product_id, $person_types );

	return $cart_item_data;
}

/**
 * Save booking data to order line item meta.
 *
 * @param WC_Order_Item_Product $item       Order item.
 * @param string                $cart_item_key Cart item key.
 * @param array                 $values        Cart item values.
 * @param WC_Order              $order         Order object.
 */
function wc_booking_calendar_add_order_item_meta( $item, $cart_item_key, $values, $order ) {
	if ( isset( $values['booking_data'] ) ) {
		$data = $values['booking_data'];

		// Persist booking details as meta
		$item->add_meta_data( '_booking_date', $data['booking_date'] );
		$item->add_meta_data( '_booking_time', $data['booking_time'] );
		$item->add_meta_data( '_booking_mode', $data['booking_mode'] );
		$item->add_meta_data( '_booking_resource_id', $data['resource_id'] );
		$item->add_meta_data( '_booking_limited_mobility', $data['limited_mobility'] );
		$item->add_meta_data( '_booking_special_requests', $data['special_requests'] );
		$item->add_meta_data( '_booking_morning_tea', $data['morning_tea'] );
		
		// Save Person Types as JSON for easy retrieval
		$item->add_meta_data( '_booking_person_types', json_encode( $data['person_types'] ) );
		
		// Store the full price as meta so you know the total value for the booking records
		$item->add_meta_data( '_booking_total_price', $item->get_subtotal() );
	}
}

/**
 * Update cart item price based on person types and add-ons.
 *
 * @param WC_Cart $cart
 */
function wc_booking_calendar_apply_booking_price( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

    foreach ( $cart->get_cart() as $cart_item ) {
        if ( isset( $cart_item['booking_data'] ) ) {
            $data = $cart_item['booking_data'];
            
            // 1. Calculate Base Price from Person Types
            // Ensure your WC_Booking_Calendar_Product::calculate_price() 
            // is returning the sum of all person types.
            $price = WC_Booking_Calendar_Product::calculate_price( 
                $cart_item['product_id'], 
                $data['person_types'] 
            );
            
            // 2. Add Morning Tea Surcharge if Guided
            if ( !empty( $data['morning_tea'] ) && 'guided' === $data['mode'] ) {
                $total_people = array_sum( $data['person_types'] );
                $price += ( 10 * $total_people ); // $10 per person
            }
            
            // 3. Set Final Price
            $cart_item['data']->set_price( $price );
        }
    }
}

/**
 * Apply 50% deposit as a cart fee.
 * 
 * @param WC_Cart $cart
 */
function wc_booking_calendar_add_deposit_fee( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}

	$deposit_total = 0;
	foreach ( $cart->get_cart() as $cart_item ) {
		if ( isset( $cart_item['booking_data'] ) ) {
			// Get price inclusive of tax to ensure the 50% matches the user's expected total
			$price = $cart_item['data']->get_price();
			$deposit_total += ( $price * 0.5 );
		}
	}

	if ( $deposit_total > 0 ) {
		// Add as a negative fee (discount)
		// Taxable: true (assuming GST is included in your base price)
		$cart->add_fee( __( 'Deposit Paid (50% of total)', 'wc-booking-calendar-nz' ), -$deposit_total, true );
	}
}

/**
 * Final validation during checkout to ensure availability hasn't changed.
 */
function wc_booking_calendar_validate_cart_items() {
	$availability = WC_Booking_Calendar_Availability_Manager::get_instance();

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		if ( isset( $cart_item['booking_data'] ) ) {
			$data = $cart_item['booking_data'];
			
			$result = $availability->check_availability(
				$cart_item['product_id'],
				$data['booking_date'],
				$data['booking_time'],
				$data['booking_mode'],
				array_sum( $data['person_types'] )
			);

			if ( is_wp_error( $result ) ) {
				wc_add_notice( sprintf( 
					__( 'Sorry, the booking for %s on %s is no longer available.', 'wc-booking-calendar-nz' ), 
					$cart_item['data']->get_name(), 
					$data['booking_date'] 
				), 'error' );
			}
		}
	}
}

/**
 * Display deposit notice at checkout.
 */
function wc_booking_calendar_deposit_notice() {
	echo '<p class="booking-deposit-notice" style="font-size: 0.9em; color: #666;">' . 
	     esc_html__( 'Note: A 50% deposit has been processed today. The remaining 50% balance is payable upon arrival.', 'wc-booking-calendar-nz' ) . 
	     '</p>';
}

/**
 * Sanitize person_types submission.
 *
 * @param mixed $raw Input.
 * @return array[int,int]
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

/**
 * Display Limited Mobility / Special Requests in order emails.
 *
 * @param WC_Order_Item_Product $formatted_meta Formatted meta data.
 * @return array
 */
function wc_booking_calendar_display_meta_in_emails( $formatted_meta ) {
    foreach ( $formatted_meta as $key => $meta ) {
        if ( '_booking_limited_mobility' === $meta->key ) {
            $formatted_meta[$key]->display_key = __( 'Limited Mobility / Special Requests', 'wc-booking-calendar-nz' );
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
	check_ajax_referer( 'wc_booking_calendar_nonce', 'nonce' );

	$start = sanitize_text_field( $_GET['start'] );
	$end   = sanitize_text_field( $_GET['end'] );

	global $wpdb;
	$bookings = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}wc_booking_calendar_bookings 
		 WHERE booking_date BETWEEN %s AND %s",
		$start, $end
	) );

	$events = array();
	foreach ( $bookings as $b ) {
		$events[] = array(
			'id'    => $b->id,
			'title' => $b->booking_mode . ' - ' . $b->customer_name,
			'start' => $b->booking_date . 'T' . $b->booking_time,
			'color' => ( $b->booking_mode === 'guided' ? '#f00' : '#00f' )
		);
	}

	wp_send_json( $events );
}

/**
 * Add custom instructions to customer emails based on booking mode.
 */
add_filter( 'woocommerce_email_order_meta_fields', 'wc_booking_calendar_add_email_instructions', 10, 3 );

function wc_booking_calendar_add_email_instructions( $fields, $sent_to_admin, $order ) {
    if ( $sent_to_admin ) return $fields;

    foreach ( $order->get_items() as $item_id => $item ) {
        $mode = $item->get_meta( '_booking_mode' );
        
        if ( ! $mode ) continue;

        $instructions = '';
        if ( 'guided' === $mode ) {
            $instructions = __( 'Guided Tour Instructions: Please arrive 15 minutes early. Morning tea is included as requested. Don\'t forget your walking shoes!', 'wc-booking-calendar-nz' );
        } else {
            $instructions = __( 'Self-Directed Walk Instructions: Guy will meet you at the shed at your scheduled time with your trail map. Please be punctual as he will be working on the property.', 'wc-booking-calendar-nz' );
        }

        $fields['booking_instructions_' . $item_id] = array(
            'label' => __( 'Booking Details', 'wc-booking-calendar-nz' ),
            'value' => $instructions
        );
    }

    return $fields;
}

/**
 * Release availability when an order is cancelled or refunded.
 *
 * @param int $order_id
 */
function wc_booking_calendar_release_availability_on_cancel( $order_id ) {
    $order = wc_get_order( $order_id );
    $availability_manager = WC_Booking_Calendar_Availability_Manager::get_instance();

    foreach ( $order->get_items() as $item ) {
        // Only process if this item is one of our bookings
        if ( $date = $item->get_meta( '_booking_date' ) ) {
            $product_id   = $item->get_product_id();
            $time         = $item->get_meta( '_booking_time' );
            $person_types = json_decode( $item->get_meta( '_booking_person_types' ), true );
            $total_people = array_sum( $person_types );

            // Logic to decrement the booked_count in your availability table
            // This should be a method in your AvailabilityManager
            $availability_manager->release_availability( $product_id, $date, $time, $total_people );
        }
    }
}
