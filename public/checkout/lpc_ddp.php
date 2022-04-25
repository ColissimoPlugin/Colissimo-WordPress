<?php

defined('ABSPATH') || die('Restricted Access');

class LpcDdp extends LpcComponent {
    public function init() {
        add_action('woocommerce_after_shipping_rate', [$this, 'addDdpDescription']);
        add_action('woocommerce_checkout_process', [$this, 'preventCheckoutProcess']);
    }

    public function addDdpDescription($method, $index = 0) {
        // To get the currently selected shipping method: WC()->session->get('chosen_shipping_methods', [])
        if (false === strpos($method->get_method_id(), '_ddp')) {
            return;
        }

        $description = LpcHelper::get_option('lpc_extracost_msg');
        if (!empty($description)) {
            echo LpcHelper::renderPartial('checkout' . DS . 'ddp_description.php', ['description' => $description]);
        }
    }

    public function preventCheckoutProcess() {
        $shippingMethod = WC()->session->get('chosen_shipping_methods');
        $needShipping   = WC()->cart->needs_shipping();

        if (!$needShipping || empty($shippingMethod)) {
            return;
        }

        $ddp = false;
        foreach ($shippingMethod as $oneMethod) {
            if (strpos($oneMethod, LpcExpertDDP::ID) !== false || strpos($oneMethod, LpcSignDDP::ID) !== false) {
                $ddp = true;
            }
        }
        if (!$ddp) {
            return;
        }

        $this->checkPhone();
        $this->checkState();
    }

    private function checkPhone() {
        $customerPhoneNumber = isset($_REQUEST['billing_phone']) ? sanitize_text_field(wp_unslash($_REQUEST['billing_phone'])) : '';

        // Even if we don't have a shipping phone natively in WooCommerce, we can check if a shipping phone exist if the billing one is empty
        // because a plugin or a theme can add it
        if (empty($customerPhoneNumber) && isset($_REQUEST['shipping_phone']) && !empty($_REQUEST['shipping_phone'])) {
            $customerPhoneNumber = sanitize_text_field(wp_unslash($_REQUEST['shipping_phone']));
        }

        $customerPhoneNumber = str_replace(' ', '', $customerPhoneNumber);

        if (empty($customerPhoneNumber)) {
            throw new Exception(__('Please define a mobile phone number for SMS notification tracking', 'wc_colissimo'));
        }
    }

    private function checkState() {
        $country = isset($_REQUEST['shipping_country']) ? sanitize_text_field(wp_unslash($_REQUEST['shipping_country'])) : '';
        $state   = isset($_REQUEST['shipping_state']) ? sanitize_text_field(wp_unslash($_REQUEST['shipping_state'])) : '';

        if (in_array($country, LpcLabelGenerationPayload::COUNTRIES_NEEDING_STATE) && empty($state)) {
            throw new Exception(__('Please define a state / province', 'wc_colissimo'));
        }
    }
}
