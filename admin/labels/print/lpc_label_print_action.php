<?php

defined('ABSPATH') || die('Restricted Access');
require_once LPC_FOLDER . DS . 'lib' . DS . 'MergePdf.class.php';


class LpcLabelPrintAction extends LpcComponent {
    const AJAX_TASK_NAME = 'label/print';
    const TRACKING_NUMBERS_VAR_NAME = 'lpc_tracking_numbers';
    const NEED_INVOICE_VAR_NAME = 'lpc_need_invoice';

    const PRINT_LABEL_TYPE_OUTWARD_AND_INWARD = 'both';

    /** @var LpcAjax */
    protected $ajaxDispatcher;
    /** @var LpcOutwardLabelDb */
    protected $outwardLabelDb;
    /** @var LpcInwardLabelDb */
    protected $inwardLabelDb;

    public function __construct(
        LpcAjax $ajaxDispatcher = null,
        LpcOutwardLabelDb $outwardLabelDb = null,
        LpcInwardLabelDb $inwardLabelDb = null
    ) {
        $this->ajaxDispatcher = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);
        $this->outwardLabelDb = LpcRegister::get('outwardLabelDb', $outwardLabelDb);
        $this->inwardLabelDb  = LpcRegister::get('inwardLabelDb', $inwardLabelDb);
    }

    public function getDependencies() {
        return ['ajaxDispatcher', 'outwardLabelDb', 'inwardLabelDb'];
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control']);
    }

    public function control() {
        if (!current_user_can('lpc_print_labels')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => 'unauthorized access to all label print',
                ]
            );
        }

        $stringTrackingNumbers = LpcHelper::getVar(self::TRACKING_NUMBERS_VAR_NAME);
        $needInvoice           = (bool) LpcHelper::getVar(self::NEED_INVOICE_VAR_NAME, false);

        $trackingNumbers = explode(',', $stringTrackingNumbers);
        try {
            $filesToMerge = [];

            foreach ($trackingNumbers as $trackingNumber) {
                $isOutward = true;
                $label     = $this->getLabel($trackingNumber, $isOutward);

                if (false === $label || false === $label['label']) {
                    continue;
                }

                if (LpcLabelGenerationPayload::LABEL_FORMAT_PDF === $label['format']) {
                    $tmpDir = ini_get('upload_tmp_dir');
                    if (empty($tmpDir)) {
                        $tmpDir = sys_get_temp_dir();
                    }

                    $labelFileName = $tmpDir . DS . 'label(' . $trackingNumber . ').pdf';

                    $labelContentFile = fopen($labelFileName, 'w');
                    fwrite($labelContentFile, $label['label']);
                    fclose($labelContentFile);
                    $filesToMerge[] = $labelFileName;
                }

                $cn23Content = $isOutward
                    ?
                    $this->outwardLabelDb->getCn23For($trackingNumber)
                    :
                    $this->inwardLabelDb->getCn23For($trackingNumber);

                if ($needInvoice) {
                    $lpcInvoiceGenerateAction = LpcRegister::get('invoiceGenerateAction');
                    $invoiceFilename          = sys_get_temp_dir() . DS . 'invoice(' . $label['order_id'] . ').pdf';

                    if (!in_array($invoiceFilename, $filesToMerge)) {
                        $lpcInvoiceGenerateAction->generateInvoice(
                            $label['order_id'],
                            $invoiceFilename,
                            MergePdf::DESTINATION__DISK
                        );
                        $filesToMerge[] = $invoiceFilename;

                        if ($cn23Content) {
                            $filesToMerge[] = $invoiceFilename;
                        }
                    }
                }

                if ($cn23Content) {
                    $cn23FileName    = sys_get_temp_dir() . DS . 'cn23(' . $trackingNumber . ').pdf';
                    $cn23ContentFile = fopen($cn23FileName, 'w');
                    fwrite($cn23ContentFile, $cn23Content);
                    fclose($cn23ContentFile);
                    $filesToMerge[] = $cn23FileName;
                }

                if ($isOutward) {
                    $this->outwardLabelDb->updatePrintedLabel($trackingNumber);
                } else {
                    $this->inwardLabelDb->updatePrintedLabel($trackingNumbers);
                }
            }

            if (!empty($filesToMerge)) {
                MergePdf::merge($filesToMerge, MergePdf::DESTINATION__INLINE);
                foreach ($filesToMerge as $fileToMerge) {
                    unlink($fileToMerge);
                }
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

    public function getUrlForTrackingNumbers($trackingNumbers = [], $needInvoice = false) {
        if (!is_array($trackingNumbers)) {
            $trackingNumbers = [$trackingNumbers];
        }

        $emptyLabels = [];
        foreach ($trackingNumbers as $trackingNumber) {
            $isOutward = true;
            $label     = $this->getLabel($trackingNumber, $isOutward);
            if ($isOutward && empty($label['label'])) {
                $emptyLabels[] = $trackingNumber;
            }
        }
        if (!empty($emptyLabels)) {
            $trackingNumbers = array_diff($trackingNumbers, $emptyLabels);

            if (empty($trackingNumbers)) {
                return false;
            }
        }

        $stringTrackingNumbers = implode(',', $trackingNumbers);

        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME)
               . '&' . self::TRACKING_NUMBERS_VAR_NAME . '=' . $stringTrackingNumbers
               . '&' . self::NEED_INVOICE_VAR_NAME . '=' . $needInvoice;
    }

    protected function getLabel($trackingNumber, &$isOutward) {
        $label = $this->outwardLabelDb->getLabelFor($trackingNumber);

        if (!empty($label['label'])) {
            $isOutward = true;

            return $label;
        }

        $label = $this->inwardLabelDb->getLabelFor($trackingNumber);

        if (!empty($label['label'])) {
            $isOutward = false;

            return $label;
        }

        return false;
    }
}
