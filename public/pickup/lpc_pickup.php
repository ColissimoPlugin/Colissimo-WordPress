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

        if ('widget' === LpcHelper::get_option('lpc_pickup_map_type', 'widget')) {
            return self::WIDGET;
        } else {
            return self::WEB_SERVICE;
        }
    }
}
