<?php

class LpcRegisterWCEmail extends LpcComponent {

    protected $lpc_inward_label_email_manager;
    protected $lpc_outward_label_email_manager;

    public function __construct() {
        $this->lpc_inward_label_email_manager  = LpcRegister::get('lpcInwardLabelEmailManager');
        $this->lpc_outward_label_email_manager = LpcRegister::get('lpcOutwardLabelEmailManager');
    }

    public function getDependencies() {
        return ['lpcInwardLabelEmailManager', 'lpcOutwardLabelEmailManager'];
    }

    public function init() {
        add_action('woocommerce_email_classes', [$this, 'generate_inward_label_woocommerce_email']);
        add_action('woocommerce_email_classes', [$this, 'generate_outward_label_woocommerce_email']);
    }

    public function generate_inward_label_woocommerce_email($emails) {
        return $this->lpc_inward_label_email_manager->generate_inward_label_woocommerce_email($emails);
    }

    public function generate_outward_label_woocommerce_email($emails) {
        return $this->lpc_outward_label_email_manager->generate_outward_label_woocommerce_email($emails);
    }
}
