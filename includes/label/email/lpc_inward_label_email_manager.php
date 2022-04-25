<?php

class LpcInwardLabelEmailManager extends LpcComponent {

    protected $ajaxDispatcher;

    const AJAX_TASK_NAME = 'inward_label_emailing';
    const TRACKING_NUMBER_VAR_NAME = 'tracking_number';
    const EMAIL_RETURN_LABEL_OPTION = 'lpc_email_return_label';
    const REDIRECTION_VAR_NAME = 'lpc_redirection';

    protected $mailer;

    /** @var LpcInwardLabelDb */
    protected $inwardLabelDb;

    public function __construct(LpcInwardLabelDb $inwardLabelDb = null) {
        $this->ajaxDispatcher = LpcRegister::get('ajaxDispatcher');
        $this->inwardLabelDb  = LpcRegister::get('inwardLabelDb', $inwardLabelDb);
    }

    public function getDependencies() {
        return ['ajaxDispatcher', 'inwardLabelDb'];
    }

    public function send_email($order_data) {
        $lpcInwardLabelGenerationEmail = WC()->mailer()->emails['lpc_generate_inward_label'];
        if (isset($order_data['label_filename'])) {
            $lpcInwardLabelGenerationEmail->trigger($order_data['order'], $order_data['label'], $order_data['label_filename']);
        } else {
            $lpcInwardLabelGenerationEmail->trigger($order_data['order'], $order_data['label']);
        }
    }

    public function generate_inward_label_woocommerce_email($emails) {
        require_once 'lpc_inward_label_generation_email.php';
        $emails['lpc_generate_inward_label'] = new LpcInwardLabelGenerationEmail();

        return $emails;
    }

    public function init() {
        add_action('lpc_inward_label_generated_to_email', [$this, 'send_email']);
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control']);
    }

    public function control() {
        $trackingNumber = LpcHelper::getVar(self::TRACKING_NUMBER_VAR_NAME);
        $redirection    = LpcHelper::getVar(self::REDIRECTION_VAR_NAME);

        switch ($redirection) {
            case LpcLabelQueries::REDIRECTION_WOO_ORDER_EDIT_PAGE:
                $orderId        = $this->inwardLabelDb->getOrderIdByTrackingNumber($trackingNumber);
                $urlRedirection = get_edit_post_link($orderId, '');
                break;
            case LpcLabelQueries::REDIRECTION_COLISSIMO_ORDERS_LISTING:
            default:
                $urlRedirection = admin_url('admin.php?page=wc_colissimo_view');
                break;
        }

        if (!current_user_can('lpc_send_emails')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => 'unauthorized access to inward label sending',
                ]
            );
        }

        try {
            WC()->mailer();
            $lpcInwardLabelGenerationEmail = new LpcInwardLabelGenerationEmail();
            $label                         = $this->inwardLabelDb->getLabelFor($trackingNumber);
            $order                         = new WC_Order($label['order_id']);
            $sent                          = $lpcInwardLabelGenerationEmail->trigger($order, $label['label']);
            // TODO : Try to find out a better way for the admin_notices
            $lpc_admin_notices = LpcRegister::get('lpcAdminNotices');
            if ($sent) {
                $lpc_admin_notices->add_notice('inward_label_sent', 'notice-success', __('Label sent', 'wc_colissimo'));
            } else {
                $lpc_admin_notices->add_notice(
                    'inward_label_sent',
                    'notice-error',
                    __('Label was not sent', 'wc_colissimo')
                );
            }

            wp_redirect($urlRedirection);
            exit;
        } catch (Exception $e) {
            return $e->getCode();
        }
    }

    public function labelEmailingUrl($trackingNumber, $redirection) {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME)
               . '&' . self::TRACKING_NUMBER_VAR_NAME . '=' . $trackingNumber
               . '&' . self::REDIRECTION_VAR_NAME . '=' . $redirection;
    }
}
