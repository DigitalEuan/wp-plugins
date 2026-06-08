/**
 * WC Booking Calendar - Availability Manager
 *
 * Core logic for slots, blocking, rules, and availability checking.
 *
 * @package WC_Booking_Calendar_NZ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Booking_Calendar_Availability_Manager
 *
 * Pure-ish logic class — most public methods are deterministic given options
 * and database state, which makes them straightforward to unit test.
 */
class WC_Booking_Calendar_Availability_Manager {

	/**
	 * Singleton instance.
	 *
	 * @var WC_Booking_Calendar_Availability_Manager|null
	 */
	private static $instance = null;

	/**
	 * Bookings table name (with prefix).
	 *
	 * @var string
	 */
	public $bookings_table;

	/**
	 * Availability table name (with prefix).
	 *
	 * @var string
	 */
	public $availability_table;

	/**
	 * Whether the last availability check landed on a peak day.
	 *
	 * @var bool
	 */
	public $is_peak_day = false;

	/**
	 * Get singleton instance.
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
	 * Reset singleton — testing only.
	 *
	 * @return void
	 */
	public static function reset_instance() {
		self::$instance = null;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		global $wpdb;
		$prefix                   = $wpdb ? $wpdb->prefix : 'wp_';
		$this->bookings_table     = $prefix . 'wc_booking_calendar_bookings';
		$this->availability_table = $prefix . 'wc_booking_calendar_availability';
	}

	/* ------------------------------------------------------------------
	 * Availability checking
	 * ------------------------------------------------------------------ */

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
		$min_group = (int) get_option( 'wc_booking_calendar_min_group_size', 1 );
		$max_group = (int) get_option( 'wc_booking_calendar_max_group_size', 50 );

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

		// Capacity check.
		$capacity         = (int) $mode_config['max_per_slot'];
		$booked           = 0;
		$available_capacity = $capacity;

		if ( $resource_id > 0 && ! empty( $mode_config['full_day_block'] ) ) {
			if ( ! $this->check_resource_full_day( $resource_id, $date ) ) {
				return new WP_Error( 'resource_blocked', __( 'Resource is not available on this date.', 'wc-booking-calendar-nz' ) );
			}
		} else {
			$booked             = $this->get_booked_count( $product_id, $date, $time, $resource_id );
			$available_capacity = $capacity - $booked;
			if ( $available_capacity < $person_count ) {
				return new WP_Error(
					'insufficient_capacity',
					/* translators: %d: remaining capacity */
					sprintf( __( 'Only %d spots remaining.', 'wc-booking-calendar-nz' ), max( 0, $available_capacity ) )
				);
			}
		}

		// Product-specific rules.
		$product_rules = $this->get_product_rules( $product_id );
		if ( $product_rules && ! $this->check_product_rules( $product_rules, $date, $time, $resource_id ) ) {
			return new WP_Error( 'product_rule_blocked', __( 'This product is not bookable for the chosen date/time.', 'wc-booking-calendar-nz' ) );
		}

		// General (global) rules.
		$general_rules = $this->get_general_rules();
		if ( ! $this->check_general_rules( $general_rules, $date, $time, $resource_id ) ) {
			return new WP_Error( 'rule_blocked', __( 'Booking is not allowed for the chosen date or time.', 'wc-booking-calendar-nz' ) );
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

		/**
		 * Filter the availability check result.
		 *
		 * @param array $result    Result data.
		 * @param int   $product_id Product ID.
		 */
		return apply_filters( 'wc_booking_calendar_check_availability', $result, $product_id );
	}

	/**
	 * Check availability with full per-person-type validation.
	 *
	 * @param int    $product_id   Product ID.
	 * @param string $date         Date.
	 * @param string $time         Time slot.
	 * @param array  $person_types Map of [type_id => count].
	 * @param int    $resource_id  Resource ID.
	 * @param string $mode         Booking mode.
	 * @return array|WP_Error
	 */
	public function check_availability_with_person_types( $product_id, $date, $time, array $person_types, $resource_id = 0, $mode = '' ) {
		$total_people = 0;
		foreach ( $person_types as $count ) {
			$total_people += max( 0, (int) $count );
		}

		$base = $this->check_availability( $product_id, $date, $time, $resource_id, $mode, $total_people );
		if ( is_wp_error( $base ) ) {
			return $base;
		}

		$base['person_types'] = $person_types;
		$base['total_people'] = $total_people;
		return $base;
	}

	/**
	 * Get available slots for a given date.
	 *
	 * @param int    $product_id  Product ID.
	 * @param string $date        Date (Y-m-d).
	 * @param int    $resource_id Resource ID (0 = any).
	 * @return array              List of available slot rows.
	 */
	public function get_available_slots( $product_id, $date, $resource_id = 0 ) {
		$available_slots = array();

		if ( ! $this->validate_date( $date ) || ! $this->is_day_available( $date ) ) {
			return $available_slots;
		}

		$slots = get_option( 'wc_booking_calendar_time_slots', array() );
		$modes = get_option( 'wc_booking_calendar_booking_modes', array() );

		foreach ( $slots as $slot ) {
			if ( empty( $slot['enabled'] ) ) {
				continue;
			}
			$time_string = $slot['start'] . '-' . $slot['end'];

			foreach ( $modes as $mode ) {
				$booked   = 0;
				$capacity = (int) $mode['max_per_slot'];

				if ( $resource_id > 0 && ! empty( $mode['full_day_block'] ) ) {
					if ( ! $this->check_resource_full_day( $resource_id, $date ) ) {
						continue;
					}
				} else {
					$booked = $this->get_booked_count( $product_id, $date, $time_string, $resource_id );
					if ( $capacity - $booked <= 0 ) {
						continue;
					}
				}

				$available_slots[] = array(
					'id'           => $slot['id'],
					'name'         => $slot['name'],
					'start'        => $slot['start'],
					'end'          => $slot['end'],
					'time'         => $time_string,
					'mode'         => $mode['name'],
					'mode_id'      => $mode['id'],
					'capacity'     => $capacity,
					'booked_count' => $booked,
					'available'    => max( 0, $capacity - $booked ),
				);
			}
		}

		return $available_slots;
	}

	/* ------------------------------------------------------------------
	 * Resource availability
	 * ------------------------------------------------------------------ */

	/**
	 * Check if a resource is free for the full day.
	 *
	 * @param int    $resource_id Resource ID.
	 * @param string $date        Date (Y-m-d).
	 * @return bool                True if free.
	 */
	public function check_resource_full_day( $resource_id, $date ) {
		global $wpdb;
		if ( ! $wpdb ) {
			return true;
		}

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->bookings_table}
				 WHERE resource_id = %d
				   AND booking_date = %s
				   AND status NOT IN ('cancelled','refunded','failed')",
				$resource_id,
				$date
			)
		);

		return 0 === $count;
	}

	/**
	 * Return resource schedule between two dates.
	 *
	 * @param int    $resource_id Resource ID.
	 * @param string $start_date  Start date (Y-m-d).
	 * @param string $end_date    End date (Y-m-d).
	 * @return array
	 */
	public function get_resource_schedule( $resource_id, $start_date, $end_date ) {
		global $wpdb;
		$schedule = array();
		if ( ! $wpdb ) {
			return $schedule;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT booking_date, booking_time, status
				 FROM {$this->bookings_table}
				 WHERE resource_id = %d
				   AND booking_date BETWEEN %s AND %s
				   AND status NOT IN ('cancelled','refunded','failed')
				 ORDER BY booking_date",
				$resource_id,
				$start_date,
				$end_date
			)
		);

		foreach ( (array) $rows as $row ) {
			$d = $row->booking_date;
			if ( ! isset( $schedule[ $d ] ) ) {
				$schedule[ $d ] = array(
					'date'   => $d,
					'booked' => false,
					'slots'  => array(),
				);
			}
			$schedule[ $d ]['booked']  = true;
			$schedule[ $d ]['slots'][] = $row->booking_time;
		}

		return $schedule;
	}

	/* ------------------------------------------------------------------
	 * Booking counts / persistence
	 * ------------------------------------------------------------------ */

	/**
	 * Get number of people already booked in a slot.
	 *
	 * @param int    $product_id  Product ID.
	 * @param string $date        Date.
	 * @param string $time        Time slot string.
	 * @param int    $resource_id Resource ID.
	 * @return int
	 */
	public function get_booked_count( $product_id, $date, $time, $resource_id = 0 ) {
		global $wpdb;
		if ( ! $wpdb ) {
			return 0;
		}

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(person_count),0)
				 FROM {$this->bookings_table}
				 WHERE product_id = %d
				   AND booking_date = %s
				   AND booking_time = %s
				   AND resource_id = %d
				   AND status IN ('pending','processing','confirmed','on-hold')",
				$product_id,
				$date,
				$time,
				$resource_id
			)
		);

		return $count;
	}

	/**
	 * Update availability table after a booking has been created.
	 *
	 * @param int   $booking_id   Booking post ID.
	 * @param array $booking_data Booking data with: product_id, booking_date,
	 *                            booking_time, booking_mode, resource_id,
	 *                            person_count.
	 * @return void
	 */
	public function update_availability( $booking_id, array $booking_data ) {
		global $wpdb;
		if ( ! $wpdb ) {
			return;
		}

		$product_id   = (int) ( $booking_data['product_id'] ?? 0 );
		$date         = (string) ( $booking_data['booking_date'] ?? '' );
		$time         = (string) ( $booking_data['booking_time'] ?? '' );
		$resource_id  = (int) ( $booking_data['resource_id'] ?? 0 );
		$person_count = (int) ( $booking_data['person_count'] ?? 1 );
		$mode_name    = (string) ( $booking_data['booking_mode'] ?? '' );

		if ( ! $product_id || ! $date || ! $time ) {
			return;
		}

		list( $slot_start, $slot_end ) = $this->split_time( $time );

		// Upsert the availability row.
		$existing_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->availability_table}
				 WHERE product_id = %d
				   AND availability_date = %s
				   AND slot_start = %s
				   AND slot_end = %s
				   AND resource_id = %d
				 LIMIT 1",
				$product_id,
				$date,
				$slot_start,
				$slot_end,
				$resource_id
			)
		);

		$mode_cfg = $this->get_mode_config( $mode_name );
		$capacity = $mode_cfg ? (int) $mode_cfg['max_per_slot'] : 0;

		if ( $existing_id > 0 ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$this->availability_table}
					 SET booked_count = booked_count + %d
					 WHERE id = %d",
					$person_count,
					$existing_id
				)
			);
		} else {
			$wpdb->insert(
				$this->availability_table,
				array(
					'product_id'        => $product_id,
					'resource_id'      => $resource_id,
					'availability_date' => $date,
					'day_of_week'      => (int) gmdate( 'N', strtotime( $date ) ),
					'slot_start'       => $slot_start,
					'slot_end'         => $slot_end,
					'capacity'         => $capacity,
					'booked_count'     => $person_count,
					'is_blocked'       => 0,
					'block_reason'     => '',
				),
				array( '%d', '%d', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%s' )
			);
		}

		// Block if capacity reached.
		if ( $capacity > 0 ) {
			$booked = $this->get_booked_count( $product_id, $date, $time, $resource_id );
			if ( $booked >= $capacity ) {
				$wpdb->update(
					$this->availability_table,
					array(
						'is_blocked'   => 1,
						'block_reason' => 'Capacity reached',
					),
					array(
						'product_id'        => $product_id,
						'availability_date' => $date,
						'slot_start'        => $slot_start,
						'slot_end'          => $slot_end,
						'resource_id'       => $resource_id,
					),
					array( '%d', '%s' ),
					array( '%d', '%s', '%s', '%s', '%d' )
				);
			}
		}

		$this->clear_availability_cache( $product_id );
		do_action( 'wc_booking_calendar_availability_updated', $booking_id, $booking_data );
	}

	/**
	 * Release a booking's reserved capacity (e.g. on cancellation).
	 *
	 * @param int $booking_id Booking post ID (or row id in custom table).
	 * @return void
	 */
	public function release_availability( $booking_id ) {
		global $wpdb;
		if ( ! $wpdb ) {
			return;
		}

		// Fetch from custom table if it is a row id, else from postmeta.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->bookings_table} WHERE booking_post_id = %d OR id = %d LIMIT 1",
				$booking_id,
				$booking_id
			)
		);

		if ( ! $row ) {
			// Try postmeta fallback.
			$product_id   = (int) get_post_meta( $booking_id, '_booking_product_id', true );
			$date         = (string) get_post_meta( $booking_id, '_booking_date', true );
			$time         = (string) get_post_meta( $booking_id, '_booking_time', true );
			$resource_id  = (int) get_post_meta( $booking_id, '_booking_resource_id', true );
			$person_count = (int) get_post_meta( $booking_id, '_booking_person_count', true );
			if ( ! $product_id || ! $date || ! $time ) {
				return;
			}
		} else {
			$product_id   = (int) $row->product_id;
			$date         = (string) $row->booking_date;
			$time         = (string) $row->booking_time;
			$resource_id  = (int) $row->resource_id;
			$person_count = (int) $row->person_count;
		}

		list( $slot_start, $slot_end ) = $this->split_time( $time );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->availability_table}
				 SET booked_count = GREATEST(0, booked_count - %d)
				 WHERE product_id = %d
				   AND availability_date = %s
				   AND slot_start = %s
				   AND slot_end = %s
				   AND resource_id = %d",
				$person_count,
				$product_id,
				$date,
				$slot_start,
				$slot_end,
				$resource_id
			)
		);

		// Unblock if there is now capacity.
		$wpdb->update(
			$this->availability_table,
			array(
				'is_blocked'   => 0,
				'block_reason' => '',
			),
			array(
				'product_id'        => $product_id,
				'availability_date' => $date,
				'slot_start'        => $slot_start,
				'slot_end'          => $slot_end,
				'resource_id'       => $resource_id,
			),
			array( '%d', '%s' ),
			array( '%d', '%s', '%s', '%s', '%d' )
		);

		$this->clear_availability_cache( $product_id );
		do_action( 'wc_booking_calendar_availability_released', $booking_id );
	}

	/* ------------------------------------------------------------------
	 * Rule / configuration accessors
	 * ------------------------------------------------------------------ */

	/**
	 * Get mode configuration by name.
	 *
	 * @param string $mode Mode name.
	 * @return array|false
	 */
	public function get_mode_config( $mode ) {
		$modes = get_option( 'wc_booking_calendar_booking_modes', array() );
		foreach ( $modes as $m ) {
			if ( isset( $m['name'] ) && $m['name'] === $mode ) {
				return $m;
			}
		}
		return false;
	}

	/**
	 * Get the first defined mode (default).
	 *
	 * @return array|false
	 */
	public function get_default_mode() {
		$modes = get_option( 'wc_booking_calendar_booking_modes', array() );
		return isset( $modes[0] ) ? $modes[0] : false;
	}

	/**
	 * Get product-specific rules.
	 *
	 * @param int $product_id Product ID.
	 * @return array|false
	 */
	public function get_product_rules( $product_id ) {
		$rules = get_post_meta( $product_id, '_wc_booking_calendar_rules', true );
		if ( empty( $rules ) ) {
			return false;
		}
		if ( is_string( $rules ) ) {
			$decoded = json_decode( $rules, true );
			return is_array( $decoded ) ? $decoded : false;
		}
		return is_array( $rules ) ? $rules : false;
	}

	/**
	 * Check product-specific rules.
	 *
	 * @param array  $rules       Rules.
	 * @param string $date        Date.
	 * @param string $time        Time slot.
	 * @param int    $resource_id Resource ID.
	 * @return bool
	 */
	public function check_product_rules( array $rules, $date, $time, $resource_id = 0 ) {
		if ( ! empty( $rules['blocked_dates'] ) && in_array( $date, (array) $rules['blocked_dates'], true ) ) {
			return false;
		}

		if ( ! empty( $rules['blocked_days'] ) ) {
			$day_name = gmdate( 'l', strtotime( $date ) );
			if ( in_array( $day_name, (array) $rules['blocked_days'], true ) ) {
				return false;
			}
		}

		if ( ! empty( $rules['allowed_resources'] ) && $resource_id > 0 ) {
			$allowed = array_map( 'intval', (array) $rules['allowed_resources'] );
			if ( ! in_array( $resource_id, $allowed, true ) ) {
				return false;
			}
		}

		if ( ! empty( $rules['booking_start_date'] ) && strtotime( $date ) < strtotime( $rules['booking_start_date'] ) ) {
			return false;
		}
		if ( ! empty( $rules['booking_end_date'] ) && strtotime( $date ) > strtotime( $rules['booking_end_date'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get general (global) availability rules.
	 *
	 * @return array
	 */
	public function get_general_rules() {
		$advanced = get_option( 'wc_booking_calendar_advanced', array() );

		$defaults = array(
			'lead_time'           => (int) get_option( 'wc_booking_calendar_lead_time_hours', 24 ),
			'advance_booking_days' => (int) get_option( 'wc_booking_calendar_advance_days', 365 ),
			'blackout_dates'      => array(),
			'peak_days'           => array(),
			'peak_multiplier'     => 1.0,
		);

		$advanced = wp_parse_args( (array) $advanced, array() );

		return array(
			'lead_time'           => $defaults['lead_time'],
			'advance_booking_days' => $defaults['advance_booking_days'],
			'blackout_dates'      => isset( $advanced['blackout_dates'] ) ? (array) $advanced['blackout_dates'] : $defaults['blackout_dates'],
			'peak_days'           => isset( $advanced['peak_days'] ) ? (array) $advanced['peak_days'] : $defaults['peak_days'],
			'peak_multiplier'     => isset( $advanced['peak_multiplier'] ) ? (float) $advanced['peak_multiplier'] : $defaults['peak_multiplier'],
		);
	}

	/**
	 * Check general rules.
	 *
	 * @param array  $rules        Rules array.
	 * @param string $date         Date.
	 * @param string $time         Time slot (HH:MM-HH:MM).
	 * @param int    $resource_id  Resource ID (unused, reserved).
	 * @return bool
	 */
	public function check_general_rules( array $rules, $date, $time, $resource_id = 0 ) {
		unset( $resource_id );

		// Lead time.
		if ( ! empty( $rules['lead_time'] ) ) {
			list( $slot_start ) = $this->split_time( $time );
			$slot_ts = strtotime( $date . ' ' . $slot_start );
			$now     = function_exists( 'current_time' ) ? current_time( 'timestamp' ) : time();
			if ( false !== $slot_ts && ( ( $slot_ts - $now ) / HOUR_IN_SECONDS ) < (int) $rules['lead_time'] ) {
				return false;
			}
		}

		// Advance booking window.
		if ( ! empty( $rules['advance_booking_days'] ) ) {
			$max_ts = strtotime( '+' . (int) $rules['advance_booking_days'] . ' days' );
			if ( strtotime( $date ) > $max_ts ) {
				return false;
			}
		}

		// Blackout dates.
		if ( ! empty( $rules['blackout_dates'] ) && in_array( $date, (array) $rules['blackout_dates'], true ) ) {
			return false;
		}

		// Peak day marker (does not block, but flag set on instance).
		if ( ! empty( $rules['peak_days'] ) ) {
			$this->is_peak_day = in_array( gmdate( 'l', strtotime( $date ) ), (array) $rules['peak_days'], true );
		}

		return true;
	}

	/* ------------------------------------------------------------------
	 * Validation helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Validate date string (Y-m-d).
	 *
	 * @param mixed $date Date.
	 * @return bool
	 */
	public function validate_date( $date ) {
		if ( ! is_string( $date ) || '' === $date ) {
			return false;
		}
		$dt = DateTime::createFromFormat( 'Y-m-d', $date );
		return $dt && $dt->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Validate time slot string (HH:MM-HH:MM).
	 *
	 * @param mixed $time Time string.
	 * @return bool
	 */
	public function validate_time( $time ) {
		if ( ! is_string( $time ) || '' === $time ) {
			return false;
		}
		if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d-([01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
			return false;
		}
		list( $start, $end ) = explode( '-', $time );
		return strtotime( '1970-01-01 ' . $start ) < strtotime( '1970-01-01 ' . $end );
	}

	/**
	 * Split a "HH:MM-HH:MM" time string into [start, end].
	 *
	 * @param string $time Time string.
	 * @return array{0:string,1:string}
	 */
	public function split_time( $time ) {
		$parts = explode( '-', (string) $time );
		if ( 2 !== count( $parts ) ) {
			return array( '', '' );
		}
		return array( trim( $parts[0] ), trim( $parts[1] ) );
	}

	/**
	 * Whether a date's day-of-week is enabled.
	 *
	 * @param string $date Date (Y-m-d).
	 * @return bool
	 */
	public function is_day_available( $date ) {
		$days     = get_option( 'wc_booking_calendar_days_of_week', array() );
		$day_name = strtolower( gmdate( 'l', strtotime( $date ) ) );
		return ! empty( $days[ $day_name ] );
	}

	/**
	 * Find a configured slot by its "HH:MM-HH:MM" string.
	 *
	 * @param string $time Time string.
	 * @return array|false
	 */
	public function get_slot_by_time( $time ) {
		list( $start, $end ) = $this->split_time( $time );
		if ( '' === $start || '' === $end ) {
			return false;
		}
		$slots = get_option( 'wc_booking_calendar_time_slots', array() );
		foreach ( $slots as $slot ) {
			if ( isset( $slot['start'], $slot['end'] ) && $slot['start'] === $start && $slot['end'] === $end ) {
				return $slot;
			}
		}
		return false;
	}

	/* ------------------------------------------------------------------
	 * Stats / reporting
	 * ------------------------------------------------------------------ */

	/**
	 * Aggregate booking stats for a date range.
	 *
	 * @param string $start_date  Start (Y-m-d).
	 * @param string $end_date    End (Y-m-d).
	 * @param int    $product_id  Optional product filter.
	 * @return array
	 */
	public function get_booking_stats( $start_date, $end_date, $product_id = 0 ) {
		global $wpdb;
		if ( ! $wpdb ) {
			return array();
		}

		if ( $product_id > 0 ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT booking_date,
						COUNT(*) AS total_bookings,
						COALESCE(SUM(person_count),0) AS total_people,
						COALESCE(SUM(total_price),0) AS total_revenue
					 FROM {$this->bookings_table}
					 WHERE status IN ('confirmed','processing','completed')
					   AND booking_date BETWEEN %s AND %s
					   AND product_id = %d
					 GROUP BY booking_date
					 ORDER BY booking_date",
					$start_date,
					$end_date,
					$product_id
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT booking_date,
						COUNT(*) AS total_bookings,
						COALESCE(SUM(person_count),0) AS total_people,
						COALESCE(SUM(total_price),0) AS total_revenue
					 FROM {$this->bookings_table}
					 WHERE status IN ('confirmed','processing','completed')
					   AND booking_date BETWEEN %s AND %s
					 GROUP BY booking_date
					 ORDER BY booking_date",
					$start_date,
					$end_date
				),
				ARRAY_A
			);
		}

		return is_array( $rows ) ? $rows : array();
	}

	/* ------------------------------------------------------------------
	 * Caching
	 * ------------------------------------------------------------------ */

	/**
	 * Cache key for availability check.
	 *
	 * @param int    $product_id  Product.
	 * @param string $date        Date.
	 * @param string $time        Time slot.
	 * @param int    $resource_id Resource.
	 * @return string
	 */
	private function availability_cache_key( $product_id, $date, $time, $resource_id ) {
		return 'wc_booking_calendar_avail_' . md5( $product_id . '|' . $date . '|' . $time . '|' . $resource_id );
	}

	/**
	 * Cached version of check_availability().
	 *
	 * @param int    $product_id  Product.
	 * @param string $date        Date.
	 * @param string $time        Time slot.
	 * @param int    $resource_id Resource.
	 * @param string $mode        Mode.
	 * @param int    $person_count People.
	 * @return array|WP_Error
	 */
	public function check_availability_cached( $product_id, $date, $time, $resource_id = 0, $mode = '', $person_count = 1 ) {
		$key    = $this->availability_cache_key( $product_id, $date, $time, $resource_id );
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return $cached;
		}

		$result = $this->check_availability( $product_id, $date, $time, $resource_id, $mode, $person_count );
		set_transient( $key, $result, 5 * MINUTE_IN_SECONDS );
		return $result;
	}

	/**
	 * Clear availability cache.
	 *
	 * @param int $product_id Product ID (0 = all).
	 * @return void
	 */
	public function clear_availability_cache( $product_id = 0 ) {
		global $wpdb;
		if ( ! $wpdb ) {
			return;
		}

		if ( $product_id > 0 ) {
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wc_booking_calendar_avail_%' OR option_name LIKE '_transient_timeout_wc_booking_calendar_avail_%'" );
		} else {
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wc_booking_calendar_avail_%' OR option_name LIKE '_transient_timeout_wc_booking_calendar_avail_%'" );
		}
	}

	/* ------------------------------------------------------------------
	 * Export / import
	 * ------------------------------------------------------------------ */

	/**
	 * Export rules and settings.
	 *
	 * @return array
	 */
	public function export_rules() {
		return array(
			'version'      => WC_BOOKING_CALENDAR_VERSION,
			'time_slots'   => get_option( 'wc_booking_calendar_time_slots', array() ),
			'days_of_week' => get_option( 'wc_booking_calendar_days_of_week', array() ),
			'booking_modes' => get_option( 'wc_booking_calendar_booking_modes', array() ),
			'person_types' => get_option( 'wc_booking_calendar_person_types', array() ),
			'advanced'     => get_option( 'wc_booking_calendar_advanced', array() ),
			'notifications' => get_option( 'wc_booking_calendar_notifications', array() ),
			'gst_inclusive' => get_option( 'wc_booking_calendar_gst_inclusive', 'yes' ),
			'min_group_size' => get_option( 'wc_booking_calendar_min_group_size', 1 ),
			'max_group_size' => get_option( 'wc_booking_calendar_max_group_size', 50 ),
			'lead_time_hours' => get_option( 'wc_booking_calendar_lead_time_hours', 24 ),
			'advance_days'  => get_option( 'wc_booking_calendar_advance_days', 365 ),
			'timezone'      => get_option( 'wc_booking_calendar_timezone', 'Pacific/Auckland' ),
		);
	}

	/**
	 * Import rules and settings.
	 *
	 * @param array $data Data array.
	 * @return bool True on success.
	 */
	public function import_rules( $data ) {
		if ( ! is_array( $data ) ) {
			return false;
		}
		$map = array(
			'time_slots'      => 'wc_booking_calendar_time_slots',
			'days_of_week'    => 'wc_booking_calendar_days_of_week',
			'booking_modes'   => 'wc_booking_calendar_booking_modes',
			'person_types'    => 'wc_booking_calendar_person_types',
			'advanced'        => 'wc_booking_calendar_advanced',
			'notifications'   => 'wc_booking_calendar_notifications',
			'gst_inclusive'   => 'wc_booking_calendar_gst_inclusive',
			'min_group_size'  => 'wc_booking_calendar_min_group_size',
			'max_group_size'  => 'wc_booking_calendar_max_group_size',
			'lead_time_hours' => 'wc_booking_calendar_lead_time_hours',
			'advance_days'    => 'wc_booking_calendar_advance_days',
			'timezone'        => 'wc_booking_calendar_timezone',
		);
		foreach ( $map as $key => $option ) {
			if ( isset( $data[ $key ] ) ) {
				update_option( $option, $data[ $key ] );
			}
		}
		$this->clear_availability_cache();
		return true;
	}
}