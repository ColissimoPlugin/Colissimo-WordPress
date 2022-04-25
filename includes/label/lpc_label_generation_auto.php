<?php

class LpcLabelGenerationAuto extends LpcComponent {

    protected $labelGenerationOutward;

    public function __construct(LpcLabelGenerationOutward $labelGenerationOutward = null) {
        $this->labelGenerationOutward = LpcRegister::get('labelGenerationOutward', $labelGenerationOutward);
    }

    public function getDependencies() {
        return ['labelGenerationOutward'];
    }

    public function init() {
        add_action('woocommerce_order_status_changed', [$this, 'generateLabelsAuto'], 50, 4);
    }

    /**
     * Automatically generate the label if order status matches status from configuration
     *
     * @param $orderId
     * @param $statusFrom
     * @param $statusTo
     * @param $order
     */
    public function generateLabelsAuto($orderId, $statusFrom, $statusTo, $order) {
        $orderStatuses = LpcHelper::get_option('lpc_generate_label_on', '');

        if (!is_array($orderStatuses)) {
            $orderStatuses = [$orderStatuses];
        }

        $key = array_search(LpcOrderStatuses::WC_LPC_DISABLE, $orderStatuses);

        if (empty($orderStatuses) || $statusFrom === $statusTo || false !== $key) {
            return;
        }

        // Woocommerce removes the "wc-" prefix on the native order statuses that are sent in the hook
        if (in_array($statusTo, $orderStatuses) || in_array('wc-' . $statusTo, $orderStatuses)) {
            try {
                $allItemsOrder = $order->get_items();
                $this->labelGenerationOutward->generate($order, ['items' => $allItemsOrder], true);
            } catch (Exception $e) {
                LpcLogger::error(__METHOD__, $e->getMessage());
            }
        }
    }
}
