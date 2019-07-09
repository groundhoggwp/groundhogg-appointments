<?php
namespace Groundhogg\Templates;

/**
 * This is an appointment cancelled Email.
 */
?>
<div class="row">
    <div class="content-wrapper text_block">
        <div class="" style="padding: 5px;font-family: Arial, sans-serif;font-size: 16px">
            <p style="text-align: left">Hey {first},</p>
            <p>Your appointment scheduled on <strong>{appointment_start_time}</strong> has been cancelled.</p>
        </div>
    </div>
</div>
<div class="row">
    <div class="content-wrapper text_block">
        <div class="" style="padding: 5px;font-family: Arial, sans-serif;font-size: 16px">
            <p>You can always book another appointment using our booking page.</p>
        </div>
    </div>
</div>
<div class="row">
    <div class="content-wrapper text_block">
        <div class="" style="padding: 5px;font-family: Arial, sans-serif;font-size: 16px">
            <p style="text-align: left"><strong>Thank you!</strong></p>
            <p><em>@ the {business_name} team</em></p>
        </div>
    </div>
</div>