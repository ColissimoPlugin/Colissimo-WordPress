<?php

defined('ABSPATH') || die('Restricted Access');

class LpcBordereauDownloadAction extends LpcComponent {
    const AJAX_TASK_NAME = 'bordereau/download';
    const BORDEREAU_ID_VAR_NAME = 'lpc_bordereau_id';

    /** @var LpcBordereauGenerationApi */
    protected $bordereauGenerationApi;
    /** @var LpcAjax */
    protected $ajaxDispatcher;

    public function __construct(
        LpcAjax $ajaxDispatcher = null,
        LpcBordereauGenerationApi $bordereauGenerationApi = null
    ) {
        $this->ajaxDispatcher         = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);
        $this->bordereauGenerationApi = LpcRegister::get('bordereauGenerationApi', $bordereauGenerationApi);
    }

    public function getDependencies() {
        return ['ajaxDispatcher', 'bordereauGenerationApi'];
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control']);
    }

    public function control() {
        if (!current_user_can('lpc_download_bordereau')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => 'unauthorized access to bordereau download',
                ]
            );
        }

        $bordereauId = LpcHelper::getVar(self::BORDEREAU_ID_VAR_NAME);
        try {
            $bordereau = $this->bordereauGenerationApi->getBordereauByNumber($bordereauId)->bordereau;

            $filename = basename('Bordereau(' . $bordereau->bordereauHeader->bordereauNumber . ').pdf');
            header('Content-Type: application/octet-stream');
            header('Content-Transfer-Encoding: Binary');
            header("Content-disposition: attachment; filename=\"$filename\"");

            die($bordereau->bordereauDataHandler);
        } catch (Exception $e) {
            header('HTTP/1.0 404 Not Found');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => $e->getMessage(),
                ]
            );
        }
    }

    public function getUrlForBordereau($bordereauId) {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME) . '&' . self::BORDEREAU_ID_VAR_NAME . '=' . (int) $bordereauId;
    }

    public function getBorderauDownloadLink($bordereauNumber) {
        if (!empty($bordereauNumber)) {
            $bordereauDownloadUrl = $this->getUrlForBordereau($bordereauNumber);

            return $bordereauDownloadUrl;
        }
    }
}
