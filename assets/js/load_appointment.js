
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
                    $('#select_time').children().remove();
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
                            if (response.status == 'failed') {
                                alert(response.msg);
                                $('#select_time').css("visibility", "hidden");
                            } else {
                                $('#select_time').css("visibility", "visible");
                                var opts = $.parseJSON(response.data);
                                $.each(opts, function ( i , d) {
                                    $('#select_time').append('<option value = "'+d.id + '" data-start_date = "'+ d.start +'" data-end_date="'+ d.end +'" >' + d.name + '</option>');
                                });
                            }
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