jQuery(function ($) {
    $(document).on('click', '.lpc-notice .notice-dismiss', function () {
        let $notice = $(this).parent('.lpc-notice');
        $.post({
            url: window.ajaxurl,
            data: {
                action: 'lpc-dismiss-notice',
                'lpc-dismiss-notice-nonce': $notice.find('input[id*="-nonce"]').val()
            }
        });
    });
});
