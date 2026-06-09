/**
 * WC Booking Calendar - Frontend JavaScript
 * Handles all frontend interactions
 */

(function($) {
    'use strict';
    
    // Global plugin object
    var WCBookingCalendar = {
        
        /**
         * Initialize plugin
         */
        init: function() {
            this.initDatePicker();
            this.initEventListeners();
            this.initAddToCart();
            this.initPriceCalculation();
            this.initValidation();
            this.initGuidedModeToggle();
        },
        
        /**
         * ========================================
         * GUIDED MODE TOGGLE
         * ========================================
         */
        initGuidedModeToggle: function() {
            var $modeSelect = $('[name="booking_mode"]');
            var $guidedOptions = $('#guided-options');
            var $teaCheckbox = $('[name="booking_morning_tea"]');
            
            if (!$modeSelect.length || !$guidedOptions.length) {
                return;
            }
            
            var toggleGuidedOptions = function() {
                if ($modeSelect.val() === 'guided') {
                    $guidedOptions.show();
                    if ($teaCheckbox.length && !$teaCheckbox.is(':checked')) {
                        $teaCheckbox.prop('checked', true);
                    }
                } else {
                    $guidedOptions.hide();
                    if ($teaCheckbox.length) {
                        $teaCheckbox.prop('checked', false);
                    }
                }
            };
            
            // Run on change
            $modeSelect.on('change', toggleGuidedOptions);
            
            // Run on load to set initial state
            toggleGuidedOptions();
        },
        
        /**
         * ========================================
         * DATE PICKER INITIALIZATION
         * ========================================
         */
        initDatePicker: function() {
            $('.wc-booking-form .date-picker').each(function() {
                var $input = $(this);
                var minDate = $input.data('min-date') || '';
                var maxDate = $input.data('max-date') || '';
                
                // Initialize datepicker
                $input.datepicker({
                    dateFormat: 'yy-mm-dd',
                    minDate: minDate,
                    maxDate: maxDate,
                    beforeShowDay: function(date) {
                        return [true, '', ''];
                    },
                    onSelect: function(dateText) {
                        WCBookingCalendar.loadTimeSlots(dateText);
                        WCBookingCalendar.calculatePrice();
                    }
                });
                
                // Fix for NZ time zone
                $input.datepicker('setDate', new Date());
            });
        },
        
        /**
         * ========================================
         * EVENT LISTENERS
         * ========================================
         */
        initEventListeners: function() {
            // Date change
            $(document).on('change', '.wc-booking-form .date-picker', function() {
                var date = $(this).val();
                if (date) {
                    WCBookingCalendar.loadTimeSlots(date);
                }
            });
            
            // Time slot change
            $(document).on('change', '.wc-booking-form #booking_time', function() {
                WCBookingCalendar.calculatePrice();
                WCBookingCalendar.checkAvailability();
            });
            
            // Resource change
            $(document).on('change', '.wc-booking-form #resource_id', function() {
                WCBookingCalendar.loadTimeSlots($('#booking_date').val());
                WCBookingCalendar.checkAvailability();
            });
            
            // Person type count change
            $(document).on('input', '.wc-booking-form .person-type-input input', function() {
                WCBookingCalendar.calculatePrice();
                WCBookingCalendar.checkAvailability();
            });
            
            // Limited mobility checkbox
            $(document).on('change', '.wc-booking-form input[name="limited_mobility"]', function() {
                var $message = $(this).closest('.limited-mobility').find('.limited-mobility-message');
                if ($(this).is(':checked')) {
                    $message.slideDown();
                } else {
                    $message.slideUp();
                }
            });
            
            // Add to cart button. If the booking form is rendered INSIDE the standard
            // WooCommerce <form class="cart"> (single-product page), let the browser do
            // the normal POST submit so WC's add-to-cart pipeline (with all our hooks)
            // runs server-side. Otherwise, fall back to an AJAX submit.
            $(document).on('click', '.wc-booking-form #booking-add-to-cart', function(e) {
                var $cartForm = $(this).closest('form.cart');
                if ($cartForm.length) {
                    // Validate first; allow native submit only if valid.
                    if (!WCBookingCalendar.validateForm()) {
                        e.preventDefault();
                    }
                    return;
                }
                e.preventDefault();
                WCBookingCalendar.handleAddToCart();
            });
        },
        
        /**
         * ========================================
         * LOAD TIME SLOTS
         * ========================================
         */
        loadTimeSlots: function(date) {
            var $select = $('#booking_time');
            var $loading = $('#loading-slots');
            
            // Show loading
            $loading.show();
            $select.prop('disabled', true);
            
            $.ajax({
                url: wc_booking_calendar.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_booking_get_slots',
                    nonce: wc_booking_calendar.nonce,
                    product_id: $('#product_id').val(),
                    date: date,
                    resource_id: $('#resource_id').val()
                },
                success: function(response) {
                    if (response.success) {
                        WCBookingCalendar.populateTimeSlots(response.data.slots);
                    } else {
                        WCBookingCalendar.showError(response.data.message || 'Failed to load slots');
                    }
                    $loading.hide();
                    $select.prop('disabled', false);
                },
                error: function() {
                    WCBookingCalendar.showError('Network error');
                    $loading.hide();
                    $select.prop('disabled', false);
                }
            });
        },
        
        /**
         * Populate time slots dropdown
         */
        populateTimeSlots: function(slots) {
            var $select = $('#booking_time');
            $select.empty();
            
            if (slots.length === 0) {
                $select.append('<option value="">' + wc_booking_calendar.i18n.no_slots + '</option>');
                return;
            }
            
            $.each(slots, function(index, slot) {
                var option = '<option value="' + slot.start + '-' + slot.end + '" data-capacity="' + slot.available + '">' +
                            slot.name + ' (' + slot.start + ' - ' + slot.end + ') - ' +
                            (slot.available > 0 ? slot.available + ' spots' : 'Full') +
                            '</option>';
                $select.append(option);
            });
        },
        
        /**
         * ========================================
         * PRICE CALCULATION
         * ========================================
         */
        calculatePrice: function() {
            var product_id = $('#product_id').val();
            var date = $('#booking_date').val();
            var time = $('#booking_time').val();
            var person_types = {};
            
            // Collect person types
            $('.person-type-input input').each(function() {
                var type_id = $(this).attr('id').replace('person_type_', '');
                var count = parseInt($(this).val()) || 0;
                if (count > 0) {
                    person_types[type_id] = count;
                }
            });
            
            // Don't calculate if no person types
            if ($.isEmptyObject(person_types)) {
                $('#booking-total-price').text('0.00');
                return;
            }
            
            $.ajax({
                url: wc_booking_calendar.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_booking_calculate_price',
                    nonce: wc_booking_calendar.nonce,
                    product_id: product_id,
                    person_types: person_types,
                    date: date,
                    time: time
                },
                success: function(response) {
                    if (response.success) {
                        // PHP returns either {total, total_formatted} or {price}.
                        var amount = (response.data && (response.data.total !== undefined ? response.data.total : response.data.price)) || 0;
                        $('#booking-total-price').text(
                            WCBookingCalendar.formatCurrency(amount)
                        );
                        
                        // Update breakdown display
                        if (response.data.breakdown) {
                            WCBookingCalendar.updatePriceBreakdown(response.data.breakdown);
                        }
                    }
                }
            });
        },
        
        /**
         * Update price breakdown display
         */
        updatePriceBreakdown: function(breakdown) {
            // You can add a detailed breakdown view here
            // For now, we just update the total
        },
        
        /**
         * Format currency
         */
        formatCurrency: function(amount) {
            var symbol = (typeof wc_booking_calendar !== 'undefined' && wc_booking_calendar.currency_symbol)
                ? wc_booking_calendar.currency_symbol
                : '$';
            return symbol + parseFloat(amount || 0).toFixed(2);
        },
        
        /**
         * ========================================
         * AVAILABILITY CHECK
         * ========================================
         */
        checkAvailability: function() {
            var product_id = $('#product_id').val();
            var date = $('#booking_date').val();
            var time = $('#booking_time').val();
            var resource_id = $('#resource_id').val();
            var mode = $('.wc-booking-form').data('mode') || '';
            var person_count = 0;
            
            // Calculate total person count
            $('.person-type-input input').each(function() {
                person_count += parseInt($(this).val()) || 0;
            });
            
            if (!product_id || !date || !time) {
                return;
            }
            
            $.ajax({
                url: wc_booking_calendar.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_booking_check_availability',
                    nonce: wc_booking_calendar.nonce,
                    product_id: product_id,
                    date: date,
                    time: time,
                    resource_id: resource_id,
                    mode: mode,
                    person_count: person_count
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.available) {
                            $('.booking-availability-status').removeClass('unavailable').addClass('available');
                            $('.availability-message').text('Available');
                        } else {
                            $('.booking-availability-status').removeClass('available').addClass('unavailable');
                            $('.availability-message').text(response.data.message || 'Unavailable');
                        }
                    } else {
                        $('.booking-availability-status').removeClass('available').addClass('unavailable');
                        $('.availability-message').text(response.data.message || 'Error checking availability');
                    }
                }
            });
        },
        
        /**
         * ========================================
         * ADD TO CART
         * ========================================
         */
        handleAddToCart: function() {
            var $button = $('#booking-add-to-cart');
            var $form = $('.wc-booking-form');
            
            // Validate form
            if (!WCBookingCalendar.validateForm()) {
                return;
            }
            
            // Disable button
            $button.prop('disabled', true).text('Adding...');
            
            // Collect form data
            var formData = {
                action: 'wc_booking_add_to_cart',
                nonce: wc_booking_calendar.nonce,
                booking_add_to_cart: 1,
                product_id: $('#product_id').val(),
                booking_date: $('#booking_date').val(),
                booking_time: $('#booking_time').val(),
                resource_id: $('#resource_id').val(),
                person_types: {},
                limited_mobility: $('input[name="limited_mobility"]').is(':checked') ? 'yes' : 'no',
                special_requests: $('#special_requests').val()
            };
            
            // Collect person types
            $('.person-type-input input').each(function() {
                var type_id = $(this).attr('id').replace('person_type_', '');
                var count = parseInt($(this).val()) || 0;
                if (count > 0) {
                    formData.person_types[type_id] = count;
                }
            });
            
            $.ajax({
                url: wc_booking_calendar.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        // Show confirmation
                        $('#booking-confirmation').show();
                        $('.booking-add-to-cart').text('✓ Added to Cart');
                        
                        // Optional: Redirect to cart
                        // window.location.href = wc_checkout_params.cart_url;
                        
                        // Re-enable button after delay
                        setTimeout(function() {
                            $button.prop('disabled', false).text('Book Now');
                        }, 2000);
                    } else {
                        // Show error
                        alert(response.data.message || 'Failed to add to cart');
                        $button.prop('disabled', false).text('Book Now');
                    }
                },
                error: function() {
                    alert('Network error. Please try again.');
                    $button.prop('disabled', false).text('Book Now');
                }
            });
        },
        
        /**
         * ========================================
         * FORM VALIDATION
         * ========================================
         */
        validateForm: function() {
            var $form = $('.wc-booking-form');
            var isValid = true;
            var errors = [];
            
            // Check date
            if (!$('#booking_date').val()) {
                errors.push('Please select a date');
                isValid = false;
            }
            
            // Check time
            if (!$('#booking_time').val()) {
                errors.push('Please select a time slot');
                isValid = false;
            }
            
            // Check person types (at least one)
            var hasPersonTypes = false;
            $('.person-type-input input').each(function() {
                if (parseInt($(this).val()) > 0) {
                    hasPersonTypes = true;
                }
            });
            
            if (!hasPersonTypes) {
                errors.push('Please select at least one person type');
                isValid = false;
            }
            
            // Check resource if required
            if ($('.wc-booking-form #resource_id').is('[required]') && 
                !$('#resource_id').val()) {
                errors.push('Please select a guide/resource');
                isValid = false;
            }
            
            // Show errors
            if (errors.length > 0) {
                alert(errors.join('\n'));
                isValid = false;
            }
            
            return isValid;
        },
        
        /**
         * ========================================
         * UTILITY FUNCTIONS
         * ========================================
         */
        showError: function(message) {
            alert(message);
        },
        
        // (duplicated formatCurrency intentionally removed; the version above wins)
    };
    
    /**
     * Initialize when DOM is ready
     */
    $(document).ready(function() {
        WCBookingCalendar.init();
    });
    
    /**
     * Initialize when WooCommerce is ready
     */
    if (typeof wc !== 'undefined') {
        $(document.body).on('updated_checkout', function() {
            // Recalculate if needed
        });
    }

})(jQuery);
