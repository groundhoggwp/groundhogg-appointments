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

<table class="form-table">
    <tbody><tr class="form-field term-name-wrap">
        <th scope="row"><label for="user_id"><?php _e( 'Appointment name' ,'groundhogg') ?></label></th>
        <td>
            <?php _e( $appointment->name , 'groundhogg' ); ?>
        </td>
    </tr>
    <tr class="form-field term-calendar-name-wrap">
        <th scope="row"><label for="user_id"><?php _e( 'Owner Name' ,'groundhogg') ?></label></th>
        <td>
            <?php   $user_data     = get_userdata( $calendar->user_id );
                    _e( $user_data->user_login . ' (' . $user_data->user_email .')');  ?>
        </td>
    </tr>
    <tr class="form-field term-calendar-name-wrap">
        <th scope="row"><label for="user_id"><?php _e( 'Client Name' ,'groundhogg') ?></label></th>
        <td>
            <?php  _e( $contact->first_name . ' ' .$contact->last_name , 'groundhogg' );  ?>
        </td>
    </tr>
    <tr class="form-field term-calendar-name-wrap">
        <th scope="row"><label for="user_id"><?php _e( 'Client Email' ,'groundhogg') ?></label></th>
        <td>
            <?php   _e( $contact->email , 'groundhogg' );  ?>
        </td>
    </tr>
    <tr class="form-field term-calendar-name-wrap">
        <th scope="row"><label for="user_id"><?php _e( 'Appointment Status' ,'groundhogg') ?></label></th>
        <td>
            <?php _e( $appointment->status , 'groundhogg' )  ?>
        </td>
    </tr>
    <tr class="form-field term-calendar-description-wrap">
        <th scope="row"><label for="user_id"><?php _e( 'Owner Name' ,'groundhogg') ?></label></th>
        <td>
            <?php _e(  date('Y-m-d H:i:s', $appointment->start_time ), 'groundhogg' ) ;  ?>
        </td>
    </tr>
    <tr class="form-field term-calendar-description-wrap">
        <th scope="row"><label for="user_id"><?php _e( 'Owner Name' ,'groundhogg') ?></label></th>
        <td>
            <?php _e(date('Y-m-d H:i:s', $appointment->end_time ) , 'groundhogg' ); ?>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <a class="page-title-action aria-button-if-js button" style="color: #fff;background-color: #28a745;border-color: #28a745;" href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=gh_calendar&action=approve&appointment='.$appointment->ID ) ); ?>"><?php _e( 'Book' ); ?></a>
            <a class="page-title-action aria-button-if-js button" style="color: #212529;background-color: #ffc107;border-color: #ffc107;" href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=gh_calendar&action=cancel&appointment='.$appointment->ID ) ); ?>"><?php _e( 'Cancel' ); ?></a>
            <a class="page-title-action aria-button-if-js button" style="color: #fff;background-color: #dc3545;border-color: #dc3545;" href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=gh_calendar&action=delete_appointment&appointment='.$appointment->ID ) ); ?>"><?php _e( 'Delete' ); ?></a>
        </td>
    </tr>
    </tbody>
</table>