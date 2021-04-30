<?php

namespace GroundhoggBookingCalendar;

use function Groundhogg\get_request_var;
use function Groundhogg\html;
use function Groundhogg\managed_page_footer;
use function Groundhogg\managed_page_head;
use GroundhoggBookingCalendar\Classes\Appointment;
use function Groundhogg\tracking;

if ( !defined( 'ABSPATH' ) ) exit;

include GROUNDHOGG_PATH . 'templates/managed-page.php';

add_action( 'wp_head', function () {

    if ( get_query_var( 'action' ) === 'reschedule' ):
        ?>
        <style>
            #main {
                max-width: 800px;
            }
        </style>
    <?php
    endif;
} );

$action = get_query_var( 'action' );
$appointment_id = get_query_var( 'appointment_id' );
$appointment = new Appointment( $appointment_id );

if ( tracking()->get_current_contact_id() !== $appointment->get_contact_id() && ! current_user_can( 'edit_contacts' ) ) {
    wp_die();
}

managed_page_head( $appointment->get_name(), $action );

switch ( $action ):
    default:
    case 'reschedule':

        echo do_shortcode( sprintf( '[gh_calendar id="%d" reschedule="%d"]', $appointment->get_calendar_id(), $appointment->get_id() ) );

        break;
    case 'cancel':

        // Authenticate...
        if ( wp_verify_nonce( get_request_var( '_wpnonce' ), 'cancel_appointment' ) ) {

            if ( $appointment->get_status() !== 'cancelled' ) {

                $appointment->cancel();

                $notes = $appointment->get_meta( 'notes' );
                $notes .= sprintf( "\n\n===== %s ===== \n\n", __( 'Cancelled', 'groundhogg-calendar' ) );
                $notes .= sanitize_textarea_field( get_request_var( 'reason' ) );

                $appointment->update_meta( 'notes', $notes );

                $text = __( 'Your appointment has been cancelled.', 'groundhogg-calendar' );
            } else {
                $text = __( 'Your appointment is already cancelled.', 'groundhogg-calendar' );
            }

            echo html()->wrap( html()->e( 'p', [], $text ), 'div', [ 'class' => 'box' ] );

        } else {

//            if ( $appointment->get_status() !== 'cancelled' && wp_verify_nonce( get_request_var( 'key' ) ) ) {
            if ( $appointment->get_status() !== 'cancelled' ) {

                echo html()->e( 'form', [ 'method' => 'post' ], [
                    wp_nonce_field( 'cancel_appointment', '_wpnonce', null, false ),
                    html()->e( 'p', [], __( 'Are you sure you want to cancel?', 'groundhogg-calendar' ) ),
                    html()->wrap( html()->textarea( [
                        'cols' => '60',
                        'rows' => '7',
                        'name' => 'reason',
                        'id' => 'reason',
                        'placeholder' => __( 'Reason for cancelling.', 'groundhogg-calendar' ),
                    ] ), 'p' ),
                    html()->wrap( html()->button( [
                        'type' => 'submit',
                        'text' => __( 'Cancel' ),
                        'name' => 'cancel',
                        'id' => 'cancel',
                        'class' => 'button',
                        'value' => 'cancel',
                    ] ), 'p' ),
                ] );

            } else {
                $text = __( 'Your appointment is already cancelled.', 'groundhogg-calendar' );
                echo html()->wrap( html()->e( 'p', [], $text ), 'div', [ 'class' => 'box' ] );
            }
        }

        break;
endswitch;

managed_page_footer();