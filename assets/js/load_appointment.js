
var appointment;

(function ($) {

    appointment = {

        activeDomainBox: null,

        init: function()
        {
            $( '#btnalert' ).on( 'click', function(e){
                e.preventDefault();
                alert($('#hidden_data').data('start_date'));
            } );

            $(document).on( 'click', '.gh_appointment_class', function(e){
                e.preventDefault();

                // get data from hidden field
                var id =  $('#hidden_data').data('control_id');

                if( !( id == '' ) ) {
                    //remove colour
                    $('#'+id).css( 'background-color' ,'#8fdf82' );
                    console.log(id);
                }
                // set data inside hidden field
                $(this).css( 'background-color' ,'#123456' );
                $('#hidden_data').data('start_date' , $(this).attr('data-start_date'));
                $('#hidden_data').data('end_date'   , $(this).attr('data-end_date'));
                $('#hidden_data').data('control_id' , $(this).attr('id'));

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
                                    $('#select_time').append('<input type="button" class="gh_appointment_class" name="appointment_time" id = "gh_appointment_'+ d.start +'" style="margin: 10px ;background-color: #8fdf82;" data-start_date = "'+ d.start +'" data-end_date="'+ d.end +'" value ="' + d.name + '"/>');
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