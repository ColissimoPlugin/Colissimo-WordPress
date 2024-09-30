<?php

defined('ABSPATH') || die('Restricted Access');

require_once LPC_INCLUDES . 'label' . DS . 'lpc_label_generation_payload.php';

class LpcReturn extends LpcComponent {
    const RETURN_PAGE_ALIAS = 'lpcreturn';

    /** @var LpcLabelInwardDownloadAccountAction */
    private $labelInwardDownloadAccountAction;
    /** @var LpcLabelGenerationApi */
    private $labelGenerationApi;
    /** @var LpcLabelGenerationInward */
    private $labelGenerationInward;
    /** @var LpcAccountApi */
    private $accountApi;

    protected $listMailBoxPickingDatesResponse;
    protected $pickUpConfirmation;

    public function __construct(
        LpcLabelInwardDownloadAccountAction $labelInwardDownloadAccountAction = null,
        LpcLabelGenerationApi $labelGenerationApi = null,
        LpcLabelGenerationInward $labelGenerationInward = null,
        LpcAccountApi $accountApi = null
    ) {
        $this->labelInwardDownloadAccountAction = LpcRegister::get('labelInwardDownloadAccountAction', $labelInwardDownloadAccountAction);
        $this->labelGenerationApi               = LpcRegister::get('labelGenerationApi', $labelGenerationApi);
        $this->labelGenerationInward            = LpcRegister::get('labelGenerationInward', $labelGenerationInward);
        $this->accountApi                       = LpcRegister::get('accountApi', $accountApi);
    }

    public function getDependencies(): array {
        return ['labelInwardDownloadAccountAction', 'labelGenerationApi', 'labelGenerationInward', 'accountApi'];
    }

    public function init() {
        add_action('init', [$this, 'addLpcReturnEndPoint']);
        add_action('woocommerce_account_' . self::RETURN_PAGE_ALIAS . '_endpoint', [$this, 'returnMenu']);
    }

    public function addLpcReturnEndPoint() {
        add_rewrite_endpoint(self::RETURN_PAGE_ALIAS, EP_PAGES);
    }

    public function returnMenu($current_page = 1) {
        $orderId = LpcHelper::getVar('order_id', 0);
        $order   = wc_get_order($orderId);

        $balStep = (int) LpcHelper::getVar('lpc_bal_step', 0);

        $balUrl = get_permalink(get_option('woocommerce_myaccount_page_id'));
        $balUrl = add_query_arg('lpcreturn', '', $balUrl);
        $balUrl = add_query_arg('order_id', $orderId, $balUrl);
        $balUrl = add_query_arg('lpc_bal_step', $balStep + 1, $balUrl);

        $data = [
            'order'              => $order,
            'generateUrlBase'    => $this->labelInwardDownloadAccountAction->getUrlForCustom($orderId),
            'downloadUrlBase'    => $this->labelInwardDownloadAccountAction->getUrlForDownload($orderId, ''),
            'balReturn'          => 'yes' === LpcHelper::get_option('lpc_bal_return', 'no') && 'FR' === $order->get_shipping_country(),
            'balReturnUrl'       => $balUrl,
            'securedReturn'      => false,
        ];

        $accountInformation = $this->accountApi->getAccountInformation();
        if (!empty($accountInformation['optionRetourToken']) && 1 === intval(LpcHelper::get_option('lpc_secured_return', 0))) {
            $data['securedReturn'] = true;
            $data['balReturn']     = false;
        }

        if (empty($balStep)) {
            // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
            echo '<link rel="stylesheet" href="' . esc_url(plugins_url('/css/orders/return.css', LPC_PUBLIC . 'init.php')) . '" />';
            // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
            echo '<script src="' . esc_url(plugins_url('/js/orders/return.js', LPC_PUBLIC . 'init.php')) . '"></script>';

            include LPC_FOLDER . 'public' . DS . 'partials' . DS . 'order' . DS . 'return.php';
        } else {
            // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
            echo '<link rel="stylesheet" href="' . esc_url(plugins_url('/css/orders/bal_return.css', LPC_PUBLIC . 'init.php')) . '" />';

            $data['products'] = LpcHelper::getVar('lpc_label_products');

            if (1 === $balStep) {
                $data['address'] = [
                    'company'     => $order->get_formatted_shipping_full_name(),
                    'address_1'   => $order->get_shipping_address_1(),
                    'city'        => $order->get_shipping_city(),
                    'postcode'    => $order->get_shipping_postcode(),
                    'country'     => 'FR',
                    'countryCode' => 'FR',
                ];
                $address2        = $order->get_shipping_address_2();
                if (!empty($address2)) {
                    $data['address']['address_1'] .= ' ' . $address2;
                }

                $shippingMethods = $order->get_shipping_methods();
                $shippingMethod  = current($shippingMethods);

                $shippingMethod = $shippingMethod->get_method_id();
                if (LpcRelay::ID === $shippingMethod) {
                    $data['address'] = [
                        'company'     => $order->get_formatted_billing_full_name(),
                        'address_1'   => $order->get_billing_address_1(),
                        'city'        => $order->get_billing_city(),
                        'postcode'    => $order->get_billing_postcode(),
                        'country'     => 'FR',
                        'countryCode' => 'FR',
                    ];
                    $address2        = $order->get_billing_address_2();
                    if (!empty($address2)) {
                        $data['address']['address_1'] .= ' ' . $address2;
                    }
                }

                include LPC_FOLDER . 'public' . DS . 'partials' . DS . 'order' . DS . 'return_bal_address.php';
            } else {
                $address                = LpcHelper::getVar('address', [], 'array');
                $address['countryCode'] = 'FR';
                $data['address']        = $address;
                $data['addressDisplay'] = $this->formatAddress($address);
                $payload                = $this->getPayload($address);
                $this->prepareListMailBoxPickingDatesResponse($payload);

                if (2 === $balStep) {
                    $data['listMailBoxPickingDatesResponse'] = $this->listMailBoxPickingDatesResponse;

                    if ($data['listMailBoxPickingDatesResponse']) {
                        $data['mailBoxPickingDate'] = $this->getMailBoxPickingDate();
                    } else {
                        $data['mailBoxPickingDate'] = null;
                    }

                    include LPC_FOLDER . 'public' . DS . 'partials' . DS . 'order' . DS . 'return_bal_availability.php';
                } elseif (3 === $balStep) {
                    $data['returnTrackingNumber'] = $this->getReturnTrackingNumber($order, $data);
                    if (!$data['returnTrackingNumber']) {
                        return;
                    }

                    $data['pickupConfirmation'] = $this->sendPickUpConfirmation($payload, $data['returnTrackingNumber']);
                    $data['labelDownloadUrl']   = $this->labelInwardDownloadAccountAction->getUrlForDownload($order->get_id(), $data['returnTrackingNumber']);

                    include LPC_FOLDER . 'public' . DS . 'partials' . DS . 'order' . DS . 'return_bal_confirmation.php';
                }
            }
        }
    }

    /**
     * Format address to display it using Woocommerce function
     *
     * @param $address : got from user request
     *
     * @return array : address formatted
     */
    private function formatAddress($address): array {
        return [
            'company'   => $address['companyName'],
            'address_1' => $address['street'],
            'city'      => $address['city'],
            'postcode'  => $address['zipCode'],
            'country'   => $address['countryCode'],
        ];
    }

    /**
     * Prepare data for the API calls
     *
     * @param $sender : address to check
     *
     * @return array
     */
    private function getPayload($sender): array {
        $payload        = new LpcLabelGenerationPayload();
        $payloadPicking = $payload->withCredentials()
                                  ->withSender($sender)
                                  ->assemble();

        $payloadPicking['sender'] = $payloadPicking['letter']['sender']['address'];
        unset($payloadPicking['letter']);

        return $payloadPicking;
    }

    /**
     * Call API to check pickup availability at a specific address
     */
    private function prepareListMailBoxPickingDatesResponse($payload) {
        try {
            $this->listMailBoxPickingDatesResponse = $this->labelGenerationApi->listMailBoxPickingDates($payload);
        } catch (Exception $e) {
            LpcLogger::debug(__METHOD__ . ' Error calling pickup', [$payload]);
            $this->listMailBoxPickingDatesResponse = false;
        }
    }

    /**
     * Format date got from API
     */
    private function getMailBoxPickingDate() {
        $pickingPossibleDates = $this->listMailBoxPickingDatesResponse['mailBoxPickingDates'];

        if (empty($pickingPossibleDates)) {
            return null;
        }

        // Make sure we show
        $cmsOffset = get_option('timezone_string', null);

        // In WP there are multiple possible formats in the same option for the timezone
        if (empty($cmsOffset)) {
            $cmsOffset = get_option('gmt_offset', null);

            if (empty($cmsOffset)) {
                $cmsOffset = 'UTC';
            } elseif ($cmsOffset < 0) {
                $cmsOffset = 'GMT' . $cmsOffset;
            } else {
                $cmsOffset = 'GMT+' . $cmsOffset;
            }
        }

        $timezone = new DateTimeZone($cmsOffset);
        if (!is_numeric($cmsOffset)) {
            $cmsOffset = $timezone->getOffset(new DateTime());
        }

        return date_i18n(
            __('F j, Y', 'wc_colissimo'),
            $pickingPossibleDates[0] / 1000 + $cmsOffset,
            true
        );
    }

    /**
     * Get return label number and generate one if no return label found
     */
    private function getReturnTrackingNumber(WC_Order $order, $data) {
        try {
            $products = json_decode($data['products'], true);
            if (empty($products)) {
                return false;
            }

            $orderedProducts = [];
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if (empty($product) || !$product->needs_shipping()) {
                    continue;
                }

                $orderedProducts[$item->get_id()] = $item;
            }

            $products        = array_combine(array_column($products, 'productId'), array_column($products, 'quantity'));
            $items           = [];
            $totalWeight     = wc_get_weight(LpcHelper::get_option('lpc_packaging_weight', '0'), 'kg');
            $insuranceAmount = 0;
            foreach ($products as $productId => $quantity) {
                if (empty($orderedProducts[$productId]) || $orderedProducts[$productId]->get_quantity() < $quantity) {
                    return false;
                }

                $product = $orderedProducts[$productId]->get_product();

                $items[$productId] = ['qty' => $quantity];
                $totalWeight       += wc_get_weight(floatval($product->get_weight()) * floatval($quantity), 'kg');
                $insuranceAmount   += $product->get_price() * $quantity;
            }

            $this->labelGenerationInward->generate(
                $order,
                [
                    'items'                => $items,
                    'outward_label_number' => 'no_outward',
                    'totalWeight'          => $totalWeight,
                    'insuranceAmount'      => $insuranceAmount,
                    'format'               => LpcLabelGenerationPayload::LABEL_FORMAT_PDF,
                    'sender'               => [
                        'companyName' => $data['address']['companyName'] ?? $order->get_billing_company(),
                        'line2'       => $data['address']['street'] ?? $order->get_billing_address_1(),
                        'city'        => $data['address']['city'] ?? $order->get_billing_city(),
                        'zipCode'     => $data['address']['zipCode'] ?? $order->get_billing_postcode(),
                        'countryCode' => 'FR',
                    ],
                ]
            );
            $order->read_meta_data(true);

            return $order->get_meta(LpcLabelGenerationInward::INWARD_PARCEL_NUMBER_META_KEY);
        } catch (Exception $exc) {
            LpcLogger::debug(__METHOD__ . ' Error generating return label on pickup confirmation', ['order' => $order->get_id()]);
        }

        return false;
    }

    /**
     * Call API to confirm pickup
     */
    private function sendPickUpConfirmation($payload, $returnTrackingNumber) {
        $payload['mailBoxPickingDate'] = $this->listMailBoxPickingDatesResponse['mailBoxPickingDates'][0];
        $payload['parcelNumber']       = $returnTrackingNumber;

        try {
            $this->pickUpConfirmation = $this->labelGenerationApi->planPickup($payload);
        } catch (Exception $e) {
            LpcLogger::debug(__METHOD__ . ' Error confirming pickup', [$payload]);
            $this->pickUpConfirmation = false;
        }

        return $this->pickUpConfirmation;
    }
}
