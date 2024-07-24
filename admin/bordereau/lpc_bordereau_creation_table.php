<?php

class LpcBordereauCreationTable extends WP_List_Table {

    const BULK_SLIP_CREATION = 'bulk-slip_creation_ids';

    private $needTodayOrder;

    /** @var LpcBordereauGeneration */
    protected $bordereauGeneration;
    /** @var LpcBordereauDownloadAction */
    protected $bordereauDownloadAction;

    public function __construct($needTodayOrder) {
        parent::__construct();

        $this->bordereauGeneration     = LpcRegister::get('bordereauGeneration');
        $this->bordereauDownloadAction = LpcRegister::get('bordereauDownloadAction');

        $this->needTodayOrder = $needTodayOrder;
    }

    public function get_columns() {
        $columns = [
            'cb'                  => '<input type="checkbox" />',
            'lpc-id'              => __('ID', 'wc_colissimo'),
            'lpc-tracking-number' => __('Tracking number', 'wc_colissimo'),
            'lpc-date-label'      => __('Label creation date', 'wc_colissimo'),
            'lpc-date-order'      => __('Order creation date', 'wc_colissimo'),
            'lpc-country'         => __('Country', 'wc_colissimo'),
            'lpc-shipping-method' => __('Shipping method', 'wc_colissimo'),
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

    public function get_pagenum() {
        $pageParamsName = $this->needTodayOrder ? 'paged_today' : 'paged_other';
        $pagenum        = isset($_REQUEST[$pageParamsName]) ? absint($_REQUEST[$pageParamsName]) : 0;

        if (isset($this->_pagination_args['total_pages']) && $pagenum > $this->_pagination_args['total_pages']) {
            $pagenum = $this->_pagination_args['total_pages'];
        }

        return max(1, $pagenum);
    }

    public function prepare_items($args = []) {
        $this->process_bulk_action();

        $filters = [
            'no_slip' => true,
        ];
        if ($this->needTodayOrder) {
            $filters['label_start_date'] = date('Y-m-d 00:00:00', time());
        } else {
            $filters['label_end_date'] = date('Y-m-d 00:00:00', time());
        }

        $columns      = $this->get_columns();
        $hidden       = [];
        $sortable     = [];
        $total_items  = LpcOrderQueries::countLpcOrders($filters);
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
        $data   = [];
        $orders = LpcOrderQueries::getLpcOrders($current_page, $per_page, $args, $filters);

        foreach ($orders as $order) {
            $orderId = $order['order_id'];

            try {
                $wc_order = new WC_Order($orderId);
            } catch (Exception $exception) {
                continue;
            }

            /**
             * Filter on the date format shown in the Colissimo listing
             *
             * @since 1.6
             */
            $date = apply_filters('woocommerce_admin_order_date_format', __('M j, Y', 'woocommerce'));

            $orderDate = $wc_order->get_date_created();
            $data[] = [
                'data-id'             => $orderId,
                'cb'                  => '<input type="checkbox" />',
                'lpc-id'              => LpcOrdersTable::getSeeOrderLink($orderId),
                'lpc-tracking-number' => $order['tracking_number'],
                'lpc-date-label'      => (new WC_DateTime($order['label_created_at']))->date_i18n($date),
                'lpc-date-order'      => empty($orderDate) ? '-' : $orderDate->date_i18n($date),
                'lpc-country'         => $wc_order->get_shipping_country(),
                'lpc-shipping-method' => $wc_order->get_shipping_method(),
            ];
        }

        return $data;
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="%s[]" value="%s" />',
            self::BULK_SLIP_CREATION,
            $item['data-id']
        );
    }

    public function displayHeaders() {
        echo '<div class="lpc_slip_creation_header">';
        if (current_user_can('lpc_manage_bordereau')) {
            $buttonGenerateBordereauLabel = __('Generate with the selected parcels', 'wc_colissimo');
            echo '<button type="button" id="colissimo_action_bordereau_selected" class="page-title-action">' . $buttonGenerateBordereauLabel . '</button>';

            $buttonGenerateBordereauAction = $this->bordereauGeneration->getGenerationBordereauEndDayUrl();
            $buttonGenerateBordereauLabel  = __('Generate end of period slip', 'wc_colissimo');
            echo '<a id="colissimo_action_bordereau_day" href="' . $buttonGenerateBordereauAction . '" class="page-title-action">' . $buttonGenerateBordereauLabel . '</a>';
        }
        echo '</div>';
    }

    protected function getOrdersByIds(array $ids) {
        return array_map(
            function ($id) {
                return new WC_Order($id);
            },
            $ids
        );
    }

    protected function process_bulk_action() {
        if (!current_user_can('lpc_manage_bordereau')) {
            return;
        }
        $ids = LpcHelper::getVar(self::BULK_SLIP_CREATION, [], 'array');

        if (empty($ids)) {
            return;
        }

        $orders = $this->getOrdersByIds($ids);

        $bordereauId = $this->bordereauGeneration->generate($orders);

        if (!empty($bordereauId)) {
            wp_redirect(admin_url('admin.php?page=wc_colissimo_view&tab=slip-history'));
        }
    }
}
