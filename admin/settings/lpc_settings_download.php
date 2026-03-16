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
        $logs = LpcLogger::get_logs('', LpcLogger::ALL_LINES);
        if (!empty($logs)) {
            $this->downloadFile(
                [
                    'content'  => str_replace('<br />', PHP_EOL, $logs),
                    'fileName' => 'colissimo.log',
                ]
            );
        } else {
            esc_html_e('The logs file is empty', 'wc_colissimo');
        }
    }

    public function doc() {
        $this->downloadFile(
            [
                'filePath' => self::DOC_FILE_PATH,
                'fileName' => 'Guide Colissimo pour WordPress.pdf',
            ]
        );
    }

    public function docEN() {
        $this->downloadFile(
            [
                'filePath' => self::DOC_EN_FILE_PATH,
                'fileName' => 'Colissimo Guide for WordPress.pdf',
            ]
        );
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

    private function downloadFile(array $options): void {
        if (!empty($options['filePath'])) {
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }

            $fileContents = $wp_filesystem->get_contents($options['filePath']);
            if (false === $fileContents) {
                wp_die('Could not read file.');
            }
        } elseif (isset($options['content'])) {
            $fileContents = $options['content'];
        } else {
            wp_die('Could not read file.');
        }

        header('Content-Disposition: attachment; filename="' . esc_attr($options['fileName']) . '"');
        header('Content-Type: text/plain');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($fileContents));

        echo $fileContents;
        exit;
    }
}
