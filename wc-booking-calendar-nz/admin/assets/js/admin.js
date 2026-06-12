/**
 * WC Booking Calendar - Admin JavaScript
 * Calendar view, booking management, and admin tools
 */

(function($) {
    'use strict';
    
    var WCBookingCalendarAdmin = {
        
        /**
         * Current month and year
         */
        currentMonth: new Date().getMonth(),
        currentYear: new Date().getFullYear(),
        blackoutLookup: {},
        
        /**
         * Initialize plugin
         */
        init: function() {
            this.initCalendar();
            this.initEventListeners();
            this.initReports();
        },
        
        /**
         * ========================================
         * CALENDAR INITIALIZATION
         * ========================================
         */
        
        /**
         * Initialize calendar
         */
        initCalendar: function() {
            var $app = $('#wc-booking-calendar-app');
            if ($app.length) {
                var initialMonth = parseInt($app.data('month'), 10);
                var initialYear = parseInt($app.data('year'), 10);
                if (!isNaN(initialMonth) && initialMonth >= 1 && initialMonth <= 12) {
                    this.currentMonth = initialMonth - 1;
                }
                if (!isNaN(initialYear) && initialYear > 0) {
                    this.currentYear = initialYear;
                }
            }
            this.blackoutLookup = this.buildBlackoutLookup();
            this.renderCalendar();
        },
        
        /**
         * Render calendar
         */
        renderCalendar: function() {
            var self = this;
            var firstDay = new Date(self.currentYear, self.currentMonth, 1);
            var lastDay = new Date(self.currentYear, self.currentMonth + 1, 0);
            var daysInMonth = lastDay.getDate();
            var startingDayOfWeek = firstDay.getDay();
            
            // Update month display
            var monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                            'July', 'August', 'September', 'October', 'November', 'December'];
            $('#current-month').text(monthNames[self.currentMonth] + ' ' + self.currentYear);
            
            // Clear calendar days
            $('#calendar-days').empty();
            
            // Add empty cells for days before month starts
            for (var i = 0; i < startingDayOfWeek; i++) {
                $('#calendar-days').append('<div class="calendar-day other-month"></div>');
            }
            
            // Add days of month
            for (var day = 1; day <= daysInMonth; day++) {
                var dateStr = self.currentYear + '-' + 
                            String(self.currentMonth + 1).padStart(2, '0') + '-' + 
                            String(day).padStart(2, '0');
                
                var isToday = self.isToday(day, self.currentMonth, self.currentYear);
                var isBlackout = self.isBlackoutDate(dateStr);
                var dayClass = 'calendar-day';
                if (isToday) {
                    dayClass += ' today';
                }
                if (isBlackout) {
                    dayClass += ' blackout';
                }
                
                $('#calendar-days').append(
                    '<div class="' + dayClass + '" data-date="' + dateStr + '">' +
                    '<div class="day-number">' + day + '</div>' +
                    '<div class="booking-dots"></div>' +
                    (isBlackout ? '<div class="calendar-day-note">' + wc_booking_calendar_admin.i18n.blackout_date + '</div>' : '') +
                    '</div>'
                );
            }
            
            // Load bookings for current month
            self.loadBookingsForMonth();
        },
        
        /**
         * Load bookings for current month
         */
        loadBookingsForMonth: function() {
            var self = this;
            var startDate = self.currentYear + '-' + 
                          String(self.currentMonth + 1).padStart(2, '0') + '-01';
            var endDate = self.currentYear + '-' + 
                         String(self.currentMonth + 1).padStart(2, '0') + '-31';
            
            $.ajax({
                url: wc_booking_calendar_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_booking_admin_get_bookings',
                    nonce: wc_booking_calendar_admin.nonce,
                    start_date: startDate,
                    end_date: endDate
                },
                success: function(response) {
                    if (response.success) {
                        self.renderBookingDots(response.data.bookings);
                    }
                }
            });
        },
        
        /**
         * Render booking dots on calendar
         */
        renderBookingDots: function(bookings) {
            var self = this;
            
            bookings.forEach(function(booking) {
                var bookingDate = booking.booking_date;
                var $dayCell = $('[data-date="' + bookingDate + '"]');
                
                if ($dayCell.length) {
                    var $dotsContainer = $dayCell.find('.booking-dots');
                    var dotClass = 'booking-dot ' + booking.status;
                    
                    $dotsContainer.append('<span class="' + dotClass + '" title="' + 
                                        booking.product_name + ' - ' + booking.status + '"></span>');
                }
            });
        },

        buildBlackoutLookup: function() {
            var lookup = {};
            (wc_booking_calendar_admin.blackout_dates || []).forEach(function(date) {
                if (date) {
                    lookup[String(date)] = true;
                }
            });
            return lookup;
        },

        isBlackoutDate: function(date) {
            return !!this.blackoutLookup[String(date || '')];
        },
        
        /**
         * Check if date is today
         */
        isToday: function(day, month, year) {
            var today = new Date();
            return day === today.getDate() && 
                   month === today.getMonth() && 
                   year === today.getFullYear();
        },
        
        /**
         * ========================================
         * EVENT LISTENERS
         * ========================================
         */
        
        /**
         * Initialize event listeners
         */
        initEventListeners: function() {
            var self = this;
            
            // Previous month
            $(document).on('click', '#prev-month', function() {
                if (self.currentMonth === 0) {
                    self.currentMonth = 11;
                    self.currentYear--;
                } else {
                    self.currentMonth--;
                }
                self.renderCalendar();
            });
            
            // Next month
            $(document).on('click', '#next-month', function() {
                if (self.currentMonth === 11) {
                    self.currentMonth = 0;
                    self.currentYear++;
                } else {
                    self.currentMonth++;
                }
                self.renderCalendar();
            });
            
            // Today button
            $(document).on('click', '#today', function() {
                var today = new Date();
                self.currentMonth = today.getMonth();
                self.currentYear = today.getFullYear();
                self.renderCalendar();
            });
            
            // Day click
            $(document).on('click', '.calendar-day', function() {
                var date = $(this).data('date');
                if (!date) {
                    return;
                }
                self.showDayDetails(date);
            });
            
            // Update booking status
            $(document).on('click', '.update-booking-status', function() {
                var $button = $(this);
                var bookingId = $button.data('booking-id');
                var status = $button.data('status');
                
                if (!confirm(wc_booking_calendar_admin.i18n.confirm_status_change)) {
                    return;
                }
                
                self.updateBookingStatus(bookingId, status);
            });
            
            // Delete booking
            $(document).on('click', '.delete-booking', function() {
                var $button = $(this);
                var bookingId = $button.data('booking-id');
                
                if (!confirm(wc_booking_calendar_admin.i18n.confirm_delete)) {
                    return;
                }
                
                self.deleteBooking(bookingId);
            });
            
            // Export bookings
            $(document).on('click', '#export-bookings', function() {
                self.exportBookings();
            });
        },
        
        /**
         * ========================================
         * DAY DETAILS
         * ========================================
         */
        
        /**
         * Show day details
         */
        showDayDetails: function(date) {
            var self = this;
            var $dayDetails = $('#day-details');
            var $selectedDate = $('#selected-date');
            
            $selectedDate.text(date);
            $dayDetails.show();
            
            // Load bookings for this day
            $.ajax({
                url: wc_booking_calendar_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_booking_admin_get_bookings',
                    nonce: wc_booking_calendar_admin.nonce,
                    start_date: date,
                    end_date: date
                },
                success: function(response) {
                    if (response.success) {
                        self.renderBookingsList(response.data.bookings, date);
                    }
                }
            });
        },
        
        /**
         * Render bookings list
         */
        renderBookingsList: function(bookings, date) {
            var $bookingsList = $('#bookings-list');
            $bookingsList.empty();
            
            if (this.isBlackoutDate(date)) {
                $bookingsList.append('<div class="booking-admin-note blackout-note">' + wc_booking_calendar_admin.i18n.blackout_date + '</div>');
            }
            if (bookings.length === 0) {
                $bookingsList.append('<p>' + wc_booking_calendar_admin.i18n.no_bookings + '</p>');
                return;
            }
            
            bookings.forEach(function(booking) {
                var $bookingItem = $('<div class="booking-item">');
                $bookingItem.append('<h4>' + booking.product_name + '</h4>');
                $bookingItem.append('<p><strong>' + wc_booking_calendar_admin.i18n.date + ':</strong> ' + booking.booking_date + '</p>');
                $bookingItem.append('<p><strong>' + wc_booking_calendar_admin.i18n.time + ':</strong> ' + booking.booking_time + '</p>');
                $bookingItem.append('<p><strong>' + wc_booking_calendar_admin.i18n.people + ':</strong> ' + booking.person_count + '</p>');
                $bookingItem.append('<p><strong>' + wc_booking_calendar_admin.i18n.total + ':</strong> ' + booking.total_price + '</p>');
                $bookingItem.append('<p><strong>' + wc_booking_calendar_admin.i18n.status + ':</strong> <span class="status-' + booking.status + '">' + booking.status + '</span></p>');
                
                if (booking.resource_name) {
                    $bookingItem.append('<p><strong>' + wc_booking_calendar_admin.i18n.resource + ':</strong> ' + booking.resource_name + '</p>');
                }
                
                if (booking.special_requests) {
                    $bookingItem.append('<p><strong>' + wc_booking_calendar_admin.i18n.special_requests + ':</strong> ' + booking.special_requests + '</p>');
                }
                
                // Actions
                var $actions = $('<div class="booking-actions">');
                
                if (booking.status === 'pending') {
                    $actions.append('<button class="button button-small update-booking-status" data-booking-id="' + booking.id + '" data-status="confirmed">' + 
                                  wc_booking_calendar_admin.i18n.confirm + '</button>');
                }
                
                if (booking.status !== 'cancelled') {
                    $actions.append('<button class="button button-small update-booking-status" data-booking-id="' + booking.id + '" data-status="cancelled">' + 
                                  wc_booking_calendar_admin.i18n.cancel + '</button>');
                }
                
                $actions.append('<button class="button button-small delete-booking" data-booking-id="' + booking.id + '">' + 
                              wc_booking_calendar_admin.i18n.delete + '</button>');
                
                $bookingItem.append($actions);
                $bookingsList.append($bookingItem);
            });
        },
        
        /**
         * ========================================
         * AJAX OPERATIONS
         * ========================================
         */
        
        /**
         * Update booking status
         */
        updateBookingStatus: function(bookingId, status) {
            var self = this;
            
            $.ajax({
                url: wc_booking_calendar_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_booking_admin_update_booking',
                    nonce: wc_booking_calendar_admin.nonce,
                    booking_id: bookingId,
                    status: status
                },
                success: function(response) {
                    if (response.success) {
                        alert(wc_booking_calendar_admin.i18n.status_updated);
                        self.loadBookingsForMonth();
                        self.showDayDetails($('#selected-date').text());
                    } else {
                        alert(response.data.message || 'Error updating booking');
                    }
                },
                error: function() {
                    alert('Network error');
                }
            });
        },
        
        /**
         * Delete booking
         */
        deleteBooking: function(bookingId) {
            var self = this;
            
            $.ajax({
                url: wc_booking_calendar_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_booking_admin_delete_booking',
                    nonce: wc_booking_calendar_admin.nonce,
                    booking_id: bookingId
                },
                success: function(response) {
                    if (response.success) {
                        alert(wc_booking_calendar_admin.i18n.booking_deleted);
                        self.loadBookingsForMonth();
                        self.showDayDetails($('#selected-date').text());
                    } else {
                        alert(response.data.message || 'Error deleting booking');
                    }
                },
                error: function() {
                    alert('Network error');
                }
            });
        },
        
        /**
         * Export bookings
         */
        exportBookings: function() {
            var self = this;
            
            $.ajax({
                url: wc_booking_calendar_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_booking_admin_export_bookings',
                    nonce: wc_booking_calendar_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Create CSV
                        var csv = 'ID,Product,Date,Time,Resource,People,Total,Status,Special Requests\n';
                        
                        response.data.data.forEach(function(booking) {
                            csv += booking.id + ',' +
                                  '"' + booking.product + '",' +
                                  booking.date + ',' +
                                  booking.time + ',' +
                                  '"' + booking.resource + '",' +
                                  booking.people + ',' +
                                  booking.total + ',' +
                                  booking.status + ',' +
                                  '"' + booking.special_requests + '"\n';
                        });
                        
                        // Download
                        var blob = new Blob([csv], { type: 'text/csv' });
                        var url = window.URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = 'bookings-export-' + new Date().toISOString().split('T')[0] + '.csv';
                        a.click();
                        window.URL.revokeObjectURL(url);
                    }
                }
            });
        },
        
        /**
         * ========================================
         * REPORTS
         * ========================================
         */
        
        /**
         * Initialize reports
         */
        initReports: function() {
            this.renderMonthlyChart();
        },
        
        /**
         * Render monthly chart
         */
        renderMonthlyChart: function() {
            // Placeholder for chart
            // You can use Chart.js or any other charting library
            $('#monthly-chart').html('<p>Chart will be rendered here. Implement with Chart.js or similar library.</p>');
        }
    };
    
    /**
     * Initialize when DOM is ready
     */
    $(document).ready(function() {
        WCBookingCalendarAdmin.init();
    });
    
    /**
     * Expose to global scope
     */
    window.WCBookingCalendarAdmin = WCBookingCalendarAdmin;

})(jQuery);
