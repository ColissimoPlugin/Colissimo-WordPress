<?php

class LpcWooOrdersTableAction extends LpcComponent {
    const AJAX_TASK_NAME = 'woocommerce/listing/generate/outward';
    const ORDER_ID_VAR_NAME = 'lpc_order_id';
    const ACTION_NAME = 'lcp_generate_outward_label';

    /** @var LpcAjax */
    protected $ajaxDispatcher;
    /** @var LpcLabelGenerationOutward */
    protected $labelGenerationOutward;

    public function __construct(LpcAjax $ajaxDispatcher = null, LpcLabelGenerationOutward $labelGenerationOutward = null) {
        $this->ajaxDispatcher         = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);
        $this->labelGenerationOutward = LpcRegister::get('labelGenerationOutward', $labelGenerationOutward);
    }

    public function getDependencies() {
        return ['ajaxDispatcher', 'labelGenerationOutward'];
    }

    public function init() {
        add_filter('woocommerce_admin_order_actions', [$this, 'addAction'], 10, 2);
        add_action(
            'current_screen',
            function ($currentScreen) {
                if ('edit' === $currentScreen->base && 'shop_order' === $currentScreen->post_type) {
                    LpcHelper::enqueueStyle(
                        'lpc_woocommerce_order_table_actions',
                        plugins_url('/css/orders/lpc_woocommerce_order_table_actions.css', LPC_ADMIN . 'init.php'),
                        null
                    );
                }
            }
        );
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control']);
    }

    public function control() {
        if (!current_user_can('lpc_manage_labels')) {
            return;
        }

        $orderId = LpcHelper::getVar(self::ORDER_ID_VAR_NAME);
        $order   = new WC_Order($orderId);

        try {
            $this->labelGenerationOutward->generate($order, ['items' => $order->get_items()], true);
        } catch (Exception $e) {
            LpcLogger::error(__METHOD__, [$e->getMessage()]);
        }

        wp_redirect('edit.php?post_type=shop_order');
    }

    public function addAction($actions, $order) {
        if (current_user_can('lpc_manage_labels')) {
            $actions[self::ACTION_NAME] = [
                'url'    => $this->generateUrl($order->get_id()),
                'name'   => __('Generate outward label', 'wc_colissimo'),
                'action' => self::ACTION_NAME,
            ];
        }

        return $actions;
    }


    public function generateUrl($orderId) {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME)
               . '&' . self::ORDER_ID_VAR_NAME . '=' . (int) $orderId;
    }

}
