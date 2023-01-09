<?php

require_once LPC_INCLUDES . 'lpc_db.php';

class LpcOutwardLabelDb extends LpcDb {
    const OLD_TABLE_NAME = 'lpc_label';
    const TABLE_NAME = 'lpc_outward_label';
    const LABEL_TYPE_OUTWARD = 'outward';


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
    id               INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    order_id         BIGINT(20) UNSIGNED NOT NULL,
    label            MEDIUMBLOB          NULL,
    label_format     VARCHAR(255)        NULL,
    label_created_at DATETIME            NULL,
    cn23             MEDIUMBLOB          NULL,
    tracking_number  VARCHAR(255)        NULL,
    bordereau_id     BIGINT(20)          NULL,
    detail           LONGTEXT            NULL,
    printed          TINYINT(1)          NOT NULL DEFAULT 0,
    status_id        INT UNSIGNED        NULL,
    label_type       VARCHAR(10)         NOT NULL DEFAULT "CLASSIC",
    PRIMARY KEY (id),
    INDEX order_id (order_id),
    INDEX tracking_number (tracking_number)
) $charset_collate;
END_SQL;
    }

    public function getOldTableOrdersToMigrate() {
        global $wpdb;

        $oldTableName = $this->getOldTableName();

        // phpcs:disable
        $queryOrdersIdsToMigrate = <<<END_SQL
SELECT order_id FROM $oldTableName ORDER BY order_id DESC
END_SQL;

        return $wpdb->get_col($queryOrdersIdsToMigrate);
        // phpcs:enable
    }

    public function migrateDataFromLabelTableForOrderIds($orderIds = []) {
        global $wpdb;

        $tableName      = $this->getTableName();
        $labelTableName = $this->getOldTableName();

        if (0 === count($orderIds)) {
            LpcLogger::error(
                'Error during outward labels migration',
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
        $queryGetLabels = "SELECT order_id, outward_label, outward_label_created_at, outward_cn23, outward_label_format 
							FROM $labelTableName 
							WHERE order_id IN ('" . implode("', '", $orderIds) . "') AND outward_label IS NOT NULL
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
                LpcLabelGenerationOutward::OUTWARD_PARCEL_NUMBER_META_KEY,
                true
            );

            if (empty($trackingNumber)) {
                continue;
            }

            $labelsToInsert[] = $wpdb->prepare(
                '(%d, %s, %s, %s, %s, %s)',
                $oneLabel->order_id,
                $oneLabel->outward_label,
                $oneLabel->outward_label_format,
                $oneLabel->outward_label_created_at,
                $oneLabel->outward_cn23,
                $trackingNumber
            );
        }

        $stringLabelsToInsert = implode(', ', $labelsToInsert);

        LpcLogger::debug(
            'Migrate outward labels',
            [
                'order_ids' => $orderIds,
                'method'    => __METHOD__,
            ]
        );

        // phpcs:disable
        $queryInsertLabels = <<<END_SQL
INSERT INTO $tableName (`order_id`, `label`, `label_format`, `label_created_at`, `cn23`, `tracking_number`) 
VALUES $stringLabelsToInsert
END_SQL;

        $resultInsert = $wpdb->query($queryInsertLabels);
        // phpcs:enable

        LpcLogger::debug(
            'Result migration outward labels',
            [
                'result'    => $resultInsert,
                'order_ids' => $orderIds,
                'method'    => __METHOD__,
            ]
        );

        if (false === $resultInsert) {
            $errorDbMessage = $wpdb->last_error;
            LpcLogger::error(
                'Error during outward labels migration',
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
        $type,
        $cn23 = null,
        $labelFormat = LpcLabelGenerationPayload::LABEL_FORMAT_PDF,
        $detail = []
    ) {
        global $wpdb;

        if (is_array($detail)) {
            $detail = json_encode($detail);
        }

        return $wpdb->query(
            $wpdb->prepare(
                'INSERT INTO ' . $wpdb->prefix . 'lpc_outward_label (`order_id`, `label`, `label_format`, `label_created_at`, `cn23`, `tracking_number`, `detail`, `label_type`) 
                VALUES (%d, %s, %s, %s, %s, %s, %s, %s)',
                $orderId,
                $label,
                $labelFormat,
                current_time('mysql'),
                $cn23,
                $trackingNumber,
                $detail,
                $type
            )
        );
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

        $outwardLabelAndFormat = $wpdb->get_results($query);
        // phpcs:enable

        if (!empty($outwardLabelAndFormat[0])) {
            $label   = $outwardLabelAndFormat[0]->label;
            $orderId = $outwardLabelAndFormat[0]->order_id;
            $printed = !empty($outwardLabelAndFormat[0]->printed);

            $format = !empty($outwardLabelAndFormat[0]->label_format) ? $outwardLabelAndFormat[0]->label_format : LpcLabelGenerationPayload::LABEL_FORMAT_PDF;
        }

        return [
            'format'   => $format,
            'label'    => $label,
            'order_id' => $orderId,
            'printed'  => $printed,
        ];
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

        $outwardCn23 = $wpdb->get_results($query);

        // phpcs:enable

        return !empty($outwardCn23[0]->cn23) ? $outwardCn23[0]->cn23 : '';
    }

    public function getLabelsInfosForOrdersId($ordersId = [], $onlyNew = false) {
        global $wpdb;
        $tableName = $this->getTableName();

        $ordersId = array_map(
            function ($orderId) {
                return (int) $orderId;
            },
            $ordersId
        );

        $where = '';

        if ($onlyNew) {
            $where = 'AND bordereau_id IS NULL';
        }

        // phpcs:disable
        $query = "SELECT order_id,
                        tracking_number,
                        label_format,
                        id,
                        detail
					FROM {$tableName}
					WHERE order_id IN ('" . implode("', '", $ordersId) . "') {$where}
					ORDER BY order_id DESC, label_created_at DESC";

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

    public function updateToVersion164() {
        global $wpdb;
        $tableName = $this->getTableName();
        $prefix    = $wpdb->prefix;

        // phpcs:disable
        $columns        = $wpdb->get_results('SHOW COLUMNS FROM ' . $tableName);
        $updatedColumns = array_filter($columns,
            function ($column) {
                return 'bordereau_id' === $column->Field || 'detail' === $column->Field;
            });
        if (!empty($updatedColumns)) {
            return;
        }

        $query = <<<END_SQL
ALTER TABLE $tableName ADD COLUMN `bordereau_id` BIGINT(20) NULL
END_SQL;

        $wpdb->query($query);

        $query = <<<END_SQL
ALTER TABLE $tableName ADD COLUMN `detail` LONGTEXT NULL
END_SQL;
        $wpdb->query($query);

        $queryUpdateBordereauColumn = <<<END_SQL
INSERT INTO $tableName (`order_id`, `bordereau_id`) 
SELECT `post_id`, `meta_value` 
FROM {$prefix}postmeta 
WHERE `meta_key` = "lpc_bordereau_id"
ON DUPLICATE KEY UPDATE `bordereau_id` = VALUES(`bordereau_id`)
END_SQL;

        $wpdb->query($queryUpdateBordereauColumn);
        // phpcs:enable
    }

    public function updateToVersion165() {
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

    public function updateToVersion172() {
        global $wpdb;
        $tableName = $this->getTableName();

        // phpcs:disable
        $columns        = $wpdb->get_results('SHOW COLUMNS FROM ' . $tableName);
        $updatedColumns = array_filter($columns,
            function ($column) {
                return in_array($column->Field, ['status_id', 'label_type']);
            }
        );
        if (!empty($updatedColumns)) {
            return;
        }

        $wpdb->query('ALTER TABLE ' . $tableName . ' ADD COLUMN `status_id` INT UNSIGNED NULL');
        $wpdb->query('ALTER TABLE ' . $tableName . ' ADD COLUMN `label_type` VARCHAR(10) NOT NULL DEFAULT "CLASSIC"');
        // phpcs:enable
    }

    public function addBordereauIdOnBordereauGeneration($outwardLabelIds, $bordereauId) {
        if (empty($outwardLabelIds) || empty($bordereauId)) {
            return;
        }

        global $wpdb;
        $tableName = $this->getTableName();

        $bordereauId     = intval($bordereauId);
        $outwardLabelIds = array_map('intval', $outwardLabelIds);

        $values = [];
        foreach ($outwardLabelIds as $labelId) {
            $values[] = $labelId . ',' . $bordereauId;
        }

        $values = '(' . implode('),(', $values) . ')';

        // phpcs:disable
        $query = <<<END_SQL
INSERT INTO $tableName (`id`, `bordereau_id`) VALUES $values ON DUPLICATE KEY UPDATE `bordereau_id`=VALUES(`bordereau_id`)
END_SQL;

        return $wpdb->query($query);
        // phpcs:enable
    }

    public function getOutwardLabelOrderIdOfTheDayWithoutBordereau() {
        global $wpdb;
        $tableName = $this->getTableName();

        $todayFirstHour = date('Y-m-d 00:00:00', time());

        // phpcs:disable
        $query = <<<END_SQL
SELECT order_id FROM $tableName WHERE label_created_at >= "$todayFirstHour" AND bordereau_id IS NULL
END_SQL;

        return $wpdb->get_col($query);
        // phpcs:enable
    }

    public function getAllLabelDetailByOrderId($orderId) {
        global $wpdb;
        $tableName = $this->getTableName();

        $orderId = intval($orderId);

        // phpcs:disable
        $query = <<<END_SQL
SELECT detail FROM $tableName WHERE order_id = $orderId 
END_SQL;

        return $wpdb->get_col($query);
        // phpcs:enable
    }

    public function getBordereauFromTrackingNumber($trackingNumber) {
        global $wpdb;
        $tableName = $this->getTableName();
        // phpcs:disable
        $query = <<<END_SQL
SELECT bordereau_id FROM $tableName WHERE tracking_number = "$trackingNumber"
END_SQL;

        return $wpdb->get_col($query);
        // phpcs:enable
    }

    public function getOrderLabels($orderId) {
        global $wpdb;

        $table_name = $this->getTableName();
        $orderId    = intval($orderId);

        // phpcs:disable
        $query = <<<END_SQL
SELECT tracking_number 
FROM $table_name
WHERE `order_id` = $orderId
END_SQL;

        return $wpdb->get_col($query);
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

    public function insertFromThirdParty($orderId, $trackingNumber) {
        if (empty($orderId) || empty($trackingNumber)) {
            return false;
        }

        global $wpdb;
        $tableName = $this->getTableName();

        // phpcs:disable
        $sql = 'INSERT INTO ' . $tableName . ' (`order_id`, `tracking_number`) VALUES (%d, %s)';

        $sql = $wpdb->prepare(
            $sql,
            $orderId,
            $trackingNumber
        );

        return $wpdb->query($sql);
        // phpcs:enable
    }

    public function deleteBordereau($bordereauID) {
        global $wpdb;
        $table_name = $this->getTableName();

        //phpcs:disable
        $sql = <<<END_SQL
UPDATE $table_name SET bordereau_id = NULL WHERE bordereau_id = $bordereauID
END_SQL;

        return $wpdb->query($sql);
        // phpcs:enable
    }

    public function getLabel($trackingNumber) {
        global $wpdb;
        $table_name = $this->getTableName();

        // phpcs:disable
        return $wpdb->get_row('SELECT * FROM ' . $table_name . ' WHERE `tracking_number` = \'' . esc_sql($trackingNumber) . '\'');
        // phpcs:enable
    }

    public function setLabelStatusId($trackingNumber, $statusId) {
        if (empty($trackingNumber)) {
            return;
        }

        global $wpdb;
        $table_name = $this->getTableName();

        // phpcs:disable
        $wpdb->query('UPDATE ' . $table_name . ' SET `status_id` = ' . intval($statusId) . ' WHERE `tracking_number` = \'' . esc_sql($trackingNumber) . '\'');
        // phpcs:enable
    }

    public function getMultiParcelsLabels($orderId): array {
        if (empty($orderId)) {
            return [];
        }

        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT `tracking_number`, `label_type` 
                FROM ' . $wpdb->prefix . 'lpc_outward_label 
                WHERE `label_type` IN ("FOLLOWER", "MASTER") 
                    AND `order_id` = %d',
                $orderId
            )
        );

        $labels = [];
        foreach ($results as $oneResult) {
            $labels[$oneResult->tracking_number] = $oneResult->label_type;
        }

        return $labels;
    }
}
