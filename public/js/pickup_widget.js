jQuery(function ($) {
    function init() {
        window.lpc_callback = function (point) {
            $('.lpc-modal .modal-close').click();

            var $errorDiv = $('#lpc_layer_error_message');
            $.ajax({
                url: lpcPickUpSelection.pickUpSelectionUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    lpc_pickUpInfo: point
                },
                success: function (response) {
                    if (response.type === 'success') {
                        $errorDiv.hide();
                        $('#lpc_pick_up_info').replaceWith(response.html);
                        $('body').trigger('update_checkout');
                    } else {
                        $errorDiv.html(response.message);
                        $errorDiv.show();
                    }
                }
            });
        };

        $('#lpc_pick_up_widget_show_map').off('click').on('click', function (e) {
            e.preventDefault();

            $(this).WCBackboneModal({
                template: 'lpc_pick_up_widget_container'
            });

            var colissimoParams = {
                callBackFrame: 'lpc_callback'
            };
            $.extend(colissimoParams, window.lpc_widget_info);

            $('#lpc_widget_container').frameColissimoOpen(colissimoParams);
        });
    }

    $(document.body)
        .on('updated_shipping_method', function () {
            init(); // this is needed when a new shipping method is chosen
        })
        .on('updated_wc_div', function () {
            init(); // this is needed when checkout is updated (new item quantity...)
        })
        .on('updated_checkout', function () {
            init(); // this is needed when checkout is loaded or updated (new item quantity...)
        });
    init(); // this is needed when page is refreshed / loaded
});
