<?php

$sms = [];

########### REMINDER ###########

$sms['scheduled'] = [
	'title'   => __( 'Appointment Scheduled', 'groundhogg-calendar' ),
	'content' => __( 'Your appointment with {calender_owner_first_name} {calender_owner_last_name} is confirmed for {appointment_start_time}.', 'groundhogg-calendar' ),
];

########### RESCHEDULED ###########

$sms['rescheduled'] = [
	'title'   => __( 'Appointment Rescheduled', 'groundhogg-calendar' ),
	'content' => __( 'Your appointment with {calender_owner_first_name} {calender_owner_last_name} has been rescheduled for {appointment_start_time}.', 'groundhogg-calendar' ),
];

########### CANCELLED ###########

$sms['cancelled'] = [
	'title'   => __( 'Appointment Cancelled', 'groundhogg-calendar' ),
	'content' => __( 'Your appointment with {calender_owner_first_name} {calender_owner_last_name} is cancelled.', 'groundhogg-calendar' ),
];

########### REMINDER ###########

$sms['reminder'] = [
	'title'   => __( 'Appointment Reminder', 'groundhogg-calendar' ),
	'content' => __( 'Your appointment with {calender_owner_first_name} {calender_owner_last_name} is in {time_to_appointment}.

Talk soon!', 'groundhogg-calendar' ),
];