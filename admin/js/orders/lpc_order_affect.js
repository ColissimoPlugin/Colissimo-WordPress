jQuery(function ($) {
    function lpcOrderAffect() {
        $('.lpc_order_affect_toggle_methods').off('click').on('click', function () {
            $(this).closest('.lpc_order_affect').find('.lpc_order_affect_available_methods').toggle();
            if ($.lpcInitWidget) {
                $.lpcInitWidget();
            }
        });

        let changingMethod = false;
        $('.lpc_order_affect_validate_method').off('click').on('click', function () {
            if (changingMethod) {
                return;
            }

            const lpcOrderAffectContainerName = $(this).closest('.lpc_order_affect').attr('id');
            const $lpcOrderAffectContainer = $('.lpc_order_affect');

            const $lpcOrderAffectElement = $(this).closest('.lpc_order_affect_available_methods');
            const $lpcOrderAffectNewMethod = $lpcOrderAffectElement.find('input[name="lpc_new_shipping_method"]:checked').val();
            const $errorMessageDiv = $lpcOrderAffectElement.find('.lpc_order_affect_error_message');

            $lpcOrderAffectElement.find('input[name="lpc_order_affect_shipping_item_id"]')
                                  .val($('.woocommerce_order_items_wrapper tr.shipping').attr('data-order_item_id'));

            if ($lpcOrderAffectNewMethod !== undefined) {
                if ($lpcOrderAffectNewMethod === 'lpc_relay' && $.isEmptyObject($.parseJSON($lpcOrderAffectElement.find(
                    'input[name="lpc_order_affect_relay_informations"]').val()))) {
                    $errorMessageDiv.find('.lpc_order_affect_error_message_pickup').show();
                } else {
                    changingMethod = true;
                    $lpcOrderAffectContainer.each(function (index, element) {
                        const $element = $(element);
                        if ($element.attr('id') !== lpcOrderAffectContainerName) {
                            $element.remove();
                        }
                    });

                    let orderId = $('#post_ID').val();
                    if (!orderId) {
                        const urlParams = new URLSearchParams($(this).closest('form').attr('action'));
                        orderId = urlParams.get('id');
                    }

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'lpc_order_affect',
                            order_id: orderId,
                            new_shipping_method: $('input[name="lpc_new_shipping_method"]:checked').val(),
                            shipping_item_id: $('input[name="lpc_order_affect_shipping_item_id"]').val(),
                            relay_information: $('input[name="lpc_order_affect_relay_informations"]').val()
                        },
                        success: function (response) {
                            changingMethod = false;
                            if (response.type === 'error') {
                                alert(response.data.message);
                            } else {
                                location.reload();
                            }
                        }
                    });
                }
            } else {
                $errorMessageDiv.find('.lpc_order_affect_error_message_method').show();
            }
        });

        $('input[name="lpc_new_shipping_method"]').on('change', function () {
            let $lpcOrderAffectElement = $(this).closest('.lpc_order_affect_available_methods');
            let $relayOptions = $lpcOrderAffectElement.find('.lpc_order_affect_relay');
            let $errorMessageDiv = $lpcOrderAffectElement.find('.lpc_order_affect_error_message');

            $errorMessageDiv.find('.lpc_order_affect_error_message_method').hide();
            $errorMessageDiv.find('.lpc_order_affect_error_message_pickup').hide();

            if ($(this).val() === 'lpc_relay') {
                $relayOptions.show();
            } else {
                $relayOptions.hide();
            }
        });
    }

    lpcOrderAffect();

    window.lpc_bind_order_affect = lpcOrderAffect;
});
