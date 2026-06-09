/**
 * Register settings & sanitizers.
 *
 * @return void
 */
public function register_settings() {
    $options = array(
        'wc_booking_calendar_time_slots'      => array( $this, 'sanitize_time_slots' ),
        'wc_booking_calendar_days_of_week'    => array( $this, 'sanitize_days_of_week' ),
        'wc_booking_calendar_booking_modes'   => array( $this, 'sanitize_booking_modes' ),
        'wc_booking_calendar_person_types'    => array( $this, 'sanitize_person_types' ),
        'wc_booking_calendar_notifications'   => array( $this, 'sanitize_notifications' ),
        'wc_booking_calendar_advanced'        => array( $this, 'sanitize_advanced' ),
        'wc_booking_calendar_gst_inclusive'   => 'sanitize_text_field',
        'wc_booking_calendar_min_group_size'  => 'absint',
        'wc_booking_calendar_max_group_size'  => 'absint',
        'wc_booking_calendar_lead_time'       => 'absint',
        'wc_booking_calendar_advance_window'  => 'absint',
        'wc_booking_calendar_advance_days'    => 'absint',
        'wc_booking_calendar_timezone'        => 'sanitize_text_field',
        'wc_booking_calendar_blackout_dates'  => array( $this, 'sanitize_blackout_dates' ),
    );
    foreach ( $options as $key => $callback ) {
        register_setting( 'wc_booking_calendar_settings', $key, array( 'sanitize_callback' => $callback ) );
    }
}
