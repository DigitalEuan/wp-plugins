<?php
/**
 * WC Booking Calendar - Availability Manager.
 *
 * @package WC_Booking_Calendar_NZ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Booking_Calendar_Availability_Manager
 */
class WC_Booking_Calendar_Availability_Manager {

	const AVAILABILITY_TABLE = 'wc_booking_calendar_availability';
	const BOOKINGS_TABLE     = 'wc_booking_calendar_bookings';

	/**
	 * Option prefix for consistency.
	 *
	 * @var string
	 */
	protected $option_prefix = 'wc_booking_calendar_';

	/**
	 * Singleton.
	 *
	 * @var WC_Booking_Calendar_Availability_Manager|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return WC_Booking_Calendar_Availability_Manager
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
	}

	/**
	 * Check if a specific date/time/resource combination is available.
	 *
	 * @param int    $product_id   Product ID.
	 * @param string $date         Date (Y-m-d).
	 * @param string $time         Time slot ("HH:MM-HH:MM").
	 * @param int    $resource_id  Resource ID (0 for none).
	 * @param string $mode         Booking mode name.
	 * @param int    $person_count Total number of people.
	 * @return array|WP_Error      Array with availability details, or WP_Error.
	 */
	public function check_availability( $product_id, $date, $time, $resource_id = 0, $mode = '', $person_count = 1 ) {
		$product_id   = (int) $product_id;
		$resource_id  = (int) $resource_id;
		$person_count = max( 0, (int) $person_count );

		// 1. Lead Time / Advance Booking Check
		$window_check = $this->validate_booking_window( $date );
		if ( is_wp_error( $window_check ) ) {
			return $window_check;
		}

		// 2. GLOBAL BLACKOUT CHECK (before transaction - just a read operation)
		if ( $this->is_blackout_date( $date ) ) {
			return new WP_Error( 'blackout', __( 'This date is unavailable.', 'wc-booking-calendar-nz' ) );
		}

		global $wpdb;

		// Start a transaction to prevent race conditions
		$wpdb->query( 'START TRANSACTION' );

		try {
			// 3. Guided Tour Logic: Check if the day is already taken by another guided tour
			if ( 'guided' === $mode ) {
				$exists = (int) $wpdb->get_var( 
					$wpdb->prepare(
						"SELECT COUNT(*) 
						 FROM {$this->bookings_table} 
						 WHERE product_id = %d 
						   AND booking_date = %s 
						   AND booking_mode = 'guided'
						   AND status NOT IN ('cancelled','refunded','failed')",
						$product_id,
						$date
					)
				);

				if ( $exists > 0 ) {
					throw new WP_Error(
						'day_booked',
						__( 'This day is already fully booked for a private tour.', 'wc-booking-calendar-nz' )
					);
				}
			}

			if ( ! $this->validate_date( $date ) ) {
				throw new WP_Error( 'invalid_date', __( 'Invalid booking date.', 'wc-booking-calendar-nz' ) );
			}
			if ( ! $this->validate_time( $time ) ) {
				throw new WP_Error( 'invalid_time', __( 'Invalid time slot.', 'wc-booking-calendar-nz' ) );
			}

			$slot = $this->get_slot_by_time( $time );
			if ( ! $slot ) {
				throw new WP_Error( 'invalid_slot', __( 'Time slot does not exist.', 'wc-booking-calendar-nz' ) );
			}
			if ( empty( $slot['enabled'] ) ) {
				throw new WP_Error( 'slot_disabled', __( 'Time slot is disabled.', 'wc-booking-calendar-nz' ) );
			}

			if ( ! $this->is_day_available( $date ) ) {
				throw new WP_Error( 'day_not_available', __( 'Bookings are not available on this day of the week.', 'wc-booking-calendar-nz' ) );
			}

			// Mode config: if no mode given, use the first one defined.
			$mode_config = '' === $mode ? $this->get_default_mode() : $this->get_mode_config( $mode );
			if ( ! $mode_config ) {
				throw new WP_Error( 'invalid_mode', __( 'Invalid booking mode.', 'wc-booking-calendar-nz' ) );
			}

			// Group size limits.
			$min_group = (int) get_option( $this->option_prefix . 'min_group_size', 1 );
			$max_group = (int) get_option( $this->option_prefix . 'max_group_size', 50 );

			if ( $person_count < $min_group ) {
				throw new WP_Error(
					'below_minimum',
					/* translators: %d: minimum group size */
					sprintf( __( 'Minimum group size is %d.', 'wc-booking-calendar-nz' ), $min_group )
				);
			}
			if ( $person_count > $max_group ) {
				throw new WP_Error(
					'exceeds_maximum',
					/* translators: %d: maximum group size */
					sprintf( __( 'Maximum group size is %d.', 'wc-booking-calendar-nz' ), $max_group )
				);
			}

			// Capacity check - Lock the specific availability row
			$capacity         = (int) $mode_config['max_per_slot'];
			$booked           = 0;
			$available_capacity = $capacity;

			if ( $resource_id > 0 && ! empty( $mode_config['full_day_block'] ) ) {
				// Lock resource availability for the full day
				$wpdb->get_row( $wpdb->prepare(
					"SELECT * FROM {$this->availability_table}
					 WHERE product_id = %d 
					   AND availability_date = %s 
					   AND resource_id = %d
					 FOR UPDATE",
					$product_id,
					$date,
					$resource_id
				) );

				if ( ! $this->check_resource_full_day( $resource_id, $date ) ) {
					throw new WP_Error( 'resource_blocked', __( 'Resource is not available on this date.', 'wc-booking-calendar-nz' ) );
				}
			} else {
				// Lock the specific slot availability
				$wpdb->get_row( $wpdb->prepare(
					"SELECT * FROM {$this->availability_table}
					 WHERE product_id = %d 
					   AND availability_date = %s 
					   AND slot_start = %s 
					   AND slot_end = %s 
					   AND resource_id = %d
					 FOR UPDATE",
					$product_id,
					$date,
					$slot['start'],
					$slot['end'],
					$resource_id
				) );

				$booked             = $this->get_booked_count( $product_id, $date, $time, $resource_id );
				$available_capacity = $capacity - $booked;
				if ( $available_capacity < $person_count ) {
					throw new WP_Error(
						'insufficient_capacity',
						/* translators: %d: remaining capacity */
						sprintf( __( 'Only %d spots remaining.', 'wc-booking-calendar-nz' ), max( 0, $available_capacity ) )
					);
				}
			}

			// Product-specific rules.
			$product_rules = $this->get_product_rules( $product_id );
			if ( $product_rules && ! $this->check_product_rules( $product_rules, $date, $time, $resource_id ) ) {
				throw new WP_Error( 'product_rule_blocked', __( 'This product is not bookable for the chosen date/time.', 'wc-booking-calendar-nz' ) );
			}

			// General (global) rules.
			$general_rules = $this->get_general_rules();
			if ( ! $this->check_general_rules( $general_rules, $date, $time, $resource_id ) ) {
				throw new WP_Error( 'rule_blocked', __( 'Booking is not allowed for the chosen date or time.', 'wc-booking-calendar-nz' ) );
			}

			// Track peak day for callers.
			$this->is_peak_day = in_array( gmdate( 'l', strtotime( $date ) ), (array) $general_rules['peak_days'], true );

			$result = array(
				'available'          => true,
				'product_id'         => $product_id,
				'date'               => $date,
				'time'               => $time,
				'resource_id'        => $resource_id,
				'mode'               => $mode_config['name'],
				'person_count'       => $person_count,
				'capacity'           => $capacity,
				'booked_count'       => $booked,
				'available_capacity' => $available_capacity,
				'is_peak_day'        => $this->is_peak_day,
			);

			// Commit transaction
			$wpdb->query( 'COMMIT' );
			
			return apply_filters( 'wc_booking_calendar_check_availability', $result, $product_id );

		} catch ( WP_Error $e ) {
			// Rollback on error
			$wpdb->query( 'ROLLBACK' );
			return $e;
		} catch ( Exception $e ) {
			// Rollback on unexpected error
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'concurrency_error', $e->getMessage() );
		}
	}

	/**
	 * Validate lead time and advance booking constraints.
	 *
	 * @param string $date The requested booking date (YYYY-MM-DD).
	 * @return bool|WP_Error
	 */
	public function validate_booking_window( $date ) {
		$lead_time_days    = (int) get_option( $this->option_prefix . 'lead_time', 1 );
		$advance_window    = (int) get_option( $this->option_prefix . 'advance_window', 365 );
		
		$requested_ts = strtotime( $date );
		$current_ts   = current_time( 'timestamp' );
		
		// 1. Lead Time Check
		// If lead_time is 0, allow same-day bookings
		if ( $lead_time_days > 0 ) {
			$min_bookable_ts = strtotime( "+{$lead_time_days} days", $current_ts );
			if ( $requested_ts < $min_bookable_ts ) {
				return new WP_Error( 
					'lead_time', 
					sprintf( 
						__( 'Please book at least %d day(s) in advance.', 'wc-booking-calendar-nz' ), 
						$lead_time_days 
					) 
				);
			}
		}

		// 2. Advance Booking Check
		$max_bookable_ts = strtotime( "+{$advance_window} days", $current_ts );
		if ( $requested_ts > $max_bookable_ts ) {
			return new WP_Error( 
				'advance_window', 
				__( 'You cannot book this far in advance.', 'wc-booking-calendar-nz' ) 
			);
		}

		return true;
	}

	/**
	 * Check if a date is globally blacked out.
	 * 
	 * @param string $date YYYY-MM-DD
	 * @return bool
	 */
	public function is_blackout_date( $date ) {
		$blackout_dates = get_option( $this->option_prefix . 'blackout_dates', array() );
		
		return in_array( $date, (array) $blackout_dates, true );
	}

	// ... rest of existing methods (validate_date, validate_time, get_slot_by_time, is_day_available, get_default_mode, get_mode_config, check_resource_full_day, get_booked_count, get_product_rules, check_product_rules, get_general_rules, check_general_rules, export_rules, import_rules, etc.)
}
