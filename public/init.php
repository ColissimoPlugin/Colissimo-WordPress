<?php

defined('ABSPATH') || die('Restricted Access');

require_once LPC_PUBLIC . 'pickup' . DS . 'lpc_pickup_ajax_content.php';
require_once LPC_PUBLIC . 'pickup' . DS . 'lpc_pickup_widget.php';
require_once LPC_PUBLIC . 'tracking' . DS . 'lpc_tracking_page.php';
require_once LPC_PUBLIC . 'order' . DS . 'lpc_order_tracking.php';
require_once LPC_PUBLIC . 'order' . DS . 'lpc_return.php';
require_once LPC_PUBLIC . 'checkout' . DS . 'lpc_checkout.php';
require_once LPC_PUBLIC . 'checkout' . DS . 'lpc_ddp.php';

class LpcPublicInit {
    public function __construct() {
        LpcRegister::register('pickupWidget', new LpcPickupWidget());
        LpcRegister::register('pickupAjaxContent', new LpcPickupAjaxContent());
        LpcRegister::register('trackingPage', new LpcTrackingPage());
        LpcRegister::register('orderTracking', new LpcOrderTracking());
        LpcRegister::register('lpcCheckout', new LpcCheckout());
        LpcRegister::register('ddp', new LpcDdp());
        LpcRegister::register('orderReturn', new LpcReturn());
    }
}
