<?php

require_once LPC_INCLUDES . 'lpc_db.php';

class LpcInwardLabelDb extends LpcDb {
    const OLD_TABLE_NAME = 'lpc_label';
    const TABLE_NAME = 'lpc_inward_label';
    const LABEL_TYPE_INWARD = 'inward';

    public function getTableName() {
        global $wpdb;

        return $wpdb->prefix . self::TABLE_NAME;
    }

    public function getOldTableName() {
        global $wpdb;

        return $wpdb->prefix . self::OLD_TABLE_NAME;
    }

    public function getTableDefinition() {
        global $wpdb;

        $table_name = $this->getTableName();

        $charset_collate = $wpdb->get_charset_collate();

        return <<<END_SQL
CREATE TABLE $table_name (
    id                      INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    order_id                BIGINT(20) UNSIGNED NOT NULL,
    label                   MEDIUMBLOB          NULL,
    label_format            VARCHAR(255)        NULL,
    label_created_at        DATETIME            NULL,
    cn23                    MEDIUMBLOB          NULL,
    tracking_number         VARCHAR(255)        NULL,
    outward_tracking_number VARCHAR(255)        NULL,
    printed                 TINYINT(1)          NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    INDEX order_id (order_id),
    INDEX tracking_number (tracking_number),
    INDEX outward_tracking_number (outward_tracking_number)  
) $charset_collate;
END_SQL;
    }

    public function migrateDataFromLabelTableForOrderIds($orderIds = []) {
        global $wpdb;

        $tableName      = $this->getTableName();
        $labelTableName = $this->getOldTableName();

        if (0 === count($orderIds)) {
            LpcLogger::error(
                'Error during inward labels migration',
                [
                    'message' => 'No orders to migrate',
                    'method'  => __METHOD__,
                ]
            );

            return false;
        }

        $orderIds = array_map(
            function ($orderId) {
                return (int) $orderId;
            },
            $orderIds
        );

        // phpcs:disable
        $queryGetLabels = "SELECT order_id, inward_label, inward_label_created_at, inward_cn23, inward_label_format 
							FROM  $labelTableName 
							WHERE order_id IN ('" . implode("', '", $orderIds) . "') AND inward_label IS NOT NULL
							ORDER BY order_id ASC";

        $labelsToMigrate = $wpdb->get_results($queryGetLabels);
        // phpcs:enable

        if (0 === count($labelsToMigrate)) {
            return true;
        }

        $labelsToInsert = [];

        foreach ($labelsToMigrate as $oneLabel) {
            $trackingNumber = get_post_meta(
                $oneLabel->order_id,
                LpcLabelGenerationInward::INWARD_PARCEL_NUMBER_META_KEY,
                true
            );

            if (empty($trackingNumber)) {
                continue;
            }

            $outwardTrackingNumber = get_post_meta(
                $oneLabel->order_id,
                LpcLabelGenerationOutward::OUTWARD_PARCEL_NUMBER_META_KEY,
                true
            );

            $labelsToInsert[] = $wpdb->prepare(
                '(%d, %s, %s, %s, %s, %s, %s)',
                $oneLabel->order_id,
                $oneLabel->inward_label,
                $oneLabel->inward_label_format,
                $oneLabel->inward_label_created_at,
                $oneLabel->inward_cn23,
                $trackingNumber,
                $outwardTrackingNumber
            );
        }

        $stringLabelsToInsert = implode(', ', $labelsToInsert);

        LpcLogger::debug(
            'Migrate inward labels',
            [
                'order_ids' => $orderIds,
                'method'    => __METHOD__,
            ]
        );

        // phpcs:disable
        $queryInsertLabels = <<<END_SQL
				INSERT INTO $tableName (`order_id`, `label`, `label_format`, `label_created_at`, `cn23`, `tracking_number`, `outward_tracking_number`) 
				VALUES $stringLabelsToInsert
END_SQL;

        $resultInsert = $wpdb->query($queryInsertLabels);
        // phpcs:enable

        LpcLogger::debug(
            'Result migration inward labels',
            [
                'result'    => $resultInsert,
                'order_ids' => $orderIds,
                'method'    => __METHOD__,
            ]
        );

        if (false === $resultInsert) {
            $errorDbMessage = $wpdb->last_error;

            LpcLogger::error(
                'Error during inward labels migration',
                [
                    'message' => $errorDbMessage,
                    'method'  => __METHOD__,
                ]
            );

            return false;
        }

        return true;
    }

    public function insert(
        $orderId,
        $label,
        $trackingNumber,
        $cn23 = null,
        $labelFormat = LpcLabelGenerationPayload::LABEL_FORMAT_PDF,
        $outwardTrackingNumber = null
    ) {
        global $wpdb;

        $tableName = $this->getTableName();

        if (is_null($outwardTrackingNumber)) {
            $outwardTrackingNumber = get_post_meta(
                $orderId,
                LpcLabelGenerationOutward::OUTWARD_PARCEL_NUMBER_META_KEY,
                true
            );
        }

        // phpcs:disable
        $sql = 'INSERT INTO ' . $tableName . ' (`order_id`, `label`, `label_format`, `label_created_at`, `cn23`, `tracking_number`, `outward_tracking_number`) VALUES (%d, %s, %s, %s, %s, %s, %s)';

        $sql = $wpdb->prepare(
            $sql,
            $orderId,
            $label,
            $labelFormat,
            current_time('mysql'),
            $cn23,
            $trackingNumber,
            $outwardTrackingNumber
        );

        return $wpdb->query($sql);
        // phpcs:enable
    }

    public function getLabelFor($trackingNumber) {
        global $wpdb;
        $tableName = $this->getTableName();

        $label   = '';
        $format  = '';
        $orderId = '';
        $printed = false;

        // phpcs:disable
        $query = <<<END_SQL
SELECT label, label_format, order_id, printed
FROM $tableName
WHERE tracking_number = "%s"
END_SQL;

        $query = $wpdb->prepare($query, $trackingNumber);

        $inwardLabelAndFormat = $wpdb->get_results($query);
        // phpcs:enable

        if (!empty($inwardLabelAndFormat[0])) {
            $label   = $inwardLabelAndFormat[0]->label;
            $orderId = $inwardLabelAndFormat[0]->order_id;
            $printed = !empty($inwardLabelAndFormat[0]->printed);

            $format = !empty($inwardLabelAndFormat[0]->label_format) ? $inwardLabelAndFormat[0]->label_format : LpcLabelGenerationPayload::LABEL_FORMAT_PDF;
        }

        return [
            'format'   => $format,
            'label'    => $label,
            'order_id' => $orderId,
            'printed'  => $printed,
        ];
    }

    public function getLabelByOutwardNumber($trackingNumber) {
        global $wpdb;
        $tableName = $this->getTableName();

        $label       = '';
        $format      = '';
        $orderId     = '';
        $labelNumber = '';

        // phpcs:disable
        $query = <<<END_SQL
SELECT label, label_format, order_id, tracking_number
FROM $tableName
WHERE outward_tracking_number = "%s"
END_SQL;

        $query = $wpdb->prepare($query, $trackingNumber);

        $outwardLabelAndFormat = $wpdb->get_results($query);
        // phpcs:enable

        if (!empty($outwardLabelAndFormat[0])) {
            $label       = $outwardLabelAndFormat[0]->label;
            $orderId     = $outwardLabelAndFormat[0]->order_id;
            $labelNumber = $outwardLabelAndFormat[0]->tracking_number;

            $format = !empty($outwardLabelAndFormat[0]->label_format) ? $outwardLabelAndFormat[0]->label_format : LpcLabelGenerationPayload::LABEL_FORMAT_PDF;
        }

        $result = [
            'format'       => $format,
            'label'        => $label,
            'order_id'     => $orderId,
            'label_number' => $labelNumber,
        ];

        return $result;
    }

    public function getCn23For($trackingNumber) {
        global $wpdb;
        $tableName = $this->getTableName();

        // phpcs:disable
        $query = <<<END_SQL
SELECT cn23
FROM $tableName
WHERE tracking_number = "%s"
END_SQL;

        $query = $wpdb->prepare($query, $trackingNumber);

        $inwardCn23 = $wpdb->get_results($query);

        // phpcs:enable

        return !empty($inwardCn23[0]->cn23) ? $inwardCn23[0]->cn23 : '';
    }

    public function getLabelsInfosForOrdersId($ordersId = []) {
        global $wpdb;
        $tableName = $this->getTableName();

        $ordersId = array_map(
            function ($orderId) {
                return (int) $orderId;
            },
            $ordersId
        );

        // phpcs:disable
        $query = "SELECT order_id,
       					tracking_number,
       					outward_tracking_number,
       					label_format
					FROM {$tableName}
					WHERE order_id IN ('" . implode("', '", $ordersId) . "')
					ORDER BY order_id DESC, label_created_at DESC";

        return $wpdb->get_results($query);
        // phpcs:enable
    }

    public function getLabelsInfosForOutward($outwardTrackingNumber) {
        global $wpdb;
        $tableName = $this->getTableName();

        // phpcs:disable
        $query = <<<END_SQL
SELECT order_id,
		tracking_number,
		outward_tracking_number,
		label_format
FROM $tableName
WHERE outward_tracking_number = "%s"
END_SQL;

        $query = $wpdb->prepare($query, $outwardTrackingNumber);

        return $wpdb->get_results($query);
        // phpcs:enable
    }

    public function delete($trackingNumber) {
        global $wpdb;
        $tableName = $this->getTableName();

        // phpcs:disable
        $query = <<<END_SQL
DELETE FROM $tableName
WHERE tracking_number = "%s"
END_SQL;

        $query = $wpdb->prepare($query, $trackingNumber);

        return $wpdb->query($query);
        // phpcs:enable
    }

    public function deleteForOutward($outwardTrackingNumber) {
        global $wpdb;
        $tableName = $this->getTableName();

        // phpcs:disable
        $query = <<<END_SQL
DELETE FROM $tableName
WHERE outward_tracking_number = "%s"
END_SQL;

        $query = $wpdb->prepare($query, $outwardTrackingNumber);

        return $wpdb->query($query);
        // phpcs:enable
    }

    public function purgeLabelsByOrdersId($ordersId) {

        if (!is_array($ordersId)) {
            $ordersId = [$ordersId];
        }

        $ordersId = array_map('intval', $ordersId);

        global $wpdb;
        $tableName = $this->getTableName();

        $whereIn = implode(',', $ordersId);

        // phpcs:disable
        $query = <<<END_SQL
DELETE FROM $tableName
WHERE order_id IN ($whereIn)
END_SQL;

        return $wpdb->query($query);
        // phpcs:enable
    }

    public function truncate() {
        global $wpdb;
        $tableName = $this->getTableName();

        // phpcs:disable
        $query = <<<END_SQL
TRUNCATE TABLE $tableName
END_SQL;

        return $wpdb->query($query);
        // phpcs:enable
    }

    public function updateToVersion174() {
        global $wpdb;
        $tableName = $this->getTableName();

        // phpcs:disable
        $columns        = $wpdb->get_results('SHOW COLUMNS FROM ' . $tableName);
        $updatedColumns = array_filter($columns,
            function ($column) {
                return 'printed' === $column->Field;
            });
        if (!empty($updatedColumns)) {
            return;
        }

        $query = <<<END_SQL
ALTER TABLE $tableName ADD COLUMN `printed` TINYINT(1) NOT NULL DEFAULT 0
END_SQL;

        $wpdb->query($query);
        // phpcs:enable
    }

    public function updatePrintedLabel($trackingNumbers) {
        if (empty($trackingNumbers)) {
            return;
        }

        if (!is_array($trackingNumbers)) {
            $trackingNumbers = [$trackingNumbers];
        }

        global $wpdb;

        $table_name = $this->getTableName();

        $whereIn = '"' . implode('","', $trackingNumbers) . '"';

        //phpcs:disable
        $query = <<<END_SQL
UPDATE $table_name SET printed = 1 WHERE tracking_number IN ($whereIn)
END_SQL;

        $wpdb->query($query);
        // phpcs:enable
    }
}
