<?php
namespace GroundhoggBookingCalendar\Admin;

use function Groundhogg\get_request_var;
use Groundhogg\Plugin;
use function  Groundhogg\html;
use function  Groundhogg\get_contactdata;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Calendar;

/*
 *  View Detail description about appointment and change status of appointment
 */
if ( !defined( 'ABSPATH' ) ) exit;

$appointment_id = absint( get_request_var( 'appointment' ) );

$appointment = new Appointment( $appointment_id );
if ( !$appointment->exists() ) {
    wp_die( __( "Appointment not found!", 'groundhogg' ) );
}
?>
<form name="" id="" method="post" action="">
    <?php wp_nonce_field(); ?>
    <input type="hidden" name="appointment" value="<?php echo $appointment->get_id(); ?>"/>
    <input type="hidden" name="calendar" value="<?php echo $appointment->get_calendar_id(); ?>"/>
    <table class="form-table">
        <tbody>
        <tr>
            <th scope="row"><label for="user_id"><?php _e( 'Appointment Name', 'groundhogg' ) ?></label></th>
            <td><?php echo html()->input( [
                    'name' => 'appointmentname',
                    'value' => $appointment->get_name()
                ] ); ?></td>
        </tr>
        <tr>
            <th scope="row"><label for="user_id"><?php _e( 'Owner Name', 'groundhogg' ) ?></label></th>
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
            <th scope="row"><label for="user_id"><?php _e( 'Contact', 'groundhogg' ) ?></label></th>
            <td>
                <div style="max-width: 350px">
                    <?php echo html()->dropdown_contacts( [ 'selected' => [ $appointment->get_contact_id() ] ] ); ?>
                    <?php echo html()->wrap( html()->e( 'a', [ 'href' => add_query_arg( [
                        'page' => 'gh_contacts',
                        'action' => 'edit',
                        'contact' => $appointment->get_contact_id()
                    ], admin_url( 'admin.php' ) ) ], __( 'Edit Contact', 'groundhogg' ) ), 'span', [ 'class' => 'row-actions' ] ); ?>
                </div>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="appointment-status"><?php _e( 'Appointment Status', 'groundhogg' ) ?></label>
            </th>
            <td id="appointment-status">
                <?php echo html()->input( [
                    'name' => 'status',
                    'class' => 'input',
                    'value' => ucwords( $appointment->get_status() ),
                    'readonly' => true
                ] ); ?>
                <div class="row-actions">
                    <a class="button btn-approve"
                       href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=gh_calendar&action=approve&appointment=' . $appointment->get_id() ) ); ?>"><?php _e( 'Approve' ); ?></a>
                    <a class="button btn-cancel"
                       href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=gh_calendar&action=cancel&appointment=' . $appointment->get_id() ) ); ?>"><?php _e( 'Cancel' ); ?></a>
                </div>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="start_date"><?php _e( 'Appointment Start Time', 'groundhogg' ) ?></label></th>
            <td>

                <?php
                echo html()->date_picker( [
                    'class' => 'input',
                    'placeholder' => 'Y-m-d',
                    'type' => 'text',
                    'id' => 'start_date',
                    'name' => 'start_date',
                    'value' => date( 'Y-m-d', Plugin::$instance->utils->date_time->convert_to_local_time( $appointment->get_start_time() ) ),
                ] );

                echo html()->input( [
                    'type' => 'time',
                    'class' => 'input',
                    'id' => 'time',
                    'name' => 'start_time',
                    'value' => date( 'H:i:s', Plugin::$instance->utils->date_time->convert_to_local_time( $appointment->get_start_time() ) )
                ] );

                ?>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="end_date"><?php _e( 'Appointment End Time', 'groundhogg' ) ?></label></th>
            <td>
                <?php
                echo html()->date_picker( [
                    'class' => 'input',
                    'placeholder' => 'Y-m-d',
                    'type' => 'text',
                    'id' => 'end_date',
                    'name' => 'end_date',
                    'value' => date( 'Y-m-d', Plugin::$instance->utils->date_time->convert_to_local_time( $appointment->get_end_time() ) ),
                ] );

                echo html()->input( [
                    'type' => 'time',
                    'class' => 'input',
                    'id' => 'time',
                    'name' => 'end_time',
                    'value' => date( 'H:i:s', Plugin::$instance->utils->date_time->convert_to_local_time( $appointment->get_end_time() ) )
                ] );

                ?>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="notes"><?php _e( 'Notes', 'groundhogg' ) ?></label></th>
            <td>
                <?php
                $note = $appointment->get_meta( 'notes', true );
                echo html()->textarea( [
                    'id' => 'notes',
                    'name' => 'notes',
                    'value' => $note
                ] );
                ?>
                <p class="description"><?php _e( 'Additional information about appointment.', 'groundhogg' ) ?></p>
            </td>
        </tr>
        </tbody>
    </table>
    <?php submit_button( __( 'Update Appointment' ), 'primary', 'update_appointment', false ); ?>
    <span id="delete-link"><a class="delete" href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=gh_calendar&action=delete_appointment&appointment=' . $appointment->get_id() ) ); ?>"><?php _e( 'Delete' ); ?></a></span>
</form>