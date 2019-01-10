<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<form name="" id="" method="post" action="">
    <?php wp_nonce_field(); ?>
    <table class="form-table">
        <tbody>
            <tr class="form-field term-contact-wrap">
                <th scope="row"><label for="user_id"><?php _e( 'Select Owner' ,'groundhogg') ?></label></th>
                <td><?php $args = array();
                    $args[ 'id' ] = 'user_id';
                    $args[ 'required' ] = true;
                    echo WPGH()->html->dropdown_owners( $args ); ?>
                    <p class="description"><?php _e( 'Select owner for whom you are creating the calendar.', 'groundhogg' ) ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="name"><?php _e( 'Name' ) ?></label></th>
                <td>
                    <?php echo WPGH()->html->input( array( 'name' => 'name' ,'placeholder' => 'Calendar Name' ) ); ?>
                    <p class="description"><?php _e( 'A name of a calendar.', 'groundhogg' ) ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="description"><?php _e( 'Description' ,'groundhogg'); ?></label></th>
                <td>
                    <?php echo WPGH()->html->textarea( array( 'name' => 'description' ,'placeholder' => 'Calendar Description' ) );?>
                    <p class="description"><?php _e( 'Calendar descriptions are only visible to admins and will never be seen by contacts.', 'groundhogg' ) ?></p>
                </td>
            </tr>
        </tbody>
    </table>
    <h2><?php _e( 'Business Hours' ,'groundhogg'); ?></h2>
    <table class="form-table">
        <tbody>
            <tr class="form-field term-contact-wrap">
                <th scope="row">
                    <label for="date"><?php _e( 'Select working days' ,'groundhogg'); ?></label>
                </th>
                <td>
                    <ul>
                        <li><input type="checkbox" name="checkbox[]" value="0" checked> Sunday</li>
                        <li><input type="checkbox" name="checkbox[]" value="1" checked> Monday</li>
                        <li><input type="checkbox" name="checkbox[]" value="2" checked> Tuesday</li>
                        <li><input type="checkbox" name="checkbox[]" value="3" checked> Wednesday</li>
                        <li><input type="checkbox" name="checkbox[]" value="4" checked> Thursday</li>
                        <li><input type="checkbox" name="checkbox[]" value="5" checked> Friday</li>
                        <li><input type="checkbox" name="checkbox[]" value="6" checked> Saturday</li>
                    </ul>
                </td>
            </tr>
            <tr class="form-field term-contact-wrap">
                <th scope="row">
                    <label for="date"><?php _e( 'Start Time:' ,'groundhogg'); ?></label>
                </th>
                <td>
                    <input type="time" id="starttime" name="starttime" value="09:00" autocomplete="off" >
                    <p class="description"><?php _e( 'Start time of working hours.', 'groundhogg' ); ?></p>
                </td>
            </tr>
            <tr class="form-field term-contact-wrap">
                <th scope="row">
                    <label for="date"><?php _e( 'End Time:' ,'groundhogg'); ?></label>
                </th>
                <td>
                    <input type="time" id="endtime" name="endtime" value="17:00" autocomplete="off" >
                    <p class="description"><?php _e( 'End time of working hours.', 'groundhogg' ); ?></p>
                </td>
            </tr>
        </tbody>
    </table>
    <h2><?php _e( 'Appointment' ,'groundhogg'); ?></h2>
    <table class="form-table">
        <tbody>
            <tr>
                <th scope="row"><label for="date"><?php _e( 'Length of appointment' ,'groundhogg'); ?></label></th>

                <td>
                    <?php
                    $hours;
                    for ($i=0 ; $i<24 ; $i++)
                    {
                        $hours[$i] = $i;
                    }
                    $mins;

                    for ($i=0 ; $i<60 ; $i++)
                    {
                        $mins[$i] = $i;
                    }
                    echo WPGH()->html->dropdown( array( 'name' =>'slot_hour' , 'options' => $hours , 'selected' => '1') ) ;
                    _e('Hour(s)','groundhogg');
                    echo WPGH()->html->dropdown( array( 'name' =>'slot_minute' , 'options' => $mins , 'selected' => '0') ) ;
                    _e('Minutes','groundhogg');
                    ?>
                    <p class="description"><?php _e( 'Select default length of appointment', 'groundhogg' ); ?></p>
                </td>

            </tr>
            <tr>
                <th scope="row"><label for="name"><?php _e( 'Time Slot Title' ,'groundhogg') ?></label></th>
                <td>
                    <?php echo WPGH()->html->input( array( 'name' => 'slot_title' ,'placeholder' => 'Custom title' , 'value' => __('Time Slots' ,'groundhogg') ) );?>
                    <p class="description"><?php _e( 'This title will be displayed above time slots.', 'groundhogg' ) ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="name"><?php _e( 'Custom Message' ,'groundhogg' ) ?></label></th>
                <td>
                    <?php echo WPGH()->html->textarea( array( 'name' => 'message' ,'placeholder' => 'Custom Message' , 'value' => __('Appointment booked successfully.' ,'groundhogg') ) );?>
                    <p class="description"><?php _e( 'This message will be displayed when user booked appointment successfully.', 'groundhogg' ) ?></p>
                </td>
            </tr>
        </tbody>
    </table>
    <div class="add-calendar-actions">
        <?php submit_button( __( 'Add Calendar' ,'groundhogg') , 'primary', 'add', false ); ?>
    </div>
</form>



