jQuery(function ($) {
    function init() {
        $('#lpc_download_return_label').on('click', () => {
            const $checkedProducts = $('#lpc_return_table').find('input[type="checkbox"]:checked');

            const selectedProducts = [];
            $checkedProducts.each(function () {
                const $quantityInput = $(this).closest('tr').find('input[data-lpc-product]');
                const productId = $quantityInput.attr('data-lpc-product');
                const quantity = $quantityInput.val();

                if (quantity > 0) {
                    selectedProducts.push({
                        productId: productId,
                        quantity: quantity
                    });
                }
            });

            if (selectedProducts.length === 0) {
                alert(lpc_orders_return.selectProducts);
                return;
            }

            window.location.href = lpc_orders_return.downloadUrlBase + encodeURIComponent(JSON.stringify(selectedProducts));
        });
    }

    init();
});
