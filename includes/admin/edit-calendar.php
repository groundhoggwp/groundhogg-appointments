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
    <div class="add-calendar-actions">
        <?php submit_button( __( 'Add Calendar' ), 'primary', 'add', false ); ?>
    </div>
</form>
