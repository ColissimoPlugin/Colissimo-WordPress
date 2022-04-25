<?php

abstract class LpcDb extends LpcComponent {

    abstract public function getTableName();

    abstract public function getTableDefinition();

    public function getOrderIdByTrackingNumber($trackingNumber) {
        global $wpdb;
        $tableName = $this->getTableName();

        // phpcs:disable
        $query = <<<END_SQL
SELECT order_id
FROM $tableName
WHERE tracking_number = "%s"
END_SQL;

        $query = $wpdb->prepare($query, $trackingNumber);

        return $wpdb->get_var($query);
        // phpcs:enable
    }
}
