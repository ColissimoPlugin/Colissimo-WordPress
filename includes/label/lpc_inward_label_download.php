<?php

defined('ABSPATH') || die('Restricted Access');

require_once LPC_FOLDER . DS . 'lib' . DS . 'MergePdf.class.php';

class LpcLabelInwardDownloadAccountAction extends LpcComponent {
    const AJAX_TASK_NAME = 'account/label/inward/download';
    const PRODUCTS_VAR_NAME = 'lpc_label_products';
    const ORDER_ID_VAR_NAME = 'lpc_label_order_id';
    const LABEL_NUMBER_VAR_NAME = 'lpc_label_number';

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

    public function getDependencies(): array {
        return ['ajaxDispatcher', 'inwardLabelDb', 'labelGenerationInward', 'outwardLabelDb'];
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control']);
    }

    public function control() {
        $orderId     = LpcHelper::getVar(self::ORDER_ID_VAR_NAME);
        $products    = LpcHelper::getVar(self::PRODUCTS_VAR_NAME);
        $labelNumber = LpcHelper::getVar(self::LABEL_NUMBER_VAR_NAME);

        if (!empty($orderId)) {
            if (!empty($products)) {
                $this->generateCustomLabel($orderId, $products);
            } elseif (!empty($labelNumber)) {
                $label = $this->inwardLabelDb->getLabelFor($labelNumber);
                $this->downloadLabel($labelNumber, $label['label']);
            }
        }

        $this->handleErrorRedirect(__('There has been an error while downloading the return label, please contact us for more information.', 'wc_colissimo'));
    }

    public function getUrlForCustom(int $orderId): string {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME) . '&' . self::ORDER_ID_VAR_NAME . '=' . $orderId . '&' . self::PRODUCTS_VAR_NAME . '=';
    }

    public function getUrlForDownload(int $orderId, string $trackingNumber): string {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME) . '&' . self::ORDER_ID_VAR_NAME . '=' . $orderId . '&' . self::LABEL_NUMBER_VAR_NAME . '=' . $trackingNumber;
    }

    private function generateCustomLabel($orderId, $products) {
        $currentUser = wp_get_current_user();
        $order       = wc_get_order($orderId);

        // Make sure the user tries to download for their own order
        if ($order->get_user_id() !== $currentUser->ID) {
            $this->handleErrorRedirect(__('You are not allowed to generate a return label for this order.', 'wc_colissimo'));
        }

        // Make sure they selected products from the order
        if (empty($products)) {
            $this->handleErrorRedirect(__('You need to select at least one item to generate a label', 'wc_colissimo'));
        }

        $orderedProducts = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (empty($product) || !$product->needs_shipping()) {
                continue;
            }

            $orderedProducts[$item->get_id()] = $item;
        }

        $products        = json_decode($products, true);
        $products        = array_combine(array_column($products, 'productId'), array_column($products, 'quantity'));
        $items           = [];
        $totalWeight     = wc_get_weight(LpcHelper::get_option('lpc_packaging_weight', '0'), 'kg');
        $insuranceAmount = 0;
        foreach ($products as $productId => $quantity) {
            if (empty($orderedProducts[$productId]) || $orderedProducts[$productId]->get_quantity() < $quantity) {
                $this->handleErrorRedirect(__('Please only select products you ordered.', 'wc_colissimo'));
            }

            $product = $orderedProducts[$productId]->get_product();

            $items[$productId] = ['qty' => $quantity];
            $totalWeight       += wc_get_weight(floatval($product->get_weight()) * floatval($quantity), 'kg');
            $insuranceAmount   += $product->get_price() * $quantity;
        }

        try {
            $inwardTrackingNumber = $this->labelGenerationInward->generate(
                $order,
                [
                    'items'                => $items,
                    'outward_label_number' => 'no_outward',
                    'totalWeight'          => $totalWeight,
                    'insuranceAmount'      => $insuranceAmount,
                    'format'               => LpcLabelGenerationPayload::LABEL_FORMAT_PDF,
                    'is_from_client'       => true,
                ]
            );
            $label                = $this->inwardLabelDb->getLabelFor($inwardTrackingNumber);
            $labelContent         = $label['label'];

            if (empty($labelContent)) {
                $this->handleErrorRedirect(__('There has been an error while downloading the return label, please contact us for more information.', 'wc_colissimo'));
            }

            echo json_encode(
                [
                    'type'           => 'success',
                    'trackingNumber' => $inwardTrackingNumber,
                ]
            );
        } catch (Exception $e) {
            $this->handleErrorRedirect($e->getMessage());
            echo json_encode(
                [
                    'type'  => 'error',
                    'error' => $e->getMessage(),
                ]
            );
        }
        exit;
    }

    private function downloadLabel(string $inwardTrackingNumber, string $labelContent) {
        $fileToDownloadName = get_temp_dir() . DS . 'Colissimo.inward(' . $inwardTrackingNumber . ').pdf';
        $labelFileName      = 'inward_label.pdf';
        $filesToMerge       = [];
        $labelContentFile   = fopen(sys_get_temp_dir() . DS . $labelFileName, 'w');
        fwrite($labelContentFile, $labelContent);
        fclose($labelContentFile);

        $filesToMerge[] = sys_get_temp_dir() . DS . $labelFileName;

        $cn23Data    = $this->inwardLabelDb->getCn23For($inwardTrackingNumber);
        $cn23Content = LpcLabelGenerationPayload::LABEL_FORMAT_PDF === $cn23Data['format'] ? $cn23Data['cn23'] : '';
        if (!empty($cn23Content)) {
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
    }

    private function handleErrorRedirect(string $errorMessage) {
        echo '<script type="text/javascript">';
        echo 'alert("' . addslashes($errorMessage) . '");';
        echo 'window.history.back();';
        echo '</script>';
        exit;
    }
}
