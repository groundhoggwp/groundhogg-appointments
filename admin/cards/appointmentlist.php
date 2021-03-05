<?php

use Groundhogg\Admin\Contacts\Tab;
use Groundhogg\Contact;
use function Groundhogg\get_db;
use function Groundhogg\groundhogg_url;
use function Groundhogg\admin_page_url;
use function Groundhogg\html;
use Groundhogg\Plugin;
use function GroundhoggPipeline\format_price;
use function Groundhogg\get_date_time_format;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Calendar;
/**
 * Display formatting examples for the new css design system for the sidebar info cards
 *
 * @var $contact \Groundhogg\Contact
 */
    $where1[] = [
        'relationship' => 'AND',
        [ 'col' => 'start_date', 'val' => absint( time() ), 'compare' => '<' ],
    ];
    $where[] =[
        'relationship' => 'AND',
        ['contact_id' => $contact->get_id()],
    ];

    $args = array(
        'where'   => $where
     );
    $appointments = get_db('appointments')->query( $args );

    $where= [
        'relationship' => 'AND',
        [ 'col' => 'start_date', 'val' => absint( time() ), 'compare' => '<' ],
        ['col' => 'contact_id' , 'val' => $contact->get_id() , 'compare' => '='  ],
     ];
     $args = array(
        'where'   => $where
     );
     $past_appointments = get_db('appointments')->query( $args );

   // print_r($appointments);
    
    ?>
    <div class="appointment-section">
        
        <div class="ic-section">
            <div class="ic-section-header">
                <div class="ic-section-header-content">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php _e( 'Upcoming Appointment', 'groundhogg-calendar' ); echo '('.count($appointments).')'; ?>
                </div>
            </div>
            <div class="ic-section-content">
                <?php if ( ! empty( $appointments ) ): ?>
                    <?php foreach ( $appointments as $appointment ):
                        $appointment = new Appointment($appointment->ID);
                    ?>
                    <div class="ic-section"> 
                         <div class="ic-section-header">
                                    <div class="ic-section-header-content">
                                        <div class="basic-details">
                                            <a href="<?php echo esc_url( admin_page_url( 'gh_calendar', [ 'action' => 'edit_appointment', 'appointment' => $appointment->get_id() ] ) ); ?> ">#<?php echo $appointment->get_id(); ?> </a> 
                                        </div>
                                    </div>
                                    
                                </div>
                                <span class="subdata">March 19 2021<?php //echo ucfirst($appointment->column_stat_time()); ?> </span>
                        <div class="ic-section-content">
                            <span>111<?php echo $appointment->get_name();?></span>
                            <span>222<?php //echo $appointment->column_stat_time();//date( get_date_time_format(),  ); ?></span>
                            <span>333<?php // echo format_price($payment->get_total())?>  </span>
                            
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php
                else:  _e( 'There is no any upcoming appointment yet!!', 'groundhogg-calendar' ); endif; ?>
            </div>

            
        </div>

        <div class="ic-section">
            <div class="ic-section-header">
                <div class="ic-section-header-content">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php _e( 'Past Appointment', 'groundhogg-calendar' ); echo '('.count($appointments).')'; ?>
                </div>
            </div>
            <div class="ic-section-content">
                <?php if ( ! empty( $past_appointments ) ): ?>
                    <?php foreach ( $past_appointments as $appointment ):
                        $appointment = new Appointment($appointment->ID);
                    ?>
                    <div class="ic-section"> 
                         <div class="ic-section-header">
                                    <div class="ic-section-header-content">
                                        <div class="basic-details">
                                            <a href="<?php echo esc_url( admin_page_url( 'gh_calendar', [ 'action' => 'edit_appointment', 'appointment' => $appointment->get_id() ] ) ); ?> ">#<?php echo $appointment->get_id(); ?> </a> 
                                        </div>
                                    </div>
                                    
                                </div>
                                <span class="subdata">March 19 2021<?php //echo ucfirst($appointment->column_stat_time()); ?> </span>
                        <div class="ic-section-content">
                            <span>111<?php echo $appointment->get_name();?></span>
                            <span>222<?php //echo $appointment->column_stat_time();//date( get_date_time_format(),  ); ?></span>
                            <span>333<?php // echo format_price($payment->get_total())?>  </span>
                            
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php
                else:  _e( 'There is no any upcoming appointment yet!!', 'groundhogg-calendar' ); endif; ?>
            </div>

            
        </div>
        
</div>