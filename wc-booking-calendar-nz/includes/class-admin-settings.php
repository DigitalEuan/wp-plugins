<?php
/**
 * WC Booking Calendar - Admin Settings.
 *
 * Registers the Settings submenu, renders the tabbed settings page,
 * and centralises all sanitisation/validation for stored options.
 *
 * @package WC_Booking_Calendar_NZ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Booking_Calendar_Admin_Settings
 */
class WC_Booking_Calendar_Admin_Settings {

	const PAGE_SLUG    = 'wc-booking-calendar-settings';
	const OPTION_GROUP = 'wc_booking_calendar_settings';

	/**
	 * Singleton.
	 *
	 * @var WC_Booking_Calendar_Admin_Settings|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return WC_Booking_Calendar_Admin_Settings
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
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_wc_booking_calendar_save_settings', array( $this, 'save_settings' ) );
	}

	/**
	 * Register the Settings submenu under the Bookings CPT.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_submenu_page(
			'edit.php?post_type=wc_booking',
			__( 'Settings', 'wc-booking-calendar-nz' ),
			__( 'Settings', 'wc-booking-calendar-nz' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings and sanitisation callbacks.
	 *
	 * @return void
	 */
	public function register_settings() {
		foreach ( $this->get_option_sanitizers() as $key => $callback ) {
			register_setting(
				self::OPTION_GROUP,
				$key,
				array( 'sanitize_callback' => $callback )
			);
		}
	}

	/* ------------------------------------------------------------------
	 * Renderer
	 * ------------------------------------------------------------------ */

	/**
	 * Tab definitions.
	 *
	 * @return array<string,array{label:string,template:string}>
	 */
	protected function get_tabs() {
		return array(
			'general'       => array(
				'label'    => __( 'General', 'wc-booking-calendar-nz' ),
				'template' => 'settings-general.php',
			),
			'time-slots'    => array(
				'label'    => __( 'Time Slots', 'wc-booking-calendar-nz' ),
				'template' => 'settings-time-slots.php',
			),
			'booking-modes' => array(
				'label'    => __( 'Booking Modes', 'wc-booking-calendar-nz' ),
				'template' => 'settings-booking-modes.php',
			),
			'person-types'  => array(
				'label'    => __( 'Person Types', 'wc-booking-calendar-nz' ),
				'template' => 'settings-person-types.php',
			),
			'notifications' => array(
				'label'    => __( 'Notifications', 'wc-booking-calendar-nz' ),
				'template' => 'settings-notifications.php',
			),
			'advanced'      => array(
				'label'    => __( 'Advanced', 'wc-booking-calendar-nz' ),
				'template' => 'settings-advanced.php',
			),
		);
	}

	/**
	 * Render the settings page (tabs + active template).
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wc-booking-calendar-nz' ) );
		}

		$tabs       = $this->get_tabs();
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $tabs[ $active_tab ] ) ) {
			$active_tab = 'general';
		}

		// Vars used by templates.
		$notifications = (array) get_option( 'wc_booking_calendar_notifications', array() );
		unset( $notifications ); // (assigned again inside the included template; this just silences IDE warnings)

		?>
		<div class="wrap wc-booking-calendar-settings">
			<h1><?php esc_html_e( 'Booking Settings', 'wc-booking-calendar-nz' ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $tab ) : ?>
					<a class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>"
					   href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'wc_booking', 'page' => self::PAGE_SLUG, 'tab' => $slug ), admin_url( 'edit.php' ) ) ); ?>">
						<?php echo esc_html( $tab['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php
				wp_nonce_field( 'wc_booking_calendar_save_settings', 'wc_booking_calendar_settings_nonce' );
				?>
				<input type="hidden" name="action" value="wc_booking_calendar_save_settings" />
				<input type="hidden" name="tab" value="<?php echo esc_attr( $active_tab ); ?>" />
				<?php
				$notifications = (array) get_option( 'wc_booking_calendar_notifications', array() );

				$template = WC_BOOKING_CALENDAR_PLUGIN_DIR . 'admin/templates/' . $tabs[ $active_tab ]['template'];
				if ( file_exists( $template ) ) {
					include $template;
				} else {
					echo '<p>' . esc_html__( 'Template missing.', 'wc-booking-calendar-nz' ) . '</p>';
				}

				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Return the sanitiser map used both by register_setting() and custom saves.
	 *
	 * @return array<string,callable|string>
	 */
	protected function get_option_sanitizers() {
		return array(
			'wc_booking_calendar_time_slots'        => array( $this, 'sanitize_time_slots' ),
			'wc_booking_calendar_days_of_week'      => array( $this, 'sanitize_days_of_week' ),
			'wc_booking_calendar_booking_modes'     => array( $this, 'sanitize_booking_modes' ),
			'wc_booking_calendar_addons'            => array( $this, 'sanitize_addons' ),
			'wc_booking_calendar_person_types'      => array( $this, 'sanitize_person_types' ),
			'wc_booking_calendar_notifications'     => array( $this, 'sanitize_notifications' ),
			'wc_booking_calendar_advanced'          => array( $this, 'sanitize_advanced' ),
			'wc_booking_calendar_gst_inclusive'     => 'sanitize_text_field',
			'wc_booking_calendar_min_group_size'    => 'absint',
			'wc_booking_calendar_max_group_size'    => 'absint',
			'wc_booking_calendar_lead_time'         => 'absint',
			'wc_booking_calendar_advance_window'    => 'absint',
			'wc_booking_calendar_advance_days'      => 'absint',
			'wc_booking_calendar_lead_time_hours'   => 'absint',
			'wc_booking_calendar_deposit_percentage' => 'absint',
			'wc_booking_calendar_timezone'          => 'sanitize_text_field',
			'wc_booking_calendar_blackout_dates'    => array( $this, 'sanitize_blackout_dates' ),
			'wc_booking_calendar_morning_tea_price' => array( $this, 'sanitize_decimal' ),
		);
	}

	/**
	 * Group settings by admin tab so saving one tab doesn't wipe the others.
	 *
	 * @return array<string,string[]>
	 */
	protected function get_tab_option_map() {
		return array(
			'general'       => array(
				'wc_booking_calendar_lead_time',
				'wc_booking_calendar_advance_window',
				'wc_booking_calendar_advance_days',
				'wc_booking_calendar_lead_time_hours',
				'wc_booking_calendar_deposit_percentage',
				'wc_booking_calendar_addons',
				'wc_booking_calendar_timezone',
				'wc_booking_calendar_min_group_size',
				'wc_booking_calendar_max_group_size',
			),
			'time-slots'    => array(
				'wc_booking_calendar_days_of_week',
				'wc_booking_calendar_time_slots',
			),
			'booking-modes' => array(
				'wc_booking_calendar_booking_modes',
			),
			'person-types'  => array(
				'wc_booking_calendar_person_types',
			),
			'notifications' => array(
				'wc_booking_calendar_notifications',
			),
			'advanced'      => array(
				'wc_booking_calendar_advanced',
				'wc_booking_calendar_blackout_dates',
			),
		);
	}

	/**
	 * Save only the active tab's settings.
	 *
	 * Avoids WordPress options.php behaviour that submits all registered
	 * settings in the group and clears missing keys from other tabs.
	 *
	 * @return void
	 */
	public function save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wc-booking-calendar-nz' ) );
		}

		check_admin_referer( 'wc_booking_calendar_save_settings', 'wc_booking_calendar_settings_nonce' );

		$tabs       = $this->get_tabs();
		$active_tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'general';
		if ( ! isset( $tabs[ $active_tab ] ) ) {
			$active_tab = 'general';
		}

		$tab_option_map = $this->get_tab_option_map();
		$option_keys    = $tab_option_map[ $active_tab ] ?? array();
		$sanitizers     = $this->get_option_sanitizers();
		$reset_on_missing = array(
			'wc_booking_calendar_days_of_week',
			'wc_booking_calendar_notifications',
			'wc_booking_calendar_advanced',
			'wc_booking_calendar_blackout_dates',
		);

		foreach ( $option_keys as $option_key ) {
			$has_posted_value = array_key_exists( $option_key, $_POST );
			if ( ! $has_posted_value && ! in_array( $option_key, $reset_on_missing, true ) ) {
				continue;
			}

			$raw_value = $has_posted_value ? wp_unslash( $_POST[ $option_key ] ) : array();
			if ( isset( $sanitizers[ $option_key ] ) && is_callable( $sanitizers[ $option_key ] ) ) {
				$clean_value = call_user_func( $sanitizers[ $option_key ], $raw_value );
			} elseif ( isset( $sanitizers[ $option_key ] ) && is_string( $sanitizers[ $option_key ] ) && function_exists( $sanitizers[ $option_key ] ) ) {
				$clean_value = call_user_func( $sanitizers[ $option_key ], $raw_value );
			} else {
				$clean_value = $raw_value;
			}

			update_option( $option_key, $clean_value );
		}

		if ( 'advanced' === $active_tab ) {
			$blackout = (array) get_option( 'wc_booking_calendar_blackout_dates', array() );
			$advanced = (array) get_option( 'wc_booking_calendar_advanced', array() );
			$advanced['blackout_dates'] = $blackout;
			if ( isset( $advanced['seasonal_pricing'] ) ) {
				unset( $advanced['seasonal_pricing'] );
			}
			update_option( 'wc_booking_calendar_advanced', $advanced );
		}

		$redirect_url = add_query_arg(
			array(
				'post_type'         => 'wc_booking',
				'page'              => self::PAGE_SLUG,
				'tab'               => $active_tab,
				'settings-updated'  => 'true',
			),
			admin_url( 'edit.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Sanitisers
	 * ------------------------------------------------------------------ */

	/**
	 * Sanitise the time slots option.
	 *
	 * Accepts either a JSON string (from the JSON textarea) or
	 * mirrored arrays from the visual editor.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize_time_slots( $input ) {
		// Visual editor mirror (slot_name[], slot_start[], slot_end[], slot_enabled[]).
		if ( is_array( $input ) && empty( $input['__json'] ) ) {
			// Could already be the parallel arrays from $_POST, or a structured array.
			if ( isset( $input[0] ) && is_array( $input[0] ) ) {
				return $this->normalize_time_slots( $input );
			}
		}

		if ( is_string( $input ) ) {
			$decoded = json_decode( $input, true );
			if ( is_array( $decoded ) ) {
				return $this->normalize_time_slots( $decoded );
			}
		}

		// Fall back to whatever we already had.
		return (array) get_option( 'wc_booking_calendar_time_slots', array() );
	}

	/**
	 * Normalise time slot rows.
	 *
	 * @param array $slots Slots.
	 * @return array
	 */
	protected function normalize_time_slots( $slots ) {
		$out = array();
		$i   = 1;
		foreach ( $slots as $slot ) {
			if ( ! is_array( $slot ) ) {
				continue;
			}
			$start = isset( $slot['start'] ) ? sanitize_text_field( $slot['start'] ) : '';
			$end   = isset( $slot['end'] ) ? sanitize_text_field( $slot['end'] ) : '';
			if ( '' === $start || '' === $end ) {
				continue;
			}
			$out[] = array(
				'id'      => isset( $slot['id'] ) ? (int) $slot['id'] : $i,
				'name'    => isset( $slot['name'] ) ? sanitize_text_field( $slot['name'] ) : sprintf( 'Slot %d', $i ),
				'start'   => $start,
				'end'     => $end,
				'enabled' => ! empty( $slot['enabled'] ) ? 1 : 0,
			);
			$i++;
		}
		return $out;
	}

	/**
	 * Sanitise days-of-week toggles.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize_days_of_week( $input ) {
		$days  = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
		$out   = array();
		$input = is_array( $input ) ? $input : array();
		foreach ( $days as $d ) {
			$out[ $d ] = ! empty( $input[ $d ] ) ? 1 : 0;
		}
		return $out;
	}

	/**
	 * Sanitise booking modes.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize_booking_modes( $input ) {
		if ( is_string( $input ) ) {
			$decoded = json_decode( $input, true );
			if ( is_array( $decoded ) ) {
				$input = $decoded;
			} else {
				return (array) get_option( 'wc_booking_calendar_booking_modes', array() );
			}
		}
		if ( ! is_array( $input ) ) {
			return (array) get_option( 'wc_booking_calendar_booking_modes', array() );
		}

		$out = array();
		$i   = 1;
		foreach ( $input as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}
			$out[] = array(
				'id'             => isset( $row['id'] ) ? (int) $row['id'] : $i,
				'key'            => isset( $row['key'] ) && '' !== $row['key'] ? $this->normalize_machine_key( $row['key'], $name, 'mode' ) : $this->normalize_mode_key_from_name( $name, $i ),
				'name'           => $name,
				'description'    => isset( $row['description'] ) ? sanitize_textarea_field( $row['description'] ) : '',
				'full_day_block' => ! empty( $row['full_day_block'] ) ? 1 : 0,
				'show_addons'    => ! empty( $row['show_addons'] ) ? 1 : 0,
				'max_per_slot'   => isset( $row['max_per_slot'] ) ? max( 0, (int) $row['max_per_slot'] ) : 0,
			);
			$i++;
		}
		return $out;
	}

	/**
	 * Sanitise person types (Adult / Child / etc.).
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize_person_types( $input ) {
		if ( is_string( $input ) ) {
			$decoded = json_decode( $input, true );
			if ( is_array( $decoded ) ) {
				$input = $decoded;
			} else {
				return (array) get_option( 'wc_booking_calendar_person_types', array() );
			}
		}
		if ( ! is_array( $input ) ) {
			return (array) get_option( 'wc_booking_calendar_person_types', array() );
		}

		$out = array();
		$i   = 1;
		foreach ( $input as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}
			$out[] = array(
				'id'      => isset( $row['id'] ) ? (int) $row['id'] : $i,
				'name'    => $name,
				'age_min' => isset( $row['age_min'] ) ? max( 0, (int) $row['age_min'] ) : 0,
				'age_max' => isset( $row['age_max'] ) ? max( 0, (int) $row['age_max'] ) : 0,
				'price'   => isset( $row['price'] ) ? (float) $row['price'] : 0.0,
			);
			$i++;
		}
		return $out;
	}

	/**
	 * Sanitise notifications block.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize_notifications( $input ) {
		$input = is_array( $input ) ? $input : array();
		return array(
			'confirmation'        => ! empty( $input['confirmation'] ) ? 1 : 0,
			'reminder'            => ! empty( $input['reminder'] ) ? 1 : 0,
			'cancellation'        => ! empty( $input['cancellation'] ) ? 1 : 0,
			'special_requests'    => ! empty( $input['special_requests'] ) ? 1 : 0,
			'email_from_name'     => isset( $input['email_from_name'] ) ? sanitize_text_field( $input['email_from_name'] ) : '',
			'email_from_address'  => isset( $input['email_from_address'] ) ? sanitize_email( $input['email_from_address'] ) : '',
		);
	}

	/**
	 * Sanitise advanced settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize_advanced( $input ) {
		$input            = is_array( $input ) ? $input : array();
		$peak_days_in     = isset( $input['peak_days'] ) ? (array) $input['peak_days'] : array();
		$peak_days_clean  = array();
		$valid_days       = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );

		// Accept either ['saturday'=>1,'sunday'=>1] or list of capitalised day names.
		foreach ( $peak_days_in as $key => $val ) {
			if ( is_string( $key ) && in_array( strtolower( $key ), $valid_days, true ) && ! empty( $val ) ) {
				$peak_days_clean[] = ucfirst( strtolower( $key ) );
			} elseif ( is_string( $val ) && in_array( strtolower( $val ), $valid_days, true ) ) {
				$peak_days_clean[] = ucfirst( strtolower( $val ) );
			}
		}

		$blackout = array();
		if ( ! empty( $input['blackout_dates'] ) ) {
			$raw_dates = is_string( $input['blackout_dates'] )
				? preg_split( "/[\r\n,]+/", $input['blackout_dates'] )
				: (array) $input['blackout_dates'];
			foreach ( $raw_dates as $date ) {
				$date = trim( (string) $date );
				if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
					$blackout[] = $date;
				}
			}
		}

		return array(
			'peak_days'       => array_values( array_unique( $peak_days_clean ) ),
			'peak_multiplier' => isset( $input['peak_multiplier'] ) ? (float) $input['peak_multiplier'] : 1.0,
			'blackout_dates'  => $blackout,
		);
	}

	/**
	 * Sanitise the textarea blackout-dates option (Y-m-d per line).
	 *
	 * @param mixed $input Raw.
	 * @return array
	 */
	public function sanitize_blackout_dates( $input ) {
		$out = array();
		if ( is_array( $input ) ) {
			foreach ( $input as $d ) {
				$d = trim( (string) $d );
				if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
					$out[] = $d;
				}
			}
			return $out;
		}
		if ( is_string( $input ) ) {
			foreach ( preg_split( "/[\r\n,]+/", $input ) as $d ) {
				$d = trim( $d );
				if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
					$out[] = $d;
				}
			}
		}
		return $out;
	}

	/**
	 * Decimal sanitiser.
	 *
	 * @param mixed $input Raw input.
	 * @return float
	 */
	public function sanitize_decimal( $input ) {
		return (float) wc_format_decimal( $input );
	}

	/**
	 * Sanitise booking add-ons.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize_addons( $input ) {
		if ( is_string( $input ) ) {
			$decoded = json_decode( $input, true );
			if ( is_array( $decoded ) ) {
				$input = $decoded;
			} else {
				return (array) get_option( 'wc_booking_calendar_addons', array() );
			}
		}
		if ( ! is_array( $input ) ) {
			return (array) get_option( 'wc_booking_calendar_addons', array() );
		}

		$out = array();
		$i   = 1;
		foreach ( $input as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}

			$out[] = array(
				'id'         => isset( $row['id'] ) ? (int) $row['id'] : $i,
				'key'        => isset( $row['key'] ) && '' !== $row['key'] ? $this->normalize_machine_key( $row['key'], $label, 'addon' ) : $this->normalize_machine_key( '', $label, 'addon_' . $i ),
				'label'      => $label,
				'price'      => isset( $row['price'] ) ? (float) wc_format_decimal( $row['price'] ) : 0.0,
				'per_person' => ! empty( $row['per_person'] ) ? 1 : 0,
				'enabled'    => ! empty( $row['enabled'] ) ? 1 : 0,
			);
			$i++;
		}

		return $out;
	}

	/**
	 * Normalise saved machine keys.
	 *
	 * @param string $raw      Raw key.
	 * @param string $fallback Fallback label.
	 * @param string $prefix   Prefix if key becomes empty.
	 * @return string
	 */
	protected function normalize_machine_key( $raw, $fallback = '', $prefix = 'item' ) {
		$key = sanitize_title( (string) $raw );
		if ( '' === $key && '' !== $fallback ) {
			$key = sanitize_title( (string) $fallback );
		}
		if ( '' === $key ) {
			$key = sanitize_title( (string) $prefix );
		}
		return $key;
	}

	/**
	 * Generate a sensible booking-mode key from a human label.
	 *
	 * @param string $name  Mode name.
	 * @param int    $index Row index.
	 * @return string
	 */
	protected function normalize_mode_key_from_name( $name, $index ) {
		$name_lc = strtolower( (string) $name );
		if ( false !== strpos( $name_lc, 'guided' ) ) {
			return 'guided';
		}
		if ( false !== strpos( $name_lc, 'self' ) || false !== strpos( $name_lc, 'walk' ) ) {
			return 'self';
		}
		return $this->normalize_machine_key( '', $name, 'mode_' . $index );
	}
}
