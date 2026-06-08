<?php
/**
 * Advanced Settings Template
 */
?>

<div class="advanced-settings">
    <h2><?php _e('Advanced Settings', 'wc-booking-calendar-nz'); ?></h2>
    
    <div class="section">
        <h3><?php _e('Peak Days', 'wc-booking-calendar-nz'); ?></h3>
        <table class="form-table">
            <tbody>
                <?php
                $advanced = get_option('wc_booking_calendar_advanced', array());
                $peak_days = $advanced['peak_days'] ?? array();
                
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
                    $checked = isset($peak_days[$day]) && $peak_days[$day] ? 'checked' : '';
                ?>
                    <tr>
                        <th scope="row"><?php echo $label; ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="wc_booking_calendar_advanced[peak_days][<?php echo $day; ?>]" 
                                       value="1" 
                                       <?php checked($checked); ?> />
                                <?php _e('Peak day', 'wc-booking-calendar-nz'); ?>
                            </label>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="section">
        <h3><?php _e('Peak Multiplier', 'wc-booking-calendar-nz'); ?></h3>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><?php _e('Multiplier', 'wc-booking-calendar-nz'); ?></th>
                    <td>
                        <input type="number" 
                               step="0.1" 
                               min="1" 
                               max="5" 
                               name="wc_booking_calendar_advanced[peak_multiplier]" 
                               value="<?php echo esc_attr($advanced['peak_multiplier'] ?? 1.0); ?>" 
                               class="small-text" />
                        <p class="description"><?php _e('Price multiplier for peak days (e.g., 1.5 = 50% more)', 'wc-booking-calendar-nz'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="section">
        <h3><?php _e('Lead Time', 'wc-booking-calendar-nz'); ?></h3>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><?php _e('Advance Booking (days)', 'wc-booking-calendar-nz'); ?></th>
                    <td>
                        <input type="number" 
                               min="0" 
                               max="365" 
                               name="wc_booking_calendar_advanced[advance_booking_days]" 
                               value="<?php echo esc_attr($advanced['advance_booking_days'] ?? 90); ?>" 
                               class="small-text" />
                        <p class="description"><?php _e('Maximum days in advance booking can be made', 'wc-booking-calendar-nz'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="section">
        <h3><?php _e('Seasonal Pricing', 'wc-booking-calendar-nz'); ?></h3>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><?php _e('Enable', 'wc-booking-calendar-nz'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="wc_booking_calendar_advanced[seasonal_pricing]" 
                                   value="1" 
                                   <?php checked(isset($advanced['seasonal_pricing'])); ?> />
                            <?php _e('Enable seasonal pricing', 'wc-booking-calendar-nz'); ?>
                        </label>
                        <p class="description"><?php _e('Allow different pricing based on date ranges', 'wc-booking-calendar-nz'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
