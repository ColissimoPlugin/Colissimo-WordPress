<?php

defined('ABSPATH') || die('Restricted Access');
require_once LPC_FOLDER . DS . 'lib' . DS . 'MergePdf.class.php';


class LpcLabelInwardDownloadAction extends LpcComponent {
    const AJAX_TASK_NAME = 'label/inward/download';
    const TRACKING_NUMBER_VAR_NAME = 'lpc_label_tracking_number';

    /** @var LpcAjax */
    protected $ajaxDispatcher;
    /** @var LpcInwardLabelDb */
    protected $inwardLabelDb;

    public function __construct(
        LpcAjax $ajaxDispatcher = null,
        LpcInwardLabelDb $inwardLabelDb = null
    ) {
        $this->ajaxDispatcher = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);
        $this->inwardLabelDb  = LpcRegister::get('inwardLabelDb', $inwardLabelDb);
    }

    public function getDependencies() {
        return ['ajaxDispatcher', 'inwardLabelDb'];
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
                    'message' => 'unauthorized access to inward label download',
                ]
            );
        }

        $trackingNumber = LpcHelper::getVar(self::TRACKING_NUMBER_VAR_NAME);
        try {
            $label        = $this->inwardLabelDb->getLabelFor($trackingNumber);
            $labelContent = $label['label'];
            if (empty($labelContent)) {
                throw new Exception('No label content');
            }

            $fileToDownloadName = get_temp_dir() . DS . 'Colissimo.inward(' . $trackingNumber . ').pdf';
            $labelFileName      = 'inward_label.pdf';
            $filesToMerge       = [];
            $labelContentFile   = fopen(sys_get_temp_dir() . DS . $labelFileName, 'w');
            fwrite($labelContentFile, $labelContent);
            fclose($labelContentFile);

            $filesToMerge[] = sys_get_temp_dir() . DS . $labelFileName;

            $cn23Content = $this->inwardLabelDb->getCn23For($trackingNumber);
            if ($cn23Content) {
                $cn23ContentFile = fopen(sys_get_temp_dir() . DS . 'inward_cn23.pdf', 'w');
                fwrite($cn23ContentFile, $cn23Content);
                fclose($cn23ContentFile);
                $filesToMerge[] = sys_get_temp_dir() . DS . 'inward_cn23.pdf';
            }
            MergePdf::merge($filesToMerge, MergePdf::DESTINATION__DISK_DOWNLOAD, $fileToDownloadName);
            foreach ($filesToMerge as $fileToMerge) {
                unlink($fileToMerge);
            }
            unlink($fileToDownloadName);
        } catch (Exception $e) {
            header('HTTP/1.0 404 Not Found');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => $e->getMessage(),
                ]
            );
        }
    }

    public function getUrlForTrackingNumber($trackingNumber) {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME) . '&' . self::TRACKING_NUMBER_VAR_NAME . '=' . $trackingNumber;
    }
}
