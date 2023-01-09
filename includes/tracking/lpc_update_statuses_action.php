<?php

defined('ABSPATH') || die('Restricted Access');

class LpcUpdateStatusesAction extends LpcComponent {
    const AJAX_TASK_NAME = 'tracking_update_all_statuses';

    protected $unifiedTrackingApi;

    protected $ajaxDispatcher;

    public function __construct(
        LpcAjax $ajaxDispatcher = null,
        LpcUnifiedTrackingApi $unifiedTrackingApi = null
    ) {
        $this->ajaxDispatcher     = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);
        $this->unifiedTrackingApi = LpcRegister::get('unifiedTrackingApi', $unifiedTrackingApi);
    }

    public function getDependencies() {
        return ['ajaxDispatcher', 'unifiedTrackingApi'];
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control']);
    }

    public function control() {
        if (!current_user_can('lpc_manage_labels')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => 'unauthorized access to statuses update',
                ]
            );
        }

        $this->unifiedTrackingApi->updateAllStatuses();

        $lpc_admin_notices = LpcRegister::get('lpcAdminNotices');
        $lpc_admin_notices->add_notice(
            'shipping_statuses_updated',
            'notice-success',
            __(
                'The statuses update process has started. Depending of the numbers of orders you have, it may take a few minutes to update all statuses. You will find more information by checking logs.',
                'wc_colissimo'
            )
        );

        wp_redirect(admin_url('admin.php?page=wc_colissimo_view'));
    }

    public function getUpdateAllStatusesUrl() {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME);
    }
}
