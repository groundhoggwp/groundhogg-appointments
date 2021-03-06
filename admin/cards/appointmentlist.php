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
    // fetch upcoming appointments
    $where= [
        'relationship' => 'AND',
        ['col' => 'start_time', 'val' => absint( time() ), 'compare' => '>' ],
        ['col' => 'contact_id' , 'val' => $contact->get_id() , 'compare' => '='  ],
    ];
    $order = 'start_time';
    $args = array(
        'where' => $where,
        'order' => $order
     );
    $appointments = get_db('appointments')->query( $args );
    // fetch past appointments
    $where= [
        'relationship' => 'AND',
        [ 'col' => 'start_time', 'val' => absint( time() ), 'compare' => '<' ],
        ['col' => 'contact_id' , 'val' => $contact->get_id() , 'compare' => '='  ],
     ];
     $order = 'start_time';
     $args = array(
        'where' => $where,
        'order' => $order,
        'order' => 'desc', 
        'orderby'=>'end_time'  
     );
     $past_appointments = get_db('appointments')->query( $args );    
    ?>
    <div class="appointment-section">
        
        <div class="ic-section">
            <div class="ic-section-header">
                <div class="ic-section-header-content">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php echo sprintf( "Upcoming Appointments (%s)", count($appointments) ); ?>
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
                                <span class="subdata"><?php echo date( get_date_time_format(), Plugin::$instance->utils->date_time->convert_to_local_time( $appointment->get_start_time() ) ); ?> </span>
                        <div class="ic-section-content">
                            <span><?php echo strtoupper($appointment->get_name());?></span>
                            <span><?php $diff = $appointment->get_end_time() - $appointment->get_start_time();
                                 echo  $hour =  minutes(date( 'H:i:s', $diff )); ?></span>
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
                    <?php echo sprintf( "Past Appointments (%s)", count($past_appointments) ); ?>
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
                                <span class="subdata"><?php echo date( get_date_time_format(), Plugin::$instance->utils->date_time->convert_to_local_time( $appointment->get_start_time() ) ) ?> </span>
                        <div class="ic-section-content">
                            <span><?php echo strtoupper($appointment->get_name());?></span>
                            <span><?php $diff = $appointment->get_end_time() - $appointment->get_start_time();
                                 echo  $hour =  minutes(date( 'H:i:s', $diff )); ?> </span>
                            
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php
                else:  _e( 'There is no any past appointment yet!!', 'groundhogg-calendar' ); endif; ?>
            </div>

            
        </div>
        
</div>
<?php
function minutes($time){
    $time = explode(':', $time);
    return ($time[0]*60) + ($time[1]) + ($time[2]/60) .' Minutes';
    }
    ?>