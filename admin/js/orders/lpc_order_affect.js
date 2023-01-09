jQuery(function ($) {
    function lpcOrderAffect() {
        $('.lpc_order_affect_toggle_methods').off('click').on('click', function () {
            $(this).closest('.lpc_order_affect').find('.lpc_order_affect_available_methods').toggle();
            if ($.lpcInitWidget) {
                $.lpcInitWidget();
            }
        });

        $('.lpc_order_affect_validate_method').off('click').on('click', function () {
            let $lpcOrderAffectElement = $(this).closest('.lpc_order_affect_available_methods');
            let $lpcOrderAffectNewMethod = $lpcOrderAffectElement.find('input[name="lpc_new_shipping_method"]:checked').val();
            let $errorMessageDiv = $lpcOrderAffectElement.find('.lpc_order_affect_error_message');

            $lpcOrderAffectElement.find('input[name="lpc_order_affect_update_method"]').val(1);
            $lpcOrderAffectElement.find('input[name="lpc_order_affect_shipping_item_id"]').val($(this).closest('tr.shipping').attr('data-order_item_id'));

            if ($lpcOrderAffectNewMethod !== undefined) {
                if ($lpcOrderAffectNewMethod === 'lpc_relay' && $.isEmptyObject($.parseJSON($lpcOrderAffectElement.find(
                    'input[name="lpc_order_affect_relay_informations"]').val()))) {
                    $errorMessageDiv.find('.lpc_order_affect_error_message_pickup').show();
                } else {
                    $(this).closest('form').submit();
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
