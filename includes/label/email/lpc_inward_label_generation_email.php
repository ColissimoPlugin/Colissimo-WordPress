<?php


class LpcInwardLabelGenerationEmail extends WC_Email {
    public function __construct() {
        $this->id             = 'lpc_inward_label_generation';
        $this->title          = __('Inward label generated', 'wc_colissimo');
        $this->description    = __('An email is sent to the customer when the inward label is generated, if this option is set up', 'wc_colissimo');
        $this->customer_email = true;
        $this->heading        = __('Inward label generated', 'wc_colissimo');
        $this->subject        = sprintf(__('[%s] Your inward label', 'wc_colissimo'), '{blogname}');
        $this->template_html  = 'lpc_inward_label_generated.php';
        $this->template_plain = 'plain' . DS . 'lpc_inward_label_generated.php';
        $this->template_base  = untrailingslashit(plugin_dir_path(__FILE__)) . DS . 'templates' . DS;

        add_action('lpc_inward_label_generated', [$this, 'trigger']);

        parent::__construct();
    }

    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            [
                'order'              => $this->object,
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

    public function trigger(WC_Order $order, $label, $label_filename = 'inward_label') {
        if (!$this->is_enabled()) {
            return false;
        }

        $this->object        = $order;
        $label_full_filename = sys_get_temp_dir() . DS . $label_filename . $this->get_extention_from_format();
        $sending             = false;
        try {
            $this->create_attachment($label_full_filename, $label);

            $this->recipient = $order->get_billing_email();
            $sending         = $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $label_full_filename);
        } catch (Exception $e) {
            return false;
        } finally {
            $this->delete_attachment($label_full_filename);

            return $sending;
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
            case 'PDF_A4_300dpi':
                return '.pdf';
            case 'PDF_10x15_300dpi':
                return '.pdf';
            case 'ZPL_10x15_203dpi':
                return '.zpl';
            case 'ZPL_10x15_300dpi':
                return '.zpl';
            case 'DPL_10x15_203dpi':
                return '.dpl';
            case 'DPL_10x15_300dpi':
                return '.dpl';
            default:
                return '.pdf';
        }
    }
}
