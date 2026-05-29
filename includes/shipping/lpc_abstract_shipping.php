<?php
defined('ABSPATH') || die('Restricted Access');

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
        $rates = $this->get_option('shipping_rates', []);

        array_walk(
            $rates,
            function (&$rate) {
                if (isset($rate['shipping_class']) && !is_array($rate['shipping_class'])) {
                    $rate['shipping_class'] = [$rate['shipping_class']];
                }
                $rate['shipping_class'] ??= [];
            }
        );

        return $rates;
    }

    public function getDiscounts() {
        return $this->get_option('shipping_discount', []);
    }

    public function getFreeShippingClasses() {
        return $this->get_option('classes_free_shipping', []);
    }

    public function getFreeForItemsWithoutFreeShippingClasses() {
        return $this->get_option('free_for_items_without_free_shipping_classes', 'no');
    }

    abstract public function freeFromOrderValue();

    public function calculate_shipping($package = []): void {
        if (!$this->isShippingAvailableForPackage($package)) {
            return;
        }

        // Get possible prices for this method
        $rates = $this->getRates();
        // Extract the information from this cart to find the correct price
        $cart = $this->analyzeCartContents();

        if (!$this->isShippingAllowedForCart($cart, $package)) {
            return;
        }

        // Find the correct price based on cart
        $cost = $this->getPriceMatchingCart($rates, $cart, $package);
        if (null === $cost) {
            return;
        }

        // Free, extra costs, discounts, etc...
        $cost = $this->applyAllCostAdjustments($cost, $cart, $package);

        $this->registerRate($cost, $package);
    }

    private function isShippingAvailableForPackage(array $package): bool {
        return (
            $this->checkPickupAvailability()
            && $this->lpcCapabilitiesPerCountry->getInfoForDestination($package['destination']['country'], $this->id)
        );
    }

    private function analyzeCartContents(): array {
        $noshipProductsCount = LpcHelper::get_option('lpc_calculate_shipping_with_noship_products', 'no') === 'yes';
        $cartContents        = WC()->cart->get_cart();

        $lineTotal             = 0;
        $lineTax               = 0;
        $lineSubTotal          = 0;
        $lineSubTax            = 0;
        $articleQuantity       = 0;
        $nbProductsToShip      = 0;
        $totalWeight           = 0;
        $productsDimensions    = [];
        $cartShippingClasses   = [];
        $cartProductCategories = [];
        $cartHazmatCategories  = [];
        $yithBundlesHandled    = [];

        foreach ($cartContents as $item) {
            if (empty($item['data'])) {
                continue;
            }

            $product         = $item['data'];
            $quantity        = (float) $item['quantity'];
            $articleQuantity += $quantity;

            if (!empty($item['bundled_by'])) {
                $yithBundlesHandled[] = $item['bundled_by'];
                continue;
            }

            $itemData = $this->extractItemData($item);
            if (null === $itemData) {
                continue;
            }

            if (!$itemData['isShippable']) {
                if ($noshipProductsCount) {
                    $lineTotal    += $itemData['line_total'];
                    $lineTax      += $itemData['line_tax'];
                    $lineSubTotal += $itemData['line_subtotal'];
                    $lineSubTax   += $itemData['line_subtotal_tax'];
                }

                continue;
            }

            $cartHazmatCategories = array_merge(
                $cartHazmatCategories,
                $this->getProductHazmatCategories($product, $itemData['categories'])
            );

            $lineTotal    += $itemData['line_total'];
            $lineTax      += $itemData['line_tax'];
            $lineSubTotal += $itemData['line_subtotal'];
            $lineSubTax   += $itemData['line_subtotal_tax'];

            $productsDimensions[]  = $itemData['dimensions'];
            $nbProductsToShip      += $quantity;
            $totalWeight           += $itemData['weight'];
            $cartShippingClasses[] = empty($itemData['shipping_class_id'])
                ? self::LPC_NO_SHIPPING_CLASS_CODE
                : $itemData['shipping_class_id'];

            if (!empty($itemData['categories'])) {
                $cartProductCategories[] = $itemData['categories'];
            }
        }

        // Don't count the Yith bundle entry as an article
        $articleQuantity -= count(array_unique($yithBundlesHandled));

        $cartShippingClasses = array_unique($cartShippingClasses);

        $packaging = LpcHelper::getMatchingPackaging(
            $nbProductsToShip,
            $totalWeight,
            $productsDimensions
        );

        $cart = compact(
            'lineTotal',
            'lineTax',
            'lineSubTotal',
            'lineSubTax',
            'articleQuantity',
            'nbProductsToShip',
            'totalWeight',
            'productsDimensions',
            'cartShippingClasses',
            'cartProductCategories',
            'cartHazmatCategories',
            'packaging'
        );

        $cart['totalPrice'] = $this->computeTotalPrice($cart);

        return $cart;
    }

    private function extractItemData(array $item): ?array {
        // YITH Bundles take care of their included products
        if (!empty($item['bundled_by'])) {
            return null;
        }

        $product = $item['data'];

        return [
            'line_total'        => $item['line_total'],
            'line_tax'          => $item['line_tax'],
            'line_subtotal'     => $item['line_subtotal'],
            'line_subtotal_tax' => $item['line_subtotal_tax'],
            'weight'            => (float) $product->get_weight() * $item['quantity'],
            'dimensions'        => [$product->get_length(), $product->get_width(), $product->get_height()],
            'shipping_class_id' => $product->get_shipping_class_id(),
            'categories'        => ('variation' === $product->get_type())
                ? wc_get_product_term_ids($product->get_parent_id(), 'product_cat')
                : $product->get_category_ids('edit'),
            'isShippable'       => !is_callable([$product, 'needs_shipping']) || $product->needs_shipping(),
        ];
    }

    private function isShippingAllowedForCart(array $cart, array $package): bool {
        if ($this->isCouponRestricted($package)) {
            return false;
        }

        $excludedClasses = $this->get_option('excluded_classes', []);
        if (!empty(array_intersect($excludedClasses, $cart['cartShippingClasses']))) {
            return false;
        }

        // DDP for GB must be commercial and between 160€ and 1050€
        $isCommercialSend = self::CUSTOMS_CATEGORY_COMMERCIAL === (int) LpcHelper::get_option('lpc_customs_defaultCustomsCategory');
        if ('GB' === $package['destination']['country']
            && LpcSignDDP::ID === $this->id
            && ($cart['totalPrice'] < 160 || $cart['totalPrice'] > 1050 || !$isCommercialSend)
        ) {
            return false;
        }

        return true;
    }

    private function computeTotalPrice(array $cart): float {
        if (LpcHelper::get_option('lpc_calculate_shipping_before_taxes', 'no') === 'yes') {
            $totalPrice              = round($cart['lineTotal'], 2);
            $totalWithoutCouponPrice = round($cart['lineSubTotal'], 2);
        } else {
            $totalPrice              = round($cart['lineTax'] + $cart['lineTotal'], 2);
            $totalWithoutCouponPrice = round($cart['lineSubTax'] + $cart['lineSubTotal'], 2);
        }

        return 'yes' === LpcHelper::get_option('lpc_calculate_shipping_before_coupon', 'no')
            ? $totalWithoutCouponPrice
            : $totalPrice;
    }

    private function computeTotalWeight(array $cart, array $package): float {
        $totalWeight = $cart['totalWeight'];
        $totalWeight += empty($cart['packaging'])
            ? LpcHelper::get_option('lpc_packaging_weight', 0)
            : $cart['packaging']['weight'];

        /**
         * Filter on the package's total weight, before the checkout calculation
         *
         * @since 1.6.7
         */
        $totalWeight = (float) apply_filters('lpc_payload_letter_parcel_weight_checkout', $totalWeight, $package);

        return $totalWeight;
    }

    private function getPriceMatchingCart(array $rates, array $cart, array $package): ?float {
        $totalWeight = $this->computeTotalWeight($cart, $package);
        $cost        = null;

        // Current format
        $rateToChoose = LpcHelper::get_option('lpc_choose_min_max_rate', 'lowest');

        foreach ($rates as $oneRate) {
            // All cart shipping classes must be specified in the rate
            $missingClasses = array_diff($cart['cartShippingClasses'], $oneRate['shipping_class']);
            if (!empty($missingClasses) && !in_array(self::LPC_ALL_SHIPPING_CLASS_CODE, $oneRate['shipping_class'])) {
                continue;
            }

            // All cart products must have at least 1 of their categories specified in the rate (product_category might not exist on older rates)
            if (!empty($oneRate['product_category'])) {
                foreach ($cart['cartProductCategories'] as $oneProductCategories) {
                    $matchingCategories = array_intersect($oneProductCategories, $oneRate['product_category']);
                    if (empty($matchingCategories) && !in_array(self::LPC_ALL_PRODUCT_CATEGORIES_CODE, $oneRate['product_category'])) {
                        continue 2;
                    }
                }
            }

            $weightMatches = $totalWeight >= $oneRate['min_weight'] && (empty($oneRate['max_weight']) || $totalWeight < $oneRate['max_weight']);
            $priceMatches  = $cart['totalPrice'] >= $oneRate['min_price'] && (empty($oneRate['max_price']) || $cart['totalPrice'] < $oneRate['max_price']);

            if (!$weightMatches || !$priceMatches) {
                continue;
            }

            if (null === $cost
                || ('lowest' === $rateToChoose && $oneRate['price'] < $cost)
                || ('highest' === $rateToChoose && $oneRate['price'] > $cost)
            ) {
                $cost = $oneRate['price'];
            }
        }

        return $cost;
    }

    private function applyAllCostAdjustments(float $cost, array $cart, array $package): float {
        $cost = $this->applyFreeShipping($cost, $cart, $package);
        $cost = $this->applyExtraCosts($cost, $package);
        $cost = $this->applyDiscount($cost, $cart);

        // We add it after any discount as it is a fixed cost
        $cost = $this->applyFixedExtraCosts($cost, $cart, $package);

        return $this->applyHazmatExtraCost($cost, $cart);
    }

    private function applyFreeShipping(float $cost, array $cart, array $package): float {
        $classesFreeShipping     = $this->getFreeShippingClasses();
        $isClassesFreeShipping   = !empty(array_intersect($classesFreeShipping, $cart['cartShippingClasses']));
        $isMethodFreeForAllItems = $this->getFreeForItemsWithoutFreeShippingClasses();
        $areOtherPayingClasses   = !empty(array_diff($cart['cartShippingClasses'], $classesFreeShipping));
        $freeFromOrderValue      = $this->freeFromOrderValue();
        $isCouponFreeShipping    = $this->hasFreeShippingCoupon($package);

        $isFree = (
            'yes' === $this->get_option('always_free')
            || ($freeFromOrderValue > 0 && $cart['totalPrice'] >= $freeFromOrderValue)
            || $isCouponFreeShipping
            || ($isClassesFreeShipping && (!$areOtherPayingClasses || 'yes' === $isMethodFreeForAllItems))
        );

        return $isFree ? 0.0 : $cost;
    }

    private function hasFreeShippingCoupon(array $package): bool {
        foreach ($package['applied_coupons'] as $oneCouponCode) {
            $coupon = new WC_Coupon($oneCouponCode);
            if ($coupon->get_free_shipping()) {
                return true;
            }
        }

        return false;
    }

    private function isCouponRestricted(array $package): bool {
        foreach ($package['applied_coupons'] as $oneCouponCode) {
            $coupon            = new WC_Coupon($oneCouponCode);
            $couponRestriction = $coupon->get_meta('lpc_coupon_restriction');
            if (!empty($couponRestriction) && in_array($this->id, $couponRestriction)) {
                return true;
            }
        }

        return false;
    }

    private function applyExtraCosts(float $cost, array $package): float {
        $countryCode = $package['destination']['country'];
        $extraCost   = 0;

        $ftdActive = LpcHelper::get_option('lpc_customs_isFtd') === 'yes';
        if ($ftdActive && in_array($countryCode, LpcLabelGenerationPayload::COUNTRIES_FTD)) {
            $extraCost = LpcHelper::get_option('lpc_extracost_outremer', 0);
        }

        if (false !== strpos($this->id, '_ddp')) {
            $extraCost = LpcHelper::get_option('lpc_extracost_' . strtolower($countryCode), 0);
        }

        return $cost + (float) $extraCost;
    }

    private function applyDiscount(float $cost, array $cart): float {
        $discountToApply = 0.0;

        foreach ($this->getDiscounts() as $discount) {
            if ($discount['nb_product'] <= $cart['articleQuantity'] && $discountToApply < $discount['percentage']) {
                $discountToApply = (float) $discount['percentage'];
            }
        }

        if (empty($discountToApply)) {
            return $cost;
        }

        return $cost * (1 - $discountToApply * 0.01);
    }

    private function applyFixedExtraCosts(float $cost, array $cart, array $package): float {
        $extraCostFree = LpcHelper::get_option('lpc_extra_cost_over_free', 'no');

        if (!empty($cost) || 'yes' === $extraCostFree) {
            $extraCost = LpcHelper::get_option('lpc_extra_cost', 0);
            if (!empty($extraCost)) {
                $cost += $extraCost;
            }

            if (!empty($cart['packaging']['extra_cost'])) {
                $cost += $cart['packaging']['extra_cost'];
            }
        }

        return $cost;
    }

    private function applyHazmatExtraCost(float $cost, array $cart): float {
        $extraCostHazmat = LpcHelper::get_option('lpc_hazmat_extra_cost_value');
        if (empty($extraCostHazmat) || empty($cart['cartHazmatCategories'])) {
            return $cost;
        }

        foreach ($cart['cartHazmatCategories'] as $hazmatCategorySlug) {
            if (empty(LpcLabelGenerationPayload::HAZMAT_CATEGORIES[$hazmatCategorySlug])) {
                continue;
            }

            $cost += (float) str_replace(',', '.', $extraCostHazmat);
            break;
        }

        return $cost;
    }

    private function registerRate(float $cost, array $package): void {
        $titleFree       = $this->get_option('title_free', '');
        $label           = empty($cost) && !empty($titleFree) ? $titleFree : $this->title;
        $translatedLabel = __($label, 'wc_colissimo');

        $this->add_rate(
            [
                'id'      => $this->get_rate_id(),
                'label'   => $translatedLabel,
                'cost'    => $cost,
                'package' => $package,
            ]
        );
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
            $token           = $pickUpWidgetApi->authenticate(true);

            update_option('lpc_current_credentials_tested', true);
            update_option('lpc_current_credentials_valid', !empty($token));

            return !empty($token);
        }
    }

    private function getProductHazmatCategories(object $product, array $productCategories): array {
        $attributesContainer = 'variation' === $product->get_type() ? wc_get_product($product->get_parent_id()) : $product;
        $attributes          = $attributesContainer->get_attributes();
        if (!empty($attributes['pa_' . LpcLabelGenerationPayload::HAZMAT_ATTRIBUTE])) {
            return $attributes['pa_' . LpcLabelGenerationPayload::HAZMAT_ATTRIBUTE]->get_slugs();
        }

        $hazmatCategories = [];
        foreach ($productCategories as $categoryId) {
            $hazmatCategories[] = get_term_meta($categoryId, LpcLabelGenerationPayload::HAZMAT_ATTRIBUTE, true);
        }

        return $hazmatCategories;
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
