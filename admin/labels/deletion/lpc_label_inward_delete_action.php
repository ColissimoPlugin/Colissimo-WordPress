<?php

defined('ABSPATH') || die('Restricted Access');

class LpcLabelInwardDeleteAction extends LpcComponent {
    const AJAX_TASK_NAME = 'label/inward/delete';
    const TRACKING_NUMBER_VAR_NAME = 'lpc_label_tracking_number';
    const REDIRECTION_VAR_NAME = 'lpc_redirection';

    /** @var LpcAjax */
    protected $ajaxDispatcher;
    /** @var LpcInwardLabelDb */
    protected $inwardLabelDb;
    /** @var LpcAdminNotices */
    protected $adminNotices;

    public function __construct(
        LpcAjax $ajaxDispatcher = null,
        LpcInwardLabelDb $inwardLabelDb = null,
        LpcAdminNotices $adminNotices = null
    ) {
        $this->ajaxDispatcher = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);
        $this->inwardLabelDb  = LpcRegister::get('inwardLabelDb', $inwardLabelDb);
        $this->adminNotices   = LpcRegister::get('lpcAdminNotices', $adminNotices);
    }

    public function getDependencies() {
        return ['ajaxDispatcher', 'inwardLabelDb', 'lpcAdminNotices'];
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control']);
    }

    public function getUrlForTrackingNumber($trackingNumber, $redirection) {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME)
               . '&' . self::TRACKING_NUMBER_VAR_NAME . '=' . $trackingNumber
               . '&' . self::REDIRECTION_VAR_NAME . '=' . $redirection;
    }

    public function control() {
        if (!current_user_can('lpc_delete_labels')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => 'unauthorized access to outward label deletion',
                ]
            );
        }

        $trackingNumber = LpcHelper::getVar(self::TRACKING_NUMBER_VAR_NAME);
        $redirection    = LpcHelper::getVar(self::REDIRECTION_VAR_NAME);

        switch ($redirection) {
            case LpcLabelQueries::REDIRECTION_WOO_ORDER_EDIT_PAGE:
                $orderId = $this->inwardLabelDb->getOrderIdByTrackingNumber($trackingNumber);
                $urlRedirection = get_edit_post_link($orderId, '');
                break;
            case LpcLabelQueries::REDIRECTION_COLISSIMO_ORDERS_LISTING:
            default:
                $urlRedirection = admin_url('admin.php?page=wc_colissimo_view');
                break;
        }

        LpcLogger::debug(
            'Delete inward label',
            [
                'tracking_number' => $trackingNumber,
                'method'          => __METHOD__,
            ]
        );

        $result = $this->inwardLabelDb->delete($trackingNumber);

        if (1 != $result) {
            LpcLogger::error(
                'Unable to delete label',
                [
                    'tracking_number' => $trackingNumber,
                    'result'          => $result,
                    'method'          => __METHOD__,
                ]
            );

            $this->adminNotices->add_notice(
                'inward_label_delete',
                'notice-error',
                sprintf(__('Unable to delete label %s', 'wc_colissimo'), $trackingNumber)
            );
        } else {
            $this->adminNotices->add_notice(
                'inward_label_delete',
                'notice-success',
                sprintf(__('Label %s deleted', 'wc_colissimo'), $trackingNumber)
            );
        }

        wp_redirect($urlRedirection);
    }
}
