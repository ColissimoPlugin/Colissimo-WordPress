<?php

abstract class LpcPickup extends LpcComponent {
    const WEB_SERVICE = 'web_service';
    const WIDGET = 'widget';

    protected function getMode($methodId, $instanceId) {
        if ('lpc_relay' !== $methodId) {
            return '';
        }

        // Add the pickup selection button only when this shipping method is selected
        $selected       = false;
        $wcSession      = WC()->session;
        $shippingMethod = $wcSession->get('chosen_shipping_methods');
        foreach ($shippingMethod as $oneMethod) {
            if ($oneMethod === $instanceId) {
                $selected = true;
            }
        }

        if (!$selected) {
            return '';
        }

        if ('yes' === LpcHelper::get_option('lpc_prUseWebService', 'no')) {
            return self::WEB_SERVICE;
        } else {
            return self::WIDGET;
        }
    }
}
