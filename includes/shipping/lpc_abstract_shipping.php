<?php

require_once LPC_INCLUDES . 'shipping' . DS . 'lpc_capabilities_per_country.php';

abstract class LpcAbstractShipping extends WC_Shipping_Method {
    const LPC_ALL_PRODUCT_CATEGORIES_CODE = 'all';
    const LPC_ALL_SHIPPING_CLASS_CODE = 'all';
    const LPC_NO_SHIPPING_CLASS_CODE = 'none';
    const LPC_LAPOSTE_TRACKING_LINK = 'https://www.laposte.fr/outils/suivre-vos-envois?code={lpc_tracking_number}';
    const CUSTOMS_CATEGORY_COMMERCIAL = 3;

    protected $lpcCapabilitiesPerCountry;

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

        $this->title      = $this->get_option('title');
        $this->tax_status = $this->get_option('tax_status');
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
            'tax_status'                                   => [
                'title'       => __('Tax status', 'woocommerce'),
                'description' => __('If a cost is defined, this controls if taxes are applied to that cost.', 'woocommerce'),
                'desc_tip'    => true,
                'type'        => 'select',
                'options'     => [
                    'taxable' => __('Taxable', 'woocommerce'),
                    'none'    => _x('None', 'Tax status', 'woocommerce'),
                ],
                'default'     => $this->get_option('tax_status', 'taxable'),
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
        $shipping = new \WC_Shipping();
        global $sitepress;
        if (!empty($sitepress)) {
            $removed = remove_filter('terms_clauses', [$sitepress, 'terms_clauses']);
        }
        $shippingClasses = $shipping->get_shipping_classes();
        array_unshift(
            $shippingClasses,
            (object) [
                'term_id' => self::LPC_NO_SHIPPING_CLASS_CODE,
                'name'    => __('No shipping class', 'wc_colissimo'),
            ]
        );
        if (!empty($sitepress) && $removed) {
            add_filter('terms_clauses', [$sitepress, 'terms_clauses'], 10, 3);
        }

        $shippingRates = LpcRegister::get('shippingRates');

        return LpcHelper::renderPartial(
            'shipping' . DS . 'shipping_rates_table.php',
            [
                'shippingMethod'   => $this,
                'shippingClasses'  => $shippingClasses,
                'exportUrl'        => $shippingRates->getUrlExport($this->instance_id),
                'importUrl'        => $shippingRates->getUrlImport($this->instance_id),
                'importDefaultUrl' => $shippingRates->getUrlDefaultPrices($this->instance_id),
            ]
        );
    }

    public function generate_classes_shipping_html($key) {
        $shipping = new \WC_Shipping();
        global $sitepress;
        if (!empty($sitepress)) {
            $removed = remove_filter('terms_clauses', [$sitepress, 'terms_clauses']);
        }
        $shippingClasses = $shipping->get_shipping_classes();
        array_unshift(
            $shippingClasses,
            (object) [
                'term_id' => self::LPC_NO_SHIPPING_CLASS_CODE,
                'name'    => __('No shipping class', 'wc_colissimo'),
            ]
        );
        if (!empty($sitepress) && $removed) {
            add_filter('terms_clauses', [$sitepress, 'terms_clauses'], 10, 3);
        }
        $args = [];

        $args['values']   = $shippingClasses;
        $args['multiple'] = true;
        if ('classes_free_shipping' === $key) {
            $args['id_and_name']     = 'classes_free_shipping[]';
            $args['label']           = __('Free shipping classes', 'wc_colissimo');
            $args['selected_values'] = $this->get_option('classes_free_shipping', []);
            $args['description']     = __('These shipping classes qualify for free shipping', 'wc_colissimo');
        } else {
            $args['id_and_name']     = 'excluded_classes[]';
            $args['label']           = __('Excluded shipping classes', 'wc_colissimo');
            $args['selected_values'] = $this->get_option('excluded_classes', []);
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

    /**
     * Called by WooCommerce when saving shipping method settings
     */
    public function validate_shipping_rates_field(string $key): array {
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
                'min_weight'       => $minWeight,
                'max_weight'       => empty($maxWeight) ? '' : $maxWeight,
                'min_price'        => $minPrice,
                'max_price'        => empty($maxPrice) ? '' : $maxPrice,
                'shipping_class'   => $rate['shipping_class'],
                'product_category' => $rate['product_category'],
                'price'            => (float) str_replace(',', '.', $rate['price']),
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

    public function getFreeForItemsWithoutFreeShippingClasses() {
        return $this->get_option('free_for_items_without_free_shipping_classes', 'no');
    }

    abstract public function freeFromOrderValue();

    public function calculate_shipping($package = []) {
        $cost = null;

        if (!$this->checkPickupAvailability()) {
            return;
        }

        if (!$this->lpcCapabilitiesPerCountry->getInfoForDestination($package['destination']['country'], $this->id)) {
            return;
        }

        $rates = $this->getRates();
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

        $lineTotal             = 0;
        $lineTax               = 0;
        $lineSubTotal          = 0;
        $lineSubTax            = 0;
        $articleQuantity       = 0;
        $nbProductsToShip      = 0;
        $discountToApply       = 0;
        $totalWeight           = 0;
        $productsDimensions    = [];
        $cartShippingClasses   = [];
        $cartProductCategories = [];

        $noshipProductsCount = LpcHelper::get_option('lpc_calculate_shipping_with_noship_products', 'no') === 'yes';
        $cartContents        = WC()->cart->cart_contents;

        $yithBundlesHandled = [];
        foreach ($package['contents'] as $item) {
            if (empty($item['data'])) {
                continue;
            }

            $product         = $item['data'];
            $articleQuantity += $item['quantity'];

            // Handle YITH Product Bundles for modified prices
            if (!empty($item['bundled_by'])) {
                // Handle when the bundle is removed, and count the price only once
                if (!isset($cartContents[$item['bundled_by']]) || in_array($item['bundled_by'], $yithBundlesHandled)) {
                    continue;
                }

                $yithBundlesHandled[] = $item['bundled_by'];

                $currentProductLineTotal    = $cartContents[$item['bundled_by']]['line_total'];
                $currentProductLineTax      = $cartContents[$item['bundled_by']]['line_tax'];
                $currentProductLineSubTotal = $cartContents[$item['bundled_by']]['line_subtotal'];
                $currentProductLineSubTax   = $cartContents[$item['bundled_by']]['line_subtotal_tax'];

                $bundle                   = $cartContents[$item['bundled_by']]['data'];
                $currentProductWeight     = (float) $bundle->get_weight() * $cartContents[$item['bundled_by']]['quantity'];
                $currentProductDimensions = [
                    $bundle->get_length(),
                    $bundle->get_width(),
                    $bundle->get_height(),
                ];
                $currentProductClassId    = $bundle->get_shipping_class_id();
                $currentProductCategories = $bundle->get_category_ids('edit');
            } else {
                $currentProductLineTotal    = $item['line_total'];
                $currentProductLineTax      = $item['line_tax'];
                $currentProductLineSubTotal = $item['line_subtotal'];
                $currentProductLineSubTax   = $item['line_subtotal_tax'];

                $currentProductWeight     = (float) $product->get_weight() * $item['quantity'];
                $currentProductDimensions = [
                    $product->get_length(),
                    $product->get_width(),
                    $product->get_height(),
                ];
                $currentProductClassId    = $product->get_shipping_class_id();

                if ('variation' === $product->get_type()) {
                    $currentProductCategories = wc_get_product_term_ids($product->get_parent_id(), 'product_cat');
                } else {
                    $currentProductCategories = $product->get_category_ids('edit');
                }
            }

            if ($noshipProductsCount) {
                $lineTotal    = $lineTotal + $currentProductLineTotal;
                $lineTax      = $lineTax + $currentProductLineTax;
                $lineSubTotal = $lineSubTotal + $currentProductLineSubTotal;
                $lineSubTax   = $lineSubTax + $currentProductLineSubTax;
            }

            if (is_callable([$product, 'needs_shipping']) && !$product->needs_shipping()) {
                continue;
            }

            if (!$noshipProductsCount) {
                $lineTotal    = $lineTotal + $currentProductLineTotal;
                $lineTax      = $lineTax + $currentProductLineTax;
                $lineSubTotal = $lineSubTotal + $currentProductLineSubTotal;
                $lineSubTax   = $lineSubTax + $currentProductLineSubTax;
            }

            $productsDimensions[] = $currentProductDimensions;

            $nbProductsToShip      += (float) $item['quantity'];
            $totalWeight           += $currentProductWeight;
            $shippingClassId       = $currentProductClassId;
            $cartShippingClasses[] = empty($shippingClassId) ? self::LPC_NO_SHIPPING_CLASS_CODE : $shippingClassId;

            if (!empty($currentProductCategories)) {
                $cartProductCategories[] = $currentProductCategories;
            }
        }

        $packagingMatchingCart = LpcHelper::getMatchingPackaging($nbProductsToShip, $totalWeight, $productsDimensions);

        if (empty($packagingMatchingCart)) {
            $totalWeight += LpcHelper::get_option('lpc_packaging_weight', 0);
        } else {
            $totalWeight += $packagingMatchingCart['weight'];
        }

        $cartShippingClasses = array_unique($cartShippingClasses);

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

        if (LpcHelper::get_option('lpc_calculate_shipping_before_taxes', 'no') === 'yes') {
            $totalPrice              = round($lineTotal, 2);
            $totalWithoutCouponPrice = round($lineSubTotal, 2);
        } else {
            $totalPrice              = round($lineTax + $lineTotal, 2);
            $totalWithoutCouponPrice = round($lineSubTax + $lineSubTotal, 2);
        }

        $totalPrice = 'yes' === LpcHelper::get_option('lpc_calculate_shipping_before_coupon', 'no') ? $totalWithoutCouponPrice : $totalPrice;

        // DDP for GB must be commercial and between 160€ and 1050€
        $isCommercialSend = self::CUSTOMS_CATEGORY_COMMERCIAL === LpcHelper::get_option('lpc_customs_defaultCustomsCategory');
        if ('GB' === $package['destination']['country'] && LpcSignDDP::ID === $this->id && ($totalPrice < 160 || $totalPrice > 1050 || !$isCommercialSend)) {
            return;
        }

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

        // For configuration of version 1.1 or lower
        if (isset($rates[0]['weight'])) {
            usort(
                $rates,
                fn($a, $b) => $a['weight'] <=> $b['weight']
            );

            foreach ($rates as $rate) {
                if ($rate['weight'] <= $totalValue) {
                    $cost = $rate['price'];
                }
            }
        } else {
            $rateToChoose = LpcHelper::get_option('lpc_choose_min_max_rate', 'lowest');

            // Step 1 : retrieve all matching line rate with price, weight and shipping classes
            foreach ($rates as $oneRate) {
                // All cart shipping classes must be specified in the rate
                $missingClasses = array_diff($cartShippingClasses, $oneRate['shipping_class']);
                if (!empty($missingClasses) && !in_array(self::LPC_ALL_SHIPPING_CLASS_CODE, $oneRate['shipping_class'])) {
                    continue;
                }

                // All cart products must have at least 1 of their categories specified in the rate (product_category might not exist on older rates)
                if (!empty($oneRate['product_category'])) {
                    foreach ($cartProductCategories as $oneProductCategories) {
                        $matchingCategories = array_intersect($oneProductCategories, $oneRate['product_category']);
                        if (empty($matchingCategories) && !in_array(self::LPC_ALL_PRODUCT_CATEGORIES_CODE, $oneRate['product_category'])) {
                            continue 2;
                        }
                    }
                }

                if (
                    $totalWeight >= $oneRate['min_weight']
                    && (empty($oneRate['max_weight']) || $totalWeight < $oneRate['max_weight'])
                    && $totalPrice >= $oneRate['min_price']
                    && (empty($oneRate['max_price']) || $totalPrice < $oneRate['max_price'])
                ) {
                    if (null === $cost) {
                        $cost = $oneRate['price'];
                    } elseif ('lowest' === $rateToChoose && $oneRate['price'] < $cost) {
                        $cost = $oneRate['price'];
                    } elseif ('highest' === $rateToChoose && $oneRate['price'] > $cost) {
                        $cost = $oneRate['price'];
                    }
                }
            }
        }

        if (null !== $cost) {
            // Handle free shipping options
            $classesFreeShipping     = $this->getFreeShippingClasses();
            $isClassesFreeShipping   = !empty(array_intersect($classesFreeShipping, $cartShippingClasses));
            $isMethodFreeForAllItems = $this->getFreeForItemsWithoutFreeShippingClasses();
            $areOtherPayingClasses   = !empty(array_diff($cartShippingClasses, $classesFreeShipping));
            $freeFromOrderValue      = $this->freeFromOrderValue();

            if (
                'yes' === $this->get_option('always_free')
                || ($freeFromOrderValue > 0 && $totalPrice >= $freeFromOrderValue)
                || $isCouponFreeShipping
                || ($isClassesFreeShipping && (!$areOtherPayingClasses || 'yes' === $isMethodFreeForAllItems))
            ) {
                $cost = 0.0;
            }

            $extraCost   = 0;
            $countryCode = $package['destination']['country'];

            $ftdActive = LpcHelper::get_option('lpc_customs_isFtd') === 'yes';
            if ($ftdActive && in_array($countryCode, LpcLabelGenerationPayload::COUNTRIES_FTD)) {
                $extraCost = LpcHelper::get_option('lpc_extracost_outremer', 0);
            }

            // For DDP shipping methods, apply the extra cost if any, even when free shipping is active
            if (false !== strpos($this->id, '_ddp')) {
                $extraCost = LpcHelper::get_option('lpc_extracost_' . strtolower($countryCode), 0);
            }

            if (!empty($extraCost)) {
                $cost += $extraCost;
            }

            // Apply discount on shipping if there is one
            if (0 != $discountToApply) {
                $cost = $cost * (1 - $discountToApply * 0.01);
            }

            // We add it after any discount as it is a fixed cost
            $extraCostFree = LpcHelper::get_option('lpc_extra_cost_over_free', 'no');
            if (!empty($cost) || 'yes' === $extraCostFree) {
                $extraCost = LpcHelper::get_option('lpc_extra_cost', 0);
                if (!empty($extraCost)) {
                    $cost += $extraCost;
                }

                if (!empty($packagingMatchingCart['extra_cost'])) {
                    $cost += $packagingMatchingCart['extra_cost'];
                }
            }

            $titleFree       = $this->get_option('title_free', '');
            $label           = 0 == $cost && !empty($titleFree) ? $titleFree : $this->title;
            $translatedLabel = __($label, 'wc_colissimo');

            $rate = [
                'id'      => $this->get_rate_id(),
                'label'   => $translatedLabel,
                'cost'    => $cost,
                'package' => $package,
            ];

            $this->add_rate($rate);
        }
    }

    private function checkPickupAvailability(): bool {
        if (LpcRelay::ID !== $this->id) {
            return true;
        }

        $testedCredentials = LpcHelper::get_option('lpc_current_credentials_tested');
        if ($testedCredentials) {
            return (bool) LpcHelper::get_option('lpc_current_credentials_valid', false);
        } else {
            $pickUpWidgetApi = LpcRegister::get('pickupWidgetApi');
            $token           = $pickUpWidgetApi->authenticate();

            update_option('lpc_current_credentials_tested', true);
            update_option('lpc_current_credentials_valid', !empty($token));

            return !empty($token);
        }
    }

    /**
     * Methods called by the Chronopost plugin, they don't check if the method is one of their own
     */
    public function refresh_methods() {

    }

    public function isAvailableForContract(): bool {
        return false;
    }
}
