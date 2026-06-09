/**
 * WC Booking Calendar - Frontend Handler.
 *
 * AJAX endpoints, shortcodes, asset enqueuing, single-product form rendering.
 *
 * @package WC_Booking_Calendar_NZ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Booking_Calendar_Frontend_Handler
 */
class WC_Booking_Calendar_Frontend_Handler {

	/**
	 * Singleton.
	 *
	 * @var WC_Booking_Calendar_Frontend_Handler|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return WC_Booking_Calendar_Frontend_Handler
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
		// Assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Cart validation.
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'get_item_data' ), 10, 2 );

		// Shortcodes.
		add_shortcode( 'wc_booking_form', array( $this, 'shortcode_booking_form' ) );
		add_shortcode( 'wc_booking_calendar', array( $this, 'shortcode_booking_calendar' ) );

		// Single product form.
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_booking_form_on_product' ), 5 );

		// AJAX (logged-in & guest).
		$endpoints = array(
			'wc_booking_get_slots'            => 'ajax_get_slots',
			'wc_booking_check_availability'   => 'ajax_check_availability',
			'wc_booking_calculate_price'      => 'ajax_calculate_price',
		);
		foreach ( $endpoints as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( $this, $method ) );
			add_action( 'wp_ajax_nopriv_' . $action, array( $this, $method ) );
		}
	}

	/* ------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------ */

	/**
	 * Enqueue frontend assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		// Only on booking-related pages.
		if ( ! $this->is_booking_context() ) {
			return;
		}

		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_style(
			'jquery-ui-style',
			'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css',
			array(),
			'1.13.2'
		);

		wp_enqueue_style(
			'wc-booking-calendar-frontend',
			WC_BOOKING_CALENDAR_PLUGIN_URL . 'public/assets/frontend.css',
			array(),
			WC_BOOKING_CALENDAR_VERSION
		);

		wp_enqueue_script(
			'wc-booking-calendar-frontend',
			WC_BOOKING_CALENDAR_PLUGIN_URL . 'public/assets/frontend.js',
			array( 'jquery', 'jquery-ui-datepicker' ),
			WC_BOOKING_CALENDAR_VERSION,
			true
		);

		wp_localize_script(
			'wc-booking-calendar-frontend',
			'wcBookingCalendar',
			array(
				'ajaxurl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'wc_booking_calendar' ),
				'currencySymbol' => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$',
				'dateFormat'     => 'yy-mm-dd',
				'i18n'           => array(
					'select_date'      => __( 'Select a date', 'wc-booking-calendar-nz' ),
					'select_time'      => __( 'Select a time slot', 'wc-booking-calendar-nz' ),
					'no_slots'         => __( 'No time slots available for this date.', 'wc-booking-calendar-nz' ),
					'checking'         => __( 'Checking availability…', 'wc-booking-calendar-nz' ),
					'available'        => __( 'Available', 'wc-booking-calendar-nz' ),
					'unavailable'      => __( 'Not available', 'wc-booking-calendar-nz' ),
					'add_at_least_one' => __( 'Please add at least one person.', 'wc-booking-calendar-nz' ),
					'error'            => __( 'An error occurred. Please try again.', 'wc-booking-calendar-nz' ),
				),
			)
		);
	}

	/**
	 * Should we enqueue assets here?
	 *
	 * @return bool
	 */
	private function is_booking_context() {
		if ( is_admin() ) {
			return false;
		}
		if ( function_exists( 'is_product' ) && is_product() ) {
			global $product;
			if ( $product && WC_Booking_Calendar_Product::is_booking_product( $product ) ) {
				return true;
			}
			$post = get_post();
			if ( $post && WC_Booking_Calendar_Product::is_booking_product( $post->ID ) ) {
				return true;
			}
		}

		// Check for shortcodes in page content.
		$post = get_post();
		if ( $post && ( has_shortcode( $post->post_content, 'wc_booking_form' ) || has_shortcode( $post->post_content, 'wc_booking_calendar' ) ) ) {
			return true;
		}

		return apply_filters( 'wc_booking_calendar_is_booking_context', false );
	}

	/* ------------------------------------------------------------------
	 * Cart Validation
	 * ------------------------------------------------------------------ */

	/**
	 * Validate booking product before adding to cart.
	 *
	 * @param bool $passed
	 * @param int  $product_id
	 * @param int  $quantity
	 * @return bool
	 */
	public function validate_add_to_cart( $passed, $product_id, $quantity ) {
		if ( ! WC_Booking_Calendar_Product::is_booking_product( $product_id ) ) {
			return $passed;
		}

		// 1. Sanitize Inputs
		$mode         = sanitize_text_field( wp_unslash( $_POST['booking_mode'] ?? 'self' ) );
		$date         = sanitize_text_field( wp_unslash( $_POST['booking_date'] ?? '' ) );
		$time         = sanitize_text_field( wp_unslash( $_POST['booking_time'] ?? '' ) );
		$person_types = $this->sanitize_person_types( $_POST['person_types'] ?? array() );
		$total_people = array_sum( $person_types );

		// 2. Business Rule: Minimum Group Size for Guided Tours
		if ( 'guided' === $mode && $total_people < 10 ) {
			wc_add_notice( __( 'Guided tours require a minimum of 10 people.', 'wc-booking-calendar-nz' ), 'error' );
			return false;
		}

		// 3. Business Rule: Required Fields
		if ( empty( $date ) || empty( $time ) ) {
			wc_add_notice( __( 'Please select both a date and a time for your booking.', 'wc-booking-calendar-nz' ), 'error' );
			return false;
		}

		// 4. Availability Engine Validation
		$availability = WC_Booking_Calendar_Availability_Manager::get_instance();
		$result = $availability->check_availability( $product_id, $date, $time, $mode, $total_people );

		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );
			return false;
		}

		return $passed;
	}

	/**
	 * Add booking data to cart item.
	 *
	 * @param array $cart_item_data
	 * @param int   $product_id
	 * @param int   $variation_id
	 * @return array
	 */
	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		if ( ! WC_Booking_Calendar_Product::is_booking_product( $product_id ) ) {
			return $cart_item_data;
		}

		$cart_item_data['booking_data'] = array(
			'date'         => sanitize_text_field( wp_unslash( $_POST['booking_date'] ) ),
			'time'         => sanitize_text_field( wp_unslash( $_POST['booking_time'] ) ),
			'person_types' => $this->sanitize_person_types( $_POST['person_types'] ?? array() ),
			'resource_id'  => isset( $_POST['resource_id'] ) ? (int) $_POST['resource_id'] : 0,
			'mode'         => sanitize_text_field( wp_unslash( $_POST['booking_mode'] ) ),
		);

		return $cart_item_data;
	}

	/**
	 * Display booking data in cart.
	 *
	 * @param array $item_data
	 * @param array $cart_item
	 * @return array
	 */
	public function get_item_data( $item_data, $cart_item ) {
		if ( isset( $cart_item['booking_data'] ) ) {
			$item_data[] = array(
				'name'  => __( 'Date', 'wc-booking-calendar-nz' ),
				'value' => $cart_item['booking_data']['date'],
			);
			$item_data[] = array(
				'name'  => __( 'Time', 'wc-booking-calendar-nz' ),
				'value' => $cart_item['booking_data']['time'],
			);
			if ( ! empty( $cart_item['booking_data']['person_types'] ) ) {
				$person_types_list = array();
				foreach ( $cart_item['booking_data']['person_types'] as $type_id => $count ) {
					$person_types_list[] = $count . 'x ' . $type_id;
				}
				$item_data[] = array(
					'name'  => __( 'Persons', 'wc-booking-calendar-nz' ),
					'value' => implode(', ', $person_types_list),
				);
			}
			if ( $cart_item['booking_data']['resource_id'] > 0 ) {
				$item_data[] = array(
					'name'  => __( 'Resource', 'wc-booking-calendar-nz' ),
					'value' => $cart_item['booking_data']['resource_id'],
				);
			}
			if ( ! empty( $cart_item['booking_data']['mode'] ) ) {
				$item_data[] = array(
					'name'  => __( 'Mode', 'wc-booking-calendar-nz' ),
					'value' => $cart_item['booking_data']['mode'],
				);
			}
		}
		return $item_data;
	}

	/* ------------------------------------------------------------------
	 * Templates
	 * ------------------------------------------------------------------ */

	/**
	 * Render booking form on single product page.
	 *
	 * @return void
	 */
	public function render_booking_form_on_product() {
		global $product;
		if ( ! $product || ! WC_Booking_Calendar_Product::is_booking_product( $product ) ) {
			return;
		}
		echo $this->get_booking_form_html( $product->get_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render booking form HTML.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public function get_booking_form_html( $product_id ) {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( ! $product ) {
			return '';
		}

		$template = locate_template( 'wc-booking-calendar-nz/single-product/booking-form.php' );
		if ( ! $template ) {
			$template = WC_BOOKING_CALENDAR_PLUGIN_DIR . 'public/templates/single-product/booking-form.php';
		}
		if ( ! file_exists( $template ) ) {
			return '';
		}

		ob_start();
		$person_types = get_option( 'wc_booking_calendar_person_types', array() );
		$resources    = WC_Booking_Calendar_Resource_CPT::get_available_resources();
		$booking_modes = get_option( 'wc_booking_calendar_booking_modes', array() );
		$requires_resource = 'yes' === $product->get_meta( '_booking_requires_resource' );
		$description       = $product->get_meta( '_booking_description' );
		$min_advance_days  = (int) $product->get_meta( '_booking_min_advance_days' );
		$max_advance_days  = (int) $product->get_meta( '_booking_max_advance_days' );
		if ( $min_advance_days <= 0 ) {
			$min_advance_days = 1;
		}
		if ( $max_advance_days <= 0 ) {
			$max_advance_days = (int) get_option( 'wc_booking_calendar_advance_days', 365 );
		}

		include $template;
		return ob_get_clean();
	}

	/**
	 * Shortcode: [wc_booking_form id="123"].
	 *
	 * @param array $atts Atts.
	 * @return string
	 */
	public function shortcode_booking_form( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'wc_booking_form' );
		$id   = (int) $atts['id'];
		if ( ! $id ) {
			return '<p>' . esc_html__( 'Please provide a product id.', 'wc-booking-calendar-nz' ) . '</p>';
		}
		return $this->get_booking_form_html( $id );
	}

	/**
	 * Shortcode: [wc_booking_calendar id="123"] — placeholder month grid.
	 *
	 * @param array $atts Atts.
	 * @return string
	 */
	public function shortcode_booking_calendar( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'month' => gmdate( 'Y-m' ),
			),
			$atts,
			'wc_booking_calendar'
		);
		$id = (int) $atts['id'];
		ob_start();
		?>
		<div class="wc-booking-calendar-shortcode" data-product-id="<?php echo esc_attr( $id ); ?>" data-month="<?php echo esc_attr( $atts['month'] ); ?>">
			<p class="wc-booking-calendar-loading"><?php esc_html_e( 'Loading availability…', 'wc-booking-calendar-nz' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------
	 * AJAX endpoints
	 * ------------------------------------------------------------------ */

	/**
	 * Validate AJAX nonce.
	 *
	 * @return void
	 */
	private function verify_ajax_nonce() {
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wc_booking_calendar' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed.', 'wc-booking-calendar-nz' ) ),
				403
			);
		}
	}

	/**
	 * AJAX: get available slots for a date.
	 *
	 * @return void
	 */
	public function ajax_get_slots() {
		$this->verify_ajax_nonce();

		$product_id  = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
		$date        = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$resource_id = isset( $_POST['resource_id'] ) ? (int) $_POST['resource_id'] : 0;

		$availability = WC_Booking_Calendar_Availability_Manager::get_instance();
		if ( ! $availability->validate_date( $date ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid date.', 'wc-booking-calendar-nz' ) ) );
		}

		$slots = $availability->get_available_slots( $product_id, $date, $resource_id );
		wp_send_json_success( array( 'slots' => $slots ) );
	}

	/**
	 * AJAX: check a specific slot.
	 *
	 * @return void
	 */
	public function ajax_check_availability() {
		$this->verify_ajax_nonce();

		$product_id  = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
		$date        = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$time        = isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '';
		$resource_id = isset( $_POST['resource_id'] ) ? (int) $_POST['resource_id'] : 0;
		$mode        = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : '';
		$person_types = $this->sanitize_person_types( $_POST['person_types'] ?? array() );

		$availability = WC_Booking_Calendar_Availability_Manager::get_instance();
		$result       = $availability->check_availability_with_person_types( $product_id, $date, $time, $person_types, $resource_id, $mode );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				)
			);
		}
		wp_send_json_success( $result );
	}

	/**
	 * AJAX: calculate price.
	 *
	 * @return void
	 */
	public function ajax_calculate_price() {
		$this->verify_ajax_nonce();

		$product_id   = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
		$person_types = $this->sanitize_person_types( $_POST['person_types'] ?? array() );
		$date         = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';

		$total = WC_Booking_Calendar_Product::calculate_price( $product_id, $person_types );

		// Peak day multiplier.
		$availability = WC_Booking_Calendar_Availability_Manager::get_instance();
		if ( $date && $availability->validate_date( $date ) ) {
			$rules = $availability->get_general_rules();
			$day   = gmdate( 'l', strtotime( $date ) );
			if ( in_array( $day, (array) $rules['peak_days'], true ) ) {
				$total = round( $total * (float) $rules['peak_multiplier'], 2 );
			}
		}

		wp_send_json_success(
			array(
				'total'           => $total,
				'total_formatted' => function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $total ) ) : (string) $total,
			)
		);
	}

	/**
	 * Sanitize the person_types POST payload into an int=>int map.
	 *
	 * @param mixed $raw Raw input.
	 * @return array<int,int>
	 */
	public function sanitize_person_types( $raw ) {
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
			if ( $tid > 0 ) {
				$clean[ $tid ] = $c;
			}
		}
		return $clean;
	}
}
