<?php

defined('ABSPATH') || die('Restricted Access');

class LpcSettingsLogsDownload extends LpcComponent {
    const AJAX_TASK_NAME = 'logs/download';

    /** @var LpcAjax */
    protected $ajaxDispatcher;

    public function __construct(
        LpcAjax $ajaxDispatcher = null
    ) {
        $this->ajaxDispatcher = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);
    }

    public function getDependencies(): array {
        return ['ajaxDispatcher'];
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control']);
    }

    public function control() {
        header('Content-Disposition: attachment; filename="colissimo.log"');
        header('Content-Type: application/force-download');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Type: text/plain');

        if (file_exists(LpcLogger::LOG_FILE)) {
            readfile(LpcLogger::LOG_FILE);
        } else {
            echo __('The log file is empty', 'wc_colissimo');
        }
    }

    public function getUrl() {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME);
    }
}
