<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$calendar_id = intval( $_GET[ 'calendar' ] );

$calendar = WPGH_APPOINTMENTS()->calendar->get_calendar($calendar_id);
if( $calendar == null) {
    wp_die( __( 'Calendar does not found.', 'groundhogg' ) );
}

// get meta value
$dow        = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'dow',true);
if ( $dow == null ) {
    $dow = array('0','1','2','3','4','5','6');
}
$start_time = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'start_time', true);
if( $start_time == null) {
    $start_time = '09:00';
}
$end_time   = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'end_time', true);
if( $end_time == null) {
    $end_time = '17:00';
}


?>
<form name="" id="" method="post" action="">
    <?php wp_nonce_field(); ?>
    <table class="form-table">
        <tbody><tr class="form-field term-contact-wrap">
            <th scope="row"><label for="user_id"><?php _e( 'Select Owner' ) ?></label></th>
            <td><?php
                echo WPGH()->html->dropdown_owners(  array( 'selected' => ( $calendar->user_id )? $calendar->user_id : 0 ) ); ?>
                <p class="description"><?php _e( 'Select owner for whom you are creating the calendar.', 'groundhogg' ) ?></p>
            </td>
        </tr>
        <tr class="form-field term-calendar-name-wrap">
            <th scope="row"><label for="name"><?php _e( 'Name' ) ?></label></th>
            <td>
                <input name="name" id="name" type="text"  size="40" aria-required="true" placeholder="Calendar Name" value="<?php echo $calendar->name; ?>">
                <p class="description"><?php _e( 'A name of a calendar.', 'groundhogg' ) ?>.</p>
            </td>
        </tr>
        <tr class="form-field term-calendar-description-wrap">
            <th scope="row"><label for="description"><?php _e( 'Description' ,'groundhogg'); ?></label></th>
            <td>
                <textarea name="description" id="description" rows="5" cols="50" class="large-text" placeholder="Calendar Description"><?php echo $calendar->description; ?></textarea>
                <p class="description"><?php _e( 'Calendar descriptions are only visible to admins and will never be seen by contacts.', 'groundhogg' ) ?>.</p>
            </td>
        </tr>
        </tbody>
    </table>
    <h2><?php _e( 'Business Hours'); ?></h2>
    <table class="form-table">
        <tbody>
        <tr class="form-field term-contact-wrap">
            <th scope="row">
                <label for="date"><?php _e( 'Select working days' ); ?></label>
            </th>
            <td>
                <ul>
                    <li><input type="checkbox" name="checkbox[]" value="0" <?php if( in_array('0' ,$dow ,true) ) { echo 'checked' ;} ?>> Sunday</li>
                    <li><input type="checkbox" name="checkbox[]" value="1" <?php if( in_array('1' ,$dow ,true) ) { echo 'checked' ;} ?>> Monday</li>
                    <li><input type="checkbox" name="checkbox[]" value="2" <?php if( in_array('2' ,$dow ,true) ) { echo 'checked' ;} ?>> Tuesday</li>
                    <li><input type="checkbox" name="checkbox[]" value="3" <?php if( in_array('3' ,$dow ,true) ) { echo 'checked' ;} ?>> Wednesday</li>
                    <li><input type="checkbox" name="checkbox[]" value="4" <?php if( in_array('4' ,$dow ,true) ) { echo 'checked' ;} ?>> Thursday</li>
                    <li><input type="checkbox" name="checkbox[]" value="5" <?php if( in_array('5' ,$dow ,true) ) { echo 'checked' ;} ?>> Friday</li>
                    <li><input type="checkbox" name="checkbox[]" value="6" <?php if( in_array('6' ,$dow ,true) ) { echo 'checked' ;} ?>> Saturday</li>
                </ul>
            </td>
        </tr>

        <tr class="form-field term-contact-wrap">
            <th scope="row">
                <label for="date"><?php _e( 'Start Time:' ); ?></label>
            </th>
            <td>
                <input type="time" id="starttime" name="starttime" value="<?php echo $start_time; ?>" autocomplete="off" >
                <p class="description"><?php _e( 'Start time of working hours.', 'groundhogg' ); ?></p>
            </td>
        </tr>
        <tr class="form-field term-contact-wrap">
            <th scope="row">
                <label for="date"><?php _e( 'End Time:' ); ?></label>
            </th>
            <td>
                <input type="time" id="endtime" name="endtime" value="<?php echo $end_time; ?>" autocomplete="off" >
                <p class="description"><?php _e( 'End time of working hours.', 'groundhogg' ); ?></p>
            </td>
        </tr>
        </tbody>
    </table>
    <input type="hidden" value="<?php echo $calendar_id; ?>" name="calendar" />
    <div class="add-calendar-actions">
        <?php submit_button( __( 'Update Calendar' ), 'primary', 'update', false ); ?>

    </div>
</form>