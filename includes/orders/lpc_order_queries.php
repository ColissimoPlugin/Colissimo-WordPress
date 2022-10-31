<?php

class LpcOrderQueries {
    const LPC_ALIAS_TABLES_NAME = [
        'woocommerce_order_items'    => 'wc_order_items',
        'lpc_label'                  => 'lpc_label',
        'woocommerce_order_itemmeta' => 'wc_order_itemmeta',
        'posts'                      => 'posts',
        'postmeta'                   => 'postmeta',

    ];

    public static function getLpcOrders(
        $current_page = 0,
        $per_page = 0,
        $args = [],
        $filters = []
    ) {
        // TODO: look if there is a better way to do (with WC Queries)
        global $wpdb;

        $query = "SELECT DISTINCT {$wpdb->prefix}woocommerce_order_items.order_id, {$wpdb->prefix}posts.post_date FROM {$wpdb->prefix}woocommerce_order_items 
                    JOIN {$wpdb->prefix}woocommerce_order_itemmeta ON {$wpdb->prefix}woocommerce_order_itemmeta.order_item_id={$wpdb->prefix}woocommerce_order_items.order_item_id 
                    JOIN {$wpdb->prefix}posts ON {$wpdb->prefix}posts.ID={$wpdb->prefix}woocommerce_order_items.order_id";

        $query .= self::getMetaJoin($args);
        $query .= self::addFilter($filters);

        $query .= self::getOrderBy($args);
        if (0 < $current_page && 0 < $per_page) {
            $offset = ($current_page - 1) * $per_page;
            $query  .= "LIMIT $per_page OFFSET $offset";
        }

        // phpcs:disable
        $results = $wpdb->get_results($query);
        // phpcs:enable

        $ordersId = [];
        if ($results) {
            foreach ($results as $result) {
                $ordersId[] = $result->order_id;
            }
        }

        return $ordersId;
    }

    public static function countLpcOrders($args = [], $filters = []) {
        global $wpdb;

        $query = "SELECT COUNT(DISTINCT {$wpdb->prefix}woocommerce_order_items.order_id) AS nb FROM {$wpdb->prefix}woocommerce_order_items 
                    JOIN {$wpdb->prefix}woocommerce_order_itemmeta ON {$wpdb->prefix}woocommerce_order_itemmeta.order_item_id={$wpdb->prefix}woocommerce_order_items.order_item_id 
                    JOIN {$wpdb->prefix}posts ON {$wpdb->prefix}posts.ID={$wpdb->prefix}woocommerce_order_items.order_id";

        $query .= self::addFilter($filters);

        // phpcs:disable
        $result = $wpdb->get_results($query);
        // phpcs:enable

        if (null !== $result) {
            return $result[0]->nb;
        }

        return 0;
    }

    public static function getLpcOrdersIdsByPostMeta($params = []) {
        global $wpdb;

        $prefix = $wpdb->prefix;

        $query = "SELECT DISTINCT wc_order_items.order_id FROM {$prefix}woocommerce_order_items AS wc_order_items
                    JOIN {$prefix}woocommerce_order_itemmeta  AS wc_order_itemmeta ON wc_order_itemmeta.order_item_id = wc_order_items.order_item_id
                    JOIN {$prefix}posts AS posts ON posts.ID = wc_order_items.order_id
                    LEFT JOIN {$prefix}postmeta AS postmeta ON postmeta.post_id = wc_order_items.order_id AND postmeta.meta_key='_lpc_is_delivered'";

        $params[] = "wc_order_itemmeta.meta_key='method_id' AND wc_order_itemmeta.meta_value LIKE 'lpc_%'";
        $params[] = 'posts.post_type = "shop_order"';

        $query .= ' WHERE (' . implode(') AND (', $params) . ') ';

        // phpcs:disable
        $results = $wpdb->get_results($query);
        // phpcs:enable

        $ordersId = [];

        if ($results) {
            foreach ($results as $result) {
                $ordersId[] = $result->order_id;
            }
        }

        return $ordersId;
    }

    public static function getLpcOrdersIdsForPurge() {
        global $wpdb;

        $nbDays = LpcHelper::get_option('lpc_day_purge');

        $fromDate = time() - $nbDays * DAY_IN_SECONDS;

        $lastEventDateMetaKey = LpcUnifiedTrackingApi::LAST_EVENT_DATE_META_KEY;
        $isDeliveredMetaKey   = LpcUnifiedTrackingApi::IS_DELIVERED_META_KEY;

        $isDelivered = LpcUnifiedTrackingApi::IS_DELIVERED_META_VALUE_TRUE;

        $metaQuery = [
            [
                'key'     => $lastEventDateMetaKey,
                'value'   => $fromDate,
                'compare' => '<',
            ],
            [
                'key'     => $isDeliveredMetaKey,
                'value'   => $isDelivered,
                'compare' => '=',
            ],
        ];

        $metaSql = get_meta_sql($metaQuery, 'post', $wpdb->posts, 'ID');

        $prefix = $wpdb->prefix;

        $query = "SELECT DISTINCT wc_order_items.order_id FROM {$prefix}woocommerce_order_items AS wc_order_items
                    JOIN {$prefix}woocommerce_order_itemmeta  AS wc_order_itemmeta ON wc_order_itemmeta.order_item_id = wc_order_items.order_item_id
                    JOIN {$prefix}posts ON wc_order_items.order_id={$prefix}posts.ID";

        $query .= $metaSql['join'];

        $query .= ' WHERE (wc_order_itemmeta.meta_key = "method_id" 
                        AND wc_order_itemmeta.meta_value LIKE "lpc_%" 
                        AND ' . $prefix . 'posts.post_type = "shop_order") ' . $metaSql['where'];

        // phpcs:disable
        $results = $wpdb->get_results($query);
        // phpcs:enable

        $ordersId = [];

        if ($results) {
            foreach ($results as $result) {
                $ordersId[] = $result->order_id;
            }
        }

        return $ordersId;
    }

    public static function getLpcOrdersPostMetaList($metaName) {
        global $wpdb;

        if (empty($metaName)) {
            return [];
        }

        $query = $wpdb->prepare(
            "SELECT DISTINCT {$wpdb->prefix}postmeta.meta_value
					FROM {$wpdb->prefix}postmeta
         			JOIN {$wpdb->prefix}woocommerce_order_items
              			ON {$wpdb->prefix}postmeta.post_id = {$wpdb->prefix}woocommerce_order_items.order_id
         			JOIN {$wpdb->prefix}woocommerce_order_itemmeta
              			ON {$wpdb->prefix}woocommerce_order_itemmeta.order_item_id = {$wpdb->prefix}woocommerce_order_items.order_item_id
					WHERE {$wpdb->prefix}woocommerce_order_itemmeta.meta_key = 'method_id'
	  					AND {$wpdb->prefix}woocommerce_order_itemmeta.meta_value LIKE %s
						AND {$wpdb->prefix}postmeta.meta_key = %s
					ORDER BY {$wpdb->prefix}postmeta.meta_value ASC",
            'lpc_%',
            $metaName
        );

        return $wpdb->get_col($query);  //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    public static function getLpcOrdersShippingMethods() {
        global $wpdb;

        return $wpdb->get_col(
            "SELECT DISTINCT {$wpdb->prefix}woocommerce_order_items.order_item_name
					FROM {$wpdb->prefix}woocommerce_order_items
                    JOIN {$wpdb->prefix}woocommerce_order_itemmeta
                        ON {$wpdb->prefix}woocommerce_order_itemmeta.order_item_id = {$wpdb->prefix}woocommerce_order_items.order_item_id
					WHERE {$wpdb->prefix}woocommerce_order_itemmeta.meta_key = 'method_id'
                        AND {$wpdb->prefix}woocommerce_order_itemmeta.meta_value LIKE 'lpc_%'
                        AND {$wpdb->prefix}woocommerce_order_items.order_item_type = 'shipping'
  					ORDER BY {$wpdb->prefix}woocommerce_order_items.order_item_name ASC;"
        );
    }

    public static function getLpcOrdersWooStatuses() {
        global $wpdb;

        return $wpdb->get_col(
            "SELECT DISTINCT {$wpdb->prefix}posts.post_status
					FROM {$wpdb->prefix}posts
         			JOIN {$wpdb->prefix}woocommerce_order_items
        				ON {$wpdb->prefix}posts.id = {$wpdb->prefix}woocommerce_order_items.order_id
        			JOIN {$wpdb->prefix}woocommerce_order_itemmeta
        				ON {$wpdb->prefix}woocommerce_order_itemmeta.order_item_id = {$wpdb->prefix}woocommerce_order_items.order_item_id
					WHERE {$wpdb->prefix}woocommerce_order_itemmeta.meta_key = 'method_id' 
					    AND {$wpdb->prefix}posts.post_type = 'shop_order' 
  						AND {$wpdb->prefix}woocommerce_order_itemmeta.meta_value LIKE 'lpc_%'
					ORDER BY {$wpdb->prefix}posts.post_status ASC"
        );
    }

    protected static function formatTextForSql(&$text) {
        $text = "'" . $text . "'";
    }

    protected static function andCriterion($criterion) {
        global $wpdb;

        return " AND {$wpdb->prefix}woocommerce_order_items.order_id IN 
			                   (SELECT {$wpdb->prefix}postmeta.post_id FROM {$wpdb->prefix}postmeta WHERE
			                        (meta_key='_shipping_first_name' AND meta_value LIKE '%$criterion%')
			                        OR (meta_key='_shipping_last_name' AND meta_value LIKE '%$criterion%')
			                        OR (meta_key='_shipping_postcode' AND meta_value = '$criterion')
			                        OR (meta_key='_shipping_city' AND meta_value LIKE '%$criterion%')
			                        OR (meta_key='lpc_outward_parcel_number' AND meta_value LIKE '%$criterion%')
			                        OR (meta_key='_shipping_country' AND meta_value = '$criterion')
			                        OR (post_id LIKE '%$criterion%')) ";
    }

    protected static function getOrderBy($args) {
        global $wpdb;
        if (empty($args['orderby'])) {
            return " ORDER BY {$wpdb->prefix}posts.post_date DESC ";
        }

        switch ($args['orderby']) {
            case 'date':
                $ord = 'posts.post_date';
                break;
            case 'id':
                $ord = 'woocommerce_order_items.order_id';
                break;
            case 'customer':
            case 'address':
            case 'country':
            case 'shipping-status':
            case 'lpc-bordereau':
                $ord = 'postmeta.meta_value';
                break;
            case 'shipping-method':
                $ord = 'woocommerce_order_items.order_item_name';
                break;
            case 'woo-status':
                $ord = 'posts.post_status';
                break;
            default:
                $ord = 'posts.post_date';
                break;
        }

        $ord = " ORDER BY {$wpdb->prefix}" . $ord . ' ';
        if (!empty($args['order'])) {
            $ord .= $args['order'] . ' ';
        }

        return $ord;
    }

    protected static function getMetaJoin($args) {
        global $wpdb;

        if (empty($args['orderby'])) {
            return ' ';
        }

        switch ($args['orderby']) {
            case 'customer':
                $where = "postmeta . meta_key = '_shipping_first_name'";
                break;
            case 'address':
                $where = "postmeta . meta_key = '_shipping_address_1'";
                break;
            case 'country':
                $where = "postmeta . meta_key = '_shipping_country'";
                break;
            case 'shipping-method':
                $where = "woocommerce_order_items . order_item_type = 'shipping'";
                break;
            case 'shipping-status':
                $where = "postmeta . meta_key = '" . esc_sql(LpcUnifiedTrackingApi::LAST_EVENT_INTERNAL_CODE_META_KEY) . "'";
                break;
            case 'lpc-bordereau':
                $where = "postmeta . meta_key = 'lpc_bordereau_id'";
                break;
            default:
                $where = ' ';
                break;
        }

        if (' ' !== $where) {
            $where = " LEFT JOIN {$wpdb->prefix}postmeta ON {$wpdb->prefix}postmeta.post_id = {$wpdb->prefix}woocommerce_order_items.order_id AND " . $wpdb->prefix . $where . ' ';
        }

        return $where;
    }

    protected static function addFilter($requestFilters = []) {
        $filters = [];
        global $wpdb;

        $filters[] = "{$wpdb->prefix}woocommerce_order_itemmeta.meta_key = 'method_id'";
        $filters[] = "{$wpdb->prefix}woocommerce_order_itemmeta.meta_value LIKE 'lpc_%'";

        if ($requestFilters['search']) {
            $search = $requestFilters['search'];

            $filters['search'] = '(';

            // ID
            $filters['search'] .= "{$wpdb->prefix}woocommerce_order_items.order_id LIKE '%{$search}%'";

            // Date
            $filters['search'] .= " OR {$wpdb->prefix}woocommerce_order_items.order_id IN (
			SELECT {$wpdb->prefix}posts.ID
			FROM {$wpdb->prefix}posts
			WHERE DATE_FORMAT({$wpdb->prefix}posts.post_date_gmt, '%m-%d-%Y') LIKE '%{$search}%')";

            // Customer Name, Shipping Address and Bordereau ID
            $filters['search'] .= " OR {$wpdb->prefix}woocommerce_order_items.order_id IN (
				SELECT {$wpdb->prefix}postmeta.post_id 
				FROM {$wpdb->prefix}postmeta 
				WHERE (
					{$wpdb->prefix}postmeta.meta_key = '_shipping_first_name'
					OR {$wpdb->prefix}postmeta.meta_key = '_shipping_last_name'
					OR {$wpdb->prefix}postmeta.meta_key = '_shipping_address_1'
					OR {$wpdb->prefix}postmeta.meta_key = '_shipping_address_2'
					OR {$wpdb->prefix}postmeta.meta_key = '_shipping_city'
					OR {$wpdb->prefix}postmeta.meta_key = '_shipping_country'
					OR {$wpdb->prefix}postmeta.meta_key = '_shipping_postcode'
					OR {$wpdb->prefix}postmeta.meta_key = 'lpc_bordereau_id'
				) AND {$wpdb->prefix}postmeta.meta_value LIKE '%{$search}%'
			)";

            // Shipping method
            $filters['search'] .= " OR {$wpdb->prefix}woocommerce_order_items.order_id IN (
				SELECT {$wpdb->prefix}woocommerce_order_items.order_id
				FROM {$wpdb->prefix}woocommerce_order_items
				WHERE {$wpdb->prefix}woocommerce_order_items.order_item_type = 'shipping'
					AND {$wpdb->prefix}woocommerce_order_items.order_item_name LIKE '%{$search}%')";

            // WooCommerce Order Status
            $filters['search'] .= " OR {$wpdb->prefix}posts.post_status LIKE '%{$search}%'";

            // Outward label number
            $filters['search'] .= " OR {$wpdb->prefix}woocommerce_order_items.order_id IN (
				SELECT {$wpdb->prefix}lpc_outward_label.order_id
				FROM {$wpdb->prefix}lpc_outward_label
				WHERE {$wpdb->prefix}lpc_outward_label.tracking_number LIKE '%{$search}%'
			)";

            // Inward label number
            $filters['search'] .= " OR {$wpdb->prefix}woocommerce_order_items.order_id IN (
				SELECT {$wpdb->prefix}lpc_inward_label.order_id
				FROM {$wpdb->prefix}lpc_inward_label
				WHERE {$wpdb->prefix}lpc_inward_label.tracking_number LIKE '%{$search}%'
			)";

            $filters['search'] .= ')';
        }

        if (isset($requestFilters['label_type'])) {
            $labelTypes = array_filter(
                $requestFilters['label_type'],
                function ($labelType) {
                    return !empty($labelType);
                }
            );

            if (in_array('inward', $labelTypes)) {
                $filters[] = "{$wpdb->prefix}woocommerce_order_items.order_id IN (
				SELECT DISTINCT {$wpdb->prefix}lpc_inward_label.order_id
				FROM {$wpdb->prefix}lpc_inward_label
				WHERE {$wpdb->prefix}lpc_inward_label.tracking_number IS NOT NULL)";
            }

            if (in_array('outward', $labelTypes)) {
                $filters[] = "{$wpdb->prefix}woocommerce_order_items.order_id IN (
				SELECT DISTINCT {$wpdb->prefix}lpc_outward_label.order_id
				FROM {$wpdb->prefix}lpc_outward_label
				WHERE {$wpdb->prefix}lpc_outward_label.tracking_number IS NOT NULL)";
            }

            if (in_array('outward_printed', $labelTypes)) {
                $filters[] = "{$wpdb->prefix}woocommerce_order_items.order_id IN (
				SELECT DISTINCT {$wpdb->prefix}lpc_outward_label.order_id
				FROM {$wpdb->prefix}lpc_outward_label
				WHERE {$wpdb->prefix}lpc_outward_label.printed = 1)";
            }

            if (in_array('outward_not_printed', $labelTypes)) {
                $filters[] = "{$wpdb->prefix}woocommerce_order_items.order_id IN (
				SELECT DISTINCT {$wpdb->prefix}lpc_outward_label.order_id
				FROM {$wpdb->prefix}lpc_outward_label
				WHERE {$wpdb->prefix}lpc_outward_label.printed = 0)";
            }

            if (in_array('none', $labelTypes)) {
                $filters[] = "{$wpdb->prefix}woocommerce_order_items.order_id NOT IN (
				SELECT DISTINCT {$wpdb->prefix}lpc_inward_label.order_id
				FROM {$wpdb->prefix}lpc_inward_label)";

                $filters[] = "{$wpdb->prefix}woocommerce_order_items.order_id NOT IN (
				SELECT DISTINCT {$wpdb->prefix}lpc_outward_label.order_id
				FROM {$wpdb->prefix}lpc_outward_label)";
            }
        }

        if (isset($requestFilters['country'])) {
            $countries = array_filter(
                $requestFilters['country'],
                function ($country) {
                    return !empty($country);
                }
            );

            if (!empty($countries)) {
                $filters[] = "{$wpdb->prefix}woocommerce_order_items.order_id IN (
				SELECT {$wpdb->prefix}postmeta.post_id 
				FROM {$wpdb->prefix}postmeta 
				WHERE {$wpdb->prefix}postmeta.meta_key = '_shipping_country' 
					AND {$wpdb->prefix}postmeta.meta_value IN ('" . implode("', '", $countries) . "'))";
            }
        }

        if (isset($requestFilters['shipping_method'])) {
            $shippingMethods = array_filter(
                $requestFilters['shipping_method'],
                function ($shippingMethod) {
                    return !empty($shippingMethod);
                }
            );

            if (!empty($shippingMethods)) {
                $filters[] = "{$wpdb->prefix}woocommerce_order_items.order_id IN (
				SELECT {$wpdb->prefix}woocommerce_order_items.order_id
				FROM {$wpdb->prefix}woocommerce_order_items
				WHERE {$wpdb->prefix}woocommerce_order_items.order_item_type = 'shipping'
					AND {$wpdb->prefix}woocommerce_order_items.order_item_name IN ('" . implode(
                        "','",
                        $shippingMethods
                    ) . "'))";
            }
        }

        if (isset($requestFilters['status'])) {
            $status = array_filter(
                $requestFilters['status'],
                function ($oneStatus) {
                    return !empty($oneStatus);
                }
            );

            if (!empty($status)) {
                $filters[] = "{$wpdb->prefix}woocommerce_order_items.order_id IN (
				SELECT {$wpdb->prefix}postmeta.post_id
				FROM {$wpdb->prefix}postmeta
				WHERE {$wpdb->prefix}postmeta.meta_key = '" . esc_sql(LpcUnifiedTrackingApi::LAST_EVENT_INTERNAL_CODE_META_KEY) . "'
					AND {$wpdb->prefix}postmeta.meta_value IN ('" . implode("', '", $status) . "'))";
            }
        }

        if (isset($requestFilters['woo_status'])) {
            $wooStatus = array_filter(
                $requestFilters['woo_status'],
                function ($oneWooStatus) {
                    return !empty($oneWooStatus);
                }
            );

            if (!empty($wooStatus)) {
                $filters[] = "{$wpdb->prefix}posts.post_status IN ('" . implode("', '", $wooStatus) . "')";
            }
        }

        // Make sure we take only orders and not subscriptions
        $filters[] = $wpdb->prefix . 'posts.post_type = "shop_order"';

        return ' WHERE ' . implode(' AND ', $filters);
    }
}
