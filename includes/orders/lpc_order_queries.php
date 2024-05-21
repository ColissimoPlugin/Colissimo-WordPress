<?php

use Automattic\WooCommerce\Utilities\OrderUtil;

class LpcOrderQueries {
    public static function getLpcOrders($currentPage = 0, $elementsPerPage = 0, $args = [], $filters = []): array {
        global $wpdb;

        $orderItems      = $wpdb->prefix . 'woocommerce_order_items';
        $orderItemMeta   = $wpdb->prefix . 'woocommerce_order_itemmeta';
        $lpcOutwardLabel = $wpdb->prefix . 'lpc_outward_label';

        $selection = ' DISTINCT ' . $orderItems . '.order_id';
        if (!empty($filters['no_slip'])) {
            $selection .= ', ' . $lpcOutwardLabel . '.label_created_at, ' . $lpcOutwardLabel . '.tracking_number';
        }

        if (self::isHposActive()) {
            $orders         = $wpdb->prefix . 'wc_orders';
            $ordersMeta     = $wpdb->prefix . 'wc_orders_meta';
            $orderAddresses = $wpdb->prefix . 'wc_order_addresses';

            $query = 'SELECT ' . $selection . ' 
                    FROM ' . $orderItems . ' 
                    JOIN ' . $orderItemMeta . ' ON ' . $orderItemMeta . '.order_item_id = ' . $orderItems . '.order_item_id 
                    JOIN ' . $orders . ' ON ' . $orders . '.id = ' . $orderItems . '.order_id';

            if (!empty($filters['no_slip'])) {
                $query .= ' JOIN ' . $lpcOutwardLabel . ' ON ' . $lpcOutwardLabel . '.order_id = ' . $orderItems . '.order_id AND ' . $lpcOutwardLabel . '.bordereau_id IS NULL';
            } else {
                $query .= ' LEFT JOIN ' . $lpcOutwardLabel . ' ON ' . $lpcOutwardLabel . '.order_id = ' . $orderItems . '.order_id';
            }

            $where = [];
            if (!empty($args['orderby'])) {
                $metaKey = '';
                switch ($args['orderby']) {
                    case 'shipping-method':
                        $where[] = $orderItems . ' . order_item_type = "shipping"';
                        break;
                    case 'shipping-status':
                        $metaKey = LpcUnifiedTrackingApi::LAST_EVENT_INTERNAL_CODE_META_KEY;
                        break;
                    case 'lpc-bordereau':
                        $metaKey = 'lpc_bordereau_id';
                        break;
                }

                if (in_array($args['orderby'], ['customer', 'address', 'country'])) {
                    $query .= ' LEFT JOIN ' . $orderAddresses . ' ON ' . $orderAddresses . '.order_id = ' . $orderItems . '.order_id AND ' . $orderAddresses . '.address_type = "shipping" ';
                } elseif (!empty($metaKey)) {
                    $query .= ' LEFT JOIN ' . $ordersMeta . ' ON ' . $ordersMeta . '.order_id = ' . $orderItems . '.order_id AND ' . $ordersMeta . '.meta_key = "' . esc_sql($metaKey) . '" ';
                }
            }

            $query .= self::addFilter($filters, $where);

            if (empty($args['orderby'])) {
                $query .= ' ORDER BY ' . $orders . '.date_created_gmt DESC ';
            } else {
                switch ($args['orderby']) {
                    case 'id':
                        $ord = $orderItems . '.order_id';
                        break;
                    case 'customer':
                        $ord = $orderAddresses . '.first_name';
                        break;
                    case 'address':
                        $ord = $orderAddresses . '.address_1';
                        break;
                    case 'country':
                        $ord = $orderAddresses . '.country';
                        break;
                    case 'shipping-method':
                        $ord = $orderItems . '.order_item_name';
                        break;
                    case 'shipping-status':
                    case 'lpc-bordereau':
                        $ord = $ordersMeta . '.meta_value';
                        break;
                    case 'woo-status':
                        $ord = $orders . '.status';
                        break;
                    default:
                        $ord = $orders . '.date_created_gmt';
                        break;
                }

                $ord = ' ORDER BY ' . $ord . ' ';
                if (!empty($args['order'])) {
                    $ord .= $args['order'] . ' ';
                }

                $query .= $ord;
            }
        } else {
            $posts    = $wpdb->prefix . 'posts';
            $postmeta = $wpdb->prefix . 'postmeta';

            $query = 'SELECT ' . $selection . ' 
                    FROM ' . $orderItems . ' 
                    JOIN ' . $orderItemMeta . ' ON ' . $orderItemMeta . '.order_item_id = ' . $orderItems . '.order_item_id 
                    JOIN ' . $posts . ' ON ' . $posts . '.ID = ' . $orderItems . '.order_id';

            if (!empty($filters['no_slip'])) {
                $query .= ' JOIN ' . $lpcOutwardLabel . ' ON ' . $lpcOutwardLabel . '.order_id = ' . $orderItems . '.order_id AND ' . $lpcOutwardLabel . '.bordereau_id IS NULL';
            } else {
                $query .= ' LEFT JOIN ' . $lpcOutwardLabel . ' ON ' . $lpcOutwardLabel . '.order_id = ' . $orderItems . '.order_id';
            }

            if (!empty($args['orderby'])) {
                switch ($args['orderby']) {
                    case 'customer':
                        $where = $postmeta . '.meta_key = "_shipping_first_name"';
                        break;
                    case 'address':
                        $where = $postmeta . '.meta_key = "_shipping_address_1"';
                        break;
                    case 'country':
                        $where = $postmeta . '.meta_key = "_shipping_country"';
                        break;
                    case 'shipping-method':
                        $where = $orderItems . '.order_item_type = "shipping"';
                        break;
                    case 'shipping-status':
                        $where = $postmeta . '.meta_key = "' . esc_sql(LpcUnifiedTrackingApi::LAST_EVENT_INTERNAL_CODE_META_KEY) . '"';
                        break;
                    case 'lpc-bordereau':
                        $where = $postmeta . '.meta_key = "lpc_bordereau_id"';
                        break;
                    default:
                        $where = ' ';
                        break;
                }

                if (' ' !== $where) {
                    $query .= ' LEFT JOIN ' . $postmeta . ' ON ' . $postmeta . '.post_id = ' . $orderItems . '.order_id AND ' . $where . ' ';
                }
            }

            $query .= self::addFilter($filters);

            if (empty($args['orderby'])) {
                $query .= ' ORDER BY ' . $posts . '.post_date DESC ';
            } else {
                switch ($args['orderby']) {
                    case 'id':
                        $ord = $orderItems . '.order_id';
                        break;
                    case 'customer':
                    case 'address':
                    case 'country':
                    case 'shipping-status':
                    case 'lpc-bordereau':
                        $ord = $postmeta . '.meta_value';
                        break;
                    case 'shipping-method':
                        $ord = $orderItems . '.order_item_name';
                        break;
                    case 'woo-status':
                        $ord = $posts . '.post_status';
                        break;
                    default:
                        $ord = $posts . '.post_date';
                        break;
                }

                $ord = ' ORDER BY ' . $ord . ' ';
                if (!empty($args['order'])) {
                    $ord .= $args['order'] . ' ';
                }

                $query .= $ord;
            }
        }

        if (0 < $currentPage && 0 < $elementsPerPage) {
            $offset = ($currentPage - 1) * $elementsPerPage;
            $query  .= ' LIMIT ' . $elementsPerPage . ' OFFSET ' . $offset;
        }

        // phpcs:disable
        $results = $wpdb->get_results($query);
        // phpcs:enable

        $ordersId = [];
        if ($results) {
            foreach ($results as $result) {
                $ordersId[] = [
                    'order_id'         => $result->order_id,
                    'label_created_at' => $result->label_created_at ?? null,
                    'tracking_number'  => $result->tracking_number ?? null,
                ];
            }
        }

        return $ordersId;
    }

    public static function countLpcOrders($filters = []) {
        global $wpdb;

        $orderItems      = $wpdb->prefix . 'woocommerce_order_items';
        $orderItemMeta   = $wpdb->prefix . 'woocommerce_order_itemmeta';
        $lpcOutwardLabel = $wpdb->prefix . 'lpc_outward_label';

        $query = 'SELECT COUNT(DISTINCT ' . $orderItems . '.order_id) AS nb FROM ' . $orderItems . ' 
                    JOIN ' . $orderItemMeta . ' ON ' . $orderItemMeta . '.order_item_id = ' . $orderItems . '.order_item_id ';

        if (self::isHposActive()) {
            $orders = $wpdb->prefix . 'wc_orders';
            $query  .= 'JOIN ' . $orders . ' ON ' . $orders . '.id = ' . $orderItems . '.order_id';
        } else {
            $posts = $wpdb->prefix . 'posts';
            $query .= 'JOIN ' . $posts . ' ON ' . $posts . '.ID = ' . $orderItems . '.order_id';
        }

        if (!empty($filters['no_slip'])) {
            $query .= ' JOIN ' . $lpcOutwardLabel . ' ON ' . $lpcOutwardLabel . '.order_id = ' . $orderItems . '.order_id AND bordereau_id IS NULL';
        } else {
            $query .= ' LEFT JOIN ' . $lpcOutwardLabel . ' ON ' . $lpcOutwardLabel . '.order_id = ' . $orderItems . '.order_id';
        }

        if (!empty($filters)) {
            $query .= self::addFilter($filters);
        }

        // phpcs:disable
        $result = $wpdb->get_results($query);
        // phpcs:enable

        if (!empty($result)) {
            return $result[0]->nb;
        }

        return 0;
    }

    public static function getLpcOrderIdsToRefreshDeliveryStatus(): array {
        global $wpdb;

        $orderItems    = $wpdb->prefix . 'woocommerce_order_items';
        $orderItemMeta = $wpdb->prefix . 'woocommerce_order_itemmeta';

        $timePeriod = '-' . LpcHelper::get_option('lpc_label_status_update_days', 90) . ' days';

        /**
         * Filter allowing to modify the time period for which the tracking status should be updated.
         *
         * @since 1.9.0
         */
        $timePeriod = apply_filters('lpc_update_delivery_status_period', $timePeriod);
        $fromDate   = date('Y-m-d', strtotime($timePeriod));

        $params = [
            $orderItemMeta . '.meta_key = "method_id"',
            $orderItemMeta . '.meta_value LIKE "lpc_%"',
        ];

        if (self::isHposActive()) {
            $orders     = $wpdb->prefix . 'wc_orders';
            $ordersMeta = $wpdb->prefix . 'wc_orders_meta';
            $query      = 'SELECT DISTINCT ' . $orderItems . '.order_id 
                    FROM ' . $orderItems . '
                    JOIN ' . $orderItemMeta . ' ON ' . $orderItemMeta . '.order_item_id = ' . $orderItems . '.order_item_id
                    JOIN ' . $orders . ' ON ' . $orders . '.id = ' . $orderItems . '.order_id
                    LEFT JOIN ' . $ordersMeta . ' ON ' . $ordersMeta . '.order_id = ' . $orderItems . '.order_id AND ' . $ordersMeta . '.meta_key = "_lpc_is_delivered"';
            $params[]   = $orders . '.type = "shop_order"';
            $params[]   = $orders . '.date_created_gmt > "' . esc_sql($fromDate) . '"';
            $params[]   = $ordersMeta . '.meta_value IS NULL OR ' . $ordersMeta . '.meta_value = "0"';
        } else {
            $posts    = $wpdb->prefix . 'posts';
            $postmeta = $wpdb->prefix . 'postmeta';
            $query    = 'SELECT DISTINCT ' . $orderItems . '.order_id 
                    FROM ' . $orderItems . '
                    JOIN ' . $orderItemMeta . ' ON ' . $orderItemMeta . '.order_item_id = ' . $orderItems . '.order_item_id
                    JOIN ' . $posts . ' ON ' . $posts . '.ID = ' . $orderItems . '.order_id
                    LEFT JOIN ' . $postmeta . ' ON ' . $postmeta . '.post_id = ' . $orderItems . '.order_id AND ' . $postmeta . '.meta_key = "_lpc_is_delivered"';
            $params[] = $posts . '.post_type = "shop_order"';
            $params[] = $posts . '.post_date > "' . esc_sql($fromDate) . '"';
            $params[] = $postmeta . '.meta_value IS NULL OR ' . $postmeta . '.meta_value = "0"';
        }

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

        $orderItems    = $wpdb->prefix . 'woocommerce_order_items';
        $orderItemMeta = $wpdb->prefix . 'woocommerce_order_itemmeta';

        $nbDays      = LpcHelper::get_option('lpc_day_purge', 30);
        $fromDate    = time() - $nbDays * DAY_IN_SECONDS;
        $isDelivered = LpcUnifiedTrackingApi::IS_DELIVERED_META_VALUE_TRUE;

        if (self::isHposActive()) {
            $orders     = $wpdb->prefix . 'wc_orders';
            $ordersMeta = $wpdb->prefix . 'wc_orders_meta';
            $query      = 'SELECT DISTINCT ' . $orderItems . '.order_id 
                FROM ' . $orderItems . '
                JOIN ' . $orderItemMeta . ' ON ' . $orderItemMeta . '.order_item_id = ' . $orderItems . '.order_item_id
                JOIN ' . $orders . ' ON ' . $orderItems . '.order_id = ' . $orders . '.id
                JOIN ' . $ordersMeta . ' AS lastEventDate ON lastEventDate.order_id = ' . $orders . '.id AND lastEventDate.meta_key = "' . LpcUnifiedTrackingApi::LAST_EVENT_DATE_META_KEY . '"
                JOIN ' . $ordersMeta . ' AS isDelivered ON isDelivered.order_id = ' . $orders . '.id AND isDelivered.meta_key = "' . LpcUnifiedTrackingApi::IS_DELIVERED_META_KEY . '"
                WHERE ' . $orderItemMeta . '.meta_key = "method_id" 
                    AND ' . $orderItemMeta . '.meta_value LIKE "lpc_%" 
                    AND ' . $orders . '.type = "shop_order" 
                    AND lastEventDate.meta_value < ' . $fromDate . '
                    AND isDelivered.meta_value = ' . intval($isDelivered);
        } else {
            $posts    = $wpdb->prefix . 'posts';
            $postmeta = $wpdb->prefix . 'postmeta';
            $query    = 'SELECT DISTINCT ' . $orderItems . '.order_id 
                FROM ' . $orderItems . '
                JOIN ' . $orderItemMeta . ' ON ' . $orderItemMeta . '.order_item_id = ' . $orderItems . '.order_item_id
                JOIN ' . $posts . ' ON  ' . $orderItems . '.order_id = ' . $posts . '.ID
                JOIN ' . $postmeta . ' AS lastEventDate ON lastEventDate.post_id = ' . $posts . '.ID AND lastEventDate.meta_key = "' . LpcUnifiedTrackingApi::LAST_EVENT_DATE_META_KEY . '"
                JOIN ' . $postmeta . ' AS isDelivered ON isDelivered.post_id = ' . $posts . '.ID AND isDelivered.meta_key = "' . LpcUnifiedTrackingApi::IS_DELIVERED_META_KEY . '"
                WHERE (' . $orderItemMeta . '.meta_key = "method_id" 
                    AND ' . $orderItemMeta . '.meta_value LIKE "lpc_%" 
                    AND ' . $posts . '.post_type = "shop_order") 
                    AND lastEventDate.meta_value < ' . $fromDate . '
                    AND isDelivered.meta_value = ' . intval($isDelivered);
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

    public static function getLpcOrdersPostMetaList(string $metaName, bool $isAddressMeta = false) {
        if (empty($metaName)) {
            return [];
        }

        global $wpdb;
        $orderItems    = $wpdb->prefix . 'woocommerce_order_items';
        $orderItemMeta = $wpdb->prefix . 'woocommerce_order_itemmeta';

        if (self::isHposActive()) {
            $orderAddresses = $wpdb->prefix . 'wc_order_addresses';
            if ($isAddressMeta) {
                [$none, $addressType, $addressPart] = explode('_', $metaName);
                // phpcs:disable
                $query = $wpdb->prepare(
                    'SELECT DISTINCT ' . $orderAddresses . '.' . $addressPart . '
					FROM ' . $orderAddresses . '
         			JOIN ' . $orderItems . '
              			ON ' . $orderAddresses . '.order_id = ' . $orderItems . '.order_id
         			JOIN ' . $orderItemMeta . '
              			ON ' . $orderItemMeta . '.order_item_id = ' . $orderItems . '.order_item_id
					WHERE ' . $orderItemMeta . '.meta_key = "method_id"
	  					AND ' . $orderItemMeta . '.meta_value LIKE %s
						AND ' . $orderAddresses . '.address_type = %s
					ORDER BY ' . $orderAddresses . '.' . $addressPart . ' ASC',
                    'lpc_%',
                    $addressType
                );
                // phpcs:enable
            } else {
                $ordersMeta = $wpdb->prefix . 'wc_orders_meta';
                // phpcs:disable
                $query = $wpdb->prepare(
                    'SELECT DISTINCT ' . $ordersMeta . '.meta_value
					FROM ' . $ordersMeta . '
         			JOIN ' . $orderItems . '
              			ON ' . $ordersMeta . '.order_id = ' . $orderItems . '.order_id
         			JOIN ' . $orderItemMeta . '
              			ON ' . $orderItemMeta . '.order_item_id = ' . $orderItems . '.order_item_id
					WHERE ' . $orderItemMeta . '.meta_key = "method_id"
	  					AND ' . $orderItemMeta . '.meta_value LIKE %s
						AND ' . $ordersMeta . '.meta_key = %s
					ORDER BY ' . $ordersMeta . '.meta_value ASC',
                    'lpc_%',
                    $metaName
                );
                // phpcs:enable
            }
        } else {
            $postmeta = $wpdb->prefix . 'postmeta';
            // phpcs:disable
            $query = $wpdb->prepare(
                'SELECT DISTINCT ' . $postmeta . '.meta_value
					FROM ' . $postmeta . '
         			JOIN ' . $orderItems . '
              			ON ' . $postmeta . '.post_id = ' . $orderItems . '.order_id
         			JOIN ' . $orderItemMeta . '
              			ON ' . $orderItemMeta . '.order_item_id = ' . $orderItems . '.order_item_id
					WHERE ' . $orderItemMeta . '.meta_key = "method_id"
	  					AND ' . $orderItemMeta . '.meta_value LIKE %s
						AND ' . $postmeta . '.meta_key = %s
					ORDER BY ' . $postmeta . '.meta_value ASC',
                'lpc_%',
                $metaName
            );
            // phpcs:enable
        }

        return $wpdb->get_col($query);  //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    public static function getLpcOrdersShippingMethods() {
        global $wpdb;
        $orderItems    = $wpdb->prefix . 'woocommerce_order_items';
        $orderItemMeta = $wpdb->prefix . 'woocommerce_order_itemmeta';

        // phpcs:disable
        return $wpdb->get_col(
            'SELECT DISTINCT ' . $orderItems . '.order_item_name
					FROM ' . $orderItems . '
                    JOIN ' . $orderItemMeta . '
                        ON ' . $orderItemMeta . '.order_item_id = ' . $orderItems . '.order_item_id
					WHERE ' . $orderItemMeta . '.meta_key = "method_id"
                        AND ' . $orderItemMeta . '.meta_value LIKE "lpc_%"
                        AND ' . $orderItems . '.order_item_type = "shipping"
  					ORDER BY ' . $orderItems . '.order_item_name ASC;'
        );
        // phpcs:enable
    }

    public static function getLpcOrdersWooStatuses() {
        global $wpdb;
        $orderItems    = $wpdb->prefix . 'woocommerce_order_items';
        $orderItemMeta = $wpdb->prefix . 'woocommerce_order_itemmeta';

        if (self::isHposActive()) {
            $orders = $wpdb->prefix . 'wc_orders';

            // phpcs:disable
            return $wpdb->get_col(
                'SELECT DISTINCT ' . $orders . '.status
					FROM ' . $orders . '
         			JOIN ' . $orderItems . '
        				ON ' . $orders . '.id = ' . $orderItems . '.order_id
        			JOIN ' . $orderItemMeta . '
        				ON ' . $orderItemMeta . '.order_item_id = ' . $orderItems . '.order_item_id
					WHERE ' . $orderItemMeta . '.meta_key = "method_id" 
					    AND ' . $orders . '.type = "shop_order" 
  						AND ' . $orderItemMeta . '.meta_value LIKE "lpc_%"
					ORDER BY ' . $orders . '.status ASC'
            );
            // phpcs:enable
        } else {
            $posts = $wpdb->prefix . 'posts';

            // phpcs:disable
            return $wpdb->get_col(
                'SELECT DISTINCT ' . $posts . '.post_status
					FROM ' . $posts . '
         			JOIN ' . $orderItems . '
        				ON ' . $posts . '.id = ' . $orderItems . '.order_id
        			JOIN ' . $orderItemMeta . '
        				ON ' . $orderItemMeta . '.order_item_id = ' . $orderItems . '.order_item_id
					WHERE ' . $orderItemMeta . '.meta_key = "method_id" 
					    AND ' . $posts . '.post_type = "shop_order" 
  						AND ' . $orderItemMeta . '.meta_value LIKE "lpc_%"
					ORDER BY ' . $posts . '.post_status ASC'
            );
            // phpcs:enable
        }
    }

    protected static function addFilter($requestFilters = [], $filters = []): string {
        global $wpdb;

        $orderItems      = $wpdb->prefix . 'woocommerce_order_items';
        $orderItemMeta   = $wpdb->prefix . 'woocommerce_order_itemmeta';
        $posts           = $wpdb->prefix . 'posts';
        $postmeta        = $wpdb->prefix . 'postmeta';
        $lpcOutwardLabel = $wpdb->prefix . 'lpc_outward_label';
        $lpcInwardLabel  = $wpdb->prefix . 'lpc_inward_label';

        $filters[] = $orderItemMeta . '.meta_key = "method_id"';
        $filters[] = $orderItemMeta . '.meta_value LIKE "lpc_%"';

        if (self::isHposActive()) {
            $orders         = $wpdb->prefix . 'wc_orders';
            $ordersMeta     = $wpdb->prefix . 'wc_orders_meta';
            $orderAddresses = $wpdb->prefix . 'wc_order_addresses';

            if (!empty($requestFilters['search'])) {
                $search = $requestFilters['search'];

                $filters['search'] = '(';

                // ID
                $filters['search'] .= $orderItems . '.order_id LIKE "%' . esc_sql($search) . '%"';

                // Date
                $filters['search'] .= ' OR ' . $orderItems . '.order_id IN (
                    SELECT ' . $orders . '.id
                    FROM ' . $orders . '
                    WHERE DATE_FORMAT(' . $orders . '.date_created_gmt, "%m-%d-%Y") LIKE "%' . esc_sql($search) . '%")';

                // Customer Name and Shipping Address
                $filters['search'] .= ' OR ' . $orderItems . '.order_id IN (
                    SELECT ' . $orderAddresses . '.order_id 
                    FROM ' . $orderAddresses . ' 
                    WHERE (
                        ' . $orderAddresses . '.first_name LIKE "%' . esc_sql($search) . '%"
                        OR ' . $orderAddresses . '.last_name LIKE "%' . esc_sql($search) . '%"
                        OR ' . $orderAddresses . '.address_1 LIKE "%' . esc_sql($search) . '%"
                        OR ' . $orderAddresses . '.address_2 LIKE "%' . esc_sql($search) . '%"
                        OR ' . $orderAddresses . '.city LIKE "%' . esc_sql($search) . '%"
                        OR ' . $orderAddresses . '.country LIKE "%' . esc_sql($search) . '%"
                        OR ' . $orderAddresses . '.postcode LIKE "%' . esc_sql($search) . '%"
                    ) AND ' . $orderAddresses . '.address_type = "shipping"
                )';

                // Slip ID and Outward label number
                $filters['search'] .= ' OR ' . $orderItems . '.order_id IN (
                    SELECT ' . $lpcOutwardLabel . '.order_id 
                    FROM ' . $lpcOutwardLabel . ' 
                    WHERE (
                        ' . $lpcOutwardLabel . '.bordereau_id LIKE "%' . esc_sql($search) . '%"
                        OR ' . $lpcOutwardLabel . '.tracking_number LIKE "%' . esc_sql($search) . '%"
                    )
                )';

                // Shipping method
                $filters['search'] .= ' OR ' . $orderItems . '.order_id IN (
                    SELECT ' . $orderItems . '.order_id
                    FROM ' . $orderItems . '
                    WHERE ' . $orderItems . '.order_item_type = "shipping"
                        AND ' . $orderItems . '.order_item_name LIKE "%' . esc_sql($search) . '%")';

                // WooCommerce Order Status
                $filters['search'] .= ' OR ' . $orders . '.status LIKE "%' . esc_sql($search) . '%"';

                // Inward label number
                $filters['search'] .= ' OR ' . $orderItems . '.order_id IN (
                    SELECT ' . $lpcInwardLabel . '.order_id
                    FROM ' . $lpcInwardLabel . '
                    WHERE ' . $lpcInwardLabel . '.tracking_number LIKE "%' . esc_sql($search) . '%"
			    )';

                $filters['search'] .= ')';
            }

            if (isset($requestFilters['country'])) {
                $countries = array_filter(
                    $requestFilters['country'],
                    function ($country) {
                        return !empty($country);
                    }
                );

                if (!empty($countries)) {
                    $filters[] .= $orderItems . '.order_id IN (
                        SELECT ' . $orderAddresses . '.order_id 
                        FROM ' . $orderAddresses . ' 
                        WHERE
                            ' . $orderAddresses . '.country IN ("' . implode('", "', $countries) . '")
                            AND ' . $orderAddresses . '.address_type = "shipping"
                    )';
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
                    $filters[] = $orderItems . '.order_id IN (
                        SELECT ' . $ordersMeta . '.order_id
                        FROM ' . $ordersMeta . '
                        WHERE ' . $ordersMeta . '.meta_key = "' . esc_sql(LpcUnifiedTrackingApi::LAST_EVENT_INTERNAL_CODE_META_KEY) . '"
                            AND ' . $ordersMeta . '.meta_value IN ("' . implode('", "', $status) . '"))';
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
                    $filters[] = $orders . '.status IN ("' . implode('", "', $wooStatus) . '")';
                }
            }

            // Make sure we take only orders and not subscriptions
            $filters[] = $orders . '.type = "shop_order"';
        } else {
            if (!empty($requestFilters['search'])) {
                $search = $requestFilters['search'];

                $filters['search'] = '(';

                // ID
                $filters['search'] .= $orderItems . '.order_id LIKE "%' . esc_sql($search) . '%"';

                // Date
                $filters['search'] .= ' OR ' . $orderItems . '.order_id IN (
                    SELECT ' . $posts . '.ID
                    FROM ' . $posts . '
                    WHERE DATE_FORMAT(' . $posts . '.post_date_gmt, "%m-%d-%Y") LIKE "%' . esc_sql($search) . '%")';

                // Customer Name, Shipping Address and Bordereau ID
                $filters['search'] .= ' OR ' . $orderItems . '.order_id IN (
                    SELECT ' . $postmeta . '.post_id 
                    FROM ' . $postmeta . ' 
                    WHERE (
                        ' . $postmeta . '.meta_key = "_shipping_first_name"
                        OR ' . $postmeta . '.meta_key = "_shipping_last_name"
                        OR ' . $postmeta . '.meta_key = "_shipping_address_1"
                        OR ' . $postmeta . '.meta_key = "_shipping_address_2"
                        OR ' . $postmeta . '.meta_key = "_shipping_city"
                        OR ' . $postmeta . '.meta_key = "_shipping_country"
                        OR ' . $postmeta . '.meta_key = "_shipping_postcode"
                        OR ' . $postmeta . '.meta_key = "lpc_bordereau_id"
                    ) AND ' . $postmeta . '.meta_value LIKE "%' . esc_sql($search) . '%"
                )';

                // Shipping method
                $filters['search'] .= ' OR ' . $orderItems . '.order_id IN (
                    SELECT ' . $orderItems . '.order_id
                    FROM ' . $orderItems . '
                    WHERE ' . $orderItems . '.order_item_type = "shipping"
                        AND ' . $orderItems . '.order_item_name LIKE "%' . esc_sql($search) . '%")';

                // WooCommerce Order Status
                $filters['search'] .= ' OR ' . $posts . '.post_status LIKE "%' . esc_sql($search) . '%"';

                // Outward label number
                $filters['search'] .= ' OR ' . $orderItems . '.order_id IN (
                    SELECT ' . $lpcOutwardLabel . '.order_id
                    FROM ' . $lpcOutwardLabel . '
                    WHERE ' . $lpcOutwardLabel . '.tracking_number LIKE "%' . esc_sql($search) . '%"
                )';

                // Inward label number
                $filters['search'] .= ' OR ' . $orderItems . '.order_id IN (
                    SELECT ' . $lpcInwardLabel . '.order_id
                    FROM ' . $lpcInwardLabel . '
                    WHERE ' . $lpcInwardLabel . '.tracking_number LIKE "%' . esc_sql($search) . '%"
                )';

                $filters['search'] .= ')';
            }

            if (isset($requestFilters['country'])) {
                $countries = array_filter(
                    $requestFilters['country'],
                    function ($country) {
                        return !empty($country);
                    }
                );

                if (!empty($countries)) {
                    $filters[] = $orderItems . '.order_id IN (
                        SELECT ' . $postmeta . '.post_id 
                        FROM ' . $postmeta . ' 
                        WHERE ' . $postmeta . '.meta_key = "_shipping_country"
                            AND ' . $postmeta . '.meta_value IN ("' . implode('", "', $countries) . '"))';
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
                    $filters[] = $orderItems . '.order_id IN (
                        SELECT ' . $postmeta . '.post_id
                        FROM ' . $postmeta . '
                        WHERE ' . $postmeta . '.meta_key = "' . esc_sql(LpcUnifiedTrackingApi::LAST_EVENT_INTERNAL_CODE_META_KEY) . '"
                            AND ' . $postmeta . '.meta_value IN ("' . implode('", "', $status) . '"))';
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
                    $filters[] = $posts . '.post_status IN ("' . implode('", "', $wooStatus) . '")';
                }
            }

            // Make sure we take only orders and not subscriptions
            $filters[] = $posts . '.post_type = "shop_order"';
        }

        if (isset($requestFilters['label_type'])) {
            $labelTypes = array_filter(
                $requestFilters['label_type'],
                function ($labelType) {
                    return !empty($labelType);
                }
            );

            if (in_array('inward', $labelTypes)) {
                $filters[] = $orderItems . '.order_id IN (
                    SELECT DISTINCT ' . $lpcInwardLabel . '.order_id
                    FROM ' . $lpcInwardLabel . '
                    WHERE ' . $lpcInwardLabel . '.tracking_number IS NOT NULL)';
            }

            if (in_array('outward', $labelTypes)) {
                $filters[] = $orderItems . '.order_id IN (
                    SELECT DISTINCT ' . $lpcOutwardLabel . '.order_id
                    FROM ' . $lpcOutwardLabel . '
                    WHERE ' . $lpcOutwardLabel . '.tracking_number IS NOT NULL)';
            }

            if (in_array('outward_printed', $labelTypes)) {
                $filters[] = $orderItems . '.order_id IN (
                    SELECT DISTINCT ' . $lpcOutwardLabel . '.order_id
                    FROM ' . $lpcOutwardLabel . '
                    WHERE ' . $lpcOutwardLabel . '.printed = 1)';
            }

            if (in_array('outward_not_printed', $labelTypes)) {
                $filters[] = $orderItems . '.order_id IN (
                    SELECT DISTINCT ' . $lpcOutwardLabel . '.order_id
                    FROM ' . $lpcOutwardLabel . '
                    WHERE ' . $lpcOutwardLabel . '.printed = 0)';
            }

            if (in_array('none', $labelTypes)) {
                $filters[] = $orderItems . '.order_id NOT IN (
                    SELECT DISTINCT ' . $lpcInwardLabel . '.order_id
                    FROM ' . $lpcInwardLabel . ')';

                $filters[] = $orderItems . '.order_id NOT IN (
                    SELECT DISTINCT ' . $lpcOutwardLabel . '.order_id
                    FROM ' . $lpcOutwardLabel . ')';
            }
        }

        if (!empty($requestFilters['label_start_date'])) {
            $filters[] = $lpcOutwardLabel . '.label_created_at > "' . esc_sql($requestFilters['label_start_date']) . '"';
        }

        if (!empty($requestFilters['label_end_date'])) {
            $filters[] = $lpcOutwardLabel . '.label_created_at < "' . esc_sql($requestFilters['label_end_date']) . '"';
        }

        if (isset($requestFilters['shipping_method'])) {
            $shippingMethods = array_filter(
                $requestFilters['shipping_method'],
                function ($shippingMethod) {
                    return !empty($shippingMethod);
                }
            );

            if (!empty($shippingMethods)) {
                $filters[] = $orderItems . '.order_id IN (
                    SELECT ' . $orderItems . '.order_id
                    FROM ' . $orderItems . '
                    WHERE ' . $orderItems . '.order_item_type = "shipping"
                        AND ' . $orderItems . '.order_item_name IN ("' . implode('","', $shippingMethods) . '"))';
            }
        }

        return ' WHERE ' . implode(' AND ', $filters);
    }

    public static function isHposActive(): bool {
        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && method_exists(
                OrderUtil::class,
                'custom_orders_table_usage_is_enabled'
            ) && OrderUtil::custom_orders_table_usage_is_enabled()) {
            return true;
        } else {
            return false;
        }
    }
}
