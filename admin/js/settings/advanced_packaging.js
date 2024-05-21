jQuery(function ($) {
    function initAdvancedPackaging() {
        setCheckAllCheckbox();
        setDeletePackaging();
        setPriorityUp();
        setPriorityDown();

        updatePriorityArrows();
        updateHeader();
        handleActions();
        handleNewPackage();
    }

    initAdvancedPackaging();

    function setCheckAllCheckbox() {
        $('#lpc_packaging_all').on('change', function () {
            $('.lpc_packaging_row input[type="checkbox"]').prop('checked', this.checked);
        });
    }

    function setDeletePackaging() {
        $('#lpc_delete_packaging').on('click', function () {
            if (!confirm(lpcSettingsPackaging.messageDeleteMultiple)) {
                return;
            }

            const $packagingRows = $('.lpc_packaging_row input[type="checkbox"]:checked').closest('tr');
            deletePackagings($packagingRows);
        });
    }

    function setPriorityUp() {
        $('.packaging_priority_up').on('click', function () {
            const $row = $(this).closest('tr');
            const $prev = $row.prev();
            if ($prev.length) {
                switchPriority($row.attr('data-lpc-packaging'), $prev.attr('data-lpc-packaging'));
                $row.insertBefore($prev);
            }

            updatePriorityArrows();
        });
    }

    function setPriorityDown() {
        $('.packaging_priority_down').on('click', function () {
            const $row = $(this).closest('tr');
            const $next = $row.next();
            if ($next.length) {
                switchPriority($row.attr('data-lpc-packaging'), $next.attr('data-lpc-packaging'));
                $row.insertAfter($next);
            }

            updatePriorityArrows();
        });
    }

    function switchPriority(firstPackaging, secondPackaging) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'lpc_switch_packagings',
                firstPackaging: firstPackaging,
                secondPackaging: secondPackaging,
                nonce: $('#lpc_packaging_nonce').val()
            },
            success: function (response) {
                if (response.type === 'error') {
                    alert(response.data.message);
                }
            }
        });
    }

    function handleActions() {
        $('.lpc_packaging_edit').off('click').on('click', function () {
            $('[data-lpc-template="lpc-packaging"]').trigger('click');
            const editedPackaging = JSON.parse($(this).closest('tr').attr('data-lpc-packaging'));
            $('#lpc_new_packaging_option_name_input').val(editedPackaging.name);
            $('#lpc_new_packaging_option_packaging_weight_input').val(editedPackaging.weight);
            $('#lpc_new_packaging_option_width_input').val(editedPackaging.width);
            $('#lpc_new_packaging_option_length_input').val(editedPackaging.length);
            $('#lpc_new_packaging_option_depth_input').val(editedPackaging.depth);
            $('#lpc_new_packaging_option_max_weight_input').val(editedPackaging.max_weight);
            $('#lpc_new_packaging_option_max_products_input').val(editedPackaging.max_products);
            $('#lpc_new_packaging_option_extra_cost_input').val(editedPackaging.extra_cost);
            $('#lpc_new_packaging_identifier').val(editedPackaging.identifier);
        });

        $('.lpc_packaging_delete').off('click').on('click', function () {
            if (!confirm(lpcSettingsPackaging.messageDeleteOne)) {
                return;
            }

            const $packagingRows = $(this).closest('tr');
            deletePackagings($packagingRows);
        });
    }

    function deletePackagings($packagingRows) {
        const identifiers = $packagingRows.map(function () {
            return JSON.parse($(this).attr('data-lpc-packaging')).identifier;
        }).get();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'lpc_delete_packagings',
                identifiers: identifiers,
                nonce: $('#lpc_packaging_nonce').val()
            },
            success: function (response) {
                if (response.type === 'error') {
                    alert(response.data.message);
                }
            }
        });

        $packagingRows.remove();
        updatePriorityArrows();
        updateHeader();
    }

    function handleNewPackage() {
        $(document).on('click', '#lpc_new_packaging_cancellation', function () {
            $(this).closest('.lpc-lib-modal-main').find('.modal-close').trigger('click');

            $('#lpc_new_packaging_option_name_input').val('');
            $('#lpc_new_packaging_option_packaging_weight_input').val('');
            $('#lpc_new_packaging_option_width_input').val('');
            $('#lpc_new_packaging_option_length_input').val('');
            $('#lpc_new_packaging_option_depth_input').val('');
            $('#lpc_new_packaging_option_max_weight_input').val('');
            $('#lpc_new_packaging_option_max_products_input').val('');
            $('#lpc_new_packaging_option_extra_cost_input').val('');
            $('#lpc_new_packaging_identifier').val('');
        });

        $(document).on('click', '#lpc_new_packaging_validation', function () {
            const newPackage = {
                name: $('#lpc_new_packaging_option_name_input').val(),
                weight: $('#lpc_new_packaging_option_packaging_weight_input').val(),
                width: $('#lpc_new_packaging_option_width_input').val(),
                length: $('#lpc_new_packaging_option_length_input').val(),
                depth: $('#lpc_new_packaging_option_depth_input').val()
            };

            // if one of the new package fields is empty, show error message
            if (Object.values(newPackage).some(field => field === '')) {
                alert(lpcSettingsPackaging.messageMissingField);
                return;
            }

            // Check on dimensions (sum must be lower than 120cm to be handled by machines)
            if (parseFloat(newPackage.width)
                + parseFloat(newPackage.length)
                + parseFloat(newPackage.depth)
                > 120
                && !confirm(lpcSettingsPackaging.messageDimensions)) {
                return;
            }

            newPackage.max_weight = $('#lpc_new_packaging_option_max_weight_input').val();
            newPackage.max_products = $('#lpc_new_packaging_option_max_products_input').val();
            newPackage.extra_cost = $('#lpc_new_packaging_option_extra_cost_input').val();

            const $saveButton = $('#lpc_new_packaging_validation');
            $saveButton.prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'lpc_new_packaging',
                    packageData: newPackage,
                    identifier: $('#lpc_new_packaging_identifier').val(),
                    nonce: $('#lpc_packaging_nonce').val()
                },
                success: function (response) {
                    if (response.type === 'error') {
                        $saveButton.prop('disabled', false);
                        alert(response.data.message);
                    } else {
                        $('.woocommerce-save-button').trigger('click');
                    }
                }
            });
        });
    }

    function updatePriorityArrows() {
        $('.packaging_priority_up, .packaging_priority_down').prop('disabled', false);
        $('#lpc_packaging_weight tbody tr:first-of-type .packaging_priority_up').prop('disabled', true);
        $('#lpc_packaging_weight tbody tr:last-of-type .packaging_priority_down').prop('disabled', true);
    }

    function updateHeader() {
        const $header = $('#lpc_packaging_weight thead');
        const $deleteButton = $('#lpc_delete_packaging');
        if ($('.lpc_packaging_row').length === 0) {
            $header.hide();
            $deleteButton.hide();
        } else {
            $header.show();
            $deleteButton.show();
        }
    }
});
