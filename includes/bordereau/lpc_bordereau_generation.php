<?php

class LpcBordereauGeneration extends LpcComponent {
    const MAX_LABEL_PER_BORDEREAU = 50;
    const BORDEREAU_ID_META_KEY = 'lpc_bordereau_id';
    const AJAX_TASK_NAME = 'bordereau/generate_day';

    /** @var LpcBordereauGenerationApi */
    protected $bordereauGenerationApi;
    /** @var LpcOutwardLabelDb */
    protected $outwardLabelDb;
    /** @var LpcAjax */
    protected $ajaxDispatcher;
    /** @var LpcAdminNotices */
    protected $lpcAdminNotices;

    public function __construct(
        LpcBordereauGenerationApi $bordereauGenerationApi = null,
        LpcOutwardLabelDb $outwardLabelDb = null,
        LpcAjax $ajaxDispatcher = null,
        LpcAdminNotices $lpcAdminNotices = null
    ) {
        $this->bordereauGenerationApi = LpcRegister::get('bordereauGenerationApi', $bordereauGenerationApi);
        $this->outwardLabelDb         = LpcRegister::get('outwardLabelDb', $outwardLabelDb);
        $this->ajaxDispatcher         = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);
        $this->lpcAdminNotices        = LpcRegister::get('lpcAdminNotices', $lpcAdminNotices);
    }

    public function getDependencies() {
        return ['ajaxDispatcher', 'bordereauGenerationApi', 'outwardLabelDb', 'lpcAdminNotices'];
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control']);
    }

    /**
     * @param WC_Order[] $orders
     *
     * @return string|null Return the bordereau if only one bordereau was generated, else null.
     */
    public function generate(array $orders) {
        $ordersId = array_map(
            function (WC_Order $order) {
                return $order->get_id();
            },
            $orders
        );

        return $this->generateFromOrdersId($ordersId);
    }

    protected function prepareBatch(array $parcelNumbers) {
        return array_chunk($parcelNumbers, self::MAX_LABEL_PER_BORDEREAU, true);
    }

    private function getOutwardLabelIdByTrackingNumber($trackingNumbers, $outwardIdByTrackingNumber) {
        $outwardLabelIds = [];

        foreach ($trackingNumbers as $trackingNumber) {
            if (in_array($trackingNumber, array_keys($outwardIdByTrackingNumber))) {
                $outwardLabelIds[] = intval($outwardIdByTrackingNumber[$trackingNumber]);
            }
        }

        return $outwardLabelIds;
    }

    public function control() {
        $outwardLabelsOrderIds = $this->outwardLabelDb->getOutwardLabelOrderIdOfTheDayWithoutBordereau();

        if (!empty($outwardLabelsOrderIds)) {
            $this->generateFromOrdersId($outwardLabelsOrderIds);
        }

        wp_redirect(admin_url('admin.php?page=wc_colissimo_view'));
    }

    private function generateFromOrdersId($ordersId) {
        $ordersLabelsInformation = $this->outwardLabelDb->getLabelsInfosForOrdersId($ordersId, true);

        $orderIdByOutwardsTrackingNumbers = [];
        $outwardIdByTrackingNumber        = [];

        foreach ($ordersLabelsInformation as $oneOrdersLabelsInformation) {
            if (!empty($oneOrdersLabelsInformation->tracking_number) && !empty($oneOrdersLabelsInformation->order_id)) {
                $orderIdByOutwardsTrackingNumbers[$oneOrdersLabelsInformation->tracking_number] = $oneOrdersLabelsInformation->order_id;
                $outwardIdByTrackingNumber[$oneOrdersLabelsInformation->tracking_number]        = $oneOrdersLabelsInformation->id;
            }
        }

        $trackingNumbersPerBatch = $this->prepareBatch($orderIdByOutwardsTrackingNumbers);

        foreach ($trackingNumbersPerBatch as $batchOfTrackingNumbers) {
            $outwardLabelIds = $this->getOutwardLabelIdByTrackingNumber(array_keys($batchOfTrackingNumbers), $outwardIdByTrackingNumber);
            try {
                $retrievedBordereau = $this->bordereauGenerationApi->generateBordereau(array_keys($batchOfTrackingNumbers));
            } catch (Exception $e) {
                $this->lpcAdminNotices->add_notice('lpc_notice', 'notice-error', $e->getMessage());
                continue;
            }

            $bordereau = $retrievedBordereau->bordereau;
            $bordereauId = $bordereau->bordereauHeader->bordereauNumber;

            $newStatus = LpcHelper::get_option('lpc_order_status_on_bordereau_generated');

            $ordersIdForBatch = array_unique($batchOfTrackingNumbers);

            foreach ($ordersIdForBatch as $orderId) {
                $order = wc_get_order($orderId);

                $bordereauIdToStore = get_post_meta($orderId, self::BORDEREAU_ID_META_KEY, true);

                if (empty($bordereauIdToStore)) {
                    $bordereauIdToStore = [];
                }

                if (!empty($bordereauIdToStore)) {
                    $bordereauIdToStore = explode(',', $bordereauIdToStore);
                }

                $bordereauIdToStore[] = $bordereauId;

                update_post_meta($orderId, self::BORDEREAU_ID_META_KEY, implode(',', $bordereauIdToStore));
                $this->outwardLabelDb->addBordereauIdOnBordereauGeneration($outwardLabelIds, $bordereauId);
                if (!empty($newStatus) && 'unchanged_order_status' !== $newStatus) {
                    $order->update_status($newStatus);
                }

                $email_outward_label = LpcHelper::get_option(LpcOutwardLabelEmailManager::EMAIL_OUTWARD_TRACKING_OPTION, 'no');
                if (LpcOutwardLabelEmailManager::ON_BORDEREAU_GENERATION_OPTION === $email_outward_label) {
                    /**
                     * Action when the shipping label has been sent by email
                     *
                     * @since 1.6
                     */
                    do_action(
                        'lpc_outward_label_generated_to_email',
                        ['order' => $order]
                    );
                }
            }
        }

        if (!empty($bordereauId) && 1 === count($trackingNumbersPerBatch)) {
            // when only 1 bordereau is generated, we return it
            return $this->bordereauGenerationApi->getBordereauByNumber($bordereauId)->bordereau;
        }
    }

    public function getGenerationBordereauEndDayUrl() {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME);
    }
}
