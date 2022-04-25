<?php

require_once __DIR__ . DS . 'lpc_abstract_shipping.php';

class LpcRelay extends LpcAbstractShipping {
    const ID = 'lpc_relay';

    public function __construct($instance_id = 0) {
        $this->id                 = self::ID;
        $this->method_title       = __('Colissimo relay', 'wc_colissimo');
        $this->method_description = __('Delivery in a relay', 'wc_colissimo');

        parent::__construct($instance_id);
    }

    public function freeFromOrderValue() {
        return LpcHelper::get_option('lpc_relay_FreeFromOrderValue', null);
    }
}
