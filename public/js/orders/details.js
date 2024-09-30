jQuery(function ($) {
    function init() {
        $('#lpc_return_products').on('click', (event) => {
            event.preventDefault();
            window.location.href = $('#lpc_return_label_url').val();
        });
    }

    init();
});
