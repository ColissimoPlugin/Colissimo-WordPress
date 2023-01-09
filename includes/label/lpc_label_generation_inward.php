<?php

require_once LPC_INCLUDES . 'label' . DS . 'lpc_label_generation_payload.php';

class LpcLabelGenerationInward extends LpcComponent {
    const ACTION_NAME = 'lpc_order_generate_inward_label';
    const INWARD_PARCEL_NUMBER_META_KEY = 'lpc_inward_parcel_number';
    const ORDERS_INWARD_PARCEL_FAILED = 'lpc_orders_inward_parcel_failed';

    protected $capabilitiesPerCountry;
    protected $labelGenerationApi;
    protected $inwardLabelDb;
    protected $shippingMethods;

    public function __construct(
        LpcCapabilitiesPerCountry $capabilitiesPerCountry = null,
        LpcLabelGenerationApi $labelGenerationApi = null,
        LpcInwardLabelDb $inwardLabelDb = null,
        LpcShippingMethods $shippingMethods = null
    ) {
        $this->capabilitiesPerCountry = LpcRegister::get('capabilitiesPerCountry', $capabilitiesPerCountry);
        $this->labelGenerationApi     = LpcRegister::get('labelGenerationApi', $labelGenerationApi);
        $this->inwardLabelDb          = LpcRegister::get('inwardLabelDb', $inwardLabelDb);
        $this->shippingMethods        = LpcRegister::get('shippingMethods', $shippingMethods);
    }

    public function getDependencies() {
        return ['capabilitiesPerCountry', 'labelGenerationApi', 'inwardLabelDb', 'shippingMethods'];
    }

    public function generate(WC_Order $order, $customParams = []) {
        if (is_admin()) {
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
                })
            );
        }
        try {
            $payload  = $this->buildPayload($order, $customParams);
            $response = $this->labelGenerationApi->generateLabel($payload);
            if (is_admin()) {
                $lpc_admin_notices->add_notice(
                    'inward_label_generate',
                    'notice-success',
                    sprintf(__('Order %s : Inward label generated', 'wc_colissimo'), $orderId)
                );
            }

            if (!empty($ordersFailed[$customParams['outward_label_number']])) {
                unset($ordersFailed[$customParams['outward_label_number']]);
                update_option(self::ORDERS_INWARD_PARCEL_FAILED, $ordersFailed);
            }
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            if (is_admin()) {
                $lpc_admin_notices->add_notice(
                    'inward_label_generate',
                    'notice-error',
                    sprintf(__('Order %s : Inward label was not generated:', 'wc_colissimo'), $orderId) . ' ' . $errorMessage
                );
            }

            $ordersFailed[$customParams['outward_label_number']] = [
                'message' => $errorMessage,
                'time'    => $time,
            ];
            update_option(self::ORDERS_INWARD_PARCEL_FAILED, $ordersFailed);

            return;
        }
        $parcelNumber = $response['<jsonInfos>']['labelV2Response']['parcelNumber'];
        $label        = $response['<label>'];

        // currently, and contrary to the not-return/outward CN23, in the return/inward CN23
        // the API always inlines the CN23 elements at the end of the label (and not in a dedicated field...)
        // because it may change in order to be more symmetrical, this code does not assume that the CN23
        // field is empty.
        $cn23 = @$response['<cn23>'];

        $labelFormat = $payload->getLabelFormat();

        update_post_meta($order->get_id(), self::INWARD_PARCEL_NUMBER_META_KEY, $parcelNumber);

        // PDF label is too big to be stored in a post_meta
        $outwardLabelNumber = isset($customParams['outward_label_number']) ? $customParams['outward_label_number'] : null;
        $this->inwardLabelDb->insert($order->get_id(), $label, $parcelNumber, $cn23, $labelFormat, $outwardLabelNumber);
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
    }

    protected function buildPayload(WC_Order $order, $customParams = []) {
        $customerAddress = [
            'companyName'  => $order->get_shipping_company(),
            'firstName'    => $order->get_shipping_first_name(),
            'lastName'     => $order->get_shipping_last_name(),
            'street'       => $order->get_shipping_address_1(),
            'street2'      => $order->get_shipping_address_2(),
            'city'         => $order->get_shipping_city(),
            'zipCode'      => $order->get_shipping_postcode(),
            'countryCode'  => $order->get_shipping_country(),
            'email'        => $order->get_billing_email(),
            'mobileNumber' => $order->get_billing_phone(),
        ];

        $productCode = $this->capabilitiesPerCountry->getReturnProductCodeForDestination($order->get_shipping_country());

        if (empty($productCode)) {
            LpcLogger::error('Not allowed for this destination', ['order' => $order]);
            throw new \Exception(__('Not allowed for this destination', 'wc_colissimo'));
        }

        $payload            = new LpcLabelGenerationPayload();
        $returnAddress      = $payload->getReturnAddress();
        $shippingMethodUsed = $this->shippingMethods->getColissimoShippingMethodOfOrder($order);
        $payload
            ->isReturnLabel(true)
            ->withOrderNumber($order->get_order_number())
            ->withContractNumber()
            ->withPassword()
            ->withCuserInfoText()
            ->withSender($customerAddress)
            ->withAddressee($returnAddress)
            ->withPackage($order, $customParams)
            ->withPreparationDelay()
            ->withInstructions($order->get_customer_note())
            ->withProductCode($productCode)
            ->withOutputFormat()
            ->withCustomsDeclaration($order, $customParams)
            ->withInsuranceValue($order->get_subtotal(), $productCode, $order->get_shipping_country(), $shippingMethodUsed, $order->get_order_number(), $customParams, true);

        return $payload->checkConsistency();
    }
}
