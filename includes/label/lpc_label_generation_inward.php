<?php

require_once LPC_INCLUDES . 'label' . DS . 'lpc_label_generation_payload.php';

class LpcLabelGenerationInward extends LpcComponent {
    const ACTION_NAME = 'lpc_order_generate_inward_label';

    const INWARD_PARCEL_NUMBER_META_KEY = 'lpc_inward_parcel_number';

    protected $capabilitiesPerCountry;
    protected $labelGenerationApi;
    protected $inwardLabelDb;

    public function __construct(
        LpcCapabilitiesPerCountry $capabilitiesPerCountry = null,
        LpcLabelGenerationApi $labelGenerationApi = null,
        LpcInwardLabelDb $inwardLabelDb = null
    ) {
        $this->capabilitiesPerCountry = LpcRegister::get('capabilitiesPerCountry', $capabilitiesPerCountry);
        $this->labelGenerationApi     = LpcRegister::get('labelGenerationApi', $labelGenerationApi);
        $this->inwardLabelDb          = LpcRegister::get('inwardLabelDb', $inwardLabelDb);
    }

    public function getDependencies() {
        return ['capabilitiesPerCountry', 'labelGenerationApi', 'inwardLabelDb'];
    }

    public function generate(WC_Order $order, $customParams = []) {
        if (is_admin()) {
            $lpc_admin_notices = LpcRegister::get('lpcAdminNotices');
        }

        try {
            $payload  = $this->buildPayload($order, $customParams);
            $response = $this->labelGenerationApi->generateLabel($payload);
            if (is_admin()) {
                $lpc_admin_notices->add_notice(
                    'inward_label_generate',
                    'notice-success',
                    sprintf(__('Order %s : Inward label generated', 'wc_colissimo'), $order->get_order_number())
                );
            }
        } catch (Exception $e) {
            if (is_admin()) {
                $lpc_admin_notices->add_notice(
                    'inward_label_generate',
                    'notice-error',
                    sprintf(__('Order %s : Inward label was not generated:', 'wc_colissimo'), $order->get_order_number()) . ' ' . $e->getMessage()
                );
            }

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

        $payload       = new LpcLabelGenerationPayload();
        $returnAddress = $payload->getReturnAddress();
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
            ->withCustomsDeclaration($order, $customParams);

        return $payload->checkConsistency();
    }
}
