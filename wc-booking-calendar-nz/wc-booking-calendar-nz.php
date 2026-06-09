<?php
/**
 * Plugin Name:       WC Booking Calendar NZ
 * Plugin URI:        https://digitaleuan.com
 * Description:       Advanced bookable products for WooCommerce with configurable time slots, resources, person types, conditional logic, and availability management. Built for New Zealand businesses.
 * Version:           1.1.1
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            E Craig
 * Author URI:        https://digitaleuan.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wc-booking-calendar-nz
 * Domain Path:       /languages
 * WC requires at least: 9.0
 * WC tested up to:   9.4
 *
 * @package WC_Booking_Calendar_NZ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'WC_BOOKING_CALENDAR_VERSION', '1.1.1' );
define( 'WC_BOOKING_CALENDAR_PLUGIN_FILE', __FILE__ );
define( 'WC_BOOKING_CALENDAR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_BOOKING_CALENDAR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_BOOKING_CALENDAR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WC_BOOKING_CALENDAR_DB_VERSION', '1.1.1' );

/**
 * Main plugin bootstrap class.
 *
 * Responsible for: activation/deactivation, requirements check, file loading,
 * and instantiating subsystems (settings, frontend, WooCommerce integration).
 */
final class WC_Booking_Calendar_NZ {

	/**
	 * Singleton instance.
	 *
	 * @var WC_Booking_Calendar_NZ|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return WC_Booking_Calendar_NZ
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor (private — use get_instance()).
	 */
	private function __construct() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 10 );

		// HPOS / Cart and Checkout Blocks compatibility declarations.
		add_action( 'before_woocommerce_init', array( $this, 'declare_woocommerce_compatibility' ) );
	}

	/**
	 * Declare compatibility with WooCommerce features.
	 *
	 * @return void
	 */
	public function declare_woocommerce_compatibility() {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, false );
		}
	}

	/**
	 * Activation hook callback.
	 *
	 * @return void
	 */
	public function activate() {
		if ( ! self::check_requirements( true ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die(
				esc_html__( 'WC Booking Calendar NZ requires WordPress 6.0+, PHP 8.0+ and WooCommerce 9.0+.', 'wc-booking-calendar-nz' ),
				esc_html__( 'Plugin Activation Error', 'wc-booking-calendar-nz' ),
				array( 'back_link' => true )
			);
		}

		// Load classes needed for activation.
		$this->include_files();

		$this->create_database_tables();
		$this->set_default_options();

		// Register CPTs so rewrite rules are correct after flush.
		if ( class_exists( 'WC_Booking_Calendar_Booking_CPT' ) ) {
			WC_Booking_Calendar_Booking_CPT::get_instance()->register_post_type();
		}
		if ( class_exists( 'WC_Booking_Calendar_Resource_CPT' ) ) {
			WC_Booking_Calendar_Resource_CPT::get_instance()->register_post_type();
		}

		flush_rewrite_rules();

		update_option( 'wc_booking_calendar_db_version', WC_BOOKING_CALENDAR_DB_VERSION );
		do_action( 'wc_booking_calendar_nz_activated' );
	}

	/**
	 * Deactivation hook callback.
	 *
	 * @return void
	 */
	public function deactivate() {
		flush_rewrite_rules();
		delete_transient( 'wc_booking_calendar_version' );
		do_action( 'wc_booking_calendar_nz_deactivated' );
	}

	/**
	 * Initialize the plugin (runs on plugins_loaded).
	 *
	 * @return void
	 */
	public function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		if ( ! self::check_requirements( false ) ) {
			add_action( 'admin_notices', array( $this, 'requirements_not_met_notice' ) );
			return;
		}

		load_plugin_textdomain(
			'wc-booking-calendar-nz',
			false,
			dirname( WC_BOOKING_CALENDAR_PLUGIN_BASENAME ) . '/languages'
		);

		$this->include_files();
		$this->maybe_upgrade();
		$this->init_components();

		do_action( 'wc_booking_calendar_nz_loaded' );
	}

	/**
	 * Run upgrade routines if DB version is stale.
	 *
	 * @return void
	 */
	private function maybe_upgrade() {
		$installed = get_option( 'wc_booking_calendar_db_version', '0' );
		if ( version_compare( $installed, WC_BOOKING_CALENDAR_DB_VERSION, '<' ) ) {
			$this->create_database_tables();
			$this->set_default_options();
			update_option( 'wc_booking_calendar_db_version', WC_BOOKING_CALENDAR_DB_VERSION );
		}
	}

	/**
	 * Check plugin runtime requirements.
	 *
	 * @param bool $strict If true, fails when WooCommerce isn't loaded yet.
	 * @return bool
	 */
	public static function check_requirements( $strict = false ) {
		if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
			return false;
		}
		if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
			return false;
		}
		if ( $strict && ! class_exists( 'WooCommerce' ) ) {
			return false;
		}
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '9.0', '<' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Create custom database tables.
	 *
	 * @return void
	 */
	private function create_database_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$bookings_table  = $wpdb->prefix . 'wc_booking_calendar_bookings';
		$availability    = $wpdb->prefix . 'wc_booking_calendar_availability';

		$sql_bookings = "CREATE TABLE {$bookings_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			order_item_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			booking_post_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			booking_date DATE NOT NULL,
			booking_time VARCHAR(100) NOT NULL DEFAULT '',
			booking_time_start TIME DEFAULT NULL,
			booking_time_end TIME DEFAULT NULL,
			booking_mode VARCHAR(100) NOT NULL DEFAULT '',
			resource_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			person_types LONGTEXT NULL,
			person_count INT(11) UNSIGNED NOT NULL DEFAULT 0,
			total_price DECIMAL(12,2) NOT NULL DEFAULT 0,
			status VARCHAR(50) NOT NULL DEFAULT 'pending',
			special_requests TEXT NULL,
			limited_mobility TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY product_id (product_id),
			KEY booking_date (booking_date),
			KEY resource_id (resource_id),
			KEY status (status),
			KEY product_date (product_id, booking_date)
		) {$charset_collate};";

		$sql_availability = "CREATE TABLE {$availability} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			resource_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			availability_date DATE NOT NULL,
			day_of_week TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			slot_start TIME DEFAULT NULL,
			slot_end TIME DEFAULT NULL,
			capacity INT(11) UNSIGNED NOT NULL DEFAULT 0,
			booked_count INT(11) UNSIGNED NOT NULL DEFAULT 0,
			is_blocked TINYINT(1) NOT NULL DEFAULT 0,
			block_reason VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY resource_id (resource_id),
			KEY availability_date (availability_date),
			KEY product_date_slot (product_id, availability_date, slot_start, slot_end)
		) {$charset_collate};";

		dbDelta( $sql_bookings );
		dbDelta( $sql_availability );
	}

	/**
	 * Set default plugin options.
	 *
	 * @return void
	 */
	private function set_default_options() {
		$defaults = array(
			'wc_booking_calendar_time_slots'        => array(
				array(
					'id'      => 1,
					'name'    => 'Morning',
					'start'   => '09:00',
					'end'     => '12:00',
					'enabled' => 1,
				),
				array(
					'id'      => 2,
					'name'    => 'Afternoon',
					'start'   => '13:00',
					'end'     => '17:00',
					'enabled' => 1,
				),
			),
			'wc_booking_calendar_days_of_week'      => array(
				'monday'    => 1,
				'tuesday'   => 1,
				'wednesday' => 1,
				'thursday'  => 1,
				'friday'    => 1,
				'saturday'  => 0,
				'sunday'    => 0,
			),
			'wc_booking_calendar_booking_modes'     => array(
				array(
					'id'             => 1,
					'name'           => 'Guided Tour',
					'description'    => 'Full-day guided experience',
					'full_day_block' => 1,
					'show_addons'    => 1,
					'max_per_slot'   => 1,
				),
				array(
					'id'             => 2,
					'name'           => 'Self-Directed Walk',
					'description'    => 'Self-guided walk',
					'full_day_block' => 0,
					'show_addons'    => 0,
					'max_per_slot'   => 50,
				),
			),
			'wc_booking_calendar_person_types'      => array(
				array(
					'id'      => 1,
					'name'    => 'Adult',
					'age_min' => 15,
					'age_max' => 120,
					'price'   => 0,
				),
				array(
					'id'      => 2,
					'name'    => 'Child (5-14 years)',
					'age_min' => 5,
					'age_max' => 14,
					'price'   => -10,
				),
				array(
					'id'      => 3,
					'name'    => 'Under 5',
					'age_min' => 0,
					'age_max' => 4,
					'price'   => 0,
				),
			),
			'wc_booking_calendar_gst_inclusive'     => 'yes',
			'wc_booking_calendar_min_group_size'    => 1,
			'wc_booking_calendar_max_group_size'    => 50,
			'wc_booking_calendar_lead_time_hours'   => 24,
			'wc_booking_calendar_lead_time'         => 1,
			'wc_booking_calendar_advance_window'    => 365,
			'wc_booking_calendar_advance_days'      => 365,
			'wc_booking_calendar_blackout_dates'    => array(),
			'wc_booking_calendar_morning_tea_price' => 10,
			'wc_booking_calendar_timezone'          => 'Pacific/Auckland',
			'wc_booking_calendar_notifications'     => array(
				'confirmation' => 1,
				'reminder'     => 1,
				'cancellation' => 1,
			),
			'wc_booking_calendar_advanced'          => array(
				'peak_days'        => array( 'Saturday', 'Sunday' ),
				'peak_multiplier'  => 1.0,
				'blackout_dates'   => array(),
				'seasonal_pricing' => array(),
			),
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}

	/**
	 * Include required files.
	 *
	 * @return void
	 */
	private function include_files() {
		$includes = WC_BOOKING_CALENDAR_PLUGIN_DIR . 'includes/';

		$files = array(
			'class-availability-manager.php',
			'class-resource-cpt.php',
			'class-booking-cpt.php',
			'class-booking-product.php',
			'class-frontend-handler.php',
			'class-cart.php',
			'class-order.php',
			'class-admin-settings.php',
			'hooks.php',
		);

		foreach ( $files as $file ) {
			$path = $includes . $file;
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}

		if ( is_admin() ) {
			$admin = WC_BOOKING_CALENDAR_PLUGIN_DIR . 'admin/class-admin.php';
			if ( file_exists( $admin ) ) {
				require_once $admin;
			}
		}
	}

	/**
	 * Initialize subsystems.
	 *
	 * @return void
	 */
	private function init_components() {
		// Core (always).
		if ( class_exists( 'WC_Booking_Calendar_Availability_Manager' ) ) {
			WC_Booking_Calendar_Availability_Manager::get_instance();
		}
		if ( class_exists( 'WC_Booking_Calendar_Resource_CPT' ) ) {
			WC_Booking_Calendar_Resource_CPT::get_instance();
		}
		if ( class_exists( 'WC_Booking_Calendar_Booking_CPT' ) ) {
			WC_Booking_Calendar_Booking_CPT::get_instance();
		}
		if ( class_exists( 'WC_Booking_Calendar_Product' ) ) {
			WC_Booking_Calendar_Product::get_instance();
		}
		if ( class_exists( 'WC_Booking_Calendar_Cart' ) ) {
			WC_Booking_Calendar_Cart::get_instance();
		}
		if ( class_exists( 'WC_Booking_Calendar_Order' ) ) {
			WC_Booking_Calendar_Order::get_instance();
		}
		if ( class_exists( 'WC_Booking_Calendar_Frontend_Handler' ) ) {
			WC_Booking_Calendar_Frontend_Handler::get_instance();
		}

		// Admin only.
		if ( is_admin() ) {
			if ( class_exists( 'WC_Booking_Calendar_Admin_Settings' ) ) {
				WC_Booking_Calendar_Admin_Settings::get_instance();
			}
			if ( class_exists( 'WC_Booking_Calendar_Admin' ) ) {
				WC_Booking_Calendar_Admin::get_instance();
			}
		}

		// Hooks file (procedural).
		if ( function_exists( 'wc_booking_calendar_register_hooks' ) ) {
			wc_booking_calendar_register_hooks();
		}
	}

	/**
	 * WooCommerce missing admin notice.
	 *
	 * @return void
	 */
	public function woocommerce_missing_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php esc_html_e( 'WC Booking Calendar NZ requires WooCommerce to be installed and active.', 'wc-booking-calendar-nz' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Generic requirements-not-met admin notice.
	 *
	 * @return void
	 */
	public function requirements_not_met_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php esc_html_e( 'WC Booking Calendar NZ requires WordPress 6.0+, PHP 8.0+ and WooCommerce 9.0+.', 'wc-booking-calendar-nz' ); ?>
			</p>
		</div>
		<?php
	}
}

/**
 * Get the main plugin instance.
 *
 * @return WC_Booking_Calendar_NZ
 */
function wc_booking_calendar_nz() {
	return WC_Booking_Calendar_NZ::get_instance();
}

// Bootstrap.
wc_booking_calendar_nz();