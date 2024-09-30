<?php

require_once LPC_INCLUDES . 'label' . DS . 'lpc_label_generation_payload.php';

class LpcLabelGenerationInward extends LpcComponent {
    const INWARD_PARCEL_NUMBER_META_KEY = 'lpc_inward_parcel_number';
    const ORDERS_INWARD_PARCEL_FAILED = 'lpc_orders_inward_parcel_failed';

    /** @var LpcCapabilitiesPerCountry */
    protected $capabilitiesPerCountry;
    /** @var LpcLabelGenerationApi */
    protected $labelGenerationApi;
    /** @var LpcInwardLabelDb */
    protected $inwardLabelDb;
    /** @var LpcShippingMethods */
    protected $shippingMethods;
    /** @var LpcAccountApi */
    protected $accountApi;

    public function __construct(
        LpcCapabilitiesPerCountry $capabilitiesPerCountry = null,
        LpcLabelGenerationApi $labelGenerationApi = null,
        LpcInwardLabelDb $inwardLabelDb = null,
        LpcShippingMethods $shippingMethods = null,
        LpcAccountApi $accountApi = null
    ) {
        $this->capabilitiesPerCountry = LpcRegister::get('capabilitiesPerCountry', $capabilitiesPerCountry);
        $this->labelGenerationApi     = LpcRegister::get('labelGenerationApi', $labelGenerationApi);
        $this->inwardLabelDb          = LpcRegister::get('inwardLabelDb', $inwardLabelDb);
        $this->shippingMethods        = LpcRegister::get('shippingMethods', $shippingMethods);
        $this->accountApi             = LpcRegister::get('accountApi', $accountApi);
    }

    public function getDependencies(): array {
        return ['capabilitiesPerCountry', 'labelGenerationApi', 'inwardLabelDb', 'shippingMethods', 'accountApi'];
    }

    public function generate(WC_Order $order, $customParams = []) {
        if (is_admin() && empty($customParams['is_from_client'])) {
            $lpc_admin_notices = LpcRegister::get('lpcAdminNotices');
        }

        $time         = time();
        $orderId      = $order->get_order_number();
        $ordersFailed = get_option(self::ORDERS_INWARD_PARCEL_FAILED, []);
        if (!empty($ordersFailed)) {
            update_option(
                self::ORDERS_INWARD_PARCEL_FAILED,
                array_filter($ordersFailed, function ($error) use ($time) {
                    return $error['time'] < $time - 604800;
                }),
                false
            );
        }

        try {
            $payload         = $this->buildPayload($order, $customParams);
            $isSecuredReturn = false;
            if (!empty($customParams['is_from_client'])) {
                $accountInformation = $this->accountApi->getAccountInformation();
                if (!empty($accountInformation['optionRetourToken'])) {
                    $isSecuredReturn = 1 === intval(LpcHelper::get_option('lpc_secured_return', 0));
                }
            }
            $response = $this->labelGenerationApi->generateLabel($payload, $isSecuredReturn);

            if (!empty($customParams['outward_label_number']) && !empty($ordersFailed[$customParams['outward_label_number']])) {
                unset($ordersFailed[$customParams['outward_label_number']]);
                update_option(self::ORDERS_INWARD_PARCEL_FAILED, $ordersFailed, false);
            }
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            if (!empty($lpc_admin_notices)) {
                $lpc_admin_notices->add_notice(
                    'inward_label_generate',
                    'notice-error',
                    sprintf(__('Order %s: Inward label was not generated:', 'wc_colissimo'), $orderId) . ' ' . $errorMessage
                );
            }

            if (!empty($customParams['outward_label_number']) && 'no_outward' !== $customParams['outward_label_number']) {
                $ordersFailed[$customParams['outward_label_number']] = [
                    'message' => $errorMessage,
                    'time'    => $time,
                ];
                update_option(self::ORDERS_INWARD_PARCEL_FAILED, $ordersFailed, false);
            }

            return false;
        }

        $contentResponseName = $isSecuredReturn ? 'tokenV2Response' : 'labelV2Response';
        $parcelNumber        = $response['<jsonInfos>'][$contentResponseName]['parcelNumber'];
        $label               = $response['<label>'];

        // currently, and contrary to the not-return/outward CN23, in the return/inward CN23
        // the API always inlines the CN23 elements at the end of the label (and not in a dedicated field...)
        // because it may change in order to be more symmetrical, this code does not assume that the CN23
        // field is empty.
        $cn23 = @$response['<cn23>'];

        $labelFormat = $payload->getLabelFormat();

        $order->update_meta_data(self::INWARD_PARCEL_NUMBER_META_KEY, $parcelNumber);
        $order->save();

        try {
            $outwardLabelNumber = $customParams['outward_label_number'] ?? null;
            $this->inwardLabelDb->insert($order->get_id(), $label, $parcelNumber, $cn23, $labelFormat, $outwardLabelNumber);
        } catch (Exception $e) {
            if (!empty($lpc_admin_notices)) {
                $lpc_admin_notices->add_notice(
                    'inward_label_generate',
                    'notice-error',
                    sprintf(__('Order %s: Inward label was not generated:', 'wc_colissimo'), $orderId) . ' ' . $e->getMessage()
                );
            }

            return false;
        }

        if (!empty($lpc_admin_notices)) {
            $actions = '';

            $labelQueries = new LpcLabelQueries();
            if (current_user_can('lpc_download_labels')) {
                $actions .= '<span class="dashicons dashicons-download lpc_label_action_download" ' .
                            $labelQueries->getLabelInwardDownloadAttr($parcelNumber, $labelFormat) . '></span>';
            }

            if (current_user_can('lpc_print_labels')) {
                $printerIcon = $GLOBALS['wp_version'] >= '5.5' ? 'dashicons-printer' : 'dashicons-media-default';
                $actions     .= '<span class="dashicons ' . $printerIcon . ' lpc_label_action_print" ' .
                                $labelQueries->getLabelInwardPrintAttr($parcelNumber, $labelFormat) . ' ></span>';
            }

            $lpc_admin_notices->add_notice(
                'inward_label_generate',
                'notice-success',
                sprintf(__('Order %s: Inward label generated', 'wc_colissimo'), $orderId) . $actions
            );
        }

        $email_inward_label = LpcHelper::get_option(LpcInwardLabelEmailManager::EMAIL_RETURN_LABEL_OPTION, 'no');
        if ('yes' === $email_inward_label) {
            /**
             * Action when the return shipping label has been sent by email
             *
             * @since 1.0.2
             */
            do_action(
                'lpc_inward_label_generated_to_email',
                [
                    'order' => $order,
                    'label' => $label,
                ]
            );
        }

        return $parcelNumber;
    }

    protected function buildPayload(WC_Order $order, $customParams = []) {
        $customerAddress = [
            'companyName' => $order->get_shipping_company(),
            'firstName'   => $order->get_shipping_first_name(),
            'lastName'    => $order->get_shipping_last_name(),
            'street'      => $order->get_shipping_address_1(),
            'street2'     => $order->get_shipping_address_2(),
            'city'        => $order->get_shipping_city(),
            'zipCode'     => $order->get_shipping_postcode(),
            'countryCode' => $order->get_shipping_country(),
            'email'       => $order->get_billing_email(),
            'phone'       => $order->get_billing_phone(),
        ];

        // For Luxembourg, the zip code must not have the "L-" prefix
        if ('LU' === strtoupper($customerAddress['countryCode'])) {
            $customerAddress['zipCode'] = ltrim($customerAddress['zipCode'], 'lL-');
        }

        if (method_exists($order, 'get_shipping_phone')) {
            $shippingPhone = $order->get_shipping_phone();
            if (!empty($shippingPhone)) {
                $customerAddress['phone'] = $shippingPhone;
            }
        }

        $productCode = $this->capabilitiesPerCountry->getReturnProductCodeForDestination($order->get_shipping_country());

        if (empty($productCode)) {
            LpcLogger::error('Not allowed for this destination', ['order' => $order]);
            throw new \Exception(__('Not allowed for this destination', 'wc_colissimo'));
        }

        $payload            = new LpcLabelGenerationPayload();
        $returnAddress      = $payload->getReturnAddress();
        $shippingMethodUsed = $this->shippingMethods->getColissimoShippingMethodOfOrder($order);
        $payload
            ->isReturnLabel()
            ->withOrderNumber($order->get_order_number())
            ->withProductCode($productCode)
            ->withCredentials()
            ->withCuserInfoText()
            ->withSender($customerAddress, $customParams)
            ->withAddressee($returnAddress)
            ->withPackage($order, $customParams)
            ->withPreparationDelay()
            ->withInstructions($order->get_customer_note())
            ->withOutputFormat($customParams)
            ->withCustomsDeclaration($order, $customParams)
            ->withInsuranceValue($order->get_subtotal(), $order->get_shipping_country(), $shippingMethodUsed, $customParams);

        return $payload->checkConsistency();
    }
}
