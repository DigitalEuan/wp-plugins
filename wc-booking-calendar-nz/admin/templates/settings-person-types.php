<?php
/**
 * Person Types Settings Template.
 *
 * Loaded by WC_Booking_Calendar_Admin_Settings::render_settings_page().
 *
 * @package WC_Booking_Calendar_NZ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$person_types = (array) get_option( 'wc_booking_calendar_person_types', array() );
?>

<div class="person-types-settings">
	<h2><?php esc_html_e( 'Person Types', 'wc-booking-calendar-nz' ); ?></h2>

	<p class="description">
		<?php esc_html_e( 'Configure the categories of people that can be booked (Adult, Child, etc.) and their per-person price adjustment relative to the product\'s base price.', 'wc-booking-calendar-nz' ); ?>
	</p>

	<div class="person-types-visual-editor">
		<table class="widefat" id="person-types-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'wc-booking-calendar-nz' ); ?></th>
					<th><?php esc_html_e( 'Min Age', 'wc-booking-calendar-nz' ); ?></th>
					<th><?php esc_html_e( 'Max Age', 'wc-booking-calendar-nz' ); ?></th>
					<th><?php esc_html_e( 'Price Adjustment', 'wc-booking-calendar-nz' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wc-booking-calendar-nz' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $person_types ) ) : ?>
					<tr>
						<td colspan="5"><?php esc_html_e( 'No person types yet. Add your first one below.', 'wc-booking-calendar-nz' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $person_types as $type ) : ?>
						<tr data-type-id="<?php echo esc_attr( $type['id'] ?? 0 ); ?>">
							<td><input type="text" name="person_type_name[]" value="<?php echo esc_attr( $type['name'] ?? '' ); ?>" class="widefat" /></td>
							<td><input type="number" min="0" max="120" name="person_type_age_min[]" value="<?php echo esc_attr( $type['age_min'] ?? 0 ); ?>" class="small-text" /></td>
							<td><input type="number" min="0" max="120" name="person_type_age_max[]" value="<?php echo esc_attr( $type['age_max'] ?? 0 ); ?>" class="small-text" /></td>
							<td><input type="number" step="0.01" name="person_type_price[]" value="<?php echo esc_attr( $type['price'] ?? 0 ); ?>" class="small-text" /></td>
							<td><button type="button" class="button delete-person-type"><?php esc_html_e( 'Delete', 'wc-booking-calendar-nz' ); ?></button></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<p>
			<button type="button" id="add-person-type" class="button button-secondary">
				<?php esc_html_e( 'Add Person Type', 'wc-booking-calendar-nz' ); ?>
			</button>
		</p>
	</div>

	<div class="person-types-json-editor">
		<h4><?php esc_html_e( 'JSON Editor', 'wc-booking-calendar-nz' ); ?></h4>
		<textarea name="wc_booking_calendar_person_types" rows="12" class="large-text code" id="person-types-editor"><?php
			echo esc_textarea( wp_json_encode( $person_types, JSON_PRETTY_PRINT ) );
		?></textarea>
		<p class="description">
			<?php esc_html_e( 'JSON array of person types. The visual editor above is a helper; the JSON below is what is saved.', 'wc-booking-calendar-nz' ); ?>
		</p>
	</div>
</div>

<script>
jQuery(document).ready(function ($) {

	function syncJsonFromTable() {
		var rows = [];
		$('#person-types-table tbody tr[data-type-id]').each(function (i) {
			var $row = $(this);
			rows.push({
				id:      parseInt($row.attr('data-type-id'), 10) || (i + 1),
				name:    $row.find('input[name="person_type_name[]"]').val() || '',
				age_min: parseInt($row.find('input[name="person_type_age_min[]"]').val(), 10) || 0,
				age_max: parseInt($row.find('input[name="person_type_age_max[]"]').val(), 10) || 0,
				price:   parseFloat($row.find('input[name="person_type_price[]"]').val()) || 0
			});
		});
		$('#person-types-editor').val(JSON.stringify(rows, null, 2));
	}

	$('#add-person-type').on('click', function () {
		var nextId = $('#person-types-table tbody tr[data-type-id]').length + 1;
		var row =
			'<tr data-type-id="' + nextId + '">' +
				'<td><input type="text" name="person_type_name[]" value="" class="widefat" /></td>' +
				'<td><input type="number" min="0" max="120" name="person_type_age_min[]" value="0" class="small-text" /></td>' +
				'<td><input type="number" min="0" max="120" name="person_type_age_max[]" value="120" class="small-text" /></td>' +
				'<td><input type="number" step="0.01" name="person_type_price[]" value="0" class="small-text" /></td>' +
				'<td><button type="button" class="button delete-person-type"><?php echo esc_js( __( 'Delete', 'wc-booking-calendar-nz' ) ); ?></button></td>' +
			'</tr>';
		// Remove the empty-state row if present.
		$('#person-types-table tbody tr:not([data-type-id])').remove();
		$('#person-types-table tbody').append(row);
		syncJsonFromTable();
	});

	$(document).on('click', '.delete-person-type', function () {
		$(this).closest('tr').remove();
		syncJsonFromTable();
	});

	$(document).on('input change', '#person-types-table input', syncJsonFromTable);
});
</script>
