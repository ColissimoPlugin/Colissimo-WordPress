<?php

require_once LPC_PUBLIC . 'pickup' . DS . 'lpc_pickup_selection.php';

class LpcPickupRelayPointOnOrder extends LpcComponent {
    public function init() {
        add_action('woocommerce_after_order_itemmeta', [$this, 'displayRelayPointInfo'], 10, 2);
    }

    public function displayRelayPointInfo($id, WC_Order_Item $item) {
        $itemData = @$item->get_data();
        if (!empty($itemData['method_id']) && LpcRelay::ID === $itemData['method_id']) {
            $orderId = $item->get_order_id();
            $order   = wc_get_order($orderId);
            if (!empty($order)) {
                echo LpcHelper::renderPartial(
                    'pickup/relay_point_info_on_order.php',
                    [
                        'pickUpLocationType' => $order->get_meta(LpcPickupSelection::PICKUP_PRODUCT_CODE_META_KEY),
                        'pickUpLocationId' => $order->get_meta(LpcPickupSelection::PICKUP_LOCATION_ID_META_KEY),
                        'pickUpLocationLabel' => $order->get_meta(LpcPickupSelection::PICKUP_LOCATION_LABEL_META_KEY),
                    ]
                );
            }
        }
    }
}
