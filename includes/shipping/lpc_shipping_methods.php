<?php

class LpcShippingMethods extends LpcComponent {

    public function init() {
        add_action(
            'woocommerce_init',
            function () {
                require_once LPC_INCLUDES . 'shipping' . DS . 'lpc_expert.php';
                require_once LPC_INCLUDES . 'shipping' . DS . 'lpc_expert_ddp.php';
                require_once LPC_INCLUDES . 'shipping' . DS . 'lpc_nosign.php';
                require_once LPC_INCLUDES . 'shipping' . DS . 'lpc_relay.php';
                require_once LPC_INCLUDES . 'shipping' . DS . 'lpc_sign.php';
                require_once LPC_INCLUDES . 'shipping' . DS . 'lpc_sign_ddp.php';
            }
        );

        add_action(
            'woocommerce_shipping_init',
            function () {
                require_once LPC_INCLUDES . 'shipping' . DS . 'lpc_expert.php';
                require_once LPC_INCLUDES . 'shipping' . DS . 'lpc_expert_ddp.php';
                require_once LPC_INCLUDES . 'shipping' . DS . 'lpc_nosign.php';
                require_once LPC_INCLUDES . 'shipping' . DS . 'lpc_relay.php';
                require_once LPC_INCLUDES . 'shipping' . DS . 'lpc_sign.php';
                require_once LPC_INCLUDES . 'shipping' . DS . 'lpc_sign_ddp.php';
            }
        );

        add_filter(
            'woocommerce_shipping_methods',
            function ($shippingMethods) {
                if (class_exists('LpcExpert')) {
                    $shippingMethods[LpcExpert::ID] = LpcExpert::class;
                } else {
                    $shippingMethods['lpc_expert'] = 'LpcExpert';
                }

                if (class_exists('LpcExpertDDP')) {
                    $shippingMethods[LpcExpertDDP::ID] = LpcExpertDDP::class;
                } else {
                    $shippingMethods['lpc_expert_ddp'] = 'LpcExpertDDP';
                }

                if (class_exists('LpcNoSign')) {
                    $shippingMethods[LpcNoSign::ID] = LpcNoSign::class;
                } else {
                    $shippingMethods['lpc_nosign'] = 'LpcNoSign';
                }

                if (class_exists('LpcRelay')) {
                    $shippingMethods[LpcRelay::ID] = LpcRelay::class;
                } else {
                    $shippingMethods['lpc_relay'] = 'LpcRelay';
                }

                if (class_exists('LpcSign')) {
                    $shippingMethods[LpcSign::ID] = LpcSign::class;
                } else {
                    $shippingMethods['lpc_sign'] = 'LpcSign';
                }

                if (class_exists('LpcSignDDP')) {
                    $shippingMethods[LpcSignDDP::ID] = LpcSignDDP::class;
                } else {
                    $shippingMethods['lpc_sign_ddp'] = 'LpcSignDDP';
                }

                return $shippingMethods;
            }
        );

        add_filter('woocommerce_cart_shipping_method_full_label', [$this, 'addShippingIcon'], 10, 2);
    }

    public function getAllShippingMethods() {
        // can't use ::ID here because WC may not yet be defined
        return [
            'lpc_expert',
            'lpc_expert_ddp',
            'lpc_nosign',
            'lpc_relay',
            'lpc_sign',
            'lpc_sign_ddp',
        ];
    }

    public function getAllShippingMethodsWithName() {
        // can't use ::ID here because WC may not yet be defined
        return [
            'lpc_expert'     => __('Colissimo International', 'wc_colissimo'),
            'lpc_expert_ddp' => __('Colissimo International - DDP Option', 'wc_colissimo'),
            'lpc_nosign'     => __('Colissimo without signature', 'wc_colissimo'),
            'lpc_relay'      => __('Colissimo relay', 'wc_colissimo'),
            'lpc_sign'       => __('Colissimo with signature', 'wc_colissimo'),
            'lpc_sign_ddp'   => __('Colissimo with signature - DDP Option', 'wc_colissimo'),
        ];
    }

    public function getAllColissimoShippingMethodsOfOrder(WC_Order $order) {
        $shipping_methods  = $order->get_shipping_methods();
        $shippingMethodIds = array_map(
            function (WC_Order_item_Shipping $v) {
                return ($v->get_method_id());
            },
            $shipping_methods
        );

        return array_intersect($this->getAllShippingMethods(), $shippingMethodIds);
    }

    public function getColissimoShippingMethodOfOrder(WC_Order $order) {
        $shippingMethod = $this->getAllColissimoShippingMethodsOfOrder($order);

        return reset($shippingMethod);
    }

    public function addShippingIcon($label, $method) {
        $img = '';
        if ('yes' === WC_Admin_Settings::get_option('display_logo') && in_array($method->get_method_id(), $this->getAllShippingMethods())) {
            $url   = plugins_url('/images/colissimo_icon.png', LPC_INCLUDES . 'init.php');
            $class = 'lpc_shipping_icon lpc_shipping_icon_' . $method->get_method_id();
            $style = 'max-width: 40px; display:inline; vertical-align: middle;';
            $img   = '<img src="' . $url . '" style="' . $style . '" class="' . $class . '"> ';
        }

        return $img . $label;
    }

    public function moveAlwaysFreeOption() {
        global $wpdb;
        $globalMethods = [
            'lpc_nosign'     => LpcHelper::get_option('lpc_domiciless_IsAlwaysFree', 'no'),
            'lpc_sign'       => LpcHelper::get_option('lpc_domicileas_IsAlwaysFree', 'no'),
            'lpc_sign_ddp'   => LpcHelper::get_option('lpc_domicileas_IsAlwaysFree', 'no'),
            'lpc_expert'     => LpcHelper::get_option('lpc_expert_IsAlwaysFree', 'no'),
            'lpc_expert_ddp' => LpcHelper::get_option('lpc_expert_IsAlwaysFree', 'no'),
            'lpc_relay'      => LpcHelper::get_option('lpc_relay_IsAlwaysFree', 'no'),
        ];
        $tableName     = $wpdb->prefix . 'woocommerce_shipping_zone_methods';
        // phpcs:disable
        $queryGetShippingMethod = "SELECT `instance_id`, `method_id` FROM " . $tableName . " WHERE `method_id` LIKE 'lpc_%'";
        $shippingMethods        = $wpdb->get_results($queryGetShippingMethod);
        // phpcs:enable
        foreach ($shippingMethods as $shippingMethod) {
            $optionName = 'woocommerce_' . $shippingMethod->method_id . '_' . $shippingMethod->instance_id . '_settings';
            $option     = LpcHelper::get_option($optionName, []);
            if ('no' !== $globalMethods[$shippingMethod->method_id]) {
                if (!empty($option)) {
                    $option['always_free'] = $globalMethods[$shippingMethod->method_id];
                    update_option($optionName, $option);
                } else {
                    $option['always_free'] = $globalMethods[$shippingMethod->method_id];
                    add_option($optionName, $option);
                }
            }
        }
        delete_option('lpc_domiciless_IsAlwaysFree');
        delete_option('lpc_domicileas_IsAlwaysFree');
        delete_option('lpc_expert_IsAlwaysFree');
        delete_option('lpc_relay_IsAlwaysFree');
    }
}
