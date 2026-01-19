<?php

defined('ABSPATH') || die('Restricted Access');

class LpcBordereauDownloadAction extends LpcComponent {
    const AJAX_TASK_NAME = 'bordereau/download';
    const BORDEREAU_ID_VAR_NAME = 'lpc_bordereau_id';

    /** @var LpcBordereauGenerationApi */
    protected $bordereauGenerationApi;
    /** @var LpcAjax */
    protected $ajaxDispatcher;
    /** @var LpcBordereauDb */
    protected $bordereauDb;

    public function __construct(
        ?LpcAjax $ajaxDispatcher = null,
        ?LpcBordereauGenerationApi $bordereauGenerationApi = null,
        ?LpcBordereauDb $bordereauDb = null
    ) {
        $this->ajaxDispatcher         = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);
        $this->bordereauGenerationApi = LpcRegister::get('bordereauGenerationApi', $bordereauGenerationApi);
        $this->bordereauDb            = LpcRegister::get('bordereauDb', $bordereauDb);
    }

    public function getDependencies(): array {
        return ['ajaxDispatcher', 'bordereauGenerationApi', 'bordereauDb'];
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

        $deliverySlipId = LpcHelper::getVar(self::BORDEREAU_ID_VAR_NAME, 0, 'int');
        try {
            $deliverySlip = $this->bordereauDb->getDeliverySlipByColissimoId($deliverySlipId);
            if (empty($deliverySlip)) {
                // TODO temporary fetch old delivery slips with Colissimo API, remove this in 2027
                $deliverySlip = $this->bordereauGenerationApi->getBordereauByNumber($deliverySlipId)->bordereau->bordereauDataHandler;

                if (empty($deliverySlip)) {
                    throw new Exception(__('File not found', 'wc_colissimo'));
                }
            }

            $filename = basename('Bordereau(' . $deliverySlipId . ').pdf');
            header('Content-Type: application/octet-stream');
            header('Content-Transfer-Encoding: Binary');
            header("Content-disposition: attachment; filename=\"$filename\"");

            die($deliverySlip);
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
