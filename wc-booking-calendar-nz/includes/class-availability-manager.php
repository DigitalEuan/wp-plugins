<?php
/**
 * WC Booking Calendar - Availability Manager.
 *
 * Single source of truth for availability checks, slot calculation,
 * capacity tracking and rule enforcement.
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
	 * Full (prefixed) bookings table name.
	 *
	 * @var string
	 */
	protected $bookings_table;

	/**
	 * Full (prefixed) availability table name.
	 *
	 * @var string
	 */
	protected $availability_table;

	/**
	 * Whether the most recent availability check landed on a peak day.
	 *
	 * @var bool
	 */
	public $is_peak_day = false;

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
		global $wpdb;
		$this->bookings_table     = $wpdb->prefix . self::BOOKINGS_TABLE;
		$this->availability_table = $wpdb->prefix . self::AVAILABILITY_TABLE;
	}

	/* ------------------------------------------------------------------
	 * Public API
	 * ------------------------------------------------------------------ */

	/**
	 * Check if a specific date/time/resource combination is available.
	 *
	 * NOTE: parameter order is (product_id, date, time, resource_id, mode, person_count).
	 *
	 * @param int    $product_id   Product ID.
	 * @param string $date         Date (Y-m-d).
	 * @param string $time         Time slot ("HH:MM-HH:MM").
	 * @param int    $resource_id  Resource ID (0 for none).
	 * @param string $mode         Booking mode key (e.g. 'guided', 'self').
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

		// 2. Global blackout check
		if ( $this->is_blackout_date( $date ) ) {
			return new WP_Error( 'blackout', __( 'This date is unavailable.', 'wc-booking-calendar-nz' ) );
		}

		// 3. Basic date / time validation.
		if ( ! $this->validate_date( $date ) ) {
			return new WP_Error( 'invalid_date', __( 'Invalid booking date.', 'wc-booking-calendar-nz' ) );
		}
		if ( ! $this->validate_time( $time ) ) {
			return new WP_Error( 'invalid_time', __( 'Invalid time slot.', 'wc-booking-calendar-nz' ) );
		}

		$slot = $this->get_slot_by_time( $time );
		if ( ! $slot ) {
			return new WP_Error( 'invalid_slot', __( 'Time slot does not exist.', 'wc-booking-calendar-nz' ) );
		}
		if ( empty( $slot['enabled'] ) ) {
			return new WP_Error( 'slot_disabled', __( 'Time slot is disabled.', 'wc-booking-calendar-nz' ) );
		}

		if ( ! $this->is_day_available( $date ) ) {
			return new WP_Error( 'day_not_available', __( 'Bookings are not available on this day of the week.', 'wc-booking-calendar-nz' ) );
		}

		// Mode config: if no mode given, use the first one defined.
		$mode_config = '' === $mode ? $this->get_default_mode() : $this->get_mode_config( $mode );
		if ( ! $mode_config ) {
			return new WP_Error( 'invalid_mode', __( 'Invalid booking mode.', 'wc-booking-calendar-nz' ) );
		}

		// Group size limits.
		$min_group = (int) get_option( $this->option_prefix . 'min_group_size', 1 );
		$max_group = (int) get_option( $this->option_prefix . 'max_group_size', 50 );

		if ( $person_count < $min_group ) {
			return new WP_Error(
				'below_minimum',
				/* translators: %d: minimum group size */
				sprintf( __( 'Minimum group size is %d.', 'wc-booking-calendar-nz' ), $min_group )
			);
		}
		if ( $person_count > $max_group ) {
			return new WP_Error(
				'exceeds_maximum',
				/* translators: %d: maximum group size */
				sprintf( __( 'Maximum group size is %d.', 'wc-booking-calendar-nz' ), $max_group )
			);
		}

		global $wpdb;

		// 4. Concurrency: wrap capacity checks in a transaction.
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Guided Tour logic: an existing guided booking blocks the whole day for that product.
			if ( 'guided' === $mode ) {
				$exists = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$this->bookings_table}
						 WHERE product_id = %d
						   AND booking_date = %s
						   AND booking_mode = %s
						   AND status NOT IN ('cancelled','refunded','failed')",
						$product_id,
						$date,
						'guided'
					)
				);
				if ( $exists > 0 ) {
					throw new Exception( __( 'This day is already fully booked for a private tour.', 'wc-booking-calendar-nz' ) );
				}
			}

			// Capacity check.
			$capacity           = (int) $mode_config['max_per_slot'];
			$booked             = 0;
			$available_capacity = $capacity;

			if ( $resource_id > 0 && ! empty( $mode_config['full_day_block'] ) ) {
				// Lock resource availability for the full day.
				$wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$this->availability_table}
						 WHERE product_id = %d AND availability_date = %s AND resource_id = %d
						 FOR UPDATE",
						$product_id,
						$date,
						$resource_id
					)
				);
				if ( ! $this->check_resource_full_day( $resource_id, $date ) ) {
					throw new Exception( __( 'Resource is not available on this date.', 'wc-booking-calendar-nz' ) );
				}
			} else {
				// Lock the specific slot row, then count bookings.
				$wpdb->get_row(
					$wpdb->prepare(
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
					)
				);

				$booked             = $this->get_booked_count( $product_id, $date, $time, $resource_id );
				$available_capacity = $capacity - $booked;
				if ( $available_capacity < $person_count ) {
					throw new Exception(
						/* translators: %d: remaining capacity */
						sprintf( __( 'Only %d spots remaining.', 'wc-booking-calendar-nz' ), max( 0, $available_capacity ) )
					);
				}
			}

			// Product-specific rules.
			$product_rules = $this->get_product_rules( $product_id );
			if ( $product_rules && ! $this->check_product_rules( $product_rules, $date, $time, $resource_id ) ) {
				throw new Exception( __( 'This product is not bookable for the chosen date/time.', 'wc-booking-calendar-nz' ) );
			}

			// Global rules.
			$general_rules = $this->get_general_rules();
			if ( ! $this->check_general_rules( $general_rules, $date, $time, $resource_id ) ) {
				throw new Exception( __( 'Booking is not allowed for the chosen date or time.', 'wc-booking-calendar-nz' ) );
			}

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

			$wpdb->query( 'COMMIT' );
			return apply_filters( 'wc_booking_calendar_check_availability', $result, $product_id );

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'unavailable', $e->getMessage() );
		}
	}

	/**
	 * Convenience wrapper used by the frontend / AJAX layer that accepts
	 * a person_types array and a mode (matches caller signature in
	 * class-frontend-handler.php and hooks.php).
	 *
	 * @param int    $product_id   Product.
	 * @param string $date         Date.
	 * @param string $time         Time slot.
	 * @param array  $person_types [type_id => count].
	 * @param int    $resource_id  Resource.
	 * @param string $mode         Mode.
	 * @return array|WP_Error
	 */
	public function check_availability_with_person_types( $product_id, $date, $time, array $person_types, $resource_id = 0, $mode = '' ) {
		$person_count = array_sum( array_map( 'intval', $person_types ) );
		return $this->check_availability( $product_id, $date, $time, (int) $resource_id, (string) $mode, (int) $person_count );
	}

	/**
	 * Get all available time slots for a product on a date.
	 *
	 * @param int    $product_id  Product.
	 * @param string $date        Date.
	 * @param int    $resource_id Resource.
	 * @return array[]
	 */
	public function get_available_slots( $product_id, $date, $resource_id = 0 ) {
		$product_id  = (int) $product_id;
		$resource_id = (int) $resource_id;
		$out         = array();

		if ( ! $this->validate_date( $date ) ) {
			return $out;
		}
		if ( $this->is_blackout_date( $date ) ) {
			return $out;
		}
		if ( ! $this->is_day_available( $date ) ) {
			return $out;
		}

		$slots       = (array) get_option( $this->option_prefix . 'time_slots', array() );
		$mode_config = $this->get_default_mode();
		$capacity    = $mode_config ? (int) $mode_config['max_per_slot'] : 0;

		foreach ( $slots as $slot ) {
			if ( empty( $slot['enabled'] ) ) {
				continue;
			}
			$time   = $slot['start'] . '-' . $slot['end'];
			$booked = $this->get_booked_count( $product_id, $date, $time, $resource_id );
			$out[]  = array(
				'id'        => isset( $slot['id'] ) ? (int) $slot['id'] : 0,
				'name'      => isset( $slot['name'] ) ? (string) $slot['name'] : $time,
				'start'     => $slot['start'],
				'end'       => $slot['end'],
				'capacity'  => $capacity,
				'booked'    => $booked,
				'available' => max( 0, $capacity - $booked ),
			);
		}

		return apply_filters( 'wc_booking_calendar_available_slots', $out, $product_id, $date, $resource_id );
	}

	/* ------------------------------------------------------------------
	 * Rules / validation helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Validate lead time and advance booking constraints.
	 *
	 * @param string $date YYYY-MM-DD.
	 * @return true|WP_Error
	 */
	public function validate_booking_window( $date ) {
		if ( ! $this->validate_date( $date ) ) {
			return new WP_Error( 'invalid_date', __( 'Invalid booking date.', 'wc-booking-calendar-nz' ) );
		}

		$lead_time_days = (int) get_option( $this->option_prefix . 'lead_time', 1 );
		$advance_window = (int) get_option( $this->option_prefix . 'advance_window', 365 );

		$requested_ts = strtotime( $date . ' 00:00:00' );
		$current_ts   = (int) current_datetime()->getTimestamp();

		if ( $lead_time_days > 0 ) {
			$min_bookable_ts = strtotime( "+{$lead_time_days} days", $current_ts );
			if ( $requested_ts < $min_bookable_ts ) {
				return new WP_Error(
					'lead_time',
					/* translators: %d: minimum lead time in days */
					sprintf( __( 'Please book at least %d day(s) in advance.', 'wc-booking-calendar-nz' ), $lead_time_days )
				);
			}
		}

		if ( $advance_window > 0 ) {
			$max_bookable_ts = strtotime( "+{$advance_window} days", $current_ts );
			if ( $requested_ts > $max_bookable_ts ) {
				return new WP_Error( 'advance_window', __( 'You cannot book this far in advance.', 'wc-booking-calendar-nz' ) );
			}
		}

		return true;
	}

	/**
	 * Is date globally blacked out?
	 *
	 * @param string $date YYYY-MM-DD.
	 * @return bool
	 */
	public function is_blackout_date( $date ) {
		$blackout = (array) get_option( $this->option_prefix . 'blackout_dates', array() );
		// Also support legacy advanced[blackout_dates].
		$advanced = (array) get_option( $this->option_prefix . 'advanced', array() );
		if ( ! empty( $advanced['blackout_dates'] ) && is_array( $advanced['blackout_dates'] ) ) {
			$blackout = array_unique( array_merge( $blackout, $advanced['blackout_dates'] ) );
		}
		return in_array( $date, $blackout, true );
	}

	/**
	 * Validate Y-m-d date string.
	 *
	 * @param string $date Date.
	 * @return bool
	 */
	public function validate_date( $date ) {
		if ( ! is_string( $date ) || '' === $date ) {
			return false;
		}
		$d = DateTime::createFromFormat( 'Y-m-d', $date );
		return $d && $d->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Validate "HH:MM-HH:MM" time string.
	 *
	 * @param string $time Time.
	 * @return bool
	 */
	public function validate_time( $time ) {
		if ( ! is_string( $time ) || '' === $time ) {
			return false;
		}
		return (bool) preg_match( '/^\d{2}:\d{2}-\d{2}:\d{2}$/', $time );
	}

	/**
	 * Find a configured slot by time string ("HH:MM-HH:MM").
	 *
	 * @param string $time Time.
	 * @return array|null
	 */
	public function get_slot_by_time( $time ) {
		if ( ! $this->validate_time( $time ) ) {
			return null;
		}
		list( $start, $end ) = explode( '-', $time );
		$slots               = (array) get_option( $this->option_prefix . 'time_slots', array() );
		foreach ( $slots as $slot ) {
			if ( isset( $slot['start'], $slot['end'] ) && $slot['start'] === $start && $slot['end'] === $end ) {
				return $slot;
			}
		}
		return null;
	}

	/**
	 * Is the weekday for the given date enabled in settings?
	 *
	 * @param string $date Date.
	 * @return bool
	 */
	public function is_day_available( $date ) {
		$days = (array) get_option(
			$this->option_prefix . 'days_of_week',
			array(
				'monday'    => 1,
				'tuesday'   => 1,
				'wednesday' => 1,
				'thursday'  => 1,
				'friday'    => 1,
				'saturday'  => 0,
				'sunday'    => 0,
			)
		);
		$day  = strtolower( gmdate( 'l', strtotime( $date ) ) );
		return ! empty( $days[ $day ] );
	}

	/**
	 * Get default booking mode (first enabled).
	 *
	 * @return array|null
	 */
	public function get_default_mode() {
		$modes = (array) get_option( $this->option_prefix . 'booking_modes', array() );
		return ! empty( $modes ) ? $this->normalize_mode( $modes[0] ) : null;
	}

	/**
	 * Look up mode config by name OR key (case-insensitive).
	 *
	 * Accepts both "guided" / "self" style keys and full names.
	 *
	 * @param string $mode Mode identifier.
	 * @return array|null
	 */
	public function get_mode_config( $mode ) {
		$mode  = strtolower( (string) $mode );
		$modes = (array) get_option( $this->option_prefix . 'booking_modes', array() );

		foreach ( $modes as $m ) {
			$name_lc = strtolower( $m['name'] ?? '' );
			if ( strtolower( $m['name'] ?? '' ) === $mode ) {
				return $this->normalize_mode( $m );
			}
			// "Guided Tour" matches mode "guided"; "Self-Directed Walk" matches "self".
			if ( str_starts_with( $name_lc, $mode ) || str_contains( $name_lc, $mode ) ) {
				return $this->normalize_mode( $m );
			}
		}

		// Sensible fallback for the two common Riverhaven modes used in code.
		if ( 'guided' === $mode ) {
			return array(
				'id'             => 1,
				'name'           => 'Guided Tour',
				'description'    => '',
				'full_day_block' => 1,
				'show_addons'    => 1,
				'max_per_slot'   => 50,
			);
		}
		if ( 'self' === $mode || 'self-directed' === $mode ) {
			return array(
				'id'             => 2,
				'name'           => 'Self-Directed Walk',
				'description'    => '',
				'full_day_block' => 0,
				'show_addons'    => 0,
				'max_per_slot'   => 50,
			);
		}

		return null;
	}

	/**
	 * Normalize a mode config row.
	 *
	 * @param array $m Mode row.
	 * @return array
	 */
	protected function normalize_mode( $m ) {
		return array(
			'id'             => isset( $m['id'] ) ? (int) $m['id'] : 0,
			'name'           => isset( $m['name'] ) ? (string) $m['name'] : '',
			'description'    => isset( $m['description'] ) ? (string) $m['description'] : '',
			'full_day_block' => ! empty( $m['full_day_block'] ) ? 1 : 0,
			'show_addons'    => ! empty( $m['show_addons'] ) ? 1 : 0,
			'max_per_slot'   => isset( $m['max_per_slot'] ) ? max( 0, (int) $m['max_per_slot'] ) : 0,
		);
	}

	/**
	 * Resource availability check for a full day.
	 *
	 * @param int    $resource_id Resource ID.
	 * @param string $date        Date.
	 * @return bool
	 */
	public function check_resource_full_day( $resource_id, $date ) {
		if ( $resource_id <= 0 ) {
			return true;
		}
		if ( class_exists( 'WC_Booking_Calendar_Resource_CPT' ) ) {
			if ( ! WC_Booking_Calendar_Resource_CPT::is_resource_available( $resource_id, $date ) ) {
				return false;
			}
		}
		global $wpdb;
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->bookings_table}
				 WHERE resource_id = %d
				   AND booking_date = %s
				   AND status NOT IN ('cancelled','refunded','failed')",
				(int) $resource_id,
				$date
			)
		);
		return $count <= 0;
	}

	/**
	 * Count booked persons for a product/date/slot/resource combination.
	 *
	 * @param int    $product_id  Product.
	 * @param string $date        Date.
	 * @param string $time        Time slot ("HH:MM-HH:MM").
	 * @param int    $resource_id Resource.
	 * @return int
	 */
	public function get_booked_count( $product_id, $date, $time, $resource_id = 0 ) {
		global $wpdb;
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(person_count),0) FROM {$this->bookings_table}
				 WHERE product_id = %d
				   AND booking_date = %s
				   AND booking_time = %s
				   AND resource_id = %d
				   AND status NOT IN ('cancelled','refunded','failed')",
				(int) $product_id,
				$date,
				(string) $time,
				(int) $resource_id
			)
		);
		return $count;
	}

	/**
	 * Get product-specific rules (stored on product meta).
	 *
	 * @param int $product_id Product.
	 * @return array|null
	 */
	public function get_product_rules( $product_id ) {
		$raw = get_post_meta( (int) $product_id, '_wc_booking_calendar_rules', true );
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			return is_array( $decoded ) ? $decoded : null;
		}
		return is_array( $raw ) && ! empty( $raw ) ? $raw : null;
	}

	/**
	 * Apply product-specific rule constraints.
	 *
	 * Supported keys (all optional):
	 *  - blocked_dates (Y-m-d array)
	 *  - blocked_days  (lowercase day name array)
	 *  - allowed_resources (int array)
	 *  - booking_start_date / booking_end_date (Y-m-d)
	 *
	 * @param array  $rules       Rules.
	 * @param string $date        Date.
	 * @param string $time        Time.
	 * @param int    $resource_id Resource.
	 * @return bool
	 */
	public function check_product_rules( $rules, $date, $time, $resource_id = 0 ) {
		unset( $time );

		if ( ! empty( $rules['blocked_dates'] ) && in_array( $date, (array) $rules['blocked_dates'], true ) ) {
			return false;
		}
		if ( ! empty( $rules['blocked_days'] ) ) {
			$day = strtolower( gmdate( 'l', strtotime( $date ) ) );
			if ( in_array( $day, (array) $rules['blocked_days'], true ) ) {
				return false;
			}
		}
		if ( ! empty( $rules['booking_start_date'] ) && strtotime( $date ) < strtotime( $rules['booking_start_date'] ) ) {
			return false;
		}
		if ( ! empty( $rules['booking_end_date'] ) && strtotime( $date ) > strtotime( $rules['booking_end_date'] ) ) {
			return false;
		}
		if ( ! empty( $rules['allowed_resources'] ) && $resource_id > 0 ) {
			$allowed = array_map( 'intval', (array) $rules['allowed_resources'] );
			if ( ! in_array( (int) $resource_id, $allowed, true ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Get general (advanced) rules with sensible defaults.
	 *
	 * @return array
	 */
	public function get_general_rules() {
		$advanced = (array) get_option(
			$this->option_prefix . 'advanced',
			array(
				'peak_days'        => array( 'Saturday', 'Sunday' ),
				'peak_multiplier'  => 1.0,
				'blackout_dates'   => array(),
				'seasonal_pricing' => array(),
			)
		);
		return array(
			'peak_days'        => isset( $advanced['peak_days'] ) ? (array) $advanced['peak_days'] : array(),
			'peak_multiplier'  => isset( $advanced['peak_multiplier'] ) ? (float) $advanced['peak_multiplier'] : 1.0,
			'blackout_dates'   => isset( $advanced['blackout_dates'] ) ? (array) $advanced['blackout_dates'] : array(),
			'seasonal_pricing' => isset( $advanced['seasonal_pricing'] ) ? (array) $advanced['seasonal_pricing'] : array(),
		);
	}

	/**
	 * Apply general/global rule constraints (currently just blackouts).
	 *
	 * @param array  $rules       Rules.
	 * @param string $date        Date.
	 * @param string $time        Time.
	 * @param int    $resource_id Resource.
	 * @return bool
	 */
	public function check_general_rules( $rules, $date, $time, $resource_id = 0 ) {
		unset( $time, $resource_id );
		if ( ! empty( $rules['blackout_dates'] ) && in_array( $date, (array) $rules['blackout_dates'], true ) ) {
			return false;
		}
		return true;
	}

	/* ------------------------------------------------------------------
	 * Mutation API (called from order / cancellation hooks)
	 * ------------------------------------------------------------------ */

	/**
	 * Persist a confirmed booking to the bookings table (mirror of the CPT row).
	 *
	 * Idempotent on (booking_post_id).
	 *
	 * @param int   $booking_post_id The booking CPT post ID.
	 * @param array $data            { product_id, booking_date, booking_time, booking_mode, resource_id, person_count, order_id?, order_item_id?, total_price?, status? }.
	 * @return int Rows affected.
	 */
	public function update_availability( $booking_post_id, array $data ) {
		global $wpdb;
		$booking_post_id = (int) $booking_post_id;
		if ( ! $booking_post_id ) {
			return 0;
		}

		$time = (string) ( $data['booking_time'] ?? '' );
		list( $start, $end ) = $this->validate_time( $time ) ? explode( '-', $time ) : array( null, null );

		$row = array(
			'order_id'           => isset( $data['order_id'] ) ? (int) $data['order_id'] : (int) get_post_meta( $booking_post_id, '_booking_order_id', true ),
			'order_item_id'      => isset( $data['order_item_id'] ) ? (int) $data['order_item_id'] : (int) get_post_meta( $booking_post_id, '_booking_order_item_id', true ),
			'booking_post_id'    => $booking_post_id,
			'product_id'         => (int) ( $data['product_id'] ?? 0 ),
			'booking_date'       => (string) ( $data['booking_date'] ?? '' ),
			'booking_time'       => $time,
			'booking_time_start' => $start,
			'booking_time_end'   => $end,
			'booking_mode'       => (string) ( $data['booking_mode'] ?? '' ),
			'resource_id'        => (int) ( $data['resource_id'] ?? 0 ),
			'person_types'       => isset( $data['person_types'] ) ? wp_json_encode( $data['person_types'] ) : '',
			'person_count'       => (int) ( $data['person_count'] ?? 0 ),
			'total_price'        => isset( $data['total_price'] ) ? (float) $data['total_price'] : 0.0,
			'status'             => isset( $data['status'] ) ? (string) $data['status'] : 'pending',
		);

		$existing = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->bookings_table} WHERE booking_post_id = %d LIMIT 1",
				$booking_post_id
			)
		);

		if ( $existing ) {
			$result = $wpdb->update( $this->bookings_table, $row, array( 'id' => $existing ) );
		} else {
			$result = $wpdb->insert( $this->bookings_table, $row );
		}

		do_action( 'wc_booking_calendar_availability_updated', $booking_post_id, $row );
		return (int) $result;
	}

	/**
	 * Release availability for a booking.
	 *
	 * Two calling conventions are supported:
	 *  - release_availability( $booking_post_id )                          – preferred
	 *  - release_availability( $product_id, $date, $time, $person_count ) – legacy
	 *
	 * @param mixed ...$args See above.
	 * @return int Rows affected.
	 */
	public function release_availability( ...$args ) {
		global $wpdb;

		// Form A: single integer = booking post ID.
		if ( 1 === count( $args ) && is_numeric( $args[0] ) ) {
			$booking_post_id = (int) $args[0];
			if ( ! $booking_post_id ) {
				return 0;
			}
			$rows = $wpdb->update(
				$this->bookings_table,
				array( 'status' => 'cancelled' ),
				array( 'booking_post_id' => $booking_post_id )
			);
			do_action( 'wc_booking_calendar_availability_released', $booking_post_id );
			return (int) $rows;
		}

		// Form B: (product_id, date, time, person_count) – best effort match.
		if ( count( $args ) >= 3 ) {
			$product_id = (int) $args[0];
			$date       = (string) $args[1];
			$time       = (string) $args[2];
			$rows       = $wpdb->update(
				$this->bookings_table,
				array( 'status' => 'cancelled' ),
				array(
					'product_id'   => $product_id,
					'booking_date' => $date,
					'booking_time' => $time,
				)
			);
			do_action( 'wc_booking_calendar_availability_released_by_slot', $product_id, $date, $time );
			return (int) $rows;
		}

		return 0;
	}
}
