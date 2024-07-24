<?php

class LpcLabelPurge extends LpcComponent {

    /** @var LpcInwardLabelDb */
    protected $inwardLabelDb;
    /** @var LpcOutwardLabelDb */
    protected $outwardLabelDb;

    public function __construct(LpcInwardLabelDb $inwardLabelDb = null, LpcOutwardLabelDb $outwardLabelDb = null) {
        $this->inwardLabelDb = LpcRegister::get('inwardLabelDb', $inwardLabelDb);
        $this->outwardLabelDb = LpcRegister::get('outwardLabelDb', $outwardLabelDb);
    }

    public function getDependencies(): array {
        return ['inwardLabelDb', 'outwardLabelDb'];
    }

    public function purgeReadyLabels() {
        $nbDays = LpcHelper::get_option('lpc_day_purge', 30);

        if ('0' == $nbDays) {
            return;
        }

        $matchingOrdersId = LpcOrderQueries::getLpcOrdersIdsForPurge();

        $this->purgeLabels($matchingOrdersId);
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
}
