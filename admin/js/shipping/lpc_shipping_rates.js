jQuery(function ($) {
    $('#lpc_shipping_rates_add').click(function () {
        const $ratesRows = $('.table_rates tr');
        const newRowId = $ratesRows.length;
        const shippingClassesOptions = $('#lpc_shipping_classes_example').html();
        let newRateMinWeight = $ratesRows.length > 0 ? $ratesRows.last().find('[name*="max_weight"]').val() : 0;
        let newRateMinPrice = $ratesRows.length > 0 ? $ratesRows.last().find('[name*="max_price"]').val() : 0;

        if (0 === newRateMinWeight.length) {
            newRateMinWeight = 0;
        }
        if (0 === newRateMinPrice.length) {
            newRateMinPrice = 0;
        }

        let newRow = $('<tr>')
            .append($('<td class="check-column"><input type="checkbox" /></td>'))
            .append($('<td style="text-align: center"><input type="number" class="input-number regular-input" step="any" min="0" value="'
                      + newRateMinWeight
                      + '" name="shipping_rates['
                      + newRowId
                      + '][min_weight]"/></td>'))
            .append($('<td style="text-align: center"><input type="number" class="input-number regular-input" step="any" min="0" name="shipping_rates['
                      + newRowId
                      + '][max_weight]"/></td>'))
            .append($('<td style="text-align: center"><input type="number" class="input-number regular-input" step="any" min="0" value="'
                      + newRateMinPrice
                      + '" name="shipping_rates['
                      + newRowId
                      + '][min_price]"/></td>'))
            .append($('<td style="text-align: center"><input type="number" class="input-number regular-input" step="any" min="0" name="shipping_rates['
                      + newRowId
                      + '][max_price]"/></td>'))
            .append($(
                '<td style="text-align: center"><select multiple="multiple" class="lpc__shipping_rates__shipping_class__select" style="width: auto; max-width: 10rem" required name="shipping_rates['
                + newRowId
                + '][shipping_class][]">'
                + shippingClassesOptions
                + '</select></td>'))
            .append($('<td style="text-align: center"><input type="number" class="input-number regular-input" step="any" min="0" required name="shipping_rates['
                      + newRowId
                      + '][price]"/></td>'));

        $(this).closest('table').children('tbody').append(newRow);

        if (!newRow.prev().hasClass('alternate')) {
            newRow.addClass('alternate');
        }

        initializeSelectWoo();
    });

    $('#lpc_shipping_discount_add').click(function () {
        let newRowId = $('.table_discount tr').length;
        let newRow = $('<tr>')
            .append($('<td class="check-column"><input type="checkbox" /></td>'))
            .append($(
                '<td style="text-align: center"><input type="number" class="input-number regular-input" step="any" min="0" required name="shipping_discount['
                + newRowId
                + '][nb_product]"/></td>'))
            .append($(
                '<td style="text-align: center"><input max="100" type="number" class="input-number regular-input" step="any" min="0" required name="shipping_discount['
                + newRowId
                + '][percentage]"/></td>'));

        $(this).closest('table').children('tbody').append(newRow);

        if (!newRow.prev().hasClass('alternate')) {
            newRow.addClass('alternate');
        }

        initializeSelectWoo();
    });

    $('#lpc_shipping_rates_remove').click(function () {
        if (confirm(lpcShippingRates.deleteRateConfirmation)) {
            $('.table_rates input:checked').closest('tr').remove();
            $('.table_rates input:checked').prop('checked', false);
        }
    });

    $('#lpc_shipping_discount_remove').click(function () {
        if (confirm(lpcShippingRates.deleteDiscountConfirmation)) {
            $('.table_discount input:checked').closest('tr').remove();
            $('.table_discount input:checked').prop('checked', false);
        }
    });

    function initializeSelectWoo() {
        let $shippingClassSelect = $('.lpc__shipping_rates__shipping_class__select');
        $shippingClassSelect.selectWoo();

        $shippingClassSelect.on('select2:select', function (e) {
            let newValue = e.params.data.id;
            let values = $(this).val();

            if (newValue === 'all') {
                $(this).val(['all']).trigger('change');
            } else {
                if ($.inArray('all', values) !== -1) {
                    values.splice(values.indexOf('all'), 1);
                    $(this).val(values).trigger('change');
                }
            }
        });

        $shippingClassSelect.on('select2:unselect', function (e) {
            let values = $(this).val();

            if (values === null) {
                $(this).val(['all']).trigger('change');
            }
        });
    }

    $('#lpc_shipping_rates_import_button').on('click', function () {
        const inputFile = document.getElementById('lpc_shipping_rates_import');

        if (!inputFile.files.length) {
            alert(lpcShippingRates.pleaseSelectFile);
            return;
        }

        const url = this.getAttribute('lpc-ajax-url');

        const formData = new FormData();
        formData.append('lpc_shipping_rates_import', inputFile.files[0]);

        $.ajax({
            type: 'POST',
            url: url,
            data: formData,
            processData: false,
            contentType: false
        }).success(function () {
            window.onbeforeunload = function () {
                // blank function do nothing and
            };
            location.reload();
        }).error(function () {
            alert(lpcShippingRates.errorWhileImporting);
        });
    });

    $('#lpc_shipping_rates_import_default_button').on('click', function () {
        if (!confirm(lpcShippingRates.defaultPricesConfirmation)) {
            return;
        }

        const url = this.getAttribute('data-lpc-ajax-url');

        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            success: function (response) {
                if (response.type === 'error') {
                    alert(response.message);
                } else {
                    $('.table_rates tr').remove();

                    for (let i = 0; i < response.data.prices.length; i++) {
                        $('#lpc_shipping_rates_add').trigger('click');

                        $('[name="shipping_rates[' + i + '][min_weight]"]').val(response.data.prices[i].weight_min);
                        $('[name="shipping_rates[' + i + '][max_weight]"]').val(response.data.prices[i].weight_max);
                        $('[name="shipping_rates[' + i + '][price]"]').val(response.data.prices[i].price);
                    }
                }
            }
        });
    });

    initializeSelectWoo();
});
