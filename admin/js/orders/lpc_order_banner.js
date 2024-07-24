jQuery(function ($) {
    initTabSystem();
    initLabelGeneration();
    countTotalWeight();
    bindOutwardLabelGeneration();
    bindEditValues();
    initCustomDocuments();
    setSendingService();
    manageInsuranceAmount();
    manageMultiParcels();
    initAdvancedPackaging();

    function initTabSystem() {
        $('.lpc__admin__order_banner__tab').off('click').on('click', function () {
            $('.lpc__admin__order_banner__content').hide();
            $('.lpc__admin__order_banner__' + $(this).attr('data-lpc-tab')).show();
            $('.lpc__admin__order_banner__tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
        });
    }

    function initLabelGeneration() {
        $('.lpc__admin__order_banner__generate_label__item__weight').on('change', function () {
            countTotalWeight($(this));
        });

        $('.lpc__admin__order_banner__generate_label__item__qty').on('change', function () {
            countTotalWeight($(this));
        });

        $('.lpc__admin__order_banner__generate_label__package_weight').on('change', function () {
            countTotalWeight();
        });

        $('.lpc__admin__order_banner__generate_label__item__checkbox').on('change', function () {
            countTotalWeight();
        });

        $('.lpc__admin__order_banner__generate_label__item__check_all').on('change', function () {
            $('.lpc__admin__order_banner__generate_label__item__checkbox').trigger('change');
        });
    }

    function countTotalWeight($trigger = null) {
        if ($trigger !== null) {
            let itemChangedId = $trigger.attr('data-item-id');

            if (!$('#' + itemChangedId + '-checkbox').prop('checked')) {
                return;
            }
        }

        let $adminOrderBanner = $('.lpc__admin__order_banner');

        let totalWeight = 0;
        let totalWeightHidden = 0;
        let numberOfProducts = 0;
        let productsDimensions = [];

        let weightUnit = $('[name="lpc__admin__order_banner__generate_label__weight__unity"]').val();

        $adminOrderBanner.find('.lpc__admin__order_banner__generate_label__item__weight').each(function () {
            let itemId = $(this).attr('data-item-id');
            if ($('#' + itemId + '-checkbox').prop('checked')) {
                let quantity = parseFloat($('#' + itemId + '-qty').val());

                let productWeight = parseFloat($(this).val());
                totalWeight += productWeight * quantity;

                if ('g' === weightUnit && productWeight < 2) {
                    productWeight = 2.00;
                } else if ('kg' === weightUnit && productWeight < 0.002) {
                    productWeight = 0.002;
                }

                totalWeightHidden += productWeight * quantity;
                numberOfProducts += quantity;
                productsDimensions.push(JSON.parse($('#' + itemId + '-dimensions').val()));
            }
        });

        const defaultPackagingWeight = parseFloat($adminOrderBanner.find('.lpc__admin__order_banner__generate_label__package_weight').val());
        let packagingWeight = $('#lpc__admin__order_banner__generate_label__packaging').val();
        if (!packagingWeight || 'custom' === packagingWeight) {
            packagingWeight = defaultPackagingWeight;
        } else if ('auto' === packagingWeight) {
            packagingWeight = getPackagingWeight(defaultPackagingWeight, numberOfProducts, totalWeight, productsDimensions);
        } else {
            packagingWeight = parseFloat(packagingWeight.substring(0, packagingWeight.indexOf('-')));
        }

        $('input[name="lpc__admin__order_banner__generate_label__package_weight_calculated"]').val(packagingWeight);
        totalWeight += packagingWeight;
        totalWeightHidden += packagingWeight;

        const roundedTotalWeight = totalWeight.toFixed(2);
        const roundedTotalWeightHidden = totalWeightHidden.toFixed(2);

        $adminOrderBanner.find('.lpc__admin__order_banner__generate_label__total_weight').html(roundedTotalWeight);
        $adminOrderBanner.find('input[name="lpc__admin__order_banner__generate_label__total_weight__input"]').val(roundedTotalWeightHidden);
    }

    function getPackagingWeight(defaultPackagingWeight, numberOfProducts, totalWeight, productsDimensions) {
        const packagings = JSON.parse($('#lpc__admin__order_banner__generate_label__packagings').val());
        const $automaticPackaging = $('#lpc__admin__order_banner__generate_label__packaging__auto');

        for (let packagingNumber = 0; packagingNumber < packagings.length; packagingNumber++) {
            if (packagings[packagingNumber].max_products && numberOfProducts > packagings[packagingNumber].max_products) {
                continue;
            }

            if (packagings[packagingNumber].max_weight && totalWeight > packagings[packagingNumber].max_weight) {
                continue;
            }

            const packagingDimensions = [
                packagings[packagingNumber].length,
                packagings[packagingNumber].width,
                packagings[packagingNumber].depth
            ];
            packagingDimensions.sort();

            let isFitting = true;
            for (let i = 0; i < productsDimensions.length; i++) {
                productsDimensions[i].sort();

                if (!isPackagingFitting(packagingDimensions, productsDimensions[i])) {
                    isFitting = false;
                }
            }

            if (isFitting) {
                $automaticPackaging.text(lpc_order_banner.automatic + ' (' + packagings[packagingNumber].name + ')');
                const $nonMachinable = $('#lpc__admin__order_banner__generate_label__non_machinable__input');
                if (packagingDimensions[0] + packagingDimensions[1] + packagingDimensions[2] > 120) {
                    $nonMachinable.prop('checked', true);
                } else {
                    $nonMachinable.prop('checked', false);
                }

                return packagings[packagingNumber].weight;
            }
        }

        $automaticPackaging.text(lpc_order_banner.automatic + ' (' + lpc_order_banner.default + ')');
        return defaultPackagingWeight;
    }

    function isPackagingFitting(packagingDimensions, productDimensions) {
        if (!productDimensions[0]) {
            return true;
        }

        for (let i = 0; i < productDimensions.length; i++) {
            if (productDimensions[i] > packagingDimensions[i]) {
                return false;
            }
        }

        return true;
    }

    function lpcDefaultValue(value, defaultValue) {
        return value !== undefined && value.length > 0 ? value : defaultValue;
    }

    function bindOutwardLabelGeneration() {
        let generatingLabel = false;
        $('.lpc__admin__order_banner__generate_label__generate-label-button').off('click').on('click', function () {
            if (generatingLabel) {
                return;
            }

            generatingLabel = true;
            const itemsForLabel = [];
            $('.lpc__admin__order_banner__generate_label__item__checkbox:checked').each(function () {
                const currentItemId = $(this).attr('data-item-id');
                itemsForLabel.push({
                    'id': currentItemId,
                    'price': $('.lpc__admin__order_banner__generate_label__item__price[data-item-id="' + currentItemId + '"]').val(),
                    'quantity': $('.lpc__admin__order_banner__generate_label__item__qty[data-item-id="' + currentItemId + '"]').val(),
                    'weight': $('.lpc__admin__order_banner__generate_label__item__weight[data-item-id="' + currentItemId + '"]').val()
                });
            });

            let orderId = $('#post_ID').val();
            if (!orderId) {
                const urlParams = new URLSearchParams($(this).closest('form').attr('action'));
                orderId = urlParams.get('id');
            }

            let block_code = 'none';
            const blockCodeInput = $('input[name="lpc__admin__order_banner__generate_label__block_code__input"]');
            if (blockCodeInput.length > 0) {
                if (blockCodeInput.is(':checked')) {
                    block_code = 'enabled';
                } else {
                    block_code = 'disabled';
                }
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'lpc_order_generate_label',
                    order_id: orderId,
                    label_type: $('select[name="lpc__admin__order_banner__generate_label__outward_or_inward"]').val(),
                    items: itemsForLabel,
                    package_weight: lpcDefaultValue($('input[name="lpc__admin__order_banner__generate_label__package_weight_calculated"]').val(), 0),
                    total_weight: lpcDefaultValue($('input[name="lpc__admin__order_banner__generate_label__total_weight__input"]').val(), 0),
                    package_length: lpcDefaultValue($('input[name="lpc__admin__order_banner__generate_label__package_length"]').val(), 0),
                    package_width: lpcDefaultValue($('input[name="lpc__admin__order_banner__generate_label__package_width"]').val(), 0),
                    package_height: lpcDefaultValue($('input[name="lpc__admin__order_banner__generate_label__package_height"]').val(), 0),
                    package_description: lpcDefaultValue($('input[name="lpc__admin__order_banner__generate_label__package_description"]').val(), ''),
                    cn23_type: lpcDefaultValue($('select[name="lpc__admin__order_banner__generate_label__cn23__type"]').val(), ''),
                    shipping_costs: lpcDefaultValue($('input[name="lpc__admin__order_banner__generate_label__shipping_costs"]').val(), 0),
                    non_machinable: $('input[name="lpc__admin__order_banner__generate_label__non_machinable__input"]:checked').length > 0 ? 'yes' : '',
                    using_insurance: $('input[name="lpc__admin__order_banner__generate_label__using__insurance__input"]:checked').length > 0 ? 'yes' : 'no',
                    insurance_amount: lpcDefaultValue($('select[name="lpc__admin__order_banner__generate_label__insurance__amount"]').val(), 0),
                    multi_parcels: $('input[name="lpc__admin__order_banner__generate_label__multi__parcels__input"]:checked').length > 0 ? 'yes' : '',
                    parcels_amount: lpcDefaultValue($('input[name="lpc__admin__order_banner__generate_label__parcels_amount"]').val(), 0),
                    block_code: block_code
                },
                success: function (response) {
                    generatingLabel = false;
                    if (response.type === 'error') {
                        alert(response.data.message);
                    } else {
                        location.reload();
                    }
                }
            });
        });
    }

    function bindEditValues() {
        $('.lpc__admin__order_banner__generate_label__edit_value').off('click').on('click', function () {
            let $generateLabelDiv = $(this).closest('.lpc__admin__order_banner__generate_label__div');

            if ($(this).hasClass('woocommerce-input-toggle--disabled')) {
                $generateLabelDiv.find('.lpc__admin__order_banner__generate_label__item__weight').removeAttr('readonly');
                $generateLabelDiv.find('.lpc__admin__order_banner__generate_label__item__price').removeAttr('readonly');
                $generateLabelDiv.find('.lpc__admin__order_banner__generate_label__shipping_costs').removeAttr('readonly');
                $generateLabelDiv.find('.lpc__admin__order_banner__generate_label__package_weight').removeAttr('readonly');
                $(this).removeClass('woocommerce-input-toggle--disabled');
                $(this).addClass('woocommerce-input-toggle--enabled');
            } else if ($(this).hasClass('woocommerce-input-toggle--enabled')) {
                $generateLabelDiv.find('.lpc__admin__order_banner__generate_label__item__weight').attr('readonly', 'readonly');
                $generateLabelDiv.find('.lpc__admin__order_banner__generate_label__item__price').attr('readonly', 'readonly');
                $generateLabelDiv.find('.lpc__admin__order_banner__generate_label__shipping_costs').attr('readonly', 'readonly');
                $generateLabelDiv.find('.lpc__admin__order_banner__generate_label__package_weight').attr('readonly', 'readonly');
                $(this).removeClass('woocommerce-input-toggle--enabled');
                $(this).addClass('woocommerce-input-toggle--disabled');
            }

        });
    }

    function manageInsuranceAmount() {
        $('.lpc__admin__order_banner__generate_label__using__insurance__input').off('click').on('click', function () {
            let $insuranceAmountInput = $('#lpc_insurance_amount');
            if (this.checked) {
                $insuranceAmountInput.prop('disabled', false);
            } else {
                $insuranceAmountInput.val('');
                $insuranceAmountInput.prop('disabled', true);
            }
        });
    }

    function manageMultiParcels() {
        $('.lpc__admin__order_banner__generate_label__multi__parcels__input').off('click').on('click', function () {
            const $parcelsAmountInput = $('#lpc_multi_parcels_number');
            if (this.checked) {
                $parcelsAmountInput.prop('disabled', false);
            } else {
                $parcelsAmountInput.val('');
                $parcelsAmountInput.prop('disabled', true);
            }
        });
    }

    function initCustomDocuments() {
        // Init Add more buttons
        let $addMoreButtons = $('.lpc__admin__order_banner__send_documents__more');
        $addMoreButtons.off('click').on('click', function () {
            let template = document.querySelector('#lpc__admin__order_banner__send_documents__template').innerHTML;
            let parcelNumber = $(this).attr('data-lpc-parcelnumber');

            $(this)
                .closest('.lpc__admin__order_banner__send_documents__container')
                .find('.lpc__admin__order_banner__send_documents__listing')
                .append(template.replace('__PARCELNUMBER__', parcelNumber));

            // Init the document type field
            $('.lpc__admin__order_banner__document__type').off('change').on('change', function () {
                let selectedType = $(this).val();
                if (0 === selectedType.length) {
                    selectedType = '__TYPE__';
                }
                let $fileInput = $(this)
                    .closest('tr')
                    .find('.lpc__admin__order_banner__document__file');
                $fileInput.attr('name', $fileInput.attr('name').replace(/\[[A-Z0-9_]+\]\[\]/, '[' + selectedType + '][]'));
                $fileInput.prop('disabled', 0 === selectedType.length || '__TYPE__' === selectedType);
            });
        });

        if (0 < $addMoreButtons.length) {
            $('form[name="post"]').attr('enctype', 'multipart/form-data');
        }

        // Add a default document row
        $addMoreButtons.each(function () {
            let $rows = $(this)
                .closest('.lpc__admin__order_banner__send_documents__container')
                .find('.lpc__admin__order_banner__send_documents__listing tr').not('.lpc__customs__sent__document');
            if (0 === $rows.length) {
                $(this).click();
            }
        });

        let sendingDocuments = false;
        $('.lpc__admin__order_banner__send_documents__listing__send_button').off('click').on('click', function () {
            if (sendingDocuments) {
                return;
            }

            const formData = new FormData();
            const $container = $(this).closest('.lpc__admin__order_banner__send_documents__container');
            const $filesToUpload = $container.find('.lpc__admin__order_banner__document__file');
            if ($filesToUpload.length === 0) {
                return;
            }

            sendingDocuments = true;

            let orderId = $('#post_ID').val();
            if (!orderId) {
                const urlParams = new URLSearchParams($(this).closest('form').attr('action'));
                orderId = urlParams.get('id');
            }

            formData.append('action', 'lpc_order_send_documents');
            formData.append('order_id', orderId);

            $filesToUpload.each(function () {
                const fileName = $(this).prop('name');
                const file = $(this).prop('files');

                if (fileName.indexOf('__TYPE__') !== -1 || file.length === 0) {
                    return;
                }

                formData.append('fileTypes[]', fileName);
                formData.append('files[]', file[0]);
            });

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'text',
                cache: false,
                contentType: false,
                processData: false,
                data: formData,
                success: function (response) {
                    sendingDocuments = false;
                    response = JSON.parse(response);

                    if (response.type === 'error') {
                        alert(response.data.message);
                    } else {
                        location.reload();
                    }
                }
            });
        });
    }

    function setSendingService() {
        $('[name="lpc__admin__order_banner__generate_label__outward_or_inward"]').on('change', function () {
            const $customSendingService = $('.lpc__admin__order_banner__generate_label__sending_service__container');
            if ('inward' === $(this).val()) {
                $customSendingService.hide();
            } else {
                $customSendingService.show();
            }
        });
    }

    function initAdvancedPackaging() {
        $('#lpc__admin__order_banner__generate_label__packaging').on('change', function () {
            countTotalWeight();
            const $packageWeightContainer = $('.lpc__admin__order_banner__generate_label__package_weight__container');
            const $nonMachinable = $('#lpc__admin__order_banner__generate_label__non_machinable__input');
            const selectedValue = $(this).val();
            if ('custom' === selectedValue) {
                $nonMachinable.prop('checked', false);
                $packageWeightContainer.show();
            } else {
                if ('auto' !== selectedValue) {
                    $nonMachinable.prop('checked', parseFloat(selectedValue.substring(selectedValue.indexOf('-') + 1)) > 120);
                }

                $packageWeightContainer.hide();
            }
        });
    }
});
