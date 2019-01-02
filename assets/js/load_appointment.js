
var appointment;

(function ($) {

    appointment = {

        activeDomainBox: null,

        init: function()
        {
            $( '#btnalert' ).on( 'click', function(e){
                e.preventDefault();
            } );

            $('#date').datepicker({
                changeMonth: true,
                changeYear: true,
                minDate:0,
                dateFormat:'yy-mm-dd',
                onSelect: function(dateText) {
                    // MAKE AJAX REQUEST TO GET ALL THE AVAILABLE TIME SLOTS..
                    //AJAX Call to add appointment

                    $.ajax({
                        type: "post",
                        url: ajax_object.ajax_url,
                        dataType: 'json',
                        data: {
                            action: 'gh_add_appointment_client',
                            date : dateText,
                            calendar : $('#calendar_id').val()
                        },
                        success: function (response) {
                            alert(response.msg);

                        }
                    });

                }
            });

        },

    };

    $(function () {
        appointment.init();
    })

})(jQuery);


