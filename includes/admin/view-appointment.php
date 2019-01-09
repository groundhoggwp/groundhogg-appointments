<?php
/*
 *  View Detail description about appointment and change status of appointment
 */
if ( ! defined( 'ABSPATH' ) ) exit;


$appointment_id = intval( $_GET['appointment'] );
//fetch appointment
$appointment = WPGH_APPOINTMENTS()->appointments->get($appointment_id);
if ($appointment == null) {
    $this->notices->add( 'NO_APPOINTMENT', __( "Appointment not found!", 'groundhogg' ), 'error' );
    return;
}
$contact =  WPGH()->contacts->get($appointment->contact_id);
if ($contact == null){
    $this->notices->add( 'NO_CONTACT', __( "Contact Details not found!", 'groundhogg' ), 'error' );
    return;
}
$calendar = WPGH_APPOINTMENTS()->calendar->get($appointment->calendar_id);
if ($calendar == null) {
    $this->notices->add( 'NO_CALENDAR', __( "Calendar not found!", 'groundhogg' ), 'error' );
    return;
}
?>
<form name="" id="" method="post" action="">
    <?php wp_nonce_field(); ?>
    <input type="hidden" name="appointment" value="<?php echo $appointment->ID; ?>" />
    <input type="hidden" name="calendar" value="<?php echo $appointment->calendar_id; ?>" />
    <table class="form-table">
        <tbody>
            <tr>
                <th scope="row"><label for="user_id"><?php _e( 'Appointment Name' ,'groundhogg') ?></label></th>
                <td><?php echo WPGH()->html->input( array( 'name' => 'appointmentname', 'value' =>  $appointment->name ) ); ?></td>
            </tr>
            <tr >
                <th scope="row"><label for="user_id"><?php _e( 'Owner Name' ,'groundhogg') ?></label></th>
                <td>
                    <?php
                        $user_data     = get_userdata( $calendar->user_id );
                        echo WPGH()->html->input( array( 'name' => 'owner_name', 'value' =>  $user_data->user_login . ' (' . $user_data->user_email .')' , 'attributes' => 'readonly' ) );
                    ?>
                </td>
            </tr>
            <tr >
                <th scope="row"><label for="user_id"><?php _e( 'contact Detail' ,'groundhogg') ?></label></th>
                <td>
                    <div style="max-width: 350px">
                        <?php echo WPGH()->html->dropdown_contacts( array( 'selected' => [$appointment->contact_id]) ); ?>
                    </div>
                </td>
            </tr>
            <tr >
                <th scope="row"><label for="user_id"><?php _e( 'Appointment Status' ,'groundhogg') ?></label></th>
                <td id="appointment-status">
                    <?php echo WPGH()->html->input( array( 'name' => 'status', 'value' =>  $appointment->status , 'attributes' => 'readonly' ) ) ; ?>
                    <div class="row-actions">
                        <a class="button btn-approve"   href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=gh_calendar&action=approve&appointment='.$appointment->ID ) ); ?>"><?php _e( 'Approve' ); ?></a>
                        <a class="button btn-cancel"   href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=gh_calendar&action=cancel&appointment='.$appointment->ID ) ); ?>"><?php _e( 'Cancel' ); ?></a>
                    </div>
                </td>
            </tr>
            <tr >
                <th scope="row"><label for="user_id"><?php _e( 'Appointment Start Time' ,'groundhogg') ?></label></th>
                <td>
                    <input style="height:29px;width: 100px" class="input" placeholder="Y-m-d" type="text" id="start_date" name="start_date" value="<?php  _e(  date('Y-m-d', $appointment->start_time ), 'groundhogg' ) ; ?>" autocomplete="off" required >
                    <input type="time" id="time" name="start_time" value="<?php  _e(  date('G:i:s', $appointment->start_time ), 'groundhogg' ) ; ?>" autocomplete="off" required>
                    <script>
                        jQuery(function($){$('#start_date').datepicker({
                            changeMonth: true,
                            changeYear: true,
                            minDate:0,
                            dateFormat:'yy-mm-dd'
                        })});
                    </script>
                </td>
            </tr>
            <tr >
                <th scope="row"><label for="user_id"><?php _e( 'Appointment End Time' ,'groundhogg') ?></label></th>
                <td>
                    <input style="height:29px;width: 100px" class="input" placeholder="Y-m-d" type="text" id="end_date" name="end_date" value="<?php  _e(  date('Y-m-d', $appointment->end_time ), 'groundhogg' ) ; ?>" autocomplete="off" required >
                    <input type="time" id="time" name="end_time" value="<?php  _e( date('G:i:s', $appointment->end_time ), 'groundhogg' ) ; ?>"  required>
                    <script>
                        jQuery(function($){$('#end_date').datepicker({
                            changeMonth: true,
                            changeYear: true,
                            minDate:0,
                            dateFormat:'yy-mm-dd'
                        })});
                    </script>
                </td>
            </tr>
            <tr >
                <th scope="row"><label for="user_id"><?php _e( 'Notes' ,'groundhogg') ?></label></th>
                <td>
                    <?php
                        $note  =  WPGH_APPOINTMENTS()->appointmentmeta->get_meta($appointment_id,'note',true);
                        echo WPGH()->html->textarea( array( 'name' => 'description' , 'value' => $note ) );
                    ?>
                    <p class="description"><?php _e( 'Additional information about appointment.', 'groundhogg' ) ?></p>
                </td>
            </tr>
        </tbody>
    </table>
    <?php submit_button( __( 'Update Appointment' ), 'primary', 'update_appointment', false ); ?>
    <span id="delete-link"><a class="delete" href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=gh_calendar&action=delete_appointment&appointment='.$appointment->ID ) ); ?>"><?php _e( 'Delete' ); ?></a></span>
</form>