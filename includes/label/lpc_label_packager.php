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

    public function getDependencies() {
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

                if ($isOutward) {
                    $outwardLabel       = $this->outwardLabelDb->getLabelFor($trackingNumber);
                    $outwardLabelFormat = !empty($outwardLabel['format']) ? $outwardLabel['format'] : LpcLabelGenerationPayload::LABEL_FORMAT_PDF;
                    if (!empty($outwardLabel['label'])) {
                        $zip->addFromString(
                            $zipDirname . '/outward_label(' . $trackingNumber . ').' . strtolower($outwardLabelFormat),
                            $outwardLabel['label']
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
                    }

                    $outwardCn23 = $this->outwardLabelDb->getCn23For($trackingNumber);
                    if (!empty($outwardCn23)) {
                        $zip->addFromString($zipDirname . '/outward_cn23(' . $trackingNumber . ').pdf', $outwardCn23);
                    }
                }

                if ($isInward) {
                    $inwardLabel       = $this->inwardLabelDb->getLabelFor($trackingNumber);
                    $inwardLabelFormat = !empty($inwardLabel['format']) ? $inwardLabel['format'] : LpcLabelGenerationPayload::LABEL_FORMAT_PDF;
                    if (!empty($inwardLabel['label'])) {
                        $zip->addFromString(
                            $zipDirname . '/inward_label(' . $trackingNumber . ').' . strtolower($inwardLabelFormat),
                            $inwardLabel['label']
                        );
                    }

                    $inwardCn23 = $this->inwardLabelDb->getCn23For($trackingNumber);
                    if (!empty($inwardCn23)) {
                        $zip->addFromString($zipDirname . '/inward_cn23(' . $trackingNumber . ').pdf', $inwardCn23);
                    }
                }
            }

            $zip->close();

            $content = readfile($filename);

            return $content;
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
