<?php


class LpcOutwardLabelGenerationEmail extends WC_Email {
    public function __construct() {
        $this->id             = 'lpc_outward_label_generation';
        $this->title          = __('Colissimo order tracking', 'wc_colissimo');
        $this->description    = __('An email is sent to the customer when the outward label or delivery docket is generated, depending of the option set in Colissimo Official configuration',
                                   'wc_colissimo');
        $this->customer_email = true;
        $this->heading        = __('Your order status changed', 'wc_colissimo');
        $this->subject        = sprintf(__('[%s] Your order status changed', 'wc_colissimo'), '{blogname}');
        $this->template_html  = 'lpc_outward_label_generated.php';
        $this->template_plain = 'plain' . DS . 'lpc_outward_label_generated.php';
        $this->template_base  = untrailingslashit(plugin_dir_path(__FILE__)) . DS . 'templates' . DS;

        add_action('lpc_outward_label_generated', [$this, 'trigger']);

        parent::__construct();
    }

    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            [
                'order'              => $this->object,
                'tracking_link'      => $this->getTrackingLink($this->object->get_id()),
                'email_heading'      => $this->heading,
                'sent_to_admin'      => false,
                'plain_text'         => false,
                'email'              => $this,
                'additional_content' => $this->get_additional_content(),
            ],
            '',
            $this->template_base
        );
    }

    public function get_content_plain() {
        return wc_get_template_html(
            $this->template_plain,
            [
                'order'              => $this->object,
                'tracking_link'      => $this->getTrackingLink($this->object->get_id()),
                'email_heading'      => $this->heading,
                'sent_to_admin'      => false,
                'plain_text'         => true,
                'email'              => $this,
                'additional_content' => $this->get_additional_content(),
            ],
            '',
            $this->template_base
        );
    }

    public function trigger(WC_Order $order) {
        if ($this->is_enabled()) {
            $this->object    = $order;
            $this->recipient = $order->get_billing_email();
            $sending         = $this->send(
                $this->get_recipient(),
                $this->get_subject(),
                $this->get_content(),
                $this->get_headers(),
                $this->get_attachments()
            );

            return $sending;
        }
    }

    protected function getTrackingLink($orderId) {
        if (LpcHelper::get_option('lpc_email_tracking_link', 'website_tracking_page') === 'website_tracking_page') {
            return get_site_url() . LpcRegister::get('unifiedTrackingApi')->getTrackingPageUrlForOrder($orderId);
        } else {
            $trackingNumber = get_post_meta($orderId, 'lpc_outward_parcel_number', true);

            return str_replace(
                '{lpc_tracking_number}',
                $trackingNumber,
                LpcAbstractShipping::LPC_LAPOSTE_TRACKING_LINK
            );
        }
    }
}
