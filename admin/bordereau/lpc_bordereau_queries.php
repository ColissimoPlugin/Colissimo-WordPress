<?php

class LpcBordereauQueries extends LpcComponent {
    const LABEL_TYPE_BORDEREAU = 'bordereau';

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

    public function getBordereauActionsIcons($bordereauLink, $bordereauID, $orderId, $redirection) {
        $printerIcon = $GLOBALS['wp_version'] >= '5.5' ? 'dashicons-printer' : 'dashicons-media-default';

        $actions = '';

        if (current_user_can('lpc_download_bordereau')) {
            $actions .= '<span class="dashicons dashicons-download lpc_label_action_download" ' . $this->getBordereauDownloadAttr($bordereauLink) . '></span>';
        }

        if (current_user_can('lpc_print_bordereau')) {
            $actions .= '<span class="dashicons ' . $printerIcon . ' lpc_label_action_print" ' . $this->getBordereauPrintAttr($bordereauID) . ' ></span>';
        }

        if (current_user_can('lpc_delete_bordereau')) {
            $actions .= '<span class="dashicons dashicons-trash lpc_label_action_delete" ' . $this->getBordereauDeletionAttr($bordereauID, $orderId, $redirection) . '></span>';
        }

        return $actions;
    }

    protected function getBordereauDeletionAttr($bordereauId, $orderId, $redirection) {
        return 'data-link="' . $this->bordereauDeleteAction->getUrlForBordereau($bordereauId, $orderId, $redirection) . '" '
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

}
