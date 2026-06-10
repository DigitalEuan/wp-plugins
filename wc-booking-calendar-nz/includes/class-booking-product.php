<?php
/**
 * WC Booking Calendar - Booking Product.
 *
 * Custom product type "bookable_tour" plus admin tabs for product data.
 *
 * @package WC_Booking_Calendar_NZ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Booking_Calendar_Product
 */
class WC_Booking_Calendar_Product {

	const PRODUCT_TYPE = 'bookable_tour';

	/**
	 * Singleton.
	 *
	 * @var WC_Booking_Calendar_Product|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return WC_Booking_Calendar_Product
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
		add_filter( 'product_type_selector', array( $this, 'add_product_type' ) );
		add_filter( 'woocommerce_product_class', array( $this, 'product_class' ), 10, 2 );
		$this->load_product_class();

		// Admin tabs.
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tabs' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_data_panels' ) );
		add_action( 'woocommerce_process_product_meta_' . self::PRODUCT_TYPE, array( $this, 'save_product_meta' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta_for_simple' ) );

		// Single product template & price display.
		add_filter( 'woocommerce_get_price_html', array( $this, 'price_html' ), 10, 2 );

		// JS to make the booking tabs show.
		add_action( 'admin_footer', array( $this, 'product_type_admin_js' ) );
	}

	/**
	 * Add to product type dropdown.
	 *
	 * @param array $types Existing types.
	 * @return array
	 */
	public function add_product_type( $types ) {
		$types[ self::PRODUCT_TYPE ] = __( 'Bookable Tour', 'wc-booking-calendar-nz' );
		return $types;
	}

	/**
	 * Map product class.
	 *
	 * @param string $classname    Class name.
	 * @param string $product_type Type.
	 * @return string
	 */
	public function product_class( $classname, $product_type ) {
		if ( self::PRODUCT_TYPE === $product_type ) {
			return 'WC_Product_Bookable_Tour';
		}
		return $classname;
	}

	/**
	 * Load the product class (a separate file so we don't nest class decls).
	 *
	 * @return void
	 */
	public function load_product_class() {
		if ( class_exists( 'WC_Product_Bookable_Tour' ) ) {
			return;
		}
		$path = WC_BOOKING_CALENDAR_PLUGIN_DIR . 'includes/class-wc-product-bookable-tour.php';
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}

	/**
	 * Add product data tabs.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_product_data_tabs( $tabs ) {
		$tabs['booking_calendar'] = array(
			'label'    => __( 'Booking', 'wc-booking-calendar-nz' ),
			'target'   => 'wc_booking_calendar_product_data',
			'class'    => array( 'show_if_' . self::PRODUCT_TYPE ),
			'priority' => 70,
		);
		return $tabs;
	}

	/**
	 * Render data panel.
	 *
	 * @return void
	 */
	public function render_data_panels() {
		global $post;
		if ( ! $post ) {
			return;
		}

		$base_price       = get_post_meta( $post->ID, '_booking_base_price', true );
		$gst_inclusive    = get_post_meta( $post->ID, '_booking_gst_inclusive', true );
		$description      = get_post_meta( $post->ID, '_booking_description', true );
		$min_advance_days = get_post_meta( $post->ID, '_booking_min_advance_days', true );
		$max_advance_days = get_post_meta( $post->ID, '_booking_max_advance_days', true );
		$requires_resource = get_post_meta( $post->ID, '_booking_requires_resource', true );
		$rules            = get_post_meta( $post->ID, '_wc_booking_calendar_rules', true );
		if ( is_array( $rules ) ) {
			$rules = wp_json_encode( $rules, JSON_PRETTY_PRINT );
		}
		?>
		<div id="wc_booking_calendar_product_data" class="panel woocommerce_options_panel">
			<?php
			woocommerce_wp_text_input(
				array(
					'id'          => '_booking_base_price',
					'label'       => __( 'Base price (per booking)', 'wc-booking-calendar-nz' ),
					'data_type'   => 'price',
					'value'       => $base_price,
					'description' => __( 'Used when no per-person pricing applies.', 'wc-booking-calendar-nz' ),
					'desc_tip'    => true,
				)
			);

			woocommerce_wp_checkbox(
				array(
					'id'    => '_booking_gst_inclusive',
					'label' => __( 'Prices include GST', 'wc-booking-calendar-nz' ),
					'value' => $gst_inclusive ? 'yes' : 'no',
				)
			);

			woocommerce_wp_checkbox(
				array(
					'id'    => '_booking_requires_resource',
					'label' => __( 'Requires resource', 'wc-booking-calendar-nz' ),
					'value' => $requires_resource ? 'yes' : 'no',
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'    => '_booking_min_advance_days',
					'label' => __( 'Earliest day from today', 'wc-booking-calendar-nz' ),
					'type'  => 'number',
					'value' => $min_advance_days,
				)
			);
			woocommerce_wp_text_input(
				array(
					'id'    => '_booking_max_advance_days',
					'label' => __( 'Latest day from today', 'wc-booking-calendar-nz' ),
					'type'  => 'number',
					'value' => $max_advance_days,
				)
			);

			woocommerce_wp_textarea_input(
				array(
					'id'          => '_booking_description',
					'label'       => __( 'Booking description', 'wc-booking-calendar-nz' ),
					'value'       => $description,
					'description' => __( 'Displayed above the booking form.', 'wc-booking-calendar-nz' ),
					'desc_tip'    => true,
				)
			);

			woocommerce_wp_textarea_input(
				array(
					'id'          => '_wc_booking_calendar_rules',
					'label'       => __( 'Custom rules (JSON)', 'wc-booking-calendar-nz' ),
					'value'       => $rules,
					'description' => __( 'Optional JSON with blocked_dates, blocked_days, allowed_resources, booking_start_date, booking_end_date.', 'wc-booking-calendar-nz' ),
					'desc_tip'    => true,
				)
			);
			?>
		</div>
		<?php
	}

	/**
	 * Save product meta when bookable_tour type is processed.
	 *
	 * @param int $post_id Product ID.
	 * @return void
	 */
	public function save_product_meta( $post_id ) {
		$this->save_meta_fields( $post_id );
	}

	/**
	 * Save product meta for simple products too (the type may switch).
	 *
	 * @param int $post_id Product ID.
	 * @return void
	 */
	public function save_product_meta_for_simple( $post_id ) {
		if ( ! isset( $_POST['product-type'] ) || self::PRODUCT_TYPE !== sanitize_text_field( wp_unslash( $_POST['product-type'] ) ) ) {
			return;
		}
		$this->save_meta_fields( $post_id );
	}

	/**
	 * Internal: save meta fields.
	 *
	 * @param int $post_id Product ID.
	 * @return void
	 */
	private function save_meta_fields( $post_id ) {
		$nonce_ok = isset( $_POST['woocommerce_meta_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['woocommerce_meta_nonce'] ), 'woocommerce_save_data' );
		if ( ! $nonce_ok ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$text_fields = array(
			'_booking_base_price'       => 'wc_format_decimal',
			'_booking_description'      => 'sanitize_textarea_field',
			'_booking_min_advance_days' => 'intval',
			'_booking_max_advance_days' => 'intval',
		);
		foreach ( $text_fields as $key => $sanitizer ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, call_user_func( $sanitizer, wp_unslash( $_POST[ $key ] ) ) );
			}
		}

		update_post_meta( $post_id, '_booking_gst_inclusive', isset( $_POST['_booking_gst_inclusive'] ) ? 'yes' : 'no' );
		update_post_meta( $post_id, '_booking_requires_resource', isset( $_POST['_booking_requires_resource'] ) ? 'yes' : 'no' );

		// Rules JSON.
		if ( isset( $_POST['_wc_booking_calendar_rules'] ) ) {
			$raw     = trim( wp_unslash( $_POST['_wc_booking_calendar_rules'] ) );
			$decoded = '' === $raw ? array() : json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				update_post_meta( $post_id, '_wc_booking_calendar_rules', wp_json_encode( $decoded ) );
			} elseif ( '' === $raw ) {
				delete_post_meta( $post_id, '_wc_booking_calendar_rules' );
			}
			// silently ignore invalid JSON.
		}
	}

	/**
	 * Customize price HTML.
	 *
	 * @param string     $price_html Existing HTML.
	 * @param WC_Product $product    Product.
	 * @return string
	 */
	public function price_html( $price_html, $product ) {
		if ( self::is_booking_product( $product ) ) {
			$base = (float) $product->get_meta( '_booking_base_price' );
			if ( $base > 0 ) {
				/* translators: %s: formatted price */
				return sprintf( __( 'From %s', 'wc-booking-calendar-nz' ), wc_price( $base ) );
			}
		}
		return $price_html;
	}

	/**
	 * Show the booking-tab JS classes on product type change.
	 *
	 * @return void
	 */
	public function product_type_admin_js() {
		global $pagenow, $typenow;
		if ( ! in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) || 'product' !== $typenow ) {
			return;
		}
		?>
		<script>
		jQuery( function( $ ) {
			$( 'body' ).on( 'woocommerce-product-type-change', function( ev, t ) {
				if ( '<?php echo esc_js( self::PRODUCT_TYPE ); ?>' === t ) {
					$( '.show_if_<?php echo esc_js( self::PRODUCT_TYPE ); ?>' ).show();
				}
			} );
			if ( $( '#product-type' ).val() === '<?php echo esc_js( self::PRODUCT_TYPE ); ?>' ) {
				$( '.show_if_<?php echo esc_js( self::PRODUCT_TYPE ); ?>' ).show();
			}
		} );
		</script>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Is product a booking product?
	 *
	 * @param mixed $product Product or ID.
	 * @return bool
	 */
	public static function is_booking_product( $product ) {
		if ( is_numeric( $product ) ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product ) : null;
		}
		return $product && method_exists( $product, 'get_type' ) && self::PRODUCT_TYPE === $product->get_type();
	}

	/**
	 * Calculate price based on per-person counts.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $person_types Map [type_id => count].
	 * @return float
	 */
	public static function calculate_price( $product_id, array $person_types ) {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( ! $product ) {
			return 0.0;
		}
		$base = (float) $product->get_meta( '_booking_base_price' );
		if ( $base <= 0 ) {
			$base = (float) $product->get_price();
		}

		$all_types = get_option( 'wc_booking_calendar_person_types', array() );
		$total     = 0.0;
		foreach ( $person_types as $type_id => $count ) {
			$count = max( 0, (int) $count );
			if ( $count <= 0 ) {
				continue;
			}
			$adjustment = 0.0;
			foreach ( $all_types as $pt ) {
				if ( (int) $pt['id'] === (int) $type_id ) {
					$adjustment = (float) $pt['price'];
					break;
				}
			}
			$total += $count * max( 0, $base + $adjustment );
		}
		return round( $total, 2 );
	}
}
