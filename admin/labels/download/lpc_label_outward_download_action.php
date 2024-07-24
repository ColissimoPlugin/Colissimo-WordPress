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

    public function getDependencies(): array {
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
            $labelFile          = 'outward_label.pdf';
            $filesToMerge       = [];
            $tempDir            = sys_get_temp_dir() . DS;
            $labelContentFile   = fopen($tempDir . $labelFile, 'w');
            fwrite($labelContentFile, $labelContent);
            fclose($labelContentFile);

            $labelFilename  = $tempDir . $labelFile;
            $filesToMerge[] = $labelFilename;

            $needInvoice = 'yes' === LpcHelper::get_option('add_invoice_download_label', 'yes');

            $invoiceFilename = null;
            if ($needInvoice) {
                $lpcInvoiceGenerateAction = LpcRegister::get('invoiceGenerateAction');
                $invoiceFilename          = $tempDir . 'invoice.pdf';
                $lpcInvoiceGenerateAction->generateInvoice($label['order_id'], $invoiceFilename, MergePdf::DESTINATION__DISK);
                $filesToMerge[] = $invoiceFilename;
            }

            $cn23Filename = null;
            $cn23Data     = $this->outwardLabelDb->getCn23For($trackingNumber);
            $cn23Content  = LpcLabelGenerationPayload::LABEL_FORMAT_PDF === $cn23Data['format'] ? $cn23Data['cn23'] : '';
            if (!empty($cn23Content)) {
                if ($needInvoice) {
                    $filesToMerge[] = $invoiceFilename;
                }
                $cn23ContentFile = fopen($tempDir . 'outward_cn23.pdf', 'w');
                fwrite($cn23ContentFile, $cn23Content);
                fclose($cn23ContentFile);
                $cn23Filename   = $tempDir . 'outward_cn23.pdf';
                $filesToMerge[] = $cn23Filename;
            }

            /**
             * Filter on the content of the downloaded PDF label
             *
             * @since 1.7.6
             */
            $filesToMerge = apply_filters(
                'lpc_pdf_label',
                $filesToMerge,
                $label,
                $labelFilename,
                $invoiceFilename,
                $cn23Filename
            );

            MergePdf::merge($filesToMerge, MergePdf::DESTINATION__DISK_DOWNLOAD, $tempDir . $fileToDownloadName);
            foreach ($filesToMerge as $fileToMerge) {
                unlink($fileToMerge);
            }
            unlink($tempDir . $fileToDownloadName);
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
