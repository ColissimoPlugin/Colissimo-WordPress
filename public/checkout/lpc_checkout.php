<?php

defined('ABSPATH') || die('Restricted Access');

class LpcCheckout extends LpcComponent {
    /** @var LpcCheckoutApi */
    protected $checkoutApi;

    public function __construct(
        ?LpcCheckoutApi $checkoutApi = null
    ) {
        $this->checkoutApi = LpcRegister::get('checkoutApi', $checkoutApi);
    }

    public function getDependencies(): array {
        return ['checkoutApi'];
    }

    public function init() {
        add_action('wp_enqueue_scripts', [$this, 'includeScripts']);
        add_action('wp_ajax_lpc_checkout_delivery_date', [$this, 'getDeliveryDate']);
        add_action('wp_ajax_nopriv_lpc_checkout_delivery_date', [$this, 'getDeliveryDate']);
    }

    public function includeScripts() {
        if (is_checkout() || has_block('woocommerce/checkout')) {
            wp_register_script('lpc_checkout', null, [], LPC_VERSION);
            wp_enqueue_script('lpc_checkout');
            wp_add_inline_script('lpc_checkout', 'window.lpc_baseAjaxUrl = "' . admin_url('admin-ajax.php') . '"', 'before');
        }
    }

    public function getDeliveryDate() {
        if ('yes' !== LpcHelper::get_option('lpc_display_shipping_date')) {
            LpcHelper::endAjax(false);
        }

        $postCode = LpcHelper::getVar('postcode');
        if (empty($postCode) || strlen($postCode) < 5) {
            LpcHelper::endAjax(false);
        }

        $deliveryDate = $this->checkoutApi->getDeliveryDate($postCode);
        if (empty($deliveryDate)) {
            LpcHelper::endAjax(false);
        }

        LpcHelper::endAjax(true, ['deliveryDateText' => $deliveryDate]);
    }
}
