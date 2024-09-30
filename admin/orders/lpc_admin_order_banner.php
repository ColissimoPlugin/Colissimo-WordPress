<?php

class LpcAdminOrderBanner extends LpcComponent {
    /** @var LpcLabelQueries */
    protected $lpcLabelQueries;

    /** @var LpcBordereauQueries */
    protected $lpcBordereauQueries;

    /** @var LpcShippingMethods */
    protected $lpcShippingMethods;

    /** @var LpcLabelGenerationOutward */
    protected $lpcOutwardLabelGeneration;

    /** @var LpcLabelGenerationInward */
    protected $lpcInwardLabelGeneration;

    /** @var LpcAdminNotices */
    protected $lpcAdminNotices;

    /** @var LpcAdminOrderAffect */
    protected $lpcAdminOrderAffect;

    /** @var LpcOutwardLabelDb */
    protected $outwardLabelDb;

    /** @var LpcBordereauDownloadAction */
    protected $bordereauDownloadAction;

    /** @var LpcCapabilitiesPerCountry */
    private $capabilitiesPerCountry;

    /** @var LpcCustomsDocumentsApi */
    private $customsDocumentsApi;

    /** @var LpcColissimoStatus */
    protected $colissimoStatus;

    /** @var LpcAdminPickupWidget */
    protected $lpcAdminPickupWidget;

    /** @var LpcAdminPickupWebService */
    protected $lpcAdminPickupWebService;

    /** @var LpcAccountApi */
    protected $accountApi;

    public function __construct(
        LpcLabelQueries $lpcLabelQueries = null,
        LpcBordereauQueries $lpcBordereauQueries = null,
        LpcShippingMethods $lpcShippingMethods = null,
        LpcLabelGenerationOutward $lpcOutwardLabelGeneration = null,
        LpcLabelGenerationInward $lpcInwardLabelGeneration = null,
        LpcAdminNotices $lpcAdminNotices = null,
        LpcOutwardLabelDb $outwardLabelDb = null,
        LpcBordereauDownloadAction $bordereauDownloadAction = null,
        LpcCapabilitiesPerCountry $capabilitiesPerCountry = null,
        LpcCustomsDocumentsApi $customsDocumentsApi = null,
        LpcColissimoStatus $colissimoStatus = null,
        LpcAdminOrderAffect $lpcAdminOrderAffect = null,
        LpcAdminPickupWebService $lpcAdminPickupWebService = null,
        LpcAdminPickupWidget $lpcAdminPickupWidget = null,
        LpcAccountApi $accountApi = null
    ) {
        $this->lpcLabelQueries           = LpcRegister::get('labelQueries', $lpcLabelQueries);
        $this->lpcBordereauQueries       = LpcRegister::get('bordereauQueries', $lpcBordereauQueries);
        $this->lpcShippingMethods        = LpcRegister::get('shippingMethods', $lpcShippingMethods);
        $this->lpcOutwardLabelGeneration = LpcRegister::get('labelGenerationOutward', $lpcOutwardLabelGeneration);
        $this->lpcInwardLabelGeneration  = LpcRegister::get('labelGenerationInward', $lpcInwardLabelGeneration);
        $this->lpcAdminNotices           = LpcRegister::get('lpcAdminNotices', $lpcAdminNotices);
        $this->outwardLabelDb            = LpcRegister::get('outwardLabelDb', $outwardLabelDb);
        $this->bordereauDownloadAction   = LpcRegister::get('bordereauDownloadAction', $bordereauDownloadAction);
        $this->capabilitiesPerCountry    = LpcRegister::get('capabilitiesPerCountry', $capabilitiesPerCountry);
        $this->customsDocumentsApi       = LpcRegister::get('customsDocumentsApi', $customsDocumentsApi);
        $this->colissimoStatus           = LpcRegister::get('colissimoStatus', $colissimoStatus);
        $this->lpcAdminOrderAffect       = LpcRegister::get('lpcAdminOrderAffect', $lpcAdminOrderAffect);
        $this->accountApi                = LpcRegister::get('accountApi', $accountApi);
        if ('widget' === LpcHelper::get_option('lpc_pickup_map_type', 'widget')) {
            $this->lpcAdminPickupWidget = LpcRegister::get('adminPickupWidget', $lpcAdminPickupWidget);
        } else {
            $this->lpcAdminPickupWebService = LpcRegister::get('adminPickupWebService', $lpcAdminPickupWebService);
        }
    }

    public function getDependencies(): array {
        return [
            'labelQueries',
            'bordereauQueries',
            'shippingMethods',
            'labelGenerationOutward',
            'labelGenerationInward',
            'lpcAdminNotices',
            'outwardLabelDb',
            'bordereauDownloadAction',
            'capabilitiesPerCountry',
            'customsDocumentsApi',
            'colissimoStatus',
            'lpcAdminOrderAffect',
            'accountApi',
        ];
    }

    public function init() {
        add_action(
            'current_screen',
            function ($currentScreen) {
                if ('woocommerce_page_wc-orders' === $currentScreen->base || ('post' === $currentScreen->base && 'shop_order' === $currentScreen->post_type)) {
                    LpcHelper::enqueueStyle(
                        'lpc_order_banner',
                        plugins_url('/css/orders/lpc_order_banner.css', LPC_ADMIN . 'init.php'),
                        null
                    );

                    LpcHelper::enqueueScript(
                        'lpc_order_banner',
                        plugins_url('/js/orders/lpc_order_banner.js', LPC_ADMIN . 'init.php'),
                        null,
                        ['jquery-core'],
                        'lpc_order_banner',
                        [
                            'automatic' => __('Automatic', 'wc_colissimo'),
                            'default'   => __('Default packaging', 'wc_colissimo'),
                        ]
                    );

                    LpcLabelQueries::enqueueLabelsActionsScript();

                    $modal = new LpcModal('');
                    $modal->loadScripts();
                }
            }
        );

        add_action('wp_ajax_lpc_order_generate_label', [$this, 'generateLabel']);
        add_action('wp_ajax_lpc_order_send_documents', [$this, 'sendCustomsDocuments']);
    }

    public function bannerContent($post) {
        $order = wc_get_order($post);
        if (empty($order) || !method_exists($order, 'get_shipping_methods')) {
            $warningMessage = __('This order could not be loaded by WooCommerce', 'wc_colissimo');
            echo '<div class="lpc__admin__order_banner__warning"><span>' . $warningMessage . '</span></div>';

            return;
        }

        $orderId        = $order->get_id();
        $shippingMethod = $this->lpcShippingMethods->getColissimoShippingMethodOfOrder($order);
        $methods        = $this->lpcAdminOrderAffect->getColissimoShippingMethodsAvailable($order);

        if ('lpc_relay' !== $shippingMethod) {
            unset($methods[$shippingMethod]);
        }

        $methods = array_map(
            function ($value) {
                return $value->get_method_title();
            },
            $methods
        );

        $args['lpc_shipping_methods'] = $methods;

        $args['link_choose_relay'] = 'widget' === LpcHelper::get_option('lpc_pickup_map_type', 'widget')
            ? $this->lpcAdminPickupWidget->addWidget($order)
            : $this->lpcAdminPickupWebService->addWebserviceMap($order);

        $args['lpc_partial_name'] = 'lpc_order_affect_methods_banner';

        if (!empty($shippingMethod)) {
            $args['button_text'] = __('Change Colissimo shipping method', 'wc_colissimo');
        }

        echo LpcHelper::renderPartial('orders' . DS . 'lpc_order_affect_methods.php', $args);

        if (empty($shippingMethod)) {
            $warningMessage = __('This order is not shipped by Colissimo', 'wc_colissimo');
            echo '<div class="lpc__admin__order_banner__warning"><span>' . $warningMessage . '</span></div>';

            return;
        }

        $trackingNumbers = [];
        $labelFormat     = [];

        $this->lpcLabelQueries->getTrackingNumbersByOrdersId($trackingNumbers, $labelFormat, $labelInfoByTrackingNumber, [$orderId]);

        $trackingNumbersForOrder = !empty($trackingNumbers[$orderId]) ? $trackingNumbers[$orderId] : [];

        $args  = [];
        $items = $order->get_items();

        $labelDetails = $this->outwardLabelDb->getAllLabelDetailByOrderId($order->get_id());

        $alreadyGeneratedLabelItems = [];
        foreach ($labelDetails as $detail) {
            if (empty($detail)) {
                continue;
            }
            $detail = json_decode($detail, true);
            $this->lpcOutwardLabelGeneration->addItemsToAlreadyGeneratedLabel($alreadyGeneratedLabelItems, $detail);
        }

        $args['lpc_order_items'] = [];

        $orderValue = 0;
        foreach ($items as $item) {
            $product = $item->get_product();
            // Compatibility with WPC Product Bundles for WooCommerce, don't count bundled products twice
            if (empty($product) || !$product->needs_shipping() || 'woosb' === $product->get_type()) {
                continue;
            }

            $quantity = $item->get_quantity();

            if (!empty($alreadyGeneratedLabelItems[$item->get_id()]['qty'])) {
                $quantity -= $alreadyGeneratedLabelItems[$item->get_id()]['qty'];
            }

            $price = wc_get_order_item_meta($item->get_id(), '_line_total');
            if (!empty(wc_get_order_item_meta($item->get_id(), '_qty'))) {
                $price /= wc_get_order_item_meta($item->get_id(), '_qty');
            }

            $args['lpc_order_items'][] = [
                'id'         => $item->get_id(),
                'name'       => $item->get_name(),
                'qty'        => max($quantity, 0),
                'weight'     => empty($product->get_weight()) ? 0 : $product->get_weight(),
                'price'      => $price,
                'base_qty'   => $item->get_quantity(),
                'dimensions' => json_encode(
                    [
                        $product->get_width(),
                        $product->get_length(),
                        $product->get_height(),
                    ]
                ),
            ];

            $orderValue += $price * $quantity;
        }

        if (empty($args['lpc_order_items'])) {
            echo '<div style="color: red;">' . esc_html('The product\'s details couldn\'t be found, the product may have been deleted from your store.') . '</div>';
        }

        $bordereauLinks = [];
        foreach ($trackingNumbersForOrder as $outward => $inward) {
            $bordereauID   = $this->outwardLabelDb->getBordereauFromTrackingNumber($outward);
            $bordereauLink = '';
            if (!empty($bordereauID[0])) {
                $bordereauLink = $this->bordereauDownloadAction->getBorderauDownloadLink($bordereauID[0]);
            }
            if (!empty($bordereauLink)) {
                $bordereauLinks[$outward] = [
                    'link' => $bordereauLink,
                    'id'   => $bordereauID[0],
                ];
            }
        }

        $countryCode = $order->get_shipping_country();

        $args['postId']                       = $orderId;
        $args['lpc_tracking_numbers']         = $trackingNumbersForOrder;
        $args['lpc_label_formats']            = $labelFormat;
        $args['lpc_label_queries']            = $this->lpcLabelQueries;
        $args['lpc_bordereau_queries']        = $this->lpcBordereauQueries;
        $args['lpc_redirection']              = LpcLabelQueries::REDIRECTION_WOO_ORDER_EDIT_PAGE;
        $args['lpc_packaging_weight']         = LpcHelper::get_option('lpc_packaging_weight', 0);
        $args['lpc_shipping_costs']           = empty($order->get_shipping_total()) ? 0 : $order->get_shipping_total();
        $args['lpc_bordereauLinks']           = $bordereauLinks;
        $args['lpc_customs_needed']           = false;
        $args['lpc_customs_insured']          = $trackingNumbers['insured'] ?? [];
        $args['lpc_ddp']                      = in_array($shippingMethod, [LpcSignDDP::ID, LpcExpertDDP::ID]);
        $args['order_id']                     = $order->get_id();
        $args['lpc_collection_allowed']       = 'FR' === $countryCode;
        $args['outwardLabelDb']               = $this->outwardLabelDb;
        $args['colissimoStatus']              = $this->colissimoStatus;
        $args['lpc_cn23_needed']              = $this->capabilitiesPerCountry->getIsCn23RequiredForDestination($order);
        $args['lpc_default_customs_category'] = LpcHelper::get_option('lpc_customs_defaultCustomsCategory', 5);

        if (!empty($trackingNumbersForOrder)) {
            if (in_array($countryCode, ['GF', 'GP', 'MQ', 'RE', 'YT'])) {
                $args['lpc_customs_needed'] = $this->capabilitiesPerCountry->getIsCn23RequiredForDestination($order);
            }

            if ($args['lpc_ddp']) {
                $args['lpc_customs_needed'] = true;
            }

            if ($args['lpc_customs_needed']) {
                // Options needed to send the documents
                $args['lpc_documents_types'] = [
                    'C50'                   => __('Custom clearance bordereau', 'wc_colissimo') . ' (C50)',
                    'CERTIFICATE_OF_ORIGIN' => __('Original certificate', 'wc_colissimo'),
                    'CN23'                  => __('Customs declaration', 'wc_colissimo') . ' (CN23)',
                    'EXPORT_LICENCE'        => __('Export license', 'wc_colissimo'),
                    'COMMERCIAL_INVOICE'    => __('Parcel invoice', 'wc_colissimo'),
                    'COMPENSATION'          => __('Compensation report', 'wc_colissimo'),
                    'DAU'                   => __('Unique administrative document', 'wc_colissimo') . ' (DAU)',
                    'DELIVERY_CERTIFICATE'  => __('Delivery certificate', 'wc_colissimo'),
                    'LABEL'                 => __('Label', 'wc_colissimo'),
                    'PHOTO'                 => __('Picture', 'wc_colissimo'),
                    'SIGNATURE'             => __('Proof of delivery', 'wc_colissimo'),
                ];
                asort($args['lpc_documents_types']);
                $args['lpc_documents_types']['OTHER'] = __('Other document', 'wc_colissimo');
                $args['lpc_documents_types']          = array_merge(['' => __('Document type', 'wc_colissimo')], $args['lpc_documents_types']);

                // Get the already sent documents
                $args['lpc_sent_documents'] = $order->get_meta('lpc_customs_sent_documents');
                $args['lpc_sent_documents'] = empty($args['lpc_sent_documents']) ? [] : json_decode($args['lpc_sent_documents'], true);
            }
        }

        $args['lpc_sending_service_needed'] = false;
        $args['lpc_sending_service_config'] = 'partner';
        $productCode                        = $this->capabilitiesPerCountry->getProductCodeForOrder($order);
        $args['lpc_product_code']           = $productCode;
        if (in_array($countryCode, ['AT', 'DE', 'IT', 'LU']) && LpcLabelGenerationPayload::PRODUCT_CODE_WITH_SIGNATURE === $productCode) {
            $shippingMethod                     = $this->lpcShippingMethods->getColissimoShippingMethodOfOrder($order);
            $args['lpc_sending_service_needed'] = true;

            if (in_array($shippingMethod, [LpcExpert::ID, LpcExpertDDP::ID])) {
                $countries = [
                    'AT' => 'lpc_expert_SendingService_austria',
                    'DE' => 'lpc_expert_SendingService_germany',
                    'IT' => 'lpc_expert_SendingService_italy',
                    'LU' => 'lpc_expert_SendingService_luxembourg',
                ];
            } else {
                $countries = [
                    'AT' => 'lpc_domicileas_SendingService_austria',
                    'DE' => 'lpc_domicileas_SendingService_germany',
                    'IT' => 'lpc_domicileas_SendingService_italy',
                    'LU' => 'lpc_domicileas_SendingService_luxembourg',
                ];
            }

            $args['lpc_sending_service_config'] = LpcHelper::get_option($countries[$countryCode]);
        }

        // On demand
        $args['lpc_ondemand_service_url'] = 'https://www.colissimo.entreprise.laposte.fr/';
        $args['lpc_ondemand_mac_url']     = 'https://www.colissimo.entreprise.laposte.fr/sites/default/files/2021-10/Widget_On-Demand-Mac.zip';
        $args['lpc_ondemand_windows_url'] = 'https://www.colissimo.entreprise.laposte.fr/sites/default/files/2021-10/Widget_On-Demand-Win.zip';

        // Multi-parcels
        $args['lpc_multi_parcels_authorized'] = in_array(
            $countryCode,
            array_merge($this->capabilitiesPerCountry::DOM1_COUNTRIES_CODE, $this->capabilitiesPerCountry::DOM2_COUNTRIES_CODE)
        );
        $args['lpc_multi_parcels_amount']     = $order->get_meta('lpc_multi_parcels_amount');
        $args['lpc_multi_parcels_existing']   = $this->outwardLabelDb->getMultiParcelsLabels($args['order_id']);

        $args['blocking_code'] = 'FR' === $countryCode && in_array(
                $productCode,
                [
                    LpcLabelGenerationPayload::PRODUCT_CODE_WITH_SIGNATURE,
                    LpcLabelGenerationPayload::PRODUCT_CODE_WITH_SIGNATURE_OM,
                    LpcLabelGenerationPayload::PRODUCT_CODE_WITH_SIGNATURE_INTRA_DOM,
                ]
            );

        if ($args['blocking_code']) {
            $accountInformation    = $this->accountApi->getAccountInformation();
            $args['blocking_code'] = !empty($accountInformation['statutCodeBloquant']);

            $minLimit = LpcHelper::get_option('lpc_domicileas_block_code_min', []);
            $maxLimit = LpcHelper::get_option('lpc_domicileas_block_code_max', []);

            $args['blocking_code_checked'] = (empty($minLimit) || $orderValue >= $minLimit) && (empty($maxLimit) || $orderValue <= $maxLimit);
        }

        echo LpcHelper::renderPartial('orders' . DS . 'lpc_admin_order_banner.php', $args);
    }

    /**
     * @throws Exception When lpcAdminNotices isn't available.
     */
    public function generateLabel() {
        $checkedItems = LpcHelper::getVar('items', [], 'array');
        if (empty($checkedItems)) {
            LpcHelper::endAjax(false, ['message' => __('You need to select at least one item to generate a label', 'wc_colissimo')]);
        }

        $orderId = LpcHelper::getVar('order_id');
        $order   = wc_get_order($orderId);
        if (empty($order) || 'shop_order' !== $order->get_type()) {
            LpcHelper::endAjax(false, ['message' => __('Order not found', 'wc_colissimo')]);
        }

        $items = [];
        foreach ($checkedItems as $oneItem) {
            $oneItemId                   = $oneItem['id'];
            $items[$oneItemId]['price']  = $oneItem['price'];
            $items[$oneItemId]['qty']    = $oneItem['quantity'];
            $items[$oneItemId]['weight'] = $oneItem['weight'];
        }

        $packageWeight  = LpcHelper::getVar('package_weight');
        $totalWeight    = LpcHelper::getVar('total_weight');
        $description    = LpcHelper::getVar('package_description');
        $customCategory = LpcHelper::getVar('cn23_type');
        if (empty($customCategory)) {
            $customCategory = LpcHelper::get_option('lpc_customs_defaultCustomsCategory', 5);
        }
        $packageLength      = LpcHelper::getVar('package_length');
        $packageWidth       = LpcHelper::getVar('package_width');
        $packageHeight      = LpcHelper::getVar('package_height');
        $shippingCosts      = LpcHelper::getVar('shipping_costs');
        $nonMachinable      = LpcHelper::getVar('non_machinable');
        $usingInsurance     = LpcHelper::getVar('using_insurance');
        $insuranceAmount    = LpcHelper::getVar('insurance_amount');
        $multiParcels       = LpcHelper::getVar('multi_parcels');
        $multiParcelsAmount = LpcHelper::getVar('parcels_amount');
        $blockCode          = LpcHelper::getVar('block_code');

        if (!empty($multiParcels)) {
            if (empty($multiParcelsAmount)) {
                $multiParcelsAmount = intval($order->get_meta('lpc_multi_parcels_amount'));
            }

            $generatedLabels           = $this->outwardLabelDb->getMultiParcelsLabels($orderId);
            $multiParcelsCurrentNumber = count($generatedLabels) + 1;
        }

        $customParams = [
            'packageWeight'             => $packageWeight,
            'totalWeight'               => $totalWeight,
            'packageLength'             => $packageLength,
            'packageWidth'              => $packageWidth,
            'packageHeight'             => $packageHeight,
            'items'                     => $items,
            'shippingCosts'             => $shippingCosts,
            'nonMachinable'             => $nonMachinable,
            'useInsurance'              => 'on' === $usingInsurance ? 'yes' : $usingInsurance,
            'insuranceAmount'           => $insuranceAmount,
            'description'               => $description,
            'multiParcels'              => $multiParcels,
            'multiParcelsAmount'        => $multiParcelsAmount,
            'multiParcelsCurrentNumber' => $multiParcelsCurrentNumber ?? 0,
            'customsCategory'           => $customCategory,
            'blockCode'                 => $blockCode,
        ];

        $outwardOrInward = LpcHelper::getVar('label_type');

        try {
            if ('outward' === $outwardOrInward || 'both' === $outwardOrInward) {
                $status = $this->lpcOutwardLabelGeneration->generate($order, $customParams);
                if ($status && !empty($multiParcelsAmount)) {
                    $order->update_meta_data('lpc_multi_parcels_amount', $multiParcelsAmount);
                    $order->save();
                }
            }

            if ('inward' === $outwardOrInward || ('both' === $outwardOrInward && 'yes' !== LpcHelper::get_option('lpc_createReturnLabelWithOutward', 'no'))) {
                $this->lpcInwardLabelGeneration->generate($order, $customParams);
            }
        } catch (Exception $e) {
            LpcHelper::endAjax(false, ['message' => $e->getMessage()]);
        }

        LpcHelper::endAjax();
    }

    public function sendCustomsDocuments() {
        $orderId = LpcHelper::getVar('order_id');
        $order   = wc_get_order($orderId);
        if (empty($order)) {
            LpcHelper::endAjax(false, ['message' => __('Order not found', 'wc_colissimo')]);
        }

        $filesByType = LpcHelper::getVar('fileTypes', [], 'array');
        if (empty($filesByType) || !isset($_FILES['files'])) {
            LpcHelper::endAjax(false, ['message' => 'files not found']);
        }

        $sentDocuments = $order->get_meta('lpc_customs_sent_documents');
        $sentDocuments = empty($sentDocuments) ? [] : json_decode($sentDocuments, true);
        $orderLabels   = $this->outwardLabelDb->getMultiParcelsLabels($orderId);

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $documents = $_FILES['files'];

        foreach ($filesByType as $index => $oneDefinition) {
            preg_match('#.*\[([^[]*)\]\[([^[]*)\]#U', $oneDefinition, $matches);
            $parcelNumber = $matches[1];
            $documentType = $matches[2];

            try {
                $documentPath    = $documents['tmp_name'][$index];
                $oneDocumentName = $documents['name'][$index];

                $documentId = $this->customsDocumentsApi->storeDocument($orderLabels, $documentType, $parcelNumber, $documentPath, $oneDocumentName);

                // Old version of API maybe, keep this test
                $dotPosition = strrpos($documentId, '.');
                if (!empty($dotPosition)) {
                    $documentId = substr($documentId, 0, $dotPosition);
                }

                $sentDocuments[$parcelNumber][$documentId] = [
                    'documentName' => $oneDocumentName,
                    'documentType' => $documentType,
                ];
            } catch (Exception $e) {
                LpcHelper::endAjax(false, ['message' => $e->getMessage()]);
            }
        }

        $order->update_meta_data('lpc_customs_sent_documents', json_encode($sentDocuments));
        $order->save();

        LpcHelper::endAjax();
    }
}
