<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WC_Booking_Calendar_Cart {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_booking_data_to_cart' ], 10, 3 );
        add_filter( 'woocommerce_get_item_data', [ $this, 'display_cart_item_data' ], 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'add_booking_meta_to_order_item' ], 10, 4 );
        add_filter( 'woocommerce_add_cart_item_identifier', [ $this, 'make_cart_item_unique' ], 10, 4 );
    }

    /**
     * Capture booking data from the form POST request.
     */
    public function add_booking_data_to_cart( $cart_item_data, $product_id, $variation_id ) {
        if ( isset( $_POST['booking_date'] ) && isset( $_POST['booking_time'] ) ) {
            
            $cart_item_data['booking_data'] = [
                'booking_date' => sanitize_text_field( $_POST['booking_date'] ),
                'booking_time' => sanitize_text_field( $_POST['booking_time'] ),
            ];

            // Generate a unique key to allow multiple different bookings of the same item
            $cart_item_data['unique_key'] = md5( microtime() . $product_id );
        }
        return $cart_item_data;
    }

    /**
     * Display the booking details in the cart and checkout pages.
     */
    public function display_cart_item_data( $item_data, $cart_item ) {
        if ( isset( $cart_item['booking_data'] ) ) {
            $item_data[] = [
                'key'   => __( 'Booking Date', 'wc-booking-calendar-nz' ),
                'value' => esc_html( $cart_item['booking_data']['booking_date'] )
            ];
            $item_data[] = [
                'key'   => __( 'Time Slot', 'wc-booking-calendar-nz' ),
                'value' => esc_html( $cart_item['booking_data']['booking_time'] )
            ];
        }
        return $item_data;
    }

    /**
     * Pass the booking data to the order line item so it persists in the database.
     */
    public function add_booking_meta_to_order_item( $item, $cart_item_key, $values, $order ) {
        if ( isset( $values['booking_data'] ) ) {
            $item->add_meta_data( '_booking_date', $values['booking_data']['booking_date'] );
            $item->add_meta_data( '_booking_time', $values['booking_data']['booking_time'] );
        }
    }

    /**
     * Make cart items unique based on booking details.
     */
    public function make_cart_item_unique( $cart_item_key, $product_id, $variation_id, $variation ) {
        if ( isset( $_POST['booking_date'] ) && isset( $_POST['booking_time'] ) ) {
            // Create a unique hash based on the specific booking details
            return md5( $cart_item_key . $_POST['booking_date'] . $_POST['booking_time'] );
        }
        return $cart_item_key;
    }
}

// Initialize
WC_Booking_Calendar_Cart::get_instance();
