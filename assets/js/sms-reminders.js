(function ($, reminders, modal) {

    $.extend( reminders, {

        init: function () {

            var self = this;

            $( document ).on( 'click', '.edit-sms', function ( e ) {

                // alert( 'click' );

                var sms_id = $(this).parent().find( '.gh-sms-picker' ).val();

                if ( ! sms_id || typeof sms_id == "undefined" ){
                    alert( 'Please select an sms first.' );
                    return;
                }
                window.open(self.edit_sms_path + '&sms=' + sms_id );
            } );

            $(document).on('click', '.trash-rule', function (e) {
                e.preventDefault();
                $(e.target).closest('tr').remove();
            });

            $(document).on('click', '.add-rule', function (e) {
                e.preventDefault();
                var $row = $(e.target).closest('tr');
                var $new = $row.clone();
                $new.insertAfter($row);
                $new.find( '.select2' ).remove();
                $(document).trigger( 'gh-init-pickers' );
            });

        },

    } );

    $(function () {
        reminders.init();
    });
})(jQuery, CalendarReminders, GroundhoggModal);