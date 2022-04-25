<?php

defined('ABSPATH') || die('Restricted Access');

class LpcLabelOutwardGenerateAction extends LpcComponent {
    const AJAX_TASK_NAME = 'label/outward/create';
    const ACTION_ID_PARAM_NAME = 'lpc_label_outward_id';

    /** @var LpcAjax */
    protected $ajaxDispatcher;
    /** @var LpcLabelGenerationOutward */
    protected $labelGenerationOutward;

    public function __construct(
        LpcAjax $ajaxDispatcher = null,
        LpcLabelGenerationOutward $labelGenerationOutward = null
    ) {
        $this->ajaxDispatcher         = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);
        $this->labelGenerationOutward = LpcRegister::get('labelGenerationOutward', $labelGenerationOutward);
    }

    public function getDependencies() {
        return ['ajaxDispatcher', 'labelGenerationOutward'];
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control']);
    }

    public function generateUrl($oneOrderId) {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME) .
               '&' . self::ACTION_ID_PARAM_NAME . '=' . (int) $oneOrderId;
    }

    public function control() {
        if (!current_user_can('lpc_manage_labels')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => 'unauthorized access to create new outward label',
                ]
            );
        }
        $urlRedirection = admin_url('admin.php?page=wc_colissimo_view');
        $orderId        = LpcHelper::getVar(self::ACTION_ID_PARAM_NAME);
        $order          = new WC_Order($orderId);

        $this->labelGenerationOutward->generate($order, ['items' => $order->get_items()], true);
        wp_redirect($urlRedirection);
    }
}
