<?php

namespace GroundhoggBookingCalendar\Admin\Calendars;
use function Groundhogg\html;

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<form name="" id="" method="post" action="">
    <?php wp_nonce_field(); ?>
    <table class="form-table">
        <tbody>
            <tr class="form-field term-contact-wrap">
                <th scope="row"><label ><?php _e( 'Select Owner' ,'groundhogg') ?></label></th>
                <td><?php
                    echo html()->dropdown_owners( [
                        'id' => 'user_id',
                        'required' => true,
                    ] ); ?>
                    <p class="description"><?php _e( 'Select owner for whom you are creating the calendar.', 'groundhogg' ) ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label><?php _e( 'Name' ) ?></label></th>
                <td>
                    <?php echo html()->input([
                        'name' => 'name' ,
                        'placeholder' => 'Calendar Name'
                    ] ) ; ?>
                    <p class="description"><?php _e( 'A name of a calendar.', 'groundhogg' ) ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label><?php _e( 'Description' ,'groundhogg'); ?></label></th>
                <td>
                <?php echo html()->textarea( [
                        'name' => 'description' ,
                        'placeholder' => 'Calendar Description'
                ] );?>
                <p class="description"><?php _e( 'Calendar descriptions are only visible to admins and will never be seen by contacts.', 'groundhogg' ) ?></p>
                </td>
            </tr>
        </tbody>
    </table>
    <div class="add-calendar-actions">
        <?php submit_button( __( 'Add Calendar' ,'groundhogg') , 'primary', 'add', false ); ?>
    </div>
</form>
