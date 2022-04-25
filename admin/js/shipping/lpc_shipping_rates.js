jQuery(function ($) {
    $('#lpc_shipping_rates_add').click(function () {
        let newRowId = $('tr').length;
        let shippingClassesOptions = $('#lpc_shipping_classes_example').html();
        let newRow = $('<tr>')
            .append($('<td class="check-column"><input type="checkbox" /></td>'))
            .append($(
                '<td style="text-align: center"><input type="number" class="input-number regular-input" step="any" min="0" required name="shipping_rates['
                + newRowId
                + '][min_weight]"/></td>'))
            .append($(
                '<td style="text-align: center"><input type="number" class="input-number regular-input" step="any" min="0" required name="shipping_rates['
                + newRowId
                + '][max_weight]"/></td>'))
            .append($(
                '<td style="text-align: center"><input type="number" class="input-number regular-input" step="any" min="0" required name="shipping_rates['
                + newRowId
                + '][min_price]"/></td>'))
            .append($(
                '<td style="text-align: center"><input type="number" class="input-number regular-input" step="any" min="0" required name="shipping_rates['
                + newRowId
                + '][max_price]"/></td>'))
            .append($(
                '<td style="text-align: center"><select multiple="multiple" class="lpc__shipping_rates__shipping_class__select" style="width: auto; max-width: 10rem" required name="shipping_rates['
                + newRowId
                + '][shipping_class][]">'
                + shippingClassesOptions
                + '</select></td>'))
            .append($(
                '<td style="text-align: center"><input type="number" class="input-number regular-input" step="any" min="0" required name="shipping_rates['
                + newRowId
                + '][price]"/></td>'));

        $(this).closest('table').children('tbody').append(newRow);

        if (!newRow.prev().hasClass('alternate')) {
            newRow.addClass('alternate');
        }

        initializeSelectWoo();
    });

    $('#lpc_shipping_rates_remove').click(function () {
        if (confirm(window.lpc_i18n_delete_selected_rate)) {
            $('table.shippingrows tbody input:checked').closest('tr').remove();
            $('table.shippingrows input:checked').prop('checked', false);
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

    initializeSelectWoo();
});
