<?php
namespace GroundhoggBookingCalendar\Admin;

use function Groundhogg\get_date_time_format;
use function Groundhogg\get_request_var;
use Groundhogg\Plugin;
use function  Groundhogg\html;
use function  Groundhogg\get_contactdata;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Calendar;
use function Groundhogg\utils;

/*
 *  View Detail description about appointment and change status of appointment
 */
if ( !defined( 'ABSPATH' ) ) exit;

$appointment_id = absint( get_request_var( 'appointment' ) );

$appointment = new Appointment( $appointment_id );
if ( !$appointment->exists() ) {
    wp_die( __( "Appointment not found!", 'groundhogg-calendar' ) );
}
?>
<form name="" id="" method="post" action="">
    <?php wp_nonce_field(); ?>
    <table class="form-table">
        <tbody>
        <tr>
            <th scope="row"><label for="user_id"><?php _e( 'Appointment Name', 'groundhogg-calendar' ) ?></label></th>
            <td><?php echo html()->input( [
                    'name' => 'appointmentname',
                    'value' => $appointment->get_name()
                ] ); ?></td>
        </tr>
        <tr>
            <th scope="row"><label for="user_id"><?php _e( 'Owner Name', 'groundhogg-calendar' ) ?></label></th>
            <td>
                <?php
                $user_data = get_userdata( $appointment->get_owner_id() );
                echo html()->input( [
                    'name' => 'owner_name',
                    'value' => $user_data->user_login . ' (' . $user_data->user_email . ')',
                    'attributes' => 'readonly'
                ] );
                ?>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="user_id"><?php _e( 'Contact', 'groundhogg-calendar' ) ?></label></th>
            <td>
                <div style="max-width: 350px">
                    <?php echo html()->dropdown_contacts( [ 'selected' => [ $appointment->get_contact_id() ] ] ); ?>
                    <?php echo html()->wrap( html()->e( 'a', [ 'href' => add_query_arg( [
                        'page' => 'gh_contacts',
                        'action' => 'edit',
                        'contact' => $appointment->get_contact_id()
                    ], admin_url( 'admin.php' ) ) ], __( 'Edit Contact', 'groundhogg-calendar' ) ), 'span', [ 'class' => 'row-actions' ] ); ?>
                </div>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="appointment-status"><?php _e( 'Status', 'groundhogg-calendar' ) ?></label>
            </th>
            <td id="appointment-status">
                <?php echo html()->dropdown([
                	'name' => 'change_status',
	                'selected' => $appointment->get_status(),
	                'options' => [
	                	'scheduled' => __( 'Scheduled','groundhogg-calendar' ),
	                	'cancelled' => __( 'Cancelled','groundhogg-calendar' ),
	                ]
                ]) ?>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="start_date"><?php _e( 'Scheduled for', 'groundhogg-calendar' ) ?></label></th>
            <td>
                <?php
                printf( __( '<b>%s</b> to <b>%s</b>', 'groundhogg-calendar' ),
	                date_i18n( get_date_time_format(), $appointment->get_start_time( true ) ),
	                date_i18n( get_date_time_format(), $appointment->get_end_time(true ) )
                );
                ?>
	            <p>
		            <?php echo html()->e( 'a', [
		            	'href' => $appointment->reschedule_link()
		            ], __( 'Reschedule', 'groundhogg-calendar' ) ) ?>
	            </p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="notes"><?php _e( 'Notes', 'groundhogg-calendar' ) ?></label></th>
            <td>
                <?php
                $note = $appointment->get_meta( 'notes', true );
                echo html()->textarea( [
                    'id' => 'notes',
                    'name' => 'notes',
                    'value' => $note
                ] );
                ?>
                <p class="description"><?php _e( 'Additional information about appointment.', 'groundhogg-calendar' ) ?></p>
            </td>
        </tr>
        </tbody>
    </table>
    <?php submit_button( __( 'Update Appointment' ), 'primary', 'update_appointment', false ); ?>
    <span id="delete-link"><a class="delete"
                              href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=gh_calendar&action=delete_appointment&appointment=' . $appointment->get_id() ) ); ?>"><?php _e( 'Delete' ); ?></a></span>
</form>