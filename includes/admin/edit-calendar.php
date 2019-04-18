<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$calendar_id = intval( $_GET[ 'calendar' ] );
$calendar = WPGH_APPOINTMENTS()->calendar->get_calendar($calendar_id);
if( $calendar == null) {
    wp_die( __( 'Calendar not found.', 'groundhogg' ) );
}

// get meta value
$dow = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'dow',true);
if ( ! $dow ) {
    $dow = array('0','1','2','3','4','5','6');
}


$start_time = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'start_time', true);
if( ! $start_time ) {
    $start_time = '09:00';
}
$end_time = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'end_time', true);
if( ! $end_time) {
    $end_time = '17:00';
}
$slot_hour = intval(  WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'slot_hour', true) );

$slot_minute = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'slot_minute', true);
if( ! $slot_minute ) {
    $slot_minute = 0;
}
$message = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'message', true);
if( ! $message ) {
    $message    =  __( 'Appointment booked successfully', 'groundhogg' );
}
$title = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'slot_title', true);
if( ! $title ) {
    $title    = __( 'Time Slot', 'groundhogg' );
}

$main_color = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'main_color', true);
if( ! $main_color ) {
    $main_color = '#f7f7f7';
}

$slots_color = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'slots_color', true);
if( ! $slots_color ) {
    $slots_color = '#29a2d9';
}

$font_color = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'font_color', true);
if ( ! $font_color ){
    $font_color = '#292929';
}

$buffer_time  = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'buffer_time', true);
if ( ! $buffer_time ){
    $buffer_time = 0;
}

$busy_slot = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'busy_slot', true);
if ( ! $busy_slot ){
    $busy_slot = 0;
}

 $slot1_start = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id, 'slot1_start_time', true);
 $slot1_end   = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id, 'slot1_end_time',  true);
 $slot2_start = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id, 'slot2_start_time',true );
 $slot2_end   = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id, 'slot2_end_time'  ,true);
 $slot3_start = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id, 'slot3_start_time',true );
 $slot3_end   = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id, 'slot3_end_time'  ,true);

$custom_text_status = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'custom_text_status',true) ;
$custom_text    =  WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id, 'custom_text',   true);
if(!$custom_text_status) {
    $custom_text_status= 0 ;
}

$redirect_link_status = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'redirect_link_status',true) ;
$redirect_link    =  WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id, 'redirect_link',   true);
if(!$redirect_link_status) {
    $redirect_link_status = 0 ;
}

$slot2_status =  WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'slot2_status', true);
if ( ! $slot2_status ){
    $slot2_status = 0;
}

$slot3_status =  WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'slot3_status', true);
if ( ! $slot3_status ){
    $slot3_status = 0;
}

$access_token = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'access_token', true);
$google_calendar_id   = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id ,'google_calendar_id' ,true);
$google_calendar_list = (array) WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'google_calendar_list',true);

?>
<form name="" id="" method="post" action="">
    <?php wp_nonce_field(); ?>
    <table class="form-table">
        <tbody>
            <tr class="form-field term-contact-wrap">
                <th scope="row"><label><?php _e( 'Select Owner' ) ?></label></th>
                <td>
                    <?php echo WPGH()->html->dropdown_owners(  array( 'selected' => ( $calendar->user_id )? $calendar->user_id : 0 ) ); ?>
                    <p class="description"><?php _e( 'Select owner for whom you are creating the calendar.', 'groundhogg' ) ?></p>
                </td>
            </tr>
            <tr >
                <th scope="row"><label><?php _e( 'Name' ) ?></label></th>
                <td>
                    <?php echo WPGH()->html->input( array( 'name' => 'name' ,'placeholder' => 'Calendar Name' ,'value' => $calendar->name) ); ?>
                    <p class="description"><?php _e( 'A name of a calendar.', 'groundhogg' ) ?>.</p>
                </td>
            </tr>
            <tr >
                <th scope="row"><label><?php _e( 'Description' ,'groundhogg'); ?></label></th>
                <td>
                    <?php echo WPGH()->html->textarea( array( 'name' => 'description' ,'placeholder' => 'Calendar Description' ,'value' => $calendar->description) ); ?>
                    <p class="description"><?php _e( 'Calendar descriptions are only visible to admins and will never be seen by contacts.', 'groundhogg' ) ?>.</p>
                </td>
            </tr>
        </tbody>
    </table>
    <h2><?php _e( 'Business Hours' ,'groundhogg' ); ?></h2>
    <table class="form-table">
        <tbody>
            <tr class="form-field term-contact-wrap">
                <th scope="row"><label><?php _e( 'Select working days' ); ?></label></th>
                <td>
                    <ul>
                        <li><input type="checkbox" name="checkbox[]" value="0" <?php if( in_array('0' ,$dow ,true) ) { echo 'checked' ;} ?>> <?php _e( 'Sunday');?></li>
                        <li><input type="checkbox" name="checkbox[]" value="1" <?php if( in_array('1' ,$dow ,true) ) { echo 'checked' ;} ?>> <?php _e( 'Monday');?></li>
                        <li><input type="checkbox" name="checkbox[]" value="2" <?php if( in_array('2' ,$dow ,true) ) { echo 'checked' ;} ?>> <?php _e( 'Tuesday');?></li>
                        <li><input type="checkbox" name="checkbox[]" value="3" <?php if( in_array('3' ,$dow ,true) ) { echo 'checked' ;} ?>> <?php _e( 'Wednesday');?></li>
                        <li><input type="checkbox" name="checkbox[]" value="4" <?php if( in_array('4' ,$dow ,true) ) { echo 'checked' ;} ?>> <?php _e( 'Thursday');?></li>
                        <li><input type="checkbox" name="checkbox[]" value="5" <?php if( in_array('5' ,$dow ,true) ) { echo 'checked' ;} ?>> <?php _e( 'Friday');?></li>
                        <li><input type="checkbox" name="checkbox[]" value="6" <?php if( in_array('6' ,$dow ,true) ) { echo 'checked' ;} ?>> <?php _e( 'Saturday '); ?></li>
                    </ul>
                </td>
            </tr>
<!--            <tr class="form-field term-contact-wrap">-->
<!--                <th scope="row"><label>--><?php //_e( 'slot 1' ); ?><!--</label></th>-->
<!--                <td>-->
<!--                    <input type="time" id="starttime" name="starttime" value="--><?php //echo $start_time; ?><!--" autocomplete="off" >-->
<!--                    --><?php //_e('Start Time','groundhogg'); ?>
<!---->
<!--                    <input type="time" id="endtime" name="endtime" value="--><?php //echo $end_time; ?><!--" autocomplete="off" >-->
<!--                    --><?php //_e('End Time','groundhogg'); ?>
<!---->
<!--                    <p class="description">--><?php //_e( 'Start time of working hours.', 'groundhogg' ); ?><!--</p>-->
<!--                </td>-->
<!--            </tr>-->

            <tr class="form-field term-contact-wrap">
                <th scope="row"><label><?php _e( 'Working Hours (slot 1)' ); ?></label></th>
                <td>
                    <input type="time" id="slot1_start_time" name="slot1_start_time" value="<?php echo $slot1_start; ?>" autocomplete="off" >
                    <?php _e('Start Time','groundhogg'); ?>

                    <input type="time" id="slot1_end_time" name="slot1_end_time" value="<?php echo $slot1_end; ?>" autocomplete="off" >
                    <?php _e('End Time','groundhogg'); ?>
                    <p class="description"><?php _e( 'Working hours.', 'groundhogg' ); ?></p>
                </td>
            </tr>


            <tr class="form-field term-contact-wrap">
                <th scope="row"><label><?php _e( 'Working Hours (slot 2)' ); ?></label></th>
                <td>
                    <input type="time" id="slot3_start_time" name="slot2_start_time" value="<?php echo $slot2_start; ?>" autocomplete="off" >
                    <?php _e('Start Time','groundhogg'); ?>

                    <input type="time" id="slot3_end_time" name="slot2_end_time" value="<?php echo $slot2_end; ?>" autocomplete="off" >
                    <?php _e('End Time','groundhogg'); ?>
                    <p><?php echo WPGH()->html->checkbox(['label'=> "Enable","name"=> "slot2_status" ,'checked'=>$slot2_status ]);?></p>
                    <p class="description"><?php _e( 'Start time of working hours.', 'groundhogg' ); ?></p>
                </td>
            </tr>
            <tr class="form-field term-contact-wrap">
                <th scope="row"><label><?php _e( 'Working Hours (slot 3)' ); ?></label></th>
                <td>
                    <input type="time" id="slot3_start_time" name="slot3_start_time" value="<?php echo $slot3_start; ?>" autocomplete="off" >
                    <?php _e('Start Time','groundhogg'); ?>

                    <input type="time" id="slot3_end_time" name="slot3_end_time" value="<?php echo $slot3_end; ?>" autocomplete="off" >
                    <?php _e('End Time','groundhogg'); ?>
                    <p><?php echo WPGH()->html->checkbox(['label'=> "Enable","name"=> "slot3_status" ,'checked'=>$slot3_status]);?></p>
                    <p class="description"><?php _e( 'Start time of working hours.', 'groundhogg' ); ?></p>
                </td>
            </tr>
        </tbody>
    </table>
    <h2><?php _e( 'Appointment Setting' ,'groundhogg'); ?></h2>
    <table class="form-table">
        <tbody>
        <tr>
            <th scope="row"><label><?php _e( 'Length of appointment' ,'groundhogg'); ?></label></th>
            <td>
                <?php
//                $hours;
                for ($i=0 ; $i<24 ; $i++) {
                    $hours[$i] = $i;
                }
//                $mins = array();
                for ($i=0 ; $i<60 ; $i++) {
                    $mins[$i] = $i;
                }
                echo WPGH()->html->dropdown( array( 'name' =>'slot_hour' , 'options' => $hours , 'selected' => $slot_hour) ) ;
                _e('Hour(s)','groundhogg');
                echo WPGH()->html->dropdown( array( 'name' =>'slot_minute' , 'options' => $mins , 'selected' => $slot_minute) ) ;
                _e('Minutes','groundhogg');
                ?>
                <p class="description"><?php _e( 'Select default length of appointment', 'groundhogg' ); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label><?php _e( 'Buffer Time (after)' ,'groundhogg') ?></label></th>
            <td>
                <?php
                for ($i=0 ; $i<=60 ; $i++) {
                    $mins[$i] = $i;
                }
                echo WPGH()->html->dropdown( array( 'name' =>'buffer_time' , 'options' => $mins , 'selected' => $buffer_time) ) ;
                _e('Minutes','groundhogg'); ?>
                <p class="description"><?php _e( 'Buffer time after completion of appointment.', 'groundhogg' ) ?></p>
            </td>
        </tr>
        <tr>
        <th scope="row"><label><?php _e( 'Make me look Busy.' ,'groundhogg') ?></label></th>
        <td>
            <?php echo WPGH()->html->input( array( 'name' => 'busy_slot' ,'placeholder' => 'Custom title' , 'value' => $busy_slot ) );?>
            <p class="description"><?php _e( 'Enter how many time slots client can see! (Enter 0 to display all time slots)', 'groundhogg' ) ?></p>
        </td>
        </tr>
        <tr>
            <th scope="row"><label><?php _e( 'Time Slot Title' ,'groundhogg') ?></label></th>
            <td>
                <?php echo WPGH()->html->input( array( 'name' => 'slot_title' ,'placeholder' => 'Custom title' , 'value' => $title ) );?>
                <p class="description"><?php _e( 'This title will be displayed above time slots.', 'groundhogg' ) ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label><?php _e( 'Custom Text Button' ,'groundhogg') ?></label></th>
            <td>
                <?php echo WPGH()->html->input( array( 'name' => 'custom_text' ,'placeholder' => 'Custom text' , 'value' => $custom_text ) );?>
                <p><?php echo WPGH()->html->checkbox(['label'=> "Enable","name"=> "custom_text_status" ,'checked'=>$custom_text_status ]);?></p>
                <p class="description"><?php _e( 'Enabling this setting displays custom text on booking slots followed by number. Default is time slot.', 'groundhogg' ) ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label><?php _e( 'Custom Message' ) ?></label></th>
            <td>
                <?php echo WPGH()->html->textarea( array( 'name' => 'message' ,'placeholder' => 'Custom Message' , 'value' => $message ) );?>
                <p class="description"><?php _e( 'This message will be displayed when user or admin booked appointment successfully.', 'groundhogg' ) ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label><?php _e( 'Thank You Page' ,'groundhogg') ?></label></th>
            <td>
                <?php echo WPGH()->html->input( array( 'name' => 'redirect_link' ,'placeholder' => 'http://www.example.com' , 'value'=>$redirect_link ) );?>
                <p><?php echo WPGH()->html->checkbox(['label'=> "Enable","name"=> "redirect_link_status" ,'checked'=>$redirect_link_status ]);?></p>
                <p class="description"><?php _e( 'Enabling this setting redirect user to specified thank you page.', 'groundhogg' ) ?></p>
            </td>
        </tr>

        </tbody>
    </table>
    <h2><?php _e( 'Calendar Styling' ,'groundhogg'); ?></h2>
    <table class="form-table">
        <tbody>
        <tr>
            <th scope="row"><label><?php _e( 'Main Calendar Color' ) ?></label></th>
            <td>
                <?php echo WPGH()->html->color_picker( array( 'name' => 'main_color', 'value' => $main_color ) );?>
                <p class="description"><?php _e( 'The main color of the calendar and time slots.', 'groundhogg' ) ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label><?php _e( 'Font Color' ) ?></label></th>
            <td>
                <?php echo WPGH()->html->color_picker( array( 'name' => 'font_color', 'value' => $font_color ) );?>
                <p class="description"><?php _e( 'The color of the fonts of the calender and time slots.', 'groundhogg' ) ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label><?php _e( 'Time Slots selected' ) ?></label></th>
            <td>
                <?php echo WPGH()->html->color_picker( array( 'name' => 'slots_color', 'value' => $slots_color ) );?>
                <p class="description"><?php _e( 'The color of the selected time slot.', 'groundhogg' ) ?></p>
            </td>
        </tr>
        </tbody>
    </table>
    <h2><?php _e( 'Google Calendar' ,'groundhogg'); ?></h2>
    <table class="form-table">
        <tbody>
        <tr>
            <th scope="row"><label><?php _e( 'Google Calendar sync' ) ?></label></th>
            <td id="appointment-status">
                <p><?php if ( $access_token  && $google_calendar_id ){  _e('Your google calendar sync is on.(Please delete calender from your google account to stop sync)','groundhogg'); } ?></p>
                <p><a class="button" id ="generate_access_code"  target="_blank" href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=gh_calendar&action=access_code&calendar='.$calendar_id ) ); ?>"><?php _e( 'Generate access code' ); ?></a> </p>
                <p>
                    <?php echo WPGH()->html->input( array( 'name' => 'auth_code' ,'id' =>'auth_code' , 'placeholder' => 'Please Enter validation code') );?>
                    <?php echo WPGH()->html->button( array( 'class' => 'button btn-approve', 'name' => 'verify_code' , 'id' => 'verify_code' ,'text' =>'Verify Code & Sync calendar') );?>
                    <div id="spinner">
                        <span class="spinner" style="float: none; visibility: visible"></span>
                    </div>
                </p>
            </td>
        </tr>
        <?php if ( $access_token  && $google_calendar_id ) :  ?>
        <tr>
            <th scope="row"><label><?php _e( 'Sync With' ) ?></label></th>
            <td id="appointment-status">
                <p>
                    <?php

                    //get list of all google calendars
                    $client = WPGH_APPOINTMENTS()->google_calendar->get_google_client_form_access_token($calendar_id);
                    $service = new Google_Service_Calendar($client);
                    $calendarList = $service->calendarList->listCalendarList();
                    while(true) {
                        foreach ($calendarList->getItems() as $calendarListEntry) {
                            if (! ($google_calendar_id == $calendarListEntry->getId()) ) {

                                $checked = false;
                                if( in_array( $calendarListEntry->getId() ,$google_calendar_list ,true) ) {
                                    $checked = true;
                                }
                                echo WPGH()->html->checkbox(array(
                                    'name'      => 'google_calendar_list[]',
                                    'value'     => $calendarListEntry->getId(),
                                    'label'     => $calendarListEntry->getSummary(),
                                    'checked'   => $checked,
                                ));
                                echo '<br/>';
                            }
                        }
                        $pageToken = $calendarList->getNextPageToken();
                        if ($pageToken) {
                            $optParams = array('pageToken' => $pageToken);
                            $calendarList = $service->calendarList->listCalendarList($optParams);
                        } else {
                            break;
                        }
                    }
                    ?>
                </p>
            </td>
        </tr>
        <?php endif; ?>
        </tbody>
    </table>
    <input type="hidden" value="<?php echo $calendar_id; ?>" name="calendar" id="calendar" />
    <div class="add-calendar-actions">
        <?php submit_button( __( 'Update Calendar' ), 'primary', 'update', false ); ?>
    </div>
</form>