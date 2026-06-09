<?php
/**
 * General Settings Template
 */
?>

<div class="general-settings">
    <h2><?php _e('General Settings', 'wc-booking-calendar-nz'); ?></h2>
    
    <div class="section">
        <h3><?php _e('Booking Rules', 'wc-booking-calendar-nz'); ?></h3>
        <table class="form-table">
            <tbody>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Lead Time (days)', 'wc-booking-calendar-nz'); ?></th>
                    <td>
                        <input type="number" 
                               name="wc_booking_calendar_lead_time" 
                               value="<?php echo esc_attr( get_option('wc_booking_calendar_lead_time', 1) ); ?>" 
                               min="0" 
                               max="365" 
                               class="small-text" />
                        <p class="description"><?php esc_html_e('Minimum days in advance a customer can book (e.g., 1 = can\'t book same day)', 'wc-booking-calendar-nz'); ?></p>
                    </td>
                </tr>
                
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Advance Booking Window (days)', 'wc-booking-calendar-nz'); ?></th>
                    <td>
                        <input type="number" 
                               name="wc_booking_calendar_advance_window" 
                               value="<?php echo esc_attr( get_option('wc_booking_calendar_advance_window', 365) ); ?>" 
                               min="1" 
                               max="1095" 
                               class="small-text" />
                        <p class="description"><?php esc_html_e('Maximum days in advance a customer can book (e.g., 365 = up to 1 year ahead)', 'wc-booking-calendar-nz'); ?></p>
                    </td>
                </tr>
                
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Advance Booking (days)', 'wc-booking-calendar-nz'); ?></th>
                    <td>
                        <input type="number" 
                               name="wc_booking_calendar_advance_days" 
                               value="<?php echo esc_attr( get_option('wc_booking_calendar_advance_days', 365) ); ?>" 
                               min="0" 
                               max="365" 
                               class="small-text" />
                        <p class="description"><?php esc_html_e('Maximum days in advance booking can be made (legacy setting)', 'wc-booking-calendar-nz'); ?></p>
                    </td>
                </tr>
                
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Lead Time (hours)', 'wc-booking-calendar-nz'); ?></th>
                    <td>
                        <input type="number" 
                               name="wc_booking_calendar_lead_time_hours" 
                               value="<?php echo esc_attr( get_option('wc_booking_calendar_lead_time_hours', 24) ); ?>" 
                               min="0" 
                               max="720" 
                               class="small-text" />
                        <p class="description"><?php esc_html_e('Minimum hours notice required before booking time', 'wc-booking-calendar-nz'); ?></p>
                    </td>
                </tr>
                
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Timezone', 'wc-booking-calendar-nz'); ?></th>
                    <td>
                        <input type="text" 
                               name="wc_booking_calendar_timezone" 
                               value="<?php echo esc_attr( get_option('wc_booking_calendar_timezone', 'UTC') ); ?>" 
                               class="regular-text" />
                        <p class="description"><?php esc_html_e('Timezone for date/time calculations (e.g., Pacific/Auckland)', 'wc-booking-calendar-nz'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="section">
        <h3><?php _e('Group Size Limits', 'wc-booking-calendar-nz'); ?></h3>
        <table class="form-table">
            <tbody>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Minimum Group Size', 'wc-booking-calendar-nz'); ?></th>
                    <td>
                        <input type="number" 
                               name="wc_booking_calendar_min_group_size" 
                               value="<?php echo esc_attr( get_option('wc_booking_calendar_min_group_size', 1) ); ?>" 
                               min="1" 
                               max="50" 
                               class="small-text" />
                        <p class="description"><?php esc_html_e('Minimum number of people required for a booking', 'wc-booking-calendar-nz'); ?></p>
                    </td>
                </tr>
                
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Maximum Group Size', 'wc-booking-calendar-nz'); ?></th>
                    <td>
                        <input type="number" 
                               name="wc_booking_calendar_max_group_size" 
                               value="<?php echo esc_attr( get_option('wc_booking_calendar_max_group_size', 50) ); ?>" 
                               min="1" 
                               max="500" 
                               class="small-text" />
                        <p class="description"><?php esc_html_e('Maximum number of people allowed per booking', 'wc-booking-calendar-nz'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
