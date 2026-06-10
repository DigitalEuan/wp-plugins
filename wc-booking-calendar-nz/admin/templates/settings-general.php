<?php
/**
 * General Settings Template
 *
 * @package WC_Booking_Calendar_NZ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$addons             = (array) get_option( 'wc_booking_calendar_addons', array() );
$deposit_percentage = (int) get_option( 'wc_booking_calendar_deposit_percentage', 50 );
if ( empty( $addons ) ) {
	$addons = array(
		array(
			'id'         => 1,
			'key'        => 'morning-tea',
			'label'      => 'Morning Tea',
			'price'      => (float) get_option( 'wc_booking_calendar_morning_tea_price', 10 ),
			'per_person' => 1,
			'enabled'    => 1,
		),
	);
}
?>

<div class="general-settings">
	<h2><?php esc_html_e( 'General Settings', 'wc-booking-calendar-nz' ); ?></h2>

	<div class="section">
		<h3><?php esc_html_e( 'Booking Rules', 'wc-booking-calendar-nz' ); ?></h3>
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Lead Time (days)', 'wc-booking-calendar-nz' ); ?></th>
					<td>
						<input type="number" name="wc_booking_calendar_lead_time" value="<?php echo esc_attr( get_option( 'wc_booking_calendar_lead_time', 1 ) ); ?>" min="0" max="365" class="small-text" />
						<p class="description"><?php esc_html_e( 'Minimum days in advance a customer can book (e.g. 1 = no same-day bookings).', 'wc-booking-calendar-nz' ); ?></p>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Advance Booking Window (days)', 'wc-booking-calendar-nz' ); ?></th>
					<td>
						<input type="number" name="wc_booking_calendar_advance_window" value="<?php echo esc_attr( get_option( 'wc_booking_calendar_advance_window', 365 ) ); ?>" min="1" max="1095" class="small-text" />
						<p class="description"><?php esc_html_e( 'Maximum days in advance a customer can book.', 'wc-booking-calendar-nz' ); ?></p>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Lead Time (hours)', 'wc-booking-calendar-nz' ); ?></th>
					<td>
						<input type="number" name="wc_booking_calendar_lead_time_hours" value="<?php echo esc_attr( get_option( 'wc_booking_calendar_lead_time_hours', 24 ) ); ?>" min="0" max="720" class="small-text" />
						<p class="description"><?php esc_html_e( 'Minimum hours notice required before the booking time.', 'wc-booking-calendar-nz' ); ?></p>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Deposit Percentage', 'wc-booking-calendar-nz' ); ?></th>
					<td>
						<input type="number" name="wc_booking_calendar_deposit_percentage" value="<?php echo esc_attr( $deposit_percentage ); ?>" min="0" max="100" class="small-text" />
						<p class="description"><?php esc_html_e( 'If set between 1 and 99, customers can choose to pay this deposit today or pay the full amount now.', 'wc-booking-calendar-nz' ); ?></p>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Timezone', 'wc-booking-calendar-nz' ); ?></th>
					<td>
						<input type="text" name="wc_booking_calendar_timezone" value="<?php echo esc_attr( get_option( 'wc_booking_calendar_timezone', 'Pacific/Auckland' ) ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Timezone for date/time calculations (e.g. Pacific/Auckland).', 'wc-booking-calendar-nz' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="section">
		<h3><?php esc_html_e( 'Group Size Limits', 'wc-booking-calendar-nz' ); ?></h3>
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Minimum Group Size', 'wc-booking-calendar-nz' ); ?></th>
					<td>
						<input type="number" name="wc_booking_calendar_min_group_size" value="<?php echo esc_attr( get_option( 'wc_booking_calendar_min_group_size', 1 ) ); ?>" min="1" max="500" class="small-text" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Maximum Group Size', 'wc-booking-calendar-nz' ); ?></th>
					<td>
						<input type="number" name="wc_booking_calendar_max_group_size" value="<?php echo esc_attr( get_option( 'wc_booking_calendar_max_group_size', 50 ) ); ?>" min="1" max="500" class="small-text" />
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="section">
		<h3><?php esc_html_e( 'Booking Add-ons', 'wc-booking-calendar-nz' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Add optional extras that can be shown for booking modes with “Show Add-ons” enabled.', 'wc-booking-calendar-nz' ); ?></p>

		<table class="widefat striped" id="wc-booking-addons-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Label', 'wc-booking-calendar-nz' ); ?></th>
					<th><?php esc_html_e( 'Price', 'wc-booking-calendar-nz' ); ?></th>
					<th><?php esc_html_e( 'Per Person', 'wc-booking-calendar-nz' ); ?></th>
					<th><?php esc_html_e( 'Enabled', 'wc-booking-calendar-nz' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wc-booking-calendar-nz' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $addons as $index => $addon ) : ?>
					<tr>
						<td>
							<input type="hidden" name="wc_booking_calendar_addons[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $addon['id'] ?? ( $index + 1 ) ); ?>" />
							<input type="hidden" name="wc_booking_calendar_addons[<?php echo esc_attr( $index ); ?>][key]" value="<?php echo esc_attr( $addon['key'] ?? '' ); ?>" class="addon-key-field" />
							<input type="text" name="wc_booking_calendar_addons[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $addon['label'] ?? '' ); ?>" class="widefat addon-label-field" />
						</td>
						<td><input type="number" step="0.01" min="0" name="wc_booking_calendar_addons[<?php echo esc_attr( $index ); ?>][price]" value="<?php echo esc_attr( $addon['price'] ?? 0 ); ?>" class="small-text" /></td>
						<td><label><input type="checkbox" name="wc_booking_calendar_addons[<?php echo esc_attr( $index ); ?>][per_person]" value="1" <?php checked( ! empty( $addon['per_person'] ) ); ?> /> <?php esc_html_e( 'Multiply by guests', 'wc-booking-calendar-nz' ); ?></label></td>
						<td><label><input type="checkbox" name="wc_booking_calendar_addons[<?php echo esc_attr( $index ); ?>][enabled]" value="1" <?php checked( ! empty( $addon['enabled'] ) ); ?> /> <?php esc_html_e( 'Enabled', 'wc-booking-calendar-nz' ); ?></label></td>
						<td><button type="button" class="button delete-addon"><?php esc_html_e( 'Delete', 'wc-booking-calendar-nz' ); ?></button></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p><button type="button" id="add-booking-addon" class="button button-secondary"><?php esc_html_e( 'Add New Add-on', 'wc-booking-calendar-nz' ); ?></button></p>
	</div>
</div>

<script>
jQuery(function($) {
	var $tableBody = $('#wc-booking-addons-table tbody');
	var nextIndex = $tableBody.find('tr').length;
	var nextId = Date.now();

	function slugify(text) {
		text = (text || '').toString().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
		return text || ('addon-' + nextId);
	}

	function buildRow(index, id) {
		return '<tr>' +
			'<td>' +
				'<input type="hidden" name="wc_booking_calendar_addons[' + index + '][id]" value="' + id + '" />' +
				'<input type="hidden" name="wc_booking_calendar_addons[' + index + '][key]" value="" class="addon-key-field" />' +
				'<input type="text" name="wc_booking_calendar_addons[' + index + '][label]" value="" class="widefat addon-label-field" />' +
			'</td>' +
			'<td><input type="number" step="0.01" min="0" name="wc_booking_calendar_addons[' + index + '][price]" value="0" class="small-text" /></td>' +
			'<td><label><input type="checkbox" name="wc_booking_calendar_addons[' + index + '][per_person]" value="1" checked /> <?php echo esc_js( __( 'Multiply by guests', 'wc-booking-calendar-nz' ) ); ?></label></td>' +
			'<td><label><input type="checkbox" name="wc_booking_calendar_addons[' + index + '][enabled]" value="1" checked /> <?php echo esc_js( __( 'Enabled', 'wc-booking-calendar-nz' ) ); ?></label></td>' +
			'<td><button type="button" class="button delete-addon"><?php echo esc_js( __( 'Delete', 'wc-booking-calendar-nz' ) ); ?></button></td>' +
		'</tr>';
	}

	$('#add-booking-addon').on('click', function() {
		$tableBody.append(buildRow(nextIndex, nextId));
		nextIndex += 1;
		nextId += 1;
	});

	$(document).on('click', '.delete-addon', function() {
		if ($tableBody.find('tr').length > 1) {
			$(this).closest('tr').remove();
		}
	});

	$(document).on('input', '.addon-label-field', function() {
		var $row = $(this).closest('tr');
		$row.find('.addon-key-field').val(slugify($(this).val()));
	});

	$tableBody.find('tr').each(function() {
		var $row = $(this);
		if (!$row.find('.addon-key-field').val()) {
			$row.find('.addon-key-field').val(slugify($row.find('.addon-label-field').val()));
		}
	});
});
</script>
