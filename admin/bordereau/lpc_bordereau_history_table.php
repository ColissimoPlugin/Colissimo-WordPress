<?php

class LpcBordereauHistoryTable extends WP_List_Table {

    /** @var LpcBordereauGenerationApi */
    protected $bordereauGenerationApi;
    /** @var LpcBordereauDownloadAction */
    protected $bordereauDownloadAction;
    /** @var LpcBordereauQueries */
    protected $bordereauQueries;

    public function __construct(
        LpcBordereauGenerationApi $bordereauGenerationApi = null,
        LpcBordereauDownloadAction $bordereauDownloadAction = null
    ) {
        parent::__construct();

        $this->bordereauGenerationApi  = LpcRegister::get('bordereauGenerationApi', $bordereauGenerationApi);
        $this->bordereauDownloadAction = LpcRegister::get('bordereauDownloadAction', $bordereauDownloadAction);
        $this->bordereauQueries        = LpcRegister::get('bordereauQueries');
    }

    public function get_columns() {
        $columns = [
            'lpc-number'           => __('Bordereau ID', 'wc_colissimo'),
            'lpc-parcels-number'   => __('Number of parcels', 'wc_colissimo'),
            'lpc-order-ids'        => __('Order IDs', 'wc_colissimo'),
            'lpc-tracking-numbers' => __('Tracking numbers', 'wc_colissimo'),
            'lpc-creation-date'    => __('Creation date', 'wc_colissimo'),
            'lpc-actions'          => __('Actions', 'wc_colissimo'),
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
        $columns      = $this->get_columns();
        $hidden       = [];
        $sortable     = [];
        $total_items  = LpcBordereauQueries::countLpcBordereau();
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
        $this->items           = $this->get_data($current_page, $per_page, $args);
    }

    protected function column_default($item, $column_name) {
        return $item[$column_name];
    }

    protected function get_data($current_page = 0, $per_page = 0, $args = [], $filters = []): array {
        $data  = [];
        $slips = LpcBordereauQueries::getLpcBordereau($current_page, $per_page);

        $formatDate = get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i');

        foreach ($slips as $slip) {
            $date = '-';
            if (!empty($slip->created_at)) {
                $date = date_i18n($formatDate, strtotime($slip->created_at));
            }

            $bordereauLink = $this->bordereauDownloadAction->getBorderauDownloadLink($slip->bordereau_external_id);

            $orderIds      = explode(',', $slip->order_ids);
            $orderIdsLinks = [];
            foreach ($orderIds as $orderId) {
                $orderIdsLinks[] = LpcOrdersTable::getSeeOrderLink($orderId);
            }

            $data[] = [
                'data-id'              => $slip->bordereau_external_id,
                'lpc-number'           => $slip->bordereau_external_id,
                'lpc-parcels-number'   => $slip->number_parcels,
                'lpc-order-ids'        => str_replace(', N/A', '', implode(', ', $orderIdsLinks)),
                'lpc-tracking-numbers' => $slip->tracking_numbers,
                'lpc-creation-date'    => $date,
                'lpc-actions'          => $this->bordereauQueries->getBordereauActionsIcons($bordereauLink,
                                                                                            $slip->bordereau_external_id,
                                                                                            LpcBordereauQueries::REDIRECTION_COLISSIMO_BORDEREAU_LISTING),
            ];
        }

        return $data;
    }
}
