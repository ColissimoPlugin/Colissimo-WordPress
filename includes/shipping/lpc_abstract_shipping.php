<?php

require_once LPC_INCLUDES . 'shipping' . DS . 'lpc_capabilities_per_country.php';

abstract class LpcAbstractShipping extends WC_Shipping_Method {
    const LPC_ALL_SHIPPING_CLASS_CODE = 'all';
    const LPC_LAPOSTE_TRACKING_LINK = 'https://www.laposte.fr/outils/suivre-vos-envois?code={lpc_tracking_number}';
    const CUSTOMS_CATEGORY_COMMERCIAL = 3;

    protected $lpcCapabilitiesPerCountry;
    /**
     * @var mixed
     */

    /**
     * LpcAbstractShipping constructor.
     *
     * @param int $instance_id
     */
    public function __construct($instance_id = 0) {
        $this->instance_id = absint($instance_id);
        $this->supports    = [
            'shipping-zones',
            'instance-settings',
        ];

        $this->lpcCapabilitiesPerCountry = new LpcCapabilitiesPerCountry();
        $this->init();
    }

    /**
     * This method is used to initialize the configuration fields' values
     */
    public function init() {
        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
    }

    /**
     * This method allows you to define configuration fields shown in the shipping methdod's configuration page
     */
    public function init_form_fields() {
        $this->instance_form_fields = [
            'title'                                        => [
                'title'       => __('Title', 'wc_colissimo'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'wc_colissimo'),
                'default'     => $this->method_title,
                'desc_tip'    => true,
            ],
            'always_free'                                  => [
                'title'       => __('Always free?', 'wc_colissimo'),
                'type'        => 'checkbox',
                'description' => __(
                    'If enabled, rates calculation for this shipping method will always be zero.',
                    'wc_colissimo'
                ),
                'default'     => $this->get_option('always_free', 'no'),
                'desc_tip'    => true,
                'label'       => ' ',
            ],
            'title_free'                                   => [
                'title'       => __('Title if free', 'wc_colissimo'),
                'type'        => 'text',
                'description' => __(
                    'This controls the title which the user sees during checkout if the shipping methods is free. Leave empty to always use standard title.',
                    'wc_colissimo'
                ),
                'default'     => $this->get_option('title_free', ''),
                'desc_tip'    => true,
            ],
            'excluded_classes'                             => [
                'type' => 'classes_shipping',
            ],
            'classes_free_shipping'                        => [
                'type' => 'classes_shipping',
            ],
            'free_for_items_without_free_shipping_classes' => [
                'title'       => __('Free if at least one item in the cart has one of the free shipping classes above', 'wc_colissimo'),
                'type'        => 'checkbox',
                'description' => __(
                    'If enabled, delivery will be free, even if the other items in the cart do not have one of the free shipping classes above',
                    'wc_colissimo'
                ),
                'default'     => $this->get_option('free_for_items_without_free_shipping_classes', 'yes'),
                'desc_tip'    => true,
                'label'       => ' ',
            ],
            'shipping_rates'                               => [
                'type' => 'shipping_rates',
            ],
            'shipping_discount'                            => [
                'type' => 'shipping_discount',
            ],
        ];
    }

    public function generate_shipping_discount_html() {
        return LpcHelper::renderPartial(
            'shipping' . DS . 'shipping_discount_table.php',
            [
                'shippingMethod' => $this,
            ]
        );
    }

    public function generate_shipping_rates_html() {
        $shipping        = new \WC_Shipping();
        $shippingClasses = $shipping->get_shipping_classes();

        $shippingRates = LpcRegister::get('shippingRates');

        return LpcHelper::renderPartial(
            'shipping' . DS . 'shipping_rates_table.php',
            [
                'shippingMethod'  => $this,
                'shippingClasses' => $shippingClasses,
                'exportUrl'       => $shippingRates->getUrlExport($this->instance_id),
                'importUrl'       => $shippingRates->getUrlImport($this->instance_id),
            ]
        );
    }

    public function generate_classes_shipping_html($key) {
        $shipping        = new \WC_Shipping();
        $shippingClasses = $shipping->get_shipping_classes();
        $args            = [];

        if ('classes_free_shipping' === $key) {
            $args['values']          = $shippingClasses;
            $args['id_and_name']     = 'classes_free_shipping[]';
            $args['label']           = __('Free shipping classes', 'wc_colissimo');
            $args['selected_values'] = $this->get_option('classes_free_shipping', []);
            $args['multiple']        = true;
            $args['description']     = __('These shipping classes qualify for free shipping', 'wc_colissimo');
        } else {
            $args['values']          = $shippingClasses;
            $args['id_and_name']     = 'excluded_classes[]';
            $args['label']           = __('Excluded shipping classes', 'wc_colissimo');
            $args['selected_values'] = $this->get_option('excluded_classes', []);
            $args['multiple']        = true;
            $args['description']     = __(
                'The current shipping method will not be displayed if one product in the cart has one of these shipping classes. This option takes precedence over the option Free shipping classes',
                'wc_colissimo'
            );
        }

        return LpcHelper::renderPartial('shipping' . DS . 'shipping_classes_select_field.php', $args);
    }

    public function validate_shipping_discount_field($key) {
        $result   = [];
        $postData = $this->get_post_data();
        if (empty($postData[$key])) {
            return $result;
        }

        return $postData[$key];
    }

    public function validate_classes_shipping_field($key) {
        $result   = [];
        $postData = $this->get_post_data();
        if (empty($postData[$key])) {
            return $result;
        }

        return $postData[$key];
    }

    public function validate_shipping_rates_field($key) {
        $result   = [];
        $postData = $this->get_post_data();
        if (empty($postData[$key])) {
            return $result;
        }
        foreach ($postData[$key] as $rate) {
            $minWeight = (float) str_replace(',', '.', $rate['min_weight']);
            $maxWeight = (float) str_replace(',', '.', $rate['max_weight']);
            $minPrice  = (float) str_replace(',', '.', $rate['min_price']);
            $maxPrice  = (float) str_replace(',', '.', $rate['max_price']);

            $minWeight = max($minWeight, 0);
            $maxWeight = max($maxWeight, 0);
            $minPrice  = max($minPrice, 0);
            $maxPrice  = max($maxPrice, 0);

            $item = [
                'min_weight'     => $minWeight,
                'max_weight'     => empty($maxWeight) ? '' : $maxWeight,
                'min_price'      => $minPrice,
                'max_price'      => empty($maxPrice) ? '' : $maxPrice,
                'shipping_class' => $rate['shipping_class'],
                'price'          => (float) str_replace(',', '.', $rate['price']),
            ];

            $result[] = $item;
        }

        usort(
            $result,
            function ($a, $b) {
                $result = 0;

                if ($a['price'] > $b['price']) {
                    $result = 1;
                } else {
                    if ($a['price'] < $b['price']) {
                        $result = - 1;
                    }
                }

                return $result;
            }
        );

        return $result;
    }

    public function getRates() {
        return $this->get_option('shipping_rates', []);
    }

    public function getDiscounts() {
        return $this->get_option('shipping_discount', []);
    }

    public function getFreeShippingClasses() {
        return $this->get_option('classes_free_shipping', []);
    }

    public function getMaximumWeight() {
        return $this->get_option('max_weight', null);
    }

    public function getUseCartPrice() {
        return $this->get_option('use_cart_price', 'no');
    }

    public function getFreeForItemsWithoutShippingClasses() {
        return $this->get_option('free_for_items_without_free_shipping_classes', 'no');
    }

    abstract public function freeFromOrderValue();

    public function calculate_shipping($package = []) {
        $cost = null;

        if (!$this->lpcCapabilitiesPerCountry->getInfoForDestination($package['destination']['country'], $this->id)) {
            return;
        }

        $totalWeight         = LpcHelper::get_option('lpc_packaging_weight', 0);
        $cartShippingClasses = [];
        $rates               = $this->getRates();

        array_walk(
            $rates,
            function (&$rate) {
                if (isset($rate['shipping_class']) && !is_array($rate['shipping_class'])) {
                    $rate['shipping_class'] = [$rate['shipping_class']];
                }

                if (empty($rate['shipping_class'])) {
                    $rate['shipping_class'] = [];
                }
            }
        );

        $lineTotal       = 0;
        $lineTax         = 0;
        $lineSubTotal    = 0;
        $lineSubTax      = 0;
        $articleQuantity = 0;
        $discountToApply = 0;

        foreach ($package['contents'] as $item) {
            $articleQuantity       += $item['quantity'];
            $product               = $item['data'];
            $totalWeight           += (float) $product->get_weight() * $item['quantity'];
            $cartShippingClasses[] = $product->get_shipping_class_id();

            $lineTotal    = $lineTotal + $item['line_total'];
            $lineTax      = $lineTax + $item['line_tax'];
            $lineSubTotal = $lineSubTotal + $item['line_subtotal'];
            $lineSubTax   = $lineSubTax + $item['line_subtotal_tax'];
        }

        // Check if there is an available discount
        $discounts = $this->getDiscounts();
        foreach ($discounts as $discount) {
            if ($discount['nb_product'] <= $articleQuantity && $discountToApply < $discount['percentage']) {
                $discountToApply = $discount['percentage'];
            }
        }
        $discountToApply = floatval($discountToApply);

        $excludedClasses = $this->get_option('excluded_classes', []);
        if (!empty(array_intersect($excludedClasses, $cartShippingClasses))) {
            return;
        }

        // For the future, if we want to calculate price on HorsTaxes checkout, we only need to calculate on $lineTotal
        $totalPrice              = round($lineTax + $lineTotal, 2);
        $totalWithoutCouponPrice = round($lineSubTax + $lineSubTotal, 2);

        $totalPrice = 'yes' === LpcHelper::get_option('lpc_calculate_shipping_before_coupon', 'no') ? $totalWithoutCouponPrice : $totalPrice;

        // DDP for GB must be commercial and between 160€ and 1050€
        $isCommercialSend = self::CUSTOMS_CATEGORY_COMMERCIAL == LpcHelper::get_option('lpc_customs_defaultCustomsCategory');
        if (LpcSignDDP::ID === $this->id && ($totalPrice < 160 || $totalPrice > 1050 || !$isCommercialSend)) {
            return;
        }

        // Remove duplicate shipping classes
        $cartShippingClasses = array_unique($cartShippingClasses);
        /**
         * Filter on the package's total weight, before the checkout calculation
         *
         * @since 1.6.7
         */
        $totalWeight = (float) apply_filters('lpc_payload_letter_parcel_weight_checkout', $totalWeight, $package);

        // For configuration of version 1.1 or lower
        if (isset($rates[0]['weight'])) {
            // Should we compare to cart weight or cart price
            if ('yes' === $this->getUseCartPrice()) {
                $totalValue = $totalPrice;
            } else {
                $totalValue = $totalWeight;
            }

            // Maximum weight or price depending on option value
            $maximumWeight = $this->getMaximumWeight();
            if ($maximumWeight && $totalValue > $maximumWeight) {
                return; // no rates
            }
        }

        $coupons              = $package['applied_coupons'];
        $isCouponFreeShipping = false;

        // Coupon : first check if a coupon should exclude the method
        foreach ($coupons as $oneCouponCode) {
            $coupon            = new WC_Coupon($oneCouponCode);
            $couponRestriction = $coupon->get_meta('lpc_coupon_restriction');
            if (!empty($couponRestriction) && in_array($this->id, $couponRestriction)) {
                return;
            }
        }

        // Coupon : secondly check if a coupon is a free shipping one
        foreach ($coupons as $oneCouponCode) {
            $coupon = new WC_Coupon($oneCouponCode);
            if ($coupon->get_free_shipping()) {
                $isCouponFreeShipping = true;
                break;
            }
        }

        $classesFreeShipping     = $this->getFreeShippingClasses();
        $isClassesFreeShipping   = !empty(array_intersect($classesFreeShipping, $cartShippingClasses));
        $isMethodFreeForAllItems = $this->getFreeForItemsWithoutShippingClasses();
        $areOtherPayingClasses   = !empty(array_diff($cartShippingClasses, $classesFreeShipping));

        if (
            'yes' === $this->get_option('always_free')
            || ($this->freeFromOrderValue() > 0 && $totalPrice >= $this->freeFromOrderValue())
            || $isCouponFreeShipping
            || $isClassesFreeShipping && (!$areOtherPayingClasses || 'yes' === $isMethodFreeForAllItems)
        ) {
            $cost = 0.0;
        } else {
            // For configuration of version 1.1 or lower
            if (isset($rates[0]['weight'])) {
                usort(
                    $rates,
                    function ($a, $b) {
                        if ($a['weight'] == $b['weight']) {
                            return 0;
                        }

                        return ($a['weight'] < $b['weight']) ? - 1 : 1;
                    }
                );

                foreach ($rates as $rate) {
                    if ($rate['weight'] <= $totalValue) {
                        $cost = $rate['price'];
                    }
                }
            } else {
                $matchingRates = [];

                // Step 1 : retrieve all matching line rate with price, weight and shipping classes
                foreach ($rates as $oneRate) {
                    $arrayIntersection = array_intersect($cartShippingClasses, $oneRate['shipping_class']);
                    sort($arrayIntersection);
                    sort($cartShippingClasses);
                    $classMatches = $arrayIntersection == $cartShippingClasses;

                    if (
                        $totalWeight >= $oneRate['min_weight']
                        && (empty($oneRate['max_weight']) || $totalWeight < $oneRate['max_weight'])
                        && $totalPrice >= $oneRate['min_price']
                        && (empty($oneRate['max_price']) || $totalPrice < $oneRate['max_price'])
                        && (
                            $classMatches
                            || in_array(self::LPC_ALL_SHIPPING_CLASS_CODE, $oneRate['shipping_class'])
                        )
                    ) {
                        $matchingRates[] = $oneRate;
                    }
                }

                $matchingShippingClassesRates = [];

                // Step 2 : Match each shipping classes with corresponding line rates
                foreach ($cartShippingClasses as $oneCartShippingClassId) {

                    // Step 2.1 : First check if a line rates is corresponding with a shipping class defined
                    if (!empty($oneCartShippingClassId)) {
                        $matchingShippingClassesRates[$oneCartShippingClassId] = array_filter(
                            $matchingRates,
                            function ($rate) use ($oneCartShippingClassId) {
                                return in_array($oneCartShippingClassId, $rate['shipping_class']);
                            }
                        );
                    }

                    // Step 2.2 : If no line rates corresponding with shipping rates or if no shipping class is set, check the line rates for all shipping classes
                    if (empty($matchingShippingClassesRates[$oneCartShippingClassId]) || '0' == $oneCartShippingClassId) {
                        $matchingShippingClassesRates[$oneCartShippingClassId] = array_filter(
                            $matchingRates,
                            function ($rate) use ($oneCartShippingClassId) {
                                return in_array(self::LPC_ALL_SHIPPING_CLASS_CODE, $rate['shipping_class']);
                            }
                        );
                    }
                }

                $shippingClassPrices = [];

                // Step 3 : For each shipping class of the cart, take the cheapest line rate
                foreach ($matchingShippingClassesRates as $shippingClassId => $oneShippingMethodRate) {
                    foreach ($oneShippingMethodRate as $oneRate) {
                        if (!isset($shippingClassPrices[$shippingClassId])) {
                            $shippingClassPrices[$shippingClassId] = $oneRate['price'];
                        } elseif ($shippingClassPrices[$shippingClassId] > $oneRate['price']) {
                            $shippingClassPrices[$shippingClassId] = $oneRate['price'];
                        }
                    }
                }

                // Step 4 : Take the more expensive shipping class
                foreach ($shippingClassPrices as $onePrice) {
                    if (null === $cost || $onePrice > $cost) {
                        $cost = $onePrice;
                    }
                }
            }
        }

        if (null !== $cost) {
            // For DDP shipping methods, apply the extra cost if any, even when free shipping is active
            if (false !== strpos($this->id, '_ddp')) {
                $countryCode = strtolower($package['destination']['country']);
                $extraCost   = LpcHelper::get_option('lpc_extracost_' . $countryCode, 0);
                if (!empty($extraCost)) {
                    $cost += $extraCost;
                }
            }

            $titleFree = $this->get_option('title_free', '');
            $label     = 0 == $cost && !empty($titleFree) ? $titleFree : $this->title;

            $translatedLabel = __($label, 'wc_colissimo');

            // Apply discount on shipping if there is one
            if (0 !== $discountToApply) {
                $cost = $cost * (1 - $discountToApply * 0.01);
            }

            $rate = [
                'id'    => $this->get_rate_id(),
                'label' => $translatedLabel,
                'cost'  => $cost,
            ];

            $this->add_rate($rate);
        }
    }
}
