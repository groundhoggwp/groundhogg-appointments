<?php
namespace Groundhogg\Templates;

/**
 * This is an appointment rescheduled Email.
 */
?>

<div class="row">
    <div class="content-wrapper text_block">
        <div class="" style="padding: 5px;font-family: Arial, sans-serif;font-size: 16px">
            <p style="text-align: left">Hey {first},</p>
            <p>We successfully rescheduled your appointment. Your new appointment will be from <strong>{appointment_start_time}</strong> to <strong>{appointment_end_time}.</strong></p>
        </div>
    </div>
</div>
<div class="row" data-block="divider">
    <div class="content-wrapper divider_block">
        <div class="content-inside inner-content text-content">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tbody>
                <tr>
                    <td class="divider">
                        <div style="margin: 5px 0 5px 0">
                            <hr style="width:80%">
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="row">
    <div class="content-wrapper text_block">
        <div class="" style="padding: 5px;font-family: Arial, sans-serif;font-size: 16px">
            <p><strong>Additional info:</strong></p>
            <p style="text-align: left">{appointment_notes}</p>
        </div>
    </div>
</div>
<div class="row" data-block="divider">
    <div class="content-wrapper divider_block">
        <div class="content-inside inner-content text-content">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tbody>
                <tr>
                    <td class="divider">
                        <div style="margin: 5px 0 5px 0">
                            <hr style="width:80%">
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="row">
    <div class="content-wrapper text_block">
        <div class="" style="padding: 5px;font-family: Arial, sans-serif;font-size: 16px">
            <p style="text-align: center">{appointment_actions}</p>
        </div>
    </div>
</div>
<div class="row" data-block="divider">
    <div class="content-wrapper divider_block">
        <div class="content-inside inner-content text-content">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tbody>
                <tr>
                    <td class="divider">
                        <div style="margin: 5px 0 5px 0">
                            <hr style="width:80%">
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>
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
