=== WC Booking Calendar NZ ===
Contributors: digitaleuan
Tags: woocommerce, booking, calendar, tours, resources, availability
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 1.1.0
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

== Manual Test Plan ==

See `tests/MANUAL-TEST-PLAN.md` in the source for the full QA checklist
(guided vs self-directed, limited mobility, peak days, cancellations,
multi-product carts, HPOS orders).