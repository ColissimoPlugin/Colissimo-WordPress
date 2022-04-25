<?php

defined('ABSPATH') || die('Restricted Access');
require_once LPC_FOLDER . DS . 'lib' . DS . 'MergePdf.class.php';


class LpcBordereauPrintAction extends LpcComponent {
    const AJAX_TASK_NAME = 'bordereau/print';
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
        if (!current_user_can('lpc_print_bordereau')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => 'unauthorized access to bordereau print',
                ]
            );
        }

        try {
            $bordereauID = LpcHelper::getVar(self::BORDEREAU_ID_VAR_NAME);
            $bordereau   = $this->bordereauGenerationApi->getBordereauByNumber($bordereauID)->bordereau;

            $tmpDir = ini_get('upload_tmp_dir');
            if (empty($tmpDir)) {
                $tmpDir = sys_get_temp_dir();
            }

            $bordereauFileName = $tmpDir . DS . 'bordereau(' . $bordereau->bordereauHeader->bordereauNumber . ').pdf';

            $bordereauContentFile = fopen($bordereauFileName, 'w');
            fwrite($bordereauContentFile, $bordereau->bordereauDataHandler);
            fclose($bordereauContentFile);

            if (!empty($bordereauFileName)) {
                MergePdf::merge([$bordereauFileName], MergePdf::DESTINATION__INLINE);
            }
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

}
