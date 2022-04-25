<?php

require_once LPC_PUBLIC . 'pickup' . DS . 'lpc_pickup_selection.php';

class LpcPickupRelayPointOnOrder extends LpcComponent {
    public function init() {
        add_action('woocommerce_after_order_itemmeta', [$this, 'displayRelayPointInfo'], 10, 2);
    }

    public function displayRelayPointInfo($id, WC_Order_Item $item) {
        $methodId = @$item->get_data()['method_id'];
        if (LpcRelay::ID === $methodId) {
            $orderId = $item->get_order_id();
            echo LpcHelper::renderPartial(
                'pickup/relay_point_info_on_order.php',
                [
                    'pickUpLocationType' => get_post_meta(
                        $orderId,
                        LpcPickupSelection::PICKUP_PRODUCT_CODE_META_KEY,
                        true
                    ),
                    'pickUpLocationId' => get_post_meta(
                        $orderId,
                        LpcPickupSelection::PICKUP_LOCATION_ID_META_KEY,
                        true
                    ),
                    'pickUpLocationLabel' => get_post_meta(
                        $orderId,
                        LpcPickupSelection::PICKUP_LOCATION_LABEL_META_KEY,
                        true
                    ),
                ]
            );
        }
    }
}
