<?php
/**
 * Notifications Settings Template
 */
?>

<div class="notifications-settings">
    <h2><?php _e('Notification Settings', 'wc-booking-calendar-nz'); ?></h2>
    
    <div class="section">
        <h3><?php _e('Email Notifications', 'wc-booking-calendar-nz'); ?></h3>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><?php _e('Confirmation Email', 'wc-booking-calendar-nz'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="wc_booking_calendar_notifications[confirmation]" 
                                   value="1" 
                                   <?php checked(isset($notifications['confirmation']) && $notifications['confirmation']); ?> />
                            <?php _e('Send confirmation email on booking', 'wc-booking-calendar-nz'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Reminder Email', 'wc-booking-calendar-nz'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="wc_booking_calendar_notifications[reminder]" 
                                   value="1" 
                                   <?php checked(isset($notifications['reminder']) && $notifications['reminder']); ?> />
                            <?php _e('Send reminder email before booking', 'wc-booking-calendar-nz'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Cancellation Email', 'wc-booking-calendar-nz'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="wc_booking_calendar_notifications[cancellation]" 
                                   value="1" 
                                   <?php checked(isset($notifications['cancellation']) && $notifications['cancellation']); ?> />
                            <?php _e('Send cancellation email', 'wc-booking-calendar-nz'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Special Requests Email', 'wc-booking-calendar-nz'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="wc_booking_calendar_notifications[special_requests]" 
                                   value="1" 
                                   <?php checked(isset($notifications['special_requests']) && $notifications['special_requests']); ?> />
                            <?php _e('Email admin on special requests', 'wc-booking-calendar-nz'); ?>
                        </label>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="section">
        <h3><?php _e('Email Templates', 'wc-booking-calendar-nz'); ?></h3>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><?php _e('From Name', 'wc-booking-calendar-nz'); ?></th>
                    <td>
                        <input type="text" 
                               id="wc_booking_calendar_email_from_name" 
                               name="wc_booking_calendar_notifications[email_from_name]" 
                               value="<?php echo esc_attr(get_option('wc_booking_calendar_notifications', array())['email_from_name'] ?? get_bloginfo('name')); ?>" 
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('From Email', 'wc-booking-calendar-nz'); ?></th>
                    <td>
                        <input type="email" 
                               id="wc_booking_calendar_email_from_address" 
                               name="wc_booking_calendar_notifications[email_from_address]" 
                               value="<?php echo esc_attr(get_option('wc_booking_calendar_notifications', array())['email_from_address'] ?? get_option('admin_email')); ?>" 
                               class="regular-text" />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
