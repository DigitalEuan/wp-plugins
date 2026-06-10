<?php
/**
 * WC Booking Calendar - Booking Form Template
 *
 * @package WC_Booking_Calendar_NZ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

$lead_time_days     = (int) get_option( 'wc_booking_calendar_lead_time', 1 );
$max_advance        = (int) get_option( 'wc_booking_calendar_advance_window', 365 );
$min_date           = $lead_time_days > 0 ? date( 'Y-m-d', strtotime( "+{$lead_time_days} days" ) ) : '';
$max_date           = $max_advance > 0 ? date( 'Y-m-d', strtotime( "+{$max_advance} days" ) ) : '';
$booking_modes      = isset( $booking_modes ) ? (array) $booking_modes : (array) get_option( 'wc_booking_calendar_booking_modes', array() );
$booking_addons     = isset( $booking_addons ) ? (array) $booking_addons : (array) get_option( 'wc_booking_calendar_addons', array() );
$booking_addons     = array_values( array_filter( $booking_addons, static function( $addon ) {
	return is_array( $addon ) && ! empty( $addon['label'] ) && ( ! array_key_exists( 'enabled', $addon ) || ! empty( $addon['enabled'] ) );
} ) );
$deposit_percentage = isset( $deposit_percentage ) ? (int) $deposit_percentage : (int) get_option( 'wc_booking_calendar_deposit_percentage', 50 );
$person_types       = isset( $person_types ) ? (array) $person_types : (array) get_option( 'wc_booking_calendar_person_types', array() );
$resources          = isset( $resources ) ? (array) $resources : array();
$requires_resource  = isset( $requires_resource ) ? (bool) $requires_resource : false;

if ( empty( $booking_modes ) ) {
	$booking_modes = array(
		array(
			'id'             => 1,
			'key'            => 'guided',
			'name'           => __( 'Guided Tour', 'wc-booking-calendar-nz' ),
			'description'    => '',
			'full_day_block' => 1,
			'show_addons'    => 1,
			'max_per_slot'   => 1,
		),
		array(
			'id'             => 2,
			'key'            => 'self',
			'name'           => __( 'Self-Directed Walk', 'wc-booking-calendar-nz' ),
			'description'    => '',
			'full_day_block' => 0,
			'show_addons'    => 0,
			'max_per_slot'   => 50,
		),
	);
}

$default_mode       = reset( $booking_modes );
$selected_mode_key  = ! empty( $default_mode['key'] ) ? sanitize_title( $default_mode['key'] ) : sanitize_title( $default_mode['name'] ?? 'guided' );
$current_desc       = isset( $default_mode['description'] ) ? (string) $default_mode['description'] : '';
$mode_payload       = array();
foreach ( $booking_modes as $mode ) {
	$key = ! empty( $mode['key'] ) ? sanitize_title( $mode['key'] ) : sanitize_title( $mode['name'] ?? '' );
	$mode_payload[] = array(
		'key'            => $key,
		'name'           => $mode['name'] ?? '',
		'description'    => $mode['description'] ?? '',
		'show_addons'    => ! empty( $mode['show_addons'] ),
		'full_day_block' => ! empty( $mode['full_day_block'] ),
	);
}
$addon_payload = array();
foreach ( $booking_addons as $addon ) {
	$addon_payload[] = array(
		'key'        => ! empty( $addon['key'] ) ? sanitize_title( $addon['key'] ) : sanitize_title( $addon['label'] ?? '' ),
		'label'      => $addon['label'] ?? '',
		'price'      => isset( $addon['price'] ) ? (float) $addon['price'] : 0,
		'per_person' => ! empty( $addon['per_person'] ),
		'enabled'    => ! array_key_exists( 'enabled', $addon ) || ! empty( $addon['enabled'] ),
	);
}
$show_payment_options = $deposit_percentage > 0 && $deposit_percentage < 100;
?>

<div class="wc-booking-form"
	id="wc-booking-form-<?php echo esc_attr( $product->get_id() ); ?>"
	data-booking-modes="<?php echo esc_attr( wp_json_encode( $mode_payload ) ); ?>"
	data-booking-addons="<?php echo esc_attr( wp_json_encode( $addon_payload ) ); ?>"
	data-deposit-percentage="<?php echo esc_attr( $deposit_percentage ); ?>">

	<input type="hidden" name="wc_booking_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wc_booking_calendar_add_to_cart' ) ); ?>">
	<input type="hidden" id="product_id" name="product_id" value="<?php echo esc_attr( $product->get_id() ); ?>">

	<div class="form-section">
		<div class="form-section-title"><?php esc_html_e( 'Booking Mode Preference', 'wc-booking-calendar-nz' ); ?></div>
		<label for="booking_mode"><?php esc_html_e( 'Choose your preferred booking mode', 'wc-booking-calendar-nz' ); ?></label>
		<select name="booking_mode" id="booking_mode" required>
			<?php foreach ( $booking_modes as $mode ) :
				$key = ! empty( $mode['key'] ) ? sanitize_title( $mode['key'] ) : sanitize_title( $mode['name'] ?? '' );
			?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected_mode_key, $key ); ?>><?php echo esc_html( $mode['name'] ?? $key ); ?></option>
			<?php endforeach; ?>
		</select>

		<div class="booking-mode-description" id="booking-mode-description"<?php echo '' === trim( $current_desc ) ? ' style="display:none;"' : ''; ?>>
			<div class="booking-mode-description__inner"><?php echo nl2br( esc_html( $current_desc ) ); ?></div>
		</div>
	</div>

	<div class="form-section">
		<div class="form-section-title"><?php esc_html_e( 'Select Date & Time', 'wc-booking-calendar-nz' ); ?></div>

		<div class="booking-date-picker">
			<label for="booking_date"><?php esc_html_e( 'Date', 'wc-booking-calendar-nz' ); ?></label>
			<input type="text" id="booking_date" name="booking_date" class="date-picker" placeholder="<?php esc_attr_e( 'Select a date…', 'wc-booking-calendar-nz' ); ?>" data-min-date="<?php echo esc_attr( $min_date ); ?>" data-max-date="<?php echo esc_attr( $max_date ); ?>" required />
		</div>

		<div class="booking-time-slots">
			<label for="booking_time"><?php esc_html_e( 'Time Slot', 'wc-booking-calendar-nz' ); ?></label>
			<select id="booking_time" name="booking_time" required>
				<option value=""><?php esc_html_e( 'Choose a time slot…', 'wc-booking-calendar-nz' ); ?></option>
			</select>
			<div class="loading-slots" style="display:none;"><span><?php esc_html_e( 'Loading available slots…', 'wc-booking-calendar-nz' ); ?></span></div>
		</div>
	</div>

	<?php if ( ! empty( $resources ) && $requires_resource ) : ?>
		<div class="form-section">
			<div class="form-section-title"><?php esc_html_e( 'Select Guide / Resource', 'wc-booking-calendar-nz' ); ?></div>
			<div class="booking-resource">
				<label for="resource_id"><?php esc_html_e( 'Guide / Resource', 'wc-booking-calendar-nz' ); ?></label>
				<select id="resource_id" name="booking_resource_id" required>
					<option value=""><?php esc_html_e( 'Choose a guide…', 'wc-booking-calendar-nz' ); ?></option>
					<?php foreach ( $resources as $resource ) : ?>
						<option value="<?php echo esc_attr( $resource->ID ); ?>"><?php echo esc_html( $resource->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
	<?php endif; ?>

	<div class="form-section">
		<div class="form-section-title"><?php esc_html_e( 'Number of People', 'wc-booking-calendar-nz' ); ?></div>
		<div class="booking-person-types">
			<?php foreach ( $person_types as $type ) : ?>
				<div class="person-type-input">
					<label for="person_type_<?php echo esc_attr( $type['id'] ); ?>">
						<?php echo esc_html( $type['name'] ); ?>
						<?php if ( ! empty( $type['age_min'] ) || ! empty( $type['age_max'] ) ) : ?>
							<span class="age-range">(<?php echo esc_html( $type['age_min'] ?? 0 ); ?>-<?php echo esc_html( $type['age_max'] ?? 0 ); ?> <?php esc_html_e( 'years', 'wc-booking-calendar-nz' ); ?>)</span>
						<?php endif; ?>
					</label>
					<input type="number" id="person_type_<?php echo esc_attr( $type['id'] ); ?>" name="person_types[<?php echo esc_attr( $type['id'] ); ?>]" min="0" max="50" value="0" data-price="<?php echo esc_attr( $type['price'] ); ?>" required />
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<?php if ( ! empty( $booking_addons ) ) : ?>
		<div class="form-section booking-addons-section" id="booking-addons-section" style="display:none;">
			<div class="form-section-title"><?php esc_html_e( 'Optional Add-ons', 'wc-booking-calendar-nz' ); ?></div>
			<div class="booking-addons-list">
				<?php foreach ( $booking_addons as $addon ) :
					$addon_key = ! empty( $addon['key'] ) ? sanitize_title( $addon['key'] ) : sanitize_title( $addon['label'] ?? '' );
					if ( empty( $addon['label'] ) ) {
						continue;
					}
				?>
					<label class="booking-addon-option">
						<input type="checkbox" name="booking_addons[]" value="<?php echo esc_attr( $addon_key ); ?>" />
						<span class="booking-addon-option__label"><?php echo esc_html( $addon['label'] ); ?></span>
						<span class="booking-addon-option__price">
							<?php echo wp_kses_post( wc_price( (float) ( $addon['price'] ?? 0 ) ) ); ?><?php echo ! empty( $addon['per_person'] ) ? esc_html__( ' per person', 'wc-booking-calendar-nz' ) : ''; ?>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $show_payment_options ) : ?>
		<div class="form-section">
			<div class="form-section-title"><?php esc_html_e( 'Payment Choice', 'wc-booking-calendar-nz' ); ?></div>
			<div class="booking-payment-options">
				<label class="booking-payment-option">
					<input type="radio" name="booking_payment_option" value="deposit" checked />
					<span><?php echo esc_html( sprintf( __( 'Pay %d%% deposit today', 'wc-booking-calendar-nz' ), $deposit_percentage ) ); ?></span>
				</label>
				<label class="booking-payment-option">
					<input type="radio" name="booking_payment_option" value="full" />
					<span><?php esc_html_e( 'Pay full amount now', 'wc-booking-calendar-nz' ); ?></span>
				</label>
			</div>
		</div>
	<?php else : ?>
		<input type="hidden" name="booking_payment_option" value="full" />
	<?php endif; ?>

	<div class="form-section">
		<div class="form-section-title"><?php esc_html_e( 'Accessibility & Special Requests', 'wc-booking-calendar-nz' ); ?></div>
		<div class="booking-field-wrapper">
			<label for="booking_special_requests"><?php esc_html_e( 'Limited mobility or special requests', 'wc-booking-calendar-nz' ); ?></label>
			<textarea name="booking_special_requests" id="booking_special_requests" rows="3" class="input-text" placeholder="<?php esc_attr_e( 'Please let us know if anyone in your group has limited mobility or special interests.', 'wc-booking-calendar-nz' ); ?>"></textarea>
			<label class="limited-mobility-checkbox">
				<input type="checkbox" name="booking_limited_mobility" value="yes" />
				<?php esc_html_e( 'Someone in our group has limited mobility', 'wc-booking-calendar-nz' ); ?>
			</label>
		</div>
	</div>

	<div class="form-section">
		<div class="booking-price-display">
			<div>
				<div class="price-label"><?php esc_html_e( 'Booking Total', 'wc-booking-calendar-nz' ); ?></div>
				<div class="price-amount" id="booking-total-price"><?php echo wp_kses_post( wc_price( 0, array( 'currency' => get_woocommerce_currency() ) ) ); ?></div>
			</div>
			<div class="booking-price-display__due">
				<div class="price-due-label"><?php esc_html_e( 'Due Today', 'wc-booking-calendar-nz' ); ?></div>
				<div class="price-due-amount" id="booking-due-today"><?php echo wp_kses_post( wc_price( 0, array( 'currency' => get_woocommerce_currency() ) ) ); ?></div>
			</div>
		</div>
	</div>

	<button type="submit" class="button booking-add-to-cart" id="booking-add-to-cart" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>"><?php esc_html_e( 'Book Now', 'wc-booking-calendar-nz' ); ?></button>

	<div class="booking-availability-status" style="display:none;"><span class="availability-message"></span></div>
	<div id="booking-errors" class="error-message" style="display:none;"></div>
	<div id="booking-success" class="success-message" style="display:none;"></div>
</div>
