<?php

require_once LPC_INCLUDES . 'lpc_modal.php';
require_once LPC_PUBLIC . 'pickup' . DS . 'lpc_pickup.php';

class LpcPickupWebService extends LpcPickup {
    const LEAFLET_JS_URL = 'https://unpkg.com/leaflet@1.9.2/dist/leaflet.js';
    const LEAFLET_JS_INTEGRITY = 'sha256-o9N1jGDZrf5tS+Ft4gbIK7mYMipq9lqpVJ91xHSyKhg=';
    const LEAFLET_CSS_URL = 'https://unpkg.com/leaflet@1.9.2/dist/leaflet.css';
    const GOOGLE_MAPS_JS_URL = 'https://maps.googleapis.com/maps/api/js?key=';

    protected $modal;
    protected $ajaxDispatcher;
    protected $lpcPickUpSelection;

    public function __construct(
        LpcAjax $ajaxDispatcher = null,
        LpcPickupSelection $lpcPickUpSelection = null
    ) {
        $this->ajaxDispatcher     = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);
        $this->lpcPickUpSelection = LpcRegister::get('pickupSelection', $lpcPickUpSelection);
    }

    public function getDependencies(): array {
        return ['ajaxDispatcher', 'pickupSelection'];
    }

    public function init() {
        if ('widget' === LpcHelper::get_option('lpc_pickup_map_type', 'widget')) {
            return;
        }

        $lpcImageUrl  = plugins_url('/images/colissimo_cropped.png', LPC_INCLUDES . 'init.php');
        $imageHtmlTag = '<img src="' . $lpcImageUrl . '" style="max-width: 90px; display:inline; vertical-align: middle;">';

        $this->modal = new LpcModal(null, $imageHtmlTag, 'lpc_pick_up_web_service');

        $this->ajaxDispatcher->register('pickupWS', [$this, 'pickupWS']);

        add_action(
            'wp_enqueue_scripts',
            function () {
                if (is_checkout() || has_block('woocommerce/checkout')) {
                    wp_register_script('lpc_pick_up_ws', plugins_url('/js/pickup/webservice.js', LPC_INCLUDES . 'init.php'), ['jquery'], LPC_VERSION, true);

                    $args = [
                        'baseAjaxUrl'           => admin_url('admin-ajax.php'),
                        'messagePhoneRequired'  => __('Please set a valid phone number', 'wc_colissimo'),
                        'messagePickupRequired' => __('Please set a pick up point', 'wc_colissimo'),
                        'ajaxURL'               => $this->ajaxDispatcher->getUrlForTask('pickupWS'),
                        'pickUpSelectionUrl'    => $this->lpcPickUpSelection->getAjaxUrl(),
                        'mapType'               => LpcHelper::get_option('lpc_pickup_map_type', 'widget'),
                        'mapMarker'             => plugins_url('/images/map_marker.png', LPC_INCLUDES . 'init.php'),
                    ];

                    wp_localize_script('lpc_pick_up_ws', 'lpcPickUpSelection', $args);

                    wp_register_style('lpc_pick_up_ws', plugins_url('/css/pickup/webservice.css', LPC_INCLUDES . 'init.php'), [], LPC_VERSION);
                    wp_register_style('lpc_pick_up', plugins_url('/css/pickup/pickup.css', LPC_INCLUDES . 'init.php'), [], LPC_VERSION);

                    wp_enqueue_script('lpc_pick_up_ws');
                    wp_enqueue_style('lpc_pick_up_ws');
                    wp_enqueue_style('lpc_pick_up');
                    $googleApiKey = LpcHelper::get_option('lpc_gmap_key', '');
                    $mapType      = LpcHelper::get_option('lpc_pickup_map_type', 'leaflet');
                    if ('leaflet' !== $mapType && !empty($googleApiKey)) {
                        wp_register_script('lpc_google_maps', self::GOOGLE_MAPS_JS_URL . $googleApiKey, [], LPC_VERSION);
                        wp_enqueue_script('lpc_google_maps');
                    } else {
                        wp_register_script('lpc_leaflet_js', self::LEAFLET_JS_URL, [], LPC_VERSION, ['integrity' => self::LEAFLET_JS_INTEGRITY]);
                        wp_register_style('lpc_leaflet_css', self::LEAFLET_CSS_URL, [], LPC_VERSION);
                        wp_enqueue_script('lpc_leaflet_js');
                        wp_enqueue_style('lpc_leaflet_css');
                    }
                    $this->modal->loadScripts();
                }
            }
        );

        add_action('woocommerce_after_shipping_rate', [$this, 'addWebserviceMap']);
    }

    /**
     * Uses a WC hook to add a "Select pick up location" button on the checkout page
     *
     * @param     $method
     * @param int $index
     */
    public function addWebserviceMap($method, $index = 0) {
        if ($this->getMode($method->get_method_id(), $method->get_id()) !== self::WEB_SERVICE) {
            return;
        }

        echo $this->getWebserviceModal();
    }

    public function getWebserviceModal($forceCheckout = false) {
        $wcSession = WC()->session;
        $customer  = $wcSession->customer;

        $map = LpcHelper::renderPartial(
            'pickup' . DS . 'webservice_map.php',
            [
                'ceAddress'     => str_replace('’', "'", $customer['shipping_address']),
                'ceZipCode'     => $customer['shipping_postcode'],
                'ceTown'        => str_replace('’', "'", $customer['shipping_city']),
                'ceCountryId'   => $customer['shipping_country'],
                'maxRelayPoint' => LpcHelper::get_option('lpc_max_relay_point', 20),
            ]
        );
        $this->modal->setContent($map);
        $currentRelay = $this->lpcPickUpSelection->getCurrentPickUpLocationInfo();

        $address = [
            'address'     => $customer['shipping_address'],
            'zipCode'     => $customer['shipping_postcode'],
            'city'        => $customer['shipping_city'],
            'countryCode' => $customer['shipping_country'],
        ];

        if ('yes' === LpcHelper::get_option('lpc_select_default_pr', 'no')
            && empty($currentRelay)
            && count($address) == count(array_filter($address))) {
            $currentRelay = $this->getDefaultPickupLocationInfoWS($address);
        }

        $args = [
            'modal'        => $this->modal,
            'apiKey'       => LpcHelper::get_option('lpc_gmap_key', ''),
            'currentRelay' => $currentRelay,
            'type'         => 'button',
            'showButton'   => is_checkout() || $forceCheckout,
            'showInfo'     => is_checkout() || $forceCheckout,
            'mapType'      => LpcHelper::get_option('lpc_pickup_map_type', 'leaflet'),
        ];

        return LpcHelper::renderPartial('pickup' . DS . 'webservice.php', $args);
    }

    public function pickupWS() {
        $address  = [
            'address'     => LpcHelper::getVar('address'),
            'zipCode'     => LpcHelper::getVar('zipCode'),
            'city'        => LpcHelper::getVar('city'),
            'countryCode' => LpcHelper::getVar('countryId'),
        ];
        $loadMore = LpcHelper::getVar('loadMore', 0, 'int') === 1;

        $resultWs = $this->getPickupWS($address);

        if (empty($resultWs)) {
            return $resultWs;
        }

        if (0 == $resultWs['errorCode']) {
            if (empty($resultWs['listePointRetraitAcheminement'])) {
                LpcLogger::warn(__('The web service returned 0 relay', 'wc_colissimo'));

                return $this->ajaxDispatcher->makeError(['message' => __('No relay available', 'wc_colissimo')]);
            }

            $listRelaysWS = $resultWs['listePointRetraitAcheminement'];
            $html         = '';

            // Choose displayed relay types
            $relayTypes = LpcHelper::get_option('lpc_relay_point_type', 'all');
            if (empty($relayTypes)) {
                $relayTypes = 'all';
            }

            // Force Post office type if cart weight > 20kg
            $cartWeight = wc_get_weight(WC()->cart->get_cart_contents_weight(), 'kg');
            if ($cartWeight > 20) {
                $relayTypes  = ['BDP', 'BPR'];
                $overWarning = __('Only post offices are available for this order', 'wc_colissimo');
                $html        .= '<div class="lpc_layer_relay_warning_relay_type">' . $overWarning . '</div>';
            }

            if ('all' != $relayTypes) {
                $listRelaysWS = array_filter(
                    $listRelaysWS,
                    function ($relay) use ($relayTypes) {
                        return in_array($relay['typeDePoint'], $relayTypes);
                    }
                );
            }

            // Limit number of displayed relays
            $maxRelayPoint = $loadMore ? 20 : LpcHelper::get_option('lpc_max_relay_point', 20);
            $listRelaysWS  = array_slice($listRelaysWS, 0, $maxRelayPoint);

            $i           = 0;
            $partialArgs = [
                'relaysNb'    => count($listRelaysWS),
                'openingDays' => [
                    'Monday'    => 'horairesOuvertureLundi',
                    'Tuesday'   => 'horairesOuvertureMardi',
                    'Wednesday' => 'horairesOuvertureMercredi',
                    'Thursday'  => 'horairesOuvertureJeudi',
                    'Friday'    => 'horairesOuvertureVendredi',
                    'Saturday'  => 'horairesOuvertureSamedi',
                    'Sunday'    => 'horairesOuvertureDimanche',
                ],
            ];

            foreach ($listRelaysWS as $oneRelay) {
                if (empty($oneRelay['identifiant']) || empty($oneRelay['typeDePoint'])) {
                    continue;
                }

                $partialArgs['oneRelay'] = $oneRelay;
                $partialArgs['i']        = $i ++;

                $html .= LpcHelper::renderPartial('pickup' . DS . 'relay.php', $partialArgs);
            }

            return $this->ajaxDispatcher->makeSuccess(
                [
                    'html'            => $html,
                    'chooseRelayText' => __('Choose this relay', 'wc_colissimo'),
                    'loadMore'        => $loadMore ? 1 : 0,
                ]
            );
        } elseif (in_array($resultWs['errorCode'], [301, 300, 203])) {
            LpcLogger::warn($resultWs['errorCode'] . ' : ' . $resultWs['errorMessage']);

            return $this->ajaxDispatcher->makeError(['message' => __('No relay available', 'wc_colissimo')]);
        } else {
            // Error codes we want to display the related messages to the client, we'll only display a generic message for the other error codes
            $errorCodesWSClientSide = [
                '104',
                '105',
                '117',
                '125',
                '129',
                '143',
                '144',
                '145',
                '146',
            ];

            if (in_array($resultWs['errorCode'], $errorCodesWSClientSide)) {
                return $this->ajaxDispatcher->makeAndLogError(['message' => $resultWs['errorCode'] . ' : ' . $resultWs['errorMessage']]);
            } else {
                LpcLogger::error($resultWs['errorCode'] . ' : ' . $resultWs['errorMessage']);

                return $this->ajaxDispatcher->makeError(['message' => __('Error', 'wc_colissimo')]);
            }
        }
    }

    public function getPickupWS($address, $optionInter = null) {
        require_once LPC_INCLUDES . 'pick_up' . DS . 'lpc_generate_relays_payload.php';
        require_once LPC_INCLUDES . 'pick_up' . DS . 'lpc_relays_api.php';

        try {
            $generateRelaysPaypload = new LpcGenerateRelaysPayload();
            $relaysApi              = new LpcRelaysApi();

            $generateRelaysPaypload
                ->withCredentials()
                ->withAddress($address)
                ->withShippingDate()
                ->withOptionInter($optionInter)
                ->checkConsistency();

            $relaysPayload = $generateRelaysPaypload->assemble();

            return $relaysApi->getRelays($relaysPayload);
        } catch (Exception $exception) {
            return $this->ajaxDispatcher->makeAndLogError(['message' => $exception->getMessage()]);
        }
    }

    public function getDefaultPickupLocationInfoWS($address, $optionInter = null) {
        $resultWs = $this->getPickupWS($address, $optionInter);
        if (!empty($resultWs) && '0' == $resultWs['errorCode']) {
            $relays = $resultWs['listePointRetraitAcheminement'];
            if (count($relays) >= 1) {
                $defaultRelay = array_shift($relays);
                $this->lpcPickUpSelection->setCurrentPickUpLocationInfo($defaultRelay);

                return $defaultRelay;
            }
        }

        return null;
    }
}
