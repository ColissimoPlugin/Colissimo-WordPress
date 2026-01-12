jQuery(function ($) {
    function lpcProductAttribute() {
        $('body').on('click', function () {
            const $select = $('select[data-taxonomy="pa_' + lpcProduct.hazmat_attribute + '"]');
            if ($select.length === 0) {
                return;
            }

            $select.off('change', selectChangeHandler).on('change', selectChangeHandler);

            const container = $select.parent();
            container.find('button.select_all_attributes').hide();
            container.find('button.select_no_attributes').hide();
            container.find('button.add_new_attribute').hide();
            $select.removeAttr('multiple');
        });
    }

    function selectChangeHandler() {
        const $select = $(this);

        $select.find('option:selected').not(':first').each(function () {
            $(this).prop('selected', false);
        });

        const lastSelected = $select.val();
        $select.val(lastSelected);

        // Get current product weight
        const productWeightInGrams = parseFloat($('#_weight').val().replace(',', '.')) * lpcProduct.weight_to_grams_factor;
        const maxWeight = lpcProduct.hazmat_categories[lastSelected] || 0;

        if (maxWeight > 0 && productWeightInGrams > maxWeight) {
            alert(lpcProduct.weight_limit_exceeded.replace('%1$s', productWeightInGrams).replace('%2$s', maxWeight));
        }
    }

    lpcProductAttribute();
});
