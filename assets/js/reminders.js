(function ($, reminders, modal) {

    $.extend( reminders, {

        init: function () {

            var self = this;

            $( document ).on( 'click', '.edit-email', function ( e ) {

                // alert( 'click' );

                var email_id = $(this).parent().find( '.gh-email-picker' ).val();

                if ( ! email_id || typeof email_id == "undefined" ){
                    alert( 'Please select an email first.' );
                    return;
                }

                modal.init( 'Edit Email', {
                    source: self.edit_email_path + '&email=' + email_id,
                    width: 1500,
                    height: 900,
                    footertext: modal.defaults.footertext
                } );

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