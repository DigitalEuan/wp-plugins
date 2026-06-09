<?php
/**
 * WC Booking Calendar - Booking Form Template
 * Frontend booking form for product pages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get the product
global $product;

// Get advance booking settings
$lead_time_days = (int) get_option('wc_booking_calendar_lead_time', 1);
$max_advance = (int) get_option('wc_booking_calendar_advance_window', 365);

// Calculate min/max dates
$min_date = $lead_time_days > 0 ? date('Y-m-d', strtotime("+{$lead_time_days} days")) : '';
$max_date = $max_advance > 0 ? date('Y-m-d', strtotime("+{$max_advance} days")) : '';
?>

<div class="wc-booking-form" id="wc-booking-form-<?php echo esc_attr($product->get_id()); ?>" data-mode="<?php echo esc_attr($product->get_meta('_booking_mode')); ?>">
    
    <?php // Nonce field for security ?>
    <input type="hidden" name="booking_nonce" value="<?php echo wp_create_nonce('wc_booking_calendar_add_to_cart'); ?>">
    
    <?php // Hidden product ID field ?>
    <input type="hidden" id="product_id" name="product_id" value="<?php echo esc_attr($product->get_id()); ?>">
    
    <?php // Booking Mode Selection ?>
    <div class="form-section">
        <div class="form-section-title"><?php _e('Booking Mode', 'wc-booking-calendar-nz'); ?></div>
        <div class="booking-mode-selection">
            <label for="booking_mode"><?php _e('Select your preference:', 'wc-booking-calendar-nz'); ?></label>
            <select name="booking_mode" id="booking_mode" required>
                <option value="self"><?php esc_html_e('Self-Directed Walk', 'wc-booking-calendar-nz'); ?></option>
                <option value="guided"><?php esc_html_e('Guided Tour', 'wc-booking-calendar-nz'); ?></option>
            </select>
        </div>
    </div>
    
    <?php // Guided Options (shown when mode is guided) ?>
    <div id="guided-options" style="display:none;">
        <div class="form-section">
            <div class="form-section-title"><?php _e('Guided Tour Options', 'wc-booking-calendar-nz'); ?></div>
            
            <!-- Morning Tea Checkbox -->
            <div class="guided-option">
                <label for="booking_morning_tea">
                    <input type="checkbox" name="booking_morning_tea" value="yes" id="booking_morning_tea">
                    <?php esc_html_e('Add Morning Tea (+$10 per person)', 'wc-booking-calendar-nz'); ?>
                </label>
            </div>
            
            <!-- Minimum People Notice -->
            <p class="description"><?php esc_html_e('Guided tours require a minimum of 10 people.', 'wc-booking-calendar-nz'); ?></p>
        </div>
    </div>
    
    <?php // Booking Description (if set) ?>
    <?php $booking_description = $product->get_meta('_booking_description'); ?>
    <?php if ($booking_description): ?>
        <div class="booking-description">
            <h3><?php _e('Booking Information', 'wc-booking-calendar-nz'); ?></h3>
            <p><?php echo wp_kses_post($booking_description); ?></p>
        </div>
    <?php endif; ?>
    
    <?php // Date Picker Section ?>
    <div class="form-section">
        <div class="form-section-title"><?php _e('Select Date & Time', 'wc-booking-calendar-nz'); ?></div>
        
        <div class="booking-date-picker">
            <label for="booking_date"><?php _e('Date', 'wc-booking-calendar-nz'); ?></label>
            <input type="text" 
                   id="booking_date" 
                   name="booking_date" 
                   class="date-picker" 
                   placeholder="<?php _e('Select a date...', 'wc-booking-calendar-nz'); ?>"
                   data-min-date="<?php echo esc_attr($min_date); ?>"
                   data-max-date="<?php echo esc_attr($max_date); ?>"
                   required />
        </div>
        
        <div class="booking-time-slots">
            <label for="booking_time"><?php _e('Time Slot', 'wc-booking-calendar-nz'); ?></label>
            <select id="booking_time" name="booking_time" required>
                <option value=""><?php _e('Choose a time slot...', 'wc-booking-calendar-nz'); ?></option>
            </select>
            <div id="loading-slots" class="loading-slots" style="display:none;">
                <span><?php _e('Loading available slots...', 'wc-booking-calendar-nz'); ?></span>
            </div>
        </div>
    </div>
    
    <?php // Resource Selection (if required) ?>
    <?php 
    $requires_resource = $product->get_meta('_booking_requires_resource') === 'yes';
    $resources = WC_Booking_Calendar_Frontend_Handler::get_instance()->get_available_resources($product);
    ?>
    
    <?php if (!empty($resources) && ($requires_resource || $product->get_meta('_booking_mode') === 'guided' || $product->get_meta('_booking_mode') === 'both')): ?>
    <div class="form-section">
        <div class="form-section-title"><?php _e('Select Guide/Resource', 'wc-booking-calendar-nz'); ?></div>
        
        <div class="booking-resource">
            <label for="resource_id"><?php _e('Guide/Resource', 'wc-booking-calendar-nz'); ?></label>
            <select id="resource_id" name="resource_id" required>
                <option value=""><?php _e('Choose a guide...', 'wc-booking-calendar-nz'); ?></option>
                <?php foreach ($resources as $resource): ?>
                    <option value="<?php echo esc_attr($resource->ID); ?>">
                        <?php echo esc_html($resource->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php endif; ?>
    
    <?php // Person Types Section ?>
    <div class="form-section">
        <div class="form-section-title"><?php _e('Number of People', 'wc-booking-calendar-nz'); ?></div>
        
        <div class="booking-person-types">
            <?php 
            $person_types = get_option('wc_booking_calendar_person_types', array());
            foreach ($person_types as $type): 
            ?>
                <div class="person-type-input">
                    <label for="person_type_<?php echo esc_attr($type['id']); ?>">
                        <?php echo esc_html($type['name']); ?>
                        <?php if ($type['age_min'] || $type['age_max']): ?>
                            <span class="age-range">
                                (<?php echo esc_html($type['age_min']); ?>-<?php echo esc_html($type['age_max']); ?> years)
                            </span>
                        <?php endif; ?>
                    </label>
                    <input type="number" 
                           id="person_type_<?php echo esc_attr($type['id']); ?>" 
                           name="person_types[<?php echo esc_attr($type['id']); ?>]" 
                           min="0" 
                           max="50" 
                           value="0" 
                           data-price="<?php echo esc_attr($type['price']); ?>"
                           required />
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <?php // Limited Mobility Section (checkbox) ?>
    <?php if ($product->get_meta('_limited_mobility') === 'yes'): ?>
    <div class="form-section">
        <div class="form-section-title"><?php _e('Accessibility', 'wc-booking-calendar-nz'); ?></div>
        
        <div class="limited-mobility">
            <label for="limited_mobility">
                <input type="checkbox" 
                       id="limited_mobility" 
                       name="limited_mobility" 
                       value="yes" />
                <?php _e('I have limited mobility', 'wc-booking-calendar-nz'); ?>
            </label>
            <div class="limited-mobility-message" style="display:none;">
                <?php _e('Please let us know your requirements so we can accommodate you.', 'wc-booking-calendar-nz'); ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php // Special Requests Section (optional) ?>
    <div class="form-section">
        <div class="form-section-title"><?php _e('Additional Information', 'wc-booking-calendar-nz'); ?></div>
        
        <div class="special-requests">
            <label for="special_requests"><?php _e('Special Requests (optional)', 'wc-booking-calendar-nz'); ?></label>
            <textarea id="special_requests" name="special_requests" rows="3" placeholder="<?php _e('Any special requirements or questions...', 'wc-booking-calendar-nz'); ?>"></textarea>
        </div>
    </div>
    
    <?php // Limited Mobility / Special Requests Combined Field (NEW) ?>
    <div class="form-section">
        <div class="form-section-title"><?php _e('Accessibility & Special Requests', 'wc-booking-calendar-nz'); ?></div>
        
        <div class="booking-field-wrapper">
            <label for="booking_limited_mobility">
                <?php esc_html_e('Limited Mobility or Special Requests:', 'wc-booking-calendar-nz'); ?>
            </label>
            <textarea name="booking_limited_mobility" id="booking_limited_mobility" rows="3" class="input-text" placeholder="<?php esc_attr_e('Please let us know if anyone in your group has limited mobility or special interests.', 'wc-booking-calendar-nz'); ?>"></textarea>
        </div>
    </div>
    
    <?php // Price Display Section ?>
    <div class="form-section">
        <div class="booking-price-display">
            <div class="price-label"><?php _e('Total Price:', 'wc-booking-calendar-nz'); ?></div>
            <div class="price-amount" id="booking-total-price"><?php echo wc_price(0, array('currency' => get_woocommerce_currency())); ?></div>
        </div>
    </div>
    
    <?php // Add to Cart Button ?>
    <button type="submit" 
            class="button booking-add-to-cart" 
            id="booking-add-to-cart">
        <?php _e('Book Now', 'wc-booking-calendar-nz'); ?>
    </button>
    
    <?php // Availability Status (hidden by default, shown by JS) ?>
    <div class="booking-availability-status" style="display:none;">
        <span class="availability-message"></span>
    </div>
    
    <?php // Confirmation Message (hidden by default) ?>
    <div id="booking-confirmation" class="booking-confirmation" style="display:none;">
        <p class="success"><?php _e('Booking added to cart!', 'wc-booking-calendar-nz'); ?></p>
    </div>
    
    <?php // Error Message Area ?>
    <div id="booking-errors" class="error-message" style="display:none;"></div>
    
    <?php // Success Message Area ?>
    <div id="booking-success" class="success-message" style="display:none;"></div>
    
</div>
