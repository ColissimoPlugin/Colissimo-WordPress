<?php

defined('ABSPATH') || die('Restricted Access');

class LpcBordereauDeleteAction extends LpcComponent {
    const AJAX_TASK_NAME = 'bordereau/delete';
    const BORDEREAU_ID_VAR_NAME = 'lpc_bordereau_id';
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

    public function getDependencies(): array {
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

        if (LpcBordereauQueries::REDIRECTION_COLISSIMO_BORDEREAU_LISTING === $redirection) {
            $urlRedirection = admin_url('admin.php?page=wc_colissimo_view&tab=slip-history');
        } else {
            $urlRedirection = admin_url('admin.php?page=wc_colissimo_view');
        }

        LpcLogger::debug(
            'Delete bordereau',
            [
                'bordereau_id' => $bordereauID,
                'method'       => __METHOD__,
            ]
        );

        $result = LpcBordereauQueries::deleteBordereauById($bordereauID);

        if ($result) {
            $this->adminNotices->add_notice(
                'bordereau_delete',
                'notice-success',
                sprintf(__('Bordereau n°%d deleted', 'wc_colissimo'), $bordereauID)
            );
        } else {
            $this->adminNotices->add_notice(
                'bordereau_delete',
                'notice-error',
                sprintf(__('Unable to delete bordereau n°%d', 'wc_colissimo'), $bordereauID));
        }
        wp_redirect($urlRedirection);
    }

    public function getUrlForBordereau($bordereauId, $redirection) {
        $url = $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME)
               . '&' . self::BORDEREAU_ID_VAR_NAME . '=' . (int) $bordereauId
               . '&' . self::REDIRECTION_VAR_NAME . '=' . $redirection;

        return $url;
    }
}
