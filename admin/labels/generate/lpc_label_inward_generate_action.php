<?php

defined('ABSPATH') || die('Restricted Access');

class LpcLabelInwardGenerateAction extends LpcComponent {
    const AJAX_TASK_NAME = 'label/inward/create';
    const ACTION_ID_PARAM_NAME = 'lpc_label_inward_id';
    const ACTION_OUTWARD_LABEL_ID_PARAM_NAME = 'lpc_label_outward_id';

    /** @var LpcAjax */
    protected $ajaxDispatcher;
    /** @var LpcLabelGenerationInward */
    protected $labelGenerationInward;

    public function __construct(
        LpcAjax $ajaxDispatcher = null,
        LpcLabelGenerationInward $labelGenerationInward = null
    ) {
        $this->ajaxDispatcher        = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);
        $this->labelGenerationInward = LpcRegister::get('labelGenerationInward', $labelGenerationInward);
    }

    public function getDependencies() {
        return ['ajaxDispatcher', 'labelGenerationInward'];
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control']);
    }

    public function generateUrl($oneOrderId, $outwardLabelId) {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME) . '&' . self::ACTION_ID_PARAM_NAME . '=' . (int) $oneOrderId
               . '&' . self::ACTION_OUTWARD_LABEL_ID_PARAM_NAME . '=' . $outwardLabelId;
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
        $outwardLabelId = LpcHelper::getVar(self::ACTION_OUTWARD_LABEL_ID_PARAM_NAME);
        $order          = new WC_Order($orderId);

        $customParams = [
            'items'                => $order->get_items(),
            'outward_label_number' => $outwardLabelId,
        ];
        $this->labelGenerationInward->generate($order, $customParams, true);

        wp_redirect($urlRedirection);
    }
}
