<?php

class LpcLabelPackager extends LpcComponent {

    /** @var LpcInvoiceGenerateAction */
    protected $invoiceGenerateAction;
    /** @var LpcOutwardLabelDb */
    protected $outwardLabelDb;
    /** @var LpcInwardLabelDb */
    protected $inwardLabelDb;

    public function __construct(
        LpcOutwardLabelDb $outwardLabelDb = null,
        LpcInwardLabelDb $inwardLabelDb = null

    ) {
        $this->invoiceGenerateAction = LpcRegister::get('invoiceGenerateAction');
        $this->outwardLabelDb        = LpcRegister::get('outwardLabelDb', $outwardLabelDb);
        $this->inwardLabelDb         = LpcRegister::get('inwardLabelDb', $inwardLabelDb);
    }

    public function getDependencies(): array {
        return ['invoiceGenerateAction', 'outwardLabelDb', 'inwardLabelDb'];
    }

    public function generateZip(array $trackingNumbers) {
        $zip      = new ZipArchive();
        $filename = tempnam(sys_get_temp_dir(), 'colissimo_');
        $tmpFiles = [];

        try {
            $zip->open($filename, ZipArchive::OVERWRITE);

            foreach ($trackingNumbers as $trackingNumber) {
                $label     = $this->outwardLabelDb->getLabelFor($trackingNumber);
                $isOutward = true;
                $isInward  = false;

                if (empty($label['label'])) {
                    $label = $this->inwardLabelDb->getLabelFor($trackingNumber);

                    $isOutward = false;
                    $isInward  = true;
                }

                if (empty($label['label'])) {
                    continue;
                }

                $orderId = $label['order_id'];

                $zipDirname = $orderId;
                $zip->addEmptyDir($zipDirname);

                $labelFormat = !empty($label['format']) ? $label['format'] : LpcLabelGenerationPayload::LABEL_FORMAT_PDF;
                if ($isOutward) {
                    $zip->addFromString(
                        $zipDirname . '/outward_label(' . $trackingNumber . ').' . strtolower($labelFormat),
                        $label['label']
                    );

                    if ('yes' === LpcHelper::get_option('add_invoice_zip_label', 'yes')) {
                        $tmpFiles[] = $invoiceFilename = sys_get_temp_dir() . DS . $orderId . '_invoice.pdf';

                        $this->invoiceGenerateAction->generateInvoice(
                            $orderId,
                            $invoiceFilename,
                            MergePdf::DESTINATION__DISK
                        );
                        $zip->addFile($invoiceFilename, $zipDirname . '/invoice(' . $orderId . ').pdf');
                    }

                    $outwardCn23 = $this->outwardLabelDb->getCn23For($trackingNumber);
                    if (!empty($outwardCn23['cn23'])) {
                        $cn23Format = !empty($outwardCn23['format']) ? $outwardCn23['format'] : LpcLabelGenerationPayload::LABEL_FORMAT_PDF;
                        $zip->addFromString($zipDirname . '/outward_cn23(' . $trackingNumber . ').' . strtolower($cn23Format), $outwardCn23['cn23']);
                    }
                }

                if ($isInward) {
                    $zip->addFromString(
                        $zipDirname . '/inward_label(' . $trackingNumber . ').' . strtolower($labelFormat),
                        $label['label']
                    );

                    $inwardCn23 = $this->inwardLabelDb->getCn23For($trackingNumber);
                    if (!empty($inwardCn23['cn23'])) {
                        $cn23Format = !empty($inwardCn23['format']) ? $inwardCn23['format'] : LpcLabelGenerationPayload::LABEL_FORMAT_PDF;
                        $zip->addFromString($zipDirname . '/inward_cn23(' . $trackingNumber . ').' . strtolower($cn23Format), $inwardCn23['cn23']);
                    }
                }
            }

            $zip->close();

            return readfile($filename);
        } finally {
            array_map(
                function ($tmpFile) {
                    unlink($tmpFile);
                },
                $tmpFiles
            );

            unlink($filename);
        }
    }
}
