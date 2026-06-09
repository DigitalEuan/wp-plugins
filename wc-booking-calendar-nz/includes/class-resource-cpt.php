<?php
/**
 * WC Booking Calendar - Resource CPT.
 *
 * Custom post type for bookable resources (guides, equipment, rooms, etc.).
 *
 * @package WC_Booking_Calendar_NZ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Booking_Calendar_Resource_CPT
 */
class WC_Booking_Calendar_Resource_CPT {

	const CPT_SLUG = 'wc_booking_resource';

	/**
	 * Singleton instance.
	 *
	 * @var WC_Booking_Calendar_Resource_CPT|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return WC_Booking_Calendar_Resource_CPT
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
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post_' . self::CPT_SLUG, array( $this, 'save_meta' ), 10, 2 );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Admin columns.
		add_filter( 'manage_' . self::CPT_SLUG . '_posts_columns', array( $this, 'admin_columns' ) );
		add_action( 'manage_' . self::CPT_SLUG . '_posts_custom_column', array( $this, 'admin_column_content' ), 10, 2 );
	}

	/**
	 * Register the post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => __( 'Resources', 'wc-booking-calendar-nz' ),
			'singular_name'      => __( 'Resource', 'wc-booking-calendar-nz' ),
			'menu_name'          => __( 'Resources', 'wc-booking-calendar-nz' ),
			'add_new'            => __( 'Add Resource', 'wc-booking-calendar-nz' ),
			'add_new_item'       => __( 'Add New Resource', 'wc-booking-calendar-nz' ),
			'edit_item'          => __( 'Edit Resource', 'wc-booking-calendar-nz' ),
			'new_item'           => __( 'New Resource', 'wc-booking-calendar-nz' ),
			'view_item'          => __( 'View Resource', 'wc-booking-calendar-nz' ),
			'search_items'       => __( 'Search Resources', 'wc-booking-calendar-nz' ),
			'not_found'          => __( 'No resources found.', 'wc-booking-calendar-nz' ),
			'not_found_in_trash' => __( 'No resources in trash.', 'wc-booking-calendar-nz' ),
		);

		register_post_type(
			self::CPT_SLUG,
			array(
				'labels'              => $labels,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=wc_booking',
				'show_in_rest'        => true,
				'rest_base'           => 'wc-booking-resources',
				'supports'            => array( 'title', 'editor', 'thumbnail' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'menu_icon'           => 'dashicons-businessman',
				'exclude_from_search' => true,
			)
		);
	}

	/**
	 * Register meta boxes.
	 *
	 * @return void
	 */
	public function register_meta_boxes() {
		add_meta_box(
			'wc_booking_resource_details',
			__( 'Resource Details', 'wc-booking-calendar-nz' ),
			array( $this, 'render_details_metabox' ),
			self::CPT_SLUG,
			'normal',
			'default'
		);
		add_meta_box(
			'wc_booking_resource_schedule',
			__( 'Availability & Schedule', 'wc-booking-calendar-nz' ),
			array( $this, 'render_schedule_metabox' ),
			self::CPT_SLUG,
			'normal',
			'default'
		);
		add_meta_box(
			'wc_booking_resource_pricing',
			__( 'Pricing', 'wc-booking-calendar-nz' ),
			array( $this, 'render_pricing_metabox' ),
			self::CPT_SLUG,
			'side',
			'default'
		);
	}

	/**
	 * Render details meta box.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public function render_details_metabox( $post ) {
		wp_nonce_field( 'wc_booking_resource_meta', 'wc_booking_resource_nonce' );
		$email     = get_post_meta( $post->ID, '_resource_email', true );
		$phone     = get_post_meta( $post->ID, '_resource_phone', true );
		$bio       = get_post_meta( $post->ID, '_resource_bio', true );
		$available = get_post_meta( $post->ID, '_resource_available', true );
		if ( '' === $available ) {
			$available = '1';
		}
		?>
		<table class="form-table">
			<tr>
				<th><label for="_resource_email"><?php esc_html_e( 'Email', 'wc-booking-calendar-nz' ); ?></label></th>
				<td><input type="email" name="_resource_email" id="_resource_email" value="<?php echo esc_attr( $email ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="_resource_phone"><?php esc_html_e( 'Phone', 'wc-booking-calendar-nz' ); ?></label></th>
				<td><input type="text" name="_resource_phone" id="_resource_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="_resource_bio"><?php esc_html_e( 'Bio / Notes', 'wc-booking-calendar-nz' ); ?></label></th>
				<td><textarea name="_resource_bio" id="_resource_bio" rows="3" class="large-text"><?php echo esc_textarea( $bio ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="_resource_available"><?php esc_html_e( 'Status', 'wc-booking-calendar-nz' ); ?></label></th>
				<td>
					<select name="_resource_available" id="_resource_available">
						<option value="1" <?php selected( $available, '1' ); ?>><?php esc_html_e( 'Available', 'wc-booking-calendar-nz' ); ?></option>
						<option value="0" <?php selected( $available, '0' ); ?>><?php esc_html_e( 'Unavailable', 'wc-booking-calendar-nz' ); ?></option>
					</select>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render schedule meta box.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public function render_schedule_metabox( $post ) {
		$schedule = get_post_meta( $post->ID, '_resource_schedule', true );
		if ( ! is_array( $schedule ) ) {
			$schedule = array();
		}
		$days = array(
			'monday'    => __( 'Monday', 'wc-booking-calendar-nz' ),
			'tuesday'   => __( 'Tuesday', 'wc-booking-calendar-nz' ),
			'wednesday' => __( 'Wednesday', 'wc-booking-calendar-nz' ),
			'thursday'  => __( 'Thursday', 'wc-booking-calendar-nz' ),
			'friday'    => __( 'Friday', 'wc-booking-calendar-nz' ),
			'saturday'  => __( 'Saturday', 'wc-booking-calendar-nz' ),
			'sunday'    => __( 'Sunday', 'wc-booking-calendar-nz' ),
		);
		?>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Day', 'wc-booking-calendar-nz' ); ?></th>
					<th><?php esc_html_e( 'Available', 'wc-booking-calendar-nz' ); ?></th>
					<th><?php esc_html_e( 'Start', 'wc-booking-calendar-nz' ); ?></th>
					<th><?php esc_html_e( 'End', 'wc-booking-calendar-nz' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $days as $key => $label ) :
				$enabled = ! empty( $schedule[ $key ]['enabled'] );
				$start   = $schedule[ $key ]['start'] ?? '09:00';
				$end     = $schedule[ $key ]['end'] ?? '17:00';
				?>
				<tr>
					<td><?php echo esc_html( $label ); ?></td>
					<td><input type="checkbox" name="_resource_schedule[<?php echo esc_attr( $key ); ?>][enabled]" value="1" <?php checked( $enabled ); ?>></td>
					<td><input type="time" name="_resource_schedule[<?php echo esc_attr( $key ); ?>][start]" value="<?php echo esc_attr( $start ); ?>"></td>
					<td><input type="time" name="_resource_schedule[<?php echo esc_attr( $key ); ?>][end]" value="<?php echo esc_attr( $end ); ?>"></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render pricing meta box.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public function render_pricing_metabox( $post ) {
		$hourly = get_post_meta( $post->ID, '_resource_hourly_rate', true );
		$flat   = get_post_meta( $post->ID, '_resource_flat_fee', true );
		?>
		<p>
			<label for="_resource_hourly_rate"><?php esc_html_e( 'Hourly Rate', 'wc-booking-calendar-nz' ); ?></label>
			<input type="number" step="0.01" min="0" name="_resource_hourly_rate" id="_resource_hourly_rate" value="<?php echo esc_attr( $hourly ); ?>" class="widefat">
		</p>
		<p>
			<label for="_resource_flat_fee"><?php esc_html_e( 'Flat Fee', 'wc-booking-calendar-nz' ); ?></label>
			<input type="number" step="0.01" min="0" name="_resource_flat_fee" id="_resource_flat_fee" value="<?php echo esc_attr( $flat ); ?>" class="widefat">
		</p>
		<?php
	}

	/**
	 * Save meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 * @return void
	 */
	public function save_meta( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['wc_booking_resource_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_POST['wc_booking_resource_nonce'] ), 'wc_booking_resource_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = array(
			'_resource_email'       => 'sanitize_email',
			'_resource_phone'       => 'sanitize_text_field',
			'_resource_bio'         => 'sanitize_textarea_field',
			'_resource_available'   => 'sanitize_text_field',
			'_resource_hourly_rate' => 'floatval',
			'_resource_flat_fee'    => 'floatval',
		);
		foreach ( $fields as $field => $sanitizer ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, $field, call_user_func( $sanitizer, wp_unslash( $_POST[ $field ] ) ) );
			}
		}

		// Schedule.
		if ( isset( $_POST['_resource_schedule'] ) && is_array( $_POST['_resource_schedule'] ) ) {
			$raw     = wp_unslash( $_POST['_resource_schedule'] );
			$clean   = array();
			foreach ( $raw as $day => $cfg ) {
				$day = sanitize_key( $day );
				$clean[ $day ] = array(
					'enabled' => ! empty( $cfg['enabled'] ) ? 1 : 0,
					'start'   => isset( $cfg['start'] ) ? sanitize_text_field( $cfg['start'] ) : '',
					'end'     => isset( $cfg['end'] ) ? sanitize_text_field( $cfg['end'] ) : '',
				);
			}
			update_post_meta( $post_id, '_resource_schedule', $clean );
		}
	}

	/**
	 * Register REST routes (read-only).
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			'wc-booking-calendar/v1',
			'/resources',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_list_resources' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * REST callback — list resources.
	 *
	 * @return WP_REST_Response
	 */
	public function rest_list_resources() {
		$resources = self::get_available_resources();
		$data      = array();
		foreach ( $resources as $r ) {
			$data[] = array(
				'id'    => $r->ID,
				'title' => $r->post_title,
			);
		}
		return rest_ensure_response( $data );
	}

	/* ------------------------------------------------------------------
	 * Helper API
	 * ------------------------------------------------------------------ */

	/**
	 * Get all available resources.
	 *
	 * @return WP_Post[]
	 */
	public static function get_available_resources() {
		return get_posts(
			array(
				'post_type'      => self::CPT_SLUG,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => array(
					array(
						'key'     => '_resource_available',
						'value'   => '1',
						'compare' => '=',
					),
				),
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * Is resource available for date.
	 *
	 * @param int    $resource_id Resource ID.
	 * @param string $date        Date.
	 * @return bool
	 */
	public static function is_resource_available( $resource_id, $date ) {
		$status = get_post_meta( $resource_id, '_resource_available', true );
		if ( '0' === $status ) {
			return false;
		}
		$schedule = get_post_meta( $resource_id, '_resource_schedule', true );
		if ( is_array( $schedule ) ) {
			$day = strtolower( gmdate( 'l', strtotime( $date ) ) );
			if ( isset( $schedule[ $day ] ) && empty( $schedule[ $day ]['enabled'] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Admin columns.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public function admin_columns( $cols ) {
		$new = array(
			'cb'        => $cols['cb'] ?? '',
			'title'     => __( 'Name', 'wc-booking-calendar-nz' ),
			'email'     => __( 'Email', 'wc-booking-calendar-nz' ),
			'phone'     => __( 'Phone', 'wc-booking-calendar-nz' ),
			'available' => __( 'Status', 'wc-booking-calendar-nz' ),
			'date'      => __( 'Date', 'wc-booking-calendar-nz' ),
		);
		return $new;
	}

	/**
	 * Admin column content.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post.
	 * @return void
	 */
	public function admin_column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'email':
				echo esc_html( get_post_meta( $post_id, '_resource_email', true ) );
				break;
			case 'phone':
				echo esc_html( get_post_meta( $post_id, '_resource_phone', true ) );
				break;
			case 'available':
				$status = get_post_meta( $post_id, '_resource_available', true );
				echo '1' === $status || '' === $status
					? '<span style="color:#46b450;">●</span> ' . esc_html__( 'Available', 'wc-booking-calendar-nz' )
					: '<span style="color:#dc3232;">●</span> ' . esc_html__( 'Unavailable', 'wc-booking-calendar-nz' );
				break;
		}
	}
}