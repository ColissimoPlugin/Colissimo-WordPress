<?php

class LpcInwardLabelGenerationEmail extends WC_Email {
    public function __construct() {
        $this->id             = 'lpc_inward_label_generation';
        $this->customer_email = true;
        $this->title          = __('Inward label generated', 'wc_colissimo');
        $this->description    = __('An email is sent to the customer when the inward label is generated, if this option is set up', 'wc_colissimo');
        $this->template_html  = 'lpc_inward_label_generated.php';
        $this->template_plain = 'plain' . DS . 'lpc_inward_label_generated.php';
        $this->template_base  = untrailingslashit(plugin_dir_path(__FILE__)) . DS . 'templates' . DS;
        $this->placeholders   = [
            '{order_date}'   => '',
            '{order_number}' => '',
        ];

        add_action('lpc_inward_label_generated', [$this, 'trigger']);

        parent::__construct();
    }

    public function get_default_subject() {
        return sprintf(__('[%s] Your inward label', 'wc_colissimo'), '{blogname}');
    }

    public function get_default_heading() {
        return __('Inward label generated', 'wc_colissimo');
    }

    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            [
                'order'              => $this->object,
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

    public function trigger(WC_Order $order, $label, $label_filename = 'inward_label') {
        if (!$this->is_enabled()) {
            return false;
        }

        $recipientId = $order->get_customer_id();

        global $wp_locale_switcher;
        $switchedLocale = false;
        if (isset($wp_locale_switcher) && !empty($recipientId)) {
            $switchedLocale = switch_to_user_locale($recipientId);
            load_textdomain('wc_colissimo', LPC_FOLDER . 'languages/wc_colissimo-' . get_locale() . '.mo');
        }

        $this->object                         = $order;
        $this->recipient                      = $order->get_billing_email();
        $this->placeholders['{order_date}']   = wc_format_datetime($this->object->get_date_created());
        $this->placeholders['{order_number}'] = $this->object->get_order_number();

        $label_full_filename = sys_get_temp_dir() . DS . $label_filename . $this->get_extention_from_format();
        $sent                = false;
        try {
            $this->create_attachment($label_full_filename, $label);

            $sent = $this->send(
                $this->get_recipient(),
                $this->get_subject(),
                $this->get_content(),
                $this->get_headers(),
                $label_full_filename
            );
        } catch (Exception $e) {
            LpcLogger::error('Error sending inward label email', ['error' => $e->getMessage()]);
        } finally {
            $this->delete_attachment($label_full_filename);

            if ($switchedLocale) {
                restore_previous_locale();
            }

            return $sent;
        }
    }

    protected function create_attachment($file_name, $content) {
        $file = fopen($file_name, 'w');
        fwrite($file, $content);
        fclose($file);
    }

    protected function delete_attachment($file_name) {
        unlink($file_name);
    }

    protected function get_extention_from_format() {
        $extention = LpcHelper::get_option('lpc_returnLabelFormat', 'PDF_A4_300dpi');

        switch ($extention) {
            case 'ZPL_10x15_203dpi':
            case 'ZPL_10x15_300dpi':
                return '.zpl';
            case 'DPL_10x15_203dpi':
            case 'DPL_10x15_300dpi':
                return '.dpl';
            default:
                return '.pdf';
        }
    }
}
