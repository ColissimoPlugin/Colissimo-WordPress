<?php

defined('ABSPATH') || die('Restricted Access');

class LpcSettingsDownload extends LpcComponent {
    const AJAX_TASK_NAME_LOGS = 'logs/download';
    const AJAX_TASK_NAME_DOC = 'doc/download';
    const AJAX_TASK_NAME_DOC_EN = 'docEN/download';
    const DOC_FILE_PATH = LPC_FOLDER . 'resources' . DS . 'doc.pdf';
    const DOC_EN_FILE_PATH = LPC_FOLDER . 'resources' . DS . 'docEN.pdf';

    /** @var LpcAjax */
    protected $ajaxDispatcher;

    public function __construct(
        ?LpcAjax $ajaxDispatcher = null
    ) {
        $this->ajaxDispatcher = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);
    }

    public function getDependencies(): array {
        return [
            'ajaxDispatcher',
        ];
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME_LOGS, [$this, 'logs']);
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME_DOC, [$this, 'doc']);
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME_DOC_EN, [$this, 'docEN']);
    }

    public function logs() {
        $logsPath = LpcLogger::getLogsPath();
        if (file_exists($logsPath)) {
            $this->downloadFile($logsPath, 'colissimo.log');
        } else {
            esc_html_e('The logs file is empty', 'wc_colissimo');
        }
    }

    public function doc() {
        $this->downloadFile(self::DOC_FILE_PATH, 'Guide Colissimo pour WordPress.pdf');
    }

    public function docEN() {
        $this->downloadFile(self::DOC_EN_FILE_PATH, 'Colissimo Guide for WordPress.pdf');
    }

    private function downloadFile(string $filePath, string $fileName) {
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Type: application/force-download');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Type: text/plain');
        readfile($filePath);
    }

    public function getUrl(string $type): string {
        if ('logs' === $type) {
            return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME_LOGS);
        } elseif ('doc' === $type) {
            return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME_DOC);
        } elseif ('docEN' === $type) {
            return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME_DOC_EN);
        } else {
            throw new \InvalidArgumentException('Unknown type for LpcSettingsDownload');
        }
    }
}
