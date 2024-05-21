<?php

class LpcBordereauQueries extends LpcComponent {
    const LABEL_TYPE_BORDEREAU = 'bordereau';
    const REDIRECTION_COLISSIMO_BORDEREAU_LISTING = 'lpc_colissimo_slip_history';

    /** @var LpcBordereauPrintAction */
    protected $bordereauPrintAction;
    /** @var LpcBordereauDeleteAction */
    protected $bordereauDeleteAction;

    public function __construct(
        LpcBordereauPrintAction $bordereauPrintAction = null,
        LpcBordereauDeleteAction $bordereauDeleteAction = null
    ) {
        $this->bordereauDeleteAction = LpcRegister::get('bordereauDeleteAction', $bordereauDeleteAction);
        $this->bordereauPrintAction  = LpcRegister::get('bordereauPrintAction', $bordereauPrintAction);
    }

    public function getBordereauActionsIcons($bordereauLink, $bordereauID, $redirection) {
        $printerIcon = $GLOBALS['wp_version'] >= '5.5' ? 'dashicons-printer' : 'dashicons-media-default';

        $actions = '';

        if (current_user_can('lpc_download_bordereau')) {
            $actions .= '<span class="dashicons dashicons-download lpc_label_action_download" ' . $this->getBordereauDownloadAttr($bordereauLink) . '></span>';
        }

        if (current_user_can('lpc_print_bordereau')) {
            $actions .= '<span class="dashicons ' . $printerIcon . ' lpc_label_action_print" ' . $this->getBordereauPrintAttr($bordereauID) . ' ></span>';
        }

        if (current_user_can('lpc_delete_bordereau')) {
            $actions .= '<span class="dashicons dashicons-trash lpc_label_action_delete" ' . $this->getBordereauDeletionAttr($bordereauID, $redirection) . '></span>';
        }

        return $actions;
    }

    protected function getBordereauDeletionAttr($bordereauId, $redirection) {
        return 'data-link="' . $this->bordereauDeleteAction->getUrlForBordereau($bordereauId, $redirection) . '" '
               . 'data-label-type="' . self::LABEL_TYPE_BORDEREAU . '" '
               . 'data-tracking-number="' . sprintf(__('Bordereau n°%d', 'wc_colissimo'), $bordereauId) . '" '
               . 'title="' . __('Delete bordereau', 'wc_colissimo') . '"';
    }

    protected function getBordereauDownloadAttr($bordereauLink) {
        return 'data-link="' . $bordereauLink .
               '"title="' . __('Download bordereau', 'wc_colissimo') . '"';
    }

    protected function getBordereauPrintAttr($bordereauId, $format = 'PDF') {
        return 'data-link="' . $this->bordereauPrintAction->getUrlForBordereau($bordereauId) . '" '
               . 'data-label-type="' . self::LABEL_TYPE_BORDEREAU . '"'
               . 'data-tracking-number="' . sprintf(__('Bordereau n°%d', 'wc_colissimo'), $bordereauId) . '" '
               . 'data-format="' . $format . '" '
               . 'title="' . __('Print bordereau', 'wc_colissimo') . '"';
    }


    public static function countLpcBordereau() {
        global $wpdb;

        // phpcs:disable
        $result = $wpdb->get_results('SELECT COUNT(DISTINCT bordereau_external_id) AS nb FROM ' . $wpdb->prefix . 'lpc_bordereau');
        // phpcs:enable

        if (!empty($result)) {
            return $result[0]->nb;
        }

        return 0;
    }

    public static function getLpcBordereau($current_page, $per_page) {
        global $wpdb;

        $query = "SELECT bordereau.id, COUNT(out_label.order_id) AS number_parcels, bordereau.bordereau_external_id, bordereau.created_at, GROUP_CONCAT(DISTINCT out_label.order_id SEPARATOR ',') AS order_ids, GROUP_CONCAT(DISTINCT out_label.tracking_number SEPARATOR ', ') AS tracking_numbers 
                    FROM {$wpdb->prefix}lpc_bordereau AS bordereau 
                    LEFT JOIN {$wpdb->prefix}lpc_outward_label AS out_label ON out_label.bordereau_id = bordereau.bordereau_external_id 
                    GROUP BY bordereau.bordereau_external_id
                    ORDER BY id DESC";

        if (0 < $current_page && 0 < $per_page) {
            $offset = ($current_page - 1) * $per_page;
            $query  .= " LIMIT $per_page OFFSET $offset";
        }

        // phpcs:disable
        return $wpdb->get_results($query);
        // phpcs:enable
    }

    public static function deleteBordereauById($bordereauId): bool {
        global $wpdb;

        // phpcs:disable
        $wpdb->query("UPDATE {$wpdb->prefix}lpc_outward_label SET bordereau_id = NULL WHERE bordereau_id = " . intval($bordereauId));
        $result = $wpdb->query("DELETE FROM {$wpdb->prefix}lpc_bordereau WHERE bordereau_external_id = " . intval($bordereauId));
        // phpcs:enable

        if (!$result) {
            LpcLogger::error(
                'Unable to delete slip',
                [
                    'slipId' => $bordereauId,
                    'result' => $result,
                    'method' => __METHOD__,
                ]
            );

            return false;
        }

        return true;
    }
}
