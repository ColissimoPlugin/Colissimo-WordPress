<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
require_once LPC_INCLUDES . 'orders' . DS . 'lpc_order_queries.php';

class LpcOrdersTable extends WP_List_Table {
    const BULK_ACTION_IDS_PARAM_NAME = 'bulk-lpc_action_id';
    const BULK_BORDEREAU_GENERATION_ACTION_NAME = 'bulk-bordereau_generation';
    const BULK_LABEL_DOWNLOAD_ACTION_NAME = 'bulk-label_download';
    const BULK_LABEL_GENERATION_OUTWARD_ACTION_NAME = 'bulk-label_generation_outward';
    const BULK_LABEL_GENERATION_INWARD_ACTION_NAME = 'bulk-label_generation_inward';
    const BULK_LABEL_PRINT_OUTWARD_ACTION_NAME = 'bulk-label_print_outward';
    const BULK_LABEL_PRINT_INWARD_ACTION_NAME = 'bulk-label_print_inward';
    const BULK_LABEL_PRINT_ACTION_NAME = 'bulk-label_print';
    const BULK_LABEL_DELETE_LABEL = 'bulk-delete_label';

    /** @var LpcBordereauGeneration */
    protected $bordereauGeneration;
    /** @var LpcUnifiedTrackingApi */
    protected $unifiedTrackingApi;
    /** @var LpcBordereauDownloadAction */
    protected $bordereauDownloadAction;
    /** @var LpcLabelPackagerDownloadAction */
    protected $labelPackagerDownloadAction;
    /** @var LpcLabelGenerationOutward */
    protected $labelGenerationOutward;
    /** @var LpcLabelGenerationInward */
    protected $labelGenerationInward;
    /** @var LpcLabelPrintAction */
    protected $labelPrintAction;
    /** @var LpcColissimoStatus */
    protected $colissimoStatus;
    /** @var LpcUpdateStatusesAction */
    protected $updateStatuses;
    /** @var LpcLabelQueries */
    protected $labelQueries;
    /** @var LpcOutwardLabelDb */
    protected $outwardLabelDb;
    /** @var LpcInwardLabelDb */
    protected $inwardLabelDb;
    /** @var LpcOutwardLabelDb */
    protected $labelOutwardImport;
    /** @var LpcLabelPurge */
    protected $lpcLabelPurge;
    /** @var LpcBordereauQueries */
    protected $bordereauQueries;

    public function __construct() {
        parent::__construct();

        $this->bordereauGeneration         = LpcRegister::get('bordereauGeneration');
        $this->unifiedTrackingApi          = LpcRegister::get('unifiedTrackingApi');
        $this->bordereauDownloadAction     = LpcRegister::get('bordereauDownloadAction');
        $this->labelPackagerDownloadAction = LpcRegister::get('labelPackagerDownloadAction');
        $this->labelGenerationOutward      = LpcRegister::get('labelGenerationOutward');
        $this->labelGenerationInward       = LpcRegister::get('labelGenerationInward');
        $this->updateStatuses              = LpcRegister::get('updateStatusesAction');
        $this->labelPrintAction            = LpcRegister::get('labelPrintAction');
        $this->colissimoStatus             = LpcRegister::get('colissimoStatus');
        $this->labelQueries                = LpcRegister::get('labelQueries');
        $this->outwardLabelDb              = LpcRegister::get('outwardLabelDb');
        $this->labelOutwardImport          = LpcRegister::get('labelOutwardImport');
        $this->inwardLabelDb               = LpcRegister::get('inwardLabelDb');
        $this->lpcLabelPurge               = LpcRegister::get('labelPurge');
        $this->bordereauQueries            = LpcRegister::get('bordereauQueries');
    }

    public function get_columns() {
        $columns = [
            'cb'                  => '<input type="checkbox" />',
            'lpc-id'              => __('ID', 'wc_colissimo'),
            'lpc-date'            => __('Date', 'wc_colissimo'),
            'lpc-customer'        => __('Customer', 'wc_colissimo'),
            'lpc-address'         => __('Address', 'wc_colissimo'),
            'lpc-country'         => __('Country', 'wc_colissimo'),
            'lpc-shipping-method' => __('Shipping method', 'wc_colissimo'),
            'lpc-woo-status'      => __('Order status', 'wc_colissimo'),
            'lpc-label'           => sprintf(
                '%s (<span id="lpc__orders_listing__title__outward">%s</span> / <span id="lpc__orders_listing__title__inward">%s</span> / <span id="lpc__orders_listing__title__bordereau">%s</span>)',
                __('Labels', 'wc_colissimo'),
                strtolower(__('Outward', 'wc_colissimo')),
                strtolower(__('Inward', 'wc_colissimo')),
                strtolower(__('Bordereau', 'wc_colissimo'))
            ),
        ];

        return array_map(
            function ($v) {
                return <<<END_HTML
<span style="font-weight:bold;">$v</span>
END_HTML;
            },
            $columns
        );
    }

    public function prepare_items($args = []) {
        $this->process_bulk_action();

        $optionsFiltersMatchRequestsKey = [
            'lpc_orders_filters_country'         => 'order_country',
            'lpc_orders_filters_shipping_method' => 'order_shipping_method',
            'lpc_orders_filters_status'          => 'order_status',
            'lpc_orders_filters_label_type'      => 'label_type',
            'lpc_orders_filters_woo_status'      => 'order_woo_status',
        ];

        foreach ($optionsFiltersMatchRequestsKey as $oneOptionFilter => $oneRequestKey) {
            if (isset($_REQUEST[$oneRequestKey])) {
                $requestValue = array_map('sanitize_text_field', wp_unslash($_REQUEST[$oneRequestKey]));

                if (false === update_option($oneOptionFilter, $requestValue)) {
                    add_option($oneOptionFilter, $requestValue);
                }
            }
        }

        $filters = $this->lpcGetFilters();

        $columns      = $this->get_columns();
        $hidden       = [];
        $sortable     = $this->get_sortable_columns();
        $total_items  = LpcOrderQueries::countLpcOrders($args, $filters);
        $current_page = $this->get_pagenum();
        $user         = get_current_user_id();
        $screen       = get_current_screen();
        $option       = $screen->get_option('per_page', 'option');

        $per_page = get_user_meta($user, $option, true);

        if (empty($per_page) || $per_page < 1) {
            $per_page = $screen->get_option('per_page', 'default');
        }

        $this->set_pagination_args(
            [
                'total_items' => $total_items,
                'per_page'    => $per_page,
            ]
        );

        $this->_column_headers = [$columns, $hidden, $sortable];
        $this->items           = $this->get_data($current_page, $per_page, $args, $filters);
    }

    protected function column_default($item, $column_name) {
        return $item[$column_name];
    }

    protected function get_data($current_page = 0, $per_page = 0, $args = [], $filters = []): array {
        $data      = [];
        $ordersIds = LpcOrderQueries::getLpcOrders($current_page, $per_page, $args, $filters);

        $trackingNumbers     = $this->getTrackingNumbersFormatted($ordersIds);
        $ordersOutwardFailed = get_option(LpcLabelGenerationOutward::ORDERS_OUTWARD_PARCEL_FAILED, []);

        foreach ($ordersIds as $orderId) {
            if (strpos(get_post_status($orderId), 'draft') !== false) {
                continue;
            }

            try {
                $wc_order = new WC_Order($orderId);
            } catch (Exception $exception) {
                continue;
            }

            $address = $wc_order->get_shipping_address_1();
            $address .= !empty($wc_order->get_shipping_address_2()) ?
                '<br>' . $wc_order->get_shipping_address_2()
                : '';
            $address .= '<br>' . $wc_order->get_shipping_postcode() . ' ' . $wc_order->get_shipping_city();

            if (current_user_can('lpc_manage_labels')) {
                $labels = '<div class="lpc_generate_outward_label lpc_generate_label">
								<span class="dashicons dashicons-plus lpc_generate_label_dashicon" '
                          . $this->labelQueries->getLabelOutwardGenerateAttr($orderId) . '></span>'
                          . __('Generate outward label', 'wc_colissimo') . '
								</div><br>';

                if (!empty($ordersOutwardFailed[$orderId])) {
                    $labels .= '<div class="lpc_outward_label_error">';
                    $labels .= '<span class="dashicons dashicons-warning lpc_outward_label_error_icon"></span>';
                    $labels .= sprintf(__('The label couldn\'t be generated: %s', 'wc_colissimo'), __($ordersOutwardFailed[$orderId]['message'], 'wc_colissimo'));
                    $labels .= '</div><br>';
                }
            } else {
                $labels = '';
            }
            $labels .= $trackingNumbers[$orderId] ?? '';

            /**
             * Filter on the date format shown in the Colissimo listing
             *
             * @since 1.6
             */
            $date = apply_filters('woocommerce_admin_order_date_format', __('M j, Y', 'woocommerce'));

            $data[] = [
                'data-id'             => $orderId,
                'cb'                  => '<input type="checkbox" />',
                'lpc-id'              => $this->getSeeOrderLink($orderId),
                'lpc-date'            => $wc_order->get_date_created()->date_i18n($date),
                'lpc-customer'        => $wc_order->get_shipping_first_name() . ' ' . $wc_order->get_shipping_last_name(),
                'lpc-address'         => $address,
                'lpc-country'         => $wc_order->get_shipping_country(),
                'lpc-shipping-method' => $wc_order->get_shipping_method(),
                'lpc-woo-status'      => wc_get_order_status_name($wc_order->get_status()),
                'lpc-label'           => $labels,
            ];
        }

        return $data;
    }

    protected function getLabelTrackingInfo($outwardTrackingNumber): array {
        if (empty($outwardTrackingNumber)) {
            return [];
        }

        $label = $this->outwardLabelDb->getLabel($outwardTrackingNumber);

        if (empty($label)) {
            return [];
        }

        $result = [
            'trackingLink' => $this->labelQueries->getOutwardLabelLink($label->order_id, $outwardTrackingNumber),
            'status'       => '',
        ];

        if (empty($label->status_id)) {
            return $result;
        }

        $result['status'] = $this->colissimoStatus->getStatusInfo($label->status_id)['label'];

        return $result;
    }

    protected function getBorderauxDownloadLinks(WC_Order $order) {
        $bordereauNumber = $order->get_meta(LpcBordereauGeneration::BORDEREAU_ID_META_KEY);
        $return          = '';
        if (!empty($bordereauNumber)) {
            $bordereauNumber = explode(',', $bordereauNumber);

            foreach ($bordereauNumber as $bordereauId) {
                $bordereauDownloadUrl = $this->bordereauDownloadAction->getUrlForBordereau($bordereauId);
                $return               .= <<<END_HTML
<a href="$bordereauDownloadUrl" target="_blank">$bordereauId</a>
END_HTML;
            }

            return $return;
        }
    }

    protected function getSeeOrderLink($orderId) {
        $orderUrl = admin_url('post.php?post=' . $orderId . '&action=edit');

        return '<a href="' . $orderUrl . '">' . $orderId . '</a>';
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="%s[]" value="%s" />',
            self::BULK_ACTION_IDS_PARAM_NAME,
            $item['data-id']
        );
    }

    public function get_bulk_actions() {
        $actions = [];

        if (current_user_can('lpc_manage_bordereau')) {
            $actions[self::BULK_BORDEREAU_GENERATION_ACTION_NAME] = __('Generate bordereau', 'wc_colissimo');
        }

        if (current_user_can('lpc_download_labels')) {
            $actions[self::BULK_LABEL_DOWNLOAD_ACTION_NAME] = __('Download label information', 'wc_colissimo');
        }

        if (current_user_can('lpc_manage_labels')) {
            $actions[self::BULK_LABEL_GENERATION_INWARD_ACTION_NAME]  = __('Generate inward labels', 'wc_colissimo');
            $actions[self::BULK_LABEL_GENERATION_OUTWARD_ACTION_NAME] = __('Generate outward labels', 'wc_colissimo');
        }

        if (current_user_can('lpc_print_labels')) {
            $actions[self::BULK_LABEL_PRINT_INWARD_ACTION_NAME]  = __('Print inward labels', 'wc_colissimo');
            $actions[self::BULK_LABEL_PRINT_OUTWARD_ACTION_NAME] = __('Print outward labels', 'wc_colissimo');
            $actions[self::BULK_LABEL_PRINT_ACTION_NAME]         = __('Print label information', 'wc_colissimo');
        }

        if (current_user_can('lpc_delete_labels')) {
            $actions[self::BULK_LABEL_DELETE_LABEL] = __('Delete labels', 'wc_colissimo');
        }

        return $actions;
    }

    public function get_sortable_columns() {
        return [
            'lpc-id'              => ['id', true],
            'lpc-date'            => ['date', false],
            'lpc-customer'        => ['customer', false],
            'lpc-address'         => ['address', false],
            'lpc-country'         => ['country', false],
            'lpc-shipping-method' => ['shipping-method', false],
            'lpc-woo-status'      => ['woo-status', false],
            'lpc-bordereau'       => ['bordereau', false],
        ];
    }

    protected function extra_tablenav($which) {
        if ('top' === $which) {
            $filters = $this->lpcGetFilters();

            $filtersNumbers = 0;

            array_walk(
                $filters,
                function ($filter, $key) use (&$filtersNumbers) {
                    if ('search' === $key || (count($filter) === 1 && empty($filter[0]))) {
                        $filtersNumbers += 0;
                    } else {
                        $filtersNumbers += count($filter);
                    }
                }
            );

            ?>
			<div id="lpc__orders_listing__page__more_options--toggle">
				<a id="lpc__orders_listing__page__more_options--toggle--text">
                    <?php echo __('Show filters', 'wc_colissimo'); ?>
				</a>
                <?php if ($filtersNumbers > 0) { ?>
					<span id="lpc__orders_listing__page__more_options--toggle--numbers_filters">
						<?php echo $filtersNumbers; ?>
				</span>
                <?php } ?>
			</div>

			<div id="lpc__orders_listing__page__more_options--options" style="display: none">
                <?php
                $this->countryFilters();
                $this->shippingMethodFilters();
                $this->wooStatusFilters();
                $this->statusFilters();
                $this->labelFilters();
                ?>
				<br>
				<div id="lpc__orders_listing__page__more_options--options__bottom-actions">
                    <?php submit_button(__('Filter', 'wc_colissimo'), '', 'filter-action', false); ?>
					<a id="lpc__orders_listing__page__more_options--options__bottom-actions__reset">
                        <?php echo __('Reset', 'wc_colissimo'); ?>
					</a>
				</div>
			</div>
            <?php
        }
    }

    protected function countryFilters() {
        $displayedCountries = false === get_option('lpc_orders_filters_country') ? [''] : get_option('lpc_orders_filters_country');

        $countries = LpcOrderQueries::getLpcOrdersPostMetaList('_shipping_country');

        if (!empty($countries)) {
            ?>
			<br>
			<p class="lpc__orders_listing__page__more_options--options__title">
                <?php echo __(
                    'Country',
                    'wc_colissimo'
                ); ?></p>

			<label>
				<input type="checkbox"
					   name="order_country[]" <?php echo in_array('', $displayedCountries) ? 'checked' : ''; ?>
					   value="">
                <?php echo __('All countries', 'wc_colissimo'); ?>
			</label>
            <?php
            foreach ($countries as $oneCountry) {
                printf(
                    '<label><input type="checkbox" name="order_country[]" %1$s value="%2$s">%3$s</label>',
                    in_array($oneCountry, $displayedCountries) ? 'checked' : '',
                    esc_attr($oneCountry),
                    esc_html($oneCountry)
                );
            }
        }
    }

    protected function statusFilters() {
        $displayedStatus = false === get_option('lpc_orders_filters_status') ? [''] : get_option('lpc_orders_filters_status');

        $status = LpcOrderQueries::getLpcOrdersPostMetaList(LpcUnifiedTrackingApi::LAST_EVENT_INTERNAL_CODE_META_KEY);

        if (!empty($status)) {
            ?>
			<br>
			<p class="lpc__orders_listing__page__more_options--options__title">
                <?php echo __(
                    'Status',
                    'wc_colissimo'
                );
                ?>
			</p>

			<label>
				<input type="checkbox" name="order_status[]" <?php echo in_array('', $displayedStatus) ? 'checked' : ''; ?> value="">
                <?php echo __('All statuses', 'wc_colissimo'); ?>
			</label>
            <?php
            foreach ($status as $oneStatusCode) {
                printf(
                    '<label><input type="checkbox" name="order_status[]" %1$s value="%2$s">%3$s</label>',
                    in_array($oneStatusCode, $displayedStatus) ? 'checked' : '',
                    esc_attr($oneStatusCode),
                    esc_html($this->colissimoStatus->getStatusInfo($oneStatusCode)['label'])
                );
            }
        }
    }

    protected function shippingMethodFilters() {
        $displayedShippingMethods = false === get_option('lpc_orders_filters_shipping_method') ? [''] : get_option('lpc_orders_filters_shipping_method');

        $shippingMethods = LpcOrderQueries::getLpcOrdersShippingMethods();

        if (!empty($shippingMethods)) {
            ?>
			<br>
			<p class="lpc__orders_listing__page__more_options--options__title"><?php echo __('Shipping method', 'wc_colissimo'); ?></p>

			<label>
				<input type="checkbox" name="order_shipping_method[]" <?php echo in_array('', $displayedShippingMethods) ? 'checked' : ''; ?> value="">
                <?php echo __('All shipping methods', 'wc_colissimo'); ?>
			</label>
            <?php

            foreach ($shippingMethods as $oneShippingMethod) {
                printf(
                    '<label><input type="checkbox" name="order_shipping_method[]" %1$s value="%2$s">%3$s</label>',
                    in_array($oneShippingMethod, $displayedShippingMethods) ? 'checked' : '',
                    esc_attr($oneShippingMethod),
                    esc_html($oneShippingMethod)
                );
            }
        }
    }

    protected function labelFilters() {
        $displayedLabelTypes = false === get_option('lpc_orders_filters_label_type') ? [''] : get_option('lpc_orders_filters_label_type');

        $labelTypes = [
            'none'                => __('No label generated', 'wc_colissimo'),
            'outward'             => __('Outward label generated', 'wc_colissimo'),
            'inward'              => __('Inward label generated', 'wc_colissimo'),
            'outward_printed'     => __('Outward label printed', 'wc_colissimo'),
            'outward_not_printed' => __('Outward label not printed', 'wc_colissimo'),
        ];

        ?>
		<br>
		<p class="lpc__orders_listing__page__more_options--options__title"><?php echo __('Labels', 'wc_colissimo'); ?></p>

		<label>
			<input type="checkbox" name="label_type[]" <?php echo in_array('', $displayedLabelTypes) ? 'checked' : ''; ?> value="">
            <?php echo __('All', 'wc_colissimo'); ?>
		</label>

        <?php
        foreach ($labelTypes as $oneLabelCode => $oneLabelType) {
            printf(
                '<label><input type="checkbox" name="label_type[]" %1$s value="%2$s">%3$s</label>',
                in_array($oneLabelCode, $displayedLabelTypes) ? 'checked' : '',
                esc_attr($oneLabelCode),
                esc_html($oneLabelType)
            );
        }
    }

    public function wooStatusFilters() {
        $displayedWooStatuses = false === get_option('lpc_orders_filters_woo_status') ? [''] : get_option('lpc_orders_filters_woo_status');

        $wooStatuses = LpcOrderQueries::getLpcOrdersWooStatuses();

        if (!empty($wooStatuses)) {
            ?>
			<br>
			<p class="lpc__orders_listing__page__more_options--options__title"><?php echo __('Order status', 'wc_colissimo'); ?></p>

			<label>
				<input type="checkbox" name="order_woo_status[]" <?php echo in_array('', $displayedWooStatuses) ? 'checked' : ''; ?> value="">
                <?php echo __('All order statuses', 'wc_colissimo'); ?>
			</label>
            <?php

            foreach ($wooStatuses as $oneWooStatus) {
                printf(
                    '<label><input type="checkbox" name="order_woo_status[]" %1$s value="%2$s">%3$s</label>',
                    in_array($oneWooStatus, $displayedWooStatuses) ? 'checked' : '',
                    esc_attr($oneWooStatus),
                    wc_get_order_status_name(esc_html($oneWooStatus))
                );
            }
        }
    }

    public function process_bulk_action() {
        if (isset($_REQUEST['_wpnonce']) && !empty($_REQUEST['_wpnonce'])) {
            $nonce  = filter_input(INPUT_POST, '_wpnonce', FILTER_SANITIZE_STRING);
            $action = 'bulk-' . $this->_args['plural'];

            if (!wp_verify_nonce($nonce, $action)) {
                wp_die(__('Access denied! (Security check failed)', 'wc_colissimo'));
            }
        } else {
            return;
        }

        $action = $this->current_action();
        $ids    = LpcHelper::getVar(self::BULK_ACTION_IDS_PARAM_NAME, [], 'array');
        if (empty($ids)) {
            // no selectionned IDs on bulk actions => nothing to do.
            return;
        }

        switch ($action) {
            case self::BULK_BORDEREAU_GENERATION_ACTION_NAME:
                if (current_user_can('lpc_manage_bordereau')) {
                    $this->bulkBordereauGeneration($ids);
                }
                break;

            case self::BULK_LABEL_DOWNLOAD_ACTION_NAME:
                $this->bulkLabelDownload($ids);
                break;

            case self::BULK_LABEL_GENERATION_OUTWARD_ACTION_NAME:
                $this->bulkLabelGeneration($this->labelGenerationOutward, $ids);
                break;

            case self::BULK_LABEL_GENERATION_INWARD_ACTION_NAME:
                $this->bulkLabelGeneration($this->labelGenerationInward, $ids);
                break;

            case self::BULK_LABEL_PRINT_INWARD_ACTION_NAME:
                $this->bulkLabelPrint($ids, LpcInwardLabelDb::LABEL_TYPE_INWARD);
                break;

            case self::BULK_LABEL_PRINT_OUTWARD_ACTION_NAME:
                $this->bulkLabelPrint($ids, LpcOutwardLabelDb::LABEL_TYPE_OUTWARD);
                break;

            case self::BULK_LABEL_PRINT_ACTION_NAME:
                $this->bulkLabelPrint($ids, LpcLabelPrintAction::PRINT_LABEL_TYPE_OUTWARD_AND_INWARD);
                break;

            case self::BULK_LABEL_DELETE_LABEL:
                $this->bulkDeleteLabel($ids);
                break;
        }
    }

    protected function getOrdersByIds(array $ids) {
        return array_map(
            function ($id) {
                return new WC_Order($id);
            },
            $ids
        );
    }

    protected function bulkBordereauGeneration(array $ids) {
        $orders = $this->getOrdersByIds($ids);

        $bordereau = $this->bordereauGeneration->generate($orders);
        /** Special handling of the generation result :
         *  - if its empty, certainly because multiple bordereaux were generated (remembering that one
         *    bordereau can only have 50 tracking numbers), we prefer not to download any of the generate
         *    bordereau, and thus only refresh/redict to the same listing page,
         *  - else, i.e. if its *not* empty, it means that only one bordereau was generated, as a convenience
         *    for the user, we directly initiate a download of it.
         */
        if (!empty($bordereau)) {
            if (current_user_can('lpc_download_bordereau')) {
                $bordereauId                  = $bordereau->bordereauHeader->bordereauNumber;
                $bordereauGenerationActionUrl = $this->bordereauDownloadAction->getUrlForBordereau($bordereauId);
                $i18n                         = __('Click here to download your created bordereau', 'wc_colissimo');
                echo <<<END_DOWNLOAD_LINK
<div class="updated"><p><a href="$bordereauGenerationActionUrl">$i18n</a></p></div>
END_DOWNLOAD_LINK;
            }
        } else {
            $requestURI = '';
            if (is_null(filter_input(INPUT_SERVER, 'REQUEST_URI'))) {
                if (isset($_SERVER['REQUEST_URI'])) {
                    $requestURI = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
                }
            } else {
                $requestURI = wp_unslash(filter_input(INPUT_SERVER, 'REQUEST_URI'));
            }

            wp_redirect(
                remove_query_arg(
                    ['_wp_http_referer', '_wpnonce', self::BULK_ACTION_IDS_PARAM_NAME, 'action', 'action2'],
                    $requestURI
                )
            );
            exit;
        }
    }

    protected function bulkLabelDownload(array $ids) {
        $trackingNumbers = $this->labelQueries->getTrackingNumbersForOrdersId($ids);

        $labelDownloadActionUrl = $this->labelPackagerDownloadAction->getUrlForTrackingNumbers($trackingNumbers);

        if (!$labelDownloadActionUrl) {
            $i18n = __('The labels that you\'ve selected are imported tracking numbers, you cannot download them', 'wc_colissimo');
            echo <<<END_DOWNLOAD_LINK
<div class="notice lpc-notice is-dismissible lpc-notice-error-notice notice-error"><p>$i18n</p></div>
END_DOWNLOAD_LINK;
        } else {
            $i18n = __('Click here to download your created label package', 'wc_colissimo');
            echo <<<END_DOWNLOAD_LINK
<div class="updated"><p><a href="$labelDownloadActionUrl">$i18n</a></p></div>
END_DOWNLOAD_LINK;
        }
    }

    protected function bulkLabelGeneration($generator, array $ids) {
        $orders = $this->getOrdersByIds($ids);

        try {
            foreach ($orders as $order) {
                $allItemsOrder = $order->get_items();
                $generator->generate($order, ['items' => $allItemsOrder], true);
            }

            $requestURI = '';
            if (is_null(filter_input(INPUT_SERVER, 'REQUEST_URI'))) {
                if (isset($_SERVER['REQUEST_URI'])) {
                    $requestURI = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
                }
            } else {
                $requestURI = wp_unslash(filter_input(INPUT_SERVER, 'REQUEST_URI'));
            }

            wp_redirect(
                remove_query_arg(
                    ['_wp_http_referer', '_wpnonce', self::BULK_ACTION_IDS_PARAM_NAME, 'action', 'action2'],
                    $requestURI
                )
            );
            exit;
        } catch (Exception $e) {
            add_action(
                'admin_notice',
                function () use ($e) {
                    LpcHelper::displayNoticeException($e);
                }
            );
        }
    }

    public function bulkLabelPrint($ids, $labelType = LpcLabelPrintAction::PRINT_LABEL_TYPE_OUTWARD_AND_INWARD) {
        $trackingNumbers = $this->labelQueries->getTrackingNumbersForOrdersId($ids, $labelType);

        $stringTrackingNumbers = implode(',', $trackingNumbers);

        $needInvoice = false;

        if (LpcLabelPrintAction::PRINT_LABEL_TYPE_OUTWARD_AND_INWARD === $labelType) {
            $needInvoice = true;
        }

        $labelPrintActionUrl = $this->labelPrintAction->getUrlForTrackingNumbers($trackingNumbers, $needInvoice);

        if (!$labelPrintActionUrl) {
            $i18n = __('The labels that you\'ve selected are imported tracking numbers, you cannot print them', 'wc_colissimo');
            echo <<<END_DOWNLOAD_LINK
<div class="notice lpc-notice is-dismissible lpc-notice-error-notice notice-error"><p>$i18n</p></div>
END_DOWNLOAD_LINK;

            return;
        }

        $this->outwardLabelDb->updatePrintedLabel($trackingNumbers);
        $this->inwardLabelDb->updatePrintedLabel($trackingNumbers);

        echo <<<END_PRINT_SCRIPT
<script type="text/javascript">
        jQuery(function ($) {
            $(document).ready(function(){
                let infos = {
                    'pdfUrl': '$labelPrintActionUrl',
                    'labelType': '$labelType',
                    'trackingNumbers': '$stringTrackingNumbers'
                };
                
                lpc_print_labels(infos);
            });
        });
</script>
END_PRINT_SCRIPT;
    }

    public function bulkDeleteLabel($ids) {
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $this->lpcLabelPurge->purgeLabels($ids);
    }

    public function displayHeaders() {
        echo '<h1 class="wp-heading-inline">' . __('Colissimo Orders', 'wc_colissimo') . '</h1>';
        $buttonUpdateStatusAction = $this->updateStatuses->getUpdateAllStatusesUrl();
        $buttonUpdateStatusLabel  = __('Update Colissimo statuses', 'wc_colissimo');
        echo '<a id="colissimo_action_update" href="' . $buttonUpdateStatusAction . '" class="page-title-action">' . $buttonUpdateStatusLabel . '</a>';

        if (current_user_can('lpc_manage_bordereau')) {
            $buttonGenerateBordereauAction = $this->bordereauGeneration->getGenerationBordereauEndDayUrl();
            $buttonGenerateBordereauLabel  = __('Generate end of day bordereau', 'wc_colissimo');
            echo '<a id="colissimo_action_bordereau" href="' . $buttonGenerateBordereauAction . '" class="page-title-action">' . $buttonGenerateBordereauLabel . '</a>';
        }

        if (current_user_can('lpc_manage_labels') && WC_Admin_Settings::get_option('display_import_tracking_number', 'no') === 'yes') {
            $buttonImportTrackingNumberLabel  = __('Import tracking numbers', 'wc_colissimo');
            $buttonImportTrackingNumberAction = $this->labelOutwardImport->getUrlToImportTrackingNumbers();
            echo '<button type="button" class="page-title-action" id="colissimo-tracking_number_import-button">' . $buttonImportTrackingNumberLabel . '</button>';
            echo '<input name="tracking_number_import" id="colissimo-tracking_number_import" type="file" accept=".csv" colissimo-data-url="' . $buttonImportTrackingNumberAction . '">';
        }

        echo '<hr class="wp-header-end">';
    }

    protected function lpcGetFilters() {
        return [
            'country'         =>
                false === get_option('lpc_orders_filters_country') ? [''] : get_option('lpc_orders_filters_country'),
            'shipping_method' => false === get_option('lpc_orders_filters_shipping_method') ?
                [''] : get_option('lpc_orders_filters_shipping_method'),
            'status'          => false === get_option('lpc_orders_filters_status') ?
                [''] : get_option('lpc_orders_filters_status'),
            'label_type'      => false === get_option('lpc_orders_filters_label_type') ?
                [''] : get_option('lpc_orders_filters_label_type'),
            'woo_status'      => false === get_option('lpc_orders_filters_woo_status') ?
                [''] : get_option('lpc_orders_filters_woo_status'),
            'search'          => isset($_REQUEST['s']) ?
                esc_attr(sanitize_text_field(wp_unslash($_REQUEST['s']))) : '',
        ];
    }

    protected function getTrackingNumbersFormatted($ordersId = []) {
        $trackingNumbersByOrders         = [];
        $renderedTrackingNumbersByOrders = [];
        $labelFormatByTrackingNumber     = [];
        $ordersInwardFailed              = get_option(LpcLabelGenerationInward::ORDERS_INWARD_PARCEL_FAILED, []);

        $this->labelQueries->getTrackingNumbersByOrdersId($trackingNumbersByOrders, $labelFormatByTrackingNumber, $ordersId);

        foreach ($trackingNumbersByOrders as $oneOrderId => $oneOrder) {
            if ('insured' === $oneOrderId) {
                continue;
            }

            $renderedTrackingNumbersByOrders[$oneOrderId] = '<div class="lpc__orders_listing__tracking-numbers">';
            foreach ($oneOrder as $outLabel => $inLabel) {
                if ('no_outward' !== $outLabel) {
                    $format        = $labelFormatByTrackingNumber[$outLabel];
                    $labelTracking = $this->getLabelTrackingInfo($outLabel);

                    if (empty($labelTracking)) {
                        $shownLabel = $outLabel;
                    } else {
                        $shownLabel = '<a target="_blank" href="' . esc_url($labelTracking['trackingLink']) . '">' . $outLabel . '</a>';
                    }

                    $renderedTrackingNumbersByOrders[$oneOrderId] .= '<span class="lpc__orders_listing__tracking-number">';
                    $renderedTrackingNumbersByOrders[$oneOrderId] .= '<span class="lpc__orders_listing__tracking_number--outward">' . $shownLabel . '</span>';
                    $renderedTrackingNumbersByOrders[$oneOrderId] .= $this->labelQueries->getOutwardLabelsActionsIcons(
                        $outLabel,
                        $format,
                        LpcLabelQueries::REDIRECTION_COLISSIMO_ORDERS_LISTING
                    );
                    if (!empty($labelTracking['status'])) {
                        $renderedTrackingNumbersByOrders[$oneOrderId] .= '<br />' . esc_html($labelTracking['status']);
                    }
                    $renderedTrackingNumbersByOrders[$oneOrderId] .= '</span><br>';

                    $bordereauID = $this->outwardLabelDb->getBordereauFromTrackingNumber($outLabel);
                    if (!empty($bordereauID[0])) {
                        $bordereauLink = $this->bordereauDownloadAction->getBorderauDownloadLink($bordereauID[0]);

                        if (!empty($bordereauLink)) {
                            $renderedTrackingNumbersByOrders[$oneOrderId] .=
                                '<span class="lpc__orders_listing__bordereau lpc-bordereau">
								<span class="lpc__orders_listing__id--bordereau">' . sprintf(__('Bordereau n°%d', 'wc_colissimo'), $bordereauID[0]) . '</span>
								<span>' .
                                $this->bordereauQueries->getBordereauActionsIcons($bordereauLink,
                                                                                  $bordereauID[0],
                                                                                  $oneOrderId,
                                                                                  LpcLabelQueries::REDIRECTION_COLISSIMO_ORDERS_LISTING)
                                . '</span>
							</span><br>';
                        }
                    }
                }

                foreach ($inLabel as $oneInLabel) {
                    $format = $labelFormatByTrackingNumber[$oneInLabel];

                    $renderedTrackingNumbersByOrders[$oneOrderId] .=
                        '<span class="lpc__orders_listing__tracking-number">' .
                        '<span class="dashicons dashicons-undo lpc__orders_listing__inward_logo"></span>'
                        . '<span class="lpc__orders_listing__tracking_number--inward"> ' . $oneInLabel . '</span>' .
                        $this->labelQueries->getInwardLabelsActionsIcons($oneInLabel, $format, LpcLabelQueries::REDIRECTION_COLISSIMO_ORDERS_LISTING)
                        . '</span><br>';
                }

                if (current_user_can('lpc_manage_labels')) {
                    $renderedTrackingNumbersByOrders[$oneOrderId] .= '<div class="lpc_generate_inward_label lpc_generate_label">
                                                                 <i class="dashicons dashicons-plus lpc_generate_label_dashicon"'
                                                                     . $this->labelQueries->getLabelInwardGenerateAttr($oneOrderId, $outLabel) . '></i>'
                                                                     . __('Generate inward label', 'wc_colissimo') . '
																</div><br>';

                    if (!empty($ordersInwardFailed[$outLabel])) {
                        $renderedTrackingNumbersByOrders[$oneOrderId] .= '<div class="lpc_outward_label_error">';
                        $renderedTrackingNumbersByOrders[$oneOrderId] .= '<span class="dashicons dashicons-warning lpc_inward_label_error_icon"></span>';
                        $renderedTrackingNumbersByOrders[$oneOrderId] .= sprintf(
                            __('The label couldn\'t be generated: %s', 'wc_colissimo'),
                            __($ordersInwardFailed[$outLabel]['message'], 'wc_colissimo')
                        );
                        $renderedTrackingNumbersByOrders[$oneOrderId] .= '</div><br>';
                    }
                }

                $renderedTrackingNumbersByOrders[$oneOrderId] .= '<br>';
            }
            $renderedTrackingNumbersByOrders[$oneOrderId] .= '</div>';
        }

        return $renderedTrackingNumbersByOrders;
    }
}
