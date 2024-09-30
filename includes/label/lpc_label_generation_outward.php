<?php

require_once LPC_INCLUDES . 'label' . DS . 'lpc_label_generation_payload.php';

class LpcLabelGenerationOutward extends LpcComponent {
    const OUTWARD_PARCEL_NUMBER_META_KEY = 'lpc_outward_parcel_number';
    const ORDERS_OUTWARD_PARCEL_FAILED = 'lpc_orders_outward_parcel_failed';
    const LABEL_TYPE_CLASSIC = 'CLASSIC';
    const LABEL_TYPE_MASTER = 'MASTER';
    const LABEL_TYPE_FOLLOWER = 'FOLLOWER';

    protected $capabilitiesPerCountry;
    protected $labelGenerationApi;
    protected $labelGenerationInward;
    protected $shippingMethods;
    protected $outwardLabelDb;

    public function __construct(
        LpcCapabilitiesPerCountry $capabilitiesPerCountry = null,
        LpcLabelGenerationApi $labelGenerationApi = null,
        LpcLabelGenerationInward $labelGenerationInward = null,
        LpcShippingMethods $shippingMethods = null,
        LpcOutwardLabelDb $outwardLabelDb = null
    ) {
        $this->capabilitiesPerCountry = LpcRegister::get('capabilitiesPerCountry', $capabilitiesPerCountry);
        $this->labelGenerationApi     = LpcRegister::get('labelGenerationApi', $labelGenerationApi);
        $this->labelGenerationInward  = LpcRegister::get('labelGenerationInward', $labelGenerationInward);
        $this->shippingMethods        = LpcRegister::get('shippingMethods', $shippingMethods);
        $this->outwardLabelDb         = LpcRegister::get('outwardLabelDb', $outwardLabelDb);
    }

    public function getDependencies(): array {
        return [
            'capabilitiesPerCountry',
            'labelGenerationApi',
            'labelGenerationInward',
            'shippingMethods',
            'outwardLabelDb',
        ];
    }

    /**
     * @param WC_Order $order
     * @param array    $customParams Accepted params : total_weight, items
     * @param bool     $isWholeOrder Is generating the label for the whole order
     *
     * @return bool
     * @throws Exception When lpcAdminNotices isn't available.
     */
    public function generate(WC_Order $order, array $customParams = [], bool $isWholeOrder = false) {
        if (is_admin()) {
            $lpc_admin_notices = LpcRegister::get('lpcAdminNotices');
        }

        $detail       = empty($customParams['items']) ? [] : $customParams['items'];
        $fullyShipped = $this->isFullyShipped($order, $detail);
        if ($isWholeOrder) {
            $customParams = [];
        }

        $time         = time();
        $orderId      = $order->get_order_number();
        $ordersFailed = get_option(self::ORDERS_OUTWARD_PARCEL_FAILED, []);
        if (!empty($ordersFailed)) {
            update_option(
                self::ORDERS_OUTWARD_PARCEL_FAILED,
                array_filter($ordersFailed, function ($error) use ($time) {
                    return $error['time'] < $time - 604800;
                }),
                false
            );
        }

        try {
            $payload     = $this->buildPayload($order, $customParams);
            $response    = $this->labelGenerationApi->generateLabel($payload);
            $labelFormat = $payload->getLabelFormat();

            if (is_admin()) {
                $accountApi = LpcRegister::get('accountApi');
                if (!$accountApi->isCgvAccepted()) {
                    $urls       = $accountApi->getAutologinURLs();
                    $accountUrl = $urls['urlConnectedCbox'] ?? 'https://www.colissimo.entreprise.laposte.fr';
                    $lpc_admin_notices->add_notice(
                        'cgv_invalid',
                        'notice-error',
                        '<span style="color:red;font-weight: bold;">' .
                        __(
                            'We have detected that you have not yet signed the latest version of our GTC. Your consent is necessary in order to continue using Colissimo services. We therefore invite you to sign them on your Colissimo entreprise space, by clicking on the link below:',
                            'wc_colissimo'
                        ) . '<br/><a href="' . $accountUrl . '" target="_blank">' . __('Sign the GTC', 'wc_colissimo') . '</a>'
                        . '</span>'
                    );
                }
            }

            if (!empty($ordersFailed[$orderId])) {
                unset($ordersFailed[$orderId]);
                update_option(self::ORDERS_OUTWARD_PARCEL_FAILED, $ordersFailed, false);
            }
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            if (is_admin()) {
                $lpc_admin_notices->add_notice(
                    'outward_label_generate',
                    'notice-error',
                    sprintf(__('Order %s: Outward label was not generated:', 'wc_colissimo'), $orderId) . ' ' . $errorMessage
                );
            }

            $ordersFailed[$orderId] = [
                'message' => $errorMessage,
                'time'    => $time,
            ];
            update_option(self::ORDERS_OUTWARD_PARCEL_FAILED, $ordersFailed, false);

            return false;
        }

        $parcelNumber = $response['<jsonInfos>']['labelV2Response']['parcelNumber'];
        $label        = $response['<label>'];
        $cn23         = @$response['<cn23>'];

        $order->update_meta_data(self::OUTWARD_PARCEL_NUMBER_META_KEY, $parcelNumber);
        $order->save();

        // If it's insured
        if ($payload->isInsured()) {
            $detail['insured'] = 1;
        }

        $type = self::LABEL_TYPE_CLASSIC;
        if (!empty($customParams['multiParcels'])) {
            if ($customParams['multiParcelsCurrentNumber'] === $customParams['multiParcelsAmount']) {
                $type = self::LABEL_TYPE_MASTER;
            } else {
                $type = self::LABEL_TYPE_FOLLOWER;
            }
        }

        // PDF label is too big to be stored in an order meta
        try {
            $this->outwardLabelDb->insert($order->get_id(), $label, $parcelNumber, $type, $cn23, $labelFormat, $detail);
        } catch (Exception $e) {
            if (is_admin()) {
                $lpc_admin_notices->add_notice(
                    'outward_label_generate',
                    'notice-error',
                    sprintf(__('Order %s : Outward label was not generated:', 'wc_colissimo'), $orderId) . ' ' . $e->getMessage()
                );
            }

            return false;
        }

        if (is_admin()) {
            $actions = '';

            $labelQueries = new LpcLabelQueries();
            if (current_user_can('lpc_download_labels')) {
                $actions .= '<span class="dashicons dashicons-download lpc_label_action_download" ' .
                            $labelQueries->getLabelOutwardDownloadAttr($parcelNumber, $labelFormat) . '></span>';
            }

            if (current_user_can('lpc_print_labels')) {
                $printerIcon = $GLOBALS['wp_version'] >= '5.5' ? 'dashicons-printer' : 'dashicons-media-default';
                $actions     .= '<span class="dashicons ' . $printerIcon . ' lpc_label_action_print" ' .
                                $labelQueries->getLabelOutwardPrintAttr($parcelNumber, $labelFormat) . ' ></span>';
            }

            $lpc_admin_notices->add_notice(
                'outward_label_generate',
                'notice-success',
                sprintf(__('Order %s : Outward label generated', 'wc_colissimo'), $orderId) . $actions
            );
        }

        if ($fullyShipped) {
            $this->applyStatusAfterLabelGeneration($order);
        } else {
            $this->applyStatusAfterPartialExpedition($order);
        }

        $email_outward_label = LpcHelper::get_option(LpcOutwardLabelEmailManager::EMAIL_OUTWARD_TRACKING_OPTION, 'no');
        if (LpcOutwardLabelEmailManager::ON_OUTWARD_LABEL_GENERATION_OPTION === $email_outward_label) {
            /**
             * Action when the shipping label has been sent by email
             *
             * @since 1.6
             */
            do_action(
                'lpc_outward_label_generated_to_email',
                ['order' => $order]
            );
        }

        if ('yes' === LpcHelper::get_option('lpc_createReturnLabelWithOutward')) {
            $this->labelGenerationInward->generate($order);
        }

        return true;
    }

    private function isFullyShipped($order, $itemsInLabel) {
        $labelDetails = $this->outwardLabelDb->getAllLabelDetailByOrderId($order->get_id());

        $alreadyGeneratedLabelItems = [];
        foreach ($labelDetails as $detail) {
            if (empty($detail)) {
                continue;
            }
            $detail = json_decode($detail, true);
            $this->addItemsToAlreadyGeneratedLabel($alreadyGeneratedLabelItems, $detail);
        }
        $this->addItemsToAlreadyGeneratedLabel($alreadyGeneratedLabelItems, $itemsInLabel);

        $allItemsOrders = $order->get_items();

        $fullyShipped = true;

        foreach ($allItemsOrders as $item) {
            $product = $item->get_product();
            if (empty($product) || !$product->needs_shipping()) {
                continue;
            }

            if (empty($alreadyGeneratedLabelItems[$item->get_id()]) || $alreadyGeneratedLabelItems[$item->get_id()]['qty'] < $item->get_quantity()) {
                $fullyShipped = false;
                break;
            }
        }

        return $fullyShipped;
    }

    public function addItemsToAlreadyGeneratedLabel(&$alreadyGeneratedLabelItems, $items) {
        foreach ($items as $itemId => $oneItemDetail) {
            if ('insured' === $itemId) {
                continue;
            }
            if (empty($alreadyGeneratedLabelItems[$itemId])) {
                $alreadyGeneratedLabelItems[$itemId] = $oneItemDetail;
            } else {
                foreach ($oneItemDetail as $itemParams => $itemParamsValue) {
                    if (!isset($alreadyGeneratedLabelItems[$itemId][$itemParams])) {
                        $alreadyGeneratedLabelItems[$itemId][$itemParams] = 0;
                    }
                    $alreadyGeneratedLabelItems[$itemId][$itemParams] += $itemParamsValue;
                }
            }
        }
    }

    /**
     * @throws Exception When the product code couldn't be found.
     */
    protected function buildPayload(WC_Order $order, $customParams = []) {
        $recipient = [
            'companyName' => $order->get_shipping_company(),
            'firstName'   => $order->get_shipping_first_name(),
            'lastName'    => $order->get_shipping_last_name(),
            'street'      => $order->get_shipping_address_1(),
            'street2'     => $order->get_shipping_address_2(),
            'city'        => $order->get_shipping_city(),
            'zipCode'     => $order->get_shipping_postcode(),
            'countryCode' => $order->get_shipping_country(),
            'stateCode'   => $order->get_shipping_state(),
            'email'       => $order->get_billing_email(),
            'phone'       => $order->get_billing_phone(),
        ];

        // For Luxembourg, the zip code must not have the "L-" prefix
        if ('LU' === strtoupper($recipient['countryCode'])) {
            $recipient['zipCode'] = ltrim($recipient['zipCode'], 'lL-');
        }

        // United Arab Emirates don't use zip codes
        if ('AE' === strtoupper($recipient['countryCode'])) {
            $recipient['zipCode'] = '00000';
        }

        if (method_exists($order, 'get_shipping_phone')) {
            $shippingPhone = $order->get_shipping_phone();
            if (!empty($shippingPhone)) {
                $recipient['phone'] = $shippingPhone;
            }
        }

        $productCode = $this->capabilitiesPerCountry->getProductCodeForOrder($order);
        if (empty($productCode)) {
            LpcLogger::error('Not allowed for this destination', ['order' => $order]);
            throw new Exception(__('Not allowed for this destination', 'wc_colissimo'));
        }

        $shippingMethodUsed = $this->shippingMethods->getColissimoShippingMethodOfOrder($order);

        $payload = new LpcLabelGenerationPayload();
        $payload
            ->withOrderNumber($order->get_order_number())
            ->withProductCode($productCode)
            ->withCredentials()
            ->withCommercialName(LpcHelper::get_option('lpc_origin_company_name'))
            ->withCuserInfoText()
            ->withSender()
            ->withAddressee($recipient)
            ->withPackage($order, $customParams)
            ->withPreparationDelay()
            ->withInstructions($order->get_customer_note())
            ->withCustomsDeclaration($order, $customParams, $shippingMethodUsed)
            ->withOutputFormat()
            ->withPostalNetwork($recipient['countryCode'], $order)
            ->withNonMachinable($customParams)
            ->withDDP($shippingMethodUsed)
            ->withMultiParcels($order->get_id(), $customParams)
            ->withBlockingCode($shippingMethodUsed, $order, $customParams);

        if ('lpc_relay' === $shippingMethodUsed) {
            $relayId = $order->get_meta(LpcPickupSelection::PICKUP_LOCATION_ID_META_KEY);
            $payload->withPickupLocationId($relayId);
        }

        $payload->withInsuranceValue($order->get_subtotal(), $order->get_shipping_country(), $shippingMethodUsed, $customParams);

        return $payload->checkConsistency();
    }

    public function applyStatusAfterLabelGeneration(WC_Order $order) {
        $statusToApply = LpcHelper::get_option('lpc_order_status_on_label_generated', null);

        if (!empty($statusToApply) && 'unchanged_order_status' !== $statusToApply) {
            $order->set_status($statusToApply);
            $order->save();
        }
    }

    protected function applyStatusAfterPartialExpedition(WC_Order $order) {
        $statusToApply = LpcHelper::get_option('lpc_status_on_partial_expedition', 'wc-lpc_partial_exp');

        if (!empty($statusToApply) && 'unchanged_order_status' !== $statusToApply) {
            $order->set_status($statusToApply);
            $order->save();
        }
    }
}
