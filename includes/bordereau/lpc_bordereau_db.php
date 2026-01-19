<?php
defined('ABSPATH') || die('Restricted Access');

class LpcBordereauDb extends LpcDb {
    const TABLE_NAME = 'lpc_bordereau';

    /** @var LpcBordereauGenerationApi */
    protected $bordereauGenerationApi;

    public function __construct(?LpcBordereauGenerationApi $bordereauGenerationApi = null) {
        $this->bordereauGenerationApi = LpcRegister::get('bordereauGenerationApi', $bordereauGenerationApi);
    }

    public function getTableName() {
        global $wpdb;

        return $wpdb->prefix . self::TABLE_NAME;
    }

    public function getTableDefinition() {
        global $wpdb;

        $table_name = $this->getTableName();

        $charset_collate = $wpdb->get_charset_collate();

        return <<<END_SQL
CREATE TABLE IF NOT EXISTS $table_name (
    id                    INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    bordereau_external_id INT(20) UNSIGNED NOT NULL,
    created_at            DATETIME         NULL,
    delivery_slip         MEDIUMBLOB       NULL,
    PRIMARY KEY (id)
) $charset_collate;
END_SQL;
    }

    public function insert($bordereauId, $creationDate, $deliverySlip) {
        if (empty($bordereauId) || empty($creationDate)) {
            return false;
        }

        global $wpdb;

        $tableName = $this->getTableName();

        // phpcs:disable
        $sql = 'INSERT INTO ' . $tableName . ' (`bordereau_external_id`, `created_at`, `delivery_slip`) VALUES (%d, %s, %s)';

        $sql = $wpdb->prepare(
            $sql,
            $bordereauId,
            date('Y-m-d H:i:s', strtotime($creationDate)),
            $deliverySlip
        );

        return $wpdb->query($sql);
        // phpcs:enable
    }

    public function getBordereauIdByOrderId($orderId) {
        if (empty($orderId)) {
            return 0;
        }

        global $wpdb;

        $query = "SELECT bordereau_id FROM {$wpdb->prefix}lpc_outward_label WHERE order_id = " . intval($orderId);

        // phpcs:disable
        $results = $wpdb->get_results($query);
        // phpcs:enable

        if (!empty($results)) {
            return $results[0]->bordereau_id;
        }

        return 0;
    }

    public function getDeliverySlipByColissimoId(int $deliverySlipId): ?string {
        global $wpdb;
        $table_name = $this->getTableName();

        // phpcs:disable
        $deliverySlip = $wpdb->get_row('SELECT delivery_slip FROM ' . $table_name . ' WHERE `bordereau_external_id` = ' . intval($deliverySlipId));

        // phpcs:enable

        return $deliverySlip->delivery_slip ?? null;
    }

    public function purge(int $nbDays) {
        global $wpdb;
        $tableName = $this->getTableName();

        // phpcs:disable
        $sql   = 'UPDATE ' . $tableName . ' SET `delivery_slip` = NULL WHERE `created_at` < %s';
        $query = $wpdb->prepare(
            $sql,
            date('Y-m-d H:i:s', time() - $nbDays * 86400)
        );

        $wpdb->query($query);
        // phpcs:enable
    }

    public function updateToVersion182() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($this->getTableDefinition());

        global $wpdb;
        $query = "SELECT DISTINCT bordereau_id, order_id FROM {$wpdb->prefix}lpc_outward_label WHERE bordereau_id IS NOT NULL AND bordereau_id != 0";

        // phpcs:disable
        $results = $wpdb->get_results($query);

        if (empty($results)) {
            return;
        }

        $tableName          = $this->getTableName();
        $bordereauxToInsert = [];
        foreach ($results as $result) {
            try {
                $bordereau = $this->bordereauGenerationApi->getBordereauByNumber($result->bordereau_id)->bordereau;
            } catch (Exception $exception) {
                LpcLogger::error(
                    'Could not load the bordereau from the Colissimo API',
                    [
                        'bordereau_id' => $result->bordereau_id,
                        'message'      => $exception->getMessage(),
                    ]
                );
                continue;
            }
            $bordereauxToInsert[] = $wpdb->prepare(
                '(%d, %s)',
                $result->bordereau_id,
                date('Y-m-d H:i:s', strtotime($bordereau->bordereauHeader->publishingDate))
            );

            $order = wc_get_order($result->order_id);
            if (empty($order)) {
                continue;
            }

            $order->delete_meta_data('lpc_bordereau_id');
            $order->save();
        }

        if (empty($bordereauxToInsert)) {
            return;
        }

        $stringBordereauxToInsert = implode(',', $bordereauxToInsert);

        $queryInsertLabels = <<<END_SQL
INSERT INTO $tableName (`bordereau_external_id`, `created_at`) 
VALUES $stringBordereauxToInsert
END_SQL;

        $wpdb->query($queryInsertLabels);
        // phpcs:enable
    }

    public function updateToVersion282() {
        global $wpdb;
        $tableName = $this->getTableName();

        // phpcs:disable
        $columns        = $wpdb->get_results('SHOW COLUMNS FROM ' . $tableName);
        $updatedColumns = array_filter($columns,
            fn($column) => 'delivery_slip' === $column->Field
        );

        if (!empty($updatedColumns)) {
            return;
        }

        $wpdb->query('ALTER TABLE ' . $tableName . ' ADD COLUMN `delivery_slip` MEDIUMBLOB NULL');
        // phpcs:enable
    }
}
