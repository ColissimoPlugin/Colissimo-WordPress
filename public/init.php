<?php

defined('ABSPATH') || die('Restricted Access');

require_once LPC_PUBLIC . 'pickup' . DS . 'lpc_pickup_widget.php';
require_once LPC_PUBLIC . 'pickup' . DS . 'lpc_pickup_web_service.php';
require_once LPC_PUBLIC . 'pickup' . DS . 'lpc_pickup_selection.php';
require_once LPC_PUBLIC . 'tracking' . DS . 'lpc_tracking_page.php';
require_once LPC_PUBLIC . 'order' . DS . 'lpc_bal_return.php';
require_once LPC_PUBLIC . 'order' . DS . 'lpc_order_tracking.php';
require_once LPC_PUBLIC . 'checkout' . DS . 'lpc_ddp.php';

class LpcPublicInit {

    public function __construct() {
        LpcRegister::register('pickupSelection', new LpcPickupSelection());
        LpcRegister::register('pickupWebService', new LpcPickupWebService());
        LpcRegister::register('pickupWidget', new LpcPickupWidget());
        LpcRegister::register('trackingPage', new LpcTrackingPage());
        LpcRegister::register('balReturn', new LpcBalReturn());
        LpcRegister::register('orderTracking', new LpcOrderTracking());
        LpcRegister::register('ddp', new LpcDdp());
    }
}
