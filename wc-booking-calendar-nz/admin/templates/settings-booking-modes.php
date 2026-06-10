<?php
/**
 * Booking Modes Settings Template
 *
 * @package WC_Booking_Calendar_NZ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$modes = (array) get_option( 'wc_booking_calendar_booking_modes', array() );
if ( empty( $modes ) ) {
	$modes = array(
		array(
			'id'             => 1,
			'key'            => 'guided',
			'name'           => 'Guided Tour',
			'description'    => '',
			'full_day_block' => 1,
			'show_addons'    => 1,
			'max_per_slot'   => 1,
		),
		array(
			'id'             => 2,
			'key'            => 'self',
			'name'           => 'Self-Directed Walk',
			'description'    => '',
			'full_day_block' => 0,
			'show_addons'    => 0,
			'max_per_slot'   => 50,
		),
	);
}
?>

<div class="booking-modes-settings">
	<h2><?php esc_html_e( 'Booking Modes Configuration', 'wc-booking-calendar-nz' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Define booking modes, the description shown on the product page, and whether add-ons are available for that mode.', 'wc-booking-calendar-nz' ); ?></p>

	<table class="widefat striped" id="wc-booking-modes-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Mode Name', 'wc-booking-calendar-nz' ); ?></th>
				<th><?php esc_html_e( 'Description', 'wc-booking-calendar-nz' ); ?></th>
				<th><?php esc_html_e( 'Full Day Block', 'wc-booking-calendar-nz' ); ?></th>
				<th><?php esc_html_e( 'Show Add-ons', 'wc-booking-calendar-nz' ); ?></th>
				<th><?php esc_html_e( 'Max Per Slot', 'wc-booking-calendar-nz' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'wc-booking-calendar-nz' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $modes as $index => $mode ) : ?>
				<tr>
					<td>
						<input type="hidden" name="wc_booking_calendar_booking_modes[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $mode['id'] ?? ( $index + 1 ) ); ?>" />
						<input type="hidden" name="wc_booking_calendar_booking_modes[<?php echo esc_attr( $index ); ?>][key]" value="<?php echo esc_attr( $mode['key'] ?? '' ); ?>" class="mode-key-field" />
						<input type="text" name="wc_booking_calendar_booking_modes[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $mode['name'] ?? '' ); ?>" class="widefat mode-name-field" />
					</td>
					<td>
						<textarea name="wc_booking_calendar_booking_modes[<?php echo esc_attr( $index ); ?>][description]" rows="3" class="widefat"><?php echo esc_textarea( $mode['description'] ?? '' ); ?></textarea>
					</td>
					<td>
						<label>
							<input type="checkbox" name="wc_booking_calendar_booking_modes[<?php echo esc_attr( $index ); ?>][full_day_block]" value="1" <?php checked( ! empty( $mode['full_day_block'] ) ); ?> />
							<?php esc_html_e( 'Blocks the whole day when booked', 'wc-booking-calendar-nz' ); ?>
						</label>
					</td>
					<td>
						<label>
							<input type="checkbox" name="wc_booking_calendar_booking_modes[<?php echo esc_attr( $index ); ?>][show_addons]" value="1" <?php checked( ! empty( $mode['show_addons'] ) ); ?> />
							<?php esc_html_e( 'Customer can choose add-ons', 'wc-booking-calendar-nz' ); ?>
						</label>
					</td>
					<td>
						<input type="number" name="wc_booking_calendar_booking_modes[<?php echo esc_attr( $index ); ?>][max_per_slot]" value="<?php echo esc_attr( $mode['max_per_slot'] ?? 0 ); ?>" min="0" max="500" class="small-text" />
					</td>
					<td>
						<button type="button" class="button delete-mode"><?php esc_html_e( 'Delete', 'wc-booking-calendar-nz' ); ?></button>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p>
		<button type="button" id="add-booking-mode" class="button button-secondary"><?php esc_html_e( 'Add New Mode', 'wc-booking-calendar-nz' ); ?></button>
	</p>
</div>

<script>
jQuery(function($) {
	var $tableBody = $('#wc-booking-modes-table tbody');
	var nextIndex = $tableBody.find('tr').length;
	var nextId = Date.now();

	function slugify(text) {
		text = (text || '').toString().toLowerCase();
		if (text.indexOf('guided') !== -1) {
			return 'guided';
		}
		if (text.indexOf('self') !== -1 || text.indexOf('walk') !== -1) {
			return 'self';
		}
		text = text.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
		return text || ('mode-' + nextId);
	}

	function buildRow(index, id) {
		return '<tr>' +
			'<td>' +
				'<input type="hidden" name="wc_booking_calendar_booking_modes[' + index + '][id]" value="' + id + '" />' +
				'<input type="hidden" name="wc_booking_calendar_booking_modes[' + index + '][key]" value="" class="mode-key-field" />' +
				'<input type="text" name="wc_booking_calendar_booking_modes[' + index + '][name]" value="" class="widefat mode-name-field" />' +
			'</td>' +
			'<td><textarea name="wc_booking_calendar_booking_modes[' + index + '][description]" rows="3" class="widefat"></textarea></td>' +
			'<td><label><input type="checkbox" name="wc_booking_calendar_booking_modes[' + index + '][full_day_block]" value="1" /> <?php echo esc_js( __( 'Blocks the whole day when booked', 'wc-booking-calendar-nz' ) ); ?></label></td>' +
			'<td><label><input type="checkbox" name="wc_booking_calendar_booking_modes[' + index + '][show_addons]" value="1" checked /> <?php echo esc_js( __( 'Customer can choose add-ons', 'wc-booking-calendar-nz' ) ); ?></label></td>' +
			'<td><input type="number" name="wc_booking_calendar_booking_modes[' + index + '][max_per_slot]" value="10" min="0" max="500" class="small-text" /></td>' +
			'<td><button type="button" class="button delete-mode"><?php echo esc_js( __( 'Delete', 'wc-booking-calendar-nz' ) ); ?></button></td>' +
		'</tr>';
	}

	$('#add-booking-mode').on('click', function() {
		$tableBody.append(buildRow(nextIndex, nextId));
		nextIndex += 1;
		nextId += 1;
	});

	$(document).on('click', '.delete-mode', function() {
		if ($tableBody.find('tr').length > 1) {
			$(this).closest('tr').remove();
		}
	});

	$(document).on('input', '.mode-name-field', function() {
		var $row = $(this).closest('tr');
		$row.find('.mode-key-field').val(slugify($(this).val()));
	});

	$tableBody.find('tr').each(function() {
		var $row = $(this);
		if (!$row.find('.mode-key-field').val()) {
			$row.find('.mode-key-field').val(slugify($row.find('.mode-name-field').val()));
		}
	});
});
</script>
