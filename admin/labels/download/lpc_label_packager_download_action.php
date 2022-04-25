<?php

defined('ABSPATH') || die('Restricted Access');

class LpcLabelPackagerDownloadAction extends LpcComponent {
    const AJAX_TASK_NAME = 'label/packager/download';
    const TRACKING_NUMBERS_VAR_NAME = 'lpc_tracking_numbers';

    /** @var LpcLabelPackager */
    protected $labelPackager;
    /** @var LpcAjax */
    protected $ajaxDispatcher;
    /** @var LpcOutwardLabelDb */
    protected $outwardLabelDb;

    public function __construct(
        LpcAjax $ajaxDispatcher = null,
        LpcLabelPackager $labelPackager = null,
        LpcOutwardLabelDb $outwardLabelDb = null
    ) {
        $this->ajaxDispatcher = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);
        $this->labelPackager  = LpcRegister::get('labelPackager', $labelPackager);
        $this->outwardLabelDb = LpcRegister::get('outwardLabelDb', $outwardLabelDb);
    }

    public function getDependencies() {
        return ['ajaxDispatcher', 'labelPackager'];
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control']);
    }

    public function control() {
        if (!current_user_can('lpc_download_labels')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => 'unauthorized access to labels package download',
                ]
            );
        }

        $trackingNumbers = explode(',', LpcHelper::getVar(self::TRACKING_NUMBERS_VAR_NAME));

        try {
            $filename = basename('Colissimo_' . date('Y-m-d_H-i') . '.zip');
            header('Content-Type: application/octet-stream');
            header('Content-Transfer-Encoding: Binary');
            header("Content-disposition: attachment; filename=\"$filename\"");

            wp_die($this->labelPackager->generateZip($trackingNumbers));
        } catch (Exception $e) {
            header('HTTP/1.0 404 Not Found');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => $e->getMessage(),
                ]
            );
        }
    }

    public function getUrlForTrackingNumbers(array $trackingNumbers) {
        $emptyLabels = [];
        foreach ($trackingNumbers as $trackingNumber) {
            $label = $this->outwardLabelDb->getLabelFor($trackingNumber);
            if (empty($label['label'])) {
                $emptyLabels[] = $trackingNumber;
            }
        }
        if (!empty($emptyLabels)) {
            $trackingNumbers = array_diff($trackingNumbers, $emptyLabels);

            if (empty($trackingNumbers)) {
                return false;
            }
        }

        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME)
               . '&' . self::TRACKING_NUMBERS_VAR_NAME . '=' . implode(',', $trackingNumbers);
    }
}
