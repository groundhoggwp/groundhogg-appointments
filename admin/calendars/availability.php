<?php
namespace GroundhoggBookingCalendar\Admin\Calendars;

use function Groundhogg\get_array_var;
use function Groundhogg\get_request_var;
use function Groundhogg\html;
use GroundhoggBookingCalendar\Classes\Calendar;
use function GroundhoggBookingCalendar\days_of_week;
use function GroundhoggBookingCalendar\get_default_availability;

$cols = [
	__( 'Day & Hours' ),
	__( 'Actions' ),
];

/**
 * @var $calendar Calendar;
 */

$calendar_id = absint( get_request_var( 'calendar' ) );
$calendar    = new Calendar( $calendar_id );

$rules = $calendar->get_meta( 'rules' ); // TODO Get rules somehow.

if ( ! $rules ) {
	$rules = get_default_availability();
}

$rows = [];

function rule_name( $att = '' ) {
	return sprintf( 'rules[%s][]', $att );
}

foreach ( $rules as $rule ):

	$rows[] = [

		html()->e( 'div', [ 'class' => 'gh-input-group' ], [
			html()->dropdown( [
				'name'     => rule_name( 'day' ),
				'options'  => days_of_week(),
				'selected' => get_array_var( $rule, 'day' ),
			] ),
			html()->input( [
				'type'  => 'time',
				'name'  => rule_name( 'start' ),
				'value' => get_array_var( $rule, 'start' ),
				'class' => 'input'
			] ),
			html()->input( [
				'type'  => 'time',
				'name'  => rule_name( 'end' ),
				'value' => get_array_var( $rule, 'end' ),
				'class' => 'input'
			] ),
		] ),
		html()->wrap( [
			html()->e( 'a', [ 'href'  => '#add',
			                  'class' => 'gh-button secondary text icon add add-rule'
			], '<span class="dashicons dashicons-plus"></span>' ),
			html()->e( 'a', [ 'href'  => '#trash',
			                  'class' => 'gh-button danger text icon trash-rule'
			], '<span class="dashicons dashicons-trash"></span>' ),
		], 'div', [
			'class' => 'display-flex'
		] )

	];

endforeach;

?>
<form method="post">
	<?php wp_nonce_field(); ?>
    <h2><?php _e( 'Availability', 'groundhogg-calendar' ); ?></h2>
    <style>select {
            vertical-align: top !important;
        }</style>

	<?php
	html()->start_form_table();

	html()->start_row();

	html()->th( __( 'Minimum booking period' ) );
	html()->td( [
		// number
		html()->number( [
			'name'  => 'min_booking_period_count',
			'value' => $calendar->get_meta( 'min_booking_period_count' ),
			'class' => 'input'
		] ),
		// month|day|year
		html()->dropdown( [
			'name'     => 'min_booking_period_type',
			'selected' => $calendar->get_meta( 'min_booking_period_type' ),
			'options'  => [
				'hours'  => __( 'Hours' ),
				'days'   => __( 'Days' ),
				'weeks'  => __( 'Weeks' ),
				'months' => __( 'Months' ),
			],
		] ),
		html()->description( __( 'The minimum amount of time from the current date and time someone can book an appointment.', 'groundhogg-calendar' ) ),
	] );

	html()->end_row();


	html()->start_row();

	html()->th( __( 'Maximum booking period' ) );
	html()->td( [
		// number
		html()->number( [
			'name'  => 'max_booking_period_count',
			'value' => $calendar->get_meta( 'max_booking_period_count' ),
			'class' => 'input'
		] ),
		// month|day|year
		html()->dropdown( [
			'name'     => 'max_booking_period_type',
			'selected' => $calendar->get_meta( 'max_booking_period_type' ),
			'options'  => [
				'days'   => __( 'Days' ),
				'weeks'  => __( 'Weeks' ),
				'months' => __( 'Months' ),
			],
		] ),
		html()->description( __( 'The maximum amount of time from the current day that someone can book an appointment.', 'groundhogg-calendar' ) ),
	] );

	html()->end_row();

	?>
    <tr>
        <th scope="row"><label><?php _e( 'Make me look busy', 'groundhogg-calendar' ) ?></label></th>
        <td>
			<?php echo html()->number( [
				'name'        => 'busy_slot',
				'placeholder' => '3',
				'value'       => $calendar->get_meta( 'busy_slot', true ) ? $calendar->get_meta( 'busy_slot', true ) : 0,
			] ); ?>
            <p class="description"><?php _e( 'Shows only a set number of available slots to make you look busier! Leave empty to show all available slots.', 'groundhogg-calendar' ) ?></p>
        </td>
    </tr>
	<?php

	html()->end_form_table();


	?>
    <h2><?php _e( 'Business hours' ) ?></h2>
    <p class="description"><?php _e( 'Time slots that can be selected for appointments.' ) ?></p>
	<?php

	html()->list_table( [ 'style' => [ 'max-width' => '700px' ], 'class' => '' ], $cols, $rows );

	?>
    <script>
      ( function ($) {
        $(function () {
          $(document).on('click', '.trash-rule', function (e) {
            e.preventDefault()
            $(e.target).closest('tr').remove()
          })

          $(document).on('click', '.add-rule', function (e) {
            e.preventDefault()
            var $row = $(e.target).closest('tr')
            $row.clone().insertAfter($row)
          })
        })
      } )(jQuery)
    </script>
	<?php


	submit_button( __( 'Update Availability', 'groundhogg-calendar' ) );
	?>
</form>
