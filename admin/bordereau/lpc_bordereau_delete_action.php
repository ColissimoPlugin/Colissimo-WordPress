<?php

defined('ABSPATH') || die('Restricted Access');

class LpcBordereauDeleteAction extends LpcComponent {
    const AJAX_TASK_NAME = 'bordereau/delete';
    const BORDEREAU_ID_VAR_NAME = 'lpc_bordereau_id';
    const ORDER_ID_VAR_NAME = 'lpc_order_id';
    const REDIRECTION_VAR_NAME = 'lpc_redirection';

    /** @var LpcAjax */
    protected $ajaxDispatcher;
    /** @var LpcOutwardLabelDb */
    protected $outwardLabelDb;
    /** @var LpcAdminNotices */
    protected $adminNotices;

    public function __construct(
        LpcAjax $ajaxDispatcher = null,
        LpcOutwardLabelDb $outwardLabelDb = null,
        LpcAdminNotices $adminNotices = null
    ) {
        $this->ajaxDispatcher = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);
        $this->outwardLabelDb = LpcRegister::get('outwardLabelDb', $outwardLabelDb);
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

    public function control() {
        if (!current_user_can('lpc_delete_bordereau')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => 'unauthorized access to bordereau deletion',
                ]
            );
        }
        $bordereauID = LpcHelper::getVar(self::BORDEREAU_ID_VAR_NAME);
        $redirection = LpcHelper::getVar(self::REDIRECTION_VAR_NAME);
        $orderId     = LpcHelper::getVar(self::ORDER_ID_VAR_NAME);

        if (LpcLabelQueries::REDIRECTION_COLISSIMO_ORDERS_LISTING === $redirection) {
            $urlRedirection = admin_url('admin.php?page=wc_colissimo_view');
        }

        LpcLogger::debug(
            'Delete bordereau',
            [
                'bordereau_id' => $bordereauID,
                'method'       => __METHOD__,
            ]
        );

        $result = $this->outwardLabelDb->deleteBordereau($bordereauID, $orderId);

        if (empty($result)) {
            LpcLogger::error(
                sprintf('Unable to delete bordereau n°%d', $bordereauID),
                [
                    'bordereau_id' => $bordereauID,
                    'result'       => $result,
                    'method'       => __METHOD__,
                ]
            );

            $this->adminNotices->add_notice(
                'bordereau_delete',
                'notice-error',
                sprintf(__('Unable to delete bordereau n°%d', 'wc_colissimo'), $bordereauID));
        } else {
            $bordereauIdsStored = get_post_meta($orderId, LpcBordereauGeneration::BORDEREAU_ID_META_KEY, true);
            if (empty($bordereauIdsStored)) {
                $bordereauIdsStored = [];
            }
            if (!empty($bordereauIdsStored)) {
                $bordereauIdsStored = explode(',', $bordereauIdsStored);
            }

            $indexBordereauId = array_search($bordereauID, $bordereauIdsStored);
            unset($bordereauIdsStored[$indexBordereauId]);

            update_post_meta($orderId, LpcBordereauGeneration::BORDEREAU_ID_META_KEY, implode(',', $bordereauIdsStored));

            $this->adminNotices->add_notice(
                'bordereau_delete',
                'notice-success',
                sprintf(__('Bordereau n°%d deleted', 'wc_colissimo'), $bordereauID)
            );
        }
        wp_redirect($urlRedirection);
    }

    public function getUrlForBordereau($bordereauId, $orderId, $redirection) {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME)
               . '&' . self::BORDEREAU_ID_VAR_NAME . '=' . (int) $bordereauId
               . '&' . self::ORDER_ID_VAR_NAME . '=' . (int) $orderId
               . '&' . self::REDIRECTION_VAR_NAME . '=' . $redirection;
    }
}
