<?php

defined('ABSPATH') || die('Restricted Access');
require_once LPC_FOLDER . DS . 'lib' . DS . 'MergePdf.class.php';

class LpcLabelOutwardDownloadAction extends LpcComponent {
    const AJAX_TASK_NAME = 'label/outward/download';
    const TRACKING_NUMBER_VAR_NAME = 'lpc_label_tracking_number';

    /** @var LpcAjax */
    protected $ajaxDispatcher;
    /** @var LpcOutwardLabelDb */
    protected $outwardLabelDb;

    public function __construct(
        LpcAjax $ajaxDispatcher = null,
        LpcOutwardLabelDb $outwardLabelDb = null
    ) {
        $this->ajaxDispatcher = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);
        $this->outwardLabelDb = LpcRegister::get('outwardLabelDb', $outwardLabelDb);
    }

    public function getDependencies() {
        return ['ajaxDispatcher', 'outwardLabelDb'];
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
                    'message' => 'unauthorized access to outward label download',
                ]
            );
        }

        $trackingNumber = LpcHelper::getVar(self::TRACKING_NUMBER_VAR_NAME);
        try {
            $label        = $this->outwardLabelDb->getLabelFor($trackingNumber);
            $labelContent = $label['label'];
            if (empty($labelContent)) {
                throw new Exception('No label content');
            }

            $fileToDownloadName = 'Colissimo.outward(' . $trackingNumber . ').pdf';
            $labelFileName      = 'outward_label.pdf';
            $filesToMerge       = [];
            $labelContentFile   = fopen(sys_get_temp_dir() . DS . $labelFileName, 'w');
            fwrite($labelContentFile, $labelContent);
            fclose($labelContentFile);

            $filesToMerge[] = sys_get_temp_dir() . DS . $labelFileName;

            $needInvoice = 'yes' === LpcHelper::get_option('add_invoice_download_label', 'yes');

            if ($needInvoice) {
                $lpcInvoiceGenerateAction = LpcRegister::get('invoiceGenerateAction');
                $invoiceFilename          = sys_get_temp_dir() . DS . 'invoice.pdf';
                $lpcInvoiceGenerateAction->generateInvoice($label['order_id'], $invoiceFilename, MergePdf::DESTINATION__DISK);
                $filesToMerge[] = $invoiceFilename;
            }

            $cn23Content = $this->outwardLabelDb->getCn23For($trackingNumber);
            if ($cn23Content) {
                if ($needInvoice) {
                    $filesToMerge[] = $invoiceFilename;
                }
                $cn23ContentFile = fopen(sys_get_temp_dir() . DS . 'outward_cn23.pdf', 'w');
                fwrite($cn23ContentFile, $cn23Content);
                fclose($cn23ContentFile);
                $filesToMerge[] = sys_get_temp_dir() . DS . 'outward_cn23.pdf';
            }
            MergePdf::merge($filesToMerge, MergePdf::DESTINATION__DISK_DOWNLOAD, __DIR__ . DS . $fileToDownloadName);
            foreach ($filesToMerge as $fileToMerge) {
                unlink($fileToMerge);
            }
            unlink(__DIR__ . DS . $fileToDownloadName);
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
