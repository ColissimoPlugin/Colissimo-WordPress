jQuery(function ($) {
    function init() {
        $('#lpc_selectall').on('click', function () {
            $('.lpc_return_checkbox').prop('checked', this.checked);
        });

        $('#lpc_download_return_label').on('click', function () {
            const selectedProducts = getSelectedProducts();

            if (selectedProducts.length === 0) {
                alert($('#lpc_select_products').val());
                return;
            }

            $(this).addClass('disabled');

            $.ajax({
                url: $('#lpc_generate_url').val() + encodeURIComponent(JSON.stringify(selectedProducts)),
                type: 'POST',
                dataType: 'json',
                success: function (response) {
                    if (response.type === 'success') {
                        $('#lpc_return_options').hide();
                        $('#lpc_return_label_confirmation_tracking_number').text(response.trackingNumber);
                        $('#lpc_return_label_confirmation').show();
                        $('#lpc_return_instructions_container').show();

                        window.location.href = $('#lpc_download_url').val() + response.trackingNumber;
                    } else {
                        alert(response.error);
                    }
                }
            });
        });

        $('#lpc_return_bal_button').on('click', () => {
            const selectedProducts = getSelectedProducts();

            if (selectedProducts.length === 0) {
                alert($('#lpc_select_products').val());
                return;
            }

            window.location.href = $('#lpc_bal_url').val() + '&lpc_label_products=' + encodeURIComponent(JSON.stringify(selectedProducts));
        });
    }

    function getSelectedProducts() {
        const $checkedProducts = $('#lpc_return_table').find('input[type="checkbox"]:checked').not('#lpc_selectall');
        const selectedProducts = [];

        $checkedProducts.each(function () {
            const $quantitySelect = $(this).closest('tr').find('select[data-lpc-product]');
            const productId = $quantitySelect.attr('data-lpc-product');
            const quantity = $quantitySelect.val();

            selectedProducts.push({
                productId: productId,
                quantity: quantity
            });
        });

        return selectedProducts;
    }

    init();
});
