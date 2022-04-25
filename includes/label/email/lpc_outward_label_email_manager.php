<?php


class LpcOutwardLabelEmailManager extends LpcComponent {

    const EMAIL_OUTWARD_TRACKING_OPTION = 'lpc_email_outward_tracking';
    const ON_OUTWARD_LABEL_GENERATION_OPTION = 'on_outward_label_generation';
    const ON_BORDEREAU_GENERATION_OPTION = 'on_bordereau_generation';

    public function init() {
        add_action('lpc_outward_label_generated_to_email', [$this, 'send_email']);
    }

    public function generate_outward_label_woocommerce_email($emails) {
        require_once 'lpc_outward_label_generation_email.php';

        $emails['lpc_generate_outward_label'] = new LpcOutwardLabelGenerationEmail();

        return $emails;
    }

    public function send_email($order_data) {
        WC()->mailer();
        $lpcOutwardLabelGenerationEmail = new LpcOutwardLabelGenerationEmail();
        $lpcOutwardLabelGenerationEmail->trigger($order_data['order']);
    }

}
