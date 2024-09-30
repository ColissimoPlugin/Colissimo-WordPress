<?php

class LpcOutwardLabelGenerationEmail extends WC_Email {
    public function __construct() {
        $this->id             = 'lpc_outward_label_generation';
        $this->customer_email = true;
        $this->title          = __('Colissimo order tracking', 'wc_colissimo');
        $this->description    = __('An email is sent to the customer when the outward label or delivery docket is generated, depending of the option set in Colissimo Official configuration',
                                   'wc_colissimo');
        $this->template_html  = 'lpc_outward_label_generated.php';
        $this->template_plain = 'plain' . DS . 'lpc_outward_label_generated.php';
        $this->template_base  = untrailingslashit(plugin_dir_path(__FILE__)) . DS . 'templates' . DS;
        $this->placeholders   = [
            '{order_date}'   => '',
            '{order_number}' => '',
        ];

        add_action('lpc_outward_label_generated', [$this, 'trigger']);

        parent::__construct();
    }

    public function get_default_subject() {
        return sprintf(__('[%s] Your order status changed', 'wc_colissimo'), '{blogname}');
    }

    public function get_default_heading() {
        return __('Your order status changed', 'wc_colissimo');
    }

    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            [
                'order'              => $this->object,
                'tracking_link'      => $this->getTrackingLink($this->object->get_id()),
                'email_heading'      => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'      => false,
                'plain_text'         => false,
                'email'              => $this,
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
                'email_heading'      => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'      => false,
                'plain_text'         => true,
                'email'              => $this,
            ],
            '',
            $this->template_base
        );
    }

    public function trigger(WC_Order $order) {
        if (!$this->is_enabled()) {
            return;
        }

        $this->recipient = $order->get_billing_email();
        $recipientId     = $order->get_customer_id();

        global $wp_locale_switcher;
        $switchedLocale = false;
        if (isset($wp_locale_switcher) && !empty($recipientId)) {
            $switchedLocale = switch_to_user_locale($recipientId);
            load_textdomain('wc_colissimo', LPC_FOLDER . 'languages/wc_colissimo-' . get_locale() . '.mo');
        }

        $this->object                         = $order;
        $this->placeholders['{order_date}']   = wc_format_datetime($this->object->get_date_created());
        $this->placeholders['{order_number}'] = $this->object->get_order_number();

        $this->send(
            $this->get_recipient(),
            $this->get_subject(),
            $this->get_content(),
            $this->get_headers(),
            $this->get_attachments()
        );

        if ($switchedLocale) {
            restore_previous_locale();
        }
    }

    protected function getTrackingLink($orderId) {
        if (LpcHelper::get_option('lpc_email_tracking_link', 'website_tracking_page') === 'website_tracking_page') {
            return get_site_url() . LpcRegister::get('unifiedTrackingApi')->getTrackingPageUrlForOrder($orderId);
        } else {
            $order = wc_get_order($orderId);
            if (empty($order)) {
                return get_site_url() . LpcRegister::get('unifiedTrackingApi')->getTrackingPageUrlForOrder($orderId);
            }

            $trackingNumber = $order->get_meta('lpc_outward_parcel_number');

            return str_replace(
                '{lpc_tracking_number}',
                $trackingNumber,
                LpcAbstractShipping::LPC_LAPOSTE_TRACKING_LINK
            );
        }
    }
}
