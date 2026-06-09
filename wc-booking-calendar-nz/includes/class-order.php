<?php
/**
 * Handles the conversion of Cart items into booked entities.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Booking_Calendar_Order {

    public function __construct() {
        // Triggered when an order is created/processed
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'add_booking_meta_to_order_item' ], 10, 4 );
        add_action( 'woocommerce_order_status_completed', [ $this, 'process_order_bookings' ] );
    }

    /**
     * Store booking data into the order line item.
     */
    public function add_booking_meta_to_order_item( $item, $cart_item_key, $values, $order ) {
        if ( isset( $values['booking_data'] ) ) {
            $item->add_meta_data( '_booking_details', $values['booking_data'] );
        }
    }

    /**
     * Triggered when order is completed. 
     * This is where you insert into your custom booking database tables.
     */
    public function process_order_bookings( $order_id ) {
        $order = wc_get_order( $order_id );

        foreach ( $order->get_items() as $item_id => $item ) {
            $booking_data = $item->get_meta( '_booking_details' );

            if ( $booking_data ) {
                $this->create_booking_record( $booking_data, $order_id, $item_id );
            }
        }
    }

    /**
     * Logic to insert into your custom table (e.g., wp_wc_booking_calendar_bookings)
     */
    private function create_booking_record( $data, $order_id, $item_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wc_booking_calendar_bookings';

        $wpdb->insert(
            $table_name,
            [
                'order_id'    => $order_id,
                'item_id'     => $item_id,
                'booking_date' => isset($data['booking_date']) ? sanitize_text_field( $data['booking_date'] ) : '',
                'time_slot'    => isset($data['booking_time']) ? sanitize_text_field( $data['booking_time'] ) : '',
                'status'      => 'confirmed',
                'created_at'  => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s' ]
        );
    }
}

new WC_Booking_Calendar_Order();
