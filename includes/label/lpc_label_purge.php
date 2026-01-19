<?php
defined('ABSPATH') || die('Restricted Access');

class LpcLabelPurge extends LpcComponent {

    /** @var LpcInwardLabelDb */
    protected $inwardLabelDb;
    /** @var LpcOutwardLabelDb */
    protected $outwardLabelDb;
    /** @var LpcBordereauDb */
    protected $bordereauDb;

    public function __construct(
        ?LpcInwardLabelDb $inwardLabelDb = null,
        ?LpcOutwardLabelDb $outwardLabelDb = null,
        ?LpcBordereauDb $bordereauDb = null
    ) {
        $this->inwardLabelDb = LpcRegister::get('inwardLabelDb', $inwardLabelDb);
        $this->outwardLabelDb = LpcRegister::get('outwardLabelDb', $outwardLabelDb);
        $this->bordereauDb = LpcRegister::get('bordereauDb', $bordereauDb);
    }

    public function getDependencies(): array {
        return ['inwardLabelDb', 'outwardLabelDb', 'bordereauDb'];
    }

    public function purgeReadyLabels() {
        $nbDays = (int) LpcHelper::get_option('lpc_day_purge', 30);

        if (empty($nbDays)) {
            return;
        }

        $matchingOrdersId = LpcOrderQueries::getLpcOrdersIdsForPurge();

        $this->purgeLabels($matchingOrdersId);
        $this->purgeDeliverySlips($nbDays);
    }

    public function purgeLabels($orderIds) {
        if (empty($orderIds)) {
            return;
        }

        LpcLogger::debug(
            __METHOD__ . ' purge labels for',
            [
                'orderIds' => implode(', ', $orderIds),
            ]
        );

        $this->inwardLabelDb->purgeLabelsByOrdersId($orderIds);
        $this->outwardLabelDb->purgeLabelsByOrdersId($orderIds);

        foreach ($orderIds as $orderId) {
            $order = wc_get_order($orderId);
            if (empty($order)) {
                continue;
            }

            $order->delete_meta_data(LpcLabelGenerationOutward::OUTWARD_PARCEL_NUMBER_META_KEY);
            $order->delete_meta_data(LpcLabelGenerationInward::INWARD_PARCEL_NUMBER_META_KEY);
            $order->save();
        }
    }

    private function purgeDeliverySlips(int $nbDays) {
        LpcLogger::debug(
            __METHOD__ . ' purge old delivery slips',
            [
                'days' => $nbDays,
            ]
        );

        $this->bordereauDb->purge($nbDays);
    }
}
