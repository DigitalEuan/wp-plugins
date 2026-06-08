/**
 * WC Booking Calendar - Admin Settings.
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

	const PAGE_SLUG = 'wc-booking-calendar-settings';

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
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_wc_booking_calendar_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_wc_booking_calendar_import', array( $this, 'handle_import' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Add the menu page.
	 *
	 * @return void
	 */
	public function add_menu_page() {
		add_submenu_page(
			'edit.php?post_type=wc_booking',
			__( 'Booking Calendar Settings', 'wc-booking-calendar-nz' ),
			__( 'Settings', 'wc-booking-calendar-nz' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin CSS.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'wc-booking-calendar-admin',
			WC_BOOKING_CALENDAR_PLUGIN_URL . 'admin/assets/css/admin.css',
			array(),
			WC_BOOKING_CALENDAR_VERSION
		);
	}

	/**
	 * Register settings & sanitizers.
	 *
	 * @return void
	 */
	public function register_settings() {
		$options = array(
			'wc_booking_calendar_time_slots'      => array( $this, 'sanitize_time_slots' ),
			'wc_booking_calendar_days_of_week'    => array( $this, 'sanitize_days_of_week' ),
			'wc_booking_calendar_booking_modes'   => array( $this, 'sanitize_booking_modes' ),
			'wc_booking_calendar_person_types'    => array( $this, 'sanitize_person_types' ),
			'wc_booking_calendar_notifications'   => array( $this, 'sanitize_notifications' ),
			'wc_booking_calendar_advanced'        => array( $this, 'sanitize_advanced' ),
			'wc_booking_calendar_gst_inclusive'   => 'sanitize_text_field',
			'wc_booking_calendar_min_group_size'  => 'absint',
			'wc_booking_calendar_max_group_size'  => 'absint',
			'wc_booking_calendar_lead_time_hours' => 'absint',
			'wc_booking_calendar_advance_days'    => 'absint',
			'wc_booking_calendar_timezone'        => 'sanitize_text_field',
		);
		foreach ( $options as $key => $callback ) {
			register_setting( 'wc_booking_calendar_settings', $key, array( 'sanitize_callback' => $callback ) );
		}
	}

	/**
	 * Render settings page with tabs.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wc-booking-calendar-nz' ) );
		}

		$tab    = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs   = array(
			'general'       => __( 'General', 'wc-booking-calendar-nz' ),
			'time-slots'    => __( 'Time Slots', 'wc-booking-calendar-nz' ),
			'booking-modes' => __( 'Booking Modes', 'wc-booking-calendar-nz' ),
			'person-types'  => __( 'Person Types', 'wc-booking-calendar-nz' ),
			'notifications' => __( 'Notifications', 'wc-booking-calendar-nz' ),
			'advanced'      => __( 'Advanced', 'wc-booking-calendar-nz' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'general';
		}
		?>
		<div class="wrap wc-booking-calendar-settings">
			<h1><?php esc_html_e( 'Booking Calendar Settings', 'wc-booking-calendar-nz' ); ?></h1>

			<?php $this->render_admin_notices(); ?>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) :
					$url = add_query_arg(
						array(
							'post_type' => 'wc_booking',
							'page'      => self::PAGE_SLUG,
							'tab'       => $slug,
						),
						admin_url( 'edit.php' )
					);
					?>
					<a href="<?php echo esc_url( $url ); ?>" class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</h2>

			<form method="post" action="options.php">
				<?php settings_fields( 'wc_booking_calendar_settings' ); ?>
				<?php $this->render_tab( $tab ); ?>
				<?php submit_button(); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Export / Import settings', 'wc-booking-calendar-nz' ); ?></h2>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wc_booking_calendar_export' ), 'wc_booking_calendar_export' ) ); ?>">
					<?php esc_html_e( 'Export settings (JSON)', 'wc-booking-calendar-nz' ); ?>
				</a>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="margin-top:10px;">
				<input type="hidden" name="action" value="wc_booking_calendar_import">
				<?php wp_nonce_field( 'wc_booking_calendar_import' ); ?>
				<input type="file" name="import_file" accept=".json,application/json">
				<button class="button button-primary"><?php esc_html_e( 'Import', 'wc-booking-calendar-nz' ); ?></button>
			</form>
		</div>
		<?php
	}

	/**
	 * Show notices from query string.
	 *
	 * @return void
	 */
	private function render_admin_notices() {
		$msg = isset( $_GET['wc_bcn_msg'] ) ? sanitize_key( $_GET['wc_bcn_msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $msg ) {
			return;
		}
		$map = array(
			'imported'        => array( 'updated', __( 'Settings imported successfully.', 'wc-booking-calendar-nz' ) ),
			'invalid_file'    => array( 'error', __( 'Invalid import file.', 'wc-booking-calendar-nz' ) ),
			'missing_file'    => array( 'error', __( 'No file provided.', 'wc-booking-calendar-nz' ) ),
			'permission'      => array( 'error', __( 'Permission denied.', 'wc-booking-calendar-nz' ) ),
		);
		if ( ! isset( $map[ $msg ] ) ) {
			return;
		}
		echo '<div class="notice notice-' . esc_attr( $map[ $msg ][0] ) . '"><p>' . esc_html( $map[ $msg ][1] ) . '</p></div>';
	}

	/**
	 * Render a tab.
	 *
	 * @param string $tab Tab slug.
	 * @return void
	 */
	private function render_tab( $tab ) {
		$file_map = array(
			'general'       => 'settings-general.php',
			'time-slots'    => 'settings-time-slots.php',
			'booking-modes' => 'settings-booking-modes.php',
			'person-types'  => 'settings-person-types.php',
			'notifications' => 'settings-notifications.php',
			'advanced'      => 'settings-advanced.php',
		);
		if ( ! isset( $file_map[ $tab ] ) ) {
			return;
		}
		$file = WC_BOOKING_CALENDAR_PLUGIN_DIR . 'admin/templates/' . $file_map[ $tab ];
		if ( file_exists( $file ) ) {
			include $file;
		}
	}

	/* ------------------------------------------------------------------
	 * Sanitizers
	 * ------------------------------------------------------------------ */

	/**
	 * Sanitize time slots.
	 *
	 * @param mixed $value Raw.
	 * @return array
	 */
	public function sanitize_time_slots( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$clean = array();
		foreach ( $value as $row ) {
			if ( empty( $row['start'] ) || empty( $row['end'] ) ) {
				continue;
			}
			$start = sanitize_text_field( $row['start'] );
			$end   = sanitize_text_field( $row['end'] );
			if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $start ) ) {
				continue;
			}
			if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $end ) ) {
				continue;
			}
			$clean[] = array(
				'id'      => isset( $row['id'] ) ? (int) $row['id'] : count( $clean ) + 1,
				'name'    => isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '',
				'start'   => $start,
				'end'     => $end,
				'enabled' => ! empty( $row['enabled'] ) ? 1 : 0,
			);
		}
		return $clean;
	}

	/**
	 * Sanitize days of week.
	 *
	 * @param mixed $value Raw.
	 * @return array
	 */
	public function sanitize_days_of_week( $value ) {
		$days = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
		$out  = array();
		foreach ( $days as $d ) {
			$out[ $d ] = ! empty( $value[ $d ] ) ? 1 : 0;
		}
		return $out;
	}

	/**
	 * Sanitize booking modes.
	 *
	 * @param mixed $value Raw.
	 * @return array
	 */
	public function sanitize_booking_modes( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$clean = array();
		foreach ( $value as $row ) {
			if ( empty( $row['name'] ) ) {
				continue;
			}
			$clean[] = array(
				'id'             => isset( $row['id'] ) ? (int) $row['id'] : count( $clean ) + 1,
				'name'           => sanitize_text_field( $row['name'] ),
				'description'    => isset( $row['description'] ) ? sanitize_textarea_field( $row['description'] ) : '',
				'full_day_block' => ! empty( $row['full_day_block'] ) ? 1 : 0,
				'show_addons'    => ! empty( $row['show_addons'] ) ? 1 : 0,
				'max_per_slot'   => isset( $row['max_per_slot'] ) ? max( 1, (int) $row['max_per_slot'] ) : 1,
			);
		}
		return $clean;
	}

	/**
	 * Sanitize person types.
	 *
	 * @param mixed $value Raw.
	 * @return array
	 */
	public function sanitize_person_types( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$clean = array();
		foreach ( $value as $row ) {
			if ( empty( $row['name'] ) ) {
				continue;
			}
			$clean[] = array(
				'id'      => isset( $row['id'] ) ? (int) $row['id'] : count( $clean ) + 1,
				'name'    => sanitize_text_field( $row['name'] ),
				'age_min' => isset( $row['age_min'] ) ? (int) $row['age_min'] : 0,
				'age_max' => isset( $row['age_max'] ) ? (int) $row['age_max'] : 120,
				'price'   => isset( $row['price'] ) ? (float) $row['price'] : 0,
			);
		}
		return $clean;
	}

	/**
	 * Sanitize notifications.
	 *
	 * @param mixed $value Raw.
	 * @return array
	 */
	public function sanitize_notifications( $value ) {
		$flags = array( 'confirmation', 'reminder', 'cancellation' );
		$out   = array();
		foreach ( $flags as $f ) {
			$out[ $f ] = ! empty( $value[ $f ] ) ? 1 : 0;
		}
		return $out;
	}

	/**
	 * Sanitize advanced options.
	 *
	 * @param mixed $value Raw.
	 * @return array
	 */
	public function sanitize_advanced( $value ) {
		$out = array(
			'peak_days'        => array(),
			'peak_multiplier'  => 1.0,
			'blackout_dates'   => array(),
			'seasonal_pricing' => array(),
		);
		if ( ! is_array( $value ) ) {
			return $out;
		}
		if ( ! empty( $value['peak_days'] ) && is_array( $value['peak_days'] ) ) {
			$valid_days = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );
			foreach ( $value['peak_days'] as $d ) {
				$d = sanitize_text_field( $d );
				if ( in_array( $d, $valid_days, true ) ) {
					$out['peak_days'][] = $d;
				}
			}
		}
		if ( isset( $value['peak_multiplier'] ) ) {
			$out['peak_multiplier'] = max( 0.1, min( 10.0, (float) $value['peak_multiplier'] ) );
		}
		if ( ! empty( $value['blackout_dates'] ) ) {
			$dates = is_array( $value['blackout_dates'] )
				? $value['blackout_dates']
				: preg_split( '/[\s,]+/', (string) $value['blackout_dates'] );
			foreach ( $dates as $d ) {
				$d = sanitize_text_field( $d );
				if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
					$out['blackout_dates'][] = $d;
				}
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Export / import
	 * ------------------------------------------------------------------ */

	/**
	 * Handle export action.
	 *
	 * @return void
	 */
	public function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wc-booking-calendar-nz' ) );
		}
		check_admin_referer( 'wc_booking_calendar_export' );

		$availability = WC_Booking_Calendar_Availability_Manager::get_instance();
		$data         = $availability->export_rules();

		nocache_headers();
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="wc-booking-calendar-settings-' . gmdate( 'Ymd-His' ) . '.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Handle import action.
	 *
	 * @return void
	 */
	public function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->redirect_with_message( 'permission' );
		}
		check_admin_referer( 'wc_booking_calendar_import' );

		if ( empty( $_FILES['import_file']['tmp_name'] ) ) {
			$this->redirect_with_message( 'missing_file' );
		}

		$raw = file_get_contents( $_FILES['import_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data = json_decode( (string) $raw, true );
		if ( ! is_array( $data ) ) {
			$this->redirect_with_message( 'invalid_file' );
		}

		WC_Booking_Calendar_Availability_Manager::get_instance()->import_rules( $data );
		$this->redirect_with_message( 'imported' );
	}

	/**
	 * Redirect back to settings page with a message slug.
	 *
	 * @param string $msg Message slug.
	 * @return void
	 */
	private function redirect_with_message( $msg ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'  => 'wc_booking',
					'page'       => self::PAGE_SLUG,
					'wc_bcn_msg' => $msg,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}
}