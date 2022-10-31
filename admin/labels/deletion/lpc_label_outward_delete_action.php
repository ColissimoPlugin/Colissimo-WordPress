<?php

defined('ABSPATH') || die('Restricted Access');

class LpcLabelOutwardDeleteAction extends LpcComponent {
    const AJAX_TASK_NAME = 'label/outward/delete';
    const TRACKING_NUMBER_VAR_NAME = 'lpc_label_tracking_number';
    const REDIRECTION_VAR_NAME = 'lpc_redirection';

    /** @var LpcAjax */
    protected $ajaxDispatcher;
    /** @var LpcOutwardLabelDb */
    protected $outwardLabelDb;
    /** @var LpcAdminNotices */
    protected $adminNotices;
    /** @var LpcInwardLabelDb */
    protected $inwardLabelDb;

    public function __construct(
        LpcAjax $ajaxDispatcher = null,
        LpcOutwardLabelDb $outwardLabelDb = null,
        LpcInwardLabelDb $inwardLabelDb = null,
        LpcAdminNotices $adminNotices = null
    ) {
        $this->ajaxDispatcher = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);
        $this->outwardLabelDb = LpcRegister::get('outwardLabelDb', $outwardLabelDb);
        $this->inwardLabelDb  = LpcRegister::get('inwardLabelDb', $inwardLabelDb);
        $this->adminNotices   = LpcRegister::get('lpcAdminNotices', $adminNotices);
    }

    public function getDependencies() {
        return ['ajaxDispatcher', 'outwardLabelDb', 'lpcAdminNotices'];
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

        $trackingNumber      = LpcHelper::getVar(self::TRACKING_NUMBER_VAR_NAME);
        $redirection         = LpcHelper::getVar(self::REDIRECTION_VAR_NAME);
        $inwardLabelsRelated = $this->inwardLabelDb->getLabelsInfosForOutward($trackingNumber);
        $orderId             = $this->outwardLabelDb->getOrderIdByTrackingNumber($trackingNumber);

        switch ($redirection) {
            case LpcLabelQueries::REDIRECTION_WOO_ORDER_EDIT_PAGE:
                $urlRedirection = get_edit_post_link($orderId, '');
                break;
            case LpcLabelQueries::REDIRECTION_COLISSIMO_ORDERS_LISTING:
            default:
                $urlRedirection = admin_url('admin.php?page=wc_colissimo_view');
                break;
        }

        LpcLogger::debug(
            'Delete outward label',
            [
                'tracking_number'       => $trackingNumber,
                'related_inward_labels' => $inwardLabelsRelated,
                'method'                => __METHOD__,
            ]
        );

        $multiParcelsLabels = $this->outwardLabelDb->getMultiParcelsLabels($orderId);
        if (!empty($multiParcelsLabels[$trackingNumber]) && 'FOLLOWER' === $multiParcelsLabels[$trackingNumber]) {
            $masterLabel = array_search('MASTER', $multiParcelsLabels);

            if (!empty($masterLabel)) {
                $this->adminNotices->add_notice(
                    'outward_label_delete',
                    'notice-error',
                    sprintf(__('You cannot delete this label because the label %s is bound to it.', 'wc_colissimo'), $masterLabel)
                );

                return wp_redirect($urlRedirection);
            }
        }

        $result = $this->outwardLabelDb->delete($trackingNumber);

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
                'outward_label_delete',
                'notice-error',
                sprintf(
                    __('Unable to delete label %s', 'wc_colissimo'),
                    $trackingNumber
                )
            );
        } else {
            // If it's the last following parcel, remove also the multi-parcels number
            if (1 === count($multiParcelsLabels) && !empty($multiParcelsLabels[$trackingNumber])) {
                update_post_meta($orderId, 'lpc_multi_parcels_amount', '');
            }

            $noticeText = sprintf(__('Label %s deleted', 'wc_colissimo'), $trackingNumber);
            if (count($inwardLabelsRelated) > 0) {
                $inwardDeletionResult = $this->inwardLabelDb->deleteForOutward($trackingNumber);

                if (count($inwardLabelsRelated) != $inwardDeletionResult) {
                    LpcLogger::error(
                        'Unable to delete some inwards label related to ouwtard',
                        [
                            'tracking_number'       => $trackingNumber,
                            'related_inward_labels' => $inwardLabelsRelated,
                            'result'                => $inwardDeletionResult,
                            'method'                => __METHOD__,
                        ]
                    );
                } else {
                    foreach ($inwardLabelsRelated as $oneInwardLabel) {
                        $noticeText .= '<br>' . sprintf(
                                __('Inward label %s deleted', 'wc_colissimo'),
                                $oneInwardLabel->tracking_number
                            );
                    }
                }
            }

            $this->adminNotices->add_notice(
                'outward_label_delete',
                'notice-success',
                $noticeText
            );
        }

        wp_redirect($urlRedirection);
    }
}
