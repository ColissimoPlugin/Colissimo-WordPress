var $affectMethodDiv;

jQuery(function ($) {
    window.lpc_callback = function (point) {
        $('.lpc-modal .modal-close').trigger('click');

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
                        if (window.lpcBlockChangeContent) {
                            window.lpcBlockChangeContent(response.html);
                        }
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

    $(document).on('click', '.lpc_pick_up_widget_show_map', function (e) {
        e.preventDefault();

        $affectMethodDiv = $(this).closest('.lpc_order_affect_available_methods');

        LpcModal({
            template: 'lpc_pick_up_widget_container'
        });

        var colissimoParams = {
            callBackFrame: 'lpc_callback'
        };

        $.extend(colissimoParams, window.lpc_widget_info);
        $('#lpc_widget_container').frameColissimoOpen(colissimoParams);

        // We need to close the widget to be able to open it again
        $('.lpc-modal .modal-close').on('click', function () {
            let container = $('#lpc_widget_container');
            if (container.length > 0) {
                try {
                    container.frameColissimoClose();
                } catch (e) {
                    console.error(e);
                }
            }
        });
    });
});
