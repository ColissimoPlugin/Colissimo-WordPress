<?php

defined('ABSPATH') || die('Restricted Access');

require_once LPC_INCLUDES . 'label' . DS . 'lpc_label_generation_payload.php';

class LpcBalReturn extends LpcComponent {
    const ROUTE = '/lpc/balReturn/';
    const ROUTE_REGEX = '^lpc/balReturn/(.+)/?';
    const QUERY_VAR = 'order';

    protected $listMailBoxPickingDatesResponse;
    protected $pickUpConfirmation;
    protected $labelGenerationApi;
    protected $labelGenerationInward;

    public function __construct(LpcLabelGenerationApi $labelGenerationApi = null) {
        $this->labelGenerationApi    = LpcRegister::get('labelGenerationApi', $labelGenerationApi);
        $this->labelGenerationInward = LpcRegister::get('labelGenerationInward');
    }

    public function getDependencies(): array {
        return ['labelGenerationApi', 'labelGenerationInward'];
    }

    public function init() {
        add_action('woocommerce_order_details_before_order_table', [$this, 'addBalReturn']);

        // Whitelist our URL parameter
        add_filter(
            'query_vars',
            function (array $query_vars) {
                $query_vars[] = self::QUERY_VAR;

                return $query_vars;
            }
        );

        // If our parameter is in the URL, show our tracking page
        add_action(
            'parse_request',
            function (WP $wp) {
                if (!empty($wp->query_vars[self::QUERY_VAR]) && preg_match('/(.*)lpc\/balReturn\/(.*)/m', $wp->request)) {
                    $this->control($wp);
                }
            }
        );
    }

    /********** Link/page structure **********/
    /**
     * Add BAL return link on front order page (in front user account)
     * Only displayed if service enabled and order delivered in France
     *
     * @param WC_Order $order
     */
    public function addBalReturn(WC_Order $order) {
        if ('yes' === LpcHelper::get_option('lpc_bal_return', 'no') && 'FR' === $order->get_shipping_country()) {
            $url  = $this->getBalReturnUrl($order->get_id());
            $link = '<a href="' . $url . '" target="_blank">' . __('MailBox picking return', 'wc_colissimo') . '</a>';
            echo $link;
        }
    }

    public static function addRewriteRule() {
        add_rewrite_rule(
            self::ROUTE_REGEX,
            'index.php?' . self::QUERY_VAR . '=$matches[1]',
            'bottom'
        );
    }

    public function getBalReturnUrl($orderId): string {
        return empty(get_option('permalink_structure')) ? '/' . self::QUERY_VAR . '=' . $orderId : self::ROUTE . $orderId;
    }

    /**************** Process ****************/
    /**
     * Handle form data to make right API calls and display corresponding data
     *
     * @param WP $wp
     */
    public function control(WP $wp) {
        LpcHelper::enqueueStyle(
            'lpc_bal_return',
            null,
            plugins_url('/css/lpc_bal_return.css', LPC_PUBLIC . 'init.php')
        );

        $orderId = $wp->query_vars[self::QUERY_VAR];
        $order   = new WC_Order($orderId);
        $baseUrl = $this->getBalReturnUrl($order->get_id());
        $data    = [
            'order'        => $order,
            'urlBalReturn' => $baseUrl,
        ];
        $partial = 'bal_return' . DS . 'request.php';

        if (!empty(LpcHelper::getVar('lpc_action'))) {
            $lpcAction              = LpcHelper::getVar('lpc_action');
            $address                = LpcHelper::getVar('address', '', 'array');
            $address['countryCode'] = 'FR';
            $data['address']        = $address;
            $data['addressDisplay'] = $this->formatAddress($address);

            // Call API to check availability
            $payload = $this->getPayload($address);
            $this->getListMailBoxPickingDatesResponse($payload);

            if ('checkAvailability' === $lpcAction) {
                $partial = 'bal_return' . DS . 'check_availability.php';

                $data['listMailBoxPickingDatesResponse'] = $this->listMailBoxPickingDatesResponse;
                if ($data['listMailBoxPickingDatesResponse']) {
                    $data['mailBoxPickingDate'] = $this->getMailBoxPickingDate();
                } else {
                    $data['mailBoxPickingDate'] = null;
                }
            } elseif ('confirm' === $lpcAction) {
                $partial = 'bal_return' . DS . 'confirm.php';

                // Call API to validate process
                $returnTrackingNumber = $this->getReturnTrackingNumber($order);
                $this->sendPickUpConfirmation($payload, $returnTrackingNumber);
                $data['pickupConfirmation']   = $this->pickUpConfirmation;
                $data['returnTrackingNumber'] = $returnTrackingNumber;
            }
        }

        // Display
        die(
        LpcHelper::renderPartialInLayout(
            $partial,
            $data
        )
        );
    }

    /**
     * Prepare data for the API calls
     *
     * @param $sender : address to check
     *
     * @return array
     */
    protected function getPayload($sender) {
        $payload        = new LpcLabelGenerationPayload();
        $payloadPicking = $payload->withContractNumber()
                                  ->withPassword()
                                  ->withSender($sender)
                                  ->assemble();

        $payloadPicking['sender'] = $payloadPicking['letter']['sender']['address'];
        unset($payloadPicking['letter']);

        return $payloadPicking;
    }

    /**
     * Call API to check pickup availability at a specific address
     *
     * @param $payload
     *
     * @return bool
     */
    protected function getListMailBoxPickingDatesResponse($payload) {
        try {
            $this->listMailBoxPickingDatesResponse = $this->labelGenerationApi->listMailBoxPickingDates($payload);
        } catch (Exception $e) {
            LpcLogger::debug(__METHOD__ . ' Error calling pickup', [$payload]);
            $this->listMailBoxPickingDatesResponse = false;
        }

        return $this->listMailBoxPickingDatesResponse;
    }

    /**
     * Call API to confirm pickup
     *
     * @param $payload
     * @param $returnTrackingNumber
     *
     * @return bool
     */
    protected function sendPickUpConfirmation($payload, $returnTrackingNumber) {
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

    /**
     * Format date got from API
     *
     * @return string|null
     */
    protected function getMailBoxPickingDate() {
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
     * Get return label number and generate one of no return label found
     *
     * @param WC_Order $order
     *
     * @return mixed
     */
    protected function getReturnTrackingNumber(WC_Order $order) {
        $inwardLabel = $order->get_meta(LpcLabelGenerationInward::INWARD_PARCEL_NUMBER_META_KEY);

        if (empty($inwardLabel)) {
            try {
                $this->labelGenerationInward->generate($order);
                $order->read_meta_data(true);
                $inwardLabel = $order->get_meta(LpcLabelGenerationInward::INWARD_PARCEL_NUMBER_META_KEY);
            } catch (Exception $exc) {
                LpcLogger::debug(__METHOD__ . ' Error generating return label on pickup confirmation', ['order' => $order->get_id()]);
            }
        }

        return $inwardLabel;
    }

    /**
     * Format address to display it using Woocommerce function
     *
     * @param $address : got from user request
     *
     * @return array : address formatted
     */
    protected function formatAddress($address) {

        $formatedAddress = [
            'company'   => $address['companyName'],
            'address_1' => $address['street'],
            'city'      => $address['city'],
            'postcode'  => $address['zipCode'],
            'country'   => $address['countryCode'],
        ];

        return $formatedAddress;
    }
}
