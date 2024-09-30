<?php

class LpcLabelGenerationPayload {
    private const MAX_INSURANCE_AMOUNT = 5000;
    private const MAX_INSURANCE_AMOUNT_RELAY = 1000;
    private const FORCED_ORIGINAL_IDENT = 'A';
    private const RETURN_TYPE_CHOICE_NO_RETURN = 3;
    private const CUSTOMS_CATEGORY_RETURN_OF_ARTICLES = 6;
    private const BELGIAN_MOBILE_NUMBER_REGEX = '/^(?:(?:\+|00)32|0)4\d{8}$/';

    public const PRODUCT_CODE_RELAY = 'HD';
    public const PRODUCT_CODE_WITHOUT_SIGNATURE = 'DOM';
    public const PRODUCT_CODE_WITHOUT_SIGNATURE_OM = 'COM';
    public const PRODUCT_CODE_WITHOUT_SIGNATURE_INTRA_DOM = 'COLD';
    public const PRODUCT_CODE_WITH_SIGNATURE = 'DOS';
    public const PRODUCT_CODE_WITH_SIGNATURE_OM = 'CDS';
    public const PRODUCT_CODE_WITH_SIGNATURE_INTRA_DOM = 'COL';
    public const PRODUCT_CODE_RETURN_FRANCE = 'CORE';
    public const PRODUCT_CODE_RETURN_INT = 'CORI';

    private const ALL_PRODUCT_CODES = [
        self::PRODUCT_CODE_WITH_SIGNATURE_OM,
        self::PRODUCT_CODE_WITH_SIGNATURE_INTRA_DOM,
        self::PRODUCT_CODE_WITHOUT_SIGNATURE_INTRA_DOM,
        self::PRODUCT_CODE_WITHOUT_SIGNATURE_OM,
        self::PRODUCT_CODE_RETURN_FRANCE,
        self::PRODUCT_CODE_RETURN_INT,
        self::PRODUCT_CODE_WITHOUT_SIGNATURE,
        self::PRODUCT_CODE_WITH_SIGNATURE,
        self::PRODUCT_CODE_RELAY,
    ];
    private const PRODUCT_CODE_INSURANCE_AVAILABLE = [
        self::PRODUCT_CODE_WITH_SIGNATURE,
        self::PRODUCT_CODE_WITH_SIGNATURE_OM,
        self::PRODUCT_CODE_WITH_SIGNATURE_INTRA_DOM,
        self::PRODUCT_CODE_RELAY,
        self::PRODUCT_CODE_RETURN_FRANCE,
        self::PRODUCT_CODE_RETURN_INT,
    ];

    public const LABEL_FORMAT_PDF = 'PDF';
    public const LABEL_FORMAT_ZPL = 'ZPL';
    public const LABEL_FORMAT_DPL = 'DPL';
    private const LABEL_FORMATS = [self::LABEL_FORMAT_PDF, self::LABEL_FORMAT_ZPL, self::LABEL_FORMAT_DPL];
    const DEFAULT_FORMAT = 'PDF_A4_300dpi';

    private const FRENCH_COUNTRY_CODE = 'FR';
    private const BELGIAN_COUNTRY_CODE = 'BE';
    private const US_COUNTRY_CODE = 'US';
    public const COUNTRIES_NEEDING_STATE = ['CA', self::US_COUNTRY_CODE];

    protected $payload;
    protected $isReturnLabel;
    protected $capabilitiesPerCountry;
    protected $orderNumber;
    protected $eoriAdded;
    protected $dimensionsAdded;

    /** @var LpcShippingMethods */
    protected $lpcShippingMethods;

    /** @var LpcOutwardLabelDb */
    protected $outwardLabelDb;

    /** @var LpcAccountApi */
    protected $accountApi;

    public function __construct(
        LpcCapabilitiesPerCountry $capabilitiesPerCountry = null,
        LpcShippingMethods $lpcShippingMethods = null,
        LpcOutwardLabelDb $outwardLabelDb = null,
        LpcAccountApi $accountApi = null
    ) {
        $this->capabilitiesPerCountry = LpcRegister::get('capabilitiesPerCountry', $capabilitiesPerCountry);
        $this->lpcShippingMethods     = LpcRegister::get('shippingMethods', $lpcShippingMethods);
        $this->outwardLabelDb         = LpcRegister::get('outwardLabelDb', $outwardLabelDb);
        $this->accountApi             = LpcRegister::get('accountApi', $accountApi);

        $this->payload = [
            'letter' => [
                'service' => [],
                'parcel'  => [],
            ],
        ];

        $this->isReturnLabel   = false;
        $this->eoriAdded       = false;
        $this->dimensionsAdded = false;
    }

    public function withSender(array $sender = null, array $customParams = []) {
        if (null === $sender) {
            $sender = $this->getStoreAddress();
        }

        $payloadSender = [
            'companyName' => @$sender['companyName'],
            'firstName'   => @$sender['firstName'],
            'lastName'    => @$sender['lastName'],
            'line2'       => @$sender['street'],
            'countryCode' => $sender['countryCode'],
            'city'        => $sender['city'],
            'zipCode'     => $sender['zipCode'],
            'email'       => @$sender['email'],
        ];

        if (!empty($customParams['sender'])) {
            $payloadSender = array_merge($payloadSender, $customParams['sender']);
        } elseif (!empty($sender['street2'])) {
            $payloadSender['line3'] = $sender['street2'];
        }

        if (!empty($sender['mobileNumber'])) {
            $payloadSender['mobileNumber'] = $this->formatPhone($sender['mobileNumber']);
        }

        if (!empty($sender['phoneNumber'])) {
            $payloadSender['phoneNumber'] = $this->formatPhone($sender['phoneNumber']);
        }

        if (!empty($sender['phone'])) {
            $payloadSender['phoneNumber'] = $this->formatPhone($sender['phone']);
        }

        $zipDashPos = strpos($payloadSender['zipCode'], '-');
        if (self::US_COUNTRY_CODE === $payloadSender['countryCode'] && false !== $zipDashPos) {
            $payloadSender['zipCode'] = substr($payloadSender['zipCode'], 0, $zipDashPos);
        }

        /**
         * Filter on the sender when generating a label
         *
         * @since 1.6
         */
        $payloadSender = apply_filters('lpc_payload_letter_sender', $payloadSender, $this->getOrderNumber(), $this->getIsReturnLabel());

        $this->payload['letter']['sender']['address'] = $payloadSender;

        return $this;
    }

    public function withCommercialName($commercialName = null) {
        /**
         * Filter on the commercial name when generating a label
         *
         * @since 1.6
         */
        $commercialName = apply_filters('lpc_payload_letter_service_commercial_name', $commercialName, $this->getOrderNumber(), $this->getIsReturnLabel());

        if (empty($commercialName)) {
            unset($this->payload['letter']['service']['commercialName']);
        } else {
            $this->payload['letter']['service']['commercialName'] = $commercialName;
        }

        return $this;
    }

    public function withCredentials() {
        if ('api_key' !== LpcHelper::get_option('lpc_credentials_type', 'account')) {
            $contractNumber = LpcHelper::get_option('lpc_id_webservices');

            /**
             * Filter on the contract number when generating a label
             *
             * @since 1.6
             */
            $contractNumber = apply_filters('lpc_payload_contract_number', $contractNumber, $this->getOrderNumber(), $this->getIsReturnLabel());

            if (!empty($contractNumber)) {
                $this->payload['contractNumber'] = $contractNumber;
            }

            $password = LpcHelper::getPasswordWebService();

            if (empty($password)) {
                unset($this->payload['password']);
            } else {
                $this->payload['password'] = $password;
            }
        }

        $parentAccountId = LpcHelper::get_option('lpc_parent_account');
        if (!empty($parentAccountId)) {
            $this->payload['fields']['field'][] = [
                'key'   => 'ACCOUNT_NUMBER',
                'value' => $parentAccountId,
            ];
        }

        return $this;
    }

    public function withAddressee(array $addressee) {
        $payloadAddressee = [
            'address' => [
                'companyName' => @$addressee['companyName'],
                'firstName'   => @$addressee['firstName'],
                'lastName'    => @$addressee['lastName'],
                'line2'       => $addressee['street'],
                'countryCode' => $addressee['countryCode'],
                'city'        => $addressee['city'],
                'zipCode'     => $addressee['zipCode'],
                'email'       => @$addressee['email'],
            ],
        ];

        if (in_array($addressee['countryCode'], self::COUNTRIES_NEEDING_STATE) && !empty($addressee['stateCode'])) {
            $payloadAddressee['address']['stateOrProvinceCode'] = $addressee['stateCode'];
        }

        $zipDashPos = strpos($addressee['zipCode'], '-');
        if (self::US_COUNTRY_CODE === $addressee['countryCode'] && false !== $zipDashPos) {
            $payloadAddressee['address']['zipCode'] = substr($addressee['zipCode'], 0, $zipDashPos);
        }

        if (!$this->getIsReturnLabel() && !empty($addressee['phone'])) {
            $phoneNumber = str_replace(' ', '', $addressee['phone']);
            $countryCode = empty($addressee['countryCode']) ? self::FRENCH_COUNTRY_CODE : $addressee['countryCode'];

            if (self::BELGIAN_COUNTRY_CODE === $countryCode && preg_match(self::BELGIAN_MOBILE_NUMBER_REGEX, $phoneNumber)) {
                $phoneNumber = preg_replace('/(04|00324)([0-9]{8})/', '+324$2', $phoneNumber);
            }

            $phoneField             = self::PRODUCT_CODE_RELAY === $this->payload['letter']['service']['productCode'] ? 'mobileNumber' : 'phoneNumber';
            $addressee[$phoneField] = $phoneNumber;
        }

        if (!empty($addressee['mobileNumber'])) {
            $payloadAddressee['address']['mobileNumber'] = $this->formatPhone($addressee['mobileNumber']);
        }

        if (!empty($addressee['phoneNumber'])) {
            $payloadAddressee['address']['phoneNumber'] = $this->formatPhone($addressee['phoneNumber']);
        }

        $this->setFtdGivenCountryCodeId($addressee['countryCode']);

        // Required bypass because Colissimo Labels for Belgium or Switzerland don't display line3
        $countryCodesNoLine3 = ['BE', 'CH'];
        if (in_array($addressee['countryCode'], $countryCodesNoLine3)) {
            if (!empty($addressee['street2'])) {
                $payloadAddressee['address']['line2'] = $payloadAddressee['address']['line2'] . ' ' . $addressee['street2'];
            }
        } else {
            if (!empty($addressee['street2'])) {
                $payloadAddressee['address']['line3'] = $addressee['street2'];
            } elseif (strlen($payloadAddressee['address']['line2']) > 35) {
                $payloadAddressee['address']['line2'] = substr($addressee['street'], 0, 35);
                $lastSpacePos                         = strrpos($payloadAddressee['address']['line2'], ' ');
                if (false === $lastSpacePos) {
                    $payloadAddressee['address']['line3'] = substr($addressee['street'], 35);
                } else {
                    $payloadAddressee['address']['line2'] = substr($addressee['street'], 0, $lastSpacePos);
                    $payloadAddressee['address']['line3'] = substr($addressee['street'], $lastSpacePos);
                }
            }
        }

        /**
         * Filter on the addressee when generating a label
         *
         * @since 1.6
         */
        $payloadAddressee = apply_filters('lpc_payload_letter_addressee', $payloadAddressee, $this->getOrderNumber(), $this->getIsReturnLabel());

        $this->payload['letter']['addressee'] = $payloadAddressee;

        return $this;
    }

    public function withPackage(WC_Order $order, &$customParams = []) {
        if (isset($customParams['totalWeight'])) {
            $totalWeight = wc_get_weight($customParams['totalWeight'], 'kg');
        } else {
            $nbProductsToShip   = 0;
            $totalWeight        = 0;
            $productsDimensions = [];
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if (empty($product)) {
                    throw new Exception(
                        __('The product couldn\'t be found.', 'wc_colissimo')
                    );
                }

                // Compatibility with WPC Product Bundles for WooCommerce, don't count bundled products twice
                if (!$product->needs_shipping() || 'woosb' === $product->get_type()) {
                    continue;
                }

                $data                 = $item->get_data();
                $productWeight        = $product->get_weight() < 0.01 ? 0.01 : $product->get_weight();
                $nbProductsToShip     += (float) $data['quantity'];
                $weight               = (float) $productWeight * $data['quantity'];
                $productsDimensions[] = [
                    $product->get_length(),
                    $product->get_width(),
                    $product->get_height(),
                ];

                if ($weight < 0) {
                    throw new Exception(
                        __('Weight cannot be negative!', 'wc_colissimo')
                    );
                }

                $totalWeight += wc_get_weight($weight, 'kg');
            }

            $matchingPackaging = LpcHelper::getMatchingPackaging($nbProductsToShip, $totalWeight, $productsDimensions);
            if (empty($matchingPackaging)) {
                $totalWeight += wc_get_weight(LpcHelper::get_option('lpc_packaging_weight', '0'), 'kg');
            } else {
                $totalWeight += wc_get_weight($matchingPackaging['weight'], 'kg');
                if ($matchingPackaging['width'] + $matchingPackaging['length'] + $matchingPackaging['depth'] > 120) {
                    $customParams['nonMachinable'] = true;
                }
            }
        }

        if ($totalWeight < 0.01) {
            $totalWeight = 0.01;
        }

        $totalWeight = number_format($totalWeight, 2);

        /**
         * Filter on the parcel total weight when generating a label
         *
         * @since 1.6
         */
        $totalWeight = apply_filters('lpc_payload_letter_parcel_weight', $totalWeight, $this->getOrderNumber(), $this->getIsReturnLabel());

        $this->payload['letter']['parcel']['weight'] = (string) $totalWeight;

        if (!empty($customParams['packageLength']) && !empty($customParams['packageWidth']) && !empty($customParams['packageHeight'])) {
            $dimensions = [
                intval($customParams['packageLength']),
                intval($customParams['packageWidth']),
                intval($customParams['packageHeight']),
            ];

            sort($dimensions);

            $this->payload['fields']['field'][] = [
                'key'   => 'LENGTH',
                'value' => array_pop($dimensions),
            ];
            $this->payload['fields']['field'][] = [
                'key'   => 'WIDTH',
                'value' => array_pop($dimensions),
            ];
            $this->payload['fields']['field'][] = [
                'key'   => 'HEIGHT',
                'value' => array_pop($dimensions),
            ];

            $this->dimensionsAdded = true;
        }

        return $this;
    }

    public function withPickupLocationId($pickupLocationId) {
        /**
         * Filter on the pickup location id when generating a label
         *
         * @since 1.6
         */
        $pickupLocationId = apply_filters('lpc_payload_letter_parcel_pickup_location_id', $pickupLocationId, $this->getOrderNumber(), $this->getIsReturnLabel());

        if (null === $pickupLocationId) {
            unset($this->payload['letter']['parcel']['pickupLocationId']);
        } else {
            $this->payload['letter']['parcel']['pickupLocationId'] = $pickupLocationId;
        }

        return $this;
    }

    public function withProductCode($productCode) {
        /**
         * Filter on the product code when generating a label
         *
         * @since 1.6
         */
        $productCode = apply_filters('lpc_payload_letter_service_product_code', $productCode, $this->getOrderNumber(), $this->getIsReturnLabel());

        if (!in_array($productCode, self::ALL_PRODUCT_CODES)) {
            LpcLogger::error(
                'Unknown product code',
                [
                    'given' => $productCode,
                    'known' => self::ALL_PRODUCT_CODES,
                ]
            );
            throw new Exception('Unknown Product code!');
        }

        $this->payload['letter']['service']['productCode']      = $productCode;
        $this->payload['letter']['service']['returnTypeChoice'] = self::RETURN_TYPE_CHOICE_NO_RETURN;

        return $this;
    }

    protected function setFtdGivenCountryCodeId($destinationCountryId) {
        if (LpcHelper::get_option('lpc_customs_isFtd') === 'yes' && $this->capabilitiesPerCountry->getFtdRequiredForDestination($destinationCountryId) === true) {
            $this->payload['letter']['parcel']['ftd'] = true;
        } else {
            unset($this->payload['letter']['parcel']['ftd']);
        }
    }

    public function withDepositDate(\DateTime $depositDate) {
        $now = new \DateTime();
        /**
         * Filter on the deposit date when generating a label
         *
         * @since 1.6
         */
        $depositDate = apply_filters('lpc_payload_letter_service_deposit_date', $depositDate, $this->getOrderNumber(), $this->getIsReturnLabel());

        if ($depositDate->getTimestamp() < $now->getTimestamp()) {
            LpcLogger::warn(
                'Given DepositDate is in the past, using today instead.',
                [
                    'given' => $depositDate,
                    'now'   => $now,
                ]
            );
            $depositDate = $now;
        }

        $this->payload['letter']['service']['depositDate'] = $depositDate->format('Y-m-d');

        return $this;
    }

    public function withPreparationDelay($delay = null) {
        if (null === $delay) {
            $delay = LpcHelper::get_option('lpc_preparation_time');
        }

        /**
         * Filter on the preparation delay when generating a label
         *
         * @since 1.6
         */
        $delay = apply_filters('lpc_payload_delay', $delay, $this->getOrderNumber(), $this->getIsReturnLabel());

        $depositDate = new \DateTime();

        $delay = (int) $delay;
        if ($delay > 0) {
            $depositDate->add(new \DateInterval("P{$delay}D"));
        } else {
            LpcLogger::warn(
                'Preparation delay was not applied because it was negative or zero!',
                ['given' => $delay]
            );
        }

        return $this->withDepositDate($depositDate);
    }

    public function withOutputFormat(array $customParams = []) {
        if ($this->getIsReturnLabel()) {
            $outputFormat = LpcHelper::get_option('lpc_returnLabelFormat');
            if (self::PRODUCT_CODE_RETURN_INT === $this->payload['letter']['service']['productCode']) {
                $outputFormat = self::DEFAULT_FORMAT;
            }

            if (!empty($customParams['format']) && self::LABEL_FORMAT_PDF === $customParams['format'] && strpos($outputFormat, self::LABEL_FORMAT_PDF) !== 0) {
                $outputFormat = self::DEFAULT_FORMAT;
            }
        } else {
            $outputFormat = LpcHelper::get_option('lpc_deliveryLabelFormat');
        }

        /**
         * Filter on the output format when generating a label
         *
         * @since 1.6
         */
        $outputFormat = apply_filters('lpc_payload_output_format', $outputFormat, $this->getOrderNumber(), $this->getIsReturnLabel());

        $this->payload['outputFormat'] = [
            'x'                  => 0,
            'y'                  => 0,
            'outputPrintingType' => $outputFormat,
        ];

        return $this;
    }

    public function withOrderNumber($orderNumber) {
        $this->orderNumber = $orderNumber;

        /**
         * Filter on the order number when generating a label
         *
         * @since 1.6
         */
        $orderNumber = apply_filters('lpc_payload_letter_service_order_number', $orderNumber, $this->getOrderNumber(), $this->getIsReturnLabel());

        $this->payload['letter']['service']['orderNumber']    = $orderNumber;
        $this->payload['letter']['sender']['senderParcelRef'] = $orderNumber;

        return $this;
    }

    public function withInsuranceValue($amount, $countryCode, $shippingMethodUsed, $customParams = []) {
        if (!empty($customParams['useInsurance'])) {
            $usingInsurance = $customParams['useInsurance'];
        } else {
            $option         = $this->getIsReturnLabel() ? 'lpc_using_insurance_inward' : 'lpc_using_insurance';
            $usingInsurance = LpcHelper::get_option($option, 'no');
        }

        /**
         * Filter on the using insurance option when generating a label
         *
         * @since 1.6
         */
        $usingInsurance = apply_filters('lpc_payload_letter_parcel_using_insurance', $usingInsurance, $this->getOrderNumber(), $this->getIsReturnLabel());

        if ('yes' !== $usingInsurance || !in_array($this->payload['letter']['service']['productCode'], self::PRODUCT_CODE_INSURANCE_AVAILABLE)) {
            return $this;
        }

        if (is_admin()) {
            $lpc_admin_notices = LpcRegister::get('lpcAdminNotices');
        }

        // Insurance is set to yes
        if (!$this->capabilitiesPerCountry->getInsuranceAvailableForDestination($countryCode)) {
            if (is_admin()) {
                $lpc_admin_notices->add_notice(
                    'insurance_unavailable_for_country',
                    'notice-warning',
                    sprintf(__('Order %s: insurance is not available for this country', 'wc_colissimo'), $this->payload['letter']['service']['orderNumber'])
                );
            }

            return $this;
        }

        // Use user defined insurance amount if exists (from Colissimo banner in order edition)
        if (!empty($customParams['insuranceAmount'])) {
            $amount = $customParams['insuranceAmount'];
        }

        /**
         * Filter on the insurance value when generating a label
         *
         * @since 1.6
         */
        $amount             = (float) apply_filters('lpc_payload_letter_parcel_insurance_value', $amount, $this->getOrderNumber(), $this->getIsReturnLabel());
        $maxInsuranceAmount = $this->getMaxInsuranceAmountByProductCode($this->payload['letter']['service']['productCode']);

        if ($amount > $maxInsuranceAmount) {
            LpcLogger::warn(
                'Selected insurance amount is too big, forced to ' . $maxInsuranceAmount,
                [
                    'given' => $amount,
                    'max'   => $maxInsuranceAmount,
                ]
            );

            if (is_admin()) {
                $shippingMethods = $this->lpcShippingMethods->getAllShippingMethods();
                $lpc_admin_notices->add_notice(
                    'outward_label_generate',
                    'notice-info',
                    sprintf(
                        __('Order %1$s is insured up to %2$s euros, this is the maximum amount for parcels delivered with %3$s shipping method', 'wc_colissimo'),
                        $this->getOrderNumber(),
                        $maxInsuranceAmount,
                        $shippingMethods[$shippingMethodUsed]
                    )
                );
            }

            $amount = $maxInsuranceAmount;
        }

        if ($amount > 0) {
            // payload want centi-euros for this field.
            $this->payload['letter']['parcel']['insuranceValue'] = (int) ($amount * 100);
        } else {
            LpcLogger::warn(
                'Insurance value was not applied because it was negative or zero!',
                [
                    'given' => $amount,
                ]
            );
        }

        return $this;
    }

    public function withNonMachinable($customParams = []) {
        if (self::PRODUCT_CODE_RELAY === $this->payload['letter']['service']['productCode']) {
            $this->payload['letter']['parcel']['nonMachinable'] = 'false';

            return $this;
        }

        if (empty($customParams['nonMachinable'])) {
            return $this;
        }

        $this->payload['letter']['parcel']['nonMachinable'] = 'true';

        return $this;
    }

    public function withDDP($shippingMethodUsed) {
        if (!in_array($shippingMethodUsed, [LpcSignDDP::ID, LpcExpertDDP::ID])) {
            $this->payload['letter']['parcel']['ddp'] = 'false';

            return $this;
        }

        // Must have the state code for US and CA
        $address = $this->payload['letter']['addressee']['address'];
        if (in_array($address['countryCode'], self::COUNTRIES_NEEDING_STATE) && empty($address['stateOrProvinceCode'])) {
            LpcLogger::error(
                'Shipping state missing for DDP label generation',
                [
                    'shippingMethod' => $shippingMethodUsed,
                ]
            );
            throw new Exception(__('Shipping state missing for label generation with this country', 'wc_colissimo'));
        }

        // Must have a phone number
        if (empty($address['phoneNumber']) && empty($address['mobileNumber'])) {
            LpcLogger::error(
                'Phone number missing for DDP label generation',
                [
                    'shippingMethod' => $shippingMethodUsed,
                ]
            );
            throw new Exception(__('Please define a mobile phone number for SMS notification tracking', 'wc_colissimo'));
        }

        // Must have dimensions
        if (!$this->dimensionsAdded) {
            LpcLogger::error(
                'Package dimensions missing for DDP label generation',
                [
                    'shippingMethod' => $shippingMethodUsed,
                ]
            );
            throw new Exception(__('Please enter the package dimensions', 'wc_colissimo'));
        }

        // Must have EORI
        if (!$this->eoriAdded) {
            LpcLogger::error(
                'EORI missing for DDP label generation',
                [
                    'shippingMethod' => $shippingMethodUsed,
                ]
            );
            throw new Exception(__('Please enter the EORI code in the Colissimo configuration', 'wc_colissimo'));
        }

        $this->payload['letter']['parcel']['ddp'] = 'true';

        return $this;
    }

    public function withInstructions($instructions) {
        if (LpcHelper::get_option('lpc_add_customer_notes', 'no') === 'no') {
            return $this;
        }

        /**
         * Filter on the shipping instructions
         *
         * @since 1.6
         */
        $instructions = apply_filters('lpc_payload_letter_parcel_instructions', $instructions, $this->getOrderNumber(), $this->getIsReturnLabel());

        if (empty($instructions)) {
            unset($this->payload['letter']['parcel']['instructions']);
        } else {
            $this->payload['letter']['parcel']['instructions'] = substr(preg_replace('/[^A-Za-z0-9 ]/', '', $instructions), 0, 35);
        }

        return $this;
    }

    public function withCuserInfoText($info = null) {
        if (null === $info) {
            global $woocommerce;

            $woocommerceVersion = $woocommerce->version;
            $pluginData         = get_plugin_data(LPC_FOLDER . DS . 'index.php', false, false);
            $colissimoVersion   = $pluginData['Version'];

            $info = 'WC' . $woocommerceVersion . ';' . $colissimoVersion;
        }

        $customFields = [
            'key'   => 'CUSER_INFO_TEXT',
            'value' => $info,
        ];

        $this->payload['fields']['field'][] = $customFields;
        $this->payload['fields']['field'][] = [
            'key'   => 'CUSER_INFO_TEXT_3',
            'value' => 'WOOCOMMERCE',
        ];

        return $this;
    }

    public function withCustomsDeclaration(WC_Order $order, $customParams = [], $shippingMethodUsed = null) {
        // No need details if no CN23 required
        if (!$this->capabilitiesPerCountry->getIsCn23RequiredForDestination($order)) {
            return $this;
        }

        $destinationCountryId = $order->get_shipping_country();
        $isMasterParcel       = false;
        if (!empty($customParams['multiParcelsCurrentNumber']) && $customParams['multiParcelsCurrentNumber'] === $customParams['multiParcelsAmount']) {
            $isMasterParcel = true;
        }
        $isCustomItems       = isset($customParams['items']);
        $customsArticles     = [];
        $totalItemsAmount    = 0;
        $articleDescriptions = [];

        foreach ($order->get_items() as $item) {
            $itemId = $item->get_id();

            if (!$isMasterParcel && $isCustomItems && !isset($customParams['items'][$itemId])) {
                continue;
            }

            $product = $item->get_product();
            if (empty($product)) {
                throw new Exception(
                    __('The product couldn\'t be found.', 'wc_colissimo')
                );
            }

            // Compatibility with WPC Product Bundles for WooCommerce, don't count bundled products twice
            if (!$product->needs_shipping() || 'woosb' === $product->get_type()) {
                continue;
            }

            $quantity = !$isMasterParcel && isset($customParams['items'][$itemId]['qty']) ? $customParams['items'][$itemId]['qty'] : $item->get_quantity();

            if (isset($customParams['items'][$itemId]['price'])) {
                $unitaryValue = $customParams['items'][$itemId]['price'];
            } elseif (!empty(wc_get_order_item_meta($item->get_id(), '_line_total'))) {
                $unitaryValue = wc_get_order_item_meta($item->get_id(), '_line_total');
                if (!empty(wc_get_order_item_meta($item->get_id(), '_qty'))) {
                    $unitaryValue /= wc_get_order_item_meta($item->get_id(), '_qty');
                }
            } else {
                $productPrice = wc_get_price_excluding_tax($product);
                $unitaryValue = empty($productPrice) ? 1 : $productPrice;
            }

            // Some users may have a free product, but the customs declaration needs a value > 0
            if (empty($unitaryValue)) {
                $unitaryValue = 1;
            }

            $totalItemsAmount += $unitaryValue * $quantity;

            // Handle emojis in product name
            $description        = $item->get_name();
            $encodedDescription = wp_json_encode($description);
            if (strpos($encodedDescription, '\u') !== false) {
                $description = trim(preg_replace('#\\\u.{4}#Ui', '', trim($encodedDescription, '"')));
            }

            // The Colissimo API returns an error if there is an accent
            $description = LpcHelper::replaceAccents($description);

            $description           = substr($description, 0, 64);
            $articleDescriptions[] = $description;

            $customsArticle = [
                'description'   => $description,
                'quantity'      => $quantity,
                'value'         => (string) round($unitaryValue, 2),
                'currency'      => $order->get_currency(),
                'artref'        => substr($product->get_sku(), 0, 44),
                'originalIdent' => self::FORCED_ORIGINAL_IDENT,
                'originCountry' => $this->getProductOriginCountry($product),
                'hsCode'        => $this->getProductHsCode($product),
            ];

            $itemWeight         = $customParams['items'][$itemId]['weight'] ?? $product->get_weight();
            $itemWeightWellUnit = wc_get_weight($itemWeight, 'kg');

            $customsArticle['weight'] = $itemWeightWellUnit < 0.01 ? '0.01' : (string) $itemWeightWellUnit;

            $customsArticles[] = $customsArticle;
        }

        $outwardCustomCategory = LpcHelper::get_option('lpc_customs_defaultCustomsCategory');
        if (isset($customParams['customsCategory'])) {
            $outwardCustomCategory = $customParams['customsCategory'];
        }

        $this->payload['fields']['field'][] = [
            'key'   => 'OUTPUT_PRINT_TYPE_CN23',
            'value' => LpcHelper::get_option('lpc_cn23_format', self::DEFAULT_FORMAT),
        ];

        $numberOfCopies = intval(LpcHelper::get_option('lpc_cn23_number', 4));

        $customsDeclarationPayload = [
            'includeCustomsDeclarations' => 1,
            'numberOfCopies'             => empty($numberOfCopies) ? 4 : $numberOfCopies,
            'contents'                   => [
                'article'  => $customsArticles,
                'category' => [
                    'value' => $this->isReturnLabel ? self::CUSTOMS_CATEGORY_RETURN_OF_ARTICLES : $outwardCustomCategory,
                ],
            ],
            'invoiceNumber'              => $order->get_order_number(),
        ];

        if (!empty($shippingMethodUsed) && in_array($shippingMethodUsed, [LpcSignDDP::ID, LpcExpertDDP::ID])) {
            $customsDeclarationPayload['description'] = substr(empty($customParams['description']) ? implode(' ', $articleDescriptions) : $customParams['description'], 0, 64);
        }

        if ('GB' === $destinationCountryId && !$this->isReturnLabel) {
            $vatNumber = LpcHelper::get_option('lpc_vat_number', 0);

            if (0 === $vatNumber) {
                LpcLogger::warn('No VAT number set in config');
            } else {
                $customsDeclarationPayload['comments'] = 'N. TVA : ' . $vatNumber;
            }
        }

        if ($this->getIsReturnLabel()) {
            $originalInvoiceDate = $order->get_date_created()
                                         ->date('Y-m-d');

            // For custom return labels this is not the "correct" original number since there is none, but this field is mandatory, so we add it
            $originalParcelNumber = $this->getOriginalParcelNumberFromInvoice($order);

            $customsDeclarationPayload['contents']['original'] =
                [
                    [
                        'originalIdent'         => self::FORCED_ORIGINAL_IDENT,
                        'originalInvoiceNumber' => $order->get_order_number(),
                        'originalInvoiceDate'   => $originalInvoiceDate,
                        'originalParcelNumber'  => $originalParcelNumber,
                    ],
                ];
        }

        /**
         * Filter on the customs declaration
         *
         * @since 1.6
         */
        $customsDeclarationPayload = apply_filters('lpc_payload_letter_customs_declarations', $customsDeclarationPayload, $this->getOrderNumber(), $this->getIsReturnLabel());

        $this->payload['letter']['customsDeclarations'] = $customsDeclarationPayload;

        $shippingCosts = $customParams['shippingCosts'] ?? $order->get_shipping_total();

        /**
         * Filter on the total shipping cost
         *
         * @since 1.6
         */
        $transportationAmount = apply_filters('lpc_payload_letter_service_total_amount', $shippingCosts, $this->getOrderNumber(), $this->getIsReturnLabel());
        if (empty($transportationAmount)) {
            // The Colissimo API rejects labels with a free shipping for the CN23
            throw new Exception(
                __(
                    'The shipping costs must not be free for the customs declaration to be valid, you can modify it manually by activating the Edit prices and weights option.',
                    'wc_colissimo'
                )
            );
        }

        // payload want centi-currency for these fields.
        $this->payload['letter']['service']['totalAmount']          = (int) ($transportationAmount * 100);
        $this->payload['letter']['service']['transportationAmount'] = (int) ($transportationAmount * 100);

        if ('GB' === $destinationCountryId) {
            $eoriNumber = LpcHelper::get_option('lpc_eori_uk_number');
            if ($totalItemsAmount >= 1000) {
                $eoriNumber .= ' ' . LpcHelper::get_option('lpc_eori_number');
            }
        } else {
            $eoriNumber = LpcHelper::get_option('lpc_eori_number');
        }

        /**
         * Filter on the eori number when generating a label
         *
         * @since 1.6
         */
        $eoriNumber = apply_filters('lpc_payload_eori_number', $eoriNumber, $this->getOrderNumber(), $this->getIsReturnLabel());

        $eoriFields = [
            'key'   => 'EORI',
            'value' => $eoriNumber,
        ];

        $this->payload['fields']['field'][] = $eoriFields;
        $this->eoriAdded                    = true;

        return $this;
    }

    /**
     * Retrieve product Origin Country
     *
     * @param $product
     *
     * @return string
     */
    protected function getProductOriginCountry($product) {
        $countryOfManufactureFieldName = LpcHelper::get_option('lpc_customs_countryOfManufactureFieldName');
        $countryOfManufacture          = $product->get_attribute($countryOfManufactureFieldName);

        if (!empty($countryOfManufacture)) {
            return $countryOfManufacture;
        }

        // If empty, we check is the parent product has the attribute (for variable product)
        $parentProduct = wc_get_product($product->get_parent_id());

        if (!empty($parentProduct)) {
            $countryOfManufacture = $parentProduct->get_attribute($countryOfManufactureFieldName);

            if (!empty($countryOfManufacture)) {
                return $countryOfManufacture;
            }
        }

        return LpcHelper::get_option('lpc_default_country_for_product', '');
    }

    /**
     * Retrieve product HS code
     *
     * @param $product
     *
     * @return array|string
     */
    protected function getProductHsCode($product) {
        $defaultHsCode   = LpcHelper::get_option('lpc_customs_defaultHsCode');
        $hsCodeFieldName = LpcHelper::get_option('lpc_customs_hsCodeFieldName');
        $hsCode          = $product->get_attribute($hsCodeFieldName);

        if (!empty($hsCode)) {
            return $hsCode;
        }

        // If empty, we check is the parent product has the attribute (for variable product)
        $parentProduct = wc_get_product($product->get_parent_id());

        if (!empty($parentProduct)) {
            $hsCode = $parentProduct->get_attribute($hsCodeFieldName);

            if (!empty($hsCode)) {
                return $hsCode;
            }
        }

        // Set default HS code if not defined on the product
        return $defaultHsCode;
    }

    public function isReturnLabel() {
        $this->isReturnLabel = true;

        return $this;
    }

    public function getIsReturnLabel() {
        return $this->isReturnLabel;
    }

    public function checkConsistency() {
        $this->checkPickupLocationId();
        $this->checkCommercialName();

        if (!$this->getIsReturnLabel()) {
            $this->checkSenderAddress();
            $this->checkAddresseeAddress();
        }

        return $this;
    }

    public function assemble() {
        return array_merge($this->payload); // makes a copy
    }

    /**
     * Retrieve payload without password for log
     *
     * @return array
     */
    public function getPayloadWithoutPassword() {
        $payloadWithoutPass = $this->payload;
        unset($payloadWithoutPass['password']);

        return $payloadWithoutPass;
    }

    protected function checkPickupLocationId() {
        if (self::PRODUCT_CODE_RELAY === $this->payload['letter']['service']['productCode']
            && (!isset($this->payload['letter']['parcel']['pickupLocationId'])
                || empty($this->payload['letter']['parcel']['pickupLocationId']))) {
            throw new Exception(
                __('The ProductCode used requires that a pickupLocationId is set!', 'wc_colissimo')
            );
        }

        if (self::PRODUCT_CODE_RELAY !== $this->payload['letter']['service']['productCode']
            && isset($this->payload['letter']['parcel']['pickupLocationId'])) {
            throw new Exception(
                __('The ProductCode used requires that a pickupLocationId is *not* set!', 'wc_colissimo')
            );
        }
    }

    protected function checkCommercialName() {
        if (self::PRODUCT_CODE_RELAY === $this->payload['letter']['service']['productCode']
            && (!isset($this->payload['letter']['service']['commercialName'])
                || empty($this->payload['letter']['service']['commercialName']))) {
            throw new Exception(
                __('You must specify the name of your store company in the Origin address!', 'wc_colissimo')
            );
        }
    }

    protected function checkSenderAddress() {
        $address = $this->payload['letter']['sender']['address'];

        if (empty($address['companyName'])) {
            throw new Exception(
                __('The name of your store company must be set in Origin address!', 'wc_colissimo')
            );
        }

        if (empty($address['line2'])) {
            throw new Exception(
                __('The address line 1 must be set in the Origin address!', 'wc_colissimo')
            );
        }

        if (empty($address['countryCode'])) {
            throw new Exception(
                __('The Country / State must be set in the Origin address!', 'wc_colissimo')
            );
        }

        if (empty($address['zipCode'])) {
            throw new Exception(
                __('The Postcode / ZIP must be set in the Origin address!', 'wc_colissimo')
            );
        }

        if (empty($address['city'])) {
            throw new Exception(
                __('The city must be set in the Origin address!', 'wc_colissimo')
            );
        }
    }

    /**
     * @throws Exception When the address format is refused.
     */
    protected function checkAddresseeAddress() {
        $address = $this->payload['letter']['addressee']['address'];

        if (empty($address['companyName'])
            && (empty($address['firstName']) || empty($address['lastName']))
        ) {
            throw new Exception(
                __('The name of the company or (firstname + lastname) must be set in the addressee\'s address!', 'wc_colissimo')
            );
        }

        if (empty($address['line2'])) {
            throw new Exception(
                __('The address line 1 must be set in the Addressee address!', 'wc_colissimo')
            );
        }

        if (empty($address['countryCode'])) {
            throw new Exception(
                __('The Country / State must be set in the Addressee address!', 'wc_colissimo')
            );
        }

        if (empty($address['zipCode'])) {
            throw new Exception(
                __('The Postcode / ZIP must be set in the Addressee address!', 'wc_colissimo')
            );
        }

        if (empty($address['city'])) {
            throw new Exception(
                __('The city must be set in the Addressee address!', 'wc_colissimo')
            );
        }

        if (self::PRODUCT_CODE_RELAY === $this->payload['letter']['service']['productCode']
            && empty($address['mobileNumber'])) {
            throw new Exception(
                __('The ProductCode used requires that a mobile number is set!', 'wc_colissimo')
            );
        }
    }

    public function getStoreAddress(): array {
        $optionsName = [
            'street'       => [
                'lpc'      => 'lpc_origin_address_line_1',
                'default'  => 'woocommerce_store_address',
                'required' => true,
            ],
            'street2'      => [
                'lpc'      => 'lpc_origin_address_line_2',
                'default'  => 'woocommerce_store_address_2',
                'required' => false,
            ],
            'countryCode'  => [
                'lpc'      => 'lpc_origin_address_country',
                'default'  => 'woocommerce_default_country',
                'required' => true,
            ],
            'city'         => [
                'lpc'      => 'lpc_origin_address_city',
                'default'  => 'woocommerce_store_city',
                'required' => true,
            ],
            'zipCode'      => [
                'lpc'      => 'lpc_origin_address_zipcode',
                'default'  => 'woocommerce_store_postcode',
                'required' => true,
            ],
            'email'        => [
                'lpc'      => 'lpc_origin_email',
                'default'  => '',
                'required' => false,
            ],
            'phoneNumber'  => [
                'lpc'      => 'lpc_origin_phone',
                'default'  => '',
                'required' => false,
            ],
            'mobileNumber' => [
                'lpc'      => 'lpc_origin_mobile',
                'default'  => '',
                'required' => false,
            ],
            'firstName'    => [
                'lpc'      => 'lpc_origin_firstname',
                'default'  => '',
                'required' => false,
            ],
            'lastName'     => [
                'lpc'      => 'lpc_origin_lastname',
                'default'  => '',
                'required' => false,
            ],
            'companyName'  => [
                'lpc'      => 'lpc_origin_company_name',
                'default'  => '',
                'required' => false,
            ],
        ];

        return $this->getAddress($optionsName);
    }

    public function getReturnAddress(): array {
        $optionsName = [
            'street'       => [
                'lpc'      => 'lpc_return_address_line_1',
                'default'  => 'lpc_origin_address_line_1',
                'required' => true,
            ],
            'street2'      => [
                'lpc'      => 'lpc_return_address_line_2',
                'default'  => 'lpc_origin_address_line_2',
                'required' => false,
            ],
            'countryCode'  => [
                'lpc'      => 'lpc_return_address_country',
                'default'  => 'lpc_origin_address_country',
                'required' => true,
            ],
            'city'         => [
                'lpc'      => 'lpc_return_address_city',
                'default'  => 'lpc_origin_address_city',
                'required' => true,
            ],
            'zipCode'      => [
                'lpc'      => 'lpc_return_address_zipcode',
                'default'  => 'lpc_origin_address_zipcode',
                'required' => true,
            ],
            'email'        => [
                'lpc'      => 'lpc_return_email',
                'default'  => 'lpc_origin_email',
                'required' => false,
            ],
            'phoneNumber'  => [
                'lpc'      => 'lpc_return_phone',
                'default'  => 'lpc_origin_phone',
                'required' => false,
            ],
            'mobileNumber' => [
                'lpc'      => 'lpc_return_mobile',
                'default'  => 'lpc_origin_mobile',
                'required' => false,
            ],
            'firstName'    => [
                'lpc'      => 'lpc_return_firstname',
                'default'  => 'lpc_origin_firstname',
                'required' => false,
            ],
            'lastName'     => [
                'lpc'      => 'lpc_return_lastname',
                'default'  => 'lpc_origin_lastname',
                'required' => false,
            ],
            'companyName'  => [
                'lpc'      => 'lpc_return_company_name',
                'default'  => 'lpc_origin_company_name',
                'required' => false,
            ],
        ];

        return $this->getAddress($optionsName);
    }

    private function getAddress($optionsName): array {
        $invalidAddress = false;
        $return         = [];

        foreach ($optionsName as $key => $optionName) {
            $option = LpcHelper::get_option($optionName['lpc']);

            if ($optionName['required'] && empty($option)) {
                $invalidAddress = true;
                break;
            }

            $return[$key] = $option;
        }

        if (!$invalidAddress) {
            return $return;
        }

        $return = [];

        foreach ($optionsName as $key => $optionName) {
            if ('countryCode' == $key) {
                // woocommerce_default_country may be the sole country code or the format 'US:IL' (i.e. with the state / province)
                $countryWithState = explode(':', WC_Admin_Settings::get_option('woocommerce_default_country'));
                $option           = reset($countryWithState);
            } else {
                if (empty($optionName['default'])) {
                    $option = LpcHelper::get_option($optionName['lpc']);
                } else {
                    if (0 === strpos($optionName['default'], 'lpc_')) {
                        $option = LpcHelper::get_option($optionName['default']);
                    } else {
                        $option = WC_Admin_Settings::get_option($optionName['default']);
                    }
                }
            }

            $return[$key] = $option;
        }

        return $return;
    }

    protected function getOriginalParcelNumberFromInvoice(WC_Order $order) {
        return $order->get_meta(LpcLabelGenerationOutward::OUTWARD_PARCEL_NUMBER_META_KEY);
    }

    public function getLabelFormat() {
        foreach (self::LABEL_FORMATS as $oneFormat) {
            if (false !== strpos($this->payload['outputFormat']['outputPrintingType'], $oneFormat)) {
                return $oneFormat;
            }
        }

        return '';
    }

    public function isInsured() {
        return isset($this->payload['letter']['parcel']['insuranceValue']);
    }

    public function getOrderNumber() {
        return $this->orderNumber;
    }

    /**
     * @param $productCode
     *
     * @return false|int
     */
    protected function getMaxInsuranceAmountByProductCode($productCode) {
        if (!in_array($productCode, self::PRODUCT_CODE_INSURANCE_AVAILABLE)) {
            return false;
        }

        return self::PRODUCT_CODE_RELAY === $productCode ? self::MAX_INSURANCE_AMOUNT_RELAY : self::MAX_INSURANCE_AMOUNT;
    }

    public function withPostalNetwork($countryCode, $order) {
        if (in_array($countryCode, ['AT', 'DE', 'IT', 'LU']) && self::PRODUCT_CODE_WITH_SIGNATURE === $this->payload['letter']['service']['productCode']) {
            $shippingMethod = $this->lpcShippingMethods->getColissimoShippingMethodOfOrder($order);

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

            $network = LpcHelper::get_option($countries[$countryCode]);

            $customSendingService = isset($_REQUEST['lpc__admin__order_banner__generate_label__sending_service']) ? sanitize_text_field(wp_unslash($_REQUEST['lpc__admin__order_banner__generate_label__sending_service'])) : $network;

            $this->payload['letter']['service']['reseauPostal'] = 'dpd' === $customSendingService ? 0 : 1;
        }

        return $this;
    }

    /**
     * @throws Exception When the number of parcels is incorrect or if all labels have been generated.
     */
    public function withMultiParcels($orderId, $customParams): LpcLabelGenerationPayload {
        if (empty($customParams['multiParcels'])) {
            return $this;
        }

        if (empty($customParams['multiParcelsAmount']) || $customParams['multiParcelsAmount'] < 2) {
            throw new Exception(__('Incorrect number of parcels', 'wc_colissimo'));
        }

        if ($customParams['multiParcelsCurrentNumber'] > $customParams['multiParcelsAmount']) {
            throw new Exception(__('All labels have already been generated. To generate a new label, please uncheck the multi-parcels shipping.', 'wc_colissimo'));
        }

        if ($customParams['multiParcelsCurrentNumber'] < $customParams['multiParcelsAmount']) {
            // Follower parcels first
            $this->payload['fields']['field'][] = [
                'key'   => 'TYPE_MULTI_PARCEL',
                'value' => 'FOLLOWER',
            ];
        } else {
            // Master parcel last
            $this->payload['fields']['field'][] = [
                'key'   => 'TYPE_MULTI_PARCEL',
                'value' => 'MASTER',
            ];

            $followerLabels                     = $this->outwardLabelDb->getMultiParcelsLabels($orderId);
            $this->payload['fields']['field'][] = [
                'key'   => 'LIST_FOLLOWER_PARCEL',
                'value' => implode('/', array_keys($followerLabels)),
            ];
        }

        $this->payload['fields']['field'][] = [
            'key'   => 'PARCEL_ITERATION_NUMBER',
            'value' => $customParams['multiParcelsCurrentNumber'],
        ];

        $this->payload['fields']['field'][] = [
            'key'   => 'TOTAL_NUMBER_PARCEL',
            'value' => $customParams['multiParcelsAmount'],
        ];

        return $this;
    }

    public function withBlockingCode($shippingMethodUsed, $order, $customParams) {
        if (!empty($customParams['blockCode'])) {
            if ('disabled' === $customParams['blockCode']) {
                $this->payload['letter']['parcel']['disabledDeliveryBlockingCode'] = '1';
            }
        } elseif (in_array($shippingMethodUsed, [LpcSign::ID, LpcSignDDP::ID])) {
            $accountInformation = $this->accountApi->getAccountInformation();
            if (!empty($accountInformation['statutCodeBloquant'])) {
                $minimumOrderValue = LpcHelper::get_option('lpc_domicileas_block_code_min');
                $maximumOrderValue = LpcHelper::get_option('lpc_domicileas_block_code_max');

                if (!empty($minimumOrderValue) || !empty($maximumOrderValue)) {
                    $orderValue = 0;
                    foreach ($order->get_items() as $item) {
                        $product = $item->get_product();
                        if (empty($product)) {
                            throw new Exception(
                                __('The product couldn\'t be found.', 'wc_colissimo')
                            );
                        }

                        if (!$product->needs_shipping()) {
                            continue;
                        }

                        $quantity = $item->get_quantity();

                        if (!empty(wc_get_order_item_meta($item->get_id(), '_line_total'))) {
                            $unitaryValue = wc_get_order_item_meta($item->get_id(), '_line_total');
                            if (!empty(wc_get_order_item_meta($item->get_id(), '_qty'))) {
                                $unitaryValue /= wc_get_order_item_meta($item->get_id(), '_qty');
                            }
                        } else {
                            $productPrice = wc_get_price_excluding_tax($product);
                            $unitaryValue = $productPrice;
                        }

                        if (empty($unitaryValue)) {
                            $unitaryValue = 0;
                        }

                        $orderValue += $unitaryValue * $quantity;
                    }

                    if (!empty($minimumOrderValue) && $orderValue < $minimumOrderValue) {
                        $this->payload['letter']['parcel']['disabledDeliveryBlockingCode'] = '1';
                    } elseif (!empty($maximumOrderValue) && $orderValue > $maximumOrderValue) {
                        $this->payload['letter']['parcel']['disabledDeliveryBlockingCode'] = '1';
                    }
                }
            }
        }

        return $this;
    }

    private function formatPhone(string $phoneNumber): string {
        return str_replace(' ', '', $phoneNumber);
    }
}
