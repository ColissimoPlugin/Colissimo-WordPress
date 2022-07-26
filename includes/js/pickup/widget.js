var $affectMethodDiv;

jQuery(function ($) {
    $.lpcInitWidget = function () {
        window.lpc_callback = function (point) {
            $('.lpc-modal .modal-close').click();

            if ($affectMethodDiv.length === 0) {
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
            } else {
                $affectMethodDiv.find('input[name="lpc_order_affect_relay_informations"]').val(JSON.stringify(point));
                $affectMethodDiv.find('.lpc_order_affect_relay_information_displayed')
                                .html(point['nom']
                                      + ' ('
                                      + point['identifiant']
                                      + ')'
                                      + '<br>'
                                      + point['adresse1']
                                      + '<br>'
                                      + point['codePostal']
                                      + ' '
                                      + point['localite']);
            }
        };

        // We need to close the widget to be able to open it again
        $('body').on('wc_backbone_modal_before_remove', function () {
            let container = $('#lpc_widget_container');
            if (container.length > 0) {
                try {
                    container.frameColissimoClose();
                } catch (e) {
                }
            }
        });

        $('#lpc_pick_up_widget_show_map').off('click').on('click', function (e) {
            e.preventDefault();

            $affectMethodDiv = $(this).closest('.lpc_order_affect_available_methods');

            $(this).WCBackboneModal({
                template: 'lpc_pick_up_widget_container'
            });

            var colissimoParams = {
                callBackFrame: 'lpc_callback'
            };

            $.extend(colissimoParams, window.lpc_widget_info);

            $('#lpc_widget_container').frameColissimoOpen(colissimoParams);
        });
    };

    $(document.body)
        .on('updated_shipping_method', function () {
            $.lpcInitWidget(); // this is needed when a new shipping method is chosen
        })
        .on('updated_wc_div', function () {
            $.lpcInitWidget(); // this is needed when checkout is updated (new item quantity...)
        })
        .on('updated_checkout', function () {
            $.lpcInitWidget(); // this is needed when checkout is loaded or updated (new item quantity...)
        });

    $.lpcInitWidget(); // this is needed when page is refreshed / loaded
});
