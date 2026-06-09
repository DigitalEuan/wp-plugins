<?php
/**
 * Booking Modes Settings Template
 */
?>

<div class="booking-modes-settings">
    <h2><?php _e('Booking Modes Configuration', 'wc-booking-calendar-nz'); ?></h2>
    
    <p class="description">
        <?php _e('Define booking modes with their rules and configurations.', 'wc-booking-calendar-nz'); ?>
    </p>
    
    <div class="booking-modes-visual-editor">
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php _e('Mode Name', 'wc-booking-calendar-nz'); ?></th>
                    <th><?php _e('Description', 'wc-booking-calendar-nz'); ?></th>
                    <th><?php _e('Full Day Block', 'wc-booking-calendar-nz'); ?></th>
                    <th><?php _e('Show Add-ons', 'wc-booking-calendar-nz'); ?></th>
                    <th><?php _e('Max Per Slot', 'wc-booking-calendar-nz'); ?></th>
                    <th><?php _e('Actions', 'wc-booking-calendar-nz'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $modes = get_option('wc_booking_calendar_booking_modes', array());
                if (empty($modes)):
                ?>
                    <tr>
                        <td colspan="6"><?php _e('No booking modes. Add your first mode.', 'wc-booking-calendar-nz'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($modes as $mode): ?>
                        <tr data-mode-id="<?php echo $mode['id']; ?>">
                            <td><input type="text" name="mode_name[]" value="<?php echo esc_attr($mode['name']); ?>" class="widefat" /></td>
                            <td><input type="text" name="mode_description[]" value="<?php echo esc_attr($mode['description']); ?>" class="widefat" /></td>
                            <td>
                                <label>
                                    <input type="checkbox" name="mode_full_day_block[]" value="1" <?php checked($mode['full_day_block']); ?> />
                                </label>
                            </td>
                            <td>
                                <label>
                                    <input type="checkbox" name="mode_show_addons[]" value="1" <?php checked($mode['show_addons']); ?> />
                                </label>
                            </td>
                            <td><input type="number" name="mode_max_per_slot[]" value="<?php echo esc_attr($mode['max_per_slot']); ?>" min="1" max="100" class="small-text" /></td>
                            <td><button type="button" class="button delete-mode"><?php _e('Delete', 'wc-booking-calendar-nz'); ?></button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <button type="button" id="add-booking-mode" class="button button-secondary">
            <?php _e('Add New Mode', 'wc-booking-calendar-nz'); ?>
        </button>
    </div>
    
    <div class="booking-modes-json-editor">
        <h4><?php _e('JSON Editor', 'wc-booking-calendar-nz'); ?></h4>
        <textarea name="wc_booking_calendar_booking_modes" rows="15" class="large-text" id="booking-modes-editor"><?php 
            echo esc_textarea(wp_json_encode(get_option('wc_booking_calendar_booking_modes', array())));
        ?></textarea>
        <p class="description">
            <?php _e('JSON array of booking modes.', 'wc-booking-calendar-nz'); ?>
            <code>[
  {
    "id": 1,
    "name": "Guided Tour",
    "description": "Full-day guided experience",
    "full_day_block": 1,
    "show_addons": 1,
    "max_per_slot": 1
  }
]</code>
        </p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#add-booking-mode').on('click', function() {
        var row = '<tr>' +
            '<td><input type="text" name="mode_name[]" value="" class="widefat" /></td>' +
            '<td><input type="text" name="mode_description[]" value="" class="widefat" /></td>' +
            '<td><label><input type="checkbox" name="mode_full_day_block[]" value="1" checked /></label></td>' +
            '<td><label><input type="checkbox" name="mode_show_addons[]" value="1" checked /></label></td>' +
            '<td><input type="number" name="mode_max_per_slot[]" value="10" min="1" max="100" class="small-text" /></td>' +
            '<td><button type="button" class="button delete-mode">' + '<?php _e('Delete', 'wc-booking-calendar-nz'); ?>' + '</button></td>' +
            '</tr>';
        $('table.widefat tbody').append(row);
    });
    
    $(document).on('click', '.delete-mode', function() {
        $(this).closest('tr').remove();
    });
});
</script>
