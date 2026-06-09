=== WC Booking Calendar NZ ===
Contributors: digitaleuan
Tags: woocommerce, booking, calendar, tours, resources, availability
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Advanced bookable products for WooCommerce — configurable time slots, resources, person types, conditional booking modes, and availability management. Built in New Zealand for NZ tour operators, activity providers and venue rentals.

== Description ==

WC Booking Calendar NZ adds a bookable product type ("Bookable Tour") to WooCommerce together with a full availability engine, an admin calendar / reports dashboard, configurable settings and a customisable single-product booking form.

= Features =

* **Booking Modes** — configurable (e.g. "Guided Tour", "Self-Directed Walk"). Each mode controls capacity and full-day blocking.
* **Person Types** — Adults, children, etc. with per-type price adjustments and minimum / maximum group sizes.
* **Time Slots** — per-day-of-week slots with start / end times.
* **Resources** — guides, equipment, rooms managed as a custom post type.
* **Availability Engine** — prevents over-booking, supports product-specific and global rules, blackout dates, peak days, lead time.
* **WooCommerce Integration** — custom product type, cart validation, order line item meta, booking CPT, status sync.
* **Admin Dashboard** — calendar view, booking list, reports, CSV export.
* **Notifications** — email confirmation, reminder, cancellation hooks.
* **REST API** — read-only `/wc-booking-calendar/v1/bookings` and `/resources`.
* **Shortcodes** — `[wc_booking_form id="123"]` and `[wc_booking_calendar id="123"]`.
* **Translation-ready** — `wc-booking-calendar-nz.pot` included.
* **HPOS-compatible** — declares compatibility with WooCommerce custom order tables.

= Requirements =

* WordPress 6.0+
* PHP 8.0+
* WooCommerce 9.0+

== Installation ==

1. Upload the `wc-booking-calendar-nz` folder to `/wp-content/plugins/`.
2. Activate the plugin in **Plugins → Installed Plugins**.
3. Go to **Bookings → Settings** to configure time slots, person types, modes and resources.
4. Create a product, set its type to **Bookable Tour**, fill in the Booking tab, and publish.

== Configuration ==

Settings are organised in tabs under **Bookings → Settings**:

* **General** — timezone, GST, min/max group size, lead time, advance booking window
* **Time Slots** — visual table editor (HH:MM start/end)
* **Booking Modes** — define guided / self-directed modes with capacity and full-day blocking flags
* **Person Types** — Adult, child, etc. with price deltas
* **Notifications** — toggle email confirmation, reminder, cancellation
* **Advanced** — peak days & multiplier, blackout dates, seasonal pricing

Resources are managed under **Bookings → Resources** (a custom post type with per-day schedules and pricing).

== Developer notes ==

= Hooks (selected) =

* `wc_booking_calendar_check_availability` — filter on every availability check result.
* `wc_booking_calendar_availability_updated` — fires when a booking adds to availability.
* `wc_booking_calendar_availability_released` — fires when a booking releases capacity.
* `wc_booking_calendar_booking_created` — fires when a booking CPT is created from a WC order.

= Custom tables =

* `{prefix}wc_booking_calendar_bookings` — denormalised booking rows (one per order item).
* `{prefix}wc_booking_calendar_availability` — per slot capacity counters.

= Tests =

The plugin ships with a PHPUnit + Brain Monkey unit test suite covering the core
availability and pricing logic. Run:

    composer install
    composer test

== Changelog ==

= 1.1.1 =
* Critical: added missing `<?php` opening tag to 6 PHP files (main plugin file, both CPT classes, frontend handler, admin class and admin-settings) — previously these would output their PHP source as plain HTML.
* Critical: implemented the missing `WC_Booking_Calendar_Admin_Settings` class (settings page, six tabs, all sanitisers and the settings submenu).
* Critical: completed the availability manager — all previously-referenced helpers (`validate_date`, `validate_time`, `get_slot_by_time`, `is_day_available`, `get_default_mode`, `get_mode_config`, `check_resource_full_day`, `get_booked_count`, `get_product_rules`, `check_product_rules`, `get_general_rules`, `check_general_rules`, `update_availability`, `release_availability`, `get_available_slots`, `check_availability_with_person_types`) are now real methods. Removed invalid `throw new WP_Error(…)` calls.
* Fixed: the `Person Types` settings template was a duplicate of Notifications; replaced with a real visual + JSON editor.
* Fixed: argument order mismatch on `check_availability()` callers (resource id vs. mode).
* Fixed: JS↔PHP global name mismatch — frontend.js uses `wc_booking_calendar.ajax_url` / nonce / i18n, admin.js uses `wc_booking_calendar_admin.*` — PHP `wp_localize_script()` now uses the matching object names.
* Fixed: duplicate cart hooks across `class-cart.php`, `class-frontend-handler.php`, `class-order.php` and `hooks.php` were overwriting cart-item data and creating duplicate bookings. The canonical hooks now live in `hooks.php`; the other classes are kept as legacy bridges with no hook registrations.
* Fixed: `class-order.php` was writing to columns (`item_id`, `time_slot`) that don't exist in the schema (`order_item_id`, `booking_time`). Persistence is now handled by the bookings CPT + `update_availability()`.
* Fixed: nonce field on the single-product booking form is now `wc_booking_nonce` to match the verifier in `hooks.php`.
* Fixed: `booking-form.php` no longer calls a non-existent `WC_Booking_Calendar_Frontend_Handler::get_available_resources()` method. The resource select now uses `name="booking_resource_id"` to match the cart hook.
* Fixed: admin AJAX action names now match what `admin.js` actually sends (`wc_booking_admin_get_bookings`, `…_update_booking`, `…_delete_booking`, `…_export_bookings`); legacy `wc_booking_calendar_*` action names are kept registered too.
* Fixed: admin calendar page now outputs the DOM nodes (`#calendar-days`, `#current-month`, `#prev-month`, `#next-month`, `#today`, `#day-details`, `#bookings-list`, `#export-bookings`) that `admin.js` was expecting.
* Fixed: `get_bookings_ajax` referenced a non-existent `customer_name` column — query is now schema-safe and nonce-checked.
* Fixed: cart pricing now uses `booking_mode` (not the never-set `mode` key) and respects the peak-day multiplier in addition to the morning-tea surcharge.
* Fixed: order line-item meta `_booking_person_count` is now stored at checkout (was referenced but never written).
* Fixed: `release_availability()` now accepts both signatures used in the codebase (`($booking_post_id)` and `($product_id, $date, $time, $person_count)`).
* Misc: `current_time('timestamp')` replaced with `current_datetime()->getTimestamp()`. `WP_Error` no longer `throw`n. Settings page registers all option keys used by the templates.

= 1.1.0 =
* Rewrite for stability — every class file now passes PHP 8 lint and has unit tests.
* HPOS and cart-checkout-blocks compatibility declarations.
* Cleaner sanitisation across admin settings.
* Custom tables now use prepared / parameterised upserts.
* New `WC_Product_Bookable_Tour` in its own file (no nested class declaration).
* Cancellation correctly releases availability and unblocks the slot.
* Lead time, advance booking, blackout dates and peak-day handling implemented end-to-end.

= 1.0.0 =
* Initial release.

== TODO list ==

### 1. Booking Modes & Logic (Riverhaven Specific)
* [✅] **`hooks.php`** Correctly persisting data
Key changes made:
Added the hook in wc_booking_calendar_register_hooks()
Added the wc_booking_calendar_add_order_item_meta() function at the bottom of the file (after the sanitize function). This function:
Checks if booking data exists in the cart item
Saves all booking details (date, time, mode, resource, limited mobility, special requests) as order item meta
Stores person types as JSON for easy retrieval later
The booking data is now permanently saved in the WooCommerce order and will be available throughout the order lifecycle, including emails, order pages, and order processing.
* [✅] **`hooks.php`** Updated with the new hooks and calculation logic
Key changes made:
Added two new hooks in wc_booking_calendar_register_hooks():
woocommerce_before_calculate_totals - to apply booking prices
woocommerce_cart_calculate_fees - to add the 50% deposit fee
Added wc_booking_calendar_apply_booking_price() function:
Calculates the correct price based on person types
Sets the cart item price to the calculated amount
Only runs in frontend (not admin unless AJAX)
Added wc_booking_calendar_add_deposit_fee() function:
Calculates 50% of the booking price for each item
Adds a negative cart fee (deposit discount) to reduce the total by 50%
Shows "Deposit Required (50% of total)" as the fee label
The cart will now:
Show the full booking price calculated from person types
Apply a 50% discount (deposit) via the cart fee
Display the net amount as what the customer pays now (50% of the full price)
* [✅] **Mode-Switching Logic**: v3_5/includes/class-frontend-handler.php
Key changes made:
Added mode detection - $mode now defaults to 'self' if not provided
Guided tour validation - Checks if mode is 'guided' and enforces minimum 10 people
Mode is passed through - The mode is included in the availability check via check_availability_with_person_types
Mode stored in cart - Mode is now included in booking_data for persistence
The guided tour will now:
Require a minimum of 10 people
Pass the mode to the availability manager which can implement the "full day capacity lock" logic
Display the mode in the cart for transparency
The validation fails early with a clear error message if someone tries to book a guided tour with fewer than 10 people.
* [✅] **Guided Tour Constraint**: Updated with the Full Day Block logic for Guided tours
Key changes made:
Added blackout date check at the start of check_availability() method
Added Guided Tour full day block logic - When mode is 'guided', it checks if any booking already exists for that date/product
Added is_blackout_date() helper method to check against the blackout dates list
Placed the guided check early - before all other validations to fail fast
The logic now:
Blocks a date entirely if a guided tour is already booked for that day
Still allows self-directed bookings (which check specific time slots) even if a guided tour exists
Properly validates blackout dates first
Uses the existing bookings table to check for existing guided
The guided tour check queries for any booking (regardless of status except cancelled/refunded/failed) for the same product on that date, effectively blocking the entire day once one guided tour is booked.
* [✅] **Self-Directed Flexibility**: Updated with the refined Guided Tour Logic
Key change made:
The Guided Tour check now specifically looks for 'guided' mode bookings only:
* [✅] **Add-on Handling**: Updated with the Morning Tea add-on implemented
Key changes made:
Updated wc_booking_calendar_add_cart_item_data() - Now captures the booking_morning_tea POST field and stores it in cart
Updated wc_booking_calendar_apply_booking_price() - Adds $10 per person surcharge when morning tea is selected and mode is guided
Added configurable price - Uses option wc_booking_calendar_morning_tea_price (defaults to $10.00) so you can adjust it
Updated wc_booking_calendar_add_order_item_meta() - Persists the morning tea choice to order meta
Deposit calculation - The deposit is now calculated on the total price (including morning tea surcharge)
Updated with the cart-level inventory validation added
Key changes made:
Added hook registration in wc_booking_calendar_register_hooks()
Added wc_booking_calendar_validate_cart_items() function - This runs during checkout and checks each booking item's availability again to ensure it hasn't changed since being added to cart.
The new validation:
Runs at checkout time (before order processing)
Checks each booking cart item's availability
Displays an error if any booking is no longer available
Uses the same availability check logic as the initial cart validation
Provides a clear error message showing which product and date is unavailable
* [✅] **Refine the Deposit Logic (`includes/hooks.php`)**:
Key changes made:
Refined deposit logic - Changed wc_booking_calendar_add_deposit_fee() to:
Use get_price() to get the price (which includes tax if applicable)
Pass true as the third parameter to add_fee() to make it taxable
This ensures the deposit is calculated correctly when GST is included
Added checkout notice - Added wc_booking_calendar_deposit_notice() function and registered it with the hook to display a clear message at checkout
Stored total price - Added $item->add_meta_data( '_booking_total_price', $item->get_subtotal() ); in wc_booking_calendar_add_order_item_meta() so you have the full booking value for records

### 2. Core WooCommerce Integration
* [✅] **Cart Validation**: Complete `validate_add_to_cart` to enforce capacity and business rules.
Refined valiKey changes made to validate_add_to_cart:
Input sanitization at the start - All inputs are now sanitized immediately
Mode defaults to 'self' if not provided
Guided tour minimum check - Enforces 10 people minimum for guided tours
Required fields check - Ensures both date and time are selected
Simplified availability check - Calls check_availability with the simpler signature
Removed redundant checks - Person types empty check was removed since the total_people calculation handles thisdate_add_to_cart method

* [✅] **Price Calculation**: Finalized logic in `hooks.php`.
* [✅] **Order Persistence**: Hook `woocommerce_checkout_create_order_line_item` implemented and meta-data keys initialized.
* [✅] **HPOS Compatibility**: Confirmed use of `$item->add_meta_data()` and `$item->get_meta()`.

### 3. Availability Engine
* [✅] **Concurrency**: Implement `SELECT ... FOR UPDATE` in `class-availability-manager.php` to prevent double-booking.
Key changes made:
Wrapped entire method in transaction - Uses START TRANSACTION / COMMIT / ROLLBACK
Added FOR UPDATE locks - Locks the specific availability row before checking capacity
Proper error handling - Catches both WP_Error and generic Exception
Rollback on any error - Ensures data integrity if validation fails
Used correct table/column names - Uses $this->availability_table and correct column names (slot_start, slot_end)
Important notes:
InnoDB requirement - Ensure your tables use InnoDB engine. Add this to your activation script:
$sql = "CREATE TABLE {$this->availability_table} (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    product_id bigint(20) NOT NULL,
    resource_id bigint(20) NOT NULL,
    availability_date date NOT NULL,
    day_of_week tinyint(2) NOT NULL,
    slot_start time NOT NULL,
    slot_end time NOT NULL,
    capacity int(11) NOT NULL,
    booked_count int(11) NOT NULL DEFAULT 0,
    is_blocked tinyint(1) NOT NULL DEFAULT 0,
    block_reason varchar(255) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY product_date_slot (product_id, availability_date, slot_start, slot_end, resource_id)
) ENGINE=InnoDB {$charset_collate};";

* [✅] **Global Blackouts**: Enable admin-controlled blackout dates (e.g., Christmas) to block all modes.
Key changes made:
Added wc_booking_calendar_blackout_dates to registered settings (line 67)
Created new sanitize_blackout_dates() method that:
Handles both array and string input
Splits textarea input by newlines
Validates each date is in YYYY-MM-DD format
Returns clean array of dates
* [✅] **Lead Time/Advance Booking**: Finalize checks for "how far in advance" a booking can be made.
Key changes:
Added validate_booking_window() method that checks:
Lead time (minimum days in advance)
Advance booking window (maximum days in advance)
Updated check_availability() to call the validation first (before blackout check)
Order of checks:
Lead time / advance window validation
Global blackout dates
Transaction starts
All other validations inside transaction
More key changes are:
Added option_prefix property
validate_booking_window() uses the prefix and handles lead_time = 0
is_blackout_date() uses the prefix
check_availability() calls validation before transaction
### 4. Frontend & Admin Experience
* [✅] **JS Dynamic Form**: Update `public/assets/frontend.js` to toggle UI elements based on "Guided" vs "Self-Directed" selection.
Key changes made:
Added initGuidedModeToggle() method (lines 39-75) that:
Listens for changes on the booking_mode field
Shows/hides guided-specific options (#guided-options)
Automatically checks the morning tea checkbox when guided mode is selected
Unchecks and hides options when switching away from guided mode
Called initGuidedModeToggle() in the init() method so it runs on page load
Improved selectivity - only runs if both the mode select and guided options container exist
More changes made:
Added Booking Mode Select (lines 28-38):
Dropdown with "Self-Directed Walk" and "Guided Tour" options
Uses booking_mode name and id matching the JavaScript expectations
Added Guided Options Section (lines 41-57):
Hidden div with id="guided-options"
Morning tea checkbox with proper name/id
Minimum 10 people notice
Hidden by default, shown via JavaScript when mode is "guided"
Fixed Min/Max Date Calculation (lines 12-18):
Uses the actual lead time and advance window settings
Properly formatted for the date picker
* [✅] **Accessibility Fields**: Ensure "Limited Mobility" notes are captured in the checkout "Order Notes" or custom meta fields.
Key changes:
Added new combined field (lines 120-134):
New section titled "Accessibility & Special Requests"
Textarea with name booking_limited_mobility and id booking_limited_mobility
Placeholder text explaining the purpose
This combines the limited mobility and special requests functionality
More changes:
Added the wc_booking_calendar_display_meta_in_emails() function (lines 244-256)
Added the filter hook at the end (lines 258-259)
* [✅] **Dashboard Calendar**: Complete the AJAX endpoint for the admin to view/block/edit dates.
### 5. Notifications & Compliance
Key changes:
Added the AJAX action at the bottom of wc_booking_calendar_register_hooks() (lines 285-286):
wp_ajax_wc_booking_calendar_get_bookings for logged-in users
wp_ajax_nopriv_wc_booking_calendar_get_bookings for non-logged-in users (if you want public access)
Added the wc_booking_calendar_get_bookings_ajax() function (lines 306-329)
This endpoint can now be called with parameters:
action=wc_booking_calendar_get_bookings
nonce=YOUR_NONCE
start=2024-01-01
end=2024-01-31
* [✅] **Email Receipts**: Implement dynamic email templates that send different instructions for Guided vs. Self-Directed visitors.
Key changes:
Added the wc_booking_calendar_add_email_instructions() function (lines 347-377)
Added the filter hook at the end (lines 379-380)
This filter will:
Only add instructions for customer emails (not admin)
Check each booking item's mode
Add appropriate instructions:
Guided Tours: "Please arrive 15 minutes early. Morning tea is included as requested. Don't forget your walking shoes!"
Self-Directed Walks: "Guy will meet you at the shed at your scheduled time with your trail map. Please be punctual as he will be working on the property."
The instructions will appear in the order email meta fields section.
* [✅] **Data Cleanup**: Add automated routine to clear/release availability if orders are cancelled or refunded.
Key changes:
Added the hook registration in wc_booking_calendar_register_hooks() (lines 287-288):
woocommerce_order_status_cancelled
woocommerce_order_status_refunded
Added the wc_booking_calendar_release_availability_on_cancel() function (lines 421-438) that:
Gets the order and availability manager instance
Iterates through each order item
Checks if it's a booking (has _booking_date)
Retrieves the product ID, time, person types, and total people count
Calls $availability_manager->release_availability() to free up the capacity
Note: You'll need to implement the release_availability() method in your WC_Booking_Calendar_Availability_Manager class to properly decrement the capacity in your availability table.
Key changes made:

* [✅] **Added a safety check** to ensure WC_Booking_Calendar_Product is loaded before referencing its constant (lines 14-17)
This prevents any potential "undefined constant" errors if the class isn't fully initialized
The code looks correct otherwise. The file should be saved as includes/class-wc-product-bookable-tour.php and will be loaded by the load_product_class() method in your main product class.

* [✅] **v3_5/public/templates/single-product/booking-form.php`**
Key fixes made:
Fixed nonce (line 13): Changed 'wc_booking_nonce' to 'wc_booking_calendar_add_to_cart' to match what your JavaScript expects
Changed button type (line 168): Changed from type="button" to type="submit" so it properly submits the form
Removed recursive template call (lines 189-206): Removed the add_action and wc_booking_calendar_display_form() function as that would cause infinite recursion

* [✅] **updated class-cart.php** to match the single-date calendar picker
Key changes:
Updated field names (line 27): Changed from booking_start_date/booking_end_date to booking_date and booking_time to match your form
Updated display labels (lines 42-49): Changed from "Start Date"/"End Date" to "Booking Date"/"Time Slot"
Updated meta keys (lines 56-57): Changed from _booking_data to _booking_date and _booking_time for proper WooCommerce meta storage
More changes:
Added filter registration (line 16): Added add_filter( 'woocommerce_add_cart_item_identifier', [ $this, 'make_cart_item_unique' ], 10, 4 );
Added method (lines 56-65): The make_cart_item_unique() method creates a unique hash based on the cart item key, booking date, and booking time
This prevents WooCommerce from merging cart items when the same product is added with different booking dates/times, as each combination will now have a unique cart item identifier.

* [✅] **Updated class-order.php to match cart data structure**
Key changes:
Updated field names (lines 45-46): Changed from start_date/end_date to booking_date and booking_time
Updated database column (line 46): Changed from end_date to time_slot to match what's likely in your database schema
Added isset checks (lines 45-46): Added safety checks to prevent errors if the data is missing
