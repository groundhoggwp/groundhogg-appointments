
var appointment;

(function ($) {

    appointment = {

        activeDomainBox: null,

        init: function()
        {
            $( '#book_appointment' ).on( 'click', function(e){
                e.preventDefault();

                //check for appointment
                if ( $('#hidden_data').data('start_date') == ''  ||  $('#hidden_data').data('start_date') == '' ) {
                    alert( 'Please select appointment.' );
                } else {
                    //check all the fields entered or not !
                    if ($('#first_name').val() == '' || $('#last_name').val() == '' || $('#email').val() == '' ){
                        alert( 'Please enter your information.' );
                    } else {
                        //validate email address
                        if(validateEmail($('#email').val())){
                            //AJAX REQUEST TO CREATE APPOINTMENT

                            // MAKE AJAX REQUEST TO ADD APPOINTMENT INSIDE DATA BASE
                            $.ajax({
                                type: "post",
                                url: ajax_object.ajax_url,
                                dataType: 'json',
                                data: {
                                    action: 'gh_add_appointment_client',
                                    start_time: $('#hidden_data').data('start_date'),
                                    end_time: $('#hidden_data').data('end_date'),
                                    email: $('#email').val(),
                                    first_name : $('#first_name').val(),
                                    last_name : $('#last_name').val(),
                                    calendar_id : $('#calendar_id').val(),
                                    appointment_name: $('#appointment_name').val(),

                                },
                                success: function (response) {


                                    if (response.status == 'failed') {
                                        alert(response.msg);

                                    } else {
                                       //clear all the data
                                        $('#email').val('');
                                        $('#first_name').val('');
                                        $('#last_name').val('');
                                        $('#appointment_name').val('');

                                        $('#hidden_data').data('start_date' , '');
                                        $('#hidden_data').data('end_date'   , '');
                                        $('#hidden_data').data('control_id' , '');
                                        // retrieve all available appointments  after booking
                                        $('#select_time').children().remove();
                                        $('#select_time').append('<h3>'+ response.msg +'</h3>');
                                        alert(response.msg);
                                    }
                                }
                            });

                        } else {
                            alert('Please enter valid email address.');
                        }
                    }
                }
            } );


            $(document).on( 'click', '.gh_appointment_class', function(e){
                e.preventDefault();

                // get data from hidden field
                var id =  $('#hidden_data').data('control_id');

                if( !( id == '' ) ) {
                    //remove colour
                    $('#'+id).css( 'background-color' ,'#8fdf82' );
                 
                }
                // set data inside hidden field
                $(this).css( 'background-color' ,'#123456' );
                $('#hidden_data').data('start_date' , $(this).data('start_date'));
                $('#hidden_data').data('end_date'   , $(this).data('end_date'));
                $('#hidden_data').data('control_id' , $(this).attr('id'));

            } );


            $('#date').datepicker({
                changeMonth: true,
                changeYear: true,
                minDate:0,
                firstDay: 0,
                dateFormat:'yy-mm-dd',
                onSelect: function(dateText) {
                    $('#select_time').children().remove();

                    //clear hidden field data on date change

                    $('#hidden_data').data('start_date' , '');
                    $('#hidden_data').data('end_date'   , '');
                    $('#hidden_data').data('control_id' , '');


                    // MAKE AJAX REQUEST TO GET ALL THE AVAILABLE TIME SLOTS..
                    $.ajax({
                        type: "post",
                        url: ajax_object.ajax_url,
                        dataType: 'json',
                        data: {
                            action: 'gh_get_appointment_client',
                            date : dateText,
                            calendar : $('#calendar_id').val()
                        },
                        success: function (response) {
                            if (response.status == 'failed') {
                                //alert(response.msg);
                                $('#select_time').append('<h3>'+ response.msg +'</h3>');
                            } else {
                                $('#select_time').children().remove();
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

            function validateEmail(sEmail) {
                var filter = /^([\w-\.]+)@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.)|(([\w-]+\.)+))([a-zA-Z]{2,4}|[0-9]{1,3})(\]?)$/;
                if (filter.test(sEmail)) {
                    return true;
                }
                else {
                    return false;
                }
            }
        },
    };

    $(function () {
        appointment.init();
    })

})(jQuery);