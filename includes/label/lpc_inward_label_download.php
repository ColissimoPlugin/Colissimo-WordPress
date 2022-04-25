<?php

defined('ABSPATH') || die('Restricted Access');
require_once LPC_FOLDER . DS . 'lib' . DS . 'MergePdf.class.php';


class LpcLabelInwardDownloadAccountAction extends LpcComponent {
    const AJAX_TASK_NAME = 'account/label/inward/download';
    const TRACKING_NUMBER_VAR_NAME = 'lpc_label_tracking_number';

    /** @var LpcAjax */
    protected $ajaxDispatcher;
    /** @var LpcInwardLabelDb */
    protected $inwardLabelDb;
    /** @var LpcLabelGenerationInward */
    protected $labelGenerationInward;
    /** @var LpcOutwardLabelDb */
    protected $outwardLabelDb;

    public function __construct(
        LpcAjax $ajaxDispatcher = null,
        LpcInwardLabelDb $inwardLabelDb = null,
        LpcLabelGenerationInward $labelGenerationInward = null,
        LpcOutwardLabelDb $outwardLabelDb = null
    ) {
        $this->ajaxDispatcher        = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);
        $this->inwardLabelDb         = LpcRegister::get('inwardLabelDb', $inwardLabelDb);
        $this->labelGenerationInward = LpcRegister::get('labelGenerationInward', $labelGenerationInward);
        $this->outwardLabelDb        = LpcRegister::get('outwardLabelDb', $outwardLabelDb);
    }

    public function getDependencies() {
        return ['ajaxDispatcher', 'inwardLabelDb', 'labelGenerationInward', 'outwardLabelDb'];
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control']);
    }

    public function control() {
        $trackingNumber = LpcHelper::getVar(self::TRACKING_NUMBER_VAR_NAME);

        $outwardLabel = $this->outwardLabelDb->getLabelFor($trackingNumber);

        try {
            $label        = $this->inwardLabelDb->getLabelByOutwardNumber($trackingNumber);
            $labelContent = $label['label'];
            if (empty($labelContent)) {
                $order = new WC_Order($outwardLabel['order_id']);
                $this->labelGenerationInward->generate($order, ['outward_label_number' => $trackingNumber]);
                $label        = $this->inwardLabelDb->getLabelByOutwardNumber($trackingNumber);
                $labelContent = $label['label'];

                if (empty($labelContent)) {
                    echo __('There has been an error while downloading the return label, please contact us for more information.', 'wc_colissimo');
                    exit;
                }
            }
            $inwardTrackingNumber = $label['label_number'];

            $fileToDownloadName = get_temp_dir() . DS . 'Colissimo.inward(' . $inwardTrackingNumber . ').pdf';
            $labelFileName      = 'inward_label.pdf';
            $filesToMerge       = [];
            $labelContentFile   = fopen(sys_get_temp_dir() . DS . $labelFileName, 'w');
            fwrite($labelContentFile, $labelContent);
            fclose($labelContentFile);

            $filesToMerge[] = sys_get_temp_dir() . DS . $labelFileName;

            $cn23Content = $this->inwardLabelDb->getCn23For($inwardTrackingNumber);
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
