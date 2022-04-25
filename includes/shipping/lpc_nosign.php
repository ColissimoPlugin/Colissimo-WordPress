<?php

require_once __DIR__ . DS . 'lpc_abstract_shipping.php';

class LpcNoSign extends LpcAbstractShipping {
    const ID = 'lpc_nosign';

    public function __construct($instance_id = 0) {
        $this->id                 = self::ID;
        $this->method_title       = __('Colissimo without signature', 'wc_colissimo');
        $this->method_description = __("A signature won't be necessary on delivery", 'wc_colissimo');

        parent::__construct($instance_id);
    }

    public function freeFromOrderValue() {
        return LpcHelper::get_option('lpc_domiciless_FreeFromOrderValue', null);
    }
}
