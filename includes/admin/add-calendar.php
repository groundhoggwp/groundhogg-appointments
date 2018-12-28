<?php

if ( ! defined( 'ABSPATH' ) ) exit;

?>
<form name="" id="" method="post" action="">
    <?php wp_nonce_field(); ?>
    <table class="form-table">
        <tbody><tr class="form-field term-contact-wrap">
            <th scope="row"><label for="user_id"><?php _e( 'Select Owner' ) ?></label></th>
            <td><?php $args = array();
                $args[ 'id' ] = 'user_id';
                $args[ 'required' ] = true;
                echo WPGH()->html->dropdown_owners( $args ); ?>
                <p class="description"><?php _e( 'Select owner for whom you are creating the calendar.', 'groundhogg' ) ?></p>
            </td>
        </tr>
        <tr class="form-field term-calendar-name-wrap">
            <th scope="row"><label for="name"><?php _e( 'Name' ) ?></label></th>
            <td>
                <input name="name" id="name" type="text"  size="40" aria-required="true" placeholder="Calendar Name">
                <p class="description"><?php _e( 'A name of a calendar.', 'groundhogg' ) ?>.</p>
            </td>
        </tr>
        <tr class="form-field term-calendar-description-wrap">
            <th scope="row"><label for="description"><?php _e( 'Description' ,'groundhogg'); ?></label></th>
            <td>
                <textarea name="description" id="description" rows="5" cols="50" class="large-text" placeholder="Calendar Description"></textarea>
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
                <label for="date"><?php _e( 'Start Time:' ); ?></label>
            </th>
            <td>
                <input type="time" id="starttime" name="starttime" value="09:00" autocomplete="off" >
                <p class="description"><?php _e( 'Start time of working hours.', 'groundhogg' ); ?></p>
            </td>
        </tr>
        <tr class="form-field term-contact-wrap">
            <th scope="row">
                <label for="date"><?php _e( 'End Time:' ); ?></label>
            </th>
            <td>
                <input type="time" id="endtime" name="endtime" value="17:00" autocomplete="off" >
                <p class="description"><?php _e( 'End time of working hours.', 'groundhogg' ); ?></p>
            </td>
        </tr>
        </tbody>
    </table>


    <div class="add-calendar-actions">
        <?php submit_button( __( 'Add Calendar' ), 'primary', 'add', false ); ?>
    </div>
</form>



