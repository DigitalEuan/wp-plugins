<?php
/**
 * Time Slots Settings Template
 */
?>

<div class="time-slots-settings">
    <h2><?php _e('Time Slots Configuration', 'wc-booking-calendar-nz'); ?></h2>
    
    <div class="section">
        <h3><?php _e('Days of Week', 'wc-booking-calendar-nz'); ?></h3>
        <table class="form-table">
            <tbody>
                <?php
                $days = get_option('wc_booking_calendar_days_of_week', array(
                    'monday' => 1,
                    'tuesday' => 1,
                    'wednesday' => 1,
                    'thursday' => 1,
                    'friday' => 1,
                    'saturday' => 0,
                    'sunday' => 0
                ));
                
                $day_names = array(
                    'monday' => __('Monday', 'wc-booking-calendar-nz'),
                    'tuesday' => __('Tuesday', 'wc-booking-calendar-nz'),
                    'wednesday' => __('Wednesday', 'wc-booking-calendar-nz'),
                    'thursday' => __('Thursday', 'wc-booking-calendar-nz'),
                    'friday' => __('Friday', 'wc-booking-calendar-nz'),
                    'saturday' => __('Saturday', 'wc-booking-calendar-nz'),
                    'sunday' => __('Sunday', 'wc-booking-calendar-nz')
                );
                
                foreach ($day_names as $day => $label):
                    $checked = isset($days[$day]) && $days[$day] ? 'checked' : '';
                ?>
                    <tr>
                        <th scope="row"><?php echo $label; ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="wc_booking_calendar_days_of_week[<?php echo $day; ?>]" 
                                       value="1" 
                                       <?php checked(isset($days[$day]) && $days[$day]); ?> />
                                <?php _e('Available', 'wc-booking-calendar-nz'); ?>
                            </label>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="section">
        <h3><?php _e('Time Slots', 'wc-booking-calendar-nz'); ?></h3>
        <p class="description">
            <?php _e('Add time slots for bookings. You can manage slots visually below or enter JSON format.', 'wc-booking-calendar-nz'); ?>
        </p>
        
        <div class="time-slots-visual-editor">
            <table class="widefat" id="time-slots-table">
                <thead>
                    <tr>
                        <th><?php _e('Name', 'wc-booking-calendar-nz'); ?></th>
                        <th><?php _e('Start', 'wc-booking-calendar-nz'); ?></th>
                        <th><?php _e('End', 'wc-booking-calendar-nz'); ?></th>
                        <th><?php _e('Enabled', 'wc-booking-calendar-nz'); ?></th>
                        <th><?php _e('Actions', 'wc-booking-calendar-nz'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $slots = get_option('wc_booking_calendar_time_slots', array());
                    if (empty($slots)):
                    ?>
                        <tr>
                            <td colspan="5"><?php _e('No time slots. Add your first slot.', 'wc-booking-calendar-nz'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($slots as $slot): ?>
                            <tr data-slot-id="<?php echo $slot['id']; ?>">
                                <td><input type="text" name="slot_name[]" value="<?php echo esc_attr($slot['name']); ?>" class="widefat" /></td>
                                <td><input type="time" name="slot_start[]" value="<?php echo esc_attr($slot['start']); ?>" class="small-text" /></td>
                                <td><input type="time" name="slot_end[]" value="<?php echo esc_attr($slot['end']); ?>" class="small-text" /></td>
                                <td>
                                    <label>
                                        <input type="checkbox" name="slot_enabled[]" value="1" <?php checked($slot['enabled']); ?> />
                                    </label>
                                </td>
                                <td><button type="button" class="button delete-slot"><?php _e('Delete', 'wc-booking-calendar-nz'); ?></button></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <button type="button" id="add-time-slot" class="button button-secondary">
                <?php _e('Add New Slot', 'wc-booking-calendar-nz'); ?>
            </button>
        </div>
        
        <div class="time-slots-json-editor">
            <h4><?php _e('JSON Editor', 'wc-booking-calendar-nz'); ?></h4>
            <textarea name="wc_booking_calendar_time_slots" rows="10" class="large-text" id="time-slots-editor"><?php 
                echo esc_textarea(wp_json_encode(get_option('wc_booking_calendar_time_slots', array())));
            ?></textarea>
            <p class="description">
                <?php _e('JSON array of time slots.', 'wc-booking-calendar-nz'); ?>
                <code>[
  {
    "id": 1,
    "name": "Morning",
    "start": "09:00",
    "end": "12:00",
    "enabled": 1
  }
]</code>
            </p>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#add-time-slot').on('click', function() {
        var row = '<tr>' +
            '<td><input type="text" name="slot_name[]" value="" class="widefat" /></td>' +
            '<td><input type="time" name="slot_start[]" value="" class="small-text" /></td>' +
            '<td><input type="time" name="slot_end[]" value="" class="small-text" /></td>' +
            '<td><label><input type="checkbox" name="slot_enabled[]" value="1" checked /></label></td>' +
            '<td><button type="button" class="button delete-slot">' + '<?php _e('Delete', 'wc-booking-calendar-nz'); ?>' + '</button></td>' +
            '</tr>';
        $('#time-slots-table tbody').append(row);
    });
    
    $(document).on('click', '.delete-slot', function() {
        $(this).closest('tr').remove();
    });
});
</script>
