jQuery(function ($) {
    $('.wc-shipping-zone-add-method').on('click', () => {
        let counter = 0;
        const methodsInterval = setInterval(() => {
            counter++;
            if (counter > 80) {
                clearInterval(methodsInterval);
            }

            const $zoneMethods = $('.wc-shipping-zone-method-selector');
            if ($zoneMethods.length > 0) {
                clearInterval(methodsInterval);
                $zoneMethods.find('#lpc_expert, #lpc_expert_ddp').closest('.wc-shipping-zone-method-input').hide();
            }
        }, 25);
    });
});
