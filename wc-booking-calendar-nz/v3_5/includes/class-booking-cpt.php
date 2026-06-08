/**
 * WC Booking Calendar - Booking CPT.
 *
 * @package WC_Booking_Calendar_NZ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Booking_Calendar_Booking_CPT
 *
 * Custom post type that stores individual bookings linked to WC orders.
 */
class WC_Booking_Calendar_Booking_CPT {

	const CPT_SLUG = 'wc_booking';

	/**
	 * Singleton.
	 *
	 * @var WC_Booking_Calendar_Booking_CPT|null
	 */
	private static $instance = null;

	/**
	 * Allowed statuses.
	 *
	 * @return string[]
	 */
	public static function get_statuses() {
		return array( 'pending', 'confirmed', 'cancelled', 'completed', 'refunded' );
	}

	/**
	 * Get singleton.
	 *
	 * @return WC_Booking_Calendar_Booking_CPT
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
		add_action( 'init', array( $this, 'register_statuses' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post_' . self::CPT_SLUG, array( $this, 'save_meta' ), 10, 2 );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		add_filter( 'manage_' . self::CPT_SLUG . '_posts_columns', array( $this, 'admin_columns' ) );
		add_action( 'manage_' . self::CPT_SLUG . '_posts_custom_column', array( $this, 'admin_column_content' ), 10, 2 );
	}

	/**
	 * Register post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => __( 'Bookings', 'wc-booking-calendar-nz' ),
			'singular_name'      => __( 'Booking', 'wc-booking-calendar-nz' ),
			'menu_name'          => __( 'Bookings', 'wc-booking-calendar-nz' ),
			'add_new'            => __( 'Add Booking', 'wc-booking-calendar-nz' ),
			'add_new_item'       => __( 'Add New Booking', 'wc-booking-calendar-nz' ),
			'edit_item'          => __( 'Edit Booking', 'wc-booking-calendar-nz' ),
			'new_item'           => __( 'New Booking', 'wc-booking-calendar-nz' ),
			'view_item'          => __( 'View Booking', 'wc-booking-calendar-nz' ),
			'search_items'       => __( 'Search Bookings', 'wc-booking-calendar-nz' ),
			'not_found'          => __( 'No bookings found.', 'wc-booking-calendar-nz' ),
			'not_found_in_trash' => __( 'No bookings in trash.', 'wc-booking-calendar-nz' ),
		);

		register_post_type(
			self::CPT_SLUG,
			array(
				'labels'              => $labels,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => true,
				'rest_base'           => 'wc-bookings',
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'menu_icon'           => 'dashicons-calendar-alt',
				'menu_position'       => 56,
				'exclude_from_search' => true,
			)
		);
	}

	/**
	 * Register custom post statuses.
	 *
	 * @return void
	 */
	public function register_statuses() {
		$statuses = array(
			'confirmed' => __( 'Confirmed', 'wc-booking-calendar-nz' ),
			'cancelled' => __( 'Cancelled', 'wc-booking-calendar-nz' ),
			'completed' => __( 'Completed', 'wc-booking-calendar-nz' ),
			'refunded'  => __( 'Refunded', 'wc-booking-calendar-nz' ),
		);
		foreach ( $statuses as $status => $label ) {
			register_post_status(
				$status,
				array(
					'label'                     => $label,
					'public'                    => false,
					'internal'                  => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					/* translators: %s: count */
					'label_count'               => _n_noop( "{$label} <span class=\"count\">(%s)</span>", "{$label} <span class=\"count\">(%s)</span>", 'wc-booking-calendar-nz' ),
				)
			);
		}
	}

	/* ------------------------------------------------------------------
	 * Meta boxes
	 * ------------------------------------------------------------------ */

	/**
	 * Register meta boxes.
	 *
	 * @return void
	 */
	public function register_meta_boxes() {
		add_meta_box( 'wc_booking_details', __( 'Booking Details', 'wc-booking-calendar-nz' ), array( $this, 'render_details_metabox' ), self::CPT_SLUG, 'normal', 'high' );
		add_meta_box( 'wc_booking_persons', __( 'Person Types', 'wc-booking-calendar-nz' ), array( $this, 'render_persons_metabox' ), self::CPT_SLUG, 'normal', 'default' );
		add_meta_box( 'wc_booking_status', __( 'Status & Actions', 'wc-booking-calendar-nz' ), array( $this, 'render_status_metabox' ), self::CPT_SLUG, 'side', 'high' );
	}

	/**
	 * Render details meta box.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public function render_details_metabox( $post ) {
		wp_nonce_field( 'wc_booking_meta', 'wc_booking_nonce' );
		$order_id    = (int) get_post_meta( $post->ID, '_booking_order_id', true );
		$product_id  = (int) get_post_meta( $post->ID, '_booking_product_id', true );
		$date        = get_post_meta( $post->ID, '_booking_date', true );
		$time        = get_post_meta( $post->ID, '_booking_time', true );
		$mode        = get_post_meta( $post->ID, '_booking_mode', true );
		$resource_id = (int) get_post_meta( $post->ID, '_booking_resource_id', true );
		$special     = get_post_meta( $post->ID, '_booking_special_requests', true );
		$limited     = get_post_meta( $post->ID, '_booking_limited_mobility', true );

		$product = $product_id ? get_post( $product_id ) : null;
		?>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Order', 'wc-booking-calendar-nz' ); ?></th>
				<td>
					<?php if ( $order_id ) : ?>
						<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $order_id . '&action=edit' ) ); ?>">#<?php echo esc_html( $order_id ); ?></a>
					<?php else : ?>
						<em><?php esc_html_e( '— none —', 'wc-booking-calendar-nz' ); ?></em>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Product', 'wc-booking-calendar-nz' ); ?></th>
				<td><?php echo $product ? esc_html( $product->post_title ) : '—'; ?></td>
			</tr>
			<tr>
				<th><label for="_booking_date"><?php esc_html_e( 'Date', 'wc-booking-calendar-nz' ); ?></label></th>
				<td><input type="date" name="_booking_date" id="_booking_date" value="<?php echo esc_attr( $date ); ?>"></td>
			</tr>
			<tr>
				<th><label for="_booking_time"><?php esc_html_e( 'Time', 'wc-booking-calendar-nz' ); ?></label></th>
				<td><input type="text" name="_booking_time" id="_booking_time" value="<?php echo esc_attr( $time ); ?>" placeholder="HH:MM-HH:MM"></td>
			</tr>
			<tr>
				<th><label for="_booking_mode"><?php esc_html_e( 'Mode', 'wc-booking-calendar-nz' ); ?></label></th>
				<td><input type="text" name="_booking_mode" id="_booking_mode" value="<?php echo esc_attr( $mode ); ?>"></td>
			</tr>
			<tr>
				<th><label for="_booking_resource_id"><?php esc_html_e( 'Resource', 'wc-booking-calendar-nz' ); ?></label></th>
				<td>
					<select name="_booking_resource_id" id="_booking_resource_id">
						<option value="0"><?php esc_html_e( '— None —', 'wc-booking-calendar-nz' ); ?></option>
						<?php foreach ( WC_Booking_Calendar_Resource_CPT::get_available_resources() as $r ) : ?>
							<option value="<?php echo esc_attr( $r->ID ); ?>" <?php selected( $resource_id, $r->ID ); ?>><?php echo esc_html( $r->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="_booking_limited_mobility"><?php esc_html_e( 'Limited Mobility', 'wc-booking-calendar-nz' ); ?></label></th>
				<td><input type="checkbox" name="_booking_limited_mobility" id="_booking_limited_mobility" value="yes" <?php checked( $limited, 'yes' ); ?>></td>
			</tr>
			<tr>
				<th><label for="_booking_special_requests"><?php esc_html_e( 'Special Requests', 'wc-booking-calendar-nz' ); ?></label></th>
				<td><textarea name="_booking_special_requests" id="_booking_special_requests" rows="3" class="large-text"><?php echo esc_textarea( $special ); ?></textarea></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render persons meta box.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public function render_persons_metabox( $post ) {
		$person_types = self::decode_person_types( get_post_meta( $post->ID, '_booking_person_types', true ) );
		$all_types    = get_option( 'wc_booking_calendar_person_types', array() );
		?>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Type', 'wc-booking-calendar-nz' ); ?></th>
					<th><?php esc_html_e( 'Count', 'wc-booking-calendar-nz' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $all_types as $type ) :
				$count = isset( $person_types[ $type['id'] ] ) ? (int) $person_types[ $type['id'] ] : 0;
				?>
				<tr>
					<td><?php echo esc_html( $type['name'] ); ?></td>
					<td><?php echo esc_html( $count ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render status meta box.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public function render_status_metabox( $post ) {
		?>
		<p><strong><?php esc_html_e( 'Status:', 'wc-booking-calendar-nz' ); ?></strong> <?php echo esc_html( $post->post_status ); ?></p>
		<p>
			<label for="wc_booking_new_status"><?php esc_html_e( 'Change status:', 'wc-booking-calendar-nz' ); ?></label>
			<select name="wc_booking_new_status" id="wc_booking_new_status">
				<?php foreach ( self::get_statuses() as $s ) : ?>
					<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $post->post_status, $s ); ?>><?php echo esc_html( $s ); ?></option>
				<?php endforeach; ?>
			</select>
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
		if ( ! isset( $_POST['wc_booking_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_POST['wc_booking_nonce'] ), 'wc_booking_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = array(
			'_booking_date'             => 'sanitize_text_field',
			'_booking_time'             => 'sanitize_text_field',
			'_booking_mode'             => 'sanitize_text_field',
			'_booking_resource_id'      => 'intval',
			'_booking_special_requests' => 'sanitize_textarea_field',
		);
		foreach ( $fields as $key => $sanitizer ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, call_user_func( $sanitizer, wp_unslash( $_POST[ $key ] ) ) );
			}
		}

		update_post_meta(
			$post_id,
			'_booking_limited_mobility',
			isset( $_POST['_booking_limited_mobility'] ) && 'yes' === $_POST['_booking_limited_mobility'] ? 'yes' : 'no'
		);

		// Status change.
		if ( isset( $_POST['wc_booking_new_status'] ) ) {
			$new_status = sanitize_text_field( wp_unslash( $_POST['wc_booking_new_status'] ) );
			if ( in_array( $new_status, self::get_statuses(), true ) && $new_status !== $post->post_status ) {
				remove_action( 'save_post_' . self::CPT_SLUG, array( $this, 'save_meta' ), 10 );
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => $new_status,
					)
				);
				add_action( 'save_post_' . self::CPT_SLUG, array( $this, 'save_meta' ), 10, 2 );

				// Side-effects.
				$availability = WC_Booking_Calendar_Availability_Manager::get_instance();
				if ( in_array( $new_status, array( 'cancelled', 'refunded' ), true ) ) {
					$availability->release_availability( $post_id );
				}
			}
		}
	}

	/* ------------------------------------------------------------------
	 * REST
	 * ------------------------------------------------------------------ */

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			'wc-booking-calendar/v1',
			'/bookings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_list_bookings' ),
				'permission_callback' => array( $this, 'check_rest_permissions' ),
			)
		);
		register_rest_route(
			'wc-booking-calendar/v1',
			'/bookings/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_get_booking' ),
				'permission_callback' => array( $this, 'check_rest_permissions' ),
			)
		);
	}

	/**
	 * Check REST permissions.
	 *
	 * @return bool
	 */
	public function check_rest_permissions() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * REST list callback.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_list_bookings( $request ) {
		$args = array(
			'post_type'      => self::CPT_SLUG,
			'posts_per_page' => min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 20 ) ),
			'post_status'    => 'any',
		);
		$posts = get_posts( $args );
		$data  = array();
		foreach ( $posts as $p ) {
			$data[] = self::get_booking_data( $p->ID );
		}
		return rest_ensure_response( $data );
	}

	/**
	 * REST get one.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_get_booking( $request ) {
		$id = (int) $request->get_param( 'id' );
		$p  = get_post( $id );
		if ( ! $p || self::CPT_SLUG !== $p->post_type ) {
			return new WP_Error( 'not_found', __( 'Booking not found.', 'wc-booking-calendar-nz' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( self::get_booking_data( $id ) );
	}

	/* ------------------------------------------------------------------
	 * Admin columns
	 * ------------------------------------------------------------------ */

	/**
	 * Admin columns.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public function admin_columns( $cols ) {
		return array(
			'cb'           => $cols['cb'] ?? '',
			'title'        => __( 'Booking', 'wc-booking-calendar-nz' ),
			'booking_date' => __( 'Date', 'wc-booking-calendar-nz' ),
			'booking_time' => __( 'Time', 'wc-booking-calendar-nz' ),
			'product'      => __( 'Product', 'wc-booking-calendar-nz' ),
			'resource'     => __( 'Resource', 'wc-booking-calendar-nz' ),
			'persons'      => __( 'People', 'wc-booking-calendar-nz' ),
			'status'       => __( 'Status', 'wc-booking-calendar-nz' ),
		);
	}

	/**
	 * Column content.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post.
	 * @return void
	 */
	public function admin_column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'booking_date':
				echo esc_html( get_post_meta( $post_id, '_booking_date', true ) );
				break;
			case 'booking_time':
				echo esc_html( get_post_meta( $post_id, '_booking_time', true ) );
				break;
			case 'product':
				$pid = (int) get_post_meta( $post_id, '_booking_product_id', true );
				echo $pid ? esc_html( get_the_title( $pid ) ) : '—';
				break;
			case 'resource':
				$rid = (int) get_post_meta( $post_id, '_booking_resource_id', true );
				echo $rid ? esc_html( get_the_title( $rid ) ) : '—';
				break;
			case 'persons':
				echo (int) get_post_meta( $post_id, '_booking_person_count', true );
				break;
			case 'status':
				$post = get_post( $post_id );
				echo esc_html( $post ? $post->post_status : '' );
				break;
		}
	}

	/* ------------------------------------------------------------------
	 * Helper API
	 * ------------------------------------------------------------------ */

	/**
	 * Decode person types from meta string-or-array.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int,int>
	 */
	public static function decode_person_types( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'intval', $value );
		}
		if ( is_string( $value ) && '' !== $value ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) {
				return array_map( 'intval', $decoded );
			}
		}
		return array();
	}

	/**
	 * Generate a deterministic unique booking ID/handle.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function generate_booking_id( $post_id = 0 ) {
		return 'BK-' . strtoupper( substr( md5( $post_id . microtime( true ) . wp_rand() ), 0, 8 ) );
	}

	/**
	 * Aggregate booking data.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function get_booking_data( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}
		return array(
			'id'                => $post_id,
			'title'             => $post->post_title,
			'status'            => $post->post_status,
			'created'           => $post->post_date_gmt,
			'order_id'          => (int) get_post_meta( $post_id, '_booking_order_id', true ),
			'product_id'        => (int) get_post_meta( $post_id, '_booking_product_id', true ),
			'date'              => get_post_meta( $post_id, '_booking_date', true ),
			'time'              => get_post_meta( $post_id, '_booking_time', true ),
			'mode'              => get_post_meta( $post_id, '_booking_mode', true ),
			'resource_id'       => (int) get_post_meta( $post_id, '_booking_resource_id', true ),
			'person_types'      => self::decode_person_types( get_post_meta( $post_id, '_booking_person_types', true ) ),
			'person_count'      => (int) get_post_meta( $post_id, '_booking_person_count', true ),
			'special_requests'  => get_post_meta( $post_id, '_booking_special_requests', true ),
			'limited_mobility'  => get_post_meta( $post_id, '_booking_limited_mobility', true ),
			'total_price'       => (float) get_post_meta( $post_id, '_booking_total_price', true ),
		);
	}

	/**
	 * Query bookings by date range.
	 *
	 * @param string $start Start (Y-m-d).
	 * @param string $end   End (Y-m-d).
	 * @return WP_Post[]
	 */
	public static function get_bookings_by_date_range( $start, $end ) {
		return get_posts(
			array(
				'post_type'      => self::CPT_SLUG,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'     => '_booking_date',
						'value'   => array( $start, $end ),
						'compare' => 'BETWEEN',
						'type'    => 'DATE',
					),
				),
			)
		);
	}

	/**
	 * Query bookings by product.
	 *
	 * @param int $product_id Product.
	 * @return WP_Post[]
	 */
	public static function get_bookings_by_product( $product_id ) {
		return get_posts(
			array(
				'post_type'      => self::CPT_SLUG,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => '_booking_product_id',
						'value' => (int) $product_id,
					),
				),
			)
		);
	}

	/**
	 * Create a booking from an order line item.
	 *
	 * @param WC_Order_Item_Product $item  Order line.
	 * @param WC_Order              $order Order.
	 * @return int Booking post ID or 0.
	 */
	public static function create_booking_from_order( $item, $order ) {
		$product = method_exists( $item, 'get_product' ) ? $item->get_product() : null;
		if ( ! $product ) {
			return 0;
		}

		$date = $item->get_meta( '_booking_date' );
		$time = $item->get_meta( '_booking_time' );
		if ( ! $date || ! $time ) {
			return 0;
		}

		$booking_id = wp_insert_post(
			array(
				'post_type'   => self::CPT_SLUG,
				'post_title'  => sprintf(
					/* translators: 1: order id 2: product 3: date */
					__( 'Booking #%1$d — %2$s — %3$s', 'wc-booking-calendar-nz' ),
					$order->get_id(),
					$product->get_name(),
					$date
				),
				'post_status' => 'pending',
				'post_author' => $order->get_user_id(),
			),
			true
		);
		if ( is_wp_error( $booking_id ) || ! $booking_id ) {
			return 0;
		}

		$person_types = self::decode_person_types( $item->get_meta( '_booking_person_types' ) );
		$person_count = array_sum( $person_types );

		update_post_meta( $booking_id, '_booking_order_id', $order->get_id() );
		update_post_meta( $booking_id, '_booking_order_item_id', $item->get_id() );
		update_post_meta( $booking_id, '_booking_product_id', $product->get_id() );
		update_post_meta( $booking_id, '_booking_date', $date );
		update_post_meta( $booking_id, '_booking_time', $time );
		update_post_meta( $booking_id, '_booking_mode', (string) $item->get_meta( '_booking_mode' ) );
		update_post_meta( $booking_id, '_booking_resource_id', (int) $item->get_meta( '_booking_resource_id' ) );
		update_post_meta( $booking_id, '_booking_person_types', wp_json_encode( $person_types ) );
		update_post_meta( $booking_id, '_booking_person_count', $person_count );
		update_post_meta( $booking_id, '_booking_limited_mobility', (string) $item->get_meta( '_booking_limited_mobility' ) );
		update_post_meta( $booking_id, '_booking_special_requests', (string) $item->get_meta( '_booking_special_requests' ) );
		update_post_meta( $booking_id, '_booking_total_price', (float) $item->get_total() );
		update_post_meta( $booking_id, '_booking_handle', self::generate_booking_id( $booking_id ) );

		$item->add_meta_data( '_booking_id', $booking_id, true );
		$item->save_meta_data();

		do_action( 'wc_booking_calendar_booking_created', $booking_id, $item, $order );
		return (int) $booking_id;
	}
}