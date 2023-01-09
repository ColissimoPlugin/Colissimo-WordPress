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
        LpcColissimoStatus $colissimoStatus = null
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
    }

    public function init() {
        add_action(
            'current_screen',
            function ($currentScreen) {
                if ('post' === $currentScreen->base && 'shop_order' === $currentScreen->post_type) {
                    LpcHelper::enqueueStyle(
                        'lpc_order_banner',
                        plugins_url('/css/orders/lpc_order_banner.css', LPC_ADMIN . 'init.php'),
                        null
                    );

                    LpcHelper::enqueueScript(
                        'lpc_order_banner',
                        plugins_url('/js/orders/lpc_order_banner.js', LPC_ADMIN . 'init.php'),
                        null,
                        ['jquery-core']
                    );

                    LpcLabelQueries::enqueueLabelsActionsScript();

                    $modal = new LpcModal('');
                    $modal->loadScripts();
                }
            }
        );

        add_action('save_post', [$this, 'generateLabel'], 10, 3);
        add_action('save_post', [$this, 'sendCustomsDocuments'], 11, 3);
    }

    public function bannerContent($post) {
        $orderId        = $post->ID;
        $order          = wc_get_order($post);
        $shippingMethod = $this->lpcShippingMethods->getColissimoShippingMethodOfOrder($order);

        if (empty($shippingMethod)) {
            $warningMessage = __('This order is not shipped by Colissimo', 'wc_colissimo');

            echo '<div class="lpc__admin__order_banner__warning"><span>' . $warningMessage . '</span></div>';

            return;
        }

        $trackingNumbers = [];
        $labelFormat     = [];

        $this->lpcLabelQueries->getTrackingNumbersByOrdersId($trackingNumbers, $labelFormat, [$orderId]);

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

        foreach ($items as $item) {
            $product = $item->get_product();
            if (empty($product)) {
                continue;
            }

            $quantity = $item->get_quantity();

            if (!empty($alreadyGeneratedLabelItems[$item->get_id()])) {
                $quantity -= $alreadyGeneratedLabelItems[$item->get_id()]['qty'];
            }

            $price = wc_get_order_item_meta($item->get_id(), '_line_total');
            if (!empty(wc_get_order_item_meta($item->get_id(), '_qty'))) {
                $price /= wc_get_order_item_meta($item->get_id(), '_qty');
            }

            $args['lpc_order_items'][] = [
                'id'       => $item->get_id(),
                'name'     => $item->get_name(),
                'qty'      => max($quantity, 0),
                'weight'   => empty($product->get_weight()) ? 0 : $product->get_weight(),
                'price'    => $price,
                'base_qty' => $item->get_quantity(),
            ];
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
            $date = date('Y-m-d');

            if (in_array($countryCode, ['GF', 'GP', 'MQ', 'YT']) || ('RE' === $countryCode && $date > '2022-05-31')) {
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
                $args['lpc_sent_documents'] = get_post_meta($orderId, 'lpc_customs_sent_documents', true);
                $args['lpc_sent_documents'] = empty($args['lpc_sent_documents']) ? [] : json_decode($args['lpc_sent_documents'], true);
            }
        }

        $args['lpc_sending_service_needed'] = false;
        $args['lpc_sending_service_config'] = 'partner';
        $productCode                        = $this->capabilitiesPerCountry->getProductCodeForOrder($order);
        $args['lpc_product_code']           = $productCode;
        if (in_array($countryCode, ['AT', 'DE', 'IT', 'LU']) && !empty($productCode) && in_array($productCode, ['BOS', 'DOS'])) {
            $shippingMethod                     = $this->lpcShippingMethods->getColissimoShippingMethodOfOrder($order);
            $args['lpc_sending_service_needed'] = true;
            if (in_array($shippingMethod, [LpcExpert::ID, LpcExpertDDP::ID])) {

                $countries                          = [
                    'AT' => 'lpc_expert_SendingService_austria',
                    'DE' => 'lpc_expert_SendingService_germany',
                    'IT' => 'lpc_expert_SendingService_italy',
                    'LU' => 'lpc_expert_SendingService_luxembourg',
                ];
                $args['lpc_sending_service_config'] = LpcHelper::get_option($countries[$countryCode]);
            } else {
                $countries                          = [
                    'AT' => 'lpc_domicileas_SendingService_austria',
                    'DE' => 'lpc_domicileas_SendingService_germany',
                    'IT' => 'lpc_domicileas_SendingService_italy',
                    'LU' => 'lpc_domicileas_SendingService_luxembourg',
                ];
                $args['lpc_sending_service_config'] = LpcHelper::get_option($countries[$countryCode]);
            }
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
        $args['lpc_multi_parcels_amount']     = get_post_meta($args['order_id'], 'lpc_multi_parcels_amount', true);
        $args['lpc_multi_parcels_existing']   = $this->outwardLabelDb->getMultiParcelsLabels($args['order_id']);

        echo LpcHelper::renderPartial('orders' . DS . 'lpc_admin_order_banner.php', $args);
    }

    /**
     * @throws Exception When lpcAdminNotices isn't available.
     */
    public function generateLabel($post_id, $post, $update) {
        $slug = 'shop_order';

        if (
            !is_admin()
            || $slug != $post->post_type
            || !isset($_REQUEST['lpc__admin__order_banner__generate_label__action'])
            || empty($_REQUEST['lpc__admin__order_banner__generate_label__action'])
        ) {
            return;
        }

        if (empty($_REQUEST['lpc__admin__order_banner__generate_label__items-id'])) {
            return;
        }

        $allItemsId = unserialize(sanitize_text_field(wp_unslash($_REQUEST['lpc__admin__order_banner__generate_label__items-id'])));

        $items = [];
        foreach ($allItemsId as $oneItemId) {
            if (!isset($_REQUEST[$oneItemId . '-checkbox']) || 'on' !== $_REQUEST[$oneItemId . '-checkbox']) {
                continue;
            }

            $items[$oneItemId]['price']  = isset($_REQUEST[$oneItemId . '-price']) ? sanitize_text_field(wp_unslash($_REQUEST[$oneItemId . '-price'])) : 0;
            $items[$oneItemId]['qty']    = isset($_REQUEST[$oneItemId . '-qty']) ? sanitize_text_field(wp_unslash($_REQUEST[$oneItemId . '-qty'])) : 0;
            $items[$oneItemId]['weight'] = isset($_REQUEST[$oneItemId . '-weight']) ? sanitize_text_field(wp_unslash($_REQUEST[$oneItemId . '-weight'])) : 0;
        }

        if (empty($items)) {
            $this->lpcAdminNotices->add_notice('lpc_notice', 'notice-warning', __('You need to select at least one item to generate a label', 'wc_colissimo'));

            return;
        }

        $order              = wc_get_order($post_id);
        $packageWeight      = isset($_REQUEST['lpc__admin__order_banner__generate_label__package_weight']) ? sanitize_text_field(wp_unslash($_REQUEST['lpc__admin__order_banner__generate_label__package_weight'])) : 0;
        $totalWeight        = isset($_REQUEST['lpc__admin__order_banner__generate_label__total_weight__input']) ? sanitize_text_field(wp_unslash($_REQUEST['lpc__admin__order_banner__generate_label__total_weight__input'])) : 0;
        $packageLength      = isset($_REQUEST['lpc__admin__order_banner__generate_label__package_length']) ? sanitize_text_field(wp_unslash($_REQUEST['lpc__admin__order_banner__generate_label__package_length'])) : 0;
        $packageWidth       = isset($_REQUEST['lpc__admin__order_banner__generate_label__package_width']) ? sanitize_text_field(wp_unslash($_REQUEST['lpc__admin__order_banner__generate_label__package_width'])) : 0;
        $packageHeight      = isset($_REQUEST['lpc__admin__order_banner__generate_label__package_height']) ? sanitize_text_field(wp_unslash($_REQUEST['lpc__admin__order_banner__generate_label__package_height'])) : 0;
        $shippingCosts      = isset($_REQUEST['lpc__admin__order_banner__generate_label__shipping_costs']) ? sanitize_text_field(wp_unslash($_REQUEST['lpc__admin__order_banner__generate_label__shipping_costs'])) : 0;
        $nonMachinable      = isset($_REQUEST['lpc__admin__order_banner__generate_label__non_machinable__input']);
        $usingInsurance     = isset($_REQUEST['lpc__admin__order_banner__generate_label__using__insurance__input']) ? sanitize_text_field(wp_unslash($_REQUEST['lpc__admin__order_banner__generate_label__using__insurance__input'])) : 'no';
        $insuranceAmount    = isset($_REQUEST['lpc__admin__order_banner__generate_label__insurrance__amount']) ? sanitize_text_field(wp_unslash($_REQUEST['lpc__admin__order_banner__generate_label__insurrance__amount'])) : 0;
        $description        = isset($_REQUEST['lpc__admin__order_banner__generate_label__package_description']) ? sanitize_text_field(wp_unslash($_REQUEST['lpc__admin__order_banner__generate_label__package_description'])) : '';
        $multiParcels       = isset($_REQUEST['lpc__admin__order_banner__generate_label__multi__parcels__input']);
        $multiParcelsAmount = isset($_REQUEST['lpc__admin__order_banner__generate_label__parcels_amount']) ? intval($_REQUEST['lpc__admin__order_banner__generate_label__parcels_amount']) : 0;
        $customCategory     = isset($_REQUEST['lpc__admin__order_banner__generate_label__cn23__type'])
            ? sanitize_text_field(wp_unslash($_REQUEST['lpc__admin__order_banner__generate_label__cn23__type']))
            : LpcHelper::get_option('lpc_customs_defaultCustomsCategory', 5);

        if (!empty($multiParcels)) {
            $orderId = $order->get_id();
            if (empty($multiParcelsAmount)) {
                $multiParcelsAmount = intval(get_post_meta($orderId, 'lpc_multi_parcels_amount', true));
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
        ];

        $outwardOrInward = isset($_REQUEST['lpc__admin__order_banner__generate_label__outward_or_inward']) ? sanitize_text_field(wp_unslash($_REQUEST['lpc__admin__order_banner__generate_label__outward_or_inward'])) : '';

        if ('outward' === $outwardOrInward || 'both' === $outwardOrInward) {
            $status = $this->lpcOutwardLabelGeneration->generate($order, $customParams);
            if ($status && !empty($multiParcelsAmount)) {
                update_post_meta($order->get_id(), 'lpc_multi_parcels_amount', $multiParcelsAmount);
            }
        }

        if ('inward' === $outwardOrInward || ('both' === $outwardOrInward && 'yes' !== LpcHelper::get_option('lpc_createReturnLabelWithOutward', 'no'))) {
            $this->lpcInwardLabelGeneration->generate($order, $customParams);
        }
    }

    public function sendCustomsDocuments($post_id, $post, $update) {
        if (!is_admin() || 'shop_order' !== $post->post_type || !isset($_FILES['lpc__customs_document'])) {
            return;
        }

        $sentDocuments = get_post_meta($post_id, 'lpc_customs_sent_documents', true);
        $sentDocuments = empty($sentDocuments) ? [] : json_decode($sentDocuments, true);
        $orderLabels   = $this->outwardLabelDb->getMultiParcelsLabels($post_id);

        $documentsPerLabel = $_FILES['lpc__customs_document']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        foreach ($documentsPerLabel['name'] as $parcelNumber => $documentTypes) {
            foreach ($documentTypes as $documentType => $documentNames) {
                foreach ($documentNames as $documentNumber => $oneDocumentName) {
                    try {
                        $document   = $documentsPerLabel['tmp_name'][$parcelNumber][$documentType][$documentNumber];
                        $documentId = $this->customsDocumentsApi->storeDocument($orderLabels, $documentType, $parcelNumber, $document, $oneDocumentName);

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
                        $this->lpcAdminNotices->add_notice('lpc_notice', 'notice-error', $e->getMessage());
                    }
                }
            }
        }

        update_post_meta(
            $post_id,
            'lpc_customs_sent_documents',
            json_encode($sentDocuments)
        );
    }
}
