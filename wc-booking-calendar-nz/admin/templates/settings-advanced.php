<?php
/**
 * Advanced Settings Template
 *
 * @package WC_Booking_Calendar_NZ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advanced  = (array) get_option( 'wc_booking_calendar_advanced', array() );
$peak_days = array_map( 'strtolower', (array) ( $advanced['peak_days'] ?? array() ) );
$day_names = array(
	'monday'    => __( 'Monday', 'wc-booking-calendar-nz' ),
	'tuesday'   => __( 'Tuesday', 'wc-booking-calendar-nz' ),
	'wednesday' => __( 'Wednesday', 'wc-booking-calendar-nz' ),
	'thursday'  => __( 'Thursday', 'wc-booking-calendar-nz' ),
	'friday'    => __( 'Friday', 'wc-booking-calendar-nz' ),
	'saturday'  => __( 'Saturday', 'wc-booking-calendar-nz' ),
	'sunday'    => __( 'Sunday', 'wc-booking-calendar-nz' ),
);
?>

<div class="advanced-settings">
	<h2><?php esc_html_e( 'Advanced Settings', 'wc-booking-calendar-nz' ); ?></h2>

	<div class="section">
		<h3><?php esc_html_e( 'Peak Days', 'wc-booking-calendar-nz' ); ?></h3>
		<table class="form-table">
			<tbody>
				<?php foreach ( $day_names as $day => $label ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $label ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wc_booking_calendar_advanced[peak_days][<?php echo esc_attr( $day ); ?>]" value="1" <?php checked( in_array( $day, $peak_days, true ) ); ?> />
								<?php esc_html_e( 'Peak day', 'wc-booking-calendar-nz' ); ?>
							</label>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="section">
		<h3><?php esc_html_e( 'Peak Multiplier', 'wc-booking-calendar-nz' ); ?></h3>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Multiplier', 'wc-booking-calendar-nz' ); ?></th>
					<td>
						<input type="number" step="0.1" min="1" max="5" name="wc_booking_calendar_advanced[peak_multiplier]" value="<?php echo esc_attr( $advanced['peak_multiplier'] ?? 1.0 ); ?>" class="small-text" />
						<p class="description"><?php esc_html_e( 'Price multiplier for peak days (e.g. 1.5 = 50% more).', 'wc-booking-calendar-nz' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="section">
		<h3><?php esc_html_e( 'Global Blackout Dates', 'wc-booking-calendar-nz' ); ?></h3>
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Blackout Dates', 'wc-booking-calendar-nz' ); ?></th>
					<td>
						<textarea name="wc_booking_calendar_blackout_dates" rows="7" cols="30" class="large-text"><?php echo esc_textarea( implode( "\n", (array) get_option( 'wc_booking_calendar_blackout_dates', array() ) ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Enter one date per line (YYYY-MM-DD). These dates are blocked for all bookings and are also disabled in the frontend date picker.', 'wc-booking-calendar-nz' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</div>
