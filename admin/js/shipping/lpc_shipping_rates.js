jQuery(function ($) {
    $('#lpc_shipping_rates_add').on('click', function () {
        let $ratesRows = $('.table_rates tr');
        const newRowId = $ratesRows.length;
        let newRateMinWeight = $ratesRows.length > 0 ? $ratesRows.last().find('[name*="max_weight"]').val() : 0;
        let newRateMinPrice = $ratesRows.length > 0 ? $ratesRows.last().find('[name*="max_price"]').val() : 0;

        if (0 === newRateMinWeight.length) {
            newRateMinWeight = 0;
        }
        if (0 === newRateMinPrice.length) {
            newRateMinPrice = 0;
        }

        const newRow = $('#lpc_shipping_grid_row_template')
            .html()
            .replaceAll('__row_id__', newRowId)
            .replace('__min_weight__', newRateMinWeight)
            .replace('__min_price__', newRateMinPrice);

        const $tableContainer = $(this).closest('table').children('tbody');
        $tableContainer.append(newRow);

        const $appendedRow = $tableContainer.children('tr').last();
        if (!$appendedRow.prev().hasClass('alternate')) {
            $appendedRow.addClass('alternate');
        }

        initializeSelectWoo();
    });

    $('#lpc_shipping_discount_add').on('click', function () {
        let newRowId = $('.table_discount tr').length;

        const newRow = $('#lpc_shipping_discount_row_template')
            .html()
            .replaceAll('__row_id__', newRowId);

        const $tableContainer = $(this).closest('table').children('tbody');
        $tableContainer.append(newRow);

        const $appendedRow = $tableContainer.children('tr').last();
        if (!$appendedRow.prev().hasClass('alternate')) {
            $appendedRow.addClass('alternate');
        }

        initializeSelectWoo();
    });

    $('#lpc_shipping_rates_remove').on('click', function () {
        if (confirm(lpcShippingRates.deleteRateConfirmation)) {
            $('.table_rates input:checked').closest('tr').remove();
            $('.table_rates input:checked').prop('checked', false);
        }
    });

    $('#lpc_shipping_discount_remove').on('click', function () {
        if (confirm(lpcShippingRates.deleteDiscountConfirmation)) {
            $('.table_discount input:checked').closest('tr').remove();
            $('.table_discount input:checked').prop('checked', false);
        }
    });

    function initializeSelectWoo() {
        const $shippingClassSelect = $('.lpc__shipping_rates__shipping_class__select');
        const $productCategorySelect = $('.lpc__shipping_rates__product_category__select');
        const $priceGridSelectSelects = $('.lpc__shipping_rates__multiselect');
        $shippingClassSelect.selectWoo();

        $productCategorySelect.selectWoo({
            ajax: {
                url: lpcShippingRates.searchCategoriesAjaxUrl,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        search: params.term,
                        page: params.page || 1
                    };
                },
                processResults: function (data, params) {
                    params.page = params.page || 1;

                    return {
                        results: data.results,
                        pagination: {
                            more: data.more
                        }
                    };
                },
                cache: true
            },
            placeholder: lpcShippingRates.searchCategories,
            minimumInputLength: 3,
            allowClear: true
        });

        $priceGridSelectSelects.on('select2:select', function (e) {
            const newValue = e.params.data.id;
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

        $priceGridSelectSelects.on('select2:unselect', function (e) {
            const values = $(this).val();

            if (values === null || values.length === 0) {
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
        }).success(function (response) {
            response = JSON.parse(response);

            if (response.type === 'error') {
                alert(response.message);
            } else {
                window.onbeforeunload = function () {
                };
                location.reload();
            }
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
