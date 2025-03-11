/**
 * Admin code for dismissing notifications.
 *
 */
(function( $ ) {
    'use strict';
    $( function() {
        $( '.wple-notice' ).on( 'click', '.notice-dismiss', function( event, el ) {
            const $notice = $(this).parent('.notice.is-dismissible');
            const notice_hash = $notice.data('msg_id');

            // simple ping the dismiss URL with the message ID
            if ( notice_hash ) {
                $.get( wple_i18n.ajax_url + "?action=wple_dismiss_notice&id="+ notice_hash +"&_wpnonce="+ wple_i18n.wple_ajax_nonce );
            }

        });
    } );
})( jQuery );