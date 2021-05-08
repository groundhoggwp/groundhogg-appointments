<?php

$emails = [];

########### REMINDER ###########

$emails['scheduled'] = [
	'title'   => __( 'Appointment Scheduled', 'groundhogg-calendar' ),
	'subject' => __( 'Your appointment is scheduled!', 'groundhogg-calendar' ),
	'content' => __( 'Hi {first},

Thank you for booking an appointment!

Your appointment will be from <strong>{appointment_start_time}</strong> to <strong>{appointment_end_time}</strong>.

{appointment_notes}

If you need to make changes to this appointment: {appointment_actions}

Looking forward to speaking with you!

Best,
{calendar_owner_signature}', 'groundhogg-calendar' ),
];

########### RESCHEDULED ###########

$emails['rescheduled'] = [
	'title'   => __( 'Appointment Rescheduled', 'groundhogg-calendar' ),
	'subject' => __( 'Your appointment has been rescheduled!', 'groundhogg-calendar' ),
	'content' => __( 'Hi {first},

Your appointment has been rescheduled to a new time.

Your appointment will now be from <strong>{appointment_start_time}</strong> to <strong>{appointment_end_time}</strong>.

{appointment_notes}

If you need to make changes to this appointment: {appointment_actions}

Looking forward to speaking with you!

Best,
{calendar_owner_signature}', 'groundhogg-calendar' ),
];

########### CANCELLED ###########

$emails['cancelled'] = [
	'title'   => __( 'Appointment Cancelled', 'groundhogg-calendar' ),
	'subject' => __( 'Your appointment has been cancelled!', 'groundhogg-calendar' ),
	'content' => __( 'Hi {first},

Your appointment has been cancelled.

If you want you can reschedule for another slot in the future.

Reschedule: {calender_link}

Best,
{calendar_owner_signature}', 'groundhogg-calendar' ),
];

########### REMINDER ###########

$emails['reminder'] = [
	'title'   => __( 'Appointment Reminder', 'groundhogg-calendar' ),
	'subject' => __( 'You have an upcoming appointment!', 'groundhogg-calendar' ),
	'content' => __( 'Hi {first},

Your appointment with {calendar_owner_first_name} {calendar_owner_last_name} is in {time_to_appointment}!

If you need to make changes to this appointment: {appointment_actions}

Best,
{calendar_owner_signature}', 'groundhogg-calendar' ),
];