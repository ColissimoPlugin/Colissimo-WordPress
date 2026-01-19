jQuery(function ($) {
    function init() {
        changeCredentialsType();
        securedReturn();
        thermalPrint();
        extraCost();
        relays();
        shippingDate();
        ddp();
        doc();
    }

    init();

    function changeCredentialsType() {
        const $apiKeyFields = $('#lpc_apikey, #lpc_contract_number');
        const $accountFields = $('#lpc_id_webservices, #lpc_pwd_webservices');
        const $apiKeyFieldsContainers = $apiKeyFields.closest('tr');
        const $accountFieldsContainers = $accountFields.closest('tr');
        $('#lpc_credentials_type').on('change', function () {
            if ('api_key' === $(this).val()) {
                $accountFieldsContainers.hide();
                $apiKeyFieldsContainers.show();
                $apiKeyFields.attr('required', 'required');
                $accountFields.removeAttr('required');
            } else {
                $accountFieldsContainers.show();
                $apiKeyFieldsContainers.hide();
                $accountFields.attr('required', 'required');
                $apiKeyFields.removeAttr('required');
            }
        }).trigger('change');
    }

    function securedReturn() {
        const $frontReturn = $('#lpc_customers_download_return_label');
        const $securedReturn = $('#lpc_secured_return');
        const $frontReturnDelayContainer = $('.wc-settings-row-lpc_customers_download_return_label_days_container');
        const $securedReturnContainer = $('#lpc_secured_return_container');
        const $balReturnContainer = $('.wc-settings-row-lpc_bal_return_container');
        const $autoReturnContainer = $('.wc-settings-row-lpc_createReturnLabelWithOutward_container');

        const toggleReturnOptions = function () {
            $frontReturnDelayContainer.show();
            $securedReturnContainer.show();
            $balReturnContainer.show();
            $autoReturnContainer.show();

            if ('no' === $frontReturn.val()) {
                $frontReturnDelayContainer.hide();
                $securedReturnContainer.hide();
                $balReturnContainer.hide();
            } else if ($securedReturn.is(':checked')) {
                $balReturnContainer.hide();
                $autoReturnContainer.hide();
            }
        };

        $frontReturn.on('change', toggleReturnOptions).trigger('change');
        $securedReturn.on('change', toggleReturnOptions);
    }

    function thermalPrint() {
        const $ipContainer = $('.wc-settings-row-lpc_zpldpl_labels_ip_container');
        const $protocolContainer = $('.wc-settings-row-lpc_zpldpl_labels_protocol_container');
        $('#lpc_zpldpl_labels_port').on('change', function () {
            if ('USB' === $(this).val()) {
                $ipContainer.hide();
                $protocolContainer.show();
            } else {
                $ipContainer.show();
                $protocolContainer.hide();
            }
        }).trigger('change');
    }

    function extraCost() {
        const $extraCostOverFreeContainer = $('.wc-settings-row-lpc_extra_cost_over_free_container');
        $('#lpc_extra_cost').on('change', function () {
            if ($(this).val() > 0) {
                $extraCostOverFreeContainer.show();
            } else {
                $extraCostOverFreeContainer.hide();
            }
        }).trigger('change');
    }

    function relays() {
        const $gmapsKeyContainer = $('.wc-settings-row-lpc_gmap_key_container');
        const $mobileMapContainer = $('.wc-settings-row-lpc_show_list_only_mobile_container');
        const $maxPointsContainer = $('.wc-settings-row-lpc_max_relay_point_container');
        const $customizeWidgetContainer = $('.wc-settings-row-lpc_prCustomizeWidget_container');
        const $customizeWidget = $('#lpc_prCustomizeWidget');
        const $addressTextColorContainer = $('.wc-settings-row-lpc_prAddressTextColor_container');
        const $listTextColorContainer = $('.wc-settings-row-lpc_prListTextColor_container');
        const $displayFontContainer = $('.wc-settings-row-lpc_prDisplayFont_container');

        $('#lpc_pickup_map_type').on('change', function () {
            if ('gmaps' === $(this).val()) {
                $gmapsKeyContainer.show();
            } else {
                $gmapsKeyContainer.hide();
            }

            if ('widget' === $(this).val()) {
                $mobileMapContainer.hide();
                $maxPointsContainer.hide();
                $customizeWidgetContainer.show();
                if ($customizeWidget.is(':checked')) {
                    $addressTextColorContainer.show();
                    $listTextColorContainer.show();
                    $displayFontContainer.show();
                } else {
                    $addressTextColorContainer.hide();
                    $listTextColorContainer.hide();
                    $displayFontContainer.hide();
                }
            } else {
                $mobileMapContainer.show();
                $maxPointsContainer.show();
                $customizeWidgetContainer.hide();
                $addressTextColorContainer.hide();
                $listTextColorContainer.hide();
                $displayFontContainer.hide();
            }
        }).trigger('change');

        $customizeWidget.on('change', function () {
            if ($(this).is(':checked')) {
                $addressTextColorContainer.show();
                $listTextColorContainer.show();
                $displayFontContainer.show();
            } else {
                $addressTextColorContainer.hide();
                $listTextColorContainer.hide();
                $displayFontContainer.hide();
            }
        });
    }

    function shippingDate() {
        const $customizeWidget = $('#lpc_display_shipping_date');
        const $shippingDateOptionsContainers = $('.wc-settings-row-lpc_delivery_date_container');

        $customizeWidget.on('change', function () {
            if ($(this).is(':checked')) {
                $shippingDateOptionsContainers.show();
            } else {
                $shippingDateOptionsContainers.hide();
            }
        }).trigger('change');
    }

    function ddp() {
        const $ftdExtraCostContainer = $('.wc-settings-row-lpc_extraCostOm');

        $('#lpc_customs_isFtd').on('change', function () {
            if ($(this).is(':checked')) {
                $ftdExtraCostContainer.show();
            } else {
                $ftdExtraCostContainer.hide();
            }
        }).trigger('change');
    }

    function doc() {
        $('#lpc_doc_download').on('click', function () {
            window.open($('#lpc_doc_url').val(), '_blank');
        });
        $('#lpc_doc_EN_download').on('click', function () {
            window.open($('#lpc_doc_EN_url').val(), '_blank');
        });
    }
});
