<?php

require_once __DIR__ . DS . 'lpc_abstract_shipping.php';

class LpcSignDDP extends LpcAbstractShipping {
    const ID = 'lpc_sign_ddp';

    public function __construct($instance_id = 0) {
        $this->id                 = self::ID;
        $this->method_title       = __('Colissimo with signature - DDP option', 'wc_colissimo');
        $this->method_description = __('A signature will be necessary on delivery', 'wc_colissimo');

        parent::__construct($instance_id);
    }

    public function freeFromOrderValue() {
        return LpcHelper::get_option('lpc_domicileas_FreeFromOrderValue', null);
    }
}
