<?php

namespace GroundhoggBookingCalendar\Admin\Calendars;

use function Groundhogg\html;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form name="" id="" method="post" action="">
	<?php wp_nonce_field(); ?>
	<table class="form-table">
		<tbody>
		<tr class="form-field term-contact-wrap">
			<th scope="row"><label><?php _e( 'Select Owner', 'groundhogg-calendar' ) ?></label></th>
			<td><?php
				echo html()->dropdown_owners( [
					'id'       => 'user_id',
					'required' => true,
				] ); ?>
				<p class="description"><?php _e( 'Select owner for whom you are creating the calendar.', 'groundhogg-calendar' ) ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label><?php _e( 'Name' ) ?></label></th>
			<td>
				<?php echo html()->input( [
					'name'        => 'name',
					'placeholder' => 'Calendar Name'
				] ); ?>
				<p class="description"><?php _e( 'The name of a calendar.', 'groundhogg-calendar' ) ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label><?php _e( 'Description/Instructions', 'groundhogg-calendar' ); ?></label></th>
			<td>
				<div style="max-width: 700px">
					<?php wp_editor( '', 'description', [
						'editor_height' => 200,
						'editor_width'  => 300,
						'media_buttons' => false,
						'quicktags'     => false,
					] ); ?>
					<p class="description"><?php _e( 'Describe your booking calendar in few words and any instructions for the guest. Shows on the scheduling page as well as in the appointment description.', 'groundhogg-calendar' ) ?></p>
				</div>
			</td>
		</tr>
		</tbody>
	</table>
	<div class="add-calendar-actions">
		<?php submit_button( __( 'Add Calendar', 'groundhogg-calendar' ), 'primary', 'add', false ); ?>
	</div>
</form>
