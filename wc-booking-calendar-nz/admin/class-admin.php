<?php
/**
 * WC Booking Calendar - Admin Dashboard.
 *
 * Calendar view, reports, AJAX handlers, REST endpoints for the admin UI.
 *
 * @package WC_Booking_Calendar_NZ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Booking_Calendar_Admin
 */
class WC_Booking_Calendar_Admin {

	const CAL_SLUG     = 'wc-booking-calendar';
	const REPORTS_SLUG = 'wc-booking-calendar-reports';

	/**
	 * Singleton.
	 *
	 * @var WC_Booking_Calendar_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return WC_Booking_Calendar_Admin
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
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// AJAX (action names match admin/assets/js/admin.js).
		add_action( 'wp_ajax_wc_booking_admin_get_bookings',    array( $this, 'ajax_get_month' ) );
		add_action( 'wp_ajax_wc_booking_admin_update_booking',  array( $this, 'ajax_update_status' ) );
		add_action( 'wp_ajax_wc_booking_admin_delete_booking',  array( $this, 'ajax_delete_booking' ) );
		add_action( 'wp_ajax_wc_booking_admin_export_bookings', array( $this, 'ajax_export_json' ) );

		// Keep legacy action names registered too, for any external integrations.
		add_action( 'wp_ajax_wc_booking_calendar_get_month',    array( $this, 'ajax_get_month' ) );
		add_action( 'wp_ajax_wc_booking_calendar_update_status', array( $this, 'ajax_update_status' ) );
		add_action( 'wp_ajax_wc_booking_calendar_delete_booking', array( $this, 'ajax_delete_booking' ) );
		add_action( 'wp_ajax_wc_booking_calendar_export_csv',  array( $this, 'ajax_export_csv' ) );
	}

	/**
	 * Add menu pages.
	 *
	 * @return void
	 */
	public function add_menu_pages() {
		add_submenu_page(
			'edit.php?post_type=wc_booking',
			__( 'Calendar', 'wc-booking-calendar-nz' ),
			__( 'Calendar', 'wc-booking-calendar-nz' ),
			'manage_woocommerce',
			self::CAL_SLUG,
			array( $this, 'render_calendar_page' )
		);
		add_submenu_page(
			'edit.php?post_type=wc_booking',
			__( 'Reports', 'wc-booking-calendar-nz' ),
			__( 'Reports', 'wc-booking-calendar-nz' ),
			'manage_woocommerce',
			self::REPORTS_SLUG,
			array( $this, 'render_reports_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( (string) $hook, self::CAL_SLUG ) && false === strpos( (string) $hook, self::REPORTS_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'wc-booking-calendar-admin',
			WC_BOOKING_CALENDAR_PLUGIN_URL . 'admin/assets/css/admin.css',
			array(),
			WC_BOOKING_CALENDAR_VERSION
		);
		wp_enqueue_script(
			'wc-booking-calendar-admin',
			WC_BOOKING_CALENDAR_PLUGIN_URL . 'admin/assets/js/admin.js',
			array( 'jquery' ),
			WC_BOOKING_CALENDAR_VERSION,
			true
		);
		wp_localize_script(
			'wc-booking-calendar-admin',
			'wc_booking_calendar_admin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wc_booking_calendar_admin' ),
				'i18n'     => array(
					'confirm_status_change' => __( 'Are you sure you want to change this booking’s status?', 'wc-booking-calendar-nz' ),
					'confirm_delete'        => __( 'Permanently delete this booking? This cannot be undone.', 'wc-booking-calendar-nz' ),
					'no_bookings'           => __( 'No bookings for this day.', 'wc-booking-calendar-nz' ),
					'date'                  => __( 'Date', 'wc-booking-calendar-nz' ),
					'time'                  => __( 'Time', 'wc-booking-calendar-nz' ),
					'people'                => __( 'People', 'wc-booking-calendar-nz' ),
					'total'                 => __( 'Total', 'wc-booking-calendar-nz' ),
					'status'                => __( 'Status', 'wc-booking-calendar-nz' ),
					'resource'              => __( 'Resource', 'wc-booking-calendar-nz' ),
					'special_requests'      => __( 'Notes', 'wc-booking-calendar-nz' ),
					'confirm'               => __( 'Confirm', 'wc-booking-calendar-nz' ),
					'cancel'                => __( 'Cancel', 'wc-booking-calendar-nz' ),
					'delete'                => __( 'Delete', 'wc-booking-calendar-nz' ),
					'status_updated'        => __( 'Booking status updated.', 'wc-booking-calendar-nz' ),
					'booking_deleted'       => __( 'Booking deleted.', 'wc-booking-calendar-nz' ),
				),
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Render pages
	 * ------------------------------------------------------------------ */

	/**
	 * Render calendar page.
	 *
	 * @return void
	 */
	public function render_calendar_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wc-booking-calendar-nz' ) );
		}
		$year  = isset( $_GET['year'] ) ? (int) $_GET['year'] : (int) gmdate( 'Y' );   // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$month = isset( $_GET['month'] ) ? (int) $_GET['month'] : (int) gmdate( 'n' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap wc-booking-calendar-admin">
			<h1><?php esc_html_e( 'Booking Calendar', 'wc-booking-calendar-nz' ); ?></h1>

			<div class="booking-calendar-container" id="wc-booking-calendar-app" data-year="<?php echo esc_attr( $year ); ?>" data-month="<?php echo esc_attr( $month ); ?>">
				<div class="booking-calendar-toolbar">
					<button type="button" class="button" id="prev-month">&laquo; <?php esc_html_e( 'Prev', 'wc-booking-calendar-nz' ); ?></button>
					<button type="button" class="button" id="today"><?php esc_html_e( 'Today', 'wc-booking-calendar-nz' ); ?></button>
					<button type="button" class="button" id="next-month"><?php esc_html_e( 'Next', 'wc-booking-calendar-nz' ); ?> &raquo;</button>
					<span class="current-month" id="current-month">—</span>
					<button type="button" class="button button-secondary" id="export-bookings" style="float:right;"><?php esc_html_e( 'Export CSV', 'wc-booking-calendar-nz' ); ?></button>
				</div>

				<div class="calendar-grid">
					<div class="calendar-weekdays">
						<span><?php esc_html_e( 'Sun', 'wc-booking-calendar-nz' ); ?></span>
						<span><?php esc_html_e( 'Mon', 'wc-booking-calendar-nz' ); ?></span>
						<span><?php esc_html_e( 'Tue', 'wc-booking-calendar-nz' ); ?></span>
						<span><?php esc_html_e( 'Wed', 'wc-booking-calendar-nz' ); ?></span>
						<span><?php esc_html_e( 'Thu', 'wc-booking-calendar-nz' ); ?></span>
						<span><?php esc_html_e( 'Fri', 'wc-booking-calendar-nz' ); ?></span>
						<span><?php esc_html_e( 'Sat', 'wc-booking-calendar-nz' ); ?></span>
					</div>
					<div id="calendar-days" class="calendar-days"></div>
				</div>

				<div id="day-details" class="day-details" style="display:none;">
					<h2><?php esc_html_e( 'Bookings for', 'wc-booking-calendar-nz' ); ?> <span id="selected-date"></span></h2>
					<div id="bookings-list"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render reports page.
	 *
	 * @return void
	 */
	public function render_reports_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wc-booking-calendar-nz' ) );
		}

		$start = isset( $_GET['start'] ) ? sanitize_text_field( wp_unslash( $_GET['start'] ) ) : gmdate( 'Y-m-01' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$end   = isset( $_GET['end'] ) ? sanitize_text_field( wp_unslash( $_GET['end'] ) ) : gmdate( 'Y-m-t' );      // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$availability = WC_Booking_Calendar_Availability_Manager::get_instance();
		if ( ! $availability->validate_date( $start ) ) {
			$start = gmdate( 'Y-m-01' );
		}
		if ( ! $availability->validate_date( $end ) ) {
			$end = gmdate( 'Y-m-t' );
		}

		$stats = self::get_booking_statistics( $start, $end );
		?>
		<div class="wrap wc-booking-calendar-reports">
			<h1><?php esc_html_e( 'Booking Reports', 'wc-booking-calendar-nz' ); ?></h1>
			<form method="get">
				<input type="hidden" name="post_type" value="wc_booking">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::REPORTS_SLUG ); ?>">
				<label><?php esc_html_e( 'From', 'wc-booking-calendar-nz' ); ?> <input type="date" name="start" value="<?php echo esc_attr( $start ); ?>"></label>
				<label><?php esc_html_e( 'To', 'wc-booking-calendar-nz' ); ?> <input type="date" name="end" value="<?php echo esc_attr( $end ); ?>"></label>
				<button class="button button-primary"><?php esc_html_e( 'Apply', 'wc-booking-calendar-nz' ); ?></button>
			</form>

			<div class="wc-booking-calendar-stats">
				<div class="card">
					<h2><?php esc_html_e( 'Total Bookings', 'wc-booking-calendar-nz' ); ?></h2>
					<p class="big"><?php echo esc_html( (int) $stats['total'] ); ?></p>
				</div>
				<div class="card">
					<h2><?php esc_html_e( 'Confirmed', 'wc-booking-calendar-nz' ); ?></h2>
					<p class="big"><?php echo esc_html( (int) $stats['confirmed'] ); ?></p>
				</div>
				<div class="card">
					<h2><?php esc_html_e( 'Cancelled', 'wc-booking-calendar-nz' ); ?></h2>
					<p class="big"><?php echo esc_html( (int) $stats['cancelled'] ); ?></p>
				</div>
				<div class="card">
					<h2><?php esc_html_e( 'Revenue', 'wc-booking-calendar-nz' ); ?></h2>
					<p class="big"><?php echo function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $stats['revenue'] ) ) : esc_html( number_format( $stats['revenue'], 2 ) ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	 * AJAX handlers
	 * ------------------------------------------------------------------ */

	/**
	 * Verify the admin AJAX nonce and permissions.
	 *
	 * @return void
	 */
	private function verify_admin_ajax() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wc-booking-calendar-nz' ) ), 403 );
		}
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wc_booking_calendar_admin' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wc-booking-calendar-nz' ) ), 403 );
		}
	}

	/**
	 * AJAX: get bookings for a month.
	 *
	 * @return void
	 */
	public function ajax_get_month() {
		$this->verify_admin_ajax();

		// Accept either (year, month) or (start_date, end_date) – admin.js sends the latter.
		if ( isset( $_POST['start_date'], $_POST['end_date'] ) ) {
			$start = sanitize_text_field( wp_unslash( $_POST['start_date'] ) );
			$end   = sanitize_text_field( wp_unslash( $_POST['end_date'] ) );
			$year  = (int) gmdate( 'Y', strtotime( $start ) );
			$month = (int) gmdate( 'n', strtotime( $start ) );
		} else {
			$year  = isset( $_POST['year'] ) ? max( 1970, min( 2999, (int) $_POST['year'] ) ) : (int) gmdate( 'Y' );
			$month = isset( $_POST['month'] ) ? max( 1, min( 12, (int) $_POST['month'] ) ) : (int) gmdate( 'n' );
			$start = sprintf( '%04d-%02d-01', $year, $month );
			$end   = gmdate( 'Y-m-t', strtotime( $start ) );
		}

		$posts    = WC_Booking_Calendar_Booking_CPT::get_bookings_by_date_range( $start, $end );
		$bookings = array();
		foreach ( $posts as $p ) {
			$d                 = WC_Booking_Calendar_Booking_CPT::get_booking_data( $p->ID );
			$d['product_name'] = $d['product_id'] ? get_the_title( $d['product_id'] ) : '';
			$d['resource_name'] = $d['resource_id'] ? get_the_title( $d['resource_id'] ) : '';
			// Aliases expected by admin.js.
			$d['booking_date'] = $d['date'];
			$d['booking_time'] = $d['time'];
			$bookings[]        = $d;
		}

		wp_send_json_success(
			array(
				'year'     => $year,
				'month'    => $month,
				'bookings' => $bookings,
			)
		);
	}

	/**
	 * AJAX: update booking status.
	 *
	 * @return void
	 */
	public function ajax_update_status() {
		$this->verify_admin_ajax();

		$booking_id = isset( $_POST['booking_id'] ) ? (int) $_POST['booking_id'] : 0;
		$status     = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

		if ( ! $booking_id || ! in_array( $status, WC_Booking_Calendar_Booking_CPT::get_statuses(), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wc-booking-calendar-nz' ) ) );
		}
		$post = get_post( $booking_id );
		if ( ! $post || WC_Booking_Calendar_Booking_CPT::CPT_SLUG !== $post->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Booking not found.', 'wc-booking-calendar-nz' ) ) );
		}

		wp_update_post(
			array(
				'ID'          => $booking_id,
				'post_status' => $status,
			)
		);

		if ( in_array( $status, array( 'cancelled', 'refunded' ), true ) ) {
			WC_Booking_Calendar_Availability_Manager::get_instance()->release_availability( $booking_id );
		}

		wp_send_json_success( WC_Booking_Calendar_Booking_CPT::get_booking_data( $booking_id ) );
	}

	/**
	 * AJAX: delete booking.
	 *
	 * @return void
	 */
	public function ajax_delete_booking() {
		$this->verify_admin_ajax();
		$booking_id = isset( $_POST['booking_id'] ) ? (int) $_POST['booking_id'] : 0;
		if ( ! $booking_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid id.', 'wc-booking-calendar-nz' ) ) );
		}
		$post = get_post( $booking_id );
		if ( ! $post || WC_Booking_Calendar_Booking_CPT::CPT_SLUG !== $post->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Booking not found.', 'wc-booking-calendar-nz' ) ) );
		}
		WC_Booking_Calendar_Availability_Manager::get_instance()->release_availability( $booking_id );
		wp_delete_post( $booking_id, true );
		wp_send_json_success();
	}

	/**
	 * AJAX: export CSV.
	 *
	 * @return void
	 */
	public function ajax_export_json() {
		$this->verify_admin_ajax();

		$start = isset( $_POST['start'] ) ? sanitize_text_field( wp_unslash( $_POST['start'] ) ) : gmdate( 'Y-m-01' );
		$end   = isset( $_POST['end'] ) ? sanitize_text_field( wp_unslash( $_POST['end'] ) ) : gmdate( 'Y-m-t' );

		$posts = WC_Booking_Calendar_Booking_CPT::get_bookings_by_date_range( $start, $end );
		$rows  = array();
		foreach ( $posts as $p ) {
			$d      = WC_Booking_Calendar_Booking_CPT::get_booking_data( $p->ID );
			$rows[] = array(
				'id'               => $d['id'],
				'product'          => $d['product_id'] ? get_the_title( $d['product_id'] ) : '',
				'date'             => $d['date'],
				'time'             => $d['time'],
				'resource'         => $d['resource_id'] ? get_the_title( $d['resource_id'] ) : '',
				'people'           => $d['person_count'],
				'total'            => $d['total_price'],
				'status'           => $d['status'],
				'special_requests' => $d['special_requests'],
			);
		}
		wp_send_json_success( array( 'data' => $rows ) );
	}

	/**
	 * AJAX: export CSV (stream).
	 *
	 * @return void
	 */
	public function ajax_export_csv() {
		$this->verify_admin_ajax();

		$start = isset( $_REQUEST['start'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['start'] ) ) : gmdate( 'Y-m-01' );
		$end   = isset( $_REQUEST['end'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['end'] ) ) : gmdate( 'Y-m-t' );

		$posts = WC_Booking_Calendar_Booking_CPT::get_bookings_by_date_range( $start, $end );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="bookings-' . $start . '-to-' . $end . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Booking ID', 'Status', 'Date', 'Time', 'Product', 'Resource', 'People', 'Total' ) );
		foreach ( $posts as $p ) {
			$d = WC_Booking_Calendar_Booking_CPT::get_booking_data( $p->ID );
			fputcsv(
				$out,
				array(
					$d['id'],
					$d['status'],
					$d['date'],
					$d['time'],
					$d['product_id'] ? get_the_title( $d['product_id'] ) : '',
					$d['resource_id'] ? get_the_title( $d['resource_id'] ) : '',
					$d['person_count'],
					$d['total_price'],
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/* ------------------------------------------------------------------
	 * Stats helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Aggregate stats for date range.
	 *
	 * @param string $start Start date.
	 * @param string $end   End date.
	 * @return array
	 */
	public static function get_booking_statistics( $start, $end ) {
		$totals = array(
			'total'     => 0,
			'confirmed' => 0,
			'pending'   => 0,
			'cancelled' => 0,
			'revenue'   => 0.0,
		);

		$posts = WC_Booking_Calendar_Booking_CPT::get_bookings_by_date_range( $start, $end );
		foreach ( $posts as $p ) {
			$totals['total']++;
			switch ( $p->post_status ) {
				case 'confirmed':
				case 'completed':
					$totals['confirmed']++;
					$totals['revenue'] += (float) get_post_meta( $p->ID, '_booking_total_price', true );
					break;
				case 'pending':
					$totals['pending']++;
					break;
				case 'cancelled':
				case 'refunded':
					$totals['cancelled']++;
					break;
			}
		}
		return $totals;
	}
}